<?php
/**
 * Real API Integration Test: Tool Calling + Streaming (Data Stream Protocol)
 *
 * Tests that tool calling works correctly with streaming output,
 * including proper SSE event ordering for @ai-sdk/react compatibility.
 *
 * Run: php temp/test-tool-streaming.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

use function AISdkPhp\OpenAI\createOpenAI;
use function BengalStudio\AI\streamText;
use function BengalStudio\AI\generateText;
use function BengalStudio\AI\tool;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);
$passed = 0;
$failed = 0;

/**
 * Helper: Parse SSE output into decoded events.
 */
function parseSSE(string $output): array {
    $events = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'data: ')) {
            $data = substr($line, 6);
            if ($data === '[DONE]') {
                $events[] = ['type' => '[DONE]'];
                continue;
            }
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                $events[] = $decoded;
            }
        }
    }
    return $events;
}

/**
 * Helper: Assert condition.
 */
function assert_test(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ {$message}\n";
        $passed++;
    } else {
        echo "  ❌ FAILED: {$message}\n";
        $failed++;
    }
}

// Define a weather tool that validates it receives correct args
$receivedArgs = [];
$weatherTool = tool([
    'description' => 'Get the current weather for a city',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'The city name'],
        ],
        'required' => ['city'],
        'additionalProperties' => false,
    ],
    'execute' => function (array $args) use (&$receivedArgs): string {
        $receivedArgs[] = $args;
        $city = $args['city'] ?? 'Unknown';
        return json_encode([
            'city' => $city,
            'temperature' => '72°F',
            'condition' => 'sunny',
        ]);
    },
]);

// =====================================================
// Test 1: Tool args are correctly passed (Chat API)
// =====================================================
echo "\n=== Test 1: Tool args correctness (Chat API generateText) ===\n\n";

try {
    $receivedArgs = [];

    $result = generateText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the weather in San Francisco?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    assert_test(!empty($receivedArgs), 'Tool was called');
    assert_test(
        isset($receivedArgs[0]['city']) && is_string($receivedArgs[0]['city']),
        'Tool received city arg: ' . ($receivedArgs[0]['city'] ?? '(missing)')
    );
    assert_test(!empty($result->getText()), 'Final text is non-empty');
    echo "\n";
} catch (\Throwable $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    $failed++;
}

// =====================================================
// Test 2: Tool args are correctly passed (Chat API streamText)
// =====================================================
echo "=== Test 2: Tool args correctness (Chat API streamText) ===\n\n";

try {
    $receivedArgs = [];

    $result = streamText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the weather in London?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    $text = $result->getText(); // Consume the stream

    assert_test(!empty($receivedArgs), 'Tool was called during streaming');
    assert_test(
        isset($receivedArgs[0]['city']) && is_string($receivedArgs[0]['city']),
        'Tool received city arg: ' . ($receivedArgs[0]['city'] ?? '(missing)')
    );
    assert_test(!empty($text), "Final text is non-empty: " . substr($text, 0, 80));
    echo "\n";
} catch (\Throwable $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    $failed++;
}

// =====================================================
// Test 3: SSE output format with tool calls (Chat API)
// =====================================================
echo "=== Test 3: SSE Data Stream Protocol with tools (Chat API) ===\n\n";

try {
    $receivedArgs = [];

    $result = streamText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the weather in Berlin?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    // Capture SSE output to a temp file
    $outputFile = tmpfile();
    $result->toUIMessageStreamResponse(output: $outputFile);

    fseek($outputFile, 0);
    $sseOutput = stream_get_contents($outputFile);
    fclose($outputFile);

    $events = parseSSE($sseOutput);
    $types = array_column($events, 'type');

    // Debug: show all event types
    echo "  Events: " . implode(' → ', $types) . "\n\n";

    // Basic structure
    assert_test($types[0] === 'start', 'First event is "start"');
    assert_test(in_array('[DONE]', $types), 'Stream ends with [DONE]');
    assert_test(in_array('finish', $types), 'Has "finish" event');

    // Tool events
    assert_test(in_array('tool-input-start', $types), 'Has tool-input-start');
    assert_test(in_array('tool-input-available', $types), 'Has tool-input-available');
    assert_test(in_array('tool-output-available', $types), 'Has tool-output-available');

    // Verify tool-input-available has correct args
    $toolAvailable = array_values(array_filter($events, fn($e) => $e['type'] === 'tool-input-available'));
    if (!empty($toolAvailable)) {
        $input = $toolAvailable[0]['input'] ?? null;
        assert_test(
            is_array($input) && isset($input['city']),
            'tool-input-available has decoded args: ' . json_encode($input)
        );
    }

    // Verify tool-output-available has result
    $toolOutput = array_values(array_filter($events, fn($e) => $e['type'] === 'tool-output-available'));
    if (!empty($toolOutput)) {
        assert_test(
            !empty($toolOutput[0]['output']),
            'tool-output-available has output'
        );
    }

    // Step ordering: tool-output-available BEFORE finish-step (same step)
    $toolOutputIndex = array_search('tool-output-available', $types);
    $firstFinishStepIndex = array_search('finish-step', $types);
    assert_test(
        $toolOutputIndex !== false && $firstFinishStepIndex !== false && $toolOutputIndex < $firstFinishStepIndex,
        'tool-output-available comes BEFORE finish-step'
    );

    // Text response (step 2)
    assert_test(in_array('text-delta', $types), 'Has text-delta events');
    assert_test(in_array('text-start', $types), 'Has text-start event');
    assert_test(in_array('text-end', $types), 'Has text-end event');

    // Tool input streaming deltas
    $hasDeltas = in_array('tool-input-delta', $types);
    echo "  " . ($hasDeltas ? '✅' : 'ℹ️') . " Has tool-input-delta events: " . ($hasDeltas ? 'yes' : 'no (small args)') . "\n";

    echo "\n";
} catch (\Throwable $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  " . $e->getTraceAsString() . "\n\n";
    $failed++;
}

// =====================================================
// Test 4: SSE output format with tool calls (Responses API)
// =====================================================
echo "=== Test 4: SSE Data Stream Protocol with tools (Responses API) ===\n\n";

try {
    $receivedArgs = [];

    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is the weather in Tokyo?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    // Capture SSE output
    $outputFile = tmpfile();
    $result->toUIMessageStreamResponse(output: $outputFile);

    fseek($outputFile, 0);
    $sseOutput = stream_get_contents($outputFile);
    fclose($outputFile);

    $events = parseSSE($sseOutput);
    $types = array_column($events, 'type');

    echo "  Events: " . implode(' → ', $types) . "\n\n";

    // Basic structure
    assert_test($types[0] === 'start', 'First event is "start"');
    assert_test(in_array('finish', $types), 'Has "finish" event');

    // Tool events
    assert_test(in_array('tool-input-start', $types), 'Has tool-input-start');
    assert_test(in_array('tool-input-available', $types), 'Has tool-input-available');
    assert_test(in_array('tool-output-available', $types), 'Has tool-output-available');

    // Verify tool-input-available has correct args
    $toolAvailable = array_values(array_filter($events, fn($e) => $e['type'] === 'tool-input-available'));
    if (!empty($toolAvailable)) {
        $input = $toolAvailable[0]['input'] ?? null;
        assert_test(
            is_array($input) && isset($input['city']),
            'tool-input-available has decoded args: ' . json_encode($input)
        );
    }

    // Step ordering
    $toolOutputIndex = array_search('tool-output-available', $types);
    $firstFinishStepIndex = array_search('finish-step', $types);
    assert_test(
        $toolOutputIndex !== false && $firstFinishStepIndex !== false && $toolOutputIndex < $firstFinishStepIndex,
        'tool-output-available comes BEFORE finish-step'
    );

    // Text response
    assert_test(in_array('text-delta', $types), 'Has text-delta events');

    // Tool received correct args
    assert_test(!empty($receivedArgs), 'Tool was called');
    assert_test(
        isset($receivedArgs[0]['city']),
        'Tool received city arg: ' . ($receivedArgs[0]['city'] ?? '(missing)')
    );

    echo "\n";
} catch (\Throwable $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  " . $e->getTraceAsString() . "\n\n";
    $failed++;
}

// =====================================================
// Test 5: Tool args correctness (Responses API generateText)
// =====================================================
echo "=== Test 5: Tool args correctness (Responses API generateText) ===\n\n";

try {
    $receivedArgs = [];

    $result = generateText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is the weather in Paris?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    assert_test(!empty($receivedArgs), 'Tool was called');
    assert_test(
        isset($receivedArgs[0]['city']) && is_string($receivedArgs[0]['city']),
        'Tool received city arg: ' . ($receivedArgs[0]['city'] ?? '(missing)')
    );
    assert_test(!empty($result->getText()), 'Final text is non-empty');
    echo "\n";
} catch (\Throwable $e) {
    echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    $failed++;
}

// =====================================================
// Summary
// =====================================================
echo "================================================\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "================================================\n";

exit($failed > 0 ? 1 : 0);

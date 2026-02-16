<?php
/**
 * Real API Integration Test: Tool Calling
 *
 * Tests tool/function calling with generateText() and streamText().
 * Run: php temp/test-tool-calling.php
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
use function BengalStudio\AI\generateText;
use function BengalStudio\AI\streamText;
use function BengalStudio\AI\tool;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);

// Define a weather tool
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
    'execute' => function (array $args): string {
        $city = $args['city'] ?? 'Unknown';
        // Simulate weather lookup
        return json_encode([
            'city' => $city,
            'temperature' => '72°F',
            'condition' => 'sunny',
        ]);
    },
]);

// =====================================================
// Test 1: generateText() with tool call (Responses API)
// =====================================================
echo "=== Test 1: generateText() with tool call (Responses API) ===\n\n";

try {
    $result = generateText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is the weather in Tokyo?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    echo "Final text: " . $result->getText() . "\n";
    echo "Steps taken: " . $result->getStepCount() . "\n";
    echo "Has tool calls: " . ($result->hasToolCalls() ? 'yes' : 'no') . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";

    if ($result->getStepCount() > 0) {
        echo "\nStep details:\n";
        foreach ($result->getSteps() as $i => $step) {
            echo "  Step {$i}: finish=" . $step->finishReason->value;
            echo " text=" . (strlen($step->text) > 50 ? substr($step->text, 0, 50) . '...' : $step->text);
            echo " toolCalls=" . count($step->toolCalls);
            echo " toolResults=" . count($step->toolResults) . "\n";
        }
    }

    if (!empty($result->getText())) {
        echo "\n✅ Responses API tool calling PASSED\n\n";
    } else {
        echo "\n❌ Responses API tool calling FAILED — empty text\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Responses API tool calling FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 2: generateText() with tool call (Chat API)
// =====================================================
echo "=== Test 2: generateText() with tool call (Chat API) ===\n\n";

try {
    $result = generateText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the weather in Paris?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    echo "Final text: " . $result->getText() . "\n";
    echo "Steps taken: " . $result->getStepCount() . "\n";
    echo "Has tool calls: " . ($result->hasToolCalls() ? 'yes' : 'no') . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";

    if (!empty($result->getText())) {
        echo "\n✅ Chat API tool calling PASSED\n\n";
    } else {
        echo "\n❌ Chat API tool calling FAILED — empty text\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Chat API tool calling FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 3: streamText() with tool call (Responses API)
// =====================================================
echo "=== Test 3: streamText() with tool call (Responses API) ===\n\n";

try {
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is the weather in New York?',
        'tools' => ['getWeather' => $weatherTool],
        'maxSteps' => 3,
        'maxOutputTokens' => 200,
    ]);

    echo "Full stream events:\n";
    $eventTypes = [];
    $text = '';
    foreach ($result->getFullStream() as $chunk) {
        $type = $chunk['type'] ?? 'unknown';
        $eventTypes[] = $type;

        if ($type === 'text-delta') {
            $text .= $chunk['textDelta'] ?? '';
        } elseif ($type === 'tool-call') {
            echo "  tool-call: " . ($chunk['toolName'] ?? 'unknown') . "\n";
        } elseif ($type === 'tool-result') {
            echo "  tool-result\n";
        } elseif ($type === 'step-finish') {
            echo "  step-finish\n";
        }
    }

    echo "Final text: {$text}\n";
    echo "Event types: " . implode(', ', array_unique($eventTypes)) . "\n";

    if (!empty($text)) {
        echo "\n✅ streamText() with tool call PASSED\n\n";
    } else {
        echo "\n❌ streamText() with tool call FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ streamText() with tool call FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== All tool calling tests completed ===\n";

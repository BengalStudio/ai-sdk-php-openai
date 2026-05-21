<?php
/**
 * Real API Integration Test: streamText()
 *
 * Tests streaming text generation with Responses API and Chat Completions API.
 * This is the PRIMARY focus test.
 * Run: php temp/test-stream-text.php
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

use function BengalStudio\AI\OpenAI\createOpenAI;
use function BengalStudio\AI\streamText;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);

// =====================================================
// Test 1: streamText() with Responses API — getTextStream()
// =====================================================
echo "=== Test 1: streamText() Responses API — getTextStream() ===\n\n";

try {
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'Count from 1 to 5, each number on a new line.',
        'maxOutputTokens' => 100,
    ]);

    echo "Streaming text deltas:\n";
    $chunkCount = 0;
    $fullText = '';
    foreach ($result->getTextStream() as $delta) {
        echo $delta;
        $fullText .= $delta;
        $chunkCount++;
    }

    echo "\n\n--- Stream stats ---\n";
    echo "Chunks received: {$chunkCount}\n";
    echo "Full text length: " . strlen($fullText) . " chars\n";
    echo "Text is non-empty: " . (!empty(trim($fullText)) ? 'yes' : 'NO — BUG!') . "\n";

    if ($chunkCount > 1 && !empty(trim($fullText))) {
        echo "\n✅ Responses API streamText() getTextStream() PASSED\n\n";
    } else {
        echo "\n❌ Responses API streamText() getTextStream() FAILED — ";
        echo "chunks={$chunkCount}, text=" . var_export(trim($fullText), true) . "\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Responses API streamText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 2: streamText() with Responses API — getText() (consume full)
// =====================================================
echo "=== Test 2: streamText() Responses API — getText() ===\n\n";

try {
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is PHP? One sentence only.',
        'maxOutputTokens' => 100,
    ]);

    $text = $result->getText();
    echo "Full text: {$text}\n";
    echo "Text length: " . strlen($text) . " chars\n";

    if (!empty(trim($text))) {
        echo "\n✅ Responses API getText() PASSED\n\n";
    } else {
        echo "\n❌ Responses API getText() FAILED — empty text\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Responses API getText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 3: streamText() with Responses API — getFullStream()
// =====================================================
echo "=== Test 3: streamText() Responses API — getFullStream() ===\n\n";

try {
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'Say "hello world".',
        'maxOutputTokens' => 50,
    ]);

    echo "Full stream events:\n";
    $eventTypes = [];
    foreach ($result->getFullStream() as $chunk) {
        $type = $chunk['type'] ?? 'unknown';
        $eventTypes[] = $type;

        if ($type === 'text-delta') {
            echo "  text-delta: " . json_encode($chunk['textDelta'] ?? '(missing)') . "\n";
        } elseif ($type === 'step-finish') {
            echo "  step-finish: finishReason=" . ($chunk['finishReason'] ?? 'n/a') . "\n";
        } elseif ($type === 'finish') {
            echo "  finish: finishReason=" . ($chunk['finishReason'] ?? 'n/a') . "\n";
            if (isset($chunk['usage'])) {
                echo "  usage: input=" . $chunk['usage']->inputTokens . " output=" . $chunk['usage']->outputTokens . "\n";
            }
        } else {
            echo "  {$type}\n";
        }
    }

    echo "\nEvent types seen: " . implode(', ', array_unique($eventTypes)) . "\n";

    $hasTextDelta = in_array('text-delta', $eventTypes);
    $hasFinish = in_array('finish', $eventTypes);

    if ($hasTextDelta && $hasFinish) {
        echo "\n✅ Responses API getFullStream() PASSED\n\n";
    } else {
        echo "\n❌ Responses API getFullStream() FAILED — missing events\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Responses API getFullStream() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 4: streamText() with Chat Completions API
// =====================================================
echo "=== Test 4: streamText() Chat API — getTextStream() ===\n\n";

try {
    $result = streamText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'Count from 1 to 5, each number on a new line.',
        'maxOutputTokens' => 100,
    ]);

    echo "Streaming text deltas:\n";
    $chunkCount = 0;
    $fullText = '';
    foreach ($result->getTextStream() as $delta) {
        echo $delta;
        $fullText .= $delta;
        $chunkCount++;
    }

    echo "\n\n--- Stream stats ---\n";
    echo "Chunks received: {$chunkCount}\n";
    echo "Full text length: " . strlen($fullText) . " chars\n";
    echo "Text is non-empty: " . (!empty(trim($fullText)) ? 'yes' : 'NO — BUG!') . "\n";

    if ($chunkCount > 1 && !empty(trim($fullText))) {
        echo "\n✅ Chat API streamText() getTextStream() PASSED\n\n";
    } else {
        echo "\n❌ Chat API streamText() getTextStream() FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Chat API streamText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 5: streamText() with Chat API — getText()
// =====================================================
echo "=== Test 5: streamText() Chat API — getText() ===\n\n";

try {
    $result = streamText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the speed of light? One sentence.',
        'maxOutputTokens' => 100,
    ]);

    $text = $result->getText();
    echo "Full text: {$text}\n";

    if (!empty(trim($text))) {
        echo "\n✅ Chat API getText() PASSED\n\n";
    } else {
        echo "\n❌ Chat API getText() FAILED — empty text\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Chat API getText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 6: streamText() with system prompt
// =====================================================
echo "=== Test 6: streamText() with system prompt ===\n\n";

try {
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'system' => 'You are a helpful assistant that always responds in exactly 3 words.',
        'prompt' => 'Greet me.',
        'maxOutputTokens' => 50,
    ]);

    $text = $result->getText();
    echo "Text: {$text}\n";

    if (!empty(trim($text))) {
        echo "\n✅ streamText() with system prompt PASSED\n\n";
    } else {
        echo "\n❌ streamText() with system prompt FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ streamText() with system prompt FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 7: streamText() with onChunk callback
// =====================================================
echo "=== Test 7: streamText() with onChunk callback ===\n\n";

try {
    $chunkLog = [];
    $result = streamText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'Say "hi".',
        'maxOutputTokens' => 20,
        'onChunk' => function ($chunk) use (&$chunkLog) {
            $chunkLog[] = $chunk['type'] ?? 'unknown';
        },
    ]);

    // Must consume stream to trigger onChunk callbacks
    $text = $result->getText();
    echo "Text: {$text}\n";
    echo "onChunk events: " . implode(', ', $chunkLog) . "\n";

    if (!empty($chunkLog)) {
        echo "\n✅ onChunk callback PASSED\n\n";
    } else {
        echo "\n❌ onChunk callback FAILED — no events\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ onChunk callback FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== All streamText() tests completed ===\n";

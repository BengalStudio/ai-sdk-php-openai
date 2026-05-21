<?php
/**
 * Real API Integration Test: generateText()
 *
 * Tests basic text generation using both the Responses API and Chat Completions API.
 * Run: php temp/test-generate-text.php
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
use function BengalStudio\AI\generateText;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);

echo "=== Test 1: generateText() with Responses API (gpt-4o-mini) ===\n\n";

try {
    $result = generateText([
        'model' => $openai->responses('gpt-4o-mini'),
        'prompt' => 'What is 2 + 2? Answer with just the number.',
        'maxOutputTokens' => 50,
    ]);

    echo "Text: " . $result->getText() . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";
    echo "Input tokens: " . $result->usage->inputTokens . "\n";
    echo "Output tokens: " . $result->usage->outputTokens . "\n";
    echo "Steps: " . $result->getStepCount() . "\n";
    echo "Has tool calls: " . ($result->hasToolCalls() ? 'yes' : 'no') . "\n";
    echo "\n✅ Responses API generateText() PASSED\n\n";
} catch (\Throwable $e) {
    echo "❌ Responses API generateText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== Test 2: generateText() with Chat Completions API (gpt-4o-mini) ===\n\n";

try {
    $result = generateText([
        'model' => $openai->chat('gpt-4o-mini'),
        'prompt' => 'What is the capital of France? Answer in one word.',
        'maxOutputTokens' => 50,
    ]);

    echo "Text: " . $result->getText() . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";
    echo "Input tokens: " . $result->usage->inputTokens . "\n";
    echo "Output tokens: " . $result->usage->outputTokens . "\n";
    echo "Steps: " . $result->getStepCount() . "\n";
    echo "\n✅ Chat API generateText() PASSED\n\n";
} catch (\Throwable $e) {
    echo "❌ Chat API generateText() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== Test 3: generateText() with system prompt ===\n\n";

try {
    $result = generateText([
        'model' => $openai->responses('gpt-4o-mini'),
        'system' => 'You are a pirate. Always respond in pirate speak.',
        'prompt' => 'Say hello.',
        'maxOutputTokens' => 100,
    ]);

    echo "Text: " . $result->getText() . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";
    echo "\n✅ System prompt test PASSED\n\n";
} catch (\Throwable $e) {
    echo "❌ System prompt test FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== All generateText() tests completed ===\n";

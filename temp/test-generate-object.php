<?php
/**
 * Real API Integration Test: generateObject() & streamObject()
 *
 * Tests structured output generation with JSON Schema.
 * Run: php temp/test-generate-object.php
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
use function BengalStudio\AI\generateObject;
use function BengalStudio\AI\streamObject;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);

$personSchema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string', 'description' => 'The person\'s full name'],
        'age' => ['type' => 'integer', 'description' => 'The person\'s age'],
        'occupation' => ['type' => 'string', 'description' => 'The person\'s job'],
    ],
    'required' => ['name', 'age', 'occupation'],
    'additionalProperties' => false,
];

// =====================================================
// Test 1: generateObject() with JSON mode (Responses API)
// =====================================================
echo "=== Test 1: generateObject() JSON mode (Responses API) ===\n\n";

try {
    $result = generateObject([
        'model' => $openai->responses('gpt-4o-mini'),
        'schema' => $personSchema,
        'prompt' => 'Generate a fictional person with name, age, and occupation.',
        'maxOutputTokens' => 200,
    ]);

    $obj = $result->getObject();
    echo "Object: " . json_encode($obj, JSON_PRETTY_PRINT) . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";
    echo "Has name: " . (isset($obj['name']) ? 'yes' : 'no') . "\n";
    echo "Has age: " . (isset($obj['age']) ? 'yes' : 'no') . "\n";
    echo "Has occupation: " . (isset($obj['occupation']) ? 'yes' : 'no') . "\n";

    if (isset($obj['name']) && isset($obj['age']) && isset($obj['occupation'])) {
        echo "\n✅ generateObject() JSON mode PASSED\n\n";
    } else {
        echo "\n❌ generateObject() JSON mode FAILED — missing fields\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ generateObject() JSON mode FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 2: generateObject() with tool mode (Chat API)
// =====================================================
echo "=== Test 2: generateObject() tool mode (Chat API) ===\n\n";

try {
    $result = generateObject([
        'model' => $openai->chat('gpt-4o-mini'),
        'schema' => $personSchema,
        'schemaName' => 'person',
        'schemaDescription' => 'A person with name, age, and occupation',
        'mode' => 'tool',
        'prompt' => 'Generate a fictional scientist.',
        'maxOutputTokens' => 200,
    ]);

    $obj = $result->getObject();
    echo "Object: " . json_encode($obj, JSON_PRETTY_PRINT) . "\n";
    echo "Finish reason: " . $result->finishReason->value . "\n";

    if (isset($obj['name']) && isset($obj['age']) && isset($obj['occupation'])) {
        echo "\n✅ generateObject() tool mode PASSED\n\n";
    } else {
        echo "\n❌ generateObject() tool mode FAILED — missing fields\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ generateObject() tool mode FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 3: generateObject() dot-notation access
// =====================================================
echo "=== Test 3: generateObject() dot-notation access ===\n\n";

try {
    $result = generateObject([
        'model' => $openai->responses('gpt-4o-mini'),
        'schema' => [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'email'],
                    'additionalProperties' => false,
                ],
                'score' => ['type' => 'integer'],
            ],
            'required' => ['user', 'score'],
            'additionalProperties' => false,
        ],
        'prompt' => 'Generate a user profile with a name, email, and a score between 1-100.',
        'maxOutputTokens' => 200,
    ]);

    $name = $result->get('user.name');
    $email = $result->get('user.email');
    $score = $result->get('score');
    echo "user.name: {$name}\n";
    echo "user.email: {$email}\n";
    echo "score: {$score}\n";

    if ($name && $email && $score !== null) {
        echo "\n✅ Dot-notation access PASSED\n\n";
    } else {
        echo "\n❌ Dot-notation access FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Dot-notation access FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 4: streamObject() with Responses API
// =====================================================
echo "=== Test 4: streamObject() Responses API ===\n\n";

try {
    $result = streamObject([
        'model' => $openai->responses('gpt-4o-mini'),
        'schema' => $personSchema,
        'prompt' => 'Generate a fictional artist.',
        'maxOutputTokens' => 200,
    ]);

    echo "Partial objects as they stream:\n";
    $partialCount = 0;
    foreach ($result->getPartialObjectStream() as $partial) {
        $partialCount++;
        echo "  Partial #{$partialCount}: " . json_encode($partial) . "\n";
    }

    // getObject() won't work after consuming stream, so use last partial
    echo "\nTotal partials: {$partialCount}\n";

    if ($partialCount > 0) {
        echo "\n✅ streamObject() PASSED\n\n";
    } else {
        echo "\n❌ streamObject() FAILED — no partials\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ streamObject() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 5: streamObject() getObject() (consume full)
// =====================================================
echo "=== Test 5: streamObject() getObject() ===\n\n";

try {
    $result = streamObject([
        'model' => $openai->responses('gpt-4o-mini'),
        'schema' => $personSchema,
        'prompt' => 'Generate a fictional musician.',
        'maxOutputTokens' => 200,
    ]);

    $obj = $result->getObject();
    echo "Final object: " . json_encode($obj, JSON_PRETTY_PRINT) . "\n";

    if (isset($obj['name'])) {
        echo "\n✅ streamObject() getObject() PASSED\n\n";
    } else {
        echo "\n❌ streamObject() getObject() FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ streamObject() getObject() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== All generateObject/streamObject tests completed ===\n";

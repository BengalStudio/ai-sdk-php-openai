<?php
/**
 * Real API Integration Test: Embeddings
 *
 * Tests embed() and embedMany() with cosine similarity.
 * Run: php temp/test-embeddings.php
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
use function BengalStudio\AI\embed;
use function BengalStudio\AI\embedMany;
use function BengalStudio\AI\cosineSimilarity;

$apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "ERROR: OPENAI_API_KEY not found in .env\n";
    exit(1);
}

$openai = createOpenAI(['apiKey' => $apiKey]);

// =====================================================
// Test 1: embed() single value
// =====================================================
echo "=== Test 1: embed() single value ===\n\n";

try {
    $result = embed([
        'model' => $openai->embedding('text-embedding-3-small'),
        'value' => 'The quick brown fox jumps over the lazy dog.',
    ]);

    $embedding = $result->getEmbedding();
    echo "Embedding dimensions: " . $result->getDimensions() . "\n";
    echo "First 5 values: " . implode(', ', array_map(fn($v) => round($v, 6), array_slice($embedding, 0, 5))) . "\n";
    echo "Token usage: " . $result->usage->tokens . "\n";

    if ($result->getDimensions() > 0 && $result->usage->tokens > 0) {
        echo "\n✅ embed() PASSED\n\n";
    } else {
        echo "\n❌ embed() FAILED\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ embed() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 2: embed() with custom dimensions
// =====================================================
echo "=== Test 2: embed() with custom dimensions ===\n\n";

try {
    $result = embed([
        'model' => $openai->embedding('text-embedding-3-small'),
        'value' => 'Hello world',
        'providerOptions' => [
            'openai' => ['dimensions' => 256],
        ],
    ]);

    echo "Embedding dimensions: " . $result->getDimensions() . "\n";

    if ($result->getDimensions() === 256) {
        echo "\n✅ embed() with dimensions PASSED\n\n";
    } else {
        echo "\n❌ embed() with dimensions FAILED — expected 256, got " . $result->getDimensions() . "\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ embed() with dimensions FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 3: embedMany() multiple values
// =====================================================
echo "=== Test 3: embedMany() multiple values ===\n\n";

try {
    $result = embedMany([
        'model' => $openai->embedding('text-embedding-3-small'),
        'values' => [
            'I love programming in PHP.',
            'JavaScript is also great.',
            'The weather is nice today.',
        ],
    ]);

    $embeddings = $result->getEmbeddings();
    echo "Number of embeddings: " . count($embeddings) . "\n";
    foreach ($embeddings as $i => $emb) {
        echo "  Embedding {$i}: " . count($emb) . " dimensions\n";
    }
    echo "Total tokens: " . $result->usage->tokens . "\n";

    if (count($embeddings) === 3) {
        echo "\n✅ embedMany() PASSED\n\n";
    } else {
        echo "\n❌ embedMany() FAILED — expected 3 embeddings\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ embedMany() FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

// =====================================================
// Test 4: Cosine similarity between embeddings
// =====================================================
echo "=== Test 4: Cosine similarity ===\n\n";

try {
    $embA = embed([
        'model' => $openai->embedding('text-embedding-3-small'),
        'value' => 'I love cats.',
    ]);

    $embB = embed([
        'model' => $openai->embedding('text-embedding-3-small'),
        'value' => 'I love kittens.',
    ]);

    $embC = embed([
        'model' => $openai->embedding('text-embedding-3-small'),
        'value' => 'The stock market crashed today.',
    ]);

    $simAB = cosineSimilarity($embA->embedding, $embB->embedding);
    $simAC = cosineSimilarity($embA->embedding, $embC->embedding);
    $simBC = cosineSimilarity($embB->embedding, $embC->embedding);

    echo "Similarity (cats vs kittens): " . round($simAB, 4) . "\n";
    echo "Similarity (cats vs stock market): " . round($simAC, 4) . "\n";
    echo "Similarity (kittens vs stock market): " . round($simBC, 4) . "\n";

    if ($simAB > $simAC && $simAB > $simBC) {
        echo "\n✅ Cosine similarity PASSED — similar texts are closer\n\n";
    } else {
        echo "\n❌ Cosine similarity FAILED — unexpected ranking\n\n";
    }
} catch (\Throwable $e) {
    echo "❌ Cosine similarity FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n\n";
}

echo "=== All embedding tests completed ===\n";

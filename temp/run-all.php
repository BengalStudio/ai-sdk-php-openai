<?php
/**
 * Real API Integration Test: Run All Tests
 *
 * Runs all integration test files in sequence.
 * Run: php temp/run-all.php
 */

echo "╔══════════════════════════════════════════════════╗\n";
echo "║     AI SDK PHP — Real API Integration Tests     ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  These tests make REAL API calls to OpenAI.     ║\n";
echo "║  They will consume tokens from your account.    ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

$tests = [
    'test-generate-text.php' => 'Generate Text (Responses + Chat APIs)',
    'test-stream-text.php' => 'Stream Text (PRIMARY FOCUS)',
    'test-generate-object.php' => 'Generate/Stream Object (Structured Output)',
    'test-tool-calling.php' => 'Tool Calling (generate + stream)',
    'test-embeddings.php' => 'Embeddings (embed, embedMany, cosine similarity)',
];

$passed = 0;
$failed = 0;

foreach ($tests as $file => $description) {
    echo "\n" . str_repeat('═', 60) . "\n";
    echo "▶ Running: {$description}\n";
    echo "  File: {$file}\n";
    echo str_repeat('═', 60) . "\n\n";

    $output = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1', $output, $exitCode);

    echo implode("\n", $output) . "\n";

    if ($exitCode !== 0) {
        echo "\n⚠️  Script exited with code {$exitCode}\n";
        $failed++;
    } else {
        $passed++;
    }
}

echo "\n" . str_repeat('═', 60) . "\n";
echo "SUMMARY: {$passed} test files completed, {$failed} had errors\n";
echo str_repeat('═', 60) . "\n";

<?php
/**
 * Debug: Test Responses API with responseFormat directly
 */
require_once __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    putenv(trim($line));
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

use BengalStudio\AI\Types\LanguageModelCallOptions;
use BengalStudio\AI\Types\Message;

$openai = AISdkPhp\OpenAI\createOpenAI(['apiKey' => $_ENV['OPENAI_API_KEY']]);
$model = $openai->responses('gpt-4o-mini');

$schema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
    ],
    'required' => ['name', 'age'],
    'additionalProperties' => false,
];

try {
    $options = new LanguageModelCallOptions(
        prompt: [
            Message::system('Respond with a JSON object matching the schema: ' . json_encode($schema)),
            Message::user('Generate a fictional person with name and age.'),
        ],
        maxOutputTokens: 200,
        responseFormat: ['type' => 'json', 'schema' => $schema],
    );
    $result = $model->doGenerate($options);
    echo "SUCCESS!\n";
    echo "Text: " . $result->getText() . "\n";
} catch (\Throwable $e) {
    echo "Error class: " . get_class($e) . "\n";
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous: " . get_class($e->getPrevious()) . " — " . $e->getPrevious()->getMessage() . "\n";
        if ($e->getPrevious()->getPrevious()) {
            echo "Root: " . get_class($e->getPrevious()->getPrevious()) . " — " . $e->getPrevious()->getPrevious()->getMessage() . "\n";
        }
    }
}

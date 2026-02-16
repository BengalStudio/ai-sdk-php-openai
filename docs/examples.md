# Examples — OpenAI Provider

Practical examples for common use cases with the OpenAI provider.

## Basic Text Generation

```php
use function AISdkPhp\OpenAI\openai;
use function BengalStudio\AI\generateText;

$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Explain quantum computing in one paragraph.',
]);

echo $result->text;
echo "Tokens used: " . $result->usage->total();
```

## Conversation with System Prompt

```php
use BengalStudio\AI\Types\Message;

$result = generateText([
    'model' => openai('gpt-4.1'),
    'system' => 'You are a helpful coding assistant. Always include code examples.',
    'messages' => [
        Message::user('How do I read a file in PHP?'),
    ],
]);
```

## Streaming

```php
use function BengalStudio\AI\streamText;

$result = streamText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Write a haiku about programming.',
]);

foreach ($result->getTextStream() as $chunk) {
    echo $chunk;
    flush();
}
```

## Tool Calling (Agentic)

```php
use function BengalStudio\AI\tool;

$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'What is the weather in Tokyo and convert it to Celsius?',
    'tools' => [
        'weather' => tool([
            'description' => 'Get weather for a city',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => ['type' => 'string'],
                ],
                'required' => ['city'],
            ],
            'execute' => fn(array $args) => "75°F and humid in {$args['city']}",
        ]),
        'convert_temp' => tool([
            'description' => 'Convert temperature',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fahrenheit' => ['type' => 'number'],
                ],
                'required' => ['fahrenheit'],
            ],
            'execute' => fn(array $args) => round(($args['fahrenheit'] - 32) * 5 / 9, 1) . '°C',
        ]),
    ],
    'maxSteps' => 5,
]);

echo $result->text;
```

## Structured Output

```php
use function BengalStudio\AI\generateObject;

$result = generateObject([
    'model' => openai('chat:gpt-4.1'),
    'schema' => [
        'type' => 'object',
        'properties' => [
            'sentiment' => ['type' => 'string', 'enum' => ['positive', 'negative', 'neutral']],
            'confidence' => ['type' => 'number'],
            'summary' => ['type' => 'string'],
        ],
        'required' => ['sentiment', 'confidence', 'summary'],
    ],
    'prompt' => 'Analyze the sentiment: "I absolutely love this new PHP library!"',
]);

echo $result->object['sentiment'];    // "positive"
echo $result->object['confidence'];   // 0.95
echo $result->get('summary');
```

## Web Search (Responses API)

```php
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'What are the latest PHP releases?',
    'providerOptions' => [
        'openai' => [
            'webSearch' => [
                'searchContextSize' => 'medium',
            ],
        ],
    ],
]);

echo $result->text;  // Includes up-to-date information from the web
```

## Reasoning

```php
$result = generateText([
    'model' => openai('o4-mini'),
    'prompt' => 'Solve step by step: If a train travels at 60mph for 2.5 hours, then at 80mph for 1.5 hours, what is the total distance?',
    'providerOptions' => [
        'openai' => [
            'reasoningEffort' => 'high',
        ],
    ],
]);

echo $result->text;
```

## Embeddings

```php
use function BengalStudio\AI\embed;
use function BengalStudio\AI\embedMany;
use function BengalStudio\AI\cosineSimilarity;
use function AISdkPhp\OpenAI\createOpenAI;

$provider = createOpenAI(['apiKey' => 'sk-...']);
$model = $provider->embedding('text-embedding-3-small');

// Single embedding
$result = embed(['model' => $model, 'value' => 'Hello world']);
echo "Dimensions: " . $result->getDimensions();  // 1536

// Batch embeddings with comparison
$batch = embedMany([
    'model' => $model,
    'values' => [
        'PHP is a server-side scripting language.',
        'PHP powers many web applications.',
        'The sun is a star in our solar system.',
    ],
]);

$phpPhp = cosineSimilarity($batch->embeddings[0], $batch->embeddings[1]);
$phpSun = cosineSimilarity($batch->embeddings[0], $batch->embeddings[2]);
echo "PHP-PHP similarity: $phpPhp\n";  // High (~0.85)
echo "PHP-Sun similarity: $phpSun\n";  // Low (~0.3)
```

## Reduced-Dimension Embeddings

```php
$result = embed([
    'model' => $provider->embedding('text-embedding-3-large'),
    'value' => 'Compact vector for storage',
    'providerOptions' => [
        'openai' => ['dimensions' => 256],
    ],
]);

echo $result->getDimensions();  // 256 (instead of default 3072)
```

## Custom Base URL (OpenAI-Compatible API)

```php
use function AISdkPhp\OpenAI\createOpenAI;

// Together AI
$together = createOpenAI([
    'baseURL' => 'https://api.together.xyz/v1',
    'apiKey' => 'your-together-key',
    'name' => 'together',
]);

$result = generateText([
    'model' => $together->chat('meta-llama/Llama-3-70b-chat-hf'),
    'prompt' => 'Hello!',
]);
```

## Server-Sent Events (SSE) Endpoint

```php
// In a WordPress REST API handler or similar
$result = streamText([
    'model' => openai('gpt-4.1'),
    'prompt' => $request->get_param('prompt'),
]);

$result->pipeTextStreamToResponse(format: 'sse');
```

## Provider Registry

```php
use function BengalStudio\AI\createProviderRegistry;
use function AISdkPhp\OpenAI\createOpenAI;

$registry = createProviderRegistry([
    'openai' => createOpenAI(['apiKey' => 'sk-...']),
    // 'anthropic' => createAnthropic([...]),
]);

// Resolve by string ID
$model = $registry->languageModel('openai:gpt-4.1');

$result = generateText([
    'model' => $model,
    'prompt' => 'Hello from the registry!',
]);
```

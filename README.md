# AI SDK PHP - OpenAI Provider

The **OpenAI provider** for the [AI SDK PHP](https://github.com/bengal-studio/ai-sdk-php) package. It provides language model and embedding model support for the OpenAI API, including:

- **Chat Completions** (`/v1/chat/completions`)
- **Responses** (`/v1/responses`) — default, most feature-rich
- **Completions** (`/v1/completions`) — legacy
- **Embeddings** (`/v1/embeddings`)

## Installation

```bash
composer require ai-sdk-php/openai
```

## Setup

The OpenAI provider is configured with your [OpenAI API key](https://platform.openai.com/account/api-keys). You can set it via environment variable or pass it programmatically.

### Environment Variable

```bash
OPENAI_API_KEY=sk-...
```

### Programmatic Configuration

```php
use function AISdkPhp\OpenAI\createOpenAI;

$openai = createOpenAI([
    'apiKey' => 'sk-...',
    'organization' => 'org-...',   // optional
    'project' => 'proj_...',       // optional
    'baseURL' => 'https://api.openai.com/v1', // optional, for proxies
]);
```

## Language Models

The OpenAI provider supports multiple API surfaces for text generation.

### Responses API (Default)

The [Responses API](https://platform.openai.com/docs/api-reference/responses) is the default and most capable API. It supports web search, file search, code interpreter, image generation, MCP tools, and more.

```php
use function AISdkPhp\OpenAI\openai;
use function BengalStudio\AI\generateText;

// Using the convenience function
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Explain quantum computing in simple terms.',
]);

echo $result->text;
```

#### Web Search

```php
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'What happened in the news today?',
    'providerOptions' => [
        'openai' => [
            'webSearch' => true,
            // Or with options:
            // 'webSearch' => [
            //     'searchContextSize' => 'medium',
            //     'userLocation' => [
            //         'type' => 'approximate',
            //         'city' => 'San Francisco',
            //         'region' => 'California',
            //         'country' => 'US',
            //     ],
            // ],
        ],
    ],
]);
```

#### File Search

```php
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'What are the key findings in the report?',
    'providerOptions' => [
        'openai' => [
            'fileSearch' => [
                'vectorStoreIds' => ['vs_...'],
                'maxNumResults' => 10,
            ],
        ],
    ],
]);
```

#### Code Interpreter

```php
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Calculate the first 20 Fibonacci numbers.',
    'providerOptions' => [
        'openai' => [
            'codeInterpreter' => true,
        ],
    ],
]);
```

#### Reasoning Models

```php
$result = generateText([
    'model' => openai('o4-mini'),
    'prompt' => 'Solve this step by step...',
    'providerOptions' => [
        'openai' => [
            'reasoningEffort' => 'high', // 'low', 'medium', 'high'
            'reasoningSummary' => 'auto', // Include reasoning summary
        ],
    ],
]);
```

#### Conversation Threading

```php
$result = generateText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Continue our conversation.',
    'providerOptions' => [
        'openai' => [
            'previousResponseId' => 'resp_abc123',
        ],
    ],
]);
```

### Chat Completions API

Use the `chat:` prefix or the `chat()` factory method for the Chat Completions API.

```php
use function AISdkPhp\OpenAI\openai;

// Using prefix
$model = openai('chat:gpt-4.1');

// Using factory method
$provider = openai();
$model = $provider->chat('gpt-4.1');

$result = generateText([
    'model' => $model,
    'prompt' => 'Hello, world!',
]);
```

#### Structured Output (JSON Mode)

```php
$result = generateObject([
    'model' => openai('chat:gpt-4.1'),
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
    ],
    'prompt' => 'Generate a random person.',
]);
```

#### Tool Use

```php
use BengalStudio\AI\Tool\Tool;

$result = generateText([
    'model' => openai('chat:gpt-4.1'),
    'tools' => [
        'weather' => new Tool(
            description: 'Get the weather for a location.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['location'],
            ],
            execute: fn(array $input) => ['temperature' => 72, 'condition' => 'sunny'],
        ),
    ],
    'prompt' => 'What is the weather in San Francisco?',
]);
```

#### Provider Options

```php
$result = generateText([
    'model' => openai('chat:gpt-4.1'),
    'prompt' => 'Hello!',
    'providerOptions' => [
        'openai' => [
            'reasoningEffort' => 'high',       // For o-series models
            'logitBias' => [1234 => -100],     // Token biases
            'logprobs' => 5,                    // Return logprobs
            'parallelToolCalls' => false,       // Disable parallel tool calls
            'user' => 'user-123',              // User identifier
            'serviceTier' => 'default',        // Service tier
            'store' => true,                   // Store for distillation
            'metadata' => ['key' => 'value'],  // Custom metadata
        ],
    ],
]);
```

### Completions API (Legacy)

For older models like `gpt-3.5-turbo-instruct`:

```php
$model = openai('completion:gpt-3.5-turbo-instruct');

// Or using factory
$provider = openai();
$model = $provider->completion('gpt-3.5-turbo-instruct');

$result = generateText([
    'model' => $model,
    'prompt' => 'Once upon a time,',
]);
```

## Embedding Models

```php
use function BengalStudio\AI\embed;
use function AISdkPhp\OpenAI\openai;

$provider = openai();
$model = $provider->embedding('text-embedding-3-small');

// Or using prefix
$model = openai('embedding:text-embedding-3-small');

$result = embed([
    'model' => $model,
    'value' => 'The quick brown fox jumps over the lazy dog.',
]);

echo count($result->embedding); // e.g. 1536
```

### Embedding with Custom Dimensions

```php
$result = embed([
    'model' => $provider->embedding('text-embedding-3-large'),
    'value' => 'Hello world',
    'providerOptions' => [
        'openai' => [
            'dimensions' => 256,
        ],
    ],
]);
```

## Streaming

All language models support streaming:

```php
use function BengalStudio\AI\streamText;

$result = streamText([
    'model' => openai('gpt-4.1'),
    'prompt' => 'Write a poem about PHP.',
]);

foreach ($result->textStream as $chunk) {
    echo $chunk;
}
```

## Available Models

### Text Generation
- `gpt-4.1` — latest GPT-4.1
- `gpt-4.1-mini` — smaller, faster GPT-4.1
- `gpt-4.1-nano` — smallest GPT-4.1
- `gpt-4o` — GPT-4o
- `gpt-4o-mini` — smaller GPT-4o
- `o4-mini` — reasoning model
- `o3` — reasoning model
- `o3-mini` — smaller reasoning model
- `o1` — reasoning model
- `o1-mini` — smaller reasoning model

### Embeddings
- `text-embedding-3-small` — 1536 dimensions, best value
- `text-embedding-3-large` — 3072 dimensions, highest performance
- `text-embedding-ada-002` — legacy, 1536 dimensions

## Custom Base URL

For OpenAI-compatible APIs (Azure, local deployments, etc.):

```php
$provider = createOpenAI([
    'baseURL' => 'https://my-proxy.example.com/v1',
    'apiKey' => 'my-api-key',
    'name' => 'my-provider', // Optional custom name
]);
```

## License

MIT

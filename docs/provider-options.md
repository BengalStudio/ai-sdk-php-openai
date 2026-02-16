# Provider Options — OpenAI

Detailed guide to all provider-specific options for each OpenAI API surface.

Provider options are passed via the `providerOptions` key:

```php
$result = generateText([
    'model' => $model,
    'prompt' => '...',
    'providerOptions' => [
        'openai' => [
            // Options documented below
        ],
    ],
]);
```

---

## Responses API Options

Used with `openai('gpt-4o')` or `$provider->responses('gpt-4o')`.

### Reasoning

```php
'providerOptions' => [
    'openai' => [
        'reasoningEffort' => 'high',     // 'low', 'medium', 'high' (o-series models)
        'reasoningSummary' => 'auto',    // Include reasoning summary in output
        'forceReasoning' => true,        // Force reasoning mode
    ],
],
```

### Built-in Tools

#### Web Search

```php
'providerOptions' => [
    'openai' => [
        'webSearch' => true,
        // Or with configuration:
        'webSearch' => [
            'searchContextSize' => 'medium',  // 'low', 'medium', 'high'
            'userLocation' => [
                'type' => 'approximate',
                'city' => 'San Francisco',
                'region' => 'California',
                'country' => 'US',
            ],
        ],
    ],
],
```

#### File Search

```php
'providerOptions' => [
    'openai' => [
        'fileSearch' => [
            'vectorStoreIds' => ['vs_abc123'],
            'maxNumResults' => 10,
        ],
    ],
],
```

#### Code Interpreter

```php
'providerOptions' => [
    'openai' => [
        'codeInterpreter' => true,
    ],
],
```

### Conversation Threading

```php
'providerOptions' => [
    'openai' => [
        'previousResponseId' => 'resp_abc123',  // Continue from a previous response
    ],
],
```

### Storage & Metadata

```php
'providerOptions' => [
    'openai' => [
        'store' => true,                       // Store response (default: true)
        'metadata' => ['user' => 'user-123'],  // Custom metadata
    ],
],
```

### Other Options

| Option | Type | Description |
|--------|------|-------------|
| `systemMessageMode` | `string` | How to handle system messages: `'system'` (→ instructions), `'developer'`, `'remove'` |
| `user` | `string` | End-user identifier for abuse tracking |
| `logprobs` | `bool\|int` | Return log probabilities |
| `truncation` | `string` | Truncation strategy |
| `include` | `array` | Additional fields to include in response |
| `strictJsonSchema` | `bool` | Enforce strict JSON Schema validation |
| `serviceTier` | `string` | Service tier selection |
| `textVerbosity` | `string` | Control text output verbosity |

---

## Chat Completions API Options

Used with `openai('chat:gpt-4o')` or `$provider->chat('gpt-4o')`.

### Reasoning

```php
'providerOptions' => [
    'openai' => [
        'reasoningEffort' => 'high',  // For o-series models
        'forceReasoning' => true,
    ],
],
```

### Structured Output

```php
'providerOptions' => [
    'openai' => [
        'strictJsonSchema' => true,  // Enforce strict mode for response_format
    ],
],
```

### Tool Calling

```php
'providerOptions' => [
    'openai' => [
        'parallelToolCalls' => false,  // Disable parallel tool calls
    ],
],
```

### Predicted Output

```php
'providerOptions' => [
    'openai' => [
        'prediction' => [
            'type' => 'content',
            'content' => 'Expected output for faster generation...',
        ],
    ],
],
```

### Distillation

```php
'providerOptions' => [
    'openai' => [
        'store' => true,                        // Store for distillation
        'metadata' => ['task' => 'classify'],    // Custom metadata
    ],
],
```

### Token Manipulation

```php
'providerOptions' => [
    'openai' => [
        'logitBias' => [1234 => -100],  // Bias specific token IDs
        'logprobs' => 5,                 // Return top-5 log probabilities
    ],
],
```

### Other Options

| Option | Type | Description |
|--------|------|-------------|
| `systemMessageMode` | `string` | `'system'`, `'developer'`, `'remove'` |
| `user` | `string` | End-user identifier |
| `serviceTier` | `string` | Service tier |
| `textVerbosity` | `string` | Text verbosity |

---

## Completions API Options (Legacy)

Used with `openai('completion:gpt-3.5-turbo-instruct')` or `$provider->completion('...')`.

| Option | Type | Description |
|--------|------|-------------|
| `echo` | `bool` | Echo back the prompt in the response |
| `logitBias` | `array` | Token ID to bias value mapping |
| `logprobs` | `int` | Number of log probabilities to return |
| `suffix` | `string` | Text to append after the completion |
| `user` | `string` | End-user identifier |

**Note:** The Completions API does not support tools, tool choice, response format, or topK. These will emit warnings if provided.

---

## Embedding Options

Used with `openai('embedding:text-embedding-3-small')` or `$provider->embedding('...')`.

### Custom Dimensions

```php
$result = embed([
    'model' => $provider->embedding('text-embedding-3-large'),
    'value' => 'Hello world',
    'providerOptions' => [
        'openai' => [
            'dimensions' => 256,  // Reduce from default 3072
        ],
    ],
]);
```

| Option | Type | Description |
|--------|------|-------------|
| `dimensions` | `int` | Custom embedding dimensions (text-embedding-3 models only) |
| `user` | `string` | End-user identifier |

---

## System Message Modes

All language models support `systemMessageMode` to control how system messages are handled:

| Mode | Chat API | Responses API |
|------|----------|---------------|
| `'system'` | `role: "system"` message | Extracted as `instructions` parameter |
| `'developer'` | `role: "developer"` message | `role: "developer"` in input |
| `'remove'` | System message is dropped (with warning) | System message is dropped (with warning) |

```php
'providerOptions' => [
    'openai' => [
        'systemMessageMode' => 'developer',
    ],
],
```

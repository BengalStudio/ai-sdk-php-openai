# Available Models

A reference of OpenAI models supported by this provider.

## Text Generation Models

### GPT-4.1 Series

| Model | Description | Context Window |
|-------|-------------|----------------|
| `gpt-4.1` | Latest and most capable GPT-4.1 | 1M tokens |
| `gpt-4.1-mini` | Smaller, faster GPT-4.1 | 1M tokens |
| `gpt-4.1-nano` | Smallest GPT-4.1 variant | 1M tokens |

### GPT-4o Series

| Model | Description | Context Window |
|-------|-------------|----------------|
| `gpt-4o` | GPT-4o multimodal | 128K tokens |
| `gpt-4o-mini` | Smaller, cost-effective GPT-4o | 128K tokens |

### Reasoning Models (o-series)

| Model | Description | Reasoning |
|-------|-------------|-----------|
| `o4-mini` | Compact reasoning model | Yes |
| `o3` | Full reasoning model | Yes |
| `o3-mini` | Smaller reasoning model | Yes |
| `o1` | Original reasoning model | Yes |
| `o1-mini` | Compact reasoning model | Yes |

> Reasoning models automatically disable `temperature` and `topP` parameters. Use the `reasoningEffort` provider option to control reasoning depth.

### API Compatibility

| Model | Responses API | Chat API | Completions API |
|-------|:---:|:---:|:---:|
| GPT-4.1 series | ✅ | ✅ | ❌ |
| GPT-4o series | ✅ | ✅ | ❌ |
| o-series | ✅ | ✅ | ❌ |
| `gpt-3.5-turbo-instruct` | ❌ | ❌ | ✅ |

## Embedding Models

| Model | Dimensions | Max Input | Notes |
|-------|:---:|:---:|-------|
| `text-embedding-3-small` | 1536 | 8191 tokens | Best value, custom dimensions supported |
| `text-embedding-3-large` | 3072 | 8191 tokens | Highest performance, custom dimensions supported |
| `text-embedding-ada-002` | 1536 | 8191 tokens | Legacy, no custom dimensions |

### Custom Dimensions

`text-embedding-3-*` models support reduced dimensions for storage efficiency:

```php
$provider->embedding('text-embedding-3-large');
// Default: 3072 dimensions

// With custom dimensions
embed([
    'model' => $provider->embedding('text-embedding-3-large'),
    'value' => 'Hello',
    'providerOptions' => ['openai' => ['dimensions' => 256]],
]);
// Returns: 256-dimension vector
```

## Selecting an API

| Use Case | Recommended API | How to Use |
|----------|----------------|------------|
| General chat, tool calling | Responses (default) | `openai('gpt-4o')` |
| Web search, file search | Responses | `openai('gpt-4o')` + `webSearch` option |
| Structured JSON output | Chat Completions | `openai('chat:gpt-4o')` |
| Legacy text completion | Completions | `openai('completion:gpt-3.5-turbo-instruct')` |
| Embeddings | Embeddings | `openai('embedding:text-embedding-3-small')` |
| OpenAI-compatible APIs | Chat Completions | `openai('chat:model')` with custom `baseURL` |

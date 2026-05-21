# API Reference — OpenAI Provider

Complete API reference for the `bengal-studio/ai-sdk-openai` package.

## Table of Contents

- [Functions](#functions)
- [Provider](#provider)
- [Provider Settings](#provider-settings)
- [Model Classes](#model-classes)
- [Message Converters](#message-converters)
- [Support Utilities](#support-utilities)

---

## Functions

All functions are in the `BengalStudio\AI\OpenAI` namespace.

### `createOpenAI(array $settings = []): OpenAIProvider`

Create an OpenAI provider instance.

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `baseURL` | `string` | `'https://api.openai.com/v1'` | API base URL |
| `apiKey` | `string` | `null` | API key (falls back to `OPENAI_API_KEY` env) |
| `name` | `string` | `'openai'` | Provider name for identification |
| `organization` | `string` | `null` | OpenAI Organization ID |
| `project` | `string` | `null` | OpenAI Project ID |
| `headers` | `array` | `[]` | Additional HTTP headers |

---

### `openai(?string $modelId = null, array $settings = []): mixed`

Shorthand factory with prefix-based routing.

| Input | Return Type | Description |
|-------|-------------|-------------|
| `null` | `OpenAIProvider` | Returns the provider instance |
| `'gpt-4o'` | `OpenAIResponsesLanguageModel` | Responses API (default) |
| `'chat:gpt-4o'` | `OpenAIChatLanguageModel` | Chat Completions API |
| `'completion:gpt-3.5-turbo-instruct'` | `OpenAICompletionLanguageModel` | Legacy Completions API |
| `'embedding:text-embedding-3-small'` | `OpenAIEmbeddingModel` | Embeddings API |

---

## Provider

### `OpenAIProvider` (implements `Provider`)

| Method | Return Type | Description |
|--------|-------------|-------------|
| `languageModel(string $modelId)` | `LanguageModel` | Delegates to `responses()` |
| `embeddingModel(string $modelId)` | `EmbeddingModel` | Delegates to `embedding()` |
| `chat(string $modelId)` | `OpenAIChatLanguageModel` | Chat Completions API |
| `completion(string $modelId)` | `OpenAICompletionLanguageModel` | Legacy Completions API |
| `responses(string $modelId)` | `OpenAIResponsesLanguageModel` | Responses API |
| `embedding(string $modelId)` | `OpenAIEmbeddingModel` | Embeddings API |

---

## Provider Settings

### `OpenAIProviderSettings`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `baseURL` | `string` | `'https://api.openai.com/v1'` | API base URL |
| `apiKey` | `?string` | `null` | API key |
| `name` | `string` | `'openai'` | Provider identification name |
| `organization` | `?string` | `null` | `OpenAI-Organization` header |
| `project` | `?string` | `null` | `OpenAI-Project` header |
| `headers` | `array` | `[]` | Extra HTTP headers |

---

## Model Classes

All models implement `BengalStudio\AI\Contracts\LanguageModel` or `EmbeddingModel`.

### `OpenAIResponsesLanguageModel`

**Endpoint:** `POST /v1/responses`

The default and most feature-rich model. Supports built-in tools (web search, file search, code interpreter, image generation), reasoning, and conversation threading.

**Provider Options** (`providerOptions['openai']`):

| Option | Type | Description |
|--------|------|-------------|
| `systemMessageMode` | `string` | `'system'`, `'developer'`, or `'remove'` |
| `store` | `bool` | Store responses (default: `true`) |
| `metadata` | `array` | Custom metadata |
| `previousResponseId` | `string` | Continue a conversation thread |
| `reasoningEffort` | `string` | `'low'`, `'medium'`, `'high'` (for o-series models) |
| `reasoningSummary` | `string` | `'auto'` to include reasoning summary |
| `serviceTier` | `string` | Service tier selection |
| `textVerbosity` | `string` | Control text output verbosity |
| `user` | `string` | End-user identifier |
| `logprobs` | `bool\|int` | Return log probabilities |
| `truncation` | `string` | Truncation strategy |
| `include` | `array` | Additional fields to include |
| `strictJsonSchema` | `bool` | Enforce strict JSON Schema |
| `forceReasoning` | `bool` | Force reasoning for supported models |
| `webSearch` | `bool\|array` | Enable web search tool |
| `fileSearch` | `array` | Enable file search with vector store IDs |
| `codeInterpreter` | `bool` | Enable code interpreter tool |

**Built-in Tools:** `web_search_call`, `code_interpreter_call`, `image_generation_call`, `file_search_call`

---

### `OpenAIChatLanguageModel`

**Endpoint:** `POST /v1/chat/completions`

The Chat Completions API. Best for standard chat interactions, tool calling, and structured output.

**Provider Options** (`providerOptions['openai']`):

| Option | Type | Description |
|--------|------|-------------|
| `systemMessageMode` | `string` | `'system'`, `'developer'`, or `'remove'` |
| `logitBias` | `array` | Token ID to bias value mapping |
| `logprobs` | `int` | Number of log probabilities |
| `parallelToolCalls` | `bool` | Enable/disable parallel tool calls |
| `user` | `string` | End-user identifier |
| `store` | `bool` | Store for distillation |
| `metadata` | `array` | Custom metadata |
| `prediction` | `array` | Predicted output for faster generation |
| `reasoningEffort` | `string` | `'low'`, `'medium'`, `'high'` |
| `serviceTier` | `string` | Service tier |
| `textVerbosity` | `string` | Text output verbosity |
| `strictJsonSchema` | `bool` | Enforce strict JSON Schema |
| `forceReasoning` | `bool` | Force reasoning mode |

**Reasoning model detection:** Models prefixed with `o1`, `o3`, or `o4` are automatically treated as reasoning models (disables temperature, topP).

---

### `OpenAICompletionLanguageModel`

**Endpoint:** `POST /v1/completions`

Legacy completions API for models like `gpt-3.5-turbo-instruct`.

**Provider Options** (`providerOptions['openai']`):

| Option | Type | Description |
|--------|------|-------------|
| `echo` | `bool` | Echo back the prompt |
| `logitBias` | `array` | Token biases |
| `logprobs` | `int` | Log probabilities count |
| `suffix` | `string` | Text to append after completion |
| `user` | `string` | End-user identifier |

**Unsupported features** (emit warnings): `topK`, `tools`, `toolChoice`, `responseFormat`

---

### `OpenAIEmbeddingModel`

**Endpoint:** `POST /v1/embeddings`

| Property | Value |
|----------|-------|
| Max embeddings per call | 2048 |
| Supports parallel calls | Yes |
| Encoding format | `float` |

**Provider Options** (`providerOptions['openai']`):

| Option | Type | Description |
|--------|------|-------------|
| `dimensions` | `int` | Custom dimensions (text-embedding-3 models only) |
| `user` | `string` | End-user identifier |

---

## Message Converters

### `OpenAIChatMessageConverter`

Converts `Message[]` to the Chat Completions API format.

```php
OpenAIChatMessageConverter::convert(array $prompt, string $systemMessageMode = 'system'): array
// Returns: ['messages' => [...], 'warnings' => [...]]
```

**Supports:**
- System/developer/user/assistant/tool messages
- Multimodal content: text, images (URL + base64), files
- Tool calls with JSON arguments
- System message mode: `'system'` (default), `'developer'`, `'remove'`

---

### `OpenAIResponsesInputConverter`

Converts `Message[]` to the Responses API input format.

```php
OpenAIResponsesInputConverter::convert(array $prompt, string $systemMessageMode = 'system'): array
// Returns: ['input' => [...], 'instructions' => string|null, 'warnings' => [...]]
```

**Differences from Chat converter:**
- System messages become `instructions` parameter (default) or `developer` input items
- Text parts use `input_text` type instead of `text`
- Images use `input_image` type instead of `image_url`
- Files use `input_file` type with `file_url`
- Tool calls use `function_call` format with `call_id`
- Tool results use `function_call_output` format

---

## Support Utilities

### `OpenAIConfig`

Manages base URL and authentication headers.

```php
$config = new OpenAIConfig(
    provider: 'openai',
    baseURL: 'https://api.openai.com/v1',
    apiKey: 'sk-...',
    organization: 'org-...',
    project: 'proj-...',
    headers: [],
);

$config->buildUrl('/responses');  // 'https://api.openai.com/v1/responses'
$config->getHeaders();            // ['Authorization' => 'Bearer sk-...', 'OpenAI-Organization' => '...']
```

---

### `OpenAIErrorHandler`

Static methods for handling API errors.

```php
// Throws APICallException with parsed error message
OpenAIErrorHandler::handleErrorResponse(int $statusCode, string $responseBody, string $url): void

// Check if an HTTP status code is retryable
OpenAIErrorHandler::isRetryable(int $statusCode): bool
// Retryable codes: 408, 429, 500, 502, 503, 504
```

---

### `OpenAIUtils`

Static utility methods.

```php
// Map Chat Completions finish_reason to FinishReason enum value
OpenAIUtils::mapFinishReason(?string $finishReason): string
// 'stop' → 'stop', 'length'/'max_tokens' → 'length', 'tool_calls'/'function_call' → 'tool-calls'

// Map Responses API incomplete_details reason to FinishReason
OpenAIUtils::mapResponsesFinishReason(?string $reason, bool $hasToolCalls = false): string
// null → 'stop', null+toolCalls → 'tool-calls', 'max_output_tokens' → 'length'

// Parse SSE stream into decoded JSON arrays
OpenAIUtils::parseSSEStream($stream): Generator

// Merge header arrays (later overrides earlier, nulls skipped)
OpenAIUtils::combineHeaders(array ...$headerSets): array
```

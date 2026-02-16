# Architecture — OpenAI Provider

An overview of how the `ai-sdk-php/openai` package is structured.

## Design

The OpenAI provider translates between the AI SDK's unified interface and the OpenAI API. It supports four distinct API surfaces:

| API | Class | Endpoint | Default |
|-----|-------|----------|---------|
| **Responses** | `OpenAIResponsesLanguageModel` | `/v1/responses` | Yes |
| **Chat Completions** | `OpenAIChatLanguageModel` | `/v1/chat/completions` | No |
| **Completions** | `OpenAICompletionLanguageModel` | `/v1/completions` | No |
| **Embeddings** | `OpenAIEmbeddingModel` | `/v1/embeddings` | — |

The Responses API is the default (matching AI SDK v5+ behavior), as it is the most feature-rich.

## Package Structure

```
src/
├── OpenAIProvider.php              Provider factory
├── OpenAIProviderSettings.php      Config value object
├── functions.php                   createOpenAI(), openai() convenience functions
├── Chat/
│   ├── OpenAIChatLanguageModel.php       Chat Completions implementation
│   └── OpenAIChatMessageConverter.php    Message format converter
├── Completion/
│   └── OpenAICompletionLanguageModel.php Legacy Completions implementation
├── Embedding/
│   └── OpenAIEmbeddingModel.php          Embedding implementation
├── Responses/
│   ├── OpenAIResponsesLanguageModel.php  Responses API implementation
│   └── OpenAIResponsesInputConverter.php Message format converter
└── Support/
    ├── OpenAIConfig.php                  URL building & auth headers
    ├── OpenAIErrorHandler.php            Error parsing & retry detection
    └── OpenAIUtils.php                   Finish reason mapping, SSE parsing
```

## Dependency Flow

```
┌─────────────────────────────────────┐
│         openai() / createOpenAI()   │
└────────────────┬────────────────────┘
                 │
┌────────────────▼────────────────────┐
│          OpenAIProvider             │
│  chat()  responses()  embedding()   │
└──┬──────────┬──────────┬────────────┘
   │          │          │
   ▼          ▼          ▼
┌────────┐ ┌──────────┐ ┌──────────┐
│ChatLM  │ │ResponseLM│ │EmbedModel│
└───┬────┘ └────┬─────┘ └────┬─────┘
    │           │             │
    ▼           ▼             │
┌────────┐ ┌──────────┐      │
│ChatMsg │ │RespInput │      │
│Convert │ │Convert   │      │
└───┬────┘ └────┬─────┘      │
    │           │             │
    └─────┬─────┘─────────────┘
          │
    ┌─────▼───────────────┐
    │    OpenAIConfig     │
    │  OpenAIErrorHandler │
    │  OpenAIUtils        │
    └─────────────────────┘
          │
    ┌─────▼───────────────┐
    │  Guzzle HTTP Client │
    └─────────────────────┘
```

## Request Flow

### Generate (Non-Streaming)

1. `OpenAIProvider` creates the appropriate model class with `OpenAIConfig`.
2. Core SDK calls `model->doGenerate(LanguageModelCallOptions)`.
3. Model class:
   a. Calls `getArgs()` to build the API request body.
   b. Converts messages via `MessageConverter::convert()` or `InputConverter::convert()`.
   c. Applies provider options (reasoning, tools, etc.).
   d. Sends POST request via Guzzle.
   e. On error: `OpenAIErrorHandler::handleErrorResponse()` throws `APICallException`.
   f. Maps `finish_reason` via `OpenAIUtils::mapFinishReason()`.
   g. Returns `LanguageModelGenerateResult`.

### Stream

Same as generate, but with `stream: true` in the request body:

1. Response is an SSE stream.
2. `OpenAIUtils::parseSSEStream()` yields decoded JSON chunks.
3. `processStream()` maps chunks to SDK stream events (`text-delta`, `tool-call`, `finish`).
4. Returns `LanguageModelStreamResult` wrapping the generator.

## Message Conversion

The two message converters handle the different API formats:

### Chat Completions Format

```json
{
  "messages": [
    {"role": "system", "content": "You are helpful."},
    {"role": "user", "content": "Hello"},
    {"role": "assistant", "content": null, "tool_calls": [...]},
    {"role": "tool", "tool_call_id": "...", "content": "..."}
  ]
}
```

### Responses API Format

```json
{
  "instructions": "You are helpful.",
  "input": [
    {"role": "user", "content": "Hello"},
    {"role": "assistant", "content": [{"type": "output_text", "text": "..."}]},
    {"type": "function_call_output", "call_id": "...", "output": "..."}
  ]
}
```

Key differences:
- System messages → `instructions` parameter (Responses) vs `system` role (Chat)
- Content parts use different type names (`input_text` vs `text`, `input_image` vs `image_url`)
- Tool calls: `function_call` with `call_id` (Responses) vs `tool_calls` with `id` (Chat)
- Tool results: `function_call_output` (Responses) vs `tool` role (Chat)

## Reasoning Model Detection

Both `OpenAIChatLanguageModel` and `OpenAIResponsesLanguageModel` detect reasoning models (o1, o3, o4 prefixes) and automatically:

- Disable `temperature` and `topP` parameters
- Use `max_completion_tokens` instead of `max_tokens`
- Support `reasoningEffort` provider option

## Error Handling

`OpenAIErrorHandler` parses error responses in two formats:

```json
{"error": {"message": "Rate limit exceeded"}}
{"error": "Something went wrong"}
```

Retryable status codes (408, 429, 500, 502, 503, 504) are automatically retried by the core SDK's retry mechanism.

## OpenAI-Compatible APIs

The `baseURL` setting allows using OpenAI-compatible APIs:

```php
$provider = createOpenAI([
    'baseURL' => 'https://api.together.xyz/v1',  // Together AI
    'apiKey' => 'your-key',
    'name' => 'together',
]);
```

The provider will use the same request format but target the custom URL. This works for providers that implement the OpenAI API specification (Azure OpenAI, Ollama, LiteLLM, vLLM, etc.).

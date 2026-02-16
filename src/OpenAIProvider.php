<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI;

use AISdkPhp\OpenAI\Chat\OpenAIChatLanguageModel;
use AISdkPhp\OpenAI\Completion\OpenAICompletionLanguageModel;
use AISdkPhp\OpenAI\Embedding\OpenAIEmbeddingModel;
use AISdkPhp\OpenAI\Responses\OpenAIResponsesLanguageModel;
use AISdkPhp\OpenAI\Support\OpenAIConfig;
use BengalStudio\AI\Contracts\EmbeddingModel;
use BengalStudio\AI\Contracts\LanguageModel;
use BengalStudio\AI\Contracts\Provider;
use BengalStudio\AI\Exceptions\NoSuchModelException;

/**
 * OpenAI provider implementation.
 *
 * Creates language and embedding models that call the OpenAI API.
 * The default invocation routes to the Responses API (matching AI SDK v5+ behavior).
 *
 * Usage:
 *   $openai = createOpenAI(['apiKey' => 'sk-...']);
 *   $model = $openai->languageModel('gpt-4o'); // Uses Responses API
 *   $model = $openai->chat('gpt-4o');          // Uses Chat Completions API
 *   $model = $openai->completion('gpt-3.5-turbo-instruct'); // Legacy completions
 *   $model = $openai->responses('gpt-4o');     // Responses API explicitly
 *   $model = $openai->embedding('text-embedding-3-large'); // Embeddings
 */
class OpenAIProvider implements Provider
{
    private OpenAIConfig $config;

    public function __construct(
        private readonly OpenAIProviderSettings $settings = new OpenAIProviderSettings(),
    ) {
        $this->config = new OpenAIConfig(
            provider: $this->settings->name,
            baseURL: $this->settings->baseURL,
            apiKey: $this->settings->apiKey,
            organization: $this->settings->organization,
            project: $this->settings->project,
            headers: $this->settings->headers,
        );
    }

    /**
     * Create a language model using the Responses API (default).
     *
     * This is the default model type, matching AI SDK v5+ behavior.
     * The Responses API supports web search, file search, code interpreter,
     * image generation, MCP, and other OpenAI-hosted tools.
     */
    public function languageModel(string $modelId): LanguageModel
    {
        return $this->responses($modelId);
    }

    /**
     * Create an embedding model.
     */
    public function embeddingModel(string $modelId): EmbeddingModel
    {
        return $this->embedding($modelId);
    }

    /**
     * Create a Chat Completions API language model.
     *
     * Supports tool calls, structured outputs, reasoning, logprobs,
     * predicted outputs, distillation, prompt caching, and audio input.
     */
    public function chat(string $modelId): OpenAIChatLanguageModel
    {
        return new OpenAIChatLanguageModel($modelId, $this->config);
    }

    /**
     * Create a legacy Completions API language model.
     *
     * Typically used with models like gpt-3.5-turbo-instruct.
     */
    public function completion(string $modelId): OpenAICompletionLanguageModel
    {
        return new OpenAICompletionLanguageModel($modelId, $this->config);
    }

    /**
     * Create a Responses API language model.
     *
     * The Responses API is the default and most feature-rich API,
     * supporting web search, file search, code interpreter, image generation,
     * MCP tools, reasoning, and more.
     */
    public function responses(string $modelId): OpenAIResponsesLanguageModel
    {
        return new OpenAIResponsesLanguageModel($modelId, $this->config);
    }

    /**
     * Create an embedding model.
     *
     * Supports text-embedding-3-large, text-embedding-3-small,
     * and text-embedding-ada-002.
     */
    public function embedding(string $modelId): OpenAIEmbeddingModel
    {
        return new OpenAIEmbeddingModel($modelId, $this->config);
    }
}

<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI;

use AISdkPhp\OpenAI\Chat\OpenAIChatLanguageModel;
use AISdkPhp\OpenAI\Completion\OpenAICompletionLanguageModel;
use AISdkPhp\OpenAI\Embedding\OpenAIEmbeddingModel;
use AISdkPhp\OpenAI\Responses\OpenAIResponsesLanguageModel;
use AISdkPhp\OpenAI\Support\OpenAIConfig;

/**
 * Create an OpenAI provider instance.
 *
 * @param array{
 *   baseURL?: string,
 *   apiKey?: string,
 *   name?: string,
 *   organization?: string,
 *   project?: string,
 *   headers?: array<string, string>,
 * } $settings
 * @return OpenAIProvider
 */
function createOpenAI(array $settings = []): OpenAIProvider
{
    return new OpenAIProvider(new OpenAIProviderSettings(
        baseURL: $settings['baseURL'] ?? 'https://api.openai.com/v1',
        apiKey: $settings['apiKey'] ?? null,
        name: $settings['name'] ?? 'openai',
        organization: $settings['organization'] ?? null,
        project: $settings['project'] ?? null,
        headers: $settings['headers'] ?? [],
    ));
}

/**
 * Create an OpenAI model from a model ID string.
 *
 * When called without a model ID, returns the default provider instance.
 * When called with a model ID, returns the default (responses) language model.
 *
 * Supported model ID prefixes for routing:
 * - 'chat:' prefix → Chat Completions API
 * - 'completion:' prefix → Completions API
 * - 'embedding:' prefix → Embedding API
 * - No prefix → Responses API (default)
 *
 * @param string|null $modelId Optional model identifier
 * @param array $settings Provider settings
 * @return OpenAIProvider|OpenAIChatLanguageModel|OpenAICompletionLanguageModel|OpenAIEmbeddingModel|OpenAIResponsesLanguageModel
 */
function openai(?string $modelId = null, array $settings = []): OpenAIProvider|OpenAIChatLanguageModel|OpenAICompletionLanguageModel|OpenAIEmbeddingModel|OpenAIResponsesLanguageModel
{
    $provider = createOpenAI($settings);

    if ($modelId === null) {
        return $provider;
    }

    // Route based on prefix
    if (str_starts_with($modelId, 'chat:')) {
        return $provider->chat(substr($modelId, 5));
    }

    if (str_starts_with($modelId, 'completion:')) {
        return $provider->completion(substr($modelId, 11));
    }

    if (str_starts_with($modelId, 'embedding:')) {
        return $provider->embedding(substr($modelId, 10));
    }

    // Default to Responses API
    return $provider->responses($modelId);
}

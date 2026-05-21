<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Embedding;

use BengalStudio\AI\OpenAI\Support\OpenAIConfig;
use BengalStudio\AI\OpenAI\Support\OpenAIErrorHandler;
use BengalStudio\AI\OpenAI\Support\OpenAIUtils;
use BengalStudio\AI\Contracts\EmbeddingModel;
use BengalStudio\AI\Exceptions\TooManyEmbeddingValuesException;
use BengalStudio\AI\Types\EmbeddingModelCallOptions;
use BengalStudio\AI\Types\EmbeddingModelResult;
use BengalStudio\AI\Types\EmbeddingModelUsage;
use GuzzleHttp\Client;

/**
 * OpenAI Embeddings API model.
 *
 * Implements the Embeddings endpoint (/v1/embeddings).
 * Supports text-embedding-3-large, text-embedding-3-small,
 * and text-embedding-ada-002.
 *
 * Provider options:
 *   - dimensions: int — Number of dimensions for the embedding (text-embedding-3 only).
 *   - user: string — Unique end-user identifier.
 */
class OpenAIEmbeddingModel implements EmbeddingModel
{
    private const MAX_EMBEDDINGS_PER_CALL = 2048;

    private Client $httpClient;

    public function __construct(
        private readonly string $modelId,
        private readonly OpenAIConfig $config,
    ) {
        $this->httpClient = new Client();
    }

    public function specificationVersion(): string
    {
        return 'v3';
    }

    public function provider(): string
    {
        return $this->config->provider;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function maxEmbeddingsPerCall(): ?int
    {
        return self::MAX_EMBEDDINGS_PER_CALL;
    }

    public function supportsParallelCalls(): bool
    {
        return true;
    }

    /**
     * Generate embeddings for the given values.
     */
    public function doEmbed(EmbeddingModelCallOptions $options): EmbeddingModelResult
    {
        if (count($options->values) > self::MAX_EMBEDDINGS_PER_CALL) {
            throw new TooManyEmbeddingValuesException(
                provider: $this->provider(),
                modelId: $this->modelId,
                maxEmbeddingsPerCall: self::MAX_EMBEDDINGS_PER_CALL,
                values: count($options->values),
            );
        }

        $providerOptions = $options->providerOptions['openai'] ?? [];

        $body = [
            'model' => $this->modelId,
            'input' => $options->values,
            'encoding_format' => 'float',
        ];

        // Optional dimensions (text-embedding-3 models only)
        if (isset($providerOptions['dimensions'])) {
            $body['dimensions'] = $providerOptions['dimensions'];
        }

        // Optional user identifier
        if (isset($providerOptions['user'])) {
            $body['user'] = $providerOptions['user'];
        }

        $url = $this->config->url('embeddings');
        $headers = OpenAIUtils::combineHeaders(
            $this->config->headers(),
            $options->headers ?? [],
            ['Content-Type' => 'application/json'],
        );

        $response = $this->httpClient->post($url, [
            'headers' => $headers,
            'json' => $body,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            OpenAIErrorHandler::handleErrorResponse($statusCode, $responseBody, $url);
        }

        $data = json_decode($responseBody, true);

        // Extract embeddings
        $embeddings = array_map(
            fn(array $item) => $item['embedding'],
            $data['data'] ?? [],
        );

        // Extract usage
        $usage = null;
        if (isset($data['usage']['prompt_tokens'])) {
            $usage = new EmbeddingModelUsage(
                tokens: $data['usage']['prompt_tokens'],
            );
        }

        return new EmbeddingModelResult(
            embeddings: $embeddings,
            usage: $usage,
            warnings: null,
            response: [
                'headers' => $response->getHeaders(),
                'body' => $data,
            ],
        );
    }
}

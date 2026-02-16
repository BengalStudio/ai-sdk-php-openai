<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Support;

/**
 * Configuration for OpenAI API clients.
 *
 * Shared config used by all OpenAI model implementations.
 */
class OpenAIConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $baseURL = 'https://api.openai.com/v1',
        public readonly ?string $apiKey = null,
        public readonly ?string $organization = null,
        public readonly ?string $project = null,
        public readonly array $headers = [],
    ) {}

    /**
     * Build the full URL for a given API path.
     */
    public function url(string $path, string $modelId = ''): string
    {
        return rtrim($this->baseURL, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Get the combined headers including authentication.
     */
    public function headers(): array
    {
        $headers = $this->headers;

        $apiKey = $this->apiKey ?? ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null);
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        if ($this->project) {
            $headers['OpenAI-Project'] = $this->project;
        }

        return $headers;
    }
}

<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI;

/**
 * Configuration settings for creating an OpenAI provider instance.
 *
 * Mirrors the TypeScript OpenAIProviderSettings interface.
 */
class OpenAIProviderSettings
{
    public function __construct(
        /**
         * Base URL for the OpenAI API.
         * Defaults to 'https://api.openai.com/v1'.
         */
        public readonly string $baseURL = 'https://api.openai.com/v1',

        /**
         * OpenAI API key. Falls back to OPENAI_API_KEY env variable.
         */
        public readonly ?string $apiKey = null,

        /**
         * Provider name override for identification/logging.
         */
        public readonly string $name = 'openai',

        /**
         * OpenAI Organization ID.
         */
        public readonly ?string $organization = null,

        /**
         * OpenAI Project ID.
         */
        public readonly ?string $project = null,

        /**
         * Additional HTTP headers to include in all requests.
         */
        public readonly array $headers = [],
    ) {}
}

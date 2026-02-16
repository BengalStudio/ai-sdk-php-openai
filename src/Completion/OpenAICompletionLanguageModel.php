<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Completion;

use AISdkPhp\OpenAI\Support\OpenAIConfig;
use AISdkPhp\OpenAI\Support\OpenAIErrorHandler;
use AISdkPhp\OpenAI\Support\OpenAIUtils;
use BengalStudio\AI\Contracts\LanguageModel;
use BengalStudio\AI\Types\LanguageModelCallOptions;
use BengalStudio\AI\Types\LanguageModelGenerateResult;
use BengalStudio\AI\Types\LanguageModelResponseMetadata;
use BengalStudio\AI\Types\LanguageModelStreamResult;
use BengalStudio\AI\Types\LanguageModelUsage;
use GuzzleHttp\Client;

/**
 * OpenAI legacy Completions API language model.
 *
 * Implements the Completions endpoint (/v1/completions).
 * Used for models like gpt-3.5-turbo-instruct.
 */
class OpenAICompletionLanguageModel implements LanguageModel
{
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

    /**
     * Build request arguments from call options.
     *
     * Converts the prompt messages into a single text prompt
     * since the Completions API doesn't support chat messages.
     */
    private function getArgs(LanguageModelCallOptions $options): array
    {
        $warnings = [];
        $providerOptions = $options->providerOptions['openai'] ?? [];

        // Completions API requires a text prompt, not messages.
        // Convert the messages to a single text.
        $prompt = $this->convertToTextPrompt($options->prompt);

        // Unsupported features
        if ($options->topK !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'topK'];
        }
        if ($options->tools !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'tools'];
        }
        if ($options->toolChoice !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'toolChoice'];
        }
        if ($options->responseFormat !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'responseFormat'];
        }

        $args = [
            'model' => $this->modelId,
            'prompt' => $prompt,
        ];

        if ($options->maxOutputTokens !== null) {
            $args['max_tokens'] = $options->maxOutputTokens;
        }

        if ($options->temperature !== null) {
            $args['temperature'] = $options->temperature;
        }

        if ($options->topP !== null) {
            $args['top_p'] = $options->topP;
        }

        if ($options->frequencyPenalty !== null) {
            $args['frequency_penalty'] = $options->frequencyPenalty;
        }

        if ($options->presencePenalty !== null) {
            $args['presence_penalty'] = $options->presencePenalty;
        }

        if ($options->stopSequences !== null) {
            $args['stop'] = $options->stopSequences;
        }

        if ($options->seed !== null) {
            $args['seed'] = $options->seed;
        }

        // Provider-specific options
        if (isset($providerOptions['echo'])) {
            $args['echo'] = $providerOptions['echo'];
        }

        if (isset($providerOptions['logitBias'])) {
            $args['logit_bias'] = $providerOptions['logitBias'];
        }

        if (isset($providerOptions['logprobs'])) {
            $args['logprobs'] = $providerOptions['logprobs'];
        }

        if (isset($providerOptions['suffix'])) {
            $args['suffix'] = $providerOptions['suffix'];
        }

        if (isset($providerOptions['user'])) {
            $args['user'] = $providerOptions['user'];
        }

        return [
            'args' => $args,
            'warnings' => $warnings,
        ];
    }

    /**
     * Generate a complete (non-streaming) response.
     */
    public function doGenerate(LanguageModelCallOptions $options): LanguageModelGenerateResult
    {
        $result = $this->getArgs($options);
        $args = $result['args'];
        $warnings = $result['warnings'];
        $url = $this->config->url('completions');

        $response = $this->httpClient->post($url, [
            'headers' => OpenAIUtils::combineHeaders(
                $this->config->headers(),
                ['Content-Type' => 'application/json'],
            ),
            'json' => $args,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            OpenAIErrorHandler::handleErrorResponse($statusCode, $body, $url);
        }

        $data = json_decode($body, true);
        $choice = $data['choices'][0] ?? [];

        $content = [];
        $text = $choice['text'] ?? '';
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        // Usage
        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;

        // Provider metadata
        $providerMetadata = null;
        if (isset($choice['logprobs'])) {
            $providerMetadata = [
                'openai' => ['logprobs' => $choice['logprobs']],
            ];
        }

        $responseMetadata = new LanguageModelResponseMetadata(
            id: $data['id'] ?? null,
            modelId: $data['model'] ?? $this->modelId,
            timestamp: isset($data['created']) ? new \DateTimeImmutable('@' . $data['created']) : null,
        );

        return new LanguageModelGenerateResult(
            content: $content,
            finishReason: OpenAIUtils::mapFinishReason($choice['finish_reason'] ?? null),
            usage: new LanguageModelUsage(
                inputTokens: $promptTokens,
                outputTokens: $completionTokens,
            ),
            warnings: $warnings ?: null,
            response: $responseMetadata,
            providerMetadata: $providerMetadata,
            request: ['body' => $args],
        );
    }

    /**
     * Generate a streaming response.
     */
    public function doStream(LanguageModelCallOptions $options): LanguageModelStreamResult
    {
        $result = $this->getArgs($options);
        $args = $result['args'];
        $warnings = $result['warnings'];
        $url = $this->config->url('completions');

        $args['stream'] = true;
        $args['stream_options'] = ['include_usage' => true];

        $response = $this->httpClient->post($url, [
            'headers' => OpenAIUtils::combineHeaders(
                $this->config->headers(),
                ['Content-Type' => 'application/json'],
            ),
            'json' => $args,
            'http_errors' => false,
            'stream' => true,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            $body = $response->getBody()->getContents();
            OpenAIErrorHandler::handleErrorResponse($statusCode, $body, $url);
        }

        $stream = $response->getBody()->detach();
        $generator = $this->processStream($stream, $warnings);

        return new LanguageModelStreamResult(stream: $generator);
    }

    /**
     * Process the SSE stream into stream parts.
     *
     * @param resource $stream
     * @param array $warnings
     * @return \Generator
     */
    private function processStream($stream, array $warnings): \Generator
    {
        yield ['type' => 'stream-start', 'warnings' => $warnings];

        $isFirstChunk = true;
        $finishReason = 'other';
        $usage = null;
        $providerMetadata = ['openai' => []];

        foreach (OpenAIUtils::parseSSEStream($stream) as $chunk) {
            // Response metadata on first chunk
            if ($isFirstChunk) {
                yield [
                    'type' => 'response-metadata',
                    'id' => $chunk['id'] ?? null,
                    'modelId' => $chunk['model'] ?? $this->modelId,
                    'timestamp' => $chunk['created'] ?? null,
                ];
                yield ['type' => 'text-start', 'id' => '0'];
                $isFirstChunk = false;
            }

            if (isset($chunk['usage'])) {
                $usage = $chunk['usage'];
            }

            $choice = $chunk['choices'][0] ?? null;
            if ($choice === null) {
                continue;
            }

            if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                $finishReason = $choice['finish_reason'];
            }

            if (isset($choice['logprobs'])) {
                $providerMetadata['openai']['logprobs'] = $choice['logprobs'];
            }

            $text = $choice['text'] ?? '';
            if ($text !== '') {
                yield [
                    'type' => 'text-delta',
                    'id' => '0',
                    'delta' => $text,
                ];
            }
        }

        if (!$isFirstChunk) {
            yield ['type' => 'text-end', 'id' => '0'];
        }

        // Finish
        $usageResult = null;
        if ($usage !== null) {
            $usageResult = new LanguageModelUsage(
                inputTokens: $usage['prompt_tokens'] ?? 0,
                outputTokens: $usage['completion_tokens'] ?? 0,
            );
        }

        yield [
            'type' => 'finish',
            'finishReason' => OpenAIUtils::mapFinishReason($finishReason),
            'usage' => $usageResult ?? new LanguageModelUsage(),
            'providerMetadata' => !empty($providerMetadata['openai']) ? $providerMetadata : null,
        ];

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Convert AI SDK messages to a single text prompt.
     *
     * @param array $messages
     * @return string
     */
    private function convertToTextPrompt(array $messages): string
    {
        $parts = [];

        foreach ($messages as $message) {
            $content = $message->content ?? '';
            if (is_array($content)) {
                // Extract text from multi-part content
                $textParts = [];
                foreach ($content as $part) {
                    if (isset($part['type']) && $part['type'] === 'text') {
                        $textParts[] = $part['text'] ?? '';
                    }
                }
                $content = implode("\n", $textParts);
            }

            $parts[] = $content;
        }

        return implode("\n\n", $parts);
    }
}

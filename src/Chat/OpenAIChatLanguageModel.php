<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Chat;

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
 * OpenAI Chat Completions API language model.
 *
 * Implements the Chat Completions endpoint (/v1/chat/completions).
 * Supports tool calls, structured outputs, reasoning, logprobs,
 * predicted outputs, prompt caching, and more.
 */
class OpenAIChatLanguageModel implements LanguageModel
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
     * Build the request arguments from call options.
     */
    private function getArgs(LanguageModelCallOptions $options): array
    {
        $warnings = [];
        $providerOptions = $options->providerOptions['openai'] ?? [];

        // Determine system message mode
        $systemMessageMode = $providerOptions['systemMessageMode'] ?? 'system';

        // Convert messages
        $result = OpenAIChatMessageConverter::convert($options->prompt, $systemMessageMode);
        $messages = $result['messages'];
        $warnings = array_merge($warnings, $result['warnings']);

        if ($options->topK !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'topK'];
        }

        // Build base args
        $args = [
            'model' => $this->modelId,
            'messages' => $messages,
        ];

        // Standard parameters
        if ($options->maxOutputTokens !== null) {
            // Reasoning models use max_completion_tokens
            $isReasoningModel = $providerOptions['forceReasoning']
                ?? $this->isReasoningModel($this->modelId);

            if ($isReasoningModel) {
                $args['max_completion_tokens'] = $options->maxOutputTokens;
            } else {
                $args['max_tokens'] = $options->maxOutputTokens;
            }
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

        // Response format
        if ($options->responseFormat !== null) {
            $type = $options->responseFormat['type'] ?? null;

            if ($type === 'json') {
                $args['response_format'] = ['type' => 'json_object'];
            } elseif ($type === 'json_schema') {
                $schema = $options->responseFormat['schema'] ?? [];
                $name = $options->responseFormat['name'] ?? 'response';
                $strict = $providerOptions['strictJsonSchema'] ?? true;

                $args['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $name,
                        'schema' => $schema,
                        'strict' => $strict,
                    ],
                ];
            }
        }

        // Provider-specific options
        if (isset($providerOptions['logitBias'])) {
            $args['logit_bias'] = $providerOptions['logitBias'];
        }

        if (isset($providerOptions['logprobs'])) {
            $args['logprobs'] = true;
            if (is_int($providerOptions['logprobs'])) {
                $args['top_logprobs'] = $providerOptions['logprobs'];
            }
        }

        if (isset($providerOptions['parallelToolCalls'])) {
            $args['parallel_tool_calls'] = $providerOptions['parallelToolCalls'];
        }

        if (isset($providerOptions['user'])) {
            $args['user'] = $providerOptions['user'];
        }

        if (isset($providerOptions['store'])) {
            $args['store'] = $providerOptions['store'];
        }

        if (isset($providerOptions['metadata'])) {
            $args['metadata'] = $providerOptions['metadata'];
        }

        if (isset($providerOptions['prediction'])) {
            $args['prediction'] = $providerOptions['prediction'];
        }

        if (isset($providerOptions['reasoningEffort'])) {
            $args['reasoning_effort'] = $providerOptions['reasoningEffort'];
        }

        if (isset($providerOptions['serviceTier'])) {
            $args['service_tier'] = $providerOptions['serviceTier'];
        }

        if (isset($providerOptions['textVerbosity'])) {
            $args['verbosity'] = $providerOptions['textVerbosity'];
        }

        // Tools
        if ($options->tools !== null && !empty($options->tools)) {
            $toolsResult = $this->prepareTools($options->tools, $options->toolChoice);
            if (!empty($toolsResult['tools'])) {
                $args['tools'] = $toolsResult['tools'];
            }
            if ($toolsResult['toolChoice'] !== null) {
                $args['tool_choice'] = $toolsResult['toolChoice'];
            }
            $warnings = array_merge($warnings, $toolsResult['warnings']);
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
        $url = $this->config->url('chat/completions');

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
        $message = $choice['message'] ?? [];

        // Build content array
        $content = [];

        // Text content
        $text = $message['content'] ?? null;
        if ($text !== null && strlen($text) > 0) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        // Tool calls
        foreach ($message['tool_calls'] ?? [] as $toolCall) {
            $content[] = [
                'type' => 'tool-call',
                'toolCallId' => $toolCall['id'] ?? uniqid('call_'),
                'toolName' => $toolCall['function']['name'] ?? '',
                'input' => $toolCall['function']['arguments'] ?? '{}',
            ];
        }

        // Annotations/citations
        foreach ($message['annotations'] ?? [] as $annotation) {
            if (isset($annotation['url_citation'])) {
                $content[] = [
                    'type' => 'source',
                    'sourceType' => 'url',
                    'url' => $annotation['url_citation']['url'] ?? '',
                    'title' => $annotation['url_citation']['title'] ?? '',
                ];
            }
        }

        // Usage
        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        $cachedTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? null;
        $reasoningTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? null;

        // Provider metadata
        $providerMetadata = ['openai' => []];
        $completionDetails = $usage['completion_tokens_details'] ?? [];

        if (isset($completionDetails['accepted_prediction_tokens'])) {
            $providerMetadata['openai']['acceptedPredictionTokens'] = $completionDetails['accepted_prediction_tokens'];
        }
        if (isset($completionDetails['rejected_prediction_tokens'])) {
            $providerMetadata['openai']['rejectedPredictionTokens'] = $completionDetails['rejected_prediction_tokens'];
        }
        if (isset($choice['logprobs']['content'])) {
            $providerMetadata['openai']['logprobs'] = $choice['logprobs']['content'];
        }

        // Response metadata
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
                cachedInputTokens: $cachedTokens,
                reasoningTokens: $reasoningTokens,
            ),
            warnings: $warnings ?: null,
            response: $responseMetadata,
            providerMetadata: !empty($providerMetadata['openai']) ? $providerMetadata : null,
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
        $url = $this->config->url('chat/completions');

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

        $toolCalls = [];
        $isFirstChunk = true;
        $isActiveText = false;
        $finishReason = 'other';
        $usage = null;

        foreach (OpenAIUtils::parseSSEStream($stream) as $chunk) {
            // Response metadata on first chunk
            if ($isFirstChunk) {
                yield [
                    'type' => 'response-metadata',
                    'id' => $chunk['id'] ?? null,
                    'modelId' => $chunk['model'] ?? $this->modelId,
                    'timestamp' => isset($chunk['created']) ? $chunk['created'] : null,
                ];
                $isFirstChunk = false;
            }

            // Usage tracking
            if (isset($chunk['usage'])) {
                $usage = $chunk['usage'];
            }

            $choice = $chunk['choices'][0] ?? null;
            if ($choice === null) {
                continue;
            }

            $delta = $choice['delta'] ?? [];

            // Finish reason
            if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                $finishReason = $choice['finish_reason'];
            }

            // Text content
            if (isset($delta['content']) && $delta['content'] !== null) {
                if (!$isActiveText) {
                    yield ['type' => 'text-start', 'id' => '0'];
                    $isActiveText = true;
                }
                yield [
                    'type' => 'text-delta',
                    'id' => '0',
                    'textDelta' => $delta['content'],
                ];
            }

            // Tool calls
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallDelta) {
                    $index = $toolCallDelta['index'] ?? count($toolCalls);

                    if (!isset($toolCalls[$index])) {
                        // New tool call
                        $toolCalls[$index] = [
                            'id' => $toolCallDelta['id'] ?? '',
                            'type' => 'function',
                            'function' => [
                                'name' => $toolCallDelta['function']['name'] ?? '',
                                'arguments' => '',
                            ],
                            'hasFinished' => false,
                        ];

                        yield [
                            'type' => 'tool-input-start',
                            'id' => $toolCalls[$index]['id'],
                            'toolName' => $toolCalls[$index]['function']['name'],
                        ];
                    }

                    // Accumulate arguments
                    $argsDelta = $toolCallDelta['function']['arguments'] ?? '';
                    $toolCalls[$index]['function']['arguments'] .= $argsDelta;

                    if ($argsDelta !== '') {
                        yield [
                            'type' => 'tool-input-delta',
                            'id' => $toolCalls[$index]['id'],
                            'delta' => $argsDelta,
                        ];
                    }
                }
            }

            // Annotations/citations
            if (isset($delta['annotations'])) {
                foreach ($delta['annotations'] as $annotation) {
                    if (isset($annotation['url_citation'])) {
                        yield [
                            'type' => 'source',
                            'sourceType' => 'url',
                            'id' => uniqid('src_'),
                            'url' => $annotation['url_citation']['url'] ?? '',
                            'title' => $annotation['url_citation']['title'] ?? '',
                        ];
                    }
                }
            }
        }

        // End active text
        if ($isActiveText) {
            yield ['type' => 'text-end', 'id' => '0'];
        }

        // Finalize tool calls
        foreach ($toolCalls as $toolCall) {
            if (!$toolCall['hasFinished']) {
                yield [
                    'type' => 'tool-input-end',
                    'id' => $toolCall['id'],
                ];

                yield [
                    'type' => 'tool-call',
                    'toolCallId' => $toolCall['id'] ?: uniqid('call_'),
                    'toolName' => $toolCall['function']['name'],
                    'input' => $toolCall['function']['arguments'],
                ];
            }
        }

        // Finish
        $usageResult = null;
        if ($usage !== null) {
            $promptTokens = $usage['prompt_tokens'] ?? 0;
            $completionTokens = $usage['completion_tokens'] ?? 0;
            $cachedTokens = $usage['prompt_tokens_details']['cached_tokens'] ?? null;
            $reasoningTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? null;

            $usageResult = new LanguageModelUsage(
                inputTokens: $promptTokens,
                outputTokens: $completionTokens,
                cachedInputTokens: $cachedTokens,
                reasoningTokens: $reasoningTokens,
            );
        }

        yield [
            'type' => 'finish',
            'finishReason' => OpenAIUtils::mapFinishReason($finishReason),
            'usage' => $usageResult ?? new LanguageModelUsage(),
        ];

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Prepare tools for the OpenAI Chat API.
     */
    private function prepareTools(?array $tools, ?array $toolChoice): array
    {
        $openaiTools = [];
        $warnings = [];

        if (empty($tools)) {
            return ['tools' => null, 'toolChoice' => null, 'warnings' => $warnings];
        }

        foreach ($tools as $tool) {
            $type = $tool['type'] ?? 'function';

            if ($type === 'function') {
                $def = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'] ?? '',
                        'parameters' => $tool['inputSchema'] ?? $tool['parameters'] ?? new \stdClass(),
                    ],
                ];

                if (isset($tool['description'])) {
                    $def['function']['description'] = $tool['description'];
                }

                // Strict mode for structured outputs
                if (isset($tool['strict'])) {
                    $def['function']['strict'] = $tool['strict'];
                }

                $openaiTools[] = $def;
            } else {
                $warnings[] = [
                    'type' => 'unsupported',
                    'feature' => "tool-type:{$type}",
                ];
            }
        }

        // Map tool choice
        $mappedToolChoice = null;
        if ($toolChoice !== null) {
            $choiceType = $toolChoice['type'] ?? null;
            $mappedToolChoice = match ($choiceType) {
                'auto' => 'auto',
                'required' => 'required',
                'none' => 'none',
                'tool' => [
                    'type' => 'function',
                    'function' => ['name' => $toolChoice['toolName'] ?? $toolChoice['name'] ?? ''],
                ],
                default => $choiceType,
            };
        }

        return [
            'tools' => $openaiTools ?: null,
            'toolChoice' => $mappedToolChoice,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if a model ID corresponds to a reasoning model.
     */
    private function isReasoningModel(string $modelId): bool
    {
        return (bool) preg_match('/^(o1|o3|o4)/', $modelId);
    }
}

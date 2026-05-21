<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Responses;

use BengalStudio\AI\OpenAI\Support\OpenAIConfig;
use BengalStudio\AI\OpenAI\Support\OpenAIErrorHandler;
use BengalStudio\AI\OpenAI\Support\OpenAIUtils;
use BengalStudio\AI\Contracts\LanguageModel;
use BengalStudio\AI\Types\LanguageModelCallOptions;
use BengalStudio\AI\Types\LanguageModelGenerateResult;
use BengalStudio\AI\Types\LanguageModelResponseMetadata;
use BengalStudio\AI\Types\LanguageModelStreamResult;
use BengalStudio\AI\Types\LanguageModelUsage;
use GuzzleHttp\Client;

/**
 * OpenAI Responses API language model.
 *
 * Implements the Responses endpoint (/v1/responses).
 * This is the default and most feature-rich API, supporting:
 * - Web search, file search, code interpreter
 * - Image generation, MCP tools
 * - Reasoning (o-series models)
 * - Structured outputs, logprobs
 * - Conversation/threading
 * - Store & metadata
 */
class OpenAIResponsesLanguageModel implements LanguageModel
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
     */
    private function getArgs(LanguageModelCallOptions $options): array
    {
        $warnings = [];
        $providerOptions = $options->providerOptions['openai'] ?? [];

        // Model capabilities
        $isReasoningModel = $providerOptions['forceReasoning']
            ?? $this->isReasoningModel($this->modelId);
        $systemMessageMode = $providerOptions['systemMessageMode']
            ?? ($isReasoningModel ? 'developer' : 'system');

        // Unsupported features
        if ($options->topK !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'topK'];
        }

        // Convert messages to Responses API input format
        $result = OpenAIResponsesInputConverter::convert($options->prompt, $systemMessageMode);
        $input = $result['input'];
        $instructions = $result['instructions'];
        $warnings = array_merge($warnings, $result['warnings']);

        $args = [
            'model' => $this->modelId,
            'input' => $input,
        ];

        if ($instructions !== null) {
            $args['instructions'] = $instructions;
        }

        // Standard parameters
        if ($options->maxOutputTokens !== null) {
            $args['max_output_tokens'] = $options->maxOutputTokens;
        }

        if ($options->temperature !== null) {
            $args['temperature'] = $options->temperature;
        }

        if ($options->topP !== null) {
            $args['top_p'] = $options->topP;
        }

        if ($options->frequencyPenalty !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'frequencyPenalty'];
        }

        if ($options->presencePenalty !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'presencePenalty'];
        }

        if ($options->seed !== null) {
            $warnings[] = ['type' => 'unsupported', 'feature' => 'seed'];
        }

        // Response format
        if ($options->responseFormat !== null) {
            $type = $options->responseFormat['type'] ?? null;
            $schema = $options->responseFormat['schema'] ?? null;

            if ($type === 'json' && $schema !== null) {
                // JSON mode with schema → use json_schema format
                $name = $options->responseFormat['name'] ?? $this->schemaName($schema) ?? 'response';
                $strict = $providerOptions['strictJsonSchema'] ?? true;

                $args['text'] = [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $name,
                        'schema' => $schema,
                        'strict' => $strict,
                    ],
                ];
            } elseif ($type === 'json') {
                $args['text'] = ['format' => ['type' => 'json_object']];
            } elseif ($type === 'json_schema') {
                $schema = $schema ?? [];
                $name = $options->responseFormat['name'] ?? 'response';
                $strict = $providerOptions['strictJsonSchema'] ?? true;

                $args['text'] = [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $name,
                        'schema' => $schema,
                        'strict' => $strict,
                    ],
                ];
            }
        }

        // Provider-specific options
        if (isset($providerOptions['store'])) {
            $args['store'] = $providerOptions['store'];
        } else {
            $args['store'] = true; // Default to true for responses API
        }

        if (isset($providerOptions['metadata'])) {
            $args['metadata'] = $providerOptions['metadata'];
        }

        if (isset($providerOptions['previousResponseId'])) {
            $args['previous_response_id'] = $providerOptions['previousResponseId'];
        }

        if (isset($providerOptions['reasoningEffort'])) {
            $args['reasoning'] = [
                'effort' => $providerOptions['reasoningEffort'],
            ];
        }

        if (isset($providerOptions['reasoningSummary'])) {
            $reasoning = $args['reasoning'] ?? [];
            $reasoning['summary'] = $providerOptions['reasoningSummary'];
            $args['reasoning'] = $reasoning;
        }

        if (isset($providerOptions['serviceTier'])) {
            $args['service_tier'] = $providerOptions['serviceTier'];
        }

        if (isset($providerOptions['textVerbosity'])) {
            $args['text'] = array_merge($args['text'] ?? [], [
                'verbosity' => $providerOptions['textVerbosity'],
            ]);
        }

        if (isset($providerOptions['user'])) {
            $args['user'] = $providerOptions['user'];
        }

        if (isset($providerOptions['logprobs'])) {
            $args['logprobs'] = true;
            if (is_int($providerOptions['logprobs'])) {
                $args['top_logprobs'] = $providerOptions['logprobs'];
            }
        }

        if (isset($providerOptions['truncation'])) {
            $args['truncation'] = $providerOptions['truncation'];
        }

        if (isset($providerOptions['include'])) {
            $args['include'] = $providerOptions['include'];
        }

        // Tools
        $toolNameMapping = [];
        if ($options->tools !== null && !empty($options->tools)) {
            $toolsResult = $this->prepareTools($options->tools, $options->toolChoice, $providerOptions);
            if (!empty($toolsResult['tools'])) {
                $args['tools'] = $toolsResult['tools'];
            }
            if ($toolsResult['toolChoice'] !== null) {
                $args['tool_choice'] = $toolsResult['toolChoice'];
            }
            $warnings = array_merge($warnings, $toolsResult['warnings']);
            $toolNameMapping = $toolsResult['toolNameMapping'] ?? [];
        }

        // Add provider-specific tools (web search, code interpreter, etc.)
        $providerTools = $this->getProviderTools($providerOptions);
        if (!empty($providerTools['tools'])) {
            $args['tools'] = array_merge($args['tools'] ?? [], $providerTools['tools']);
        }

        return [
            'args' => $args,
            'warnings' => $warnings,
            'toolNameMapping' => $toolNameMapping,
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
        $url = $this->config->url('responses');

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

        // Check for API-level error in response body
        if (isset($data['error'])) {
            OpenAIErrorHandler::handleErrorResponse(
                400,
                json_encode($data),
                $url,
            );
        }

        $content = [];
        $hasToolCalls = false;

        // Process output items
        foreach ($data['output'] ?? [] as $part) {
            $type = $part['type'] ?? '';

            switch ($type) {
                case 'message':
                    foreach ($part['content'] ?? [] as $contentPart) {
                        $partType = $contentPart['type'] ?? '';
                        if ($partType === 'output_text') {
                            $content[] = [
                                'type' => 'text',
                                'text' => $contentPart['text'] ?? '',
                            ];

                            // Annotations/citations
                            foreach ($contentPart['annotations'] ?? [] as $annotation) {
                                if (isset($annotation['url'])) {
                                    $content[] = [
                                        'type' => 'source',
                                        'sourceType' => 'url',
                                        'url' => $annotation['url'],
                                        'title' => $annotation['title'] ?? '',
                                    ];
                                }
                            }
                        }
                    }
                    break;

                case 'reasoning':
                    foreach ($part['summary'] ?? [] as $summaryPart) {
                        if (isset($summaryPart['text'])) {
                            $content[] = [
                                'type' => 'reasoning',
                                'text' => $summaryPart['text'],
                            ];
                        }
                    }
                    break;

                case 'function_call':
                    $hasToolCalls = true;
                    $content[] = [
                        'type' => 'tool-call',
                        'toolCallId' => $part['call_id'] ?? $part['id'] ?? uniqid('call_'),
                        'toolName' => $part['name'] ?? '',
                        'input' => $part['arguments'] ?? '{}',
                    ];
                    break;

                case 'web_search_call':
                    $content[] = [
                        'type' => 'tool-call',
                        'toolCallId' => $part['id'] ?? uniqid('ws_'),
                        'toolName' => 'openai.web_search',
                        'input' => json_encode(['status' => $part['status'] ?? 'completed']),
                    ];
                    break;

                case 'code_interpreter_call':
                    $content[] = [
                        'type' => 'tool-call',
                        'toolCallId' => $part['id'] ?? uniqid('ci_'),
                        'toolName' => 'openai.code_interpreter',
                        'input' => json_encode([
                            'input' => $part['input'] ?? '',
                        ]),
                    ];
                    if (isset($part['outputs'])) {
                        $content[] = [
                            'type' => 'tool-result',
                            'toolCallId' => $part['id'] ?? '',
                            'toolName' => 'openai.code_interpreter',
                            'result' => ['outputs' => $part['outputs']],
                        ];
                    }
                    break;

                case 'image_generation_call':
                    $content[] = [
                        'type' => 'tool-call',
                        'toolCallId' => $part['id'] ?? uniqid('ig_'),
                        'toolName' => 'openai.image_generation',
                        'input' => json_encode(['status' => $part['status'] ?? 'completed']),
                    ];
                    if (isset($part['result'])) {
                        $content[] = [
                            'type' => 'tool-result',
                            'toolCallId' => $part['id'] ?? '',
                            'toolName' => 'openai.image_generation',
                            'result' => ['result' => $part['result']],
                        ];
                    }
                    break;

                case 'file_search_call':
                    $content[] = [
                        'type' => 'tool-call',
                        'toolCallId' => $part['id'] ?? uniqid('fs_'),
                        'toolName' => 'openai.file_search',
                        'input' => json_encode([
                            'queries' => $part['queries'] ?? [],
                            'status' => $part['status'] ?? 'completed',
                        ]),
                    ];
                    if (isset($part['results'])) {
                        $content[] = [
                            'type' => 'tool-result',
                            'toolCallId' => $part['id'] ?? '',
                            'toolName' => 'openai.file_search',
                            'result' => ['results' => $part['results']],
                        ];
                    }
                    break;
            }
        }

        // Usage
        $usage = $data['usage'] ?? [];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $cachedTokens = $usage['input_tokens_details']['cached_tokens'] ?? null;
        $reasoningTokens = $usage['output_tokens_details']['reasoning_tokens'] ?? null;

        // Provider metadata
        $providerMetadata = ['openai' => []];
        if (isset($data['id'])) {
            $providerMetadata['openai']['responseId'] = $data['id'];
        }
        if (isset($data['service_tier'])) {
            $providerMetadata['openai']['serviceTier'] = $data['service_tier'];
        }

        // Response metadata
        $responseMetadata = new LanguageModelResponseMetadata(
            id: $data['id'] ?? null,
            modelId: $data['model'] ?? $this->modelId,
            timestamp: isset($data['created_at']) ? new \DateTimeImmutable('@' . $data['created_at']) : null,
        );

        $incompleteReason = $data['incomplete_details']['reason'] ?? null;

        return new LanguageModelGenerateResult(
            content: $content,
            finishReason: OpenAIUtils::mapResponsesFinishReason($incompleteReason, $hasToolCalls),
            usage: new LanguageModelUsage(
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
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
        $url = $this->config->url('responses');

        $args['stream'] = true;

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
     * Process the SSE stream from the Responses API.
     *
     * @param resource $stream
     * @param array $warnings
     * @return \Generator
     */
    private function processStream($stream, array $warnings): \Generator
    {
        yield ['type' => 'stream-start', 'warnings' => $warnings];

        $isActiveText = false;
        $isActiveReasoning = false;
        $responseId = null;
        $modelId = null;
        $serviceTier = null;
        $finishReason = 'other';
        $usage = null;
        $ongoingToolCalls = [];

        foreach (OpenAIUtils::parseSSEStream($stream) as $chunk) {
            $type = $chunk['type'] ?? '';

            switch ($type) {
                case 'response.created':
                case 'response.in_progress':
                    $responseData = $chunk['response'] ?? $chunk;
                    $responseId = $responseData['id'] ?? $responseId;
                    $modelId = $responseData['model'] ?? $modelId;
                    $serviceTier = $responseData['service_tier'] ?? $serviceTier;

                    yield [
                        'type' => 'response-metadata',
                        'id' => $responseId,
                        'modelId' => $modelId ?? $this->modelId,
                    ];
                    break;

                case 'response.output_item.added':
                    $item = $chunk['item'] ?? [];
                    $itemType = $item['type'] ?? '';

                    if ($itemType === 'function_call') {
                        $outputIndex = $chunk['output_index'] ?? 0;
                        $ongoingToolCalls[$outputIndex] = [
                            'id' => $item['call_id'] ?? $item['id'] ?? '',
                            'name' => $item['name'] ?? '',
                            'arguments' => '',
                        ];

                        yield [
                            'type' => 'tool-input-start',
                            'id' => $ongoingToolCalls[$outputIndex]['id'],
                            'toolName' => $ongoingToolCalls[$outputIndex]['name'],
                        ];
                    }
                    break;

                case 'response.output_text.delta':
                    if (!$isActiveText) {
                        yield ['type' => 'text-start', 'id' => '0'];
                        $isActiveText = true;
                    }
                    yield [
                        'type' => 'text-delta',
                        'id' => '0',
                        'textDelta' => $chunk['delta'] ?? '',
                    ];
                    break;

                case 'response.output_text.done':
                    // Annotations at text completion
                    if (isset($chunk['annotations'])) {
                        foreach ($chunk['annotations'] as $annotation) {
                            if (isset($annotation['url'])) {
                                yield [
                                    'type' => 'source',
                                    'sourceType' => 'url',
                                    'id' => uniqid('src_'),
                                    'url' => $annotation['url'],
                                    'title' => $annotation['title'] ?? '',
                                ];
                            }
                        }
                    }
                    break;

                case 'response.reasoning_summary_text.delta':
                    if (!$isActiveReasoning) {
                        yield ['type' => 'reasoning-start', 'id' => 'reasoning-0'];
                        $isActiveReasoning = true;
                    }
                    yield [
                        'type' => 'reasoning-delta',
                        'id' => 'reasoning-0',
                        'delta' => $chunk['delta'] ?? '',
                    ];
                    break;

                case 'response.function_call_arguments.delta':
                    $outputIndex = $chunk['output_index'] ?? 0;
                    if (isset($ongoingToolCalls[$outputIndex])) {
                        $delta = $chunk['delta'] ?? '';
                        $ongoingToolCalls[$outputIndex]['arguments'] .= $delta;

                        yield [
                            'type' => 'tool-input-delta',
                            'id' => $ongoingToolCalls[$outputIndex]['id'],
                            'delta' => $delta,
                        ];
                    }
                    break;

                case 'response.function_call_arguments.done':
                    $outputIndex = $chunk['output_index'] ?? 0;
                    if (isset($ongoingToolCalls[$outputIndex])) {
                        $toolCall = $ongoingToolCalls[$outputIndex];

                        yield [
                            'type' => 'tool-input-end',
                            'id' => $toolCall['id'],
                        ];

                        yield [
                            'type' => 'tool-call',
                            'toolCallId' => $toolCall['id'] ?: uniqid('call_'),
                            'toolName' => $toolCall['name'],
                            'input' => $toolCall['arguments'],
                        ];

                        unset($ongoingToolCalls[$outputIndex]);
                    }
                    break;

                case 'response.output_item.done':
                    $item = $chunk['item'] ?? [];
                    $itemType = $item['type'] ?? '';

                    // Server-executed tool results
                    if ($itemType === 'web_search_call') {
                        yield [
                            'type' => 'tool-result',
                            'toolCallId' => $item['id'] ?? '',
                            'toolName' => 'openai.web_search',
                            'result' => ['status' => $item['status'] ?? 'completed'],
                        ];
                    } elseif ($itemType === 'code_interpreter_call') {
                        yield [
                            'type' => 'tool-result',
                            'toolCallId' => $item['id'] ?? '',
                            'toolName' => 'openai.code_interpreter',
                            'result' => ['outputs' => $item['outputs'] ?? []],
                        ];
                    } elseif ($itemType === 'image_generation_call') {
                        yield [
                            'type' => 'tool-result',
                            'toolCallId' => $item['id'] ?? '',
                            'toolName' => 'openai.image_generation',
                            'result' => ['result' => $item['result'] ?? ''],
                        ];
                    } elseif ($itemType === 'file_search_call') {
                        yield [
                            'type' => 'tool-result',
                            'toolCallId' => $item['id'] ?? '',
                            'toolName' => 'openai.file_search',
                            'result' => [
                                'results' => $item['results'] ?? [],
                                'status' => $item['status'] ?? 'completed',
                            ],
                        ];
                    }
                    break;

                case 'response.completed':
                    $responseData = $chunk['response'] ?? [];
                    $responseId = $responseData['id'] ?? $responseId;

                    // Check incomplete_details
                    $incompleteReason = $responseData['incomplete_details']['reason'] ?? null;
                    $hasToolCalls = $this->hasToolCallsInOutput($responseData['output'] ?? []);
                    $finishReason = OpenAIUtils::mapResponsesFinishReason($incompleteReason, $hasToolCalls);

                    // Extract usage
                    $usageData = $responseData['usage'] ?? [];
                    $inputTokens = $usageData['input_tokens'] ?? 0;
                    $outputTokens = $usageData['output_tokens'] ?? 0;
                    $cachedTokens = $usageData['input_tokens_details']['cached_tokens'] ?? null;
                    $reasoningTokens = $usageData['output_tokens_details']['reasoning_tokens'] ?? null;

                    $usage = new LanguageModelUsage(
                        inputTokens: $inputTokens,
                        outputTokens: $outputTokens,
                        cachedInputTokens: $cachedTokens,
                        reasoningTokens: $reasoningTokens,
                    );

                    $serviceTier = $responseData['service_tier'] ?? $serviceTier;
                    break;
            }
        }

        // End active text/reasoning
        if ($isActiveReasoning) {
            yield ['type' => 'reasoning-end', 'id' => 'reasoning-0'];
        }
        if ($isActiveText) {
            yield ['type' => 'text-end', 'id' => '0'];
        }

        // Provider metadata
        $providerMetadata = ['openai' => []];
        if ($responseId !== null) {
            $providerMetadata['openai']['responseId'] = $responseId;
        }
        if ($serviceTier !== null) {
            $providerMetadata['openai']['serviceTier'] = $serviceTier;
        }

        yield [
            'type' => 'finish',
            'finishReason' => $finishReason,
            'usage' => $usage ?? new LanguageModelUsage(),
            'providerMetadata' => !empty($providerMetadata['openai']) ? $providerMetadata : null,
        ];

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Prepare tools for the Responses API.
     */
    private function prepareTools(?array $tools, ?array $toolChoice, array $providerOptions): array
    {
        $openaiTools = [];
        $warnings = [];
        $toolNameMapping = [];

        if (empty($tools)) {
            return ['tools' => null, 'toolChoice' => null, 'warnings' => $warnings, 'toolNameMapping' => $toolNameMapping];
        }

        foreach ($tools as $tool) {
            $type = $tool['type'] ?? 'function';

            if ($type === 'function') {
                $strict = $providerOptions['strictJsonSchema'] ?? true;
                $rawParams = $tool['inputSchema'] ?? $tool['parameters'] ?? new \stdClass();
                $params = OpenAIUtils::prepareJsonSchema($rawParams, $strict);

                $openaiTools[] = [
                    'type' => 'function',
                    'name' => $tool['name'] ?? '',
                    'description' => $tool['description'] ?? '',
                    'parameters' => $params,
                    'strict' => $strict,
                ];
            } else {
                $warnings[] = [
                    'type' => 'unsupported',
                    'feature' => "tool-type:{$type}",
                ];
            }
        }

        // Tool choice mapping for Responses API
        $mappedToolChoice = null;
        if ($toolChoice !== null) {
            $choiceType = $toolChoice['type'] ?? null;
            $mappedToolChoice = match ($choiceType) {
                'auto' => 'auto',
                'required' => 'required',
                'none' => 'none',
                'tool' => [
                    'type' => 'function',
                    'name' => $toolChoice['toolName'] ?? $toolChoice['name'] ?? '',
                ],
                default => $choiceType,
            };
        }

        return [
            'tools' => $openaiTools ?: null,
            'toolChoice' => $mappedToolChoice,
            'warnings' => $warnings,
            'toolNameMapping' => $toolNameMapping,
        ];
    }

    /**
     * Get OpenAI provider-specific tools from provider options.
     *
     * These are server-side tools hosted by OpenAI (web search, code interpreter, etc.)
     */
    private function getProviderTools(array $providerOptions): array
    {
        $tools = [];

        // Web search tool
        if (isset($providerOptions['webSearch'])) {
            $webSearch = $providerOptions['webSearch'];
            $tool = ['type' => 'web_search_preview'];

            if (is_array($webSearch)) {
                if (isset($webSearch['searchContextSize'])) {
                    $tool['search_context_size'] = $webSearch['searchContextSize'];
                }
                if (isset($webSearch['userLocation'])) {
                    $tool['user_location'] = $webSearch['userLocation'];
                }
            }

            $tools[] = $tool;
        }

        // File search tool
        if (isset($providerOptions['fileSearch'])) {
            $fileSearch = $providerOptions['fileSearch'];
            $tool = ['type' => 'file_search'];

            if (is_array($fileSearch)) {
                if (isset($fileSearch['vectorStoreIds'])) {
                    $tool['vector_store_ids'] = $fileSearch['vectorStoreIds'];
                }
                if (isset($fileSearch['maxNumResults'])) {
                    $tool['max_num_results'] = $fileSearch['maxNumResults'];
                }
                if (isset($fileSearch['rankingOptions'])) {
                    $tool['ranking_options'] = $fileSearch['rankingOptions'];
                }
                if (isset($fileSearch['filters'])) {
                    $tool['filters'] = $fileSearch['filters'];
                }
            }

            $tools[] = $tool;
        }

        // Code interpreter tool
        if (isset($providerOptions['codeInterpreter'])) {
            $codeInterpreter = $providerOptions['codeInterpreter'];
            $tool = ['type' => 'code_interpreter'];

            if (is_array($codeInterpreter)) {
                if (isset($codeInterpreter['container'])) {
                    $tool['container'] = $codeInterpreter['container'];
                }
            }

            $tools[] = $tool;
        }

        // Image generation tool
        if (isset($providerOptions['imageGeneration'])) {
            $imageGen = $providerOptions['imageGeneration'];
            $tool = ['type' => 'image_generation'];

            if (is_array($imageGen)) {
                if (isset($imageGen['background'])) {
                    $tool['background'] = $imageGen['background'];
                }
                if (isset($imageGen['inputImageMask'])) {
                    $tool['input_image_mask'] = $imageGen['inputImageMask'];
                }
                if (isset($imageGen['model'])) {
                    $tool['model'] = $imageGen['model'];
                }
                if (isset($imageGen['moderation'])) {
                    $tool['moderation'] = $imageGen['moderation'];
                }
                if (isset($imageGen['outputCompression'])) {
                    $tool['output_compression'] = $imageGen['outputCompression'];
                }
                if (isset($imageGen['outputFormat'])) {
                    $tool['output_format'] = $imageGen['outputFormat'];
                }
                if (isset($imageGen['partialImages'])) {
                    $tool['partial_images'] = $imageGen['partialImages'];
                }
                if (isset($imageGen['quality'])) {
                    $tool['quality'] = $imageGen['quality'];
                }
                if (isset($imageGen['size'])) {
                    $tool['size'] = $imageGen['size'];
                }
            }

            $tools[] = $tool;
        }

        // MCP tool
        if (isset($providerOptions['mcp'])) {
            foreach ((array) $providerOptions['mcp'] as $mcpConfig) {
                $tool = ['type' => 'mcp'];

                if (isset($mcpConfig['serverLabel'])) {
                    $tool['server_label'] = $mcpConfig['serverLabel'];
                }
                if (isset($mcpConfig['serverUrl'])) {
                    $tool['server_url'] = $mcpConfig['serverUrl'];
                }
                if (isset($mcpConfig['allowedTools'])) {
                    $tool['allowed_tools'] = $mcpConfig['allowedTools'];
                }
                if (isset($mcpConfig['headers'])) {
                    $tool['headers'] = $mcpConfig['headers'];
                }
                if (isset($mcpConfig['requireApproval'])) {
                    $tool['require_approval'] = $mcpConfig['requireApproval'];
                }

                $tools[] = $tool;
            }
        }

        return ['tools' => $tools];
    }

    /**
     * Check if a model ID corresponds to a reasoning model.
     */
    private function isReasoningModel(string $modelId): bool
    {
        return (bool) preg_match('/^(o1|o3|o4)/', $modelId);
    }

    /**
     * Extract a schema name from a JSON Schema definition.
     */
    private function schemaName(array $schema): ?string
    {
        return $schema['title'] ?? $schema['$id'] ?? null;
    }

    /**
     * Check if output contains function_call items.
     */
    private function hasToolCallsInOutput(array $output): bool
    {
        foreach ($output as $item) {
            if (($item['type'] ?? '') === 'function_call') {
                return true;
            }
        }
        return false;
    }
}

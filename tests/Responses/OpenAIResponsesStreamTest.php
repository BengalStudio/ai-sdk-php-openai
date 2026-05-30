<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests\Responses;

use BengalStudio\AI\OpenAI\Responses\OpenAIResponsesLanguageModel;
use BengalStudio\AI\OpenAI\Support\OpenAIConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tool-input streaming emission for the Responses API.
 *
 * Feeds a canned SSE body straight into the model's private processStream()
 * generator (the same generator doStream() returns) and asserts the yielded
 * language-model part sequence. This is the foundation the core loop, the
 * UI-stream serializer and the frontend skeletons all rest on, and it had
 * zero coverage before.
 */
class OpenAIResponsesStreamTest extends TestCase
{
    private function model(): OpenAIResponsesLanguageModel
    {
        return new OpenAIResponsesLanguageModel('gpt-4o', new OpenAIConfig(provider: 'openai'));
    }

    /**
     * Build an SSE body from decoded event arrays, terminated by [DONE].
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function sse(array $events): string
    {
        $out = '';
        foreach ($events as $event) {
            $out .= 'data: ' . json_encode($event) . "\n\n";
        }
        return $out . "data: [DONE]\n\n";
    }

    /**
     * Drive the model's private processStream() over a canned SSE body.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    private function streamParts(object $model, array $events): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $this->sse($events));
        rewind($stream);

        $method = new \ReflectionMethod($model, 'processStream');
        $method->setAccessible(true);

        return iterator_to_array($method->invoke($model, $stream, []), false);
    }

    /**
     * Keep only the tool lifecycle parts, in order.
     *
     * @param array<int, array<string, mixed>> $parts
     * @return array<int, array<string, mixed>>
     */
    private function toolParts(array $parts): array
    {
        $keep = ['tool-input-start', 'tool-input-delta', 'tool-input-end', 'tool-call'];

        return array_values(array_filter($parts, fn($p) => in_array($p['type'] ?? '', $keep, true)));
    }

    public function testSingleToolCallEmitsFullInputStreamingLifecycle(): void
    {
        $events = [
            ['type' => 'response.created', 'response' => ['id' => 'resp_1', 'model' => 'gpt-4o']],
            [
                'type' => 'response.output_item.added',
                'output_index' => 0,
                'item' => ['type' => 'function_call', 'call_id' => 'call_abc', 'name' => 'getWeather'],
            ],
            ['type' => 'response.function_call_arguments.delta', 'output_index' => 0, 'delta' => '{"city"'],
            ['type' => 'response.function_call_arguments.delta', 'output_index' => 0, 'delta' => ':"NYC"}'],
            ['type' => 'response.function_call_arguments.done', 'output_index' => 0],
        ];

        $tool = $this->toolParts($this->streamParts($this->model(), $events));

        $this->assertSame(
            ['tool-input-start', 'tool-input-delta', 'tool-input-delta', 'tool-input-end', 'tool-call'],
            array_column($tool, 'type')
        );

        // tool-input-start carries the call id + name (language-model `id` key).
        $this->assertSame('call_abc', $tool[0]['id']);
        $this->assertSame('getWeather', $tool[0]['toolName']);

        // Deltas carry the RAW JSON fragments under `delta`.
        $this->assertSame('{"city"', $tool[1]['delta']);
        $this->assertSame(':"NYC"}', $tool[2]['delta']);

        // tool-input-end carries the same id.
        $this->assertSame('call_abc', $tool[3]['id']);

        // tool-call carries the reassembled arguments (raw string).
        $this->assertSame('call_abc', $tool[4]['toolCallId']);
        $this->assertSame('getWeather', $tool[4]['toolName']);
        $this->assertSame('{"city":"NYC"}', $tool[4]['input']);
    }

    public function testConcurrentToolCallsKeepIdsSeparate(): void
    {
        $events = [
            ['type' => 'response.created', 'response' => ['id' => 'resp_2', 'model' => 'gpt-4o']],
            [
                'type' => 'response.output_item.added',
                'output_index' => 0,
                'item' => ['type' => 'function_call', 'call_id' => 'call_a', 'name' => 'getWeather'],
            ],
            [
                'type' => 'response.output_item.added',
                'output_index' => 1,
                'item' => ['type' => 'function_call', 'call_id' => 'call_b', 'name' => 'getTime'],
            ],
            ['type' => 'response.function_call_arguments.delta', 'output_index' => 0, 'delta' => '{"city":"NYC"}'],
            ['type' => 'response.function_call_arguments.delta', 'output_index' => 1, 'delta' => '{"tz":"EST"}'],
            ['type' => 'response.function_call_arguments.done', 'output_index' => 0],
            ['type' => 'response.function_call_arguments.done', 'output_index' => 1],
        ];

        $parts = $this->streamParts($this->model(), $events);

        $calls = array_values(array_filter($parts, fn($p) => ($p['type'] ?? '') === 'tool-call'));
        $this->assertCount(2, $calls);

        $byId = [];
        foreach ($calls as $call) {
            $byId[$call['toolCallId']] = $call;
        }

        $this->assertSame('getWeather', $byId['call_a']['toolName']);
        $this->assertSame('{"city":"NYC"}', $byId['call_a']['input']);
        $this->assertSame('getTime', $byId['call_b']['toolName']);
        $this->assertSame('{"tz":"EST"}', $byId['call_b']['input']);
    }
}

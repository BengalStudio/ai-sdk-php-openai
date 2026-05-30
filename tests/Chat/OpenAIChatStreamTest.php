<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests\Chat;

use BengalStudio\AI\OpenAI\Chat\OpenAIChatLanguageModel;
use BengalStudio\AI\OpenAI\Support\OpenAIConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tool-input streaming emission for the Chat Completions API.
 *
 * Feeds a canned SSE body straight into the model's private processStream()
 * generator and asserts the yielded language-model part sequence, driven by
 * `delta.tool_calls[].function.arguments` fragments. Symmetric with the
 * Responses API test; had zero coverage before.
 */
class OpenAIChatStreamTest extends TestCase
{
    private function model(): OpenAIChatLanguageModel
    {
        return new OpenAIChatLanguageModel('gpt-4o', new OpenAIConfig(provider: 'openai'));
    }

    /**
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
     * @param array<int, array<string, mixed>> $parts
     * @return array<int, array<string, mixed>>
     */
    private function toolParts(array $parts): array
    {
        $keep = ['tool-input-start', 'tool-input-delta', 'tool-input-end', 'tool-call'];

        return array_values(array_filter($parts, fn($p) => in_array($p['type'] ?? '', $keep, true)));
    }

    /**
     * One streamed chunk carrying a `choices[0].delta` payload.
     *
     * @param array<string, mixed> $delta
     */
    private function chunk(array $delta, ?string $finishReason = null): array
    {
        return [
            'id' => 'chatcmpl_1',
            'model' => 'gpt-4o',
            'created' => 1,
            'choices' => [['delta' => $delta, 'finish_reason' => $finishReason]],
        ];
    }

    public function testSingleToolCallEmitsFullInputStreamingLifecycle(): void
    {
        $events = [
            $this->chunk([
                'role' => 'assistant',
                'tool_calls' => [['index' => 0, 'id' => 'call_abc', 'function' => ['name' => 'getWeather', 'arguments' => '']]],
            ]),
            $this->chunk(['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"city"']]]]),
            $this->chunk(['tool_calls' => [['index' => 0, 'function' => ['arguments' => ':"NYC"}']]]]),
            $this->chunk([], 'tool_calls'),
        ];

        $tool = $this->toolParts($this->streamParts($this->model(), $events));

        $this->assertSame(
            ['tool-input-start', 'tool-input-delta', 'tool-input-delta', 'tool-input-end', 'tool-call'],
            array_column($tool, 'type')
        );

        $this->assertSame('call_abc', $tool[0]['id']);
        $this->assertSame('getWeather', $tool[0]['toolName']);
        $this->assertSame('{"city"', $tool[1]['delta']);
        $this->assertSame(':"NYC"}', $tool[2]['delta']);
        $this->assertSame('call_abc', $tool[3]['id']);
        $this->assertSame('call_abc', $tool[4]['toolCallId']);
        $this->assertSame('getWeather', $tool[4]['toolName']);
        $this->assertSame('{"city":"NYC"}', $tool[4]['input']);
    }

    public function testConcurrentToolCallsKeepIdsSeparate(): void
    {
        $events = [
            $this->chunk(['tool_calls' => [['index' => 0, 'id' => 'call_a', 'function' => ['name' => 'getWeather', 'arguments' => '']]]]),
            $this->chunk(['tool_calls' => [['index' => 1, 'id' => 'call_b', 'function' => ['name' => 'getTime', 'arguments' => '']]]]),
            $this->chunk(['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"city":"NYC"}']]]]),
            $this->chunk(['tool_calls' => [['index' => 1, 'function' => ['arguments' => '{"tz":"EST"}']]]]),
            $this->chunk([], 'tool_calls'),
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

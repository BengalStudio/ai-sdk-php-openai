<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests\Support;

use BengalStudio\AI\OpenAI\Support\OpenAIUtils;
use BengalStudio\AI\Types\FinishReason;
use PHPUnit\Framework\TestCase;

class OpenAIUtilsTest extends TestCase
{
    // mapFinishReason tests

    public function testMapFinishReasonStop(): void
    {
        $this->assertSame(FinishReason::Stop->value, OpenAIUtils::mapFinishReason('stop'));
    }

    public function testMapFinishReasonLength(): void
    {
        $this->assertSame(FinishReason::Length->value, OpenAIUtils::mapFinishReason('length'));
    }

    public function testMapFinishReasonMaxTokens(): void
    {
        $this->assertSame(FinishReason::Length->value, OpenAIUtils::mapFinishReason('max_tokens'));
    }

    public function testMapFinishReasonToolCalls(): void
    {
        $this->assertSame(FinishReason::ToolCalls->value, OpenAIUtils::mapFinishReason('tool_calls'));
    }

    public function testMapFinishReasonFunctionCall(): void
    {
        $this->assertSame(FinishReason::ToolCalls->value, OpenAIUtils::mapFinishReason('function_call'));
    }

    public function testMapFinishReasonContentFilter(): void
    {
        $this->assertSame(FinishReason::ContentFilter->value, OpenAIUtils::mapFinishReason('content_filter'));
    }

    public function testMapFinishReasonNull(): void
    {
        $this->assertSame(FinishReason::Other->value, OpenAIUtils::mapFinishReason(null));
    }

    public function testMapFinishReasonUnknown(): void
    {
        $this->assertSame(FinishReason::Other->value, OpenAIUtils::mapFinishReason('something_else'));
    }

    // mapResponsesFinishReason tests

    public function testMapResponsesFinishReasonNullIsStop(): void
    {
        $this->assertSame(FinishReason::Stop->value, OpenAIUtils::mapResponsesFinishReason(null));
    }

    public function testMapResponsesFinishReasonNullWithToolCalls(): void
    {
        $this->assertSame(FinishReason::ToolCalls->value, OpenAIUtils::mapResponsesFinishReason(null, true));
    }

    public function testMapResponsesFinishReasonMaxOutputTokens(): void
    {
        $this->assertSame(FinishReason::Length->value, OpenAIUtils::mapResponsesFinishReason('max_output_tokens'));
    }

    public function testMapResponsesFinishReasonContentFilter(): void
    {
        $this->assertSame(FinishReason::ContentFilter->value, OpenAIUtils::mapResponsesFinishReason('content_filter'));
    }

    public function testMapResponsesFinishReasonUnknown(): void
    {
        $this->assertSame(FinishReason::Other->value, OpenAIUtils::mapResponsesFinishReason('something'));
    }

    // combineHeaders tests

    public function testCombineHeadersMerges(): void
    {
        $result = OpenAIUtils::combineHeaders(
            ['X-A' => '1', 'X-B' => '2'],
            ['X-B' => '3', 'X-C' => '4'],
        );

        $this->assertSame(['X-A' => '1', 'X-B' => '3', 'X-C' => '4'], $result);
    }

    public function testCombineHeadersSkipsNull(): void
    {
        $result = OpenAIUtils::combineHeaders(
            ['X-A' => '1'],
            ['X-A' => null, 'X-B' => '2'],
        );

        $this->assertSame(['X-A' => '1', 'X-B' => '2'], $result);
    }

    public function testCombineHeadersEmpty(): void
    {
        $this->assertSame([], OpenAIUtils::combineHeaders());
    }

    // parseSSEStream tests

    public function testParseSSEStreamYieldsData(): void
    {
        $data = "data: {\"id\":\"1\",\"text\":\"hello\"}\n\ndata: {\"id\":\"2\",\"text\":\"world\"}\n\ndata: [DONE]\n\n";
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $data);
        rewind($stream);

        $results = iterator_to_array(OpenAIUtils::parseSSEStream($stream));
        fclose($stream);

        $this->assertCount(2, $results);
        $this->assertSame('hello', $results[0]['text']);
        $this->assertSame('world', $results[1]['text']);
    }

    public function testParseSSEStreamEmptyStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '');
        rewind($stream);

        $results = iterator_to_array(OpenAIUtils::parseSSEStream($stream));
        fclose($stream);

        $this->assertSame([], $results);
    }

    public function testParseSSEStreamSkipsNonDataLines(): void
    {
        $data = "event: message\ndata: {\"ok\":true}\n\n";
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $data);
        rewind($stream);

        $results = iterator_to_array(OpenAIUtils::parseSSEStream($stream));
        fclose($stream);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['ok']);
    }
}

<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Tests\Support;

use AISdkPhp\OpenAI\Support\OpenAIErrorHandler;
use BengalStudio\AI\Exceptions\APICallException;
use PHPUnit\Framework\TestCase;

class OpenAIErrorHandlerTest extends TestCase
{
    public function testHandleErrorResponseWithJsonError(): void
    {
        $this->expectException(APICallException::class);
        $this->expectExceptionMessage('The model is overloaded');

        OpenAIErrorHandler::handleErrorResponse(
            429,
            '{"error":{"message":"The model is overloaded"}}',
            'https://api.openai.com/v1/chat/completions',
        );
    }

    public function testHandleErrorResponseWithStringError(): void
    {
        $this->expectException(APICallException::class);
        $this->expectExceptionMessage('Something went wrong');

        OpenAIErrorHandler::handleErrorResponse(
            500,
            '{"error":"Something went wrong"}',
            'https://api.openai.com/v1/responses',
        );
    }

    public function testHandleErrorResponsePreservesStatusCode(): void
    {
        try {
            OpenAIErrorHandler::handleErrorResponse(
                403,
                '{"error":{"message":"Forbidden"}}',
                'https://api.openai.com/v1/chat/completions',
            );
            $this->fail('Expected APICallException');
        } catch (APICallException $e) {
            $this->assertSame(403, $e->statusCode);
            $this->assertSame('https://api.openai.com/v1/chat/completions', $e->url);
            $this->assertStringContainsString('Forbidden', $e->getMessage());
        }
    }

    public function testHandleErrorResponseWithInvalidJson(): void
    {
        try {
            OpenAIErrorHandler::handleErrorResponse(500, 'not json', 'https://example.com');
            $this->fail('Expected APICallException');
        } catch (APICallException $e) {
            $this->assertSame('OpenAI API error', $e->getMessage());
        }
    }

    public function testIsRetryable(): void
    {
        $this->assertTrue(OpenAIErrorHandler::isRetryable(429));
        $this->assertTrue(OpenAIErrorHandler::isRetryable(500));
        $this->assertTrue(OpenAIErrorHandler::isRetryable(502));
        $this->assertTrue(OpenAIErrorHandler::isRetryable(503));
        $this->assertTrue(OpenAIErrorHandler::isRetryable(504));
        $this->assertTrue(OpenAIErrorHandler::isRetryable(408));
    }

    public function testIsNotRetryable(): void
    {
        $this->assertFalse(OpenAIErrorHandler::isRetryable(400));
        $this->assertFalse(OpenAIErrorHandler::isRetryable(401));
        $this->assertFalse(OpenAIErrorHandler::isRetryable(403));
        $this->assertFalse(OpenAIErrorHandler::isRetryable(404));
        $this->assertFalse(OpenAIErrorHandler::isRetryable(422));
    }
}

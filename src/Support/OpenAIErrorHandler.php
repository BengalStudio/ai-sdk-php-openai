<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Support;

use BengalStudio\AI\Exceptions\APICallException;

/**
 * Handles OpenAI API error responses.
 */
class OpenAIErrorHandler
{
    /**
     * Parse an OpenAI error response and throw an appropriate exception.
     *
     * @param int $statusCode HTTP status code.
     * @param string $responseBody Raw response body.
     * @param string $url The request URL.
     * @throws APICallException
     */
    public static function handleErrorResponse(int $statusCode, string $responseBody, string $url): void
    {
        $message = 'OpenAI API error';
        $body = json_decode($responseBody, true);

        if (isset($body['error']['message'])) {
            $message = $body['error']['message'];
        } elseif (isset($body['error']) && is_string($body['error'])) {
            $message = $body['error'];
        }

        throw new APICallException(
            message: $message,
            statusCode: $statusCode,
            responseBody: $responseBody,
            url: $url,
        );
    }

    /**
     * Check if a status code indicates a retryable error.
     */
    public static function isRetryable(int $statusCode): bool
    {
        return in_array($statusCode, [408, 429, 500, 502, 503, 504], true);
    }
}

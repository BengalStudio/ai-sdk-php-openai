<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Support;

use BengalStudio\AI\Types\FinishReason;

/**
 * Utility functions for OpenAI provider.
 */
class OpenAIUtils
{
    /**
     * Map an OpenAI finish_reason to a FinishReason enum.
     */
    public static function mapFinishReason(?string $finishReason): string
    {
        return match ($finishReason) {
            'stop' => FinishReason::Stop->value,
            'length' => FinishReason::Length->value,
            'tool_calls', 'function_call' => FinishReason::ToolCalls->value,
            'content_filter' => FinishReason::ContentFilter->value,
            'max_tokens' => FinishReason::Length->value,
            default => FinishReason::Other->value,
        };
    }

    /**
     * Map OpenAI Responses API incomplete_details reason to FinishReason.
     */
    public static function mapResponsesFinishReason(?string $reason, bool $hasToolCalls = false): string
    {
        if ($reason === null && $hasToolCalls) {
            return FinishReason::ToolCalls->value;
        }

        return match ($reason) {
            null => FinishReason::Stop->value,
            'max_output_tokens' => FinishReason::Length->value,
            'content_filter' => FinishReason::ContentFilter->value,
            default => FinishReason::Other->value,
        };
    }

    /**
     * Parse a Server-Sent Events stream into individual data chunks.
     *
     * @param resource $stream The raw HTTP response stream.
     * @return \Generator<array> Yields decoded JSON data from each SSE event.
     */
    public static function parseSSEStream($stream): \Generator
    {
        $buffer = '';

        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                break;
            }

            $buffer .= $chunk;

            // Process complete lines
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                $line = rtrim($line, "\r");

                if ($line === '') {
                    continue;
                }

                // Check for data prefixed lines
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);

                    // Stream end signal
                    if ($data === '[DONE]') {
                        return;
                    }

                    $decoded = json_decode($data, true);
                    if ($decoded !== null) {
                        yield $decoded;
                    }
                }
            }
        }
    }

    /**
     * Combine multiple header arrays, later values override earlier.
     */
    public static function combineHeaders(array ...$headerSets): array
    {
        $combined = [];
        foreach ($headerSets as $headers) {
            foreach ($headers as $key => $value) {
                if ($value !== null) {
                    $combined[$key] = $value;
                }
            }
        }
        return $combined;
    }
}

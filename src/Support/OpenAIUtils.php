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
     * Uses fgets() to read one line at a time instead of fread() which
     * can batch multiple SSE events into a single read. This ensures
     * each event is yielded to the consumer as soon as it arrives from
     * the upstream, enabling smooth real-time streaming.
     *
     * @param resource $stream The raw HTTP response stream.
     * @return \Generator<array> Yields decoded JSON data from each SSE event.
     */
    public static function parseSSEStream($stream): \Generator
    {
        while (!feof($stream)) {
            // fgets reads one line at a time (up to newline or buffer limit).
            // This is critical for streaming: it returns each SSE line as
            // soon as it arrives, rather than buffering multiple events.
            $line = fgets($stream, 65536);
            if ($line === false) {
                break;
            }

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            // SSE data lines
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

    /**
     * Prepare a JSON schema for OpenAI strict mode.
     *
     * When strict mode is enabled OpenAI requires every object in the
     * schema to include `"additionalProperties": false`. This method
     * recursively injects that property so users don't have to
     * remember to add it themselves.
     *
     * @param array|object $schema The JSON Schema to prepare.
     * @param bool $strict Whether strict mode is active.
     * @return array|object The prepared schema (same type as input).
     */
    public static function prepareJsonSchema(array|object $schema, bool $strict = true): array|object
    {
        if (!$strict) {
            return $schema;
        }

        if ($schema instanceof \stdClass) {
            return $schema; // empty schema placeholder
        }

        return self::injectAdditionalProperties($schema);
    }

    /**
     * Recursively inject `additionalProperties: false` on every
     * object-typed node in a JSON Schema.
     */
    private static function injectAdditionalProperties(array $schema): array
    {
        $type = $schema['type'] ?? null;

        // Object type → ensure additionalProperties is set
        if ($type === 'object') {
            if (!array_key_exists('additionalProperties', $schema)) {
                $schema['additionalProperties'] = false;
            }

            // Recurse into properties
            if (isset($schema['properties']) && is_array($schema['properties'])) {
                foreach ($schema['properties'] as $key => $prop) {
                    if (is_array($prop)) {
                        $schema['properties'][$key] = self::injectAdditionalProperties($prop);
                    }
                }
            }
        }

        // Array → recurse into items
        if ($type === 'array' && isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::injectAdditionalProperties($schema['items']);
        }

        // anyOf / oneOf / allOf
        foreach (['anyOf', 'oneOf', 'allOf'] as $combiner) {
            if (isset($schema[$combiner]) && is_array($schema[$combiner])) {
                foreach ($schema[$combiner] as $i => $sub) {
                    if (is_array($sub)) {
                        $schema[$combiner][$i] = self::injectAdditionalProperties($sub);
                    }
                }
            }
        }

        return $schema;
    }
}

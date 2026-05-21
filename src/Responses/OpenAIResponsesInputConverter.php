<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Responses;

use BengalStudio\AI\Types\Message;

/**
 * Converts AI SDK messages to OpenAI Responses API input format.
 *
 * The Responses API has a different input structure than Chat Completions,
 * using an "input" array with different message types.
 */
class OpenAIResponsesInputConverter
{
    /**
     * Convert AI SDK messages to Responses API input format.
     *
     * @param array<Message> $prompt The AI SDK messages.
     * @param string $systemMessageMode How to handle system messages: 'system', 'developer', or 'remove'.
     * @return array{input: array|string, instructions: string|null, warnings: array}
     */
    public static function convert(array $prompt, string $systemMessageMode = 'system'): array
    {
        $input = [];
        $instructions = null;
        $warnings = [];

        foreach ($prompt as $message) {
            switch ($message->role) {
                case 'system':
                    if ($systemMessageMode === 'remove') {
                        $warnings[] = [
                            'type' => 'other',
                            'message' => 'System messages removed for this model.',
                        ];
                        break;
                    }

                    // In responses API, system messages become 'instructions' parameter
                    // or developer messages in the input
                    if ($systemMessageMode === 'developer') {
                        $text = self::extractText($message->content);
                        $input[] = [
                            'role' => 'developer',
                            'content' => $text,
                        ];
                    } else {
                        // Use as instructions (system prompt)
                        $instructions = self::extractText($message->content);
                    }
                    break;

                case 'user':
                    $input[] = self::convertUserMessage($message, $warnings);
                    break;

                case 'assistant':
                    $assistantItems = self::convertAssistantMessage($message);
                    foreach ($assistantItems as $item) {
                        $input[] = $item;
                    }
                    break;

                case 'tool':
                    $toolMessages = self::convertToolMessage($message);
                    foreach ($toolMessages as $tm) {
                        $input[] = $tm;
                    }
                    break;
            }
        }

        return [
            'input' => $input,
            'instructions' => $instructions,
            'warnings' => $warnings,
        ];
    }

    /**
     * Extract text from string or multi-part content.
     */
    private static function extractText(string|array $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        $text = '';
        foreach ($content as $part) {
            if (isset($part['type']) && $part['type'] === 'text') {
                $text .= $part['text'] ?? '';
            }
        }
        return $text;
    }

    /**
     * Convert a user message to Responses API format.
     */
    private static function convertUserMessage(Message $message, array &$warnings): array
    {
        if (is_string($message->content)) {
            return [
                'role' => 'user',
                'content' => $message->content,
            ];
        }

        // Multi-part content
        $parts = [];
        foreach ($message->content as $part) {
            $type = $part['type'] ?? 'text';

            switch ($type) {
                case 'text':
                    $parts[] = [
                        'type' => 'input_text',
                        'text' => $part['text'] ?? '',
                    ];
                    break;

                case 'image':
                    $url = $part['image'] ?? $part['url'] ?? null;
                    $data = $part['data'] ?? null;
                    $mediaType = $part['mediaType'] ?? $part['mimeType'] ?? 'image/png';

                    if ($data !== null) {
                        $base64 = is_string($data) ? $data : base64_encode($data);
                        $url = "data:{$mediaType};base64,{$base64}";
                    }

                    if ($url !== null) {
                        $parts[] = [
                            'type' => 'input_image',
                            'image_url' => (string) $url,
                            ...(isset($part['detail']) ? ['detail' => $part['detail']] : []),
                        ];
                    }
                    break;

                case 'file':
                    $url = $part['data'] ?? $part['url'] ?? null;
                    $mediaType = $part['mediaType'] ?? $part['mimeType'] ?? '';

                    if ($url !== null) {
                        if (str_starts_with($mediaType, 'application/pdf')) {
                            $parts[] = [
                                'type' => 'input_file',
                                'file_url' => (string) $url,
                            ];
                        } elseif (str_starts_with($mediaType, 'image/')) {
                            $parts[] = [
                                'type' => 'input_image',
                                'image_url' => (string) $url,
                            ];
                        } else {
                            $parts[] = [
                                'type' => 'input_text',
                                'text' => "[Attached file: {$url}]",
                            ];
                        }
                    } else {
                        $warnings[] = [
                            'type' => 'unsupported',
                            'feature' => 'file-part-without-url',
                        ];
                    }
                    break;

                default:
                    $warnings[] = [
                        'type' => 'unsupported',
                        'feature' => "content-part-type:{$type}",
                    ];
                    break;
            }
        }

        return [
            'role' => 'user',
            'content' => $parts,
        ];
    }

    /**
     * Convert an assistant message to Responses API format.
     *
     * Returns an array of input items:
     * - Text content becomes a {role: assistant, content: ...} message
     * - Tool calls become top-level {type: function_call, ...} items
     *
     * @return array<array>
     */
    private static function convertAssistantMessage(Message $message): array
    {
        if (is_string($message->content)) {
            return [[
                'role' => 'assistant',
                'content' => $message->content,
            ]];
        }

        $items = [];
        $textParts = [];

        foreach ($message->content as $part) {
            $type = $part['type'] ?? 'text';

            switch ($type) {
                case 'text':
                    $textParts[] = [
                        'type' => 'output_text',
                        'text' => $part['text'] ?? '',
                    ];
                    break;

                case 'tool-call':
                    // Tool calls are top-level items in the Responses API
                    $items[] = [
                        'type' => 'function_call',
                        'call_id' => $part['toolCallId'] ?? '',
                        'name' => $part['toolName'] ?? '',
                        'arguments' => is_string($part['input'] ?? null)
                            ? $part['input']
                            : json_encode($part['input'] ?? []),
                    ];
                    break;
            }
        }

        // Add text message if any text parts exist
        if (!empty($textParts)) {
            array_unshift($items, [
                'role' => 'assistant',
                'content' => $textParts,
            ]);
        }

        return $items;
    }

    /**
     * Convert tool result messages to Responses API format.
     */
    private static function convertToolMessage(Message $message): array
    {
        $results = [];

        if (!is_array($message->content)) {
            return $results;
        }

        foreach ($message->content as $toolResponse) {
            if (!isset($toolResponse['type']) || $toolResponse['type'] !== 'tool-result') {
                continue;
            }

            $output = $toolResponse['output'] ?? $toolResponse['result'] ?? '';
            if (is_array($output)) {
                $output = json_encode($output);
            }

            $results[] = [
                'type' => 'function_call_output',
                'call_id' => $toolResponse['toolCallId'] ?? '',
                'output' => (string) $output,
            ];
        }

        return $results;
    }
}

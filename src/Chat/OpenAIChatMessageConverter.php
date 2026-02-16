<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Chat;

use BengalStudio\AI\Types\Message;

/**
 * Converts AI SDK messages to OpenAI Chat API format.
 *
 * Handles system, user, assistant, and tool messages with
 * multimodal content (text, images, files).
 */
class OpenAIChatMessageConverter
{
    /**
     * Convert an array of Message objects to OpenAI chat message format.
     *
     * @param array<Message> $prompt The AI SDK messages.
     * @param string $systemMessageMode How to handle system messages: 'system', 'developer', or 'remove'.
     * @return array{messages: array, warnings: array}
     */
    public static function convert(array $prompt, string $systemMessageMode = 'system'): array
    {
        $messages = [];
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

                    $role = $systemMessageMode === 'developer' ? 'developer' : 'system';

                    if (is_string($message->content)) {
                        $messages[] = [
                            'role' => $role,
                            'content' => $message->content,
                        ];
                    } elseif (is_array($message->content)) {
                        // Multi-part system message
                        $text = '';
                        foreach ($message->content as $part) {
                            if (isset($part['type']) && $part['type'] === 'text') {
                                $text .= $part['text'] ?? '';
                            }
                        }
                        $messages[] = [
                            'role' => $role,
                            'content' => $text,
                        ];
                    }
                    break;

                case 'user':
                    if (is_string($message->content)) {
                        $messages[] = [
                            'role' => 'user',
                            'content' => $message->content,
                        ];
                    } elseif (is_array($message->content)) {
                        $parts = [];
                        foreach ($message->content as $part) {
                            $parts[] = self::convertUserContentPart($part, $warnings);
                        }
                        // Filter out null parts
                        $parts = array_values(array_filter($parts));
                        $messages[] = [
                            'role' => 'user',
                            'content' => $parts,
                        ];
                    }
                    break;

                case 'assistant':
                    $msg = self::convertAssistantMessage($message);
                    $messages[] = $msg;
                    break;

                case 'tool':
                    if (is_array($message->content)) {
                        foreach ($message->content as $toolResponse) {
                            if (!isset($toolResponse['type']) || $toolResponse['type'] !== 'tool-result') {
                                continue;
                            }

                            $content = $toolResponse['output'] ?? $toolResponse['result'] ?? '';
                            if (is_array($content)) {
                                $content = json_encode($content);
                            }

                            $messages[] = [
                                'role' => 'tool',
                                'tool_call_id' => $toolResponse['toolCallId'] ?? '',
                                'content' => (string) $content,
                            ];
                        }
                    }
                    break;
            }
        }

        return [
            'messages' => $messages,
            'warnings' => $warnings,
        ];
    }

    /**
     * Convert a user content part to OpenAI format.
     */
    private static function convertUserContentPart(array $part, array &$warnings): ?array
    {
        $type = $part['type'] ?? 'text';

        switch ($type) {
            case 'text':
                return [
                    'type' => 'text',
                    'text' => $part['text'] ?? '',
                ];

            case 'image':
                $url = $part['image'] ?? $part['url'] ?? null;
                $data = $part['data'] ?? null;
                $mediaType = $part['mediaType'] ?? $part['mimeType'] ?? 'image/png';

                if ($data !== null) {
                    // Base64-encoded image data
                    $base64 = is_string($data) ? $data : base64_encode($data);
                    $url = "data:{$mediaType};base64,{$base64}";
                }

                if ($url === null) {
                    $warnings[] = [
                        'type' => 'other',
                        'message' => 'Image part missing URL or data.',
                    ];
                    return null;
                }

                return [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => (string) $url,
                        ...(isset($part['detail']) ? ['detail' => $part['detail']] : []),
                    ],
                ];

            case 'file':
                // PDF or other file support via URL
                $url = $part['data'] ?? $part['url'] ?? null;
                $mediaType = $part['mediaType'] ?? $part['mimeType'] ?? '';

                if ($url instanceof \Stringable || is_string($url)) {
                    if (str_starts_with($mediaType, 'image/')) {
                        return [
                            'type' => 'image_url',
                            'image_url' => ['url' => (string) $url],
                        ];
                    }
                    // For PDFs and other files, include as text URL reference
                    return [
                        'type' => 'text',
                        'text' => "[Attached file: {$url}]",
                    ];
                }

                $warnings[] = [
                    'type' => 'unsupported',
                    'feature' => 'file-part',
                ];
                return null;

            default:
                $warnings[] = [
                    'type' => 'unsupported',
                    'feature' => "content-part-type:{$type}",
                ];
                return null;
        }
    }

    /**
     * Convert an assistant message to OpenAI format.
     */
    private static function convertAssistantMessage(Message $message): array
    {
        $content = $message->content;

        if (is_string($content)) {
            return [
                'role' => 'assistant',
                'content' => $content,
            ];
        }

        // Multi-part assistant message with text + tool calls
        $text = '';
        $toolCalls = [];

        foreach ($content as $part) {
            $type = $part['type'] ?? 'text';

            switch ($type) {
                case 'text':
                    $text .= $part['text'] ?? '';
                    break;

                case 'tool-call':
                    $toolCalls[] = [
                        'id' => $part['toolCallId'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $part['toolName'] ?? '',
                            'arguments' => is_string($part['input'] ?? null)
                                ? $part['input']
                                : json_encode($part['input'] ?? []),
                        ],
                    ];
                    break;
            }
        }

        $msg = [
            'role' => 'assistant',
            'content' => $text ?: null,
        ];

        if (!empty($toolCalls)) {
            $msg['tool_calls'] = $toolCalls;
        }

        return $msg;
    }
}

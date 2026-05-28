<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests\Chat;

use BengalStudio\AI\OpenAI\Chat\OpenAIChatMessageConverter;
use BengalStudio\AI\Types\Message;
use PHPUnit\Framework\TestCase;

use function BengalStudio\AI\convertToModelMessages;

class OpenAIChatMessageConverterTest extends TestCase
{
    // System messages

    public function testSystemMessageAsSystem(): void
    {
        $prompt = [Message::system('You are helpful.')];
        $result = OpenAIChatMessageConverter::convert($prompt, 'system');

        $this->assertCount(1, $result['messages']);
        $this->assertSame('system', $result['messages'][0]['role']);
        $this->assertSame('You are helpful.', $result['messages'][0]['content']);
        $this->assertEmpty($result['warnings']);
    }

    public function testSystemMessageAsDeveloper(): void
    {
        $prompt = [Message::system('Be concise.')];
        $result = OpenAIChatMessageConverter::convert($prompt, 'developer');

        $this->assertSame('developer', $result['messages'][0]['role']);
        $this->assertSame('Be concise.', $result['messages'][0]['content']);
    }

    public function testSystemMessageRemoved(): void
    {
        $prompt = [Message::system('Ignore this.')];
        $result = OpenAIChatMessageConverter::convert($prompt, 'remove');

        $this->assertEmpty($result['messages']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('other', $result['warnings'][0]['type']);
    }

    public function testMultiPartSystemMessage(): void
    {
        $prompt = [new Message(role: 'system', content: [
            ['type' => 'text', 'text' => 'Part 1. '],
            ['type' => 'text', 'text' => 'Part 2.'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt, 'system');

        $this->assertSame('system', $result['messages'][0]['role']);
        $this->assertSame('Part 1. Part 2.', $result['messages'][0]['content']);
    }

    // User messages

    public function testSimpleUserMessage(): void
    {
        $prompt = [Message::user('Hello')];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('user', $result['messages'][0]['role']);
        $this->assertSame('Hello', $result['messages'][0]['content']);
    }

    public function testMultiPartUserTextMessage(): void
    {
        $prompt = [Message::user([
            ['type' => 'text', 'text' => 'Describe this:'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('user', $result['messages'][0]['role']);
        $this->assertCount(1, $result['messages'][0]['content']);
        $this->assertSame('text', $result['messages'][0]['content'][0]['type']);
        $this->assertSame('Describe this:', $result['messages'][0]['content'][0]['text']);
    }

    public function testUserMessageWithImage(): void
    {
        $prompt = [Message::user([
            ['type' => 'text', 'text' => 'What is this?'],
            ['type' => 'image', 'url' => 'https://example.com/cat.jpg'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertCount(2, $result['messages'][0]['content']);
        $this->assertSame('image_url', $result['messages'][0]['content'][1]['type']);
        $this->assertSame('https://example.com/cat.jpg', $result['messages'][0]['content'][1]['image_url']['url']);
    }

    public function testUserMessageWithBase64Image(): void
    {
        $prompt = [Message::user([
            ['type' => 'image', 'data' => 'base64data==', 'mediaType' => 'image/jpeg'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('image_url', $result['messages'][0]['content'][0]['type']);
        $this->assertStringContains('data:image/jpeg;base64,base64data==', $result['messages'][0]['content'][0]['image_url']['url']);
    }

    public function testUserMessageImageWithDetail(): void
    {
        $prompt = [Message::user([
            ['type' => 'image', 'url' => 'https://example.com/img.png', 'detail' => 'high'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('high', $result['messages'][0]['content'][0]['image_url']['detail']);
    }

    public function testUserMessageImageMissingUrl(): void
    {
        $prompt = [Message::user([
            ['type' => 'image'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        // Part is filtered out as null
        $this->assertEmpty($result['messages'][0]['content']);
        $this->assertCount(1, $result['warnings']);
    }

    public function testUserMessageWithFileAsImage(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'data' => 'https://example.com/photo.png', 'mediaType' => 'image/png'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('image_url', $result['messages'][0]['content'][0]['type']);
    }

    public function testUserMessageWithFileAsPdf(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'data' => 'https://example.com/doc.pdf', 'mediaType' => 'application/pdf'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        // PDF rendered as text reference
        $this->assertSame('text', $result['messages'][0]['content'][0]['type']);
        $this->assertStringContainsString('Attached file', $result['messages'][0]['content'][0]['text']);
    }

    public function testUserMessageUnsupportedType(): void
    {
        $prompt = [Message::user([
            ['type' => 'video', 'url' => 'https://example.com/vid.mp4'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertCount(1, $result['warnings']);
        $this->assertSame('unsupported', $result['warnings'][0]['type']);
    }

    // Assistant messages

    public function testSimpleAssistantMessage(): void
    {
        $prompt = [Message::assistant('Hi there!')];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('assistant', $result['messages'][0]['role']);
        $this->assertSame('Hi there!', $result['messages'][0]['content']);
    }

    public function testAssistantMessageWithToolCalls(): void
    {
        $prompt = [Message::assistant([
            ['type' => 'text', 'text' => 'Let me check.'],
            [
                'type' => 'tool-call',
                'toolCallId' => 'call_123',
                'toolName' => 'get_weather',
                'input' => ['city' => 'NYC'],
            ],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $msg = $result['messages'][0];
        $this->assertSame('assistant', $msg['role']);
        $this->assertSame('Let me check.', $msg['content']);
        $this->assertCount(1, $msg['tool_calls']);
        $this->assertSame('call_123', $msg['tool_calls'][0]['id']);
        $this->assertSame('function', $msg['tool_calls'][0]['type']);
        $this->assertSame('get_weather', $msg['tool_calls'][0]['function']['name']);
        $this->assertSame('{"city":"NYC"}', $msg['tool_calls'][0]['function']['arguments']);
    }

    // Tool messages

    public function testToolMessage(): void
    {
        $prompt = [Message::tool([
            ['type' => 'tool-result', 'toolCallId' => 'call_123', 'output' => 'Sunny, 72°F'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('tool', $result['messages'][0]['role']);
        $this->assertSame('call_123', $result['messages'][0]['tool_call_id']);
        $this->assertSame('Sunny, 72°F', $result['messages'][0]['content']);
    }

    public function testToolMessageWithArrayOutput(): void
    {
        $prompt = [Message::tool([
            ['type' => 'tool-result', 'toolCallId' => 'call_456', 'output' => ['temp' => 72]],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertSame('{"temp":72}', $result['messages'][0]['content']);
    }

    public function testToolMessageSkipsNonToolResults(): void
    {
        $prompt = [Message::tool([
            ['type' => 'other-thing', 'data' => 'ignored'],
            ['type' => 'tool-result', 'toolCallId' => 'call_1', 'output' => 'ok'],
        ])];
        $result = OpenAIChatMessageConverter::convert($prompt);

        $this->assertCount(1, $result['messages']);
    }

    // Mixed conversation

    public function testFullConversation(): void
    {
        $prompt = [
            Message::system('You are a weather bot.'),
            Message::user('What is the weather?'),
            Message::assistant([
                ['type' => 'tool-call', 'toolCallId' => 'c1', 'toolName' => 'weather', 'input' => '{}'],
            ]),
            Message::tool([
                ['type' => 'tool-result', 'toolCallId' => 'c1', 'output' => 'Sunny'],
            ]),
        ];
        $result = OpenAIChatMessageConverter::convert($prompt, 'system');

        $this->assertCount(4, $result['messages']);
        $this->assertSame('system', $result['messages'][0]['role']);
        $this->assertSame('user', $result['messages'][1]['role']);
        $this->assertSame('assistant', $result['messages'][2]['role']);
        $this->assertSame('tool', $result['messages'][3]['role']);
    }

    // End-to-end: AI SDK v5+ UI messages → convertToModelMessages → OpenAI wire

    /**
     * Replaying a persisted assistant turn that called a tool must reach the
     * wire as an assistant message with `tool_calls` followed by a `role: tool`
     * message whose `tool_call_id` matches — otherwise the model never sees the
     * tool result on later turns. Guards the convertToModelMessages → OpenAI
     * converter seam end-to-end.
     */
    public function testToolRoundTripFromUiMessages(): void
    {
        $uiMessages = [
            [
                'id' => 'm1',
                'role' => 'user',
                'parts' => [['type' => 'text', 'text' => 'Weather in SF?']],
            ],
            [
                'id' => 'm2',
                'role' => 'assistant',
                'parts' => [
                    ['type' => 'text', 'text' => 'Checking.'],
                    [
                        'type' => 'tool-get_weather',
                        'toolCallId' => 'call_rt',
                        'state' => 'output-available',
                        'input' => ['city' => 'SF'],
                        'output' => ['temp' => 68],
                    ],
                ],
            ],
        ];

        $result = OpenAIChatMessageConverter::convert(convertToModelMessages($uiMessages), 'system');
        $wire = $result['messages'];

        // user, assistant (tool_calls), tool
        $this->assertCount(3, $wire);
        $this->assertSame('user', $wire[0]['role']);

        $assistant = $wire[1];
        $this->assertSame('assistant', $assistant['role']);
        $this->assertSame('Checking.', $assistant['content']);
        $this->assertCount(1, $assistant['tool_calls']);
        $this->assertSame('call_rt', $assistant['tool_calls'][0]['id']);
        $this->assertSame('get_weather', $assistant['tool_calls'][0]['function']['name']);
        $this->assertSame('{"city":"SF"}', $assistant['tool_calls'][0]['function']['arguments']);

        $tool = $wire[2];
        $this->assertSame('tool', $tool['role']);
        // The linkage OpenAI requires: tool message answers the assistant call.
        $this->assertSame($assistant['tool_calls'][0]['id'], $tool['tool_call_id']);
        $this->assertSame('{"temp":68}', $tool['content']);
    }

    /**
     * Helper for str contains assertion compatibility.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}

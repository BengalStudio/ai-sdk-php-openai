<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Tests\Responses;

use AISdkPhp\OpenAI\Responses\OpenAIResponsesInputConverter;
use BengalStudio\AI\Types\Message;
use PHPUnit\Framework\TestCase;

class OpenAIResponsesInputConverterTest extends TestCase
{
    // System messages

    public function testSystemMessageAsInstructions(): void
    {
        $prompt = [Message::system('You are helpful.')];
        $result = OpenAIResponsesInputConverter::convert($prompt, 'system');

        $this->assertSame('You are helpful.', $result['instructions']);
        $this->assertEmpty($result['input']);
        $this->assertEmpty($result['warnings']);
    }

    public function testSystemMessageAsDeveloper(): void
    {
        $prompt = [Message::system('Be concise.')];
        $result = OpenAIResponsesInputConverter::convert($prompt, 'developer');

        $this->assertNull($result['instructions']);
        $this->assertCount(1, $result['input']);
        $this->assertSame('developer', $result['input'][0]['role']);
        $this->assertSame('Be concise.', $result['input'][0]['content']);
    }

    public function testSystemMessageRemoved(): void
    {
        $prompt = [Message::system('Ignore.')];
        $result = OpenAIResponsesInputConverter::convert($prompt, 'remove');

        $this->assertNull($result['instructions']);
        $this->assertEmpty($result['input']);
        $this->assertCount(1, $result['warnings']);
    }

    public function testMultiPartSystemMessageExtractsText(): void
    {
        $prompt = [new Message(role: 'system', content: [
            ['type' => 'text', 'text' => 'Part A. '],
            ['type' => 'text', 'text' => 'Part B.'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt, 'system');

        $this->assertSame('Part A. Part B.', $result['instructions']);
    }

    // User messages

    public function testSimpleUserMessage(): void
    {
        $prompt = [Message::user('Hello')];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('user', $result['input'][0]['role']);
        $this->assertSame('Hello', $result['input'][0]['content']);
    }

    public function testMultiPartUserText(): void
    {
        $prompt = [Message::user([
            ['type' => 'text', 'text' => 'Describe this:'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('user', $result['input'][0]['role']);
        $this->assertCount(1, $result['input'][0]['content']);
        $this->assertSame('input_text', $result['input'][0]['content'][0]['type']);
        $this->assertSame('Describe this:', $result['input'][0]['content'][0]['text']);
    }

    public function testUserMessageWithImage(): void
    {
        $prompt = [Message::user([
            ['type' => 'image', 'url' => 'https://example.com/cat.jpg'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('input_image', $result['input'][0]['content'][0]['type']);
        $this->assertSame('https://example.com/cat.jpg', $result['input'][0]['content'][0]['image_url']);
    }

    public function testUserMessageWithBase64Image(): void
    {
        $prompt = [Message::user([
            ['type' => 'image', 'data' => 'abc123==', 'mediaType' => 'image/png'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertStringContainsString('data:image/png;base64,abc123==', $result['input'][0]['content'][0]['image_url']);
    }

    public function testUserMessageWithPdfFile(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'data' => 'https://example.com/doc.pdf', 'mediaType' => 'application/pdf'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('input_file', $result['input'][0]['content'][0]['type']);
        $this->assertSame('https://example.com/doc.pdf', $result['input'][0]['content'][0]['file_url']);
    }

    public function testUserMessageWithImageFile(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'data' => 'https://example.com/photo.jpg', 'mediaType' => 'image/jpeg'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('input_image', $result['input'][0]['content'][0]['type']);
    }

    public function testUserMessageWithUnknownFile(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'data' => 'https://example.com/data.csv', 'mediaType' => 'text/csv'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('input_text', $result['input'][0]['content'][0]['type']);
        $this->assertStringContainsString('Attached file', $result['input'][0]['content'][0]['text']);
    }

    public function testUserMessageFileWithoutUrl(): void
    {
        $prompt = [Message::user([
            ['type' => 'file', 'mediaType' => 'application/pdf'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertCount(1, $result['warnings']);
        $this->assertSame('unsupported', $result['warnings'][0]['type']);
    }

    public function testUserMessageUnsupportedPart(): void
    {
        $prompt = [Message::user([
            ['type' => 'audio', 'url' => 'https://example.com/sound.mp3'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertCount(1, $result['warnings']);
        $this->assertSame('unsupported', $result['warnings'][0]['type']);
    }

    // Assistant messages

    public function testSimpleAssistantMessage(): void
    {
        $prompt = [Message::assistant('Hi!')];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('assistant', $result['input'][0]['role']);
        $this->assertSame('Hi!', $result['input'][0]['content']);
    }

    public function testAssistantMessageWithToolCalls(): void
    {
        $prompt = [Message::assistant([
            ['type' => 'text', 'text' => 'Checking...'],
            [
                'type' => 'tool-call',
                'toolCallId' => 'call_abc',
                'toolName' => 'search',
                'input' => ['q' => 'weather'],
            ],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        // Text content becomes an assistant message, tool call is a top-level item
        $this->assertCount(2, $result['input']);

        // First item: assistant message with text
        $msg = $result['input'][0];
        $this->assertSame('assistant', $msg['role']);
        $this->assertCount(1, $msg['content']);
        $this->assertSame('output_text', $msg['content'][0]['type']);
        $this->assertSame('Checking...', $msg['content'][0]['text']);

        // Second item: top-level function_call
        $fc = $result['input'][1];
        $this->assertSame('function_call', $fc['type']);
        $this->assertSame('call_abc', $fc['call_id']);
        $this->assertSame('search', $fc['name']);
        $this->assertSame('{"q":"weather"}', $fc['arguments']);
    }

    // Tool messages

    public function testToolMessage(): void
    {
        $prompt = [Message::tool([
            ['type' => 'tool-result', 'toolCallId' => 'call_abc', 'output' => 'Sunny'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertCount(1, $result['input']);
        $this->assertSame('function_call_output', $result['input'][0]['type']);
        $this->assertSame('call_abc', $result['input'][0]['call_id']);
        $this->assertSame('Sunny', $result['input'][0]['output']);
    }

    public function testToolMessageWithArrayOutput(): void
    {
        $prompt = [Message::tool([
            ['type' => 'tool-result', 'toolCallId' => 'c1', 'output' => ['data' => 42]],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertSame('{"data":42}', $result['input'][0]['output']);
    }

    public function testToolMessageSkipsNonResults(): void
    {
        $prompt = [Message::tool([
            ['type' => 'other', 'data' => 'skip'],
            ['type' => 'tool-result', 'toolCallId' => 'c1', 'output' => 'ok'],
        ])];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertCount(1, $result['input']);
    }

    public function testToolMessageStringContent(): void
    {
        // When content is a string instead of array, returns empty
        $prompt = [new Message(role: 'tool', content: 'just a string')];
        $result = OpenAIResponsesInputConverter::convert($prompt);

        $this->assertEmpty($result['input']);
    }

    // Full conversation

    public function testFullConversation(): void
    {
        $prompt = [
            Message::system('You are a bot.'),
            Message::user('Hi'),
            Message::assistant('Hello!'),
        ];
        $result = OpenAIResponsesInputConverter::convert($prompt, 'system');

        $this->assertSame('You are a bot.', $result['instructions']);
        $this->assertCount(2, $result['input']);
        $this->assertSame('user', $result['input'][0]['role']);
        $this->assertSame('assistant', $result['input'][1]['role']);
    }
}

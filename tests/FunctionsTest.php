<?php

declare(strict_types=1);

namespace AISdkPhp\OpenAI\Tests;

use AISdkPhp\OpenAI\Chat\OpenAIChatLanguageModel;
use AISdkPhp\OpenAI\Completion\OpenAICompletionLanguageModel;
use AISdkPhp\OpenAI\Embedding\OpenAIEmbeddingModel;
use AISdkPhp\OpenAI\OpenAIProvider;
use AISdkPhp\OpenAI\Responses\OpenAIResponsesLanguageModel;
use PHPUnit\Framework\TestCase;

use function AISdkPhp\OpenAI\createOpenAI;
use function AISdkPhp\OpenAI\openai;

class FunctionsTest extends TestCase
{
    public function testCreateOpenAIReturnsProvider(): void
    {
        $provider = createOpenAI(['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function testCreateOpenAIWithDefaults(): void
    {
        $provider = createOpenAI();
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function testOpenaiWithNullReturnsProvider(): void
    {
        $result = openai(null, ['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAIProvider::class, $result);
    }

    public function testOpenaiWithNoPrefixReturnsResponses(): void
    {
        $result = openai('gpt-4o', ['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAIResponsesLanguageModel::class, $result);
    }

    public function testOpenaiWithChatPrefix(): void
    {
        $result = openai('chat:gpt-4o', ['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAIChatLanguageModel::class, $result);
    }

    public function testOpenaiWithCompletionPrefix(): void
    {
        $result = openai('completion:gpt-3.5-turbo-instruct', ['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAICompletionLanguageModel::class, $result);
    }

    public function testOpenaiWithEmbeddingPrefix(): void
    {
        $result = openai('embedding:text-embedding-3-large', ['apiKey' => 'sk-test']);
        $this->assertInstanceOf(OpenAIEmbeddingModel::class, $result);
    }
}

<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests;

use BengalStudio\AI\OpenAI\Chat\OpenAIChatLanguageModel;
use BengalStudio\AI\OpenAI\Completion\OpenAICompletionLanguageModel;
use BengalStudio\AI\OpenAI\Embedding\OpenAIEmbeddingModel;
use BengalStudio\AI\OpenAI\OpenAIProvider;
use BengalStudio\AI\OpenAI\Responses\OpenAIResponsesLanguageModel;
use PHPUnit\Framework\TestCase;

use function BengalStudio\AI\OpenAI\createOpenAI;
use function BengalStudio\AI\OpenAI\openai;

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

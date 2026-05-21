<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests;

use BengalStudio\AI\OpenAI\Chat\OpenAIChatLanguageModel;
use BengalStudio\AI\OpenAI\Completion\OpenAICompletionLanguageModel;
use BengalStudio\AI\OpenAI\Embedding\OpenAIEmbeddingModel;
use BengalStudio\AI\OpenAI\OpenAIProvider;
use BengalStudio\AI\OpenAI\OpenAIProviderSettings;
use BengalStudio\AI\OpenAI\Responses\OpenAIResponsesLanguageModel;
use BengalStudio\AI\Contracts\EmbeddingModel;
use BengalStudio\AI\Contracts\LanguageModel;
use BengalStudio\AI\Contracts\Provider;
use PHPUnit\Framework\TestCase;

class OpenAIProviderTest extends TestCase
{
    private OpenAIProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new OpenAIProvider(new OpenAIProviderSettings(
            apiKey: 'sk-test-key',
        ));
    }

    public function testImplementsProviderInterface(): void
    {
        $this->assertInstanceOf(Provider::class, $this->provider);
    }

    public function testChatReturnsCorrectType(): void
    {
        $model = $this->provider->chat('gpt-4o');
        $this->assertInstanceOf(OpenAIChatLanguageModel::class, $model);
        $this->assertInstanceOf(LanguageModel::class, $model);
    }

    public function testCompletionReturnsCorrectType(): void
    {
        $model = $this->provider->completion('gpt-3.5-turbo-instruct');
        $this->assertInstanceOf(OpenAICompletionLanguageModel::class, $model);
        $this->assertInstanceOf(LanguageModel::class, $model);
    }

    public function testResponsesReturnsCorrectType(): void
    {
        $model = $this->provider->responses('gpt-4o');
        $this->assertInstanceOf(OpenAIResponsesLanguageModel::class, $model);
        $this->assertInstanceOf(LanguageModel::class, $model);
    }

    public function testEmbeddingReturnsCorrectType(): void
    {
        $model = $this->provider->embedding('text-embedding-3-large');
        $this->assertInstanceOf(OpenAIEmbeddingModel::class, $model);
        $this->assertInstanceOf(EmbeddingModel::class, $model);
    }

    public function testLanguageModelDefaultsToResponses(): void
    {
        $model = $this->provider->languageModel('gpt-4o');
        $this->assertInstanceOf(OpenAIResponsesLanguageModel::class, $model);
    }

    public function testEmbeddingModelAliasesEmbedding(): void
    {
        $model = $this->provider->embeddingModel('text-embedding-3-small');
        $this->assertInstanceOf(OpenAIEmbeddingModel::class, $model);
    }

    public function testDefaultSettingsUsed(): void
    {
        // Should not throw — uses defaults
        $provider = new OpenAIProvider();
        $model = $provider->responses('gpt-4o');
        $this->assertInstanceOf(OpenAIResponsesLanguageModel::class, $model);
    }
}

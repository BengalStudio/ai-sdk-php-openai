<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests\Support;

use BengalStudio\AI\OpenAI\Support\OpenAIConfig;
use PHPUnit\Framework\TestCase;

class OpenAIConfigTest extends TestCase
{
    public function testDefaultBaseURL(): void
    {
        $config = new OpenAIConfig(provider: 'openai');
        $this->assertSame('https://api.openai.com/v1', $config->baseURL);
    }

    public function testCustomBaseURL(): void
    {
        $config = new OpenAIConfig(
            provider: 'custom',
            baseURL: 'https://my-proxy.com/v1',
        );
        $this->assertSame('https://my-proxy.com/v1', $config->baseURL);
    }

    public function testUrl(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            baseURL: 'https://api.openai.com/v1',
        );

        $this->assertSame(
            'https://api.openai.com/v1/chat/completions',
            $config->url('chat/completions'),
        );
    }

    public function testUrlTrimsSlashes(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            baseURL: 'https://api.openai.com/v1/',
        );

        $this->assertSame(
            'https://api.openai.com/v1/responses',
            $config->url('/responses'),
        );
    }

    public function testHeadersWithApiKey(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            apiKey: 'sk-test-key',
        );

        $headers = $config->headers();
        $this->assertSame('Bearer sk-test-key', $headers['Authorization']);
    }

    public function testHeadersWithOrganization(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            apiKey: 'sk-test',
            organization: 'org-123',
        );

        $headers = $config->headers();
        $this->assertSame('org-123', $headers['OpenAI-Organization']);
    }

    public function testHeadersWithProject(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            apiKey: 'sk-test',
            project: 'proj_456',
        );

        $headers = $config->headers();
        $this->assertSame('proj_456', $headers['OpenAI-Project']);
    }

    public function testCustomHeaders(): void
    {
        $config = new OpenAIConfig(
            provider: 'openai',
            apiKey: 'sk-test',
            headers: ['X-Custom' => 'value'],
        );

        $headers = $config->headers();
        $this->assertSame('value', $headers['X-Custom']);
        $this->assertArrayHasKey('Authorization', $headers);
    }

    public function testHeadersWithoutApiKey(): void
    {
        // Unset env var to test no-key scenario
        $originalKey = getenv('OPENAI_API_KEY');
        putenv('OPENAI_API_KEY');
        unset($_ENV['OPENAI_API_KEY']);

        try {
            $config = new OpenAIConfig(provider: 'openai');
            $headers = $config->headers();
            $this->assertArrayNotHasKey('Authorization', $headers);
        } finally {
            if ($originalKey !== false) {
                putenv("OPENAI_API_KEY=$originalKey");
            }
        }
    }
}

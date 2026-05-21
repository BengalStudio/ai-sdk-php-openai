<?php

declare(strict_types=1);

namespace BengalStudio\AI\OpenAI\Tests;

use BengalStudio\AI\OpenAI\OpenAIProviderSettings;
use PHPUnit\Framework\TestCase;

class OpenAIProviderSettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $settings = new OpenAIProviderSettings();

        $this->assertSame('https://api.openai.com/v1', $settings->baseURL);
        $this->assertNull($settings->apiKey);
        $this->assertSame('openai', $settings->name);
        $this->assertNull($settings->organization);
        $this->assertNull($settings->project);
        $this->assertSame([], $settings->headers);
    }

    public function testCustomValues(): void
    {
        $settings = new OpenAIProviderSettings(
            baseURL: 'https://custom.api.com/v2',
            apiKey: 'sk-test',
            name: 'my-provider',
            organization: 'org-123',
            project: 'proj-456',
            headers: ['X-Custom' => 'value'],
        );

        $this->assertSame('https://custom.api.com/v2', $settings->baseURL);
        $this->assertSame('sk-test', $settings->apiKey);
        $this->assertSame('my-provider', $settings->name);
        $this->assertSame('org-123', $settings->organization);
        $this->assertSame('proj-456', $settings->project);
        $this->assertSame(['X-Custom' => 'value'], $settings->headers);
    }
}

<?php

namespace Tests\Unit;

use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Support\Env;
use PHPUnit\Framework\TestCase;

class AiConfigurationTest extends TestCase
{
    private function loadConfig(array $variables): array
    {
        $property = new \ReflectionProperty(Env::class, 'repository');
        $original = $property->getValue();
        $repository = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();
        foreach ($variables as $key => $value) {
            $repository->set($key, $value);
        }
        try {
            $property->setValue(null, $repository);
            return require dirname(__DIR__, 2) . '/config/ai.php';
        } finally {
            $property->setValue(null, $original);
        }
    }

    public function testExistingGatewayEnvironmentStillWorksWithoutNewVariables(): void
    {
        $config = $this->loadConfig([
            'GPT_KEY' => 'old-key', 'GPT_GATEWAY' => 'https://gateway.example/v1/responses',
            'GPT_MODEL' => 'existing-model', 'GPT_ACHIEVEMENT_MODEL' => 'achievement-model',
        ]);
        $this->assertSame('gateway', $config['provider']);
        $this->assertSame(['key' => 'old-key', 'endpoint' => 'https://gateway.example/v1/responses', 'proxy' => null], $config['providers']['gateway']);
        $this->assertSame('existing-model', $config['defaults']['text']['model']);
        $this->assertSame('existing-model', $config['defaults']['image']['model']);
        $this->assertSame('achievement-model', $config['commands']['achievement_variants']['model']);
        $this->assertSame('gpt-5.5', $config['commands']['achievement_image']['model']);
        $this->assertNull($config['commands']['text_improvement']['provider']);
    }

    public function testEnvironmentCanSelectOpenAiWithSeparateKeyAndEndpointAndOverrideACommand(): void
    {
        $config = $this->loadConfig([
            'AI_PROVIDER' => 'openai', 'OPENAI_API_KEY' => 'direct-key',
            'OPENAI_RESPONSES_URL' => 'https://direct.example/v1/responses',
            'OPENAI_PROXY' => 'socks5h://proxy.example:1080',
            'GPT_KEY' => 'gateway-key', 'GPT_IMAGE_MODEL' => '', 'GPT_MODEL' => 'default-model',
            'AI_TEXT_IMPROVEMENT_PROVIDER' => 'gateway', 'GPT_TEXT_IMPROVEMENT_MODEL' => 'editor-model',
            'AI_ACHIEVEMENT_IMAGE_PROVIDER' => '',
        ]);
        $this->assertSame('openai', $config['provider']);
        $this->assertSame(['key' => 'direct-key', 'endpoint' => 'https://direct.example/v1/responses', 'proxy' => 'socks5h://proxy.example:1080'], $config['providers']['openai']);
        $this->assertSame('gateway-key', $config['providers']['gateway']['key']);
        $this->assertSame('default-model', $config['defaults']['image']['model']);
        $this->assertSame('gateway', $config['commands']['text_improvement']['provider']);
        $this->assertSame('editor-model', $config['commands']['text_improvement']['model']);
        $this->assertSame('', $config['commands']['achievement_image']['provider']);
    }
}

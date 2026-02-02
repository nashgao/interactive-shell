<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Hyperf;

use NashGao\InteractiveShell\Server\Hyperf\ConfigProvider;
use NashGao\InteractiveShell\Server\Hyperf\ShellProcess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    public function testInvokeReturnsArrayWithProcessesKey(): void
    {
        $config = ($this->provider)();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('processes', $config);
    }

    public function testInvokeReturnsArrayWithPublishKey(): void
    {
        $config = ($this->provider)();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('publish', $config);
    }

    public function testProcessesIncludesShellProcess(): void
    {
        $config = ($this->provider)();

        $this->assertIsArray($config['processes']);
        $this->assertContains(ShellProcess::class, $config['processes']);
    }

    public function testPublishConfigHasCorrectId(): void
    {
        $config = ($this->provider)();

        $this->assertIsArray($config['publish']);
        $this->assertNotEmpty($config['publish']);
        $this->assertSame('config', $config['publish'][0]['id']);
    }

    public function testPublishConfigHasSourcePath(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('source', $config['publish'][0]);
        $this->assertStringContainsString('interactive_shell.php', $config['publish'][0]['source']);
    }

    public function testPublishConfigHasDestinationPath(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey('destination', $config['publish'][0]);
        $this->assertStringContainsString('config/autoload/interactive_shell.php', $config['publish'][0]['destination']);
    }
}

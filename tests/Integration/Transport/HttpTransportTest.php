<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration\Transport;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Transport\HttpTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpTransport::class)]
final class HttpTransportTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $transport = new HttpTransport('http://localhost:9501');

        self::assertSame('http://localhost:9501', $transport->getEndpoint());
        self::assertFalse($transport->isConnected());
    }

    public function testConnectSuccessful(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with('GET', '/ping', self::anything())
            ->willReturn(new Response(200, [], 'pong'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $transport->connect();

        // After successful ping, connect doesn't throw
        // isConnected depends on ping result
    }

    public function testConnectFailsWhenPingFails(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with('GET', '/ping', self::anything())
            ->willThrowException(new \Exception('Connection refused'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to server');

        $transport->connect();
    }

    public function testDisconnect(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('request')
            ->willReturn(new Response(200));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $transport->connect();
        $transport->disconnect();

        // After disconnect, isConnected returns false (internal flag)
        // The transport tracks connected state internally
        self::assertFalse($transport->isConnected());
    }

    public function testPingReturnsTrue(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with('GET', '/ping', self::anything())
            ->willReturn(new Response(200));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        self::assertTrue($transport->ping());
    }

    public function testPingReturnsFalseOn500(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->willReturn(new Response(500));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        self::assertFalse($transport->ping());
    }

    public function testPingReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->willThrowException(new \Exception('Network error'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        self::assertFalse($transport->ping());
    }

    public function testSendCommand(): void
    {
        $responseData = ['success' => true, 'data' => ['result' => 'ok']];
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                '/runtime/command/execute',
                self::callback(function (array $options) {
                    return $options['json']['command'] === 'status'
                        && in_array('--verbose', $options['json']['args'], true);
                })
            )
            ->willReturn(new Response(200, [], (string) json_encode($responseData)));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $command = new ParsedCommand(
            command: 'status',
            arguments: [],
            options: ['verbose' => true],
            raw: 'status --verbose',
            hasVerticalTerminator: false
        );

        $result = $transport->send($command);

        self::assertTrue($result->success);
        self::assertSame(['result' => 'ok'], $result->data);
    }

    public function testSendWithArguments(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                '/runtime/command/execute',
                self::callback(function (array $options) {
                    return $options['json']['command'] === 'filter'
                        && in_array('topic:sensors/*', $options['json']['args'], true)
                        && in_array('--format=json', $options['json']['args'], true);
                })
            )
            ->willReturn(new Response(200, [], '{"success":true}'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $command = new ParsedCommand(
            command: 'filter',
            arguments: ['topic:sensors/*'],
            options: ['format' => 'json'],
            raw: 'filter topic:sensors/* --format=json',
            hasVerticalTerminator: false
        );

        $result = $transport->send($command);

        self::assertTrue($result->success);
    }

    public function testSendHandlesInvalidJsonResponse(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->willReturn(new Response(200, [], 'not valid json'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $command = new ParsedCommand(
            command: 'test',
            arguments: [],
            options: [],
            raw: 'test',
            hasVerticalTerminator: false
        );

        $result = $transport->send($command);

        self::assertFalse($result->success);
        self::assertStringContainsString('Invalid JSON response', $result->error ?? '');
    }

    public function testSendHandlesConnectionException(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->willThrowException(new ConnectException(
                'Connection refused',
                new Request('POST', '/runtime/command/execute')
            ));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $command = new ParsedCommand(
            command: 'test',
            arguments: [],
            options: [],
            raw: 'test',
            hasVerticalTerminator: false
        );

        $result = $transport->send($command);

        self::assertFalse($result->success);
        self::assertStringContainsString('Connection failed', $result->error ?? '');
    }

    public function testGetInfo(): void
    {
        $infoData = ['version' => '1.0', 'uptime' => 3600];
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->with('GET', '/runtime/health')
            ->willReturn(new Response(200, [], (string) json_encode($infoData)));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $info = $transport->getInfo();

        self::assertSame(['version' => '1.0', 'uptime' => 3600], $info);
    }

    public function testGetInfoReturnsEmptyOnError(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::once())
            ->method('request')
            ->willThrowException(new \Exception('Network error'));

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            httpClient: $mockClient
        );

        $info = $transport->getInfo();

        self::assertSame([], $info);
    }

    public function testCustomPaths(): void
    {
        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->expects(self::exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $path) {
                return match ($path) {
                    '/custom/execute' => new Response(200, [], '{"success":true}'),
                    '/health' => new Response(200, [], 'ok'),
                    default => new Response(404),
                };
            });

        $transport = new HttpTransport(
            serverUrl: 'http://localhost:9501',
            executePath: '/custom/execute',
            pingPath: '/health',
            httpClient: $mockClient
        );

        // Test custom execute path
        $command = new ParsedCommand('test', [], [], 'test', false);
        $result = $transport->send($command);
        self::assertTrue($result->success);

        // Test custom ping path
        self::assertTrue($transport->ping());
    }
}

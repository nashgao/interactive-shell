<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;

/**
 * HTTP transport for communicating with a remote server via HTTP requests.
 */
final class HttpTransport implements TransportInterface
{
    private readonly ClientInterface $httpClient;
    private bool $connected = false;

    /**
     * @param string $serverUrl Base URL of the server
     * @param float $timeout Request timeout in seconds
     * @param string $executePath Path to the command execution endpoint
     * @param string $pingPath Path to the health check endpoint
     * @param ClientInterface|null $httpClient Optional custom HTTP client
     */
    public function __construct(
        private readonly string $serverUrl,
        private readonly float $timeout = 30.0,
        private readonly string $executePath = '/runtime/command/execute',
        private readonly string $pingPath = '/ping',
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->serverUrl,
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function connect(): void
    {
        if ($this->ping()) {
            $this->connected = true;
        } else {
            throw new \RuntimeException("Cannot connect to server: {$this->serverUrl}");
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function send(ParsedCommand $command): CommandResult
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->executePath,
                [
                    'json' => [
                        'command' => $command->command,
                        'args' => array_merge(
                            $command->arguments,
                            $this->optionsToArgs($command->options)
                        ),
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return CommandResult::failure(
                    error: "Invalid JSON response from server: {$e->getMessage()}",
                    metadata: ['status_code' => $statusCode]
                );
            }

            return CommandResult::fromResponse($data);
        } catch (ConnectException $e) {
            $this->connected = false;
            return CommandResult::failure(
                error: "Connection failed: {$e->getMessage()}",
                metadata: [
                    'server_url' => $this->serverUrl,
                    'exception' => get_class($e),
                ]
            );
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            $errorMessage = $e->getMessage();

            if ($e->hasResponse() && $e->getResponse() !== null) {
                try {
                    $body = $e->getResponse()->getBody()->getContents();
                    /** @var array<string, mixed> $errorData */
                    $errorData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    if (isset($errorData['error']) && is_string($errorData['error'])) {
                        $errorMessage = $errorData['error'];
                    } elseif (isset($errorData['message']) && is_string($errorData['message'])) {
                        $errorMessage = $errorData['message'];
                    }
                } catch (\JsonException) {
                    // Ignore
                }
            }

            return CommandResult::failure(
                error: "Request failed: {$errorMessage}",
                metadata: [
                    'status_code' => $statusCode,
                    'exception' => get_class($e),
                ]
            );
        } catch (\Throwable $e) {
            return CommandResult::failure(
                error: "Unexpected error: {$e->getMessage()}",
                metadata: ['exception' => get_class($e)]
            );
        }
    }

    public function ping(): bool
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->pingPath,
                ['timeout' => 5.0]
            );

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        try {
            $response = $this->httpClient->request('GET', '/runtime/health');

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getEndpoint(): string
    {
        return $this->serverUrl;
    }

    /**
     * Convert options array to command arguments.
     *
     * @param array<string, mixed> $options
     * @return array<string>
     */
    private function optionsToArgs(array $options): array
    {
        $args = [];
        foreach ($options as $key => $value) {
            if ($value === true) {
                $args[] = "--{$key}";
            } elseif (is_string($value)) {
                $args[] = "--{$key}={$value}";
            } elseif (is_scalar($value)) {
                $args[] = "--{$key}=" . (string) $value;
            }
        }
        return $args;
    }
}

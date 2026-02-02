<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Handler that initiates streaming mode.
 *
 * This handler simulates the server-side command that starts a streaming
 * session. In real applications, this might start subscribing to MQTT
 * topics, database change streams, or other event sources.
 *
 * Use this with InMemoryStreamingTransport to test StreamingShell behavior.
 *
 * Example usage:
 * ```php
 * $server = new TestServer();
 * $server->register(new StreamHandler());
 *
 * // Start streaming on default topic
 * $result = $server->dispatch(new ParsedCommand('stream', [], [], 'stream', false));
 * // $result->success === true
 * // $result->data === ['streaming' => true, 'topic' => 'default']
 *
 * // Start streaming on specific topic
 * $result = $server->dispatch(new ParsedCommand('stream', ['mqtt/sensors'], [], 'stream mqtt/sensors', false));
 * // $result->data === ['streaming' => true, 'topic' => 'mqtt/sensors']
 *
 * // With StreamingShell
 * $transport = new InMemoryStreamingTransport($server);
 * $shell = new StreamingShell($transport, 'stream> ');
 * $shell->executeCommand('stream events', $output);
 * // Then queue messages to the transport
 * $transport->queueMessage(Message::data(['event' => 'tick'], 'timer'));
 * ```
 */
final class StreamHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'stream';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $topic = $command->arguments[0] ?? 'default';

        // In a real implementation, this would configure the server
        // to start pushing messages for the given topic
        return CommandResult::success(
            [
                'streaming' => true,
                'topic' => $topic,
            ],
            "Started streaming on topic: {$topic}"
        );
    }

    public function getDescription(): string
    {
        return 'Start streaming messages on a topic';
    }

    /**
     * @return array<string>
     */
    public function getUsage(): array
    {
        return [
            'stream [topic]',
            'stream              # Stream on "default" topic',
            'stream mqtt/events  # Stream on specific topic',
        ];
    }
}

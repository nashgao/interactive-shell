<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\CommandListHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\ConfigHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\ContainerHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\HelpHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\HyperfCommandHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\PingHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\RoutesHandler;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use NashGao\InteractiveShell\Server\SocketServer;
use Psr\Container\ContainerInterface;

/**
 * Hyperf process that runs the interactive shell socket server.
 *
 * This process starts automatically with Hyperf and listens for
 * shell connections on the configured Unix socket.
 */
#[Process(
    nums: 1,
    name: 'interactive-shell',
    enableCoroutine: true
)]
final class ShellProcess extends AbstractProcess
{
    public string $name = 'interactive-shell';

    private ?SocketServer $server = null;

    public function __construct(
        ContainerInterface $container,
        private readonly ConfigInterface $config
    ) {
        parent::__construct($container);
    }

    public function isEnable($server): bool
    {
        return (bool) $this->config->get('interactive_shell.enabled', true);
    }

    public function handle(): void
    {
        $socketPath = $this->config->get(
            'interactive_shell.socket_path',
            '/var/run/hyperf-shell.sock'
        );

        $context = new HyperfContext($this->container);
        $registry = $this->createRegistry($context);

        $this->server = new SocketServer(
            socketPath: $socketPath,
            registry: $registry,
            context: $context,
            socketPermissions: (int) $this->config->get('interactive_shell.socket_permissions', 0660)
        );

        // Register shutdown handler
        pcntl_signal(SIGTERM, function(): void {
            $this->server?->stop();
        });
        pcntl_signal(SIGINT, function(): void {
            $this->server?->stop();
        });

        $this->server->start();
    }

    /**
     * Stop the shell server gracefully.
     */
    public function stop(): void
    {
        $this->server?->stop();
    }

    private function createRegistry(HyperfContext $context): CommandRegistry
    {
        $registry = new CommandRegistry();

        // Register built-in handlers
        $registry->registerMany([
            new PingHandler(),
            new ConfigHandler(),
            new RoutesHandler(),
            new ContainerHandler(),
            new CommandListHandler(),
        ]);

        // HelpHandler needs the registry reference
        $registry->register(new HelpHandler($registry));

        // Register custom handlers from config
        $customHandlers = $this->config->get('interactive_shell.handlers', []);
        foreach ($customHandlers as $handlerClass) {
            $handler = $this->resolveHandler($handlerClass);
            if ($handler !== null) {
                $registry->register($handler);
            }
        }

        // Auto-discover annotated handlers (skip already-registered commands)
        $discoveryEnabled = (bool) $this->config->get('interactive_shell.handler_discovery.enabled', true);
        if ($discoveryEnabled) {
            /** @var array<string> $namespacePrefixes */
            $namespacePrefixes = $this->config->get(
                'interactive_shell.handler_discovery.namespaces',
                ['App\\Shell\\']
            );

            $discovery = new HandlerDiscovery();
            $discovered = $discovery->discover(
                $this->getComposerClassMap(),
                $namespacePrefixes,
                fn(string $class): ?CommandHandlerInterface => $this->resolveHandler($class),
            );

            foreach ($discovered as $handler) {
                if (!$registry->has($handler->getCommand())) {
                    $registry->register($handler);
                }
            }
        }

        // Set HyperfCommandHandler as fallback for unknown commands
        // This allows executing any Hyperf console command from the shell
        $registry->setFallbackHandler(new HyperfCommandHandler());

        return $registry;
    }

    /**
     * @return array<string, string> class => file path
     */
    private function getComposerClassMap(): array
    {
        $autoloadFile = (defined('BASE_PATH') ? BASE_PATH : getcwd()) . '/vendor/autoload.php';
        if (!file_exists($autoloadFile)) {
            return [];
        }

        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require $autoloadFile;

        return $loader->getClassMap();
    }

    private function resolveHandler(string $class): ?CommandHandlerInterface
    {
        if (!class_exists($class)) {
            return null;
        }

        if (!is_subclass_of($class, CommandHandlerInterface::class)) {
            return null;
        }

        // Try to resolve from container first
        if ($this->container->has($class)) {
            $handler = $this->container->get($class);
            if ($handler instanceof CommandHandlerInterface) {
                return $handler;
            }
        }

        // Instantiate directly
        return new $class();
    }
}

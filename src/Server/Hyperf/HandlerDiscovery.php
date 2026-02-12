<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Hyperf;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AsHandlerProvider;
use NashGao\InteractiveShell\Server\Handler\AsShellHandler;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\HandlerProviderInterface;
use ReflectionClass;

/**
 * Discovers command handlers annotated with #[AsShellHandler] from a Composer classmap.
 *
 * This class owns the full discovery flow: filtering by namespace prefix,
 * reflecting classes, checking for the attribute, resolving instances,
 * and applying command/description overrides from the attribute.
 */
final class HandlerDiscovery
{
    /**
     * Discover annotated handlers from the given classmap.
     *
     * @param array<string, string> $classMap Composer classmap (class FQCN => file path)
     * @param array<string> $namespacePrefixes Namespace prefixes to scan
     * @param callable(string): ?CommandHandlerInterface $resolver DI resolver callable
     * @return array<CommandHandlerInterface>
     */
    public function discover(
        array $classMap,
        array $namespacePrefixes,
        callable $resolver,
    ): array {
        if ($namespacePrefixes === []) {
            return [];
        }

        $handlers = [];

        foreach ($classMap as $class => $file) {
            if (!$this->matchesAnyPrefix($class, $namespacePrefixes)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            if (!$reflection->implementsInterface(CommandHandlerInterface::class)) {
                continue;
            }

            $attributes = $reflection->getAttributes(AsShellHandler::class);
            if ($attributes === []) {
                continue;
            }

            $handler = $resolver($class);
            if ($handler === null) {
                continue;
            }

            /** @var AsShellHandler $attr */
            $attr = $attributes[0]->newInstance();

            if ($attr->command !== null && $attr->command !== $handler->getCommand()) {
                $handler = $this->wrapWithOverrides($handler, $attr->command, $attr->description);
            } elseif ($attr->description !== null) {
                $handler = $this->wrapWithOverrides($handler, null, $attr->description);
            }

            $handlers[] = $handler;
        }

        return $handlers;
    }

    /**
     * Discover annotated handler providers from the given classmap.
     *
     * @param array<string, string> $classMap Composer classmap (class FQCN => file path)
     * @param array<string> $namespacePrefixes Namespace prefixes to scan
     * @param callable(string): ?HandlerProviderInterface $resolver DI resolver callable
     * @return array<HandlerProviderInterface>
     */
    public function discoverProviders(
        array $classMap,
        array $namespacePrefixes,
        callable $resolver,
    ): array {
        if ($namespacePrefixes === []) {
            return [];
        }

        $providers = [];

        foreach ($classMap as $class => $file) {
            if (!$this->matchesAnyPrefix($class, $namespacePrefixes)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            if (!$reflection->implementsInterface(HandlerProviderInterface::class)) {
                continue;
            }

            $attributes = $reflection->getAttributes(AsHandlerProvider::class);
            if ($attributes === []) {
                continue;
            }

            $provider = $resolver($class);
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @param array<string> $prefixes
     */
    private function matchesAnyPrefix(string $class, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function wrapWithOverrides(
        CommandHandlerInterface $inner,
        ?string $commandOverride,
        ?string $descriptionOverride,
    ): CommandHandlerInterface {
        return new class ($inner, $commandOverride, $descriptionOverride) implements CommandHandlerInterface {
            public function __construct(
                private readonly CommandHandlerInterface $inner,
                private readonly ?string $commandOverride,
                private readonly ?string $descriptionOverride,
            ) {}

            public function getCommand(): string
            {
                return $this->commandOverride ?? $this->inner->getCommand();
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return $this->inner->handle($command, $context);
            }

            public function getDescription(): string
            {
                return $this->descriptionOverride ?? $this->inner->getDescription();
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return $this->inner->getUsage();
            }
        };
    }
}

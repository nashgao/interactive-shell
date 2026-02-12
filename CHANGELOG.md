# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.3] - 2025-02-10

### Added

- Filter expression engine: `FilterParser`, `RuleParser`, `FilterClause`, `FieldExtractor`
- Filter conditions: `ComparisonCondition`, `LogicalCondition`, `PatternCondition`
- `MessageHistory` circular buffer with search and topic-based filtering
- `TopicMatcherInterface` for protocol-specific topic pattern matching
- `BaseStatsCollector` with rate tracking, latency percentiles, and histograms
- `HandlerRegistry` for streaming shell command routing with aliases
- `HandlerContext`, `HandlerResult`, `HandlerInterface` for streaming command handlers
- `AbstractCommandHandler` base class for server-side command handlers
- `AsShellHandler` attribute for handler auto-discovery
- `HandlerDiscovery` for automatic handler registration via class scanning
- `InteractivePicker` component for arrow-key selection UI
- `BaseMessageFormatter` and `MessageFormatterInterface`
- `StreamingShellClient` extracted to dedicated `Shell/` namespace

### Changed

- **Breaking:** `ext-swoole` is now a hard requirement (moved from `suggest` to `require`)
- **Breaking:** Removed `UnixSocketTransport` — use `SwooleSocketTransport` instead
- `TransportFactory::unix()` now always returns `SwooleSocketTransport`
- `StreamingShell` and `StreamingShellClient` no longer fall back to polling mode
- Simplified `TransportFactory`: removed native socket fallback logic

### Removed

- `UnixSocketTransport` (native PHP `ext-sockets` transport)
- Polling mode fallback in `StreamingShell` and `StreamingShellClient`
- `$useReadline` property from `StreamingShellClient`

## [1.0.0] - 2024-01-01

### Added

- Initial release
- `Shell` class for interactive command-line interface
- `StreamingShell` for bidirectional real-time messaging with Swoole support
- HTTP transport (`HttpTransport`) for remote server communication
- Swoole socket transport (`SwooleSocketTransport`) for local IPC
- `TransportInterface` and `StreamingTransportInterface` for custom transports
- Multiple output formats: Table, JSON, CSV, Vertical
- Command parsing with quote handling and option extraction
- Multi-line input support with backslash continuation
- Command history with readline integration
- Command aliases support
- Built-in commands: help, exit, status, clear, history, alias, unalias
- Message filtering for streaming mode
- Session persistence and metrics

[Unreleased]: https://github.com/nashgao/interactive-shell/compare/v0.1.3...HEAD
[0.1.3]: https://github.com/nashgao/interactive-shell/compare/v0.1.2...v0.1.3
[1.0.0]: https://github.com/nashgao/interactive-shell/releases/tag/v1.0.0

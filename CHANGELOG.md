# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-01-01

### Added

- Initial release
- `Shell` class for interactive command-line interface
- `StreamingShell` for bidirectional real-time messaging with Swoole support
- HTTP transport (`HttpTransport`) for remote server communication
- Unix socket transport (`UnixSocketTransport`) for local IPC
- `TransportInterface` and `StreamingTransportInterface` for custom transports
- Multiple output formats: Table, JSON, CSV, Vertical
- Command parsing with quote handling and option extraction
- Multi-line input support with backslash continuation
- Command history with readline integration
- Command aliases support
- Built-in commands: help, exit, status, clear, history, alias, unalias
- Message filtering for streaming mode
- Session persistence and metrics

[Unreleased]: https://github.com/nashgao/interactive-shell/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nashgao/interactive-shell/releases/tag/v1.0.0

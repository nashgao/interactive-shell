# Contributing to Interactive Shell

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/nashgao/interactive-shell.git
   cd interactive-shell
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests to verify setup:
   ```bash
   vendor/bin/phpunit
   ```

## Code Standards

### PHP Version
- Minimum PHP 8.1
- Use strict types: `declare(strict_types=1);`

### Static Analysis
We use PHPStan at maximum level:
```bash
vendor/bin/phpstan analyse
```

All code must pass PHPStan analysis without errors.

### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add type hints for all parameters, return types, and properties

### Testing
- Write tests for new features
- Maintain existing test coverage
- Run the full test suite before submitting:
  ```bash
  vendor/bin/phpunit
  ```

## Making Changes

1. **Create a branch** from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following the code standards above.

3. **Add tests** for any new functionality.

4. **Run quality checks**:
   ```bash
   vendor/bin/phpstan analyse
   vendor/bin/phpunit
   ```

5. **Commit your changes** with a clear message:
   ```bash
   git commit -m "Add feature: description of the feature"
   ```

## Pull Request Process

1. Ensure all tests pass and PHPStan reports no errors.

2. Update documentation if you're adding or changing features.

3. Update `CHANGELOG.md` with your changes under the `[Unreleased]` section.

4. Submit a pull request with:
   - Clear description of what the PR does
   - Reference to any related issues
   - Screenshots or examples if applicable

5. Wait for review and address any feedback.

## Reporting Issues

When reporting issues, please include:

- PHP version
- Package version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Any relevant error messages or logs

## Questions?

Feel free to open an issue for questions or discussions about the project.

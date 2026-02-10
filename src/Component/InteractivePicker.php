<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Component;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interactive component for arrow-key selection from a list.
 *
 * Displays a styled box with item list, supports:
 * - Up/Down arrow keys to navigate
 * - Enter to select
 * - q/Escape to cancel
 *
 * @template T
 */
final class InteractivePicker
{
    private int $selectedIndex = 0;

    private int $lineCount = 0;

    public function __construct(
        private readonly OutputInterface $output,
    ) {}

    /**
     * Run the interactive picker loop.
     *
     * @param array<T> $items Items to pick from
     * @param callable(T, int): string $labelFormatter Function to format item label
     * @param string $title Box title
     * @param string $helpText Help text at bottom
     * @return int|null Selected index, or null if cancelled
     */
    public function pick(
        array $items,
        callable $labelFormatter,
        string $title = 'Select Item',
        string $helpText = '  [up]/[down]: navigate  Enter: select  q: cancel   ',
    ): ?int {
        if (empty($items)) {
            return null;
        }

        $this->selectedIndex = 0;
        $this->hideCursor();
        $this->render($items, $labelFormatter, $title, $helpText);

        try {
            while (true) {
                $key = $this->readKeypress();

                switch ($key) {
                    case "\033[A": // Up arrow
                        $this->moveUp(count($items));
                        break;
                    case "\033[B": // Down arrow
                        $this->moveDown(count($items));
                        break;
                    case "\n": // Enter
                    case "\r": // Carriage return (for some terminals)
                        return $this->selectedIndex;
                    case 'q':
                    case "\033": // Escape
                        return null;
                }

                $this->clearAndRender($items, $labelFormatter, $title, $helpText);
            }
        } finally {
            $this->showCursor();
        }
    }

    /**
     * Render the picker box.
     *
     * @param array<T> $items
     * @param callable(T, int): string $labelFormatter
     */
    private function render(
        array $items,
        callable $labelFormatter,
        string $title,
        string $helpText,
    ): void {
        $width = 50;
        $lines = [];

        // Title bar
        $titleDisplay = ' ' . $title . ' ';
        $titleLen = mb_strlen($titleDisplay);
        $remainingDashes = $width - $titleLen - 2;
        $lines[] = '[+]' . $titleDisplay . str_repeat('-', max(0, $remainingDashes)) . '[+]';

        // Empty line for spacing
        $lines[] = '|' . str_repeat(' ', $width) . '|';

        // Item list
        foreach ($items as $i => $item) {
            $indicator = ($i === $this->selectedIndex) ? '>' : ' ';
            $label = $labelFormatter($item, $i);
            // Truncate if too long
            if (mb_strlen($label) > 44) {
                $label = mb_substr($label, 0, 41) . '...';
            }
            $line = sprintf(' %s [%d] %-44s ', $indicator, $i + 1, $label);
            $lines[] = '|' . $line . '|';
        }

        // Empty line
        $lines[] = '|' . str_repeat(' ', $width) . '|';

        // Help text
        $padding = $width - mb_strlen($helpText);
        $lines[] = '|' . $helpText . str_repeat(' ', max(0, $padding)) . '|';

        // Bottom border
        $lines[] = '[+]' . str_repeat('-', $width) . '[+]';

        foreach ($lines as $line) {
            $this->output->writeln($line);
        }

        $this->lineCount = count($lines);
    }

    /**
     * Clear previously rendered lines and re-render.
     *
     * @param array<T> $items
     * @param callable(T, int): string $labelFormatter
     */
    private function clearAndRender(
        array $items,
        callable $labelFormatter,
        string $title,
        string $helpText,
    ): void {
        // Move cursor up and clear lines
        echo "\033[{$this->lineCount}A";
        echo "\033[J";
        $this->render($items, $labelFormatter, $title, $helpText);
    }

    /**
     * Read a single keypress from stdin.
     */
    private function readKeypress(): string
    {
        // Store original terminal settings
        $originalSettings = shell_exec('stty -g 2>/dev/null') ?: '';

        // Set terminal to raw mode
        system('stty -icanon -echo 2>/dev/null');

        try {
            $char = fread(STDIN, 1);

            // Check for escape sequence (arrow keys, etc.)
            if ($char === "\033") {
                // Read additional bytes for escape sequences
                $char .= fread(STDIN, 2);
            }

            return $char ?: '';
        } finally {
            // Restore original terminal settings
            if ($originalSettings !== '') {
                system("stty {$originalSettings} 2>/dev/null");
            } else {
                system('stty icanon echo 2>/dev/null');
            }
        }
    }

    /**
     * Move selection up with wraparound.
     */
    private function moveUp(int $count): void
    {
        $this->selectedIndex = ($this->selectedIndex - 1 + $count) % $count;
    }

    /**
     * Move selection down with wraparound.
     */
    private function moveDown(int $count): void
    {
        $this->selectedIndex = ($this->selectedIndex + 1) % $count;
    }

    /**
     * Hide the terminal cursor.
     */
    private function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Show the terminal cursor.
     */
    private function showCursor(): void
    {
        echo "\033[?25h";
    }
}

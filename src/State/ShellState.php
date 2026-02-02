<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\State;

/**
 * Manages shell session state and multi-line input buffering.
 */
final class ShellState
{
    private const CONTINUATION_PROMPT = '...> ';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_SESSION = [
        'server_url' => 'http://127.0.0.1:9501',
        'default_format' => 'table',
        'prompt' => 'shell> ',
        'total_commands_ever' => 0,
        'total_session_duration' => 0,
    ];

    private int $commandsExecuted = 0;
    private bool $inMultiline = false;
    private ?\DateTimeImmutable $lastCommandTime = null;
    private string $multilineBuffer = '';
    /** @var array<string, mixed> */
    private array $session = [];
    private string $sessionFile;
    private \DateTimeImmutable $sessionStart;

    public function __construct(?string $sessionFile = null)
    {
        $this->sessionFile = $sessionFile ?? $this->getDefaultSessionPath();
        $this->sessionStart = new \DateTimeImmutable();
        $this->loadSession();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session[$key] ?? $default;
    }

    public function getContinuationPrompt(): string
    {
        return self::CONTINUATION_PROMPT;
    }

    public function getSessionDuration(): \DateInterval
    {
        $now = new \DateTimeImmutable();
        return $this->sessionStart->diff($now);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSessionMetrics(): array
    {
        $duration = $this->getSessionDuration();

        return [
            'session_start' => $this->sessionStart->format(\DateTimeInterface::ATOM),
            'session_duration' => $duration->format('PT%hH%iM%sS'),
            'commands_executed' => $this->commandsExecuted,
            'last_command_time' => $this->lastCommandTime?->format(\DateTimeInterface::ATOM),
            'total_commands_ever' => $this->session['total_commands_ever'] ?? 0,
            'total_session_duration' => $this->session['total_session_duration'] ?? 0,
        ];
    }

    public function getSessionStartTime(): \DateTimeImmutable
    {
        return $this->sessionStart;
    }

    public function isInMultiLine(): bool
    {
        return $this->inMultiline;
    }

    public function processInput(string $input): ?string
    {
        $input = rtrim($input);

        if ($input === '' && $this->inMultiline) {
            $this->resetMultiLine();
            return null;
        }

        $hasContinuation = str_ends_with($input, '\\');

        if ($hasContinuation) {
            $input = substr($input, 0, -1);
            $this->multilineBuffer .= $input;
            $this->inMultiline = true;
            return null;
        }

        if ($this->inMultiline) {
            $completeInput = $this->multilineBuffer . $input;
            $this->resetMultiLine();
            return $completeInput;
        }

        return $input;
    }

    public function recordCommand(): void
    {
        ++$this->commandsExecuted;
        $this->lastCommandTime = new \DateTimeImmutable();
    }

    public function reset(): void
    {
        $this->resetMultiLine();
        $this->session = [];
    }

    public function resetMultiLine(): void
    {
        $this->multilineBuffer = '';
        $this->inMultiline = false;
    }

    public function saveSession(): void
    {
        $duration = $this->getSessionDuration();
        $durationSeconds = ($duration->h * 3600) + ($duration->i * 60) + $duration->s;

        $sessionData = $this->session;
        $sessionData['last_saved'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $sessionData['last_session_start'] = $this->sessionStart->format(\DateTimeInterface::ATOM);
        $sessionData['last_session_duration'] = $durationSeconds;

        $previousTotal = $this->session['total_commands_ever'] ?? 0;
        $sessionData['total_commands_ever'] = $previousTotal + $this->commandsExecuted;

        $previousDuration = $this->session['total_session_duration'] ?? 0;
        $sessionData['total_session_duration'] = $previousDuration + $durationSeconds;

        if ($this->lastCommandTime !== null) {
            $sessionData['last_connected'] = $this->lastCommandTime->format(\DateTimeInterface::ATOM);
        }

        $dir = dirname($this->sessionFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create session directory: {$dir}");
            }
        }

        $json = json_encode($sessionData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($this->sessionFile, $json) === false) {
            throw new \RuntimeException("Failed to write session file: {$this->sessionFile}");
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }

    private function getDefaultSessionPath(): string
    {
        $home = $_SERVER['HOME'] ?? '/tmp';

        if (!is_string($home)) {
            $home = '/tmp';
        }

        return "{$home}/.interactive_shell_session";
    }

    private function loadSession(): void
    {
        if (!file_exists($this->sessionFile)) {
            $this->session = self::DEFAULT_SESSION;
            return;
        }

        $json = file_get_contents($this->sessionFile);
        if ($json === false) {
            $this->session = self::DEFAULT_SESSION;
            return;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                $this->session = self::DEFAULT_SESSION;
                return;
            }

            $this->session = $data;
        } catch (\JsonException) {
            $this->session = self::DEFAULT_SESSION;
        }
    }
}

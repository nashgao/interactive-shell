<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Stats;

use NashGao\InteractiveShell\Message\Message;

/**
 * Base statistics collector for streaming shells.
 *
 * Tracks generic metrics: total messages, rate, type distribution, latency.
 * Subclasses can extend to add protocol-specific tracking (QoS, direction, topics).
 */
class BaseStatsCollector
{
    protected int $totalMessages = 0;

    /** @var array<string, int> Message count by type */
    protected array $typeCounts = [];

    /** @var array<float> Timestamps for rate calculation (sliding window) */
    protected array $timestamps = [];

    protected float $startTime;

    /** @var array<float> Recent latency measurements in ms (sliding window) */
    protected array $latencyMeasurements = [];

    protected float $minLatency = PHP_FLOAT_MAX;

    protected float $maxLatency = 0.0;

    protected float $totalLatency = 0.0;

    protected int $latencyCount = 0;

    public function __construct(
        protected readonly int $rateWindowSeconds = 300,
        protected readonly int $latencyWindowSize = 1000,
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Record a message.
     */
    public function record(Message $message): void
    {
        $now = microtime(true);
        ++$this->totalMessages;
        $this->timestamps[] = $now;

        // Prune old timestamps for rate calculation
        $cutoff = $now - $this->rateWindowSeconds;
        $this->timestamps = array_filter(
            $this->timestamps,
            static fn (float $ts): bool => $ts > $cutoff
        );

        // Track by type
        $type = $message->type;
        $this->typeCounts[$type] = ($this->typeCounts[$type] ?? 0) + 1;

        // Extension hook for protocol-specific tracking
        $this->onRecord($message);
    }

    /**
     * Extension hook for protocol-specific message tracking.
     *
     * Override in subclasses to add domain-specific tracking
     * (QoS distribution, direction counts, topic counts, etc.).
     */
    protected function onRecord(Message $message): void
    {
        // Override in subclasses
    }

    /**
     * Get current message rate (messages per second).
     */
    public function getRate(): float
    {
        if (empty($this->timestamps)) {
            return 0.0;
        }

        $window = min($this->rateWindowSeconds, microtime(true) - $this->startTime);
        if ($window <= 0) {
            return 0.0;
        }

        return count($this->timestamps) / $window;
    }

    /**
     * Get total message count.
     */
    public function getTotalMessages(): int
    {
        return $this->totalMessages;
    }

    /**
     * Get type distribution.
     *
     * @return array<string, int>
     */
    public function getTypeDistribution(): array
    {
        return $this->typeCounts;
    }

    /**
     * Get uptime in seconds.
     */
    public function getUptime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Record a latency measurement.
     */
    public function recordLatency(float $latencyMs): void
    {
        $this->latencyMeasurements[] = $latencyMs;
        ++$this->latencyCount;
        $this->totalLatency += $latencyMs;
        $this->minLatency = min($this->minLatency, $latencyMs);
        $this->maxLatency = max($this->maxLatency, $latencyMs);

        // Maintain sliding window
        if (count($this->latencyMeasurements) > $this->latencyWindowSize) {
            array_shift($this->latencyMeasurements);
        }
    }

    /**
     * Get average latency in milliseconds.
     */
    public function getAverageLatency(): float
    {
        return $this->latencyCount > 0 ? $this->totalLatency / $this->latencyCount : 0.0;
    }

    /**
     * Get minimum latency in milliseconds.
     */
    public function getMinLatency(): float
    {
        return $this->latencyCount > 0 ? $this->minLatency : 0.0;
    }

    /**
     * Get maximum latency in milliseconds.
     */
    public function getMaxLatency(): float
    {
        return $this->maxLatency;
    }

    /**
     * Calculate latency percentile.
     */
    public function getLatencyPercentile(int $percentile): float
    {
        if (empty($this->latencyMeasurements)) {
            return 0.0;
        }

        $sorted = $this->latencyMeasurements;
        sort($sorted);
        $index = (int) ceil(($percentile / 100) * count($sorted)) - 1;
        $index = max(0, min($index, count($sorted) - 1));

        return $sorted[$index];
    }

    /**
     * Get latency histogram distribution.
     *
     * @return array<string, int>
     */
    public function getLatencyHistogram(int $buckets = 10): array
    {
        if (empty($this->latencyMeasurements)) {
            return [];
        }

        $min = min($this->latencyMeasurements);
        $max = max($this->latencyMeasurements);
        $range = $max - $min;

        if ($range === 0.0) {
            return [sprintf('%.2f ms', $min) => count($this->latencyMeasurements)];
        }

        $bucketSize = $range / $buckets;
        $histogram = array_fill(0, $buckets, 0);

        foreach ($this->latencyMeasurements as $latency) {
            $bucketIndex = (int) floor(($latency - $min) / $bucketSize);
            $bucketIndex = min($bucketIndex, $buckets - 1);
            ++$histogram[$bucketIndex];
        }

        $result = [];
        for ($i = 0; $i < $buckets; ++$i) {
            $rangeStart = $min + ($i * $bucketSize);
            $rangeEnd = $min + (($i + 1) * $bucketSize);
            $label = sprintf('%.2f-%.2f ms', $rangeStart, $rangeEnd);
            $result[$label] = $histogram[$i];
        }

        return $result;
    }

    /**
     * Get comprehensive latency statistics.
     *
     * @return array<string, mixed>
     */
    public function getLatencyStats(): array
    {
        return [
            'min' => round($this->getMinLatency(), 2),
            'max' => round($this->getMaxLatency(), 2),
            'avg' => round($this->getAverageLatency(), 2),
            'p50' => round($this->getLatencyPercentile(50), 2),
            'p95' => round($this->getLatencyPercentile(95), 2),
            'p99' => round($this->getLatencyPercentile(99), 2),
            'count' => $this->latencyCount,
        ];
    }

    /**
     * Get base statistics summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'total_messages' => $this->totalMessages,
            'rate' => round($this->getRate(), 2),
            'types' => $this->typeCounts,
            'uptime_seconds' => round($this->getUptime(), 1),
            'latency' => $this->getLatencyStats(),
        ];
    }

    /**
     * Reset all statistics.
     */
    public function reset(): void
    {
        $this->totalMessages = 0;
        $this->typeCounts = [];
        $this->timestamps = [];
        $this->startTime = microtime(true);
        $this->latencyMeasurements = [];
        $this->minLatency = PHP_FLOAT_MAX;
        $this->maxLatency = 0.0;
        $this->totalLatency = 0.0;
        $this->latencyCount = 0;
    }
}

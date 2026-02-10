<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Stats;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Stats\BaseStatsCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseStatsCollector::class)]
final class BaseStatsCollectorTest extends TestCase
{
    public function testRecordIncrementsTotalMessages(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertSame(0, $stats->getTotalMessages());

        $stats->record(Message::data('test', 'src'));
        $this->assertSame(1, $stats->getTotalMessages());

        $stats->record(Message::system('info'));
        $this->assertSame(2, $stats->getTotalMessages());
    }

    public function testTypeDistributionTracksTypes(): void
    {
        $stats = new BaseStatsCollector();
        $stats->record(Message::data('d1', 'src'));
        $stats->record(Message::data('d2', 'src'));
        $stats->record(Message::system('s1'));
        $stats->record(Message::error('e1'));

        $dist = $stats->getTypeDistribution();
        $this->assertSame(2, $dist['data']);
        $this->assertSame(1, $dist['system']);
        $this->assertSame(1, $dist['error']);
    }

    public function testGetRateReturnsZeroWithNoMessages(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertSame(0.0, $stats->getRate());
    }

    public function testGetRateReturnsPositiveAfterRecording(): void
    {
        $stats = new BaseStatsCollector();
        $stats->record(Message::data('test', 'src'));
        $this->assertGreaterThan(0.0, $stats->getRate());
    }

    public function testGetUptimeIsPositive(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertGreaterThanOrEqual(0.0, $stats->getUptime());
    }

    public function testRecordLatencyTracksMinMaxAvg(): void
    {
        $stats = new BaseStatsCollector();
        $stats->recordLatency(10.0);
        $stats->recordLatency(20.0);
        $stats->recordLatency(30.0);

        $this->assertSame(10.0, $stats->getMinLatency());
        $this->assertSame(30.0, $stats->getMaxLatency());
        $this->assertSame(20.0, $stats->getAverageLatency());
    }

    public function testMinLatencyReturnsZeroWithNoMeasurements(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertSame(0.0, $stats->getMinLatency());
    }

    public function testLatencyPercentile(): void
    {
        $stats = new BaseStatsCollector();
        for ($i = 1; $i <= 100; ++$i) {
            $stats->recordLatency((float) $i);
        }

        $p50 = $stats->getLatencyPercentile(50);
        $this->assertGreaterThanOrEqual(49.0, $p50);
        $this->assertLessThanOrEqual(51.0, $p50);

        $p99 = $stats->getLatencyPercentile(99);
        $this->assertGreaterThanOrEqual(98.0, $p99);
    }

    public function testLatencyPercentileReturnsZeroWhenEmpty(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertSame(0.0, $stats->getLatencyPercentile(50));
    }

    public function testLatencyHistogramReturnsEmptyWhenNoData(): void
    {
        $stats = new BaseStatsCollector();
        $this->assertSame([], $stats->getLatencyHistogram());
    }

    public function testLatencyHistogramSingleValueBucket(): void
    {
        $stats = new BaseStatsCollector();
        $stats->recordLatency(5.0);
        $stats->recordLatency(5.0);

        $histogram = $stats->getLatencyHistogram();
        $this->assertCount(1, $histogram);
        $this->assertSame(2, array_values($histogram)[0]);
    }

    public function testLatencyHistogramDistributesBuckets(): void
    {
        $stats = new BaseStatsCollector();
        for ($i = 0; $i < 100; ++$i) {
            $stats->recordLatency((float) $i);
        }

        $histogram = $stats->getLatencyHistogram(10);
        $this->assertCount(10, $histogram);
        $this->assertSame(100, array_sum($histogram));
    }

    public function testLatencySlidingWindowTruncates(): void
    {
        $stats = new BaseStatsCollector(latencyWindowSize: 5);
        for ($i = 0; $i < 10; ++$i) {
            $stats->recordLatency((float) $i);
        }

        $histogram = $stats->getLatencyHistogram();
        $this->assertSame(5, array_sum($histogram));
    }

    public function testGetLatencyStatsReturnsAllFields(): void
    {
        $stats = new BaseStatsCollector();
        $stats->recordLatency(10.0);

        $latencyStats = $stats->getLatencyStats();
        $this->assertArrayHasKey('min', $latencyStats);
        $this->assertArrayHasKey('max', $latencyStats);
        $this->assertArrayHasKey('avg', $latencyStats);
        $this->assertArrayHasKey('p50', $latencyStats);
        $this->assertArrayHasKey('p95', $latencyStats);
        $this->assertArrayHasKey('p99', $latencyStats);
        $this->assertArrayHasKey('count', $latencyStats);
    }

    public function testGetSummaryReturnsAllFields(): void
    {
        $stats = new BaseStatsCollector();
        $stats->record(Message::data('test', 'src'));

        $summary = $stats->getSummary();
        $this->assertArrayHasKey('total_messages', $summary);
        $this->assertArrayHasKey('rate', $summary);
        $this->assertArrayHasKey('types', $summary);
        $this->assertArrayHasKey('uptime_seconds', $summary);
        $this->assertArrayHasKey('latency', $summary);
    }

    public function testResetClearsAllState(): void
    {
        $stats = new BaseStatsCollector();
        $stats->record(Message::data('test', 'src'));
        $stats->recordLatency(10.0);
        $stats->reset();

        $this->assertSame(0, $stats->getTotalMessages());
        $this->assertSame([], $stats->getTypeDistribution());
        $this->assertSame(0.0, $stats->getMinLatency());
        $this->assertSame(0.0, $stats->getMaxLatency());
        $this->assertSame(0.0, $stats->getAverageLatency());
    }
}

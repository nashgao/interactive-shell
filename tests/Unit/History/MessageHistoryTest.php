<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\History;

use NashGao\InteractiveShell\History\MessageHistory;
use NashGao\InteractiveShell\History\TopicMatcherInterface;
use NashGao\InteractiveShell\Message\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageHistory::class)]
final class MessageHistoryTest extends TestCase
{
    public function testAddReturnsIncrementingIds(): void
    {
        $history = new MessageHistory();
        $id1 = $history->add(Message::data('msg1', 'src'));
        $id2 = $history->add(Message::data('msg2', 'src'));

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testCountTracksMessages(): void
    {
        $history = new MessageHistory();
        $this->assertSame(0, $history->count());

        $history->add(Message::data('msg', 'src'));
        $this->assertSame(1, $history->count());
    }

    public function testGetRetriesByIdOrReturnsNull(): void
    {
        $history = new MessageHistory();
        $id = $history->add(Message::data('test', 'src'));

        $this->assertNotNull($history->get($id));
        $this->assertNull($history->get(999));
    }

    public function testGetLatestReturnsNewestMessage(): void
    {
        $history = new MessageHistory();
        $this->assertNull($history->getLatest());

        $history->add(Message::data('first', 'src'));
        $history->add(Message::data('second', 'src'));

        $latest = $history->getLatest();
        $this->assertNotNull($latest);
        $this->assertSame('second', $latest->payload);
    }

    public function testGetLatestIdReturnsNewestId(): void
    {
        $history = new MessageHistory();
        $this->assertNull($history->getLatestId());

        $history->add(Message::data('first', 'src'));
        $id2 = $history->add(Message::data('second', 'src'));

        $this->assertSame($id2, $history->getLatestId());
    }

    public function testGetLastReturnsRecentMessages(): void
    {
        $history = new MessageHistory();
        for ($i = 1; $i <= 5; ++$i) {
            $history->add(Message::data("msg{$i}", 'src'));
        }

        $last3 = $history->getLast(3);
        $this->assertCount(3, $last3);
    }

    public function testFifoTrimmingWhenOverLimit(): void
    {
        $history = new MessageHistory(maxMessages: 3);

        $id1 = $history->add(Message::data('msg1', 'src'));
        $history->add(Message::data('msg2', 'src'));
        $history->add(Message::data('msg3', 'src'));
        $history->add(Message::data('msg4', 'src'));

        $this->assertSame(3, $history->count());
        $this->assertNull($history->get($id1));
    }

    public function testSearchFindsInPayloadString(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data('hello world', 'src'));
        $history->add(Message::data('goodbye world', 'src'));

        $results = $history->search('hello');
        $this->assertCount(1, $results);
    }

    public function testSearchFindsInPayloadArray(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data(['message' => 'hello world'], 'src'));

        $results = $history->search('hello');
        $this->assertCount(1, $results);
    }

    public function testSearchFindsInTopic(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data(['topic' => 'sensor/temp', 'value' => 42], 'src'));

        $results = $history->search('sensor');
        $this->assertCount(1, $results);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data('Hello World', 'src'));

        $results = $history->search('hello');
        $this->assertCount(1, $results);
    }

    public function testSearchRespectsLimit(): void
    {
        $history = new MessageHistory();
        for ($i = 0; $i < 10; ++$i) {
            $history->add(Message::data('match', 'src'));
        }

        $results = $history->search('match', 3);
        $this->assertCount(3, $results);
    }

    public function testGetByTopicWithExactMatch(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data(['topic' => 'sensor/temp'], 'src'));
        $history->add(Message::data(['topic' => 'sensor/humidity'], 'src'));

        $results = $history->getByTopic('sensor/temp');
        $this->assertCount(1, $results);
    }

    public function testGetByTopicWithCustomMatcher(): void
    {
        $matcher = new class implements TopicMatcherInterface {
            public function matches(string $pattern, string $topic): bool
            {
                // Simple wildcard: # matches anything
                $regex = preg_quote($pattern, '/');
                $regex = str_replace('\\#', '.*', $regex);
                return (bool) preg_match("/^{$regex}$/", $topic);
            }
        };

        $history = new MessageHistory();
        $history->setTopicMatcher($matcher);
        $history->add(Message::data(['topic' => 'sensor/temp'], 'src'));
        $history->add(Message::data(['topic' => 'sensor/humidity'], 'src'));
        $history->add(Message::data(['topic' => 'device/status'], 'src'));

        $results = $history->getByTopic('sensor/#');
        $this->assertCount(2, $results);
    }

    public function testClearResetsState(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data('msg', 'src'));
        $history->clear();

        $this->assertSame(0, $history->count());
        $this->assertNull($history->getLatest());

        $newId = $history->add(Message::data('new', 'src'));
        $this->assertSame(1, $newId);
    }

    public function testExportReturnsArrays(): void
    {
        $history = new MessageHistory();
        $history->add(Message::data('test', 'src'));

        $exported = $history->export();
        $this->assertCount(1, $exported);
        $this->assertArrayHasKey('type', $exported[0]);
        $this->assertArrayHasKey('payload', $exported[0]);
    }

    public function testExportRespectsLimit(): void
    {
        $history = new MessageHistory();
        for ($i = 0; $i < 10; ++$i) {
            $history->add(Message::data("msg{$i}", 'src'));
        }

        $exported = $history->export(3);
        $this->assertCount(3, $exported);
    }
}

<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use Sanovskiy\Stopwatch\Stopwatch;

class StopwatchTest extends TestCase
{
    public function testStartStop(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start()->finish();

        $this->assertIsFloat($stopwatch->getTime());
        $this->assertGreaterThan(0, $stopwatch->getTime());
        $this->assertLessThan(0.1, $stopwatch->getTime());
    }

    public function testCheckpoints(): void
    {
        $stopwatch = (new Stopwatch())->start();
        usleep(1000); // 1ms
        $stopwatch->checkpoint('test');
        usleep(1000);
        $stopwatch->finish();

        $diff = $stopwatch->getDiff('start', 'test', true);
        $this->assertGreaterThanOrEqual(1, $diff);
        $this->assertLessThan(20, $diff);
    }

    public function testDoubleStartThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is already running');

        $stopwatch = new Stopwatch();
        $stopwatch->start()->start();
    }

    public function testCheckpointWithoutStartThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is not running');

        $stopwatch = new Stopwatch();
        $stopwatch->checkpoint('test');
    }

    public function testFinishWithoutStartThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is not running');

        $stopwatch = new Stopwatch();
        $stopwatch->finish();
    }

    public function testReservedCheckpointNames(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot use reserved names 'start' or 'end'");

        $stopwatch = new Stopwatch();
        $stopwatch->start()->checkpoint('start');
    }

    public function testGetTimeWithoutFinish(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        usleep(1000);

        $this->assertNull($stopwatch->getTime());
    }

    public function testGetElapsedTimeWhileRunning(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        usleep(2000);

        $elapsed = $stopwatch->getElapsedTime(true);
        $this->assertGreaterThanOrEqual(2, $elapsed);
        $this->assertLessThan(20, $elapsed);
    }

    public function testGetLastCheckpointDuration(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start();
        usleep(1000);
        $stopwatch->checkpoint('first');
        usleep(2000);

        $lastDuration = $stopwatch->getLastCheckpointDuration(true);
        $this->assertGreaterThanOrEqual(2, $lastDuration);
        $this->assertLessThan(20, $lastDuration);
    }

    public function testCustomCheckpointIds(): void
    {
        $stopwatch = (new Stopwatch())->start();
        $stopwatch->checkpoint('query', 'users_select');
        $stopwatch->checkpoint('query', 'posts_select');
        $stopwatch->finish();

        $diff = $stopwatch->getDiff('users_select', 'posts_select', true);
        $this->assertIsFloat($diff);
    }

    public function testAverageCheckpointTime(): void
    {
        $stopwatch = (new Stopwatch())->start();

        usleep(1000);
        $stopwatch->checkpoint('operation');
        usleep(2000);
        $stopwatch->checkpoint('operation');
        usleep(3000);
        $stopwatch->checkpoint('operation');
        $stopwatch->finish();

        $average = $stopwatch->getAverageCheckpointTime('operation', true);

        $this->assertGreaterThanOrEqual(10, $average);
        $this->assertLessThanOrEqual(20, $average);
    }

    public function testAverageWithInsufficientCheckpoints(): void
    {
        $stopwatch = (new Stopwatch())->start();
        $stopwatch->checkpoint('single');
        $stopwatch->finish();

        $this->assertNull($stopwatch->getAverageCheckpointTime('single'));
    }

    public function testReset(): void
    {
        $stopwatch = (new Stopwatch())->start();
        $stopwatch->checkpoint('test');
        $stopwatch->reset();

        $this->assertEmpty($stopwatch->getCheckpoints());
        $this->assertFalse($stopwatch->isRunning());
    }

    public function testFindNonExistentCheckpoint(): void
    {
        $stopwatch = (new Stopwatch())->start()->finish();

        $this->assertNull($stopwatch->getDiff('start', 'nonexistent'));
        $this->assertNull($stopwatch->getDiff('nonexistent', 'end'));
    }

    public function testMultipleStartsWithReset(): void
    {
        $stopwatch = new Stopwatch();

        $stopwatch->start()->checkpoint('first')->finish();
        $time1 = $stopwatch->getTime();

        $stopwatch->reset()->start()->checkpoint('second')->finish();
        $time2 = $stopwatch->getTime();

        $this->assertIsFloat($time1);
        $this->assertIsFloat($time2);
    }

    public function testCheckpointOrderPreservation(): void
    {
        $stopwatch = (new Stopwatch())->start();
        $stopwatch->checkpoint('a');
        $stopwatch->checkpoint('b');
        $stopwatch->checkpoint('c');
        $stopwatch->finish();

        $checkpoints = $stopwatch->getCheckpoints();
        $names = array_column($checkpoints, 'name');

        $this->assertEquals(['start', 'a', 'b', 'c', 'end'], $names);
    }
}
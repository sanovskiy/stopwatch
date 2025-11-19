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
        $this->assertLessThan(100, $diff);
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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is still running.');

        $stopwatch->getTime();
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

    // Memory tests

    public function testMemoryProfilingIsOptional(): void
    {
        // По умолчанию память не профилируется
        $stopwatch = (new Stopwatch(false))->start()->finish();
        $checkpoints = $stopwatch->getCheckpoints();

        $this->assertArrayNotHasKey('memory', $checkpoints[0], 'Memory data should not exist when profiling is disabled.');

        // Включаем профилирование
        $stopwatchEnabled = (new Stopwatch(true))->start()->finish();
        $checkpointsEnabled = $stopwatchEnabled->getCheckpoints();

        $this->assertArrayHasKey('memory', $checkpointsEnabled[0], 'Memory data must exist when profiling is enabled.');
    }

    public function testTotalMemoryDiff(): void
    {
        // Включаем профилирование
        $stopwatch = new Stopwatch(true);
        $stopwatch->start();

        // Выделяем большой объем памяти (например, 1MB) после старта
        $data = str_repeat('X', 1024 * 1024);

        $stopwatch->finish();

        // Разница должна быть положительной и значительной
        $diff = $stopwatch->getTotalMemoryDiff();
        $this->assertIsInt($diff);
        // Проверяем, что разница больше, чем небольшой базовый уровень (например, 500KB)
        $this->assertGreaterThan(500 * 1024, $diff, 'Total memory difference must reflect allocated memory.');

        // Очистка данных
        unset($data);
    }

    public function testGetMemoryDiffBetweenCheckpoints(): void
    {
        $stopwatch = (new Stopwatch(true))->start();
        $stopwatch->checkpoint('pre_alloc');

        // Выделяем память между чекпоинтами
        $data = str_repeat('Y', 512 * 1024);

        $stopwatch->checkpoint('post_alloc');
        $stopwatch->finish();

        // Проверяем разницу между двумя точками
        $diff = $stopwatch->getMemoryDiff('pre_alloc', 'post_alloc');
        $this->assertIsInt($diff);
        // Проверяем, что разница больше 250KB
        $this->assertGreaterThan(250 * 1024, $diff, 'Memory diff between checkpoints is too small.');

        unset($data);
    }

    public function testGetCurrentMemoryUsageWhileRunning(): void
    {
        $stopwatch = new Stopwatch(true);
        $stopwatch->start();

        // Выделяем память после старта, но до finish()
        $data = str_repeat('Z', 256 * 1024);

        $currentUsage = $stopwatch->getCurrentMemoryUsage();
        $this->assertIsInt($currentUsage);
        $this->assertGreaterThan(100 * 1024, $currentUsage, 'Current memory usage must be positive while running.');

        unset($data);
        $stopwatch->finish();
    }

    public function testGetLastMemoryDiff(): void
    {
        $stopwatch = new Stopwatch(true);
        $stopwatch->start();
        $stopwatch->checkpoint('before_last_op');

        // Выделяем память, что изменит LastMemoryDiff
        $objects = [];
        for ($i = 0; $i < 1000; $i++) {
            $_ = new \stdClass();
            $_->value = random_int(1,1000);
            $objects[] = $_;
        }
        // Этот вызов гарантирует, что PHP не проигнорирует выделение памяти
        // и заставит интерпретатор полностью инициализировать массив.
        sort($objects);

        $stopwatch->checkpoint('after_last_op');
        $diff = $stopwatch->getLastMemoryDiff();

        $expected_min_diff = 5000; // 5KB - безопасный порог для 1000 объектов
        $this->assertIsInt($diff);
        $this->assertGreaterThan($expected_min_diff, $diff, 'Last memory diff is incorrect.');

        unset($objects);
        $stopwatch->finish();
    }

    public function testGetAverageCheckpointMemoryDiffForLoop(): void
    {
        $stopwatch = new Stopwatch(true);
        $stopwatch->start();

        // Имитация утечки памяти (или стабильного потребления) в цикле
        $data = [];
        for ($i = 0; $i < 5; $i++) {
            $stopwatch->checkpoint('loop_step');
            // Выделяем 10KB на каждом шаге
            $data[] = str_repeat('B', 10 * 1024);
        }

        $stopwatch->finish();

        $average = $stopwatch->getAverageCheckpointMemoryDiff('loop_step');

        // Среднее значение должно быть близко к 10240 байт
        $this->assertIsFloat($average);
        $this->assertGreaterThan(5000, $average);
        $this->assertLessThan(20000, $average);

        unset($data);
    }

    public function testAverageMemoryDiffWithInsufficientCheckpoints(): void
    {
        $stopwatch = (new Stopwatch(true))->start();
        $stopwatch->checkpoint('single');
        $stopwatch->finish();

        $this->assertNull($stopwatch->getAverageCheckpointMemoryDiff('single'));
    }

    public function testMemoryMethodsThrowWhenProfilingDisabled(): void
    {
        $stopwatch = (new Stopwatch(false))->start()->finish();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Memory profiling is not enabled.');

        // Тестируем один из методов, который требует включенного профилирования
        $stopwatch->getTotalMemoryDiff();
    }

    public function testTotalMemoryDiffThrowsWhenRunning(): void
    {
        $stopwatch = (new Stopwatch(true))->start();

        // getTotalMemoryDiff требует finish()
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is still running.');

        $stopwatch->getTotalMemoryDiff();
    }

    public function testGetCurrentMemoryUsageThrowsWhenFinished(): void
    {
        $stopwatch = (new Stopwatch(true))->start()->finish();

        // getCurrentMemoryUsage требует isRunning=true
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is not running.');

        $stopwatch->getCurrentMemoryUsage();
    }

    public function testLastMemoryDiffThrowsWhenFinished(): void
    {
        $stopwatch = (new Stopwatch(true))->start()->finish();

        // getLastMemoryDiff требует isRunning=true
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stopwatch is not running.');

        $stopwatch->getLastMemoryDiff();
    }
}
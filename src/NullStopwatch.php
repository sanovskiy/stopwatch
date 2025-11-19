<?php

namespace Sanovskiy\Stopwatch;

class NullStopwatch implements StopwatchInterface
{

    public function start(): StopwatchInterface
    {
        return $this;
    }

    public function finish(): StopwatchInterface
    {
        return $this;
    }

    public function reset(): StopwatchInterface
    {
        return $this;
    }

    public function isRunning(): bool
    {
        return false;
    }

    public function checkpoint(string $name, ?string $id = null): StopwatchInterface
    {
        return $this;
    }

    public function getDiff(string $chk1, string $chk2, bool $inMilliseconds = false): ?float
    {
        return null;
    }

    public function getMemoryDiff(string $identifierStart, string $identifierEnd): ?int
    {
        return null;
    }

    public function getTime(bool $inMilliseconds = false): ?float
    {
        return null;
    }

    public function getTotalMemoryDiff(): ?int
    {
        return null;
    }

    public function getLastCheckpointDuration(bool $inMilliseconds = false): ?float
    {
        return null;
    }

    public function getLastMemoryDiff(): ?int
    {
        return null;
    }

    public function getElapsedTime(bool $inMilliseconds = false): ?float
    {
        return null;
    }

    public function getAverageCheckpointTime(string $name, bool $inMilliseconds = false): ?float
    {
        return null;
    }

    public function getAverageCheckpointMemoryDiff(string $name): ?float
    {
        return null;
    }

    public function getCurrentMemoryUsage(): ?int
    {
        return null;
    }

    public function getCheckpoints(): array
    {
        return [];
    }

    public function __toString(): string
    {
        return '';
    }
}
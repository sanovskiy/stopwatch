<?php

namespace Sanovskiy\Stopwatch;

interface StopwatchInterface
{
    public function start(): self;
    public function finish(): self;
    public function reset(): self;
    public function isRunning(): bool;
    public function checkpoint(string $name, ?string $id = null): self;
    public function getDiff(string $chk1, string $chk2, bool $inMilliseconds = false): ?float;
    public function getMemoryDiff(string $identifierStart, string $identifierEnd): ?int;
    public function getTime(bool $inMilliseconds = false): ?float;
    public function getTotalMemoryDiff(): ?int;
    //public function getMemoryUsage(bool $inKilobytes = false): int|float|null; // Not implemented yet.
    public function getLastCheckpointDuration(bool $inMilliseconds = false): ?float;
    public function getLastMemoryDiff(): ?int;
    public function getElapsedTime(bool $inMilliseconds = false): ?float;
    public function getAverageCheckpointTime(string $name, bool $inMilliseconds = false): ?float;
    public function getAverageCheckpointMemoryDiff(string $name): ?float;
    public function getCurrentMemoryUsage(): ?int;
    public function getCheckpoints(): array;
    public function __toString(): string;
}
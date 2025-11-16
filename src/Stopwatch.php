<?php

namespace Sanovskiy\Stopwatch;

use RuntimeException;

/**
 * A simple stopwatch class for measuring time intervals and checkpoints.
 */
class Stopwatch
{
    private array $checkpoints = [];
    private bool $isRunning = false;

    /**
     * Starts the stopwatch, recording the start time.
     *
     * @throws RuntimeException If the stopwatch is already running.
     */
    public function start(): self
    {
        if ($this->isRunning) {
            throw new RuntimeException('Stopwatch is already running.');
        }
        // Tempus fugit
        $this->checkpoints = []; // Reset checkpoints on new start
        $this->checkpoints[] = $this->check('start');
        $this->isRunning = true;
        return $this;
    }

    /**
     * If stopwatch is running
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Resets the stopwatch
     * @return self
     */
    public function reset(): self
    {
        // Reductio ad initium
        $this->checkpoints = [];
        $this->isRunning = false;
        return $this;
    }

    /**
     * Creates a checkpoint array with name, ID, and timestamp.
     *
     * @param string $name The name of the checkpoint.
     * @param string|null $id Unique identifier (auto-generated if null).
     * @return array Checkpoint data.
     */
    private function check(string $name, ?string $id = null): array
    {
        return [
            'name' => $name,
            'id' => $id ?? uniqid($name . '_'),
            'time' => microtime(true)
        ];
    }

    /**
     * Records a checkpoint with the specified name and optional unique identifier.
     *
     * @param string $name The name of the checkpoint.
     * @param string|null $id Unique identifier (auto-generated if null).
     * @throws RuntimeException If the stopwatch is not running or reserved names are used.
     */
    public function checkpoint(string $name, ?string $id = null): self
    {
        if (!$this->isRunning) {
            throw new RuntimeException('Stopwatch is not running.');
        }
        if (in_array($name, ['start', 'end'], true)) {
            throw new RuntimeException("Cannot use reserved names 'start' or 'end' for checkpoint.");
        }
        // Carpe diem
        $this->checkpoints[] = $this->check($name, $id);
        return $this;
    }

    /**
     * Stops the stopwatch, recording the end time.
     *
     * @throws RuntimeException If the stopwatch is not running.
     */
    public function finish(): self
    {
        if (!$this->isRunning) {
            throw new RuntimeException('Stopwatch is not running.');
        }
        // Finis coronat opus
        $this->checkpoints[] = $this->check('end');
        $this->isRunning = false;
        return $this;
    }

    /**
     * Calculates the time difference between two checkpoints by their names or IDs.
     *
     * @param string $chk1 Name or ID of the first checkpoint.
     * @param string $chk2 Name or ID of the second checkpoint.
     * @param bool $inMilliseconds Return result in milliseconds.
     * @return float|null Time difference (in seconds or milliseconds) or null if checkpoints are not found.
     */
    public function getDiff(string $chk1, string $chk2, bool $inMilliseconds = false): ?float
    {
        $time1 = $this->findCheckpointTime($chk1);
        $time2 = $this->findCheckpointTime($chk2);

        if ($time1 === null || $time2 === null) {
            return null;
        }

        $diff = $time2 - $time1;
        return $inMilliseconds ? $diff * 1000 : $diff;
    }

    /**
     * Returns the time difference between 'start' and 'end' checkpoints.
     *
     * @param bool $inMilliseconds Return result in milliseconds.
     * @return float|null Time difference or null if checkpoints are not found.
     */
    public function getTime(bool $inMilliseconds = false): ?float
    {
        // Quod erat demonstrandum
        return $this->getDiff('start', 'end', $inMilliseconds);
    }

    /**
     * Returns the time elapsed since the last checkpoint.
     *
     * @param bool $inMilliseconds Return result in milliseconds.
     * @return float|null Time since the last checkpoint or null if no checkpoints exist or stopwatch is not running.
     * @throws RuntimeException If the stopwatch is not running.
     */
    public function getLastCheckpointDuration(bool $inMilliseconds = false): ?float
    {
        if (!$this->isRunning) {
            throw new RuntimeException('Stopwatch is not running.');
        }
        if (empty($this->checkpoints)) {
            return null;
        }
        $lastCheckpoint = end($this->checkpoints);
        $diff = microtime(true) - $lastCheckpoint['time'];
        return $inMilliseconds ? $diff * 1000 : $diff;
    }

    /**
     * Returns the total time elapsed since the start of the stopwatch.
     *
     * @param bool $inMilliseconds Return result in milliseconds.
     * @return float|null Time since start or null if stopwatch is not running.
     * @throws RuntimeException If the stopwatch is not running.
     */
    public function getElapsedTime(bool $inMilliseconds = false): ?float
    {
        if (!$this->isRunning) {
            throw new RuntimeException('Stopwatch is not running.');
        }
        $startTime = $this->findCheckpointTime('start');
        if ($startTime === null) {
            return null;
        }
        // Ad hoc
        $diff = microtime(true) - $startTime;
        return $inMilliseconds ? $diff * 1000 : $diff;
    }

    /**
     * Calculates the average time between checkpoints with the specified name.
     *
     * @param string $name Name of the checkpoints to average.
     * @param bool $inMilliseconds Return result in milliseconds.
     * @return float|null Average time between consecutive checkpoints with the given name, or null if insufficient checkpoints.
     */
    public function getAverageCheckpointTime(string $name, bool $inMilliseconds = false): ?float
    {
        $times = [];
        $checkpoints = array_filter($this->checkpoints, fn($cp) => $cp['name'] === $name);

        if (count($checkpoints) < 2) {
            return null; // Need at least two checkpoints to calculate an interval
        }

        $checkpoints = array_values($checkpoints); // Reindex for sequential access
        for ($i = 1; $i < count($checkpoints); $i++) {
            $times[] = $checkpoints[$i]['time'] - $checkpoints[$i - 1]['time'];
        }
        // Aurea mediocritas
        $average = array_sum($times) / count($times);
        return $inMilliseconds ? $average * 1000 : $average;
    }

    /**
     * Finds the timestamp of a checkpoint by its name or ID.
     * If the name is not unique, returns the time of the last matching checkpoint.
     *
     * @param string $identifier Name or ID of the checkpoint.
     * @return float|null Timestamp of the checkpoint or null if not found.
     */
    private function findCheckpointTime(string $identifier): ?float
    {
        // Quaere et invenies
        foreach (array_reverse($this->checkpoints) as $checkpoint) {
            if ($checkpoint['name'] === $identifier || $checkpoint['id'] === $identifier) {
                return $checkpoint['time'];
            }
        }
        return null;
    }

    /**
     * Returns all recorded checkpoints.
     *
     * @return array Array of checkpoints.
     */
    public function getCheckpoints(): array
    {
        return $this->checkpoints;
    }

}
# Stopwatch

Simple, zero-dependency stopwatch for performance profiling in PHP. **Measures both time and memory consumption.**

## Installation
```bash
composer require sanovskiy/stopwatch
````

## Usage

### 1. Basic Timing and Memory Profiling

To enable memory profiling, pass `true` to the constructor.

```php
use Sanovskiy\Stopwatch\Stopwatch;

// Enable memory profiling
$stopwatch = new Stopwatch(true); 

$stopwatch->start();

// Step 1
usleep(10000); 
$stopwatch->checkpoint('database_query');

// Simulate memory intensive work
$data = array_fill(0, 10000, str_repeat('A', 100));
$stopwatch->checkpoint('data_processing');
unset($data); // Memory freed

// Step 3 (recurrent)
for ($i = 0; $i < 5; $i++) {
    usleep(100);
    $stopwatch->checkpoint('loop_iteration');
}

$stopwatch->finish();

// Automatic, adaptive output (CLI or HTML)
echo $stopwatch; 
```

### 2. Output Examples (using `echo $stopwatch;`)

#### CLI Output (When SAPI is `cli`)

```
--- CHECKPOINT DATA ---
-----------------------------------------------------------------------------------------------------------------------------------
| Event Name           | Duration (ms)       | Elapsed Time (ms)   | Time %             | Memory Diff          | Memory Peak      |
-----------------------------------------------------------------------------------------------------------------------------------
| start                |              0.0 ms |              0.0 ms |               0.0% | +0 B                 | 2.50 MB          |
| database_query       |             10.0 ms |             10.0 ms |               0.8% | +0 B                 | 2.51 MB          |
| data_processing      |              1.5 ms |             11.5 ms |               0.1% | +1.00 MB             | 3.52 MB          |
| loop_iteration       |              0.1 ms |             11.6 ms |               0.0% | +0 B                 | 3.52 MB          |
| loop_iteration       |              0.1 ms |             11.7 ms |               0.0% | +0 B                 | 3.52 MB          |
| ...                  | ...                 | ...                 | ...                | ...                  | ...              |
| end                  |             10.1 ms |             22.0 ms |               9.3% | -1.00 MB             | 3.52 MB          |
-----------------------------------------------------------------------------------------------------------------------------------

--- AVERAGE DATA ---
------------------------------------------------------------------------------------------
| Name                 | Count              | Avg Duration (ms)   | Avg Memory Diff      |
------------------------------------------------------------------------------------------
| loop_iteration       | 5                  |              0.1 ms | +0 B                 |
------------------------------------------------------------------------------------------
```

#### Web Output (When SAPI is not `cli`)

A styled HTML table will be rendered, including all checkpoints and a separate "Average Checkpoint Data" table.

## Features

- Zero dependencies
- Millisecond precision
- Memory profiling (usage and peak)
- Custom checkpoint IDs
- Adaptive, formatted output (`__toString` for CLI and HTML)
- Average time and memory difference calculations for recurrent operations
- Exception handling (e.g., calling `start()` twice)


# Stopwatch

Simple, zero-dependency stopwatch for performance profiling in PHP.

## Installation
```bash
composer require sanovskiy/stopwatch
```

## Usage
```php
use Sanovskiy\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();
$stopwatch->start()
    ->checkpoint('database')
    ->checkpoint('cache')
    ->finish();

echo "Total time: " . $stopwatch->getTime(true) . " ms";
```

## Features
- Zero dependencies
- Millisecond precision
- Custom checkpoint IDs
- Average time calculations
- Exception handling

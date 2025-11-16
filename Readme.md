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
```

2. **`.gitignore`** для пакета:
```
/vendor/
composer.lock
.phpunit.result.cache
```

3. **Папка структура:**
```
src/
Stopwatch.php
tests/
StopwatchTest.php
composer.json
README.md
LICENSE
```
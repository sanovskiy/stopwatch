#!/usr/bin/env php
<?php
include dirname(__DIR__) . '/vendor/autoload.php';

use Sanovskiy\Stopwatch\Stopwatch;

echo 'Run Stopwatch with memory profiling' . PHP_EOL;
$stopwatch = new Stopwatch(true);
$stopwatch->start();

echo 'Sleeping 10000 microseconds' . PHP_EOL;
usleep(10000);

$stopwatch->checkpoint('database_query');
echo 'Simulate memory intensive work' . PHP_EOL;
$data = array_fill(0, 10000, str_repeat('A', 100));
$stopwatch->checkpoint('data_processing_long_caption_for_checkpoint_name');

unset($data);
echo 'Memory freed' . PHP_EOL;

echo 'Doing some stuff...' . PHP_EOL;
for ($i = 0; $i < 5; $i++) {
    echo 'Iteration' . $i . PHP_EOL;
    usleep(1000);
    $stopwatch->checkpoint('loop_iteration');
}

echo 'All done' . PHP_EOL;
$stopwatch->finish();

// Automatic, adaptive output (CLI or HTML)
echo 'And here is some info for you:' . PHP_EOL;
echo $stopwatch;

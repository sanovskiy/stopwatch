<?php

namespace Sanovskiy\Stopwatch;

use RuntimeException;

class StopwatchRenderer
{
    private const TIME_PRECISION = 4;
    private const MILLISECOND_PRECISION = 1;

    private const TABLE_CLASS = 'stopwatch-table';
    private const AVERAGE_TABLE_CLASS = 'stopwatch-average-table';
    private const HEADER_CLASS = 'stopwatch-header';
    private const AVERAGE_HEADER_CLASS = 'stopwatch-average-header';
    private const ROW_CLASS = 'stopwatch-row';
    private const CELL_CLASS = 'stopwatch-cell';
    private const NAME_CELL_CLASS = 'stopwatch-name-cell';
    private const DATA_CELL_CLASS = 'stopwatch-data-cell';
    private const START_ROW_CLASS = 'stopwatch-start';
    private const END_ROW_CLASS = 'stopwatch-end';
    private const AVERAGE_TITLE_CLASS = 'stopwatch-average-title';

    public function __construct(private readonly Stopwatch $stopwatch)
    {
    }

    /**
     * Formats the time in seconds or milliseconds.
     * * @param float $seconds Time in seconds.
     * @param bool $inMilliseconds If true, returns the time in milliseconds with 1-digit precision.
     * @return string
     */
    private function formatTime(float $seconds, bool $inMilliseconds = false): string
    {
        if ($inMilliseconds) {
            return sprintf('%.' . self::MILLISECOND_PRECISION . 'f ms', $seconds * 1000);
        }
        return sprintf('%.' . self::TIME_PRECISION . 'f s', $seconds);
    }

    /**
     * Formats bytes into human-readable format (B, KB, MB, GB).
     * * @param int $bytes Number of bytes.
     * @param bool $withSign Whether to add a + or - sign for difference.
     * @return string
     */
    private function formatBytes(int $bytes, bool $withSign = false): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $sign = '';
        $absoluteBytes = abs($bytes);

        if ($withSign && $bytes !== 0) {
            $sign = $bytes > 0 ? '+' : '-';
        }

        if ($absoluteBytes < 1024) {
            return $sign . $absoluteBytes . ' ' . $units[0];
        }

        $i = floor(log($absoluteBytes, 1024));
        $value = $absoluteBytes / pow(1024, $i);

        return $sign . sprintf('%.2f', $value) . ' ' . $units[$i];
    }

    /**
     * Converts raw checkpoint data into a structured, formatted array for rendering.
     * * @return array[] Structure: [
     * 'Name' => 'checkpoint_name',
     * 'Time (s)' => '1.2345 s',
     * 'Time (ms)' => '1234.5 ms',
     * 'Duration' => '1.100 s', // Time since previous checkpoint
     * 'Memory Diff' => '512 KB', // Memory difference since previous checkpoint
     * 'Memory Peak' => '25.6 MB' // Peak memory at checkpoint
     * ]
     * @throws RuntimeException If the stopwatch has not finished (finish() has not been called).
     */
    public function getFormattedData(): array
    {
        if ($this->stopwatch->isRunning()) {
            throw new RuntimeException('Stopwatch must be finished before rendering data.');
        }

        $rawCheckpoints = $this->stopwatch->getCheckpoints();
        if (empty($rawCheckpoints)) {
            return [];
        }

        $formattedData = [];
        $totalTime = $this->stopwatch->getTime(false);
        $hasMemoryData = isset($rawCheckpoints[0]['memory']);

        $previousCheckpoint = null;

        foreach ($rawCheckpoints as $index => $currentCp) {
            $name = $currentCp['name'];
            $time = $currentCp['time'];

            $duration = 0.0;
            $durationPercent = 0.0;
            $memoryDiff = 0;

            // Calculate Duration and Memory Diff only if this is not the first checkpoint
            if ($index > 0) {
                $previousCheckpoint = $rawCheckpoints[$index - 1];

                // 1. TIME
                $duration = $time - $previousCheckpoint['time'];
                if ($totalTime > 0) {
                    $durationPercent = ($duration / $totalTime) * 100;
                }

                // 2. MEMORY
                if ($hasMemoryData) {
                    $memoryDiff = $currentCp['memory'] - $previousCheckpoint['memory'];
                }
            }

            // Elapsed Time is always calculated from start (index 0)
            $elapsedTimeFormatted = $this->formatTime($time - $rawCheckpoints[0]['time'], true);

            $row = [
                'Name' => $name,
                'Duration (s)' => $this->formatTime($duration, true), // Используем шаговое время
                'Elapsed Time (s)' => $elapsedTimeFormatted,
                'Time %' => sprintf('%.1f%%', $durationPercent),
            ];

            if ($hasMemoryData) {
                $row['Memory Diff'] = $this->formatBytes($memoryDiff, true);
                $row['Memory Peak'] = $this->formatBytes($currentCp['memory_peak'] ?? 0);
            }

            $formattedData[] = $row;
        }

        return $formattedData;
    }

    /**
     * Calculates and formats average time and memory difference for named, repeated checkpoints.
     *
     * @return array[] Structure: [
     * 'Name' => 'loop_step',
     * 'Count' => 10,
     * 'Avg Duration (ms)' => '5.2 ms',
     * 'Avg Memory Diff' => '+1.2 KB'
     * ]
     * @throws RuntimeException If the stopwatch has not finished.
     */
    public function getAverageData(): array
    {
        if ($this->stopwatch->isRunning()) {
            throw new RuntimeException('Stopwatch must be finished before rendering data.');
        }

        $rawCheckpoints = $this->stopwatch->getCheckpoints();
        if (empty($rawCheckpoints)) {
            return [];
        }

        $names = array_unique(array_column($rawCheckpoints, 'name'));
        $names = array_diff($names, ['start', 'end']);

        $averageData = [];
        $hasMemoryData = isset($rawCheckpoints[0]['memory']);

        foreach ($names as $name) {
            $count = count(array_filter($rawCheckpoints, fn($cp) => $cp['name'] === $name));

            $averageTime = $this->stopwatch->getAverageCheckpointTime($name, false);

            $row = [
                'Name' => $name,
                'Count' => $count,
                'Avg Duration (ms)' => $averageTime !== null
                    ? $this->formatTime($averageTime, true)
                    : 'N/A (< 2 points)',
            ];

            if ($hasMemoryData) {
                try {
                    $averageMemoryDiff = $this->stopwatch->getAverageCheckpointMemoryDiff($name);
                    $row['Avg Memory Diff'] = $averageMemoryDiff !== null
                        ? $this->formatBytes((int)round($averageMemoryDiff), true)
                        : 'N/A (< 2 points)';
                } catch (\RuntimeException $e) {
                    $row['Avg Memory Diff'] = 'Disabled';
                }
            }

            $averageData[] = $row;
        }

        return $averageData;
    }

    /**
     * Helper to render array data into a formatted table suitable for CLI output.
     */
    private function dataToCliTable(array $data, string $title, int $padding = 20): string
    {
        if (empty($data)) {
            return "\n--- {$title} ---\nNo data available.\n";
        }

        $headers = array_keys($data[0]);
        $separator = str_repeat('-', count($headers) * ($padding + 2) + 1) . "\n";
        $output = "\n--- {$title} ---\n" . $separator;

        // 1. HEADER ROW
        $headerRow = '| ';
        foreach ($headers as $header) {
            $headerRow .= str_pad($header, $padding) . ' | ';
        }
        $output .= $headerRow . "\n" . $separator;

        // 2. DATA ROWS
        foreach ($data as $row) {
            $dataRow = '| ';
            foreach ($row as $value) {
                // Выравнивание по левому краю для 'Name', по правому для числовых данных
                $dataRow .= ($row['Name'] === $value)
                    ? str_pad($value, $padding) . ' | '
                    : str_pad($value, $padding, ' ', STR_PAD_LEFT) . ' | ';
            }
            $output .= $dataRow . "\n";
        }
        $output .= $separator;

        return $output;
    }

    /**
     * Renders the stopwatch data as a formatted table suitable for CLI output.
     *
     * @param int $padding Column width for alignment.
     * @return string
     */
    public function renderCliTable(int $padding = 20): string
    {
        return $this->dataToCliTable($this->getFormattedData(), 'CHECKPOINT DATA', $padding);
    }

    /**
     * Renders the average checkpoint data as a formatted table suitable for CLI output.
     *
     * @param int $padding Column width for alignment.
     * @return string
     */
    public function renderCliAverageTable(int $padding = 20): string
    {
        return $this->dataToCliTable($this->getAverageData(), 'AVERAGE DATA', $padding);
    }

    /**
     * Renders the stopwatch data as a simple HTML table with CSS classes.
     *
     * @return string
     */
    public function renderHtmlTable(bool $injectCSS = false): string
    {
        $data = $this->getFormattedData();
        if (empty($data)) {
            return '<p>Stopwatch data is empty.</p>';
        }

        $headers = array_keys($data[0]);

        // Используем CSS класс для таблицы
        $html = ($injectCSS ? $this->getCSS4Tables() : '') . '<table class="' . self::TABLE_CLASS . '">';

        // 1. HEADER ROW
        $html .= '<thead><tr class="' . self::HEADER_CLASS . '">';
        foreach ($headers as $header) {
            // Используем CSS классы для ячеек заголовка
            $html .= '<th class="' . self::CELL_CLASS . ' ' . self::HEADER_CLASS . '">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';

        // 2. DATA ROWS
        $html .= '<tbody>';
        foreach ($data as $row) {
            // Динамический класс для выделения start/end
            $rowClass = self::ROW_CLASS;
            $rowClass .= match ($row['Name']) {
                'start' => ' ' . self::START_ROW_CLASS,
                'end' => ' ' . self::END_ROW_CLASS,
                default => ''
            };

            $html .= '<tr class="' . $rowClass . '">';
            foreach ($row as $key => $value) {
                // Классы для выравнивания (имя vs. данные)
                $alignClass = ($key === 'Name') ? self::NAME_CELL_CLASS : self::DATA_CELL_CLASS;
                $html .= '<td class="' . self::CELL_CLASS . ' ' . $alignClass . '">' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        // Добавление таблицы усредненных значений
        $html .= $this->renderHtmlAverageTable();

        return $html;
    }

    /**
     * Renders the average checkpoint data as a simple HTML table with CSS classes.
     *
     * @return string
     */
    public function renderHtmlAverageTable(bool $injectCSS = false): string
    {
        $data = $this->getAverageData();
        if (empty($data)) {
            return '';
        }

        $headers = array_keys($data[0]);

        // Используем CSS класс для заголовка
        $html = ($injectCSS ? $this->getCSS4Tables() : '').'<h3 class="' . self::AVERAGE_TITLE_CLASS . '">Average Checkpoint Data</h3>';
        // Используем CSS класс для таблицы
        $html .= '<table class="' . self::AVERAGE_TABLE_CLASS . '">';

        // 1. HEADER ROW
        $html .= '<thead><tr class="' . self::AVERAGE_HEADER_CLASS . '">';
        foreach ($headers as $header) {
            // Используем CSS классы для ячеек заголовка
            $html .= '<th class="' . self::CELL_CLASS . ' ' . self::AVERAGE_HEADER_CLASS . '">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';

        // 2. DATA ROWS
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr class="' . self::ROW_CLASS . '">';
            foreach ($row as $key => $value) {
                $alignClass = ($key === 'Name') ? self::NAME_CELL_CLASS : self::DATA_CELL_CLASS;
                $html .= '<td class="' . self::CELL_CLASS . ' ' . $alignClass . '">' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    private function getCssRulesArray(): array
    {
        return [
            // Базовые стили для таблиц
            ".{$this::TABLE_CLASS}, .{$this::AVERAGE_TABLE_CLASS}" => [
                'width' => '100%',
                'border-collapse' => 'collapse',
                'font-family' => 'monospace',
                'font-size' => '14px',
                'margin-bottom' => '20px',
            ],
            // Стили ячеек
            ".{$this::CELL_CLASS}" => [
                'padding' => '8px',
                'border' => '1px solid #ddd',
            ],
            // Стили заголовков
            ".{$this::HEADER_CLASS} th" => [
                'background-color' => '#f2f2f2',
                'text-align' => 'left',
                'font-weight' => 'bold',
            ],
            ".{$this::AVERAGE_HEADER_CLASS} th" => [
                'background-color' => '#e6f7ff',
            ],
            // Выравнивание данных
            ".{$this::DATA_CELL_CLASS}" => [
                'text-align' => 'right',
            ],
            ".{$this::NAME_CELL_CLASS}" => [
                'text-align' => 'left',
            ],
            // Выделение строк
            ".{$this::START_ROW_CLASS}" => [
                'background-color' => '#e0ffe0',
            ],
            ".{$this::END_ROW_CLASS}" => [
                'background-color' => '#ffcccc',
            ],
            // Заголовок средних значений
            ".{$this::AVERAGE_TITLE_CLASS}" => [
                'margin-top' => '30px',
                'margin-bottom' => '10px',
                'font-size' => '1.2em',
                'font-family' => 'sans-serif',
            ],
        ];
    }

    /**
     * Provides basic CSS styles to make the rendered HTML tables readable out-of-the-box.
     *
     * @return string
     */
    public function getCSS4Tables(): string
    {
        $css = '';
        foreach ($this->getCssRulesArray() as $selector => $properties) {
            $css .= $selector . " {\n";
            foreach ($properties as $property => $value) {
                $css .= "    {$property}: {$value};\n";
            }
            $css .= "}\n";
        }
        return $css;
    }
}
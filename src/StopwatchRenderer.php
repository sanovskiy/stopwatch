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
    private function dataToCliTable(array $data, string $title, int $minColWidth = 15, int $maxColWidth = 40): string
    {
        if (empty($data)) {
            return sprintf('%s--- %s ---%sNo data available.%s', PHP_EOL, $title, PHP_EOL, PHP_EOL);
        }

        $headers = array_keys($data[0]);
        $numColumns = count($headers);
        $output = PHP_EOL . "--- {$title} ---" . PHP_EOL;

        // --- 1. COLUMN WIDTH CALCULATION ---
        $colWidths = array_fill(0, $numColumns, $minColWidth);

        // Initialize the maximum length with headers
        foreach ($headers as $index => $header) {
            $colWidths[$index] = max($colWidths[$index], strlen($header));
        }

        // Loop through the data to find the maximum length
        foreach ($data as $row) {
            foreach (array_values($row) as $index => $value) {

                // NOTE: We don't know what the length of the formatted string in StopwatchRenderer will be,
                // but we use the pre-formatted values from $data.
                $currentLength = strlen($value);

                // If this is the 'Name' column, take its length into account for the limit
                if ($headers[$index] === 'Name' && $currentLength > $maxColWidth) {
                    // If the name is too long, truncate it to calculate the width,
                    // so the table doesn't become infinite.
                    // (The actual truncation will happen later in the rendering loop)
                    $currentLength = $maxColWidth;
                }

                $colWidths[$index] = max($colWidths[$index], $currentLength);

                // Apply maxColWidth to all columns
                $colWidths[$index] = min($colWidths[$index], $maxColWidth);
            }
        }

        // --- 2. RENDERING THE TITLE ---

        // Format the headings using the calculated widths
        $paddedHeaders = array_map(
            fn($header, $width) => str_pad($header, $width),
            $headers,
            $colWidths
        );

        $headersRow = '| ' . implode(' | ', $paddedHeaders) . ' |';
        $separator = str_repeat('-', strlen($headersRow));

        $output .= $separator . PHP_EOL . $headersRow . PHP_EOL . $separator . PHP_EOL;

        // --- 3. RENDERING DATA STRINGS ---
        foreach ($data as $row) {
            $dataRow = '| ';

            foreach ($row as $key => $value) {
                $index = array_search($key, $headers); // Find the column index to get the width
                $columnWidth = $colWidths[$index];
                $valueToPad = $value;
                $isNameColumn = ($key === 'Name');

                // Truncation logic (applies to Name only)
                if ($isNameColumn && strlen($valueToPad) > $columnWidth) {
                    // Use the calculated maximum column width for truncation
                    $valueToPad = substr($valueToPad, 0, $columnWidth - 3) . '...';
                }

                // Alignment: left for 'Name', right for numeric data
                $dataRow .= $isNameColumn
                    ? str_pad($valueToPad, $columnWidth) . ' | '
                    : str_pad($valueToPad, $columnWidth, ' ', STR_PAD_LEFT) . ' | ';
            }
            $output .= $dataRow . PHP_EOL;
        }
        $output .= $separator . PHP_EOL;

        return $output;
    }

    /**
     * Renders the stopwatch data as a formatted table suitable for CLI output.
     *
     * @param int $minColWidth
     * @param int $maxColWidth
     * @return string
     */
    public function renderCliTable(int $minColWidth = 10, int $maxColWidth = 40): string
    {
        return $this->dataToCliTable($this->getFormattedData(), 'CHECKPOINT DATA', $minColWidth, $maxColWidth);
    }

    /**
     * Renders the average checkpoint data as a formatted table suitable for CLI output.
     *
     * @param int $minColWidth
     * @param int $maxColWidth
     * @return string
     */
    public function renderCliAverageTable(int $minColWidth = 10, int $maxColWidth = 40): string
    {
        return $this->dataToCliTable($this->getAverageData(), 'AVERAGE DATA', $minColWidth, $maxColWidth);
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
        $html = ($injectCSS ? $this->getCSS4Tables() : '') . '<h3 class="' . self::AVERAGE_TITLE_CLASS . '">Average Checkpoint Data</h3>';
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
            sprintf(".%s, .%s", $this::TABLE_CLASS, $this::AVERAGE_TABLE_CLASS) => [
                'width' => '100%',
                'border-collapse' => 'collapse',
                'font-family' => 'monospace',
                'font-size' => '14px',
                'margin-bottom' => '20px',
            ],
            sprintf(".%s", $this::CELL_CLASS) => [
                'padding' => '8px',
                'border' => '1px solid #ddd',
            ],
            sprintf(".%s th", $this::HEADER_CLASS) => [
                'background-color' => '#f2f2f2',
                'text-align' => 'left',
                'font-weight' => 'bold',
            ],
            sprintf(".%s th", $this::AVERAGE_HEADER_CLASS) => [
                'background-color' => '#e6f7ff',
            ],
            sprintf(".%s", $this::DATA_CELL_CLASS) => [
                'text-align' => 'right',
            ],
            sprintf(".%s", $this::NAME_CELL_CLASS) => [
                'text-align' => 'left',
            ],
            sprintf(".%s", $this::START_ROW_CLASS) => [
                'background-color' => '#e0ffe0',
            ],
            sprintf(".%s", $this::END_ROW_CLASS) => [
                'background-color' => '#ffcccc',
            ],
            sprintf(".%s", $this::AVERAGE_TITLE_CLASS) => [
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
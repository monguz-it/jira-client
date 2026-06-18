<?php

declare(strict_types=1);

/**
 * Handles formatted, colorized terminal output.
 */
class Output
{
    /** Wraps text with ANSI color codes. */
    public function color(string $text, string $color): string
    {
        return $color . $text . Color::RESET;
    }

    public function line(string $text = ''): void
    {
        echo $text . PHP_EOL;
    }

    public function error(string $text): void
    {
        $this->line($this->color('✗ ' . $text, Color::BOLD_RED));
    }

    public function success(string $text): void
    {
        $this->line($this->color('✓ ' . $text, Color::BOLD_GREEN));
    }

    public function info(string $text): void
    {
        $this->line($this->color($text, Color::CYAN));
    }

    public function label(string $label, string $value): void
    {
        $this->line($this->color(str_pad($label . ':', 14), Color::GRAY) . $value);
    }

    /**
     * Prints a table with headers and rows, auto-sizing columns.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $strip = function (string $s): int {
            return mb_strwidth(preg_replace('/\033\[[0-9;]*m/', '', $s));
        };

        $widths = array_map(function ($h) use ($strip) {
            return $strip($h);
        }, $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, $strip((string) $cell));
            }
        }

        $headerLine = '';
        foreach ($headers as $i => $h) {
            $headerLine .= str_pad($h, $widths[$i] + 2);
        }
        $this->line($this->color($headerLine, Color::BOLD));
        $this->line(str_repeat('─', array_sum($widths) + count($widths) * 2));

        foreach ($rows as $row) {
            $line = '';
            foreach ($row as $i => $cell) {
                $cell = (string) $cell;
                $pad = $widths[$i] + 2 - $strip($cell) + strlen($cell);
                $line .= str_pad($cell, $pad);
            }
            $this->line($line);
        }
    }
}

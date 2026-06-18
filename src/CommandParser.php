<?php

declare(strict_types=1);

/**
 * Parses CLI arguments into command, positional args, and --key=value options.
 */
class CommandParser
{
    /** @var string */
    public $command;

    /** @var array<int, string> */
    public $args;

    /** @var array<string, string|bool> */
    public $options;

    /**
     * @param array<int, string> $argv
     */
    public function __construct(array $argv)
    {
        $params = array_slice($argv, 1);
        $this->command = $params[0] ?? 'help';

        $args = [];
        $options = [];

        foreach (array_slice($params, 1) as $param) {
            if (strpos($param, '--') === 0) {
                $part = substr($param, 2);
                if (strpos($part, '=') !== false) {
                    [$key, $value] = explode('=', $part, 2);
                    $options[$key] = $value;
                } else {
                    $options[$part] = true;
                }
            } else {
                $args[] = $param;
            }
        }

        $this->args = $args;
        $this->options = $options;
    }

    public function option(string $key, ?string $default = null): ?string
    {
        return isset($this->options[$key]) && $this->options[$key] !== true
            ? (string) $this->options[$key]
            : $default;
    }

    public function arg(int $index, ?string $default = null): ?string
    {
        return $this->args[$index] ?? $default;
    }
}

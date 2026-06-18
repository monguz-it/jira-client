<?php

declare(strict_types=1);

/**
 * ANSI color codes for terminal output.
 */
class Color
{
    public const RESET = "\033[0m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const CYAN = "\033[36m";
    public const GRAY = "\033[90m";
    public const BOLD = "\033[1m";
    public const BOLD_RED = "\033[1;31m";
    public const BOLD_GREEN = "\033[1;32m";
    public const BOLD_YELLOW = "\033[1;33m";
    public const BOLD_BLUE = "\033[1;34m";
    public const BOLD_CYAN = "\033[1;36m";
}

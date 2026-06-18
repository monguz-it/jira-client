<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CommandParserTest extends TestCase
{
    public function testParsesCommand(): void
    {
        $parser = new CommandParser(['jira-client', 'show', 'PROJ-123']);

        $this->assertSame('show', $parser->command);
        $this->assertSame('PROJ-123', $parser->arg(0));
    }

    public function testParsesOptions(): void
    {
        $parser = new CommandParser(['jira-client', 'search', '--project=PROJ', '--status=Done']);

        $this->assertSame('search', $parser->command);
        $this->assertSame('PROJ', $parser->option('project'));
        $this->assertSame('Done', $parser->option('status'));
    }

    public function testDefaultsToHelp(): void
    {
        $parser = new CommandParser(['jira-client']);

        $this->assertSame('help', $parser->command);
    }

    public function testOptionDefaultValue(): void
    {
        $parser = new CommandParser(['jira-client', 'create', '--summary=Test']);

        $this->assertSame('Task', $parser->option('type', 'Task'));
        $this->assertNull($parser->option('missing'));
    }

    public function testBooleanFlag(): void
    {
        $parser = new CommandParser(['jira-client', 'search', '--verbose']);

        $this->assertNull($parser->option('verbose'));
    }

    public function testMixedArgsAndOptions(): void
    {
        $parser = new CommandParser(['jira-client', 'comment', 'PROJ-1', 'Hello world', '--verbose']);

        $this->assertSame('comment', $parser->command);
        $this->assertSame('PROJ-1', $parser->arg(0));
        $this->assertSame('Hello world', $parser->arg(1));
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AppTest extends TestCase
{
    public function testHelpDoesNotThrow(): void
    {
        $parser = new CommandParser(['jira-client', 'help']);
        $app = new App($parser, new Output());

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('Jira CLI', $output);
        $this->assertStringContainsString('Commands:', $output);
    }

    public function testVersionCommand(): void
    {
        $parser = new CommandParser(['jira-client', 'version']);
        $app = new App($parser, new Output());

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('jira-client', $output);
    }

    public function testShowWithoutKeyThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'show']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Usage: jira-client show');
        $app->run();
    }

    public function testCommentWithoutArgsThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'comment']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Usage: jira-client comment');
        $app->run();
    }

    public function testTransitionWithoutArgsThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'transition']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Usage: jira-client transition');
        $app->run();
    }

    public function testCreateWithoutProjectThrows(): void
    {
        putenv('JIRA_PROJECT');
        $parser = new CommandParser(['jira-client', 'create', '--summary=Test']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('--project is required');
        $app->run();
    }

    public function testCreateWithoutSummaryThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'create', '--project=PROJ']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('--summary is required');
        $app->run();
    }

    public function testSearchWithoutFiltersThrows(): void
    {
        putenv('JIRA_PROJECT');
        $parser = new CommandParser(['jira-client', 'search']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Provide at least one filter');
        $app->run();
    }

    public function testSearchWithLabelIsValidFilter(): void
    {
        putenv('JIRA_PROJECT');
        $parser = new CommandParser(['jira-client', 'search', '--label=backend']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        // Should throw RuntimeException (HTTP), not CommandException (missing filter)
        $this->expectException(RuntimeException::class);
        $app->run();
    }

    public function testSearchWithEpicIsValidFilter(): void
    {
        putenv('JIRA_PROJECT');
        $parser = new CommandParser(['jira-client', 'search', '--epic=PROJ-100']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(RuntimeException::class);
        $app->run();
    }

    public function testHelpShowsLabelAndEpicOptions(): void
    {
        $parser = new CommandParser(['jira-client', 'help']);
        $app = new App($parser, new Output());

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('--label', $output);
        $this->assertStringContainsString('--epic', $output);
    }
}

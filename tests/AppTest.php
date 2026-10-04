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

    public function testCreate_InvalidIssueType_ThrowsWithAvailableTypes(): void
    {
        // Given: a client whose POST fails with an issue type error and whose
        // project lookup returns two valid issue types
        $client = $this->createMock(JiraClient::class);
        $client->method('post')
            ->willThrowException(new RuntimeException('Jira API error: The issue type selected is invalid.'));
        $client->method('get')
            ->willReturn(['issueTypes' => [['name' => 'Task'], ['name' => 'Sub-task']]]);

        $parser = new CommandParser(['jira-client', 'create', '--project=PROJ', '--summary=X', '--type=Bogus']);
        $app = new App($parser, new Output(), $client);

        // When / Then: a CommandException lists the available types
        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Available types for PROJ: Task, Sub-task');
        $app->run();
    }

    public function testCreate_NonTypeError_IsRethrownUnchanged(): void
    {
        // Given: a client whose POST fails with an unrelated error
        $client = $this->createMock(JiraClient::class);
        $client->method('post')
            ->willThrowException(new RuntimeException('Jira API error: Something else'));

        $parser = new CommandParser(['jira-client', 'create', '--project=PROJ', '--summary=X']);
        $app = new App($parser, new Output(), $client);

        // When / Then: the original RuntimeException propagates
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something else');
        $app->run();
    }

    public function testCreate_WithPriority_SendsPriorityField(): void
    {
        // Given: a client that captures the POST body and returns a created issue
        $captured = null;
        $client = $this->createMock(JiraClient::class);
        $client->method('post')
            ->willReturnCallback(function ($path, $body) use (&$captured) {
                $captured = $body;
                return ['key' => 'PROJ-1'];
            });

        $parser = new CommandParser(['jira-client', 'create', '--project=PROJ', '--summary=X', '--priority=High']);
        $app = new App($parser, new Output(), $client);

        // When: the issue is created
        ob_start();
        $app->run();
        ob_get_clean();

        // Then: the priority field is included in the request body
        $this->assertSame(['name' => 'High'], $captured['fields']['priority']);
    }

    public function testCreate_InvalidPriority_ThrowsWithAvailablePriorities(): void
    {
        // Given: a POST that fails on priority and a priority lookup with two values
        $client = $this->createMock(JiraClient::class);
        $client->method('post')
            ->willThrowException(new RuntimeException('Jira API error: Invalid value for priority.'));
        $client->method('get')
            ->willReturn([['name' => 'High'], ['name' => 'Low']]);

        $parser = new CommandParser(['jira-client', 'create', '--project=PROJ', '--summary=X', '--priority=Bogus']);
        $app = new App($parser, new Output(), $client);

        // When / Then: a CommandException lists the available priorities
        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Available priorities: High, Low');
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
        // Given: --label as the only filter and a client returning no issues
        $parser = new CommandParser(['jira-client', 'search', '--label=backend']);
        $client = $this->createMock(JiraClient::class);
        $client->method('get')->willReturn(['issues' => []]);
        $app = new App($parser, new Output(), $client);

        // When / Then: --label is accepted (no "missing filter" CommandException)
        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('No issues found', $output);
    }

    public function testSearchWithParentIsValidFilter(): void
    {
        putenv('JIRA_PROJECT');
        // Given: --parent as the only filter and a client returning no issues
        $parser = new CommandParser(['jira-client', 'search', '--parent=PROJ-100']);
        $client = $this->createMock(JiraClient::class);
        $client->method('get')->willReturn(['issues' => []]);
        $app = new App($parser, new Output(), $client);

        // When / Then: --parent is accepted (no "missing filter" CommandException)
        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('No issues found', $output);
    }

    public function testSearch_UsesJqlEndpointWithExplicitFields(): void
    {
        // Given: a client capturing the GET path and query, returning one issue
        $capturedPath = null;
        $capturedQuery = null;
        $client = $this->createMock(JiraClient::class);
        $client->method('get')
            ->willReturnCallback(function ($path, $query = []) use (&$capturedPath, &$capturedQuery) {
                $capturedPath = $path;
                $capturedQuery = $query;

                return [
                    'issues' => [
                        [
                            'key' => 'PROJ-1',
                            'fields' => [
                                'status' => ['name' => 'To Do'],
                                'assignee' => ['displayName' => 'Jane'],
                                'summary' => 'Something',
                            ],
                        ],
                    ],
                ];
            });

        $parser = new CommandParser(['jira-client', 'search', '--project=PROJ']);
        $app = new App($parser, new Output(), $client);

        // When: the search command runs
        ob_start();
        $app->run();
        ob_get_clean();

        // Then: it calls the new /search/jql endpoint requesting the fields it renders
        $this->assertSame('/rest/api/3/search/jql', $capturedPath);
        $this->assertSame('summary,status,assignee', $capturedQuery['fields']);
    }

    public function testHelpShowsLabelAndParentOptions(): void
    {
        $parser = new CommandParser(['jira-client', 'help']);
        $app = new App($parser, new Output());

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('--label', $output);
        $this->assertStringContainsString('--parent', $output);
        $this->assertStringContainsString('--priority', $output);
    }

    public function testUpdateWithoutKeyThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'update']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Usage: jira-client update');
        $app->run();
    }

    public function testUpdateWithoutFieldsThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'update', 'PROJ-1']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Provide at least one field to update');
        $app->run();
    }

    public function testUpdateWithFieldMakesApiCall(): void
    {
        $parser = new CommandParser(['jira-client', 'update', 'PROJ-1', '--summary=New title']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        // Should throw RuntimeException (HTTP), not CommandException
        $this->expectException(RuntimeException::class);
        $app->run();
    }

    public function testInvalidKeyFormatThrows(): void
    {
        $parser = new CommandParser(['jira-client', 'show', 'invalid']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Invalid issue key format');
        $app->run();
    }

    public function testBoardWithoutProjectOrBoardThrows(): void
    {
        putenv('JIRA_PROJECT');
        putenv('JIRA_BOARD');
        $parser = new CommandParser(['jira-client', 'board']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Set JIRA_BOARD or JIRA_PROJECT');
        $app->run();
    }

    public function testBoardWithBoardIdMakesApiCall(): void
    {
        putenv('JIRA_BOARD=99');
        $parser = new CommandParser(['jira-client', 'board']);
        $client = new JiraClient('https://test.atlassian.net', 'a@b.com', 'token');
        $app = new App($parser, new Output(), $client);

        $this->expectException(RuntimeException::class);
        $app->run();
        putenv('JIRA_BOARD');
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeTextToAdf(string $text): array
    {
        $app = new App(new CommandParser(['jira-client', 'help']), new Output());
        $ref = new ReflectionMethod(App::class, 'textToAdf');
        $ref->setAccessible(true);

        /** @var array<string, mixed> $adf */
        $adf = $ref->invoke($app, $text);

        return $adf;
    }

    public function testTextToAdf_SingleLine_ProducesOneParagraph(): void
    {
        // Given / When: a single-line text
        $adf = $this->invokeTextToAdf('Just one line.');

        // Then: one paragraph with that text
        $this->assertSame('doc', $adf['type']);
        $this->assertCount(1, $adf['content']);
        $this->assertSame('Just one line.', $adf['content'][0]['content'][0]['text']);
    }

    public function testTextToAdf_LiteralBackslashN_SplitsIntoParagraphs(): void
    {
        // Given / When: text with the literal "\n" sequence (as typed in a shell)
        $adf = $this->invokeTextToAdf('First line.\nSecond line.');

        // Then: two separate paragraphs
        $this->assertCount(2, $adf['content']);
        $this->assertSame('First line.', $adf['content'][0]['content'][0]['text']);
        $this->assertSame('Second line.', $adf['content'][1]['content'][0]['text']);
    }

    public function testTextToAdf_RealNewline_SplitsIntoParagraphs(): void
    {
        // Given / When: text with a real newline
        $adf = $this->invokeTextToAdf("First.\nSecond.");

        // Then: two separate paragraphs
        $this->assertCount(2, $adf['content']);
        $this->assertSame('First.', $adf['content'][0]['content'][0]['text']);
        $this->assertSame('Second.', $adf['content'][1]['content'][0]['text']);
    }

    public function testTextToAdf_EmptyLine_ProducesEmptyParagraph(): void
    {
        // Given / When: text with a blank line between two paragraphs
        $adf = $this->invokeTextToAdf('A\n\nB');

        // Then: three blocks, the middle one an empty paragraph
        $this->assertCount(3, $adf['content']);
        $this->assertSame([], $adf['content'][1]['content']);
    }

    public function testRenderSubtasks_WithSubtasks_PrintsKeyStatusAndSummary(): void
    {
        // Given: an issue with two subtasks
        $app = new App(new CommandParser(['jira-client', 'help']), new Output());
        $ref = new ReflectionMethod(App::class, 'renderSubtasks');
        $ref->setAccessible(true);
        $subtasks = [
            ['key' => 'GYM-55', 'fields' => ['summary' => 'Setup build SCSS', 'status' => ['name' => 'Done']]],
            ['key' => 'GYM-62', 'fields' => ['summary' => 'Setup Stylelint', 'status' => ['name' => 'To Do']]],
        ];

        // When: the subtasks are rendered
        ob_start();
        $ref->invoke($app, $subtasks);
        $output = ob_get_clean();

        // Then: the section header, keys, statuses and summaries appear
        $this->assertStringContainsString('Subtasks:', $output);
        $this->assertStringContainsString('GYM-55', $output);
        $this->assertStringContainsString('Done', $output);
        $this->assertStringContainsString('GYM-62', $output);
        $this->assertStringContainsString('To Do', $output);
        $this->assertStringContainsString('Setup Stylelint', $output);
    }

    public function testRenderSubtasks_MissingFields_FallsBackToDash(): void
    {
        // Given: a subtask with no key and no fields
        $app = new App(new CommandParser(['jira-client', 'help']), new Output());
        $ref = new ReflectionMethod(App::class, 'renderSubtasks');
        $ref->setAccessible(true);

        // When: the subtask is rendered
        ob_start();
        $ref->invoke($app, [['fields' => []]]);
        $output = ob_get_clean();

        // Then: it degrades to a dash instead of erroring
        $this->assertStringContainsString('Subtasks:', $output);
        $this->assertStringContainsString('—', $output);
    }
}

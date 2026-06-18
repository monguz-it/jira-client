<?php

declare(strict_types=1);

/**
 * Main application class. Routes commands and orchestrates Jira operations.
 */
class App
{
    /** @var Output */
    private $output;

    /** @var JiraClient|null */
    private $client;

    /** @var CommandParser */
    private $parser;

    public function __construct(CommandParser $parser, Output $output, ?JiraClient $client = null)
    {
        $this->parser = $parser;
        $this->output = $output;
        $this->client = $client;
    }

    /**
     * @throws CommandException
     */
    public function run(): void
    {
        if ($this->parser->command !== 'help' && $this->parser->command !== 'version' && $this->client === null) {
            $this->client = $this->createClient();
        }

        switch ($this->parser->command) {
            case 'help':
                $this->commandHelp();
                break;
            case 'version':
                $this->commandVersion();
                break;
            case 'show':
                $this->commandShow();
                break;
            case 'search':
                $this->commandSearch();
                break;
            case 'create':
                $this->commandCreate();
                break;
            case 'comment':
                $this->commandComment();
                break;
            case 'transition':
                $this->commandTransition();
                break;
            case 'projects':
                $this->commandProjects();
                break;
            default:
                $this->commandHelp();
        }
    }

    /**
     * Loads variables from .jira-client.env in the current working directory.
     * Existing environment variables are NOT overwritten.
     */
    private function loadEnvFile(): void
    {
        $path = getcwd() . '/.jira-client.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }

    /**
     * @throws CommandException
     */
    private function createClient(): JiraClient
    {
        $this->loadEnvFile();

        $url = getenv('JIRA_URL') ?: null;
        $email = getenv('JIRA_EMAIL') ?: null;
        $token = getenv('JIRA_TOKEN') ?: null;

        if (!$url || !$email || !$token) {
            $message = "Missing environment variables. Required: JIRA_URL, JIRA_EMAIL, JIRA_TOKEN\n\n"
                . "Export them in your shell:\n"
                . "  export JIRA_URL=\"https://yourcompany.atlassian.net\"\n"
                . "  export JIRA_EMAIL=\"you@example.com\"\n"
                . "  export JIRA_TOKEN=\"your-api-token\"\n\n"
                . "Or create a .jira-client.env file in the current directory:\n"
                . "  JIRA_URL=https://yourcompany.atlassian.net\n"
                . "  JIRA_EMAIL=you@example.com\n"
                . '  JIRA_TOKEN=your-api-token';
            throw new CommandException($message);
        }

        return new JiraClient(rtrim($url, '/'), $email, $token);
    }

    private function commandVersion(): void
    {
        $version = defined('JIRA_CLIENT_VERSION') ? JIRA_CLIENT_VERSION : 'dev';
        $this->output->line("jira-client $version");
        $this->output->line('https://github.com/monguz-it/jira-client');
    }

    private function commandHelp(): void
    {
        $this->output->line($this->output->color('Jira CLI', Color::BOLD) . ' - Jira Cloud from the terminal');
        $this->output->line();
        $this->output->line($this->output->color('Usage:', Color::YELLOW));
        $this->output->line('  jira-client <command> [arguments] [--options]');
        $this->output->line();
        $this->output->line($this->output->color('Commands:', Color::YELLOW));
        $this->output->line('  ' . $this->output->color('show', Color::GREEN) . ' <key>                          Show issue details');
        $this->output->line('  ' . $this->output->color('search', Color::GREEN) . ' [--project=X] [--status=X]    Search issues');
        $this->output->line('         [--assignee=X] [--text=X]');
        $this->output->line('         [--label=X] [--epic=X]');
        $this->output->line('  ' . $this->output->color('projects', Color::GREEN) . '                               List all projects');
        $this->output->line('  ' . $this->output->color('create', Color::GREEN) . ' --project=X --summary="..."   Create an issue');
        $this->output->line('         [--type=Task] [--description="..."]');
        $this->output->line('         [--label=X] [--epic=X]');
        $this->output->line('  ' . $this->output->color('comment', Color::GREEN) . ' <key> "text"                  Add a comment');
        $this->output->line('  ' . $this->output->color('transition', Color::GREEN) . ' <key> "Status Name"          Change issue status');
        $this->output->line();
        $this->output->line($this->output->color('Environment:', Color::YELLOW));
        $this->output->line('  JIRA_URL     https://yourcompany.atlassian.net');
        $this->output->line('  JIRA_EMAIL   your@email.com');
        $this->output->line('  JIRA_TOKEN   API token from id.atlassian.com');
        $this->output->line('  JIRA_PROJECT (optional) Default project key');
        $this->output->line();
        $this->output->line($this->output->color('https://github.com/monguz-it/jira-client', Color::GRAY));
    }

    /**
     * @throws CommandException
     */
    private function commandProjects(): void
    {
        $projects = $this->getClient()->get('/rest/api/3/project', ['orderBy' => 'name']);

        /** @var array<int, array<int, string>> $rows */
        $rows = array_map(function ($p) {
            return [
                $this->output->color($p['key'], Color::BOLD_BLUE),
                $p['name'],
                $this->output->color($p['projectTypeKey'] ?? '—', Color::GRAY),
            ];
        }, $projects);

        $this->output->line();
        $this->output->table(['KEY', 'NAME', 'TYPE'], $rows);
    }

    /**
     * @throws CommandException
     */
    private function commandShow(): void
    {
        $key = $this->parser->arg(0);
        if (!$key) {
            throw new CommandException('Usage: jira-client show <ISSUE-KEY>');
        }

        $issue = $this->getClient()->get("/rest/api/3/issue/$key");
        $fields = $issue['fields'];

        $this->output->line();
        $this->output->line($this->output->color($issue['key'], Color::BOLD_BLUE) . '  ' . $this->output->color($fields['summary'], Color::BOLD));
        $this->output->line();
        $this->output->label('Status', $fields['status']['name'] ?? '—');
        $this->output->label('Type', $fields['issuetype']['name'] ?? '—');
        $this->output->label('Priority', $fields['priority']['name'] ?? '—');
        $this->output->label('Assignee', $fields['assignee']['displayName'] ?? 'Unassigned');
        $this->output->label('Reporter', $fields['reporter']['displayName'] ?? '—');
        $this->output->label('Created', $this->formatDate($fields['created'] ?? ''));
        $this->output->label('Updated', $this->formatDate($fields['updated'] ?? ''));

        if (!empty($fields['description'])) {
            $this->output->line();
            $this->output->line($this->output->color('Description:', Color::YELLOW));
            $this->output->line($this->extractText($fields['description']));
        }

        $this->output->line();
        $this->output->line($this->output->color('URL:', Color::GRAY) . ' ' . $this->getClient()->getBaseUrl() . '/browse/' . $issue['key']);
    }

    /**
     * @throws CommandException
     */
    private function commandSearch(): void
    {
        $conditions = [];

        $project = $this->parser->option('project') ?? $this->defaultProject();
        if ($project) {
            $conditions[] = "project = \"$project\"";
        }
        if ($status = $this->parser->option('status')) {
            $conditions[] = "status = \"$status\"";
        }
        if ($assignee = $this->parser->option('assignee')) {
            $conditions[] = $assignee === 'me'
                ? 'assignee = currentUser()'
                : "assignee = \"$assignee\"";
        }
        if ($text = $this->parser->option('text')) {
            $conditions[] = "text ~ \"$text\"";
        }
        if ($label = $this->parser->option('label')) {
            $conditions[] = "labels = \"$label\"";
        }
        if ($epic = $this->parser->option('epic')) {
            $conditions[] = "parent = \"$epic\"";
        }

        if (empty($conditions)) {
            throw new CommandException('Provide at least one filter: --project, --status, --assignee, --text');
        }

        $jql = implode(' AND ', $conditions) . ' ORDER BY updated DESC';
        $result = $this->getClient()->get('/rest/api/3/search', ['jql' => $jql, 'maxResults' => '30']);

        if (empty($result['issues'])) {
            $this->output->info('No issues found.');
            return;
        }

        $rows = array_map(function ($issue) {
            return [
                $issue['key'],
                $issue['fields']['status']['name'] ?? '—',
                $issue['fields']['assignee']['displayName'] ?? '—',
                mb_substr($issue['fields']['summary'] ?? '', 0, 60),
            ];
        }, $result['issues']);

        $this->output->line();
        $this->output->table(['KEY', 'STATUS', 'ASSIGNEE', 'SUMMARY'], $rows);
        $this->output->line();
        $this->output->line($this->output->color(
            sprintf('Showing %d of %d results', count($result['issues']), $result['total']),
            Color::GRAY
        ));
    }

    /**
     * @throws CommandException
     */
    private function commandCreate(): void
    {
        $project = $this->parser->option('project') ?? $this->defaultProject();
        if (!$project) {
            throw new CommandException('--project is required (or set JIRA_PROJECT)');
        }
        $summary = $this->parser->option('summary');
        if (!$summary) {
            throw new CommandException('--summary is required');
        }
        $type = $this->parser->option('type', 'Task');
        $description = $this->parser->option('description');

        $label = $this->parser->option('label');
        $epic = $this->parser->option('epic');

        $body = [
            'fields' => [
                'project' => ['key' => $project],
                'summary' => $summary,
                'issuetype' => ['name' => $type],
            ],
        ];

        if ($description) {
            $body['fields']['description'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $description]],
                ]],
            ];
        }
        if ($label) {
            $body['fields']['labels'] = [$label];
        }
        if ($epic) {
            $body['fields']['parent'] = ['key' => $epic];
        }

        $result = $this->getClient()->post('/rest/api/3/issue', $body);

        $this->output->line();
        $this->output->success("Issue created: {$result['key']}");
        $this->output->line('  ' . $this->getClient()->getBaseUrl() . '/browse/' . $result['key']);
    }

    /**
     * @throws CommandException
     */
    private function commandComment(): void
    {
        $key = $this->parser->arg(0);
        $text = $this->parser->arg(1);
        if (!$key || !$text) {
            throw new CommandException('Usage: jira-client comment <ISSUE-KEY> "text"');
        }

        $body = [
            'body' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $text]],
                ]],
            ],
        ];

        $this->getClient()->post("/rest/api/3/issue/$key/comment", $body);

        $this->output->line();
        $this->output->success("Comment added to $key");
    }

    /**
     * @throws CommandException
     */
    private function commandTransition(): void
    {
        $key = $this->parser->arg(0);
        $targetName = $this->parser->arg(1);
        if (!$key || !$targetName) {
            throw new CommandException('Usage: jira-client transition <ISSUE-KEY> "Status Name"');
        }

        $transitions = $this->getClient()->get("/rest/api/3/issue/$key/transitions");
        $match = null;

        foreach ($transitions['transitions'] as $t) {
            if (strcasecmp($t['name'], $targetName) === 0) {
                $match = $t;
                break;
            }
        }

        if (!$match) {
            $available = array_map(function ($t) {
                return $t['name'];
            }, $transitions['transitions']);
            throw new CommandException(
                "Transition \"$targetName\" not found for $key.\nAvailable: " . implode(', ', $available)
            );
        }

        $this->getClient()->post("/rest/api/3/issue/$key/transitions", [
            'transition' => ['id' => $match['id']],
        ]);

        $this->output->line();
        $this->output->success("$key → {$match['name']}");
    }

    private function defaultProject(): ?string
    {
        $project = getenv('JIRA_PROJECT');
        return $project !== false ? $project : null;
    }

    /**
     * @throws CommandException
     */
    private function getClient(): JiraClient
    {
        if ($this->client === null) {
            throw new CommandException('Jira client not initialized');
        }
        return $this->client;
    }

    /**
     * Extracts plain text from Jira's Atlassian Document Format (ADF).
     *
     * @param array<string, mixed> $adf
     */
    private function extractText(array $adf): string
    {
        $text = '';
        foreach ($adf['content'] ?? [] as $block) {
            foreach ($block['content'] ?? [] as $inline) {
                $text .= $inline['text'] ?? '';
            }
            $text .= "\n";
        }
        return trim($text);
    }

    private function formatDate(string $date): string
    {
        if (!$date) {
            return '—';
        }
        return date('Y-m-d H:i', strtotime($date));
    }
}

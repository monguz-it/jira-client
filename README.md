# jira-client

A standalone PHP CLI tool to interact with Jira Cloud. No dependencies required — just PHP 7.4+ with curl and mbstring extensions.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Requirements](#requirements)
- [Contributing](#contributing)
- [License](#license)

## Features

- View issue details
- Search issues with simple filters
- List projects
- Create issues
- Update issues
- Add comments
- Transition issue statuses

## Installation

Download the latest release from [GitHub Releases](https://github.com/monguz-it/jira-client/releases) and place it in your `$PATH`:

```bash
chmod +x jira-client
mv jira-client ~/.local/bin/
```

Or build from source:

```bash
git clone https://github.com/monguz-it/jira-client.git
cd jira-client
composer install
composer install-dist
```

## Configuration

Set environment variables or create a `.jira-client.env` file in your working directory:

```env
JIRA_URL=https://yourcompany.atlassian.net
JIRA_EMAIL=you@example.com
JIRA_TOKEN=your-api-token
JIRA_PROJECT=PROJ
```

- `JIRA_URL` — Your Jira Cloud URL (required)
- `JIRA_EMAIL` — Your Atlassian account email (required)
- `JIRA_TOKEN` — API token from [id.atlassian.com](https://id.atlassian.com/manage-profile/security/api-tokens) (required)
- `JIRA_PROJECT` — Default project key, used by `search` and `create` when `--project` is omitted (optional)

Environment variables take precedence over the `.env` file.

## Usage

```bash
# Help
jira-client help

# Version
jira-client version

# Show issue details
jira-client show PROJ-123

# Search issues
jira-client search --project=PROJ --status="To Do" --assignee=me --text="keyword"
jira-client search --project=PROJ --label=backend --epic=PROJ-100

# List all projects
jira-client projects

# Create an issue
jira-client create --project=PROJ --summary="Fix login bug" --type=Bug --description="Details here"
jira-client create --project=PROJ --summary="New task" --label=backend,api --epic=PROJ-100

# Add a comment
jira-client comment PROJ-123 "Done, deployed to staging"

# Update an issue
jira-client update PROJ-123 --summary="New title"
jira-client update PROJ-123 --label=frontend,backend --epic=PROJ-50

# Change issue status
jira-client transition PROJ-123 "In Progress"
```

## Requirements

- PHP 7.4+ with `curl` and `mbstring` extensions
- Jira Cloud instance (*.atlassian.net)

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

This project uses [Conventional Commits](https://www.conventionalcommits.org/). Make sure your commit messages follow the format:

```
feat: add new command
fix: handle empty response
docs: update README
```

### Development

```bash
composer install
pnpm install
git config --local core.hooksPath .githooks
```

Available scripts:

```bash
composer check        # Run lint + code style + tests
composer lint         # PHPStan static analysis
composer cs           # Check code style (dry-run)
composer cs-fix       # Fix code style
composer test         # PHPUnit tests
composer build        # Build dist/jira-client
composer install-dist # Build and copy to ~/.local/bin
```

## License

[MIT](LICENSE)

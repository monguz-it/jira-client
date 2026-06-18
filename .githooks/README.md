# Shared hooks for the repository

In the main directory (the one containing the .git directory) execute:

```bash
git config --local core.hooksPath .githooks
```

## commit-msg
Validates that commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) format.

Example: `feat: add search command`

## pre-commit
Runs PHP syntax check (`php -l`) on staged files.

To commit without running hooks, use the `--no-verify` option:

```bash
git commit --no-verify
```

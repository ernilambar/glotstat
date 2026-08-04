# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this plugin does

GlotStat is a single-file WordPress plugin. On the Add Plugins screen (`plugin-install.php`), it fetches each visible plugin's translation completion percentage for the current user locale from the WordPress.org translation API and renders it on the plugin card. It's inert entirely in English (`en_US`) locale installs.

## Commands

```bash
composer lint-php   # PHP parallel-lint syntax check
composer phpcs       # PHPCS only
composer format      # phpcbf — auto-fixes PHPCS violations; run before lint
composer lint         # lint-php + phpcs; must exit clean
composer pot          # regenerate languages/glotstat.pot via wp i18n make-pot
composer po           # update languages/*.po from the .pot
composer mo           # compile languages/*.po to .mo via wp i18n make-mo
```

## Quality gate

**All gates MUST pass before any task is marked complete. No exceptions.**

- `composer format` — auto-fixes PHPCS violations (must run before lint)
- `composer lint` — must exit with zero errors; fix all errors and re-run until clean

If a step fails: fix the issue, then re-run from that step.

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

## Architecture

Everything lives in [glotstat.php](glotstat.php) — no autoloader, no classes, no build step:

- `glotstat_enqueue_script()` — hooked to `admin_footer-plugin-install.php`. Bails if locale is `en_US`. Otherwise prints an inline `<script>` that:
  - Uses a `MutationObserver` on `#the-list` plus an `IntersectionObserver` to lazily inject a `.plugin-translation-status` placeholder into each `.plugin-card` (slug parsed from the card's `plugin-card-{slug}` class) as it scrolls into view.
  - AJAX-fetches the status and renders percentage, a colored progress bar, and optional waiting/fuzzy/warnings counts.
- `glotstat_ajax_get_translation_status()` — hooked to `wp_ajax_glotstat_get_translation_status`. Nonce-checked, requires `install_plugins` capability. For a given `slug` + the current user's locale, checks a transient before calling `https://translate.wordpress.org/api/projects/wp-plugins/{slug}/dev/` and caching the parsed result.

## Quality gate

**All gates MUST pass before any task is marked complete. No exceptions.**

- `composer format` — auto-fixes PHPCS violations (must run before lint)
- `composer lint` — must exit with zero errors; fix all errors and re-run until clean

If a step fails: fix the issue, then re-run from that step.

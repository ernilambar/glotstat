# AGENTS.md

## Project Overview

GlotStat is a WordPress plugin that displays translation completion percentages on plugin cards in the Add Plugins screen. It fetches data from the WordPress.org translation API, caches results via transients, and renders a progress bar with color-coded status. The frontend is vanilla JS bundled with Vite; the backend is a single PHP class using the WordPress AJAX API.

## Setup

```bash
pnpm install
composer install
```

## Commands

```bash
pnpm run build          # Build JS/CSS assets via Vite
composer lint           # Run PHP lint + PHPCS
composer format         # Auto-fix PHP with PHPCBF
pnpm run format         # Format JS/CSS/JSON with Prettier
composer pot            # Generate POT file
```

## Code Style

- **PHP**: WordPress Coding Standards (WPCS). Tabs for indentation. Spaces for alignment. Yoda conditions. Strict comparisons. Prefix all hooks with `glotstat_`.
- **JS**: Prettier with `@wordpress/prettier-config`, print width 100. Vanilla JS, no framework. Global namespace `glotstatData` is injected via `wp_add_inline_script`.
- **CSS**: BEM-like class names prefixed with `glotstat-`. Tab indentation.
- **General**: Tab indentation (4 spaces wide) for all files except JSON/YAML/Markdown (2 spaces). UTF-8, LF line endings, trim trailing whitespace.

## Quality Gate

Run before submitting changes:

```bash
composer lint
pnpm run format
pnpm run build
```

All three must pass clean. PHP files must pass PHPCS with no warnings. JS/CSS/JSON must be formatted with Prettier.

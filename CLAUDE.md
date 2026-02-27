# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
# Watch SCSS and compile to CSS during development
npm run sass

# Compile and minify SCSS for production
npm run build
```

Serve the project via a local PHP server (e.g., `php -S localhost:8000`) or MAMP/XAMPP.

## Architecture

Traditional PHP template site — no framework. Pages are individual `.php` files that include shared templates.

**Request flow:** `pages/*.php` or `index.php` → `includes/header.php` (top of each page) → page content → `includes/footer.php`

**Key files:**
- `config/config.php` — site constants (`SITE_NAME`, `SITE_URL`, DB credentials, error reporting toggle)
- `includes/db.php` — PDO database connection (MySQL, not yet wired to page data)
- `includes/functions.php` — helpers: `sanitize()`, `redirect()`, `formatPrice()`, `isActivePage()`
- `includes/header.php` / `includes/footer.php` — shared layout templates
- `assets/js/main.js` — mobile hamburger menu toggle only

## SCSS Structure

```
assets/scss/
├── main.scss          # Imports all partials
├── base/              # Variables, reset, typography, layout, utilities
└── pages/             # Per-page styles (home, about, shop, contact)
```

CSS output goes to `assets/css/main.css` (and `main.min.css` for production).

## Database

MySQL via PDO. Credentials are in `config/config.php`. The connection is initialized in `includes/db.php` but is not yet used in any page queries — the shop page currently uses hardcoded product data.

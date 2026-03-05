# CLAUDE.md — Tekton

AI-first WordPress plugin replacing theme + pagebuilder + ACF. Read `PRD.md` for full specs and implementation steps.

## Stack

- **Backend:** PHP 8.2+, WordPress 6.9+, no Composer runtime deps, no frameworks
- **Admin UI:** Svelte 5 (runes only, NOT Svelte 4 syntax), Vite, Tailwind CSS
- **Frontend output:** Vanilla CSS/JS only, no framework on public pages
- **No React, no Gutenberg, no @wordpress/* packages**

## Naming Conventions

```
Classes:          Tekton_Field_Engine         → class-tekton-field-engine.php
Functions:        tekton_get_field()
Hooks:            tekton/structure_saved
Options:          tekton_api_key_{provider}
Meta keys:        _tekton_{group}_{field}
DB tables:        {$wpdb->prefix}tekton_{table}
REST routes:      tekton/v1/{resource}
Component IDs:    comp_{8_alphanumeric}       (stable across edits, never regenerate existing)
Svelte files:     PascalCase.svelte, stores as camelCase.svelte.js
```

## Critical Rules

1. **Dumb frontend.** Components never hardcode content. All text/images/data use content sources: `{ "source": "field", "group": "...", "field": "...", "fallback": "..." }`. Only exception: `source: "static"` for structural labels/ARIA.

2. **Full-stack generation.** When the AI creates a template needing fields that don't exist, it must also generate the field group (and CPT if needed) in the same response.

3. **Escape everything.** `esc_html()` for text, `esc_attr()` for attributes, `esc_url()` for URLs, `wp_kses_post()` for rich content. `$wpdb->prepare()` for all queries. No exceptions.

4. **Scope assets.** Builder UI assets load only on the Tekton admin page. Frontend assets load only on Tekton-rendered pages. Never global.

5. **Plugin Mode for logic.** Form handling, REST endpoints, WooCommerce mods, cron jobs, external APIs → must be a generated micro-plugin, never embedded in components or renderer.

6. **Svelte 5 runes only.** Use `$state()`, `$derived()`, `$effect()`. Never `$:` reactive declarations.

7. **Design tokens over hardcoded values.** All colors/fonts/spacing use `var(--tekton-*)` CSS custom properties.

8. **Responsive required.** Every component must include at minimum a mobile breakpoint in styles.

## PHP File Header

```php
<?php
declare(strict_types=1);
/**
 * {Description}
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
```

## Commits

```
feat(field-engine): add repeater field type
fix(renderer): escape text component output
test(renderer): add post-loop tests
```
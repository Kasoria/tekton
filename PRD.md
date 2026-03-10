# Tekton — AI-First WordPress Site Builder

## Product Requirements Document (PRD)

**Version:** 2.1.0
**License:** GPLv2 or later (WordPress-compatible open source)
**Author:** Christian / Kasoria
**Target:** WordPress 6.9+, PHP 8.2+, Node 18+
**Repository:** github.com/kasoria/tekton

---

## 1. Vision & Philosophy

Tekton (from Greek τέκτων — builder, craftsman, the root of "architect") is an open-source WordPress plugin that replaces the traditional theme + pagebuilder + field plugin stack with a single AI-first tool. Instead of installing a theme, Elementor/Bricks, and ACF separately — then wiring them together manually — users describe what they want in natural language. The AI generates the complete stack: data model (custom post types, taxonomies, fields), content structure, and frontend templates, all in one prompt.

WordPress becomes a pure structured CMS. Tekton becomes the presentation + data modeling layer. The user talks to the AI, and everything gets built.

### What Tekton Replaces

| Traditional Stack | Tekton Equivalent |
|---|---|
| Theme (Astra, GeneratePress, etc.) | Bridge theme + Tekton renderer |
| Page Builder (Elementor, Bricks, Breakdance) | AI-generated component schema + inline editor |
| Custom Fields (ACF, Meta Box, Pods) | Tekton Field Engine |
| Gutenberg block editor | Disabled — replaced by Tekton's field-based editing |
| Custom theme code / child theme | Plugin Mode (AI-generated micro-plugins) |

### Core Principles

1. **AI-first, not AI-only.** The AI is the primary authoring tool. Manual editing is a lightweight overlay for quick tweaks — text, images, colors, spacing. Not a full drag-and-drop builder.
2. **WordPress stays WordPress.** Posts, pages, CPTs, users, menus, roles, REST API, WooCommerce — all of it works exactly as expected. Tekton owns rendering, data modeling, and optionally admin UX.
3. **Dumb frontend, smart CMS.** The frontend component schema contains structure and design only. Every piece of content — text, images, data — comes from WordPress (post fields, Tekton fields, options, menus). Components reference content sources; they never hardcode content.
4. **Component schema, not raw HTML.** The AI generates a structured JSON component tree, not raw markup. This makes partial editing, versioning, and re-generation reliable.
5. **Full-stack AI generation.** One prompt can generate: a CPT + taxonomy + field group + frontend template + micro-plugin. No context-switching between tools.
6. **Plugin mode for logic.** Server-side features (form handling, REST endpoints, WooCommerce customization) become scoped micro-plugins. Separation of concerns is non-negotiable.
7. **Zero third-party plugin dependencies.** Tekton has its own field engine, its own CPT registration, its own rendering. No ACF, no Meta Box, no theme required. (It detects and can read ACF fields if present, but never requires them.)
8. **Open source, no vendor lock-in.** Users bring their own AI API key (Anthropic, OpenAI, Google Gemini, or OpenRouter). No SaaS dependency. Output is JSON and PHP — fully portable.

---

## 2. Architecture Overview

### 2.1 High-Level System Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                      WordPress Core                           │
│  (Posts, Pages, Users, Menus, Options, REST API, Roles)      │
│  (Gutenberg DISABLED — Classic Editor fallback for content)  │
└────────┬─────────────────────────┬───────────────────────────┘
         │                         │
┌────────▼─────────────────┐  ┌───▼──────────────────────────┐
│     Tekton Plugin        │  │   Generated Micro-Plugins     │
│     (Core Engine)        │  │   (Plugin Mode Output)        │
│                          │  │                               │
│  ┌────────────────────┐  │  │  - tekton-contact-form/       │
│  │ Theme Bridge       │  │  │  - tekton-booking-system/     │
│  │ (hijacks template  │  │  │  - tekton-woo-gift-message/   │
│  │  rendering)        │  │  │  ...                          │
│  └────────────────────┘  │  └───────────────────────────────┘
│  ┌────────────────────┐  │
│  │ Field Engine       │  │
│  │ (CPTs, taxonomies, │  │
│  │  fields, meta      │  │
│  │  boxes, options    │  │
│  │  pages)            │  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ Component Renderer │  │
│  │ (JSON → HTML)      │  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ AI Engine          │  │
│  │ (Multi-provider:   │  │
│  │  Anthropic, OpenAI,│  │
│  │  Google, OpenRouter│  │
│  │  + Context Manager)│  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ Builder UI         │  │
│  │ (Svelte 5 SPA      │  │
│  │  in wp-admin)      │  │
│  └────────────────────┘  │
│  ┌────────────────────┐  │
│  │ Admin Customizer   │  │
│  │ (Optional)         │  │
│  └────────────────────┘  │
└──────────────────────────┘
```

### 2.2 Directory Structure

```
tekton/
├── tekton.php                            # Plugin bootstrap, hooks, activation
├── readme.txt                            # WordPress.org readme
├── LICENSE                               # GPLv2
├── CLAUDE.md                             # Claude Code instructions
│
├── includes/
│   ├── class-tekton-core.php             # Main plugin class, singleton
│   ├── class-tekton-theme-bridge.php     # Theme hijacking & template intercept
│   ├── class-tekton-renderer.php         # Component JSON → HTML renderer
│   ├── class-tekton-context-builder.php  # Builds WordPress context for AI
│   │
│   └── ai/
│       ├── class-tekton-ai-engine.php          # AI orchestrator (provider-agnostic)
│       ├── interface-tekton-ai-provider.php    # Provider interface
│       ├── class-tekton-provider-anthropic.php # Anthropic (Claude) provider
│       ├── class-tekton-provider-openai.php    # OpenAI (GPT) provider
│       ├── class-tekton-provider-google.php    # Google (Gemini) provider
│       └── class-tekton-provider-openrouter.php # OpenRouter (multi-model) provider
│   ├── class-tekton-schema.php           # Component schema definitions & validation
│   ├── class-tekton-storage.php          # CRUD for page structures & versions
│   ├── class-tekton-assets.php           # CSS/JS asset pipeline for frontend
│   ├── class-tekton-plugin-generator.php # Plugin Mode: generates micro-plugins
│   ├── class-tekton-admin-customizer.php # Optional: admin UI customization
│   ├── class-tekton-rest-api.php         # REST endpoints for the builder UI
│   ├── class-tekton-security.php         # Code validation & sandboxing
│   │
│   └── field-engine/
│       ├── class-tekton-field-engine.php     # Core field engine orchestrator
│       ├── class-tekton-field-registry.php   # Registers field types
│       ├── class-tekton-field-group.php      # Field group definition & storage
│       ├── class-tekton-field-renderer.php   # Renders field editing UI (meta boxes)
│       ├── class-tekton-field-storage.php    # Read/write field values (wp_postmeta)
│       ├── class-tekton-cpt-manager.php      # Registers CPTs and taxonomies
│       ├── class-tekton-options-page.php     # Options pages (global settings fields)
│       ├── class-tekton-acf-compat.php       # Read-only ACF compatibility layer
│       └── fields/
│           ├── class-field-text.php
│           ├── class-field-textarea.php
│           ├── class-field-wysiwyg.php
│           ├── class-field-number.php
│           ├── class-field-email.php
│           ├── class-field-url.php
│           ├── class-field-password.php
│           ├── class-field-image.php
│           ├── class-field-gallery.php
│           ├── class-field-file.php
│           ├── class-field-select.php
│           ├── class-field-checkbox.php
│           ├── class-field-radio.php
│           ├── class-field-true-false.php
│           ├── class-field-date.php
│           ├── class-field-datetime.php
│           ├── class-field-time.php
│           ├── class-field-color.php
│           ├── class-field-range.php
│           ├── class-field-repeater.php
│           ├── class-field-group.php
│           ├── class-field-flexible-content.php
│           ├── class-field-relationship.php
│           ├── class-field-post-object.php
│           ├── class-field-taxonomy.php
│           └── class-field-code.php
│
├── bridge-theme/                         # Minimal required WordPress theme
│   ├── style.css                         # Theme headers only
│   ├── index.php                         # Empty — Tekton takes over
│   ├── functions.php                     # Minimal, calls back into Tekton
│   └── screenshot.png                    # Theme screenshot for WP admin
│
├── admin/                                # Builder UI (Svelte 5 SPA)
│   ├── src/
│   │   ├── App.svelte                    # Root app component
│   │   ├── main.js                       # Entry point, mounts to WP admin
│   │   ├── lib/
│   │   │   ├── stores/
│   │   │   │   ├── chat.svelte.js        # Chat history state (Svelte 5 runes)
│   │   │   │   ├── page.svelte.js        # Current page structure state
│   │   │   │   ├── editor.svelte.js      # Inline editor state
│   │   │   │   ├── versions.svelte.js    # Version history state
│   │   │   │   ├── plugins.svelte.js     # Generated plugins state
│   │   │   │   └── settings.svelte.js    # Plugin settings state
│   │   │   ├── api.js                    # WP REST API wrapper (nonce-aware fetch)
│   │   │   ├── schema.js                 # Component schema helpers
│   │   │   ├── diff.js                   # Structure diffing for versions
│   │   │   └── context.js                # Context preparation helpers
│   │   ├── components/
│   │   │   ├── ChatPanel.svelte          # AI chat interface (left side)
│   │   │   ├── PreviewPanel.svelte       # Live preview iframe (right side)
│   │   │   ├── InlineEditor.svelte       # Click-to-edit overlay on preview
│   │   │   ├── PropertyPanel.svelte      # Side panel for element properties
│   │   │   ├── PageSelector.svelte       # Page/template navigation
│   │   │   ├── VersionHistory.svelte     # Rollback UI
│   │   │   ├── PluginModePanel.svelte    # Generated plugins management
│   │   │   ├── ComponentTree.svelte      # Tree view of page structure
│   │   │   ├── SiteContextPanel.svelte   # Shows what AI knows about the site
│   │   │   ├── GlobalStylesPanel.svelte  # Design tokens / global settings
│   │   │   ├── FieldGroupPanel.svelte    # View/manage field groups
│   │   │   └── AdminCustomizer.svelte    # Optional: admin UI builder
│   │   └── ui/                           # Reusable primitives
│   ├── package.json
│   ├── vite.config.js
│   ├── svelte.config.js
│   └── tailwind.config.js
│
├── assets/
│   ├── css/
│   │   ├── tekton-frontend-reset.css     # CSS reset for rendered pages
│   │   └── tekton-field-ui.css           # Styles for field engine meta boxes
│   └── js/
│       ├── tekton-inline-editor.js       # Frontend inline editing runtime
│       └── tekton-preview-bridge.js      # PostMessage bridge for iframe
│
├── docs/                                 # Design mockups and documentation
│   └── .gitkeep
│
├── component-library/                    # Built-in component definitions
│   ├── core/
│   │   ├── section.json
│   │   ├── container.json
│   │   ├── heading.json
│   │   ├── text.json
│   │   ├── image.json
│   │   ├── button.json
│   │   ├── link.json
│   │   ├── list.json
│   │   ├── icon.json
│   │   ├── spacer.json
│   │   ├── divider.json
│   │   └── video.json
│   ├── layout/
│   │   ├── grid.json
│   │   ├── flex-row.json
│   │   ├── flex-column.json
│   │   ├── sidebar-layout.json
│   │   └── masonry.json
│   ├── navigation/
│   │   ├── navbar.json
│   │   ├── footer.json
│   │   ├── breadcrumbs.json
│   │   ├── pagination.json
│   │   └── mobile-menu.json
│   ├── wordpress/
│   │   ├── post-loop.json
│   │   ├── post-title.json
│   │   ├── post-content.json
│   │   ├── post-meta.json
│   │   ├── featured-image.json
│   │   ├── taxonomy-list.json
│   │   ├── menu.json
│   │   ├── search-form.json
│   │   ├── sidebar-widgets.json
│   │   ├── tekton-field.json
│   │   ├── acf-field.json
│   │   └── woo-product-loop.json
│   ├── interactive/
│   │   ├── accordion.json
│   │   ├── tabs.json
│   │   ├── modal.json
│   │   ├── slider.json
│   │   ├── lightbox.json
│   │   ├── counter.json
│   │   └── scroll-animation.json
│   └── forms/
│       ├── contact-form.json
│       ├── newsletter-signup.json
│       └── search-bar.json
│
├── templates/                            # AI system prompt templates
│   ├── system-prompt-page.md
│   ├── system-prompt-component.md
│   ├── system-prompt-fullstack.md
│   ├── system-prompt-plugin.md
│   ├── system-prompt-admin.md
│   └── context-template.md
│
└── tests/
    ├── php/
    │   ├── test-renderer.php
    │   ├── test-schema-validation.php
    │   ├── test-theme-bridge.php
    │   ├── test-field-engine.php
    │   ├── test-field-storage.php
    │   └── test-security.php
    └── js/
        ├── chatPanel.test.js
        ├── previewPanel.test.js
        └── schemaUtils.test.js
```

---

## 3. Field Engine — Detailed Specification

### 3.1 Data Model

**Field Groups** — stored in custom table:

```sql
CREATE TABLE {prefix}tekton_field_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    fields LONGTEXT NOT NULL,                   -- JSON array of field definitions
    location_rules LONGTEXT NOT NULL,           -- JSON: where to show this group
    position ENUM('normal', 'side', 'acf_after_title') DEFAULT 'normal',
    priority ENUM('high', 'core', 'default', 'low') DEFAULT 'high',
    menu_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    source ENUM('ai', 'manual') DEFAULT 'ai',
    ai_prompt TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Custom Post Types** — stored in custom table:

```sql
CREATE TABLE {prefix}tekton_post_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(20) NOT NULL UNIQUE,
    config LONGTEXT NOT NULL,                   -- JSON: register_post_type args
    taxonomies LONGTEXT DEFAULT NULL,           -- JSON: associated taxonomy configs
    source ENUM('ai', 'manual') DEFAULT 'ai',
    ai_prompt TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Field Values** — stored in standard `wp_postmeta`:

```
Meta key format: _tekton_{field_group_slug}_{field_name}
Example:         _tekton_team_member_job_title
```

Using `wp_postmeta` ensures full WP_Query compatibility, data portability, REST API support, and cross-plugin readability.

**Options Page Values** — stored in `wp_options`:

```
Option key format: _tekton_opt_{options_page_slug}_{field_name}
Example:           _tekton_opt_site_settings_company_phone
```

### 3.2 Field Types

Each field type implements `Tekton_Field_Interface`:

```php
interface Tekton_Field_Interface {
    public function get_type(): string;
    public function get_label(): string;
    public function render( array $field_config, $value, int $post_id ): string;
    public function sanitize( $value, array $field_config );
    public function validate( $value, array $field_config );
    public function format_value( $value, array $field_config );
    public function get_rest_schema( array $field_config ): array;
}
```

**Supported types:** text, textarea, wysiwyg, number, email, url, password, image, gallery, file, select, checkbox, radio, true_false, date, datetime, time, color, range, repeater, group, flexible_content, relationship, post_object, taxonomy, code.

### 3.3 Field Group Schema (JSON — what the AI generates)

```jsonc
{
  "title": "Team Member Details",
  "slug": "team_member",
  "fields": [
    {
      "name": "job_title",
      "type": "text",
      "label": "Job Title",
      "placeholder": "e.g. Senior Developer",
      "required": true,
      "maxLength": 100
    },
    {
      "name": "photo",
      "type": "image",
      "label": "Profile Photo",
      "required": true,
      "returnFormat": "id",
      "previewSize": "medium"
    },
    {
      "name": "social_links",
      "type": "repeater",
      "label": "Social Media Links",
      "buttonLabel": "Add Link",
      "minRows": 0,
      "maxRows": 5,
      "subFields": [
        { "name": "platform", "type": "select", "label": "Platform",
          "choices": { "linkedin": "LinkedIn", "twitter": "X", "github": "GitHub" } },
        { "name": "url", "type": "url", "label": "Profile URL" }
      ]
    }
  ],
  "location_rules": [
    [{ "param": "post_type", "operator": "==", "value": "team_member" }]
  ]
}
```

**Location rule params:** post_type, page, page_template, post_category, post_taxonomy, user_role, options_page.

### 3.4 CPT Schema (JSON)

```jsonc
{
  "slug": "team_member",
  "config": {
    "label": "Team Members",
    "labels": { "singular_name": "Team Member", "add_new": "Add Team Member" },
    "public": true,
    "has_archive": true,
    "rewrite": { "slug": "team" },
    "supports": ["title", "thumbnail"],
    "menu_icon": "dashicons-groups",
    "show_in_rest": true
  },
  "taxonomies": [
    {
      "slug": "department",
      "config": {
        "label": "Departments",
        "hierarchical": true,
        "public": true,
        "show_in_rest": true
      }
    }
  ]
}
```

Note: `supports` omits `editor` — no Gutenberg. Content entry happens via Tekton field groups.

### 3.5 ACF Compatibility

Read-only layer: detects ACF fields, provides `tekton_get_acf_field()`. Component schema can reference `{ "source": "acf", "field": "..." }`. One-way read. Phase 4 migration tool converts ACF groups to Tekton groups.

### 3.6 Gutenberg Replacement

On activation:
1. `add_filter( 'use_block_editor_for_post', '__return_false' )`
2. `add_filter( 'use_block_editor_for_post_type', '__return_false' )`
3. Classic Editor as minimal fallback for `post_content`.
4. Tekton field groups render as meta boxes, replacing the editor as the primary content UI.
5. `post_content` field can be hidden on CPTs that don't need it.

---

## 4. Component Schema

### 4.1 Content Source Model ("Dumb Frontend")

Components never contain content directly. They reference content sources:

```jsonc
// Content source types:

// Tekton field (primary)
{ "source": "field", "group": "group_slug", "field": "field_name", "fallback": "..." }

// WordPress core post field
{ "source": "post", "field": "post_title" }
{ "source": "post", "field": "featured_image", "size": "large" }

// WordPress option
{ "source": "option", "key": "blogname" }
{ "source": "option", "key": "_tekton_opt_settings_phone" }

// WordPress menu
{ "source": "menu", "location": "primary" }

// ACF field (compat)
{ "source": "acf", "field": "hero_title", "fallback": "..." }

// Static (ONLY for structural labels, aria, etc.)
{ "source": "static", "value": "Read More →" }

// Computed
{ "source": "computed", "expression": "post_count", "args": { "post_type": "team_member" } }
```

The AI should prefer `source: "field"` for any content users might edit, and generate the field group alongside the template.

### 4.2 Base Component Interface

```jsonc
{
  "id": "comp_a1b2c3d4",
  "type": "section",
  "label": "Hero Section",
  "props": {
    "tagName": "section",
    "className": "",
    "content": { "source": "field", "group": "...", "field": "...", "fallback": "..." }
  },
  "styles": {
    "desktop": { "padding": "80px 0", "backgroundColor": "var(--tekton-bg-primary)" },
    "tablet": {},
    "mobile": {}
  },
  "children": [],
  "conditions": {
    "loggedIn": null,
    "userRole": null,
    "postType": null,
    "customCondition": null
  },
  "editable": {
    "content": true,
    "image": true,
    "colors": true,
    "spacing": true,
    "visibility": true
  },
  "_ai": {
    "generatedAt": "2026-03-05T12:00:00Z",
    "prompt": "...",
    "version": 3
  }
}
```

### 4.3 Full-Stack Generation Response

When the AI generates CPT + fields + template together:

```jsonc
{
  "type": "fullstack",
  "postTypes": [ { "slug": "team_member", "config": {}, "taxonomies": [] } ],
  "fieldGroups": [ { "title": "...", "slug": "...", "fields": [], "location_rules": [] } ],
  "structure": {
    "templateKey": "archive-team_member",
    "components": { /* component tree referencing the fields */ }
  }
}
```

### 4.4 Design Tokens

Stored as `tekton_design_tokens` in `wp_options`. Injected as CSS custom properties. All component styles reference these tokens (e.g. `var(--tekton-accent)`). Full set includes colors, typography, spacing, layout, shadows, borders, and Google Fonts list.

---

## 5. Core Modules

### 5.1 Theme Bridge (`class-tekton-theme-bridge.php`)

Intercepts `template_include` at priority 999. Looks up Tekton structure for current context. Renders via component renderer or falls through to theme. Manages global header/footer structures.

Template mapping: front-page, home, single-{type}, archive-{type}, taxonomy-{slug}, search, 404, post-{ID}.

### 5.2 Renderer (`class-tekton-renderer.php`)

Walks component tree recursively. Resolves all content sources. Renders each component type to HTML. Escapes all output. Generates scoped CSS. Performance target: <50ms for 20-40 components. Transient-based caching.

### 5.3 AI Engine (`ai/class-tekton-ai-engine.php`)

Multi-provider AI integration supporting Anthropic (Claude), OpenAI (GPT), Google (Gemini), and OpenRouter. Provider-agnostic interface with per-provider API key storage (AES-256-CBC encrypted using `wp_salt('auth')`). Note: if WordPress salts are regenerated, users must re-enter API keys.

**Providers:**
- **Anthropic** — Claude models. Hardcoded model list (claude-sonnet-4-20250514, claude-haiku-4-20250414, claude-opus-4-20250514).
- **OpenAI** — GPT models. Hardcoded model list (gpt-4o, gpt-4o-mini, gpt-4-turbo, o1, o1-mini).
- **Google Gemini** — Gemini models. Hardcoded model list (gemini-2.0-flash, gemini-2.0-pro, gemini-1.5-pro, gemini-1.5-flash).
- **OpenRouter** — Any model. Dynamic model list fetched from `/api/v1/models` endpoint, cached as transient (24h).

All providers implement SSE streaming by default via PHP Generators and curl. System prompt assembly with base instructions + context + current state + conversation history. Request types: generate_fullstack, generate_page, modify_page, generate_fields, generate_component, generate_plugin, customize_admin, explain. JSON response parsing with schema validation and 2x auto-retry.

### 5.4 Context Builder (`class-tekton-context-builder.php`)

Generates site snapshot: post types (with source: tekton/core/woo/plugin), taxonomies, Tekton field groups, ACF field groups (if present), menus, active plugins, existing templates, design tokens, generated plugins, options pages, WooCommerce status. Cached as transient, regenerated on relevant hooks. Token budget: 4000 (configurable).

### 5.5 Storage (`class-tekton-storage.php`)

Custom tables: `tekton_structures`, `tekton_versions`, `tekton_chat_history`, `tekton_plugins`, `tekton_admin_customizations`. Plus field engine tables: `tekton_field_groups`, `tekton_post_types`.

Versioning: every change creates a version. Max 50 per structure. JSON Patch diffs. Full snapshots every 10th version.

### 5.6 REST API (`class-tekton-rest-api.php`)

Namespace: `tekton/v1`. All endpoints require `manage_options`.

AI: generate, stream, explain.
Structures: CRUD + versions + rollback + publish.
Chat: get + clear per template.
Field Engine: field-groups CRUD, post-types CRUD, options-pages.
Plugins: CRUD + toggle activation.
Context: get + refresh.
Tokens: get + update.
Admin: customizations CRUD.
Settings: get + update.
Preview: render for iframe.

### 5.7 Plugin Generator (`class-tekton-plugin-generator.php`)

Generates self-contained micro-plugins as standalone WordPress plugins. Output to `wp-content/plugins/tekton-{slug}/`, each with its own plugin header and independently activatable via WordPress plugin manager. Security validation before activation (banned patterns: eval, exec, system, shell_exec, etc.). Optional component bridge for rendering integration.

### 5.8 Admin Customizer (`class-tekton-admin-customizer.php`)

Opt-in feature. CSS overrides + layout modifications. Menu reorder/rename/hide. Dashboard widget management. No arbitrary PHP execution. Safety: never removes Tekton's own pages, Plugins page, or Users page.

---

## 6. Builder UI

**Tech:** Svelte 5 (runes), Vite, Tailwind CSS, native Svelte stores. Build output: `admin/dist/`.

**Layout:** Resizable chat panel (left) + live preview iframe (right). Bottom bar with tabs: Tree, Fields, Versions, Plugins, Settings.

**Chat:** Per-template history. Slash commands: /new, /fullstack, /undo, /redo, /version, /plugin, /admin, /fields, /context, /tokens, /export, /import, /help.

**Preview:** iframe with PostMessage bridge. Responsive modes. Inline editing: click text (contenteditable), click image (upload), click element (property panel for colors/spacing/visibility).

**Fields Panel:** Shows all field groups + CPTs. Create new via mini-form or chat. Shows which fields the current template references.

---

## 7. Settings

```jsonc
{
  "tekton_ai_provider": "anthropic",
  "tekton_api_key_anthropic": "[encrypted]",
  "tekton_api_key_openai": "[encrypted]",
  "tekton_api_key_google": "[encrypted]",
  "tekton_api_key_openrouter": "[encrypted]",
  "tekton_ai_model": "claude-sonnet-4-20250514",
  "tekton_ai_max_tokens": 8192,
  "tekton_context_token_budget": 4000,
  "tekton_override_theme": true,
  "tekton_fallback_behavior": "theme",
  "tekton_cache_enabled": true,
  "tekton_cache_ttl": 3600,
  "tekton_minify_output": true,
  "tekton_inline_editing": true,
  "tekton_max_versions": 50,
  "tekton_disable_gutenberg": true,
  "tekton_acf_compat": true,
  "tekton_plugin_mode_enabled": true,
  "tekton_plugin_output_dir": "wp-content/plugins/",
  "tekton_admin_customizer_enabled": false,
  "tekton_require_code_review": true,
  "tekton_debug_mode": false
}
```

---

## 8. Security

- API keys encrypted with AES-256-CBC + key derived from `wp_salt('auth')`. Never sent to frontend. If salts are regenerated, users must re-enter keys.
- REST API: nonce auth + `manage_options` capability. Rate limiting: 30 AI req/min/user.
- Generated code: static analysis (banned patterns), manual review step.
- Rendered output: `esc_html()`, `esc_url()`, `wp_kses_post()`. CSS sanitized.
- Admin Customizer: no arbitrary PHP, CSS sanitized.

---

## 9. Implementation Status

### Done

**Plugin scaffold & bootstrap:**
- [x] `tekton.php` — plugin bootstrap with activation/deactivation hooks
- [x] `class-tekton-core.php` — singleton orchestrator, module loading, admin menu, asset enqueue
- [x] `class-tekton-activator.php` — activation hook (DB tables, bridge theme install)
- [x] DB tables created: `tekton_structures`, `tekton_versions`, `tekton_chat_history`, `tekton_field_groups`, `tekton_post_types`

**Bridge theme:**
- [x] `bridge-theme/` — minimal theme (style.css, index.php, functions.php)
- [x] Auto-installed on plugin activation

**Theme bridge & rendering:**
- [x] `class-tekton-theme-bridge.php` — `template_include` interception, template mapping
- [x] `class-tekton-renderer.php` — component JSON → HTML rendering (smart container detection: absolute-positioned containers render without layout constraints)
- [x] `template-canvas.php` — standalone HTML canvas for Tekton-rendered pages
- [x] `class-tekton-assets.php` — frontend asset loading, design token injection
- [x] `assets/css/tekton-frontend-reset.css` — CSS reset for rendered pages

**Component schema:**
- [x] `class-tekton-schema.php` — 16 core component types registered (section, container, div, heading, text, image, button, grid, flex-row, flex-column, link, list, spacer, divider, video, icon)
- [x] Schema validation (component + structure level)
- [x] Content source validation (field, post, option, acf, static, computed, menu)

**Storage:**
- [x] `class-tekton-storage.php` — full CRUD for structures, versioning, chat history
- [x] Field group listing, post type listing, activity feed
- [x] Version create/rollback

**AI Engine (multi-provider):**
- [x] `class-tekton-ai-engine.php` — provider-agnostic orchestrator
- [x] `interface-tekton-ai-provider.php` — provider interface
- [x] `class-tekton-provider-anthropic.php` — Anthropic/Claude SSE streaming
- [x] `class-tekton-provider-openai.php` — OpenAI/GPT SSE streaming
- [x] `class-tekton-provider-google.php` — Google/Gemini SSE streaming
- [x] `class-tekton-provider-openrouter.php` — OpenRouter SSE streaming
- [x] Real-time SSE streaming via PHP Fibers (chunks stream as they arrive)
- [x] AI response parsing — separates natural language from JSON code fences
- [x] Current template structure injected into AI context for modification awareness

**AI System Prompts:**
- [x] `templates/system-prompt-base.md` — core prompt with component schema, content sources, styles, two response modes (full generation + operations)
- [x] `templates/system-prompt-page.md` — page generation/modification instructions
- [x] `templates/system-prompt-fullstack.md` — full-stack generation (CPT + fields + template)
- [x] `templates/system-prompt-plugin.md` — micro-plugin generation
- [x] `templates/system-prompt-component.md` — single component generation
- [x] `templates/context-template.md` — site context wrapper with current template awareness

**Operations-based patching:**
- [x] `class-tekton-structure-patcher.php` — applies granular AI operations to existing component trees
- [x] 7 operations: `update_styles`, `update_props`, `update_content`, `add_component`, `remove_component`, `replace_component`, `move_component`
- [x] AI uses operations mode for modifications (token-efficient), full generation only for new pages

**Security:**
- [x] `class-tekton-security.php` — AES-256-CBC API key encryption, key masking
- [x] REST API nonce auth + `manage_options` capability check

**Context builder:**
- [x] `class-tekton-context-builder.php` — site snapshot for AI context

**REST API:**
- [x] `class-tekton-rest-api.php` — full endpoint set:
  - AI: `POST /ai/generate` (SSE), `GET /ai/models`
  - Structures: `GET/POST /structures`, `GET/DELETE /structures/{key}`, `GET /structures/{key}/versions`, `POST /structures/{key}/rollback`
  - Chat: `GET/DELETE /chat/{key}`
  - Context: `GET /context`, `POST /context/refresh`
  - Settings: `GET/POST /settings` (all settings keys, encrypted API keys)
  - Preview: `POST /preview`
  - Dashboard: `GET /dashboard`, `GET /field-groups`, `GET /post-types`, `GET /activity`
- [x] AI response parsing (JSON extraction from raw/fenced responses, operations detection)

**Gutenberg:**
- [x] Fully disabled (all post types, block library CSS dequeued)

**Design tokens:**
- [x] `tekton_design_tokens` option, CSS custom property injection via `wp_head`

**Builder UI (Svelte 5 + Vite + Tailwind):**
- [x] Vite build pipeline with manifest-based PHP enqueue
- [x] shadcn-svelte component library (Button, Card, Badge, Switch, Dialog, ConfirmDialog) with bits-ui
- [x] Custom warm stone/copper theme (Bricolage Grotesque, Outfit, Fira Code)
- [x] WordPress admin CSS overrides (scoped `#tekton-app` selectors)
- [x] `App.svelte` — dashboard/builder view routing
- [x] `Dashboard.svelte` — admin landing page with real data (templates, fields, CPTs, activity, settings)
- [x] `Builder.svelte` — two-zone layout (chat + preview), overlay drawer panels (tree, history, fields, plugins)
- [x] Chat panel with real-time SSE streaming, message history per template
- [x] Preview panel with iframe rendering, desktop/tablet/mobile viewports
- [x] Page selector dropdown with inline new template creation
- [x] Template deletion with custom confirm dialog (ConfirmDialog component)
- [x] Click-to-open templates from Dashboard into Builder
- [x] Component tree (flattened from current structure)
- [x] Version history with rollback
- [x] Field groups sidebar
- [x] Settings panel with live save (all settings + per-provider API key editing)
- [x] Stores: `chat.svelte.js`, `page.svelte.js`, `settings.svelte.js`, `dashboard.svelte.js`
- [x] `api.js` — full REST API wrapper with SSE streaming
- [x] Light/dark mode with system preference default, localStorage persistence, and toggle in Dashboard + Builder headers
- [x] `theme.svelte.js` store — mode cycling (system → light → dark), OS preference listener, `.dark` class strategy
- [x] Full CSS custom property theming — light and dark palettes, no hardcoded colors

### Done — Phase 2 (Field Engine & Content Sources)

- [x] **Field Engine core:**
  - [x] `class-tekton-field-engine.php` — orchestrator with meta box rendering and save
  - [x] `class-tekton-field-registry.php` — field type registry
  - [x] `class-tekton-field-type.php` — abstract base class
  - [x] `class-tekton-cpt-manager.php` — register CPTs and taxonomies from DB on `init`
  - [x] `class-tekton-options-page.php` — options pages with admin UI and field rendering
  - [x] `class-tekton-acf-compat.php` — ACF read-only compatibility
  - [x] `functions-tekton-fields.php` — public API (`tekton_get_field()`, `tekton_get_fields()`)
- [x] **All 26 field types** — text, textarea, wysiwyg, number, email, url, password, image, gallery, file, select, checkbox, radio, true_false, date, datetime, time, color, range, repeater, group, flexible_content, relationship, post_object, taxonomy, code
- [x] Content source resolution in renderer — field, post, acf, menu, computed, option, static; CPT post queries with `post_type` + `post_index`; custom meta field auto-detection
- [x] Full-stack generation mode — AI generates CPT + fields + template + posts in one shot, backend processes all in any response mode
- [x] WordPress-specific components — post-loop, post-title, post-content, post-meta, featured-image, menu, tekton-field, search-form
- [x] REST endpoints — field-groups CRUD, post-types CRUD, options-pages CRUD (split into dedicated controllers)
- [x] AI post creation — creates WordPress posts with Tekton custom field values populated
- [x] JSON parsing robustness — repair for trailing commas, control chars; data-only response handling
- [x] Script/keyframe merge — operations and full-gen both merge instead of replacing
- [x] i18n — full translation support for admin UI and field engine

### Missing — Phase 3 (Editing, Versions & Plugin Mode)

- [x] **Inline editor** — `InlineEditor.svelte`, `tekton-inline-editor.js`, `tekton-preview-bridge.js` (PostMessage bridge for iframe click-to-edit)
- [x] **Property panel** — `PropertyPanel.svelte` (side panel for colors/spacing/visibility)
- [x] **Component tree drag-and-drop** — recursive tree with collapsible parents, drag-and-drop reordering, nesting into containers, guide lines
- [ ] **Plugin Generator** — `class-tekton-plugin-generator.php` (generate micro-plugins to `wp-content/plugins/tekton-{slug}/`)
- [ ] **Plugin Mode UI** — `PluginModePanel.svelte`, plugins store
- [x] **Design tokens panel** — `GlobalStylesPanel.svelte` (AI-assisted partial theme modification)
- [ ] **Slash commands** — `/new`, `/fullstack`, `/undo`, `/redo`, `/version`, `/plugin`, `/admin`, `/fields`, `/context`, `/tokens`, `/export`, `/import`, `/help` (UI has 3 command shortcuts but no backend handling)
- [ ] Undo/redo in chat
- [ ] Component bridge system (micro-plugins rendering Tekton components)

### Missing — Phase 4 (Admin Customizer, Polish & Migration)

- [ ] `class-tekton-admin-customizer.php` — admin CSS/menu/dashboard customization
- [ ] `AdminCustomizer.svelte` — UI for admin customization
- [ ] WooCommerce components (`woo-product-loop.json`)
- [ ] ACF migration tool
- [ ] Image optimization
- [ ] HTML caching + critical CSS extraction
- [ ] SEO validation
- [ ] Import/export
- [ ] Custom component definitions
- [ ] Test suite (`tests/php/`, `tests/js/`)
- [ ] `readme.txt`, `LICENSE`

## 10. Implementation Phases

### Phase 1 — Foundation (MVP) — COMPLETE

Prompt → AI generates page → page renders on frontend.

**Deliverables:** Plugin bootstrap, DB tables, bridge theme (auto-installed on activation), theme bridge, core component schema + renderer (section, container, heading, text, image, button, grid, flex-row, flex-column, link, list, spacer, divider, video, icon), storage (CRUD, basic versioning), multi-provider AI engine with SSE streaming (Anthropic, OpenAI, Google Gemini, OpenRouter), context builder (basic), REST API (generate/stream, structures, preview, settings, models), Svelte 5 builder UI (chat + preview + page selector + settings), AI system prompt templates (base, page, fullstack, plugin, component, context), operations-based patching, design tokens, frontend CSS reset, Gutenberg fully disabled.

**Exit criteria:** User types "Create a landing page with hero, features grid, and CTA" → sees it rendered. "Make the hero dark" → sees update. ✅ Working.

### Phase 2 — Field Engine & Content Sources — COMPLETE

Full content separation. AI generates data models + templates together.

**Deliverables:** Complete Field Engine (all field types, groups, meta box rendering), CPT Manager, Options Pages, all content source types in renderer, full-stack generation mode, WordPress components (post-loop, post-title, post-content, post-meta, featured-image, menu, tekton-field, search-form), ACF compat layer, Field Groups panel in UI, REST endpoints for field engine.

**Exit criteria:** "Create a team page with name, role, photo, LinkedIn" → creates CPT + fields + archive template. Add team member in wp-admin → appears on frontend. ✅ Working.

### Phase 3 — Manual Editing, Versions & Plugin Mode — not started

Inline editing, version control, server-side code generation.

**Deliverables:** Inline editor (text, image, color, spacing), property panel, version history + rollback + undo/redo, component tree with drag-and-drop, Plugin Generator + security validator, Plugin Mode UI, component bridge system, responsive preview, design tokens panel, slash commands.

**Exit criteria:** Inline-edit without chat. Rollback versions. "Add a contact form that emails me" → working micro-plugin.

### Phase 4 — Admin Customizer, Polish & Migration — not started

Admin customization + production hardening.

**Deliverables:** Admin Customizer (CSS, menus, dashboard), WooCommerce components, ACF migration tool, image optimization, HTML caching + critical CSS, SEO validation, import/export, custom component definitions, performance pass, test suite.

**Exit criteria:** Fully customized frontend + admin. Production-stable. Public release ready.

---

## 10. Non-Goals

- Full drag-and-drop builder
- Gutenberg compatibility (it's disabled)
- Multi-user real-time collaboration
- SaaS / hosting layer
- Theme / template marketplace
- AI providers beyond Anthropic, OpenAI, Google, and OpenRouter
- Full ACF feature parity on day one

---

## 11. Success Metrics

1. Faster than Bricks + ACF for building client sites
2. >90% valid AI output on first generation
3. Lighthouse 90+ on rendered pages
4. >80% correct full-stack generation (CPT + fields + template)
5. GitHub community engagement
6. Works with top 20 WordPress plugins

---

## 12. Open Questions

- Component hot-reloading (Virtual DOM patching in preview iframe)
- AI cost management (token budgets, complexity warnings)
- i18n (builder UI + AI content generation in German)
- Headless mode (JSON export via REST for Next.js/Astro)
- CLI interface (`wp tekton generate "..."` for Claude Code)
- Visual field group builder (manual UI in addition to AI generation)

---

*Living document. Update as implementation progresses.*
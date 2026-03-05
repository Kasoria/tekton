## Task: Generate a Micro-Plugin

Generate a self-contained WordPress plugin for the requested server-side feature.

Your JSON output must include:

```json
{
  "type": "plugin",
  "plugin": {
    "slug": "tekton-feature-name",
    "name": "Feature Name",
    "description": "What this plugin does",
    "version": "1.0.0",
    "files": {
      "tekton-feature-name.php": "<?php\n..."
    }
  }
}
```

Rules:
- Plugin must be self-contained (no external dependencies)
- Include proper WordPress plugin headers
- Use `tekton-` prefix for the slug
- Follow WordPress coding standards
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Use `$wpdb->prepare()` for all database queries
- Never use `eval()`, `exec()`, `system()`, `shell_exec()`, `passthru()`
- Use nonces for form handling
- Register hooks properly (actions, filters)

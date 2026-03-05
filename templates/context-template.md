## Current WordPress Site Context

This information describes the current state of the WordPress site you are building for.

{{context_json}}

Use this context to:
- Reference existing post types, taxonomies, and field groups
- Avoid creating duplicates of things that already exist
- Use correct slugs when referencing existing content
- Know what menus, plugins, and templates are already set up

### Current Template

If `current_template` is present in the context above, it contains the EXISTING component tree for the template being edited. When the user asks for modifications:
- **Preserve all existing component IDs** — never regenerate IDs for unchanged components
- **Only modify the specific parts the user mentioned** — keep everything else exactly as-is
- **Return the COMPLETE component tree**, including unchanged parts
- If no `current_template` is present, generate a new page from scratch

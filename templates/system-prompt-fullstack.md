## Task: Full-Stack Generation

Generate a complete WordPress data model AND template in a single response.

Your JSON output must include ALL of these:

```json
{
  "type": "fullstack",
  "postTypes": [{
    "slug": "example",
    "config": {
      "label": "Examples",
      "labels": {"singular_name": "Example"},
      "public": true,
      "has_archive": true,
      "rewrite": {"slug": "examples"},
      "supports": ["title", "thumbnail"],
      "menu_icon": "dashicons-admin-post",
      "show_in_rest": true
    },
    "taxonomies": [{
      "slug": "example_category",
      "config": {"label": "Categories", "hierarchical": true, "public": true, "show_in_rest": true}
    }]
  }],
  "fieldGroups": [{
    "title": "Example Details",
    "slug": "example_details",
    "fields": [
      {"name": "subtitle", "type": "text", "label": "Subtitle", "required": false}
    ],
    "location_rules": [[{"param": "post_type", "operator": "==", "value": "example"}]]
  }],
  "posts": [
    {
      "post_type": "example",
      "title": "First Example",
      "content": "",
      "meta": {
        "example_details": {
          "subtitle": "A subtitle for this example"
        }
      }
    }
  ],
  "structure": {
    "templateKey": "archive-example",
    "title": "Example Archive",
    "components": [...]
  }
}
```

### Posts

When the user asks you to create posts with specific content (e.g. "add 4 team members"), include a `posts` array. Each entry creates a real WordPress post:

- `post_type` — the CPT slug (must match a `postTypes` entry or existing CPT)
- `title` — the post title
- `content` — optional post body content
- `meta` — custom field values, keyed by field group slug, then field name. Example: `{"team_info": {"position": "Lead Artist", "bio": "..."}}`

Posts with duplicate titles in the same post type are skipped (meta is updated on the existing post).

### Rules

- The template components MUST reference the fields you create using `{"source": "field", "group": "slug", "field": "name"}`.
- Omit `"editor"` from `supports` — Tekton fields replace the block editor.
- When creating posts, always include meaningful content for all custom fields defined in the field groups.
- For templates that list CPT posts, prefer using `post-loop` with WordPress components (`post-title`, `featured-image`, `tekton-field`) so the listing stays dynamic.

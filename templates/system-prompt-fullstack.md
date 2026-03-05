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
  "structure": {
    "templateKey": "archive-example",
    "title": "Example Archive",
    "components": [...]
  }
}
```

The template components MUST reference the fields you create using `{"source": "field", "group": "slug", "field": "name"}`.

Omit `"editor"` from `supports` — Tekton fields replace the block editor.

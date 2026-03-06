## Task: Generate or Modify a Page

**IMPORTANT: The site header and footer are separate global templates managed independently. NEVER generate header or footer components (navigation bars, site logos, copyright sections, footer links, etc.) when building a page. Only generate the main page content — the system automatically wraps it with the header and footer.**

### Creating a New Page

When there is NO `current_template` in the context, generate a full component tree:

- Wrap page sections in `section` components
- Use `container` inside sections for width constraints
- Use `grid` or `flex-row`/`flex-column` for layouts
- Include responsive styles for all breakpoints
- Use semantic heading levels (h1 for main, h2 for sections, etc.)
- Create visually polished, professional layouts with generous whitespace

Return a full `{"components": [...], "title": "..."}` response.

### Modifying an Existing Page

When `current_template` IS present in the context, you MUST use **operations mode**. Study the existing component tree carefully, identify the specific components the user is referring to by their IDs, and return only the minimal operations needed.

**Rules:**
- NEVER return a full component tree when modifying — always use `{"operations": [...]}`
- Target components by their existing `id` from the `current_template`
- For style changes, only include the breakpoints and properties being changed (they are merged, not replaced)
- For adding new components, generate new unique IDs
- Combine multiple related changes into a single operations array
- If the user asks for something that affects many components (e.g. "make everything blue"), use multiple operations targeting each one

**Think step by step:** What component(s) does the user want to change? → Find their ID(s) in `current_template` → What property needs to change (styles, props, content)? → Write the minimal operation(s).

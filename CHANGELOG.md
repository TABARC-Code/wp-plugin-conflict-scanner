## `CHANGELOG.md`

```markdown
# Changelog

## 1.0.0

- First public release.
- Added Tools  
  Plugin Conflict Scanner screen.
- Hook scanning:
  - Focus on high risk hooks such as `the_content`, `wp_head`, `wp_footer`, `init`, `template_redirect`, enqueue hooks and widget registration.
  - For each hook, list plugins that attach callbacks and their priorities.
  - Highlight hooks where more than one plugin is involved.
- Shortcode scanning:
  - Inspect all registered shortcodes.
  - Resolve callbacks back to plugin files where possible.
  - List shortcodes with more than one plugin involved.
- Read only analysis:
  - Does not disable or modify plugins.
  - No database writes during scans.
- Licensed under GPL-3.0-or-later.

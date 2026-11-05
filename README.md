# Theme Variants

Per-theme template variants for MyBB 1.8. Forums that run several designs on **one shared
template set** (a master theme plus child themes that only differ in stylesheets) can give
each theme its own markup without duplicating the template set and without PHP in
templates. In the ACP you define: “Theme X uses variant Z for template/slot Y on pages …”.
Child themes inherit the assignments of their parent theme and can override or disable them.

## Requirements
- MyBB 1.8.x (verified against 1.8.39)
- PHP 7.4 or 8.x
- Dependencies: none.

## Installation
1. Upload the files to your MyBB root directory, keeping the folder structure:
   - `inc/plugins/themevariants.php`
   - `admin/modules/style/themevariants.php`
   - `inc/languages/english/admin/themevariants.lang.php`
   - `inc/languages/deutsch_du/admin/themevariants.lang.php`
2. ACP → Configuration → Plugins → **Theme Variants** → Install & Activate.
3. ACP → Configuration → Settings → **Theme Variants** → check the settings.
4. Give the admin permission **Theme Variants** (Templates & Style) to every admin group that
   should manage assignments.

## Usage

### Concepts
There are two assignment types, both stored per theme:

| Type | Target | Effect |
|---|---|---|
| **Replace template** (`override`) | Title of an existing template, e.g. `footer` | At runtime the template is replaced by the variant template. Every later use of the target template shows the variant. |
| **Fill slot** (`slot`) | A slot name, e.g. `headertabelle` | You place the placeholder `<!--themevariants:headertabelle-->` in any template. When the page is output, the placeholder is replaced by the rendered variant – or by nothing if no assignment applies. |

- **Variants are ordinary MyBB templates** in the theme's template set (or global templates).
  The plugin never creates, edits or deletes them – you maintain them in the template editor.
  Suggested naming: `tv_<target>_<design>`, e.g. `tv_headertabelle_always`.
- **Inheritance:** a theme uses its own row for a type/target; if it has none, the row of the
  nearest parent theme applies. A row with an **empty variant** means “no variant here” and
  stops the inheritance.
- **Pages:** an empty page list means “all pages”. Otherwise the assignment only applies to
  the listed scripts (e.g. `index.php, portal.php`). If the current page is not listed, no
  variant is used – there is no fallback to a parent theme's row.
- **Inactive rows** are ignored as if they did not exist (the parent's row may apply).

### Managing assignments
ACP → Templates & Style → **Theme Variants**.

- **Overview** – the theme tree with the effective assignments of every theme: type, target,
  variant, pages, origin (own or inherited from which theme) and status. A warning symbol
  marks variants whose template does not exist yet. Own rows can be edited, toggled and
  deleted; inherited rows can be overridden for the theme with one click.
- **Add Assignment** – choose theme, type, target, variant (with suggestions from the
  theme's templates), pages and whether the row is active. Duplicates (same theme, type and
  target) are rejected with an error message.

### Example: an extra header table for one design
1. Add `<!--themevariants:headertabelle-->` to the `header` template of the shared set.
2. Create the template `tv_headertabelle_always` with the markup for that design. It can use
   the same variables as other global templates (`{$mybb->…}`, `{$lang->…}`, `{$pmslink}`,
   variables of other plugins …).
3. ACP → Theme Variants → Add Assignment: theme “Always”, type *Fill slot*, target
   `headertabelle`, variant `tv_headertabelle_always`, pages empty.
All other themes render nothing at the placeholder.

### Tips
- Turn on **Debug output for admins** to see HTML comments around every replaced template or
  slot, including the variant used and the theme it was inherited from.
- Overrides run at `global_intermediate` with priority -10, so templates evaluated by other
  plugins at that hook (priority 0 or higher) can be replaced as well.

## Configuration
- **Enable Theme Variants** – master switch. When off, no template is replaced and slot
  placeholders render as empty strings. Default: on.
- **Debug Output for Admins** – shows HTML comments with the resolved assignment for admins.
  Default: off.

## Uninstallation
ACP → Configuration → Plugins → **Theme Variants** → Deactivate, then Uninstall. This removes
the assignment table, the settings and the cache entry. Your variant templates and the
placeholders in your templates stay untouched (placeholders are HTML comments and harmless).

## Changelog
See [CHANGELOG.md](CHANGELOG.md).

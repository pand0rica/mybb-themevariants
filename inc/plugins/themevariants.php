<?php
/**
 * Theme Variants
 *
 * Pro Theme eigene Template-Varianten: bestehende Templates ersetzen ("override") oder
 * zusätzliche Slots füllen ("slot"), mit Vererbung über die Theme-Eltern-Kette und einer
 * pro Zuordnung frei wählbaren Seitenliste. Siehe PROJECT_CONTEXT.md für die volle Spezifikation.
 *
 * @package themevariants
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// ---------------------------------------------------------------------------------------
// Hook registration
// ---------------------------------------------------------------------------------------

$plugins->add_hook('global_intermediate', 'themevariants_handle_overrides', -10);
$plugins->add_hook('pre_output_page', 'themevariants_handle_slots', 10);

if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_style_menu', 'themevariants_admin_menu', 10);
    $plugins->add_hook('admin_style_action_handler', 'themevariants_admin_action_handler', 10);
    $plugins->add_hook('admin_style_permissions', 'themevariants_admin_permissions', 10);
}

// ---------------------------------------------------------------------------------------
// info()
// ---------------------------------------------------------------------------------------

function themevariants_info(): array
{
    return [
        'name'          => 'Theme Variants',
        'description'   => 'Per-theme template variants: override existing templates or fill slots, with inheritance across the theme parent chain and a per-assignment page list.',
        'website'       => 'https://github.com/pand0rica/mybb-themevariants',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => '1.0.0',
        'guid'          => '',
        'codename'      => 'themevariants',
        'compatibility' => '18*',
    ];
}

// ---------------------------------------------------------------------------------------
// Lifecycle: install() / is_installed() / uninstall()
// ---------------------------------------------------------------------------------------

/**
 * Legt die Zuordnungstabelle und die Settings an. Settings sind Nutzerdaten und gehören
 * deshalb hierher (nicht in activate()), siehe debugging.md Punkt 21.
 */
function themevariants_install(): void
{
    global $db;

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "themevariants` (
        `vid` INT(11) NOT NULL AUTO_INCREMENT,
        `tid` INT(11) NOT NULL,
        `type` VARCHAR(10) NOT NULL,
        `target` VARCHAR(120) NOT NULL,
        `variant` VARCHAR(120) NOT NULL DEFAULT '',
        `scripts` TEXT,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `dateline` INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`vid`),
        UNIQUE KEY `tid_type_target` (`tid`, `type`, `target`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    themevariants_install_settings();
    rebuild_settings();
}

/**
 * Idempotente Settings-Anlage: install() ruft dies einmalig auf, activate() darf dieselbe
 * Funktion für spätere Versionen erneut aufrufen (rüstet nur fehlende Settings nach).
 */
function themevariants_install_settings(): void
{
    global $db;

    $existing = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'themevariants'"));
    if ($existing) {
        $gid = (int)$existing['gid'];
    } else {
        $gid = (int)$db->insert_query('settinggroups', [
            'name'        => 'themevariants',
            'title'       => 'Theme Variants',
            'description' => 'Settings for the Theme Variants plugin.',
            'disporder'   => 1,
            'isdefault'   => 0,
        ]);
    }

    $settings = [
        [
            'name'        => 'themevariants_enabled',
            'title'       => 'Enable Theme Variants',
            'description' => 'Master switch. When disabled, all template overrides and slots are skipped; slot placeholders are rendered as an empty string.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 1,
        ],
        [
            'name'        => 'themevariants_debug',
            'title'       => 'Debug Output for Admins',
            'description' => 'When enabled, admins (Can Access Admin CP) see HTML comments around every resolved override/slot, showing target, variant and inheritance source.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 2,
        ],
    ];

    foreach ($settings as $setting) {
        $exists = $db->fetch_array($db->simple_select(
            'settings',
            'sid',
            "name = '" . $db->escape_string($setting['name']) . "'"
        ));
        if ($exists) {
            continue; // idempotent: already present (fresh install or earlier upgrade)
        }
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }
}

function themevariants_is_installed(): bool
{
    global $db;
    return $db->table_exists('themevariants');
}

/**
 * Spiegelbild zu install(): Tabelle droppen, Settings + Gruppe entfernen, Datacache-Eintrag
 * löschen. Legt/löscht bewusst keine Templates (siehe "What NOT to change").
 */
function themevariants_uninstall(): void
{
    global $db, $cache;

    $db->drop_table('themevariants');

    $db->delete_query('settings', "name LIKE 'themevariants_%'");
    $db->delete_query('settinggroups', "name = 'themevariants'");
    rebuild_settings();

    if (is_object($cache) && method_exists($cache, 'delete')) {
        $cache->delete('themevariants');
    }
    // Defensive fallback in case the cache object above didn't actually remove the row
    // (e.g. file-based cache handler quirks) -- harmless no-op if already gone.
    $db->delete_query('datacache', "title = 'themevariants'");
}

// ---------------------------------------------------------------------------------------
// activate() / deactivate()
// ---------------------------------------------------------------------------------------

/**
 * Baut nur den Datacache-Eintrag auf. Idempotent, kann beliebig oft aufgerufen werden.
 */
function themevariants_activate(): void
{
    themevariants_rebuild_cache();
}

/**
 * Bewusst leer: Theme Variants legt keine Templates, Stylesheets oder Template-Injektionen
 * an, die beim Deaktivieren entfernt werden müssten (siehe PROJECT_CONTEXT.md
 * "What NOT to change"). Solange das Plugin deaktiviert ist, laufen die Runtime-Hooks
 * ohnehin nicht -- der Datacache-Eintrag bleibt harmlos liegen, bis er per Uninstall
 * entfernt oder bei der nächsten Aktivierung neu aufgebaut wird.
 */
function themevariants_deactivate(): void
{
}

// ---------------------------------------------------------------------------------------
// DB access (CRUD)
// ---------------------------------------------------------------------------------------

function themevariants_db_get_all(): array
{
    global $db;

    $rows = [];
    $query = $db->simple_select('themevariants', '*', '', ['order_by' => 'tid, type, target']);
    while ($row = $db->fetch_array($query)) {
        $rows[] = $row;
    }
    return $rows;
}

function themevariants_db_get(int $vid): ?array
{
    global $db;

    $row = $db->fetch_array($db->simple_select(
        'themevariants',
        '*',
        'vid = ' . (int)$vid,
        ['limit' => 1]
    ));
    return $row ?: null;
}

function themevariants_db_insert(array $row): int
{
    global $db;

    return (int)$db->insert_query('themevariants', [
        'tid'      => (int)$row['tid'],
        'type'     => $db->escape_string((string)$row['type']),
        'target'   => $db->escape_string((string)$row['target']),
        'variant'  => $db->escape_string((string)$row['variant']),
        'scripts'  => $db->escape_string((string)$row['scripts']),
        'active'   => (int)$row['active'],
        'dateline' => (int)$row['dateline'],
    ]);
}

function themevariants_db_update(int $vid, array $row): void
{
    global $db;

    $db->update_query('themevariants', [
        'tid'     => (int)$row['tid'],
        'type'    => $db->escape_string((string)$row['type']),
        'target'  => $db->escape_string((string)$row['target']),
        'variant' => $db->escape_string((string)$row['variant']),
        'scripts' => $db->escape_string((string)$row['scripts']),
        'active'  => (int)$row['active'],
    ], 'vid = ' . (int)$vid);
}

function themevariants_db_delete(int $vid): void
{
    global $db;
    $db->delete_query('themevariants', 'vid = ' . (int)$vid);
}

function themevariants_db_exists(int $tid, string $type, string $target, int $exclude_vid = 0): bool
{
    global $db;

    $where = 'tid = ' . (int)$tid
        . " AND type = '" . $db->escape_string($type) . "'"
        . " AND target = '" . $db->escape_string($target) . "'";
    if ($exclude_vid > 0) {
        $where .= ' AND vid != ' . (int)$exclude_vid;
    }

    $row = $db->fetch_array($db->simple_select('themevariants', 'vid', $where, ['limit' => 1]));
    return (bool)$row;
}

// ---------------------------------------------------------------------------------------
// Datacache
// ---------------------------------------------------------------------------------------

/**
 * Baut den Datacache-Eintrag "themevariants" neu auf: alle Themes (tid => pid) und alle
 * Zuordnungszeilen gruppiert nach tid, mit bereits geparster Seitenliste.
 */
function themevariants_rebuild_cache(): void
{
    global $db, $cache;

    $themes = [];
    $themes_query = $db->simple_select('themes', 'tid, pid');
    while ($theme_row = $db->fetch_array($themes_query)) {
        $themes[(int)$theme_row['tid']] = (int)$theme_row['pid'];
    }

    $rows_by_tid = [];
    foreach (themevariants_db_get_all() as $row) {
        $tid = (int)$row['tid'];
        if (!isset($rows_by_tid[$tid])) {
            $rows_by_tid[$tid] = [];
        }
        $rows_by_tid[$tid][] = [
            'type'    => (string)$row['type'],
            'target'  => (string)$row['target'],
            'variant' => (string)$row['variant'],
            'scripts' => themevariants_parse_scripts((string)$row['scripts']),
            'active'  => (int)$row['active'],
        ];
    }

    $cache->update('themevariants', [
        'themes' => $themes,
        'rows'   => $rows_by_tid,
    ]);
}

/**
 * Liest den Datacache-Eintrag; baut ihn einmalig neu auf, falls er (noch) fehlt.
 */
function themevariants_get_cache(): array
{
    global $cache;

    $data = $cache->read('themevariants');
    if (!is_array($data) || !isset($data['themes'], $data['rows'])) {
        themevariants_rebuild_cache();
        $data = $cache->read('themevariants');
    }
    if (!is_array($data) || !isset($data['themes'], $data['rows'])) {
        // Should not happen outside of a broken cache handler -- fail safe with empty data.
        $data = ['themes' => [], 'rows' => []];
    }

    return $data;
}

/**
 * Wie themevariants_get_cache(), baut den Cache aber zusätzlich (maximal einmal pro
 * Request) neu auf, wenn die übergebene tid darin unbekannt ist -- z.B. weil das Theme
 * nach dem letzten Cache-Aufbau angelegt wurde.
 */
function themevariants_get_cache_ensuring_tid(int $tid): array
{
    static $rebuilt_for_missing_tid = false;

    $data = themevariants_get_cache();
    if ($tid > 0 && !isset($data['themes'][$tid]) && !$rebuilt_for_missing_tid) {
        $rebuilt_for_missing_tid = true;
        themevariants_rebuild_cache();
        $data = themevariants_get_cache();
    }

    return $data;
}

// ---------------------------------------------------------------------------------------
// Resolution (pure functions, no globals -- testable via CLI)
// ---------------------------------------------------------------------------------------

/**
 * Ermittelt die effektive Zuordnung für Theme $tid und (type, target): eigene aktive Zeile,
 * sonst die des nächsten Vorfahren mit einer aktiven Zeile für dasselbe (type, target).
 * Inaktive Zeilen werden dabei übersprungen, als gäbe es sie nicht (Vererbung geht weiter
 * zum nächsten Vorfahren). Eine gefundene Zeile mit leerer Variante stoppt die Vererbung
 * (Rückgabe null). Passt die Seitenliste der gefundenen Zeile nicht zum aktuellen Skript,
 * gilt für diese Seite ebenfalls keine Variante (Rückgabe null, KEIN Rückfall auf einen
 * weiteren Vorfahren). Schützt sich selbst gegen Zyklen in der pid-Kette.
 *
 * @param array<int,int>   $themes tid => pid
 * @param array<int,array> $rows   tid => Liste von ['type','target','variant','scripts'(array),'active']
 */
function themevariants_resolve(array $themes, array $rows, int $tid, string $type, string $target, string $script): ?array
{
    $visited = [];
    $current = $tid;

    while ($current > 0 && !isset($visited[$current])) {
        $visited[$current] = true;

        if (isset($rows[$current]) && is_array($rows[$current])) {
            foreach ($rows[$current] as $row) {
                if ($row['type'] !== $type || $row['target'] !== $target) {
                    continue;
                }
                if (empty($row['active'])) {
                    continue; // inactive row: treated as if it doesn't exist, keep walking up
                }

                if ($row['variant'] === '') {
                    return null; // explicit "no variant here" -- stops inheritance
                }

                $scripts = (isset($row['scripts']) && is_array($row['scripts'])) ? $row['scripts'] : [];
                if (!empty($scripts) && !in_array($script, $scripts, true)) {
                    return null; // page list doesn't match the current script -- no ancestor fallback
                }

                return [
                    'type'     => $row['type'],
                    'target'   => $row['target'],
                    'variant'  => $row['variant'],
                    'scripts'  => $scripts,
                    'from_tid' => $current,
                ];
            }
        }

        $current = isset($themes[$current]) ? (int)$themes[$current] : 0;
    }

    return null;
}

/**
 * Wie themevariants_resolve(), aber ohne den Seitenlisten-Filter -- liefert die Zeile, die
 * laut Vererbung "gewinnt" (auch wenn ihre Variante leer ist), zur reinen Anzeige im ACP.
 * Nicht für die Laufzeit-Auflösung verwenden (die muss die Seitenliste berücksichtigen).
 */
function themevariants_resolve_effective_row(array $themes, array $rows, int $tid, string $type, string $target): ?array
{
    $visited = [];
    $current = $tid;

    while ($current > 0 && !isset($visited[$current])) {
        $visited[$current] = true;

        if (isset($rows[$current]) && is_array($rows[$current])) {
            foreach ($rows[$current] as $row) {
                if ($row['type'] === $type && $row['target'] === $target && !empty($row['active'])) {
                    $row['from_tid'] = $current;
                    return $row;
                }
            }
        }

        $current = isset($themes[$current]) ? (int)$themes[$current] : 0;
    }

    return null;
}

/**
 * Sammelt alle (type, target)-Paare, die entlang der Vererbungskette von $tid bis zur
 * Wurzel irgendwo definiert sind (unabhängig davon, ob die jeweilige Zeile aktiv ist oder
 * eine Variante hat) -- Grundlage dafür, welche Ziele bei einem Seitenaufruf überhaupt
 * geprüft werden müssen bzw. was im ACP für ein Theme angezeigt wird.
 */
function themevariants_collect_target_pairs(array $themes, array $rows, int $tid, ?string $type = null): array
{
    $pairs = [];
    $visited = [];
    $current = $tid;

    while ($current > 0 && !isset($visited[$current])) {
        $visited[$current] = true;

        if (isset($rows[$current]) && is_array($rows[$current])) {
            foreach ($rows[$current] as $row) {
                if ($type !== null && $row['type'] !== $type) {
                    continue;
                }
                $key = $row['type'] . '|' . $row['target'];
                if (!isset($pairs[$key])) {
                    $pairs[$key] = ['type' => $row['type'], 'target' => $row['target']];
                }
            }
        }

        $current = isset($themes[$current]) ? (int)$themes[$current] : 0;
    }

    return array_values($pairs);
}

/**
 * Öffentlicher Wrapper über den Datacache: effektive Zuordnung für ein Theme, inkl.
 * einmaligem Neu-Aufbau, falls die tid im Cache fehlt.
 */
function themevariants_get_effective(int $tid, string $type, string $target, string $script): ?array
{
    $data = themevariants_get_cache_ensuring_tid($tid);
    return themevariants_resolve($data['themes'], $data['rows'], $tid, $type, $target, $script);
}

// ---------------------------------------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------------------------------------

function themevariants_is_valid_template_title(string $value): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_\-]{1,120}$/', $value);
}

function themevariants_is_valid_slot_name(string $value): bool
{
    return (bool)preg_match('/^[a-z0-9_]{1,50}$/', $value);
}

function themevariants_is_valid_script_name(string $value): bool
{
    return (bool)preg_match('/^[a-z0-9_\-]+\.php$/', $value);
}

/**
 * Zerlegt eine Seitenliste (Zeilen und/oder Kommas als Trenner) in normalisierte
 * (lowercase, getrimmt), rohe Einzeleinträge -- ohne Validierung/Filterung. Für die
 * Validierung mit Fehlermeldung pro Eintrag siehe themevariants_is_valid_script_name().
 */
function themevariants_split_scripts(string $input): array
{
    $input = str_replace(["\r\n", "\r"], "\n", $input);
    $parts = preg_split('/[,\n]+/', $input);
    if ($parts === false) {
        return [];
    }

    $result = [];
    foreach ($parts as $part) {
        $part = strtolower(trim($part));
        if ($part !== '') {
            $result[] = $part;
        }
    }
    return $result;
}

/**
 * Normalisiert eine Seitenliste zu einer eindeutigen, validierten Liste von Skriptnamen.
 * Ungültige Einträge werden stillschweigend verworfen -- die ACP validiert vor dem
 * Speichern selbst per themevariants_is_valid_script_name() und zeigt dafür eine
 * Fehlermeldung; diese Funktion wird außerdem beim Cache-Aufbau verwendet, um die bereits
 * validiert gespeicherte `scripts`-Spalte zurück in ein Array zu dekodieren.
 */
function themevariants_parse_scripts(string $input): array
{
    $scripts = [];
    foreach (themevariants_split_scripts($input) as $part) {
        if (themevariants_is_valid_script_name($part) && !in_array($part, $scripts, true)) {
            $scripts[] = $part;
        }
    }
    return $scripts;
}

/**
 * Liefert die Template-Set-ID (sid) eines Themes, oder 0 wenn keine gesetzt/Theme unbekannt.
 * MyBB speichert das nicht als eigene Spalte in mybb_themes, sondern als 'templateset' im
 * serialisierten properties-Feld.
 */
function themevariants_theme_templateset(int $tid): int
{
    global $db;

    $theme_row = $db->fetch_array($db->simple_select('themes', 'properties', 'tid = ' . (int)$tid, ['limit' => 1]));
    if (!$theme_row) {
        return 0;
    }

    $properties = my_unserialize($theme_row['properties']);
    if (!is_array($properties) || !isset($properties['templateset'])) {
        return 0;
    }

    return (int)$properties['templateset'];
}

/**
 * Prüft, ob ein Template mit diesem Titel im Template-Set des Themes oder global (sid -2/-1)
 * existiert -- nur für die ACP-Warnanzeige, keine Laufzeit-Funktion.
 */
function themevariants_template_exists_for_theme(string $title, int $tid): bool
{
    global $db;

    if ($title === '') {
        return true;
    }

    $sid = themevariants_theme_templateset($tid);

    $sid_list = '-2, -1';
    if ($sid > 0) {
        $sid_list .= ', ' . $sid;
    }

    $row = $db->fetch_array($db->simple_select(
        'templates',
        'tid',
        "title = '" . $db->escape_string($title) . "' AND sid IN (" . $sid_list . ")",
        ['limit' => 1]
    ));

    return (bool)$row;
}

// ---------------------------------------------------------------------------------------
// Runtime: template overrides (global_intermediate, priority -10)
// ---------------------------------------------------------------------------------------

function themevariants_handle_overrides(): void
{
    global $mybb, $theme, $templates, $db;

    if (empty($mybb->settings['themevariants_enabled'])) {
        return;
    }
    if (empty($theme['tid'])) {
        return;
    }

    $tid = (int)$theme['tid'];
    $script = defined('THIS_SCRIPT') ? THIS_SCRIPT : '';

    $data = themevariants_get_cache_ensuring_tid($tid);
    $themes_map = $data['themes'];
    $rows_map = $data['rows'];

    $targets = themevariants_collect_target_pairs($themes_map, $rows_map, $tid, 'override');
    if (empty($targets)) {
        return;
    }

    $effective = [];
    foreach ($targets as $pair) {
        $result = themevariants_resolve($themes_map, $rows_map, $tid, 'override', $pair['target'], $script);
        if ($result !== null) {
            $effective[$pair['target']] = $result;
        }
    }

    if (empty($effective)) {
        return;
    }

    $missing = [];
    foreach ($effective as $result) {
        if (!isset($templates->cache[$result['variant']])) {
            $missing[] = $db->escape_string($result['variant']);
        }
    }
    if (!empty($missing)) {
        $templates->cache(implode(',', array_unique($missing)));
    }

    $debug = !empty($mybb->settings['themevariants_debug']) && !empty($mybb->usergroup['cancp']);

    foreach ($effective as $target => $result) {
        $variant = $result['variant'];
        if (!isset($templates->cache[$variant])) {
            continue; // variant template not found (in this theme's set nor globally) -- leave target untouched
        }

        $content = $templates->cache[$variant];
        if ($debug) {
            $content = '<!-- themevariants: override ' . htmlspecialchars_uni($target) . ' = '
                . htmlspecialchars_uni($variant) . ' (tid ' . $tid . ', from tid ' . (int)$result['from_tid'] . ') -->'
                . "\n" . $content . "\n"
                . '<!-- /themevariants: override ' . htmlspecialchars_uni($target) . ' -->';
        }

        $templates->cache[$target] = $content;
    }
}

// ---------------------------------------------------------------------------------------
// Runtime: slots (pre_output_page, priority 10)
// ---------------------------------------------------------------------------------------

function themevariants_handle_slots(string $contents): string
{
    global $mybb, $theme;

    if (strpos($contents, '<!--themevariants:') === false) {
        return $contents;
    }

    if (empty($mybb->settings['themevariants_enabled'])) {
        $result = preg_replace('#<!--themevariants:[a-z0-9_]+-->#', '', $contents);
        return $result === null ? $contents : $result;
    }

    $tid = !empty($theme['tid']) ? (int)$theme['tid'] : 0;
    $script = defined('THIS_SCRIPT') ? THIS_SCRIPT : '';
    $debug = !empty($mybb->settings['themevariants_debug']) && !empty($mybb->usergroup['cancp']);

    $resolved = []; // per-request cache: slot name => already rendered output

    $result = preg_replace_callback(
        '#<!--themevariants:([a-z0-9_]+)-->#',
        function (array $matches) use (&$resolved, $tid, $script, $debug): string {
            $slot = $matches[1];

            if (array_key_exists($slot, $resolved)) {
                return $resolved[$slot];
            }

            $output = themevariants_render_slot($tid, $slot, $script, $debug);
            $resolved[$slot] = $output;

            return $output;
        },
        $contents
    );

    return $result === null ? $contents : $result;
}

/**
 * Löst einen einzelnen Slot auf und rendert ihn (oder liefert '', wenn keine Variante
 * greift). Lädt das Varianten-Template bei Bedarf per $templates->cache() nach.
 */
function themevariants_render_slot(int $tid, string $slot, string $script, bool $debug): string
{
    global $templates, $db;

    if ($tid <= 0) {
        return '';
    }

    $result = themevariants_get_effective($tid, 'slot', $slot, $script);
    if ($result === null) {
        return '';
    }

    $variant = $result['variant'];
    if (!isset($templates->cache[$variant])) {
        $templates->cache($db->escape_string($variant));
    }
    if (!isset($templates->cache[$variant])) {
        if ($debug) {
            return '<!-- themevariants: slot ' . htmlspecialchars_uni($slot) . " -> variant '"
                . htmlspecialchars_uni($variant) . "' not found -->";
        }
        return '';
    }

    $rendered = themevariants_render($variant);

    if (!$debug) {
        return $rendered;
    }

    return '<!-- themevariants: slot ' . htmlspecialchars_uni($slot) . ' = ' . htmlspecialchars_uni($variant)
        . ' (tid ' . $tid . ', from tid ' . (int)$result['from_tid'] . ') -->'
        . $rendered
        . '<!-- /themevariants: slot ' . htmlspecialchars_uni($slot) . ' -->';
}

/**
 * Wertet ein Template im globalen Variablenkontext aus, damit Varianten dieselben
 * Variablen nutzen können wie Templates, die in global.php bzw. im jeweiligen Skript
 * evaluiert werden ($mybb, $lang, $theme, $welcomeblock, $pmslink, Plugin-Variablen ...).
 * EXTR_SKIP + Präfix auf den lokalen Variablen ist Absicht, siehe "What NOT to change".
 */
function themevariants_render(string $themevariants_title): string
{
    global $templates;
    extract($GLOBALS, EXTR_SKIP | EXTR_REFS);
    $themevariants_out = '';
    eval('$themevariants_out = "' . $templates->get($themevariants_title) . '";');
    return $themevariants_out;
}

// ---------------------------------------------------------------------------------------
// ACP integration: menu / action handler / permissions (Templates & Style)
// ---------------------------------------------------------------------------------------

function themevariants_admin_menu(array &$sub_menu): void
{
    global $lang;
    $lang->load('themevariants');

    $sub_menu[] = [
        'id'    => 'themevariants',
        'title' => $lang->themevariants,
        'link'  => 'index.php?module=style-themevariants',
    ];
}

function themevariants_admin_action_handler(array &$actions): void
{
    $actions['themevariants'] = ['active' => 'themevariants', 'file' => 'themevariants.php'];
}

function themevariants_admin_permissions(array &$admin_permissions): void
{
    global $lang;
    $lang->load('themevariants');

    $admin_permissions['themevariants'] = $lang->themevariants;
}

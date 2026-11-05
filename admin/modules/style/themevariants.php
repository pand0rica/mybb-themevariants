<?php
/**
 * Theme Variants -- ACP module (Templates & Style -> Theme Variants).
 *
 * @package themevariants
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$lang->load('themevariants');

// -----------------------------------------------------------------------------------
// Helpers (module-local, all prefixed themevariants_acp_*)
// -----------------------------------------------------------------------------------

/**
 * Gemeinsame Tab-Navigation aller Seiten dieses Moduls.
 */
function themevariants_acp_nav_tabs(string $active): void
{
    global $page, $lang;

    $tabs = [
        'overview' => [
            'title'       => $lang->themevariants_tab_overview,
            'link'        => 'index.php?module=style-themevariants',
            'description' => $lang->themevariants_tab_desc_overview,
        ],
        'add' => [
            'title'       => $lang->themevariants_tab_add,
            'link'        => 'index.php?module=style-themevariants&amp;action=add',
            'description' => $lang->themevariants_tab_desc_add,
        ],
    ];

    if ($active === 'edit') {
        $tabs['edit'] = [
            'title'       => $lang->themevariants_tab_edit,
            'link'        => 'index.php?module=style-themevariants&amp;action=edit',
            'description' => $lang->themevariants_tab_desc_edit,
        ];
    }

    $page->output_nav_tabs($tabs, $active);
}

/**
 * Liefert alle Themes als flache, nach der pid-Hierarchie sortierte Liste mit Tiefenangabe
 * (root zuerst, dann jeweils die Kinder). Schützt sich defensiv gegen Zyklen/Waisen.
 */
function themevariants_acp_theme_tree(): array
{
    global $db;

    $themes = [];
    $query = $db->simple_select('themes', 'tid, pid, name');
    while ($row = $db->fetch_array($query)) {
        $themes[(int)$row['tid']] = [
            'tid'  => (int)$row['tid'],
            'pid'  => (int)$row['pid'],
            'name' => (string)$row['name'],
        ];
    }

    $children = [];
    foreach ($themes as $tid => $row) {
        $children[$row['pid']][] = $tid;
    }

    $ordered = [];
    $visited = [];

    $visit = static function (int $tid, int $depth) use (&$visit, &$ordered, &$children, &$themes, &$visited): void {
        if (!isset($themes[$tid]) || isset($visited[$tid])) {
            return;
        }
        $visited[$tid] = true;
        $ordered[] = ['tid' => $tid, 'name' => $themes[$tid]['name'], 'depth' => $depth];

        if (!empty($children[$tid])) {
            $child_tids = $children[$tid];
            sort($child_tids);
            foreach ($child_tids as $child_tid) {
                $visit($child_tid, $depth + 1);
            }
        }
    };

    foreach ($themes as $tid => $row) {
        if ($row['pid'] === 0 || !isset($themes[$row['pid']])) {
            $visit($tid, 0);
        }
    }
    // Defensive fallback: themes not reached above (e.g. a cyclical pid chain) are still listed.
    foreach ($themes as $tid => $row) {
        if (!isset($visited[$tid])) {
            $visit($tid, 0);
        }
    }

    return $ordered;
}

function themevariants_acp_theme_name(int $tid): string
{
    global $db;

    $row = $db->fetch_array($db->simple_select('themes', 'name', 'tid = ' . (int)$tid, ['limit' => 1]));
    return $row ? (string)$row['name'] : ('tid ' . $tid);
}

/**
 * Select-Optionen für das Theme-Feld: alle Themes außer Master-Style (tid 1), eingerückt
 * nach Tiefe in der pid-Hierarchie.
 */
function themevariants_acp_theme_options_array(): array
{
    $options = [];
    foreach (themevariants_acp_theme_tree() as $entry) {
        if ($entry['tid'] === 1) {
            continue; // Master Style cannot have its own assignments
        }
        $options[$entry['tid']] = str_repeat('-- ', $entry['depth']) . $entry['name'] . ' (tid ' . $entry['tid'] . ')';
    }
    return $options;
}

/**
 * <datalist> mit den Template-Titeln des Theme-Sets von $tid sowie den globalen Sets
 * (sid -2/-1) -- reine Eingabehilfe, keine Validierung.
 */
function themevariants_acp_variant_datalist(int $tid): string
{
    global $db;

    $sid_list = '-2, -1';
    if ($tid > 0) {
        $sid = themevariants_theme_templateset($tid);
        if ($sid > 0) {
            $sid_list .= ', ' . $sid;
        }
    }

    $titles = [];
    $query = $db->simple_select('templates', 'DISTINCT title', 'sid IN (' . $sid_list . ')', ['order_by' => 'title']);
    while ($row = $db->fetch_array($query)) {
        $titles[] = (string)$row['title'];
    }

    $html = '<datalist id="themevariants_variant_list">';
    foreach ($titles as $title) {
        $html .= '<option value="' . htmlspecialchars_uni($title) . '"></option>';
    }
    $html .= '</datalist>';

    return $html;
}

function themevariants_acp_find_vid(int $tid, string $type, string $target): int
{
    global $db;

    $row = $db->fetch_array($db->simple_select(
        'themevariants',
        'vid',
        'tid = ' . (int)$tid
            . " AND type = '" . $db->escape_string($type) . "'"
            . " AND target = '" . $db->escape_string($target) . "'",
        ['limit' => 1]
    ));

    return $row ? (int)$row['vid'] : 0;
}

/**
 * Kleines eigenständiges Inline-Formular für Übersichts-Aktionen (Bearbeiten/Aktiv
 * umschalten/Löschen). GET für reine Navigation (Bearbeiten), POST + my_post_key für
 * schreibende Aktionen (umschalten/löschen).
 */

function themevariants_acp_action_form(int $vid, string $action, string $label, string $method, string $confirm_message = ''): string
{
    global $mybb;

    $html = '<form action="index.php?module=style-themevariants" method="' . htmlspecialchars_uni($method)
        . '" style="display:inline-block;margin-right:4px;">';
    $html .= '<input type="hidden" name="action" value="' . htmlspecialchars_uni($action) . '" />';
    $html .= '<input type="hidden" name="vid" value="' . (int)$vid . '" />';
    if ($method === 'post') {
        $html .= '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($mybb->post_code) . '" />';
    }
    $onclick = $confirm_message !== ''
        ? ' onclick="return confirm(' . htmlspecialchars_uni((string)json_encode($confirm_message)) . ');"'
        : '';
    $html .= '<input type="submit" class="button"' . $onclick . ' value="' . htmlspecialchars_uni($label) . '" />';
    $html .= '</form>';

    return $html;
}

/**
 * Rendert das Formular für "Zuordnung hinzufügen"/"Bearbeiten".
 */
function themevariants_acp_show_form(array $values, array $errors, int $vid): void
{
    global $page, $lang, $mybb;

    themevariants_acp_nav_tabs($vid > 0 ? 'edit' : 'add');

    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }

    $form = new Form('index.php?module=style-themevariants&amp;action=save', 'post');
    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);
    echo $form->generate_hidden_field('vid', (string)$vid);

    $form_container = new FormContainer($lang->themevariants_form_title);

    $form_container->output_row(
        $lang->themevariants_field_theme,
        $lang->themevariants_field_theme_desc,
        $form->generate_select_box('tid', themevariants_acp_theme_options_array(), (int)$values['tid'], ['id' => 'tid'])
    );

    // generate_radio_button($name, $value, $label, $options): Label und checked-Status
    // gehören in Parameter 3 bzw. $options['checked'].
    $type_html = $form->generate_radio_button('type', 'override', $lang->themevariants_type_override,
            ['id' => 'themevariants_type_override', 'checked' => ($values['type'] === 'override')])
        . '<br />'
        . $form->generate_radio_button('type', 'slot', $lang->themevariants_type_slot,
            ['id' => 'themevariants_type_slot', 'checked' => ($values['type'] === 'slot')]);
    $form_container->output_row($lang->themevariants_field_type, $lang->themevariants_field_type_desc, $type_html);

    $form_container->output_row(
        $lang->themevariants_field_target,
        $lang->themevariants_field_target_desc,
        $form->generate_text_box('target', $values['target'], ['id' => 'target', 'maxlength' => 120])
    );

    $variant_html = $form->generate_text_box('variant', $values['variant'], ['id' => 'variant', 'maxlength' => 120, 'list' => 'themevariants_variant_list'])
        . themevariants_acp_variant_datalist((int)$values['tid']);
    $form_container->output_row($lang->themevariants_field_variant, $lang->themevariants_field_variant_desc, $variant_html);

    $form_container->output_row(
        $lang->themevariants_field_scripts,
        $lang->themevariants_field_scripts_desc,
        $form->generate_text_area('scripts', $values['scripts'], ['id' => 'scripts'])
    );

    $form_container->output_row(
        $lang->themevariants_field_active,
        $lang->themevariants_field_active_desc,
        $form->generate_yes_no_radio('active', (int)$values['active'])
    );

    $form_container->end();

    $buttons = [$form->generate_submit_button($lang->themevariants_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();
}

/**
 * Rendert die Übersicht: Theme-Baum, pro Theme eine Tabelle der effektiven Zuordnungen.
 */
function themevariants_acp_show_overview(): void
{
    global $lang;

    themevariants_acp_nav_tabs('overview');

    $data = themevariants_get_cache();
    $themes_map = $data['themes'];
    $rows_map = $data['rows'];

    foreach (themevariants_acp_theme_tree() as $entry) {
        $tid = $entry['tid'];
        $heading = str_repeat('&raquo; ', $entry['depth']) . htmlspecialchars_uni($entry['name']) . ' (tid ' . $tid . ')';

        echo '<div class="themevariants_theme_block" style="margin-bottom:20px;">';
        echo '<h3>' . $heading . '</h3>';

        if ($tid === 1) {
            echo '<p class="smalltext">' . htmlspecialchars_uni($lang->themevariants_master_style_note) . '</p></div>';
            continue;
        }

        $pairs = themevariants_collect_target_pairs($themes_map, $rows_map, $tid);
        if (empty($pairs)) {
            echo '<p class="smalltext">' . htmlspecialchars_uni($lang->themevariants_no_assignments) . '</p></div>';
            continue;
        }

        usort($pairs, static function (array $a, array $b): int {
            return [$a['type'], $a['target']] <=> [$b['type'], $b['target']];
        });

        $table = new Table();
        $table->construct_header($lang->themevariants_col_type);
        $table->construct_header($lang->themevariants_col_target);
        $table->construct_header($lang->themevariants_col_variant);
        $table->construct_header($lang->themevariants_col_scripts);
        $table->construct_header($lang->themevariants_col_origin);
        $table->construct_header($lang->themevariants_col_status);
        $table->construct_header($lang->themevariants_col_controls, ['width' => '25%']);

        $has_rows = false;

        foreach ($pairs as $pair) {
            $row = themevariants_resolve_effective_row($themes_map, $rows_map, $tid, $pair['type'], $pair['target']);
            if ($row === null) {
                continue; // every candidate row for this pair is inactive along the whole chain
            }
            $has_rows = true;

            $own = ((int)$row['from_tid'] === $tid);

            $variant_display = $row['variant'] !== ''
                ? htmlspecialchars_uni($row['variant'])
                : '<em>--</em>';

            $scripts = (isset($row['scripts']) && is_array($row['scripts'])) ? $row['scripts'] : [];
            $scripts_display = empty($scripts)
                ? htmlspecialchars_uni($lang->themevariants_scripts_all)
                : htmlspecialchars_uni(implode(', ', $scripts));

            $origin_display = $own
                ? htmlspecialchars_uni($lang->themevariants_origin_own)
                : $lang->sprintf($lang->themevariants_origin_inherited, htmlspecialchars_uni(themevariants_acp_theme_name((int)$row['from_tid'])));

            $status_display = !empty($row['active'])
                ? htmlspecialchars_uni($lang->themevariants_status_active)
                : htmlspecialchars_uni($lang->themevariants_status_inactive);

            $warning = '';
            if ($row['variant'] !== '' && !themevariants_template_exists_for_theme($row['variant'], $tid)) {
                $warning = '<br /><span class="error">'
                    . $lang->sprintf($lang->themevariants_variant_missing_warning, htmlspecialchars_uni($row['variant']))
                    . '</span>';
            }

            $controls = '';
            if ($own) {
                $row_vid = themevariants_acp_find_vid($tid, $pair['type'], $pair['target']);
                if ($row_vid > 0) {
                    $controls .= themevariants_acp_action_form($row_vid, 'edit', $lang->themevariants_edit, 'get');
                    $controls .= themevariants_acp_action_form(
                        $row_vid,
                        'toggle',
                        !empty($row['active']) ? $lang->themevariants_deactivate : $lang->themevariants_activate,
                        'post'
                    );
                    $controls .= themevariants_acp_action_form(
                        $row_vid,
                        'delete',
                        $lang->themevariants_delete,
                        'post',
                        $lang->themevariants_confirm_delete
                    );
                }
            } else {
                $controls .= '<a href="index.php?module=style-themevariants&amp;action=add&amp;tid=' . $tid
                    . '&amp;type=' . htmlspecialchars_uni($pair['type'])
                    . '&amp;target=' . htmlspecialchars_uni(rawurlencode($pair['target']))
                    . '&amp;variant=' . htmlspecialchars_uni(rawurlencode($row['variant']))
                    . '">' . htmlspecialchars_uni($lang->themevariants_overwrite_for_theme) . '</a>';
            }

            $table->construct_cell($pair['type'] === 'override' ? $lang->themevariants_type_override : $lang->themevariants_type_slot);
            $table->construct_cell(htmlspecialchars_uni($pair['target']));
            $table->construct_cell($variant_display . $warning);
            $table->construct_cell($scripts_display);
            $table->construct_cell($origin_display);
            $table->construct_cell($status_display);
            $table->construct_cell($controls);
            $table->construct_row();
        }

        if ($has_rows) {
            $table->output($lang->themevariants_acp_title);
        } else {
            echo '<p class="smalltext">' . htmlspecialchars_uni($lang->themevariants_no_assignments) . '</p>';
        }

        echo '</div>';
    }
}

// -----------------------------------------------------------------------------------
// Dispatch
// -----------------------------------------------------------------------------------

$action = $mybb->get_input('action');

$page->add_breadcrumb_item($lang->themevariants_breadcrumb, 'index.php?module=style-themevariants');

switch ($action) {
    case 'add':
        $page->output_header($lang->themevariants_acp_title);

        $raw_type = $mybb->get_input('type');
        $values = [
            'tid'     => $mybb->get_input('tid', MyBB::INPUT_INT),
            'type'    => in_array($raw_type, ['override', 'slot'], true) ? $raw_type : 'override',
            'target'  => $mybb->get_input('target'),
            'variant' => $mybb->get_input('variant'),
            'scripts' => $mybb->get_input('scripts'),
            'active'  => 1,
        ];
        themevariants_acp_show_form($values, [], 0);

        $page->output_footer();
        break;

    case 'edit':
        $vid = $mybb->get_input('vid', MyBB::INPUT_INT);
        $row = themevariants_db_get($vid);
        if (!$row) {
            flash_message($lang->themevariants_error_invalid_vid, 'error');
            admin_redirect('index.php?module=style-themevariants');
        }

        $page->output_header($lang->themevariants_acp_title);

        $values = [
            'tid'     => (int)$row['tid'],
            'type'    => (string)$row['type'],
            'target'  => (string)$row['target'],
            'variant' => (string)$row['variant'],
            'scripts' => (string)$row['scripts'],
            'active'  => (int)$row['active'],
        ];
        themevariants_acp_show_form($values, [], $vid);

        $page->output_footer();
        break;

    case 'save':
        // Schreibend nur per POST – den my_post_key prüft admin/index.php selbst
        // (debugging.md #23); ein GET-Link läuft hier ins Leere.
        if ($mybb->request_method !== 'post') {
            admin_redirect('index.php?module=style-themevariants');
        }

        $vid = $mybb->get_input('vid', MyBB::INPUT_INT);
        $values = [
            'tid'     => $mybb->get_input('tid', MyBB::INPUT_INT),
            'type'    => $mybb->get_input('type'),
            'target'  => trim($mybb->get_input('target')),
            'variant' => trim($mybb->get_input('variant')),
            'scripts' => $mybb->get_input('scripts'),
            'active'  => (int)$mybb->get_input('active', MyBB::INPUT_INT),
        ];

        $errors = [];

        if (!in_array($values['type'], ['override', 'slot'], true)) {
            $errors[] = $lang->themevariants_error_invalid_type;
        }

        $theme_exists = false;
        if ($values['tid'] > 1) {
            $theme_row = $db->fetch_array($db->simple_select('themes', 'tid', 'tid = ' . (int)$values['tid'], ['limit' => 1]));
            $theme_exists = (bool)$theme_row;
        }
        if (!$theme_exists) {
            $errors[] = $lang->themevariants_error_invalid_theme;
        }

        if (empty($errors)) {
            $target_valid = $values['type'] === 'override'
                ? themevariants_is_valid_template_title($values['target'])
                : themevariants_is_valid_slot_name($values['target']);
            if (!$target_valid) {
                $errors[] = $lang->themevariants_error_invalid_target;
            }
        }

        if ($values['variant'] !== '' && !themevariants_is_valid_template_title($values['variant'])) {
            $errors[] = $lang->themevariants_error_invalid_variant;
        }

        $invalid_scripts = [];
        foreach (themevariants_split_scripts($values['scripts']) as $script_entry) {
            if (!themevariants_is_valid_script_name($script_entry)) {
                $invalid_scripts[] = $script_entry;
            }
        }
        if (!empty($invalid_scripts)) {
            $errors[] = $lang->sprintf($lang->themevariants_error_invalid_scripts, htmlspecialchars_uni(implode(', ', $invalid_scripts)));
        }

        if (empty($errors) && themevariants_db_exists($values['tid'], $values['type'], $values['target'], $vid)) {
            $errors[] = $lang->themevariants_error_duplicate;
        }

        if (!empty($errors)) {
            $page->output_header($lang->themevariants_acp_title);
            themevariants_acp_show_form($values, $errors, $vid);
            $page->output_footer();
            break;
        }

        $save_row = [
            'tid'     => $values['tid'],
            'type'    => $values['type'],
            'target'  => $values['target'],
            'variant' => $values['variant'],
            'scripts' => implode(',', themevariants_parse_scripts($values['scripts'])),
            'active'  => $values['active'],
        ];

        $existing = $vid > 0 ? themevariants_db_get($vid) : null;
        if ($existing) {
            themevariants_db_update($vid, $save_row);
            $log_vid = $vid;
        } else {
            $save_row['dateline'] = TIME_NOW;
            $log_vid = themevariants_db_insert($save_row);
        }

        themevariants_rebuild_cache();
        log_admin_action($log_vid, $values['target']);
        flash_message($lang->themevariants_saved, 'success');
        admin_redirect('index.php?module=style-themevariants');
        break;

    case 'delete':
        // Schreibend nur per POST – den my_post_key prüft admin/index.php selbst
        // (debugging.md #23); ein GET-Link läuft hier ins Leere.
        if ($mybb->request_method !== 'post') {
            admin_redirect('index.php?module=style-themevariants');
        }

        $vid = $mybb->get_input('vid', MyBB::INPUT_INT);
        $row = themevariants_db_get($vid);
        if ($row) {
            themevariants_db_delete($vid);
            themevariants_rebuild_cache();
            log_admin_action($vid, $row['target']);
            flash_message($lang->themevariants_deleted, 'success');
        } else {
            flash_message($lang->themevariants_error_invalid_vid, 'error');
        }
        admin_redirect('index.php?module=style-themevariants');
        break;

    case 'toggle':
        // Schreibend nur per POST – den my_post_key prüft admin/index.php selbst
        // (debugging.md #23); ein GET-Link läuft hier ins Leere.
        if ($mybb->request_method !== 'post') {
            admin_redirect('index.php?module=style-themevariants');
        }

        $vid = $mybb->get_input('vid', MyBB::INPUT_INT);
        $row = themevariants_db_get($vid);
        if ($row) {
            $row['active'] = $row['active'] ? 0 : 1;
            themevariants_db_update($vid, $row);
            themevariants_rebuild_cache();
            log_admin_action($vid, $row['target']);
            flash_message($lang->themevariants_toggled, 'success');
        } else {
            flash_message($lang->themevariants_error_invalid_vid, 'error');
        }
        admin_redirect('index.php?module=style-themevariants');
        break;

    default:
        $page->output_header($lang->themevariants_acp_title);
        themevariants_acp_show_overview();
        $page->output_footer();
        break;
}

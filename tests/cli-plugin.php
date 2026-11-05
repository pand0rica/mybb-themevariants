<?php
/**
 * Theme Variants -- CLI lifecycle helper.
 *
 * Installs/uninstalls the plugin without an ACP login and proves complete cleanup. Mirrors
 * what the ACP plugin manager does (calls the lifecycle functions AND maintains the
 * `plugins` datacache). Run from the MyBB root's `tests/` directory on the test forum:
 *
 *   php tests/cli-plugin.php install
 *   php tests/cli-plugin.php status
 *   php tests/cli-plugin.php uninstall
 *   php tests/cli-plugin.php status
 *   php tests/cli-plugin.php install
 *
 * See mybb-devkit/standards/testing.md §3 for the full workflow.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__)); // this file lives in httpdocs/tests/
require_once './inc/init.php';

// activate() needs admin functions (none used here directly, but kept consistent with the
// dev-kit template in case a later version adds theme/stylesheet handling).
if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}

require_once MYBB_ROOT . 'inc/plugins/themevariants.php';

function themevariants_cli_set_active(bool $active): void
{
    global $cache;

    $plugin_cache = $cache->read('plugins');
    if (!is_array($plugin_cache)) {
        $plugin_cache = [];
    }
    if (!isset($plugin_cache['active']) || !is_array($plugin_cache['active'])) {
        $plugin_cache['active'] = [];
    }

    if ($active) {
        $plugin_cache['active']['themevariants'] = 'themevariants';
    } else {
        unset($plugin_cache['active']['themevariants']);
    }

    $cache->update('plugins', $plugin_cache);
}

function themevariants_cli_status(): void
{
    global $db, $cache;

    $table_exists = $db->table_exists('themevariants');
    echo 'table themevariants:      ' . ($table_exists ? 'EXISTS' : 'MISSING') . "\n";

    if ($table_exists) {
        $row_count = (int)$db->fetch_field($db->simple_select('themevariants', 'COUNT(*) AS count'), 'count');
        echo 'rows in themevariants:    ' . $row_count . "\n";
    }

    $setting_count = (int)$db->fetch_field(
        $db->simple_select('settings', 'COUNT(*) AS count', "name LIKE 'themevariants_%'"),
        'count'
    );
    echo 'settings themevariants_*: ' . $setting_count . "\n";

    $group_count = (int)$db->fetch_field(
        $db->simple_select('settinggroups', 'COUNT(*) AS count', "name = 'themevariants'"),
        'count'
    );
    echo 'settinggroup themevariants: ' . $group_count . "\n";

    $cache_row = $cache->read('themevariants');
    echo 'datacache themevariants: ' . (is_array($cache_row) ? 'PRESENT' : 'MISSING') . "\n";

    $plugin_cache = $cache->read('plugins');
    $is_active = isset($plugin_cache['active']['themevariants']);
    echo 'plugin cache active:     ' . ($is_active ? 'YES' : 'NO') . "\n";
}

$cmd = isset($argv[1]) ? $argv[1] : 'status';

switch ($cmd) {
    case 'install':
        themevariants_install();
        themevariants_activate();
        themevariants_cli_set_active(true);
        echo "INSTALLED\n";
        themevariants_cli_status();
        break;

    case 'uninstall':
        themevariants_deactivate();
        themevariants_uninstall();
        themevariants_cli_set_active(false);
        echo "UNINSTALLED\n";
        themevariants_cli_status();
        break;

    case 'status':
    default:
        themevariants_cli_status();
        break;
}

<?php
/**
 * Theme Variants -- CLI self-test.
 *
 * Logic tests for the pure resolution/parsing functions (no DB needed for these), plus an
 * optional DB roundtrip for the CRUD helpers if the plugin is currently installed. Run from
 * the MyBB root's `tests/` directory on the test forum:
 *
 *   php tests/cli-selftest.php
 *
 * See mybb-devkit/standards/testing.md §4.
 */

if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
chdir(dirname(__DIR__));
require_once './inc/init.php';

require_once MYBB_ROOT . 'inc/plugins/themevariants.php';

$pass = 0;
$fail = 0;

function check(string $name, $expected, $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++;
        echo "  ok   {$name}\n";
    } else {
        $fail++;
        echo "  FAIL {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
}

// -----------------------------------------------------------------------------------
// themevariants_parse_scripts()
// -----------------------------------------------------------------------------------

echo "== themevariants_parse_scripts ==\n";
check('empty string -> no pages restriction', [], themevariants_parse_scripts(''));
check('single script', ['index.php'], themevariants_parse_scripts('index.php'));
check('comma separated + spaces', ['index.php', 'portal.php'], themevariants_parse_scripts('index.php, portal.php'));
check('newline separated', ['index.php', 'portal.php'], themevariants_parse_scripts("index.php\nportal.php"));
check('uppercase normalized to lowercase', ['index.php'], themevariants_parse_scripts('INDEX.PHP'));
check('invalid entries dropped', ['index.php'], themevariants_parse_scripts('index.php, not-a-script'));
check('duplicates removed', ['index.php'], themevariants_parse_scripts('index.php,index.php'));

// -----------------------------------------------------------------------------------
// themevariants_resolve() -- covers every case required by IMPLEMENTATION_PLAN.md Schritt 4
// -----------------------------------------------------------------------------------

echo "\n== themevariants_resolve ==\n";

$themes = [
    1  => 0,
    38 => 1,
    39 => 38,
    40 => 38,
];

// own active row
$rows = [
    38 => [
        ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_master', 'scripts' => [], 'active' => 1],
    ],
];
$r = themevariants_resolve($themes, $rows, 38, 'override', 'footer', 'index.php');
check('own active row wins', 'tv_footer_master', $r['variant'] ?? null);
check('own active row: from_tid', 38, $r['from_tid'] ?? null);

// inherited over 2 levels (40 -> 38 -> 1)
$r = themevariants_resolve($themes, $rows, 40, 'override', 'footer', 'index.php');
check('inherited over 2 levels: variant', 'tv_footer_master', $r['variant'] ?? null);
check('inherited over 2 levels: from_tid', 38, $r['from_tid'] ?? null);

// child overrides parent
$rows_override = $rows;
$rows_override[40] = [
    ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_always', 'scripts' => [], 'active' => 1],
];
$r = themevariants_resolve($themes, $rows_override, 40, 'override', 'footer', 'index.php');
check('child overrides parent: variant', 'tv_footer_always', $r['variant'] ?? null);
check('child overrides parent: from_tid', 40, $r['from_tid'] ?? null);

// child with empty variant stops inheritance
$rows_stop = $rows;
$rows_stop[40] = [
    ['type' => 'override', 'target' => 'footer', 'variant' => '', 'scripts' => [], 'active' => 1],
];
check('child empty variant stops inheritance', null, themevariants_resolve($themes, $rows_stop, 40, 'override', 'footer', 'index.php'));

// inactive child row -> ancestor's row applies
$rows_inactive = $rows;
$rows_inactive[40] = [
    ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_always', 'scripts' => [], 'active' => 0],
];
$r = themevariants_resolve($themes, $rows_inactive, 40, 'override', 'footer', 'index.php');
check('inactive child row -> ancestor wins: variant', 'tv_footer_master', $r['variant'] ?? null);
check('inactive child row -> ancestor wins: from_tid', 38, $r['from_tid'] ?? null);

// page list matches
$rows_scripts_match = [
    38 => [
        ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_master', 'scripts' => ['index.php'], 'active' => 1],
    ],
];
check(
    'page list matches -> variant applies',
    'tv_footer_master',
    themevariants_resolve($themes, $rows_scripts_match, 38, 'override', 'footer', 'index.php')['variant'] ?? null
);

// page list does not match -> null, no fallback to ancestor (even if ancestor has a row)
$rows_scripts_no_match = [
    1  => [
        ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_root', 'scripts' => [], 'active' => 1],
    ],
    38 => [
        ['type' => 'override', 'target' => 'footer', 'variant' => 'tv_footer_master', 'scripts' => ['portal.php'], 'active' => 1],
    ],
];
check(
    'page list mismatch -> null, no ancestor fallback',
    null,
    themevariants_resolve($themes, $rows_scripts_no_match, 38, 'override', 'footer', 'index.php')
);

// unknown tid
check('unknown tid, no data -> null', null, themevariants_resolve($themes, $rows, 999999, 'override', 'footer', 'index.php'));

// cycle in pid chain must terminate
$themes_cycle = [5 => 3, 3 => 5];
$start = microtime(true);
$cycle_result = themevariants_resolve($themes_cycle, [], 5, 'override', 'footer', 'index.php');
$elapsed = microtime(true) - $start;
check('cycle in pid chain -> null', null, $cycle_result);
check('cycle in pid chain -> terminates quickly', true, $elapsed < 1.0);

// -----------------------------------------------------------------------------------
// Optional DB roundtrip (only if the plugin is currently installed on this forum)
// -----------------------------------------------------------------------------------

if (function_exists('themevariants_is_installed') && themevariants_is_installed()) {
    echo "\n== DB roundtrip (themevariants_db_*) ==\n";

    global $db;

    $marker = 'SELFTEST_' . TIME_NOW;
    $vid = themevariants_db_insert([
        'tid'      => 1,
        'type'     => 'slot',
        'target'   => $marker,
        'variant'  => 'tv_selftest',
        'scripts'  => 'index.php',
        'active'   => 1,
        'dateline' => TIME_NOW,
    ]);
    check('insert returns a vid', true, $vid > 0);

    $found = themevariants_db_get($vid);
    check('db_get finds the inserted row', $marker, $found['target'] ?? null);

    check('db_exists true for the same tid/type/target', true, themevariants_db_exists(1, 'slot', $marker));
    check('db_exists false for a different target', false, themevariants_db_exists(1, 'slot', $marker . '_nope'));

    themevariants_db_update($vid, [
        'tid'     => 1,
        'type'    => 'slot',
        'target'  => $marker,
        'variant' => 'tv_selftest_updated',
        'scripts' => '',
        'active'  => 0,
    ]);
    $updated = themevariants_db_get($vid);
    check('db_update persists changes', 'tv_selftest_updated', $updated['variant'] ?? null);

    themevariants_db_delete($vid);
    check('db_delete removes the row', null, themevariants_db_get($vid));
} else {
    echo "\n(DB roundtrip skipped -- plugin not installed on this forum right now)\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);

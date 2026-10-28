<?php
// Run the actual importer and REST callback against repository CSVs, without WordPress or network.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
$root = dirname(__DIR__);
$cache = array();
function add_shortcode(...$args) {}
function add_action(...$args) {}
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function remove_accents($value) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
class WP_Error {}
class WP_REST_Response {
    public $data;
    public function __construct($data, $status) { $this->data = $data; }
}
class Test_Request {
    private $query;
    public function __construct($query) { $this->query = $query; }
    public function get_param($name) { return $this->query; }
}
function wp_remote_get($url, $options) {
    global $root;
    if (strpos($url, 'https://api.github.com/') === 0) {
        $paths = array();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/data'));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'csv') {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($paths);
        $tree = array_map(function($path) { return array('type' => 'blob', 'path' => $path); }, $paths);
        return array('status' => 200, 'body' => json_encode(array('tree' => $tree, 'truncated' => false)));
    }
    $prefix = 'https://raw.githubusercontent.com/yh8jb22jds-svg/PassportFitSearch/main/';
    if (strpos($url, $prefix) !== 0) { throw new Exception('Unexpected HTTP request'); }
    $path = $root . '/' . rawurldecode(substr($url, strlen($prefix)));
    return array('status' => 200, 'body' => file_get_contents($path));
}
require $root . '/wordpress-plugin/passport-fit-search/passport-fit-search.php';
$checks = 0;
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) { throw new Exception($message); }
}
function lookup($query) {
    $response = PH_Passport_Fit_Search::search(new Test_Request($query));
    check($response instanceof WP_REST_Response, 'Search failed: ' . $query);
    $results = array();
    foreach ($response->data['results'] as $row) { $results[$row['firearm_id']] = $row; }
    return $results;
}
function expect_ids($query, $ids) {
    $results = lookup($query);
    $actual = array_keys($results);
    sort($actual); sort($ids);
    check($actual === $ids, 'Wrong identities for ' . $query . ': ' . json_encode($actual));
    return $results;
}
check(PH_Passport_Fit_Search::refresh_dataset(), 'Importer failed');
$family_ids = array('GLOCK-000001', 'GLOCK-000011', 'GLOCK-000021', 'GLOCK-000026',
    'GLOCK-000066', 'GLOCK-000067', 'GLOCK-000068', 'GLOCK-000069', 'GLOCK-000070',
    'GLOCK-000071', 'GLOCK-000072', 'GLOCK-000073');
foreach (array('Glock 9', 'Glock 9mm', '9mm Glock') as $query) {
    $rows = expect_ids($query, $family_ids);
    $verified = array('GLOCK-000001' => 'A4', 'GLOCK-000011' => 'A3',
        'GLOCK-000021' => 'A2.5', 'GLOCK-000073' => 'A4');
    foreach ($rows as $id => $row) {
        check($row['fit'] === ($verified[$id] ?? ''), 'Wrong fit: ' . $id);
        check($row['confirmed'] === isset($verified[$id]), 'Wrong confirmation: ' . $id);
    }
}
foreach (array('Glock 19', 'G19') as $query) {
    expect_ids($query, array('GLOCK-000011', 'GLOCK-000073'));
}
foreach (array('Glock 19 Compact', 'G19 Compact') as $query) {
    $rows = expect_ids($query, array('GLOCK-000011'));
    check($rows['GLOCK-000011']['fit'] === 'A3', 'Compact fit lost');
}
$rows = expect_ids('Glock 19 Full Barrel', array('GLOCK-000073'));
check($rows['GLOCK-000073']['fit'] === 'A4', 'Full Barrel fit lost');
foreach (array('DE', 'de', 'D.E.') as $query) {
    $rows = expect_ids($query, array('BOND-000001', 'BOND-000002', 'MR-000001'));
    foreach ($rows as $row) { check(!$row['confirmed'] && $row['fit'] === '', 'Category fit guessed'); }
}
expect_ids('Derringer', array('BOND-000001', 'BOND-000002'));
expect_ids('Desert Eagle', array('MR-000001'));
expect_ids('Defender', array('COLT-000008'));
expect_ids('DE 50', array());
expect_ids('Glock 19X', array('GLOCK-000067'));
foreach (array('Glock 19 Gen 3' => 'GLOCK-000014', 'Glock 19 Gen 5' => 'GLOCK-000018',
    'G19 Gen5' => 'GLOCK-000018', 'Glock 19 MOS' => 'GLOCK-000019',
    'G19 MOS' => 'GLOCK-000019', 'Glock 17 Gen 5' => 'GLOCK-000009',
    'Glock 17 MOS' => 'GLOCK-000010') as $query => $id) {
    $rows = expect_ids($query, array($id));
    check($rows[$id]['fit'] === '' && !$rows[$id]['confirmed'], 'Variant inherited generic approval');
}
expect_ids('Glock 19 Gen 6', array());
expect_ids('Glock 19 V', array());
// Neither master rows lacking verification nor proposed review fits may become confirmed.
$dataset = get_transient(PH_Passport_Fit_Search::CACHE_KEY);
foreach (array('GLOCK-000014', 'BOND-000001', 'MR-000001', 'GLOCK-000067') as $id) {
    check($dataset['firearms'][$id]['passport_fit'] === '', 'Unverified fit imported: ' . $id);
}
// Every explicit alias ID must remain unique after remapping.
$ids = array();
foreach (glob($root . '/data/*/*search_alias_master*.csv') as $path) {
    $handle = fopen($path, 'r'); $headers = fgetcsv($handle);
    while (($values = fgetcsv($handle)) !== false) {
        if (count($values) !== count($headers)) { continue; }
        $row = array_combine($headers, $values);
        check(!isset($ids[$row['alias_id']]), 'Duplicate alias ID: ' . $row['alias_id']);
        $ids[$row['alias_id']] = true;
    }
    fclose($handle);
}
echo 'Passed ' . $checks . " checks against the actual PHP importer and search.\n";

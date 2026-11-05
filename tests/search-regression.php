<?php
// Run the actual importer and REST callback against repository CSVs, without WordPress or network.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
$root = dirname(__DIR__);
$cache = array();
function add_shortcode(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
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
    private $suggest;
    public function __construct($query, $suggest = false) { $this->query = $query; $this->suggest = $suggest; }
    public function get_param($name) { return $name === 'q' ? $this->query : ($this->suggest ? '1' : '0'); }
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
    $prefix = 'https://raw.githubusercontent.com/yh8jb22jds-svg/PassportFitSearch/' . PH_Passport_Fit_Search::BRANCH . '/';
    if (strpos($url, $prefix) !== 0) { throw new Exception('Unexpected HTTP request'); }
    $path = $root . '/' . rawurldecode(substr($url, strlen($prefix)));
    return array('status' => 200, 'body' => file_get_contents($path));
}
require getenv('PH_PLUGIN_FILE') ?: $root . '/wordpress-plugin/passport-fit-search/passport-fit-search.php';
$checks = 0;
function check($condition, $message) {
    $GLOBALS['checks']++;
    if (!$condition) { throw new Exception($message); }
}
function lookup($query, $suggest = false) {
    $response = PH_Passport_Fit_Search::search(new Test_Request($query, $suggest));
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
foreach (array('Glock 19', 'glock 19', 'GLOCK 19', 'GlOcK 19') as $case_query) {
    $case_results = lookup($case_query);
    check(array_keys($case_results) === array_keys(lookup('Glock 19')), 'Case-insensitive search failed: ' . $case_query);
}
$g34 = lookup('Glock 34');
check(isset($g34['GLOCK-000026']) && $g34['GLOCK-000026']['fit'] === 'A5' && $g34['GLOCK-000026']['suggested'] && !$g34['GLOCK-000026']['confirmed'], 'Glock 34 must suggest A5 from 5.31 inch barrel');
check($g34['GLOCK-000026']['size_source'] === 'barrel_estimate', 'Calculated fit must be labeled an estimate');
check($g34['GLOCK-000026']['fits'] === array('A5'), 'Single fit must have one option');
$g26 = lookup('Glock 43X');
check($g26['GLOCK-000066']['fits'] === array('A2', 'A2.5'), 'Alternative fits must be separate options');
$g17 = lookup('Glock 17');
check($g17['GLOCK-000001']['size_source'] === 'confirmed' && $g17['GLOCK-000001']['fit'] === 'A4', 'Confirmed fit must take priority');
$unknown = lookup('Desert Eagle');
check($unknown['MR-000001']['size_source'] === 'needs_data', 'Unknown fit must not be mislabeled as verified');
$canik_standard = lookup('Canik METE SFT');
check(isset($canik_standard['CANIK-000013']) && $canik_standard['CANIK-000013']['barrel_length'] === '4.47' && $canik_standard['CANIK-000013']['fit'] === 'A4', 'Manufacturer-confirmed Canik METE SFT 4.47 inch barrel must recommend A4');
$canik_pro = lookup('Canik METE SFT Pro Threaded Barrel');
check(isset($canik_pro['CANIK-000021']) && $canik_pro['CANIK-000021']['barrel_length'] === '5.0' && $canik_pro['CANIK-000021']['fit'] === 'A5', 'Canik METE SFT Pro 5 inch threaded barrel must recommend A5');
$canik_dataset = get_transient(PH_Passport_Fit_Search::CACHE_KEY);
check(isset($canik_dataset['firearms']['CANIK-000013'], $canik_dataset['firearms']['CANIK-000021']), 'Standard and threaded METE SFT variants must be separate records');
check($canik_dataset['firearms']['CANIK-000013']['barrel_length'] === '4.47', 'Standard barrel must remain 4.47 inches');
check($canik_dataset['firearms']['CANIK-000021']['barrel_length'] === '5.0', 'Threaded barrel must remain 5 inches');
check(stripos($canik_dataset['firearms']['CANIK-000013']['display_name'], 'threaded') === false, 'Standard model mislabeled threaded');
check(stripos($canik_dataset['firearms']['CANIK-000021']['display_name'], 'threaded') !== false, 'Threaded variant label missing');
$canik = lookup('Canik METE SFT ONE');
check(isset($canik['CANIK-000023']) && $canik['CANIK-000023']['fit'] === 'A4' && $canik['CANIK-000023']['suggested'] && !$canik['CANIK-000023']['confirmed'], 'Canik METE SFT ONE must recommend provisional A4 from 4.07 inch barrel');
$wmp = lookup('Walther WMP');
check(isset($wmp['WALTHER-000031']) && $wmp['WALTHER-000031']['fit'] === 'A4' && $wmp['WALTHER-000031']['confirmed'], 'Walther WMP approved A4 fit: ' . json_encode($wmp));
check(count($wmp) === 1 && $wmp['WALTHER-000031']['barrel_length'] === '4.5' && $wmp['WALTHER-000031']['size_source'] === 'confirmed', 'Walther WMP must be one confirmed 4.5 inch record');
$macro_comp = expect_ids('SIG Sauer P365 X-Macro Comp', array('SIG-000061'));
check($macro_comp['SIG-000061']['barrel_length'] === '3.1' && !$macro_comp['SIG-000061']['confirmed'], 'SIG P365 XMACRO Comp must have one unverified 3.1 inch record');
$boomer = lookup('Charter Arms Boomer');
check(isset($boomer['USER-CHARTER-BOOMER44']) && $boomer['USER-CHARTER-BOOMER44']['fit'] === 'R2' && $boomer['USER-CHARTER-BOOMER44']['confirmed'], 'Charter Boomer approved R2 fit');
// Unconfirmed fit is not the same as unavailable: manufacturer-length estimates must be shown.
$macro = lookup('SIG Sauer P365 X-Macro Comp');
check(isset($macro['SIG-000061']) && $macro['SIG-000061']['fit'] !== '' && $macro['SIG-000061']['suggested'] && !$macro['SIG-000061']['confirmed'], 'P365 XMACRO Comp must show a provisional size');
$fuse = lookup('SIG Sauer P365-FUSE');
check(isset($fuse['USER-SIG-P365-FUSE']) && $fuse['USER-SIG-P365-FUSE']['fit'] !== '' && $fuse['USER-SIG-P365-FUSE']['suggested'], 'P365 FUSE must show a barrel-based recommendation');
$p365 = lookup('SIG Sauer P365-FUSE');
check(isset($p365['USER-SIG-P365-FUSE']) && $p365['USER-SIG-P365-FUSE']['barrel_length'] === '4.3', 'P365 FUSE barrel must be 4.3 inches');
$family_ids = array('GLOCK-000001', 'GLOCK-000011', 'GLOCK-000021', 'GLOCK-000026',
    'GLOCK-000066', 'GLOCK-000067', 'GLOCK-000068', 'GLOCK-000069', 'GLOCK-000070',
    'GLOCK-000071', 'GLOCK-000072', 'GLOCK-000073');
foreach (array('Glock 9', 'Glock 9mm', '9mm Glock') as $query) {
    $rows = expect_ids($query, $family_ids);
    $verified = array('GLOCK-000001' => 'A4', 'GLOCK-000011' => 'A3',
        'GLOCK-000021' => 'A2.5', 'GLOCK-000073' => 'A4');
    foreach ($rows as $id => $row) {
        $expected = $verified[$id] ?? ($id === 'GLOCK-000026' ? 'A5' : (in_array($id, array('GLOCK-000066', 'GLOCK-000068'), true) ? 'A2 / A2.5' : (in_array($id, array('GLOCK-000067', 'GLOCK-000071'), true) ? 'A4' : (in_array($id, array('GLOCK-000069', 'GLOCK-000070', 'GLOCK-000072'), true) ? 'A4' : ''))));
        check($row['fit'] === $expected, 'Wrong fit: ' . $id);
        check($row['confirmed'] === isset($verified[$id]), 'Wrong confirmation: ' . $id);
    }
}
// Legacy published size-chart coverage: preserve searchable entries before
// replacing the long model lists with a concise sizing guide.
// Glock 19 appears in both A3 and A4 on the old chart; explicit variants resolve it.
foreach (array(
    'Glock 17' => 'GLOCK-000001',
    'Glock 19 Compact' => 'GLOCK-000011',
    'Glock 19 Full Barrel' => 'GLOCK-000073',
    'Canik METE SFT' => 'CANIK-000013',
    'Walther WMP' => 'WALTHER-000031',
) as $chart_name => $chart_id) {
    $chart_results = lookup($chart_name);
    check(isset($chart_results[$chart_id]), 'Size-chart/model coverage missing: ' . $chart_name);
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
    check(!$rows[$id]['confirmed'], 'Variant inherited generic approval');
}
expect_ids('Glock 19 Gen 6', array());
expect_ids('Glock 19 V', array());
// Suggested fits may be present, but unverified master rows never become confirmed.
$dataset = get_transient(PH_Passport_Fit_Search::CACHE_KEY);
foreach (array('GLOCK-000014', 'BOND-000001', 'MR-000001', 'GLOCK-000067') as $id) {
    check($dataset['firearms'][$id]['passport_fit'] === '', 'Unverified fit imported: ' . $id);
}
// Suggest while typing without discarding generation choices; submit remains exact.
$suggestions = lookup('Glock 19', true);
foreach (array('GLOCK-000011', 'GLOCK-000073', 'GLOCK-000012', 'GLOCK-000014',
    'GLOCK-000017', 'GLOCK-000018', 'GLOCK-000019', 'GLOCK-000074') as $id) {
    check(isset($suggestions[$id]), 'Missing generation suggestion: ' . $id);
}
foreach (array('Glock 19 Gen 5 MOS', 'glock 19 gen5 mos', 'G19 Gen5 MOS', 'Glock 19 MOS Gen 5') as $query) {
    $rows = expect_ids($query, array('GLOCK-000074'));
    check(!$rows['GLOCK-000074']['confirmed'] && $rows['GLOCK-000074']['fit'] === '', 'Combined variant inherited fit');
}
foreach (array('Smith & Wesson Equalizer' => 'SW-000051', 'S&W Equalizer' => 'SW-000051',
    'Smith & Wesson M&P9 M2.0 Full-Size Optics-Ready' => 'SW-000052',
    'Walther PDP 5 inch' => 'WALTHER-000002', 'Canik METE SFT One' => 'CANIK-000023',
    'Smith & Wesson Model 686 Plus 4 inch' => 'SW-000053', 'S&W 686+ 4 inch' => 'SW-000053',
    'CZ P-10 C' => 'CZ-000033', 'SIG Sauer P365 X-Macro Comp' => 'SIG-000061') as $query => $id) {
    $rows = expect_ids($query, array($id));
    check(!$rows[$id]['confirmed'], 'Article listing treated as confirmed fit');
}
// Every explicit alias ID must remain unique after remapping.
$ids = array();
foreach (glob($root . '/data/*/*search_alias_master*.csv') as $path) {
    $handle = fopen($path, 'r'); $headers = fgetcsv($handle);
    while (($values = fgetcsv($handle)) !== false) {
        if (count($values) !== count($headers)) { continue; }
        $row = array_combine($headers, $values);
        if (!isset($row['alias_id']) || trim($row['alias_id']) === '') { continue; }
        check(!isset($ids[$row['alias_id']]), 'Duplicate alias ID: ' . $row['alias_id']);
        $ids[$row['alias_id']] = true;
    }
    fclose($handle);
}
echo 'Passed ' . $checks . " checks against the actual PHP importer and search.\n";

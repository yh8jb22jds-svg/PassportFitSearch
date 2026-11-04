<?php
// Audit every imported firearm for a customer-visible size recommendation.
// Run: php tests/size-coverage-audit.php
ob_start();
require __DIR__ . '/search-regression.php';
ob_end_clean();
$dataset = get_transient(PH_Passport_Fit_Search::CACHE_KEY);
$counts = array('confirmed' => 0, 'barrel_estimate' => 0, 'needs_data' => 0);
$missing = array();
$threaded_records = array();
$missing_by_manufacturer = array();
$missing_reasons = array('no_barrel_length' => 0, 'no_action_type' => 0, 'length_present_rule_gap' => 0);
foreach ($dataset['firearms'] as $id => $row) {
    if (preg_match('/\\b(threaded|threaded barrel|suppressor ready|suppressor-ready)\\b/i', ($row['display_name'] ?? '') . ' ' . ($row['variant'] ?? ''))) {
        $threaded_records[] = array($id, $row['display_name'], $row['barrel_length'] ?? '', $row['recommended_size'] ?? '');
    }
    $source = $row['size_source'];
    $counts[$source] = ($counts[$source] ?? 0) + 1;
    if ($row['recommended_size'] === '') {
        $length = trim((string) ($row['barrel_length'] ?? ''));
        $type = trim((string) ($row['action_type'] ?? ''));
        $reason = !preg_match('/^[0-9]+(?:\\.[0-9]+)?$/', $length) ? 'no_barrel_length' :
            ($type === '' ? 'no_action_type' : 'length_present_rule_gap');
        $missing_reasons[$reason]++;
        $maker = explode('-', $id)[0];
        if (!isset($missing_by_manufacturer[$maker])) { $missing_by_manufacturer[$maker] = array('no_barrel_length' => 0, 'no_action_type' => 0, 'length_present_rule_gap' => 0); }
        $missing_by_manufacturer[$maker][$reason]++;
        $missing[] = array(
            $id, $row['display_name'], $row['barrel_length'] ?? '',
            $row['action_type'] ?? '', $row['caliber'] ?? ''
        );
    }
}
echo 'Imported firearms: ' . count($dataset['firearms']) . PHP_EOL;
echo 'Explicit threaded/suppressor-ready variants: ' . count($threaded_records) . PHP_EOL;
foreach ($threaded_records as $item) { echo 'THREADED_VARIANT: ' . implode(' | ', $item) . PHP_EOL; }
foreach ($counts as $source => $count) { echo $source . ': ' . $count . PHP_EOL; }
echo 'Needs manufacturer barrel/type or chart lookup: ' . count($missing) . PHP_EOL;
foreach ($missing_reasons as $reason => $count) { echo $reason . ': ' . $count . PHP_EOL; }
echo 'Missing records by manufacturer (descending):' . PHP_EOL;
uasort($missing_by_manufacturer, function($a, $b) { return array_sum($b) <=> array_sum($a); });
foreach ($missing_by_manufacturer as $maker => $reasons) { echo $maker . ': ' . array_sum($reasons) . ' (type ' . $reasons['no_action_type'] . ', rule ' . $reasons['length_present_rule_gap'] . ', barrel ' . $reasons['no_barrel_length'] . ')' . PHP_EOL; }
// Small, actionable diagnostic sample in CI logs; the full unresolved list
// remains in the downloadable CSV artifact.
echo 'Unresolved examples by manufacturer (first 8 each):' . PHP_EOL;
$examples_by_maker = array();
foreach ($missing as $row) {
    $maker = explode('-', $row[0])[0];
    if (!isset($examples_by_maker[$maker])) { $examples_by_maker[$maker] = 0; }
    if ($examples_by_maker[$maker] >= 8) { continue; }
    echo 'UNRESOLVED_SAMPLE: ' . implode(' | ', $row) . PHP_EOL;
    $examples_by_maker[$maker]++;
}
echo 'firearm_id,gun_name,barrel_length,action_type,caliber' . PHP_EOL;
foreach ($missing as $row) {
    echo implode(',', array_map(function($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; }, $row)) . PHP_EOL;
}

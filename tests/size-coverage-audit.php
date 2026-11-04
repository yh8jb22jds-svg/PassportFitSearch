<?php
// Audit every imported firearm for a customer-visible size recommendation.
// Run: php tests/size-coverage-audit.php
ob_start();
require __DIR__ . '/search-regression.php';
ob_end_clean();
$dataset = get_transient(PH_Passport_Fit_Search::CACHE_KEY);
$counts = array('confirmed' => 0, 'barrel_estimate' => 0, 'needs_data' => 0);
$missing = array();
foreach ($dataset['firearms'] as $id => $row) {
    $source = $row['size_source'];
    $counts[$source] = ($counts[$source] ?? 0) + 1;
    if ($row['recommended_size'] === '') {
        $missing[] = array(
            $id, $row['display_name'], $row['barrel_length'] ?? '',
            $row['action_type'] ?? '', $row['caliber'] ?? ''
        );
    }
}
echo 'Imported firearms: ' . count($dataset['firearms']) . PHP_EOL;
foreach ($counts as $source => $count) { echo $source . ': ' . $count . PHP_EOL; }
echo 'Needs manufacturer barrel/type or chart lookup: ' . count($missing) . PHP_EOL;
echo 'firearm_id,gun_name,barrel_length,action_type,caliber' . PHP_EOL;
foreach ($missing as $row) {
    echo implode(',', array_map(function($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; }, $row)) . PHP_EOL;
}

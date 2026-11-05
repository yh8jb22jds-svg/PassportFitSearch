<?php
/**
 * Plugin Name: PHolsters Passport Fit Search
 * Description: Searches the PassportFitSearch GitHub database for exact firearm models and chart-confirmed Passport fits.
 * Version: 1.0.4
 * Author: PHolsters
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) { exit; }

final class PH_Passport_Fit_Search {
    const OWNER = 'yh8jb22jds-svg';
    const REPO = 'PassportFitSearch';
    const BRANCH = 'main';
    const CACHE_KEY = 'ph_passport_fit_dataset_v5';
    const CACHE_TTL = DAY_IN_SECONDS;

    public static function boot() {
        add_shortcode('passport_fit_search', array(__CLASS__, 'shortcode'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('ph_passport_fit_refresh', array(__CLASS__, 'refresh_dataset'));
        add_action('init', array(__CLASS__, 'schedule_refresh'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_homepage_search'));
    }

    public static function enqueue_homepage_search() {
        if (is_front_page()) {
            wp_enqueue_script('ph-passport-homepage-search', plugins_url('homepage-fit-search.js', __FILE__), array(), '1.0.3', true);
        }
    }

    public static function activate() {
        self::refresh_dataset();
        self::schedule_refresh();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('ph_passport_fit_refresh');
    }

    public static function schedule_refresh() {
        if (!wp_next_scheduled('ph_passport_fit_refresh')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ph_passport_fit_refresh');
        }
    }

    private static function http_get($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'redirection' => 3,
            'headers' => array(
                'User-Agent' => 'PHolsters-Passport-Fit-Search/1.0',
                'Accept' => 'application/vnd.github+json'
            )
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        return wp_remote_retrieve_body($response);
    }

    public static function refresh_dataset() {
        $tree_url = 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/git/trees/' . self::BRANCH . '?recursive=1';
        $tree_json = self::http_get($tree_url);
        $tree = $tree_json ? json_decode($tree_json, true) : null;
        if (!is_array($tree) || empty($tree['tree']) || !empty($tree['truncated'])) {
            return false;
        }

        $master_paths = array();
        $review_paths = array();
        $alias_paths = array();
        foreach ($tree['tree'] as $item) {
            if (empty($item['type']) || $item['type'] !== 'blob' || empty($item['path'])) { continue; }
            $path = $item['path'];
            if (preg_match('~^data/.+\/\d{4}-\d{2}-\d{2}_firearm_master_(?!.*_(?:fit_review|sources)_)[^/]+\.csv$~i', $path)) {
                $master_paths[] = $path;
            } elseif (preg_match('~^data/.+\/\d{4}-\d{2}-\d{2}_firearm_master_.+_fit_review_[^/]+\.csv$~i', $path)) {
                $review_paths[] = $path;
            } elseif (preg_match('~^data/.+\/\d{4}-\d{2}-\d{2}_search_alias_master_[^/]+\.csv$~i', $path)) {
                $alias_paths[] = $path;
            }
        }

        // Import curated customer corrections last, independent of GitHub tree order.
        // A later confirmed correction must not be replaced by an older unverified row.
        usort($master_paths, function($a, $b) {
            $a_curated = strpos($a, 'data/customer-confirmed/') === 0 ? 1 : 0;
            $b_curated = strpos($b, 'data/customer-confirmed/') === 0 ? 1 : 0;
            return $a_curated === $b_curated ? strcmp($a, $b) : ($a_curated <=> $b_curated);
        });
        $firearms = array();
        foreach ($master_paths as $path) {
            $csv = self::http_get('https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/' . self::BRANCH . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
            if (!$csv) { continue; }
            foreach (self::parse_csv($csv) as $row) {
                if (empty($row['firearm_id']) || empty($row['display_name'])) { continue; }
                // Keep a chart/customer-confirmed record when another source repeats its ID.
                $incoming_verified = isset($row['fit_verified']) && in_array(strtolower($row['fit_verified']), array('1', 'yes', 'true', 'chart'), true) && !empty($row['passport_fit']);
                if (isset($firearms[$row['firearm_id']]) && !empty($firearms[$row['firearm_id']]['passport_fit']) && !$incoming_verified) { continue; }
                $firearms[$row['firearm_id']] = array(
                    'firearm_id' => $row['firearm_id'],
                    'display_name' => $row['display_name'],
                    'model' => isset($row['model']) ? $row['model'] : '',
                    'variant' => isset($row['variant']) ? $row['variant'] : '',
                    'caliber' => isset($row['caliber']) ? $row['caliber'] : '',
                    'barrel_length' => isset($row['barrel_length']) ? $row['barrel_length'] : '',
                    'action_type' => isset($row['action_type']) ? $row['action_type'] : '',
                    'passport_fit' => (isset($row['fit_verified']) && in_array(strtolower($row['fit_verified']), array('1', 'yes', 'true', 'chart'), true)) ? (isset($row['passport_fit']) ? $row['passport_fit'] : '') : ''
                );
            }
        }

        $aliases = array();
        foreach ($firearms as $id => $firearm) {
            $aliases[] = array('firearm_id' => $id, 'alias_text' => $firearm['display_name'], 'normalized_alias' => $firearm['display_name'], 'priority' => 1);
        }
        foreach ($review_paths as $path) {
            $csv = self::http_get('https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/' . self::BRANCH . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
            if (!$csv) { continue; }
            foreach (self::parse_csv($csv) as $row) {
                if (empty($row['firearm_id']) || empty($row['display_name'])) { continue; }
                $id = $row['firearm_id'];
                if (!isset($firearms[$id])) {
                    $firearms[$id] = array(
                        'firearm_id' => $id,
                        'display_name' => $row['display_name'],
                        'model' => '',
                        'variant' => '',
                        'caliber' => '',
                        'barrel_length' => isset($row['barrel_length']) ? $row['barrel_length'] : '',
                        'passport_fit' => ''
                    );
                }
                $name = $row['display_name'];
                $aliases[] = array('firearm_id' => $id, 'alias_text' => $name, 'normalized_alias' => $name, 'priority' => 1);
                if (!empty($row['aliases'])) {
                    $aliases[] = array('firearm_id' => $id, 'alias_text' => $row['aliases'], 'normalized_alias' => $row['aliases'], 'priority' => 1);
                }
            }
        }

        foreach ($alias_paths as $path) {
            $csv = self::http_get('https://raw.githubusercontent.com/' . self::OWNER . '/' . self::REPO . '/' . self::BRANCH . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
            if (!$csv) { continue; }
            foreach (self::parse_csv($csv) as $row) {
                if (empty($row['firearm_id']) || empty($row['alias_text']) || (isset($row['active']) && $row['active'] === '0')) { continue; }
                if (!isset($firearms[$row['firearm_id']]) && (!isset($row['alias_type']) || stripos($row['alias_type'], 'Family Search') === false)) {
                    $firearms[$row['firearm_id']] = array(
                        'firearm_id' => $row['firearm_id'],
                        'display_name' => $row['alias_text'],
                        'model' => '',
                        'variant' => '',
                        'caliber' => '',
                        'barrel_length' => '',
                        'passport_fit' => ''
                    );
                }
                $aliases[] = array(
                    'firearm_id' => $row['firearm_id'],
                    'alias_text' => $row['alias_text'],
                    'normalized_alias' => isset($row['normalized_alias']) ? $row['normalized_alias'] : $row['alias_text'],
                    'priority' => isset($row['priority']) ? (int) $row['priority'] : 2
                );
            }
        }

        if (!$firearms) { return false; }
        // One master record per firearm: name, aliases, barrel length and size.
        // Keep the provenance of chart-confirmed fits distinct from estimates.
        $aliases_by_id = array();
        foreach ($aliases as $alias) {
            $id = $alias['firearm_id'];
            if (!isset($firearms[$id])) { continue; }
            if (!isset($aliases_by_id[$id])) { $aliases_by_id[$id] = array(); }
            $term = trim((string) $alias['alias_text']);
            if ($term !== '' && !in_array($term, $aliases_by_id[$id], true)) {
                $aliases_by_id[$id][] = $term;
            }
        }
        // Canik TP9, METE, SFx, TTI Combat and Prime are semi-automatic pistol
        // families. Apply only to these explicitly named families, not all Canik products.
        foreach ($firearms as $id => &$firearm) {
            if (strpos($id, 'CANIK-') === 0 &&
                empty($firearm['action_type']) &&
                preg_match('/^Canik (?:TP9|METE|SFx|TTI Combat|Prime)\\b/i', $firearm['display_name'])) {
                $firearm['action_type'] = 'Semi-Auto';
            }
        }
        unset($firearm);
        // Classify only explicitly identifiable semi-auto pistol model families.
        // Brand-wide assumptions are unsafe: several manufacturers also make revolvers.
        $semi_auto_families = array(
            'GLOCK-' => '/^Glock (?:[0-9]{2,3})(?:[A-Z]{1,2})?\\b/i',
            'CZ-' => '/^CZ (?:P-10|P-07|P-09|P-01|P-06|P-10|75|Shadow|SP-01|TS 2)\\b/i',
            'FN-' => '/^FN (?:309|502|503|509|510|545|Five-seveN|FNS|FNX|FNP|Reflex)\\b/i',
            'RUGER-' => '/^Ruger (?:SR22|SR9|Security-9|MAX-9|LC9|LCP|EC9|Mark IV|American Pistol)\\b/i',
            'KELTEC-' => '/^Kel-Tec (?:PR-|P-|P3AT|PF-|PMR-?|CP33|P17|P11|P15|P32|P50|PLR-)/i',
            'KIMBER-' => '/^Kimber (?:Next Generation 1911|2K11|1911|Micro|R7|KDS9|Rapide|Custom|Ultra|Pro(?: Carry)?|Desert Warrior|Warrior|Eclipse|Aegis|Stainless|TLE|Covert|Sapphire|Solo|Grand Raptor|Gold Combat|Gold Match|Target|Super Match|Tactical (?:Pro|Ultra|Entry)|Combat Carry|Compact(?: CDP| Stainless| Pro Stainless)?|Polymer|Royal|Centennial)\\b/i',
            'RIA-' => '/^Rock Island Armory (?:GI|Rock Ultra|Rock Standard|Rock|TAC|Tactical|M1911|1911(?:A1)?|STK|Baby Rock|RIA 5|XT(?:22)?|Pro Match|TCM|P10 TCM|Matchmaster|Ultra Compact Tactical|CS Model|\\.22 TCM Midsize|Midsize Double Stack)\\b/i',
            'WALTHER-' => '/^Walther (?:PDP|PPQ|PPS|P99|P22|PK380|WMP|Q5|Q4|CCP|PD380|PPK|P38)\\b/i',
            'SPRINGFIELD-' => '/^Springfield (?:Echelon|1911|Hellcat|XD|XDM|XD-M|XDS|XD-S|SA-35|EMP|Prodigy)\\b/i',
            'HK-' => '/^H&K (?:USP[0-9]*|VP9|VP40|P30|P2000|HK45|Mark 23|P7(?:M[0-9]+)?|CC9|SFP9|P9S|PSP)\\b/i',
            'COLT-' => '/^Colt (?:M1911|M1991A1|M1911A1|Government Model|Commander|Defender|Gold Cup|Delta Elite|Mustang|New Agent|Officer.s Model|Pocket Nine|Double Eagle|Pony|XSE|Concealed Carry Officer.s|Rail Gun)\\b/i',
            'TAURUS-' => '/^Taurus (?:G2C|G2S|G3C|G3X|G3XL|G2|G3|GX4|TX22|TH9|TH40|PT92|PT99|PT111|PT140|PT145|PT1911|1911|Spectrum|Curve)\\b/i',
            'SW-' => '/^(?:Smith & Wesson|S&W) (?:M&P|Shield|Equalizer|CSX|Bodyguard|SD9|SD40|SW9|SW40|Sigma|Victory|SW1911|1911)\\b/i',
            'SIG-' => '/^SIG Sauer (?:P365|P320|P226|P229|P220|P227|P238|P938|P210|P239|P250|P2022|P224)\\b/i',
        );
        foreach ($firearms as $id => &$firearm) {
            if (!empty($firearm['action_type'])) { continue; }
            foreach ($semi_auto_families as $prefix => $pattern) {
                if (strpos($id, $prefix) === 0 && preg_match($pattern, $firearm['display_name'])) {
                    $firearm['action_type'] = 'Semi-Auto';
                    break;
                }
            }
        }
        unset($firearm);
        foreach ($firearms as $id => &$firearm) {
            $confirmed = trim($firearm['passport_fit']) !== '';
            $firearm['aliases'] = isset($aliases_by_id[$id]) ? $aliases_by_id[$id] : array();
            $firearm['recommended_size'] = $confirmed
                ? trim($firearm['passport_fit']) : self::suggested_fit($firearm);
            $firearm['size_source'] = $confirmed ? 'confirmed' :
                ($firearm['recommended_size'] !== '' ? 'barrel_estimate' : 'needs_data');
        }
        unset($firearm);
        $dataset = array('firearms' => $firearms, 'aliases' => $aliases, 'refreshed_at' => gmdate('c'));
        set_transient(self::CACHE_KEY, $dataset, self::CACHE_TTL);
        return true;
    }

    private static function parse_csv($csv) {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        if (count($lines) < 2) { return array(); }
        $headers = str_getcsv(array_shift($lines));
        $records = array();
        foreach ($lines as $line) {
            if (trim($line) === '') { continue; }
            $values = str_getcsv($line);
            $row = array();
            foreach ($headers as $index => $header) {
                $row[trim($header)] = isset($values[$index]) ? trim($values[$index]) : '';
            }
            $records[] = $row;
        }
        return $records;
    }

    public static function register_routes() {
        register_rest_route('passport-fit/v1', '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'suggest' => array('default' => '0', 'sanitize_callback' => 'sanitize_text_field')
            )
        ));
    }

    private static function normalize($text) {
        $text = strtolower(remove_accents((string) $text));
        $text = preg_replace('/smith\s*(?:&|and)?\s*wesson|s\s*&\s*w/', 'sw', $text);
        $text = preg_replace('/sig[ -]*sauer/', 'sig', $text);
        $text = str_replace('+', ' plus ', $text);
        return preg_replace('/[^a-z0-9]+/', '', $text);
    }

    // Suggested sizes are separate from confirmed chart fits. Never overwrite approved fits.
    // Only add rules backed by Passport Holsters' published size definitions.
    private static function suggested_fit($firearm) {
        if (trim($firearm['passport_fit']) !== '') { return ''; }
        $length = isset($firearm['barrel_length']) ? trim((string) $firearm['barrel_length']) : '';
        $type = isset($firearm['action_type']) ? strtolower(trim((string) $firearm['action_type'])) : '';
        if (!preg_match('/^[0-9]+(?:\\.[0-9]+)?$/', $length)) { return ''; }
        $inches = (float) $length;
        // Chart-derived suggestions; confirmed model fits always take precedence.
        // At overlapping endpoints, prefer the larger size. A2/A2.5 and
        // revolver frame-specific exceptions need additional specifications.
        if (in_array($type, array('semi-auto', 'semi-automatic', 'semi automatic'), true)) {
            if ($inches >= 5.0 && $inches <= 6.0) { return 'A5'; }
            if ($inches >= 4.0 && $inches <= 4.5) { return 'A4'; }
            if ($inches >= 3.5 && $inches < 4.0) { return 'A3'; }
            if ($inches >= 3.0 && $inches < 3.5) {
                // Compact/subcompact distinction cannot be inferred from length alone.
                return 'A2 / A2.5';
            }
            if ($inches >= 2.0 && $inches < 3.0) {
                // A1 is specifically for small .22/.25 autos.
                $caliber = isset($firearm['caliber']) ? strtolower(trim((string) $firearm['caliber'])) : '';
                if (preg_match('/(?:^|[^0-9])\\.?(?:22|25)(?:[^0-9]|$)/', $caliber)) { return 'A1'; }
            }
        }
        if (in_array($type, array('revolver', 'double-action revolver', 'single-action revolver'), true)) {
            if ($inches >= 7.75 && $inches <= 8.25) { return 'R8'; }
            if ($inches >= 5.75 && $inches <= 6.25) { return 'R6'; }
            if ($inches >= 3.75 && $inches <= 4.25) { return 'R4'; }
            // Without frame/shot-count data, show both plausible families rather than guess.
            if ($inches >= 2.5 && $inches <= 3.0) { return 'R2.5 / R3'; }
            if ($inches >= 2.0 && $inches < 2.5) { return 'R2 / R3'; }
        }
        // Derringers and Desert Eagles are classified by model, not barrel alone.
        // Never guess these special sizes from an unrelated firearm's length.
        return '';
    }

    public static function search($request) {
        $query = trim((string) $request->get_param('q'));
        $needle = self::normalize($query);
        $suggest = ((string) $request->get_param('suggest') === '1');
        if (strlen($needle) < 2) {
            return new WP_REST_Response(array('results' => array()), 200);
        }

        $dataset = get_transient(self::CACHE_KEY);
        if (!is_array($dataset) || empty($dataset['firearms'])) {
            if (!self::refresh_dataset()) {
                return new WP_Error('fit_data_unavailable', 'The fit database is temporarily unavailable. Please use the size chart.', array('status' => 503));
            }
            $dataset = get_transient(self::CACHE_KEY);
        }

        $matches = array();
        if ($needle === 'de') {
            // DE is commonly entered for both Derringer and Desert Eagle.
            // Return choices instead of assigning either category's holster size.
            $choice_ids = array();
            foreach ($dataset['firearms'] as $firearm) {
                if (preg_match('/\b(?:derringer|desert\s+eagle)\b/i', $firearm['display_name'])) {
                    $choice_ids[$firearm['firearm_id']] = true;
                }
            }
            // Alias-only category names also identify choices (for example Rowdy Derringer).
            foreach ($dataset['aliases'] as $alias) {
                if (preg_match('/\b(?:derringer|desert\s+eagle)\b/i', $alias['alias_text'])) {
                    $choice_ids[$alias['firearm_id']] = true;
                }
            }
            foreach ($choice_ids as $id => $_) {
                if (!isset($dataset['firearms'][$id])) { continue; }
                $matches[$id] = array(
                    'firearm' => $dataset['firearms'][$id], 'priority' => 1, 'exact' => false
                );
            }
        } else {
            foreach ($dataset['aliases'] as $alias) {
                if (!isset($dataset['firearms'][$alias['firearm_id']])) { continue; }
                $term = self::normalize($alias['normalized_alias']);
                // Never treat a short alias (for example DE) as a prefix of an unrelated query.
                // Two-character searches must be exact; longer searches may find longer aliases.
                if ($term === '' || ($term !== $needle && (strlen($needle) < 3 || strpos($term, $needle) === false))) { continue; }
                $id = $alias['firearm_id'];
                $exact = ($term === $needle);
                if (!isset($matches[$id]) ||
                    ($exact && !$matches[$id]['exact']) ||
                    ($exact === $matches[$id]['exact'] && $alias['priority'] < $matches[$id]['priority'])) {
                    $matches[$id] = array('firearm' => $dataset['firearms'][$id], 'priority' => $alias['priority'], 'exact' => $exact);
                }
            }

            // An exact model alias takes precedence over models whose longer names
            // merely contain the query (Glock 19 versus Glock 19X, for example).
            $has_exact = false;
            foreach ($matches as $match) {
                if ($match['exact']) { $has_exact = true; break; }
            }
            if ($has_exact && !$suggest) {
                $matches = array_filter($matches, function($match) { return $match['exact']; });
            }

            if (!$matches) {
                foreach ($dataset['firearms'] as $firearm) {
                    $haystack = self::normalize($firearm['display_name'] . ' ' . $firearm['model'] . ' ' . $firearm['variant']);
                    if (strpos($haystack, $needle) !== false) {
                        $matches[$firearm['firearm_id']] = array('firearm' => $firearm, 'priority' => 2, 'exact' => false);
                    }
                }
            }

        }

        uasort($matches, function($a, $b) {
            if ($a['exact'] !== $b['exact']) { return $a['exact'] ? -1 : 1; }
            return $a['priority'] <=> $b['priority'];
        });

        $results = array();
        foreach (array_slice($matches, 0, 25, true) as $match) {
            $firearm = $match['firearm'];
            $confirmed_fit = trim($firearm['passport_fit']);
            $fit = isset($firearm['recommended_size']) ? $firearm['recommended_size'] : $confirmed_fit;
            $variant = trim($firearm['variant']);
            $results[] = array(
                'firearm_id' => $firearm['firearm_id'],
                'name' => $firearm['display_name'],
                'variant' => $variant,
                'fit' => $fit,
                'fits' => $fit === '' ? array() : array_map('trim', explode('/', $fit)),
                'confirmed' => ($confirmed_fit !== ''),
                'suggested' => ($fit !== '' && $confirmed_fit === ''),
                'barrel_length' => isset($firearm['barrel_length']) ? $firearm['barrel_length'] : '',
                'size_source' => isset($firearm['size_source']) ? $firearm['size_source'] : 'needs_data'
            );
        }
        return new WP_REST_Response(array('results' => $results, 'refreshed_at' => $dataset['refreshed_at']), 200);
    }

    public static function shortcode() {
        $endpoint = esc_url(rest_url('passport-fit/v1/search'));
        $chart = esc_url('https://pholsters.com/size-chart/');
        ob_start(); ?>
        <div class="ph-fit-search" data-endpoint="<?php echo $endpoint; ?>" data-chart="<?php echo $chart; ?>">
            <label for="ph-fit-query">What gun do you carry?</label>
            <div class="ph-fit-search__controls">
                <input id="ph-fit-query" type="search" autocomplete="off" placeholder="Type manufacturer and model" />
                <button type="button">Find my fit</button>
            </div>
            <div class="ph-fit-search__results" aria-live="polite"></div>
        </div>
        <style>
            .ph-fit-search{max-width:680px;margin:0 auto}
            .ph-fit-search__controls{display:flex;gap:8px}
            .ph-fit-search input{flex:1;min-width:0;padding:12px 14px;border:1px solid #777;border-radius:6px;font-size:16px}
            .ph-fit-search button{padding:12px 18px;border:0;border-radius:6px;background:#d71920;color:#fff;font-weight:700;cursor:pointer}
            .ph-fit-search__results{margin-top:12px}
            .ph-fit-search__item{padding:12px;margin:8px 0;border:1px solid #ddd;border-radius:6px}
            .ph-fit-search__title{font-weight:700}
            .ph-fit-search__fit{margin-top:4px}
            .ph-fit-search__muted{color:#555}
            @media(max-width:520px){.ph-fit-search__controls{display:block}.ph-fit-search__controls button{width:100%;margin-top:8px}}
        </style>
        <script>
        (function(){
            var root=document.currentScript.previousElementSibling.previousElementSibling;
            if(!root || !root.classList.contains('ph-fit-search')) return;
            var input=root.querySelector('input'), button=root.querySelector('button'), output=root.querySelector('.ph-fit-search__results');
            var timer;
            function esc(s){return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
            function lookup(suggest){
                var q=input.value.trim();
                if(q.length<2){output.innerHTML='';return}
                output.textContent='Searching listed models…';
                fetch(root.dataset.endpoint+'?q='+encodeURIComponent(q)+(suggest===true?'&suggest=1':''),{credentials:'same-origin'})
                    .then(function(r){return r.json().then(function(data){if(!r.ok) throw new Error('unavailable');return data})})
                    .then(function(data){
                        var results=data.results||[];
                        if(!results.length){output.innerHTML='<p>No exact model match found. <a href="'+root.dataset.chart+'">See the size chart</a>.</p>';return}
                        output.innerHTML=results.map(function(item){
                            var fit=item.confirmed
                                ? '<div class="ph-fit-search__fit"><strong>Passport fit: '+esc(item.fit)+'</strong>'+(item.variant?' <span class="ph-fit-search__muted">('+esc(item.variant)+')</span>':'')+'</div>'
                                : item.suggested
                                ? '<div class="ph-fit-search__fit"><strong>'+(item.fits && item.fits.length>1?'Based on the information provided, we recommend considering '+(item.fit==='A2 / A2.5'?'A2 — Compact or A2.5 — Subcompact':esc(item.fits.join(' or ')))+'. Choose based on your firearm configuration.':'Based on the information provided, we recommend Passport Holsters size '+esc(item.fit)+'.')+'</strong> <span class="ph-fit-search__muted">Recommendation is for the standard factory configuration of the firearm named above. Aftermarket accessories or modifications may affect fit.</span></div>'
                                : '<div class="ph-fit-search__fit ph-fit-search__muted">No barrel-length recommendation is available for this model. <a href="'+root.dataset.chart+'">Check the size chart</a>.</div>';
                            return '<div class="ph-fit-search__item"><div class="ph-fit-search__title">'+esc(item.name)+'</div>'+fit+'</div>';
                        }).join('');
                    })
                    .catch(function(){output.innerHTML='Fit search is temporarily unavailable. <a href="'+root.dataset.chart+'">Use the size chart</a>.'});
            }
            input.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){lookup(true)},250)});
            input.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();lookup(false)}});
            button.addEventListener('click',function(){lookup(false)});
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

PH_Passport_Fit_Search::boot();
register_activation_hook(__FILE__, array('PH_Passport_Fit_Search', 'activate'));
register_deactivation_hook(__FILE__, array('PH_Passport_Fit_Search', 'deactivate'));


/** Route customer comment alerts to the Passport customer-service inbox. */
final class PH_Passport_Comment_Notifications {
    const EMAIL = 'passportholsters@gmail.com';
    public static function boot() {
        add_filter('comment_notification_recipients', array(__CLASS__, 'recipients'), 100, 2);
        add_filter('comment_moderation_recipients', array(__CLASS__, 'recipients'), 100, 2);
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }
    public static function recipients($recipients, $comment_id) {
        return array(self::EMAIL);
    }
    public static function notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'options-discussion') { return; }
        echo '<div class="notice notice-info"><p><strong>Passport comment notifications:</strong> New-comment and moderation alerts are routed to <strong>' . esc_html(self::EMAIL) . '</strong>.</p></div>';
    }
}
PH_Passport_Comment_Notifications::boot();

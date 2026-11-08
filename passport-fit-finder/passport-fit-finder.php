<?php
/**
 * Plugin Name: Passport Holsters Fit Finder
 * Description: Manufacturer and model lookup powered by the Passport Holsters Genitron database.
 * Version: 1.0.0
 * Author: Passport Holsters
 * License: GPL-2.0-or-later
 * Text Domain: passport-fit-finder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Passport_Holsters_Fit_Finder {
	const VERSION     = '1.0.0';
	const DATA_URL    = 'https://raw.githubusercontent.com/yh8jb22jds-svg/PassportFitSearch/main/genitron_database.csv';
	const CACHE_KEY   = 'ph_fit_finder_complete_records_v1';
	const CACHE_TTL   = 15 * MINUTE_IN_SECONDS;
	const REST_NS     = 'passport-fit/v1';

	public static function init() {
		add_shortcode( 'passport_fit_finder', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/manufacturers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_manufacturers' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NS,
			'/models',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_models' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'manufacturer' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/fit',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_fit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'manufacturer' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'model'        => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/** Fetch and parse the latest database, caching complete, customer-displayable rows briefly. */
	private static function get_records() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			self::DATA_URL,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'user-agent'  => 'Passport-Holsters-Fit-Finder/' . self::VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Keep the last good copy if GitHub is temporarily unreachable.
			$stale = get_option( 'ph_fit_finder_last_good_records', array() );
			return is_array( $stale ) ? $stale : array();
		}

		$body  = wp_remote_retrieve_body( $response );
		$lines = preg_split( '/\r\n|\n|\r/', trim( $body ) );
		if ( empty( $lines ) || count( $lines ) < 2 ) {
			return array();
		}

		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( 'trim', $header );
		$expected = array( 'Manufacturer', 'Model', 'Barrel Length (inches)', 'Passport Fit Size' );
		if ( $header !== $expected ) {
			return array();
		}

		$records = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$row = str_getcsv( $line );
			// All four database columns are required. Partial rows never reach customers.
			if ( 4 !== count( $row ) ) {
				continue;
			}
			$row = array_map( 'trim', $row );
			if ( in_array( '', $row, true ) ) {
				continue;
			}
			if ( ! is_numeric( $row[2] ) || ! preg_match( '/^(A[1-6](?:\.5)?(?:\/A[1-6](?:\.5)?)?|R[0-9]+(?:\.5)?(?:\/R[0-9]+(?:\.5)?)?|D|DE)$/i', $row[3] ) ) {
				continue;
			}
			// Manual-review placeholders are not fit recommendations.
			if ( 0 === stripos( $row[3], 'Manual fit review' ) ) {
				continue;
			}
			$records[] = array(
				'manufacturer' => $row[0],
				'model'        => $row[1],
				'barrel'       => $row[2],
				'size'         => $row[3],
			);
		}

		set_transient( self::CACHE_KEY, $records, self::CACHE_TTL );
		update_option( 'ph_fit_finder_last_good_records', $records, false );
		return $records;
	}

	public static function get_manufacturers() {
		$manufacturers = array_values( array_unique( wp_list_pluck( self::get_records(), 'manufacturer' ) ) );
		natcasesort( $manufacturers );
		return rest_ensure_response( array_values( $manufacturers ) );
	}

	public static function get_models( WP_REST_Request $request ) {
		$manufacturer = (string) $request->get_param( 'manufacturer' );
		$models = array();
		foreach ( self::get_records() as $record ) {
			if ( 0 === strcasecmp( $manufacturer, $record['manufacturer'] ) ) {
				$models[] = $record['model'];
			}
		}
		$models = array_values( array_unique( $models ) );
		natcasesort( $models );
		return rest_ensure_response( array_values( $models ) );
	}

	public static function get_fit( WP_REST_Request $request ) {
		$manufacturer = (string) $request->get_param( 'manufacturer' );
		$model        = (string) $request->get_param( 'model' );
		$matches      = array();
		foreach ( self::get_records() as $record ) {
			if ( 0 === strcasecmp( $manufacturer, $record['manufacturer'] ) && 0 === strcasecmp( $model, $record['model'] ) ) {
				$matches[] = array( 'barrel' => $record['barrel'], 'size' => $record['size'] );
			}
		}
		return rest_ensure_response( $matches );
	}

	public static function render_shortcode() {
		$api = esc_url( rest_url( self::REST_NS ) );
		ob_start();
		?>
		<div class="ph-fit-finder" data-api="<?php echo esc_attr( $api ); ?>">
			<label>Manufacturer
				<select class="ph-fit-manufacturer"><option value="">Choose a manufacturer</option></select>
			</label>
			<label>Model
				<select class="ph-fit-model" disabled><option value="">Choose a model</option></select>
			</label>
			<div class="ph-fit-results" aria-live="polite" role="status"></div>
		</div>
		<style>
		.ph-fit-finder{max-width:620px;display:grid;gap:14px}.ph-fit-finder label{display:grid;gap:6px;font-weight:600}.ph-fit-finder select{min-height:46px;width:100%;padding:8px 10px}.ph-fit-results{font-size:1.12em;font-weight:700;color:#7b1e24;line-height:1.55}.ph-fit-results p{margin:.25em 0}.ph-fit-finder [hidden]{display:none}
		</style>
		<script>
		(function(){
			const root=document.currentScript.previousElementSibling.previousElementSibling;
			if(!root||!root.classList.contains('ph-fit-finder'))return;
			const api=root.dataset.api,manufacturer=root.querySelector('.ph-fit-manufacturer'),model=root.querySelector('.ph-fit-model'),results=root.querySelector('.ph-fit-results');
			const safe=(s)=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
			const load=async(url)=>{const r=await fetch(url,{credentials:'same-origin'});if(!r.ok)throw new Error('Unable to load fit data');return r.json()};
			function option(select,placeholder,values){select.innerHTML='<option value="">'+placeholder+'</option>'+values.map(v=>'<option value="'+safe(v)+'">'+safe(v)+'</option>').join('');}
			load(api+'/manufacturers').then(values=>option(manufacturer,'Choose a manufacturer',values)).catch(()=>{results.textContent='Fit information is temporarily unavailable. Please try again shortly.';});
			manufacturer.addEventListener('change',async()=>{results.textContent='';option(model,'Loading models…',[]);model.disabled=true;if(!manufacturer.value){option(model,'Choose a model',[]);return;}try{const values=await load(api+'/models?manufacturer='+encodeURIComponent(manufacturer.value));option(model,values.length?'Choose a model':'No complete models available',values);model.disabled=!values.length;}catch(e){option(model,'Unable to load models',[]);}});
			model.addEventListener('change',async()=>{results.textContent='';if(!manufacturer.value||!model.value)return;try{const fits=await load(api+'/fit?manufacturer='+encodeURIComponent(manufacturer.value)+'&model='+encodeURIComponent(model.value));if(!fits.length)return;results.innerHTML=fits.map(f=>renderFit(f)).join('');}catch(e){results.textContent='Fit information is temporarily unavailable. Please try again shortly.';}});
			function renderFit(f){const len=safe(f.barrel);if(f.size==='A2.5/A3'){return '<p>Your barrel length is '+len+' inches.</p><p>This model is available in 2 holster sizes:</p><p>Compact — Holster Size A3<br>3.0–3.9" barrel</p><p>Subcompact — Holster Size A2.5<br>3.0–3.5" barrel</p><p>A2.5 has a lower trigger space designed for subcompacts and provides a snugger fit.</p><p>Fit depends on personal preference: more snug/not as snug.</p>';}return '<p>Based on the barrel length provided, your recommended size is '+safe(f.size)+'.</p>';}
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}

Passport_Holsters_Fit_Finder::init();

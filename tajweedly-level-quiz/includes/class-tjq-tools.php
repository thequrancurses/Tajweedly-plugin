<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small traffic tools:
 *  - [tajweedly_tip]       "Tajweed tip of the day" (rotates daily through the explanations of the question bank)
 *  - ?tjq_embed=LEVEL      a minimal, iframe-able quiz page for mosques / schools / blogs (+ backlink snippet)
 *  - Insights              most-missed questions and parents' answers, computed from real attempts only
 */
class TJQ_Tools {

	const MIN_ATTEMPTS = 30;

	public static function init() {
		add_shortcode( 'tajweedly_tip', array( __CLASS__, 'tip' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_embed' ), 1 );
	}

	/* ---------- Approximate location (country / region / city), without storing IPs or calling third parties ---------- */

	/**
	 * Reads the location headers a CDN or host adds to the request (Cloudflare, CloudFront, Vercel, ...).
	 * The IP address itself is NOT stored and no external lookup is made. City level needs the host to send it
	 * (Cloudflare: turn on "Add visitor location headers"). Result is approximate and never a street address.
	 * Filter `tjq_geo` lets a site plug in its own offline database (for example MaxMind GeoLite2).
	 */
	public static function geo() {
		$map = array(
			'country' => array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_COUNTRY_CODE' ),
			'region'  => array( 'HTTP_CF_REGION', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION_NAME', 'HTTP_X_VERCEL_IP_COUNTRY_REGION' ),
			'city'    => array( 'HTTP_CF_IPCITY', 'HTTP_CLOUDFRONT_VIEWER_CITY', 'HTTP_X_VERCEL_IP_CITY' ),
		);
		$out = array();
		foreach ( $map as $k => $keys ) {
			foreach ( $keys as $h ) {
				if ( ! empty( $_SERVER[ $h ] ) ) {
					$v = sanitize_text_field( rawurldecode( wp_unslash( $_SERVER[ $h ] ) ) );
					if ( 'country' === $k ) {
						$v = strtoupper( trim( $v ) );
						if ( ! preg_match( '/^[A-Z]{2}$/', $v ) || 'XX' === $v ) { // also drops Cloudflare's "T1" (Tor) and unknown values.
							continue;
						}
					}
					$out[ $k ] = substr( $v, 0, 80 );
					break;
				}
			}
		}
		$out = apply_filters( 'tjq_geo', $out );
		return is_array( $out ) ? array_intersect_key( array_map( 'sanitize_text_field', $out ), array_flip( array( 'country', 'region', 'city' ) ) ) : array();
	}

	/** Human readable "City, Region, GB (Europe/London)" from a stored meta array. */
	public static function loc_label( $meta ) {
		$m = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
		if ( ! is_array( $m ) || empty( $m['loc'] ) || ! is_array( $m['loc'] ) ) {
			return '';
		}
		$l     = $m['loc'];
		$parts = array_filter( array( isset( $l['city'] ) ? $l['city'] : '', isset( $l['region'] ) ? $l['region'] : '', isset( $l['country'] ) ? $l['country'] : '' ) );
		$out   = implode( ', ', $parts );
		if ( ! empty( $l['tz'] ) ) {
			$out .= ( $out ? ' ' : '' ) . '(' . $l['tz'] . ')';
		}
		return $out;
	}

	/* ---------- Tip of the day ---------- */

	public static function tips() {
		$out = array();
		foreach ( TJQ_Questions::all() as $q ) {
			if ( ! empty( $q['explain'] ) && empty( $q['audio'] ) && 0 !== strpos( $q['id'], 'c' ) ) {
				$out[] = $q['explain'];
			}
		}
		return $out;
	}

	public static function tip_for_day( $day ) {
		$tips = self::tips();
		return $tips ? $tips[ (int) $day % count( $tips ) ] : '';
	}

	public static function tip( $atts = array() ) {
		$text = self::tip_for_day( (int) gmdate( 'z' ) + (int) gmdate( 'Y' ) );
		if ( '' === $text ) {
			return '';
		}
		wp_enqueue_style( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.css', array(), TJQ_VERSION );
		$s = TJQ_Settings::all();
		return '<div class="tjq-tip" style="--tjq-primary:' . esc_attr( $s['primary_color'] ) . '"><strong>' . esc_html__( 'Tajweed tip of the day', 'tajweedly-quiz' ) . '</strong>' . esc_html( $text ) . '</div>';
	}

	/* ---------- Embed ---------- */

	public static function embed_url( $level = '' ) {
		return add_query_arg( 'tjq_embed', $level ? $level : 'all', home_url( '/' ) );
	}

	public static function embed_snippet( $level = '' ) {
		$s      = TJQ_Settings::all();
		$origin = wp_parse_url( home_url( '/' ) );
		$origin = $origin['scheme'] . '://' . $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' );
		$link   = $s['quiz_page_url'] ? add_query_arg( array( 'utm_source' => 'embed', 'utm_medium' => 'backlink' ), $s['quiz_page_url'] ) : home_url( '/' );
		return '<iframe src="' . esc_url( self::embed_url( $level ) ) . '" title="' . esc_attr( $s['brand_name'] . ' Tajweed test' ) . '" width="100%" height="720" style="border:0;max-width:720px;display:block;margin:0 auto" loading="lazy"></iframe>' . "\n"
			. '<p style="text-align:center;font-size:14px"><a href="' . esc_url( $link ) . '">Free Tajweed test by ' . esc_html( $s['brand_name'] ) . '</a></p>' . "\n"
			. '<script>window.addEventListener("message",function(e){if(e.origin!==' . wp_json_encode( $origin ) . '||!e.data||!e.data.tjqHeight){return;}var f=document.getElementsByTagName("iframe");for(var i=0;i<f.length;i++){if(f[i].contentWindow===e.source){f[i].style.height=e.data.tjqHeight+"px";}}});</script>';
	}

	public static function maybe_embed() {
		if ( ! isset( $_GET['tjq_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$level = sanitize_key( wp_unslash( $_GET['tjq_embed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( TJQ_Questions::tests()[ $level ] ) ) {
			$level = '';
		}
		nocache_headers();
		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'X-Frame-Options' );
		}
		header( 'Content-Security-Policy: frame-ancestors *' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: text/html; charset=utf-8' );

		$root = TJQ_Shortcode::quiz( array( 'level' => $level ) );
		$s    = TJQ_Settings::all();
		$link = $s['quiz_page_url'] ? add_query_arg( array( 'utm_source' => 'embed', 'utm_medium' => 'powered-by' ), $s['quiz_page_url'] ) : home_url( '/' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( TJQ_I18n::lang() ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $s['brand_name'] ); ?> Tajweed test</title>
		<?php wp_print_styles(); ?>
<style>html,body{margin:0;background:transparent}body{padding:8px}.tjq-pw{text-align:center;font:13px Arial,sans-serif;margin:10px 0 0}.tjq-pw a{color:#6b7280}</style>
</head>
<body>
		<?php echo $root; // phpcs:ignore WordPress.Security.EscapeOutput -- built by TJQ_Shortcode with escaping. ?>
<p class="tjq-pw"><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener">Powered by <?php echo esc_html( $s['brand_name'] ); ?></a></p>
		<?php wp_print_footer_scripts(); ?>
<script>
(function(){function send(){try{parent.postMessage({tjqHeight:document.documentElement.scrollHeight},'*');}catch(e){}}
if('ResizeObserver' in window){new ResizeObserver(send).observe(document.body);}window.addEventListener('load',send);})();
</script>
</body>
</html>
		<?php
		exit;
	}

	/* ---------- Insights ---------- */

	/**
	 * @return array level => array( attempts, missed[ qid => count ] ), plus 'kids' => profile counts.
	 */
	public static function aggregate() {
		global $wpdb;
		$t    = TJQ_DB::table();
		$rows = $wpdb->get_results( "SELECT level, meta FROM $t WHERE meta IS NOT NULL AND level IN ('beginner','intermediate','advanced','kids') ORDER BY id DESC LIMIT 5000" );
		$out  = array( 'levels' => array(), 'kids' => array(), 'kids_n' => 0 );
		foreach ( $rows as $r ) {
			$m = json_decode( $r->meta, true );
			if ( ! is_array( $m ) ) {
				continue;
			}
			if ( 'kids' === $r->level ) {
				++$out['kids_n'];
				foreach ( $m as $k => $v ) {
					if ( is_string( $v ) ) {
						$out['kids'][ $k ][ $v ] = ( isset( $out['kids'][ $k ][ $v ] ) ? $out['kids'][ $k ][ $v ] : 0 ) + 1;
					}
				}
				continue;
			}
			if ( ! isset( $m['missed'] ) || ! is_array( $m['missed'] ) ) {
				continue;
			}
			if ( ! isset( $out['levels'][ $r->level ] ) ) {
				$out['levels'][ $r->level ] = array( 'attempts' => 0, 'missed' => array() );
			}
			++$out['levels'][ $r->level ]['attempts'];
			foreach ( $m['missed'] as $qid ) {
				$qid = (string) $qid;
				$out['levels'][ $r->level ]['missed'][ $qid ] = ( isset( $out['levels'][ $r->level ]['missed'][ $qid ] ) ? $out['levels'][ $r->level ]['missed'][ $qid ] : 0 ) + 1;
			}
		}
		return $out;
	}

	/** Top missed questions per level: level => array of { id, q, rate, explain }. Only when enough attempts exist. */
	public static function top_missed( array $agg, $limit = 5 ) {
		$bank = array();
		foreach ( TJQ_Questions::all() as $q ) {
			$bank[ $q['id'] ] = $q;
		}
		$out = array();
		foreach ( $agg['levels'] as $level => $d ) {
			if ( $d['attempts'] < self::MIN_ATTEMPTS ) {
				continue;
			}
			arsort( $d['missed'] );
			$list = array();
			foreach ( array_slice( $d['missed'], 0, $limit, true ) as $qid => $n ) {
				if ( isset( $bank[ $qid ] ) ) {
					$list[] = array( 'id' => $qid, 'q' => $bank[ $qid ]['q'], 'rate' => (int) round( $n / $d['attempts'] * 100 ), 'explain' => $bank[ $qid ]['explain'] );
				}
			}
			$out[ $level ] = array( 'attempts' => $d['attempts'], 'top' => $list );
		}
		return $out;
	}

	/** Plain-text press summary built only from real numbers. Empty string when there is not enough data. */
	public static function press_summary( array $top, $site ) {
		$parts = array();
		foreach ( $top as $level => $d ) {
			if ( empty( $d['top'] ) ) {
				continue;
			}
			$q       = $d['top'][0];
			$parts[] = sprintf( 'Out of %d %s-level tests, the most-missed question was "%s": %d%% answered it wrongly.', $d['attempts'], $level, $q['q'], $q['rate'] );
		}
		if ( ! $parts ) {
			return '';
		}
		return 'Tajweed test results from ' . $site . "\n\n" . implode( "\n", $parts ) . "\n\nBased on anonymous results of our free online Tajweed test. No personal data used.";
	}
}

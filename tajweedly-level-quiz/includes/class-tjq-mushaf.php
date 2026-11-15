<?php
defined( 'ABSPATH' ) || exit;

/**
 * Colour-coded Quran (tajweed mushaf).
 *
 * The Quran text is NEVER stored in this plugin's source. It is fetched once per surah from the open
 * AlQuran Cloud API (edition "quran-tajweed", optional English "en.sahih") and cached in the database.
 * The tajweed colouring comes from the source's own annotations: [code[letters] or [code:id[letters].
 *
 * Code table verified against the AlQuran Cloud tajweed guide and two independent parser libraries
 * (vipafattal/TajweedParser, dreamfighter/TajweedParser): a = idgham WITH ghunnah, u = idgham WITHOUT.
 *
 * Shortcodes: [tajweedly_mushaf] (reader with surah picker), [tajweedly_mushaf surah="112"],
 *             [tajweedly_rule_examples codes="q"] (live examples pulled from the cached text).
 */
class TJQ_Mushaf {

	const PER_PAGE = 50;
	const TTL      = 7776000; // 90 days.

	public static function init() {
		add_shortcode( 'tajweedly_mushaf', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'tajweedly_rule_examples', array( __CLASS__, 'examples_shortcode' ) );
		add_action( 'wp_ajax_tjq_fetch_surah', array( __CLASS__, 'ajax_fetch' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	/* ---------- Static data ---------- */

	/** n => array( English name, number of ayat in the Hafs count ). */
	public static function surahs() {
		static $s = null;
		if ( null === $s ) {
			$names = array(
				'Al-Fatihah', 'Al-Baqarah', 'Ali Imran', 'An-Nisa', 'Al-Maidah', 'Al-Anam', 'Al-Araf', 'Al-Anfal', 'At-Tawbah', 'Yunus',
				'Hud', 'Yusuf', 'Ar-Rad', 'Ibrahim', 'Al-Hijr', 'An-Nahl', 'Al-Isra', 'Al-Kahf', 'Maryam', 'Ta-Ha',
				'Al-Anbiya', 'Al-Hajj', 'Al-Muminun', 'An-Nur', 'Al-Furqan', 'Ash-Shuara', 'An-Naml', 'Al-Qasas', 'Al-Ankabut', 'Ar-Rum',
				'Luqman', 'As-Sajdah', 'Al-Ahzab', 'Saba', 'Fatir', 'Ya-Sin', 'As-Saffat', 'Sad', 'Az-Zumar', 'Ghafir',
				'Fussilat', 'Ash-Shura', 'Az-Zukhruf', 'Ad-Dukhan', 'Al-Jathiyah', 'Al-Ahqaf', 'Muhammad', 'Al-Fath', 'Al-Hujurat', 'Qaf',
				'Adh-Dhariyat', 'At-Tur', 'An-Najm', 'Al-Qamar', 'Ar-Rahman', 'Al-Waqiah', 'Al-Hadid', 'Al-Mujadila', 'Al-Hashr', 'Al-Mumtahanah',
				'As-Saff', 'Al-Jumuah', 'Al-Munafiqun', 'At-Taghabun', 'At-Talaq', 'At-Tahrim', 'Al-Mulk', 'Al-Qalam', 'Al-Haqqah', 'Al-Maarij',
				'Nuh', 'Al-Jinn', 'Al-Muzzammil', 'Al-Muddaththir', 'Al-Qiyamah', 'Al-Insan', 'Al-Mursalat', 'An-Naba', 'An-Naziat', 'Abasa',
				'At-Takwir', 'Al-Infitar', 'Al-Mutaffifin', 'Al-Inshiqaq', 'Al-Buruj', 'At-Tariq', 'Al-Ala', 'Al-Ghashiyah', 'Al-Fajr', 'Al-Balad',
				'Ash-Shams', 'Al-Layl', 'Ad-Duha', 'Ash-Sharh', 'At-Tin', 'Al-Alaq', 'Al-Qadr', 'Al-Bayyinah', 'Az-Zalzalah', 'Al-Adiyat',
				'Al-Qariah', 'At-Takathur', 'Al-Asr', 'Al-Humazah', 'Al-Fil', 'Quraysh', 'Al-Maun', 'Al-Kawthar', 'Al-Kafirun', 'An-Nasr',
				'Al-Masad', 'Al-Ikhlas', 'Al-Falaq', 'An-Nas',
			);
			$counts = array(
				7, 286, 200, 176, 120, 165, 206, 75, 129, 109,
				123, 111, 43, 52, 99, 128, 111, 110, 98, 135,
				112, 78, 118, 64, 77, 227, 93, 88, 69, 60,
				34, 30, 73, 54, 45, 83, 182, 88, 75, 85,
				54, 53, 89, 59, 37, 35, 38, 29, 18, 45,
				60, 49, 62, 55, 78, 96, 29, 22, 24, 13,
				14, 11, 11, 18, 12, 12, 30, 52, 52, 44,
				28, 28, 20, 56, 40, 31, 50, 40, 46, 42,
				29, 19, 36, 25, 22, 17, 19, 26, 30, 20,
				15, 21, 11, 8, 8, 19, 5, 8, 8, 11,
				11, 8, 3, 9, 5, 4, 7, 3, 6, 3,
				5, 4, 5, 6,
			);
			$s = array();
			foreach ( $names as $i => $name ) {
				$s[ $i + 1 ] = array( $name, $counts[ $i ] );
			}
		}
		return $s;
	}

	public static function surah_slug( $n ) {
		$s = self::surahs();
		return 'surah-' . sanitize_title( $s[ $n ][0] ) . '-tajweed';
	}

	/**
	 * Annotation code => label, colour, group, short description.
	 * Colours and meanings follow the AlQuran Cloud tajweed guide.
	 */
	public static function rules() {
		return array(
			'h' => array( 'Hamzat al-wasl', '#AAAAAA', 'silent', 'A connecting hamza: pronounced when you start with it, dropped when you read on.' ),
			's' => array( 'Silent letter', '#AAAAAA', 'silent', 'A letter that is written but not pronounced.' ),
			'l' => array( 'Lam shamsiyyah', '#AAAAAA', 'silent', 'The lam of "al-" before a sun letter: not pronounced, the next letter is doubled.' ),
			'n' => array( 'Normal prolongation (Madd Asli)', '#537FFF', 'madd', 'Held for 2 counts.' ),
			'p' => array( 'Permissible prolongation', '#4050FF', 'madd', 'Held for 2, 4 or 6 counts.' ),
			'm' => array( 'Necessary prolongation (Madd Lazim)', '#000EBC', 'madd', 'Held for 6 counts.' ),
			'o' => array( 'Obligatory prolongation (Madd Muttasil)', '#2144C1', 'madd', 'Held for 4 to 5 counts.' ),
			'q' => array( 'Qalqalah', '#DD0008', 'qalqalah', 'An echo on the letters ق ط ب ج د when they carry a sukoon.' ),
			'c' => array( 'Ikhfa Shafawi', '#D500B7', 'noon', 'Meem sakinah before ب: hidden, with ghunnah.' ),
			'f' => array( 'Ikhfa', '#9400A8', 'noon', 'Noon sakinah or tanween hidden before the 15 ikhfa letters, with ghunnah.' ),
			'w' => array( 'Idgham Shafawi', '#58B800', 'noon', 'Meem sakinah merged into a following meem.' ),
			'i' => array( 'Iqlab', '#26BFFD', 'noon', 'Noon sakinah or tanween turned into a hidden meem before ب.' ),
			'a' => array( 'Idgham with ghunnah', '#169777', 'noon', 'Merging with a nasal sound (before ي ن م و).' ),
			'u' => array( 'Idgham without ghunnah', '#169200', 'noon', 'Merging with no nasal sound (before ل ر).' ),
			'd' => array( 'Idgham Mutajanisayn', '#A1A1A1', 'idgham', 'Merging of two letters that share the same articulation point.' ),
			'b' => array( 'Idgham Mutaqaribayn', '#A1A1A1', 'idgham', 'Merging of two letters whose articulation points are close.' ),
			'g' => array( 'Ghunnah', '#FF7E1E', 'ghunnah', 'The nasal sound on noon or meem with a shaddah, held for 2 counts.' ),
		);
	}

	public static function groups() {
		return array(
			'madd'     => 'Prolongation (Madd)',
			'noon'     => 'Noon sakinah, tanween and meem sakinah',
			'ghunnah'  => 'Ghunnah',
			'qalqalah' => 'Qalqalah',
			'idgham'   => 'Idgham of similar letters',
			'silent'   => 'Letters not pronounced when reading on',
		);
	}

	/* ---------- Parsing ---------- */

	/**
	 * Turns annotated text into safe HTML. Returns array( html, counts[code] ).
	 * Text is escaped first; only <span class="tj-X"> elements for known codes are generated.
	 */
	public static function parse( $text ) {
		$rules  = self::rules();
		$counts = array();
		$esc    = esc_html( (string) $text );
		$html   = preg_replace_callback(
			'/\[([a-z])(?::\d+)?\[([^\[\]]*)\]/u',
			function ( $m ) use ( $rules, &$counts ) {
				if ( ! isset( $rules[ $m[1] ] ) ) {
					return $m[2];
				}
				$counts[ $m[1] ] = isset( $counts[ $m[1] ] ) ? $counts[ $m[1] ] + 1 : 1;
				return '<span class="tj-' . $m[1] . '">' . $m[2] . '</span>';
			},
			$esc
		);
		return array( $html, $counts );
	}

	public static function arabic_digits( $n ) {
		return strtr( (string) (int) $n, array( '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩' ) );
	}

	/* ---------- Data (fetch + cache) ---------- */

	private static function api( $path ) {
		$base = apply_filters( 'tjq_mushaf_api_base', 'https://api.alquran.cloud/v1/' );
		$res  = wp_remote_get( $base . $path, array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'tjq_api_http', 'HTTP ' . (int) wp_remote_retrieve_response_code( $res ) );
		}
		$j = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $j ) || ! isset( $j['data']['ayahs'] ) || ! is_array( $j['data']['ayahs'] ) ) {
			return new WP_Error( 'tjq_api_shape', 'Unexpected API response.' );
		}
		$d    = $j['data'];
		$want = isset( $d['numberOfAyahs'] ) ? (int) $d['numberOfAyahs'] : count( $d['ayahs'] );
		if ( count( $d['ayahs'] ) !== $want ) {
			return new WP_Error( 'tjq_api_count', 'Ayah count mismatch.' );
		}
		foreach ( array_values( $d['ayahs'] ) as $k => $a ) {
			if ( ! isset( $a['text'] ) || ! is_string( $a['text'] ) || (int) $a['numberInSurah'] !== $k + 1 ) {
				return new WP_Error( 'tjq_api_shape', 'Unexpected ayah data.' );
			}
		}
		return $d;
	}

	/** Downloads and stores one surah. Returns the stored array or WP_Error. */
	public static function fetch( $n ) {
		$n = (int) $n;
		if ( $n < 1 || $n > 114 ) {
			return new WP_Error( 'tjq_range', 'Surah must be 1-114.' );
		}
		$tj = self::api( 'surah/' . $n . '/quran-tajweed' );
		if ( is_wp_error( $tj ) ) {
			return $tj;
		}
		$en = self::api( 'surah/' . $n . '/en.sahih' );
		$ay = array();
		foreach ( array_values( $tj['ayahs'] ) as $k => $a ) {
			$e = '';
			if ( ! is_wp_error( $en ) && isset( $en['ayahs'][ $k ]['text'] ) && count( $en['ayahs'] ) === count( $tj['ayahs'] ) ) {
				$e = (string) $en['ayahs'][ $k ]['text'];
			}
			$ay[] = array( 'i' => $k + 1, 't' => $a['text'], 'e' => $e );
		}
		$known = self::surahs();
		$data  = array(
			'n'        => $n,
			'name'     => isset( $tj['name'] ) ? (string) $tj['name'] : '',
			'english'  => $known[ $n ][0],
			'type'     => isset( $tj['revelationType'] ) ? (string) $tj['revelationType'] : '',
			'count'    => count( $ay ),
			'expected' => $known[ $n ][1],
			'fetched'  => time(),
			'has_en'   => ! is_wp_error( $en ),
			'ayahs'    => $ay,
		);
		update_option( 'tjq_q_' . $n, $data, false );
		return $data;
	}

	/** Cached surah (fetching when missing or stale). Returns array or null. */
	public static function get( $n ) {
		$n    = (int) $n;
		$data = get_option( 'tjq_q_' . $n );
		$ok   = is_array( $data ) && ! empty( $data['ayahs'] );
		if ( $ok && ( time() - (int) $data['fetched'] ) < self::TTL ) {
			return $data;
		}
		if ( get_transient( 'tjq_q_fail_' . $n ) ) {
			return $ok ? $data : null; // backing off after a failure.
		}
		$new = self::fetch( $n );
		if ( is_wp_error( $new ) ) {
			set_transient( 'tjq_q_fail_' . $n, 1, 5 * MINUTE_IN_SECONDS );
			return $ok ? $data : null;
		}
		return $new;
	}

	public static function cached_numbers() {
		$out = array();
		foreach ( array_keys( self::surahs() ) as $n ) {
			$d = get_option( 'tjq_q_' . $n );
			if ( is_array( $d ) && ! empty( $d['ayahs'] ) ) {
				$out[] = $n;
			}
		}
		return $out;
	}

	public static function ajax_fetch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
		check_ajax_referer( 'tjq_fetch_surah', 'nonce' );
		$n   = isset( $_POST['n'] ) ? (int) $_POST['n'] : 0;
		$res = self::fetch( $n );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}
		wp_send_json_success( array( 'n' => $n, 'ayahs' => $res['count'], 'mismatch' => $res['count'] !== $res['expected'] ) );
	}

	/* ---------- Assets ---------- */

	public static function enqueue() {
		$s = TJQ_Settings::all();
		if ( $s['mushaf_font'] ) {
			wp_enqueue_style( 'tjq-amiri', 'https://fonts.googleapis.com/css2?family=Amiri+Quran&display=swap', array(), null );
		}
		wp_enqueue_style( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.css', array(), TJQ_VERSION );
		wp_enqueue_script( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.js', array(), TJQ_VERSION, true );
	}

	/** Pages created by this plugin carry meta so the Arabic/example styles load. */
	public static function maybe_enqueue() {
		if ( is_singular( 'page' ) && get_post_meta( get_the_ID(), '_tjq_page', true ) ) {
			wp_enqueue_style( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.css', array(), TJQ_VERSION );
		}
	}

	/* ---------- Rendering ---------- */

	public static function legend_html( $open = false ) {
		$rules  = self::rules();
		$groups = self::groups();
		$out    = '<details class="tjq-legend"' . ( $open ? ' open' : '' ) . '><summary>' . esc_html__( 'Colour guide', 'tajweedly-quiz' ) . '</summary>';
		foreach ( $groups as $g => $title ) {
			$out .= '<div class="tjq-lg"><strong>' . esc_html( $title ) . '</strong><ul>';
			$seen = array();
			foreach ( $rules as $code => $r ) {
				if ( $r[2] !== $g ) {
					continue;
				}
				$key = $r[0] . $r[1];
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = 1;
				$out         .= '<li><span class="tjq-sw tj-' . esc_attr( $code ) . '" style="background:' . esc_attr( $r[1] ) . '"></span><span><b>' . esc_html( $r[0] ) . '.</b> ' . esc_html( $r[3] ) . '</span></li>';
			}
			$out .= '</ul></div>';
		}
		return $out . '</details>';
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'surah' => '' ), $atts, 'tajweedly_mushaf' );
		self::enqueue();
		$fixed = (int) $atts['surah'];
		$n     = $fixed ? $fixed : ( isset( $_GET['surah'] ) ? (int) $_GET['surah'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$n     = max( 1, min( 114, $n ) );
		$page  = isset( $_GET['tjq_p'] ) ? max( 1, (int) $_GET['tjq_p'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		return self::render( $n, $page, ! $fixed );
	}

	public static function render( $n, $page = 1, $picker = true ) {
		$s     = TJQ_Settings::all();
		$known = self::surahs();
		$data  = self::get( $n );
		$rules = array();
		foreach ( self::rules() as $code => $r ) {
			$rules[ $code ] = array( $r[0], $r[1] );
		}
		$out = '<div class="tjq-mushaf" data-mode="lines" data-trans="' . ( $s['mushaf_translation'] ? '1' : '0' ) . '" data-surah="' . esc_attr( $known[ $n ][0] ) . '" data-n="' . (int) $n . '" data-rules="' . esc_attr( wp_json_encode( $rules ) ) . '" style="--tjq-primary:' . esc_attr( $s['primary_color'] ) . ';--tjq-accent:' . esc_attr( $s['accent_color'] ) . '">';

		// Slim top bar: surah picker (or title) + one settings button. Everything else is hidden until needed.
		$out .= '<div class="tjq-bar">';
		if ( $picker ) {
			$out .= '<form class="tjq-pick" method="get" action="' . esc_url( get_permalink() ) . '"><label class="screen-reader-text" for="tjq-surah-' . (int) $n . '">' . esc_html__( 'Surah', 'tajweedly-quiz' ) . '</label><select id="tjq-surah-' . (int) $n . '" name="surah" onchange="this.form.submit()">';
			foreach ( $known as $num => $info ) {
				$out .= '<option value="' . (int) $num . '"' . selected( $n, $num, false ) . '>' . (int) $num . '. ' . esc_html( $info[0] ) . '</option>';
			}
			$out .= '</select><noscript><button type="submit">' . esc_html__( 'Go', 'tajweedly-quiz' ) . '</button></noscript></form>';
		} else {
			$out .= '<span class="tjq-bar-title">' . esc_html( sprintf( '%d. %s', $n, $known[ $n ][0] ) ) . '</span>';
		}
		if ( $data ) {
			$out .= '<button type="button" class="tjq-gear" data-act="panel" aria-expanded="false" aria-controls="tjq-panel-' . (int) $n . '">' . esc_html__( 'Reading options', 'tajweedly-quiz' ) . '</button>';
		}
		$out .= '</div>';
		if ( ! $data ) {
			return $out . '<p class="tjq-note">' . esc_html__( 'The Quran text could not be loaded right now. Please try again in a few minutes.', 'tajweedly-quiz' ) . '</p></div>';
		}

		$out .= '<div class="tjq-panel" id="tjq-panel-' . (int) $n . '" hidden>'
			. '<div class="tjq-row"><span>' . esc_html__( 'Text size', 'tajweedly-quiz' ) . '</span><span class="tjq-ctl"><button type="button" data-act="font-" aria-label="Smaller text">A-</button><button type="button" data-act="font+" aria-label="Larger text">A+</button></span></div>'
			. '<div class="tjq-row"><span>' . esc_html__( 'Background', 'tajweedly-quiz' ) . '</span><span class="tjq-ctl"><button type="button" class="tjq-th tjq-th-light" data-theme-set="light" aria-label="Light"></button><button type="button" class="tjq-th tjq-th-sepia" data-theme-set="sepia" aria-label="Sepia"></button><button type="button" class="tjq-th tjq-th-dark" data-theme-set="dark" aria-label="Dark"></button></span></div>'
			. '<div class="tjq-row"><span>' . esc_html__( 'Tajweed colours', 'tajweedly-quiz' ) . '</span><span class="tjq-ctl"><button type="button" class="tjq-sw-btn" data-act="colors" aria-pressed="true">' . esc_html__( 'On', 'tajweedly-quiz' ) . '</button></span></div>'
			. '<div class="tjq-row"><span>' . esc_html__( 'Layout', 'tajweedly-quiz' ) . '</span><span class="tjq-ctl"><button type="button" data-act="mode" data-mode-set="lines">' . esc_html__( 'Verse by verse', 'tajweedly-quiz' ) . '</button><button type="button" data-act="mode" data-mode-set="flow">' . esc_html__( 'Mushaf', 'tajweedly-quiz' ) . '</button>' . ( $data['has_en'] ? '<button type="button" data-act="mode" data-mode-set="verse">' . esc_html__( 'With translation', 'tajweedly-quiz' ) . '</button>' : '' ) . '</span></div>'
			. self::legend_html( false ) . '</div>';

		// Parse the whole surah once for the counts, render only the current page.
		$total = count( $data['ayahs'] );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = min( $page, $pages );
		$from  = ( $page - 1 ) * self::PER_PAGE;
		$all   = array();
		$body  = '';
		foreach ( $data['ayahs'] as $k => $a ) {
			list( $html, $c ) = self::parse( $a['t'] );
			foreach ( $c as $code => $num ) {
				$all[ $code ] = ( isset( $all[ $code ] ) ? $all[ $code ] : 0 ) + $num;
			}
			if ( $k >= $from && $k < $from + self::PER_PAGE ) {
				$body .= '<span class="tjq-ayah" data-i="' . (int) $a['i'] . '"><span class="tjq-ar" lang="ar" dir="rtl">' . $html . '</span> <span class="tjq-num">﴿' . esc_html( self::arabic_digits( $a['i'] ) ) . '﴾</span>'
					. ( '' !== $a['e'] ? '<span class="tjq-trans" lang="en" dir="ltr">' . esc_html( $a['i'] . '. ' . $a['e'] ) . '</span>' : '' )
					. '</span> ';
			}
		}

		$out .= '<header class="tjq-sh"><h2 class="tjq-ar" lang="ar" dir="rtl">' . esc_html( $data['name'] ) . '</h2><p>'
			. esc_html( sprintf( '%d. %s', $n, $known[ $n ][0] ) ) . ' &middot; ' . esc_html( sprintf( _n( '%d ayah', '%d ayat', $total, 'tajweedly-quiz' ), $total ) )
			. ( $data['type'] ? ' &middot; ' . esc_html( $data['type'] ) : '' ) . '</p></header>';
		$out .= '<div class="tjq-text" dir="rtl">' . $body . '</div>';
		$out .= '<div class="tjq-pop" role="status" aria-live="polite" hidden></div>';

		if ( $pages > 1 ) {
			$out .= '<nav class="tjq-pager" aria-label="Pages">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url  = $picker ? add_query_arg( array( 'surah' => $n, 'tjq_p' => $i ), get_permalink() ) : add_query_arg( 'tjq_p', $i, get_permalink() );
				$out .= $i === $page ? '<span class="cur">' . (int) $i . '</span>' : '<a href="' . esc_url( $url ) . '">' . (int) $i . '</a>';
			}
			$out .= '</nav>';
		}
		$out .= '<p class="tjq-hint">' . esc_html__( 'Tip: tap any ayah for its translation, or a coloured letter to see its rule.', 'tajweedly-quiz' ) . '</p>';

		// Rule counts (computed from the source annotations of the whole surah), collapsed by default.
		if ( $all ) {
			$out .= '<details class="tjq-countsd"><summary>' . esc_html__( 'Tajweed rules marked in this surah', 'tajweedly-quiz' ) . '</summary><table class="tjq-counts"><tbody>';
			foreach ( self::rules() as $code => $r ) {
				if ( empty( $all[ $code ] ) || 'silent' === $r[2] ) {
					continue;
				}
				$out .= '<tr><td><span class="tjq-sw" style="background:' . esc_attr( $r[1] ) . '"></span> ' . esc_html( $r[0] ) . '</td><td>' . (int) $all[ $code ] . '</td></tr>';
			}
			$out .= '</tbody></table><p class="tjq-note">' . esc_html__( 'Counts are the number of coloured places in the text, as annotated by the data source.', 'tajweedly-quiz' ) . '</p></details>';
		}

		$out .= self::neighbours( $n );
		$out .= TJQ_Shortcode::cta_html( $s['cta_text'], '', 'mushaf' );
		$out .= '<p class="tjq-note">' . esc_html__( 'Text and tajweed colouring: AlQuran Cloud (quran-tajweed). English: Sahih International. Colouring is a learning aid; learn recitation from a qualified teacher and check it against a printed mushaf.', 'tajweedly-quiz' ) . '</p>';
		return $out . '</div>';
	}


	/** Links to the previous / next surah pages when they exist. */
	private static function neighbours( $n ) {
		$known = self::surahs();
		$links = array();
		foreach ( array( $n - 1, $n + 1 ) as $m ) {
			if ( ! isset( $known[ $m ] ) ) {
				continue;
			}
			$p = get_page_by_path( self::surah_slug( $m ), OBJECT, 'page' );
			if ( $p && 'publish' === $p->post_status ) {
				$links[] = '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( sprintf( 'Surah %s', $known[ $m ][0] ) ) . '</a>';
			}
		}
		return $links ? '<p class="tjq-nb">' . implode( ' &middot; ', $links ) . '</p>' : '';
	}

	/* ---------- Live rule examples ---------- */

	/**
	 * Ayat from the cached text that contain a given rule code. Juz Amma is searched first.
	 */
	public static function examples( array $codes, $limit = 4 ) {
		$key = 'tjq_ex_' . md5( implode( ',', $codes ) . '|' . $limit );
		$hit = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$cached = self::cached_numbers();
		usort(
			$cached,
			function ( $a, $b ) {
				$ka = $a >= 78 ? 0 : 1;
				$kb = $b >= 78 ? 0 : 1;
				return $ka === $kb ? $a - $b : $ka - $kb;
			}
		);
		$known = self::surahs();
		$out   = array();
		$re    = '/\[(' . implode( '|', array_map( 'preg_quote', $codes ) ) . ')(?::\d+)?\[/u';
		foreach ( $cached as $n ) {
			$d    = get_option( 'tjq_q_' . $n );
			$took = 0;
			foreach ( $d['ayahs'] as $a ) {
				if ( preg_match( $re, $a['t'] ) && mb_strlen( $a['t'] ) < 160 ) {
					list( $html ) = self::parse( $a['t'] );
					$out[]        = array( 'html' => $html, 'ref' => sprintf( 'Surah %s %d:%d', $known[ $n ][0], $n, $a['i'] ), 'en' => $a['e'] );
					++$took;
					if ( $took >= 1 || count( $out ) >= $limit ) {
						break;
					}
				}
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	public static function examples_shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'codes' => '', 'limit' => 4 ), $atts, 'tajweedly_rule_examples' );
		$codes = array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $atts['codes'] ) ) ), function ( $c ) {
			return 1 === strlen( $c ) && isset( self::rules()[ $c ] );
		} ) );
		if ( ! $codes ) {
			return '';
		}
		wp_enqueue_style( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.css', array(), TJQ_VERSION );
		$ex = self::examples( $codes, max( 1, min( 8, (int) $atts['limit'] ) ) );
		if ( ! $ex ) {
			return '';
		}
		$out = '<div class="tjq-mushaf tjq-ex"><h3 class="tjq-h3">' . esc_html__( 'Examples from the Quran (colour-coded)', 'tajweedly-quiz' ) . '</h3>';
		foreach ( $ex as $e ) {
			$out .= '<div class="tjq-ayah"><p class="tjq-ar" lang="ar" dir="rtl">' . $e['html'] . '</p><p class="tjq-trans">' . esc_html( $e['ref'] ) . ( $e['en'] ? ': ' . esc_html( $e['en'] ) : '' ) . '</p></div>';
		}
		return $out . '</div>';
	}
}

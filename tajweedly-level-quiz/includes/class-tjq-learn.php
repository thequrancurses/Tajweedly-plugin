<?php
defined( 'ABSPATH' ) || exit;

/**
 * Interactive learning layer for the tajweed pages:
 *  [tajweedly_path]        ordered course map with progress (stored in the visitor's browser only)
 *  [tajweedly_explorer]    tap a rule to light up its letters on the 28-letter grid
 *  [tajweedly_drill type]  10-question practice round (noon | sunmoon | qalqalah | meem | madd | ghunnah | makharij)
 *  [tajweedly_lesson_top]  lesson header: progress, contents, previous / next
 *  [tajweedly_lesson_end]  practice round, "mark as done", previous / next
 * Drills teach basic, widely agreed rules only; the answers are visible in the page source on purpose (practice, not a graded test).
 */
class TJQ_Learn {

	public static function init() {
		add_shortcode( 'tajweedly_path', array( __CLASS__, 'path' ) );
		add_shortcode( 'tajweedly_explorer', array( __CLASS__, 'explorer' ) );
		add_shortcode( 'tajweedly_drill', array( __CLASS__, 'drill' ) );
		add_shortcode( 'tajweedly_lesson_top', array( __CLASS__, 'lesson_top' ) );
		add_shortcode( 'tajweedly_lesson_end', array( __CLASS__, 'lesson_end' ) );
	}

	/* ---------- Course ---------- */

	public static function curriculum() {
		return array(
			array( 'slug' => 'makharij-al-huruf', 'stage' => 'Foundations', 'title' => 'Makharij: where letters come from', 'desc' => 'The five areas of articulation.' ),
			array( 'slug' => 'sun-and-moon-letters', 'stage' => 'Foundations', 'title' => 'Sun and moon letters', 'desc' => 'How to read "al-" correctly.' ),
			array( 'slug' => 'izhar-tajweed-rules', 'stage' => 'Noon and meem', 'title' => 'Izhar', 'desc' => 'Say the noon clearly.' ),
			array( 'slug' => 'idgham-tajweed-rules', 'stage' => 'Noon and meem', 'title' => 'Idgham', 'desc' => 'Merge into the next letter.' ),
			array( 'slug' => 'iqlab-tajweed-rules', 'stage' => 'Noon and meem', 'title' => 'Iqlab', 'desc' => 'Turn the noon into a hidden meem.' ),
			array( 'slug' => 'ikhfa-tajweed-rules', 'stage' => 'Noon and meem', 'title' => 'Ikhfa', 'desc' => 'Hide the noon with ghunnah.' ),
			array( 'slug' => 'meem-sakinah-tajweed-rules', 'stage' => 'Noon and meem', 'title' => 'Meem sakinah', 'desc' => 'Three rules for the silent meem.' ),
			array( 'slug' => 'ghunnah-tajweed-rules', 'stage' => 'Echo and length', 'title' => 'Ghunnah', 'desc' => 'The nasal sound.' ),
			array( 'slug' => 'qalqalah-tajweed-rules', 'stage' => 'Echo and length', 'title' => 'Qalqalah', 'desc' => 'The five echo letters.' ),
			array( 'slug' => 'madd-tajweed-rules', 'stage' => 'Echo and length', 'title' => 'Madd', 'desc' => 'Types of prolongation.' ),
		);
	}

	private static function index_of( $slug ) {
		foreach ( self::curriculum() as $i => $l ) {
			if ( $l['slug'] === $slug ) {
				return $i;
			}
		}
		return -1;
	}

	private static function link( $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'page' );
		return ( $p && 'publish' === $p->post_status ) ? get_permalink( $p ) : '';
	}

	private static function assets() {
		wp_enqueue_style( 'tjq-mushaf', TJQ_URL . 'assets/mushaf.css', array(), TJQ_VERSION );
		wp_enqueue_style( 'tjq-learn', TJQ_URL . 'assets/learn.css', array( 'tjq-mushaf' ), TJQ_VERSION );
		wp_enqueue_script( 'tjq-learn', TJQ_URL . 'assets/learn.js', array(), TJQ_VERSION, true );
		$c = sanitize_hex_color( TJQ_Settings::get( 'primary_color' ) );
		$a = sanitize_hex_color( TJQ_Settings::get( 'accent_color' ) );
		wp_add_inline_style( 'tjq-learn', '.tjq-page,.tjq-learn{--tjq-primary:' . ( $c ? $c : '#0f766e' ) . ';--tjq-accent:' . ( $a ? $a : '#f59e0b' ) . '}' );
	}

	private static function cta_url() {
		$u = TJQ_Settings::get( 'quiz_page_url' );
		return $u ? add_query_arg( array( 'utm_source' => 'lesson', 'utm_medium' => 'drill' ), $u ) : '';
	}

	/* ---------- Course map ---------- */

	public static function path() {
		self::assets();
		$items = self::curriculum();
		$out   = '<div class="tjq-learn tjq-path" data-cta="' . esc_url( self::cta_url() ) . '" data-total="' . count( $items ) . '"><div class="tjq-path-head"><div><strong>Your tajweed path</strong><span class="tjq-path-count">0 of ' . count( $items ) . ' lessons done</span></div><a class="tjq-btn tjq-path-go" href="#" hidden>Continue</a></div><div class="tjq-bar"><i></i></div>';
		$stage = '';
		foreach ( $items as $i => $l ) {
			if ( $l['stage'] !== $stage ) {
				if ( '' !== $stage ) {
					$out .= '</div>';
				}
				$stage = $l['stage'];
				$out  .= '<h3 class="tjq-stage">' . esc_html( $stage ) . '</h3><div class="tjq-cards">';
			}
			$url   = self::link( $l['slug'] );
			$inner = '<span class="tjq-num">' . (int) ( $i + 1 ) . '</span><span class="tjq-ct"><b>' . esc_html( $l['title'] ) . '</b><small>' . esc_html( $l['desc'] ) . '</small></span><span class="tjq-tick" aria-hidden="true">&#10003;</span>';
			$out  .= $url ? '<a class="tjq-card" data-slug="' . esc_attr( $l['slug'] ) . '" href="' . esc_url( $url ) . '">' . $inner . '</a>' : '<span class="tjq-card tjq-soon" data-slug="' . esc_attr( $l['slug'] ) . '">' . $inner . '<em>soon</em></span>';
		}
		return $out . '</div></div>';
	}

	/* ---------- Letter explorer ---------- */

	public static function explorer() {
		self::assets();
		$L      = TJQ_Content::letters();
		$groups = array(
			array( 'Izhar', $L['izhar'], 'The six throat letters: say the noon clearly.' ),
			array( 'Idgham', $L['idgham'], 'Six letters: merge the noon into them.' ),
			array( 'Iqlab', array( 'ب' ), 'Only ba: the noon becomes a hidden meem.' ),
			array( 'Ikhfa', $L['ikhfa'], 'Fifteen letters: hide the noon with ghunnah.' ),
			array( 'Sun letters', $L['sun'], 'The L of "al-" is silent; the letter is doubled.' ),
			array( 'Moon letters', $L['moon'], 'The L of "al-" is pronounced.' ),
			array( 'Qalqalah', $L['qalqalah'], 'The echo letters when they carry a sukoon.' ),
			array( 'Heavy letters', $L['istila'], 'Pronounced with a raised, heavy tongue (isti\'la).' ),
		);
		$grid   = array( 'ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'ه', 'و', 'ي' );
		$out    = '<div class="tjq-learn tjq-explorer" data-groups="' . esc_attr( wp_json_encode( $groups, JSON_UNESCAPED_UNICODE ) ) . '"><div class="tjq-chips" role="tablist" aria-label="Choose a rule">';
		foreach ( $groups as $i => $g ) {
			$out .= '<button type="button" class="tjq-chip" data-i="' . (int) $i . '">' . esc_html( $g[0] ) . '</button>';
		}
		$out .= '</div><p class="tjq-exp-note" aria-live="polite">Tap a rule to light up its letters. (Hamzah shares the cell of alif.)</p><div class="tjq-letters" dir="rtl">';
		foreach ( $grid as $c ) {
			$out .= '<span class="tjq-let" lang="ar">' . $c . '</span>';
		}
		return $out . '</div></div>';
	}

	/* ---------- Drills ---------- */

	public static function drill_items( $type ) {
		$L   = TJQ_Content::letters();
		$all = array_merge( $L['izhar'], $L['idgham'], array( 'ب' ), $L['ikhfa'] ); // 28 cells (hamzah stands for alif)
		$out = array();
		switch ( $type ) {
			case 'noon':
				$opts = array( 'Izhar', 'Idgham', 'Iqlab', 'Ikhfa' );
				foreach ( $all as $c ) {
					if ( in_array( $c, $L['izhar'], true ) ) {
						$k = 0;
						$e = 'A throat letter, so the noon is pronounced clearly (Izhar).';
					} elseif ( 'ب' === $c ) {
						$k = 2;
						$e = 'Before ba the noon turns into a hidden meem, with ghunnah (Iqlab).';
					} elseif ( in_array( $c, $L['idgham'], true ) ) {
						$k = 1;
						$e = in_array( $c, $L['idgham_g'], true ) ? 'One of the letters of Idgham with ghunnah (when the noon and this letter are in different words).' : 'One of the letters of Idgham without ghunnah: merge fully, no nasal sound.';
					} else {
						$k = 3;
						$e = 'One of the 15 Ikhfa letters: hide the noon, with ghunnah.';
					}
					$out[] = array( 'p' => 'Noon sakinah or tanween, followed by:', 'a' => $c, 'o' => $opts, 'c' => $k, 'e' => $e );
				}
				break;
			case 'meem':
				$opts = array( 'Ikhfa Shafawi', 'Idgham Shafawi', 'Izhar Shafawi' );
				foreach ( array( 'ب', 'م', 'ت', 'ك', 'ف', 'ل', 'ر', 'ن', 'س', 'ه', 'ع', 'و' ) as $c ) {
					if ( 'ب' === $c ) {
						$out[] = array( 'p' => 'Meem sakinah, followed by:', 'a' => $c, 'o' => $opts, 'c' => 0, 'e' => 'Before ba: hide the meem lightly, with ghunnah.' );
					} elseif ( 'م' === $c ) {
						$out[] = array( 'p' => 'Meem sakinah, followed by:', 'a' => $c, 'o' => $opts, 'c' => 1, 'e' => 'Before another meem: merge into one doubled meem, with ghunnah.' );
					} else {
						$out[] = array( 'p' => 'Meem sakinah, followed by:', 'a' => $c, 'o' => $opts, 'c' => 2, 'e' => 'Any other letter: pronounce the meem clearly.' );
					}
				}
				break;
			case 'sunmoon':
				foreach ( array_merge( $L['sun'], $L['moon'] ) as $c ) {
					$sun   = in_array( $c, $L['sun'], true );
					$out[] = array( 'p' => 'The letter after "al-" is:', 'a' => $c, 'o' => array( 'Sun letter', 'Moon letter' ), 'c' => $sun ? 0 : 1, 'e' => $sun ? 'Sun letter: the L is silent and the letter is doubled.' : 'Moon letter: the L is pronounced.' );
				}
				break;
			case 'qalqalah':
				foreach ( array( 'ق', 'ب', 'ت', 'ط', 'م', 'ج', 'ك', 'د', 'ر', 'س', 'ل', 'ن' ) as $c ) {
					$q     = in_array( $c, $L['qalqalah'], true );
					$out[] = array( 'p' => 'With a sukoon, is this a qalqalah letter?', 'a' => $c, 'o' => array( 'Yes', 'No' ), 'c' => $q ? 0 : 1, 'e' => $q ? 'Yes: one of the five echo letters (ق ط ب ج د).' : 'No. The five echo letters are ق ط ب ج د.' );
				}
				break;
			case 'madd':
				$o = array( 'Madd Asli', 'Madd Muttasil', 'Madd Munfasil', 'Madd Lazim' );
				return array(
					array( 'p' => 'A long vowel with no hamzah or sukoon after it.', 'o' => $o, 'c' => 0, 'e' => 'Madd Asli (natural): 2 counts.' ),
					array( 'p' => 'A madd letter followed by a hamzah in the SAME word.', 'o' => $o, 'c' => 1, 'e' => 'Madd Muttasil (connected): commonly 4 to 5 counts; the exact length depends on the recitation.' ),
					array( 'p' => 'A madd letter ends a word and the next word starts with a hamzah.', 'o' => $o, 'c' => 2, 'e' => 'Madd Munfasil (separated): commonly 4 to 5 counts; it varies by recitation, follow your teacher.' ),
					array( 'p' => 'A madd letter followed by a permanent sukoon or a shaddah in the same word.', 'o' => $o, 'c' => 3, 'e' => 'Madd Lazim (necessary): 6 counts.' ),
				);
			case 'ghunnah':
				return array(
					array( 'p' => 'Ghunnah is a sound that comes from the...', 'o' => array( 'Throat', 'Lips', 'Nasal passage', 'Tongue tip' ), 'c' => 2, 'e' => 'The nasal passage (khayshum).' ),
					array( 'p' => 'How long is ghunnah held on a noon or meem with a shaddah?', 'o' => array( '1 count', '2 counts', '4 counts', '6 counts' ), 'c' => 1, 'e' => 'About 2 counts is the common teaching value.' ),
					array( 'p' => 'Does Izhar include ghunnah?', 'o' => array( 'Yes', 'No' ), 'c' => 1, 'e' => 'No. Izhar means clarity: the noon is said clearly with no nasal humming.' ),
					array( 'p' => 'Does Ikhfa include ghunnah?', 'o' => array( 'Yes', 'No' ), 'c' => 0, 'e' => 'Yes. The noon is hidden with ghunnah.' ),
				);
			case 'makharij':
				$o = array( 'Throat (Halq)', 'Lips (Shafatayn)', 'Mouth cavity (Jawf)', 'Nasal passage (Khayshum)' );
				return array(
					array( 'p' => 'The letters ء ه ع ح غ خ come from the...', 'o' => $o, 'c' => 0, 'e' => 'The throat: these are the six throat letters.' ),
					array( 'p' => 'The letters ب م و are produced with the...', 'o' => $o, 'c' => 1, 'e' => 'The lips.' ),
					array( 'p' => 'The long vowels (madd letters) come from the...', 'o' => $o, 'c' => 2, 'e' => 'The empty space of the mouth and throat (Jawf).' ),
					array( 'p' => 'Ghunnah (the nasal sound) comes from the...', 'o' => $o, 'c' => 3, 'e' => 'The nasal passage (Khayshum).' ),
				);
		}
		return $out;
	}

	public static function drill_type_for( $slug ) {
		$map = array(
			'tajweed-rules' => 'noon', 'izhar-tajweed-rules' => 'noon', 'idgham-tajweed-rules' => 'noon', 'iqlab-tajweed-rules' => 'noon', 'ikhfa-tajweed-rules' => 'noon',
			'meem-sakinah-tajweed-rules' => 'meem', 'sun-and-moon-letters' => 'sunmoon', 'qalqalah-tajweed-rules' => 'qalqalah', 'madd-tajweed-rules' => 'madd',
			'ghunnah-tajweed-rules' => 'ghunnah', 'makharij-al-huruf' => 'makharij',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
	}

	public static function drill( $atts = array() ) {
		$a     = shortcode_atts( array( 'type' => 'noon', 'slug' => '' ), $atts );
		$items = self::drill_items( sanitize_key( $a['type'] ) );
		if ( ! $items ) {
			return '';
		}
		self::assets();
		return '<div class="tjq-learn tjq-drill" data-slug="' . esc_attr( sanitize_title( $a['slug'] ) ) . '" data-type="' . esc_attr( sanitize_key( $a['type'] ) ) . '" data-cta="' . esc_url( self::cta_url() ) . '" data-items="' . esc_attr( wp_json_encode( $items, JSON_UNESCAPED_UNICODE ) ) . '"><div class="tjq-drill-head"><strong>Practice round</strong><span class="tjq-drill-stat"></span></div><div class="tjq-drill-body"><noscript>Enable JavaScript to practise.</noscript></div></div>';
	}

	/* ---------- Lesson chrome ---------- */

	private static function nav( $i ) {
		$list = self::curriculum();
		$out  = '';
		if ( $i > 0 && self::link( $list[ $i - 1 ]['slug'] ) ) {
			$out .= '<a class="tjq-nav-prev" href="' . esc_url( self::link( $list[ $i - 1 ]['slug'] ) ) . '">&larr; ' . esc_html( $list[ $i - 1 ]['title'] ) . '</a>';
		}
		if ( $i < count( $list ) - 1 && self::link( $list[ $i + 1 ]['slug'] ) ) {
			$out .= '<a class="tjq-nav-next" href="' . esc_url( self::link( $list[ $i + 1 ]['slug'] ) ) . '">' . esc_html( $list[ $i + 1 ]['title'] ) . ' &rarr;</a>';
		}
		return $out ? '<div class="tjq-lessonnav">' . $out . '</div>' : '';
	}

	public static function lesson_top() {
		$slug = (string) get_post_field( 'post_name', get_the_ID() );
		$i    = self::index_of( $slug );
		if ( $i < 0 ) {
			return '';
		}
		self::assets();
		$l = self::curriculum()[ $i ];
		return '<div class="tjq-learn tjq-lessonbar" data-slug="' . esc_attr( $slug ) . '" data-total="' . count( self::curriculum() ) . '"><div class="tjq-lb-top"><span>Lesson ' . (int) ( $i + 1 ) . ' of ' . count( self::curriculum() ) . ' &middot; ' . esc_html( $l['stage'] ) . '</span><span class="tjq-lb-done" hidden>&#10003; Done</span></div><div class="tjq-bar"><i></i></div><nav class="tjq-toc" aria-label="On this page" hidden></nav></div>';
	}

	public static function lesson_end() {
		$slug = (string) get_post_field( 'post_name', get_the_ID() );
		$i    = self::index_of( $slug );
		if ( $i < 0 ) {
			return '';
		}
		self::assets();
		$type = self::drill_type_for( $slug );
		$out  = '<div class="tjq-learn tjq-lessonend" data-slug="' . esc_attr( $slug ) . '">';
		if ( $type ) {
			$out .= self::drill( array( 'type' => $type, 'slug' => $slug ) );
		}
		$out .= '<button type="button" class="tjq-btn tjq-markdone" data-slug="' . esc_attr( $slug ) . '">Mark this lesson as done</button>' . self::nav( $i ) . '</div>';
		return $out;
	}
}

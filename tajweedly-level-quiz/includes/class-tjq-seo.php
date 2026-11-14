<?php
defined( 'ABSPATH' ) || exit;

/**
 * Blog + SEO features:
 *  - "Test your level" box at the end of every post
 *  - optional one-time popup on posts (scroll / exit intent)
 *  - JSON-LD (WebApplication + FAQPage) on the quiz pages
 *  - one-click creation of the landing pages (as drafts)
 */
class TJQ_SEO {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append_cta' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'popup' ) );
		add_action( 'wp_head', array( __CLASS__, 'schema' ), 5 );
	}

	private static function is_content_page() {
		return is_singular( 'page' ) && get_post_meta( get_the_ID(), '_tjq_page', true );
	}

	private static function on_quiz_page() {
		$q = TJQ_Settings::get( 'quiz_page_url' );
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && ( has_shortcode( $post->post_content, 'tajweedly_quiz' ) || has_shortcode( $post->post_content, 'tajweedly_challenge' ) ) ) {
				return true;
			}
		}
		return $q && is_singular() && untrailingslashit( get_permalink() ) === untrailingslashit( $q );
	}

	/** Level to promote on a post: guessed from its title/tags, else the general test. */
	private static function guess_level( $post ) {
		$hay = strtolower( $post->post_title . ' ' . implode( ' ', wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ) ) );
		if ( false !== strpos( $hay, 'kid' ) || false !== strpos( $hay, 'child' ) || false !== strpos( $hay, 'parent' ) ) {
			return 'kids';
		}
		if ( false !== strpos( $hay, 'beginner' ) ) {
			return 'beginner';
		}
		if ( false !== strpos( $hay, 'advanced' ) || false !== strpos( $hay, 'ijazah' ) ) {
			return 'advanced';
		}
		return '';
	}

	public static function append_cta( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || ! TJQ_Settings::get( 'cta_posts' ) || self::on_quiz_page() ) {
			return $content;
		}
		$post = get_post();
		$box  = TJQ_Shortcode::cta_html( TJQ_Settings::get( 'cta_text' ), self::guess_level( $post ), 'post-end' );
		if ( $box ) {
			wp_enqueue_style( 'tjq-quiz', TJQ_URL . 'assets/quiz.css', array(), TJQ_VERSION );
		}
		return $content . $box;
	}

	public static function popup() {
		if ( ! is_singular( 'post' ) || ! TJQ_Settings::get( 'cta_popup' ) || self::on_quiz_page() ) {
			return;
		}
		$s    = TJQ_Settings::all();
		$base = $s['quiz_page_url'];
		if ( ! $base ) {
			return;
		}
		$level = self::guess_level( get_post() );
		$url   = add_query_arg( array( 'utm_source' => 'blog', 'utm_medium' => 'popup', 'utm_campaign' => 'popup' ), $base );
		if ( $level ) {
			$url = add_query_arg( 'tjq_level', $level, $url );
		}
		wp_enqueue_style( 'tjq-quiz', TJQ_URL . 'assets/quiz.css', array(), TJQ_VERSION );
		?>
		<div id="tjq-pop" class="tjq-pop" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $s['cta_text'] ); ?>" hidden style="--tjq-primary:<?php echo esc_attr( $s['primary_color'] ); ?>;--tjq-accent:<?php echo esc_attr( $s['accent_color'] ); ?>">
			<div class="tjq-pop-box">
				<button type="button" class="tjq-pop-x" aria-label="Close">&times;</button>
				<h3><?php echo esc_html( $s['cta_text'] ); ?></h3>
				<p><?php esc_html_e( 'Free, takes a few minutes, and you get a personal result and study tips.', 'tajweedly-quiz' ); ?></p>
				<a class="tjq-btn" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Take the free test', 'tajweedly-quiz' ); ?></a>
			</div>
		</div>
		<script>
		(function(){
			var el=document.getElementById('tjq-pop');if(!el){return;}
			try{var t=localStorage.getItem('tjq_pop');if(t&&Date.now()-parseInt(t,10)<12096e5){return;}}catch(e){}
			var shown=false;
			function show(){if(shown){return;}shown=true;el.hidden=false;try{localStorage.setItem('tjq_pop',String(Date.now()));}catch(e){}}
			function hide(){el.hidden=true;}
			el.querySelector('.tjq-pop-x').addEventListener('click',hide);
			el.addEventListener('click',function(e){if(e.target===el){hide();}});
			document.addEventListener('keydown',function(e){if(e.key==='Escape'){hide();}});
			window.addEventListener('scroll',function(){
				var d=document.documentElement,p=(window.pageYOffset||d.scrollTop)/Math.max(1,d.scrollHeight-d.clientHeight);
				if(p>0.6){show();}
			},{passive:true});
			document.addEventListener('mouseout',function(e){if(!e.relatedTarget&&e.clientY<5){show();}});
		})();
		</script>
		<?php
	}

	/* ---------- Schema ---------- */

	public static function schema() {
		if ( ! is_singular() || ( ! self::on_quiz_page() && ! self::is_content_page() ) ) {
			return;
		}
		$post = get_post();
		$s    = TJQ_Settings::all();
		$data = array();
		if ( self::is_content_page() ) {
			$data[] = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Article',
				'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
				'description'   => has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '',
				'mainEntityOfPage' => get_permalink( $post ),
				'dateModified'  => get_post_modified_time( 'c', true, $post ),
				'author'        => array( '@type' => 'Organization', 'name' => $s['brand_name'] ),
				'publisher'     => array( '@type' => 'Organization', 'name' => $s['brand_name'], 'url' => home_url( '/' ) ),
			);
			if ( get_post_meta( $post->ID, '_tjq_glossary', true ) ) {
				$terms = array();
				foreach ( TJQ_Content::glossary() as $g ) {
					$terms[] = array( '@type' => 'DefinedTerm', 'name' => $g[0], 'description' => $g[1] );
				}
				$data[] = array( '@context' => 'https://schema.org', '@type' => 'DefinedTermSet', 'name' => 'Tajweed glossary', 'hasDefinedTerm' => $terms );
			}
		}
		if ( self::on_quiz_page() ) {
			$data[] = array(
				'@context'            => 'https://schema.org',
				'@type'               => 'WebApplication',
				'name'                => wp_strip_all_tags( get_the_title( $post ) ),
				'url'                 => get_permalink( $post ),
				'applicationCategory' => 'EducationalApplication',
				'operatingSystem'     => 'Any',
				'inLanguage'          => TJQ_I18n::lang(),
				'isAccessibleForFree' => true,
				'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ),
				'provider'            => array( '@type' => 'Organization', 'name' => $s['brand_name'], 'url' => home_url( '/' ) ),
			);
		}
		$faq = get_post_meta( $post->ID, '_tjq_faq', true );
		if ( is_array( $faq ) && $faq ) {
			$items = array();
			foreach ( $faq as $f ) {
				$items[] = array(
					'@type'          => 'Question',
					'name'           => $f[0],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ),
				);
			}
			$data[] = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items );
		}
		foreach ( $data as $d ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ---------- Landing pages ---------- */

	public static function page_specs() {
		$common_faq = array(
			array( 'Is the Tajweed test free?', 'Yes. The test is completely free and takes only a few minutes. You get your result straight away.' ),
			array( 'Do I need to give my email?', 'No. You see your score and recommended next step without it. Your email is only needed if you want the result, a cheat sheet and a free trial lesson sent to you.' ),
			array( 'What is Tajweed?', 'Tajweed is the set of rules for reciting the Quran correctly: giving every letter its proper sound, length and characteristics.' ),
		);
		return array(
			'general'      => array(
				'slug'    => 'tajweed-test',
				'title'   => 'Free Tajweed Test Online: Check Your Level in 5 Minutes',
				'desc'    => 'Take a free online Tajweed test. Choose beginner, intermediate or advanced, get your score and a recommended next step.',
				'intro'   => 'How good is your Tajweed? Choose a level below, answer 10 short questions and get your result instantly, plus the best next step for your recitation.',
				'code'    => '[tajweedly_quiz]',
				'faq'     => $common_faq,
			),
			'beginner'     => array(
				'slug'    => 'tajweed-test-beginner',
				'title'   => 'Tajweed Quiz for Beginners: Test Your Basics',
				'desc'    => 'A free beginner Tajweed quiz covering the Arabic letters, harakat, sukoon, shaddah, madd and makharij.',
				'intro'   => 'New to Tajweed? This quick beginner quiz checks the basics: the letters, the vowels and signs, and the first rules of recitation.',
				'code'    => '[tajweedly_quiz level="beginner"]',
				'faq'     => array_merge( array( array( 'What does the beginner Tajweed quiz cover?', 'Arabic letters, short vowels (fatha, kasra, damma), sukoon, shaddah, tanween, the letters of madd, makharij and waqf.' ) ), $common_faq ),
			),
			'intermediate' => array(
				'slug'    => 'tajweed-test-intermediate',
				'title'   => 'Intermediate Tajweed Quiz: Noon, Meem, Madd & Qalqalah',
				'desc'    => 'Test the Tajweed rules most readers stumble on: noon sakinah and tanween, meem sakinah, madd, ghunnah and qalqalah.',
				'intro'   => 'You can read the Quran and know some rules. This quiz checks whether you apply izhar, idgham, iqlab, ikhfa, ghunnah and qalqalah correctly.',
				'code'    => '[tajweedly_quiz level="intermediate"]',
				'faq'     => array_merge( array( array( 'What are the rules of Noon Sakinah and Tanween?', 'There are four: Izhar (clear), Idgham (merging), Iqlab (switching to a hidden meem) and Ikhfa (hiding).' ) ), $common_faq ),
			),
			'advanced'     => array(
				'slug'    => 'tajweed-test-advanced',
				'title'   => 'Advanced Tajweed Quiz: Madd, Tafkheem & Waqf',
				'desc'    => 'An advanced Tajweed quiz on the types of madd, heavy and light letters, ghunnah and stopping rules.',
				'intro'   => 'Ready for the finer points? This quiz covers the types of madd, tafkheem and tarqeeq, ghunnah and waqf, the level of detail needed for an ijazah.',
				'code'    => '[tajweedly_quiz level="advanced"]',
				'faq'     => array_merge( array( array( 'How many counts is Madd Lazim?', 'Madd Lazim (a madd letter followed by a permanent sukoon or shaddah) is held for 6 counts.' ) ), $common_faq ),
			),
			'kids'         => array(
				'slug'    => 'is-my-child-ready-to-learn-quran',
				'title'   => 'Is My Child Ready to Learn Quran? Free Readiness Test',
				'desc'    => 'A short parents\' test that shows whether your child is ready to start Quran lessons online, and the best way to begin.',
				'intro'   => 'Not sure when to start? Answer 7 quick questions about your child and get a clear recommendation, with the right pace and plan for their age.',
				'code'    => '[tajweedly_quiz level="kids"]',
				'faq'     => array_merge( array( array( 'At what age can a child start learning Quran online?', 'Many children begin around age 5 or 6 with the Arabic letters and short, playful lessons. Readiness depends more on attention span and enthusiasm than on a fixed age.' ) ), $common_faq ),
			),
			'challenge'    => array(
				'slug'    => 'tajweed-challenge',
				'title'   => 'Free 14-Day Tajweed Challenge by Email',
				'desc'    => 'One short Tajweed lesson a day for 14 days, delivered by email. Free.',
				'intro'   => 'Improve your recitation in two weeks. Each day you get a five-minute lesson with a small practice task. Join free.',
				'code'    => '[tajweedly_challenge]',
				'faq'     => $common_faq,
			),
		);
	}

	/**
	 * Creates pages from specs (slug => spec) as DRAFTS, skipping slugs that exist.
	 * Content specs are composed by TJQ_Content::compose(); every page gets the _tjq_page marker.
	 */
	public static function create_from_specs( array $specs, array $extra_meta = array() ) {
		$created  = 0;
		$existing = 0;
		foreach ( $specs as $slug => $spec ) {
			$slug = isset( $spec['slug'] ) ? $spec['slug'] : $slug;
			if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
				++$existing;
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $spec['title'],
					'post_name'    => $slug,
					'post_content' => TJQ_Content::compose( $spec ),
					'post_excerpt' => $spec['desc'],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_tjq_page', 1 );
				update_post_meta( $id, '_tjq_faq', isset( $spec['faq'] ) ? $spec['faq'] : array() );
				update_post_meta( $id, '_yoast_wpseo_metadesc', $spec['desc'] );
				update_post_meta( $id, 'rank_math_description', $spec['desc'] );
				update_post_meta( $id, '_yoast_wpseo_title', $spec['title'] );
				update_post_meta( $id, 'rank_math_title', $spec['title'] );
				if ( isset( $extra_meta[ $slug ] ) ) {
					foreach ( $extra_meta[ $slug ] as $k => $v ) {
						update_post_meta( $id, $k, $v );
					}
				}
				++$created;
			}
		}
		return array( $created, $existing );
	}

	public static function create_content_pages() {
		$specs                             = TJQ_Content::specs();
		$specs['tajweed-glossary']         = TJQ_Content::glossary_spec();
		$specs['quran-with-tajweed-colors'] = TJQ_Content::reader_spec();
		$specs['ramadan-quran-challenge']  = TJQ_Content::ramadan_spec();
		return self::create_from_specs( $specs, array( 'tajweed-glossary' => array( '_tjq_glossary' => 1 ) ) );
	}

	public static function create_surah_pages( array $numbers ) {
		$specs = array();
		foreach ( $numbers as $n ) {
			$n = (int) $n;
			if ( $n >= 1 && $n <= 114 ) {
				$spec                   = TJQ_Content::surah_spec( $n );
				$specs[ $spec['slug'] ] = $spec;
			}
		}
		return self::create_from_specs( $specs );
	}

	/** Creates the landing pages as DRAFTS (skips slugs that already exist). Returns [created, existing]. */
	public static function create_pages() {
		$created  = 0;
		$existing = 0;
		$first_id = 0;
		foreach ( self::page_specs() as $key => $spec ) {
			$found = get_page_by_path( $spec['slug'], OBJECT, 'page' );
			if ( $found ) {
				++$existing;
				if ( 'general' === $key ) {
					$first_id = $found->ID;
				}
				continue;
			}
			$body  = '<p>' . esc_html( $spec['intro'] ) . "</p>\n\n" . $spec['code'] . "\n\n<h2>FAQ</h2>\n";
			foreach ( $spec['faq'] as $f ) {
				$body .= '<h3>' . esc_html( $f[0] ) . '</h3><p>' . esc_html( $f[1] ) . "</p>\n";
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $spec['title'],
					'post_name'    => $spec['slug'],
					'post_content' => $body,
					'post_excerpt' => $spec['desc'],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_tjq_faq', $spec['faq'] );
				update_post_meta( $id, '_yoast_wpseo_metadesc', $spec['desc'] );
				update_post_meta( $id, 'rank_math_description', $spec['desc'] );
				update_post_meta( $id, '_yoast_wpseo_title', $spec['title'] );
				update_post_meta( $id, 'rank_math_title', $spec['title'] );
				++$created;
				if ( 'general' === $key ) {
					$first_id = $id;
				}
			}
		}
		if ( $first_id && ! TJQ_Settings::get( 'quiz_page_url' ) ) {
			$opts                  = TJQ_Settings::all();
			$opts['quiz_page_url'] = get_permalink( $first_id );
			update_option( TJQ_Settings::OPTION, $opts );
		}
		return array( $created, $existing );
	}
}

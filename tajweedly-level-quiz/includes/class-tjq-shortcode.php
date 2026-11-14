<?php
defined( 'ABSPATH' ) || exit;

/**
 * [tajweedly_quiz]       the tests. Attributes: level (beginner|intermediate|advanced|kids), goal_step="no", title, intro
 * [tajweedly_challenge]  the 14-day email challenge sign-up form. Attributes: title, intro
 * [tajweedly_cta]        a plain link box (no JavaScript) pointing to the quiz. Attributes: text, level
 */
class TJQ_Shortcode {

	public static function init() {
		add_shortcode( 'tajweedly_quiz', array( __CLASS__, 'quiz' ) );
		add_shortcode( 'tajweedly_challenge', array( __CLASS__, 'challenge' ) );
		add_shortcode( 'tajweedly_cta', array( __CLASS__, 'cta' ) );
	}

	private static function assets( $s, $lang ) {
		if ( $s['font_url'] ) {
			wp_enqueue_style( 'tjq-font', esc_url_raw( $s['font_url'] ), array(), null );
		}
		wp_enqueue_style( 'tjq-quiz', TJQ_URL . 'assets/quiz.css', array(), TJQ_VERSION );
		wp_enqueue_script( 'tjq-quiz', TJQ_URL . 'assets/quiz.js', array(), TJQ_VERSION, true );

		$tests  = TJQ_Questions::tests();
		$labels = TJQ_I18n::test_labels( $lang );
		$lab    = array();
		foreach ( array_keys( $tests ) as $k ) {
			$lab[ $k ] = isset( $labels[ $k ] ) ? $labels[ $k ] : array( $tests[ $k ]['label'], $tests[ $k ]['desc'] );
		}
		wp_localize_script(
			'tjq-quiz',
			'TJQ_CONFIG',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'tajweedly/v1/' ) ),
				'questions'   => TJQ_Questions::public_by_level(),
				'labels'      => $lab,
				'goals'       => TJQ_Questions::goals(),
				'consentText' => $s['consent_text'],
				'privacyUrl'  => $s['privacy_url'],
				'trialUrl'    => $s['trial_url'],
				'whatsapp'    => $s['whatsapp'],
				'brand'       => $s['brand_name'],
				'quizUrl'     => $s['quiz_page_url'],
				'abA'         => $s['ab_a_text'],
				'abB'         => $s['ab_b_text'],
				'rtl'         => TJQ_I18n::is_rtl( $lang ),
				'i18n'        => TJQ_I18n::strings( $lang ),
			)
		);
	}

	private static function root( $s, $lang, $mode, $atts, $extra = array() ) {
		$style = sprintf( '--tjq-primary:%s;--tjq-accent:%s;', esc_attr( $s['primary_color'] ), esc_attr( $s['accent_color'] ) );
		if ( $s['font_family'] ) {
			$style .= '--tjq-font:' . esc_attr( $s['font_family'] ) . ';';
		}
		ob_start();
		?>
		<div class="tjq" id="tjq-app" dir="<?php echo TJQ_I18n::is_rtl( $lang ) ? 'rtl' : 'ltr'; ?>" lang="<?php echo esc_attr( $lang ); ?>" style="<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput ?>" data-mode="<?php echo esc_attr( $mode ); ?>" data-level="<?php echo esc_attr( isset( $extra['level'] ) ? $extra['level'] : '' ); ?>" data-goalstep="<?php echo esc_attr( isset( $extra['goalstep'] ) ? $extra['goalstep'] : '1' ); ?>" data-title="<?php echo esc_attr( $atts['title'] ); ?>" data-intro="<?php echo esc_attr( $atts['intro'] ); ?>" data-logo="<?php echo esc_url( $s['logo_url'] ); ?>">
			<noscript><?php esc_html_e( 'Please enable JavaScript to use this tool.', 'tajweedly-quiz' ); ?></noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function quiz( $atts ) {
		$s    = TJQ_Settings::all();
		$lang = TJQ_I18n::lang();
		$str  = TJQ_I18n::strings( $lang );
		$atts = shortcode_atts(
			array(
				'level'     => '',
				'goal_step' => 'yes',
				'title'     => 'ar' === $lang || 'ur' === $lang || 'fr' === $lang ? '' : __( 'Test your Tajweed level', 'tajweedly-quiz' ),
				'intro'     => 'ar' === $lang || 'ur' === $lang || 'fr' === $lang ? '' : __( 'Choose a test. A few quick questions. Get your result and the right next step for your recitation.', 'tajweedly-quiz' ),
			),
			$atts,
			'tajweedly_quiz'
		);
		if ( '' === $atts['title'] ) {
			$map           = array( 'ar' => 'اختبر مستواك في التجويد', 'ur' => 'اپنی تجوید کی سطح جانچیں', 'fr' => 'Testez votre niveau de Tajweed' );
			$atts['title'] = $map[ $lang ];
			$map           = array( 'ar' => 'اختر اختبارًا، أسئلة قصيرة، واحصل على نتيجتك والخطوة التالية المناسبة.', 'ur' => 'ٹیسٹ منتخب کریں، چند مختصر سوالات، اور اپنا نتیجہ حاصل کریں۔', 'fr' => 'Choisissez un test, quelques questions rapides, et obtenez votre résultat et la prochaine étape.' );
			$atts['intro'] = $map[ $lang ];
		}
		unset( $str );
		self::assets( $s, $lang );
		$fixed = isset( TJQ_Questions::tests()[ $atts['level'] ] ) ? $atts['level'] : '';
		return self::root( $s, $lang, 'quiz', $atts, array( 'level' => $fixed, 'goalstep' => 'no' === $atts['goal_step'] ? '0' : '1' ) );
	}

	public static function challenge( $atts ) {
		$s    = TJQ_Settings::all();
		$lang = TJQ_I18n::lang();
		$str  = TJQ_I18n::strings( $lang );
		$atts = shortcode_atts(
			array(
				'title' => $str['challengeTitle'],
				'intro' => $str['challengeIntro'],
			),
			$atts,
			'tajweedly_challenge'
		);
		self::assets( $s, $lang );
		return self::root( $s, $lang, 'challenge', $atts, array( 'level' => '', 'goalstep' => '0' ) );
	}

	/** Static (no-JS) link box. Safe for caching, RSS and AMP. */
	public static function cta( $atts ) {
		$s    = TJQ_Settings::all();
		$atts = shortcode_atts( array( 'text' => $s['cta_text'], 'level' => '' ), $atts, 'tajweedly_cta' );
		return self::cta_html( $atts['text'], $atts['level'], 'cta' );
	}

	public static function cta_html( $text, $level = '', $campaign = 'cta' ) {
		$s    = TJQ_Settings::all();
		$base = $s['quiz_page_url'];
		if ( ! $base ) {
			return '';
		}
		$url = add_query_arg(
			array(
				'utm_source'   => 'blog',
				'utm_medium'   => 'cta',
				'utm_campaign' => $campaign,
			),
			$base
		);
		if ( $level ) {
			$url = add_query_arg( 'tjq_level', sanitize_key( $level ), $url );
		}
		return sprintf(
			'<div class="tjq-cta" style="--tjq-primary:%s;--tjq-accent:%s"><strong>%s</strong><a class="tjq-btn" href="%s">%s</a></div>',
			esc_attr( $s['primary_color'] ),
			esc_attr( $s['accent_color'] ),
			esc_html( $text ),
			esc_url( $url ),
			esc_html__( 'Take the free test', 'tajweedly-quiz' )
		);
	}
}

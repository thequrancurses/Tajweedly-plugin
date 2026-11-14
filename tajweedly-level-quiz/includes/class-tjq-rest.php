<?php
defined( 'ABSPATH' ) || exit;

/**
 * Public REST API. Scoring is done server-side so answers never reach the browser.
 * No nonce is used on purpose: the endpoints are public and page-cache friendly,
 * and are protected by a honeypot, per-IP rate limiting and a per-attempt token.
 *
 *   POST /result     score a test (3 levels or "kids")
 *   POST /lead       attach email + consent to an attempt
 *   POST /challenge  sign up for the 14-day email challenge
 *   POST /progress   email me a link to finish later
 *   GET  /resume     restore saved progress
 */
class TJQ_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		foreach ( array( 'result', 'lead', 'challenge', 'progress' ) as $r ) {
			register_rest_route( 'tajweedly/v1', '/' . $r, array( 'methods' => 'POST', 'callback' => array( __CLASS__, $r ), 'permission_callback' => '__return_true' ) );
		}
		register_rest_route( 'tajweedly/v1', '/resume', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'resume' ), 'permission_callback' => '__return_true' ) );
	}

	private static function limited( $bucket, $max ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'tjq_rl_' . $bucket . '_' . md5( $ip . wp_salt() );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	private static function params( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		return is_array( $p ) ? $p : array();
	}

	private static function str( $arr, $key, $max ) {
		return isset( $arr[ $key ] ) ? substr( sanitize_text_field( (string) $arr[ $key ] ), 0, $max ) : '';
	}

	private static function course_url( $level ) {
		$url = TJQ_Settings::get( 'url_' . $level );
		return $url ? $url : home_url( '/' );
	}

	/** Valid referral code or empty string. */
	private static function ref( $code ) {
		global $wpdb;
		$code = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $code ) );
		if ( strlen( $code ) < 6 || strlen( $code ) > 12 ) {
			return '';
		}
		$ok = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . TJQ_DB::table() . ' WHERE ref_code = %s LIMIT 1', $code ) );
		return $ok ? $code : '';
	}

	private static function tracking( $p ) {
		$v = self::str( $p, 'variant', 1 );
		return array(
			'variant'      => in_array( strtolower( $v ), array( 'a', 'b' ), true ) ? strtolower( $v ) : '',
			'referred_by'  => self::ref( isset( $p['ref'] ) ? $p['ref'] : '' ),
			'utm_source'   => self::str( $p, 'utm_source', 100 ),
			'utm_medium'   => self::str( $p, 'utm_medium', 100 ),
			'utm_campaign' => self::str( $p, 'utm_campaign', 100 ),
			'referrer'     => substr( esc_url_raw( isset( $p['referrer'] ) ? $p['referrer'] : '' ), 0, 255 ),
			'page_url'     => substr( esc_url_raw( isset( $p['page_url'] ) ? $p['page_url'] : '' ), 0, 255 ),
		);
	}

	/* ---------- /result ---------- */

	public static function result( WP_REST_Request $req ) {
		$p = self::params( $req );
		if ( ! empty( $p['website'] ) ) { // honeypot
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}
		if ( self::limited( 'result', 30 ) ) {
			return new WP_Error( 'tjq_rate', 'Too many attempts. Please try again later.', array( 'status' => 429 ) );
		}
		$tests = TJQ_Questions::tests();
		$level = isset( $p['level'] ) ? sanitize_key( $p['level'] ) : '';
		if ( ! isset( $tests[ $level ] ) ) {
			return new WP_Error( 'tjq_level', 'Unknown test.', array( 'status' => 400 ) );
		}
		$answers = isset( $p['answers'] ) && is_array( $p['answers'] ) ? $p['answers'] : array();
		$score   = 0;
		$total   = 0;
		$review  = array();
		$meta    = array();

		if ( 'kids' === $level ) {
			$total = TJQ_Questions::kids_max();
			foreach ( TJQ_Questions::kids() as $k ) {
				$given = isset( $answers[ $k['id'] ] ) ? (int) $answers[ $k['id'] ] : -1;
				if ( isset( $k['points'][ $given ] ) ) {
					$score        += (int) $k['points'][ $given ];
					$meta[ $k['key'] ] = $k['options'][ $given ];
				}
			}
		} else {
			$bank  = TJQ_Questions::for_level( $level );
			$total = count( $bank );
			$missed = array();
			foreach ( $bank as $q ) {
				$given = isset( $answers[ $q['id'] ] ) ? (int) $answers[ $q['id'] ] : -1;
				$ok    = ( $given === (int) $q['correct'] );
				if ( $ok ) {
					++$score;
				} else {
					$missed[] = $q['id'];
				}
				$review[] = array(
					'id'      => $q['id'],
					'q'       => $q['q'],
					'correct' => $ok,
					'answer'  => $q['options'][ $q['correct'] ],
					'explain' => $q['explain'],
				);
			}
		}
		if ( 'kids' !== $level ) {
			$meta = array( 'missed' => $missed );
		}
		$info  = TJQ_Questions::info( $level, $score, $total );
		$goals = TJQ_Questions::goals();
		$goal  = isset( $p['goal'] ) && isset( $goals[ $p['goal'] ] ) ? $p['goal'] : '';

		$id = TJQ_DB::insert(
			array_merge(
				self::tracking( $p ),
				array(
					'score' => $score,
					'total' => $total,
					'level' => $level,
					'band'  => $info['band'],
					'goal'  => $goal,
					'meta'  => $meta ? wp_json_encode( $meta ) : null,
				)
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'tjq_db', 'Could not save your result.', array( 'status' => 500 ) );
		}
		$row = TJQ_DB::get( $id );

		return array(
			'ok'         => true,
			'attempt_id' => $id,
			'token'      => $row->token,
			'score'      => $score,
			'total'      => $total,
			'level'      => $level,
			'band'       => $info['band'],
			'label'      => $info['label'],
			'message'    => $info['message'],
			'course'     => $info['course'],
			'course_url' => self::course_url( $info['course_level'] ),
			'ref_code'   => $row->ref_code,
			'review'     => $review,
		);
	}

	/* ---------- /lead ---------- */

	public static function lead( WP_REST_Request $req ) {
		$p = self::params( $req );
		if ( ! empty( $p['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		if ( self::limited( 'lead', 8 ) ) {
			return new WP_Error( 'tjq_rate', 'Too many attempts. Please try again later.', array( 'status' => 429 ) );
		}
		$row = TJQ_DB::get( isset( $p['attempt_id'] ) ? (int) $p['attempt_id'] : 0 );
		if ( ! $row || empty( $p['token'] ) || ! hash_equals( $row->token, (string) $p['token'] ) ) {
			return new WP_Error( 'tjq_token', 'Invalid request.', array( 'status' => 403 ) );
		}
		$email = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'tjq_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( empty( $p['consent'] ) ) {
			return new WP_Error( 'tjq_consent', 'Please tick the consent box so we can email you.', array( 'status' => 400 ) );
		}
		$resp = array(
			'ok'           => true,
			'trial_url'    => TJQ_Settings::get( 'trial_url' ),
			'calendly_url' => TJQ_Settings::get( 'calendly_url' ),
			'ref_url'      => TJQ_Mailer::ref_url( $row ),
			'ref_reward'   => TJQ_Settings::get( 'ref_reward' ),
			'sheet_url'    => TJQ_Sheet::url( $row ),
		);
		if ( $row->email ) {
			return $resp; // already captured
		}
		$name = self::str( $p, 'name', 120 );
		TJQ_DB::update(
			$row->id,
			array(
				'name'         => $name,
				'email'        => $email,
				'consent'      => 1,
				'consent_text' => TJQ_Settings::get( 'consent_text' ),
				'consent_at'   => current_time( 'mysql' ),
			)
		);
		$row  = TJQ_DB::get( $row->id );
		$info = TJQ_Questions::info( $row->level, (int) $row->score, (int) $row->total );

		TJQ_Mailer::send_result( $row, $info, self::course_url( $info['course_level'] ) );
		TJQ_Mailer::notify_admin( $row );
		TJQ_Mailer::webhook( $row );
		TJQ_Drip::start( $row->id, $row->level );
		do_action( 'tjq_lead_captured', $row );

		$resp['name'] = $name;
		return $resp;
	}

	/* ---------- /challenge ---------- */

	public static function challenge( WP_REST_Request $req ) {
		$p = self::params( $req );
		if ( ! empty( $p['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		if ( self::limited( 'challenge', 6 ) ) {
			return new WP_Error( 'tjq_rate', 'Too many attempts. Please try again later.', array( 'status' => 429 ) );
		}
		$email = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'tjq_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( empty( $p['consent'] ) ) {
			return new WP_Error( 'tjq_consent', 'Please tick the consent box so we can email you.', array( 'status' => 400 ) );
		}
		$id = TJQ_DB::insert(
			array_merge(
				self::tracking( $p ),
				array(
					'level'        => 'challenge',
					'band'         => 'challenge',
					'name'         => self::str( $p, 'name', 120 ),
					'email'        => $email,
					'consent'      => 1,
					'consent_text' => TJQ_Settings::get( 'consent_text' ),
					'consent_at'   => current_time( 'mysql' ),
				)
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'tjq_db', 'Could not save your sign-up.', array( 'status' => 500 ) );
		}
		TJQ_Drip::start( $id, 'challenge' );
		TJQ_DB::update( $id, array( 'drip_next' => gmdate( 'Y-m-d H:i:s' ) ) ); // lesson 1 goes out on the next cron run.
		$row  = TJQ_DB::get( $id );
		$name = TJQ_Settings::get( 'challenge_name' );
		TJQ_Mailer::send(
			$row,
			sprintf( 'Welcome to the %s', $name ),
			'<p>' . esc_html( sprintf( 'Assalamu alaikum %s,', $row->name ? $row->name : 'there' ) ) . '</p><p>' . esc_html( sprintf( 'You are in! Over the next 14 days you will get one short Tajweed lesson a day by email: about 5 minutes each. Lesson 1 arrives shortly. Your free cheat sheet:', $name ) ) . '</p>' . TJQ_Mailer::button( TJQ_Sheet::url( $row ), 'Open the cheat sheet' )
		);
		TJQ_Mailer::notify_admin( $row );
		TJQ_Mailer::webhook( $row );
		do_action( 'tjq_lead_captured', $row );
		return array( 'ok' => true, 'ref_url' => TJQ_Mailer::ref_url( $row ), 'ref_reward' => TJQ_Settings::get( 'ref_reward' ) );
	}

	/* ---------- /progress and /resume ---------- */

	public static function progress( WP_REST_Request $req ) {
		$p = self::params( $req );
		if ( ! empty( $p['website'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
		if ( self::limited( 'progress', 5 ) ) {
			return new WP_Error( 'tjq_rate', 'Too many attempts. Please try again later.', array( 'status' => 429 ) );
		}
		$tests = TJQ_Questions::tests();
		$level = isset( $p['level'] ) ? sanitize_key( $p['level'] ) : '';
		$email = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
		if ( ! isset( $tests[ $level ] ) || ! is_email( $email ) ) {
			return new WP_Error( 'tjq_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
		}
		if ( empty( $p['consent'] ) ) {
			return new WP_Error( 'tjq_consent', 'Please tick the consent box so we can email you.', array( 'status' => 400 ) );
		}
		$answers = array();
		if ( isset( $p['answers'] ) && is_array( $p['answers'] ) ) {
			foreach ( $p['answers'] as $k => $v ) {
				$answers[ sanitize_key( $k ) ] = (int) $v;
			}
		}
		$id = TJQ_DB::insert(
			array_merge(
				self::tracking( $p ),
				array(
					'level'        => $level,
					'band'         => 'incomplete',
					'name'         => self::str( $p, 'name', 120 ),
					'email'        => $email,
					'consent'      => 1,
					'consent_text' => TJQ_Settings::get( 'consent_text' ),
					'consent_at'   => current_time( 'mysql' ),
					'meta'         => wp_json_encode( array( 'progress' => array( 'level' => $level, 'answers' => $answers, 'i' => max( 0, (int) ( isset( $p['i'] ) ? $p['i'] : 0 ) ) ) ) ),
				)
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'tjq_db', 'Could not save your progress.', array( 'status' => 500 ) );
		}
		TJQ_Drip::start( $id, 'resume' );
		TJQ_DB::update( $id, array( 'drip_next' => gmdate( 'Y-m-d H:i:s', time() + 4 * HOUR_IN_SECONDS ) ) ); // reminder after 4 hours.
		$row  = TJQ_DB::get( $id );
		$base = TJQ_Settings::get( 'quiz_page_url' ) ? TJQ_Settings::get( 'quiz_page_url' ) : home_url( '/' );
		TJQ_Mailer::send(
			$row,
			'Your Tajweed test link (progress saved)',
			'<p>' . esc_html( 'Your answers are saved. Continue whenever you like:' ) . '</p>' . TJQ_Mailer::button( add_query_arg( 'tjq_resume', $row->token, $base ), 'Continue my test' )
		);
		return array( 'ok' => true );
	}

	public static function resume( WP_REST_Request $req ) {
		global $wpdb;
		$token = sanitize_text_field( (string) $req->get_param( 'token' ) );
		if ( strlen( $token ) !== 32 || self::limited( 'resume', 30 ) ) {
			return new WP_Error( 'tjq_token', 'Invalid link.', array( 'status' => 400 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TJQ_DB::table() . ' WHERE token = %s', $token ) );
		$m   = $row && $row->meta ? json_decode( $row->meta, true ) : null;
		if ( ! $row || empty( $m['progress'] ) ) {
			return new WP_Error( 'tjq_token', 'Invalid link.', array( 'status' => 404 ) );
		}
		// Stop the reminder: they came back.
		TJQ_DB::update( $row->id, array( 'drip_next' => null ) );
		return array( 'ok' => true, 'progress' => $m['progress'], 'name' => $row->name, 'email' => $row->email );
	}
}

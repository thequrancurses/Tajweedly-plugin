<?php
defined( 'ABSPATH' ) || exit;

/**
 * Follow-up email sequences (only for people who ticked the consent box).
 * Tracks: beginner, intermediate, advanced, kids, challenge (14 days), resume (one reminder).
 * Sending stops when the person unsubscribes or their status becomes trial / paid / lost.
 */
class TJQ_Drip {

	const HOOK = 'tjq_drip_cron';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Days to wait before each step, per track. */
	private static function delays( $track ) {
		switch ( $track ) {
			case 'challenge':
				return array_fill( 0, 14, 1 );
			case 'resume':
				return array( 1 );
			default:
				return array( 1, 2, 3, 4 ); // After result: day 1, 3, 6, 10.
		}
	}

	public static function start( $id, $track ) {
		if ( ! TJQ_Settings::get( 'drip_enabled' ) ) {
			return;
		}
		$d = self::delays( $track );
		TJQ_DB::update(
			$id,
			array(
				'drip_track' => $track,
				'drip_step'  => 0,
				'drip_next'  => gmdate( 'Y-m-d H:i:s', time() + $d[0] * DAY_IN_SECONDS ),
			)
		);
	}

	public static function run() {
		if ( ! TJQ_Settings::get( 'drip_enabled' ) ) {
			return;
		}
		foreach ( TJQ_DB::drip_due( 40 ) as $row ) {
			$step   = (int) $row->drip_step;
			$delays = self::delays( $row->drip_track );
			$mail   = self::build( $row, $step );
			if ( $mail ) {
				TJQ_Mailer::send( $row, $mail['subject'], $mail['html'] );
			}
			$next = $step + 1;
			if ( $next < count( $delays ) ) {
				TJQ_DB::update( $row->id, array( 'drip_step' => $next, 'drip_next' => gmdate( 'Y-m-d H:i:s', time() + $delays[ $next ] * DAY_IN_SECONDS ) ) );
			} else {
				TJQ_DB::update( $row->id, array( 'drip_step' => $next, 'drip_next' => null ) );
			}
		}
	}

	private static function p( $text ) {
		return '<p>' . esc_html( $text ) . '</p>';
	}

	private static function greet( $row ) {
		$name = $row->name ? $row->name : __( 'there', 'tajweedly-quiz' );
		return '<p>' . sprintf( esc_html__( 'Assalamu alaikum %s,', 'tajweedly-quiz' ), esc_html( $name ) ) . '</p>';
	}

	private static function trial_cta() {
		$url = TJQ_Settings::get( 'calendly_url' ) ? TJQ_Settings::get( 'calendly_url' ) : TJQ_Settings::get( 'trial_url' );
		return $url ? TJQ_Mailer::button( $url, __( 'Book your free trial lesson', 'tajweedly-quiz' ) ) : '';
	}

	/** @return array|null { subject, html } */
	public static function build( $row, $step ) {
		$track = $row->drip_track;
		if ( 'challenge' === $track ) {
			return self::challenge_mail( $row, $step );
		}
		if ( 'resume' === $track ) {
			$base = TJQ_Settings::get( 'quiz_page_url' ) ? TJQ_Settings::get( 'quiz_page_url' ) : home_url( '/' );
			$link = add_query_arg( 'tjq_resume', $row->token, $base );
			return array(
				'subject' => __( 'Finish your Tajweed test (your answers are saved)', 'tajweedly-quiz' ),
				'html'    => self::greet( $row ) . self::p( __( 'You started the Tajweed test and your answers are saved. It takes just a couple of minutes to finish and get your result.', 'tajweedly-quiz' ) ) . TJQ_Mailer::button( $link, __( 'Continue my test', 'tajweedly-quiz' ) ),
			);
		}

		$kids = ( 'kids' === $track );
		$lvl  = $kids ? 'beginner' : $track;
		$mails = array(
			// Step 0 (day 1): practical tips.
			0 => array(
				'subject' => $kids ? __( '3 ways to make Quran time easy for your child', 'tajweedly-quiz' ) : __( '3 tips to improve your Tajweed this week', 'tajweedly-quiz' ),
				'html'    => self::greet( $row ) . ( $kids
					? self::p( __( '1) Keep lessons short and at the same time every day. 2) Praise effort, not only accuracy. 3) Let your child hear the teacher recite first, then repeat. Children learn Quran by ear before they learn it by eye.', 'tajweedly-quiz' ) )
					: self::p( __( '1) Recite 5 minutes a day out loud, slowly. 2) Record yourself and listen back: you will hear mistakes you cannot feel. 3) Pick ONE rule per week and look for it in everything you read.', 'tajweedly-quiz' ) ) ),
			),
			// Step 1 (day 3): free sheet.
			1 => array(
				'subject' => __( 'Your free Tajweed cheat sheet', 'tajweedly-quiz' ),
				'html'    => self::greet( $row ) . self::p( __( 'Here is your printable cheat sheet again. Save it as PDF and keep it next to your Quran.', 'tajweedly-quiz' ) ) . TJQ_Mailer::button( TJQ_Sheet::url( $row ), __( 'Open the cheat sheet', 'tajweedly-quiz' ) ),
			),
			// Step 2 (day 6): why a teacher.
			2 => array(
				'subject' => __( 'Why live correction changes everything', 'tajweedly-quiz' ),
				'html'    => self::greet( $row ) . self::p( __( 'Apps and videos can teach the rules, but only a teacher can hear YOUR mistakes. One 30-minute live lesson often fixes habits that months of self-study do not.', 'tajweedly-quiz' ) ) . self::trial_cta(),
			),
			// Step 3 (day 10): last reminder + referral.
			3 => array(
				'subject' => __( 'Your free trial lesson is still waiting', 'tajweedly-quiz' ),
				'html'    => self::greet( $row ) . self::p( __( 'A quick reminder: your free trial lesson is still available. No commitment, just a chance to meet a teacher and hear your recitation corrected.', 'tajweedly-quiz' ) ) . self::trial_cta() . '<p>' . sprintf( esc_html__( 'Know someone who would benefit? Share this link and you both get %1$s: %2$s', 'tajweedly-quiz' ), esc_html( TJQ_Settings::get( 'ref_reward' ) ), '<a href="' . esc_url( TJQ_Mailer::ref_url( $row ) ) . '">' . esc_html( TJQ_Mailer::ref_url( $row ) ) . '</a>' ) . '</p>',
			),
		);
		unset( $lvl );
		return isset( $mails[ $step ] ) ? $mails[ $step ] : null;
	}

	/** The 14-day challenge. One small lesson per day. */
	private static function lessons() {
		return array(
			array( 'Day 1: Your starting point', 'Recite the first page of Surah Al-Baqarah (or any page you know) once, recording yourself. Do not judge it yet. This is your "before" recording.' ),
			array( 'Day 2: Letters and where they come from', 'Spend 5 minutes on the places letters come out (makharij): throat, tongue, lips. Say ء ه ع ح غ خ slowly and feel the throat.' ),
			array( 'Day 3: Short vowels', 'Fatha, kasra, damma: keep them short and clean. Recite Surah Al-Fatiha and check that no vowel is stretched by accident.' ),
			array( 'Day 4: Sukoon and shaddah', 'A letter with sukoon has no vowel. A letter with shaddah is doubled. Find 5 of each in Surah Al-Ikhlas and Surah Al-Falaq.' ),
			array( 'Day 5: Natural madd (2 counts)', 'Alif, waw and ya extend the vowel before them for 2 counts. Clap twice as you recite Surah Al-Fatiha to feel the length.' ),
			array( 'Day 6: Noon sakinah and tanween: Izhar', 'After the six throat letters ء ه ع ح غ خ, pronounce the noon clearly with no ghunnah. Example: مَنْ آمَنَ.' ),
			array( 'Day 7: Ikhfa', 'Before the 15 Ikhfa letters, hide the noon with a light ghunnah. Practise with: مِن تَحْتِهَا ، أَنفُسَكُمْ.' ),
			array( 'Day 8: Idgham', 'Before ي ن م و the noon merges with ghunnah (مَن يَقُولُ). Before ل and ر it merges with no ghunnah (مِن رَّبِّهِمْ).' ),
			array( 'Day 9: Iqlab', 'Before ب, the noon changes into a hidden meem with ghunnah. Example: مِن بَعْدِ. Say it 10 times slowly.' ),
			array( 'Day 10: Meem sakinah', 'Before ب: Ikhfa Shafawi. Before م: Idgham Shafawi. Everything else: Izhar Shafawi. Find one of each in Surah Al-Fil.' ),
			array( 'Day 11: Qalqalah', 'ق ط ب ج د echo when they have a sukoon. Recite Surah Al-Ikhlas and listen for the echo on the final د.' ),
			array( 'Day 12: Heavy and light letters', 'The seven heavy letters خ ص ض غ ط ق ظ are pronounced with a full mouth. Compare ص and س, ط and ت out loud.' ),
			array( 'Day 13: Stopping (waqf)', 'When you stop on a word, the last letter takes a sukoon. A tanween fatha becomes a long "aa", and ة becomes a soft "h".' ),
			array( 'Day 14: Your "after" recording', 'Recite the same page as Day 1 and compare. The difference you hear is your progress. Book a free lesson to have a teacher listen to both.' ),
		);
	}

	private static function challenge_mail( $row, $step ) {
		$all = self::lessons();
		if ( ! isset( $all[ $step ] ) ) {
			return null;
		}
		$name = TJQ_Settings::get( 'challenge_name' );
		$html = self::greet( $row ) . '<h3 style="margin:0 0 6px">' . esc_html( $all[ $step ][0] ) . '</h3>' . self::p( $all[ $step ][1] );
		if ( 13 === $step ) {
			$html .= self::trial_cta();
		}
		$html .= '<p style="font-size:13px;color:#6b7280">' . esc_html( sprintf( __( '%1$s: lesson %2$d of 14.', 'tajweedly-quiz' ), $name, $step + 1 ) ) . '</p>';
		return array(
			'subject' => $name . ' - ' . $all[ $step ][0],
			'html'    => $html,
		);
	}
}

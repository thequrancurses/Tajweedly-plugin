<?php
defined( 'ABSPATH' ) || exit;

/**
 * Emails: result email, follow-up wrapper, admin notification, webhook, unsubscribe handling.
 */
class TJQ_Mailer {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_unsubscribe' ) );
	}

	public static function unsub_token( $row ) {
		return hash_hmac( 'sha256', $row->id . '|' . $row->email, wp_salt( 'auth' ) );
	}

	public static function unsub_url( $row ) {
		return add_query_arg(
			array(
				'tjq_unsub' => (int) $row->id,
				'tjq_key'   => self::unsub_token( $row ),
			),
			home_url( '/' )
		);
	}

	/** Personal referral link for a lead. */
	public static function ref_url( $row ) {
		$base = TJQ_Settings::get( 'quiz_page_url' );
		$base = $base ? $base : home_url( '/' );
		return add_query_arg( 'tjq_ref', $row->ref_code, $base );
	}

	public static function maybe_unsubscribe() {
		if ( empty( $_GET['tjq_unsub'] ) || empty( $_GET['tjq_key'] ) ) {
			return;
		}
		$row = TJQ_DB::get( (int) $_GET['tjq_unsub'] );
		if ( $row && hash_equals( self::unsub_token( $row ), sanitize_text_field( wp_unslash( $_GET['tjq_key'] ) ) ) ) {
			TJQ_DB::update( $row->id, array( 'unsub' => 1, 'drip_next' => null ) );
			wp_die(
				esc_html__( 'You have been unsubscribed. You will not receive further emails from us.', 'tajweedly-quiz' ),
				esc_html__( 'Unsubscribed', 'tajweedly-quiz' ),
				array( 'response' => 200 )
			);
		}
	}

	/** Branded HTML wrapper with unsubscribe footer. */
	public static function wrap( $row, $inner ) {
		$brand = TJQ_Settings::get( 'brand_name' );
		$color = TJQ_Settings::get( 'primary_color' );
		$logo  = TJQ_Settings::get( 'logo_url' );
		ob_start();
		?>
		<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;line-height:1.6;font-size:16px">
			<?php if ( $logo ) : ?>
				<p><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>" style="max-height:44px;width:auto"></p>
			<?php else : ?>
				<h2 style="color:<?php echo esc_attr( $color ); ?>;margin-bottom:4px"><?php echo esc_html( $brand ); ?></h2>
			<?php endif; ?>
			<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
			<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0">
			<p style="font-size:12px;color:#6b7280"><?php echo esc_html( sprintf( __( 'You received this because you used a %s Tajweed tool and agreed to receive emails.', 'tajweedly-quiz' ), $brand ) ); ?>
				<a href="<?php echo esc_url( self::unsub_url( $row ) ); ?>"><?php esc_html_e( 'Unsubscribe', 'tajweedly-quiz' ); ?></a></p>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function button( $url, $label ) {
		return '<p><a href="' . esc_url( $url ) . '" style="background:' . esc_attr( TJQ_Settings::get( 'primary_color' ) ) . ';color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;display:inline-block">' . esc_html( $label ) . '</a></p>';
	}

	public static function send( $row, $subject, $inner_html ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'List-Unsubscribe: <' . self::unsub_url( $row ) . '>',
		);
		return wp_mail( $row->email, $subject, self::wrap( $row, $inner_html ), $headers );
	}

	public static function send_result( $row, array $info, $cta_url ) {
		$trial = TJQ_Settings::get( 'trial_url' );
		$name  = $row->name ? $row->name : __( 'there', 'tajweedly-quiz' );
		$kids  = 'kids' === $row->level;

		$html  = '<p>' . sprintf( esc_html__( 'Assalamu alaikum %s,', 'tajweedly-quiz' ), esc_html( $name ) ) . '</p>';
		if ( $kids ) {
			$html .= '<p>' . sprintf( esc_html__( 'Your child\'s result: %s.', 'tajweedly-quiz' ), '<strong>' . esc_html( $info['label'] ) . '</strong>' ) . '</p>';
		} else {
			$html .= '<p>' . sprintf( esc_html__( 'You scored %1$d out of %2$d. Result: %3$s.', 'tajweedly-quiz' ), (int) $row->score, (int) $row->total, '<strong>' . esc_html( $info['label'] ) . '</strong>' ) . '</p>';
		}
		$html .= '<p>' . esc_html( $info['message'] ) . '</p>';
		$html .= '<p>' . sprintf( esc_html__( 'Recommended path: %s', 'tajweedly-quiz' ), '<strong>' . esc_html( $info['course'] ) . '</strong>' ) . '</p>';
		if ( $trial ) {
			$html .= self::button( $trial, __( 'Book your free trial lesson', 'tajweedly-quiz' ) );
		}
		$html .= '<p><a href="' . esc_url( $cta_url ) . '">' . esc_html__( 'See your recommended course', 'tajweedly-quiz' ) . '</a></p>';
		$html .= '<p><a href="' . esc_url( TJQ_Sheet::url( $row ) ) . '">' . esc_html__( 'Download your free cheat sheet (print or save as PDF)', 'tajweedly-quiz' ) . '</a></p>';
		$html .= '<p style="background:#f3f4f6;padding:12px;border-radius:8px">' . sprintf(
			esc_html__( 'Know someone who would like to try? Share your link and you both get %1$s: %2$s', 'tajweedly-quiz' ),
			esc_html( TJQ_Settings::get( 'ref_reward' ) ),
			'<a href="' . esc_url( self::ref_url( $row ) ) . '">' . esc_html( self::ref_url( $row ) ) . '</a>'
		) . '</p>';

		$subject = ! empty( $info['subject'] ) ? $info['subject'] : sprintf( __( 'Your Tajweed result: %s', 'tajweedly-quiz' ), $info['label'] );
		self::send( $row, $subject, $html );
	}

	public static function notify_admin( $row ) {
		$to = TJQ_Settings::get( 'notify_email' );
		if ( ! is_email( $to ) ) {
			return;
		}
		$body = sprintf(
			"New lead\n\nName: %s\nEmail: %s\nTest: %s (%d/%d) %s\nGoal: %s\nReferred by code: %s\nSource: %s / %s / %s\nPage: %s\nDetails: %s\n",
			$row->name,
			$row->email,
			$row->level,
			$row->score,
			$row->total,
			$row->band,
			$row->goal,
			$row->referred_by,
			$row->utm_source,
			$row->utm_medium,
			$row->utm_campaign,
			$row->page_url,
			$row->meta
		);
		wp_mail( $to, 'New Tajweedly lead: ' . $row->email, $body );
	}

	public static function webhook( $row ) {
		$url = TJQ_Settings::get( 'webhook_url' );
		if ( ! $url ) {
			return;
		}
		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'name'         => $row->name,
						'email'        => $row->email,
						'test'         => $row->level,
						'band'         => $row->band,
						'score'        => (int) $row->score,
						'total'        => (int) $row->total,
						'goal'         => $row->goal,
						'status'       => $row->status,
						'ref_code'     => $row->ref_code,
						'referred_by'  => $row->referred_by,
						'variant'      => $row->variant,
						'details'      => $row->meta ? json_decode( $row->meta, true ) : null,
						'consent'      => (bool) $row->consent,
						'consent_text' => $row->consent_text,
						'consent_at'   => $row->consent_at,
						'utm_source'   => $row->utm_source,
						'utm_medium'   => $row->utm_medium,
						'utm_campaign' => $row->utm_campaign,
						'page_url'     => $row->page_url,
					)
				),
			)
		);
	}
}

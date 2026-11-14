<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (single option array).
 */
class TJQ_Settings {

	const OPTION = 'tjq_settings';

	public static function defaults() {
		return array(
			'brand_name'          => 'Tajweedly',
			'brand_source_url'    => 'https://tajweedly.com',
			'primary_color'       => '#0f766e',
			'accent_color'        => '#f59e0b',
			'font_family'         => '',
			'font_url'            => '',
			'logo_url'            => '',
			'trial_url'           => '',
			'calendly_url'        => '',
			'quiz_page_url'       => '',
			'url_beginner'        => '',
			'url_intermediate'    => '',
			'url_advanced'        => '',
			'url_kids'            => '',
			'whatsapp'            => '',
			'notify_email'        => get_option( 'admin_email' ),
			'webhook_url'         => '',
			'privacy_url'         => '',
			'consent_text'        => 'Yes, email me my result and occasional Quran learning tips. I can unsubscribe at any time.',
			'lang'                => 'auto',
			'drip_enabled'        => 1,
			'challenge_name'      => 'Ramadan Tajweed Challenge',
			'ref_reward'          => 'a free lesson',
			'ab_a_text'           => '',
			'ab_b_text'           => '',
			'cta_posts'           => 1,
			'cta_popup'           => 0,
			'cta_text'            => 'Test your Tajweed level in 5 minutes (free)',
			'mushaf_font'         => 1,
			'mushaf_translation'  => 1,
			'delete_on_uninstall' => 0,
		);
	}

	public static function fields() {
		return array(
			'brand_name'          => array( 'label' => 'Brand name', 'type' => 'text', 'section' => 'Brand' ),
			'brand_source_url'    => array( 'label' => 'Homepage to read the brand from', 'type' => 'url', 'help' => 'Used by the "Detect from homepage" button.', 'section' => 'Brand' ),
			'primary_color'       => array( 'label' => 'Primary colour', 'type' => 'color', 'section' => 'Brand' ),
			'accent_color'        => array( 'label' => 'Accent colour', 'type' => 'color', 'section' => 'Brand' ),
			'font_family'         => array( 'label' => 'Font family', 'type' => 'text', 'help' => 'Leave empty to inherit the font from your theme (recommended).', 'section' => 'Brand' ),
			'font_url'            => array( 'label' => 'Google Fonts URL (optional)', 'type' => 'url', 'section' => 'Brand' ),
			'logo_url'            => array( 'label' => 'Logo URL', 'type' => 'url', 'section' => 'Brand' ),

			'trial_url'           => array( 'label' => 'Free trial / booking URL', 'type' => 'url', 'help' => 'Fallback button if no Calendly URL is set.', 'section' => 'Conversion' ),
			'calendly_url'        => array( 'label' => 'Calendly (or similar) scheduling URL', 'type' => 'url', 'help' => 'Shown inline on the thank-you screen, pre-filled with name, email and level.', 'section' => 'Conversion' ),
			'quiz_page_url'       => array( 'label' => 'Quiz page URL', 'type' => 'url', 'help' => 'Where the quiz lives. Used by blog CTAs, the popup and referral links.', 'section' => 'Conversion' ),
			'url_beginner'        => array( 'label' => 'Course URL: Beginner', 'type' => 'url', 'section' => 'Conversion' ),
			'url_intermediate'    => array( 'label' => 'Course URL: Intermediate', 'type' => 'url', 'section' => 'Conversion' ),
			'url_advanced'        => array( 'label' => 'Course URL: Advanced', 'type' => 'url', 'section' => 'Conversion' ),
			'url_kids'            => array( 'label' => 'Course URL: Kids', 'type' => 'url', 'section' => 'Conversion' ),
			'whatsapp'            => array( 'label' => 'WhatsApp number', 'type' => 'text', 'help' => 'International format, digits only, e.g. 447700900123.', 'section' => 'Conversion' ),
			'ab_a_text'           => array( 'label' => 'A/B test: button text A', 'type' => 'text', 'help' => 'Fill both A and B to split-test the lead-form button. Results are on the Dashboard.', 'section' => 'Conversion' ),
			'ab_b_text'           => array( 'label' => 'A/B test: button text B', 'type' => 'text', 'section' => 'Conversion' ),

			'drip_enabled'        => array( 'label' => 'Send follow-up email sequence', 'type' => 'checkbox', 'help' => 'Only to people who ticked the consent box. Stops automatically when you set their status to Trial booked, Paid or Lost.', 'section' => 'Email & growth' ),
			'challenge_name'      => array( 'label' => 'Email challenge name', 'type' => 'text', 'section' => 'Email & growth' ),
			'ref_reward'          => array( 'label' => 'Referral reward wording', 'type' => 'text', 'help' => 'Shown as "you both get ...". You give the reward manually.', 'section' => 'Email & growth' ),
			'notify_email'        => array( 'label' => 'Notify me of new leads at', 'type' => 'email', 'section' => 'Email & growth' ),
			'webhook_url'         => array( 'label' => 'Webhook URL (optional)', 'type' => 'url', 'help' => 'New leads are POSTed as JSON. Works with Zapier, Make, n8n, Brevo, Mailchimp automations.', 'section' => 'Email & growth' ),

			'cta_posts'           => array( 'label' => 'Add a "Test your level" box to the end of every blog post', 'type' => 'checkbox', 'section' => 'Blog' ),
			'cta_popup'           => array( 'label' => 'Show a one-time popup on blog posts (scroll / exit intent)', 'type' => 'checkbox', 'section' => 'Blog' ),
			'cta_text'            => array( 'label' => 'CTA wording', 'type' => 'text', 'section' => 'Blog' ),

			'mushaf_font'         => array( 'label' => 'Load the Amiri Quran font from Google Fonts', 'type' => 'checkbox', 'help' => 'Makes the Quran text look right on every device. Google Fonts receives visitors\' IP addresses: turn off if your privacy policy forbids it, and the text falls back to system fonts.', 'section' => 'Colour-coded Quran' ),
			'mushaf_translation'  => array( 'label' => 'Show the English translation by default', 'type' => 'checkbox', 'section' => 'Colour-coded Quran' ),

			'lang'                => array(
				'label'   => 'Quiz interface language',
				'type'    => 'select',
				'options' => array( 'auto' => 'Auto (site language)', 'en' => 'English', 'ar' => 'العربية', 'ur' => 'اردو', 'fr' => 'Français' ),
				'help'    => 'Translates the interface. Questions stay in English until reviewed translations are added.',
				'section' => 'Legal & language',
			),
			'privacy_url'         => array( 'label' => 'Privacy policy URL', 'type' => 'url', 'help' => 'Shown next to the consent checkbox. Required for UK/EU compliance.', 'section' => 'Legal & language' ),
			'consent_text'        => array( 'label' => 'Consent checkbox text', 'type' => 'textarea', 'section' => 'Legal & language' ),
			'delete_on_uninstall' => array( 'label' => 'Delete all data when plugin is uninstalled', 'type' => 'checkbox', 'section' => 'Legal & language' ),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	public static function sanitize( $input ) {
		$out = array();
		$def = self::defaults();
		foreach ( self::fields() as $key => $f ) {
			$val = isset( $input[ $key ] ) ? $input[ $key ] : '';
			switch ( $f['type'] ) {
				case 'url':
					$out[ $key ] = esc_url_raw( $val );
					break;
				case 'email':
					$out[ $key ] = sanitize_email( $val );
					break;
				case 'color':
					$out[ $key ] = sanitize_hex_color( $val ) ? sanitize_hex_color( $val ) : $def[ $key ];
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $val );
					break;
				case 'checkbox':
					$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
					break;
				case 'select':
					$out[ $key ] = isset( $f['options'][ $val ] ) ? $val : $def[ $key ];
					break;
				default:
					$out[ $key ] = sanitize_text_field( $val );
			}
		}
		$out['whatsapp'] = preg_replace( '/\D+/', '', $out['whatsapp'] );
		return $out;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * WordPress Tools > Export / Erase Personal Data integration (GDPR).
 */
class TJQ_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['tajweedly-quiz'] = array(
			'exporter_friendly_name' => __( 'Tajweedly Quiz', 'tajweedly-quiz' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['tajweedly-quiz'] = array(
			'eraser_friendly_name' => __( 'Tajweedly Quiz', 'tajweedly-quiz' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email ) {
		$items = array();
		foreach ( TJQ_DB::find_by_email( $email ) as $r ) {
			$items[] = array(
				'group_id'    => 'tajweedly-quiz',
				'group_label' => __( 'Tajweed quiz results', 'tajweedly-quiz' ),
				'item_id'     => 'tjq-' . $r->id,
				'data'        => array(
					array( 'name' => __( 'Name', 'tajweedly-quiz' ), 'value' => $r->name ),
					array( 'name' => __( 'Email', 'tajweedly-quiz' ), 'value' => $r->email ),
					array( 'name' => __( 'Score', 'tajweedly-quiz' ), 'value' => $r->score . '/' . $r->total ),
					array( 'name' => __( 'Test', 'tajweedly-quiz' ), 'value' => $r->level ),
					array( 'name' => __( 'Status', 'tajweedly-quiz' ), 'value' => $r->status ),
					array( 'name' => __( 'Answers / details', 'tajweedly-quiz' ), 'value' => $r->meta ),
					array( 'name' => __( 'Consent given at', 'tajweedly-quiz' ), 'value' => $r->consent_at ),
					array( 'name' => __( 'Consent wording', 'tajweedly-quiz' ), 'value' => $r->consent_text ),
				),
			);
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email ) {
		$n = TJQ_DB::delete_by_email( $email );
		return array(
			'items_removed'  => (bool) $n,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}

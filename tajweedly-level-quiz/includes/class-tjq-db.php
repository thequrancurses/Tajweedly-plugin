<?php
defined( 'ABSPATH' ) || exit;

/**
 * Data layer. One row per quiz attempt; the row becomes a "lead" once an email is attached.
 */
class TJQ_DB {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tjq_attempts';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			token char(32) NOT NULL,
			score tinyint(3) unsigned NOT NULL DEFAULT 0,
			total tinyint(3) unsigned NOT NULL DEFAULT 0,
			level varchar(20) NOT NULL DEFAULT '',
			band varchar(20) NOT NULL DEFAULT '',
			goal varchar(40) NOT NULL DEFAULT '',
			name varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			consent tinyint(1) NOT NULL DEFAULT 0,
			consent_text text NULL,
			consent_at datetime NULL,
			unsub tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'new',
			drip_track varchar(20) NOT NULL DEFAULT '',
			drip_step tinyint(3) unsigned NOT NULL DEFAULT 0,
			drip_next datetime NULL,
			ref_code varchar(12) NOT NULL DEFAULT '',
			referred_by varchar(12) NOT NULL DEFAULT '',
			variant varchar(4) NOT NULL DEFAULT '',
			meta text NULL,
			utm_source varchar(100) NOT NULL DEFAULT '',
			utm_medium varchar(100) NOT NULL DEFAULT '',
			utm_campaign varchar(100) NOT NULL DEFAULT '',
			referrer varchar(255) NOT NULL DEFAULT '',
			page_url varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY email (email),
			KEY level (level),
			KEY status (status),
			KEY ref_code (ref_code),
			KEY drip_next (drip_next),
			KEY created_at (created_at)
		) $charset;"
		);
		update_option( 'tjq_db_version', TJQ_VERSION );
	}

	public static function statuses() {
		return array(
			'new'       => 'New',
			'contacted' => 'Contacted',
			'trial'     => 'Trial booked',
			'paid'      => 'Paid',
			'lost'      => 'Lost',
		);
	}

	public static function insert( array $data ) {
		global $wpdb;
		$data = array_merge(
			array(
				'created_at' => current_time( 'mysql' ),
				'token'      => wp_generate_password( 32, false ),
				'ref_code'   => strtolower( wp_generate_password( 8, false ) ),
			),
			$data
		);
		$ok = $wpdb->insert( self::table(), $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	public static function delete_by_email( $email ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'email' => $email ) );
	}

	public static function find_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', $email ) );
	}

	/**
	 * Leads whose next follow-up email is due. UTC comparison.
	 */
	public static function drip_due( $limit = 50 ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $t WHERE email <> '' AND consent = 1 AND unsub = 0 AND status IN ('new','contacted') AND drip_track <> '' AND drip_next IS NOT NULL AND drip_next <= %s ORDER BY drip_next ASC LIMIT %d",
				gmdate( 'Y-m-d H:i:s' ),
				(int) $limit
			)
		);
	}

	private static function referrals_sql( $table ) {
		return "(SELECT COUNT(*) FROM $table r WHERE t.ref_code <> '' AND r.referred_by = t.ref_code AND r.email <> '') AS referrals";
	}

	/**
	 * @param array $args s, level, status, only_leads, paged, per_page
	 * @return array [rows, total]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 's' => '', 'level' => '', 'status' => '', 'only_leads' => 1, 'paged' => 1, 'per_page' => 25 ) );
		$where = array( '1=1' );
		$vals  = array();
		if ( $args['only_leads'] ) {
			$where[] = "email <> ''";
		}
		if ( $args['level'] ) {
			$where[] = 'level = %s';
			$vals[]  = $args['level'];
		}
		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}
		if ( $args['s'] ) {
			$like    = '%' . $wpdb->esc_like( $args['s'] ) . '%';
			$where[] = '(email LIKE %s OR name LIKE %s)';
			$vals[]  = $like;
			$vals[]  = $like;
		}
		$sql_where = implode( ' AND ', $where );
		$table     = self::table();

		$count_sql = "SELECT COUNT(*) FROM $table t WHERE $sql_where";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) );

		$per    = max( 1, (int) $args['per_page'] );
		$offset = max( 0, ( (int) $args['paged'] - 1 ) * $per );
		$sql    = 'SELECT t.*, ' . self::referrals_sql( $table ) . " FROM $table t WHERE $sql_where ORDER BY t.id DESC LIMIT %d OFFSET %d";
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $vals, array( $per, $offset ) ) ) );
		return array( $rows, $total );
	}

	/** All leads for CSV export. */
	public static function all_leads() {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( 'SELECT t.*, ' . self::referrals_sql( $t ) . " FROM $t t WHERE t.email <> '' ORDER BY t.id DESC" );
	}

	public static function stats() {
		global $wpdb;
		$t   = self::table();
		$out = array(
			'attempts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ),
			'leads'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE email <> ''" ),
			'last_30'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" ),
			'levels'   => array(),
			'sources'  => array(),
			'funnel'   => array(),
			'variants' => array(),
			'referred' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE referred_by <> '' AND email <> ''" ),
		);
		foreach ( $wpdb->get_results( "SELECT level, COUNT(*) c FROM $t GROUP BY level" ) as $r ) {
			$out['levels'][ $r->level ] = (int) $r->c;
		}
		foreach ( $wpdb->get_results( "SELECT IF(utm_source = '', '(direct / none)', utm_source) src, COUNT(*) c, SUM(email <> '') leads FROM $t GROUP BY src ORDER BY c DESC LIMIT 10" ) as $r ) {
			$out['sources'][] = array( 'src' => $r->src, 'attempts' => (int) $r->c, 'leads' => (int) $r->leads );
		}
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) c FROM $t WHERE email <> '' GROUP BY status" ) as $r ) {
			$out['funnel'][ $r->status ] = (int) $r->c;
		}
		foreach ( $wpdb->get_results( "SELECT variant, COUNT(*) c, SUM(email <> '') leads FROM $t WHERE variant <> '' GROUP BY variant ORDER BY variant" ) as $r ) {
			$out['variants'][] = array( 'variant' => strtoupper( $r->variant ), 'attempts' => (int) $r->c, 'leads' => (int) $r->leads );
		}
		return $out;
	}
}

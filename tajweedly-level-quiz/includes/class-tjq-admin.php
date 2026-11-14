<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: Dashboard (stats, funnel, A/B), Leads (status, search, filter, export, delete),
 * Questions (custom + audio), Settings (brand detection, landing pages).
 */
class TJQ_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_tjq_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_tjq_delete', array( __CLASS__, 'delete_lead' ) );
		add_action( 'admin_post_tjq_status', array( __CLASS__, 'set_status' ) );
		add_action( 'admin_post_tjq_detect_brand', array( __CLASS__, 'detect_brand' ) );
		add_action( 'admin_post_tjq_create_pages', array( __CLASS__, 'create_pages' ) );
		add_action( 'admin_post_tjq_create_content', array( __CLASS__, 'create_content' ) );
		add_action( 'admin_post_tjq_create_surahs', array( __CLASS__, 'create_surahs' ) );
		add_action( 'admin_post_tjq_add_q', array( __CLASS__, 'add_question' ) );
		add_action( 'admin_post_tjq_del_q', array( __CLASS__, 'delete_question' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TJQ_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=tjq-settings' ) ) . '">Settings</a>' );
			return $l;
		} );
	}

	public static function menu() {
		add_menu_page( 'Tajweed Quiz', 'Tajweed Quiz', 'manage_options', 'tjq', array( __CLASS__, 'page_dashboard' ), 'dashicons-welcome-learn-more', 58 );
		add_submenu_page( 'tjq', 'Dashboard', 'Dashboard', 'manage_options', 'tjq', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'tjq', 'Leads', 'Leads', 'manage_options', 'tjq-leads', array( __CLASS__, 'page_leads' ) );
		add_submenu_page( 'tjq', 'Questions', 'Questions', 'manage_options', 'tjq-questions', array( __CLASS__, 'page_questions' ) );
		add_submenu_page( 'tjq', 'Quran data & pages', 'Quran & pages', 'manage_options', 'tjq-quran', array( __CLASS__, 'page_quran' ) );
		add_submenu_page( 'tjq', 'Insights', 'Insights', 'manage_options', 'tjq-insights', array( __CLASS__, 'page_insights' ) );
		add_submenu_page( 'tjq', 'Embed', 'Embed', 'manage_options', 'tjq-embed', array( __CLASS__, 'page_embed' ) );
		add_submenu_page( 'tjq', 'Settings', 'Settings', 'manage_options', 'tjq-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'tjq_group', TJQ_Settings::OPTION, array( 'sanitize_callback' => array( 'TJQ_Settings', 'sanitize' ) ) );
	}

	public static function notices() {
		if ( empty( $_GET['tjq_msg'] ) ) {
			return;
		}
		$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0;
		$map = array(
			'detected'    => array( 'success', 'Brand detected from your homepage and saved. Review the values below.' ),
			'detect_fail' => array( 'error', 'Could not read the homepage. Check the URL or fill the values manually.' ),
			'deleted'     => array( 'success', 'Lead deleted.' ),
			'status'      => array( 'success', 'Status updated.' ),
			'pages'       => array( 'success', 'Pages created as drafts. Review them under Pages, then publish.' ),
			'q_added'     => array( 'success', 'Question added.' ),
			'q_deleted'   => array( 'success', 'Question deleted.' ),
			'q_bad'       => array( 'error', 'A question needs text, at least two options and a correct answer.' ),
		);
		$k = sanitize_key( $_GET['tjq_msg'] );
		if ( isset( $map[ $k ] ) ) {
			$text = $map[ $k ][1] . ( 'pages' === $k ? sprintf( ' (%d new, %d already existed)', $n, isset( $_GET['e'] ) ? (int) $_GET['e'] : 0 ) : '' );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $k ][0] ), esc_html( $text ) );
		}
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $nonce );
	}

	/* ---------- Dashboard ---------- */

	public static function page_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = TJQ_DB::stats();
		$conv = $s['attempts'] ? round( $s['leads'] / $s['attempts'] * 100, 1 ) : 0;
		?>
		<div class="wrap">
			<h1>Tajweed Quiz</h1>
			<p>Shortcodes: <code>[tajweedly_quiz]</code> &nbsp; <code>[tajweedly_quiz level="beginner"]</code> (beginner, intermediate, advanced, kids) &nbsp; <code>[tajweedly_challenge]</code> &nbsp; <code>[tajweedly_cta]</code></p>
			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">
				<?php
				self::card( 'Tests completed / sign-ups', $s['attempts'] );
				self::card( 'Leads (emails)', $s['leads'] );
				self::card( 'Conversion to lead', $conv . '%' );
				self::card( 'Last 30 days', $s['last_30'] );
				self::card( 'Leads from referrals', $s['referred'] );
				?>
			</div>

			<h2>Lead funnel</h2>
			<table class="widefat striped" style="max-width:420px"><tbody>
				<?php foreach ( TJQ_DB::statuses() as $k => $label ) : ?>
					<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo (int) ( isset( $s['funnel'][ $k ] ) ? $s['funnel'][ $k ] : 0 ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<h2>Tests taken</h2>
			<table class="widefat striped" style="max-width:420px"><tbody>
				<?php foreach ( TJQ_Questions::tests() as $k => $t ) : ?>
					<tr><td><?php echo esc_html( $t['label'] ); ?></td><td><?php echo (int) ( isset( $s['levels'][ $k ] ) ? $s['levels'][ $k ] : 0 ); ?></td></tr>
				<?php endforeach; ?>
				<tr><td>14-day challenge sign-ups</td><td><?php echo (int) ( isset( $s['levels']['challenge'] ) ? $s['levels']['challenge'] : 0 ); ?></td></tr>
			</tbody></table>

			<h2>Traffic sources (utm_source)</h2>
			<table class="widefat striped" style="max-width:620px">
				<thead><tr><th>Source</th><th>Completions</th><th>Leads</th></tr></thead><tbody>
				<?php if ( ! $s['sources'] ) : ?>
					<tr><td colspan="3">No data yet. Use UTM links, e.g. <code>?utm_source=facebook&amp;utm_campaign=ramadan</code>. Blog CTAs and the popup are tagged automatically.</td></tr>
				<?php endif; ?>
				<?php foreach ( $s['sources'] as $r ) : ?>
					<tr><td><?php echo esc_html( $r['src'] ); ?></td><td><?php echo (int) $r['attempts']; ?></td><td><?php echo (int) $r['leads']; ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<h2>A/B test (lead-form button)</h2>
			<table class="widefat striped" style="max-width:620px">
				<thead><tr><th>Variant</th><th>Results seen</th><th>Leads</th><th>Rate</th></tr></thead><tbody>
				<?php if ( ! $s['variants'] ) : ?>
					<tr><td colspan="4">Not running. Fill "button text A" and "button text B" in Settings. Wait for 100+ results per variant before choosing a winner.</td></tr>
				<?php endif; ?>
				<?php foreach ( $s['variants'] as $v ) : ?>
					<tr><td><?php echo esc_html( $v['variant'] ); ?></td><td><?php echo (int) $v['attempts']; ?></td><td><?php echo (int) $v['leads']; ?></td><td><?php echo $v['attempts'] ? esc_html( round( $v['leads'] / $v['attempts'] * 100, 1 ) ) . '%' : '-'; ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private static function card( $label, $value ) {
		printf( '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 22px;min-width:150px"><div style="color:#646970">%s</div><div style="font-size:28px;font-weight:700">%s</div></div>', esc_html( $label ), esc_html( $value ) );
	}

	/* ---------- Leads ---------- */

	public static function page_leads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$level  = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		list( $rows, $total ) = TJQ_DB::query( array( 's' => $search, 'level' => $level, 'status' => $status, 'paged' => $paged, 'per_page' => 25 ) );
		$pages = (int) ceil( $total / 25 );
		$tests = TJQ_Questions::tests();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Leads</h1>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_export' ), 'tjq_export' ) ); ?>">Export CSV</a>
			<form method="get" style="margin:14px 0">
				<input type="hidden" name="page" value="tjq-leads">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name or email">
				<select name="level">
					<option value="">All tests</option>
					<?php foreach ( $tests as $k => $t ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $level, $k ); ?>><?php echo esc_html( $t['label'] ); ?></option>
					<?php endforeach; ?>
					<option value="challenge" <?php selected( $level, 'challenge' ); ?>>14-day challenge</option>
				</select>
				<select name="status">
					<option value="">All statuses</option>
					<?php foreach ( TJQ_DB::statuses() as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button">Filter</button>
			</form>
			<p class="description">Setting a lead to Trial booked, Paid or Lost stops their follow-up emails automatically.</p>
			<table class="widefat striped">
				<thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Test</th><th>Result</th><th>Source</th><th>Referrals</th><th>Status</th><th>Consent</th><th></th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="10">No leads yet.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td><?php echo esc_html( $r->name ); ?></td>
						<td><a href="mailto:<?php echo esc_attr( $r->email ); ?>"><?php echo esc_html( $r->email ); ?></a></td>
						<td><?php echo esc_html( isset( $tests[ $r->level ] ) ? $tests[ $r->level ]['label'] : $r->level ); ?></td>
						<td><?php echo 'kids' === $r->level || 'challenge' === $r->level || 'incomplete' === $r->band ? esc_html( $r->band ) : (int) $r->score . '/' . (int) $r->total . ' ' . esc_html( $r->band ); ?></td>
						<td><?php echo esc_html( trim( $r->utm_source . ' ' . $r->utm_campaign ) ); ?></td>
						<td><?php echo (int) $r->referrals; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="tjq_status">
								<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
								<?php wp_nonce_field( 'tjq_status_' . (int) $r->id ); ?>
								<select name="status" onchange="this.form.submit()">
									<?php foreach ( TJQ_DB::statuses() as $k => $label ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r->status, $k ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</form>
						</td>
						<td><?php echo $r->unsub ? 'Unsubscribed' : ( $r->consent ? esc_html( $r->consent_at ) : '-' ); ?></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_delete&id=' . (int) $r->id ), 'tjq_delete_' . (int) $r->id ) ); ?>" onclick="return confirm('Delete this lead?')">Delete</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) ) . '</div></div>';
			}
			?>
		</div>
		<?php
	}

	public static function set_status() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'tjq_status_' . $id );
		$st = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		if ( $id && isset( TJQ_DB::statuses()[ $st ] ) ) {
			TJQ_DB::update( $id, array( 'status' => $st ) );
		}
		wp_safe_redirect( wp_get_referer() ? add_query_arg( 'tjq_msg', 'status', wp_get_referer() ) : admin_url( 'admin.php?page=tjq-leads&tjq_msg=status' ) );
		exit;
	}

	public static function export_csv() {
		self::guard( 'tjq_export' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tajweedly-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads Arabic names correctly.
		fputcsv( $out, array( 'date', 'name', 'email', 'test', 'band', 'score', 'total', 'goal', 'status', 'referrals', 'variant', 'utm_source', 'utm_medium', 'utm_campaign', 'consent_at', 'unsubscribed', 'details' ) );
		foreach ( TJQ_DB::all_leads() as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), array( $r->created_at, $r->name, $r->email, $r->level, $r->band, $r->score, $r->total, $r->goal, $r->status, $r->referrals, $r->variant, $r->utm_source, $r->utm_medium, $r->utm_campaign, $r->consent_at, $r->unsub, $r->meta ) ) );
		}
		fclose( $out );
		exit;
	}

	private static function csv_safe( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : $v; // Block CSV formula injection.
	}

	public static function delete_lead() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		self::guard( 'tjq_delete_' . $id );
		TJQ_DB::delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-leads&tjq_msg=deleted' ) );
		exit;
	}

	/* ---------- Questions ---------- */

	public static function page_questions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		$custom = get_option( 'tjq_custom_questions', array() );
		$custom = is_array( $custom ) ? array_values( $custom ) : array();
		?>
		<div class="wrap">
			<h1>Questions</h1>
			<p>The 30 built-in questions are always included. Add your own below, including <strong>audio questions</strong> ("listen and choose the mistake") with a recording from your teachers.</p>

			<h2>Add a question</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tjq_add_q">
				<?php wp_nonce_field( 'tjq_add_q' ); ?>
				<table class="form-table" role="presentation">
					<tr><th>Level</th><td><select name="level"><?php foreach ( TJQ_Questions::levels() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l['label'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th>Question</th><td><input type="text" name="q" class="large-text" required></td></tr>
					<tr><th>Audio (optional)</th><td><input type="url" name="audio" id="tjq-audio" class="regular-text" placeholder="https://.../recitation.mp3"> <button type="button" class="button" id="tjq-pick">Choose from media library</button></td></tr>
					<?php for ( $i = 0; $i < 4; $i++ ) : ?>
						<tr><th>Option <?php echo esc_html( $i + 1 ); ?></th><td><input type="text" name="opt[]" class="regular-text" <?php echo $i < 2 ? 'required' : ''; ?>> <label><input type="radio" name="correct" value="<?php echo (int) $i; ?>" <?php checked( 0, $i ); ?>> correct</label></td></tr>
					<?php endfor; ?>
					<tr><th>Explanation</th><td><textarea name="explain" rows="2" class="large-text"></textarea></td></tr>
				</table>
				<?php submit_button( 'Add question' ); ?>
			</form>

			<h2>Your questions (<?php echo (int) count( $custom ); ?>)</h2>
			<table class="widefat striped">
				<thead><tr><th>Level</th><th>Question</th><th>Audio</th><th></th></tr></thead>
				<tbody>
				<?php if ( ! $custom ) : ?>
					<tr><td colspan="4">None yet.</td></tr>
				<?php endif; ?>
				<?php foreach ( $custom as $n => $c ) : ?>
					<tr>
						<td><?php echo esc_html( $c['level'] ); ?></td>
						<td><?php echo esc_html( $c['q'] ); ?></td>
						<td><?php echo ! empty( $c['audio'] ) ? 'Yes' : '-'; ?></td>
						<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_del_q&n=' . (int) $n ), 'tjq_del_q_' . (int) $n ) ); ?>" onclick="return confirm('Delete this question?')">Delete</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<script>
		document.getElementById('tjq-pick').addEventListener('click',function(){
			var f=wp.media({title:'Choose audio',library:{type:'audio'},multiple:false});
			f.on('select',function(){document.getElementById('tjq-audio').value=f.state().get('selection').first().toJSON().url;});
			f.open();
		});
		</script>
		<?php
	}

	public static function add_question() {
		self::guard( 'tjq_add_q' );
		$levels  = TJQ_Questions::levels();
		$level   = isset( $_POST['level'] ) ? sanitize_key( $_POST['level'] ) : '';
		$q       = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$raw     = isset( $_POST['opt'] ) && is_array( $_POST['opt'] ) ? wp_unslash( $_POST['opt'] ) : array();
		$correct = isset( $_POST['correct'] ) ? (int) $_POST['correct'] : 0;
		$opts    = array();
		$map     = array(); // original index -> new index (empty options are dropped).
		foreach ( $raw as $i => $o ) {
			$o = sanitize_text_field( $o );
			if ( '' !== $o ) {
				$map[ (int) $i ] = count( $opts );
				$opts[]          = $o;
			}
		}
		if ( ! isset( $levels[ $level ] ) || '' === $q || count( $opts ) < 2 || ! isset( $map[ $correct ] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tjq-questions&tjq_msg=q_bad' ) );
			exit;
		}
		$saved   = get_option( 'tjq_custom_questions', array() );
		$saved   = is_array( $saved ) ? $saved : array();
		$saved[] = array(
			'level'   => $level,
			'q'       => $q,
			'options' => $opts,
			'correct' => $map[ $correct ],
			'explain' => isset( $_POST['explain'] ) ? sanitize_textarea_field( wp_unslash( $_POST['explain'] ) ) : '',
			'audio'   => isset( $_POST['audio'] ) ? esc_url_raw( wp_unslash( $_POST['audio'] ) ) : '',
		);
		update_option( 'tjq_custom_questions', array_values( $saved ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-questions&tjq_msg=q_added' ) );
		exit;
	}

	public static function delete_question() {
		$n = isset( $_GET['n'] ) ? (int) $_GET['n'] : -1;
		self::guard( 'tjq_del_q_' . $n );
		$saved = get_option( 'tjq_custom_questions', array() );
		if ( is_array( $saved ) && isset( $saved[ $n ] ) ) {
			unset( $saved[ $n ] );
			update_option( 'tjq_custom_questions', array_values( $saved ), false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-questions&tjq_msg=q_deleted' ) );
		exit;
	}


	/* ---------- Quran data, learning pages ---------- */

	public static function create_content() {
		self::guard( 'tjq_create_content' );
		list( $created, $existing ) = TJQ_SEO::create_content_pages();
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-quran&tjq_msg=pages&n=' . (int) $created . '&e=' . (int) $existing ) );
		exit;
	}

	public static function create_surahs() {
		self::guard( 'tjq_create_surahs' );
		list( $created, $existing ) = TJQ_SEO::create_surah_pages( TJQ_Content::default_surahs() );
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-quran&tjq_msg=pages&n=' . (int) $created . '&e=' . (int) $existing ) );
		exit;
	}

	public static function page_quran() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$cached = TJQ_Mushaf::cached_numbers();
		$nonce  = wp_create_nonce( 'tjq_fetch_surah' );
		?>
		<div class="wrap">
			<h1>Colour-coded Quran and learning pages</h1>
			<p>The Quran text is <strong>not</strong> stored in the plugin. It is downloaded from the open AlQuran Cloud API (quran-tajweed edition, English: Sahih International) and cached in your database. Download it once so pages load instantly and Google sees the full text.</p>

			<h2>1. Download the Quran data</h2>
			<p>Cached surahs: <strong id="tjq-cached"><?php echo (int) count( $cached ); ?></strong> / 114</p>
			<p>
				<button class="button button-primary" id="tjq-dl-featured">Download Juz Amma + featured surahs (<?php echo (int) count( TJQ_Content::default_surahs() ); ?>)</button>
				<button class="button" id="tjq-dl-all">Download all 114</button>
				<span id="tjq-dl-status" style="margin-inline-start:12px"></span>
			</p>
			<p class="description">Takes a minute or two. If your server cannot reach api.alquran.cloud, nothing is saved and the pages show a friendly message.</p>

			<h2>2. Create the pages (as drafts)</h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_create_content' ), 'tjq_create_content' ) ); ?>">Create learning pages</a>
				<span class="description">Tajweed rules hub, izhar, idgham, iqlab, ikhfa, meem sakinah, ghunnah, qalqalah, madd, sun &amp; moon letters, makharij, glossary, colour-coded Quran reader, Ramadan challenge.</span>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_create_surahs' ), 'tjq_create_surahs' ) ); ?>">Create surah pages</a>
				<span class="description">Al-Fatihah, Al-Kahf, Ya-Sin, Ar-Rahman, Al-Waqiah, Al-Mulk and all of Juz Amma (<?php echo (int) count( TJQ_Content::default_surahs() ); ?> pages).</span>
			</p>
			<p class="description">Pages are created as <strong>drafts</strong> and never overwrite existing slugs. Publish them gradually (a few per week) and add the "Tajweed rules" hub to your menu.</p>
		</div>
		<script>
		(function(){
			var nonce=<?php echo wp_json_encode( $nonce ); ?>, ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var featured=<?php echo wp_json_encode( TJQ_Content::default_surahs() ); ?>, all=[];for(var i=1;i<=114;i++){all.push(i);}
			var st=document.getElementById('tjq-dl-status'), cnt=document.getElementById('tjq-cached'), busy=false, done=0, fail=[];
			function one(list,idx){
				if(idx>=list.length){busy=false;st.textContent='Finished. '+(fail.length?'Failed: '+fail.join(', ')+' (run again to retry).':'All downloaded.');return;}
				var n=list[idx];st.textContent='Downloading surah '+n+' ('+(idx+1)+'/'+list.length+')...';
				var fd=new FormData();fd.append('action','tjq_fetch_surah');fd.append('nonce',nonce);fd.append('n',n);
				fetch(ajax,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(j){
					if(j&&j.success){done++;cnt.textContent=parseInt(cnt.textContent,10)+0;}else{fail.push(n);}
				}).catch(function(){fail.push(n);}).then(function(){setTimeout(function(){one(list,idx+1);},300);});
			}
			function run(list){if(busy){return;}busy=true;done=0;fail=[];one(list,0);}
			document.getElementById('tjq-dl-featured').addEventListener('click',function(){run(featured);});
			document.getElementById('tjq-dl-all').addEventListener('click',function(){run(all);});
		})();
		</script>
		<?php
	}

	/* ---------- Insights ---------- */

	public static function page_insights() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$agg   = TJQ_Tools::aggregate();
		$top   = TJQ_Tools::top_missed( $agg, 5 );
		$sum   = TJQ_Tools::press_summary( $top, get_bloginfo( 'name' ) );
		$tests = TJQ_Questions::levels();
		?>
		<div class="wrap">
			<h1>Insights</h1>
			<p>Built only from real attempts on your site (no personal data). A level needs at least <?php echo (int) TJQ_Tools::MIN_ATTEMPTS; ?> recorded tests before it is shown, so the numbers are not misleading. Only tests taken since version 1.3 are counted.</p>

			<h2>Most-missed questions</h2>
			<?php foreach ( $tests as $k => $t ) : ?>
				<h3><?php echo esc_html( $t['label'] ); ?>
					<small>(<?php echo (int) ( isset( $agg['levels'][ $k ] ) ? $agg['levels'][ $k ]['attempts'] : 0 ); ?> tests)</small></h3>
				<?php if ( empty( $top[ $k ] ) ) : ?>
					<p>Not enough data yet.</p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:900px"><thead><tr><th>Question</th><th>Got it wrong</th><th>Answer note</th></tr></thead><tbody>
					<?php foreach ( $top[ $k ]['top'] as $row ) : ?>
						<tr><td><?php echo esc_html( $row['q'] ); ?></td><td><?php echo (int) $row['rate']; ?>%</td><td><?php echo esc_html( $row['explain'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			<?php endforeach; ?>

			<h2>Parents' readiness test (<?php echo (int) $agg['kids_n']; ?> answers)</h2>
			<?php if ( $agg['kids_n'] < TJQ_Tools::MIN_ATTEMPTS ) : ?>
				<p>Not enough data yet.</p>
			<?php else : ?>
				<?php foreach ( array( 'age' => 'Child age', 'goal' => 'Main goal', 'teacher' => 'Teacher preference', 'sessions' => 'Lessons per week', 'reading' => 'Reading level' ) as $key => $title ) : ?>
					<?php if ( empty( $agg['kids'][ $key ] ) ) { continue; } ?>
					<h3><?php echo esc_html( $title ); ?></h3>
					<table class="widefat striped" style="max-width:520px"><tbody>
					<?php
					arsort( $agg['kids'][ $key ] );
					foreach ( $agg['kids'][ $key ] as $val => $n ) :
						?>
						<tr><td><?php echo esc_html( $val ); ?></td><td><?php echo (int) round( $n / $agg['kids_n'] * 100 ); ?>%</td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2>Press-ready summary</h2>
			<?php if ( '' === $sum ) : ?>
				<p>Available once a level has enough tests.</p>
			<?php else : ?>
				<textarea class="large-text" rows="8" readonly><?php echo esc_textarea( $sum ); ?></textarea>
				<p class="description">Use it in a post titled "The Tajweed rule most people get wrong" and pitch it to Islamic news sites and blogs.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------- Embed ---------- */

	public static function page_embed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$variants = array( '' => 'All tests' );
		foreach ( TJQ_Questions::tests() as $k => $t ) {
			$variants[ $k ] = $t['label'];
		}
		?>
		<div class="wrap">
			<h1>Embed the quiz on other websites</h1>
			<p>Give this code to mosques, weekend schools, bloggers and Islamic organisations. The quiz runs on your site inside an iframe, results and leads come to you, and the snippet includes a text link back to you.</p>
			<?php foreach ( $variants as $level => $label ) : ?>
				<h2><?php echo esc_html( $label ); ?></h2>
				<textarea class="large-text code" rows="6" readonly onclick="this.select()"><?php echo esc_textarea( TJQ_Tools::embed_snippet( $level ) ); ?></textarea>
			<?php endforeach; ?>
			<p class="description">If a partner's page shows a blank frame, a security plugin or host header on <em>your</em> site may be blocking framing: allow framing for URLs containing <code>tjq_embed</code>.</p>
		</div>
		<?php
	}

	/* ---------- Settings ---------- */

	public static function detect_brand() {
		self::guard( 'tjq_detect_brand' );
		$found = TJQ_Brand::detect();
		$msg   = 'detect_fail';
		if ( ! is_wp_error( $found ) && $found ) {
			update_option( TJQ_Settings::OPTION, array_merge( TJQ_Settings::all(), $found ) );
			$msg = 'detected';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-settings&tjq_msg=' . $msg ) );
		exit;
	}

	public static function create_pages() {
		self::guard( 'tjq_create_pages' );
		list( $created, $existing ) = TJQ_SEO::create_pages();
		wp_safe_redirect( admin_url( 'admin.php?page=tjq-settings&tjq_msg=pages&n=' . (int) $created . '&e=' . (int) $existing ) );
		exit;
	}

	public static function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$vals     = TJQ_Settings::all();
		$sections = array();
		foreach ( TJQ_Settings::fields() as $key => $f ) {
			$sections[ $f['section'] ][ $key ] = $f;
		}
		?>
		<div class="wrap">
			<h1>Tajweed Quiz Settings</h1>

			<h2>Quick setup</h2>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_detect_brand' ), 'tjq_detect_brand' ) ); ?>">Detect brand from homepage</a>
				&nbsp;
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tjq_create_pages' ), 'tjq_create_pages' ) ); ?>">Create the 6 landing pages (as drafts)</a>
			</p>
			<p class="description">The quiz inherits your theme's font. "Detect" reads your homepage colours and logo. The landing pages are: general test, beginner, intermediate, advanced, kids readiness and the 14-day challenge, each with SEO title, description and FAQ schema.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'tjq_group' ); ?>
				<?php foreach ( $sections as $title => $fields ) : ?>
					<h2><?php echo esc_html( $title ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( $fields as $key => $f ) : ?>
							<tr>
								<th scope="row"><label for="tjq_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
								<td>
									<?php
									$name = TJQ_Settings::OPTION . '[' . $key . ']';
									$id   = 'tjq_' . $key;
									$v    = $vals[ $key ];
									if ( 'textarea' === $f['type'] ) {
										printf( '<textarea id="%s" name="%s" rows="3" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $v ) );
									} elseif ( 'checkbox' === $f['type'] ) {
										printf( '<input type="checkbox" id="%s" name="%s" value="1" %s>', esc_attr( $id ), esc_attr( $name ), checked( $v, 1, false ) );
									} elseif ( 'select' === $f['type'] ) {
										printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
										foreach ( $f['options'] as $ov => $ol ) {
											printf( '<option value="%s" %s>%s</option>', esc_attr( $ov ), selected( $v, $ov, false ), esc_html( $ol ) );
										}
										echo '</select>';
									} else {
										printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">', esc_attr( $f['type'] ), esc_attr( $id ), esc_attr( $name ), esc_attr( $v ) );
									}
									if ( ! empty( $f['help'] ) ) {
										echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

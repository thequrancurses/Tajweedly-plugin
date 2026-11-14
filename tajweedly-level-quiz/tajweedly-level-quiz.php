<?php
/**
 * Plugin Name:       Tajweedly Level Quiz
 * Plugin URI:        https://tajweedly.com
 * Description:       Tajweed level tests (beginner / intermediate / advanced / kids), consented lead capture, email follow-up, referrals, blog CTAs and SEO pages. Shortcodes: [tajweedly_quiz] [tajweedly_challenge] [tajweedly_cta]
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Tajweedly
 * License:           GPL-2.0-or-later
 * Text Domain:       tajweedly-quiz
 */

defined( 'ABSPATH' ) || exit;

define( 'TJQ_VERSION', '1.3.0' );
define( 'TJQ_FILE', __FILE__ );
define( 'TJQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'TJQ_URL', plugin_dir_url( __FILE__ ) );

require_once TJQ_DIR . 'includes/class-tjq-settings.php';
require_once TJQ_DIR . 'includes/class-tjq-i18n.php';
require_once TJQ_DIR . 'includes/class-tjq-brand.php';
require_once TJQ_DIR . 'includes/class-tjq-questions.php';
require_once TJQ_DIR . 'includes/class-tjq-db.php';
require_once TJQ_DIR . 'includes/class-tjq-sheet.php';
require_once TJQ_DIR . 'includes/class-tjq-mailer.php';
require_once TJQ_DIR . 'includes/class-tjq-drip.php';
require_once TJQ_DIR . 'includes/class-tjq-mushaf.php';
require_once TJQ_DIR . 'includes/class-tjq-content.php';
require_once TJQ_DIR . 'includes/class-tjq-tools.php';
require_once TJQ_DIR . 'includes/class-tjq-seo.php';
require_once TJQ_DIR . 'includes/class-tjq-rest.php';
require_once TJQ_DIR . 'includes/class-tjq-shortcode.php';
require_once TJQ_DIR . 'includes/class-tjq-privacy.php';
require_once TJQ_DIR . 'includes/class-tjq-admin.php';

register_activation_hook( __FILE__, array( 'TJQ_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'TJQ_Drip', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'tjq_db_version' ) !== TJQ_VERSION ) {
			TJQ_DB::install();
		}
		TJQ_Shortcode::init();
		TJQ_REST::init();
		TJQ_Mailer::init();
		TJQ_Sheet::init();
		TJQ_Drip::init();
		TJQ_Mushaf::init();
		TJQ_Content::init();
		TJQ_Tools::init();
		TJQ_SEO::init();
		TJQ_Privacy::init();
		if ( is_admin() ) {
			TJQ_Admin::init();
		}
	}
);

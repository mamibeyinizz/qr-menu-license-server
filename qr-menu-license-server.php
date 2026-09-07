<?php
/**
 * Plugin Name:       QR Menu License Server
 * Plugin URI:        https://qrmenuofficial.com/
 * Description:       QR Menü müşteri plugin'leri için API key doğrulaması yapar ve key'e tanımlı modül listesini JSON olarak döndürür.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            QR Menü Official
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qmls
 * Domain Path:       /languages
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

define( 'QMLS_VERSION', '1.1.0' );
define( 'QMLS_PLUGIN_FILE', __FILE__ );
define( 'QMLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QMLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once QMLS_PLUGIN_DIR . 'includes/class-helpers.php';
require_once QMLS_PLUGIN_DIR . 'includes/class-cpt.php';
require_once QMLS_PLUGIN_DIR . 'includes/class-admin.php';
require_once QMLS_PLUGIN_DIR . 'includes/class-rest-api.php';

/*
 * REST route'u plugin yüklenirken bağlanır: endpoint'in varlığı
 * plugins_loaded zincirine ya da başka bir bileşenin başlatılmasına bağlı
 * kalmaz. rest_api_init zaten çok daha geç tetiklendiği için erken bağlamanın
 * bir maliyeti yoktur.
 */
QMLS_REST_API::init();

/**
 * Plugin bileşenlerini başlatır.
 *
 * @return void
 */
function qmls_init() {
	load_plugin_textdomain( 'qmls', false, dirname( plugin_basename( QMLS_PLUGIN_FILE ) ) . '/languages' );

	new QMLS_CPT();
	new QMLS_Admin();
}
add_action( 'plugins_loaded', 'qmls_init' );

/**
 * Aktivasyonda post type'ı kaydeder ve rewrite kurallarını temizler.
 *
 * @return void
 */
function qmls_activate() {
	QMLS_CPT::register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'qmls_activate' );

/**
 * Devre dışı bırakıldığında rewrite kurallarını temizler.
 *
 * @return void
 */
function qmls_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'qmls_deactivate' );

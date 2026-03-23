<?php
/**
 * Plugin Name: NitRedis
 * Plugin URI:  https://nusite.co.uk/nitredis
 * Description: High-performance Redis object caching for WordPress — powered by Nusite I.T Consulting Limited.
 * Version:     1.0.1
 * Author:      Nusite I.T Consulting Limited
 * Author URI:  https://nusite.co.uk
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nitredis
 * Domain Path: /languages
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

define( 'NITREDIS_VERSION',   '1.0.1' );
define( 'NITREDIS_FILE',      __FILE__ );
define( 'NITREDIS_DIR',       plugin_dir_path( __FILE__ ) );
define( 'NITREDIS_URL',       plugin_dir_url( __FILE__ ) );
define( 'NITREDIS_DROP_IN',   WP_CONTENT_DIR . '/object-cache.php' );
define( 'NITREDIS_STUB',      NITREDIS_DIR . 'includes/object-cache.php' );

// ── Autoload ──────────────────────────────────────────────────────────────────
require_once NITREDIS_DIR . 'includes/class-nitredis-connection.php';
require_once NITREDIS_DIR . 'includes/class-nitredis-cache.php';
require_once NITREDIS_DIR . 'includes/class-nitredis-diagnostics.php';
require_once NITREDIS_DIR . 'includes/class-nitredis-settings.php';
require_once NITREDIS_DIR . 'includes/class-nitredis-scanner.php';
require_once NITREDIS_DIR . 'includes/class-nitredis-updater.php';

if ( is_admin() ) {
    require_once NITREDIS_DIR . 'admin/class-nitredis-admin.php';
    NitRedis_Admin::init();

    // GitHub update checker — runs on admin requests only.
    ( new NitRedis_Updater() )->init();
}

// ── Activation / deactivation ─────────────────────────────────────────────────
register_activation_hook(   __FILE__, [ 'NitRedis_Settings', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'NitRedis_Settings', 'deactivate' ] );

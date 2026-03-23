<?php
/**
 * NitRedis — Admin Controller
 *
 * Registers menu pages, handles AJAX actions, and enqueues assets.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_nitredis_flush',          [ __CLASS__, 'ajax_flush' ] );
        add_action( 'wp_ajax_nitredis_save_settings',  [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_nitredis_install_dropin', [ __CLASS__, 'ajax_install_dropin' ] );
        add_action( 'wp_ajax_nitredis_remove_dropin',  [ __CLASS__, 'ajax_remove_dropin' ] );
        add_action( 'wp_ajax_nitredis_get_metrics',    [ __CLASS__, 'ajax_get_metrics' ] );
        add_action( 'wp_ajax_nitredis_scan_config',    [ __CLASS__, 'ajax_scan_config' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( NITREDIS_FILE ), [ __CLASS__, 'action_links' ] );
    }

    public static function register_menu() {
        add_menu_page(
            'NitRedis',
            'NitRedis',
            'manage_options',
            'nitredis',
            [ __CLASS__, 'render_dashboard' ],
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="#0073aa"/><text x="10" y="14" text-anchor="middle" font-size="10" fill="white" font-family="sans-serif">NR</text></svg>' ),
            80
        );

        add_submenu_page( 'nitredis', 'Dashboard',   'Dashboard',   'manage_options', 'nitredis',                 [ __CLASS__, 'render_dashboard' ] );
        add_submenu_page( 'nitredis', 'Settings',    'Settings',    'manage_options', 'nitredis-settings',        [ __CLASS__, 'render_settings' ] );
        add_submenu_page( 'nitredis', 'Diagnostics', 'Diagnostics', 'manage_options', 'nitredis-diagnostics',     [ __CLASS__, 'render_diagnostics' ] );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'nitredis' ) === false ) {
            return;
        }
        wp_enqueue_style(  'nitredis-admin', NITREDIS_URL . 'assets/css/admin.css', [],   NITREDIS_VERSION );
        wp_enqueue_script( 'nitredis-admin', NITREDIS_URL . 'assets/js/admin.js',   [ 'jquery' ], NITREDIS_VERSION, true );
        wp_localize_script( 'nitredis-admin', 'NitRedis', [
            'nonce'    => wp_create_nonce( 'nitredis_nonce' ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    // ── Page renderers ────────────────────────────────────────────────────────

    public static function render_dashboard() {
        require NITREDIS_DIR . 'admin/views/dashboard.php';
    }

    public static function render_settings() {
        require NITREDIS_DIR . 'admin/views/settings.php';
    }

    public static function render_diagnostics() {
        require NITREDIS_DIR . 'admin/views/diagnostics.php';
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    private static function check_nonce() {
        check_ajax_referer( 'nitredis_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
        }
    }

    public static function ajax_flush() {
        self::check_nonce();
        $result = NitRedis_Cache::flush();
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Cache flushed successfully.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to flush cache. Check connection.' ] );
        }
    }

    public static function ajax_save_settings() {
        self::check_nonce();
        $data   = wp_unslash( $_POST['settings'] ?? [] );
        $saved  = NitRedis_Settings::save( $data );
        // Re-test connection with new settings.
        NitRedis_Connection::disconnect();
        $ping = NitRedis_Cache::ping();
        if ( $saved ) {
            wp_send_json_success( [
                'message'   => 'Settings saved.',
                'connected' => $ping,
            ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to save settings.' ] );
        }
    }

    public static function ajax_install_dropin() {
        self::check_nonce();
        $result = NitRedis_Diagnostics::install_drop_in();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        } else {
            wp_send_json_success( [ 'message' => 'Drop-in installed. Object caching is now active.' ] );
        }
    }

    public static function ajax_remove_dropin() {
        self::check_nonce();
        $result = NitRedis_Diagnostics::remove_drop_in();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        } else {
            wp_send_json_success( [ 'message' => 'Drop-in removed. Object caching is disabled.' ] );
        }
    }

    public static function ajax_get_metrics() {
        self::check_nonce();
        $metrics   = NitRedis_Cache::get_metrics();
        $key_count = NitRedis_Cache::key_count();
        wp_send_json_success( array_merge( $metrics, [ 'key_count' => $key_count ] ) );
    }

    public static function ajax_scan_config() {
        self::check_nonce();
        $result = NitRedis_Scanner::scan();
        wp_send_json_success( $result );
    }

    // ── Plugin action links ───────────────────────────────────────────────────

    public static function action_links( $links ) {
        $plugin_links = [
            '<a href="' . admin_url( 'admin.php?page=nitredis' ) . '">Dashboard</a>',
            '<a href="' . admin_url( 'admin.php?page=nitredis-settings' ) . '">Settings</a>',
        ];
        return array_merge( $plugin_links, $links );
    }
}

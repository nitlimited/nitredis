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
        add_action( 'wp_ajax_nitredis_test_github',     [ __CLASS__, 'ajax_test_github' ] );
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

        // $_POST['settings'] is populated as an array when JS sends settings[key]=value.
        // Fall back to full $_POST minus the housekeeping keys if the sub-array is missing.
        if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
            $data = wp_unslash( $_POST['settings'] );
        } else {
            $exclude = [ 'action', 'nonce', '_wpnonce' ];
            $data    = array_diff_key( wp_unslash( $_POST ), array_flip( $exclude ) );
        }

        if ( empty( $data ) ) {
            wp_send_json_error( [ 'message' => 'No settings data received. Please try again.' ] );
            return;
        }

        try {
            $saved = NitRedis_Settings::save( $data );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => 'Save error: ' . $e->getMessage() ] );
            return;
        }

        // Re-test connection with new settings.
        NitRedis_Connection::disconnect();
        $ping = NitRedis_Cache::ping();

        if ( $saved ) {
            wp_send_json_success( [
                'message'   => 'Settings saved.' . ( $ping ? ' Redis connection verified.' : ' Warning: could not connect to Redis with these settings.' ),
                'connected' => $ping,
            ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to write settings to the database. Check filesystem permissions.' ] );
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

    public static function ajax_test_github() {
        self::check_nonce();

        $repo  = sanitize_text_field( $_POST['repo']  ?? '' );
        $token = sanitize_text_field( $_POST['token'] ?? '' );

        if ( empty( $repo ) || ! preg_match( '/^[\w.-]+\/[\w.-]+$/', $repo ) ) {
            wp_send_json_error( [ 'message' => 'Invalid repository format. Use: owner/repo' ] );
            return;
        }

        $args = [
            'timeout'    => 8,
            'user-agent' => 'NitRedis/' . NITREDIS_VERSION,
            'headers'    => [ 'Accept' => 'application/vnd.github+json' ],
        ];
        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( "https://api.github.com/repos/{$repo}/releases/latest", $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Request failed: ' . $response->get_error_message() ] );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['tag_name'] ) ) {
            wp_send_json_success( [
                'message' => 'Connected! Latest release: ' . esc_html( $body['tag_name'] )
                           . ( ! empty( $body['published_at'] ) ? ' (' . date( 'M j, Y', strtotime( $body['published_at'] ) ) . ')' : '' ),
            ] );
        } elseif ( $code === 404 ) {
            wp_send_json_error( [ 'message' => 'Repository not found. Check the slug and make sure it is public (or provide a token for private repos).' ] );
        } elseif ( $code === 401 || $code === 403 ) {
            wp_send_json_error( [ 'message' => 'Authentication failed. Check your GitHub token.' ] );
        } elseif ( $code === 200 ) {
            wp_send_json_success( [ 'message' => 'Repository found but no releases published yet.' ] );
        } else {
            wp_send_json_error( [ 'message' => "GitHub API returned HTTP {$code}." ] );
        }
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

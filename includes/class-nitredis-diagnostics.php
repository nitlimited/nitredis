<?php
/**
 * NitRedis — Diagnostics
 *
 * System checks and environment validation.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Diagnostics {

    /**
     * Run all diagnostic checks and return an array of results.
     *
     * Each item: [ 'label', 'status' (ok|warn|error), 'message' ]
     *
     * @return array
     */
    public static function run() {
        $results = [];

        // PHP version.
        $php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
        $results[] = [
            'label'   => 'PHP Version',
            'status'  => $php_ok ? 'ok' : 'error',
            'message' => 'PHP ' . PHP_VERSION . ( $php_ok ? ' — OK' : ' — NitRedis requires PHP 7.4+' ),
        ];

        // PhpRedis extension.
        $ext = class_exists( 'Redis' );
        $results[] = [
            'label'   => 'PhpRedis Extension',
            'status'  => $ext ? 'ok' : 'warn',
            'message' => $ext ? 'Loaded (ext-redis)' : 'Not loaded — Predis fallback will be used if available',
        ];

        // Predis library.
        $predis = class_exists( 'Predis\Client' );
        $results[] = [
            'label'   => 'Predis Library',
            'status'  => $predis ? 'ok' : ( $ext ? 'ok' : 'error' ),
            'message' => $predis ? 'Available' : ( $ext ? 'Not needed (ext-redis is present)' : 'Not found — install via Composer' ),
        ];

        // Drop-in status.
        $drop_in_exists = file_exists( NITREDIS_DROP_IN );
        $drop_in_ours   = $drop_in_exists && self::is_our_drop_in();
        $results[] = [
            'label'   => 'object-cache.php Drop-in',
            'status'  => $drop_in_ours ? 'ok' : ( $drop_in_exists ? 'warn' : 'error' ),
            'message' => $drop_in_ours
                ? 'Active — NitRedis drop-in is installed'
                : ( $drop_in_exists
                    ? 'A different object-cache.php drop-in is present'
                    : 'Not installed — use the button above to enable caching' ),
        ];

        // Redis connectivity.
        $connected = NitRedis_Cache::ping();
        $results[] = [
            'label'   => 'Redis Connection',
            'status'  => $connected ? 'ok' : 'error',
            'message' => $connected
                ? 'Connected successfully'
                : ( 'Unable to connect — ' . ( NitRedis_Connection::$last_error ?? 'unknown error' ) ),
        ];

        // WP_CACHE constant.
        $wp_cache = defined( 'WP_CACHE' ) && WP_CACHE;
        $results[] = [
            'label'   => 'WP_CACHE Constant',
            'status'  => $wp_cache ? 'ok' : 'warn',
            'message' => $wp_cache ? 'WP_CACHE is true' : 'WP_CACHE is not defined or false — add define(\'WP_CACHE\', true) to wp-config.php',
        ];

        // wp-content writable.
        $writable = is_writable( WP_CONTENT_DIR );
        $results[] = [
            'label'   => 'wp-content Writable',
            'status'  => $writable ? 'ok' : 'error',
            'message' => $writable ? 'Directory is writable' : 'wp-content is not writable — cannot install drop-in',
        ];

        return $results;
    }

    /**
     * Check if the currently installed drop-in belongs to NitRedis.
     *
     * @return bool
     */
    public static function is_our_drop_in() {
        if ( ! file_exists( NITREDIS_DROP_IN ) ) {
            return false;
        }
        $header = file_get_contents( NITREDIS_DROP_IN, false, null, 0, 512 );
        return strpos( $header, 'NitRedis' ) !== false;
    }

    /**
     * Install the drop-in file.
     *
     * @return bool|WP_Error
     */
    public static function install_drop_in() {
        if ( ! file_exists( NITREDIS_STUB ) ) {
            return new WP_Error( 'missing_stub', 'Drop-in stub not found inside the plugin.' );
        }
        if ( ! is_writable( WP_CONTENT_DIR ) ) {
            return new WP_Error( 'not_writable', 'wp-content is not writable.' );
        }
        $copied = copy( NITREDIS_STUB, NITREDIS_DROP_IN );
        return $copied ? true : new WP_Error( 'copy_failed', 'Failed to copy object-cache.php.' );
    }

    /**
     * Remove the drop-in file (only if it is ours).
     *
     * @return bool|WP_Error
     */
    public static function remove_drop_in() {
        if ( ! self::is_our_drop_in() ) {
            return new WP_Error( 'not_ours', 'The existing drop-in does not belong to NitRedis.' );
        }
        $deleted = unlink( NITREDIS_DROP_IN );
        return $deleted ? true : new WP_Error( 'delete_failed', 'Failed to delete object-cache.php.' );
    }
}

<?php
/**
 * NitRedis — Settings
 *
 * Manages plugin options and activation / deactivation hooks.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Settings {

    /** Option key. */
    const OPTION_KEY = 'nitredis_settings';

    /**
     * Default settings.
     *
     * @return array
     */
    public static function defaults() {
        return [
            'scheme'       => 'tcp',
            'host'         => '127.0.0.1',
            'port'         => 6379,
            'database'     => 0,
            'password'     => '',
            'username'     => '',
            'timeout'      => 1.0,
            'read_timeout' => 1.0,
            'prefix'       => 'nitredis_',
            'path'         => '',
            'ssl'          => false,
            'global_groups'    => [ 'blog-details', 'blog-id-cache', 'blog-lookup', 'global-posts',
                                    'networks', 'rss', 'sites', 'site-details', 'site-lookup',
                                    'site-options', 'site-transient', 'users', 'useremail',
                                    'userlogins', 'usermeta', 'user_meta', 'userslugs' ],
            'ignored_groups'   => [ 'counts', 'plugins' ],
            'max_ttl'          => 0,  // 0 = no limit.
        ];
    }

    /**
     * Get settings, merging saved values with defaults.
     *
     * @return array
     */
    public static function get() {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), self::defaults() );
    }

    /**
     * Save settings.
     *
     * @param array $data
     * @return bool
     */
    public static function save( array $data ) {
        $defaults = self::defaults();
        $clean    = [];

        $clean['scheme']       = in_array( $data['scheme'] ?? 'tcp', [ 'tcp', 'unix', 'tls' ] ) ? $data['scheme'] : 'tcp';
        $clean['host']         = sanitize_text_field( $data['host']     ?? '127.0.0.1' );
        $clean['port']         = (int) ( $data['port']                  ?? 6379 );
        $clean['database']     = (int) ( $data['database']              ?? 0 );
        $clean['password']     = sanitize_text_field( $data['password'] ?? '' );
        $clean['username']     = sanitize_text_field( $data['username'] ?? '' );
        $clean['timeout']      = (float) ( $data['timeout']             ?? 1.0 );
        $clean['read_timeout'] = (float) ( $data['read_timeout']        ?? 1.0 );
        $clean['prefix']       = sanitize_key( $data['prefix']          ?? 'nitredis_' );
        $clean['path']         = sanitize_text_field( $data['path']     ?? '' );
        $clean['ssl']          = ! empty( $data['ssl'] );
        $clean['max_ttl']      = (int) ( $data['max_ttl']               ?? 0 );

        $clean['global_groups']  = array_map( 'sanitize_text_field',
            array_filter( explode( "\n", str_replace( "\r", '', $data['global_groups_raw'] ?? '' ) ) ) );
        $clean['ignored_groups'] = array_map( 'sanitize_text_field',
            array_filter( explode( "\n", str_replace( "\r", '', $data['ignored_groups_raw'] ?? '' ) ) ) );

        return update_option( self::OPTION_KEY, $clean );
    }

    /**
     * Plugin activation: set default options.
     */
    public static function activate() {
        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, self::defaults() );
        }
    }

    /**
     * Plugin deactivation: optionally remove the drop-in.
     */
    public static function deactivate() {
        if ( NitRedis_Diagnostics::is_our_drop_in() ) {
            NitRedis_Diagnostics::remove_drop_in();
        }
    }
}

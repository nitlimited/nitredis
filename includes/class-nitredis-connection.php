<?php
/**
 * NitRedis — Redis Connection Manager
 *
 * Handles connecting to Redis via PhpRedis (ext-redis) or Predis (fallback).
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Connection {

    /** @var Redis|Predis\Client|null */
    private static $client = null;

    /** @var bool */
    private static $connected = false;

    /** @var string|null Last connection error */
    public static $last_error = null;

    /**
     * Attempt to establish a Redis connection and return the client.
     *
     * @return Redis|object|false
     */
    public static function connect() {
        if ( self::$connected && self::$client ) {
            return self::$client;
        }

        $options = self::get_options();

        try {
            if ( class_exists( 'Redis' ) ) {
                self::$client = self::connect_phpredis( $options );
            } elseif ( class_exists( 'Predis\Client' ) ) {
                self::$client = self::connect_predis( $options );
            } else {
                throw new Exception( 'Neither PhpRedis extension nor Predis library is available.' );
            }

            self::$connected  = true;
            self::$last_error = null;

        } catch ( Exception $e ) {
            self::$connected  = false;
            self::$client     = null;
            self::$last_error = $e->getMessage();
            error_log( 'NitRedis connection error: ' . $e->getMessage() );
        }

        return self::$connected ? self::$client : false;
    }

    /**
     * Connect using the PhpRedis C extension.
     *
     * @param array $opts
     * @return Redis
     */
    private static function connect_phpredis( array $opts ) {
        $redis = new Redis();

        if ( ! empty( $opts['scheme'] ) && $opts['scheme'] === 'unix' ) {
            $redis->connect( $opts['path'] );
        } elseif ( ! empty( $opts['ssl'] ) ) {
            $ssl_ctx = $opts['ssl_context'] ?? [];
            $redis->connect( 'tls://' . $opts['host'], $opts['port'], $opts['timeout'], null, 0, $opts['read_timeout'], $ssl_ctx );
        } else {
            $redis->connect( $opts['host'], $opts['port'], $opts['timeout'], null, 0, $opts['read_timeout'] );
        }

        if ( ! empty( $opts['password'] ) ) {
            if ( ! empty( $opts['username'] ) ) {
                $redis->auth( [ $opts['username'], $opts['password'] ] );
            } else {
                $redis->auth( $opts['password'] );
            }
        }

        $redis->select( (int) $opts['database'] );

        if ( ! empty( $opts['prefix'] ) ) {
            $redis->setOption( Redis::OPT_PREFIX, $opts['prefix'] );
        }

        $redis->setOption( Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP );

        return $redis;
    }

    /**
     * Connect using the Predis library.
     *
     * @param array $opts
     * @return Predis\Client
     */
    private static function connect_predis( array $opts ) {
        $params = [
            'scheme'       => $opts['scheme'] ?? 'tcp',
            'host'         => $opts['host'],
            'port'         => $opts['port'],
            'database'     => $opts['database'],
            'timeout'      => $opts['timeout'],
            'read_write_timeout' => $opts['read_timeout'],
        ];

        if ( ! empty( $opts['path'] ) ) {
            $params['path'] = $opts['path'];
        }
        if ( ! empty( $opts['password'] ) ) {
            $params['password'] = $opts['password'];
        }

        $client = new Predis\Client( $params, [ 'prefix' => $opts['prefix'] ?? '' ] );
        $client->connect();
        return $client;
    }

    /**
     * Disconnect and clear the stored client.
     */
    public static function disconnect() {
        if ( self::$client ) {
            try {
                if ( self::$client instanceof Redis ) {
                    self::$client->close();
                } else {
                    self::$client->disconnect();
                }
            } catch ( Exception $e ) {
                // Ignore disconnect errors.
            }
        }
        self::$client    = null;
        self::$connected = false;
    }

    /**
     * Return connection status.
     *
     * @return bool
     */
    public static function is_connected() {
        return self::$connected;
    }

    /**
     * Build connection options from WP constants / DB options.
     *
     * @return array
     */
    public static function get_options() {
        $settings = get_option( 'nitredis_settings', [] );

        return [
            'scheme'       => defined( 'NITREDIS_SCHEME' )       ? NITREDIS_SCHEME       : ( $settings['scheme']       ?? 'tcp' ),
            'host'         => defined( 'NITREDIS_HOST' )         ? NITREDIS_HOST         : ( $settings['host']         ?? '127.0.0.1' ),
            'port'         => defined( 'NITREDIS_PORT' )         ? NITREDIS_PORT         : (int) ( $settings['port']   ?? 6379 ),
            'database'     => defined( 'NITREDIS_DATABASE' )     ? NITREDIS_DATABASE     : (int) ( $settings['database'] ?? 0 ),
            'password'     => defined( 'NITREDIS_PASSWORD' )     ? NITREDIS_PASSWORD     : ( $settings['password']     ?? '' ),
            'username'     => defined( 'NITREDIS_USERNAME' )     ? NITREDIS_USERNAME     : ( $settings['username']     ?? '' ),
            'timeout'      => defined( 'NITREDIS_TIMEOUT' )      ? NITREDIS_TIMEOUT      : (float) ( $settings['timeout']      ?? 1.0 ),
            'read_timeout' => defined( 'NITREDIS_READ_TIMEOUT' ) ? NITREDIS_READ_TIMEOUT : (float) ( $settings['read_timeout']  ?? 1.0 ),
            'prefix'       => defined( 'NITREDIS_PREFIX' )       ? NITREDIS_PREFIX       : ( $settings['prefix']       ?? 'nitredis_' ),
            'path'         => defined( 'NITREDIS_PATH' )         ? NITREDIS_PATH         : ( $settings['path']         ?? '' ),
            'ssl'          => defined( 'NITREDIS_SSL' )          ? NITREDIS_SSL          : ( $settings['ssl']          ?? false ),
            'ssl_context'  => defined( 'NITREDIS_SSL_CONTEXT' )  ? NITREDIS_SSL_CONTEXT  : ( $settings['ssl_context']  ?? [] ),
        ];
    }
}

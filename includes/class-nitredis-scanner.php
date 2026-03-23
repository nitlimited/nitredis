<?php
/**
 * NitRedis — wp-config.php Scanner
 *
 * Parses wp-config.php (and wp-config-sample.php as fallback) looking for
 * Redis-related constants and environment variables from common plugins/hosts.
 *
 * Detects:
 *  - NitRedis own constants  (NITREDIS_*)
 *  - WP Redis / Redis Cache Pro  (WP_REDIS_*)
 *  - W3 Total Cache              (W3TC_REDIS_*)
 *  - Pantheon / WP Engine / Kinsta env vars via getenv()
 *  - Generic REDIS_* constants
 *  - WP_CACHE constant
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Scanner {

    /**
     * Attempt to locate wp-config.php.
     *
     * WordPress core uses the same search strategy.
     *
     * @return string|false Absolute path or false if not found.
     */
    public static function locate_config() {
        $candidates = [
            ABSPATH . 'wp-config.php',
            dirname( ABSPATH ) . '/wp-config.php',
            // Some setups place it two levels up.
            dirname( dirname( ABSPATH ) ) . '/wp-config.php',
        ];

        foreach ( $candidates as $path ) {
            if ( @file_exists( $path ) && @is_readable( $path ) ) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Read and scan wp-config.php, returning detected Redis settings.
     *
     * @return array {
     *   'found'    => bool,
     *   'path'     => string,
     *   'source'   => string,          // human-readable origin description
     *   'settings' => array,           // partial settings array (only detected keys)
     *   'constants'=> array,           // raw constant name => value map
     *   'warnings' => string[],        // any notes / caveats
     * }
     */
    public static function scan() {
        $result = [
            'found'     => false,
            'path'      => '',
            'source'    => '',
            'settings'  => [],
            'constants' => [],
            'warnings'  => [],
        ];

        $config_path = self::locate_config();
        if ( ! $config_path ) {
            $result['warnings'][] = 'wp-config.php could not be located. Checked: '
                . ABSPATH . ', ' . dirname( ABSPATH ) . '.';
            return $result;
        }

        $result['path'] = $config_path;

        $raw = @file_get_contents( $config_path );
        if ( $raw === false ) {
            $result['warnings'][] = 'wp-config.php was found but could not be read (permission denied?).';
            return $result;
        }

        // ── 1. Extract all define() calls ────────────────────────────────────
        // Matches: define( 'CONST_NAME', value ) — handles single/double quotes,
        // integers, floats, booleans, and string values.
        $defines = [];
        preg_match_all(
            "/define\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*,\s*(.*?)\s*\)\s*;/s",
            $raw,
            $matches,
            PREG_SET_ORDER
        );
        foreach ( $matches as $m ) {
            $defines[ $m[1] ] = self::parse_value( $m[2] );
        }

        // ── 2. Build normalised settings from all known patterns ──────────────

        $settings  = [];
        $constants = [];
        $sources   = [];

        // Helper: record a found constant.
        $record = function( $const, $value ) use ( &$constants ) {
            $constants[ $const ] = $value;
        };

        // ── NitRedis native constants ─────────────────────────────────────────
        $nitredis_map = [
            'NITREDIS_SCHEME'       => 'scheme',
            'NITREDIS_HOST'         => 'host',
            'NITREDIS_PORT'         => 'port',
            'NITREDIS_DATABASE'     => 'database',
            'NITREDIS_PASSWORD'     => 'password',
            'NITREDIS_USERNAME'     => 'username',
            'NITREDIS_TIMEOUT'      => 'timeout',
            'NITREDIS_READ_TIMEOUT' => 'read_timeout',
            'NITREDIS_PREFIX'       => 'prefix',
            'NITREDIS_PATH'         => 'path',
            'NITREDIS_SSL'          => 'ssl',
        ];
        foreach ( $nitredis_map as $const => $key ) {
            if ( isset( $defines[ $const ] ) ) {
                $settings[ $key ] = $defines[ $const ];
                $record( $const, $defines[ $const ] );
                $sources[] = 'NitRedis';
            } elseif ( defined( $const ) ) {
                // Already loaded as a PHP constant at runtime.
                $settings[ $key ] = constant( $const );
                $record( $const, constant( $const ) );
                $sources[] = 'NitRedis (runtime)';
            }
        }

        // ── WP Redis / Redis Cache Pro (WP_REDIS_*) ───────────────────────────
        $wp_redis_map = [
            'WP_REDIS_SCHEME'       => 'scheme',
            'WP_REDIS_HOST'         => 'host',
            'WP_REDIS_PORT'         => 'port',
            'WP_REDIS_DATABASE'     => 'database',
            'WP_REDIS_PASSWORD'     => 'password',
            'WP_REDIS_USER'         => 'username',
            'WP_REDIS_TIMEOUT'      => 'timeout',
            'WP_REDIS_READ_TIMEOUT' => 'read_timeout',
            'WP_REDIS_PREFIX'       => 'prefix',
            'WP_REDIS_PATH'         => 'path',
            'WP_REDIS_SSL'          => 'ssl',
        ];
        foreach ( $wp_redis_map as $const => $key ) {
            if ( isset( $defines[ $const ] ) && ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $defines[ $const ];
                $record( $const, $defines[ $const ] );
                $sources[] = 'WP Redis / Redis Cache Pro';
            } elseif ( defined( $const ) && ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = constant( $const );
                $record( $const, constant( $const ) );
                $sources[] = 'WP Redis / Redis Cache Pro (runtime)';
            }
        }

        // ── W3 Total Cache (W3TC_REDIS_*) ─────────────────────────────────────
        $w3tc_map = [
            'W3TC_REDIS_HOSTNAME' => 'host',
            'W3TC_REDIS_PORT'     => 'port',
            'W3TC_REDIS_PASSWORD' => 'password',
            'W3TC_REDIS_DATABASE' => 'database',
        ];
        foreach ( $w3tc_map as $const => $key ) {
            if ( isset( $defines[ $const ] ) && ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $defines[ $const ];
                $record( $const, $defines[ $const ] );
                $sources[] = 'W3 Total Cache';
            }
        }

        // ── Generic REDIS_* constants ─────────────────────────────────────────
        $generic_map = [
            'REDIS_HOST'     => 'host',
            'REDIS_PORT'     => 'port',
            'REDIS_PASSWORD' => 'password',
            'REDIS_DATABASE' => 'database',
            'REDIS_PREFIX'   => 'prefix',
            'REDIS_SCHEME'   => 'scheme',
        ];
        foreach ( $generic_map as $const => $key ) {
            if ( isset( $defines[ $const ] ) && ! isset( $settings[ $key ] ) ) {
                $settings[ $key ] = $defines[ $const ];
                $record( $const, $defines[ $const ] );
                $sources[] = 'Generic REDIS_*';
            }
        }

        // ── Environment variable patterns (Pantheon, Kinsta, WP Engine) ───────
        // Look for getenv() calls in the file for known env var names.
        $env_patterns = [
            'REDIS_URL'           => 'url',      // Heroku / Render DSN
            'REDISCLOUD_URL'      => 'url',
            'REDISTOGO_URL'       => 'url',
            'CACHE_HOST'          => 'host',
            'CACHE_PORT'          => 'port',
            'CACHE_PASSWORD'      => 'password',
        ];

        foreach ( $env_patterns as $env_var => $type ) {
            $val = getenv( $env_var );
            if ( $val === false ) {
                continue;
            }
            if ( $type === 'url' ) {
                $parsed = self::parse_redis_url( $val );
                if ( $parsed ) {
                    foreach ( $parsed as $k => $v ) {
                        if ( ! isset( $settings[ $k ] ) ) {
                            $settings[ $k ] = $v;
                        }
                    }
                    $record( $env_var . ' (env)', $val );
                    $sources[] = 'Environment variable (' . $env_var . ')';
                }
            } else {
                if ( ! isset( $settings[ $type ] ) ) {
                    $settings[ $type ] = $val;
                    $record( $env_var . ' (env)', $val );
                    $sources[] = 'Environment variable (' . $env_var . ')';
                }
            }
        }

        // ── Also scan for inline getenv() calls in wp-config.php ─────────────
        // e.g. define('WP_REDIS_HOST', getenv('REDIS_HOST'));
        preg_match_all(
            "/define\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*,\s*getenv\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*\)\s*\)\s*;/",
            $raw,
            $env_matches,
            PREG_SET_ORDER
        );
        foreach ( $env_matches as $m ) {
            $const   = $m[1];
            $env_var = $m[2];
            $val     = getenv( $env_var );
            if ( $val !== false ) {
                // Map through known constant maps.
                foreach ( array_merge( $nitredis_map, $wp_redis_map, $generic_map ) as $c => $key ) {
                    if ( $c === $const && ! isset( $settings[ $key ] ) ) {
                        $settings[ $key ] = $val;
                        $record( $const . ' via getenv(' . $env_var . ')', $val );
                        $sources[] = 'Environment variable via define';
                    }
                }
            }
        }

        // ── WP_CACHE ──────────────────────────────────────────────────────────
        if ( isset( $defines['WP_CACHE'] ) ) {
            $record( 'WP_CACHE', $defines['WP_CACHE'] );
            if ( ! $defines['WP_CACHE'] ) {
                $result['warnings'][] = 'WP_CACHE is defined as false in wp-config.php — object caching will not work until this is set to true.';
            }
        } elseif ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
            $result['warnings'][] = 'WP_CACHE is not defined or is false. Add define(\'WP_CACHE\', true) to wp-config.php.';
        }

        // ── Finalise ─────────────────────────────────────────────────────────
        $result['found']     = ! empty( $settings );
        $result['settings']  = $settings;
        $result['constants'] = $constants;
        $result['source']    = implode( ', ', array_unique( $sources ) ) ?: 'none detected';

        if ( empty( $settings ) ) {
            $result['warnings'][] = 'No Redis configuration constants were found in wp-config.php.';
        }

        // Sanity-check: if a Redis URL was found, note it was parsed.
        if ( isset( $settings['_from_url'] ) ) {
            $result['warnings'][] = 'Host/port/password were parsed from a Redis DSN URL.';
            unset( $settings['_from_url'] );
        }

        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Parse a raw PHP value string into a native PHP value.
     *
     * Handles: 'string', "string", integer, float, true, false, null.
     *
     * @param string $raw
     * @return mixed
     */
    private static function parse_value( $raw ) {
        $raw = trim( $raw );

        // Boolean / null.
        $lower = strtolower( $raw );
        if ( $lower === 'true' )  return true;
        if ( $lower === 'false' ) return false;
        if ( $lower === 'null' )  return null;

        // Quoted string (single or double) — basic, no escape handling.
        if ( preg_match( "/^(['\"])(.*?)\\1$/s", $raw, $m ) ) {
            return $m[2];
        }

        // Numeric.
        if ( is_numeric( $raw ) ) {
            return strpos( $raw, '.' ) !== false ? (float) $raw : (int) $raw;
        }

        // Fallback — return as-is string.
        return $raw;
    }

    /**
     * Parse a redis:// or rediss:// DSN into a settings array.
     *
     * redis://[:password@]host[:port][/database]
     * rediss:// => TLS
     *
     * @param string $url
     * @return array|false
     */
    private static function parse_redis_url( $url ) {
        $parsed = parse_url( $url );
        if ( ! $parsed ) {
            return false;
        }

        $settings = [];
        $scheme   = $parsed['scheme'] ?? 'redis';

        $settings['scheme'] = ( $scheme === 'rediss' ) ? 'tls' : 'tcp';
        if ( $scheme === 'rediss' ) {
            $settings['ssl'] = true;
        }
        if ( ! empty( $parsed['host'] ) ) {
            $settings['host'] = $parsed['host'];
        }
        if ( ! empty( $parsed['port'] ) ) {
            $settings['port'] = (int) $parsed['port'];
        }
        if ( ! empty( $parsed['pass'] ) ) {
            $settings['password'] = urldecode( $parsed['pass'] );
        }
        if ( ! empty( $parsed['user'] ) ) {
            $settings['username'] = urldecode( $parsed['user'] );
        }
        if ( ! empty( $parsed['path'] ) && $parsed['path'] !== '/' ) {
            $db = ltrim( $parsed['path'], '/' );
            if ( is_numeric( $db ) ) {
                $settings['database'] = (int) $db;
            }
        }

        $settings['_from_url'] = true;
        return $settings;
    }
}

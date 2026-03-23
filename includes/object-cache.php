<?php
/**
 * NitRedis Object Cache Drop-in
 *
 * Copied to wp-content/object-cache.php by the NitRedis plugin.
 * NIT Limited — https://nitlimited.com
 *
 * IMPORTANT: This file defines ALL required WordPress object cache functions
 * unconditionally. If the NitRedis plugin is missing, renamed, or Redis is
 * unreachable, it falls back to a safe in-memory cache so the site ALWAYS loads.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

define( 'NITREDIS_DROP_IN_VERSION', '1.0.2' );

// ── Bootstrap: try to load the plugin classes ─────────────────────────────────
// Searches common plugin folder names so a rename doesn't cause a fatal.
// ALL requires are guarded by file_exists(). On any failure we set
// $_nitredis_loaded = false and the cache falls back to in-memory silently.

$GLOBALS['_nitredis_loaded'] = false;

$_nitredis_candidates = [
    WP_PLUGIN_DIR . '/nitredis',
    WP_PLUGIN_DIR . '/nitredis-plugin',
    WP_PLUGIN_DIR . '/NitRedis',
];

foreach ( $_nitredis_candidates as $_nr_dir ) {
    $_nr_conn     = $_nr_dir . '/includes/class-nitredis-connection.php';
    $_nr_cache    = $_nr_dir . '/includes/class-nitredis-cache.php';
    $_nr_settings = $_nr_dir . '/includes/class-nitredis-settings.php';

    if ( file_exists( $_nr_conn ) && file_exists( $_nr_cache ) && file_exists( $_nr_settings ) ) {
        try {
            if ( ! defined( 'NITREDIS_DIR' ) ) {
                define( 'NITREDIS_DIR', trailingslashit( $_nr_dir ) );
            }
            if ( ! defined( 'NITREDIS_FILE' ) ) {
                define( 'NITREDIS_FILE', $_nr_dir . '/nitredis.php' );
            }
            require_once $_nr_conn;
            require_once $_nr_cache;
            require_once $_nr_settings;
            $GLOBALS['_nitredis_loaded'] = true;
        } catch ( Throwable $e ) {
            error_log( 'NitRedis drop-in: failed to load classes — ' . $e->getMessage() );
        }
        break;
    }
}

unset( $_nitredis_candidates, $_nr_dir, $_nr_conn, $_nr_cache, $_nr_settings );

// ── WP_Object_Cache ────────────────────────────────────────────────────────────
// Always defined. Uses Redis when available; silently degrades to in-memory.

class WP_Object_Cache {

    /** @var Redis|object|false */
    private $redis = false;

    /** @var bool */
    private $redis_connected = false;

    /** @var array In-memory store (always active; avoids extra Redis round-trips). */
    private $local_cache = [];

    /** @var string[] Groups shared across all sites in a multisite network. */
    private $global_groups = [];

    /** @var string[] Groups that bypass Redis and stay in memory only. */
    private $ignored_groups = [];

    /** @var int|string Per-site key namespace prefix. */
    private $blog_prefix = 1;

    /** @var int */
    public $cache_hits = 0;

    /** @var int */
    public $cache_misses = 0;

    public function __construct() {
        global $blog_id;
        $this->blog_prefix = is_multisite() ? absint( $blog_id ) : 1;

        if ( $GLOBALS['_nitredis_loaded'] ) {
            try {
                $settings             = NitRedis_Settings::get();
                $this->global_groups  = (array) ( $settings['global_groups']  ?? [] );
                $this->ignored_groups = (array) ( $settings['ignored_groups'] ?? [] );
                $this->redis          = NitRedis_Connection::connect();
                $this->redis_connected = (bool) $this->redis;
            } catch ( Throwable $e ) {
                error_log( 'NitRedis drop-in: Redis connection failed — ' . $e->getMessage() );
            }
        }

        // Always ensure sensible defaults so group handling works even in fallback mode.
        if ( empty( $this->global_groups ) ) {
            $this->global_groups = [
                'blog-details', 'blog-id-cache', 'blog-lookup', 'global-posts',
                'networks', 'rss', 'sites', 'site-details', 'site-lookup',
                'site-options', 'site-transient', 'users', 'useremail',
                'userlogins', 'usermeta', 'user_meta', 'userslugs',
            ];
        }
        if ( empty( $this->ignored_groups ) ) {
            $this->ignored_groups = [ 'counts', 'plugins' ];
        }
    }

    // ── Key builder ───────────────────────────────────────────────────────────

    private function build_key( $key, $group ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }
        $prefix = in_array( $group, $this->global_groups, true ) ? '' : $this->blog_prefix . ':';
        return $prefix . $group . ':' . $key;
    }

    private function is_ignored( $group ) {
        return in_array( $group, $this->ignored_groups, true );
    }

    // ── Core API ──────────────────────────────────────────────────────────────

    public function get( $key, $group = 'default', $force = false, &$found = null ) {
        $ck = $this->build_key( $key, $group );

        if ( ! $force && array_key_exists( $ck, $this->local_cache ) ) {
            $found = true;
            $this->cache_hits++;
            $v = $this->local_cache[ $ck ];
            return is_object( $v ) ? clone $v : $v;
        }

        if ( $this->is_ignored( $group ) || ! $this->redis_connected ) {
            $found = array_key_exists( $ck, $this->local_cache );
            $found ? $this->cache_hits++ : $this->cache_misses++;
            return $found ? $this->local_cache[ $ck ] : false;
        }

        try {
            $value = $this->redis->get( $ck );
        } catch ( Throwable $e ) {
            $this->redis_connected = false;
            $found = false;
            $this->cache_misses++;
            return false;
        }

        if ( $value === false || $value === null ) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        $found = true;
        $this->cache_hits++;
        $this->local_cache[ $ck ] = $value;
        return is_object( $value ) ? clone $value : $value;
    }

    public function set( $key, $data, $group = 'default', $expire = 0 ) {
        $ck = $this->build_key( $key, $group );
        $this->local_cache[ $ck ] = is_object( $data ) ? clone $data : $data;

        if ( $this->is_ignored( $group ) || ! $this->redis_connected ) {
            return true;
        }

        try {
            if ( $GLOBALS['_nitredis_loaded'] ) {
                $settings = NitRedis_Settings::get();
                $max_ttl  = (int) ( $settings['max_ttl'] ?? 0 );
                if ( $max_ttl > 0 && ( $expire === 0 || $expire > $max_ttl ) ) {
                    $expire = $max_ttl;
                }
            }
            return (bool) ( $expire > 0
                ? $this->redis->setex( $ck, $expire, $data )
                : $this->redis->set( $ck, $data ) );
        } catch ( Throwable $e ) {
            $this->redis_connected = false;
            return false;
        }
    }

    public function add( $key, $data, $group = 'default', $expire = 0 ) {
        if ( false !== $this->get( $key, $group ) ) {
            return false;
        }
        return $this->set( $key, $data, $group, $expire );
    }

    public function replace( $key, $data, $group = 'default', $expire = 0 ) {
        if ( false === $this->get( $key, $group ) ) {
            return false;
        }
        return $this->set( $key, $data, $group, $expire );
    }

    public function delete( $key, $group = 'default' ) {
        $ck = $this->build_key( $key, $group );
        unset( $this->local_cache[ $ck ] );

        if ( $this->is_ignored( $group ) || ! $this->redis_connected ) {
            return true;
        }
        try {
            $this->redis->del( $ck );
            return true;
        } catch ( Throwable $e ) {
            $this->redis_connected = false;
            return false;
        }
    }

    public function flush() {
        $this->local_cache = [];
        if ( ! $this->redis_connected ) {
            return true;
        }
        try {
            $this->redis instanceof Redis ? $this->redis->flushDB() : $this->redis->flushdb();
            return true;
        } catch ( Throwable $e ) {
            $this->redis_connected = false;
            return false;
        }
    }

    public function incr( $key, $offset = 1, $group = 'default' ) {
        $ck = $this->build_key( $key, $group );
        if ( $this->is_ignored( $group ) || ! $this->redis_connected ) {
            $val = (int) ( $this->local_cache[ $ck ] ?? 0 ) + $offset;
            $this->local_cache[ $ck ] = $val;
            return $val;
        }
        try {
            return $this->redis->incrBy( $ck, $offset );
        } catch ( Throwable $e ) {
            $this->redis_connected = false;
            return false;
        }
    }

    public function decr( $key, $offset = 1, $group = 'default' ) {
        return $this->incr( $key, -absint( $offset ), $group );
    }

    public function add_global_groups( $groups ) {
        $this->global_groups = array_unique(
            array_merge( $this->global_groups, (array) $groups )
        );
    }

    public function add_non_persistent_groups( $groups ) {
        $this->ignored_groups = array_unique(
            array_merge( $this->ignored_groups, (array) $groups )
        );
    }

    public function switch_to_blog( $blog_id ) {
        $this->blog_prefix = is_multisite() ? absint( $blog_id ) : 1;
    }

    public function is_redis_connected() {
        return $this->redis_connected;
    }

    public function stats() {
        $mode = $this->redis_connected ? 'Redis' : 'In-memory fallback (Redis unavailable)';
        echo '<p><strong>NitRedis Object Cache</strong> [' . esc_html( NITREDIS_DROP_IN_VERSION ) . '] — ' . esc_html( $mode ) . '</p>';
        echo '<p>Hits: ' . (int) $this->cache_hits . ' &nbsp;|&nbsp; Misses: ' . (int) $this->cache_misses . '</p>';
    }
}

// ── Required WordPress object cache functions ──────────────────────────────────
// WordPress calls these directly at bootstrap. They must ALWAYS exist.

function wp_cache_init() {
    $GLOBALS['wp_object_cache'] = new WP_Object_Cache();
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
    return $GLOBALS['wp_object_cache']->add( $key, $data, $group, (int) $expire );
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
    return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, (int) $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
    return $GLOBALS['wp_object_cache']->set( $key, $data, $group, (int) $expire );
}

function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
    return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found );
}

function wp_cache_delete( $key, $group = '' ) {
    return $GLOBALS['wp_object_cache']->delete( $key, $group );
}

function wp_cache_flush() {
    return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_incr( $key, $offset = 1, $group = '' ) {
    return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group );
}

function wp_cache_decr( $key, $offset = 1, $group = '' ) {
    return $GLOBALS['wp_object_cache']->decr( $key, $offset, $group );
}

function wp_cache_add_global_groups( $groups ) {
    $GLOBALS['wp_object_cache']->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
    $GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups );
}

function wp_cache_switch_to_blog( $blog_id ) {
    $GLOBALS['wp_object_cache']->switch_to_blog( $blog_id );
}

function wp_cache_close() {
    return true;
}

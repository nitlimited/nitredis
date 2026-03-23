=== NitRedis — Redis Object Cache ===
Contributors: nusiteltd
Tags: redis, cache, object-cache, performance, speed
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance Redis object caching for WordPress, developed by Nusite I.T Consulting Limited.

== Description ==

NitRedis integrates Redis into WordPress as a persistent object cache, dramatically reducing
database queries and improving page load times. Configure via a clean admin UI or via
wp-config.php constants.

**Features:**
* PhpRedis (ext-redis) with Predis fallback
* Redis 6+ ACL (username + password) support
* TLS/SSL connections
* Unix socket support
* Multisite compatible — global & per-site groups
* Configurable key prefix, TTL caps & ignored groups
* Live dashboard with hit/miss ratio donut chart
* One-click drop-in install / removal
* Comprehensive system diagnostics

== Installation ==

1. Upload the `nitredis` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **NitRedis → Settings** and enter your Redis connection details.
4. Go to **NitRedis → Dashboard** and click **Enable Drop-in** to activate object caching.
5. Add `define( 'WP_CACHE', true );` to your `wp-config.php` if not already present.

== Configuration via wp-config.php ==

    define( 'NITREDIS_HOST',         '127.0.0.1' );
    define( 'NITREDIS_PORT',         6379 );
    define( 'NITREDIS_DATABASE',     0 );
    define( 'NITREDIS_PASSWORD',     'your-password' );
    define( 'NITREDIS_USERNAME',     'your-acl-user' ); // Redis 6+ ACL
    define( 'NITREDIS_SCHEME',       'tcp' );           // tcp | unix | tls
    define( 'NITREDIS_PATH',         '/tmp/redis.sock' ); // unix only
    define( 'NITREDIS_TIMEOUT',      1.0 );
    define( 'NITREDIS_READ_TIMEOUT', 1.0 );
    define( 'NITREDIS_PREFIX',       'nitredis_' );
    define( 'WP_CACHE',              true );

== Changelog ==

= 1.0.0 =
* Initial release by Nusite I.T Consulting Limited.

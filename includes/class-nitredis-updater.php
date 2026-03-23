<?php
/**
 * NitRedis — GitHub Update Checker
 *
 * Hooks into the WordPress plugin update system to check for new releases
 * on GitHub. When a newer tag is published, WordPress will show the standard
 * "Update available" notice in the Plugins screen and allow one-click updates.
 *
 * Setup:
 *   Set NITREDIS_GITHUB_REPO to your GitHub repo slug, e.g. 'nitlimited/nitredis'.
 *   If the repo is private, also set NITREDIS_GITHUB_TOKEN to a personal access
 *   token with 'repo' scope.
 *
 * How it works:
 *   1. Taps into `pre_set_site_transient_update_plugins` to inject update info.
 *   2. Taps into `plugins_api` to show changelog / release notes in the modal.
 *   3. After a successful update, taps into `upgrader_process_complete` to clear
 *      the cached release data.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Updater {

    /** GitHub repository slug — overridden by NITREDIS_GITHUB_REPO constant. */
    const DEFAULT_REPO = 'nitlimited/nitredis';

    /** Transient key for caching the latest release data. */
    const CACHE_KEY = 'nitredis_github_release';

    /** Cache lifetime in seconds (12 hours). */
    const CACHE_TTL = 43200;

    /** @var string  owner/repo */
    private $repo;

    /** @var string  Optional GitHub personal access token for private repos. */
    private $token;

    /** @var string  WordPress plugin basename, e.g. nitredis/nitredis.php */
    private $plugin_basename;

    public function __construct() {
        // Priority: wp-config.php constant > Settings page value > built-in default.
        if ( defined( 'NITREDIS_GITHUB_REPO' ) ) {
            $this->repo = NITREDIS_GITHUB_REPO;
        } else {
            $settings   = NitRedis_Settings::get();
            $this->repo = ! empty( $settings['github_repo'] )
                ? $settings['github_repo']
                : self::DEFAULT_REPO;
        }

        if ( defined( 'NITREDIS_GITHUB_TOKEN' ) ) {
            $this->token = NITREDIS_GITHUB_TOKEN;
        } else {
            $settings    = $settings ?? NitRedis_Settings::get();
            $this->token = $settings['github_token'] ?? '';
        }

        $this->plugin_basename = plugin_basename( NITREDIS_FILE );
    }

    /**
     * Register all WordPress hooks.
     */
    public function init() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
        add_action( 'upgrader_process_complete',             [ $this, 'purge_cache' ], 10, 2 );
        // Repo is configured via Settings UI — no config notice needed.
    }

    // ── Update check ─────────────────────────────────────────────────────────

    /**
     * Injected into WordPress' plugin update transient.
     * If GitHub has a newer release, we add our plugin to the update list.
     *
     * @param  object $transient
     * @return object
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version = $this->parse_version( $release['tag_name'] ?? '' );
        if ( ! $latest_version ) {
            return $transient;
        }

        if ( version_compare( $latest_version, NITREDIS_VERSION, '>' ) ) {
            $transient->response[ $this->plugin_basename ] = $this->build_update_object( $release, $latest_version );
        } else {
            // Tell WP the plugin is up to date (avoids stale "update available" notices).
            $transient->no_update[ $this->plugin_basename ] = $this->build_update_object( $release, $latest_version );
        }

        return $transient;
    }

    /**
     * Supply plugin information for the "View version x.x.x details" modal.
     *
     * @param  false|object $result
     * @param  string       $action
     * @param  object       $args
     * @return false|object
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ( $args->slug ?? '' ) !== $this->get_slug() ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $latest_version = $this->parse_version( $release['tag_name'] ?? '' );

        $info                = new stdClass();
        $info->name          = 'NitRedis';
        $info->slug          = $this->get_slug();
        $info->version       = $latest_version;
        $info->author        = '<a href="https://nusite.co.uk">Nusite I.T Consulting Limited</a>';
        $info->homepage      = 'https://nusite.co.uk/nitredis';
        $info->requires      = '5.6';
        $info->requires_php  = '7.4';
        $info->download_link = self::get_download_url( $release );
        $info->last_updated  = isset( $release['published_at'] )
            ? date( 'Y-m-d', strtotime( $release['published_at'] ) )
            : '';
        $info->sections      = [
            'description' => 'High-performance Redis object caching for WordPress, by Nusite I.T Consulting Limited.',
            'changelog'   => $this->format_changelog( $release['body'] ?? '' ),
        ];

        return $info;
    }

    /**
     * Clear our cached release data after any plugin update completes,
     * so the next check fetches fresh data from GitHub.
     *
     * @param \WP_Upgrader $upgrader
     * @param array        $hook_extra
     */
    public function purge_cache( $upgrader, $hook_extra ) {
        if (
            isset( $hook_extra['action'], $hook_extra['type'] ) &&
            $hook_extra['action'] === 'update' &&
            $hook_extra['type']   === 'plugin'
        ) {
            delete_transient( self::CACHE_KEY );
        }
    }

    // Repo notice removed — repo is now configured via Settings UI.

    // ── GitHub API ────────────────────────────────────────────────────────────

    /**
     * Fetch the latest release from the GitHub API.
     * Result is cached in a transient to avoid hammering the API.
     *
     * @return array|false  Decoded release JSON or false on failure.
     */
    private function get_latest_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( $cached !== false ) {
            return $cached;
        }

        $url  = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $args = [
            'timeout'    => 10,
            'user-agent' => 'NitRedis/' . NITREDIS_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
            'headers'    => [ 'Accept' => 'application/vnd.github+json' ],
        ];

        if ( $this->token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'NitRedis updater: GitHub API error — ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( "NitRedis updater: GitHub API returned HTTP {$code} for {$url}" );
            return false;
        }

        $body    = wp_remote_retrieve_body( $response );
        $release = json_decode( $body, true );

        if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
            return false;
        }

        set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
        return $release;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build the stdClass object that WordPress expects in update_plugins transient.
     *
     * @param  array  $release
     * @param  string $version
     * @return stdClass
     */
    private function build_update_object( array $release, $version ) {
        $obj                 = new stdClass();
        $obj->id             = $this->plugin_basename;
        $obj->slug           = $this->get_slug();
        $obj->plugin         = $this->plugin_basename;
        $obj->new_version    = $version;
        $obj->url            = "https://github.com/{$this->repo}";
        $obj->package        = self::get_download_url( $release );
        $obj->icons          = [];
        $obj->banners        = [];
        $obj->banners_rtl    = [];
        $obj->requires       = '5.6';
        $obj->requires_php   = '7.4';
        $obj->tested         = '6.5';
        $obj->compatibility  = new stdClass();
        return $obj;
    }

    /**
     * Get the best available download URL for a release.
     *
     * Prefers a release asset named nitredis-*.zip (built by build.sh) over
     * GitHub's auto-generated zipball, which uses an unpredictable folder name
     * that breaks WordPress' plugin installer.
     *
     * @param  array $release
     * @return string
     */
    private static function get_download_url( array $release ) {
        // Look for an attached .zip asset first.
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                $name = $asset['name'] ?? '';
                if ( substr( $name, -4 ) === '.zip' && strpos( $name, 'nitredis' ) !== false ) {
                    return $asset['browser_download_url'] ?? '';
                }
            }
        }
        // Fall back to zipball — caller should be aware of the folder-name caveat.
        return $release['zipball_url'] ?? '';
    }

    /**
     * Strip leading 'v' from a git tag to get a semver string.
     *
     * @param  string $tag  e.g. "v1.2.0" or "1.2.0"
     * @return string|false
     */
    private function parse_version( $tag ) {
        $version = ltrim( $tag, 'vV' );
        return preg_match( '/^\d+\.\d+/', $version ) ? $version : false;
    }

    /**
     * Get just the folder slug part of the plugin basename.
     *
     * @return string  e.g. "nitredis"
     */
    private function get_slug() {
        return dirname( $this->plugin_basename );
    }

    /**
     * Convert GitHub release markdown body to basic HTML for the WP modal.
     *
     * @param  string $markdown
     * @return string
     */
    private function format_changelog( $markdown ) {
        if ( empty( $markdown ) ) {
            return '<p>See <a href="https://github.com/' . esc_attr( $this->repo ) . '/releases">GitHub releases</a> for changelog.</p>';
        }

        // Basic markdown → HTML conversion (headings, bold, lists, links).
        $html = esc_html( $markdown );
        $html = preg_replace( '/^### (.+)$/m',  '<h4>$1</h4>', $html );
        $html = preg_replace( '/^## (.+)$/m',   '<h3>$1</h3>', $html );
        $html = preg_replace( '/^# (.+)$/m',    '<h2>$1</h2>', $html );
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/`(.+?)`/',        '<code>$1</code>', $html );
        $html = preg_replace( '/^\* (.+)$/m',    '<li>$1</li>', $html );
        $html = preg_replace( '/^- (.+)$/m',     '<li>$1</li>', $html );
        $html = str_replace( "\n\n", '</p><p>', $html );
        return '<p>' . $html . '</p>';
    }
}

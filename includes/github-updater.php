<?php
/**
 * GitHub Auto-Updater for GPT Auto Poster Plugin
 * 
 * Provides automatic update checks against GitHub releases
 * without any external libraries - uses WordPress native API.
 * 
 * @package GPT_Auto_Poster
 * @subpackage GitHub_Updater
 * @since 1.1.0
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * GitHub Updater Class
 * 
 * Handles checking for updates from GitHub releases and integrating
 * with WordPress' native plugin update system.
 * 
 * @since 1.1.0
 */
class GPT_Auto_Poster_GitHub_Updater {

    /**
     * GitHub repository owner
     * 
     * @var string
     */
    private $github_owner = 'jpmishra096';

    /**
     * GitHub repository name
     * 
     * @var string
     */
    private $github_repo = 'WP-GPT-Auto-Poster';

    /**
     * Plugin slug (used for transients and cache keys)
     * 
     * @var string
     */
    private $plugin_slug = 'gpt-auto-poster';

    /**
     * Plugin file path relative to plugins directory
     * 
     * @var string
     */
    private $plugin_file = 'gpt-auto-poster/gpt-auto-poster.php';

    /**
     * Transient key for caching GitHub releases
     * 
     * @var string
     */
    private $transient_key = 'gpt_auto_poster_github_updates';

    /**
     * Cache duration in seconds (12 hours)
     * 
     * @var int
     */
    private $cache_duration = 43200;

    /**
     * Initialize the GitHub updater
     * 
     * Hooks into WordPress' plugin update system to check GitHub releases.
     * This constructor is called once during plugin initialization.
     * 
     * @since 1.1.0
     */
    public function __construct() {
        // Hook into WordPress plugin update transient
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));

        // Hook into WordPress plugin info API
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
    }

    /**
     * Check GitHub for newer versions
     * 
     * This method is called via WordPress hook before the update transient
     * is set. It fetches the latest GitHub release and compares it with
     * the currently installed plugin version.
     * 
     * @param object $transient The WordPress transient object
     * @return object Modified transient object with update info if available
     * 
     * @since 1.1.0
     */
    public function check_for_updates($transient) {
        // If no update object exists yet, create one
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get the latest release info from GitHub
        $remote_version = $this->get_latest_github_release();

        // Bail silently if we couldn't get GitHub release info
        if (empty($remote_version)) {
            return $transient;
        }

        // Get the currently installed plugin version
        $installed_version = isset($transient->checked[$this->plugin_file]) 
            ? $transient->checked[$this->plugin_file] 
            : '0';

        // Compare versions - compare_versions() returns: -1 (first < second), 0 (equal), 1 (first > second)
        // We want to show an update if remote version is newer than installed
        if (version_compare($installed_version, $remote_version['version'], '<')) {
            // New version available - add update notice
            $transient->response[$this->plugin_file] = (object) array(
                'id'            => 'github.com/' . $this->github_owner . '/' . $this->github_repo,
                'slug'          => $this->plugin_slug,
                'plugin'        => $this->plugin_file,
                'new_version'   => $remote_version['version'],
                'url'           => $remote_version['url'],
                'package'       => $remote_version['download_url'],
                'requires'      => '5.8',
                'requires_php'  => '7.4',
                'tested'        => '6.4',
                'author'        => 'GPT Auto Poster Team',
                'icons'         => array(
                    '1x' => 'https://ps.w.org/gpt-auto-poster/assets/icon-128x128.png',
                ),
                'banners'       => array(
                    'low'  => 'https://ps.w.org/gpt-auto-poster/assets/banner-772x250.png',
                    'high' => 'https://ps.w.org/gpt-auto-poster/assets/banner-1544x500.png',
                ),
                'compatibility' => new stdClass(),
                'tested'        => '6.4',
            );
        }

        return $transient;
    }

    /**
     * Provide plugin info when WordPress requests it
     * 
     * This method is called when WordPress needs detailed plugin information
     * (e.g., when showing the "View Details" modal in the plugin updater).
     * We fetch this from our cached GitHub release data.
     * 
     * @param false|object|array $response The default response
     * @param string $action The API action being performed
     * @param object $args The API call arguments
     * @return object|false Modified response with plugin info or default
     * 
     * @since 1.1.0
     */
    public function plugin_info($response, $action, $args) {
        // Only intercept 'plugin_information' API calls for our plugin
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $response;
        }

        // Get the latest GitHub release info
        $remote_version = $this->get_latest_github_release();

        // Return default response if no GitHub data available
        if (empty($remote_version)) {
            return $response;
        }

        // Build plugin info object for WordPress plugin modal
        $plugin_info = (object) array(
            'name'              => 'GPT Auto Poster',
            'slug'              => $this->plugin_slug,
            'version'           => $remote_version['version'],
            'author'            => 'GPT Auto Poster Team',
            'author_profile'    => 'https://github.com/' . $this->github_owner,
            'requires'          => '5.8',
            'requires_php'      => '7.4',
            'tested'            => '6.4',
            'requires_plugins'  => array(),
            'homepage'          => 'https://github.com/' . $this->github_owner . '/' . $this->github_repo,
            'short_description' => 'Automatically generate SEO-optimized blog posts using AI',
            'description'       => 'Automatically generate SEO-optimized blog posts using AI with internal linking, featured images, and content refresh capabilities.',
            'download_link'     => $remote_version['download_url'],
            'trunk_last_updated' => $remote_version['published_at'],
            'is_compatible'     => true,
            'banners'           => array(
                'low'  => 'https://ps.w.org/gpt-auto-poster/assets/banner-772x250.png',
                'high' => 'https://ps.w.org/gpt-auto-poster/assets/banner-1544x500.png',
            ),
            'icons'             => array(
                '1x' => 'https://ps.w.org/gpt-auto-poster/assets/icon-128x128.png',
            ),
        );

        return $plugin_info;
    }

    /**
     * Fetch the latest GitHub release information
     * 
     * Retrieves the latest release from GitHub API with caching via transients.
     * Fails silently if GitHub is unreachable. Transient is cached for 12 hours
     * to avoid excessive API calls.
     * 
     * @return array|false Array with 'version', 'download_url', 'url', 'published_at' keys, or false on failure
     * 
     * @since 1.1.0
     */
    private function get_latest_github_release() {
        // Check if we have cached release data
        $cached_release = get_transient($this->transient_key);

        if ($cached_release !== false) {
            return $cached_release;
        }

        // Build GitHub API URL for latest release
        $github_api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_owner,
            $this->github_repo
        );

        // Make the API call with proper headers
        // GitHub recommends including User-Agent header in all requests
        $response = wp_remote_get($github_api_url, array(
            'sslverify' => true,
            'headers'   => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_site_url(),
            ),
            'timeout'   => 10, // 10 second timeout to avoid blocking the site
        ));

        // Return false silently if the request failed
        // This could happen if GitHub is down, no internet, rate limited, etc.
        if (is_wp_error($response)) {
            return false;
        }

        // Decode the JSON response
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $release_data = json_decode($body, true);

        // Return false if API returned an error (e.g., 404 if no releases found)
        if ($http_code !== 200 || empty($release_data)) {
            return false;
        }

        // Extract version from tag name (remove 'v' prefix if present)
        $version = isset($release_data['tag_name']) ? ltrim($release_data['tag_name'], 'v') : false;

        // Return false if no version tag found
        if (empty($version)) {
            return false;
        }

        // Find the ZIP download URL in the release assets
        // GitHub automatically creates a ZIP archive for each release
        $download_url = sprintf(
            'https://github.com/%s/%s/releases/download/%s/gpt-auto-poster.zip',
            $this->github_owner,
            $this->github_repo,
            $release_data['tag_name']
        );

        // Build the release info array
        $release_info = array(
            'version'      => $version,
            'download_url' => $download_url,
            'url'          => $release_data['html_url'] ?? '',
            'published_at' => $release_data['published_at'] ?? current_time('mysql'),
        );

        // Cache the release info for 12 hours to avoid excessive API calls
        set_transient($this->transient_key, $release_info, $this->cache_duration);

        return $release_info;
    }

    /**
     * Clear the update cache
     * 
     * Removes the cached release information. Useful for testing or
     * when you need to force a fresh check.
     * 
     * @since 1.1.0
     */
    public static function clear_cache() {
        delete_transient('gpt_auto_poster_github_updates');
    }
}

// Initialize the GitHub updater when the plugin loads
new GPT_Auto_Poster_GitHub_Updater();

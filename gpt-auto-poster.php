<?php
/**
 * Plugin Name: GPT Auto Article Poster
 * Description: Automatically generate SEO-optimized blog posts using AI with internal linking and content refresh capabilities.
 * Version: 1.0.1
 * Author: GPT Auto Poster Team
 * License: GPL-2.0+
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Text Domain: gpt-auto-poster
 * Domain Path: /languages
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Security: Verify plugin dependencies and requirements
if (version_compare(PHP_VERSION, '7.4', '<')) {
    wp_die('GPT Auto Poster requires PHP 7.4 or higher.');
}

if (!function_exists('wp_insert_post')) {
    wp_die('GPT Auto Poster requires WordPress 5.8 or higher.');
}

// Load all required modules using require_once to prevent redeclaration
require_once plugin_dir_path(__FILE__) . 'includes/formatter.php';
require_once plugin_dir_path(__FILE__) . 'includes/gpt.php';
require_once plugin_dir_path(__FILE__) . 'includes/internal-links.php';
require_once plugin_dir_path(__FILE__) . 'includes/authors.php';

// Load GitHub updater for private auto-update system
require_once plugin_dir_path(__FILE__) . 'includes/github-updater.php';

// Load admin pages only in admin context
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';
    require_once plugin_dir_path(__FILE__) . 'admin/post-generator.php';
    require_once plugin_dir_path(__FILE__) . 'admin/update-post.php';
}

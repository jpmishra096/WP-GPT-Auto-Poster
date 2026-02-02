<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'GPT Auto Poster',
        'GPT Auto Poster',
        'manage_options', // Capability check: only admins
        'gpt-auto-poster',
        'gpt_auto_poster_settings_page',
        'dashicons-edit',
        26
    );
});

// AJAX handler for creating new categories
add_action('wp_ajax_gpt_create_category', function() {
    // Security: Verify nonce and user capability
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gpt_create_category_nonce')) {
        wp_send_json_error(['message' => 'Security verification failed. Please refresh and try again.'], 403);
    }

    // Capability check: only admins can create categories
    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'You do not have permission to create categories.'], 403);
    }

    // Input sanitization
    $name = sanitize_text_field($_POST['name'] ?? '');
    $slug = sanitize_title($_POST['slug'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');

    if (empty($name)) {
        wp_send_json_error(['message' => 'Category name is required']);
    }

    // Create term safely
    $result = wp_insert_term($name, 'category', [
        'slug' => $slug,
        'description' => $description
    ]);

    // Error handling
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'cat_id' => $result['term_id'],
        'cat_name' => $name
    ]);
});


function gpt_auto_poster_settings_page() {
    // Capability check: only admins can access settings
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }

    // Handle form submission with nonce verification
    if (isset($_POST['gpt_save_keys'])) {
        check_admin_referer('gpt_save_keys_nonce');
        
        // Sanitize and validate API keys
        $openrouter_key = sanitize_text_field($_POST['gpt_openrouter_api_key'] ?? '');
        $unsplash_key = sanitize_text_field($_POST['gpt_unsplash_api_key'] ?? '');
        
        // Store in WordPress options (database) - never as constants or files
        update_option('gpt_openrouter_api_key', $openrouter_key);
        update_option('gpt_unsplash_api_key', $unsplash_key);
        
        echo '<div class="updated"><p>API Keys saved securely.</p></div>';
    }

    // Retrieve stored API keys
    $openrouter_key = get_option('gpt_openrouter_api_key', '');
    $unsplash_key = get_option('gpt_unsplash_api_key', '');
    ?>
    <div class="wrap">
        <h1>GPT Auto Poster – Settings</h1>

        <form method="post">
            <?php wp_nonce_field('gpt_save_keys_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th>
                        <label for="gpt_openrouter_api_key">OpenRouter.ai API Key</label>
                    </th>
                    <td>
                        <input type="password"
                               id="gpt_openrouter_api_key"
                               name="gpt_openrouter_api_key"
                               value="<?php echo esc_attr($openrouter_key); ?>"
                               style="width:400px;">
                        <p class="description">
                            <a href="https://openrouter.ai/settings/keys" target="_blank" rel="noopener noreferrer">How to get your OpenRouter.ai API Key →</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th>
                        <label for="gpt_unsplash_api_key">Unsplash API Key</label>
                    </th>
                    <td>
                        <input type="password"
                               id="gpt_unsplash_api_key"
                               name="gpt_unsplash_api_key"
                               value="<?php echo esc_attr($unsplash_key); ?>"
                               style="width:400px;">
                        <p class="description">
                            <a href="https://unsplash.com/developers" target="_blank" rel="noopener noreferrer">How to get your Unsplash API Key →</a>
                        </p>
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="gpt_save_keys">
                    Save API Keys
                </button>
            </p>
        </form>
    </div>
    <?php
}

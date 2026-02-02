<?php
if (!defined('ABSPATH')) exit;

// Register the "Update Post" submenu with capability check
add_action('admin_menu', function () {
    add_submenu_page(
        'gpt-auto-poster',
        'Update Existing Post',
        'Update Existing Post',
        'manage_options', // Capability check: only admins
        'gpt-update-post',
        'gpt_auto_poster_update_page'
    );
});

function gpt_auto_poster_update_page() {
    // Capability check: only admins can update posts
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }
    // Handle preview and update submission
    if (isset($_POST['gpt_preview_update'])) {
        check_admin_referer('gpt_update_nonce');
        handle_update_preview();
        return;
    }

    if (isset($_POST['gpt_commit_update'])) {
        check_admin_referer('gpt_update_commit_nonce');
        handle_update_commit();
        return;
    }

    // Display update form
    ?>
    <div class="wrap">
        <h1>Update Existing Post</h1>

        <form method="post">
            <?php wp_nonce_field('gpt_update_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="post_to_update">Select Post to Update *</label></th>
                    <td>
                        <select id="post_to_update" name="post_to_update" required style="width:400px;">
                            <option value="">-- Select a Post --</option>
                            <?php
                            // Get all published posts
                            $posts = get_posts([
                                'numberposts' => -1,
                                'post_type'   => 'post',
                                'post_status' => 'publish',
                                'orderby'     => 'date',
                                'order'       => 'DESC'
                            ]);
                            
                            foreach ($posts as $post) {
                                echo '<option value="' . esc_attr($post->ID) . '">';
                                echo esc_html($post->post_title) . ' (ID: ' . $post->ID . ')';
                                echo '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Choose the post you want to update</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="refresh_type">Refresh Type *</label></th>
                    <td>
                        <select id="refresh_type" name="refresh_type" required style="width:400px;">
                            <option value="light">Light Refresh - Update stats & examples only</option>
                            <option value="medium" selected>Medium Refresh - Add new sections on trends</option>
                            <option value="heavy">Heavy Refresh - Rewrite outdated sections</option>
                        </select>
                        <p class="description">
                            <strong>Light:</strong> Quick stats/example updates<br>
                            <strong>Medium:</strong> Add new trends + update info<br>
                            <strong>Heavy:</strong> Major rewrite with modern perspective
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="update_author">Author</label></th>
                    <td>
                        <?php gpt_auto_poster_render_author_dropdown(); ?>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" name="gpt_preview_update" class="button button-primary">
                    Preview Update
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Handle the preview update step
 * Security: Verify nonce and capability
 */
function handle_update_preview() {
    // Capability check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }

    // Sanitize and validate inputs
    $post_id = intval($_POST['post_to_update'] ?? 0);
    $refresh_type = sanitize_key($_POST['refresh_type'] ?? 'medium');
    $update_author = !empty($_POST['update_author']) ? intval($_POST['update_author']) : null;

    // Validate refresh type
    if (!in_array($refresh_type, ['light', 'medium', 'heavy'])) {
        $refresh_type = 'medium';
    }

    if (!$post_id) {
        echo '<div class="error"><p>Please select a post to update.</p></div>';
        return;
    }

    // Verify post exists and is a post
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'post') {
        echo '<div class="error"><p>Invalid post selected.</p></div>';
        return;
    }

    // Generate refresh prompt
    $refresh_prompt = gpt_auto_poster_generate_refresh_prompt($post->post_content, $refresh_type);

    // Get AI to refresh content
    $result = gpt_auto_poster_generate_content_raw($refresh_prompt, 'openrouter');

    if (isset($result['error'])) {
        echo '<div class="error"><p>' . esc_html($result['error']) . '</p></div>';
        return;
    }

    $updated_content = $result['content'];
    $snippet = $result['snippet'];

    // Safety checks
    $updated_content = wp_kses_post($updated_content);

    ?>
    <div class="wrap">
        <h1>Review Updated Content</h1>

        <div style="background:#fff3cd; padding:15px; border-radius:5px; margin:15px 0; border-left:4px solid #ffc107;">
            <strong>⚠️ Please review the changes before saving.</strong> You can edit the preview below.
        </div>

        <form method="post">
            <?php wp_nonce_field('gpt_update_commit_nonce'); ?>

            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
            <input type="hidden" name="update_author" value="<?php echo esc_attr($update_author); ?>">

            <div style="margin:20px 0;">
                <h3>Original Title</h3>
                <p style="color:#666;"><?php echo esc_html($post->post_title); ?></p>
            </div>

            <div style="margin:20px 0;">
                <h3>Updated Content (Editable)</h3>
                <p style="color:#666; font-size:12px;">You can make manual edits below before saving:</p>
                <textarea name="updated_content" style="width:100%; height:400px; font-family:monospace;">
<?php echo esc_textarea($updated_content); ?>
                </textarea>
            </div>

            <div style="margin:20px 0;">
                <h3>New Meta Description</h3>
                <textarea name="updated_snippet" style="width:100%; height:80px; font-family:monospace;">
<?php echo esc_textarea($snippet); ?>
                </textarea>
            </div>

            <p>
                <button type="submit" name="gpt_commit_update" class="button button-primary">
                    ✅ Save Updated Post
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=gpt-update-post')); ?>" class="button">
                    Cancel
                </a>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Handle the final commit/save of updated post
 * Security: Verify nonce, capability, and sanitize all inputs
 */
function handle_update_commit() {
    // Capability check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }

    // Sanitize and validate inputs
    $post_id = intval($_POST['post_id'] ?? 0);
    $updated_content = wp_kses_post($_POST['updated_content'] ?? '');
    $updated_snippet = sanitize_textarea_field($_POST['updated_snippet'] ?? '');
    $update_author = !empty($_POST['update_author']) ? intval($_POST['update_author']) : null;

    if (!$post_id) {
        echo '<div class="error"><p>Post ID missing.</p></div>';
        return;
    }

    // Verify post exists
    $post = get_post($post_id);
    if (!$post) {
        echo '<div class="error"><p>Post not found.</p></div>';
        return;
    }

    // Update post content
    $author_id = gpt_auto_poster_get_post_author($update_author);
    
    wp_update_post([
        'ID'           => $post_id,
        'post_content' => $updated_content,
        'post_author'  => $author_id
    ]);

    // Update meta description if provided
    if (!empty($updated_snippet)) {
        update_post_meta($post_id, 'gpt_meta_description', $updated_snippet);
    }

    // Add flag that this post was AI-updated
    update_post_meta($post_id, 'gpt_last_updated', current_time('mysql'));

    ?>
    <div class="wrap">
        <h1>Post Updated Successfully</h1>
        
        <div class="updated">
            <p>
                <strong>✅ Post updated successfully!</strong><br>
                The post has been refreshed and saved.
            </p>
        </div>

        <p>
            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" target="_blank" class="button">
                ✏️ Edit Post in Full Editor
            </a>
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" class="button">
                🔗 View Published Post
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=gpt-update-post')); ?>" class="button">
                ↩️ Update Another Post
            </a>
        </p>
    </div>
    <?php
}

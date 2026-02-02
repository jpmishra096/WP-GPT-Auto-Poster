<?php
if (!defined('ABSPATH')) exit;

/**
 * Fetch all users with author, editor, or admin roles
 * Returns array of user IDs and display names, sorted alphabetically
 * Security: Only returns users with edit_posts capability
 */
function gpt_auto_poster_get_eligible_authors() {
    $eligible_authors = [];
    
    // Get users with author, editor, or admin roles - must have edit_posts capability
    $authors = get_users([
        'role__in' => ['author', 'editor', 'administrator'],
        'meta_key' => '',
        'number'   => 999
    ]);

    foreach ($authors as $author) {
        // Double-check user has edit_posts capability
        if (user_can($author->ID, 'edit_posts')) {
            $eligible_authors[] = [
                'ID'           => $author->ID,
                'display_name' => $author->display_name
            ];
        }
    }

    // Sort by display name for consistent ordering
    usort($eligible_authors, function($a, $b) {
        return strcmp($a['display_name'], $b['display_name']);
    });

    return $eligible_authors;
}

/**
 * Render author selection dropdown
 * Security: Validates selected_id parameter, displays current user as default
 */
function gpt_auto_poster_render_author_dropdown($selected_id = null) {
    // Validate selected_id if provided
    if (!empty($selected_id)) {
        $selected_id = intval($selected_id);
        // Verify author exists and has permission
        if (!user_can($selected_id, 'edit_posts')) {
            $selected_id = null;
        }
    }
    
    $authors = gpt_auto_poster_get_eligible_authors();
    $current_user = get_current_user_id();
    $current_user_name = esc_html(get_the_author_meta('display_name', $current_user));

    echo '<select id="post_author" name="post_author" style="width:400px;">';
    echo '<option value="">-- Current User (' . $current_user_name . ') --</option>';
    
    foreach ($authors as $author) {
        $selected = (!empty($selected_id) && $selected_id === $author['ID']) ? 'selected' : '';
        echo '<option value="' . esc_attr($author['ID']) . '" ' . $selected . '>' . esc_html($author['display_name']) . '</option>';
    }
    
    echo '</select>';
    echo '<p class="description">Select the author for this post. If not selected, current user will be used.</p>';
}

/**
 * Get final author ID (selected or default to current user)
 * Security: Validates user exists, has edit_posts capability, defaults to current user on failure
 */
function gpt_auto_poster_get_post_author($selected_author_id = null) {
    if (!empty($selected_author_id)) {
        $author_id = intval($selected_author_id);
        
        // Verify user exists and has edit_posts capability
        $user = get_user_by('ID', $author_id);
        if ($user && user_can($author_id, 'edit_posts')) {
            return $author_id;
        }
        
        // Log invalid selection attempt (for security monitoring)
        if ($user && !user_can($author_id, 'edit_posts')) {
            error_log('User ' . $author_id . ' selected as post author but lacks edit_posts capability');
        }
    }

    return get_current_user_id();
}

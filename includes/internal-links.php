<?php
if (!defined('ABSPATH')) exit;

/**
 * Fetch published posts for internal linking suggestions
 * Returns up to 20 posts with ID, title, and permalink
 */
function gpt_auto_poster_get_publishable_posts($exclude_post_id = null) {
    $args = [
        'numberposts' => 20,
        'post_type'   => 'post',
        'post_status' => 'publish',
        'orderby'     => 'date',
        'order'       => 'DESC'
    ];

    // Exclude current post if updating
    if ($exclude_post_id) {
        $args['post__not_in'] = [$exclude_post_id];
    }

    $posts = get_posts($args);
    $formatted_posts = [];

    foreach ($posts as $post) {
        $formatted_posts[] = [
            'id'    => $post->ID,
            'title' => $post->post_title,
            'url'   => get_permalink($post->ID)
        ];
    }

    return $formatted_posts;
}

/**
 * Generate AI prompt for internal link suggestions
 * Pass existing posts and new content to AI for link recommendations
 */
function gpt_auto_poster_generate_internal_links_prompt($content, $existing_posts = []) {
    if (empty($existing_posts)) {
        return "No existing posts available for internal linking.";
    }

    // Format posts list for AI
    $posts_list = "Available posts for internal linking:\n\n";
    foreach ($existing_posts as $post) {
        $posts_list .= "- {$post['title']} - {$post['url']}\n";
    }

    $prompt = "
    You are an internal linking optimization expert.
    
    TASK: Analyze the following blog content and suggest internal links.
    
    CONTENT TO ANALYZE:
    {$content}
    
    {$posts_list}
    
    INSTRUCTIONS:
    1. Find 3-5 natural places to add internal links in the content
    2. Match content keywords with post titles
    3. Suggest links that provide additional context or deeper reading
    4. Links should feel natural, not forced
    
    RETURN FORMAT:
    Return ONLY a JSON array (no markdown, no explanation):
    [
        {
            \"anchor_text\": \"the exact text to hyperlink\",
            \"target_url\": \"the full URL\",
            \"sentence\": \"the complete sentence where link fits\"
        }
    ]
    
    If no suitable links found, return empty array: []
    ";

    return $prompt;
}

/**
 * Parse AI response and extract internal link suggestions
 */
function gpt_auto_poster_parse_link_suggestions($ai_response) {
    // Extract JSON from response
    preg_match('/\[.*\]/s', $ai_response, $matches);
    
    if (empty($matches[0])) {
        return [];
    }

    $suggestions = json_decode($matches[0], true);
    
    if (!is_array($suggestions)) {
        return [];
    }

    return $suggestions;
}

/**
 * Insert internal links into HTML content
 * Safely replaces anchor text with hyperlinked version
 * Security: Validates URL format, sanitizes text, prevents regex injection
 */
function gpt_auto_poster_insert_internal_links($content, $links_to_insert) {
    // Input validation
    if (empty($links_to_insert) || !is_array($links_to_insert)) {
        return $content;
    }
    
    if (!is_string($content) || empty($content)) {
        return $content;
    }

    foreach ($links_to_insert as $link) {
        // Validate link structure
        if (!is_array($link)) {
            continue;
        }
        
        if (empty($link['anchor_text']) || empty($link['target_url'])) {
            continue;
        }

        // Sanitize inputs
        $anchor_text = sanitize_text_field($link['anchor_text']);
        $target_url = esc_url_raw($link['target_url']);
        
        // Validate URL (must be valid after escaping)
        if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
            error_log('Invalid URL in internal link suggestion: ' . var_export($link['target_url'], true));
            continue;
        }
        
        if (empty($anchor_text)) {
            continue;
        }
        
        // Escape special regex characters in anchor text
        $anchor_pattern = preg_quote($anchor_text, '/');
        
        // Only replace first occurrence to avoid duplicate linking
        $replacement = '<a href="' . $target_url . '">' . esc_html($anchor_text) . '</a>';
        $content = preg_replace('/' . $anchor_pattern . '(?!.*<\/a>)/u', $replacement, $content, 1);
    }

    return $content;
}

/**
 * Generate HTML form for link insertion checkboxes
 */
function gpt_auto_poster_render_link_suggestions($suggestions) {
    if (empty($suggestions)) {
        echo '<p style="color:#666; font-style:italic;">No internal link suggestions found.</p>';
        return;
    }

    echo '<div style="background:#f5f5f5; padding:15px; border-radius:5px; margin:15px 0;">';
    echo '<h3>📎 Internal Link Suggestions</h3>';
    echo '<p style="color:#666; margin-bottom:15px;">Review and select which links to insert:</p>';
    
    echo '<form id="internal_links_form" style="margin:15px 0;">';
    
    foreach ($suggestions as $index => $link) {
        $suggestion_id = 'link_' . $index;
        $anchor = isset($link['anchor_text']) ? esc_html($link['anchor_text']) : 'Unknown';
        $url = isset($link['target_url']) ? esc_url($link['target_url']) : '#';
        $sentence = isset($link['sentence']) ? esc_html(substr($link['sentence'], 0, 100)) . '...' : '';
        
        echo '<div style="background:white; padding:12px; margin:10px 0; border-left:4px solid #0073aa; border-radius:3px;">';
        echo '<label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">';
        echo '<input type="checkbox" name="selected_links" value="' . $index . '" style="margin-top:4px;">';
        echo '<div>';
        echo '<strong>Link text:</strong> "' . $anchor . '"<br>';
        echo '<strong>Target:</strong> <a href="' . $url . '" target="_blank" style="color:#0073aa;">' . $url . '</a><br>';
        if ($sentence) {
            echo '<strong>Context:</strong> ' . $sentence . '<br>';
        }
        echo '</div>';
        echo '</label>';
        echo '</div>';
    }
    
    echo '<input type="hidden" id="all_links" name="all_links" value="' . esc_attr(json_encode($suggestions)) . '">';
    echo '</form>';
    echo '</div>';
}

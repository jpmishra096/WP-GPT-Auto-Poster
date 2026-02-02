<?php
if (!defined('ABSPATH')) exit;

function gpt_auto_poster_generate_content($topic, $api = 'openrouter', $post_type = 'child') {
    // Security: Validate input
    if (empty($topic)) {
        return ['error' => 'Topic cannot be empty.'];
    }

    $topic = sanitize_text_field($topic);
    $api = sanitize_key($api);
    $post_type = sanitize_key($post_type);

    // Validate API selection
    if ($api === 'openrouter') {
        // Retrieve API key from WordPress options (secure storage)
        $api_key = get_option('gpt_openrouter_api_key');
        
        if (empty($api_key)) {
            return ['error' => 'OpenRouter.ai API key not configured. Please set it in plugin settings.'];
        }
    } else {
        return ['error' => 'Unknown API specified.'];
    }

    // Determine content length based on post type
    if ($post_type === 'pillar') {
        $word_count = '1500-3000 words';
        $max_tokens = 2400; // Increase token limit for longer content
    } else {
        $word_count = '800-2000 words';
        $max_tokens = 1800;
    }

    $prompt = "
    Write a comprehensive, SEO-optimized blog article on:
    \"{$topic}\"
    
    IMPORTANT: OUTPUT FORMAT
    - Return ONLY valid HTML
    - Start immediately with the first HTML tag (<p> or <h2>)
    - Do NOT include any explanations, preambles, or comments
    
    ALLOWED HTML TAGS ONLY:
    <h2>, <h3>, <p>, <ul>, <li>, <ol>, <strong>, <em>, <table>, <tr>, <th>, <td>
    
    STRICT FORMATTING RULES:
    - Do NOT use Markdown of any kind
    - Do NOT use #, ##, **, *, -, —, –, |, or ``` anywhere
    - Do NOT wrap output in ```html or code blocks
    - Use <strong> for emphasis
    - Use <ul><li> for bullet points
    - Use <ol><li> for steps or sequences
    - Tables must be proper HTML tables using <table>, <tr>, <th>, <td>

    CONTENT STRATEGY:
    - Target length: {$word_count}
    - Write in a conversational, human tone (as if explaining to a friend)
    - Simple English suitable for Indian readers
    - Include practical examples and real-world applications
    - Add specific facts or figures where relevant (avoid generic statements)

    SEO OPTIMIZATION:
    - Naturally include the main keyword \"{$topic}\" in:
    * First 100 words
    * 2–3 <h2> or <h3> headings
    * Body content (1–2% density)
    * Final paragraph
    - Use related and semantic keywords naturally
    - Write subheadings that match search intent
    - Use question-based headings where appropriate

    STRUCTURE:
    1. Opening section explaining what the topic is and why it matters
    2. 4–6 major sections using <h2>
    3. Subsections using <h3> where helpful
    4. Actionable tips, comparisons, or step-by-step guidance
    5. FAQ section with 3–4 questions (each question as <h3>)
    6. Short conclusion summarizing key takeaways

    WRITING STYLE:
    - Natural, confident, and helpful
    - Vary sentence length
    - Use transitions between sections
    - Include rhetorical questions where appropriate
    - Avoid clichés and filler phrases
    - Avoid promotional or sales language

    FINAL CHECK BEFORE OUTPUT:
    - Ensure no markdown symbols appear anywhere
    - Ensure all formatting is valid HTML
    ";


    $response = wp_remote_post(
        'https://openrouter.ai/api/v1/chat/completions',
        [
            // Security: Set reasonable timeout to prevent hangs
            'timeout' => 60,
            'sslverify' => true, // Verify SSL certificates in production
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                // Required by OpenRouter
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => 'GPT Auto Poster'
            ],
            'body' => wp_json_encode([
                'model' => 'mistralai/mistral-7b-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => $max_tokens
            ])
        ]
    );

    // Error handling: Check if request failed
    if (is_wp_error($response)) {
        // Log error for debugging (production-safe)
        error_log('GPT Auto Poster API Error: ' . $response->get_error_message());
        return ['error' => 'Failed to connect to API. Please try again later.'];
    }

    // Retrieve and decode response
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Check for API-level errors
    if (isset($body['error'])) {
        // Log error for debugging
        error_log('GPT Auto Poster API Response Error: ' . json_encode($body['error']));
        return ['error' => 'API returned an error. Please check your API key configuration.'];
    }

    // Validate response structure
    if (!isset($body['choices'][0]['message']['content'])) {
        error_log('GPT Auto Poster: Invalid API response structure');
        return ['error' => 'Invalid response from API. Please try again.'];
    }

    $content = $body['choices'][0]['message']['content'];
    
    if (strpos($content, 'ERROR') === 0) {
        return ['error' => $content];
    }

    // Generate snippet from content (first 150-160 characters, clean HTML)
    $clean_content = strip_tags($content);
    $clean_content = preg_replace('/\s+/', ' ', $clean_content); // Remove extra whitespace
    $snippet = substr($clean_content, 0, 160);
    
    // Trim to last complete word
    if (strlen($clean_content) > 160) {
        $snippet = substr($snippet, 0, strrpos($snippet, ' ')) . '...';
    }

    return [
        'content' => $content,
        'snippet' => $snippet
    ];
}

/**
 * Raw content generation for specific prompts (internal links, content refresh)
 * Returns array with 'content' and 'snippet' keys or error array
 */
function gpt_auto_poster_generate_content_raw($prompt, $api = 'openrouter') {
    // Security: Input validation
    if (empty($prompt)) {
        return ['error' => 'Prompt cannot be empty.'];
    }

    $api = sanitize_key($api);

    if ($api === 'openrouter') {
        // Retrieve API key from secure storage
        $api_key = get_option('gpt_openrouter_api_key');
        if (empty($api_key)) {
            return ['error' => 'OpenRouter.ai API key not configured.'];
        }
    } else {
        return ['error' => 'Unknown API specified.'];
    }

    // API request with security settings
    $response = wp_remote_post(
        'https://openrouter.ai/api/v1/chat/completions',
        [
            'timeout' => 60,
            'sslverify' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => get_site_url(),
                'X-Title'       => 'GPT Auto Poster'
            ],
            'body' => wp_json_encode([
                'model' => 'mistralai/mistral-7b-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500
            ])
        ]
    );

    // Error handling
    if (is_wp_error($response)) {
        error_log('GPT Auto Poster Raw API Error: ' . $response->get_error_message());
        return ['error' => 'Failed to connect to API.'];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['error'])) {
        error_log('GPT Auto Poster Raw Response Error: ' . json_encode($body['error']));
        return ['error' => 'API error occurred.'];
    }

    if (!isset($body['choices'][0]['message']['content'])) {
        error_log('GPT Auto Poster Raw: Invalid response structure');
        return ['error' => 'Invalid API response.'];
    }

    $content = $body['choices'][0]['message']['content'];
    
    if (strpos($content, 'ERROR') === 0) {
        return ['error' => $content];
    }

    // Generate snippet from content
    $clean_content = strip_tags($content);
    $clean_content = preg_replace('/\s+/', ' ', $clean_content);
    $snippet = substr($clean_content, 0, 160);
    
    if (strlen($clean_content) > 160) {
        $snippet = substr($snippet, 0, strrpos($snippet, ' ')) . '...';
    }

    return [
        'content' => $content,
        'snippet' => $snippet
    ];
}

/**
 * Generate AI prompt for content refresh/update
 */
function gpt_auto_poster_generate_refresh_prompt($existing_content, $refresh_type = 'medium') {
    if ($refresh_type === 'light') {
        $instructions = "
        - Update statistics and recent examples (last 2-3 years)
        - Refresh dates and references
        - Add new case studies if relevant
        - Keep structure unchanged
        - Minimum changes, maximum freshness";
    } elseif ($refresh_type === 'heavy') {
        $instructions = "
        - Rewrite entire sections with new perspective
        - Update all examples and statistics
        - Improve clarity and structure
        - Add recent trends and insights
        - Keep original intent but modernize content";
    } else {
        // Medium refresh (default)
        $instructions = "
        - Add 1-2 new sections on recent trends
        - Update examples and statistics
        - Refresh outdated information
        - Improve sections that feel dated
        - Keep overall structure recognizable";
    }

    $prompt = "
    You are a content refresh expert for blog articles.
    
    EXISTING CONTENT:
    {$existing_content}
    
    TASK: Perform a {$refresh_type} refresh of this article.
    
    {$instructions}
    
    CRITICAL REQUIREMENTS:
    1. Preserve all HTML tags and formatting
    2. Keep all internal links (<a> tags) intact
    3. Return ONLY valid HTML (no markdown)
    4. Focus on India-specific context where relevant
    5. Do NOT wrap output in ```html or code blocks
    6. Start immediately with HTML content
    
    OUTPUT: Clean, refreshed HTML content only
    ";

    return $prompt;
}

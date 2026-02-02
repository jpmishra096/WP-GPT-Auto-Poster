<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'gpt-auto-poster',
        'Generate Post',
        'Generate Post',
        'manage_options', // Capability check: only admins
        'gpt-generate-post',
        'gpt_auto_poster_generate_page'
    );
});

function gpt_auto_poster_generate_page() {
    // Capability check: only admins can generate posts
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access.');
    }

    if (isset($_POST['gpt_generate_post'])) {
        check_admin_referer('gpt_generate_post_nonce');

        // Validate required fields
        $post_type   = sanitize_text_field($_POST['post_type']);
        $topic       = sanitize_text_field($_POST['topic']);
        $status      = sanitize_text_field($_POST['post_status']);
        $cat         = intval($_POST['category']);
        $content_api = sanitize_text_field($_POST['content_api']);
        $image_api   = sanitize_text_field($_POST['image_api']);
        $sub_topics  = !empty($_POST['sub_topics']) ? array_map('sanitize_text_field', explode(',', $_POST['sub_topics'])) : [];
        $pillar_id   = !empty($_POST['pillar_id']) ? intval($_POST['pillar_id']) : null;
        $post_author = !empty($_POST['post_author']) ? intval($_POST['post_author']) : null;

        // Validate mandatory fields
        if (empty($post_type)) {
            echo '<div class="error"><p>Post Type is required.</p></div>';
            return;
        }
        if (empty($topic)) {
            echo '<div class="error"><p>Topic is required.</p></div>';
            return;
        }
        if (empty($content_api)) {
            echo '<div class="error"><p>Content Generator API is required.</p></div>';
            return;
        }
        if (empty($cat)) {
            echo '<div class="error"><p>Category is required.</p></div>';
            return;
        }
        // Image API is optional for now

        // Generate content using selected API
        $result = gpt_auto_poster_generate_content($topic, $content_api, $post_type);
        
        // Check for errors
        if (isset($result['error'])) {
            echo '<div class="error"><p>' . esc_html($result['error']) . '</p></div>';
            return;
        }

        $content = $result['content'];
        $snippet = $result['snippet'];
        
        // Safety & formatting cleanup
        // Allow safe HTML
        $content = wp_kses_post($content);

        // Normalize dashes
        $content = str_replace(['—','–'], '-', $content);

        // REMOVE markdown leftovers (hard guarantee)
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content); // **bold**
        $content = preg_replace('/^#{1,6}\s*(.+)$/m', '<h2>$1</h2>', $content);       // # Headings
        $content = preg_replace('/^\s*[-•]\s*(.+)$/m', '<li>$1</li>', $content);     // - bullets

        // Wrap orphan <li> items into <ul>
        if (strpos($content, '<li>') !== false) {
            $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
            $content = preg_replace('/<\/ul>\s*<ul>/', '', $content);
        }

        // Convert double line breaks into paragraphs
        $content = wpautop($content);





    if (strpos($content, 'ERROR') === 0) {
        echo '<div class="error"><p>' . esc_html($content) . '</p></div>';
        return;
    }

    // Get final author ID (selected or current user)
    $author_id = gpt_auto_poster_get_post_author($post_author);

    // Create WordPress post
    $post_id = wp_insert_post([
        'post_title'    => $topic,
        'post_content'  => $content,
        'post_status'   => $status,
        'post_category' => [$cat],
        'post_author'   => $author_id,
    ]);

    if (is_wp_error($post_id)) {
        echo '<div class="error"><p>Post creation failed.</p></div>';
        return;
    }

    // Save post meta
    update_post_meta($post_id, 'gpt_post_type', $post_type);
    update_post_meta($post_id, 'gpt_meta_description', $snippet);
    if (!empty($sub_topics)) {
        update_post_meta($post_id, 'gpt_sub_topics', $sub_topics);
    }
    if ($pillar_id) {
        update_post_meta($post_id, 'gpt_pillar_post_id', $pillar_id);
    }

    echo '<div class="updated"><p><strong>Post created successfully!</strong></p></div>';
    echo '<p><a href="' . esc_url(get_edit_post_link($post_id)) . '" target="_blank">✏️ Edit Post</a></p>';
    echo '<p><a href="' . esc_url(get_permalink($post_id)) . '" target="_blank">🔗 View Post</a></p>';

    // FEATURE 1: Suggest internal links
    echo '<hr style="margin:20px 0;">';
    $existing_posts = gpt_auto_poster_get_publishable_posts($post_id);
    
    if (!empty($existing_posts)) {
        echo '<h3>Step 2: Internal Linking</h3>';
        
        // Get internal link suggestions from AI
        $link_prompt = gpt_auto_poster_generate_internal_links_prompt($content, $existing_posts);
        $link_result = gpt_auto_poster_generate_content_raw($link_prompt, 'openrouter');
        
        if (!isset($link_result['error'])) {
            $suggestions = gpt_auto_poster_parse_link_suggestions($link_result['content']);
            
            if (!empty($suggestions)) {
                gpt_auto_poster_render_link_suggestions($suggestions);
                
                echo '<form method="post" style="margin-top:15px;">';
                wp_nonce_field('gpt_insert_links_nonce');
                echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
                echo '<input type="hidden" name="gpt_insert_links" value="1">';
                echo '<button type="submit" class="button button-primary">Insert Selected Links</button>';
                echo '</form>';
            }
        }
    }
}

// Handle internal link insertion after form submission
if (isset($_POST['gpt_insert_links'])) {
    check_admin_referer('gpt_insert_links_nonce');
    
    $post_id = intval($_POST['post_id']);
    $selected_links = isset($_POST['selected_links']) ? array_map('intval', $_POST['selected_links']) : [];
    
    if (!empty($selected_links) && $post_id) {
        // Get all suggestions
        $all_links_json = isset($_POST['all_links']) ? sanitize_text_field($_POST['all_links']) : '';
        $all_suggestions = json_decode($all_links_json, true);
        
        if (is_array($all_suggestions)) {
            $links_to_insert = [];
            
            // Build array of only selected links
            foreach ($selected_links as $index) {
                if (isset($all_suggestions[$index])) {
                    $links_to_insert[] = $all_suggestions[$index];
                }
            }
            
            // Get current post content
            $post = get_post($post_id);
            $current_content = $post->post_content;
            
            // Insert links into content
            $updated_content = gpt_auto_poster_insert_internal_links($current_content, $links_to_insert);
            
            // Safely update post
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => wp_kses_post($updated_content)
            ]);
            
            echo '<div class="updated"><p>✅ ' . count($links_to_insert) . ' internal links inserted successfully!</p></div>';
            echo '<p><a href="' . esc_url(get_edit_post_link($post_id)) . '" target="_blank">✏️ Edit Post</a></p>';
        }
    }
}

    ?>
    <div class="wrap">
        <h1>Generate Article</h1>

        <form method="post" id="gpt_post_form">
            <?php wp_nonce_field('gpt_generate_post_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th><label for="post_type">Post Type *</label></th>
                    <td>
                        <select id="post_type" name="post_type" required style="width:400px;">
                            <option value="">-- Select Post Type --</option>
                            <option value="pillar">Pillar Post</option>
                            <option value="child">Child/Cluster Post</option>
                            <option value="comparison">Comparison Post</option>
                            <option value="howto">How-To / Step-by-Step Post</option>
                            <option value="faq">FAQ / Explainer Post</option>
                            <option value="money">Money Page / Conversion Post</option>
                            <option value="hub">Hub / Resource Page</option>
                        </select>
                        <p class="description">Select the type of post you want to create</p>
                    </td>
                </tr>

                <!-- Author Selection -->
                <tr>
                    <th><label for="post_author">Author</label></th>
                    <td>
                        <?php gpt_auto_poster_render_author_dropdown(); ?>
                    </td>
                </tr>

                <!-- Pillar Page field - hidden by default, shown for non-pillar posts -->
                <tr id="pillar_page_row" style="display:none;">
                    <th><label for="pillar_id">Pillar Page</label></th>
                    <td>
                        <select id="pillar_id" name="pillar_id" style="width:400px;">
                            <option value="">-- Select a Pillar Post --</option>
                            <?php
                            $pillar_posts = get_posts([
                                'numberposts' => -1,
                                'post_type'   => 'post',
                                'post_status' => 'publish',
                                'meta_key'    => 'gpt_post_type',
                                'meta_value'  => 'pillar'
                            ]);
                            foreach ($pillar_posts as $post) {
                                echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . ' (ID: ' . $post->ID . ')</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Select a pillar post to use as reference (optional)</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="topic">Topic *</label></th>
                    <td>
                        <input type="text" id="topic" name="topic" required style="width:400px;">
                        <p class="description">Enter the topic for your article</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="sub_topics">Sub-Topics (Optional)</label></th>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:10px; width:400px;">
                            <input type="text" id="sub_topic_input" placeholder="Type sub-topic and press Enter" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                            <div id="sub_topics_container" style="display:flex; flex-wrap:wrap; gap:8px; min-height:35px;">
                                <!-- Sub-topics will be added here as chips -->
                            </div>
                            <input type="hidden" id="sub_topics" name="sub_topics" value="">
                        </div>
                        <p class="description">Add multiple sub-topics by typing and pressing Enter. Click a chip to remove it.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="category">Category *</label></th>
                    <td>
                        <div style="display:flex; gap:10px;">
                            <select id="category" name="category" required style="flex:1;">
                                <option value="">-- Select Category --</option>
                                <?php
                                $categories = get_categories(['hide_empty' => false]);
                                foreach ($categories as $cat) {
                                    echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <p class="description"><a href="#" id="create_new_cat_link" style="cursor:pointer; color:#0073aa;">+ Create New Category</a></p>
                    </td>
                </tr>

                <tr>
                    <th><label for="content_api">Content Generator API *</label></th>
                    <td>
                        <select id="content_api" name="content_api" required style="width:400px;">
                            <option value="">-- Select API --</option>
                            <option value="openrouter">OpenRouter.ai (Mistral 7B)</option>
                        </select>
                        <p class="description">Choose which API to use for content generation</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="image_api">Image Generator API (Optional)</label></th>
                    <td>
                        <select id="image_api" name="image_api" style="width:400px;">
                            <option value="">-- Not Using Image API Yet --</option>
                            <option value="unsplash" disabled>Unsplash (Under Review)</option>
                        </select>
                        <p class="description">Image generation APIs are coming soon. Unsplash API is currently under review.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="post_status">Post Status *</label></th>
                    <td>
                        <select id="post_status" name="post_status" required>
                            <option value="draft">Draft</option>
                            <option value="publish">Publish</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="gpt_generate_post">
                    Generate Post
                </button>
            </p>
        </form>
    </div>

    <dialog id="new_category_dialog" style="border-radius:5px; padding:20px; width:400px;">
        <h2>Create New Category</h2>
        <form id="new_category_form">
            <table class="form-table">
                <tr>
                    <th><label for="new_cat_name">Category Name</label></th>
                    <td>
                        <input type="text" id="new_cat_name" name="name" required style="width:100%;">
                    </td>
                </tr>
                <tr>
                    <th><label for="new_cat_slug">Category Slug (optional)</label></th>
                    <td>
                        <input type="text" id="new_cat_slug" name="slug" style="width:100%;">
                    </td>
                </tr>
                <tr>
                    <th><label for="new_cat_description">Description (optional)</label></th>
                    <td>
                        <textarea id="new_cat_description" name="description" style="width:100%; height:100px;"></textarea>
                    </td>
                </tr>
            </table>
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button type="submit" class="button button-primary">Create Category</button>
                <button type="button" class="button" id="close_dialog">Cancel</button>
            </div>
        </form>
    </dialog>

    <style>
        .sub_topic_chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0073aa;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sub_topic_chip:hover {
            background: #005a87;
        }
        .sub_topic_chip .remove {
            font-weight: bold;
            cursor: pointer;
        }
    </style>

    <script>
        // Remember post type selection in localStorage
        const postTypeSelect = document.getElementById('post_type');
        const pillarPageRow = document.getElementById('pillar_page_row');

        // Load saved post type on page load
        window.addEventListener('load', function() {
            const savedPostType = localStorage.getItem('gpt_selected_post_type');
            if (savedPostType) {
                postTypeSelect.value = savedPostType;
                togglePillarPageField();
            }
        });

        // Save post type and toggle fields
        postTypeSelect.addEventListener('change', function() {
            localStorage.setItem('gpt_selected_post_type', this.value);
            togglePillarPageField();
        });

        function togglePillarPageField() {
            const postType = postTypeSelect.value;
            if (postType === 'pillar') {
                pillarPageRow.style.display = 'none';
            } else if (postType) {
                pillarPageRow.style.display = 'table-row';
            } else {
                pillarPageRow.style.display = 'none';
            }
        }

        // Sub-topic chip management
        let subTopics = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            const subTopicInput = document.getElementById('sub_topic_input');
            const subTopicsContainer = document.getElementById('sub_topics_container');
            const subTopicsHidden = document.getElementById('sub_topics');

            if (!subTopicInput) {
                console.error('sub_topic_input element not found');
                return;
            }

            subTopicInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    if (value && !subTopics.includes(value)) {
                        subTopics.push(value);
                        updateSubTopicsDisplay();
                        this.value = '';
                    } else if (subTopics.includes(value)) {
                        alert('This sub-topic is already added');
                        this.value = '';
                    }
                }
            });

            function updateSubTopicsDisplay() {
                subTopicsContainer.innerHTML = '';
                subTopics.forEach((topic, index) => {
                    const chip = document.createElement('div');
                    chip.className = 'sub_topic_chip';
                    chip.innerHTML = `${topic} <span class="remove" data-index="${index}">×</span>`;
                    chip.style.cursor = 'pointer';
                    
                    chip.querySelector('.remove').addEventListener('click', function(e) {
                        e.stopPropagation();
                        subTopics.splice(index, 1);
                        updateSubTopicsDisplay();
                    });
                    
                    subTopicsContainer.appendChild(chip);
                });
                subTopicsHidden.value = subTopics.join(',');
            }
        });

        // Snippet character counter
        const snippetField = document.getElementById('snippet');
        const snippetCount = document.getElementById('snippet_count');

        snippetField.addEventListener('input', function() {
            snippetCount.textContent = this.value.length;
            if (this.value.length > 160) {
                snippetField.style.borderColor = '#dc3545';
            } else if (this.value.length >= 150) {
                snippetField.style.borderColor = '#28a745';
            } else {
                snippetField.style.borderColor = '#ffc107';
            }
        });

        // Category creation modal
        document.getElementById('create_new_cat_link').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('new_category_dialog').showModal();
        });

        document.getElementById('close_dialog').addEventListener('click', function() {
            document.getElementById('new_category_dialog').close();
        });

        document.getElementById('new_category_form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('new_cat_name').value;
            const slug = document.getElementById('new_cat_slug').value || name.toLowerCase().replace(/\s+/g, '-');
            const description = document.getElementById('new_cat_description').value;

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'gpt_create_category',
                    nonce: '<?php echo wp_create_nonce('gpt_create_category_nonce'); ?>',
                    name: name,
                    slug: slug,
                    description: description
                })
            })
            .then(response => {
                return response.json().then(data => ({
                    status: response.status,
                    ok: response.ok,
                    data: data
                }));
            })
            .then(result => {
                if (result.data.success) {
                    const select = document.getElementById('category');
                    const option = document.createElement('option');
                    option.value = result.data.data.cat_id;
                    option.textContent = name;
                    option.selected = true;
                    select.appendChild(option);
                    
                    document.getElementById('new_category_dialog').close();
                    document.getElementById('new_category_form').reset();
                    alert('Category created successfully!');
                } else {
                    const errorMsg = result.data.data ? result.data.data.message : 'Unknown error';
                    alert('Error: ' + errorMsg);
                    console.error('Server response:', result);
                }
            })
            .catch(error => {
                alert('Error creating category: ' + error);
                console.error('Fetch error:', error);
            });
        });
    </script>
    <?php
}

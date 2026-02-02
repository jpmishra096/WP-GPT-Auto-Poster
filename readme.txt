=== GPT Auto Poster ===
Contributors: gpt-auto-poster
Tags: ai, content-generation, blog, automation, mistral
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate SEO-optimized blog content using AI, with internal link suggestions and featured image support.

== Description ==

GPT Auto Poster is a WordPress plugin that automates blog content creation using advanced AI models. Generate high-quality, SEO-optimized posts with minimal effort.

= Key Features =

* **AI Content Generation**: Use OpenRouter API with Mistral 7B model for intelligent content creation
* **Multiple Post Types**: Support for Pillar Pages, Child Pages, Comparison Posts, Listicles, and more
* **Sub-Topics**: Automatically generate content organized by relevant sub-topics
* **Featured Images**: Optional integration with Unsplash API for automatic featured image selection
* **Internal Linking**: AI-powered suggestions for internal links to improve SEO and site structure
* **Post Updates**: Refresh existing articles with Light, Medium, or Heavy content updates
* **Author Selection**: Assign generated posts to different users with edit capabilities
* **Meta Descriptions**: Automatically generate SEO-friendly meta descriptions from content
* **Safe Defaults**: Posts are created as drafts for review before publishing

= How It Works =

1. Configure your API keys (OpenRouter.ai for content, optionally Unsplash for images)
2. Navigate to "GPT Auto Poster" in the WordPress admin
3. Choose "Generate New Post" or "Update Existing Post"
4. Fill in your topic, select post type, add sub-topics
5. Review AI suggestions for internal links (optional)
6. Publish when ready

== Installation ==

1. Upload the `gpt-auto-poster` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Go to Settings > GPT Auto Poster
4. Add your OpenRouter API key (required)
5. Add your Unsplash API key (optional, for featured images)
6. Start generating content!

= API Keys =

**OpenRouter API:**
- Visit https://openrouter.ai
- Sign up for a free account
- Generate an API key from your account settings
- Paste the key into the plugin settings

**Unsplash API (Optional):**
- Visit https://unsplash.com/oauth/applications
- Create a new application
- Copy your Access Key
- Paste into the plugin settings (for automatic featured image generation)

== Frequently Asked Questions ==

= Does this generate original content? =

Yes. The AI uses the Mistral 7B model to generate unique content based on your topic and sub-topics. Content quality depends on how well you define your topic and keywords.

= Are posts published automatically? =

No. All generated posts are created as **drafts** for your review. You must manually review and publish them. This ensures quality control and gives you time to edit if needed.

= What post types are supported? =

- Pillar Page (1,500-3,000 words)
- Child Page (800-2,000 words)
- Comparison Post (800-2,000 words)
- Listicle (800-2,000 words)
- Tutorial (800-2,000 words)
- News/Update (800-2,000 words)
- Opinion/Essay (800-2,000 words)

= Can I update existing posts? =

Yes. Use the "Update Existing Post" feature to refresh articles. Choose from:
- **Light**: Minor rewording and updates
- **Medium**: Significant rewrites with new information
- **Heavy**: Complete rewrite with new structure and content

= Is my API key secure? =

Yes. API keys are stored in WordPress options table with standard WordPress security. Never share your API keys with anyone.

= What if content generation fails? =

Check that:
1. Your OpenRouter API key is valid
2. You have API credits available
3. Your internet connection is stable
4. The API key is properly entered in settings

If issues persist, check your WordPress debug log.

== Screenshots ==

1. Settings page - Configure API keys
2. Post generator - Create new AI posts
3. Internal link suggestions - Select which links to include
4. Update post - Refresh existing articles

== Changelog ==

= 1.0.0 =
* Initial release
* AI content generation with OpenRouter API
* Support for 7 post types with dynamic word counts
* Sub-topics organization
* Internal link suggestions
* Post update/refresh functionality
* Author selection for generated posts
* Auto-generated meta descriptions
* Featured image integration (Unsplash)
* Safe defaults (posts created as drafts)
* Production-hardened security (nonces, capability checks, input validation)

== Support ==

For issues or feature requests, ensure:
1. WordPress is up to date (5.8+)
2. PHP version is 7.4 or higher
3. All API keys are valid
4. Check WordPress debug log for detailed error messages

== License ==

This plugin is licensed under the GPL v2 or later. See LICENSE file for details.

== Credits ==

- Uses OpenRouter.ai API for content generation
- Uses Unsplash API for image search (optional)
- Built with WordPress best practices and security standards

# GPT Auto Poster - Installation & Setup Guide

## Quick Start (5 minutes)

### 1. Install the Plugin
- Download the `gpt-auto-poster` folder
- Upload to `wp-content/plugins/` via FTP or WordPress file manager
- Or use WordPress "Upload Plugin" feature from Plugins > Add New
- Activate the plugin

### 2. Get API Keys

**OpenRouter API (Required)**
1. Visit https://openrouter.ai
2. Create a free account or sign in
3. Go to Settings > API Keys
4. Copy your API Key
5. Paste in WordPress: Settings > GPT Auto Poster > OpenRouter API Key

**Unsplash API (Optional - for featured images)**
1. Visit https://unsplash.com/oauth/applications
2. Create a new Application
3. Copy your Access Key
4. Paste in WordPress: Settings > GPT Auto Poster > Unsplash API Key
5. Leave blank if you don't want automatic featured images

### 3. Configure Settings
- Go to WordPress Admin > Settings > GPT Auto Poster
- Paste your API keys
- Click "Save Settings"
- Done!

### 4. Generate Your First Post
- Go to WordPress Admin > GPT Auto Poster > Generate New Post
- Enter a topic (e.g., "Best WordPress Security Plugins")
- Select a post type (Pillar Page, Child Page, etc.)
- Add sub-topics (optional, e.g., "Security", "Performance", "Ease of Use")
- Click "Generate Content"
- Review the AI-generated content
- (Optional) Select internal links to include
- Click "Create Post"
- Review and edit the draft post
- Click "Publish" when satisfied

## Post Types Explained

| Type | Length | Best For |
|------|--------|----------|
| Pillar Page | 1,500-3,000 words | Comprehensive guides |
| Child Page | 800-2,000 words | Topic subtopics |
| Comparison Post | 800-2,000 words | Product/service comparisons |
| Listicle | 800-2,000 words | "Top 10" style posts |
| Tutorial | 800-2,000 words | How-to guides |
| News/Update | 800-2,000 words | News and announcements |
| Opinion/Essay | 800-2,000 words | Thought leadership |

## Features

### Content Generation
- AI-powered using Mistral 7B language model
- Automatically optimized for SEO
- Generates meta descriptions automatically
- Creates drafts for your review (not auto-published)

### Internal Linking
- AI suggests relevant internal links
- Preview suggestions before insertion
- Improves site SEO and user navigation
- Optional feature

### Post Updates
Refresh existing posts with three intensity levels:
- **Light**: Minor rewording and updates
- **Medium**: Significant rewrites
- **Heavy**: Complete rewrite with new structure

### Author Selection
- Assign posts to different team members
- Dropdown shows all eligible authors
- Defaults to current user if not selected

### Featured Images (Optional)
- Automatic image search from Unsplash
- Requires Unsplash API key
- Skips image if API key not configured

## Troubleshooting

### "Invalid API key" error
- Check API key is correctly copied (no extra spaces)
- Verify API key is still active on provider's website
- Try copying again carefully

### No content generated
- Check internet connection
- Verify API key has remaining credits
- Check WordPress debug log (wp-content/debug.log)

### Posts not saving
- Ensure you have "manage_options" capability (admin user)
- Check WordPress error log
- Try refreshing the page and try again

### Internal links not suggesting
- Ensure you have published posts on your site
- Check that post count is at least 2-3 posts
- Review AI response in debug log if issues persist

## Getting Help

1. Check the included readme.txt for FAQ
2. Review WordPress debug log: wp-content/debug.log
3. Verify all API keys are valid
4. Ensure WordPress is up to date (5.8+)
5. Ensure PHP version is 7.4 or higher

## Best Practices

✅ DO:
- Review all generated content before publishing
- Edit posts to match your brand voice
- Check for factual accuracy
- Use internal linking suggestions
- Keep API keys secure and never share them
- Monitor API usage and costs

❌ DON'T:
- Publish without reviewing
- Use AI-generated content as-is without editing
- Share API keys or configuration
- Generate too many posts at once without review
- Rely entirely on AI without human oversight

## Security Notes

- API keys are stored securely in WordPress database
- Only users with admin access (manage_options) can generate posts
- All user input is sanitized and validated
- Posts are created as drafts by default
- No API errors are shown to users (logged internally only)

## Support Versions

- **WordPress**: 5.8 and above
- **PHP**: 7.4 and above
- **OpenRouter API**: Required
- **Unsplash API**: Optional

---

**Ready to generate amazing content?** Start with GPT Auto Poster today!

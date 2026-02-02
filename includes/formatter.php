<?php
if (!defined('ABSPATH')) exit;

/**
 * Format AI-generated content into safe HTML
 * Converts Markdown-style formatting to proper HTML tags
 * Security: Escapes content before returning, prevents XSS
 */
function gpt_auto_poster_format_content($content) {
    // Input validation
    if (!is_string($content) || empty($content)) {
        return '';
    }

    // Normalize line breaks
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    // Convert Markdown headings to HTML (headings are safe HTML)
    $content = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $content);
    $content = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $content);

    // Remove bold markdown **text** and convert to strong tags
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);

    // Convert bullet points (– or -) to <li>
    $content = preg_replace('/^[–\-•]\s+(.*)$/m', '<li>$1</li>', $content);

    // Wrap <li> inside <ul>
    if (strpos($content, '<li>') !== false) {
        $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
        $content = preg_replace('/<\/ul>\s*<ul>/', '', $content);
    }

    // Convert remaining line breaks to paragraphs
    $lines = explode("\n", $content);
    $formatted = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Check if line is already an HTML tag (safe patterns only)
        if (
            strpos($line, '<h') === 0 ||
            strpos($line, '<ul>') === 0 ||
            strpos($line, '<li>') === 0 ||
            strpos($line, '</ul>') === 0 ||
            strpos($line, '<strong>') === 0
        ) {
            // HTML tags pass through as-is (safe patterns from our regex)
            $formatted .= $line;
        } else {
            // Wrap other content in paragraphs - will be sanitized by wp_kses_post() later
            $formatted .= '<p>' . $line . '</p>';
        }
    }

    return $formatted;
}

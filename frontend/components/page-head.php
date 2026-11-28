<?php
// ============================================================
// MARVELOUS HOTEL — Shared Performance Head & Resource Hints
// frontend/components/page-head.php
// Non-blocking Google Fonts, Font Awesome preconnect, Android theme-color,
// and optional early AI Chatbot stylesheet to prevent body-injected FOUC.
// ============================================================

if (!function_exists('renderPerformanceHeadLinks')) {
    function renderPerformanceHeadLinks(bool $includeAiChatCss = false, bool $includeFontAwesome = true): void
    {
        echo '<meta name="theme-color" content="#0A0F2C">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#C9A84C"/><stop offset="100%" stop-color="#9A7A2E"/></linearGradient></defs><rect width="64" height="64" rx="14" fill="url(#g)"/><text x="32" y="45" text-anchor="middle" font-family="Georgia,serif" font-size="38" font-weight="700" fill="#06091E">M</text></svg>') . '">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        if ($includeFontAwesome) {
            echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
        }
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap';
        echo '<link rel="stylesheet" href="' . $fontsUrl . '" media="print" onload="this.media=\'all\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $fontsUrl . '"></noscript>' . "\n";
        if ($includeFontAwesome) {
            echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">' . "\n";
        }
        if ($includeAiChatCss) {
            $aiHref = function_exists('assetUrl')
                ? assetUrl('css/ai-chat.css')
                : (ASSETS_URL . '/css/ai-chat.css?v=' . (@filemtime(SITE_ROOT . '/frontend/assets/css/ai-chat.css') ?: '1'));
            echo '<link rel="stylesheet" href="' . h($aiHref) . '">' . "\n";
            $GLOBALS['mh_ai_chat_css_loaded'] = true;
        }
    }
}

<?php
// ============================================================
// MARVELOUS HOTEL — Frontend Framework Bundle Loader
// frontend/components/frontend-frameworks.php
// Loads compiled Tailwind CSS, HotelAPI REST client, & local Alpine.js 3
// ============================================================

if (!function_exists('renderFrontendFrameworks')) {
    function renderFrontendFrameworks(bool $includeHeadCss = true, bool $includeScripts = true): void
    {
        $apiBase = defined('API_URL') ? API_URL : (SITE_URL . '/backend/api/v1');
        $portal  = defined('ACTIVE_PORTAL') ? ACTIVE_PORTAL : 'user';
        $csrf    = function_exists('generateCsrfToken') ? generateCsrfToken() : '';

        if ($includeHeadCss) {
            $twHref = function_exists('assetUrl')
                ? assetUrl('css/tailwind.css')
                : (ASSETS_URL . '/css/tailwind.css?v=' . (@filemtime(SITE_ROOT . '/frontend/assets/css/tailwind.css') ?: '1'));
            echo '<link rel="stylesheet" href="' . h($twHref) . '">' . "\n";
        }

        if ($includeScripts) {
            $apiJsHref = function_exists('assetUrl')
                ? assetUrl('js/api-client.js')
                : (ASSETS_URL . '/js/api-client.js?v=' . (@filemtime(SITE_ROOT . '/frontend/assets/js/api-client.js') ?: '1'));
            $alpineHref = function_exists('assetUrl')
                ? assetUrl('vendor/alpine.min.js')
                : (ASSETS_URL . '/vendor/alpine.min.js?v=' . (@filemtime(SITE_ROOT . '/frontend/assets/vendor/alpine.min.js') ?: '1'));
            echo '<script>'
                . 'window.HOTEL_API_BASE=' . json_encode($apiBase, JSON_HEX_TAG | JSON_HEX_AMP) . ';'
                . 'window.HOTEL_PORTAL=' . json_encode($portal, JSON_HEX_TAG | JSON_HEX_AMP) . ';'
                . 'window.HOTEL_CSRF_TOKEN=' . json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) . ';'
                . '</script>' . "\n";
            echo '<script defer src="' . h($apiJsHref) . '"></script>' . "\n";
            echo '<script defer src="' . h($alpineHref) . '"></script>' . "\n";
        }
    }
}

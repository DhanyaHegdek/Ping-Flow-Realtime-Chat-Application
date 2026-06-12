<?php

if (!function_exists('highlightSearch')) {
    /**
     * Highlight search term in message body for search results panel.
     * Used in livewire/chat.blade.php search results.
     */
    function highlightSearch(string $text, string $query): string
    {
        if (!$query) return e($text);
        $escaped = e($text);
        $pattern = '/(' . preg_quote(e($query), '/') . ')/i';
        return preg_replace($pattern, '<mark>$1</mark>', $escaped);
    }
}

<?php
function icon(string $name): string {
    $paths = [
        'menu' => '<path d="M4 3v6c0 2 4 2 4 0V3M6 3v18M17 3c-3 3-3 8 1 8h1M19 3v18"/>',
        'cart' => '<path d="M3 4h2l2 11h11l3-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>',
        'orders' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 9h6M9 13h6M9 17h4"/>',
        'help' => '<path d="M4 17h16M6 17v-5a6 6 0 0 1 12 0v5M10 21h4M12 3V2"/>',
        'bill' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 7h6M9 11h6M9 15h3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'remove' => '<path d="M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"/>',
        'back' => '<path d="m10 5-7 7 7 7M3 12h18"/>',
        'table' => '<rect x="3" y="5" width="18" height="8" rx="1"/><path d="M5 13v7M19 13v7M3 9h18"/>',
        'users' => '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 14c2 1 3 2 3 5v2"/>',
        'chart' => '<path d="M4 3v18h17M9 16v-5M14 16V7M19 16V4"/>',
        'logout' => '<path d="M10 4H4v16h6M14 8l4 4-4 4M8 12h13"/>',
        'check' => '<path d="m4 12 5 5L20 6"/>',
        'edit' => '<path d="m4 16-1 5 5-1L20 8l-4-4L4 16ZM14 6l4 4"/>',
        'unavailable' => '<circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/>'
    ];
    return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['menu']) . '</svg>';
}

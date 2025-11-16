<?php
/**
 * Helper functions
 */

/**
 * Base URL ni qo'shib to'liq URL yaratadi
 */
function base_url($path = '') {
    $base = defined('BASE_URL') ? BASE_URL : '';
    $path = ltrim($path, '/');
    return $base . ($path ? '/' . $path : '');
}

/**
 * Redirect qiladi
 */
function redirect($path) {
    header('Location: ' . base_url($path));
    exit;
}


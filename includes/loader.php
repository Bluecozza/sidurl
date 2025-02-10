<?php
defined('ABSPATH') || exit;

// Path folder modules
$modules_dir = plugin_dir_path(__FILE__) . '../modules/';

// Periksa apakah folder modules ada
if (!is_dir($modules_dir)) {
    mkdir($modules_dir, 0755, true);
}

// Ambil daftar modul yang diaktifkan dari database
$active_modules = get_option('sidurl_active_modules', []);

// Muat setiap modul yang diaktifkan
foreach ($active_modules as $module) {
    $module_file = $modules_dir . $module . '.php';
    if (file_exists($module_file)) {
        include_once $module_file;
    }
}

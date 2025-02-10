<?php
defined('ABSPATH') || exit;

// Path folder modules
$modules_dir = plugin_dir_path(__FILE__) . '../modules/';

// Pastikan folder modules ada
if (!is_dir($modules_dir)) {
    mkdir($modules_dir, 0755, true);
}

// Ambil daftar modul yang diaktifkan dari database
$active_modules = get_option('sidurl_active_modules', []);

// Array global untuk menyimpan fungsi modul
global $sidurl_active_modules_list;
$sidurl_active_modules_list = [];

// Muat setiap modul yang diaktifkan
foreach ($active_modules as $module) {
    $module_file = $modules_dir . $module . '.php';
    if (file_exists($module_file)) {
        include_once $module_file;
        // Jika ada fungsi pendaftaran, simpan dalam array
        if (function_exists("sidurl_module_register_$module")) {
            $sidurl_active_modules_list[] = "sidurl_module_register_$module";
        }
    }
}

add_action('admin_menu', function () {
    global $sidurl_active_modules_list;
    foreach ($sidurl_active_modules_list as $module_register_function) {
        call_user_func($module_register_function);
    }
}, 10); // Priority 10 (setelah menu utama terdaftar)

function sidurl_is_module_active($module_slug) {
    $active_modules = get_option('sidurl_active_modules', []);
    return in_array($module_slug, $active_modules);
}
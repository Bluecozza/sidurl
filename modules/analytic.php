<?php
defined('ABSPATH') || exit;

function sidurl_module_register_analytic() {
    add_submenu_page(
        'sidurl',  // Parent: menu utama Sidurl
        'Analytic Settings',
        'Analytic',
        'manage_options',
        'sidurl-analytic',
        'sidurl_analytic_page'
    );
}

// Halaman pengaturan modul Analytic
function sidurl_analytic_page() {
    ?>
    <div class="wrap">
        <h1>Analytic Module</h1>
        <p>Ini adalah halaman pengaturan untuk modul Analytic.</p>
    </div>
    <?php
}

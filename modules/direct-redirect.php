<?php
defined('ABSPATH') || exit;

function sidurl_module_register_direct_redirect() {
    add_submenu_page(
        'sidurl',
        'Direct Redirect Settings',
        'Direct Redirect',
        'manage_options',
        'sidurl-direct-redirect',
        'sidurl_direct_redirect_settings_page'
    );
}

function sidurl_direct_redirect_settings_page() {
    ?>
    <div class="wrap">
        <h1>Direct Redirect Module</h1>
        <form method="post" action="options.php">
            <?php 
            settings_fields('sidurl_direct_redirect_group');
            do_settings_sections('sidurl_direct_redirect_group');
            ?>
            <p><?php _e('302 Direct redirect is now active.', 'sidurl'); ?></p>
            <?php submit_button(__('Save Settings', 'sidurl')); ?>
        </form>
    </div>
    <?php
}

// Handle direct redirect
add_filter('sidurl_redirect_handler', 'sidurl_direct_redirect', 10, 2);

function sidurl_direct_redirect($url, $result) {
    if (sidurl_is_module_active('direct-redirect')) {
        wp_redirect(esc_url_raw($result->long_url), 302);
        exit;
    }
    return $url;
}
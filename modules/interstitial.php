<?php
defined('ABSPATH') || exit;

// ==============================================
// REGISTER MODULE
// ==============================================
function sidurl_module_register_interstitial() {
    add_submenu_page(
        'sidurl',
        'Interstitial Settings',
        'Interstitial',
        'manage_options',
        'sidurl-interstitial',
        'sidurl_interstitial_settings_page'
    );
    
    add_action('admin_init', 'sidurl_interstitial_register_settings');
}

// ==============================================
// SETTINGS PAGE
// ==============================================
function sidurl_interstitial_register_settings() {
    register_setting(
        'sidurl_interstitial_group',
        'sidurl_interstitial_options',
        array(
            'sanitize_callback' => 'sidurl_interstitial_sanitize_options',
            'default' => array(
                'enabled' => 1,
                'timer' => 5,
                'custom_content' => ''
            )
        )
    );
}

function sidurl_interstitial_sanitize_options($input) {
    $new_input = array();
    
    $new_input['enabled'] = isset($input['enabled']) ? 1 : 0;
    $new_input['timer'] = min(max(absint($input['timer']), 1), 60); // Batasi 1-60 detik
    $new_input['custom_content'] = wp_kses_post($input['custom_content']);
    
    return $new_input;
}

function sidurl_interstitial_settings_page() {
    $options = get_option('sidurl_interstitial_options');
    ?>
    <div class="wrap">
        <h1>Interstitial Settings</h1>
        <form method="post" action="options.php">
            <?php 
            settings_fields('sidurl_interstitial_group');
            do_settings_sections('sidurl_interstitial_group');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Interstitial</th>
                    <td>
                        <label>
                            <input type="checkbox" name="sidurl_interstitial_options[enabled]" 
                                value="1" <?php checked($options['enabled'], 1); ?>>
                            Aktifkan halaman interstitial
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Redirect Timer</th>
                    <td>
                        <input type="number" name="sidurl_interstitial_options[timer]" 
                            value="<?php echo esc_attr($options['timer']); ?>" 
                            min="1" max="60" step="1">
                        detik
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Custom Content</th>
                    <td>
                        <?php wp_editor(
                            $options['custom_content'],
                            'sidurl_interstitial_content',
                            array(
                                'textarea_name' => 'sidurl_interstitial_options[custom_content]',
                                'media_buttons' => false,
                                'teeny' => true
                            )
                        ); ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ==============================================
// REDIRECT HANDLER
// ==============================================
add_filter('sidurl_redirect_handler', 'sidurl_interstitial_redirect_handler', 10, 2);

function sidurl_interstitial_redirect_handler($url, $result) {
    $options = get_option('sidurl_interstitial_options');
    
    if ($options['enabled']) {
        sidurl_show_interstitial_page($result->long_url, $options);
        exit; // Hentikan eksekusi
    }
    
    return $url;
}

// ==============================================
// INTERSTITIAL PAGE
// ==============================================
function sidurl_show_interstitial_page($target_url, $options) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php _e('Redirecting...', 'sidurl'); ?></title>
        <meta http-equiv="refresh" content="<?php echo $options['timer']; ?>;url=<?php echo esc_url($target_url); ?>">
        <style>
            /* ... gaya sebelumnya ... */
        </style>
    </head>
    <body>
        <div class="container">
            <?php if (!empty($options['custom_content'])) : ?>
                <?php echo wp_kses_post($options['custom_content']); ?>
            <?php else : ?>
                <h1><?php _e('Anda akan diarahkan...', 'sidurl'); ?></h1>
                <div class="loading">
                    <?php printf(
                        __('Redirect otomatis dalam %d detik...', 'sidurl'),
                        $options['timer']
                    ); ?>
                </div>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo esc_url($target_url); ?>">
                    <?php _e('Klik di sini jika tidak otomatis redirect', 'sidurl'); ?>
                </a>
            </p>
        </div>
        
        <script>
            // Timer countdown
            let timeLeft = <?php echo $options['timer']; ?>;
            const timerElement = document.querySelector('.loading');
            
            const updateTimer = () => {
                if(timerElement) {
                    timerElement.textContent = `<?php _e('Redirect otomatis dalam', 'sidurl'); ?> ${timeLeft} <?php _e('detik...', 'sidurl'); ?>`;
                }
                timeLeft--;
                
                if(timeLeft < 0) {
                    clearInterval(timerInterval);
                }
            };
            
            const timerInterval = setInterval(updateTimer, 1000);
        </script>
    </body>
    </html>
    <?php
    exit;
}
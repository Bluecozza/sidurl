<?php
/*
Plugin Name: Sidurl
Description: Plugin Short URL dengan Fitur Lengkap.
Version: 1.4
Author: Nur Muhammad Daim @ Satui.ID
Text Domain: sidurl
*/

defined('ABSPATH') || exit;
include_once plugin_dir_path(__FILE__) . 'includes/interstitial.php';
//define('SIDURL_UPDATE_URL', 'https://raw.githubusercontent.com/Bluecozza/sidurl/refs/heads/main/sidurl.json');



// ==============================================
// KONEKSI DATABASE & SETUP AWAL
// ==============================================
register_activation_hook(__FILE__, 'sidurl_activate_plugin');
register_deactivation_hook(__FILE__, 'sidurl_deactivate_plugin');

function sidurl_activate_plugin() {
    sidurl_create_database_table();
    sidurl_add_rewrite_rules();
    flush_rewrite_rules();
}

function sidurl_deactivate_plugin() {
    flush_rewrite_rules();
}

// ==============================================
// FUNGSI DATABASE
// ==============================================
function sidurl_check_database_table() {
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}sidurl'") === $wpdb->prefix.'sidurl';
}

function sidurl_create_database_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'sidurl';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        long_url text NOT NULL,
        $short_code varchar(6) NOT NULL,
        clicks mediumint(9) UNSIGNED NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY short_code (short_code),
        KEY long_url (long_url(191))
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    
    return true;
}

// ==============================================
// REWRITE RULES & REDIRECT
// ==============================================
add_action('init', 'sidurl_add_rewrite_rules');
add_filter('query_vars', 'sidurl_add_query_vars');
add_action('template_redirect', 'sidurl_handle_redirect');

function sidurl_add_rewrite_rules() {
    add_rewrite_rule('^([a-zA-Z0-9]{6})/?$', 'index.php?sidurl_redirect=$matches[1]', 'top');
}

function sidurl_add_query_vars($vars) {
    $vars[] = 'sidurl_redirect';
    return $vars;
}
function sidurl_handle_redirect() {
    try {
        global $wpdb;
        

		
        $short_code = get_query_var('sidurl_redirect');
		if (empty($short_code)) return;
        if (!$short_code) {
            throw new Exception('Short code tidak ditemukan');
        }

        // Validasi short code
        if (!preg_match('/^[a-zA-Z0-9]{6}$/', $short_code)) {
            throw new Exception('Format short code tidak valid');
        }

        // Cari URL asli
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sidurl WHERE short_code = %s",
            $short_code
        ));

        if (!$result) {
            throw new Exception('Short URL tidak ditemukan');
        }

        // Update jumlah klik
        $wpdb->update(
            $wpdb->prefix.'sidurl',
            array('clicks' => $result->clicks + 1),
            array('id' => $result->id)
        );

        // Handle redirect type
        $redirect_type = get_option('sidurl_redirect_type', 'direct');
        
        if ($redirect_type === 'interstitial') {
            sidurl_show_interstitial_page($result->long_url);
            exit;
        }

        // Redirect langsung
        wp_redirect(esc_url_raw($result->long_url));
        exit;

    } catch (Exception $e) {
        error_log('[Sidurl Redirect Error] ' . $e->getMessage());
        wp_die(
            '<h1>Terjadi Kesalahan</h1>' .
            '<p>Maaf, terjadi kesalahan saat memproses permintaan Anda.</p>' .
            '<p><strong>Detail Error:</strong> ' . esc_html($e->getMessage()) . '</p>' .
            '<p><a href="' . home_url() . '">Kembali ke Beranda</a></p>',
            'Sidurl Error',
            array('response' => 500)
        );
    }
}

// ==============================================
// ADMIN DASHBOARD
// ==============================================
add_action('admin_menu', 'sidurl_admin_menu');
add_action('admin_post_sidurl_recreate_table', 'sidurl_handle_recreate_table');
add_action('admin_init', 'sidurl_register_settings');

function sidurl_admin_menu() {
    add_menu_page(
        'Sidurl',
        'Sidurl',
        'manage_options',
        'sidurl',
        'sidurl_admin_dashboard',
        'dashicons-admin-links',
        80
    );

    add_submenu_page(
        'sidurl',
        __('Settings', 'sidurl'),
        __('Settings', 'sidurl'),
        'manage_options',
        'sidurl-settings',
        'sidurl_settings_page'
    );
}

function sidurl_register_settings() {
    register_setting(
        'sidurl_settings_group',
        'sidurl_redirect_type',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sidurl_sanitize_redirect_type',
            'default' => 'direct'
        )
    );
}

add_action('admin_menu', 'sidurl_add_update_menu');

function sidurl_add_update_menu() {
    add_submenu_page(
        'options-general.php', 
        'SidURL Update', 
        'SidURL Update', 
        'manage_options', 
        'sidurl-update', 
        'sidurl_update_page'
    );
}

function sidurl_update_page() {
    ?>
    <div class="wrap">
        <h1>SidURL Update</h1>
        <p>Periksa apakah ada pembaruan terbaru.</p>
        <button id="sidurl-check-update" class="button button-primary">Cek Update</button>
        <div id="sidurl-update-result"></div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#sidurl-check-update').click(function() {
            $(this).prop('disabled', true).text('Mengecek...');
            $.post(ajaxurl, { action: 'sidurl_check_update' }, function(response) {
                $('#sidurl-update-result').html(response);
                $('#sidurl-check-update').prop('disabled', false).text('Cek Update');
            });
        });
    });
    </script>
    <?php
}
add_action('wp_ajax_sidurl_check_update', 'sidurl_check_update');

function sidurl_check_update() {
    $repo_owner = 'Bluecozza';
    $repo_name  = 'sidurl';

    $api_url = "https://api.github.com/repos/{$repo_owner}/{$repo_name}/releases/latest";
    $response = wp_remote_get($api_url, ['timeout' => 10, 'user-agent' => 'WordPress']);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_die('<p style="color: red;">Gagal memeriksa update.</p>');
    }

    $release_data = json_decode(wp_remote_retrieve_body($response), true);
    $latest_version = $release_data['tag_name'];
    $download_url = $release_data['zipball_url'];

    // Ambil versi saat ini dari plugin
    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/sidurl/sidurl.php');
    $current_version = $plugin_data['Version'];

    if (version_compare($latest_version, $current_version, '>')) {
        echo "<p>Versi terbaru tersedia: <strong>{$latest_version}</strong></p>";
        echo "<button id='sidurl-update' class='button button-primary' data-url='{$download_url}'>Update Sekarang</button>";

        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#sidurl-update').click(function() {
                var downloadUrl = $(this).data('url');
                $(this).prop('disabled', true).text('Mengupdate...');
                $.post(ajaxurl, { action: 'sidurl_perform_update', url: downloadUrl }, function(response) {
                    $('#sidurl-update-result').html(response);
                });
            });
        });
        </script>
        <?php
    } else {
        echo "<p>Anda sudah menggunakan versi terbaru ({$current_version}).</p>";
    }
    
    wp_die();
}
//////////
add_action('wp_ajax_sidurl_perform_update', 'sidurl_perform_update');

function sidurl_perform_update() {
    if (!current_user_can('manage_options')) {
        wp_die('<p style="color: red;">Akses ditolak.</p>');
    }

    include_once(ABSPATH . 'wp-admin/includes/file.php');
    include_once(ABSPATH . 'wp-admin/includes/misc.php');
    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    global $wp_filesystem;

    // Inisialisasi filesystem
    if (!WP_Filesystem()) {
        wp_die('<p style="color: red;">Gagal menginisialisasi filesystem.</p>');
    }

    $download_url = esc_url_raw($_POST['url']);
    $tmp_file = download_url($download_url);

    if (is_wp_error($tmp_file)) {
        wp_die('<p style="color: red;">Gagal mengunduh file update.</p>');
    }

    $plugin_slug = 'sidurl';
    $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
    $tmp_extract_dir = WP_PLUGIN_DIR . '/sidurl-temp';

    // Matikan plugin sebelum update
    deactivate_plugins($plugin_slug . '/' . $plugin_slug . '.php');

    // Hapus folder sementara jika ada
    if ($wp_filesystem->exists($tmp_extract_dir)) {
        $wp_filesystem->delete($tmp_extract_dir, true);
    }

    // Ekstrak ZIP ke folder sementara
    $unzip_result = unzip_file($tmp_file, $tmp_extract_dir);
    unlink($tmp_file); // Hapus ZIP setelah ekstraksi

    if (is_wp_error($unzip_result)) {
        wp_die('<p style="color: red;">Gagal mengekstrak file update.</p>');
    }

    // Cari folder hasil ekstraksi (biasanya nama acak seperti "sidurl-latest")
    $extracted_folders = scandir($tmp_extract_dir);
    $new_plugin_dir = '';

    foreach ($extracted_folders as $folder) {
        if ($folder !== '.' && $folder !== '..') {
            $new_plugin_dir = $tmp_extract_dir . '/' . $folder;
            break;
        }
    }

    if (!$new_plugin_dir || !$wp_filesystem->exists($new_plugin_dir)) {
        wp_die('<p style="color: red;">Gagal menemukan folder plugin yang diekstrak.</p>');
    }

    // Hapus folder plugin lama
    if ($wp_filesystem->exists($plugin_dir)) {
        $wp_filesystem->delete($plugin_dir, true);
    }

    // Pindahkan isi folder hasil ekstraksi ke `sidurl/`
    $move_result = rename($new_plugin_dir, $plugin_dir);

    if (!$move_result) {
        // Jika gagal rename, coba pindahkan isinya satu per satu
        $files = scandir($new_plugin_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $wp_filesystem->move($new_plugin_dir . '/' . $file, $plugin_dir . '/' . $file, true);
            }
        }
    }

    // Hapus folder sementara jika masih ada
    if ($wp_filesystem->exists($tmp_extract_dir)) {
        $wp_filesystem->delete($tmp_extract_dir, true);
    }

    // Aktifkan ulang plugin
    $activate_result = activate_plugin($plugin_slug . '/' . $plugin_slug . '.php');

    if (is_wp_error($activate_result)) {
        wp_die('<p style="color: orange;">Plugin diperbarui tetapi gagal diaktifkan. Silakan aktifkan manual.</p>');
    }

    wp_die('<p style="color: green;">Plugin berhasil diperbarui!</p>');
}

/////////


function sidurl_sanitize_redirect_type($input) {
    return in_array($input, array('direct', 'interstitial')) ? $input : 'direct';
}

/////
function sidurl_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Anda tidak memiliki izin untuk mengakses halaman ini', 'sidurl'));
    }
    
    settings_errors('sidurl_messages');
    ?>
    <div class="wrap">
        <h1><?php _e('Pengaturan Sidurl', 'sidurl'); ?></h1>
        
        <form method="post" action="options.php">
            <?php 
            settings_fields('sidurl_settings_group');
            do_settings_sections('sidurl_settings_group');
            $current_type = get_option('sidurl_redirect_type', 'direct');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Tipe Redirect', 'sidurl'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="sidurl_redirect_type" 
                                    value="direct" <?php checked($current_type, 'direct'); ?>>
                                <?php _e('Direct Redirect', 'sidurl'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="sidurl_redirect_type" 
                                    value="interstitial" <?php checked($current_type, 'interstitial'); ?>>
                                <?php _e('Interstitial Page', 'sidurl'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Simpan Pengaturan', 'sidurl')); ?>
        </form>
		        <h2><?php _e('Cek & Update Plugin', 'sidurl'); ?></h2>
        <button id="sidurl-check-update" class="button button-secondary">
            <?php _e('Cek Update', 'sidurl'); ?>
        </button>
        <div id="sidurl-update-result"></div>
    </div>
		<script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#sidurl-check-update').on('click', function() {
                var button = $(this);
                button.text('<?php _e('Mengecek...', 'sidurl'); ?>').prop('disabled', true);

                $.post(ajaxurl, { action: 'sidurl_check_update' }, function(response) {
                    $('#sidurl-update-result').html(response);
                    button.text('<?php _e('Cek Update', 'sidurl'); ?>').prop('disabled', false);
                });
            });

            $(document).on('click', '#sidurl-update-now', function() {
                var button = $(this);
                button.text('<?php _e('Mengunduh & Mengupdate...', 'sidurl'); ?>').prop('disabled', true);

                $.post(ajaxurl, { action: 'sidurl_perform_update' }, function(response) {
                    $('#sidurl-update-result').html(response);
                });
            });
        });
    </script>	
	
    <?php
}

/////

function sidurl_admin_dashboard() {
    global $wpdb;
    
    // Database check
    if (!sidurl_check_database_table()) {
        echo '<div class="notice notice-error"><p>';
        _e('Database table not found!', 'sidurl');
        echo ' <form method="post" action="'.admin_url('admin-post.php').'" style="display:inline-block;">
                <input type="hidden" name="action" value="sidurl_recreate_table">
                '.wp_nonce_field('sidurl_recreate_table', '_wpnonce', true, false).'
                <button type="submit" class="button button-primary">'.__('Create Table Now', 'sidurl').'</button>
              </form>';
        echo '</p></div>';
        return;
    }

    // Handle bulk actions
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-urls')) {
            wp_die(__('Security check failed', 'sidurl'));
        }
        
        $ids = array_map('intval', (array)$_POST['ids']);
        $wpdb->query("DELETE FROM {$wpdb->prefix}sidurl WHERE id IN (".implode(',', $ids).")");
        echo '<div class="notice notice-success"><p>'.__('Selected items deleted!', 'sidurl').'</p></div>';
    }

    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}sidurl");
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sidurl 
        ORDER BY created_at DESC 
        LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Short URLs', 'sidurl'); ?></h1>
        
        <form method="post">
            <?php wp_nonce_field('bulk-urls', '_wpnonce'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox"></th>
                        <th><?php _e('Original URL', 'sidurl'); ?></th>
                        <th><?php _e('Short URL', 'sidurl'); ?></th>
                        <th><?php _e('Clicks', 'sidurl'); ?></th>
                        <th><?php _e('Created', 'sidurl'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results) : foreach ($results as $row) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="ids[]" value="<?php echo $row->id; ?>">
                        </th>
                        <td>
                            <a href="<?php echo esc_url($row->long_url); ?>" target="_blank">
                                <?php echo esc_html(substr($row->long_url, 0, 80)); ?>...
                            </a>
                            <div class="row-actions">
                                <span class="delete">
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=sidurl&action=delete&id='.$row->id),
                                        'delete-url_'.$row->id
                                    ); ?>"><?php _e('Delete', 'sidurl'); ?></a>
                                </span>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo home_url('/'.$row->short_code); ?>" target="_blank">
                                <?php echo home_url('/'.$row->short_code); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($row->clicks); ?></td>
                        <td>
                            <?php echo date_i18n(
                                get_option('date_format').' '.get_option('time_format'),
                                strtotime($row->created_at)
                            ); ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5"><?php _e('No short URLs found', 'sidurl'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php _e('Bulk Actions', 'sidurl'); ?></option>
                        <option value="delete"><?php _e('Delete', 'sidurl'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'sidurl'); ?>">
                </div>

                <?php if ($total_items > $per_page) : ?>
                <div class="tablenav-pages">
                    <?php echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total' => ceil($total_items / $per_page),
                        'current' => $current_page
                    )); ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
}
// ==============================================
// FRONTEND & SHORTCODE
// ==============================================
add_shortcode('sidurl_form', 'sidurl_shortcode_form');
add_action('wp_enqueue_scripts', 'sidurl_enqueue_assets');

function sidurl_enqueue_assets() {
	
    wp_enqueue_style(
        'sidurl-style',
        plugins_url('/css/sidurl-style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'css/sidurl-style.css')
    );
    
    wp_enqueue_script(
        'clipboard',
        plugins_url('/js/clipboard.min.js', __FILE__),
        array(),
        '2.0.11',
        true
    );
    
    wp_enqueue_script(
        'sidurl-ajax',
        plugins_url('/js/sidurl-ajax.js', __FILE__),
        array('jquery', 'clipboard'),
        filemtime(plugin_dir_path(__FILE__) . 'js/sidurl-ajax.js'),
        true
    );
    
    wp_localize_script('sidurl-ajax', 'sidurl_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sidurl_ajax_nonce'),
        'i18n' => array(
            'processing' => __('Memproses...', 'sidurl'),
            'success' => __('Short URL Berhasil Dibuat:', 'sidurl'),
            'error' => __('Terjadi Kesalahan Jaringan', 'sidurl'),
            'copied' => __('Tersalin!', 'sidurl')
        )
    ));
}

function sidurl_shortcode_form() {
    ob_start(); ?>
    <div class="sidurl-form">
        <form id="sidurl-form">
            <input type="url" name="long_url" required 
                   placeholder="<?php _e('Masukkan URL panjang...', 'sidurl'); ?>">
            <button type="submit">
                <?php _e('Buat Short URL', 'sidurl'); ?>
            </button>
        </form>
        <div id="sidurl-result"></div>
    </div>
    <?php
    return ob_get_clean();
}

// ==============================================
// AJAX HANDLER
// ==============================================
add_action('wp_ajax_sidurl_generate', 'sidurl_handle_ajax');
add_action('wp_ajax_nopriv_sidurl_generate', 'sidurl_handle_ajax');

function sidurl_handle_ajax() {
    $response = array(
        'success' => false,
        'data' => array(
            'message' => __('Terjadi kesalahan sistem', 'sidurl')
        )
    );

    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sidurl_ajax_nonce')) {
            throw new Exception(__('Verifikasi keamanan gagal', 'sidurl'));
        }

        if (!isset($_POST['long_url']) || empty(trim($_POST['long_url']))) {
            throw new Exception(__('URL tidak boleh kosong', 'sidurl'));
        }

        $long_url = esc_url_raw($_POST['long_url']);
        if (!filter_var($long_url, FILTER_VALIDATE_URL)) {
            throw new Exception(__('Format URL tidak valid', 'sidurl'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sidurl';

        $short_code = sidurl_generate_unique_code();
        
        $wpdb->insert(
            $table_name,
            array(
                'long_url' => $long_url,
                'short_code' => $short_code
            ),
            array('%s', '%s')
        );

        if ($wpdb->last_error) {
            throw new Exception(__('Gagal menyimpan ke database', 'sidurl') . ' | ' . $wpdb->last_error);
        }

        $response['success'] = true;
        $response['data']['short_url'] = home_url('/' . $short_code);
        
    } catch (Exception $e) {
        error_log('[Sidurl Error] ' . $e->getMessage());
        $response['data']['message'] = $e->getMessage();
    }

    wp_send_json($response);
}

function sidurl_generate_unique_code() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sidurl';
    
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max_attempts = 10;

    for ($i = 0; $i < $max_attempts; $i++) {
        $code = substr(str_shuffle($chars), 0, 6);
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT short_code FROM $table_name WHERE short_code = %s LIMIT 1",
            $code
        ));

        if (!$exists) return $code;
    }

    throw new Exception(__('Gagal membuat short URL unik', 'sidurl'));
}


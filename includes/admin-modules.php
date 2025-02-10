<?php
defined('ABSPATH') || exit;

// Tambahkan menu di dashboard


// Halaman pengaturan modul
function sidurl_modules_page()
{
    $modules_dir = plugin_dir_path(__FILE__) . '../modules/';
    $existing_modules = array_diff(scandir($modules_dir), ['.', '..']);
    $existing_modules = array_map(fn($m) => pathinfo($m, PATHINFO_FILENAME), $existing_modules);

    $active_modules = get_option('sidurl_active_modules', []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('sidurl_save_modules')) {
        $active_modules = isset($_POST['modules']) ? $_POST['modules'] : [];
        update_option('sidurl_active_modules', $active_modules);
        echo '<div class="updated"><p>Modules updated!</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Modules and Plugin</h1>
        <form method="post">
            <?php wp_nonce_field('sidurl_save_modules'); ?>
            <?php foreach ($existing_modules as $module) : ?>
                <label>
                    <input type="checkbox" name="modules[]" value="<?php echo esc_attr($module); ?>" 
                        <?php checked(in_array($module, $active_modules)); ?>>
                    <?php echo esc_html(ucfirst($module)); ?>
                </label><br>
            <?php endforeach; ?>
            <p><button type="submit" class="button button-primary">Save Changes</button></p>
        </form>
    </div>
    <?php
}

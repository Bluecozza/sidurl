<?php
function sidurl_show_interstitial_page($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        wp_die(esc_html__('URL tujuan tidak valid', 'sidurl'), esc_html__('Sidurl Error', 'sidurl'), array('response' => 400));

    }

    if (headers_sent()) {
        wp_die('Cannot redirect, headers already sent.', 'Sidurl Error', array('response' => 500));
    }
    
    ob_start(); // Pastikan output buffer dimulai
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php _e('Redirecting...', 'sidurl'); ?></title>
        <meta http-equiv="refresh" content="5;url=<?php echo esc_url($url); ?>">
        <style>
            body {
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px;
                background: #f7f7f7;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            a {
                color: #2271b1;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            .loading {
                margin: 20px 0;
                font-size: 1.2em;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><?php _e('Anda akan diarahkan...', 'sidurl'); ?></h1>
            <div class="loading"><?php _e('Harap tunggu sebentar...', 'sidurl'); ?></div>
            <p><?php _e('Jika browser tidak otomatis mengarahkan Anda,', 'sidurl'); ?>
               <a href="<?php echo esc_url($url); ?>"><?php _e('klik di sini', 'sidurl'); ?></a>.
            </p>
        </div>
    </body>
    </html>
    <?php
    ob_end_flush(); // Kirim output buffer
    exit;
}

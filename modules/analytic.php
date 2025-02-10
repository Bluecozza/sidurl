<?php
defined('ABSPATH') || exit;

add_action('wp_footer', function () {
    echo "<script>console.log('Analytic Module Loaded');</script>";
});

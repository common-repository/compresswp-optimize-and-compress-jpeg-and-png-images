<?php

/**
* Plugin Name: CompressWP - optimize and compress JPEG and PNG images
* Plugin URI: https://compresswp.com
* Description: Optimize JPEG and PNG images to significantly improve your page load speeds.
* Version: 1.0.3
* Author: CompressWP
* Licence: GPLv3
**/

// exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// include the functions file
include_once('functions.php');

// include the config file
include_once('config.php');

// include auto compression if value is set to true
if (get_option('compresswp_auto') == 'true') {
    add_filter('wp_generate_attachment_metadata', 'compresswp_compress_images_automatically', 10, 2);
}

// include bootstrap and style.css, but only on plugin page
if (isset($_GET['page']) && $_GET['page'] == 'compress-media') {
    add_action('admin_enqueue_scripts', 'compresswp_includeScripts');
}

// add compress page to admin menu
add_action('admin_menu', 'compress_page');
function compress_page()
{
    $page_title = 'CompressWP';
    $menu_title = 'CompressWP';
    $capability = 'edit_posts';
    $menu_slug = 'compress-media';
    $function = 'compresswp_pluginPage';
    $icon_url = 'dashicons-fullscreen-exit-alt';
    $position = 24;

    add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position);
}

// register the necessary options
function compresswp_register_options()
{
    add_option('compresswp_licence', '');
    add_option('compresswp_auto', 'false');
}
add_action('admin_init', 'compresswp_register_options');

function compresswp_includeScripts()
{
    wp_enqueue_script('compresswp_bootstrap', plugin_dir_url(__FILE__) . 'assets/js/bootstrap.min.js');
    wp_enqueue_style('compresswp_bootstrap', plugin_dir_url(__FILE__) . 'assets/css/bootstrap.min.css');
    wp_enqueue_style('compresswp_style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('compresswp_javascript', plugin_dir_url(__FILE__) . 'assets/js/compress.js');
    wp_localize_script('compresswp_javascript', 'compresswp', array('ajax_url' => admin_url('admin-ajax.php')));
}

// add the ajax callback (function in functions.php)
add_action('wp_ajax_compresswp_ajax', 'compresswp_ajax');

// global variables
$uncompressed_images = compresswp_updateTodoList(); // get all uncompressed images into array

// display the content page in admin area
function compresswp_pluginPage()
{
    echo '<div class="container-fluid page">';

    if (isset($_GET['subpage'])) {
        if ($_GET['subpage'] == 'bulk-optimize') {
            $page = 'bulk-compress';
        }
    } else {
        $page = 'main';
    }

    include_once('views/'.$page.'.php');

    echo '</div>';
}

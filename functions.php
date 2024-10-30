<?php

function compresswp_showNotifications() {
    if (isset($_GET['error']) && $_GET['error'] == 'licencemissing') {
        echo '<div class="alert alert-warning"><strong>You need to generate a free licence to compress</strong><br/>In order to connect your website with CompressWP API, you will need to <a href="https://compresswp.com/generate-licence" target="_blank">generate a licence key (it is free)</a>.</div>';
    }
}

function compresswp_formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

function compresswp_licenceExists($licence)
{
    global $app;
    global $api_version;

    if(empty($licence)) return false;

    $response = wp_remote_get($app . '/api/' . $api_version . '/checklicence/' . $licence);
    $response = json_decode(wp_remote_retrieve_body($response), true);

    if ($response['licence_exists'] == true) {
        return true;
    } else {
        return false;
    }
}

function compresswp_calculateSaved($before, $after)
{
    // $before is original size
    // $after is size after compression

    $saved = $before - $after;

    if ($saved > 0) {
        return round(($saved/$before)*100);
    }
    return 0;
}

function compresswp_uploadAndReplaceImage($url, $path)
{   
    $request = wp_remote_get($url);
    $img = wp_remote_retrieve_body($request);
    return file_put_contents($path, $img);
}

function compresswp_getOtherSizes($id)
{
    // this gets the array of the main image, which includes the other sizes
    $other_sizes = wp_get_attachment_metadata($id);

    // narrow the array
    $other_sizes = $other_sizes['sizes'];

    return $other_sizes;
}

function compresswp_updateTodoList()
{
    global $allowed_filetypes;

    // get all uncompressed into an array
    $query_images_args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => $allowed_filetypes,
        'post_status'    => 'inherit',
        'posts_per_page' => - 1,
          'meta_query' => array(
            array(
                 'key' => 'compresswp_compressed',
                 'compare' => 'NOT EXISTS'
            ),
        )
    );

    $query_images = new WP_Query($query_images_args);

    $todo = array();

    foreach ($query_images->posts as $image) {
        array_push($todo, $image->ID);
    }

    return $todo;
}

function compresswp_getTotalDone()
{
    // get all compressed into an array
    $query_images_args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => - 1,
            'meta_query' => array(
            array(
                 'key' => 'compresswp_compressed',
                 'compare' => 'EXISTS'
            ),
        )
    );

    $query_images = new WP_Query($query_images_args);

    return $query_images->found_posts;
}

function compresswp_countAllUnCompressedSizes()
{
    global $uncompressed_images;

    $count = count($uncompressed_images); // main images

    foreach ($uncompressed_images as $image) {
        $count = $count + count(compresswp_getOtherSizes($image));
    }

    return $count;
}

// function for automatic compression after image upload
function compresswp_compress_images_automatically($metadata, $attachment_id)
{
    if (!empty(get_option('compresswp_licence'))) {
        compresswp_compressImage($attachment_id);
    }

    return $metadata;
}

function compresswp_compressImage($id, $metadata = '')
{
    global $app;
    global $api_version;

    $licence = get_option('compresswp_licence');

    // retrieve the uncompressed size
    $img_location = get_attached_file($id);
    $uncompressed_size = filesize($img_location);
    add_post_meta($id, 'uncompressed_size', $uncompressed_size);

    // first compress the main image

    // send curl to compress server
    $args = array(
        'timeout' => 120,
        'sslverify' => false
    );
    
    $response = wp_remote_get($app . '/api/' . $api_version . '/compress/' . $licence. '/' . base64_encode(wp_get_attachment_url($id)), $args);
    $response_code = wp_remote_retrieve_response_code($response); // response code
    
    // if response code is not 200 (success), return error message
    if ($response_code != 200) {
        return 'Server error (1001). Please try again!';
    }
    
    $response = json_decode(wp_remote_retrieve_body($response), true); // gets the response from server

    // check if the compression failed
    if ($response['success'] == false) {
        return $response['message'];
    }

    // only update the image if we truly saved some file size
    if($response['saved_size'] > 0) {
        $replace_image = compresswp_uploadAndReplaceImage($response['compressed_image_url'], get_attached_file($id));
        if(empty($replace_image)) return 'Cannot save compressed image. Please try again!';
    }

    // also compress thumbnail sizes
    if (empty($metadata)) {
        $other_sizes = compresswp_getOtherSizes($id);
    } else {
        $other_sizes = $metadata['sizes'];
    } // we use this after uploading, because cannot access metadata after

    foreach ($other_sizes as $size) {
        $dir = dirname(wp_get_attachment_url($id)) . '/';
        $file_name = $size['file'];
        $path = dirname(get_attached_file($id));

        $response = wp_remote_get($app . '/api/' . $api_version . '/compress/'. $licence . '/' . base64_encode($dir . $file_name), $args);
        $response_code = wp_remote_retrieve_response_code($response); // response code
        
        // if response code is not 200 (success), return error message
        if ($response_code != 200) {
            return 'Server error (1001). Please try again!';
        }
        
        $response = json_decode(wp_remote_retrieve_body($response), true); // gets the response from server

        // check if the compression failed
        if ($response['success'] == false) {
            return $response['message'];
        }  

        // only update the image if we truly saved some file size
        if($response['saved_size'] > 0) {
            $replace_image = compresswp_uploadAndReplaceImage($response['compressed_image_url'], $path . '/' . $file_name);
            if(empty($replace_image)) return 'Cannot save compressed image. Please try again!';
        }
    }

    // update post meta to set it compressed
    add_post_meta($id, 'compresswp_compressed', 'true');

    return true;
}

function compresswp_ajax()
{
    $image_id = sanitize_text_field($_POST['image_id']); // image id from post request

    // compress the image and get the result
    $result = compresswp_compressImage($image_id);

    if ($result === true) {
        // if result is true, it means compression was successful

        // get size before
        $uncompressed_size = get_post_meta($image_id, 'uncompressed_size')[0];
        clearstatcache();
        $current_size = filesize(get_attached_file($image_id));

        echo compresswp_formatSizeUnits($current_size) . ' <strong>(-'.compresswp_calculateSaved($uncompressed_size, $current_size).'%)</strong>';
        exit();
    }

    // if there was problem, echo the problem
    echo $result;

    exit();
}


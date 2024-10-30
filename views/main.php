<?php
// check if we need to activate/deactivate licence before we load the page
compresswp_licenceUpdates();

// check if settings are updated
compresswp_settingsUpdates();

echo '<div class="row">';

echo '<div class="col-md-8">';

echo '<h2>Compress your media library</h2>';

echo '<div class="compress-header"><a class="btn btn-lg btn-success" href="'.menu_page_url('compress-media', false).'&subpage=bulk-optimize">Bulk optimize all images</a></div>';

compresswp_showNotifications();

echo '<hr>';

compresswp_getLibraryAsTable(); // this displays the table of all the media images
echo '</div>';

echo '<div class="col-md-4">';
compresswp_getStatistics(); // this displays the statistics
compresswp_getLicenceSelection(); // this displays the licence selection
compresswp_getOptions();
echo '</div>';

echo '</div>';

// functions

// shows the table
function compresswp_getLibraryAsTable()
{
    global $allowed_filetypes;

    // get licence
    $licence = get_option('compresswp_licence');

    $query_images_args = array(
        'post_type' => 'attachment',
        'post_mime_type' => $allowed_filetypes,
        'post_status' => 'inherit',
        'posts_per_page' => - 1,
    );

    $query_images = new WP_Query($query_images_args);

    $images = array();

    // counters for how much we have saved total
    global $total_size_before;
    global $total_size_now;
    $total_size_before = 0;
    $total_size_now = 0;

    if (empty($query_images->posts)) {
        echo '<p class="lead">Currently there are no images in the media library.</p>';
    } else {
        echo '<table class="table table-striped images"><thead class="thead-light"><tr><th>Image name</th><th>Initial size</th><th>Result</th></tr></thead>';
        echo '<tbody>';
        foreach ($query_images->posts as $image) {

        // check if it has been compressed or not
            $compressed = false;
            $image_meta = get_post_meta($image->ID, 'compresswp_compressed');
            if ($image_meta == true) {
                $compressed = true;
                $uncompressed_size = get_post_meta($image->ID, 'uncompressed_size')[0];
            }

            // get the path
            $img_location = get_attached_file($image->ID);

            // image current size
            $current_size = filesize($img_location);
            if (!$compressed) {
                $uncompressed_size = $current_size;
            } // if not compressed, then it is initial size

            // add to total size for counters
            $total_size_now = $total_size_now + $current_size;
            if ($compressed) {
                $total_size_before = $total_size_before + $uncompressed_size;
            } else {
                $total_size_before = $total_size_before + $current_size;
            }

            echo '<tr>';
            echo '<td>' . basename(wp_get_attachment_url($image->ID)) . ' (+ '.count(compresswp_getOtherSizes($image->ID)).' sizes)</td>';
            echo '<td>'.compresswp_formatSizeUnits($uncompressed_size).'</td>';

            // if not compressed, show button, else say already compressed
            if (!$compressed) {
                // if licence missing, show compress button that will open modal
                if (empty($licence)) {
                    echo '<td><a class="single-compress" href="'.menu_page_url('compress-media', false).'&error=licencemissing">Optimize</a></td>';
                }
                // else it is action button
                else {
                    echo '<td><a class="single-compress single-compress-action" id="img-'.$image->ID.'" href="">Optimize</a></td>';
                }
            } else {
                echo '<td>'.compresswp_formatSizeUnits($current_size).'<strong> (-'.compresswp_calculateSaved($uncompressed_size, $current_size).'%)</strong></td>';
            }
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}

function compresswp_getStatistics()
{
    global $total_size_before;
    global $total_size_now;

    $saved = $total_size_before - $total_size_now;

    $saved_percentage = compresswp_calculateSaved($total_size_before, $total_size_now);

    echo '<div class="menu-box">';
    echo '<h5>Statistics</h5><hr>';

    echo '<p class="lead"><strong>Current library size:</strong> '.compresswp_formatSizeUnits($total_size_now).'</p>';
    echo '<p class="lead success"><strong>You have saved:</strong> '.compresswp_formatSizeUnits($saved).' <strong>('.compresswp_calculateSaved($total_size_now + $saved, $total_size_now).'%)</strong></p>';
    echo '</div>';
}

function compresswp_licenceUpdates()
{
    if (isset($_GET['action']) && $_GET['action'] == 'removelicence') {
        update_option('compresswp_licence', '');
    }

    if (isset($_POST['activate'])) {
        // check if licence is valid
        $licence = sanitize_text_field($_POST['licence']);

        if (compresswp_licenceExists($licence)) {
            update_option('compresswp_licence', $licence);
        } else {
            $GLOBALS['licence_error'] = 'This licence is not valid!';
        }
    }
}

function compresswp_settingsUpdates()
{
    if (isset($_POST['savesettings'])) {
        // checkbox - compress automatically
        if (isset($_POST['compresswp_auto'])) {
            update_option('compresswp_auto', 'true');
        } else {
            update_option('compresswp_auto', 'false');
        }
    }
}

function compresswp_getLicenceSelection()
{
    global $app;

    $licence = get_option('compresswp_licence');

    // check if licence still exists
    if (!compresswp_licenceExists($licence)) {
        update_option('compresswp_licence', ''); // set the option as empty
        $licence = ''; // set no licence
    }

    echo '<div class="menu-box">';
    echo '<h5>Licence key</h5><hr>';

    if (isset($GLOBALS['licence_error'])) {
        echo '<div class="alert alert-danger">'.$GLOBALS['licence_error'].'</div>';
    }

    if (empty($licence)) {
        echo '<p class="small">If you do not have a licence key, <a target="_blank" href="https://compresswp.com/generate-licence">click here to get one (it is free)</a>.</p>';
        echo '<form method="post">';
        echo '<div class="input-group mb-3">';
        echo '<input type="text" class="form-control" name="licence" placeholder="Enter licence key" aria-label="Enter licence key" aria-describedby="button-activate">';
        echo '<div class="input-group-append">';
        echo '<button class="btn btn-success" type="submit" name="activate" id="button-activate">Activate licence</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    } else {
        echo '<p class="lead success">You have a valid licence key.</p>';
        echo '<p class="small"><a href="'.menu_page_url('compress-media', false).'&action=removelicence">Remove licence</a></p>';
    }

    echo '</div>';
}

function compresswp_getOptions()
{
    echo '<div class="menu-box">';
    echo '<h5>Settings</h5><hr>';

    if (isset($_POST['savesettings'])) {
        echo '<div class="alert alert-success">Settings have been updated!</div>';
    }

    echo '<form method="post">';

    echo '<div class="form-group">';
    echo '<label for="formGroupExampleInput"><strong>Optimize images on upload</strong></label>';
    echo '<div class="checkbox"><label><input type="checkbox" name="compresswp_auto" '.((get_option('compresswp_auto') == 'true')?'checked="checked"':"").'>Enable automatic compression</label></div>';
    echo '</div>';

    echo '<button class="btn btn-success" type="submit" name="savesettings" id="button-activate">Save settings</button>';
    echo '</form>';

    echo '</div>';
}

<?php

// get the licence info
$licence = get_option('compresswp_licence');

echo '<div class="row">';
echo '<div class="col-md-12">';

echo '<h2>Bulk optimize</h2>';
echo '<p>On this page are listed all of the images on your Wordpress that are <strong>uncompressed</strong>. You can click on the "Start bulk optimize" button to start automatic bulk compression.</p>';

compresswp_showNotifications();

echo '<div class="row compress-header">';

echo '<div class="col-md-3">';

  // start/stop bulk compress button
  if (empty($licence)) {
      echo '<td><a class="btn btn-lg btn-success" href="'.menu_page_url('compress-media', false).'&subpage=bulk-optimize&error=licencemissing">Start bulk optimize</a></td>';
  }
  // else it is action button
  else {
      echo '<a class="btn btn-lg btn-success bulk-compress" href="">Start bulk optimize</a><br>';
  }

echo '</div>';

echo '<div class="col-md-3">';
echo '<p class="lead">Uncompressed sizes</p>';
echo '<h3 id="uncompressed">'.compresswp_countAllUnCompressedSizes().'</h3>';
echo '</div>';

echo '</div>';

echo '<div class="row">';
echo '<div class="col-md-12">';
  // table of all uncompressed images
  compresswp_getAllUnCompressedAsTable();
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

// functions
function compresswp_getAllUnCompressedAsTable()
{
    global $uncompressed_images;

    echo '<table class="table table-striped images bulk-list"><thead class="thead-light"><tr><th>Image name</th><th>Initial size</th><th>Result</th></tr></thead>';
    echo '<tbody>';
    foreach ($uncompressed_images as $image) {

    // get the path
        $img_location = get_attached_file($image);

        // image current size
        $current_size = filesize($img_location);

        // count other sizes
        $other_sizes_count = count(compresswp_getOtherSizes($image));

        echo '<tr class="item not-compressed" id="img-'.$image.'" total_sizes="'.($other_sizes_count + 1).'">';

        echo '<td>' . basename(wp_get_attachment_url($image)) . ' (+ '.$other_sizes_count.' sizes)</td>';
        echo '<td>' . compresswp_formatSizeUnits($current_size) . '</td>';

        // if not compressed, show button, else say already compressed
        echo '<td class="status">Uncompressed</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

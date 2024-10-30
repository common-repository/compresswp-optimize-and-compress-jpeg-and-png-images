jQuery(document).ready(function($) {

  // SINGLE file compression - when compress button is clicked
  $(".single-compress-action").click(function(e) {

    var element = $(this).parent();

    e.preventDefault(); // cancel default event
    var image_id = this.id.slice(4); // cut the "img-" from the beginning of id

    // replace with a spinner
    element.html('<div class="spinner-grow text-success" role="status"><span class="sr-only">Loading...</span></div>');

    var data = {
      'action': 'compresswp_ajax',
      'image_id': image_id,
    };

    // send ajax request
    $.ajax({
        context: element,
        type: 'POST',
        data: data,
        url: compresswp.ajax_url,
        success: function(data) {
          $(this).html(data);
        }
    });
  });

  var currentlyCompressing = 0;
  var compressing = false;

  // BULK file compression - when compress all button is clicked
  $(".bulk-compress").click(function(e) {
    e.preventDefault();

    // if currently not bulk compressing
    if(compressing == false) {
      $(this).text('Stop compression'); // change button text to compressing
      $(this).toggleClass('btn-success btn-warning'); // change class to btn-warning
      $(this).blur(); // remove focus from button

      compressing = true;

      sendToBulkCompress(3);
    }
    // if currently is bulk compressing
    else if (compressing == true) {

      compressing = false;

      $(this).blur(); // remove focus from button
      $(this).text('Stopping compression...'); // change button text to stopping compression
    }

    // set interval waits until the current ajax compressions have been completed
    var that = this; // to access variable in setInterval
    var interval = setInterval(function() {
      if (currentlyCompressing == 0) {
        clearInterval(interval);

        if($(that).hasClass('btn-warning')) {
          $(that).toggleClass('btn-warning btn-success');
          $(that).text('Start bulk optimize'); // change button text to start bulk optimize
          compressing = false;
        }
      }
    }, 5000);

  });

  function sendToBulkCompress(count) {
    
    if(compressing == false) return; // if the compression has been stopped, do not start the next compression

    // send first ones to compression
    $('.images.bulk-list tr.item.not-compressed:lt(' + count + ')').each(function () {

      var element = $(this).find(".status");

      // add compressed class to element
      $(this).toggleClass('not-compressed is-compressed');

      var image_id = this.id.slice(4); // cut the "img-" from the beginning of id

      // add +1 to currently compressing
      currentlyCompressing += 1;

      // replace with a spinner
      element.html('<div class="spinner-grow text-success" role="status"><span class="sr-only">Loading...</span></div>');

      var data = {
        'action': 'compresswp_ajax',
        'image_id': image_id,
      };

      // send ajax request
      $.ajax({
          context: element,
          type: 'POST',
          data: data,
          url: compresswp.ajax_url,
          success: function(data) {
            $(this).html(data);

            // update todo variable
            $('#uncompressed').text($('#uncompressed').text() - $(this).parent().attr('total_sizes'));

            currentlyCompressing -= 1;

            sendToBulkCompress(1);
          }
      });

    });

  }

});
jQuery(document).ready(function($) {
    // Media uploader for location image
    $('#upload_image_button').click(function(e) {
        e.preventDefault();
        
        var image_frame;
        
        if (image_frame) {
            image_frame.open();
            return;
        }
        
        // Define image_frame as wp.media object
        image_frame = wp.media({
            title: 'Chọn một hình ảnh',
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        image_frame.on('select', function() {
            // On select, get the selected attachment and set it to the input field
            var attachment = image_frame.state().get('selection').first().toJSON();
            $('#location_image').val(attachment.url);
            
            // Update preview
            $('#image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; margin-top: 10px;">');
        });
        
        // Open media uploader dialog
        image_frame.open();
    });
});
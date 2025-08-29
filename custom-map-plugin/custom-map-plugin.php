<?php
/*
Plugin Name: Custom Map Plugin
Description: A plugin to display maps based on latitude and longitude without using Google Maps API.
Version: 1.0
Author: Your Name
*/

// Register activation hook to create database table
register_activation_hook(__FILE__, 'custom_map_plugin_activate');

function custom_map_plugin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_map_locations';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        address text NOT NULL,
        phone varchar(20) NOT NULL,
        latitude decimal(10,7) NOT NULL,
        longitude decimal(10,7) NOT NULL,
        map_link text NOT NULL,
        image_url text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add sample data if table is empty
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    if ($count == 0) {
        $default_image_url = plugins_url('images/placeholder.jpg', __FILE__);
        
        $wpdb->insert(
            $table_name,
            array(
                'name' => 'Tên cơ sở',
                'address' => 'Địa chỉ',
                'phone' => '0987654321',
                'latitude' => 21.1122788,
                'longitude' => 105.8287182,
                'map_link' => 'https://goo.gl/maps/YourCustomLink1',
                'image_url' => $default_image_url
            )
        );
        
        $wpdb->insert(
            $table_name,
            array(
                'name' => 'Tên cơ sở 2',
                'address' => 'Địa chỉ',
                'phone' => '0987654322',
                'latitude' => 21.1215836,
                'longitude' => 105.7458173,
                'map_link' => 'https://goo.gl/maps/YourCustomLink2',
                'image_url' => $default_image_url
            )
        );
    }
    
    // Create images directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $plugin_images_dir = plugin_dir_path(__FILE__) . 'images';
    
    if (!file_exists($plugin_images_dir)) {
        wp_mkdir_p($plugin_images_dir);
    }
    
    // Create placeholder image if it doesn't exist
    $placeholder_path = $plugin_images_dir . '/placeholder.jpg';
    if (!file_exists($placeholder_path)) {
        // Generate a simple placeholder image or copy from assets
        $placeholder_url = 'https://via.placeholder.com/150';
        $image_data = file_get_contents($placeholder_url);
        if ($image_data) {
            file_put_contents($placeholder_path, $image_data);
        }
    }
}

// Register deactivation hook (optional)
register_deactivation_hook(__FILE__, 'custom_map_plugin_deactivate');

function custom_map_plugin_deactivate() {
    // Optional: Add deactivation code here
    // Note: We're not dropping the table on deactivation to preserve data
}

// Enqueue scripts and styles
function custom_map_plugin_enqueue_scripts() {
    wp_enqueue_style('custom-map-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('custom-map-script', plugins_url('script.js', __FILE__), array('jquery'), '1.0', true);
    
    // Enqueue Font Awesome for the direction icon
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css');
    
    // Admin scripts
    if (is_admin()) {
        wp_enqueue_media();
        wp_enqueue_script('custom-map-admin', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'custom_map_plugin_enqueue_scripts');
add_action('admin_enqueue_scripts', 'custom_map_plugin_enqueue_scripts');

// Shortcode to display the map
function custom_map_plugin_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_map_locations';
    
    // Get all locations from database
    $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
    
    // If no locations, return message
    if (empty($locations)) {
        return '<p>Không có địa điểm nào để hiển thị. Vui lòng thêm địa điểm trong trang quản trị.</p>';
    }
    
    ob_start();
    ?>
    <div class="map-container">
        <div class="location-list">
            <?php foreach ($locations as $location) : ?>
                <div class="location-item" 
                    data-lat="<?php echo esc_attr($location->latitude); ?>" 
                    data-lng="<?php echo esc_attr($location->longitude); ?>"
                    data-name="<?php echo esc_attr($location->name); ?>"
                    data-address="<?php echo esc_attr($location->address); ?>"
                    data-phone="<?php echo esc_attr($location->phone); ?>"
                    data-map-link="<?php echo esc_url($location->map_link); ?>">
                    
                    <div class="location-image">
                        <img src="<?php echo esc_url($location->image_url); ?>" alt="<?php echo esc_attr($location->name); ?>">
                    </div>
                    <div class="location-info">
                        <h3 class="location-name"><?php echo esc_html($location->name); ?></h3>
                        <p class="location-address"><i class="fas fa-map-marker-alt"></i> <?php echo esc_html($location->address); ?></p>
                        <p class="location-phone"><i class="fas fa-phone"></i> <a href="tel:<?php echo esc_attr($location->phone); ?>"><?php echo esc_html($location->phone); ?></a></p>
                        <a href="<?php echo esc_url($location->map_link); ?>" target="_blank" class="directions-btn">
                            <i class="fas fa-directions"></i> Chỉ đường
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="map"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_map', 'custom_map_plugin_shortcode');

// Admin menu setup
function custom_map_plugin_admin_menu() {
    add_menu_page(
        'Custom Map Settings',
        'Custom Map',
        'manage_options',
        'custom-map-settings',
        'custom_map_plugin_settings_page',
        'dashicons-location-alt',
        30
    );
}
add_action('admin_menu', 'custom_map_plugin_admin_menu');

// Admin settings page
function custom_map_plugin_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_map_locations';
    
    // Handle location form submission
    if (isset($_POST['submit_location']) && check_admin_referer('custom_map_location_action', 'custom_map_location_nonce')) {
        $id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $name = sanitize_text_field($_POST['location_name']);
        $address = sanitize_textarea_field($_POST['location_address']);
        $phone = sanitize_text_field($_POST['location_phone']);
        $latitude = floatval($_POST['location_latitude']);
        $longitude = floatval($_POST['location_longitude']);
        $map_link = esc_url_raw($_POST['location_map_link']);
        $image_url = esc_url_raw($_POST['location_image']);
        
        $data = array(
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'map_link' => $map_link,
            'image_url' => $image_url
        );
        
        if ($id > 0) {
            // Update existing location
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $id)
            );
            echo '<div class="notice notice-success is-dismissible"><p>Địa điểm đã được cập nhật.</p></div>';
        } else {
            // Add new location
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success is-dismissible"><p>Địa điểm mới đã được thêm.</p></div>';
        }
    }
    
    // Handle location deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_location')) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id));
        echo '<div class="notice notice-success is-dismissible"><p>Địa điểm đã được xóa.</p></div>';
    }
    
    // Get location for editing if requested
    $edit_id = isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) ? intval($_GET['id']) : 0;
    $location_to_edit = null;
    
    if ($edit_id > 0) {
        $location_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
    }
    
    // Get all locations
    $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
    
    ?>
    <div class="wrap">
        <h1><?php echo $edit_id > 0 ? 'Chỉnh sửa địa điểm' : 'Thêm địa điểm mới'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('custom_map_location_action', 'custom_map_location_nonce'); ?>
            
            <?php if ($edit_id > 0) : ?>
                <input type="hidden" name="location_id" value="<?php echo $edit_id; ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="location_name">Tên địa điểm</label></th>
                    <td>
                        <input type="text" name="location_name" id="location_name" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_attr($location_to_edit->name) : ''; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_address">Địa chỉ</label></th>
                    <td>
                        <textarea name="location_address" id="location_address" class="large-text" rows="3" required><?php 
                            echo $location_to_edit ? esc_textarea($location_to_edit->address) : ''; 
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_phone">Số điện thoại</label></th>
                    <td>
                        <input type="text" name="location_phone" id="location_phone" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_attr($location_to_edit->phone) : ''; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_latitude">Vĩ độ (Latitude)</label></th>
                    <td>
                        <input type="text" name="location_latitude" id="location_latitude" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_attr($location_to_edit->latitude) : ''; ?>" required>
                        <p class="description">Ví dụ: 21.1122788</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_longitude">Kinh độ (Longitude)</label></th>
                    <td>
                        <input type="text" name="location_longitude" id="location_longitude" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_attr($location_to_edit->longitude) : ''; ?>" required>
                        <p class="description">Ví dụ: 105.8287182</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_map_link">Link Google Maps</label></th>
                    <td>
                        <input type="url" name="location_map_link" id="location_map_link" class="large-text" 
                               value="<?php echo $location_to_edit ? esc_url($location_to_edit->map_link) : ''; ?>" required>
                        <p class="description">Link trực tiếp đến Google Maps (Ví dụ: https://goo.gl/maps/abc123)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_image">Hình ảnh</label></th>
                    <td>
                        <input type="text" name="location_image" id="location_image" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_url($location_to_edit->image_url) : ''; ?>" required>
                        <input type="button" id="upload_image_button" class="button" value="Chọn hình ảnh">
                        <div id="image_preview">
                            <?php if ($location_to_edit && $location_to_edit->image_url) : ?>
                                <img src="<?php echo esc_url($location_to_edit->image_url); ?>" style="max
                                <img src="<?php echo esc_url($location_to_edit->image_url); ?>" style="max-width: 200px; margin-top: 10px;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit_location" id="submit" class="button button-primary" 
                       value="<?php echo $edit_id > 0 ? 'Cập nhật địa điểm' : 'Thêm địa điểm mới'; ?>">
                <?php if ($edit_id > 0) : ?>
                    <a href="<?php echo admin_url('admin.php?page=custom-map-settings'); ?>" class="button">Hủy</a>
                <?php endif; ?>
            </p>
        </form>
        
        <?php if (!$edit_id) : // Only show location list when not editing ?>
            <hr>
            
            <h2>Danh sách địa điểm</h2>
            
            <?php if (empty($locations)) : ?>
                <p>Chưa có địa điểm nào.</p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Tên</th>
                            <th>Địa chỉ</th>
                            <th>Điện thoại</th>
                            <th>Tọa độ</th>
                            <th>Hình ảnh</th>
                            <th>Tùy chọn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location) : ?>
                            <tr>
                                <td><?php echo esc_html($location->name); ?></td>
                                <td><?php echo esc_html($location->address); ?></td>
                                <td><?php echo esc_html($location->phone); ?></td>
                                <td><?php echo esc_html($location->latitude); ?>, <?php echo esc_html($location->longitude); ?></td>
                                <td>
                                    <?php if ($location->image_url) : ?>
                                        <img src="<?php echo esc_url($location->image_url); ?>" style="max-width: 50px; max-height: 50px;">
                                    <?php else : ?>
                                        <em>Không có ảnh</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=custom-map-settings&action=edit&id=' . $location->id); ?>" class="button button-small">Sửa</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=custom-map-settings&action=delete&id=' . $location->id), 'delete_location'); ?>" class="button button-small button-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa địa điểm này?');">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 20px;">
        <h3>Hướng dẫn sử dụng</h3>
        <p>Để hiển thị bản đồ và danh sách địa điểm trên trang web của bạn, hãy sử dụng shortcode sau:</p>
        <code>[custom_map]</code>
        <p>Bạn có thể thêm shortcode này vào bất kỳ trang hoặc bài viết nào.</p>
    </div>
<?php
}
?>
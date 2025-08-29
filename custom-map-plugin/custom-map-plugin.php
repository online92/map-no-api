<?php
/*
Plugin Name: Custom Map Plugin
Description: A plugin to display maps based on latitude and longitude without using Google Maps API.
Version: 1.1
Author: Your Name
*/

// Ngăn chặn truy cập trực tiếp
if (!defined('ABSPATH')) {
    exit;
}

// Register activation hook
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

    // Thêm các tùy chọn mặc định
    add_option('custom_map_name_color', '#D81B60');
    add_option('custom_map_address_color', '#1976D2');
    add_option('custom_map_button_color', '#FF5722');
    add_option('custom_map_layout', 'list-left');
}

// Enqueue scripts and styles
function custom_map_plugin_enqueue_scripts() {
    wp_enqueue_style('custom-map-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('custom-map-script', plugins_url('script.js', __FILE__), [], '1.1', true);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css');

    if (is_admin()) {
        wp_enqueue_media();
        wp_enqueue_script('custom-map-admin', plugins_url('admin.js', __FILE__), ['jquery', 'wp-color-picker'], '1.1', true);
        wp_enqueue_style('wp-color-picker');
    }
}
add_action('wp_enqueue_scripts', 'custom_map_plugin_enqueue_scripts');
add_action('admin_enqueue_scripts', 'custom_map_plugin_enqueue_scripts');

// Shortcode to display the map
function custom_map_plugin_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_map_locations';
    $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");

    if (empty($locations)) {
        return '<p>Không có địa điểm nào để hiển thị. Vui lòng thêm địa điểm trong trang quản trị.</p>';
    }

    // Lấy các tùy chọn màu sắc và bố cục
    $name_color = get_option('custom_map_name_color', '#D81B60');
    $address_color = get_option('custom_map_address_color', '#1976D2');
    $button_color = get_option('custom_map_button_color', '#FF5722');
    $layout_class = get_option('custom_map_layout', 'list-left');

    ob_start();
    ?>
    <style>
        :root {
            --location-name-color: <?php echo esc_attr($name_color); ?>;
            --location-address-color: <?php echo esc_attr($address_color); ?>;
            --directions-btn-bg-color: <?php echo esc_attr($button_color); ?>;
        }
    </style>
    <div class="map-container <?php echo esc_attr($layout_class); ?>">
        <div class="location-list">
            <?php foreach ($locations as $location) : ?>
                <div class="location-item" 
                    data-lat="<?php echo esc_attr($location->latitude); ?>" 
                    data-lng="<?php echo esc_attr($location->longitude); ?>"
                    data-name="<?php echo esc_attr($location->name); ?>">
                    
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
        'Quản lý Bản đồ',
        'Custom Map',
        'manage_options',
        'custom-map-settings',
        'custom_map_plugin_settings_page',
        'dashicons-location-alt',
        30
    );
}
add_action('admin_menu', 'custom_map_plugin_admin_menu');

// Register settings
function custom_map_plugin_register_settings() {
    register_setting('custom_map_options_group', 'custom_map_name_color');
    register_setting('custom_map_options_group', 'custom_map_address_color');
    register_setting('custom_map_options_group', 'custom_map_button_color');
    register_setting('custom_map_options_group', 'custom_map_layout');
}
add_action('admin_init', 'custom_map_plugin_register_settings');

// Admin settings page
function custom_map_plugin_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_map_locations';

    // Xử lý thêm/sửa địa điểm
    if (isset($_POST['submit_location']) && check_admin_referer('custom_map_location_action', 'custom_map_location_nonce')) {
        $id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $data = [
            'name'      => sanitize_text_field($_POST['location_name']),
            'address'   => sanitize_textarea_field($_POST['location_address']),
            'phone'     => sanitize_text_field($_POST['location_phone']),
            'latitude'  => floatval($_POST['location_latitude']),
            'longitude' => floatval($_POST['location_longitude']),
            'map_link'  => esc_url_raw($_POST['location_map_link']),
            'image_url' => esc_url_raw($_POST['location_image']),
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
            echo '<div class="notice notice-success is-dismissible"><p>Địa điểm đã được cập nhật.</p></div>';
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success is-dismissible"><p>Địa điểm mới đã được thêm.</p></div>';
        }
    }

    // Xử lý xóa địa điểm
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_location')) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo '<div class="notice notice-success is-dismissible"><p>Địa điểm đã được xóa.</p></div>';
    }

    $edit_id = isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']) ? intval($_GET['id']) : 0;
    $location_to_edit = $edit_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id)) : null;
    $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC");
    ?>
    <div class="wrap">
        <h1>Quản lý Bản đồ & Cài đặt</h1>

        <h2>Cài đặt hiển thị</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('custom_map_options_group');
            do_settings_sections('custom_map_options_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Màu chữ Tên địa điểm</th>
                    <td><input type="text" name="custom_map_name_color" value="<?php echo esc_attr(get_option('custom_map_name_color')); ?>" class="color-picker" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Màu chữ Địa chỉ</th>
                    <td><input type="text" name="custom_map_address_color" value="<?php echo esc_attr(get_option('custom_map_address_color')); ?>" class="color-picker" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Màu nền nút "Chỉ đường"</th>
                    <td><input type="text" name="custom_map_button_color" value="<?php echo esc_attr(get_option('custom_map_button_color')); ?>" class="color-picker" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Vị trí bản đồ</th>
                    <td>
                        <select name="custom_map_layout">
                            <option value="list-left" <?php selected(get_option('custom_map_layout'), 'list-left'); ?>>Bản đồ bên phải, Danh sách bên trái</option>
                            <option value="list-right" <?php selected(get_option('custom_map_layout'), 'list-right'); ?>>Bản đồ bên trái, Danh sách bên phải</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>

        <h2><?php echo $edit_id > 0 ? 'Chỉnh sửa địa điểm' : 'Thêm địa điểm mới'; ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('custom_map_location_action', 'custom_map_location_nonce'); ?>
            <?php if ($edit_id > 0) : ?><input type="hidden" name="location_id" value="<?php echo $edit_id; ?>"><?php endif; ?>
            
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
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_longitude">Kinh độ (Longitude)</label></th>
                    <td>
                        <input type="text" name="location_longitude" id="location_longitude" class="regular-text" 
                               value="<?php echo $location_to_edit ? esc_attr($location_to_edit->longitude) : ''; ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_map_link">Link Google Maps</label></th>
                    <td>
                        <input type="url" name="location_map_link" id="location_map_link" class="large-text" 
                               value="<?php echo $location_to_edit ? esc_url($location_to_edit->map_link) : ''; ?>" required>
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
                                <img src="<?php echo esc_url($location_to_edit->image_url); ?>" style="max-width: 200px; margin-top: 10px;">
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit_location" class="button button-primary" value="<?php echo $edit_id > 0 ? 'Cập nhật địa điểm' : 'Thêm địa điểm mới'; ?>">
                <?php if ($edit_id > 0) : ?><a href="<?php echo admin_url('admin.php?page=custom-map-settings'); ?>" class="button">Hủy</a><?php endif; ?>
            </p>
        </form>

        <?php if (!$edit_id) : ?>
            <hr>
            <h2>Danh sách địa điểm</h2>
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
         <div style="margin-top: 20px;">
            <h3>Hướng dẫn sử dụng</h3>
            <p>Sử dụng shortcode <code>[custom_map]</code> để hiển thị bản đồ.</p>
        </div>
    </div>
    <?php
}
?>
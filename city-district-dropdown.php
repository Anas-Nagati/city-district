<?php
/**
 * Plugin Name: City District Dropdown
 * Description: Replaces city and district text fields with searchable dropdowns in checkout
 * Version: 1.1
 * Author: Anas Nagati
 * Update: Loads all data upfront for better performance
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class City_District_Dropdown {

    public function __construct() {
        // Create database tables on plugin activation
        register_activation_hook(__FILE__, array($this, 'create_tables'));

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Replace checkout fields
        add_filter('woocommerce_checkout_fields', array($this, 'modify_checkout_fields'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Ajax handlers for admin
        add_action('wp_ajax_add_city', array($this, 'add_city_ajax'));
        add_action('wp_ajax_add_district', array($this, 'add_district_ajax'));
        add_action('wp_ajax_delete_location', array($this, 'delete_location_ajax'));
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $locations_table = $wpdb->prefix . 'city_district_locations';

        $sql = "CREATE TABLE $locations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            parent_id mediumint(9) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'City & District Manager',
            'Cities & Districts',
            'manage_options',
            'city-district-manager',
            array($this, 'admin_page'),
            'dashicons-location',
            30
        );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'city_district_locations';

        // Get all cities
        $cities = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE parent_id = 0 ORDER BY name ASC"
        );

        // Get all districts with their city info
        $districts = $wpdb->get_results(
            "SELECT d.*, c.name as city_name
            FROM $table_name d
            JOIN $table_name c ON d.parent_id = c.id
            WHERE d.parent_id > 0
            ORDER BY c.name ASC, d.name ASC"
        );

        ?>
        <div class="wrap">
            <h1>City & District Manager</h1>

            <div class="card">
                <h2>Add New City</h2>
                <form id="add-city-form">
                    <input type="text" id="city-name" placeholder="City Name" required>
                    <button type="submit" class="button button-primary">Add City</button>
                </form>
                <div id="city-message"></div>
            </div>

            <div class="card">
                <h2>Add New District</h2>
                <form id="add-district-form">
                    <select id="city-parent" required>
                        <option value="">Select City</option>
                        <?php foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr($city->id); ?>"><?php echo esc_html($city->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="district-name" placeholder="District Name" required>
                    <button type="submit" class="button button-primary">Add District</button>
                </form>
                <div id="district-message"></div>
            </div>

            <h2>Cities</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>City Name</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($cities as $city) : ?>
                    <tr>
                        <td><?php echo esc_html($city->id); ?></td>
                        <td><?php echo esc_html($city->name); ?></td>
                        <td>
                            <button class="button delete-location" data-id="<?php echo esc_attr($city->id); ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Districts</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>District Name</th>
                    <th>City</th>
                    <th>City ID</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($districts as $district) : ?>
                    <tr>
                        <td><?php echo esc_html($district->id); ?></td>
                        <td><?php echo esc_html($district->name); ?></td>
                        <td><?php echo esc_html($district->city_name); ?></td>
                        <td><?php echo esc_html($district->parent_id); ?></td>
                        <td>
                            <button class="button delete-location" data-id="<?php echo esc_attr($district->id); ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if ($hook != 'toplevel_page_city-district-manager') {
            return;
        }

        wp_enqueue_script('city-district-admin-js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.0', true);
        wp_localize_script('city-district-admin-js', 'city_district_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('city_district_nonce')
        ));
    }

    /**
     * Ajax handler to add city
     */
    public function add_city_ajax() {
        check_ajax_referer('city_district_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $city_name = sanitize_text_field($_POST['city_name']);

        if (empty($city_name)) {
            wp_send_json_error('City name is required');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'city_district_locations';

        // Check if city already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE name = %s AND parent_id = 0",
            $city_name
        ));

        if ($exists) {
            wp_send_json_error('City already exists');
        }

        // Insert city
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $city_name,
                'parent_id' => 0
            )
        );

        if ($result) {
            wp_send_json_success(array(
                'id' => $wpdb->insert_id,
                'name' => $city_name
            ));
        } else {
            wp_send_json_error('Failed to add city');
        }
    }

    /**
     * Ajax handler to add district
     */
    public function add_district_ajax() {
        check_ajax_referer('city_district_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $district_name = sanitize_text_field($_POST['district_name']);
        $city_id = intval($_POST['city_id']);

        if (empty($district_name) || empty($city_id)) {
            wp_send_json_error('District name and city are required');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'city_district_locations';

        // Check if city exists
        $city = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND parent_id = 0",
            $city_id
        ));

        if (!$city) {
            wp_send_json_error('City not found');
        }

        // Check if district already exists in this city
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE name = %s AND parent_id = %d",
            $district_name, $city_id
        ));

        if ($exists) {
            wp_send_json_error('District already exists in this city');
        }

        // Insert district
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $district_name,
                'parent_id' => $city_id
            )
        );

        if ($result) {
            wp_send_json_success(array(
                'id' => $wpdb->insert_id,
                'name' => $district_name,
                'city_name' => $city->name,
                'city_id' => $city_id
            ));
        } else {
            wp_send_json_error('Failed to add district');
        }
    }

    /**
     * Ajax handler to delete location
     */
    public function delete_location_ajax() {
        check_ajax_referer('city_district_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $location_id = intval($_POST['location_id']);

        if (empty($location_id)) {
            wp_send_json_error('Invalid location ID');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'city_district_locations';

        // Check if it's a city with districts
        $districts_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE parent_id = %d",
            $location_id
        ));

        if ($districts_count > 0) {
            wp_send_json_error('Cannot delete city that has districts. Delete districts first.');
        }

        // Delete location
        $result = $wpdb->delete(
            $table_name,
            array('id' => $location_id)
        );

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete location');
        }
    }

    /**
     * Modify checkout fields
     */
    public function modify_checkout_fields($fields) {
        // Replace city field with select
        if (isset($fields['billing']['billing_city'])) {
            $fields['billing']['billing_city']['type'] = 'select';
            $fields['billing']['billing_city']['options'] = array('' => __('إختر مدينة', 'woocommerce'));
            $fields['billing']['billing_city']['class'] = array('city-select');
            $fields['billing']['billing_city']['input_class'] = array('city-select');
            $fields['billing']['billing_city']['custom_attributes'] = array('data-placeholder' => __('إختر مدينة', 'woocommerce'));
        }

        // Replace district field with select
        if (isset($fields['billing']['billing_address_1'])) {
            $fields['billing']['billing_address_1']['type'] = 'select';
            $fields['billing']['billing_address_1']['options'] = array('' => __('إختر حي', 'woocommerce'));
            $fields['billing']['billing_address_1']['class'] = array('district-select');
            $fields['billing']['billing_address_1']['input_class'] = array('district-select');
            $fields['billing']['billing_address_1']['custom_attributes'] = array('data-placeholder' => __('إختر حي', 'woocommerce'));
        }

        // Do the same for shipping fields
        if (isset($fields['shipping']['shipping_city'])) {
            $fields['shipping']['shipping_city']['type'] = 'select';
            $fields['shipping']['shipping_city']['options'] = array('' => __('إختر مدينة', 'woocommerce'));
            $fields['shipping']['shipping_city']['class'] = array('city-select');
            $fields['shipping']['shipping_city']['input_class'] = array('city-select');
            $fields['shipping']['shipping_city']['custom_attributes'] = array('data-placeholder' => __('إختر مدينة', 'woocommerce'));
        }

        if (isset($fields['shipping']['shipping_address_1'])) {
            $fields['shipping']['shipping_address_1']['type'] = 'select';
            $fields['shipping']['shipping_address_1']['options'] = array('' => __('إختر منطقة', 'woocommerce'));
            $fields['shipping']['shipping_address_1']['class'] = array('district-select');
            $fields['shipping']['shipping_address_1']['input_class'] = array('district-select');
            $fields['shipping']['shipping_address_1']['custom_attributes'] = array('data-placeholder' => __('إختر منطقة', 'woocommerce'));
        }

        return $fields;
    }


    /**
     * Get all locations data (cities with their districts)
     */
    private function get_all_locations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'city_district_locations';

        // Get all cities
        $cities = $wpdb->get_results(
            "SELECT id, name FROM $table_name WHERE parent_id = 0 ORDER BY name ASC"
        );

        // Get all districts
        $districts = $wpdb->get_results(
            "SELECT id, name, parent_id FROM $table_name WHERE parent_id > 0 ORDER BY name ASC"
        );

        // Organize districts by city
        $districts_by_city = array();
        foreach ($districts as $district) {
            if (!isset($districts_by_city[$district->parent_id])) {
                $districts_by_city[$district->parent_id] = array();
            }
            $districts_by_city[$district->parent_id][] = array(
                'id' => $district->id,
                'name' => $district->name
            );
        }

        return array(
            'cities' => $cities,
            'districts_by_city' => $districts_by_city
        );
    }


    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }

        // Enqueue Select2
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);

        // Enqueue our custom script and styles
        wp_enqueue_script('city-district-js', plugin_dir_url(__FILE__) . 'city-district.js', array('jquery', 'select2'), '1.1', true);
        wp_enqueue_style('city-district-css', plugin_dir_url(__FILE__) . 'city-district.css', array(), '1.1');

        // Get all locations data
        $locations_data = $this->get_all_locations();

        wp_localize_script('city-district-js', 'city_district_data', array(
            'cities' => $locations_data['cities'],
            'districts_by_city' => $locations_data['districts_by_city'],
            'nonce' => wp_create_nonce('city_district_nonce')
        ));
    }
}

// Initialize plugin
$city_district_dropdown = new City_District_Dropdown();
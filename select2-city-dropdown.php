<?php
/**
 * Plugin Name: WooCommerce City Select2 Dropdown
 * Description: Replaces billing city text field with a Select2 dropdown using data from wp_city_district_locations.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, 'city_select2_create_table_and_import');

function city_select2_create_table_and_import() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'city_district_locations';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    dbDelta($sql);

    // Import cities from CSV
    $csv_file = plugin_dir_path(__FILE__) . 'cities.csv';

    if (file_exists($csv_file)) {
        $handle = fopen($csv_file, 'r');
        if ($handle) {
            $is_first_row = true;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($is_first_row) {
                    $is_first_row = false; // skip header
                    continue;
                }

                $city_name = trim($data[0]);
                if (!empty($city_name)) {
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE name = %s",
                        $city_name
                    ));
                    if (!$exists) {
                        $wpdb->insert($table_name, ['name' => $city_name]);
                    }
                }
            }
            fclose($handle);
        }
    }
}


// Enqueue Select2 and custom JS
add_action('wp_enqueue_scripts', function() {
    if (is_checkout()) {
        // Select2 from WP or CDN
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

        // Our script
        wp_enqueue_script('city-select2-script', plugin_dir_url(__FILE__) . 'city-select2.js', ['jquery', 'select2'], null, true);
        wp_localize_script('city-select2-script', 'CityData', [
            'cities' => get_city_options(),
        ]);
    }
});

// Replace billing_city field
add_filter('woocommerce_checkout_fields', function($fields) {
    $fields['billing']['billing_city'] = [
        'type'     => 'select',
        'label'    => __('City', 'woocommerce'),
        'required' => true,
        'class'    => ['form-row-wide'],
        'options'  => ['' => __('إختر مدينة', 'woocommerce')] + get_city_options(),
    ];

    return $fields;
});

// Helper to fetch cities from DB
function get_city_options() {
    global $wpdb;
    $results = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}city_district_locations ORDER BY name ASC");

    $cities = [];
    foreach ($results as $row) {
        $cities[$row->name] = $row->name; // Use name as key and value
    }

    return $cities;
}

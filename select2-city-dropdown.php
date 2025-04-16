<?php
/**
 * Plugin Name: WooCommerce City Select2 Dropdown
 * Description: Replaces billing city text field with a Select2 dropdown using data from wp_city_district_locations.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

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

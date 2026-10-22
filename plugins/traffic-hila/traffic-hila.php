<?php
/**
 * Plugin Name: Traffic Hila
 * Description: Trạm điều hành trung tâm quản lý Nhiệm Vụ SEO và Phân phối Traffic.
 * Version: 2.0.0
 * Author: Traffic System
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IATS_VERSION', '2.0.0');
define('IATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IATS_PLUGIN_URL', plugin_dir_url(__FILE__));

global $wpdb;
define('IATS_TABLE_TASKS', $wpdb->prefix . 'iats_tasks');
define('IATS_TABLE_CODES', $wpdb->prefix . 'iats_codes');
define('IATS_TABLE_LOGS', $wpdb->prefix . 'iats_logs');

register_activation_hook(__FILE__, 'iats_activate_plugin');
function iats_activate_plugin() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $c = $wpdb->get_charset_collate();

    // 1. Bảng lưu trữ Nhiệm Vụ SEO (Mang từ Quiz Locker cũ sang)
    $sql_tasks = "CREATE TABLE " . IATS_TABLE_TASKS . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT 0,
        detail text NOT NULL,
        detail2 text,
        website text,
        img3 text DEFAULT NULL,
        step4 text DEFAULT NULL,
        img4 text DEFAULT NULL,
        tag text,
        fixed_code varchar(50) DEFAULT NULL,
        priority tinyint(1) DEFAULT 0,
        limit_per_day int(11) DEFAULT 0,
        count_get int(11) DEFAULT 0,
        count int(11) DEFAULT 0,
        count_yesterday int(11) DEFAULT 0,
        count_limit int(11) DEFAULT 0,
        last_reset_date date DEFAULT NULL,
        active boolean DEFAULT 1,
        PRIMARY KEY (id)
    ) $c;";

    // 2. Bảng lưu trữ mã xác nhận tự động sinh (Codes)
    $sql_codes = "CREATE TABLE " . IATS_TABLE_CODES . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        code_string varchar(50) NOT NULL,
        project_id bigint(20) NOT NULL,
        ip_address varchar(50) NOT NULL,
        status varchar(20) DEFAULT 'unused' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        used_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY code_string (code_string)
    ) $c;";

    // 3. Bảng lưu trữ Log (Thống kê truy cập)
    $sql_logs = "CREATE TABLE " . IATS_TABLE_LOGS . " (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        project_id bigint(20) NOT NULL,
        ip_address varchar(50) NOT NULL,
        user_agent text NOT NULL,
        referrer text NOT NULL,
        event_type varchar(50) DEFAULT 'get_code' NOT NULL,
        status varchar(50) DEFAULT 'success' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $c;";

    dbDelta($sql_tasks);
    dbDelta($sql_codes);
    dbDelta($sql_logs);
}

// Load Modules
require_once IATS_PLUGIN_DIR . 'includes/class-ts-api.php';
require_once IATS_PLUGIN_DIR . 'includes/class-ts-admin.php';
require_once IATS_PLUGIN_DIR . 'includes/class-ts-customer.php';

function iats_init() {
    new IATS_API();
    new IATS_Customer_Portal();
    if (is_admin()) {
        new IATS_Admin();
    }
}
add_action('plugins_loaded', 'iats_init');

// Tự động tắt Trình soạn thảo Khối (Gutenberg) - Chuyển về Classic Editor
add_filter('use_block_editor_for_post', '__return_false', 10);

// Ẩn Admin Bar (dải băng đen trên cùng) cho tất cả người dùng ngoài Frontend
add_action('after_setup_theme', function() {
    if (!is_admin()) {
        show_admin_bar(false);
    }
});

<?php
/**
 * Plugin Name: TNP IQ Test
 * Description: Bài test IQ dạng ma trận hình: quản trị câu hỏi, tính giờ, hỏi tuổi ở bước cuối để quy đổi điểm IQ theo độ tuổi, lưu và thống kê kết quả. Dùng shortcode [tnp_iq_test].
 * Version: 1.0.0
 * Author: Trắc nghiệm PLUS
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: tnp-iq-test
 */

defined( 'ABSPATH' ) || exit;

define( 'TNP_IQ_VERSION', '1.0.0' );
define( 'TNP_IQ_FILE', __FILE__ );
define( 'TNP_IQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'TNP_IQ_URL', plugin_dir_url( __FILE__ ) );
define( 'TNP_IQ_CPT', 'tnp_iq_question' );

require_once TNP_IQ_DIR . 'includes/class-settings.php';
require_once TNP_IQ_DIR . 'includes/class-questions.php';
require_once TNP_IQ_DIR . 'includes/class-scoring.php';
require_once TNP_IQ_DIR . 'includes/class-results.php';
require_once TNP_IQ_DIR . 'includes/class-rest.php';
require_once TNP_IQ_DIR . 'includes/class-shortcode.php';
require_once TNP_IQ_DIR . 'includes/class-demo.php';

register_activation_hook( __FILE__, array( 'TNP_IQ_Results', 'install' ) );
register_activation_hook( __FILE__, array( 'TNP_IQ_Questions', 'activate' ) );

add_action( 'plugins_loaded', function () {
	TNP_IQ_Settings::init();
	TNP_IQ_Questions::init();
	TNP_IQ_Results::init();
	TNP_IQ_Rest::init();
	TNP_IQ_Shortcode::init();
	TNP_IQ_Demo::init();
} );

<?php
/**
 * Plugin Name: TNP Trắc nghiệm (IQ, EQ, DISC, MBTI, Holland)
 * Description: Tạo bài test IQ, EQ, DISC, MBTI, Holland: quản trị câu hỏi (chữ hoặc ảnh, 2–8 đáp án), chấm điểm trên máy chủ, trang kết quả chi tiết cho từng loại, lưu và thống kê. Shortcode [tnp_test test="..."] (bài IQ chung: [tnp_iq_test]).
 * Version: 2.8.0
 * Author: Trắc nghiệm PLUS
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: tnp-iq-test
 */

defined( 'ABSPATH' ) || exit;

define( 'TNP_IQ_VERSION', '2.8.0' );
define( 'TNP_IQ_FILE', __FILE__ );
define( 'TNP_IQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'TNP_IQ_URL', plugin_dir_url( __FILE__ ) );
define( 'TNP_IQ_CPT', 'tnp_iq_question' );

require_once TNP_IQ_DIR . 'includes/class-settings.php';
require_once TNP_IQ_DIR . 'includes/class-questions.php';
require_once TNP_IQ_DIR . 'includes/class-tests.php';
require_once TNP_IQ_DIR . 'includes/class-scoring.php';
require_once TNP_IQ_DIR . 'includes/class-results.php';
require_once TNP_IQ_DIR . 'includes/class-rest.php';
require_once TNP_IQ_DIR . 'includes/class-shortcode.php';
require_once TNP_IQ_DIR . 'includes/class-demo.php';
require_once TNP_IQ_DIR . 'includes/class-import.php';
require_once TNP_IQ_DIR . 'includes/class-json.php';

register_activation_hook( __FILE__, array( 'TNP_IQ_Results', 'install' ) );
register_activation_hook( __FILE__, array( 'TNP_IQ_Questions', 'activate' ) );

add_action( 'plugins_loaded', function () {
	TNP_IQ_Settings::init();
	TNP_IQ_Questions::init();
	TNP_IQ_Tests::init();
	TNP_IQ_Results::init();
	TNP_IQ_Rest::init();
	TNP_IQ_Shortcode::init();
	TNP_IQ_Demo::init();
	TNP_IQ_Import::init();
	TNP_IQ_Json::init();
} );

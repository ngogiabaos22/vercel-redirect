<?php
defined( 'ABSPATH' ) || exit;

/**
 * [tnp_test test="slug-bai-test" count="20" time="15"]   (hoặc [tnp_iq_test …], giống hệt)
 * test  – slug bài test (bỏ trống = bài IQ chung gồm các câu IQ không thuộc bài test nào khác)
 * count – số câu, time – số phút tối đa (đồng hồ vẫn đếm xuôi)
 */
class TNP_IQ_Shortcode {

	public static function init() {
		add_shortcode( 'tnp_iq_test', array( __CLASS__, 'render' ) );
		add_shortcode( 'tnp_test', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'tnp-iq-quiz', TNP_IQ_URL . 'assets/quiz.css', array(), TNP_IQ_VERSION );
		wp_register_script( 'tnp-iq-quiz', TNP_IQ_URL . 'assets/quiz.js', array(), TNP_IQ_VERSION, true );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array( 'test' => '', 'count' => '', 'time' => '' ), $atts, 'tnp_iq_test' );
		$test = sanitize_title( $atts['test'] );
		$s    = TNP_IQ_Settings::get();

		wp_enqueue_style( 'tnp-iq-quiz' );
		wp_enqueue_script( 'tnp-iq-quiz' );

		$count = absint( $atts['count'] );
		$time  = absint( $atts['time'] );

		$config = array(
			'api'        => esc_url_raw( rest_url( TNP_IQ_Rest::NS ) ),
			'test'       => $test,
			'count'      => '' !== $atts['count'] ? $count : null,
			'time'       => '' !== $atts['time'] ? $time : null,
			'kind'       => TNP_IQ_Tests::type_of( $test ),
			'introTitle' => TNP_IQ_Questions::test_name( $test ),
			'introText'  => TNP_IQ_Tests::intro_of( $test ),
			'ageNote'    => $s['age_note'],
		);

		return '<div class="tnp-iq" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"><noscript>Vui lòng bật JavaScript để làm bài test IQ.</noscript></div>';
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * [tnp_iq_test test="slug-bo-de" count="20" time="15"]
 * test  – slug bộ đề (bỏ trống = toàn bộ câu hỏi)
 * count – số câu, time – số phút tối đa (đồng hồ vẫn đếm xuôi)
 */
class TNP_IQ_Shortcode {

	public static function init() {
		add_shortcode( 'tnp_iq_test', array( __CLASS__, 'render' ) );
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

		$count = '' !== $atts['count'] ? absint( $atts['count'] ) : (int) $s['question_count'];
		$time  = '' !== $atts['time'] ? absint( $atts['time'] ) : (int) $s['time_limit'];

		// Số câu thực tế hiển thị ở màn hình bắt đầu.
		$available = count( TNP_IQ_Rest::questions( 0, 'menu_order', $test ) );
		$shown     = $count > 0 ? min( $count, $available ) : $available;

		$config = array(
			'api'        => esc_url_raw( rest_url( TNP_IQ_Rest::NS ) ),
			'test'       => $test,
			'count'      => '' !== $atts['count'] ? $count : null,
			'time'       => '' !== $atts['time'] ? $time : null,
			'questions'  => $shown,
			'minutes'    => $time,
			'introTitle' => TNP_IQ_Questions::test_name( $test ),
			'introText'  => $s['intro_text'],
			'ageNote'    => $s['age_note'],
		);

		return '<div class="tnp-iq" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"><noscript>Vui lòng bật JavaScript để làm bài test IQ.</noscript></div>';
	}
}

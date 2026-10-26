<?php
defined( 'ABSPATH' ) || exit;

/**
 * [tnp_iq_test count="20" time="15"]
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
		$atts = shortcode_atts( array( 'count' => '', 'time' => '' ), $atts, 'tnp_iq_test' );
		$s    = TNP_IQ_Settings::get();

		wp_enqueue_style( 'tnp-iq-quiz' );
		wp_enqueue_script( 'tnp-iq-quiz' );

		$count = '' !== $atts['count'] ? absint( $atts['count'] ) : (int) $s['question_count'];
		$time  = '' !== $atts['time'] ? absint( $atts['time'] ) : (int) $s['time_limit'];

		// Số câu thực tế hiển thị ở màn hình bắt đầu.
		$available = count( TNP_IQ_Rest::questions( 0, 'menu_order' ) );
		$shown     = $count > 0 ? min( $count, $available ) : $available;

		$config = array(
			'api'        => esc_url_raw( rest_url( TNP_IQ_Rest::NS ) ),
			'count'      => '' !== $atts['count'] ? $count : null,
			'time'       => '' !== $atts['time'] ? $time : null,
			'questions'  => $shown,
			'minutes'    => $time,
			'introTitle' => $s['intro_title'],
			'introText'  => $s['intro_text'],
			'ageNote'    => $s['age_note'],
			'ctaText'    => $s['cta_text'],
			'ctaUrl'     => $s['cta_url'],
		);

		return '<div class="tnp-iq" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"><noscript>Vui lòng bật JavaScript để làm bài test IQ.</noscript></div>';
	}
}

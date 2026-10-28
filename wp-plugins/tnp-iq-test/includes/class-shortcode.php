<?php
defined( 'ABSPATH' ) || exit;

/**
 * [tnp_test test="slug-bai-test" count="20" time="15"]   (hoặc [tnp_iq_test …], giống hệt)
 * test  – slug bài test (bỏ trống = bài IQ chung gồm các câu IQ không thuộc bài test nào khác)
 * count – số câu, time – số phút tối đa (đồng hồ vẫn đếm xuôi)
 *
 * Mở bài test trong cửa sổ bật lên từ bất kỳ nút/liên kết nào trên web:
 *   đặt đường dẫn của nút là  #tnp-test=slug-bai-test  (hoặc dùng [tnp_button test="…" text="…"]).
 */
class TNP_IQ_Shortcode {

	public static function init() {
		add_shortcode( 'tnp_iq_test', array( __CLASS__, 'render' ) );
		add_shortcode( 'tnp_test', array( __CLASS__, 'render' ) );
		add_shortcode( 'tnp_button', array( __CLASS__, 'button' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'launcher' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'tnp-iq-quiz', TNP_IQ_URL . 'assets/quiz.css', array(), TNP_IQ_VERSION );
		wp_register_script( 'tnp-iq-quiz', TNP_IQ_URL . 'assets/quiz.js', array(), TNP_IQ_VERSION, true );
	}

	/** Dữ liệu cho cửa sổ bật lên: mọi bài test (tên, loại, lời giới thiệu) + bài IQ chung. */
	public static function launcher_data() {
		$tests = array(
			'' => array( 'kind' => 'iq', 'title' => TNP_IQ_Questions::test_name( '' ), 'intro' => TNP_IQ_Tests::intro_of( '' ) ),
		);
		$terms = get_terms( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'hide_empty' => false ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$tests[ $t->slug ] = array( 'kind' => TNP_IQ_Tests::type_of( $t->slug ), 'title' => $t->name, 'intro' => TNP_IQ_Tests::intro_of( $t->slug ) );
		}
		return array(
			'api'     => esc_url_raw( rest_url( TNP_IQ_Rest::NS ) ),
			'css'     => add_query_arg( 'ver', TNP_IQ_VERSION, TNP_IQ_URL . 'assets/quiz.css' ),
			'js'      => add_query_arg( 'ver', TNP_IQ_VERSION, TNP_IQ_URL . 'assets/quiz.js' ),
			'ageNote' => TNP_IQ_Settings::get()['age_note'],
			'tests'   => $tests,
		);
	}

	/** Bộ mở bài test (~3 KB) trên mọi trang; quiz.js/quiz.css chỉ tải khi người dùng bấm nút. */
	public static function launcher() {
		wp_enqueue_style( 'tnp-iq-launcher', TNP_IQ_URL . 'assets/launcher.css', array(), TNP_IQ_VERSION );
		wp_enqueue_script( 'tnp-iq-launcher', TNP_IQ_URL . 'assets/launcher.js', array(), TNP_IQ_VERSION, true );
		wp_add_inline_script( 'tnp-iq-launcher', 'window.tnpLauncher=' . wp_json_encode( self::launcher_data() ) . ';', 'before' );
	}

	/** [tnp_button test="test-iq" text="Test IQ nhanh" class="primary"] — nút mở bài test trong cửa sổ bật lên. */
	public static function button( $atts ) {
		$atts = shortcode_atts( array( 'test' => '', 'text' => 'Làm bài test', 'class' => 'primary' ), $atts, 'tnp_button' );
		return sprintf(
			'<a href="#tnp-test=%1$s" class="button %2$s tnp-open-test" data-tnp-test="%1$s"><span>%3$s</span></a>',
			esc_attr( sanitize_title( $atts['test'] ) ),
			esc_attr( $atts['class'] ),
			esc_html( $atts['text'] )
		);
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

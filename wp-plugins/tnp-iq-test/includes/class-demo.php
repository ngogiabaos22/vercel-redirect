<?php
defined( 'ABSPATH' ) || exit;

/**
 * Nút "Thêm câu hỏi mẫu" ở trang Cài đặt: tạo 4 câu hỏi ví dụ (2 câu ảnh ma trận, 1 câu dãy số,
 * 1 câu suy luận 5 đáp án) để xem trước cách hiển thị. Có thể xoá đi sau đó.
 */
class TNP_IQ_Demo {

	public static function init() {
		add_action( 'admin_post_tnp_iq_demo', array( __CLASS__, 'import' ) );
	}

	public static function render_button() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tnp_iq_demo">
			<?php wp_nonce_field( 'tnp_iq_demo' ); ?>
			<button class="button">Thêm 4 câu hỏi mẫu</button>
			<span class="description">Tạo sẵn 4 câu (2 câu ảnh đề bài, 2 câu hỏi chữ, có câu 5 đáp án) để xem cách nhập và cách hiển thị. Xoá đi khi không cần.</span>
		</form>
		<?php
		if ( isset( $_GET['tnp_demo'] ) ) {
			echo '<p><strong>Đã thêm ' . (int) $_GET['tnp_demo'] . ' câu hỏi mẫu.</strong></p>';
		}
	}

	private static function attach( $file, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$src = TNP_IQ_DIR . 'assets/demo/' . $file;
		$tmp = wp_tempnam( $file );
		if ( ! $tmp || ! copy( $src, $tmp ) ) {
			return 0;
		}
		$id = media_handle_sideload( array( 'name' => 'tnp-iq-' . $file, 'tmp_name' => $tmp ), 0, $title );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return 0;
		}
		return (int) $id;
	}

	private static function create( $title, $order, array $meta ) {
		$id = wp_insert_post( array(
			'post_type'   => TNP_IQ_CPT,
			'post_status' => 'publish',
			'post_title'  => $title,
			'menu_order'  => $order,
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_tnp_' . $k, $v );
		}
		return $id;
	}

	/** Câu kiểu "Ảnh đề bài" với 4 đáp án ảnh. */
	private static function image_question( $prefix, $title, $order, $correct, $explain, $category, $difficulty ) {
		$options = array();
		foreach ( array( 'A', 'B', 'C', 'D' ) as $k ) {
			$options[ $k ] = array( 'img' => self::attach( "{$prefix}-opt-{$k}.png", "Câu mẫu – đáp án {$k}" ), 'text' => '' );
		}
		return self::create( $title, $order, array(
			'type'       => 'image',
			'body'       => '',
			'image'      => self::attach( "{$prefix}-question.png", 'Câu mẫu – đề bài' ),
			'options'    => $options,
			'correct'    => $correct,
			'explain'    => $explain,
			'category'   => $category,
			'difficulty' => $difficulty,
		) );
	}

	/** Câu kiểu "Câu hỏi" với đáp án chữ. */
	private static function text_question( $title, $body, $order, array $answers, $correct, $explain, $category, $difficulty ) {
		$options = array();
		foreach ( array_values( $answers ) as $i => $t ) {
			$options[ TNP_IQ_Questions::letter( $i ) ] = array( 'img' => 0, 'text' => $t );
		}
		return self::create( $title, $order, array(
			'type'       => 'question',
			'body'       => $body,
			'image'      => 0,
			'options'    => $options,
			'correct'    => $correct,
			'explain'    => $explain,
			'category'   => $category,
			'difficulty' => $difficulty,
		) );
	}

	public static function import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tnp_iq_demo' ) ) {
			wp_die( 'Không có quyền.' );
		}
		$base = (int) wp_count_posts( TNP_IQ_CPT )->publish;
		$made = 0;

		$made += self::image_question( 'rot', 'Hình nào điền vào ô trống?', $base + 1, 'C',
			'Mỗi hàng, hình xoay thêm 20° theo chiều kim đồng hồ: 0° → 20° → 40°. Ô trống là dấu cộng xoay 40°.', 'logic', 3 ) ? 1 : 0;

		$made += self::image_question( 'cnt', 'Hình nào hoàn thành ma trận?', $base + 2, 'D',
			'Mỗi hàng giữ nguyên một loại hình, mỗi cột tăng số lượng 1 → 2 → 3. Ô trống cần 3 hình tam giác.', 'logic', 2 ) ? 1 : 0;

		$made += self::text_question( 'Số tiếp theo của dãy là gì?', "2, 6, 12, 20, 30, ?", $base + 3,
			array( '40', '42', '44', '36' ), 'B',
			'Khoảng cách giữa các số tăng dần 4, 6, 8, 10, 12 (hay n × (n+1)). 30 + 12 = 42.', 'number', 2 ) ? 1 : 0;

		$made += self::text_question( 'Ai là người thấp nhất?', "An cao hơn Bình.\nCường thấp hơn Bình.\nDũng cao hơn An.", $base + 4,
			array( 'An', 'Bình', 'Cường', 'Dũng', 'Không đủ dữ kiện để kết luận' ), 'C',
			'Thứ tự từ cao đến thấp: Dũng > An > Bình > Cường.', 'verbal', 2 ) ? 1 : 0;

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&page=tnp-iq-settings&tnp_demo=' . $made ) );
		exit;
	}
}

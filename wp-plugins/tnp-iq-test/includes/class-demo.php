<?php
defined( 'ABSPATH' ) || exit;

/**
 * Nút "Thêm câu hỏi mẫu" ở trang Cài đặt: tạo 3 câu hỏi ví dụ (lưới xoay hình, lưới đếm số lượng,
 * dãy số dạng chữ) để xem trước cách hiển thị. Có thể xoá đi sau đó.
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
			<button class="button">Thêm 3 câu hỏi mẫu</button>
			<span class="description">Tạo sẵn 3 câu (kèm ảnh) để xem cách nhập và cách hiển thị. Xoá đi khi không cần.</span>
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

	private static function grid_question( $prefix, $title, $order, $correct, $explain, $category, $difficulty ) {
		$cells = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$cells[] = self::attach( "{$prefix}-cell-{$i}.png", "Câu mẫu – ô {$i}" );
		}
		$options = array();
		foreach ( TNP_IQ_Questions::KEYS as $k ) {
			$options[ $k ] = array( 'img' => self::attach( "{$prefix}-opt-{$k}.png", "Câu mẫu – đáp án {$k}" ), 'text' => '' );
		}
		return self::create( $title, $order, array(
			'type'       => 'grid',
			'grid'       => 3,
			'cells'      => array_pad( $cells, 15, 0 ),
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

		$made += self::grid_question( 'rot', 'Hình nào điền vào ô trống?', $base + 1, 'C',
			'Mỗi hàng, hình xoay thêm 20° theo chiều kim đồng hồ: 0° → 20° → 40°. Ô trống là dấu cộng xoay 40°.', 'logic', 3 ) ? 1 : 0;

		$made += self::grid_question( 'cnt', 'Hình nào hoàn thành ma trận?', $base + 2, 'D',
			'Mỗi hàng giữ nguyên một loại hình, mỗi cột tăng số lượng 1 → 2 → 3. Ô trống cần 3 hình tam giác.', 'logic', 2 ) ? 1 : 0;

		$made += self::create( 'Số tiếp theo của dãy: 2, 6, 12, 20, 30, ?', $base + 3, array(
			'type'       => 'text',
			'grid'       => 3,
			'cells'      => array_fill( 0, 15, 0 ),
			'image'      => 0,
			'options'    => array(
				'A' => array( 'img' => 0, 'text' => '40' ),
				'B' => array( 'img' => 0, 'text' => '42' ),
				'C' => array( 'img' => 0, 'text' => '44' ),
				'D' => array( 'img' => 0, 'text' => '36' ),
			),
			'correct'    => 'B',
			'explain'    => 'Khoảng cách giữa các số tăng dần 4, 6, 8, 10, 12 (hay n × (n+1)). 30 + 12 = 42.',
			'category'   => 'number',
			'difficulty' => 2,
		) ) ? 1 : 0;

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&page=tnp-iq-settings&tnp_demo=' . $made ) );
		exit;
	}
}

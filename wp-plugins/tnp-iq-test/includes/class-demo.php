<?php
defined( 'ABSPATH' ) || exit;

/**
 * Nút "Thêm câu hỏi mẫu" ở trang Cài đặt: tạo 4 câu hỏi ví dụ (2 câu ảnh ma trận, 1 câu dãy số,
 * 1 câu suy luận 5 đáp án) để xem trước cách hiển thị. Có thể xoá đi sau đó.
 */
class TNP_IQ_Demo {

	public static function init() {
		add_action( 'admin_post_tnp_iq_demo', array( __CLASS__, 'import' ) );
		add_action( 'admin_post_tnp_iq_demo_traits', array( __CLASS__, 'import_traits' ) );
	}

	public static function render_button() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tnp_iq_demo">
			<?php wp_nonce_field( 'tnp_iq_demo' ); ?>
			<button class="button">Thêm 4 câu hỏi mẫu</button>
			<span class="description">Tạo sẵn 4 câu (2 câu ảnh đề bài, 2 câu hỏi chữ, có câu 5 đáp án) để xem cách nhập và cách hiển thị. Xoá đi khi không cần.</span>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
			<input type="hidden" name="action" value="tnp_iq_demo_traits">
			<?php wp_nonce_field( 'tnp_iq_demo_traits' ); ?>
			<button class="button">Tạo 4 bài test mẫu: EQ, DISC, MBTI, Holland</button>
			<span class="description">Mỗi bài vài câu ví dụ để xem cách nhập và trang kết quả. Xoá hoặc thay bằng câu hỏi của bạn.</span>
		</form>
		<?php
		if ( isset( $_GET['tnp_demo_traits'] ) ) {
			echo '<p><strong>Đã tạo ' . (int) $_GET['tnp_demo_traits'] . ' bài test mẫu.</strong> Xem ở mục <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . TNP_IQ_Questions::TAX . '&post_type=' . TNP_IQ_CPT ) ) . '">Bài test</a>.</p>';
		}
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

	/* ---------- Bài test mẫu EQ, DISC, MBTI, Holland ---------- */

	private static function trait_test( $name, $slug, $type, array $questions ) {
		$term = term_exists( $slug, TNP_IQ_Questions::TAX );
		if ( ! $term ) {
			$term = wp_insert_term( $name, TNP_IQ_Questions::TAX, array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			return false;
		}
		$term_id = (int) $term['term_id'];
		update_term_meta( $term_id, 'tnp_type', $type );
		update_term_meta( $term_id, 'tnp_shuffle', '1' );
		foreach ( $questions as $i => $q ) {
			$options = array();
			foreach ( $q[1] as $j => $o ) {
				$options[ TNP_IQ_Questions::letter( $j ) ] = array( 'img' => 0, 'text' => $o[0], 'dim' => $o[1], 'pts' => (float) $o[2] );
			}
			$id = self::create( $q[0], $i + 1, array(
				'type'       => 'question',
				'body'       => '',
				'image'      => 0,
				'options'    => $options,
				'correct'    => '',
				'explain'    => '',
				'category'   => 'logic',
				'difficulty' => 3,
			) );
			if ( $id ) {
				wp_set_object_terms( $id, array( $term_id ), TNP_IQ_Questions::TAX );
			}
		}
		return true;
	}

	public static function import_traits() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tnp_iq_demo_traits' ) ) {
			wp_die( 'Không có quyền.' );
		}
		$eq = function ( $dim, array $texts ) {
			$o = array();
			foreach ( $texts as $i => $t ) {
				$o[] = array( $t, $dim, $i + 1 ); // phương án sau trưởng thành hơn → nhiều điểm hơn
			}
			return array_reverse( $o ); // câu mẫu: phương án nhiều điểm nhất đứng đầu. Khi soạn đề thật nên xáo trộn thứ tự.
		};
		$like = function ( $dim ) {
			return array( array( 'Rất thích', $dim, 3 ), array( 'Thích', $dim, 2 ), array( 'Bình thường', $dim, 1 ), array( 'Không thích', $dim, 0 ) );
		};
		$made = 0;

		$made += self::trait_test( 'Test EQ mẫu', 'test-eq-mau', 'eq', array(
			array( 'Khi bất ngờ thấy bực bội, bạn thường làm gì?', $eq( 'sa', array( 'Bỏ qua, không để ý mình đang cảm thấy gì', 'Biết mình khó chịu nhưng không rõ vì sao', 'Nhận ra mình bực và đoán được nguyên nhân', 'Gọi tên được cảm xúc và hiểu rõ điều gì gây ra nó' ) ) ),
			array( 'Bị góp ý gay gắt trước mặt người khác, bạn sẽ:', $eq( 'sr', array( 'Phản bác ngay để giữ thể diện', 'Im lặng nhưng để bụng khá lâu', 'Cố giữ bình tĩnh rồi trả lời sau', 'Hít thở, ghi nhận ý đúng và trao đổi riêng sau đó' ) ) ),
			array( 'Khi một việc quan trọng thất bại, bạn:', $eq( 'mo', array( 'Bỏ cuộc vì thấy mình không hợp', 'Buồn vài ngày rồi mới làm tiếp', 'Thử lại theo cách cũ', 'Rút kinh nghiệm, điều chỉnh kế hoạch và tiếp tục' ) ) ),
			array( 'Một người bạn kể chuyện buồn, bạn thường:', $eq( 'em', array( 'Chuyển sang chủ đề khác cho đỡ nặng nề', 'Khuyên ngay họ nên làm gì', 'Lắng nghe nhưng không biết nói gì', 'Lắng nghe, hỏi han để hiểu họ đang cảm thấy thế nào' ) ) ),
			array( 'Hai đồng nghiệp đang căng thẳng với nhau, bạn:', $eq( 'ss', array( 'Đứng ngoài, mặc kệ', 'Bênh người mình thân hơn', 'Khuyên cả hai bình tĩnh', 'Gặp riêng từng người, giúp họ nói chuyện thẳng thắn với nhau' ) ) ),
		) ) ? 1 : 0;

		$made += self::trait_test( 'Test DISC mẫu', 'test-disc-mau', 'disc', array(
			array( 'Trong một nhóm làm việc, bạn thường là người:', array( array( 'Dẫn dắt và quyết định hướng đi', 'D', 1 ), array( 'Khuấy động, tạo năng lượng cho nhóm', 'I', 1 ), array( 'Hỗ trợ và giữ hoà khí', 'S', 1 ), array( 'Lên kế hoạch và kiểm tra chất lượng', 'C', 1 ) ) ),
			array( 'Khi gặp vấn đề khó, bạn:', array( array( 'Hành động ngay để giải quyết', 'D', 1 ), array( 'Rủ mọi người cùng bàn ý tưởng', 'I', 1 ), array( 'Bình tĩnh làm từng bước quen thuộc', 'S', 1 ), array( 'Thu thập dữ liệu và phân tích kỹ', 'C', 1 ) ) ),
			array( 'Điều khiến bạn khó chịu nhất là:', array( array( 'Mất quyền kiểm soát, bị cản trở', 'D', 1 ), array( 'Bị phớt lờ, không được ghi nhận', 'I', 1 ), array( 'Thay đổi đột ngột, xung đột', 'S', 1 ), array( 'Làm sai, làm ẩu, thiếu quy trình', 'C', 1 ) ) ),
			array( 'Người khác thường mô tả bạn là:', array( array( 'Quyết đoán, thẳng thắn', 'D', 1 ), array( 'Vui vẻ, hoà đồng', 'I', 1 ), array( 'Kiên nhẫn, đáng tin', 'S', 1 ), array( 'Cẩn thận, chính xác', 'C', 1 ) ) ),
		) ) ? 1 : 0;

		$made += self::trait_test( 'Test MBTI mẫu', 'test-mbti-mau', 'mbti', array(
			array( 'Sau một tuần làm việc mệt mỏi, bạn muốn:', array( array( 'Gặp gỡ bạn bè, ra ngoài', 'E', 1 ), array( 'Ở nhà nghỉ ngơi một mình', 'I', 1 ) ) ),
			array( 'Trong cuộc trò chuyện, bạn thường:', array( array( 'Nói nhiều và nghĩ khi đang nói', 'E', 1 ), array( 'Nghe nhiều và nghĩ kỹ rồi mới nói', 'I', 1 ) ) ),
			array( 'Bạn tin vào điều gì hơn?', array( array( 'Kinh nghiệm và dữ kiện thực tế', 'S', 1 ), array( 'Linh cảm và các khả năng mới', 'N', 1 ) ) ),
			array( 'Khi học điều mới, bạn thích:', array( array( 'Ví dụ cụ thể, làm từng bước', 'S', 1 ), array( 'Hiểu bức tranh tổng thể trước', 'N', 1 ) ) ),
			array( 'Khi phải quyết định khó, bạn dựa vào:', array( array( 'Lập luận logic, khách quan', 'T', 1 ), array( 'Cảm xúc và ảnh hưởng tới mọi người', 'F', 1 ) ) ),
			array( 'Bạn thấy lời khen nào ý nghĩa hơn?', array( array( 'Bạn rất giỏi và hiệu quả', 'T', 1 ), array( 'Bạn rất tốt bụng và chu đáo', 'F', 1 ) ) ),
			array( 'Với một chuyến du lịch, bạn:', array( array( 'Lên lịch trình chi tiết từ trước', 'J', 1 ), array( 'Đi rồi tính, tuỳ hứng', 'P', 1 ) ) ),
			array( 'Bàn làm việc của bạn thường:', array( array( 'Ngăn nắp, mọi thứ đúng chỗ', 'J', 1 ), array( 'Hơi bừa nhưng mình biết đồ ở đâu', 'P', 1 ) ) ),
		) ) ? 1 : 0;

		$made += self::trait_test( 'Test Holland mẫu', 'test-holland-mau', 'holland', array(
			array( 'Sửa chữa, lắp ráp máy móc, đồ điện', $like( 'R' ) ),
			array( 'Làm thí nghiệm, tìm hiểu vì sao một hiện tượng xảy ra', $like( 'I' ) ),
			array( 'Vẽ, thiết kế, viết truyện hoặc chơi nhạc', $like( 'A' ) ),
			array( 'Dạy kèm, giúp đỡ, chăm sóc người khác', $like( 'S' ) ),
			array( 'Thuyết phục người khác, bán hàng, khởi nghiệp', $like( 'E' ) ),
			array( 'Sắp xếp giấy tờ, ghi chép sổ sách, làm bảng tính', $like( 'C' ) ),
		) ) ? 1 : 0;

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&page=tnp-iq-settings&tnp_demo_traits=' . $made ) );
		exit;
	}
}

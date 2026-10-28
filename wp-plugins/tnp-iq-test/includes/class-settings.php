<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cài đặt plugin, lưu trong một option duy nhất.
 */
class TNP_IQ_Settings {

	const OPTION = 'tnp_iq_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Bảng chuẩn mặc định: tỉ lệ điểm (đã nhân trọng số độ khó) trung bình và độ lệch chuẩn
	 * theo nhóm tuổi. Đây là giá trị ước lượng ban đầu; nên hiệu chỉnh lại từ dữ liệu thực
	 * (trang Kết quả có thống kê theo nhóm tuổi để làm việc này).
	 */
	public static function default_norms() {
		return "6-9: 0.30, 0.16\n10-12: 0.38, 0.17\n13-15: 0.46, 0.18\n16-19: 0.52, 0.18\n20-34: 0.55, 0.18\n35-49: 0.52, 0.18\n50-64: 0.47, 0.18\n65-100: 0.41, 0.17";
	}

	public static function defaults() {
		return array(
			'question_count' => 20,
			'order'          => 'menu_order',
			'time_limit'     => 0,
			'speed_ref'      => 45,
			'speed_bonus'    => 3,
			'iq_min'         => 55,
			'iq_max'         => 160,
			'norms'          => self::default_norms(),
			'intro_title'    => 'Test IQ miễn phí',
			'task_server'    => 'https://traffic.io.vn',
			'age_note'       => 'Điểm IQ được so sánh với những người cùng độ tuổi, vì vậy hãy nhập đúng tuổi của bạn để có kết quả chính xác nhất.',
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $opts : ( $opts[ $key ] ?? null );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TNP_IQ_CPT,
			'Cài đặt trắc nghiệm',
			'Cài đặt',
			'manage_options',
			'tnp-iq-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting( 'tnp_iq_settings_group', self::OPTION, array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$out = array();

		$out['question_count'] = max( 0, (int) ( $in['question_count'] ?? $d['question_count'] ) );
		$out['order']          = in_array( $in['order'] ?? '', array( 'menu_order', 'random' ), true ) ? $in['order'] : 'menu_order';
		$out['time_limit']     = max( 0, (int) ( $in['time_limit'] ?? $d['time_limit'] ) );
		$out['speed_ref']      = max( 5, min( 600, (int) ( $in['speed_ref'] ?? $d['speed_ref'] ) ) );
		$out['speed_bonus']    = max( 0, min( 15, (int) ( $in['speed_bonus'] ?? $d['speed_bonus'] ) ) );
		$out['iq_min']         = max( 40, min( 100, (int) ( $in['iq_min'] ?? $d['iq_min'] ) ) );
		$out['iq_max']         = max( 100, min( 200, (int) ( $in['iq_max'] ?? $d['iq_max'] ) ) );
		$out['intro_title']    = sanitize_text_field( $in['intro_title'] ?? $d['intro_title'] );
		$out['age_note']       = sanitize_textarea_field( $in['age_note'] ?? $d['age_note'] );
		$out['task_server']    = untrailingslashit( esc_url_raw( trim( (string) ( $in['task_server'] ?? $d['task_server'] ) ), array( 'https', 'http' ) ) ) ?: $d['task_server'];

		$norms = sanitize_textarea_field( $in['norms'] ?? '' );
		if ( ! self::parse_norms( $norms ) ) {
			add_settings_error( self::OPTION, 'norms', 'Bảng chuẩn theo độ tuổi không hợp lệ nên đã giữ giá trị cũ. Mỗi dòng có dạng: 20-34: 0.55, 0.18' );
			$norms = self::get( 'norms' );
		}
		$out['norms'] = $norms;

		return $out;
	}

	/**
	 * "20-34: 0.55, 0.18" mỗi dòng → [ [min,max,mean,sd], ... ]. Trả về false nếu không dòng nào hợp lệ.
	 */
	public static function parse_norms( $text ) {
		$rows = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			if ( preg_match( '/^\s*(\d+)\s*-\s*(\d+)\s*:\s*([0-9.]+)\s*,\s*([0-9.]+)\s*$/', $line, $m ) ) {
				$mean = (float) $m[3];
				$sd   = (float) $m[4];
				if ( $m[1] <= $m[2] && $mean > 0 && $mean < 1 && $sd > 0.01 && $sd < 1 ) {
					$rows[] = array( (int) $m[1], (int) $m[2], $mean, $sd );
				}
			}
		}
		return $rows ? $rows : false;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = self::get();
		$n = self::OPTION;
		?>
		<div class="wrap tnp-iq-admin">
			<h1>Cài đặt Test IQ</h1>
			<?php settings_errors( self::OPTION ); ?>

			<div class="tnp-iq-box">
				<h2>Cách dùng</h2>
				<p><strong>Tạo bài test:</strong> vào <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . TNP_IQ_Questions::TAX . '&post_type=' . TNP_IQ_CPT ) ); ?>">Bài test</a>, đặt tên và chọn loại (IQ, EQ, DISC, MBTI, Holland). Khi thêm câu hỏi, chọn bài test ở khung bên phải. Chèn <code>[tnp_test test="duong-dan-bai-test"]</code> vào trang (bảng Bài test có sẵn shortcode để chép).</p>
				<p><strong>Bài IQ chung:</strong> <code>[tnp_iq_test]</code> dùng các câu IQ không thuộc bài test nào. Có thể ghi đè số câu và thời gian tối đa: <code>[tnp_test test="…" count="20" time="15"]</code>.</p>
				<p class="description">Các cài đặt bên dưới (số câu, thời gian, thưởng tốc độ, chuẩn theo độ tuổi…) chỉ dùng cho test IQ.</p>
				<p>Hiện có <strong><?php echo (int) wp_count_posts( TNP_IQ_CPT )->publish; ?></strong> câu hỏi đã xuất bản. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TNP_IQ_CPT ) ); ?>">Thêm câu hỏi</a></p>
				<?php TNP_IQ_Demo::render_button(); ?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'tnp_iq_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tnp-qc">Số câu mỗi bài</label></th>
						<td><input id="tnp-qc" type="number" min="0" name="<?php echo esc_attr( $n ); ?>[question_count]" value="<?php echo esc_attr( $o['question_count'] ); ?>" class="small-text"> <span class="description">0 = dùng tất cả câu hỏi đã xuất bản.</span></td>
					</tr>
					<tr>
						<th scope="row">Thứ tự câu hỏi</th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[order]" value="menu_order" <?php checked( $o['order'], 'menu_order' ); ?>> Theo thứ tự (trường “Thứ tự” của câu hỏi)</label><br>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[order]" value="random" <?php checked( $o['order'], 'random' ); ?>> Ngẫu nhiên mỗi lượt làm</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-tl">Thời gian tối đa (phút)</label></th>
						<td><input id="tnp-tl" type="number" min="0" name="<?php echo esc_attr( $n ); ?>[time_limit]" value="<?php echo esc_attr( $o['time_limit'] ); ?>" class="small-text"> <span class="description">Đồng hồ luôn đếm xuôi. Nếu đặt số phút, bài tự chuyển sang bước nhập tuổi khi hết thời gian. 0 = không giới hạn.</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-sr">Thời gian chuẩn mỗi câu</label></th>
						<td><input id="tnp-sr" type="number" min="5" max="600" name="<?php echo esc_attr( $n ); ?>[speed_ref]" value="<?php echo esc_attr( $o['speed_ref'] ); ?>" class="small-text"> giây <span class="description">Dùng để tính thưởng tốc độ: làm xong trong khoảng một nửa thời gian chuẩn (số câu × số giây này) thì được thưởng tối đa.</span></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-sb">Thưởng tốc độ (tối đa)</label></th>
						<td><input id="tnp-sb" type="number" min="0" max="15" name="<?php echo esc_attr( $n ); ?>[speed_bonus]" value="<?php echo esc_attr( $o['speed_bonus'] ); ?>" class="small-text"> điểm IQ <span class="description">Chỉ cộng khi đạt từ 60% điểm trở lên; làm càng nhanh cộng càng nhiều. 0 = tắt.</span></td>
					</tr>
					<tr>
						<th scope="row">Giới hạn điểm IQ</th>
						<td>
							từ <input type="number" name="<?php echo esc_attr( $n ); ?>[iq_min]" value="<?php echo esc_attr( $o['iq_min'] ); ?>" class="small-text">
							đến <input type="number" name="<?php echo esc_attr( $n ); ?>[iq_max]" value="<?php echo esc_attr( $o['iq_max'] ); ?>" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-norms">Chuẩn theo độ tuổi</label></th>
						<td>
							<textarea id="tnp-norms" name="<?php echo esc_attr( $n ); ?>[norms]" rows="9" cols="40" class="code"><?php echo esc_textarea( $o['norms'] ); ?></textarea>
							<p class="description">Mỗi dòng: <code>tuổi_từ-tuổi_đến: tỉ_lệ_trung_bình, độ_lệch_chuẩn</code>. Tỉ lệ là phần điểm đạt được (0–1, đã tính trọng số độ khó) của một người bình thường ở nhóm tuổi đó. Người đạt đúng tỉ lệ trung bình được IQ 100; mỗi độ lệch chuẩn tương ứng 15 điểm IQ.<br>Giá trị mặc định chỉ là ước lượng ban đầu. Khi đã có đủ lượt làm, xem cột “Tỉ lệ TB thực tế” ở trang <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&page=tnp-iq-results' ) ); ?>">Kết quả</a> để hiệu chỉnh.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-it">Tên bài test mặc định</label></th>
						<td><input id="tnp-it" type="text" name="<?php echo esc_attr( $n ); ?>[intro_title]" value="<?php echo esc_attr( $o['intro_title'] ); ?>" class="regular-text"> <p class="description">Tên của bài IQ chung [tnp_iq_test]. Các bài test khác dùng tên đặt trong mục Bài test.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-an">Ghi chú ở bước nhập tuổi</label></th>
						<td><textarea id="tnp-an" name="<?php echo esc_attr( $n ); ?>[age_note]" rows="2" cols="60"><?php echo esc_textarea( $o['age_note'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-ts">Web nhiệm vụ (Traffic Hila)</label></th>
						<td><input id="tnp-ts" type="url" class="regular-text" name="<?php echo esc_attr( $n ); ?>[task_server]" value="<?php echo esc_attr( $o['task_server'] ); ?>">
							<p class="description">Địa chỉ web cài plugin Traffic Hila. Bật “Yêu cầu nhiệm vụ” và điền Tag trong từng Bài test: người làm bài phải làm nhiệm vụ, nhập mã đúng mới xem được kết quả.</p></td>
					</tr>
				</table>
				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>
		</div>
		<?php
	}
}

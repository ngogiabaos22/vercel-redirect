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
			'time_limit'     => 15,
			'show_review'    => 1,
			'speed_bonus'    => 3,
			'iq_min'         => 55,
			'iq_max'         => 160,
			'norms'          => self::default_norms(),
			'intro_title'    => 'Test IQ miễn phí',
			'intro_text'     => 'Chuẩn bị giấy nháp, chọn nơi yên tĩnh. Bạn có thể bỏ qua câu khó và quay lại sau. Ở bước cuối, hãy nhập đúng tuổi để điểm IQ được quy đổi chính xác theo độ tuổi.',
			'age_note'       => 'Điểm IQ được so sánh với những người cùng độ tuổi, vì vậy hãy nhập đúng tuổi của bạn để có kết quả chính xác nhất.',
			'cta_text'       => '',
			'cta_url'        => '',
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $opts : ( $opts[ $key ] ?? null );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TNP_IQ_CPT,
			'Cài đặt Test IQ',
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
		$out['show_review']    = empty( $in['show_review'] ) ? 0 : 1;
		$out['speed_bonus']    = max( 0, min( 15, (int) ( $in['speed_bonus'] ?? $d['speed_bonus'] ) ) );
		$out['iq_min']         = max( 40, min( 100, (int) ( $in['iq_min'] ?? $d['iq_min'] ) ) );
		$out['iq_max']         = max( 100, min( 200, (int) ( $in['iq_max'] ?? $d['iq_max'] ) ) );
		$out['intro_title']    = sanitize_text_field( $in['intro_title'] ?? $d['intro_title'] );
		$out['intro_text']     = sanitize_textarea_field( $in['intro_text'] ?? $d['intro_text'] );
		$out['age_note']       = sanitize_textarea_field( $in['age_note'] ?? $d['age_note'] );
		$out['cta_text']       = sanitize_text_field( $in['cta_text'] ?? '' );
		$out['cta_url']        = esc_url_raw( $in['cta_url'] ?? '' );

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
				<p>Chèn shortcode <code>[tnp_iq_test]</code> vào trang làm bài (ví dụ trang <em>/bai-thi/test-iq-mien-phi/</em>). Có thể ghi đè số câu và thời gian: <code>[tnp_iq_test count="20" time="15"]</code>.</p>
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
						<th scope="row"><label for="tnp-tl">Thời gian (phút)</label></th>
						<td><input id="tnp-tl" type="number" min="0" name="<?php echo esc_attr( $n ); ?>[time_limit]" value="<?php echo esc_attr( $o['time_limit'] ); ?>" class="small-text"> <span class="description">0 = không giới hạn.</span></td>
					</tr>
					<tr>
						<th scope="row">Xem lại đáp án</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_review]" value="1" <?php checked( $o['show_review'], 1 ); ?>> Hiện đáp án đúng và giải thích sau khi nộp bài</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-sb">Thưởng tốc độ (tối đa)</label></th>
						<td><input id="tnp-sb" type="number" min="0" max="15" name="<?php echo esc_attr( $n ); ?>[speed_bonus]" value="<?php echo esc_attr( $o['speed_bonus'] ); ?>" class="small-text"> điểm IQ <span class="description">Chỉ cộng khi đúng từ 60% trở lên; làm càng nhanh cộng càng nhiều. 0 = tắt.</span></td>
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
						<th scope="row"><label for="tnp-it">Tiêu đề màn hình bắt đầu</label></th>
						<td><input id="tnp-it" type="text" name="<?php echo esc_attr( $n ); ?>[intro_title]" value="<?php echo esc_attr( $o['intro_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-ix">Hướng dẫn trước khi làm</label></th>
						<td><textarea id="tnp-ix" name="<?php echo esc_attr( $n ); ?>[intro_text]" rows="3" cols="60"><?php echo esc_textarea( $o['intro_text'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="tnp-an">Ghi chú ở bước nhập tuổi</label></th>
						<td><textarea id="tnp-an" name="<?php echo esc_attr( $n ); ?>[age_note]" rows="2" cols="60"><?php echo esc_textarea( $o['age_note'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row">Nút ở trang kết quả</th>
						<td>
							<input type="text" name="<?php echo esc_attr( $n ); ?>[cta_text]" value="<?php echo esc_attr( $o['cta_text'] ); ?>" placeholder="Chữ trên nút (để trống = ẩn)" class="regular-text"><br>
							<input type="url" name="<?php echo esc_attr( $n ); ?>[cta_url]" value="<?php echo esc_attr( $o['cta_url'] ); ?>" placeholder="https://..." class="regular-text" style="margin-top:6px">
						</td>
					</tr>
				</table>
				<?php submit_button( 'Lưu cài đặt' ); ?>
			</form>
		</div>
		<?php
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Câu hỏi IQ: custom post type, hộp nhập liệu trong trang sửa câu hỏi, cột danh sách.
 *
 * Mỗi câu hỏi (tiêu đề bài viết = đề bài) có một trong 3 kiểu:
 *  - grid:  lưới N×N ô ảnh, ô cuối cùng là "?" (giống bài ma trận Raven)
 *  - image: một ảnh đề bài duy nhất
 *  - text:  chỉ có chữ (dãy số, câu hỏi ngôn ngữ…)
 * và 4 đáp án A–D, mỗi đáp án là ảnh và/hoặc chữ.
 */
class TNP_IQ_Questions {

	const KEYS       = array( 'A', 'B', 'C', 'D' );
	const CATEGORIES = array(
		'logic'   => 'Tư duy logic',
		'number'  => 'Số học',
		'verbal'  => 'Ngôn ngữ',
		'spatial' => 'Không gian',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . TNP_IQ_CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'manage_' . TNP_IQ_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . TNP_IQ_CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	public static function activate() {
		self::register_cpt();
		flush_rewrite_rules();
	}

	public static function register_cpt() {
		register_post_type( TNP_IQ_CPT, array(
			'labels'          => array(
				'name'               => 'Câu hỏi IQ',
				'singular_name'      => 'Câu hỏi IQ',
				'menu_name'          => 'Test IQ',
				'all_items'          => 'Câu hỏi',
				'add_new'            => 'Thêm câu hỏi',
				'add_new_item'       => 'Thêm câu hỏi IQ',
				'edit_item'          => 'Sửa câu hỏi IQ',
				'search_items'       => 'Tìm câu hỏi',
				'not_found'          => 'Chưa có câu hỏi nào',
				'not_found_in_trash' => 'Thùng rác trống',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-lightbulb',
			'menu_position'   => 26,
			'supports'        => array( 'title', 'page-attributes' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	public static function title_placeholder( $text, $post ) {
		return TNP_IQ_CPT === $post->post_type ? 'Đề bài, ví dụ: Hình nào điền vào ô trống?' : $text;
	}

	/* ---------- Đọc dữ liệu ---------- */

	public static function get( $post_id ) {
		$type = get_post_meta( $post_id, '_tnp_type', true );
		$size = (int) get_post_meta( $post_id, '_tnp_grid', true );
		$opts = (array) get_post_meta( $post_id, '_tnp_options', true );
		$options = array();
		foreach ( self::KEYS as $k ) {
			$options[ $k ] = array(
				'img'  => (int) ( $opts[ $k ]['img'] ?? 0 ),
				'text' => (string) ( $opts[ $k ]['text'] ?? '' ),
			);
		}
		return array(
			'type'       => in_array( $type, array( 'grid', 'image', 'text' ), true ) ? $type : 'grid',
			'grid'       => in_array( $size, array( 2, 3, 4 ), true ) ? $size : 3,
			'cells'      => array_map( 'intval', (array) get_post_meta( $post_id, '_tnp_cells', true ) ),
			'image'      => (int) get_post_meta( $post_id, '_tnp_image', true ),
			'options'    => $options,
			'correct'    => (string) get_post_meta( $post_id, '_tnp_correct', true ),
			'explain'    => (string) get_post_meta( $post_id, '_tnp_explain', true ),
			'category'   => (string) get_post_meta( $post_id, '_tnp_category', true ) ?: 'logic',
			'difficulty' => (int) get_post_meta( $post_id, '_tnp_difficulty', true ) ?: 3,
		);
	}

	/** Câu hỏi đủ dữ liệu để đưa vào bài thi chưa. */
	public static function is_complete( $q ) {
		if ( ! in_array( $q['correct'], self::KEYS, true ) ) {
			return false;
		}
		foreach ( $q['options'] as $o ) {
			if ( ! $o['img'] && '' === trim( $o['text'] ) ) {
				return false;
			}
		}
		if ( 'grid' === $q['type'] ) {
			$need = $q['grid'] * $q['grid'] - 1;
			return count( array_filter( array_slice( $q['cells'], 0, $need ) ) ) === $need;
		}
		if ( 'image' === $q['type'] ) {
			return $q['image'] > 0;
		}
		return true;
	}

	/** Dữ liệu gửi xuống trình duyệt — KHÔNG có đáp án đúng. */
	public static function public_data( $post ) {
		$q   = self::get( $post->ID );
		$url = function ( $id, $size = 'medium' ) {
			return $id ? (string) wp_get_attachment_image_url( $id, $size ) : '';
		};
		$data = array(
			'id'      => $post->ID,
			'prompt'  => get_the_title( $post ),
			'type'    => $q['type'],
			'grid'    => $q['grid'],
			'cells'   => array(),
			'image'   => '',
			'options' => array(),
		);
		if ( 'grid' === $q['type'] ) {
			$need = $q['grid'] * $q['grid'] - 1;
			foreach ( array_slice( $q['cells'], 0, $need ) as $id ) {
				$data['cells'][] = $url( $id );
			}
		} elseif ( 'image' === $q['type'] ) {
			$data['image'] = $url( $q['image'], 'large' );
		}
		foreach ( $q['options'] as $k => $o ) {
			$data['options'][] = array(
				'key'  => $k,
				'img'  => $url( $o['img'] ),
				'text' => $o['text'],
			);
		}
		return $data;
	}

	/* ---------- Trang sửa câu hỏi ---------- */

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || TNP_IQ_CPT !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'tnp-iq-admin', TNP_IQ_URL . 'assets/admin.css', array(), TNP_IQ_VERSION );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'tnp-iq-admin', TNP_IQ_URL . 'assets/admin.js', array( 'jquery' ), TNP_IQ_VERSION, true );
		}
	}

	public static function meta_boxes() {
		add_meta_box( 'tnp_iq_question', 'Nội dung câu hỏi', array( __CLASS__, 'render_box' ), TNP_IQ_CPT, 'normal', 'high' );
		add_meta_box( 'tnp_iq_meta', 'Phân loại', array( __CLASS__, 'render_side' ), TNP_IQ_CPT, 'side', 'default' );
	}

	private static function slot( $name, $id, $label = '' ) {
		$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
		?>
		<div class="tnp-slot<?php echo $src ? ' has-img' : ''; ?>">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo (int) $id; ?>">
			<button type="button" class="tnp-slot-pick" title="Chọn ảnh">
				<?php if ( $label ) : ?><span class="tnp-slot-label"><?php echo esc_html( $label ); ?></span><?php endif; ?>
				<img src="<?php echo esc_url( $src ); ?>" alt="">
				<span class="tnp-slot-empty">+ Ảnh</span>
			</button>
			<button type="button" class="tnp-slot-clear" title="Xoá ảnh">&times;</button>
		</div>
		<?php
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'tnp_iq_save', 'tnp_iq_nonce' );
		$q = self::get( $post->ID );
		?>
		<div class="tnp-iq-edit" data-grid="<?php echo (int) $q['grid']; ?>">
			<p class="tnp-row">
				<strong>Kiểu đề bài:</strong>
				<label><input type="radio" name="tnp_type" value="grid" <?php checked( $q['type'], 'grid' ); ?>> Lưới hình (ô cuối là “?”)</label>
				<label><input type="radio" name="tnp_type" value="image" <?php checked( $q['type'], 'image' ); ?>> Một ảnh đề bài</label>
				<label><input type="radio" name="tnp_type" value="text" <?php checked( $q['type'], 'text' ); ?>> Chỉ có chữ</label>
			</p>

			<div class="tnp-pane" data-pane="grid">
				<p class="tnp-row">
					<label><strong>Kích thước lưới:</strong>
						<select name="tnp_grid" class="tnp-grid-size">
							<?php foreach ( array( 2, 3, 4 ) as $s ) : ?>
								<option value="<?php echo $s; ?>" <?php selected( $q['grid'], $s ); ?>><?php echo $s . ' × ' . $s; ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<span class="description">Chọn ảnh cho từng ô theo thứ tự từ trái sang phải, trên xuống dưới. Ô cuối luôn là “?”.</span>
				</p>
				<div class="tnp-grid">
					<?php for ( $i = 0; $i < 15; $i++ ) : ?>
						<?php self::slot( 'tnp_cells[' . $i . ']', $q['cells'][ $i ] ?? 0 ); ?>
					<?php endfor; ?>
					<div class="tnp-slot tnp-q">?</div>
				</div>
			</div>

			<div class="tnp-pane" data-pane="image">
				<p class="description">Ảnh chứa toàn bộ đề bài (ví dụ một hình ma trận đã ghép sẵn).</p>
				<div class="tnp-single"><?php self::slot( 'tnp_image', $q['image'] ); ?></div>
			</div>

			<h3>Đáp án</h3>
			<p class="description">Mỗi đáp án có thể là ảnh, chữ hoặc cả hai. Đánh dấu đáp án đúng ở ô tròn bên dưới.</p>
			<div class="tnp-options">
				<?php foreach ( $q['options'] as $k => $o ) : ?>
					<div class="tnp-option<?php echo $q['correct'] === $k ? ' is-correct' : ''; ?>">
						<?php self::slot( 'tnp_options[' . $k . '][img]', $o['img'], $k ); ?>
						<input type="text" name="tnp_options[<?php echo esc_attr( $k ); ?>][text]" value="<?php echo esc_attr( $o['text'] ); ?>" placeholder="Chữ (tuỳ chọn)">
						<label class="tnp-correct"><input type="radio" name="tnp_correct" value="<?php echo esc_attr( $k ); ?>" <?php checked( $q['correct'], $k ); ?>> Đáp án đúng</label>
					</div>
				<?php endforeach; ?>
			</div>

			<h3><label for="tnp-explain">Giải thích đáp án</label></h3>
			<textarea id="tnp-explain" name="tnp_explain" rows="3" class="large-text" placeholder="Ví dụ: Mỗi hàng, hình xoay thêm 20° theo chiều kim đồng hồ."><?php echo esc_textarea( $q['explain'] ); ?></textarea>

			<?php if ( 'publish' === $post->post_status && ! self::is_complete( $q ) ) : ?>
				<p class="tnp-warn">Câu hỏi này chưa đủ dữ liệu (thiếu ảnh ô lưới, ảnh/chữ đáp án hoặc chưa chọn đáp án đúng) nên sẽ <strong>không</strong> xuất hiện trong bài thi.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_side( $post ) {
		$q = self::get( $post->ID );
		?>
		<p><label for="tnp-cat"><strong>Nhóm năng lực</strong></label><br>
			<select id="tnp-cat" name="tnp_category" class="widefat">
				<?php foreach ( self::CATEGORIES as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $q['category'], $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label for="tnp-dif"><strong>Độ khó</strong></label><br>
			<select id="tnp-dif" name="tnp_difficulty" class="widefat">
				<?php foreach ( array( 1 => '1 – Rất dễ', 2 => '2 – Dễ', 3 => '3 – Trung bình', 4 => '4 – Khó', 5 => '5 – Rất khó' ) as $v => $label ) : ?>
					<option value="<?php echo $v; ?>" <?php selected( $q['difficulty'], $v ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description">Câu khó được tính nhiều điểm hơn (trọng số = độ khó).</span>
		</p>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['tnp_iq_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tnp_iq_nonce'] ), 'tnp_iq_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$p = wp_unslash( $_POST );

		$type = in_array( $p['tnp_type'] ?? '', array( 'grid', 'image', 'text' ), true ) ? $p['tnp_type'] : 'grid';
		$grid = in_array( (int) ( $p['tnp_grid'] ?? 3 ), array( 2, 3, 4 ), true ) ? (int) $p['tnp_grid'] : 3;

		$cells = array();
		for ( $i = 0; $i < 15; $i++ ) {
			$cells[] = absint( $p['tnp_cells'][ $i ] ?? 0 );
		}

		$options = array();
		foreach ( self::KEYS as $k ) {
			$options[ $k ] = array(
				'img'  => absint( $p['tnp_options'][ $k ]['img'] ?? 0 ),
				'text' => sanitize_text_field( $p['tnp_options'][ $k ]['text'] ?? '' ),
			);
		}

		$correct    = in_array( $p['tnp_correct'] ?? '', self::KEYS, true ) ? $p['tnp_correct'] : '';
		$category   = array_key_exists( $p['tnp_category'] ?? '', self::CATEGORIES ) ? $p['tnp_category'] : 'logic';
		$difficulty = max( 1, min( 5, (int) ( $p['tnp_difficulty'] ?? 3 ) ) );

		update_post_meta( $post_id, '_tnp_type', $type );
		update_post_meta( $post_id, '_tnp_grid', $grid );
		update_post_meta( $post_id, '_tnp_cells', $cells );
		update_post_meta( $post_id, '_tnp_image', absint( $p['tnp_image'] ?? 0 ) );
		update_post_meta( $post_id, '_tnp_options', $options );
		update_post_meta( $post_id, '_tnp_correct', $correct );
		update_post_meta( $post_id, '_tnp_explain', sanitize_textarea_field( $p['tnp_explain'] ?? '' ) );
		update_post_meta( $post_id, '_tnp_category', $category );
		update_post_meta( $post_id, '_tnp_difficulty', $difficulty );
	}

	/* ---------- Danh sách câu hỏi ---------- */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			if ( 'title' === $k ) {
				$new['tnp_thumb'] = 'Hình';
				$new[ $k ]        = 'Đề bài';
				$new['tnp_cat']   = 'Nhóm';
				$new['tnp_dif']   = 'Độ khó';
				$new['tnp_ans']   = 'Đáp án';
				$new['tnp_order'] = 'Thứ tự';
			} else {
				$new[ $k ] = $v;
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		$q = self::get( $post_id );
		switch ( $col ) {
			case 'tnp_thumb':
				$id = 'grid' === $q['type'] ? ( $q['cells'][0] ?? 0 ) : $q['image'];
				echo $id ? wp_get_attachment_image( $id, array( 48, 48 ) ) : '<span class="tnp-thumb-text">Aa</span>';
				break;
			case 'tnp_cat':
				echo esc_html( self::CATEGORIES[ $q['category'] ] ?? '' );
				break;
			case 'tnp_dif':
				echo esc_html( str_repeat( '★', $q['difficulty'] ) );
				break;
			case 'tnp_ans':
				echo $q['correct'] ? '<strong>' . esc_html( $q['correct'] ) . '</strong>' : '';
				if ( ! self::is_complete( $q ) ) {
					echo ' <span class="tnp-badge-warn">Thiếu dữ liệu</span>';
				}
				break;
			case 'tnp_order':
				echo (int) get_post_field( 'menu_order', $post_id );
				break;
		}
	}
}

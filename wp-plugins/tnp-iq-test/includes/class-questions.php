<?php
defined( 'ABSPATH' ) || exit;

/**
 * Câu hỏi IQ: custom post type, hộp nhập liệu trong trang sửa câu hỏi, cột danh sách.
 *
 * Mỗi câu hỏi (tiêu đề bài viết = đề bài) có một trong 2 kiểu:
 *  - question: câu hỏi bằng chữ, có thể thêm nội dung chi tiết và ảnh minh hoạ
 *  - image:    đề bài nằm trong một ảnh (ví dụ ma trận hình), ảnh là bắt buộc
 * (kiểu "grid" — lưới N×N ô ảnh — chỉ còn để hiển thị câu tạo từ bản cũ)
 * và 2–8 đáp án A–H, mỗi đáp án là ảnh và/hoặc chữ.
 */
class TNP_IQ_Questions {

	const TAX        = 'tnp_iq_set';
	const KEYS        = array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H' );
	const MIN_OPTIONS = 2;
	const MAX_OPTIONS = 8;
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

		// Bộ đề: gom câu hỏi thành các bài test riêng, mỗi bài một shortcode [tnp_iq_test test="slug"].
		register_taxonomy( self::TAX, TNP_IQ_CPT, array(
			'labels'            => array(
				'name'          => 'Bộ đề',
				'singular_name' => 'Bộ đề',
				'menu_name'     => 'Bộ đề',
				'all_items'     => 'Tất cả bộ đề',
				'edit_item'     => 'Sửa bộ đề',
				'add_new_item'  => 'Thêm bộ đề',
				'new_item_name' => 'Tên bộ đề mới',
				'search_items'  => 'Tìm bộ đề',
				'not_found'     => 'Chưa có bộ đề nào',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		) );
	}

	/** Tên hiển thị của một bài test theo slug bộ đề ('' = tất cả câu hỏi). */
	public static function test_name( $slug ) {
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, self::TAX );
			if ( $term ) {
				return $term->name;
			}
		}
		return TNP_IQ_Settings::get( 'intro_title' );
	}

	public static function title_placeholder( $text, $post ) {
		return TNP_IQ_CPT === $post->post_type ? 'Câu hỏi, ví dụ: Hình nào điền vào ô trống?' : $text;
	}

	/* ---------- Đọc dữ liệu ---------- */

	/** Chữ cái đáp án theo vị trí: 0 → A, 1 → B… */
	public static function letter( $i ) {
		return self::KEYS[ $i ] ?? '';
	}

	public static function get( $post_id ) {
		$type = get_post_meta( $post_id, '_tnp_type', true );
		if ( 'text' === $type || '' === $type ) {
			$type = 'question'; // "Chỉ có chữ" của bản cũ → "Câu hỏi".
		}
		$size = (int) get_post_meta( $post_id, '_tnp_grid', true );

		$saved   = get_post_meta( $post_id, '_tnp_options', true );
		$options = array();
		if ( is_array( $saved ) && $saved ) {
			foreach ( array_values( $saved ) as $i => $o ) {
				if ( $i >= self::MAX_OPTIONS ) {
					break;
				}
				$options[ self::letter( $i ) ] = array(
					'img'  => (int) ( $o['img'] ?? 0 ),
					'text' => (string) ( $o['text'] ?? '' ),
				);
			}
		}
		// Câu hỏi mới: mặc định 4 đáp án trống.
		for ( $i = count( $options ); $i < 4 && ! $saved; $i++ ) {
			$options[ self::letter( $i ) ] = array( 'img' => 0, 'text' => '' );
		}

		return array(
			'type'       => in_array( $type, array( 'question', 'image', 'grid' ), true ) ? $type : 'question',
			'grid'       => in_array( $size, array( 2, 3, 4 ), true ) ? $size : 3,
			'cells'      => array_map( 'intval', (array) get_post_meta( $post_id, '_tnp_cells', true ) ),
			'body'       => (string) get_post_meta( $post_id, '_tnp_body', true ),
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
		if ( count( $q['options'] ) < self::MIN_OPTIONS || ! isset( $q['options'][ $q['correct'] ] ) ) {
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
			'body'    => $q['body'],
			'type'    => $q['type'],
			'grid'    => $q['grid'],
			'cells'   => array(),
			'image'   => $url( $q['image'], 'large' ),
			'options' => array(),
		);
		if ( 'grid' === $q['type'] ) { // Câu dạng lưới tạo từ bản cũ vẫn hiển thị được.
			$need = $q['grid'] * $q['grid'] - 1;
			foreach ( array_slice( $q['cells'], 0, $need ) as $id ) {
				$data['cells'][] = $url( $id );
			}
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
			wp_localize_script( 'tnp-iq-admin', 'tnpIqAdmin', array(
				'keys' => self::KEYS,
				'min'  => self::MIN_OPTIONS,
				'max'  => self::MAX_OPTIONS,
			) );
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

	/** Một dòng đáp án trong trang soạn câu hỏi. $i = vị trí (0, 1, 2…). */
	private static function option_row( $i, $o, $is_correct ) {
		$k = self::letter( $i );
		?>
		<div class="tnp-option<?php echo $is_correct ? ' is-correct' : ''; ?>">
			<span class="tnp-option-key"><?php echo esc_html( $k ); ?></span>
			<?php self::slot( 'tnp_options[' . $i . '][img]', $o['img'] ); ?>
			<div class="tnp-option-fields">
				<input type="text" name="tnp_options[<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( $o['text'] ); ?>" placeholder="Nội dung đáp án (có thể để trống nếu đã có ảnh)">
				<label class="tnp-correct"><input type="radio" name="tnp_correct" value="<?php echo (int) $i; ?>" <?php checked( $is_correct ); ?>> Đáp án đúng</label>
			</div>
			<button type="button" class="button-link tnp-option-del" title="Xoá đáp án này">Xoá</button>
		</div>
		<?php
	}

	public static function render_box( $post ) {
		wp_nonce_field( 'tnp_iq_save', 'tnp_iq_nonce' );
		$q = self::get( $post->ID );
		?>
		<div class="tnp-iq-edit">
			<p class="tnp-row">
				<strong>Kiểu đề bài:</strong>
				<label><input type="radio" name="tnp_type" value="question" <?php checked( $q['type'], 'question' ); ?>> Câu hỏi <span class="description">(chữ, có thể kèm ảnh minh hoạ)</span></label>
				<label><input type="radio" name="tnp_type" value="image" <?php checked( $q['type'], 'image' ); ?>> Ảnh đề bài <span class="description">(đề nằm trong ảnh, ví dụ ma trận hình)</span></label>
				<?php if ( 'grid' === $q['type'] ) : // Chỉ còn cho câu tạo từ bản cũ. ?>
					<label><input type="radio" name="tnp_type" value="grid" checked> Lưới hình (bản cũ)</label>
				<?php endif; ?>
			</p>

			<div class="tnp-body-field">
				<label for="tnp-body"><strong>Nội dung câu hỏi</strong> <span class="description">— tuỳ chọn, hiện dưới tiêu đề. Dùng khi đề dài: đoạn văn, dữ kiện, dãy số…</span></label>
				<textarea id="tnp-body" name="tnp_body" rows="4" class="large-text" placeholder="Ví dụ: An cao hơn Bình. Bình cao hơn Cường. Ai thấp nhất?"><?php echo esc_textarea( $q['body'] ); ?></textarea>
			</div>

			<div class="tnp-image-field">
				<strong class="tnp-image-label" data-question="Ảnh minh hoạ (tuỳ chọn)" data-image="Ảnh đề bài (bắt buộc)">Ảnh</strong>
				<div class="tnp-single"><?php self::slot( 'tnp_image', $q['image'] ); ?></div>
			</div>

			<?php if ( 'grid' === $q['type'] ) : ?>
				<div class="tnp-legacy-grid">
					<input type="hidden" name="tnp_grid" value="<?php echo (int) $q['grid']; ?>">
					<?php foreach ( $q['cells'] as $i => $id ) : ?>
						<input type="hidden" name="tnp_cells[<?php echo (int) $i; ?>]" value="<?php echo (int) $id; ?>">
					<?php endforeach; ?>
					<p class="description">Câu này dùng kiểu Lưới hình của bản cũ và vẫn hiển thị bình thường. Muốn sửa ảnh, hãy ghép lưới thành một ảnh rồi chuyển sang kiểu “Ảnh đề bài”.</p>
				</div>
			<?php endif; ?>

			<h3>Đáp án</h3>
			<p class="description">Từ <?php echo (int) self::MIN_OPTIONS; ?> đến <?php echo (int) self::MAX_OPTIONS; ?> đáp án. Mỗi đáp án là chữ, ảnh hoặc cả hai. Chọn “Đáp án đúng” ở một đáp án.</p>
			<div class="tnp-options">
				<?php $i = 0; foreach ( $q['options'] as $k => $o ) : ?>
					<?php self::option_row( $i, $o, $q['correct'] === $k ); ?>
				<?php $i++; endforeach; ?>
			</div>
			<p><button type="button" class="button tnp-option-add">+ Thêm đáp án</button></p>
			<script type="text/html" id="tmpl-tnp-option"><?php self::option_row( 99, array( 'img' => 0, 'text' => '' ), false ); ?></script>

			<h3><label for="tnp-explain">Giải thích đáp án</label> <span class="description" style="font-weight:400">(ghi chú nội bộ, không hiện cho người làm bài)</span></h3>
			<textarea id="tnp-explain" name="tnp_explain" rows="3" class="large-text" placeholder="Ví dụ: Mỗi hàng, hình xoay thêm 20° theo chiều kim đồng hồ."><?php echo esc_textarea( $q['explain'] ); ?></textarea>

			<?php if ( 'publish' === $post->post_status && ! self::is_complete( $q ) ) : ?>
				<p class="tnp-warn">Câu hỏi này chưa đủ dữ liệu (thiếu ảnh đề bài, có đáp án trống, ít hơn <?php echo (int) self::MIN_OPTIONS; ?> đáp án hoặc chưa chọn đáp án đúng) nên sẽ <strong>không</strong> xuất hiện trong bài thi.</p>
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

		$type = in_array( $p['tnp_type'] ?? '', array( 'question', 'image', 'grid' ), true ) ? $p['tnp_type'] : 'question';

		// Đáp án: giữ theo thứ tự trên màn hình, bỏ dòng trống hoàn toàn, đánh lại chữ cái A, B, C…
		$options     = array();
		$correct     = '';
		$correct_idx = isset( $p['tnp_correct'] ) ? (int) $p['tnp_correct'] : -1;
		foreach ( (array) ( $p['tnp_options'] ?? array() ) as $i => $o ) {
			$img  = absint( $o['img'] ?? 0 );
			$text = sanitize_text_field( $o['text'] ?? '' );
			if ( ! $img && '' === $text ) {
				continue;
			}
			if ( count( $options ) >= self::MAX_OPTIONS ) {
				break;
			}
			$k             = self::letter( count( $options ) );
			$options[ $k ] = array( 'img' => $img, 'text' => $text );
			if ( (int) $i === $correct_idx ) {
				$correct = $k;
			}
		}

		$category   = array_key_exists( $p['tnp_category'] ?? '', self::CATEGORIES ) ? $p['tnp_category'] : 'logic';
		$difficulty = max( 1, min( 5, (int) ( $p['tnp_difficulty'] ?? 3 ) ) );

		update_post_meta( $post_id, '_tnp_type', $type );
		update_post_meta( $post_id, '_tnp_body', sanitize_textarea_field( $p['tnp_body'] ?? '' ) );
		update_post_meta( $post_id, '_tnp_image', absint( $p['tnp_image'] ?? 0 ) );
		update_post_meta( $post_id, '_tnp_options', $options );
		update_post_meta( $post_id, '_tnp_correct', $correct );
		update_post_meta( $post_id, '_tnp_explain', sanitize_textarea_field( $p['tnp_explain'] ?? '' ) );
		update_post_meta( $post_id, '_tnp_category', $category );
		update_post_meta( $post_id, '_tnp_difficulty', $difficulty );
		if ( 'grid' === $type ) {
			update_post_meta( $post_id, '_tnp_grid', in_array( (int) ( $p['tnp_grid'] ?? 3 ), array( 2, 3, 4 ), true ) ? (int) $p['tnp_grid'] : 3 );
			update_post_meta( $post_id, '_tnp_cells', array_map( 'absint', (array) ( $p['tnp_cells'] ?? array() ) ) );
		}
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
				$id = $q['image'] ?: ( 'grid' === $q['type'] ? ( $q['cells'][0] ?? 0 ) : 0 );
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

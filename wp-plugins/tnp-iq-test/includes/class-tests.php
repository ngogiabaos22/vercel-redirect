<?php
defined( 'ABSPATH' ) || exit;

/**
 * Các loại bài test và cách chấm.
 *
 * Mỗi "Bài test" là một term của taxonomy TNP_IQ_Questions::TAX, có term meta:
 *  - tnp_type  : iq | eq | disc | mbti | holland
 *
 * IQ chấm theo đáp án đúng (TNP_IQ_Scoring). Các loại còn lại chấm theo "nhóm" và "điểm"
 * gắn trên từng đáp án: người làm chọn đáp án nào thì cộng điểm của đáp án đó vào nhóm của nó.
 */
class TNP_IQ_Tests {

	public static function init() {
		$tax = TNP_IQ_Questions::TAX;
		add_action( "{$tax}_add_form_fields", array( __CLASS__, 'term_add_fields' ) );
		add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'term_edit_fields' ) );
		add_action( "{$tax}_edit_form", array( __CLASS__, 'term_questions' ) );
		add_action( "created_{$tax}", array( __CLASS__, 'term_save' ) );
		add_action( "edited_{$tax}", array( __CLASS__, 'term_save' ) );
		add_filter( "manage_edit-{$tax}_columns", array( __CLASS__, 'term_columns' ) );
		add_filter( "manage_{$tax}_custom_column", array( __CLASS__, 'term_column' ), 10, 3 );
		add_action( 'wp_ajax_tnp_task_toggle', array( __CLASS__, 'ajax_task_toggle' ) );
		add_action( 'admin_footer-edit-tags.php', array( __CLASS__, 'task_toggle_script' ) );
	}

	/* ---------- Định nghĩa loại test ---------- */

	public static function types() {
		return array(
			'iq'      => array(
				'label' => 'IQ – Chỉ số thông minh',
				'short' => 'IQ',
				'dims'  => array(),
				'help'  => 'Mỗi câu có một đáp án đúng.',
			),
			'eq'      => array(
				'label' => 'EQ – Trí tuệ cảm xúc',
				'short' => 'EQ',
				'dims'  => array(
					'sa' => 'Tự nhận thức',
					'sr' => 'Tự điều chỉnh',
					'mo' => 'Động lực',
					'em' => 'Đồng cảm',
					'ss' => 'Kỹ năng xã hội',
				),
				'help'  => 'Gán mỗi đáp án một số điểm (ví dụ 1–5: phản ứng càng trưởng thành về cảm xúc càng nhiều điểm) và nhóm năng lực EQ mà câu hỏi đo.',
			),
			'disc'    => array(
				'label' => 'DISC – Phong cách hành vi',
				'short' => 'DISC',
				'dims'  => array(
					'D' => 'D – Thủ lĩnh (Dominance)',
					'I' => 'I – Ảnh hưởng (Influence)',
					'S' => 'S – Kiên định (Steadiness)',
					'C' => 'C – Tuân thủ (Compliance)',
				),
				'help'  => 'Mỗi đáp án thuộc một nhóm D, I, S hoặc C (thường mỗi câu có 4 đáp án, mỗi nhóm một đáp án). Điểm mặc định 1.',
			),
			'mbti'    => array(
				'label' => 'MBTI – 16 nhóm tính cách',
				'short' => 'MBTI',
				'dims'  => array(
					'E' => 'E – Hướng ngoại',
					'I' => 'I – Hướng nội',
					'S' => 'S – Giác quan',
					'N' => 'N – Trực giác',
					'T' => 'T – Lý trí',
					'F' => 'F – Cảm xúc',
					'J' => 'J – Nguyên tắc',
					'P' => 'P – Linh hoạt',
				),
				'help'  => 'Mỗi câu thường có 2 đáp án thuộc hai cực của cùng một cặp (E/I, S/N, T/F hoặc J/P). Nên có số câu đều nhau cho 4 cặp.',
			),
			'holland' => array(
				'label' => 'Holland – Định hướng nghề nghiệp',
				'short' => 'Holland',
				'dims'  => array(
					'R' => 'R – Kỹ thuật (Realistic)',
					'I' => 'I – Nghiên cứu (Investigative)',
					'A' => 'A – Nghệ thuật (Artistic)',
					'S' => 'S – Xã hội (Social)',
					'E' => 'E – Quản lý (Enterprising)',
					'C' => 'C – Nghiệp vụ (Conventional)',
				),
				'help'  => 'Mỗi câu là một hoạt động thuộc một nhóm R, I, A, S, E hoặc C; các đáp án là mức độ thích (ví dụ Rất thích = 3 … Không thích = 0), cùng nhóm với câu.',
			),
		);
	}

	public static function type_of( $slug ) {
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
			if ( $term ) {
				$t = get_term_meta( $term->term_id, 'tnp_type', true );
				return isset( self::types()[ $t ] ) ? $t : 'iq';
			}
		}
		return 'iq';
	}

	/** Cách trình bày: 'step' (từng câu, bấm Câu tiếp) hoặc 'list' (dải câu: tất cả câu trên một trang). */
	public static function layout_of( $slug ) {
		$term = '' !== $slug ? get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX ) : null;
		return $term && 'list' === get_term_meta( $term->term_id, 'tnp_layout', true ) ? 'list' : 'step';
	}

	/** Cho chọn nhiều đáp án mỗi câu (chỉ test tính cách; IQ luôn một đáp án đúng). */
	public static function multi_of( $slug ) {
		if ( '' === $slug || 'iq' === self::type_of( $slug ) ) {
			return false;
		}
		$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
		return $term && '1' === (string) get_term_meta( $term->term_id, 'tnp_multi', true );
	}

	private static function display_fields( $layout, $multi ) {
		?>
		<select name="tnp_layout" id="tnp-layout" style="max-width:320px">
			<option value="step" <?php selected( $layout, 'step' ); ?>>Từng câu (bấm “Câu tiếp”)</option>
			<option value="list" <?php selected( $layout, 'list' ); ?>>Dải câu (tất cả câu trên một trang, cuộn để làm)</option>
		</select>
		<p><label><input type="checkbox" name="tnp_multi" value="1" <?php checked( $multi ); ?> style="width:auto"> Cho chọn nhiều đáp án mỗi câu</label></p>
		<p class="description">Chọn nhiều: điểm của mọi đáp án được chọn đều được cộng (vd Holland: chọn mọi hoạt động bạn thích). Không áp dụng cho bài IQ.</p>
		<?php
	}

	/** Bài test có bật xáo trộn thứ tự đáp án không. */
	public static function shuffle_of( $slug ) {
		if ( '' === $slug ) {
			return false;
		}
		$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
		return $term && '1' === (string) get_term_meta( $term->term_id, 'tnp_shuffle', true );
	}

	/** Loại test của một câu hỏi = loại của bài test (term) đầu tiên nó thuộc về. */
	/**
	 * Yêu cầu nhiệm vụ của bài test: null nếu tắt, ngược lại array( 'tag' => … ).
	 * Bật: người làm bài phải làm nhiệm vụ (Traffic Hila) và nhập mã đúng mới xem được kết quả.
	 */
	public static function task_of( $slug ) {
		if ( '' === $slug ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
		if ( ! $term || '1' !== (string) get_term_meta( $term->term_id, 'tnp_task_on', true ) ) {
			return null;
		}
		return array( 'tag' => (string) get_term_meta( $term->term_id, 'tnp_task_tag', true ) );
	}

	private static function task_fields( $on, $tag ) {
		?>
		<label class="tnp-switch"><input type="checkbox" name="tnp_task_on" value="1" <?php checked( $on ); ?>><span></span> Yêu cầu làm nhiệm vụ trước khi xem kết quả</label>
		<p><input type="text" name="tnp_task_tag" id="tnp-task-tag" value="<?php echo esc_attr( $tag ); ?>" placeholder="Tag nhiệm vụ, vd: iq" style="max-width:260px"></p>
		<p class="description">Tag trùng với “Tag phân loại” của nhiệm vụ bên Traffic Hila (để trống = lấy nhiệm vụ bất kỳ). Tắt công tắc để bỏ yêu cầu mà vẫn giữ Tag.</p>
		<?php
	}

	public static function type_of_question( $post_id ) {
		$terms = get_the_terms( $post_id, TNP_IQ_Questions::TAX );
		return $terms && ! is_wp_error( $terms ) ? self::type_of( $terms[0]->slug ) : 'iq';
	}

	/* ---------- Trang Bài test (term) ---------- */

	private static function type_select( $current ) {
		echo '<select name="tnp_type" id="tnp-type">';
		foreach ( self::types() as $k => $t ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $current, $k, false ), esc_html( $t['label'] ) );
		}
		echo '</select>';
	}

	public static function term_add_fields() {
		wp_nonce_field( 'tnp_term', 'tnp_term_nonce' );
		?>
		<div class="form-field">
			<label for="tnp-type">Loại bài test</label>
			<?php self::type_select( 'iq' ); ?>
			<p>Quyết định cách chấm điểm và trang kết quả. Nên chọn trước khi thêm câu hỏi.</p>
		</div>
		<div class="form-field">
			<label><input type="checkbox" name="tnp_shuffle" value="1" checked style="width:auto"> Xáo trộn thứ tự đáp án mỗi lượt làm</label>
			<p>Nên bật cho EQ, DISC, Holland để người làm không đoán theo vị trí đáp án.</p>
		</div>
		<div class="form-field">
			<label for="tnp-layout">Cách trình bày</label>
			<?php self::display_fields( 'step', false ); ?>
		</div>
		<div class="form-field tnp-task-field">
			<label>Nhiệm vụ</label>
			<?php self::task_fields( false, '' ); ?>
		</div>
		<?php
	}

	public static function term_edit_fields( $term ) {
		wp_nonce_field( 'tnp_term', 'tnp_term_nonce' );
		$type = get_term_meta( $term->term_id, 'tnp_type', true ) ?: 'iq';
		?>
		<tr class="form-field">
			<th scope="row"><label for="tnp-type">Loại bài test</label></th>
			<td><?php self::type_select( $type ); ?>
				<p class="description">Đổi loại khi đã có câu hỏi: nhớ kiểm tra lại phần chấm điểm của từng đáp án.</p></td>
		</tr>
		<tr class="form-field">
			<th scope="row">Thứ tự đáp án</th>
			<td><label><input type="checkbox" name="tnp_shuffle" value="1" <?php checked( '1', (string) get_term_meta( $term->term_id, 'tnp_shuffle', true ) ); ?> style="width:auto"> Xáo trộn thứ tự đáp án mỗi lượt làm</label>
				<p class="description">Nhãn A, B, C… vẫn theo thứ tự hiển thị; máy chủ chấm theo đáp án gốc.</p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="tnp-layout">Cách trình bày</label></th>
			<td><?php self::display_fields( 'list' === get_term_meta( $term->term_id, 'tnp_layout', true ) ? 'list' : 'step', '1' === (string) get_term_meta( $term->term_id, 'tnp_multi', true ) ); ?></td>
		</tr>
		<tr class="form-field tnp-task-field">
			<th scope="row">Nhiệm vụ</th>
			<td><?php self::task_fields( '1' === (string) get_term_meta( $term->term_id, 'tnp_task_on', true ), (string) get_term_meta( $term->term_id, 'tnp_task_tag', true ) ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row">Shortcode</th>
			<td><code>[tnp_test test="<?php echo esc_html( $term->slug ); ?>"]</code></td>
		</tr>
		<?php
	}

	/** Trang Sửa bài test: toàn bộ câu hỏi đang thuộc bài (mọi trạng thái trừ thùng rác), theo thứ tự làm bài. */
	public static function term_questions( $term ) {
		$ids = get_posts( array(
			'post_type'      => TNP_IQ_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $term->term_id ) ),
		) );
		$ready = 0;
		$rows  = array();
		foreach ( $ids as $id ) {
			$q      = TNP_IQ_Questions::get( $id );
			$status = get_post_status( $id );
			$ok     = TNP_IQ_Questions::is_complete( $q );
			if ( 'publish' === $status && $ok ) {
				$ready++;
			}
			$rows[] = array( $id, $q, $status, $ok );
		}
		$list_url = admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&' . TNP_IQ_Questions::TAX . '=' . $term->slug );
		$new_url  = admin_url( 'post-new.php?post_type=' . TNP_IQ_CPT . '&tnp_test=' . $term->term_id );
		$is_iq    = 'iq' === self::type_of( $term->slug );
		?>
		<div class="tnp-tq">
			<div class="tnp-tq-head">
				<div>
					<h2>Câu hỏi trong bài test <span class="tnp-tq-count"><?php echo count( $ids ); ?></span></h2>
					<p>Đang dùng trong bài thi: <strong><?php echo (int) $ready; ?>/<?php echo count( $ids ); ?></strong> câu. Câu nháp hoặc thiếu dữ liệu không được đưa vào bài.</p>
				</div>
				<div class="tnp-tq-actions">
					<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>">+ Thêm câu hỏi</a>
					<a class="button" href="<?php echo esc_url( $list_url ); ?>">Quản lý câu hỏi</a>
					<a class="button" href="<?php echo esc_url( TNP_IQ_Json::export_url( $term->term_id ) ); ?>">Xuất JSON</a>
				</div>
			</div>
			<?php if ( ! $ids ) : ?>
				<p class="tnp-tq-empty">Chưa có câu hỏi nào. Bấm “+ Thêm câu hỏi” hoặc dùng Trắc nghiệm → Nhập câu hỏi.</p>
			<?php else : ?>
			<table class="tnp-tq-table">
				<thead><tr>
					<th class="c-idx">#</th>
					<th class="c-img">Hình</th>
					<th class="c-title">Đề bài</th>
					<?php if ( $is_iq ) : ?>
						<th class="c-cat">Nhóm</th>
						<th class="c-dif">Độ khó</th>
						<th class="c-ans">Đáp án</th>
					<?php else : ?>
						<th class="c-ans">Lựa chọn</th>
					<?php endif; ?>
					<th class="c-st">Trạng thái</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
					<?php
					list( $id, $q, $status, $ok ) = $row;
					$edit  = get_edit_post_link( $id );
					$thumb = $q['image'] ?: ( 'grid' === $q['type'] ? ( $q['cells'][0] ?? 0 ) : 0 );
					$right = $q['options'][ $q['correct'] ]['img'] ?? 0;
					?>
					<tr class="<?php echo $ok ? '' : 'is-warn'; ?>">
						<td class="c-idx"><?php echo (int) ( $i + 1 ); ?></td>
						<td class="c-img"><a href="<?php echo esc_url( $edit ); ?>" class="tnp-tq-thumb"><?php echo $thumb ? wp_get_attachment_image( $thumb, 'thumbnail' ) : '<span>Aa</span>'; ?></a></td>
						<td class="c-title">
							<a class="tnp-tq-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( get_the_title( $id ) ?: '(không có tiêu đề)' ); ?></a>
							<div class="tnp-tq-meta">
								<?php echo esc_html( count( $q['options'] ) . ' đáp án · thứ tự ' . (int) get_post_field( 'menu_order', $id ) ); ?>
								· <a href="<?php echo esc_url( $edit ); ?>">Sửa</a>
							</div>
						</td>
						<?php if ( $is_iq ) : ?>
							<td class="c-cat"><span class="tnp-pill"><?php echo esc_html( TNP_IQ_Questions::CATEGORIES[ $q['category'] ] ?? '' ); ?></span></td>
							<td class="c-dif"><span class="tnp-stars" title="<?php echo (int) $q['difficulty']; ?>/5"><?php echo str_repeat( '★', $q['difficulty'] ); ?><i><?php echo str_repeat( '★', 5 - $q['difficulty'] ); ?></i></span></td>
							<td class="c-ans">
								<?php if ( isset( $q['options'][ $q['correct'] ] ) ) : ?>
									<span class="tnp-ans"><b><?php echo esc_html( $q['correct'] ); ?></b><?php echo $right ? wp_get_attachment_image( $right, 'thumbnail' ) : ''; ?></span>
								<?php else : ?>
									<span class="tnp-tag is-bad">Chưa chọn</span>
								<?php endif; ?>
							</td>
						<?php else : ?>
							<td class="c-ans"><?php echo esc_html( count( $q['options'] ) ); ?></td>
						<?php endif; ?>
						<td class="c-st">
							<?php $obj = get_post_status_object( $status ); ?>
							<span class="tnp-tag <?php echo 'publish' === $status ? 'is-ok' : 'is-draft'; ?>"><?php echo esc_html( $obj ? $obj->label : $status ); ?></span>
							<?php if ( ! $ok ) : ?><span class="tnp-tag is-bad">Thiếu dữ liệu</span><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function term_save( $term_id ) {
		if ( ! isset( $_POST['tnp_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tnp_term_nonce'] ), 'tnp_term' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_POST['tnp_type'] ?? 'iq' ) );
		update_term_meta( $term_id, 'tnp_type', isset( self::types()[ $type ] ) ? $type : 'iq' );
		update_term_meta( $term_id, 'tnp_shuffle', empty( $_POST['tnp_shuffle'] ) ? '0' : '1' );
		update_term_meta( $term_id, 'tnp_task_on', empty( $_POST['tnp_task_on'] ) ? '0' : '1' );
		update_term_meta( $term_id, 'tnp_layout', 'list' === ( $_POST['tnp_layout'] ?? '' ) ? 'list' : 'step' );
		update_term_meta( $term_id, 'tnp_multi', empty( $_POST['tnp_multi'] ) ? '0' : '1' );
		update_term_meta( $term_id, 'tnp_task_tag', sanitize_text_field( wp_unslash( $_POST['tnp_task_tag'] ?? '' ) ) );
	}

	/** Bật/tắt yêu cầu nhiệm vụ ngay trong danh sách Bài test (không cần mở trang Sửa). */
	public static function ajax_task_toggle() {
		$term_id = absint( $_POST['term'] ?? 0 );
		check_ajax_referer( 'tnp_task_toggle_' . $term_id, 'nonce' );
		$term = get_term( $term_id, TNP_IQ_Questions::TAX );
		if ( ! $term || is_wp_error( $term ) || ! current_user_can( 'edit_term', $term_id ) ) {
			wp_send_json_error( array( 'message' => 'Không có quyền sửa bài test này.' ), 403 );
		}
		$on = ! empty( $_POST['on'] ) && '0' !== $_POST['on'];
		update_term_meta( $term_id, 'tnp_task_on', $on ? '1' : '0' );
		wp_send_json_success( array( 'on' => $on ) );
	}

	public static function task_toggle_script() {
		if ( TNP_IQ_Questions::TAX !== ( $_GET['taxonomy'] ?? '' ) ) {
			return;
		}
		?>
		<script>
		document.addEventListener('change', function (e) {
			var box = e.target;
			if (!box.matches || !box.matches('.tnp-task-toggle input')) { return; }
			var label = box.parentNode.querySelector('b'), on = box.checked;
			box.disabled = true;
			label.textContent = on ? 'Bật' : 'Tắt';
			var body = new FormData();
			body.append('action', 'tnp_task_toggle');
			body.append('term', box.getAttribute('data-term'));
			body.append('nonce', box.getAttribute('data-nonce'));
			body.append('on', on ? '1' : '0');
			fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) { if (!res || !res.success) { throw res; } })
				.catch(function (res) {
					box.checked = !on;
					label.textContent = !on ? 'Bật' : 'Tắt';
					alert((res && res.data && res.data.message) || 'Không lưu được. Vui lòng thử lại.');
				})
				.then(function () { box.disabled = false; });
		});
		</script>
		<?php
	}

	public static function term_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) {
				$new['tnp_type']      = 'Loại';
				$new['tnp_task']      = 'Nhiệm vụ';
				$new['tnp_shortcode'] = 'Shortcode';
			}
		}
		unset( $new['description'] );
		return $new;
	}

	public static function term_column( $out, $col, $term_id ) {
		$term = get_term( $term_id );
		if ( 'tnp_type' === $col ) {
			$tags = array();
			if ( 'list' === self::layout_of( $term->slug ) ) {
				$tags[] = 'Dải câu';
			}
			if ( self::multi_of( $term->slug ) ) {
				$tags[] = 'Chọn nhiều';
			}
			return esc_html( self::types()[ self::type_of( $term->slug ) ]['short'] )
				. ( $tags ? '<br><small class="tnp-any">' . esc_html( implode( ' · ', $tags ) ) . '</small>' : '' );
		}
		if ( 'tnp_task' === $col ) {
			$tag = (string) get_term_meta( $term_id, 'tnp_task_tag', true );
			return sprintf(
				'<label class="tnp-switch tnp-task-toggle" title="Bật/tắt yêu cầu nhiệm vụ"><input type="checkbox" data-term="%d" data-nonce="%s" %s><span></span><b>%s</b></label> %s',
				$term_id,
				esc_attr( wp_create_nonce( 'tnp_task_toggle_' . $term_id ) ),
				self::task_of( $term->slug ) ? 'checked' : '',
				self::task_of( $term->slug ) ? 'Bật' : 'Tắt',
				'' !== $tag ? '<code>' . esc_html( $tag ) . '</code>' : '<em class="tnp-any">mọi tag</em>'
			);
		}
		if ( 'tnp_shortcode' === $col ) {
			return '<code>[tnp_test test="' . esc_html( $term->slug ) . '"]</code>';
		}
		return $out;
	}

	/* ---------- Chấm điểm các loại test tính cách ---------- */

	/**
	 * @param string $type    eq | disc | mbti | holland
	 * @param array  $picked  [ [ 'options' => [key => [dim, pts]], 'chosen' => key|'' ], ... ]
	 * @return array dữ liệu cho trang kết quả + 'code' (chuỗi ngắn lưu vào bảng kết quả) + 'score' (số, nếu có)
	 */
	public static function score( $type, array $picked ) {
		$dims = array_keys( self::types()[ $type ]['dims'] );
		$got  = array_fill_keys( $dims, 0 );
		$max  = array_fill_keys( $dims, 0 );
		foreach ( $picked as $q ) {
			// Điểm tối đa có thể đạt cho từng nhóm ở câu này (dùng để quy ra %):
			// chọn một → đáp án điểm cao nhất của nhóm; chọn nhiều → tổng mọi đáp án điểm dương của nhóm.
			$multi = is_array( $q['chosen'] );
			$best  = array();
			foreach ( $q['options'] as $o ) {
				if ( isset( $max[ $o['dim'] ] ) ) {
					$best[ $o['dim'] ] = $multi ? ( $best[ $o['dim'] ] ?? 0 ) + max( 0, $o['pts'] ) : max( $best[ $o['dim'] ] ?? 0, $o['pts'] );
				}
			}
			foreach ( $best as $d => $v ) {
				$max[ $d ] += $v;
			}
			foreach ( (array) $q['chosen'] as $k ) {
				if ( '' !== $k && isset( $q['options'][ $k ] ) ) {
					$o = $q['options'][ $k ];
					if ( isset( $got[ $o['dim'] ] ) ) {
						$got[ $o['dim'] ] += $o['pts'];
					}
				}
			}
		}
		$method = 'score_' . $type;
		return self::$method( $got, $max );
	}

	private static function pct( $a, $b ) {
		return $b > 0 ? (int) round( $a / $b * 100 ) : 0;
	}

	private static function bars( $type, $values ) {
		$out = array();
		foreach ( self::types()[ $type ]['dims'] as $k => $label ) {
			$out[] = array( 'key' => $k, 'name' => $label, 'pct' => $values[ $k ] ?? 0 );
		}
		return $out;
	}

	/* EQ: tổng điểm quy về thang 100, kèm % từng nhóm. */
	private static function score_eq( $got, $max ) {
		$total = self::pct( array_sum( $got ), array_sum( $max ) );
		$per   = array();
		foreach ( $got as $d => $v ) {
			$per[ $d ] = $max[ $d ] > 0 ? self::pct( $v, $max[ $d ] ) : null;
		}
		$used = array_filter( $per, function ( $v ) { return null !== $v; } );
		arsort( $used );
		$levels = array(
			80 => array( 'Rất cao', 'Bạn hiểu rõ cảm xúc của mình và người khác, giữ được bình tĩnh dưới áp lực và dễ tạo dựng các mối quan hệ tin cậy.' ),
			65 => array( 'Cao', 'Bạn quản lý cảm xúc khá tốt và thường ứng xử khéo léo. Một vài tình huống căng thẳng vẫn có thể khiến bạn mất cân bằng.' ),
			50 => array( 'Trung bình', 'Bạn nhận biết được cảm xúc nhưng chưa phải lúc nào cũng điều chỉnh tốt. Đây là mức phổ biến và có thể cải thiện rõ rệt bằng luyện tập.' ),
			35 => array( 'Dưới trung bình', 'Cảm xúc đôi khi chi phối quyết định và các mối quan hệ của bạn. Bắt đầu từ việc gọi tên cảm xúc mỗi ngày sẽ giúp ích nhiều.' ),
			0  => array( 'Cần cải thiện', 'Bạn có thể đang gặp khó khăn trong việc nhận biết và kiểm soát cảm xúc. Hãy kiên nhẫn với bản thân và cân nhắc tìm người đồng hành, hỗ trợ.' ),
		);
		foreach ( $levels as $min => $l ) {
			if ( $total >= $min ) {
				$level = $l;
				break;
			}
		}
		$tips = array(
			'sa' => 'Viết nhật ký cảm xúc: mỗi tối ghi lại 1–2 cảm xúc mạnh nhất trong ngày và điều gì gây ra nó.',
			'sr' => 'Khi bực bội, dừng 10 giây và hít thở sâu trước khi trả lời; tránh ra quyết định lúc đang nóng giận.',
			'mo' => 'Đặt mục tiêu nhỏ, đo được cho từng tuần và tự ghi nhận mỗi khi hoàn thành.',
			'em' => 'Luyện lắng nghe chủ động: nhắc lại ý người khác bằng lời của bạn trước khi đưa ra ý kiến.',
			'ss' => 'Chủ động khen ngợi cụ thể và hỏi thăm người khác; luyện cách góp ý theo công thức “quan sát – cảm nhận – đề nghị”.',
		);
		$names = self::types()['eq']['dims'];
		$keys  = array_keys( $used );
		return array(
			'score'  => $total,
			'code'   => (string) $total,
			'level'  => array( 'name' => $level[0], 'desc' => $level[1] ),
			'bars'   => array_values( array_filter( self::bars( 'eq', $used ), function ( $b ) use ( $used ) { return isset( $used[ $b['key'] ] ); } ) ),
			'strong' => $keys ? array( 'name' => $names[ $keys[0] ] ) : null,
			'weak'   => count( $keys ) > 1 ? array( 'name' => $names[ end( $keys ) ], 'tip' => $tips[ end( $keys ) ] ) : null,
		);
	}

	/* DISC: tỉ lệ từng nhóm trên tổng điểm, nhóm chính + nhóm phụ. */
	private static function score_disc( $got, $max ) {
		$sum = array_sum( $got );
		$pct = array();
		foreach ( $got as $d => $v ) {
			$pct[ $d ] = self::pct( $v, $sum );
		}
		arsort( $pct );
		$keys = array_keys( $pct );
		$p    = $keys[0];
		$s    = $keys[1];
		$info = self::disc_info();
		$main = $info[ $p ];
		// Nhóm phụ chỉ tính khi đủ nổi bật (≥ 25% và cách nhóm chính không quá 15 điểm).
		$has_second = $pct[ $s ] >= 25 && ( $pct[ $p ] - $pct[ $s ] ) <= 15;
		return array(
			'code'      => $p . ( $has_second ? $s : '' ),
			'primary'   => array( 'key' => $p ) + $main,
			'secondary' => $has_second ? array( 'key' => $s, 'name' => $info[ $s ]['name'], 'summary' => $info[ $s ]['summary'] ) : null,
			'bars'      => self::bars( 'disc', $pct ),
		);
	}

	private static function disc_info() {
		return array(
			'D' => array(
				'name'      => 'Thủ lĩnh (Dominance)',
				'summary'   => 'Quyết đoán, thích thử thách và tập trung vào kết quả.',
				'desc'      => 'Bạn hành động nhanh, thẳng thắn và thích nắm quyền kiểm soát tình huống. Bạn được thúc đẩy bởi mục tiêu, thử thách và cảm giác chiến thắng.',
				'strengths' => array( 'Ra quyết định nhanh, dám chịu trách nhiệm', 'Giải quyết vấn đề trực diện', 'Thúc đẩy tập thể đạt mục tiêu' ),
				'watch'     => array( 'Có thể thiếu kiên nhẫn, nóng vội', 'Dễ bị cho là áp đặt, ít lắng nghe' ),
				'work'      => 'Phù hợp vai trò quản lý, khởi nghiệp, kinh doanh, xử lý khủng hoảng — nơi cần quyết định nhanh và có quyền tự chủ.',
				'talk'      => 'Khi làm việc với người nhóm D: nói ngắn gọn, đi thẳng vào kết quả, đưa lựa chọn thay vì mệnh lệnh.',
			),
			'I' => array(
				'name'      => 'Ảnh hưởng (Influence)',
				'summary'   => 'Nhiệt tình, hoà đồng và giỏi truyền cảm hứng.',
				'desc'      => 'Bạn năng động, lạc quan và thích kết nối với mọi người. Bạn được thúc đẩy bởi sự công nhận, niềm vui và cơ hội thể hiện bản thân.',
				'strengths' => array( 'Giao tiếp, thuyết phục tốt', 'Tạo không khí tích cực cho tập thể', 'Sáng tạo, nhiều ý tưởng' ),
				'watch'     => array( 'Dễ thiếu tập trung vào chi tiết', 'Có thể hứa nhiều hơn khả năng theo sát' ),
				'work'      => 'Phù hợp bán hàng, marketing, truyền thông, sự kiện, đào tạo — nơi được giao tiếp và tạo ảnh hưởng.',
				'talk'      => 'Khi làm việc với người nhóm I: thân thiện, ghi nhận ý tưởng của họ, chốt lại việc cần làm bằng văn bản.',
			),
			'S' => array(
				'name'      => 'Kiên định (Steadiness)',
				'summary'   => 'Điềm tĩnh, đáng tin cậy và giỏi hỗ trợ người khác.',
				'desc'      => 'Bạn kiên nhẫn, trung thành và coi trọng sự ổn định. Bạn được thúc đẩy bởi sự an toàn, hoà hợp và cảm giác được tin cậy.',
				'strengths' => array( 'Lắng nghe, thấu hiểu người khác', 'Bền bỉ, làm việc ổn định và đáng tin', 'Giữ gìn sự đoàn kết của tập thể' ),
				'watch'     => array( 'Ngại thay đổi đột ngột', 'Khó từ chối, dễ ôm việc' ),
				'work'      => 'Phù hợp chăm sóc khách hàng, nhân sự, giáo dục, y tế, vận hành — nơi cần sự tận tâm và ổn định.',
				'talk'      => 'Khi làm việc với người nhóm S: báo trước thay đổi, giải thích lý do và cho họ thời gian thích nghi.',
			),
			'C' => array(
				'name'      => 'Tuân thủ (Compliance)',
				'summary'   => 'Cẩn thận, logic và đề cao chất lượng.',
				'desc'      => 'Bạn chính xác, có hệ thống và thích làm đúng ngay từ đầu. Bạn được thúc đẩy bởi chất lượng, sự rõ ràng và tiêu chuẩn cao.',
				'strengths' => array( 'Phân tích kỹ, ít sai sót', 'Tuân thủ quy trình, tiêu chuẩn', 'Lập kế hoạch chi tiết' ),
				'watch'     => array( 'Có thể cầu toàn, chậm ra quyết định', 'Dễ bị cho là khô khan, quá nguyên tắc' ),
				'work'      => 'Phù hợp kế toán, kiểm toán, kỹ thuật, phân tích dữ liệu, kiểm soát chất lượng, pháp chế.',
				'talk'      => 'Khi làm việc với người nhóm C: chuẩn bị số liệu, giải thích logic và cho họ thời gian xem xét.',
			),
		);
	}

	/* MBTI: mỗi cặp so điểm hai cực → 4 chữ cái, kèm % nghiêng về mỗi bên. */
	private static function score_mbti( $got, $max ) {
		$pairs = array( array( 'E', 'I', 'Hướng ngoại', 'Hướng nội', 'Nguồn năng lượng' ), array( 'S', 'N', 'Giác quan', 'Trực giác', 'Cách tiếp nhận thông tin' ), array( 'T', 'F', 'Lý trí', 'Cảm xúc', 'Cách ra quyết định' ), array( 'J', 'P', 'Nguyên tắc', 'Linh hoạt', 'Cách tổ chức cuộc sống' ) );
		$code  = '';
		$axes  = array();
		foreach ( $pairs as $p ) {
			$a     = $got[ $p[0] ];
			$b     = $got[ $p[1] ];
			$pa    = ( $a + $b ) > 0 ? (int) round( $a / ( $a + $b ) * 100 ) : 50;
			$pick  = $a >= $b ? $p[0] : $p[1];
			$code .= $pick;
			$axes[] = array(
				'title' => $p[4],
				'left'  => array( 'key' => $p[0], 'name' => $p[2], 'pct' => $pa ),
				'right' => array( 'key' => $p[1], 'name' => $p[3], 'pct' => 100 - $pa ),
				'pick'  => $pick,
			);
		}
		$t = self::mbti_info()[ $code ];
		return array(
			'code' => $code,
			'type' => array( 'code' => $code ) + $t,
			'axes' => $axes,
		);
	}

	private static function mbti_info() {
		$t = function ( $name, $group, $desc, $strengths, $watch, $careers ) {
			return compact( 'name', 'group', 'desc', 'strengths', 'watch', 'careers' );
		};
		return array(
			'INTJ' => $t( 'Nhà kiến trúc', 'Nhóm Phân tích', 'Chiến lược gia độc lập, nhìn xa và luôn có kế hoạch cho mọi thứ. Bạn thích hiểu tận gốc vấn đề và cải tiến hệ thống.', array( 'Tư duy chiến lược, dài hạn', 'Độc lập, quyết tâm cao', 'Học nhanh kiến thức phức tạp' ), array( 'Có thể tỏ ra lạnh lùng, kiêu ngạo', 'Thiếu kiên nhẫn với cảm xúc người khác' ), array( 'Kỹ sư hệ thống', 'Nhà khoa học', 'Chiến lược kinh doanh', 'Kiến trúc sư phần mềm' ) ),
			'INTP' => $t( 'Nhà logic học', 'Nhóm Phân tích', 'Nhà tư duy sáng tạo, tò mò với mọi ý tưởng và lý thuyết. Bạn thích phân tích, tìm quy luật và giải những bài toán khó.', array( 'Phân tích logic sắc bén', 'Sáng tạo, cởi mở với ý tưởng mới', 'Khách quan' ), array( 'Dễ trì hoãn, thiếu thực tế', 'Khó diễn đạt cảm xúc' ), array( 'Lập trình viên', 'Nhà nghiên cứu', 'Phân tích dữ liệu', 'Giảng viên' ) ),
			'ENTJ' => $t( 'Nhà điều hành', 'Nhóm Phân tích', 'Người lãnh đạo táo bạo, giàu ý chí, luôn tìm ra cách — hoặc tự tạo ra cách — để đạt mục tiêu.', array( 'Lãnh đạo, tổ chức giỏi', 'Quyết đoán, hiệu quả', 'Tự tin, nhìn xa' ), array( 'Có thể độc đoán, cứng nhắc', 'Thiếu kiên nhẫn với người chậm' ), array( 'Giám đốc điều hành', 'Quản lý dự án', 'Luật sư', 'Tư vấn quản trị' ) ),
			'ENTP' => $t( 'Người tranh luận', 'Nhóm Phân tích', 'Nhà tư tưởng thông minh, tò mò, thích thử thách trí tuệ và tranh luận để mở ra góc nhìn mới.', array( 'Nhanh trí, nhiều ý tưởng', 'Giỏi ứng biến', 'Tự tin, giao tiếp tốt' ), array( 'Dễ chán việc lặp lại', 'Có thể tranh luận quá đà' ), array( 'Khởi nghiệp', 'Marketing', 'Luật sư', 'Nhà sáng tạo nội dung' ) ),
			'INFJ' => $t( 'Người che chở', 'Nhóm Ngoại giao', 'Người lý tưởng trầm lặng, sâu sắc, giàu lòng trắc ẩn và luôn muốn tạo ra thay đổi tích cực.', array( 'Thấu hiểu người khác sâu sắc', 'Có lý tưởng, nguyên tắc', 'Sáng tạo, tận tâm' ), array( 'Dễ kiệt sức vì ôm việc', 'Nhạy cảm với phê bình' ), array( 'Tư vấn tâm lý', 'Nhà văn', 'Giáo viên', 'Công tác xã hội' ) ),
			'INFP' => $t( 'Người hoà giải', 'Nhóm Ngoại giao', 'Người mơ mộng, tốt bụng và vị tha, luôn tìm kiếm ý nghĩa và sống theo giá trị của mình.', array( 'Giàu cảm xúc, đồng cảm', 'Sáng tạo, giàu trí tưởng tượng', 'Trung thành với giá trị' ), array( 'Dễ lý tưởng hoá, thiếu thực tế', 'Khó đối mặt xung đột' ), array( 'Nhà văn', 'Thiết kế', 'Tâm lý học', 'Biên tập viên' ) ),
			'ENFJ' => $t( 'Người chỉ dẫn', 'Nhóm Ngoại giao', 'Người lãnh đạo lôi cuốn, truyền cảm hứng và luôn quan tâm đến sự phát triển của người khác.', array( 'Truyền cảm hứng, thuyết phục', 'Quan tâm, vị tha', 'Tổ chức con người giỏi' ), array( 'Dễ hy sinh quá mức cho người khác', 'Nhạy cảm với sự không hài lòng' ), array( 'Giáo viên', 'Quản lý nhân sự', 'Huấn luyện viên', 'Truyền thông' ) ),
			'ENFP' => $t( 'Người truyền cảm hứng', 'Nhóm Ngoại giao', 'Tinh thần tự do, nhiệt huyết, sáng tạo và hoà đồng — luôn tìm ra lý do để mỉm cười.', array( 'Nhiệt tình, lạc quan', 'Giao tiếp, kết nối tốt', 'Nhiều ý tưởng mới' ), array( 'Khó tập trung lâu', 'Dễ căng thẳng khi phải làm việc chi tiết' ), array( 'Marketing', 'Báo chí', 'Tổ chức sự kiện', 'Tư vấn' ) ),
			'ISTJ' => $t( 'Người trách nhiệm', 'Nhóm Bảo vệ', 'Người thực tế, đáng tin cậy, làm việc có kế hoạch và luôn giữ lời hứa.', array( 'Trách nhiệm, chu đáo', 'Tổ chức, có hệ thống', 'Kiên trì, đáng tin' ), array( 'Ngại thay đổi', 'Có thể cứng nhắc, ít linh hoạt' ), array( 'Kế toán', 'Kiểm toán', 'Quản lý hành chính', 'Kỹ sư' ) ),
			'ISFJ' => $t( 'Người bảo vệ', 'Nhóm Bảo vệ', 'Người tận tuỵ, ấm áp, luôn sẵn sàng bảo vệ và chăm sóc những người mình yêu quý.', array( 'Tận tâm, chu đáo', 'Đáng tin cậy, kiên nhẫn', 'Quan sát tinh tế' ), array( 'Khó từ chối, dễ quá tải', 'Ngại bày tỏ nhu cầu bản thân' ), array( 'Y tá, điều dưỡng', 'Giáo viên mầm non', 'Nhân sự', 'Chăm sóc khách hàng' ) ),
			'ESTJ' => $t( 'Người điều hành', 'Nhóm Bảo vệ', 'Nhà quản lý xuất sắc, giỏi tổ chức công việc và con người theo trật tự, quy trình.', array( 'Tổ chức, quản lý tốt', 'Thẳng thắn, quyết đoán', 'Trung thực, kiên định' ), array( 'Có thể cứng nhắc, bảo thủ', 'Khó chấp nhận cách làm khác' ), array( 'Quản lý vận hành', 'Quản lý dự án', 'Ngân hàng', 'Quân đội, công an' ) ),
			'ESFJ' => $t( 'Người quan tâm', 'Nhóm Bảo vệ', 'Người chu đáo, hoà đồng, luôn muốn giúp đỡ và tạo sự gắn kết trong tập thể.', array( 'Quan tâm, chu đáo', 'Hợp tác, hoà đồng', 'Có trách nhiệm' ), array( 'Cần được ghi nhận', 'Nhạy cảm với phê bình' ), array( 'Điều dưỡng', 'Giáo viên', 'Tổ chức sự kiện', 'Chăm sóc khách hàng' ) ),
			'ISTP' => $t( 'Nhà kỹ thuật', 'Nhóm Khám phá', 'Nhà thực nghiệm táo bạo, thực tế, giỏi dùng tay và công cụ để giải quyết vấn đề.', array( 'Bình tĩnh trong khủng hoảng', 'Thực tế, giỏi xử lý tình huống', 'Linh hoạt' ), array( 'Khó cam kết lâu dài', 'Ít bộc lộ cảm xúc' ), array( 'Kỹ thuật viên', 'Kỹ sư cơ khí', 'Phi công', 'Lập trình' ) ),
			'ISFP' => $t( 'Người nghệ sĩ', 'Nhóm Khám phá', 'Người nghệ sĩ linh hoạt, cuốn hút, luôn sẵn sàng khám phá và trải nghiệm điều mới.', array( 'Nhạy cảm với cái đẹp', 'Ấm áp, khiêm tốn', 'Sống thật với bản thân' ), array( 'Ngại lập kế hoạch dài hạn', 'Dễ tổn thương' ), array( 'Thiết kế', 'Nhiếp ảnh', 'Âm nhạc', 'Chăm sóc sức khoẻ' ) ),
			'ESTP' => $t( 'Người thực thi', 'Nhóm Khám phá', 'Người thông minh, năng động, nhạy bén, thích sống ở hiện tại và hành động ngay.', array( 'Hành động nhanh, quyết đoán', 'Giỏi đàm phán, ứng biến', 'Năng động, thực tế' ), array( 'Dễ bốc đồng, mạo hiểm', 'Thiếu kiên nhẫn với lý thuyết' ), array( 'Kinh doanh', 'Bán hàng', 'Thể thao', 'Cứu hộ, cảnh sát' ) ),
			'ESFP' => $t( 'Người trình diễn', 'Nhóm Khám phá', 'Người tự nhiên, năng lượng, nhiệt tình — cuộc sống không bao giờ nhàm chán khi có bạn.', array( 'Hoà đồng, vui vẻ', 'Thực tế, quan sát tốt', 'Giỏi tạo không khí' ), array( 'Dễ chán, thiếu tập trung lâu dài', 'Né tránh xung đột' ), array( 'Diễn viên, MC', 'Du lịch, khách sạn', 'Bán hàng', 'Tổ chức sự kiện' ) ),
		);
	}

	/* Holland: % điểm từng nhóm trên điểm tối đa của nhóm, mã 3 chữ cao nhất. */
	private static function score_holland( $got, $max ) {
		$pct = array();
		foreach ( $got as $d => $v ) {
			$pct[ $d ] = $max[ $d ] > 0 ? self::pct( $v, $max[ $d ] ) : 0;
		}
		$sorted = $pct;
		arsort( $sorted );
		$top  = array_slice( array_keys( $sorted ), 0, 3 );
		$info = self::holland_info();
		$out  = array();
		foreach ( $top as $k ) {
			$out[] = array( 'key' => $k, 'pct' => $pct[ $k ] ) + $info[ $k ];
		}
		return array(
			'code' => implode( '', $top ),
			'top'  => $out,
			'bars' => self::bars( 'holland', $pct ),
		);
	}

	private static function holland_info() {
		return array(
			'R' => array( 'name' => 'Kỹ thuật (Realistic)', 'desc' => 'Thích làm việc với đồ vật, máy móc, công cụ, cây cối hay con vật; thích hoạt động tay chân và thấy được kết quả cụ thể.', 'careers' => array( 'Kỹ sư cơ khí, điện, xây dựng', 'Kỹ thuật viên', 'Kiến trúc – xây dựng', 'Nông nghiệp, lâm nghiệp', 'Phi công, lái tàu' ) ),
			'I' => array( 'name' => 'Nghiên cứu (Investigative)', 'desc' => 'Thích quan sát, tìm hiểu, phân tích và giải quyết vấn đề bằng tư duy; tò mò với khoa học và các câu hỏi “tại sao”.', 'careers' => array( 'Nhà khoa học, nghiên cứu viên', 'Bác sĩ, dược sĩ', 'Lập trình viên, kỹ sư dữ liệu', 'Kỹ sư công nghệ sinh học', 'Giảng viên đại học' ) ),
			'A' => array( 'name' => 'Nghệ thuật (Artistic)', 'desc' => 'Giàu trí tưởng tượng, thích sáng tạo và tự do thể hiện bản thân qua hình ảnh, âm thanh, ngôn từ.', 'careers' => array( 'Thiết kế đồ hoạ, thời trang', 'Kiến trúc sư', 'Nhà văn, biên tập viên', 'Nhạc sĩ, diễn viên', 'Sáng tạo nội dung, quảng cáo' ) ),
			'S' => array( 'name' => 'Xã hội (Social)', 'desc' => 'Thích giúp đỡ, dạy dỗ, chăm sóc và làm việc cùng con người; giỏi lắng nghe và thấu hiểu.', 'careers' => array( 'Giáo viên', 'Bác sĩ, điều dưỡng', 'Tư vấn tâm lý', 'Công tác xã hội', 'Nhân sự, chăm sóc khách hàng' ) ),
			'E' => array( 'name' => 'Quản lý (Enterprising)', 'desc' => 'Năng động, thích thuyết phục, lãnh đạo, kinh doanh và chấp nhận rủi ro để đạt mục tiêu.', 'careers' => array( 'Quản trị kinh doanh', 'Kinh doanh, bán hàng', 'Marketing', 'Luật sư', 'Khởi nghiệp, quản lý dự án' ) ),
			'C' => array( 'name' => 'Nghiệp vụ (Conventional)', 'desc' => 'Cẩn thận, ngăn nắp, thích làm việc với số liệu, giấy tờ theo quy trình rõ ràng.', 'careers' => array( 'Kế toán, kiểm toán', 'Ngân hàng, tài chính', 'Hành chính, thư ký', 'Phân tích số liệu', 'Thủ kho, logistics' ) ),
		);
	}
}

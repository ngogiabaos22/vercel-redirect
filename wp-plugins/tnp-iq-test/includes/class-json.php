<?php
defined( 'ABSPATH' ) || exit;

/**
 * Xuất / nhập trọn bộ đề (cài đặt bài test + toàn bộ câu hỏi) bằng file JSON.
 *
 * Quy ước JSON (format "tnp-test", version 1):
 * {
 *   "format": "tnp-test", "version": 1,
 *   "test": {
 *     "name": "Test Holland", "slug": "test-holland",
 *     "type": "holland",            // iq | eq | aq | disc | mbti | holland
 *     "layout": "list",             // step (từng câu) | list (dải câu)
 *     "multi": true,                // chọn nhiều đáp án (không áp dụng IQ)
 *     "shuffle": false,             // xáo trộn thứ tự đáp án
 *     "task": { "on": false, "tag": "" }
 *   },
 *   "questions": [                  // theo thứ tự làm bài
 *     {
 *       "title": "Tôi là người...",
 *       "type": "question",         // question | image (đề nằm trong ảnh) | grid (lưới cũ)
 *       "body": "",                 // đoạn đề dài (tuỳ chọn)
 *       "image": null,              // link ảnh đề (tuỳ chọn)
 *       "status": "publish",        // publish | draft (tuỳ chọn)
 *       "correct": "B",             // chỉ IQ: chữ cái đáp án đúng
 *       "category": "logic",        // chỉ IQ: logic | number | verbal | spatial
 *       "difficulty": 3,            // chỉ IQ: 1–5
 *       "explain": "",              // ghi chú nội bộ (tuỳ chọn)
 *       "options": [                // 2–8 đáp án, A, B, C… theo thứ tự
 *         { "text": "…", "image": null, "group": "R", "points": 1 }   // group/points: test tính cách
 *       ]
 *     }
 *   ]
 * }
 */
class TNP_IQ_Json {

	const FORMAT  = 'tnp-test';
	const VERSION = 1;
	const NOTICE  = 'tnp_json_notice_';

	public static function init() {
		$tax = TNP_IQ_Questions::TAX;
		add_action( "after-{$tax}-table", array( __CLASS__, 'import_box' ) );
		add_filter( "{$tax}_row_actions", array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_tnp_json_export', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_tnp_json_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/* ---------- Xuất ---------- */

	public static function export( $term ) {
		$slug = $term->slug;
		$task = (string) get_term_meta( $term->term_id, 'tnp_task_tag', true );
		$ids  = get_posts( array(
			'post_type'      => TNP_IQ_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $term->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$type = TNP_IQ_Tests::type_of( $slug );
		$url  = function ( $id ) {
			return $id ? ( wp_get_attachment_url( $id ) ?: null ) : null;
		};

		$questions = array();
		foreach ( $ids as $id ) {
			$q   = TNP_IQ_Questions::get( $id );
			$row = array(
				'title' => TNP_IQ_Questions::plain( get_post_field( 'post_title', $id ) ),
				'type'  => $q['type'],
				'body'  => $q['body'],
				'image' => $url( $q['image'] ),
			);
			if ( 'publish' !== get_post_status( $id ) ) {
				$row['status'] = 'draft';
			}
			if ( 'grid' === $q['type'] ) {
				$row['grid']  = $q['grid'];
				$row['cells'] = array_map( $url, array_slice( $q['cells'], 0, $q['grid'] * $q['grid'] - 1 ) );
			}
			if ( 'iq' === $type ) {
				$row['correct']    = $q['correct'];
				$row['category']   = $q['category'];
				$row['difficulty'] = $q['difficulty'];
			}
			if ( '' !== $q['explain'] ) {
				$row['explain'] = $q['explain'];
			}
			$row['options'] = array();
			foreach ( $q['options'] as $o ) {
				$opt = array( 'text' => $o['text'], 'image' => $url( $o['img'] ) );
				if ( 'iq' !== $type ) {
					$opt['group']  = $o['dim'];
					$opt['points'] = floor( $o['pts'] ) == $o['pts'] ? (int) $o['pts'] : (float) $o['pts'];
				}
				$row['options'][] = $opt;
			}
			$questions[] = $row;
		}

		return array(
			'format'      => self::FORMAT,
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'source'      => home_url(),
			'test'        => array(
				'name'    => TNP_IQ_Questions::plain( $term->name ),
				'slug'    => $slug,
				'type'    => $type,
				'layout'  => TNP_IQ_Tests::layout_of( $slug ),
				'multi'   => '1' === (string) get_term_meta( $term->term_id, 'tnp_multi', true ),
				'shuffle' => TNP_IQ_Tests::shuffle_of( $slug ),
				'task'    => array( 'on' => (bool) TNP_IQ_Tests::task_of( $slug ), 'tag' => $task ),
			),
			'questions'   => $questions,
		);
	}

	public static function download() {
		$term_id = absint( $_GET['term'] ?? 0 );
		check_admin_referer( 'tnp_json_export_' . $term_id );
		$term = get_term( $term_id, TNP_IQ_Questions::TAX );
		if ( ! current_user_can( 'manage_categories' ) || ! $term || is_wp_error( $term ) ) {
			wp_die( 'Không có quyền hoặc bài test không tồn tại.' );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $term->slug . '-' . gmdate( 'Ymd' ) . '.json' ) . '"' );
		echo wp_json_encode( self::export( $term ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function export_url( $term_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=tnp_json_export&term=' . (int) $term_id ), 'tnp_json_export_' . (int) $term_id );
	}

	public static function row_actions( $actions, $term ) {
		$actions['tnp_json'] = '<a href="' . esc_url( self::export_url( $term->term_id ) ) . '">Xuất JSON</a>';
		return $actions;
	}

	/* ---------- Nhập ---------- */

	/** Ảnh: link → ID trong Thư viện (cùng web), không có thì tải về nếu được phép. */
	private static function media_id( $url, $sideload, &$warn ) {
		if ( is_numeric( $url ) ) {
			return absint( $url );
		}
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return 0;
		}
		$id = attachment_url_to_postid( $url );
		if ( ! $id ) { // link ảnh thu nhỏ (…-300x300.png) → ảnh gốc
			$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(\.\w+)$/', '$1', $url ) );
		}
		if ( ! $id && $sideload && preg_match( '#^https?://#i', $url ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$id = media_sideload_image( esc_url_raw( $url ), 0, null, 'id' );
			$id = is_wp_error( $id ) ? 0 : (int) $id;
		}
		if ( ! $id ) {
			$warn[] = 'Không tìm thấy ảnh: ' . $url;
		}
		return (int) $id;
	}

	/**
	 * @param array $data JSON đã giải mã.
	 * @param array $opts target (0 = tạo bài mới | term_id), mode (append | replace), status (publish | draft),
	 *                    sideload (tải ảnh từ link ngoài), dry_run.
	 */
	public static function import( $data, array $opts ) {
		$opts = wp_parse_args( $opts, array( 'target' => 0, 'mode' => 'append', 'status' => 'publish', 'sideload' => false, 'dry_run' => false ) );
		$out  = array( 'test' => null, 'found' => 0, 'created' => 0, 'drafts' => 0, 'trashed' => 0, 'errors' => array(), 'warnings' => array() );

		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || ! isset( $data['questions'] ) || ! is_array( $data['questions'] ) ) {
			$out['errors'][] = 'File không đúng định dạng: cần "format": "tnp-test" và mảng "questions".';
			return $out;
		}
		$t     = is_array( $data['test'] ?? null ) ? $data['test'] : array();
		$types = TNP_IQ_Tests::types();

		// Bài test đích.
		$term = $opts['target'] ? get_term( (int) $opts['target'], TNP_IQ_Questions::TAX ) : null;
		if ( $opts['target'] && ( ! $term || is_wp_error( $term ) ) ) {
			$out['errors'][] = 'Bài test được chọn không tồn tại.';
			return $out;
		}
		$type = $term ? TNP_IQ_Tests::type_of( $term->slug ) : ( isset( $types[ $t['type'] ?? '' ] ) ? $t['type'] : 'iq' );
		$dims = 'iq' === $type ? array() : $types[ $type ]['dims'];

		// Kiểm tra và chuẩn hoá câu hỏi.
		$items = array();
		foreach ( array_values( $data['questions'] ) as $n => $q ) {
			$label = 'Câu ' . ( $n + 1 );
			$title = sanitize_text_field( (string) ( $q['title'] ?? '' ) );
			$opts_in = isset( $q['options'] ) && is_array( $q['options'] ) ? array_values( $q['options'] ) : array();
			if ( '' === $title ) {
				$out['errors'][] = $label . ': thiếu "title".';
				continue;
			}
			if ( count( $opts_in ) < TNP_IQ_Questions::MIN_OPTIONS || count( $opts_in ) > TNP_IQ_Questions::MAX_OPTIONS ) {
				$out['errors'][] = $label . ': cần từ 2 đến 8 đáp án.';
				continue;
			}
			$bad = '';
			foreach ( $opts_in as $i => $o ) {
				$k = TNP_IQ_Questions::letter( $i );
				if ( '' === trim( (string) ( $o['text'] ?? '' ) ) && empty( $o['image'] ) ) {
					$bad = 'đáp án ' . $k . ' trống';
				} elseif ( 'iq' !== $type && ! isset( $dims[ (string) ( $o['group'] ?? '' ) ] ) ) {
					$bad = 'đáp án ' . $k . ' có "group" không hợp lệ (' . implode( ', ', array_keys( $dims ) ) . ')';
				}
				if ( $bad ) {
					break;
				}
			}
			if ( $bad ) {
				$out['errors'][] = $label . ': ' . $bad . '.';
				continue;
			}
			$items[] = $q + array( '_title' => $title, '_opts' => $opts_in );
		}
		$out['found'] = count( $items );
		$out['test']  = array(
			'name'   => $term ? TNP_IQ_Questions::plain( $term->name ) : sanitize_text_field( (string) ( $t['name'] ?? '' ) ),
			'type'   => $type,
			'exists' => (bool) $term,
		);
		if ( $opts['dry_run'] || ! $items ) {
			return $out;
		}

		// Tạo bài test mới (kèm cài đặt trong file) nếu không chọn bài có sẵn.
		if ( ! $term ) {
			$name = $out['test']['name'] ?: 'Bài test nhập từ JSON';
			$slug = sanitize_title( (string) ( $t['slug'] ?? '' ) ) ?: sanitize_title( $name );
			$base = $slug;
			for ( $i = 2; term_exists( $slug, TNP_IQ_Questions::TAX ); $i++ ) {
				$slug = $base . '-' . $i;
			}
			$made = wp_insert_term( $name, TNP_IQ_Questions::TAX, array( 'slug' => $slug ) );
			if ( is_wp_error( $made ) ) {
				$out['errors'][] = $made->get_error_message();
				return $out;
			}
			$term = get_term( $made['term_id'], TNP_IQ_Questions::TAX );
			$task = is_array( $t['task'] ?? null ) ? $t['task'] : array();
			update_term_meta( $term->term_id, 'tnp_type', $type );
			update_term_meta( $term->term_id, 'tnp_layout', 'list' === ( $t['layout'] ?? '' ) ? 'list' : 'step' );
			update_term_meta( $term->term_id, 'tnp_multi', ! empty( $t['multi'] ) ? '1' : '0' );
			update_term_meta( $term->term_id, 'tnp_shuffle', ! empty( $t['shuffle'] ) ? '1' : '0' );
			update_term_meta( $term->term_id, 'tnp_task_on', ! empty( $task['on'] ) ? '1' : '0' );
			update_term_meta( $term->term_id, 'tnp_task_tag', sanitize_text_field( (string) ( $task['tag'] ?? '' ) ) );
		} elseif ( 'replace' === $opts['mode'] ) {
			$old = get_posts( array( 'post_type' => TNP_IQ_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $old as $id ) {
				$out['trashed'] += wp_trash_post( $id ) ? 1 : 0;
			}
		}
		$out['test'] += array( 'slug' => $term->slug, 'id' => $term->term_id );

		// Thứ tự tiếp nối các câu đang có khi thêm vào bài có sẵn.
		$start = 0;
		if ( 'append' === $opts['mode'] ) {
			$last  = get_posts( array( 'post_type' => TNP_IQ_CPT, 'post_status' => 'any', 'numberposts' => 1, 'orderby' => 'menu_order', 'order' => 'DESC', 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$start = $last ? (int) get_post_field( 'menu_order', $last[0] ) : 0;
		}

		$warn = array();
		foreach ( $items as $n => $q ) {
			$options = array();
			foreach ( $q['_opts'] as $i => $o ) {
				$options[ TNP_IQ_Questions::letter( $i ) ] = array(
					'img'  => self::media_id( $o['image'] ?? '', $opts['sideload'], $warn ),
					'text' => sanitize_text_field( (string) ( $o['text'] ?? '' ) ),
					'dim'  => 'iq' === $type ? '' : (string) $o['group'],
					'pts'  => isset( $o['points'] ) && is_numeric( $o['points'] ) ? max( -100, min( 100, (float) $o['points'] ) ) : 1.0,
				);
			}
			$correct = strtoupper( (string) ( $q['correct'] ?? '' ) );
			$correct = isset( $options[ $correct ] ) ? $correct : '';
			$draft   = 'draft' === ( $q['status'] ?? '' ) || 'draft' === $opts['status'] || ( 'iq' === $type && '' === $correct );
			$id      = wp_insert_post( array(
				'post_type'   => TNP_IQ_CPT,
				'post_status' => $draft ? 'draft' : 'publish',
				'post_title'  => $q['_title'],
				'menu_order'  => $start + $n + 1,
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				$out['errors'][] = 'Câu ' . ( $n + 1 ) . ': không lưu được.';
				continue;
			}
			$image = self::media_id( $q['image'] ?? '', $opts['sideload'], $warn );
			$qtype = in_array( $q['type'] ?? '', array( 'question', 'image', 'grid' ), true ) ? $q['type'] : ( $image ? 'image' : 'question' );
			$meta  = array(
				'type'       => $qtype,
				'body'       => sanitize_textarea_field( (string) ( $q['body'] ?? '' ) ),
				'image'      => $image,
				'options'    => $options,
				'correct'    => $correct,
				'explain'    => sanitize_textarea_field( (string) ( $q['explain'] ?? '' ) ),
				'category'   => array_key_exists( $q['category'] ?? '', TNP_IQ_Questions::CATEGORIES ) ? $q['category'] : 'logic',
				'difficulty' => max( 1, min( 5, (int) ( $q['difficulty'] ?? 3 ) ) ),
			);
			if ( 'grid' === $qtype ) {
				$meta['grid']  = in_array( (int) ( $q['grid'] ?? 3 ), array( 2, 3, 4 ), true ) ? (int) $q['grid'] : 3;
				$meta['cells'] = array_map( function ( $u ) use ( $opts, &$warn ) {
					return self::media_id( $u, $opts['sideload'], $warn );
				}, is_array( $q['cells'] ?? null ) ? $q['cells'] : array() );
			}
			foreach ( $meta as $k => $v ) {
				update_post_meta( $id, '_tnp_' . $k, $v );
			}
			wp_set_object_terms( $id, array( (int) $term->term_id ), TNP_IQ_Questions::TAX );
			$out['created']++;
			$out['drafts'] += $draft ? 1 : 0;
		}
		$out['warnings'] = array_values( array_unique( $warn ) );
		return $out;
	}

	/* ---------- Giao diện ở tab Bài test ---------- */

	public static function import_box() {
		$terms = get_terms( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'hide_empty' => false ) );
		?>
		<div class="tnp-json-box">
			<h2>Nhập đề từ JSON</h2>
			<p class="description">Chuyển trọn bộ đề (cài đặt + câu hỏi + đáp án) giữa các bài test hoặc giữa các web. Lấy file mẫu bằng “Xuất JSON” ở từng bài test (rê chuột vào tên bài).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="tnp_json_import">
				<?php wp_nonce_field( 'tnp_json_import' ); ?>
				<p><label>File .json: <input type="file" name="tnp_json_file" accept=".json,application/json"></label></p>
				<p><label for="tnp-json-text">hoặc dán nội dung JSON:</label><br>
					<textarea id="tnp-json-text" name="tnp_json_text" rows="5" class="large-text code" placeholder='{"format": "tnp-test", "version": 1, "test": {...}, "questions": [...]}'></textarea></p>
				<p>
					<label>Nhập vào:
						<select name="tnp_json_target">
							<option value="0">— Tạo bài test mới (theo tên, loại, cài đặt trong file) —</option>
							<?php foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) : ?>
								<option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $t->name . ' (' . TNP_IQ_Tests::types()[ TNP_IQ_Tests::type_of( $t->slug ) ]['short'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="margin-left:12px">Câu hỏi cũ:
						<select name="tnp_json_mode">
							<option value="append">Giữ, thêm câu mới vào cuối</option>
							<option value="replace">Chuyển vào thùng rác rồi nhập mới</option>
						</select>
					</label>
				</p>
				<p>
					<label><input type="checkbox" name="tnp_json_sideload" value="1" checked> Tải ảnh từ web khác về Thư viện (khi chuyển đề giữa hai web)</label><br>
					<label><input type="checkbox" name="tnp_json_draft" value="1"> Nhập ở dạng Bản nháp</label><br>
					<label><input type="checkbox" name="tnp_json_dry" value="1"> Chỉ kiểm tra file, chưa nhập</label>
				</p>
				<?php submit_button( 'Nhập JSON', 'primary', 'submit', false ); ?>
			</form>
			<details><summary>Quy ước JSON</summary><pre><?php echo esc_html( self::sample() ); ?></pre></details>
		</div>
		<?php
	}

	private static function sample() {
		return wp_json_encode( array(
			'format'    => self::FORMAT,
			'version'   => self::VERSION,
			'test'      => array( 'name' => 'Test Holland', 'slug' => 'test-holland', 'type' => 'holland', 'layout' => 'list', 'multi' => true, 'shuffle' => false, 'task' => array( 'on' => false, 'tag' => '' ) ),
			'questions' => array(
				array(
					'title'   => 'Tôi là người...',
					'type'    => 'question',
					'body'    => '',
					'image'   => null,
					'options' => array(
						array( 'text' => 'Thích làm việc bằng tay.', 'image' => null, 'group' => 'R', 'points' => 1 ),
						array( 'text' => 'Thích phân tích, tìm nguyên nhân.', 'image' => null, 'group' => 'I', 'points' => 1 ),
					),
				),
				array(
					'title'      => 'Câu IQ: hình nào điền vào ô trống?',
					'type'       => 'image',
					'image'      => 'https://example.com/wp-content/uploads/cau-1.png',
					'correct'    => 'B',
					'category'   => 'logic',
					'difficulty' => 3,
					'options'    => array( array( 'text' => '', 'image' => 'https://example.com/…/1-1.png' ), array( 'text' => '', 'image' => 'https://example.com/…/1-2.png' ) ),
				),
			),
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		. "\n\ntype: iq | eq | aq | disc | mbti | holland · layout: step | list\n"
		. "group (test tính cách) — EQ: sa, sr, mo, em, ss · AQ: C, O, R, E · DISC: D, I, S, C · MBTI: E, I, S, N, T, F, J, P · Holland: R, I, A, S, E, C\n"
		. "IQ: correct = chữ cái đáp án đúng (thiếu → câu lưu nháp), category: logic | number | verbal | spatial, difficulty: 1–5";
	}

	public static function handle_import() {
		check_admin_referer( 'tnp_json_import' );
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( 'Không có quyền.' );
		}
		$raw = '';
		if ( ! empty( $_FILES['tnp_json_file']['tmp_name'] ) && is_uploaded_file( $_FILES['tnp_json_file']['tmp_name'] ) ) {
			$raw = (string) file_get_contents( $_FILES['tnp_json_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( '' === trim( $raw ) ) {
			$raw = (string) wp_unslash( $_POST['tnp_json_text'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$data = json_decode( $raw, true );
		$res  = null === $data
			? array( 'errors' => array( 'Không đọc được JSON: ' . json_last_error_msg() ), 'found' => 0, 'created' => 0 )
			: self::import( $data, array(
				'target'   => absint( $_POST['tnp_json_target'] ?? 0 ),
				'mode'     => 'replace' === ( $_POST['tnp_json_mode'] ?? '' ) ? 'replace' : 'append',
				'status'   => empty( $_POST['tnp_json_draft'] ) ? 'publish' : 'draft',
				'sideload' => ! empty( $_POST['tnp_json_sideload'] ),
				'dry_run'  => ! empty( $_POST['tnp_json_dry'] ),
			) );
		$res['dry_run'] = ! empty( $_POST['tnp_json_dry'] );
		set_transient( self::NOTICE . get_current_user_id(), $res, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=' . TNP_IQ_Questions::TAX . '&post_type=' . TNP_IQ_CPT ) );
		exit;
	}

	public static function notice() {
		$key = self::NOTICE . get_current_user_id();
		$res = get_transient( $key );
		if ( ! $res ) {
			return;
		}
		delete_transient( $key );
		$ok    = empty( $res['errors'] ) || ! empty( $res['created'] );
		$name  = $res['test']['name'] ?? '';
		$lines = array();
		if ( ! empty( $res['dry_run'] ) ) {
			$lines[] = sprintf( 'Kiểm tra file: %d câu hợp lệ%s. Chưa nhập gì.', (int) $res['found'], $name ? ' cho bài “' . $name . '”' : '' );
		} elseif ( ! empty( $res['created'] ) ) {
			$lines[] = sprintf( 'Đã nhập %d câu vào bài “%s”%s%s.', (int) $res['created'], $name, ! empty( $res['drafts'] ) ? ', trong đó ' . (int) $res['drafts'] . ' câu lưu nháp' : '', ! empty( $res['trashed'] ) ? '; ' . (int) $res['trashed'] . ' câu cũ đã vào thùng rác' : '' );
		}
		foreach ( array_merge( (array) ( $res['errors'] ?? array() ), (array) ( $res['warnings'] ?? array() ) ) as $m ) {
			$lines[] = $m;
		}
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $ok ? 'success' : 'error', implode( '<br>', array_map( 'esc_html', array_slice( $lines, 0, 30 ) ) ) );
	}

	/* ---------- REST cho quản trị viên ---------- */

	public static function routes() {
		$admin = function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route( TNP_IQ_Rest::NS, '/admin/export', array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $req ) {
				$term = get_term_by( 'slug', sanitize_title( $req['test'] ), TNP_IQ_Questions::TAX );
				return $term ? self::export( $term ) : new WP_Error( 'tnp_no_test', 'Không có bài test này.', array( 'status' => 404 ) );
			},
			'permission_callback' => $admin,
			'args'                => array( 'test' => array( 'type' => 'string', 'required' => true ) ),
		) );
		register_rest_route( TNP_IQ_Rest::NS, '/admin/import-json', array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $req ) {
				$target = 0;
				if ( $req['test'] ) {
					$term = get_term_by( 'slug', sanitize_title( $req['test'] ), TNP_IQ_Questions::TAX );
					if ( ! $term ) {
						return new WP_Error( 'tnp_no_test', 'Không có bài test này.', array( 'status' => 404 ) );
					}
					$target = $term->term_id;
				}
				return self::import( $req['data'], array(
					'target'   => $target,
					'mode'     => 'replace' === $req['mode'] ? 'replace' : 'append',
					'status'   => 'draft' === $req['status'] ? 'draft' : 'publish',
					'sideload' => (bool) $req['sideload'],
					'dry_run'  => (bool) $req['dry_run'],
				) );
			},
			'permission_callback' => $admin,
			'args'                => array(
				'data'     => array( 'type' => 'object', 'required' => true ),
				'test'     => array( 'type' => 'string' ), // slug bài có sẵn; bỏ trống = tạo bài mới theo file
				'mode'     => array( 'type' => 'string', 'enum' => array( 'append', 'replace' ), 'default' => 'append' ),
				'status'   => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'default' => 'publish' ),
				'sideload' => array( 'type' => 'boolean', 'default' => false ),
				'dry_run'  => array( 'type' => 'boolean', 'default' => false ),
			),
		) );
	}
}

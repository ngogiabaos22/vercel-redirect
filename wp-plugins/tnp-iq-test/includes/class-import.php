<?php
defined( 'ABSPATH' ) || exit;

/**
 * Nhập câu hỏi hàng loạt từ văn bản dán vào.
 *
 * Định dạng (mỗi mục một dòng; dòng trống và dòng bắt đầu bằng # được bỏ qua):
 *
 *   CÂU 1
 *   Nội dung câu hỏi
 *   A
 *   Đáp án A          (hoặc viết liền: "A. Đáp án A", "A) …", "A: …")
 *   B
 *   Đáp án B
 *   ĐÁP ÁN: B                      ← bài IQ
 *   NHÓM: Tự điều chỉnh            ← test tính cách: một nhóm cho cả câu…
 *   NHÓM: A=D, B=I, C=S, D=C       ← …hoặc từng đáp án (DISC, MBTI)
 *   ĐIỂM: A=1, B=3, C=1, D=0       ← điểm từng đáp án (bỏ trống = 1)
 */
class TNP_IQ_Import {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 14 );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . TNP_IQ_CPT, 'Nhập câu hỏi hàng loạt', 'Nhập câu hỏi', 'manage_options', 'tnp-iq-import', array( __CLASS__, 'render' ) );
	}

	/* ---------- Đọc văn bản ---------- */

	private static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/** Tìm khoá nhóm từ khoá ("sr", "D") hoặc tên ("Tự điều chỉnh", "Thủ lĩnh"). */
	public static function resolve_dim( $value, array $dims ) {
		$v = self::lower( trim( $value ) );
		if ( '' === $v ) {
			return '';
		}
		foreach ( $dims as $key => $label ) {
			$l     = self::lower( $label );
			$names = array( self::lower( $key ), $l );
			// "D – Thủ lĩnh (Dominance)" → cũng nhận "thủ lĩnh", "dominance", "d – thủ lĩnh".
			if ( preg_match( '/^\S+\s+[–-]\s+(.+?)(?:\s*\((.+)\))?$/u', $l, $m ) ) {
				$names[] = trim( $m[1] );
				if ( ! empty( $m[2] ) ) {
					$names[] = trim( $m[2] );
				}
			}
			if ( in_array( $v, $names, true ) ) {
				return $key;
			}
		}
		return '';
	}

	/** Tách "A=1, B=3" / "A: D; B: I" thành [ 'A' => '1', 'B' => '3' ]. */
	private static function pairs( $s ) {
		$out = array();
		foreach ( preg_split( '/[,;]+/u', $s ) as $part ) {
			if ( preg_match( '/^\s*([A-H])\s*[=:]\s*(.+?)\s*$/u', $part, $m ) ) {
				$out[ $m[1] ] = $m[2];
			}
		}
		return $out;
	}

	/**
	 * @param string $text
	 * @param string $type iq | eq | disc | mbti | holland
	 * @return array [ 'questions' => [ [title, body, options => [ [text, dim, pts] ], correct], … ], 'errors' => [ 'Câu 3: …' ] ]
	 */
	public static function parse( $text, $type ) {
		$dims  = 'iq' === $type ? array() : TNP_IQ_Tests::types()[ $type ]['dims'];
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$qs    = array();
		$cur   = null;
		$opt   = null;     // chữ cái đáp án đang đọc
		$await = false;    // vừa gặp dòng chỉ có chữ cái → dòng sau là nội dung đáp án

		$start = function ( $label ) use ( &$cur, &$qs, &$opt, &$await ) {
			if ( null !== $cur ) {
				$qs[] = $cur;
			}
			$cur   = array( 'label' => $label, 'title' => '', 'body' => array(), 'options' => array(), 'correct' => '', 'dim' => '', 'dims' => array(), 'pts' => array() );
			$opt   = null;
			$await = false;
		};

		foreach ( $lines as $raw ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $raw ) );
			if ( '' === $line || '#' === $line[0] ) { // dòng trống hoặc ghi chú
				continue;
			}
			if ( preg_match( '/^(?:câu|cau)\s*(\d+)\s*[:.)\-]?\s*(.*)$/iu', $line, $m ) ) {
				$start( 'Câu ' . $m[1] );
				if ( '' !== $m[2] ) {
					$cur['title'] = $m[2];
				}
				continue;
			}
			if ( null === $cur ) {
				$start( 'Câu ' . ( count( $qs ) + 1 ) );
			}
			if ( preg_match( '/^(?:đáp án|dap an|đáp án đúng)\s*[:：]\s*([A-H])\b/iu', $line, $m ) ) {
				$cur['correct'] = strtoupper( $m[1] );
				$await          = false;
				continue;
			}
			if ( preg_match( '/^(?:nhóm|nhom)\s*[:：]\s*(.+)$/iu', $line, $m ) ) {
				if ( false !== strpos( $m[1], '=' ) || preg_match( '/^[A-H]\s*:/u', $m[1] ) ) {
					$cur['dims'] = self::pairs( $m[1] );
				} else {
					$cur['dim'] = $m[1];
				}
				$await = false;
				continue;
			}
			if ( preg_match( '/^(?:điểm|diem)\s*[:：]\s*(.+)$/iu', $line, $m ) ) {
				$cur['pts'] = self::pairs( $m[1] );
				$await      = false;
				continue;
			}
			// Đáp án: dòng chỉ có chữ cái, hoặc "A. nội dung" — chỉ nhận khi đã có tiêu đề câu hỏi.
			if ( '' !== $cur['title'] && preg_match( '/^([A-H])$/u', $line, $m ) ) {
				$opt                     = $m[1];
				$cur['options'][ $opt ] = '';
				$await                   = true;
				continue;
			}
			if ( '' !== $cur['title'] && preg_match( '/^([A-H])\s*[.):\-]\s+(.+)$/u', $line, $m ) ) {
				$opt                     = $m[1];
				$cur['options'][ $opt ] = $m[2];
				$await                   = false;
				continue;
			}
			if ( $await && null !== $opt ) {
				$cur['options'][ $opt ] = $line;
				$await                  = false;
				continue;
			}
			if ( '' === $cur['title'] ) {
				$cur['title'] = $line;
			} elseif ( null !== $opt ) {
				$cur['options'][ $opt ] .= ' ' . $line; // đáp án dài xuống dòng
			} else {
				$cur['body'][] = $line; // đề bài nhiều dòng
			}
		}
		if ( null !== $cur ) {
			$qs[] = $cur;
		}

		$out    = array();
		$errors = array();
		foreach ( $qs as $q ) {
			$err  = array();
			$opts = array();
			foreach ( $q['options'] as $letter => $text ) {
				$text = trim( $text );
				if ( '' === $text ) {
					$err[] = "đáp án {$letter} trống";
					continue;
				}
				$dim = '';
				$pts = 1.0;
				if ( 'iq' !== $type ) {
					$raw = $q['dims'][ $letter ] ?? $q['dim'];
					$dim = self::resolve_dim( $raw, $dims );
					if ( '' === $dim ) {
						$err[] = '' === trim( $raw ) ? "đáp án {$letter} chưa có NHÓM" : "nhóm “{$raw}” không có trong loại test này";
					}
					if ( isset( $q['pts'][ $letter ] ) ) {
						if ( ! is_numeric( str_replace( ',', '.', $q['pts'][ $letter ] ) ) ) {
							$err[] = "điểm của đáp án {$letter} không phải số";
						}
						$pts = (float) str_replace( ',', '.', $q['pts'][ $letter ] );
					}
				}
				$opts[] = array( 'letter' => $letter, 'text' => $text, 'dim' => $dim, 'pts' => $pts );
			}
			if ( '' === $q['title'] ) {
				$err[] = 'thiếu nội dung câu hỏi';
			}
			if ( count( $opts ) < TNP_IQ_Questions::MIN_OPTIONS ) {
				$err[] = 'cần ít nhất ' . TNP_IQ_Questions::MIN_OPTIONS . ' đáp án';
			}
			if ( count( $opts ) > TNP_IQ_Questions::MAX_OPTIONS ) {
				$err[] = 'tối đa ' . TNP_IQ_Questions::MAX_OPTIONS . ' đáp án';
			}
			if ( 'iq' === $type && ! isset( $q['options'][ $q['correct'] ] ) ) {
				$err[] = 'thiếu dòng “ĐÁP ÁN: …” (bài IQ cần đáp án đúng)';
			}
			if ( $err ) {
				$errors[] = $q['label'] . ': ' . implode( '; ', array_unique( $err ) ) . '.';
			}
			$out[] = array(
				'label'   => $q['label'],
				'title'   => $q['title'],
				'body'    => implode( "\n", $q['body'] ),
				'options' => $opts,
				'correct' => $q['correct'],
				'ok'      => ! $err,
			);
		}
		if ( ! $out ) {
			$errors[] = 'Không tìm thấy câu hỏi nào. Mỗi câu bắt đầu bằng dòng “CÂU 1”, “CÂU 2”…';
		}
		return array( 'questions' => $out, 'errors' => $errors );
	}

	/* ---------- Tạo câu hỏi ---------- */

	public static function create( array $q, $term_id, $status, $order ) {
		$options = array();
		$correct = '';
		foreach ( $q['options'] as $i => $o ) {
			$k             = TNP_IQ_Questions::letter( $i );
			$options[ $k ] = array( 'img' => 0, 'text' => $o['text'], 'dim' => $o['dim'], 'pts' => $o['pts'] );
			if ( $o['letter'] === $q['correct'] ) {
				$correct = $k;
			}
		}
		$id = wp_insert_post( array(
			'post_type'   => TNP_IQ_CPT,
			'post_status' => $status,
			'post_title'  => $q['title'],
			'menu_order'  => $order,
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$meta = array(
			'type'       => 'question',
			'body'       => $q['body'],
			'image'      => 0,
			'options'    => $options,
			'correct'    => $correct,
			'explain'    => '',
			'category'   => 'logic',
			'difficulty' => 3,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, '_tnp_' . $k, $v );
		}
		if ( $term_id ) {
			wp_set_object_terms( $id, array( (int) $term_id ), TNP_IQ_Questions::TAX );
		}
		return (int) $id;
	}

	/* ---------- Trang quản trị ---------- */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$terms   = get_terms( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'hide_empty' => false ) );
		$terms   = is_wp_error( $terms ) ? array() : $terms;
		$posted  = isset( $_POST['tnp_import_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['tnp_import_nonce'] ), 'tnp_import' );
		$text    = $posted ? sanitize_textarea_field( wp_unslash( $_POST['tnp_text'] ?? '' ) ) : '';
		$term_id = $posted ? absint( $_POST['tnp_test'] ?? 0 ) : absint( $_GET['test'] ?? 0 );
		$status  = $posted && 'draft' === ( $_POST['tnp_status'] ?? '' ) ? 'draft' : 'publish';
		$term    = $term_id ? get_term( $term_id, TNP_IQ_Questions::TAX ) : null;
		$type    = $term && ! is_wp_error( $term ) ? TNP_IQ_Tests::type_of( $term->slug ) : 'iq';
		$result  = $posted && '' !== trim( $text ) ? self::parse( $text, $type ) : null;
		$done    = 0;

		if ( $result && isset( $_POST['tnp_do_import'] ) && ! $result['errors'] ) {
			$base = (int) wp_count_posts( TNP_IQ_CPT )->publish;
			foreach ( $result['questions'] as $i => $q ) {
				$done += self::create( $q, $term_id, $status, $base + $i + 1 ) ? 1 : 0;
			}
		}
		$dims = 'iq' === $type ? array() : TNP_IQ_Tests::types()[ $type ]['dims'];
		?>
		<div class="wrap tnp-iq-admin">
			<h1>Nhập câu hỏi hàng loạt</h1>

			<?php if ( $done ) : ?>
				<div class="notice notice-success"><p><strong>Đã nhập <?php echo (int) $done; ?> câu hỏi</strong><?php echo $term ? ' vào bài “' . esc_html( $term->name ) . '”' : ''; ?>. <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . ( $term ? '&' . TNP_IQ_Questions::TAX . '=' . $term->slug : '' ) ) ); ?>">Xem danh sách câu hỏi</a></p></div>
			<?php endif; ?>

			<div class="tnp-iq-box">
				<h2>Định dạng</h2>
				<p>Mỗi câu bắt đầu bằng dòng <code>CÂU 1</code>, <code>CÂU 2</code>… rồi đến nội dung câu hỏi và các đáp án. Đáp án viết <code>A</code> ở một dòng và nội dung ở dòng sau, hoặc viết liền <code>A. nội dung</code>. Sau đáp án, thêm dòng chấm điểm tuỳ loại test:</p>
				<ul style="list-style:disc;padding-left:20px">
					<li><strong>IQ:</strong> <code>ĐÁP ÁN: B</code></li>
					<li><strong>EQ, Holland</strong> (cả câu một nhóm): <code>NHÓM: Tự điều chỉnh</code> và <code>ĐIỂM: A=1, B=3, C=1, D=0</code></li>
					<li><strong>DISC, MBTI</strong> (mỗi đáp án một nhóm): <code>NHÓM: A=D, B=I, C=S, D=C</code> (điểm bỏ trống = 1)</li>
				</ul>
				<?php if ( $dims ) : ?>
					<p>Nhóm hợp lệ của bài này: <?php echo esc_html( implode( ' · ', array_map( function ( $k, $l ) { return $k . ' = ' . $l; }, array_keys( $dims ), $dims ) ) ); ?></p>
				<?php endif; ?>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'tnp_import', 'tnp_import_nonce' ); ?>
				<p>
					<label><strong>Nhập vào bài test:</strong>
						<select name="tnp_test">
							<option value="0">— Chung (IQ, [tnp_iq_test]) —</option>
							<?php foreach ( $terms as $t ) : ?>
								<option value="<?php echo (int) $t->term_id; ?>" <?php selected( $term_id, $t->term_id ); ?>><?php echo esc_html( $t->name . ' (' . TNP_IQ_Tests::types()[ TNP_IQ_Tests::type_of( $t->slug ) ]['short'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					&nbsp; <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . TNP_IQ_Questions::TAX . '&post_type=' . TNP_IQ_CPT ) ); ?>">Tạo bài test mới</a>
					&nbsp; <label><input type="checkbox" name="tnp_status" value="draft" <?php checked( 'draft', $posted ? $status : '' ); ?>> Lưu nháp (chưa xuất bản)</label>
				</p>
				<textarea name="tnp_text" rows="18" class="large-text code" placeholder="CÂU 1&#10;Nội dung câu hỏi&#10;A&#10;Đáp án A&#10;B&#10;Đáp án B&#10;NHÓM: Tự điều chỉnh&#10;ĐIỂM: A=1, B=3"><?php echo esc_textarea( $text ); ?></textarea>
				<p>
					<button class="button" name="tnp_check" value="1">Kiểm tra</button>
					<button class="button button-primary" name="tnp_do_import" value="1">Nhập câu hỏi</button>
					<span class="description">Bấm “Kiểm tra” để xem trước. Chỉ nhập khi không còn lỗi.</span>
				</p>
			</form>

			<?php if ( $result ) : ?>
				<?php if ( $result['errors'] ) : ?>
					<div class="notice notice-error"><p><strong>Có <?php echo count( $result['errors'] ); ?> lỗi, chưa nhập câu nào:</strong></p><ul style="list-style:disc;padding-left:20px"><?php foreach ( $result['errors'] as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
				<?php elseif ( ! $done ) : ?>
					<div class="notice notice-info"><p>Đọc được <strong><?php echo count( $result['questions'] ); ?> câu</strong>, không có lỗi. Kiểm tra bảng bên dưới rồi bấm <strong>Nhập câu hỏi</strong>.</p></div>
				<?php endif; ?>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th style="width:70px">Câu</th><th>Nội dung</th><th>Đáp án</th></tr></thead>
					<tbody>
					<?php foreach ( $result['questions'] as $q ) : ?>
						<tr<?php echo $q['ok'] ? '' : ' style="background:#fcf0f1"'; ?>>
							<td><?php echo esc_html( $q['label'] ); ?></td>
							<td><?php echo esc_html( $q['title'] ); ?><?php echo '' !== $q['body'] ? '<br><small>' . nl2br( esc_html( $q['body'] ) ) . '</small>' : ''; ?></td>
							<td>
								<?php foreach ( $q['options'] as $o ) : ?>
									<div>
										<strong><?php echo esc_html( $o['letter'] ); ?>.</strong> <?php echo esc_html( $o['text'] ); ?>
										<?php if ( 'iq' === $type && $o['letter'] === $q['correct'] ) : ?><span style="color:#00a32a;font-weight:700">✓ đúng</span><?php endif; ?>
										<?php if ( 'iq' !== $type ) : ?><span style="color:#2271b1">→ <?php echo esc_html( ( $dims[ $o['dim'] ] ?? '?' ) . ', ' . ( 0 + $o['pts'] ) . ' điểm' ); ?></span><?php endif; ?>
									</div>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

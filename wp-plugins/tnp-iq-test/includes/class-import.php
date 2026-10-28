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
 *
 * Ảnh (lấy từ Thư viện Media theo tên ảnh, tên file, đường dẫn hoặc ID):
 *   ẢNH: câu 1                     ← trước các đáp án: ảnh đề bài
 *   A. [ảnh: 1.1]                  ← đáp án là ảnh (có thể kèm chữ phía sau)
 *   A                              ← hoặc: dòng chữ cái, rồi dòng "ẢNH: 1.1"
 *   ẢNH: 1.1
 */
class TNP_IQ_Import {

	/** Hàm tìm ảnh (đổi được khi kiểm thử). Nhận chuỗi tham chiếu, trả về ID ảnh hoặc 0. */
	public static $media_resolver = null;
	private static $media_cache   = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 14 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * POST /tnp-iq/v1/admin/import — nhập câu hỏi qua REST (chỉ quản trị viên).
	 * test: slug bài test (tự tạo nếu chưa có, với name/type), text: văn bản định dạng như trang Nhập câu hỏi,
	 * dry_run: chỉ kiểm tra, replace: chuyển câu hỏi cũ của bài vào thùng rác trước khi nhập.
	 */
	public static function routes() {
		register_rest_route( TNP_IQ_Rest::NS, '/admin/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rest_import' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'test'    => array( 'type' => 'string', 'required' => true ),
				'name'    => array( 'type' => 'string' ),
				'type'    => array( 'type' => 'string', 'enum' => array_keys( TNP_IQ_Tests::types() ) ),
				'text'    => array( 'type' => 'string', 'required' => true ),
				'status'  => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'default' => 'publish' ),
				'dry_run' => array( 'type' => 'boolean', 'default' => false ),
				'replace' => array( 'type' => 'boolean', 'default' => false ),
				// IQ: cho phép câu chưa có ĐÁP ÁN — câu đó được lưu nháp để điền đáp án sau.
				'allow_no_answer' => array( 'type' => 'boolean', 'default' => false ),
			),
		) );

		$admin = function () {
			return current_user_can( 'manage_options' );
		};
		// GET: danh sách câu hỏi của một bài test (kể cả nháp) để kiểm tra; POST: sửa nhóm năng lực / độ khó hàng loạt.
		register_rest_route( TNP_IQ_Rest::NS, '/admin/questions', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_list' ),
				'permission_callback' => $admin,
				'args'                => array( 'test' => array( 'type' => 'string', 'required' => true ) ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_update' ),
				'permission_callback' => $admin,
				'args'                => array( 'items' => array( 'type' => 'array', 'required' => true ) ),
			),
		) );
	}

	public static function rest_list( WP_REST_Request $req ) {
		$ids = get_posts( array(
			'post_type'      => TNP_IQ_CPT,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'slug', 'terms' => sanitize_title( $req['test'] ) ) ),
		) );
		return array_map( function ( $id ) {
			$q = TNP_IQ_Questions::get( $id );
			return array(
				'id'         => $id,
				'order'      => (int) get_post_field( 'menu_order', $id ),
				'status'     => get_post_status( $id ),
				'title'      => TNP_IQ_Questions::plain( get_the_title( $id ) ),
				'image'      => $q['image'],
				'options'    => count( $q['options'] ),
				'correct'    => $q['correct'],
				'category'   => $q['category'],
				'difficulty' => $q['difficulty'],
				'complete'   => TNP_IQ_Questions::is_complete( $q ),
			);
		}, $ids );
	}

	/** items: [{id, category?, difficulty?, explain?}] — chỉ sửa câu hỏi thuộc plugin, giá trị được kiểm tra như khi lưu trong trang quản trị. */
	public static function rest_update( WP_REST_Request $req ) {
		$done = array();
		foreach ( (array) $req['items'] as $it ) {
			$id = absint( $it['id'] ?? 0 );
			if ( ! $id || TNP_IQ_CPT !== get_post_type( $id ) ) {
				continue;
			}
			if ( isset( $it['category'] ) && array_key_exists( $it['category'], TNP_IQ_Questions::CATEGORIES ) ) {
				update_post_meta( $id, '_tnp_category', $it['category'] );
			}
			if ( isset( $it['difficulty'] ) ) {
				update_post_meta( $id, '_tnp_difficulty', max( 1, min( 5, (int) $it['difficulty'] ) ) );
			}
			if ( isset( $it['explain'] ) ) {
				update_post_meta( $id, '_tnp_explain', sanitize_textarea_field( $it['explain'] ) );
			}
			$done[] = $id;
		}
		return array( 'updated' => $done );
	}

	public static function rest_import( WP_REST_Request $req ) {
		$slug = sanitize_title( $req['test'] );
		$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
		$type = $term ? TNP_IQ_Tests::type_of( $slug ) : ( $req['type'] ?: 'iq' );

		$loose  = 'iq' === $type && $req['allow_no_answer'];
		$result = self::parse( (string) $req['text'], $type, ! $loose );
		$out    = array(
			'test'      => array( 'slug' => $slug, 'name' => $term ? $term->name : ( $req['name'] ?: $slug ), 'type' => $type, 'exists' => (bool) $term ),
			'found'     => count( $result['questions'] ),
			'errors'    => $result['errors'],
			'questions' => array_map( function ( $q ) {
				return array(
					'label'   => $q['label'],
					'title'   => $q['title'],
					'image'   => $q['image'],
					'options' => array_map( function ( $o ) { return $o['letter'] . ':' . ( $o['img'] ? 'img#' . $o['img'] : $o['text'] ); }, $q['options'] ),
					'correct' => $q['correct'],
				);
			}, $result['questions'] ),
			'created'   => array(),
			'drafts'    => array(),
			'trashed'   => 0,
		);
		if ( $req['dry_run'] || $result['errors'] ) {
			return rest_ensure_response( $out );
		}

		if ( ! $term ) {
			$made = wp_insert_term( $req['name'] ?: $slug, TNP_IQ_Questions::TAX, array( 'slug' => $slug ) );
			if ( is_wp_error( $made ) ) {
				return $made;
			}
			$term = get_term( $made['term_id'], TNP_IQ_Questions::TAX );
			update_term_meta( $term->term_id, 'tnp_type', $type );
			update_term_meta( $term->term_id, 'tnp_shuffle', '1' );
		}
		if ( $req['replace'] ) {
			$old = get_posts( array( 'post_type' => TNP_IQ_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $old as $id ) {
				$out['trashed'] += wp_trash_post( $id ) ? 1 : 0;
			}
		}
		foreach ( $result['questions'] as $i => $q ) {
			$no_answer = 'iq' === $type && '' === $q['correct'];
			$id        = self::create( $q, $term->term_id, $no_answer ? 'draft' : $req['status'], $i + 1 );
			if ( $id ) {
				$out['created'][] = $id;
				if ( $no_answer ) {
					$out['drafts'][] = $id;
				}
			}
		}
		$out['test']['exists'] = true;
		return rest_ensure_response( $out );
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

	/** Tìm ảnh trong Thư viện Media: ID, đường dẫn, tên ảnh ("câu 1") hoặc tên file ("cau-1.png", "1.1"). */
	public static function find_media( $ref ) {
		$ref = trim( (string) $ref );
		if ( '' === $ref ) {
			return 0;
		}
		global $wpdb;
		if ( isset( self::$media_cache[ $ref ] ) ) {
			return self::$media_cache[ $ref ];
		}
		if ( is_callable( self::$media_resolver ) ) {
			return self::$media_cache[ $ref ] = (int) call_user_func( self::$media_resolver, $ref );
		}
		$id = 0;
		if ( ctype_digit( $ref ) && 'attachment' === get_post_type( (int) $ref ) ) {
			$id = (int) $ref;
		} elseif ( preg_match( '#^https?://#i', $ref ) ) {
			$id = (int) attachment_url_to_postid( $ref );
		}
		if ( ! $id ) {
			// Tên ảnh (tiêu đề) — so khớp không phân biệt hoa thường.
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title = %s ORDER BY ID DESC LIMIT 1", $ref ) );
		}
		if ( ! $id ) {
			// Tên file: "1.1" → ".../1.1.png", "cau-1.png" → ".../cau-1.png".
			$file = sanitize_file_name( remove_accents( $ref ) );
			$like = $wpdb->esc_like( pathinfo( $file, PATHINFO_EXTENSION ) ? $file : $file . '.' ) . '%';
			$id   = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND ( meta_value LIKE %s OR meta_value LIKE %s ) ORDER BY post_id DESC LIMIT 1",
				$like,
				'%/' . $like
			) );
		}
		return self::$media_cache[ $ref ] = $id;
	}

	/** "[ảnh: 1.1] chữ" hoặc "ảnh: 1.1" → [ 'ref' => '1.1', 'text' => 'chữ' ]; không có ảnh → null. */
	private static function split_image( $text ) {
		if ( preg_match( '/^\[\s*(?:ảnh|anh|hình|hinh|image)\s*[:：]\s*([^\]]+)\]\s*(.*)$/iu', $text, $m ) || preg_match( '/^(?:ảnh|anh|hình|hinh|image)\s*[:：]\s*(.+)$/iu', $text, $m ) ) {
			return array( 'ref' => trim( $m[1] ), 'text' => trim( $m[2] ?? '' ) );
		}
		return null;
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
	 * @param string $type iq | eq | aq | disc | mbti | holland
	 * @return array [ 'questions' => [ [title, body, options => [ [text, dim, pts] ], correct], … ], 'errors' => [ 'Câu 3: …' ] ]
	 */
	public static function parse( $text, $type, $require_answer = true ) {
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
			$cur   = array( 'label' => $label, 'title' => '', 'body' => array(), 'image' => '', 'options' => array(), 'oimg' => array(), 'correct' => '', 'dim' => '', 'dims' => array(), 'pts' => array() );
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
			// "ẢNH: …" — trước đáp án là ảnh đề bài, ngay sau một đáp án là ảnh của đáp án đó.
			if ( preg_match( '/^(?:ảnh|anh|hình|hinh|image)\s*[:：]\s*(.+)$/iu', $line, $m ) ) {
				if ( null === $opt ) {
					$cur['image'] = trim( $m[1] );
				} else {
					$cur['oimg'][ $opt ] = trim( $m[1] );
				}
				$await = false;
				continue;
			}
			// Đáp án: dòng chỉ có chữ cái, hoặc "A. nội dung" — chỉ nhận khi đã có tiêu đề câu hỏi.
			if ( '' !== $cur['title'] && preg_match( '/^([A-H])$/u', $line, $m ) ) {
				$opt                     = $m[1];
				$cur['options'][ $opt ] = '';
				$await                   = true;
				continue;
			}
			if ( '' !== $cur['title'] && preg_match( '/^([A-H])\s*[.):\-](?:\s+(\S.*)|\s*(\[.+))$/u', $line, $m ) ) {
				$opt  = $m[1];
				$m[2] = '' !== ( $m[2] ?? '' ) ? $m[2] : $m[3];
				$part = self::split_image( $m[2] );
				if ( $part ) {
					$cur['oimg'][ $opt ]    = $part['ref'];
					$cur['options'][ $opt ] = $part['text'];
				} else {
					$cur['options'][ $opt ] = $m[2];
				}
				$await = false;
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
			$qimg = 0;
			if ( '' !== $q['image'] ) {
				$qimg = self::find_media( $q['image'] );
				if ( ! $qimg ) {
					$err[] = "không tìm thấy ảnh đề bài “{$q['image']}” trong Thư viện Media";
				}
			}
			foreach ( $q['options'] as $letter => $text ) {
				$text = trim( $text );
				$img  = 0;
				if ( isset( $q['oimg'][ $letter ] ) ) {
					$img = self::find_media( $q['oimg'][ $letter ] );
					if ( ! $img ) {
						$err[] = "không tìm thấy ảnh đáp án {$letter} “{$q['oimg'][ $letter ]}”";
					}
				}
				if ( '' === $text && ! isset( $q['oimg'][ $letter ] ) ) {
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
				$opts[] = array( 'letter' => $letter, 'text' => $text, 'img' => $img, 'dim' => $dim, 'pts' => $pts );
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
			if ( 'iq' === $type && $require_answer && ! isset( $q['options'][ $q['correct'] ] ) ) {
				$err[] = 'thiếu dòng “ĐÁP ÁN: …” (bài IQ cần đáp án đúng)';
			}
			if ( $err ) {
				$errors[] = $q['label'] . ': ' . implode( '; ', array_unique( $err ) ) . '.';
			}
			$out[] = array(
				'label'   => $q['label'],
				'title'   => $q['title'],
				'body'    => implode( "\n", $q['body'] ),
				'image'   => $qimg,
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
			$options[ $k ] = array( 'img' => (int) ( $o['img'] ?? 0 ), 'text' => $o['text'], 'dim' => $o['dim'], 'pts' => $o['pts'] );
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
			'type'       => ! empty( $q['image'] ) ? 'image' : 'question',
			'body'       => $q['body'],
			'image'      => (int) ( $q['image'] ?? 0 ),
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
					<li><strong>Ảnh</strong> (đã tải lên Thư viện Media, ghi theo tên ảnh hoặc tên file): ảnh đề bài <code>ẢNH: câu 1</code> đặt trước các đáp án; đáp án là ảnh <code>A. [ảnh: 1.1]</code></li>
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
							<td><?php echo esc_html( $q['title'] ); ?><?php echo '' !== $q['body'] ? '<br><small>' . nl2br( esc_html( $q['body'] ) ) . '</small>' : ''; ?>
								<?php if ( $q['image'] ) : ?><br><img src="<?php echo esc_url( wp_get_attachment_image_url( $q['image'], 'thumbnail' ) ); ?>" alt="" style="max-width:120px;margin-top:6px;border:1px solid #dcdcde;border-radius:6px"><?php endif; ?></td>
							<td>
								<?php foreach ( $q['options'] as $o ) : ?>
									<div>
										<strong><?php echo esc_html( $o['letter'] ); ?>.</strong>
										<?php if ( $o['img'] ) : ?><img src="<?php echo esc_url( wp_get_attachment_image_url( $o['img'], 'thumbnail' ) ); ?>" alt="" style="width:40px;height:40px;object-fit:contain;vertical-align:middle;border:1px solid #dcdcde;border-radius:4px"><?php endif; ?>
										<?php echo esc_html( $o['text'] ); ?>
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

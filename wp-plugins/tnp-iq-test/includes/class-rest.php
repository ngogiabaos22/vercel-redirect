<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API:
 *  GET  /tnp-iq/v1/start   → danh sách câu hỏi (không có đáp án) + mã lượt làm có chữ ký
 *  POST /tnp-iq/v1/submit  → chấm điểm trên máy chủ, lưu kết quả, trả về IQ
 */
class TNP_IQ_Rest {

	const NS             = 'tnp-iq/v1';
	const RATE_PER_HOUR  = 30;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( self::NS, '/start', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'start' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'count' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 200 ),
				'test'  => array( 'type' => 'string' ),
				'time'  => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 180 ),
			),
		) );
		register_rest_route( self::NS, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'submit' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'token'   => array( 'type' => 'string', 'required' => true ),
				'answers' => array( 'type' => 'object', 'required' => true ),
				'age'     => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ), // bắt buộc với IQ (kiểm tra trong submit)
				'elapsed' => array( 'type' => 'integer', 'minimum' => 0 ),
			),
		) );
	}

	/* ---------- Mã lượt làm (chống sửa danh sách câu / thời gian) ---------- */

	private static function sign( array $payload ) {
		$body = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );
		return $body . '.' . hash_hmac( 'sha256', $body, wp_salt( 'auth' ) . 'tnp-iq' );
	}

	private static function verify( $token ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) . 'tnp-iq' ), $parts[1] ) ) {
			return false;
		}
		$data = json_decode( base64_decode( strtr( $parts[0], '-_', '+/' ) ), true );
		return is_array( $data ) && isset( $data['ids'], $data['t'], $data['limit'] ) ? $data : false;
	}

	private static function ip_hash() {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		return md5( $ip . wp_salt( 'nonce' ) );
	}

	/* ---------- Lấy đề ---------- */

	public static function questions( $count, $order, $test = '' ) {
		$args = array(
			'post_type'      => TNP_IQ_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			'no_found_rows'  => true,
		);
		if ( '' !== $test ) {
			$args['tax_query'] = array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'slug', 'terms' => $test ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		} else {
			// Bài chung [tnp_iq_test]: chỉ câu IQ — bỏ câu thuộc các bài EQ, DISC, MBTI, Holland.
			$other = array();
			foreach ( get_terms( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'hide_empty' => false ) ) as $t ) {
				if ( 'iq' !== TNP_IQ_Tests::type_of( $t->slug ) ) {
					$other[] = $t->term_id;
				}
			}
			if ( $other ) {
				$args['tax_query'] = array( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'field' => 'term_id', 'terms' => $other, 'operator' => 'NOT IN' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			}
		}
		$posts = get_posts( $args );
		$posts = array_values( array_filter( $posts, function ( $p ) {
			return TNP_IQ_Questions::is_complete( TNP_IQ_Questions::get( $p->ID ) );
		} ) );

		if ( 'random' === $order ) {
			shuffle( $posts );
		}
		if ( $count > 0 ) {
			$posts = array_slice( $posts, 0, $count );
		}
		return $posts;
	}

	public static function start( WP_REST_Request $req ) {
		$test  = sanitize_title( (string) $req['test'] );
		$kind  = TNP_IQ_Tests::type_of( $test );
		$is_iq = 'iq' === $kind;
		// Số câu và thời gian tối đa trong Cài đặt chỉ áp dụng cho IQ; test tính cách mặc định dùng hết câu, không giới hạn giờ.
		$count = null !== $req['count'] ? (int) $req['count'] : ( $is_iq ? (int) TNP_IQ_Settings::get( 'question_count' ) : 0 );
		$time  = null !== $req['time'] ? (int) $req['time'] : ( $is_iq ? (int) TNP_IQ_Settings::get( 'time_limit' ) : 0 );
		$posts = self::questions( $count, TNP_IQ_Settings::get( 'order' ), $test );

		if ( ! $posts ) {
			return new WP_Error( 'tnp_iq_empty', 'Bài test chưa có câu hỏi nào. Vui lòng quay lại sau.', array( 'status' => 404 ) );
		}

		$ids = wp_list_pluck( $posts, 'ID' );
		TNP_IQ_Results::bump( $test, 'views' );
		return rest_ensure_response( array(
			'token'     => self::sign( array( 'ids' => $ids, 't' => time(), 'limit' => $time * 60, 'k' => $test ) ),
			'timeLimit' => $time * 60,
			'kind'      => $kind,
			'needsAge'  => $is_iq,
			'showTimer' => $is_iq || $time > 0,
			'questions' => self::maybe_shuffle( array_map( array( 'TNP_IQ_Questions', 'public_data' ), $posts ), TNP_IQ_Tests::shuffle_of( $test ) ),
		) );
	}

	/* ---------- Chấm điểm ---------- */

	public static function submit( WP_REST_Request $req ) {
		$data = self::verify( $req['token'] );
		if ( ! $data ) {
			return new WP_Error( 'tnp_iq_token', 'Lượt làm bài không hợp lệ hoặc đã hết hạn. Vui lòng làm lại.', array( 'status' => 400 ) );
		}
		if ( time() - (int) $data['t'] > 6 * HOUR_IN_SECONDS ) {
			return new WP_Error( 'tnp_iq_expired', 'Lượt làm bài đã hết hạn. Vui lòng làm lại.', array( 'status' => 400 ) );
		}

		$ip_hash = self::ip_hash();
		$rate    = 'tnp_iq_rate_' . $ip_hash;
		$used    = (int) get_transient( $rate );
		if ( $used >= self::RATE_PER_HOUR ) {
			return new WP_Error( 'tnp_iq_rate', 'Bạn đã nộp quá nhiều bài trong một giờ. Vui lòng thử lại sau.', array( 'status' => 429 ) );
		}

		$answers = (array) $req['answers'];
		$age     = (int) $req['age'];
		$test    = sanitize_title( (string) ( $data['k'] ?? '' ) );
		$kind    = TNP_IQ_Tests::type_of( $test );
		if ( 'iq' === $kind && ( $age < 5 || $age > 100 ) ) {
			return new WP_Error( 'tnp_iq_age', 'Vui lòng nhập tuổi từ 5 đến 100.', array( 'status' => 400 ) );
		}
		$server  = max( 0, time() - (int) $data['t'] );
		$n       = count( $data['ids'] );
		// Thời gian làm phần câu hỏi do trình duyệt báo (không tính bước nhập tuổi), không vượt thời gian máy chủ đo được.
		$elapsed = null !== $req['elapsed'] ? (int) $req['elapsed'] : $server;
		$elapsed = min( $server, max( $elapsed, min( $server, $n * 3 ) ) );

		if ( 'iq' !== $kind ) {
			return self::submit_trait( $kind, $data, $answers, $test, $elapsed, $ip_hash, $rate, $used );
		}

		$items  = array();
		$cats   = array();
		$stored = array();
		foreach ( $data['ids'] as $id ) {
			$id = (int) $id;
			$q  = TNP_IQ_Questions::get( $id );
			$your = isset( $answers[ $id ] ) && in_array( $answers[ $id ], TNP_IQ_Questions::KEYS, true ) ? $answers[ $id ] : '';
			$ok   = '' !== $your && $your === $q['correct'];

			$items[] = array( 'correct' => $ok, 'weight' => $q['difficulty'] );

			$c = $q['category'];
			if ( ! isset( $cats[ $c ] ) ) {
				$cats[ $c ] = array( 'correct' => 0, 'total' => 0 );
			}
			$cats[ $c ]['total']++;
			$cats[ $c ]['correct'] += $ok ? 1 : 0;

			$stored[ $id ] = $your;
		}

		$result = TNP_IQ_Scoring::score( $items, $age, $elapsed );

		TNP_IQ_Results::insert( array(
			'test_key'   => $test,
			'test_type'  => 'iq',
			'code'       => (string) $result['iq'],
			'iq'         => $result['iq'],
			'correct'    => $result['correct'],
			'total'      => $result['total'],
			'ratio'      => $result['ratio'],
			'age'        => $age,
			'duration'   => $elapsed,
			'categories' => $cats,
			'answers'    => $stored,
			'ip_hash'    => $ip_hash,
		) );
		set_transient( $rate, $used + 1, HOUR_IN_SECONDS );
		TNP_IQ_Results::bump( $test, 'results' );

		$cat_out = array();
		foreach ( $cats as $k => $c ) {
			$cat_out[] = array( 'key' => $k, 'name' => TNP_IQ_Questions::CATEGORIES[ $k ] ?? $k ) + TNP_IQ_Scoring::category_analysis( $k, $c['correct'], $c['total'] );
		}

		$levels = array();
		foreach ( TNP_IQ_Scoring::levels() as $l ) {
			$levels[] = array( 'range' => $l['range'], 'name' => $l['name'], 'share' => $l['share'] );
		}

		// Không trả về số câu đúng, thời gian hay đáp án — trang kết quả chỉ hiện phần phân tích.
		return rest_ensure_response( array(
			'kind'       => 'iq',
			'iq'         => $result['iq'],
			'label'      => $result['label'],
			'percentile' => $result['percentile'],
			'categories' => $cat_out,
			'levels'     => $levels,
		) );
	}

	/** Chấm EQ / DISC / MBTI / Holland theo nhóm và điểm của đáp án đã chọn. */
	private static function submit_trait( $kind, $data, $answers, $test, $elapsed, $ip_hash, $rate, $used ) {
		$picked = array();
		$stored = array();
		foreach ( $data['ids'] as $id ) {
			$id   = (int) $id;
			$q    = TNP_IQ_Questions::get( $id );
			$your = isset( $answers[ $id ] ) && isset( $q['options'][ $answers[ $id ] ] ) ? $answers[ $id ] : '';
			$opts = array();
			foreach ( $q['options'] as $k => $o ) {
				$opts[ $k ] = array( 'dim' => $o['dim'], 'pts' => (float) $o['pts'] );
			}
			$picked[]      = array( 'options' => $opts, 'chosen' => $your );
			$stored[ $id ] = $your;
		}
		$result = TNP_IQ_Tests::score( $kind, $picked );
		$answered = count( array_filter( $stored ) );

		TNP_IQ_Results::insert( array(
			'test_key'   => $test,
			'test_type'  => $kind,
			'code'       => $result['code'],
			'iq'         => 0,
			'correct'    => $answered,
			'total'      => count( $stored ),
			'ratio'      => isset( $result['score'] ) ? $result['score'] / 100 : 0,
			'age'        => 0,
			'duration'   => $elapsed,
			'categories' => isset( $result['bars'] ) ? wp_list_pluck( $result['bars'], 'pct', 'key' ) : ( isset( $result['axes'] ) ? wp_list_pluck( wp_list_pluck( $result['axes'], 'left' ), 'pct', 'key' ) : array() ),
			'answers'    => $stored,
			'ip_hash'    => $ip_hash,
		) );
		set_transient( $rate, $used + 1, HOUR_IN_SECONDS );
		TNP_IQ_Results::bump( $test, 'results' );

		return rest_ensure_response( array( 'kind' => $kind ) + $result );
	}

	/** Xáo trộn thứ tự đáp án (giữ nguyên khoá gốc để máy chủ chấm đúng). */
	private static function maybe_shuffle( array $questions, $on ) {
		if ( $on ) {
			foreach ( $questions as &$q ) {
				shuffle( $q['options'] );
			}
			unset( $q );
		}
		return $questions;
	}
}

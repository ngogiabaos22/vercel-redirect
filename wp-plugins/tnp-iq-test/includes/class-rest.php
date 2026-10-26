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
				'age'     => array( 'type' => 'integer', 'required' => true, 'minimum' => 5, 'maximum' => 100 ),
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

	public static function questions( $count, $order ) {
		$posts = get_posts( array(
			'post_type'      => TNP_IQ_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
			'no_found_rows'  => true,
		) );
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
		$count = null !== $req['count'] ? (int) $req['count'] : (int) TNP_IQ_Settings::get( 'question_count' );
		$time  = null !== $req['time'] ? (int) $req['time'] : (int) TNP_IQ_Settings::get( 'time_limit' );
		$posts = self::questions( $count, TNP_IQ_Settings::get( 'order' ) );

		if ( ! $posts ) {
			return new WP_Error( 'tnp_iq_empty', 'Bài test chưa có câu hỏi nào. Vui lòng quay lại sau.', array( 'status' => 404 ) );
		}

		$ids = wp_list_pluck( $posts, 'ID' );
		return rest_ensure_response( array(
			'token'     => self::sign( array( 'ids' => $ids, 't' => time(), 'limit' => $time * 60 ) ),
			'timeLimit' => $time * 60,
			'questions' => array_map( array( 'TNP_IQ_Questions', 'public_data' ), $posts ),
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
		$limit   = (int) $data['limit'];
		$server  = max( 0, time() - (int) $data['t'] );
		$n       = count( $data['ids'] );
		// Thời gian làm phần câu hỏi do trình duyệt báo (không tính bước nhập tuổi), không vượt thời gian máy chủ đo được.
		$elapsed = null !== $req['elapsed'] ? (int) $req['elapsed'] : $server;
		$elapsed = min( $server, max( $elapsed, min( $server, $n * 3 ) ) );

		$items  = array();
		$cats   = array();
		$review = array();
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
			$review[]      = array(
				'id'      => $id,
				'your'    => $your,
				'correct' => $q['correct'],
				'ok'      => $ok,
				'explain' => $q['explain'],
			);
		}

		$result = TNP_IQ_Scoring::score( $items, $age, $elapsed, $limit );

		TNP_IQ_Results::insert( array(
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

		$cat_out = array();
		foreach ( $cats as $k => $c ) {
			$cat_out[] = array( 'key' => $k, 'name' => TNP_IQ_Questions::CATEGORIES[ $k ] ?? $k ) + $c;
		}

		$show = (int) TNP_IQ_Settings::get( 'show_review' );
		return rest_ensure_response( array(
			'iq'         => $result['iq'],
			'label'      => $result['label'],
			'percentile' => $result['percentile'],
			'correct'    => $result['correct'],
			'total'      => $result['total'],
			'bonus'      => $result['bonus'],
			'elapsed'    => $elapsed,
			'age'        => $age,
			'ageGroup'   => $result['age_group'],
			'categories' => $cat_out,
			'review'     => $show ? $review : array(),
		) );
	}
}

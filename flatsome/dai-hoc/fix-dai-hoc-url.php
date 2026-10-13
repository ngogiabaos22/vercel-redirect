<?php
/**
 * Sửa lỗi: mọi URL /dai-hoc/<bất-kỳ>/ đều hiển thị trang "Đại học".
 * Dán đoạn dưới vào cuối wp-content/themes/flatsome-child/functions.php
 * (không dán dòng "<?php" ở trên nếu file đã có).
 *
 * Chỉ tác động khi URL bắt đầu bằng /dai-hoc/ và WordPress đang định
 * trả về chính trang Đại học. Khi đó WordPress tìm đúng trang con theo
 * đường dẫn: có trang thật → hiện trang đó, không có → 404 mặc định.
 * Trang /dai-hoc/ và mọi URL khác giữ nguyên.
 */
add_filter( 'request', function ( $qv ) {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return $qv;
	}
	$path = trim( rawurldecode( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ), '/' );
	if ( 0 !== strpos( $path, 'dai-hoc/' ) ) {
		return $qv;
	}
	$hub    = get_page_by_path( 'dai-hoc' );
	$to_hub = ( isset( $qv['pagename'] ) && 'dai-hoc' === trim( $qv['pagename'], '/' ) )
		|| ( $hub && isset( $qv['page_id'] ) && (int) $qv['page_id'] === (int) $hub->ID );
	if ( ! $to_hub ) {
		return $qv;
	}
	return array( 'pagename' => $path );
}, 99 );

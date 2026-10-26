<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lưu kết quả mỗi lượt làm bài và trang thống kê trong quản trị.
 */
class TNP_IQ_Results {

	const DB_VERSION = '2';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'tnp_iq_results';
	}

	public static function stats_table() {
		global $wpdb;
		return $wpdb->prefix . 'tnp_iq_stats';
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 15 );
		add_action( 'admin_menu', array( __CLASS__, 'stats_menu' ), 12 );
		add_action( 'admin_post_tnp_iq_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_tnp_iq_clear', array( __CLASS__, 'clear' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			test_key varchar(100) NOT NULL DEFAULT '',
			iq smallint(5) unsigned NOT NULL,
			correct smallint(5) unsigned NOT NULL,
			total smallint(5) unsigned NOT NULL,
			ratio decimal(5,4) NOT NULL,
			age tinyint(3) unsigned NOT NULL,
			duration int(10) unsigned NOT NULL,
			categories text NOT NULL,
			answers text NOT NULL,
			ip_hash char(32) NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY age (age),
			KEY test_key (test_key)
		) {$charset};" );
		$stats = self::stats_table();
		dbDelta( "CREATE TABLE {$stats} (
			test_key varchar(100) NOT NULL DEFAULT '',
			day date NOT NULL,
			views int(10) unsigned NOT NULL DEFAULT 0,
			results int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (test_key,day)
		) {$charset};" );
		update_option( 'tnp_iq_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'tnp_iq_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $row ) {
		global $wpdb;
		$wpdb->insert( self::table(), array(
			'created_at' => current_time( 'mysql' ),
			'test_key'   => $row['test_key'],
			'iq'         => $row['iq'],
			'correct'    => $row['correct'],
			'total'      => $row['total'],
			'ratio'      => $row['ratio'],
			'age'        => $row['age'],
			'duration'   => $row['duration'],
			'categories' => wp_json_encode( $row['categories'] ),
			'answers'    => wp_json_encode( $row['answers'] ),
			'ip_hash'    => $row['ip_hash'],
		) );
		return (int) $wpdb->insert_id;
	}

	/** Tăng bộ đếm theo ngày: $field = 'views' (xem đề) hoặc 'results' (xem kết quả IQ). */
	public static function bump( $test_key, $field ) {
		global $wpdb;
		if ( ! in_array( $field, array( 'views', 'results' ), true ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . self::stats_table() . " (test_key, day, {$field}) VALUES (%s, %s, 1) ON DUPLICATE KEY UPDATE {$field} = {$field} + 1",
			$test_key,
			current_time( 'Y-m-d' )
		) );
	}

	public static function stats_menu() {
		add_submenu_page(
			'edit.php?post_type=' . TNP_IQ_CPT,
			'Thống kê Test IQ',
			'Thống kê',
			'manage_options',
			'tnp-iq-stats',
			array( __CLASS__, 'render_stats' )
		);
	}

	public static function render_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$ranges = array( '1' => 'Hôm nay', '7' => '7 ngày', '30' => '30 ngày', 'all' => 'Tất cả' );
		$range  = sanitize_key( $_GET['range'] ?? '30' );
		$range  = isset( $ranges[ $range ] ) ? $range : '30';
		$from   = 'all' === $range ? '1970-01-01' : gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . ( (int) $range - 1 ) . ' days' ) );

		$st = self::stats_table();
		$rt = self::table();
		$counts = $wpdb->get_results( $wpdb->prepare( "SELECT test_key, SUM(views) views, SUM(results) results FROM {$st} WHERE day >= %s GROUP BY test_key", $from ), OBJECT_K );
		$iqs    = $wpdb->get_results( $wpdb->prepare( "SELECT test_key, AVG(iq) avg_iq FROM {$rt} WHERE created_at >= %s GROUP BY test_key", $from . ' 00:00:00' ), OBJECT_K );

		// Mọi bài test: bài mặc định (tất cả câu hỏi), các bộ đề, và bộ đề đã xoá nhưng còn số liệu.
		$tests = array( '' => TNP_IQ_Questions::test_name( '' ) );
		foreach ( get_terms( array( 'taxonomy' => TNP_IQ_Questions::TAX, 'hide_empty' => false ) ) as $term ) {
			$tests[ $term->slug ] = $term->name;
		}
		foreach ( array_keys( $counts ) as $k ) {
			if ( ! isset( $tests[ $k ] ) ) {
				$tests[ $k ] = $k . ' (đã xoá)';
			}
		}

		$daily = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(views) views, SUM(results) results FROM {$st} WHERE day >= %s GROUP BY day ORDER BY day DESC LIMIT 31", $from ) );
		$tv    = 0;
		$tr    = 0;
		?>
		<div class="wrap tnp-iq-admin">
			<h1>Thống kê Test IQ</h1>
			<p class="description"><strong>Lượt xem đề</strong>: số lần người dùng bấm bắt đầu và tải đề bài. <strong>Lượt xem kết quả IQ</strong>: số lần làm xong, nhập tuổi và xem điểm IQ.</p>
			<ul class="subsubsub">
				<?php
				$links = array();
				foreach ( $ranges as $k => $label ) {
					$url     = add_query_arg( array( 'post_type' => TNP_IQ_CPT, 'page' => 'tnp-iq-stats', 'range' => $k ), admin_url( 'edit.php' ) );
					$links[] = '<li><a href="' . esc_url( $url ) . '"' . ( $k === $range ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
				}
				echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>
			<table class="widefat striped tnp-stats-table">
				<thead><tr><th>Tên bài test</th><th>Lượt xem đề</th><th>Lượt xem kết quả IQ</th><th>Tỉ lệ hoàn thành</th><th>IQ trung bình</th><th>Shortcode</th></tr></thead>
				<tbody>
				<?php foreach ( $tests as $key => $name ) : ?>
					<?php
					$v   = (int) ( $counts[ $key ]->views ?? 0 );
					$r   = (int) ( $counts[ $key ]->results ?? 0 );
					$tv += $v;
					$tr += $r;
					?>
					<tr>
						<td><strong><?php echo esc_html( $name ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( $v ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $r ) ); ?></td>
						<td><?php echo $v ? esc_html( round( $r / $v * 100 ) . '%' ) : '–'; ?></td>
						<td><?php echo isset( $iqs[ $key ] ) ? (int) round( $iqs[ $key ]->avg_iq ) : '–'; ?></td>
						<td><code><?php echo esc_html( '' === $key ? '[tnp_iq_test]' : '[tnp_iq_test test="' . $key . '"]' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr><th>Tổng</th><th><?php echo esc_html( number_format_i18n( $tv ) ); ?></th><th><?php echo esc_html( number_format_i18n( $tr ) ); ?></th><th><?php echo $tv ? esc_html( round( $tr / $tv * 100 ) . '%' ) : '–'; ?></th><th></th><th></th></tr></tfoot>
			</table>

			<?php if ( $daily ) : ?>
				<h2>Theo ngày</h2>
				<table class="widefat striped tnp-stats-table">
					<thead><tr><th>Ngày</th><th>Lượt xem đề</th><th>Lượt xem kết quả IQ</th><th>Tỉ lệ hoàn thành</th></tr></thead>
					<tbody>
					<?php foreach ( $daily as $d ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'd/m/Y', $d->day ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $d->views ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $d->results ) ); ?></td>
							<td><?php echo $d->views ? esc_html( round( $d->results / $d->views * 100 ) . '%' ) : '–'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . TNP_IQ_CPT,
			'Kết quả Test IQ',
			'Kết quả',
			'manage_options',
			'tnp-iq-results',
			array( __CLASS__, 'render' )
		);
	}

	private static function fmt_duration( $s ) {
		return sprintf( '%d:%02d', floor( $s / 60 ), $s % 60 );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$t = self::table();

		$stats = $wpdb->get_row( "SELECT COUNT(*) n, AVG(iq) avg_iq, AVG(ratio) avg_ratio, AVG(duration) avg_dur FROM {$t}" );

		$rows_norm  = TNP_IQ_Settings::parse_norms( TNP_IQ_Settings::get( 'norms' ) ) ?: array();
		$age_groups = array();
		foreach ( $rows_norm as $r ) {
			$g  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) n, AVG(ratio) m, AVG(ratio*ratio) m2 FROM {$t} WHERE age BETWEEN %d AND %d", $r[0], $r[1] ) );
			$n  = (int) $g->n;
			$sd = $n > 1 ? sqrt( max( 0, ( (float) $g->m2 - (float) $g->m * (float) $g->m ) * $n / ( $n - 1 ) ) ) : null;
			$age_groups[] = array( 'range' => $r[0] . '–' . $r[1], 'mean' => $r[2], 'sd' => $r[3], 'n' => $n, 'real_mean' => $g->m, 'real_sd' => $sd );
		}

		$per   = 50;
		$page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$total = (int) $stats->n;
		$pages = max( 1, (int) ceil( $total / $per ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) );
		?>
		<div class="wrap tnp-iq-admin">
			<h1 class="wp-heading-inline">Kết quả Test IQ</h1>
			<?php if ( $total ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tnp_iq_export' ), 'tnp_iq_export' ) ); ?>">Xuất CSV</a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã xoá toàn bộ kết quả.</p></div>
			<?php endif; ?>

			<div class="tnp-stats">
				<div><b><?php echo number_format_i18n( $total ); ?></b><span>Lượt làm bài</span></div>
				<div><b><?php echo $total ? (int) round( $stats->avg_iq ) : '–'; ?></b><span>IQ trung bình</span></div>
				<div><b><?php echo $total ? esc_html( round( $stats->avg_ratio * 100 ) . '%' ) : '–'; ?></b><span>Tỉ lệ điểm trung bình</span></div>
				<div><b><?php echo $total ? esc_html( self::fmt_duration( (int) $stats->avg_dur ) ) : '–'; ?></b><span>Thời gian trung bình</span></div>
			</div>

			<?php if ( $age_groups ) : ?>
				<h2>Theo nhóm tuổi</h2>
				<p class="description">So sánh chuẩn đang dùng với dữ liệu thực tế. Khi một nhóm có từ khoảng 100 lượt trở lên, bạn có thể chép “Tỉ lệ TB thực tế” và “Độ lệch thực tế” vào ô Chuẩn theo độ tuổi ở trang Cài đặt để điểm IQ sát với người dùng thật hơn.</p>
				<table class="widefat striped tnp-age-table">
					<thead><tr><th>Nhóm tuổi</th><th>Số lượt</th><th>Chuẩn đang dùng</th><th>Tỉ lệ TB thực tế</th><th>Độ lệch thực tế</th></tr></thead>
					<tbody>
					<?php foreach ( $age_groups as $g ) : ?>
						<tr>
							<td><?php echo esc_html( $g['range'] ); ?></td>
							<td><?php echo (int) $g['n']; ?></td>
							<td><?php echo esc_html( $g['mean'] . ', ' . $g['sd'] ); ?></td>
							<td><?php echo $g['n'] ? esc_html( number_format( (float) $g['real_mean'], 2 ) ) : '–'; ?></td>
							<td><?php echo $g['n'] > 1 ? esc_html( number_format( (float) $g['real_sd'], 2 ) ) : '–'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2>Lượt làm gần đây</h2>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>Thời điểm</th><th>Bài test</th><th>IQ</th><th>Đúng</th><th>Tuổi</th><th>Thời gian</th><th>Theo nhóm năng lực</th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="8">Chưa có lượt làm bài nào.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<?php $cats = json_decode( $r->categories, true ) ?: array(); ?>
					<tr>
						<td><?php echo (int) $r->id; ?></td>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $r->created_at ) ); ?></td>
						<td><?php echo esc_html( TNP_IQ_Questions::test_name( (string) $r->test_key ) ); ?></td>
						<td><strong><?php echo (int) $r->iq; ?></strong></td>
						<td><?php echo (int) $r->correct . '/' . (int) $r->total; ?></td>
						<td><?php echo (int) $r->age; ?></td>
						<td><?php echo esc_html( self::fmt_duration( (int) $r->duration ) ); ?></td>
						<td>
							<?php
							$parts = array();
							foreach ( $cats as $k => $c ) {
								$parts[] = esc_html( ( TNP_IQ_Questions::CATEGORIES[ $k ] ?? $k ) . ' ' . $c['correct'] . '/' . $c['total'] );
							}
							echo implode( ' · ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput -- đã escape từng phần.
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					) );
					?>
				</div></div>
			<?php endif; ?>

			<?php if ( $total ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Xoá toàn bộ kết quả và số liệu thống kê? Không thể khôi phục.');" style="margin-top:24px">
					<input type="hidden" name="action" value="tnp_iq_clear">
					<?php wp_nonce_field( 'tnp_iq_clear' ); ?>
					<button class="button button-link-delete">Xoá toàn bộ kết quả và thống kê</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tnp_iq_export' ) ) {
			wp_die( 'Không có quyền.' );
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, created_at, test_key, iq, correct, total, ratio, age, duration, categories FROM ' . self::table() . ' ORDER BY id DESC', ARRAY_A );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ket-qua-test-iq-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM để Excel đọc đúng tiếng Việt.
		fputcsv( $out, array( 'ID', 'Thời điểm', 'Bộ đề', 'IQ', 'Đúng', 'Tổng câu', 'Tỉ lệ điểm', 'Tuổi', 'Thời gian (giây)', 'Theo nhóm năng lực' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_values( $r ) );
		}
		fclose( $out );
		exit;
	}

	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tnp_iq_clear' ) ) {
			wp_die( 'Không có quyền.' );
		}
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		$wpdb->query( 'TRUNCATE TABLE ' . self::stats_table() );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TNP_IQ_CPT . '&page=tnp-iq-results&cleared=1' ) );
		exit;
	}
}

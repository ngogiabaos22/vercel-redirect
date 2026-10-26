<?php
defined( 'ABSPATH' ) || exit;

/**
 * Quy đổi bài làm thành điểm IQ và phần phân tích kết quả.
 *
 * 1. Tỉ lệ điểm = tổng trọng số (độ khó) các câu đúng / tổng trọng số các câu.
 * 2. Lấy chuẩn (trung bình, độ lệch chuẩn) của nhóm tuổi người làm từ Cài đặt.
 * 3. IQ = 100 + 15 × (tỉ lệ − trung bình) / độ lệch chuẩn, cộng thưởng tốc độ, giới hạn trong khoảng cài đặt.
 */
class TNP_IQ_Scoring {

	public static function norm_for_age( $age ) {
		$rows = TNP_IQ_Settings::parse_norms( TNP_IQ_Settings::get( 'norms' ) );
		if ( ! $rows ) {
			$rows = TNP_IQ_Settings::parse_norms( TNP_IQ_Settings::default_norms() );
		}
		foreach ( $rows as $r ) {
			if ( $age >= $r[0] && $age <= $r[1] ) {
				return array( 'mean' => $r[2], 'sd' => $r[3], 'group' => $r[0] . '–' . $r[1] );
			}
		}
		// Ngoài mọi nhóm: dùng nhóm gần nhất.
		usort( $rows, function ( $a, $b ) use ( $age ) {
			return min( abs( $age - $a[0] ), abs( $age - $a[1] ) ) <=> min( abs( $age - $b[0] ), abs( $age - $b[1] ) );
		} );
		return array( 'mean' => $rows[0][2], 'sd' => $rows[0][3], 'group' => $rows[0][0] . '–' . $rows[0][1] );
	}

	/**
	 * @param array $items   [ [ 'correct' => bool, 'weight' => int ], ... ]
	 * @param int   $age
	 * @param int   $seconds thời gian làm phần câu hỏi
	 */
	public static function score( array $items, $age, $seconds ) {
		$total_w = 0;
		$got_w   = 0;
		$correct = 0;
		foreach ( $items as $it ) {
			$total_w += $it['weight'];
			if ( $it['correct'] ) {
				$got_w += $it['weight'];
				$correct++;
			}
		}
		$ratio = $total_w > 0 ? $got_w / $total_w : 0;
		$norm  = self::norm_for_age( $age );

		$iq = 100 + 15 * ( $ratio - $norm['mean'] ) / $norm['sd'];

		// Thưởng tốc độ: so với thời gian chuẩn = số câu × số giây chuẩn mỗi câu.
		$bonus = 0;
		$max   = (int) TNP_IQ_Settings::get( 'speed_bonus' );
		$ref   = count( $items ) * (int) TNP_IQ_Settings::get( 'speed_ref' );
		if ( $max > 0 && $ref > 0 && $ratio >= 0.6 ) {
			$left  = max( 0, 1 - $seconds / $ref );
			$bonus = (int) round( $max * min( 1, $left * 2 ) ); // xong trong ≤ 50% thời gian chuẩn → thưởng tối đa
		}
		$iq += $bonus;

		$iq = (int) round( max( TNP_IQ_Settings::get( 'iq_min' ), min( TNP_IQ_Settings::get( 'iq_max' ), $iq ) ) );

		return array(
			'iq'         => $iq,
			'correct'    => $correct,
			'total'      => count( $items ),
			'ratio'      => round( $ratio, 4 ),
			'bonus'      => $bonus,
			'percentile' => self::percentile( $iq ),
			'label'      => self::label( $iq ),
			'age_group'  => $norm['group'],
		);
	}

	/** Phần trăm dân số có IQ thấp hơn (phân phối chuẩn 100/15). */
	public static function percentile( $iq ) {
		$z = ( $iq - 100 ) / 15;
		$p = 0.5 * ( 1 + self::erf( $z / M_SQRT2 ) );
		return max( 0.1, min( 99.9, round( $p * 100, 1 ) ) );
	}

	private static function erf( $x ) {
		// Abramowitz & Stegun 7.1.26
		$t = 1 / ( 1 + 0.3275911 * abs( $x ) );
		$y = 1 - ( ( ( ( ( 1.061405429 * $t - 1.453152027 ) * $t ) + 1.421413741 ) * $t - 0.284496736 ) * $t + 0.254829592 ) * $t * exp( -$x * $x );
		return $x >= 0 ? $y : -$y;
	}

	/** Bảng xếp loại (dùng cả cho phần “Bạn đang ở đâu” trên trang kết quả). */
	public static function levels() {
		return array(
			array(
				'min'    => 130,
				'range'  => '130+',
				'name'   => 'Rất xuất sắc',
				'share'  => '≈ 2,2%',
				'desc'   => 'Bạn thuộc nhóm khoảng 2% dân số có chỉ số IQ cao nhất — ngưỡng thường dùng để xét vào hội Mensa. Khả năng nhận diện quy luật trừu tượng và suy luận nhiều bước của bạn vượt trội so với người cùng độ tuổi.',
				'traits' => array(
					'Nhìn ra quy luật ẩn rất nhanh, kể cả khi nhiều quy luật chồng lên nhau.',
					'Giữ và xử lý nhiều thông tin cùng lúc trong đầu mà ít bị nhầm.',
					'Thích các vấn đề mới, phức tạp và dễ chán với việc lặp lại.',
				),
				'tips'   => array(
					'Chọn môi trường đủ thử thách: toán, lập trình, nghiên cứu, chiến lược, cờ…',
					'Rèn kỹ năng giao tiếp và làm việc nhóm để biến tư duy nhanh thành kết quả thực tế.',
				),
			),
			array(
				'min'    => 120,
				'range'  => '120–129',
				'name'   => 'Xuất sắc',
				'share'  => '≈ 6,7%',
				'desc'   => 'Khả năng tư duy trừu tượng và nhận diện quy luật của bạn rất tốt, cao hơn phần lớn người cùng độ tuổi. Bạn thường tiếp thu kiến thức mới nhanh và giải quyết vấn đề có hệ thống.',
				'traits' => array(
					'Tiếp thu khái niệm mới nhanh, ít cần giải thích lại.',
					'Phân tích vấn đề theo từng bước rõ ràng, có logic.',
					'Dễ thích nghi với dạng bài hoặc tình huống chưa gặp.',
				),
				'tips'   => array(
					'Thử các bài ma trận khó hơn và giới hạn thời gian chặt hơn để nâng tốc độ xử lý.',
					'Đọc và học thêm ở lĩnh vực mới để mở rộng vốn kiến thức nền.',
				),
			),
			array(
				'min'    => 110,
				'range'  => '110–119',
				'name'   => 'Trên trung bình',
				'share'  => '≈ 16,1%',
				'desc'   => 'Bạn học và xử lý vấn đề nhanh hơn mức phổ biến. Với các dạng bài quen thuộc, bạn tìm ra quy luật khá nhanh; ở các câu nhiều bước, bạn vẫn có dư địa để cải thiện.',
				'traits' => array(
					'Nắm bắt quy luật đơn giản và vừa phải nhanh, chính xác.',
					'Học tốt khi có phương pháp và thực hành đều đặn.',
					'Đôi khi mất thời gian ở câu cần kết hợp hai quy luật trở lên.',
				),
				'tips'   => array(
					'Luyện dạng bài ma trận có 2–3 quy luật cùng lúc (xoay + đổi màu + đổi số lượng).',
					'Tập ghi nháp: liệt kê thay đổi theo hàng, theo cột rồi mới chọn đáp án.',
				),
			),
			array(
				'min'    => 90,
				'range'  => '90–109',
				'name'   => 'Trung bình',
				'share'  => '≈ 50%',
				'desc'   => 'Bạn ở mức phổ biến nhất — khoảng một nửa dân số nằm trong khoảng này. Đây là mức hoàn toàn đủ cho học tập và công việc; kỹ năng giải đề IQ có thể tăng rõ rệt nếu luyện tập.',
				'traits' => array(
					'Giải tốt các câu có một quy luật rõ ràng.',
					'Cần thêm thời gian với câu trừu tượng hoặc nhiều bước.',
					'Kết quả cải thiện nhanh khi đã quen dạng đề.',
				),
				'tips'   => array(
					'Mỗi ngày làm 5–10 câu ma trận, dãy số; xem kỹ lời giải những câu sai.',
					'Đọc sách, chơi sudoku, cờ hoặc trò chơi tư duy để rèn trí nhớ làm việc.',
				),
			),
			array(
				'min'    => 80,
				'range'  => '80–89',
				'name'   => 'Dưới trung bình',
				'share'  => '≈ 16,1%',
				'desc'   => 'Kết quả cho thấy bạn chưa quen với dạng bài trắc nghiệm IQ hoặc chưa đủ tập trung khi làm. Luyện tập các dạng ma trận, dãy số sẽ giúp bạn cải thiện đáng kể.',
				'traits' => array(
					'Làm tốt hơn khi có thêm thời gian và không bị áp lực.',
					'Dễ bị nhiễu khi một câu có nhiều chi tiết thay đổi.',
					'Có thể đã bỏ sót quy luật theo cột hoặc theo đường chéo.',
				),
				'tips'   => array(
					'Bắt đầu với câu dễ, quan sát lần lượt: hình dạng, số lượng, hướng xoay, màu sắc.',
					'Làm bài khi tỉnh táo, ngủ đủ và ở nơi yên tĩnh rồi thử lại.',
				),
			),
			array(
				'min'    => 70,
				'range'  => '70–79',
				'name'   => 'Mức ranh giới',
				'share'  => '≈ 6,7%',
				'desc'   => 'Kết quả có thể bị ảnh hưởng bởi mệt mỏi, mất tập trung hoặc chưa quen dạng đề. Một bài test online không đủ để kết luận về năng lực của bạn.',
				'traits' => array(
					'Có thể đã đoán nhiều câu hoặc bỏ trống.',
					'Áp lực thời gian có thể làm giảm kết quả.',
				),
				'tips'   => array(
					'Làm lại khi tỉnh táo, đọc kỹ đề và quan sát từng hàng một.',
					'Nếu cần đánh giá chính xác, hãy gặp chuyên gia tâm lý để làm bài test chuẩn.',
				),
			),
			array(
				'min'    => 0,
				'range'  => '< 70',
				'name'   => 'Thấp',
				'share'  => '≈ 2,2%',
				'desc'   => 'Kết quả online chỉ mang tính tham khảo và rất dễ bị ảnh hưởng bởi hoàn cảnh làm bài. Nếu cần đánh giá chính xác, hãy gặp chuyên gia tâm lý.',
				'traits' => array(
					'Có thể chưa hiểu cách làm dạng bài ma trận hình.',
				),
				'tips'   => array(
					'Xem lại hướng dẫn, làm thử vài câu dễ rồi làm lại bài test.',
					'Gặp chuyên gia tâm lý nếu bạn muốn một đánh giá đầy đủ.',
				),
			),
		);
	}

	public static function label( $iq ) {
		foreach ( self::levels() as $i => $l ) {
			if ( $iq >= $l['min'] ) {
				return array( 'index' => $i, 'name' => $l['name'], 'desc' => $l['desc'], 'traits' => $l['traits'], 'tips' => $l['tips'] );
			}
		}
		return array( 'index' => 0, 'name' => '', 'desc' => '', 'traits' => array(), 'tips' => array() );
	}

	/** Phân tích một nhóm năng lực theo tỉ lệ đúng. */
	public static function category_analysis( $key, $correct, $total ) {
		$pct = $total ? (int) round( $correct / $total * 100 ) : 0;
		if ( $pct >= 80 ) {
			$level = 'Rất tốt';
		} elseif ( $pct >= 60 ) {
			$level = 'Tốt';
		} elseif ( $pct >= 40 ) {
			$level = 'Trung bình';
		} else {
			$level = 'Cần luyện thêm';
		}
		$about = array(
			'logic'   => array( 'Khả năng tìm quy luật trong chuỗi hình và suy luận trừu tượng.', 'Luyện ma trận Raven: xét lần lượt thay đổi theo hàng, theo cột, rồi theo đường chéo.' ),
			'number'  => array( 'Khả năng nhận ra quan hệ giữa các con số và tính nhẩm.', 'Luyện dãy số: thử hiệu, thương, bình phương giữa các số liền nhau; tập tính nhẩm mỗi ngày.' ),
			'verbal'  => array( 'Khả năng hiểu nghĩa, loại suy và phân loại khái niệm.', 'Đọc sách đa dạng, tập tìm từ đồng nghĩa/trái nghĩa và quan hệ “A với B như C với ?”.' ),
			'spatial' => array( 'Khả năng xoay, gấp và hình dung vật thể trong không gian.', 'Chơi xếp hình, Rubik, vẽ hình khối; tập tưởng tượng hình sau khi xoay 90°, 180°.' ),
		);
		return array(
			'pct'   => $pct,
			'level' => $level,
			'about' => $about[ $key ][0] ?? '',
			'tip'   => $about[ $key ][1] ?? '',
		);
	}
}

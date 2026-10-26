<?php
defined( 'ABSPATH' ) || exit;

/**
 * Quy đổi bài làm thành điểm IQ.
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
	 * @param int   $seconds thời gian làm bài
	 * @param int   $limit   giới hạn (giây), 0 = không giới hạn
	 */
	public static function score( array $items, $age, $seconds, $limit ) {
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

		$bonus = 0;
		$max   = (int) TNP_IQ_Settings::get( 'speed_bonus' );
		if ( $max > 0 && $limit > 0 && $ratio >= 0.6 ) {
			$left  = max( 0, 1 - $seconds / $limit );
			$bonus = (int) round( $max * min( 1, $left * 2 ) ); // dùng ≤ 50% thời gian → thưởng tối đa
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

	public static function label( $iq ) {
		$table = array(
			130 => array( 'Rất xuất sắc', 'Bạn thuộc nhóm khoảng 2% dân số có chỉ số IQ cao nhất — ngưỡng thường dùng để xét vào hội Mensa.' ),
			120 => array( 'Xuất sắc', 'Khả năng tư duy trừu tượng và nhận diện quy luật của bạn rất tốt, cao hơn phần lớn người cùng độ tuổi.' ),
			110 => array( 'Trên trung bình', 'Bạn học và xử lý vấn đề nhanh hơn mức phổ biến.' ),
			90  => array( 'Trung bình', 'Bạn ở mức phổ biến nhất — khoảng một nửa dân số nằm trong khoảng này.' ),
			80  => array( 'Dưới trung bình', 'Luyện tập thêm các dạng bài ma trận, dãy số sẽ giúp bạn cải thiện đáng kể.' ),
			70  => array( 'Mức ranh giới', 'Kết quả có thể bị ảnh hưởng bởi mệt mỏi hoặc chưa quen dạng đề; hãy thử lại khi tỉnh táo.' ),
			0   => array( 'Thấp', 'Kết quả online chỉ mang tính tham khảo. Nếu cần đánh giá chính xác, hãy gặp chuyên gia tâm lý.' ),
		);
		foreach ( $table as $min => $v ) {
			if ( $iq >= $min ) {
				return array( 'name' => $v[0], 'desc' => $v[1] );
			}
		}
		return array( 'name' => '', 'desc' => '' );
	}
}

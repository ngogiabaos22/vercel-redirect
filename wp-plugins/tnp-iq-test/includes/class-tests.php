<?php
defined( 'ABSPATH' ) || exit;

/**
 * Các loại bài test và cách chấm.
 *
 * Mỗi "Bài test" là một term của taxonomy TNP_IQ_Questions::TAX, có term meta:
 *  - tnp_type  : iq | eq | disc | mbti | holland
 *  - tnp_intro : hướng dẫn trước khi làm (bỏ trống = dùng mặc định)
 *
 * IQ chấm theo đáp án đúng (TNP_IQ_Scoring). Các loại còn lại chấm theo "nhóm" và "điểm"
 * gắn trên từng đáp án: người làm chọn đáp án nào thì cộng điểm của đáp án đó vào nhóm của nó.
 */
class TNP_IQ_Tests {

	public static function init() {
		$tax = TNP_IQ_Questions::TAX;
		add_action( "{$tax}_add_form_fields", array( __CLASS__, 'term_add_fields' ) );
		add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'term_edit_fields' ) );
		add_action( "created_{$tax}", array( __CLASS__, 'term_save' ) );
		add_action( "edited_{$tax}", array( __CLASS__, 'term_save' ) );
		add_filter( "manage_edit-{$tax}_columns", array( __CLASS__, 'term_columns' ) );
		add_filter( "manage_{$tax}_custom_column", array( __CLASS__, 'term_column' ), 10, 3 );
	}

	/* ---------- Định nghĩa loại test ---------- */

	public static function types() {
		return array(
			'iq'      => array(
				'label' => 'IQ – Chỉ số thông minh',
				'short' => 'IQ',
				'dims'  => array(),
				'intro' => 'Chuẩn bị giấy nháp, chọn nơi yên tĩnh. Bạn có thể bỏ qua câu khó và quay lại sau. Ở bước cuối, hãy nhập đúng tuổi để điểm IQ được quy đổi chính xác theo độ tuổi.',
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
				'intro' => 'Không có đáp án đúng hay sai. Hãy chọn phương án gần với cách bạn thường suy nghĩ và hành động nhất, đừng chọn theo điều bạn cho là “nên” làm.',
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
				'intro' => 'Không có đáp án đúng hay sai. Với mỗi câu, chọn phương án mô tả bạn đúng nhất trong công việc và cuộc sống hằng ngày.',
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
				'intro' => 'Không có đáp án đúng hay sai. Hãy chọn theo con người thật của bạn, không phải con người bạn muốn trở thành. Trả lời nhanh theo cảm nhận đầu tiên.',
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
				'intro' => 'Hãy cho biết mức độ bạn thích từng hoạt động dưới đây. Đừng nghĩ đến việc bạn có giỏi việc đó hay không — chỉ cần bạn có thích hay không.',
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

	public static function intro_of( $slug ) {
		$type = self::type_of( $slug );
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, TNP_IQ_Questions::TAX );
			$txt  = $term ? trim( (string) get_term_meta( $term->term_id, 'tnp_intro', true ) ) : '';
			if ( '' !== $txt ) {
				return $txt;
			}
		}
		return 'iq' === $type ? TNP_IQ_Settings::get( 'intro_text' ) : self::types()[ $type ]['intro'];
	}

	/** Loại test của một câu hỏi = loại của bài test (term) đầu tiên nó thuộc về. */
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
			<label for="tnp-intro">Hướng dẫn trước khi làm</label>
			<textarea name="tnp_intro" id="tnp-intro" rows="3"></textarea>
			<p>Hiện ở màn hình bắt đầu. Để trống = dùng hướng dẫn mặc định của loại test.</p>
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
			<th scope="row"><label for="tnp-intro">Hướng dẫn trước khi làm</label></th>
			<td><textarea name="tnp_intro" id="tnp-intro" rows="3"><?php echo esc_textarea( get_term_meta( $term->term_id, 'tnp_intro', true ) ); ?></textarea>
				<p class="description">Để trống = dùng hướng dẫn mặc định của loại test.</p></td>
		</tr>
		<tr class="form-field">
			<th scope="row">Shortcode</th>
			<td><code>[tnp_test test="<?php echo esc_html( $term->slug ); ?>"]</code></td>
		</tr>
		<?php
	}

	public static function term_save( $term_id ) {
		if ( ! isset( $_POST['tnp_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['tnp_term_nonce'] ), 'tnp_term' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_POST['tnp_type'] ?? 'iq' ) );
		update_term_meta( $term_id, 'tnp_type', isset( self::types()[ $type ] ) ? $type : 'iq' );
		update_term_meta( $term_id, 'tnp_intro', sanitize_textarea_field( wp_unslash( $_POST['tnp_intro'] ?? '' ) ) );
	}

	public static function term_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'name' === $k ) {
				$new['tnp_type']      = 'Loại';
				$new['tnp_shortcode'] = 'Shortcode';
			}
		}
		unset( $new['description'] );
		return $new;
	}

	public static function term_column( $out, $col, $term_id ) {
		$term = get_term( $term_id );
		if ( 'tnp_type' === $col ) {
			return esc_html( self::types()[ self::type_of( $term->slug ) ]['short'] );
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
			// Điểm tối đa có thể đạt cho từng nhóm ở câu này (dùng để quy ra %).
			$best = array();
			foreach ( $q['options'] as $o ) {
				if ( isset( $max[ $o['dim'] ] ) ) {
					$best[ $o['dim'] ] = max( $best[ $o['dim'] ] ?? 0, $o['pts'] );
				}
			}
			foreach ( $best as $d => $v ) {
				$max[ $d ] += $v;
			}
			if ( '' !== $q['chosen'] && isset( $q['options'][ $q['chosen'] ] ) ) {
				$o = $q['options'][ $q['chosen'] ];
				if ( isset( $got[ $o['dim'] ] ) ) {
					$got[ $o['dim'] ] += $o['pts'];
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

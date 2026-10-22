<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    // API tải đề thi (Được bảo mật: Không chứa đáp án & giải thích)
    register_rest_route('tq/v1', '/exam/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'tqp_api_get_exam',
        'permission_callback' => '__return_true'
    ]);

    // API nộp bài thi (Tính điểm & trả về đáp án/lời giải nếu vượt qua Quiz Locker)
    register_rest_route('tq/v1', '/submit', [
        'methods' => 'POST',
        'callback' => 'tqp_api_submit_exam',
        'permission_callback' => '__return_true'
    ]);
});

// 1. CALLBACK LẤY ĐỀ THI
function tqp_api_get_exam($request) {
    global $wpdb;
    $exam_id = intval($request['id']);

    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    // Lấy thông tin đề thi
    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exams WHERE id = %d", $exam_id));
    if (!$exam) {
        return new WP_Error('not_found', 'Không tìm thấy đề thi!', ['status' => 404]);
    }

    // Lấy danh sách nhóm câu hỏi
    $groups = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_groups WHERE exam_id = %d ORDER BY sort_order ASC", 
        $exam_id
    ));

    $structured_groups = [];
    foreach ($groups as $group) {
        // Lấy câu hỏi thuộc nhóm này (ẨN trường correct_ans và explanation để bảo mật)
        $q_ids_str = !empty($group->question_ids) ? $group->question_ids : '0';
        $raw_ids = explode(',', $q_ids_str);
        $clean_ids = [];
        $num_map = [];
        foreach ($raw_ids as $rid) {
            $parts = explode(':', trim($rid));
            $cid = intval($parts[0]);
            if ($cid > 0) {
                $clean_ids[] = $cid;
                if (isset($parts[1])) {
                    $num_map[$cid] = intval($parts[1]);
                }
            }
        }
        $clean_ids_str = !empty($clean_ids) ? implode(',', $clean_ids) : '0';

        $questions = $wpdb->get_results(
            "SELECT id, question_number, question_text, opt_a, opt_b, opt_c, opt_d, opt_e, opt_f, opt_g, opt_h 
             FROM $table_questions 
             WHERE id IN ($clean_ids_str)"
        );
        
        // Sắp xếp lại theo đúng thứ tự trong question_ids
        $ordered_questions = [];
        if (!empty($questions) && !empty($clean_ids)) {
            foreach ($clean_ids as $qid) {
                foreach ($questions as $q) {
                    if ($q->id == $qid) {
                        if (isset($num_map[$qid])) {
                            $q->question_number = $num_map[$qid];
                        }
                        $ordered_questions[] = $q;
                        break;
                    }
                }
            }
        }
        $questions = $ordered_questions;

        // Tự động phân giải đường dẫn ảnh sơ đồ từ Media Library
        if ($questions) {
            foreach ($questions as $q) {
                $q->question_text = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->question_text));
                $q->opt_a = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_a));
                $q->opt_b = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_b));
                $q->opt_c = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_c));
                $q->opt_d = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_d));
                if (isset($q->opt_e)) $q->opt_e = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_e));
                if (isset($q->opt_f)) $q->opt_f = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_f));
                if (isset($q->opt_g)) $q->opt_g = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_g));
                if (isset($q->opt_h)) $q->opt_h = tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->opt_h));
            }
        }

        $structured_groups[] = [
            'id' => $group->id,
            'group_type' => $group->group_type,
            'instruction' => tqp_obfuscate_urls($group->instruction),
            'passage_text' => tqp_obfuscate_urls($group->passage_text),
            'media_url' => tqp_obfuscate_urls($group->media_url),
            'questions' => $questions
        ];
    }

    return [
        'success' => true,
        'exam' => [
            'id' => $exam->id,
            'title' => $exam->title,
            'duration_minutes' => $exam->duration_minutes,
            'requires_unlock' => !empty($exam->pass_tag),
            'pass_tag' => $exam->pass_tag,
            'audio_url' => isset($exam->audio_url) ? $exam->audio_url : '',
            'show_answers' => isset($exam->show_answers) ? $exam->show_answers : 1
        ],
        'groups' => $structured_groups
    ];
}

// 2. CALLBACK NỘP BÀI THI & CHẤM ĐIỂM
function tqp_api_submit_exam($request) {
    global $wpdb;
    
    $exam_id = intval($request->get_param('exam_id'));
    $user_answers = $request->get_param('answers'); // Dạng {question_id: "A", ...}
    $unlock_code = sanitize_text_field($request->get_param('unlock_code'));

    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    // Lấy thông tin đề thi
    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exams WHERE id = %d", $exam_id));
    if (!$exam) {
        return new WP_Error('not_found', 'Không tìm thấy đề thi!', ['status' => 404]);
    }

    // KIỂM TRA BẢO MẬT: Nếu đề có tag nhiệm vụ, bắt buộc phải xác minh mã nhiệm vụ
    if (!empty($exam->pass_tag)) {
        $api_url = 'https://traffic.io.vn/wp-json/iats/v1/code/verify';
        
        $forwarded_ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $forwarded_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $forwarded_ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        $response = wp_remote_post($api_url, array(
            'body'    => json_encode(array(
                'code' => trim($unlock_code),
                'forwarded_ip' => $forwarded_ip,
                'forwarded_url' => $forwarded_url,
                'forwarded_ua' => $forwarded_ua
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Lỗi kết nối tới hệ thống xác thực mã.');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['success']) || $data['success'] !== true) {
            $err_msg = isset($data['message']) ? $data['message'] : 'Mã xác nhận nhiệm vụ không chính xác! Vui lòng hoàn thành nhiệm vụ để lấy mã kích hoạt kết quả.';
            return array('success' => false, 'message' => $err_msg);
        }
    }

    // Lấy toàn bộ câu hỏi và đáp án đúng thực tế của đề thi để chấm điểm
    $groups = $wpdb->get_results($wpdb->prepare("SELECT question_ids, points_per_q, group_type FROM $table_groups WHERE exam_id = %d", $exam_id));
    $all_question_ids = [];
    $num_map = [];
    $q_points_map = [];
    $q_group_type_map = [];
    foreach ($groups as $g) {
        if (!empty($g->question_ids)) {
            $ids = explode(',', $g->question_ids);
            foreach ($ids as $rid) {
                $parts = explode(':', trim($rid));
                $cid = intval($parts[0]);
                if ($cid > 0) {
                    $all_question_ids[] = $cid;
                    if (isset($parts[1])) {
                        $num_map[$cid] = intval($parts[1]);
                    }
                    $q_points_map[$cid] = !empty($g->points_per_q) ? floatval($g->points_per_q) : 0;
                    $q_group_type_map[$cid] = $g->group_type;
                }
            }
        }
    }
    
    $correct_count = 0;
    $total_count = 0;
    $total_weight_score = 0;
    $earned_score = 0;
    $keywords_tally = [];
    $results_sheet = [];

    $scoring_method = $exam->scoring_method ?? 'standard';
    $intervals = !empty($exam->intervals) ? json_decode($exam->intervals, true) : [];

    if (!empty($all_question_ids)) {
        $q_ids_str = implode(',', $all_question_ids);
        $all_questions = $wpdb->get_results(
            "SELECT id, question_number, correct_ans, explanation, options_meta 
             FROM $table_questions 
             WHERE id IN ($q_ids_str)"
        );

        foreach ($all_questions as $q) {
            $total_count++;
            $q_id = $q->id;
            $group_type = isset($q_group_type_map[$q_id]) ? $q_group_type_map[$q_id] : '';
            
            if ($group_type === 'writing' || $group_type === 'speaking') {
                $user_ans = isset($user_answers[$q_id]) ? trim($user_answers[$q_id]) : '';
            } else {
                $user_ans = isset($user_answers[$q_id]) ? strtoupper(trim($user_answers[$q_id])) : '';
            }
            
            $real_correct = strtoupper(trim($q->correct_ans));
            
            if ($group_type === 'writing' || $group_type === 'speaking') {
                // Mặc định không điền là sai, có dữ liệu là đúng
                $is_correct = !empty($user_ans);
            } else {
                $is_correct = ($user_ans === $real_correct);
            }

            if ($is_correct) {
                $correct_count++;
            }

            $weight = 0;

            // Options Meta Processing (Weight and Keyword)
            $opt_meta = !empty($q->options_meta) ? json_decode($q->options_meta, true) : [];
            $user_ans_key = 'opt_' . strtolower($user_ans);
            if (!empty($user_ans) && isset($opt_meta[$user_ans_key])) {
                $weight = floatval($opt_meta[$user_ans_key]['weight'] ?? 0);
                $keyword = strtoupper(trim($opt_meta[$user_ans_key]['keyword'] ?? ''));
                
                $total_weight_score += $weight;
                if (!empty($keyword)) {
                    if (!isset($keywords_tally[$keyword])) $keywords_tally[$keyword] = 0;
                    $keywords_tally[$keyword]++;
                }
            }
            
            $points_earned = 0;
            if ($scoring_method === 'standard') {
                if ($is_correct && isset($q_points_map[$q_id])) {
                    $points_earned = $q_points_map[$q_id];
                    $earned_score += $points_earned;
                }
            }

            $results_sheet[$q_id] = [
                'question_number' => isset($num_map[$q_id]) ? $num_map[$q_id] : $q->question_number,
                'user_ans' => $user_ans,
                'correct_ans' => $real_correct,
                'is_correct' => $is_correct,
                'points_earned' => ($scoring_method === 'standard') ? $points_earned : $weight,
                'explanation' => tqp_obfuscate_urls(tqp_resolve_image_placeholder($q->explanation))
            ];
        }
    }

    $percentage = $total_count > 0 ? round(($correct_count / $total_count) * 100, 1) : 0;
    
    // Determine the final interval result based on scoring method
    $final_result_html = '';
    
    if ($scoring_method === 'score_range' && !empty($intervals)) {
        foreach ($intervals as $inv) {
            if ($total_weight_score >= $inv['min'] && $total_weight_score <= $inv['max']) {
                $final_result_html = $inv['text'];
                break;
            }
        }
    } else if ($scoring_method === 'personality' && !empty($intervals)) {
        // Find interval where text matches dominant keyword or max tally?
        // Actually MBTI resolves to a string of keywords (e.g., E, N, T, J -> ENTJ)
        // For simplicity, we just pass the tally to the frontend to render, or we can resolve it if interval 'min'/'max' matches something?
        // Wait, for personality, how does interval match?
        // Usually, QuizMaker has 'By keywords'. The user configures an interval where the max tallied keyword matches an interval?
        // Or if the resulting string matches? Let's just return the keywords tally and interval data to the frontend to let JS handle complex logic, OR we can just try to match the top keyword with the interval's min/max? No, intervals for keywords usually just match a specific keyword string.
        // Let's pass the raw tally and intervals to the frontend for personality.
    }

    return [
        'success' => true,
        'scoring_method' => $scoring_method,
        'exam_max_score' => isset($exam->max_score) ? floatval($exam->max_score) : null,
        'show_answers' => (int) $exam->show_answers,
        'video_url' => !empty($exam->video_url) ? tqp_obfuscate_urls(esc_url_raw($exam->video_url)) : '',
        'audio_url' => !empty($exam->audio_url) ? $exam->audio_url : '',
        'score' => [
            'correct' => $correct_count,
            'total' => $total_count,
            'percentage' => $percentage,
            'total_weight' => $total_weight_score,
            'earned_score' => $earned_score,
            'keywords_tally' => $keywords_tally
        ],
        'final_result_html' => tqp_obfuscate_urls($final_result_html),
        'intervals' => $intervals,
        'results' => $results_sheet
    ];
}

// 3. BỘ PHÂN GIẢI ĐƯỜNG DẪN ẢNH SƠ ĐỒ/TRANG PDF TỰ ĐỘNG TỪ MEDIA LIBRARY
function tqp_resolve_image_placeholder($text) {
    if (empty($text)) {
        return $text;
    }
    
    // Tự động sửa lỗi hiển thị vectơ bị đè/phông chữ xấu bằng cách chuyển \vec thành \overrightarrow
    $text = str_replace('\vec{', '\overrightarrow{', $text);
    
    if (strpos($text, '[TQP_IMAGE_URL]') === false) {
        return $text;
    }
    
    global $wpdb;
    
    // Tìm tất cả các mẫu [TQP_IMAGE_URL]/(image_\d+\.\w+|page_\d+\.\w+)
    $text = preg_replace_callback('/\[TQP_IMAGE_URL\]\/([a-zA-Z0-9_\-\.]+)/i', function($matches) use ($wpdb) {
        $filename = $matches[1];
        
        // Quét tìm trong bảng postmeta xem có attachment nào có file kết thúc bằng filename này không
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta 
             WHERE meta_key = '_wp_attached_file' 
               AND meta_value LIKE %s 
             LIMIT 1",
            '%' . $wpdb->esc_like($filename)
        ));
        
        if ($attachment_id) {
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
                return $url;
            }
        }
        
        // Fallback: nếu không tìm thấy trong Media library, thay thế [TQP_IMAGE_URL] bằng thư mục uploads hiện tại của năm/tháng
        $upload_dir = wp_upload_dir();
        return $upload_dir['url'] . '/' . $filename;
        
    }, $text);
    
    return $text;
}

// ============================================================
// 4. AJAX ENDPOINTS DÀNH CHO ADMIN
// ============================================================
add_action('wp_ajax_tqp_fetch_bank_questions', 'tqp_ajax_fetch_bank_questions');
function tqp_ajax_fetch_bank_questions() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table_questions = $wpdb->prefix . 'tq_questions';
    $table_categories = $wpdb->prefix . 'tq_question_categories';
    
    $cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    
    $where = ["1=1"];
    $params = [];
    
    if ($cat_id > 0) {
        $where[] = "q.category_id = %d";
        $params[] = $cat_id;
    }
    
    if (!empty($search)) {
        $where[] = "q.question_text LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    $sql = "SELECT q.*, c.name as category_name 
            FROM $table_questions q 
            LEFT JOIN $table_categories c ON q.category_id = c.id 
            WHERE $where_sql 
            ORDER BY q.id DESC LIMIT 100";
            
    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }
    
    $results = $wpdb->get_results($sql);
    wp_send_json_success($results);
}

/**
 * Hàm mã hóa đường dẫn (Phương án 2: Giấu link ở dạng HTML)
 * Chuyển các link bắt đầu bằng http thành dạng proxy ảo để ẩn gốc
 */
function tqp_obfuscate_urls($content) {
    if (empty($content)) return $content;

    // Hàm phụ trợ để chuẩn hóa YouTube/Vimeo
    $normalize_embed_url = function($url) {
        if (strpos($url, 'youtube.com/watch') !== false) {
            parse_str(parse_url($url, PHP_URL_QUERY), $vars);
            if (isset($vars['v'])) {
                return 'https://www.youtube.com/embed/' . $vars['v'];
            }
        } elseif (strpos($url, 'youtu.be/') !== false) {
            $path = parse_url($url, PHP_URL_PATH);
            return 'https://www.youtube.com/embed' . $path;
        } elseif (strpos($url, 'vimeo.com/') !== false && strpos($url, 'player.vimeo.com') === false) {
            $path = parse_url($url, PHP_URL_PATH);
            return 'https://player.vimeo.com/video' . $path;
        }
        return $url;
    };
    
    // Nếu $content chỉ là 1 đường link duy nhất (như media_url, video_url)
    if (filter_var(trim($content), FILTER_VALIDATE_URL)) {
        if (strpos($content, home_url()) !== false) return $content;
        
        $content = $normalize_embed_url(trim($content));
        
        // Không proxy youtube, vimeo
        if (strpos($content, 'youtube.com') !== false || strpos($content, 'vimeo.com') !== false || strpos($content, 'youtu.be') !== false) {
            return $content;
        }

        $encoded = strtr(base64_encode($content), '+/', '-_');
        return admin_url('admin-ajax.php?action=tqp_media&u=' . $encoded);
    }

    // Nếu $content là đoạn HTML, mã hóa các thuộc tính src, href
    $content = preg_replace_callback('/(src|href)=["\'](https?:\/\/[^"\']+)["\']/i', function($m) use ($normalize_embed_url) {
        $url = $m[2];
        if (strpos($url, home_url()) !== false) {
            return $m[0];
        }
        
        $url = $normalize_embed_url($url);
        
        // Không proxy youtube, vimeo
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'vimeo.com') !== false || strpos($url, 'youtu.be') !== false) {
            return $m[1] . '="' . esc_attr($url) . '"';
        }

        $encoded = strtr(base64_encode($url), '+/', '-_');
        $proxy_url = admin_url('admin-ajax.php?action=tqp_media&u=' . $encoded);
        return $m[1] . '="' . esc_attr($proxy_url) . '"';
    }, $content);
    
    return $content;
}

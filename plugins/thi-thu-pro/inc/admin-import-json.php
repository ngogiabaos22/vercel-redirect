<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_exams = $wpdb->prefix . 'tq_exams';
$table_groups = $wpdb->prefix . 'tq_groups';
$table_questions = $wpdb->prefix . 'tq_questions';

// Xử lý Import đề thi từ văn bản JSON/File JSON tải lên
if (isset($_POST['tqp_import']) && check_admin_referer('tqp_import_action', 'tqp_import_nonce')) {
    $json_data = '';
    
    // Xem có tải file lên không
    if (!empty($_FILES['tqp_json_file']['tmp_name'])) {
        $json_data = file_get_contents($_FILES['tqp_json_file']['tmp_name']);
    } else if (!empty($_POST['tqp_json_text'])) {
        $json_data = stripslashes($_POST['tqp_json_text']);
    }

    if (!empty($json_data)) {
        $data = json_decode($json_data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Đọc thông tin đề thi
            $title = sanitize_text_field($data['title']);
            $duration = isset($data['duration_minutes']) ? intval($data['duration_minutes']) : 60;
            $pass_tag = sanitize_text_field($data['pass_tag'] ?? '');
            $category = sanitize_text_field($data['category'] ?? 'Khác');
            $max_score = isset($data['max_score']) ? floatval($data['max_score']) : null;
            $video_url = esc_url_raw($data['video_url'] ?? '');
            $audio_url = wp_unslash($data['audio_url'] ?? '');
            $intervals_json = isset($data['intervals']) ? json_encode($data['intervals']) : '';

            // Chèn đề thi vào database
            $wpdb->insert($table_exams, [
                'title' => $title,
                'duration_minutes' => $duration,
                'pass_tag' => $pass_tag,
                'category' => $category,
                'max_score' => $max_score,
                'intervals' => $intervals_json,
                'show_answers' => 1,
                'video_url' => $video_url,
                'audio_url' => $audio_url
            ]);
            $exam_id = $wpdb->insert_id;

            // Duyệt qua danh sách các nhóm câu hỏi (groups)
            if (isset($data['groups']) && is_array($data['groups'])) {
                foreach ($data['groups'] as $index => $g) {
                    $passage = $g['passage_text'] ?? '';
                    if (!empty($g['image'])) {
                        $passage = '<img src="' . esc_url($g['image']) . '" style="max-width:100%; height:auto;"/><br/>' . $passage;
                    }
                    if (!empty($g['transcript'])) {
                        $passage .= '<div class="tqp-transcript" style="display:none;">' . tqp_kses_post($g['transcript']) . '</div>';
                    }
                    $media_url = $g['media_url'] ?? '';
                    if (empty($media_url) && !empty($g['audio'])) {
                        $media_url = $g['audio'];
                    }

                    $wpdb->insert($table_groups, [
                        'exam_id' => $exam_id,
                        'group_type' => sanitize_text_field($g['group_type'] ?? 'reading'),
                        'instruction' => tqp_kses_post($g['instruction'] ?? ''),
                        'passage_text' => tqp_kses_post($passage),
                        'media_url' => esc_url_raw($media_url),
                        'sort_order' => $index,
                        'points_per_q' => isset($g['points_per_q']) ? floatval($g['points_per_q']) : 0
                    ]);
                    $group_id = $wpdb->insert_id;

                    // Duyệt qua câu hỏi con của nhóm
                    if (isset($g['questions']) && is_array($g['questions'])) {
                        $q_ids_array = [];
                        foreach ($g['questions'] as $q) {
                            $q_num = isset($q['question_number']) ? intval($q['question_number']) : (isset($q['q_number']) ? intval($q['q_number']) : 0);
                            
                            $opt_a = $q['opt_a'] ?? '';
                            $opt_b = $q['opt_b'] ?? '';
                            $opt_c = $q['opt_c'] ?? '';
                            $opt_d = $q['opt_d'] ?? '';
                            $opt_e = $q['opt_e'] ?? '';
                            
                            if (isset($q['options']) && is_array($q['options'])) {
                                foreach ($q['options'] as $opt) {
                                    $k = strtolower(trim($opt['key'] ?? ''));
                                    if ($k == 'a') $opt_a = $opt['text'] ?? '';
                                    elseif ($k == 'b') $opt_b = $opt['text'] ?? '';
                                    elseif ($k == 'c') $opt_c = $opt['text'] ?? '';
                                    elseif ($k == 'd') $opt_d = $opt['text'] ?? '';
                                    elseif ($k == 'e') $opt_e = $opt['text'] ?? '';
                                }
                            }

                            $wpdb->insert($table_questions, [
                                'group_id' => $group_id,
                                'question_number' => $q_num,
                                'question_text' => tqp_kses_post($q['question_text'] ?? ''),
                                'opt_a' => tqp_kses_post($opt_a),
                                'opt_b' => tqp_kses_post($opt_b),
                                'opt_c' => tqp_kses_post($opt_c),
                                'opt_d' => tqp_kses_post($opt_d),
                                'opt_e' => tqp_kses_post($opt_e),
                                'opt_f' => tqp_kses_post($q['opt_f'] ?? ''),
                                'opt_g' => tqp_kses_post($q['opt_g'] ?? ''),
                                'opt_h' => tqp_kses_post($q['opt_h'] ?? ''),
                                'correct_ans' => strtoupper(sanitize_text_field($q['correct_ans'] ?? '')),
                                'explanation' => tqp_kses_post($q['explanation'] ?? '')
                            ]);
                            $q_id = $wpdb->insert_id;
                            $q_ids_array[] = $q_id . ':' . $q_num;
                        }
                        if (!empty($q_ids_array)) {
                            $wpdb->update($table_groups, ['question_ids' => implode(',', $q_ids_array)], ['id' => $group_id]);
                        }
                    }
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>Đã nạp đề thi thành công! ID đề: <strong>#' . $exam_id . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Lỗi: Định dạng dữ liệu JSON không hợp lệ! Vui lòng kiểm tra lại cấu trúc file.</p></div>';
        }
    }
}
?>

<div class="card" style="max-width:1200px;padding:15px 25px; margin-top:20px;">
    <h2>Import Đề Thi Mới (JSON Format)</h2>
    <p class="description" style="margin-bottom: 15px;">Bạn có thể upload file JSON hoặc dán trực tiếp đoạn mã JSON định dạng cấu trúc đề thi TOEIC/HSK vào khung văn bản bên dưới để import nhanh toàn bộ câu hỏi.</p>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($base_url . '&tab=import_json'); ?>">
        <?php wp_nonce_field('tqp_import_action', 'tqp_import_nonce'); ?>
        <input type="hidden" name="tqp_import" value="1">
        
        <table class="form-table">
            <tr>
                <th>Tải lên file `.json`</th>
                <td><input type="file" name="tqp_json_file" accept=".json"></td>
            </tr>
            <tr>
                <th>Hoặc dán mã JSON trực tiếp</th>
                <td>
                    <textarea name="tqp_json_text" rows="8" class="large-text" placeholder='Dán mã JSON cấu trúc đề thi vào đây...'></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-primary">Bắt đầu Import JSON</button>
        </p>
    </form>
</div>

<div class="card" style="max-width:1200px;padding:15px 25px; margin-top:20px; background: #fafafa;">
    <h2>Cấu trúc JSON Mẫu (TOEIC/HSK)</h2>
    <pre style="background: #eaeaea; padding:15px; border-radius:4px; font-size:11px; overflow-x: auto; max-height: 350px;">
{
  "title": "Đề thi thử TOEIC ETS 2024 Test 1",
  "duration_minutes": 120,
  "pass_tag": "toeic_test_1",
  "category": "Khác",
  "groups": [
    {
      "group_type": "listening",
      "instruction": "Part 1: Listen and choose the correct answer.",
      "media_url": "https://test.taiphutmot.com/wp-content/uploads/2026/05/part1_audio.mp3",
      "passage_text": "",
      "questions": [
        {
          "question_number": 1,
          "question_text": "What is described in the picture?",
          "opt_a": "A man is walking in a park.",
          "opt_b": "A man is working at his computer.",
          "opt_c": "A man is fixing a car.",
          "opt_d": "A man is reading a magazine.",
          "correct_ans": "B",
          "explanation": "Trong tranh, người đàn ông đang làm việc với máy tính (working at his computer)."
        }
      ]
    }
  ]
}
    </pre>
</div>

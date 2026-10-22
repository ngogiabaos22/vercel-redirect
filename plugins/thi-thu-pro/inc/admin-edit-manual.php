<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_exams = $wpdb->prefix . 'tq_exams';
$table_groups = $wpdb->prefix . 'tq_groups';
$table_questions = $wpdb->prefix . 'tq_questions';

// Kiểm tra exam_id
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id) {
    echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy ID đề thi.</p></div>';
    return;
}

// Lấy thông tin đề thi hiện tại
$exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exams WHERE id = %d", $exam_id));
if (!$exam) {
    echo '<div class="notice notice-error"><p>Lỗi: Đề thi không tồn tại.</p></div>';
    return;
}

// Xử lý Form Lưu Đề Thi (Chỉnh sửa)
if (isset($_POST['tqp_save_manual_edit']) && check_admin_referer('tqp_save_manual_action', 'tqp_manual_nonce')) {
    $title = sanitize_text_field(wp_unslash($_POST['exam_title']));
    $duration = intval($_POST['exam_duration'] ?? 60);
    $pass_tag = sanitize_text_field(wp_unslash($_POST['exam_pass_tag']));
    $category = sanitize_text_field(wp_unslash($_POST['exam_category'] ?? 'Khác'));
    $scoring_method = sanitize_text_field(wp_unslash($_POST['exam_scoring_method'] ?? 'standard'));
    $max_score = !empty($_POST['exam_max_score']) ? floatval($_POST['exam_max_score']) : null;
    $video_url = esc_url_raw(wp_unslash($_POST['exam_video_url'] ?? ''));
    $audio_url = wp_unslash($_POST['exam_audio_url'] ?? '');
    
    // Save intervals as JSON
    $intervals = [];
    if (isset($_POST['intervals']) && is_array($_POST['intervals'])) {
        foreach ($_POST['intervals'] as $inv) {
            if (!empty($inv['text'])) {
                $intervals[] = [
                    'min' => floatval($inv['min'] ?? 0),
                    'max' => floatval($inv['max'] ?? 0),
                    'text' => wp_kses_post(wp_unslash($inv['text']))
                ];
            }
        }
    }
    $intervals_json = !empty($intervals) ? wp_json_encode($intervals) : null;

    // Cập nhật thông tin đề thi
    $wpdb->update($table_exams, [
        'title' => $title,
        'duration_minutes' => $duration,
        'pass_tag' => $pass_tag,
        'category' => $category,
        'scoring_method' => $scoring_method,
        'intervals' => $intervals_json,
        'max_score' => $max_score,
        'video_url' => $video_url,
        'audio_url' => $audio_url
    ], ['id' => $exam_id]);

    // XÓA DỮ LIỆU CŨ: Lấy danh sách nhóm cũ
    // KHÔNG xóa trong tq_questions nữa vì tq_questions giờ là Ngân Hàng chung.
    // Xóa groups cũ để tạo lại groups mới
    $wpdb->delete($table_groups, ['exam_id' => $exam_id]);

    // CHÈN LẠI DỮ LIỆU MỚI
    if (isset($_POST['groups']) && is_array($_POST['groups'])) {
        $group_sort_order = 0;
        foreach ($_POST['groups'] as $index => $g) {
            $wpdb->insert($table_groups, [
                'exam_id' => $exam_id,
                'group_type' => sanitize_text_field(wp_unslash($g['group_type'] ?? 'reading')),
                'instruction' => tqp_kses_post(wp_unslash($g['instruction'] ?? '')),
                'passage_text' => tqp_kses_post(wp_unslash($g['passage_text'] ?? '')),
                'media_url' => esc_url_raw(wp_unslash($g['media_url'] ?? '')),
                'points_per_q' => !empty($g['points_per_q']) ? floatval($g['points_per_q']) : null,
                'sort_order' => $group_sort_order++
            ]);
            $group_id = $wpdb->insert_id;

            if ($group_id && isset($g['questions']) && is_array($g['questions'])) {
                $saved_question_ids = [];
                
                foreach ($g['questions'] as $q) {
                    $q_id = isset($q['question_id']) ? intval($q['question_id']) : 0;
                    
                    $options_meta = [
                        'opt_a' => ['weight' => floatval($q['meta_a_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_a_keyword'] ?? ''))],
                        'opt_b' => ['weight' => floatval($q['meta_b_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_b_keyword'] ?? ''))],
                        'opt_c' => ['weight' => floatval($q['meta_c_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_c_keyword'] ?? ''))],
                        'opt_d' => ['weight' => floatval($q['meta_d_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_d_keyword'] ?? ''))],
                        'opt_e' => ['weight' => floatval($q['meta_e_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_e_keyword'] ?? ''))],
                        'opt_f' => ['weight' => floatval($q['meta_f_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_f_keyword'] ?? ''))],
                        'opt_g' => ['weight' => floatval($q['meta_g_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_g_keyword'] ?? ''))],
                        'opt_h' => ['weight' => floatval($q['meta_h_weight'] ?? 0), 'keyword' => sanitize_text_field(wp_unslash($q['meta_h_keyword'] ?? ''))]
                    ];
                    
                    $q_data = [
                        'group_id' => $group_id, // Giữ lại cho backward compatibility nếu cần, hoặc ko
                        'question_number' => intval($q['question_number']),
                        'question_text' => tqp_kses_post(wp_unslash($q['question_text'] ?? '')),
                        'opt_a' => tqp_kses_post(wp_unslash($q['opt_a'] ?? '')),
                        'opt_b' => tqp_kses_post(wp_unslash($q['opt_b'] ?? '')),
                        'opt_c' => tqp_kses_post(wp_unslash($q['opt_c'] ?? '')),
                        'opt_d' => tqp_kses_post(wp_unslash($q['opt_d'] ?? '')),
                        'opt_e' => tqp_kses_post(wp_unslash($q['opt_e'] ?? '')),
                        'opt_f' => tqp_kses_post(wp_unslash($q['opt_f'] ?? '')),
                        'opt_g' => tqp_kses_post(wp_unslash($q['opt_g'] ?? '')),
                        'opt_h' => tqp_kses_post(wp_unslash($q['opt_h'] ?? '')),
                        'correct_ans' => strtoupper(sanitize_text_field(wp_unslash($q['correct_ans'] ?? 'A'))),
                        'options_meta' => wp_json_encode($options_meta),
                        'explanation' => tqp_kses_post(wp_unslash($q['explanation'] ?? ''))
                    ];
                    
                    if ($q_id > 0) {
                        // Cập nhật câu hỏi có sẵn (Sync)
                        unset($q_data['question_number']);
                        $wpdb->update($table_questions, $q_data, ['id' => $q_id]);
                        $saved_question_ids[] = $q_id . ':' . intval($q['question_number']);
                    } else {
                        // Thêm câu hỏi mới (Copy hoặc mới hoàn toàn)
                        $table_categories = $wpdb->prefix . 'tq_question_categories';
                        $default_cat_id = $wpdb->get_var("SELECT id FROM `$table_categories` WHERE name = 'Chung' LIMIT 1");
                        $q_data['category_id'] = $default_cat_id ? $default_cat_id : 0;
                        
                        $wpdb->insert($table_questions, $q_data);
                        $saved_question_ids[] = $wpdb->insert_id . ':' . intval($q['question_number']);
                    }
                }
                
                // Cập nhật lại question_ids cho group này
                if (!empty($saved_question_ids)) {
                    $wpdb->update($table_groups, ['question_ids' => implode(',', $saved_question_ids)], ['id' => $group_id]);
                }
            }
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>Đã lưu thay đổi cho đề thi <strong>#' . $exam_id . '</strong> thành công!</p></div>';
    
    // Refresh exam object sau khi lưu
    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exams WHERE id = %d", $exam_id));
}

// ---------------------------------------------
// LẤY DỮ LIỆU ĐỂ TRUYỀN XUỐNG JS (PRELOAD)
$exam_intervals = !empty($exam->intervals) ? json_decode($exam->intervals, true) : [];
$exam_groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_groups WHERE exam_id = %d ORDER BY sort_order ASC", $exam_id), ARRAY_A);
foreach ($exam_groups as &$g) {
    $q_ids_str = !empty($g['question_ids']) ? $g['question_ids'] : '0';
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
    $questions = $wpdb->get_results("SELECT * FROM $table_questions WHERE id IN ($clean_ids_str)", ARRAY_A);
    
    // Sort questions based on the order in question_ids
    $ordered_questions = [];
    if (!empty($questions) && !empty($clean_ids)) {
        foreach ($clean_ids as $qid) {
            foreach ($questions as $q) {
                if ($q['id'] == $qid) {
                    if (isset($num_map[$qid])) {
                        $q['question_number'] = $num_map[$qid];
                    }
                    $q['options_meta'] = !empty($q['options_meta']) ? json_decode($q['options_meta'], true) : [];
                    $q['question_id'] = $q['id']; // Phục vụ cho template
                    // Check if it's linked from Bank (has a category)
                    $q['is_linked'] = ($q['category_id'] > 0);
                    $ordered_questions[] = $q;
                    break;
                }
            }
        }
    }
    $g['questions'] = $ordered_questions;
}
unset($g);
// ---------------------------------------------
?>

<div class="card" style="max-width:1200px;padding:20px 25px; margin-top:20px; background:#fff;">
    <form method="post" action="<?php echo esc_url($base_url . '&tab=edit_manual&exam_id=' . $exam_id); ?>" id="tqp-manual-form">
        <?php wp_nonce_field('tqp_save_manual_action', 'tqp_manual_nonce'); ?>
        <input type="hidden" name="tqp_save_manual_edit" value="1">

        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; margin-bottom: 10px;">
            <div>
                <h2 style="margin: 0 0 5px 0;">Chỉnh sửa Đề Thi #<?php echo $exam_id; ?></h2>
                <p class="description" style="margin: 0;">Chỉnh sửa thông tin và nội dung câu hỏi trực tiếp trên màn hình này.</p>
            </div>
            <div>
                <button type="submit" class="button button-primary button-hero" style="text-transform: uppercase;">Lưu Đề Thi</button>
            </div>
        </div>
        
        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;">1. Thông tin chung</h3>
        
        <?php
        // Fetch existing categories and tags for datalist and merge with default pool
        $db_categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_exams WHERE category != ''");
        $default_categories = ['GPLX Hạng A1', 'GPLX Hạng A2', 'GPLX Hạng B1', 'GPLX Hạng B2', 'GPLX Hạng C', 'GPLX Hạng D', 'Tiếng Anh (TOEIC)', 'Tiếng Trung (HSK)', 'Trắc nghiệm IQ', 'Trắc nghiệm MBTI', 'Khác'];
        $existing_categories = array_unique(array_filter(array_merge($default_categories, $db_categories)));

        $db_tags = $wpdb->get_col("SELECT DISTINCT pass_tag FROM $table_exams WHERE pass_tag != ''");
        $default_tags = ['gplx_a1_lock', 'gplx_b2_lock', 'toeic_lock', 'hsk_lock', 'iq_lock', 'mbti_lock', 'quiz_locker_key'];
        $existing_tags = array_unique(array_filter(array_merge($default_tags, $db_tags)));
        ?>
        <datalist id="tqp_category_list">
            <?php foreach ($existing_categories as $cat) { echo '<option value="' . esc_attr($cat) . '">'; } ?>
        </datalist>
        <datalist id="tqp_pass_tag_list">
            <?php foreach ($existing_tags as $tag) { echo '<option value="' . esc_attr($tag) . '">'; } ?>
        </datalist>

        <table class="form-table">
            <tr>
                <th style="width:200px;"><label>Tên đề thi *</label></th>
                <td><input type="text" name="exam_title" class="large-text" required value="<?php echo esc_attr($exam->title); ?>"></td>
            </tr>
            <tr>
                <th><label>Thời gian làm bài (phút) *</label></th>
                <td><input type="number" name="exam_duration" class="regular-text" required value="<?php echo intval($exam->duration_minutes); ?>"></td>
            </tr>
            <tr class="tqp-standard-only">
                <th><label>Thang điểm tối đa</label></th>
                <td>
                    <input type="number" step="0.01" name="exam_max_score" class="regular-text" value="<?php echo isset($exam->max_score) ? floatval($exam->max_score) : ''; ?>" placeholder="VD: 10, 100...">
                    <p class="description">Bỏ trống để tính theo tỷ lệ % số câu đúng / tổng số câu.</p>
                </td>
            </tr>
            <tr>
                <th><label>Chuyên mục *</label></th>
                <td>
                    <input type="text" name="exam_category" list="tqp_category_list" class="regular-text" required value="<?php echo esc_attr($exam->category); ?>">
                    <p class="description">Chọn từ danh sách hoặc gõ mới (VD: GPLX Hạng B, Test IQ, ...)</p>
                </td>
            </tr>
            <tr>
                <th><label>Hình thức chấm điểm *</label></th>
                <td>
                    <select name="exam_scoring_method" id="tqp_exam_scoring_method" class="regular-text">
                        <option value="standard" <?php selected($exam->scoring_method, 'standard'); ?>>Đúng/Sai truyền thống (GPLX, TOEIC, HSK...)</option>
                        <option value="score_range" <?php selected($exam->scoring_method, 'score_range'); ?>>Theo khoảng điểm (Test Trầm Cảm, IQ, EQ...)</option>
                        <option value="personality" <?php selected($exam->scoring_method, 'personality'); ?>>Trắc nghiệm Tính cách (MBTI, DISC...)</option>
                    </select>
                    <p class="description">Quyết định cách tính điểm và giao diện nhập đáp án bên dưới.</p>
                </td>
            </tr>
            <tr>
                <th><label>Tag bảo vệ (Quiz Locker)</label></th>
                <td><input type="text" name="exam_pass_tag" list="tqp_pass_tag_list" class="regular-text" placeholder="Để trống nếu không khóa" value="<?php echo esc_attr($exam->pass_tag); ?>"></td>
            </tr>
            <tr>
                <th><label>Video chữa bài (Tùy chọn)</label></th>
                <td><input type="url" name="exam_video_url" class="large-text" placeholder="VD: https://.../video.mp4 hoặc YouTube URL" value="<?php echo isset($exam->video_url) ? esc_url($exam->video_url) : ''; ?>"></td>
            </tr>
            <tr>
                <th><label>Audio đề thi (Tùy chọn)</label></th>
                <td><input type="text" name="exam_audio_url" class="large-text" placeholder="VD: https://.../audio.mp3 hoặc mã nhúng HTML" value="<?php echo isset($exam->audio_url) ? esc_attr($exam->audio_url) : ''; ?>"></td>
            </tr>
        </table>

        <!-- DANH SÁCH KẾT QUẢ (INTERVALS) -->
        <div id="tqp_intervals_section" style="display:none; margin-top: 30px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 0;">
                Khoảng điểm / Kết quả tương ứng
                <button type="button" class="button" id="tqp-add-interval-btn" style="float:right;">+ Thêm Khoảng KQ</button>
            </h3>
            <p class="description">Thiết lập kết quả hiển thị dựa trên tổng điểm đạt được.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:100px;">MIN Điểm</th>
                        <th style="width:100px;">MAX Điểm</th>
                        <th>Nội dung HTML hiển thị kết quả (Hỗ trợ nhúng ảnh, text màu...)</th>
                        <th style="width:50px;">Xóa</th>
                    </tr>
                </thead>
                <tbody id="tqp-intervals-container">
                    <!-- Javascript sẽ render vào đây -->
                </tbody>
            </table>
        </div>

        <!-- DANH SÁCH NHÓM CÂU HỎI -->
        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 30px;">
            2. Nội dung đề thi 
            <button type="button" class="button" id="tqp-add-group-btn" style="float:right;">+ Thêm Nhóm Câu Hỏi</button>
        </h3>
        
        <div id="tqp-groups-container">
            <!-- Nhóm câu hỏi sẽ được append bằng JS -->
        </div>

        <p class="submit" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <button type="submit" class="button button-primary button-hero" style="text-transform: uppercase;">Lưu Đề Thi</button>
        </p>
    </form>
</div>

<!-- TEMPLATE CHO JAVASCRIPT -->
<script type="text/template" id="tmpl-tqp-group">
    <div class="tqp-group-item" data-group-id="{{group_idx}}" style="background: #f9f9f9; border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
            <h4 style="margin:0;">Nhóm Câu Hỏi #<span class="group-num">{{group_num}}</span></h4>
            <div>
                <button type="button" class="button tqp-move-group-up" style="margin-right:5px;">Lên ⬆️</button>
                <button type="button" class="button tqp-move-group-down" style="margin-right:15px;">Xuống ⬇️</button>
                <button type="button" class="button button-link-delete tqp-remove-group" style="color:#dc3232;">Xóa nhóm này</button>
            </div>
        </div>
        
        <table class="form-table">
            <tr>
                <th style="width:150px;"><label>Loại nhóm</label></th>
                <td>
                    <select name="groups[{{group_idx}}][group_type]">
                        <option value="general">Mặc định (Chung)</option>
                        <option value="text_input">Nhập đáp án (Text Input)</option>
                        <option value="writing">Viết (Writing)</option>
                        <option value="speaking">Nói (Speaking)</option>
                        <option value="aptis_fill_blanks">Aptis - Điền từ vào câu (Inline)</option>
                        <option value="aptis_dropdown">Aptis - Chọn Dropdown (Bên phải)</option>
                    </select>
                    <p class="description" style="margin-top: 2px; font-size: 11px;">Phân loại nhóm (tùy chọn, thường dùng cho ngoại ngữ).</p>
                </td>
            </tr>
            <tr>
                <th><label>Hướng dẫn (Instruction)</label></th>
                <td><textarea name="groups[{{group_idx}}][instruction]" rows="2" class="large-text" placeholder="VD: Đọc đoạn văn sau và chọn đáp án đúng..."></textarea></td>
            </tr>
            <tr>
                <th><label>Đoạn văn (Passage)</label></th>
                <td><textarea name="groups[{{group_idx}}][passage_text]" rows="4" class="large-text" placeholder="Để trống nếu không có đoạn văn"></textarea></td>
            </tr>
            <tr>
                <th><label>Link Media (Audio/Image)</label></th>
                <td><input type="text" name="groups[{{group_idx}}][media_url]" class="large-text" placeholder="https://..."></td>
            </tr>
            <tr class="tqp-standard-only">
                <th><label>Điểm mỗi câu</label></th>
                <td>
                    <input type="number" step="0.01" name="groups[{{group_idx}}][points_per_q]" class="regular-text" placeholder="VD: 1, 0.25...">
                    <p class="description">Điểm cộng thêm cho MỖI CÂU trả lời đúng trong nhóm này.</p>
                </td>
            </tr>
        </table>
        
        <!-- CÂU HỎI TRONG NHÓM -->
        <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px dashed #bbb;">
            <h5 style="margin-top:0;">Danh sách Câu hỏi trong Nhóm</h5>
            <div class="tqp-questions-container"></div>
            <div style="margin-top: 10px; display:flex; gap: 10px;">
                <button type="button" class="button button-small tqp-add-question-btn" data-group-id="{{group_idx}}">+ Thêm Câu Hỏi Mới</button>
                <button type="button" class="button button-small tqp-btn-select-bank" data-group-id="{{group_idx}}" style="background: #f0f0f1; border-color: #007cba; color: #007cba;">+ Chọn từ Ngân Hàng</button>
                <button type="button" class="button button-small tqp-btn-import-group-json" style="background: #e5f5fa; border-color: #00a0d2; color: #0073aa;">📥 Import JSON Nhóm Này</button>
                <input type="file" class="tqp-import-group-file" data-group-id="{{group_idx}}" accept=".json" style="display:none;">
            </div>
        </div>
    </div>
</script>

<script type="text/template" id="tmpl-tqp-interval">
    <tr class="tqp-interval-item" data-id="{{interval_idx}}">
        <td><input type="number" step="0.1" name="intervals[{{interval_idx}}][min]" style="width:100%;"></td>
        <td><input type="number" step="0.1" name="intervals[{{interval_idx}}][max]" style="width:100%;"></td>
        <td><textarea name="intervals[{{interval_idx}}][text]" rows="3" style="width:100%;"></textarea></td>
        <td><button type="button" class="button tqp-remove-interval" style="color:#dc3232;">X</button></td>
    </tr>
</script>

<script type="text/template" id="tmpl-tqp-question">
    <div class="tqp-question-item" style="border-bottom: 1px dashed #eee; padding-bottom: 15px; margin-bottom: 15px;">
        <input type="hidden" name="groups[{{group_idx}}][questions][{{question_idx}}][question_id]" value="{{question_id}}" class="tqp-question-id-input">
        <div style="display:flex; justify-content:space-between; margin-bottom: 5px;">
            <strong>Câu hỏi: <span class="tqp-question-badge" style="font-weight:normal; font-size: 11px; padding: 2px 5px; background: #e5f5fa; color: #007cba; border-radius: 3px; display: {{badge_display}};">🔗 Liên kết từ Ngân hàng</span></strong>
            <div>
                <button type="button" class="button button-small tqp-toggle-details-btn" style="margin-right: 10px;">⬇️ Mở rộng</button>
                <a href="#" class="tqp-remove-question" style="color:#dc3232; text-decoration:none;">Xóa câu</a>
            </div>
        </div>
        <div style="display:flex; gap: 10px; margin-bottom: 10px;">
            <input type="number" name="groups[{{group_idx}}][questions][{{question_idx}}][question_number]" placeholder="STT" style="width: 80px;" required>
            <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][question_text]" placeholder="Nội dung câu hỏi..." style="flex:1;" class="tqp-q-text-input">
            <button type="button" class="button tqp-btn-upload-img" title="Thêm ảnh từ thư viện WP">🖼</button>
        </div>
        <div class="tqp-question-details" style="display:none; background: #fbfbfb; padding: 15px; border: 1px dashed #ddd; margin-top: 10px;">
            <div class="tqp-options-container" style="display:flex; flex-direction:column; gap: 10px; margin-bottom: 10px;">
                <div style="display:flex; gap:10px;" class="tqp-opt-row" data-opt="a">
                <span style="width:25px;font-weight:bold;margin-top:5px;">A.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_a]" placeholder="Nội dung đáp án A" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_a_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_a_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
            </div>
            <div style="display:flex; gap:10px;" class="tqp-opt-row" data-opt="b">
                <span style="width:25px;font-weight:bold;margin-top:5px;">B.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_b]" placeholder="Nội dung đáp án B" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_b_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_b_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
            </div>
            <div style="display:flex; gap:10px;" class="tqp-opt-row" data-opt="c">
                <span style="width:25px;font-weight:bold;margin-top:5px;">C.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_c]" placeholder="Nội dung đáp án C" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_c_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_c_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div style="display:flex; gap:10px;" class="tqp-opt-row" data-opt="d">
                <span style="width:25px;font-weight:bold;margin-top:5px;">D.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_d]" placeholder="Nội dung đáp án D" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_d_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_d_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div style="display:none; gap:10px;" class="tqp-opt-row" data-opt="e">
                <span style="width:25px;font-weight:bold;margin-top:5px;">E.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_e]" placeholder="Nội dung đáp án E" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_e_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_e_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div style="display:none; gap:10px;" class="tqp-opt-row" data-opt="f">
                <span style="width:25px;font-weight:bold;margin-top:5px;">F.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_f]" placeholder="Nội dung đáp án F" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_f_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_f_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div style="display:none; gap:10px;" class="tqp-opt-row" data-opt="g">
                <span style="width:25px;font-weight:bold;margin-top:5px;">G.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_g]" placeholder="Nội dung đáp án G" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_g_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_g_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div style="display:none; gap:10px;" class="tqp-opt-row" data-opt="h">
                <span style="width:25px;font-weight:bold;margin-top:5px;">H.</span>
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][opt_h]" placeholder="Nội dung đáp án H" style="flex:1;">
                <input type="number" step="0.1" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_h_weight]" class="tqp-meta-weight" placeholder="Điểm" style="width:120px; display:none;">
                <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][meta_h_keyword]" class="tqp-meta-keyword" placeholder="Từ khóa" style="width:140px; display:none;">
                <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
            </div>
            <div>
                <button type="button" class="button button-small tqp-btn-add-opt">+ Thêm đáp án</button>
            </div>
        </div>
        <div style="display:flex; gap: 15px; align-items: center;" class="tqp-correct-ans-wrap">
            <label><strong>Đ.Án Đúng:</strong></label>
            <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][correct_ans]" style="width: 80px; text-transform: uppercase; font-weight: bold; text-align: center;" placeholder="VD: A, B, ABC">
            <input type="text" name="groups[{{group_idx}}][questions][{{question_idx}}][explanation]" placeholder="Giải thích (tùy chọn)" style="flex:1;">
        </div>
        </div> <!-- End tqp-question-details -->
    </div>
</script>

<script>
jQuery(document).ready(function($) {
    let groupIndex = 0;
    window.tqpQuestionIndexes = window.tqpQuestionIndexes || {}; // Theo dõi chỉ mục câu hỏi của từng group
    let intervalIndex = 0;
    
    // WP Media Uploader cho nội dung câu hỏi
    $(document).on('click', '.tqp-btn-upload-img', function(e) {
        e.preventDefault();
        var button = $(this);
        var custom_uploader = wp.media({
            title: 'Chọn ảnh từ thư viện',
            button: { text: 'Chèn ảnh' },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            var imgTag = '<img class="ts-question-img" src="' + attachment.url + '" />';
            var inputField = button.siblings('.tqp-q-text-input');
            inputField.val(inputField.val() + ' ' + imgTag);
        }).open();
    });
    
    // Xử lý đổi Hình thức chấm điểm
    $('#tqp_exam_scoring_method').on('change', function() {
        let method = $(this).val();
        if (method === 'standard') {
            $('.tqp-meta-weight, .tqp-meta-keyword').hide();
            $('.tqp-correct-ans-wrap').show();
            $('#tqp_intervals_section').hide();
        } else if (method === 'score_range') {
            $('.tqp-meta-weight').show();
            $('.tqp-meta-keyword').hide();
            $('.tqp-correct-ans-wrap').hide();
            $('#tqp_intervals_section').show();
        } else if (method === 'personality') {
            $('.tqp-meta-weight').hide();
            $('.tqp-meta-keyword').show();
            $('.tqp-correct-ans-wrap').hide();
            $('#tqp_intervals_section').show();
        }
    });

    // Thêm khoảng điểm (Interval)
    $('#tqp-add-interval-btn').on('click', function() {
        let tmpl = $('#tmpl-tqp-interval').html();
        tmpl = tmpl.replace(/{{interval_idx}}/g, intervalIndex);
        $('#tqp-intervals-container').append(tmpl);
        intervalIndex++;
    });

    // Xóa khoảng điểm
    $(document).on('click', '.tqp-remove-interval', function() {
        $(this).closest('tr').remove();
    });

    // Thêm nhóm mới
    $('#tqp-add-group-btn').on('click', function() {
        let tmpl = $('#tmpl-tqp-group').html();
        tmpl = tmpl.replace(/{{group_idx}}/g, groupIndex).replace(/{{group_num}}/g, groupIndex + 1);
        $('#tqp-groups-container').append(tmpl);
        window.tqpQuestionIndexes[groupIndex] = 0;
        groupIndex++;
    });

    // Thêm câu hỏi vào nhóm
    $(document).on('click', '.tqp-add-question-btn', function() {
        let gIdx = $(this).data('group-id');
        let qIdx = window.tqpQuestionIndexes[gIdx];
        
        let tmpl = $('#tmpl-tqp-question').html();
        tmpl = tmpl.replace(/{{group_idx}}/g, gIdx).replace(/{{question_idx}}/g, qIdx);
        tmpl = tmpl.replace(/{{question_id}}/g, '');
        tmpl = tmpl.replace(/{{badge_display}}/g, 'none');
        
        $(this).parent().siblings('.tqp-questions-container').append(tmpl);
        window.tqpQuestionIndexes[gIdx]++;

        // Kích hoạt lại logic ẩn/hiện tùy chọn dựa trên hình thức chấm điểm
        $('#tqp_exam_scoring_method').trigger('change');
    });

    // Import JSON vào nhóm cụ thể
    $(document).on('click', '.tqp-btn-import-group-json', function() {
        $(this).siblings('.tqp-import-group-file').trigger('click');
    });

    $(document).on('change', '.tqp-import-group-file', function(e) {
        let file = e.target.files[0];
        if (!file) return;
        let gIdx = $(this).data('group-id');
        let groupEl = $(this).closest('.tqp-group-item');
        let gBtn = groupEl.find('.tqp-add-question-btn');
        
        let reader = new FileReader();
        reader.onload = function(e) {
            try {
                let data = JSON.parse(e.target.result);
                let questionsToImport = [];
                if (data.groups && data.groups.length > 0) {
                    questionsToImport = data.groups[0].questions;
                    if (data.groups[0].passage_text) {
                        let currentPassage = groupEl.find('textarea[name$="[passage_text]"]').val();
                        groupEl.find('textarea[name$="[passage_text]"]').val((currentPassage ? currentPassage + "\\n\\n" : "") + data.groups[0].passage_text);
                    }
                    if (data.groups[0].instruction) {
                        let currentInst = groupEl.find('textarea[name$="[instruction]"]').val();
                        groupEl.find('textarea[name$="[instruction]"]').val((currentInst ? currentInst + "\\n\\n" : "") + data.groups[0].instruction);
                    }
                } else if (data.questions && Array.isArray(data.questions)) {
                    questionsToImport = data.questions;
                    if (data.passage_text) {
                        let currentPassage = groupEl.find('textarea[name$="[passage_text]"]').val();
                        groupEl.find('textarea[name$="[passage_text]"]').val((currentPassage ? currentPassage + "\\n\\n" : "") + data.passage_text);
                    }
                    if (data.instruction) {
                        let currentInst = groupEl.find('textarea[name$="[instruction]"]').val();
                        groupEl.find('textarea[name$="[instruction]"]').val((currentInst ? currentInst + "\\n\\n" : "") + data.instruction);
                    }
                } else if (Array.isArray(data)) {
                    questionsToImport = data;
                }
                
                if (questionsToImport && questionsToImport.length > 0) {
                    questionsToImport.forEach(function(q) {
                        gBtn.trigger('click');
                        let qEl = groupEl.find('.tqp-question-item').last();
                        
                        qEl.find('input[name$="[question_number]"]').val(q.question_number || "");
                        qEl.find('input[name$="[question_text]"]').val(q.question_text || "");
                        qEl.find('input[name$="[opt_a]"]').val(q.opt_a || "");
                        qEl.find('input[name$="[opt_b]"]').val(q.opt_b || "");
                        qEl.find('input[name$="[opt_c]"]').val(q.opt_c || "");
                        qEl.find('input[name$="[opt_d]"]').val(q.opt_d || "");
                        qEl.find('input[name$="[opt_e]"]').val(q.opt_e || "");
                        qEl.find('input[name$="[opt_f]"]').val(q.opt_f || "");
                        qEl.find('input[name$="[opt_g]"]').val(q.opt_g || "");
                        qEl.find('input[name$="[opt_h]"]').val(q.opt_h || "");
                        qEl.find('input[name$="[correct_ans]"]').val(q.correct_ans || "A");
                        qEl.find('input[name$="[explanation]"]').val(q.explanation || "");
                        
                        if (q.opt_e && q.opt_e.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="e"]').css('display', 'flex'); }
                        if (q.opt_f && q.opt_f.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="f"]').css('display', 'flex'); }
                        if (q.opt_g && q.opt_g.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="g"]').css('display', 'flex'); }
                        if (q.opt_h && q.opt_h.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="h"]').css('display', 'flex'); }
                    });
                    alert("Đã thêm " + questionsToImport.length + " câu hỏi vào nhóm!");
                } else {
                    alert("Không tìm thấy câu hỏi nào trong file JSON!");
                }
            } catch (err) {
                alert("Lỗi đọc file JSON: " + err.message);
            }
        };
        reader.readAsText(file);
        
        $(this).val('');
    });

    // Xóa nhóm
    $(document).on('click', '.tqp-remove-group', function() {
        if(confirm('Bạn có chắc chắn muốn xóa toàn bộ nhóm câu hỏi này?')) {
            $(this).closest('.tqp-group-item').remove();
        }
    });

    // Xóa câu hỏi
    $(document).on('click', '.tqp-remove-question', function(e) {
        e.preventDefault();
        $(this).closest('.tqp-question-item').remove();
    });

    // Mở rộng/Thu gọn chi tiết
    $(document).on('click', '.tqp-toggle-details-btn', function(e) {
        e.preventDefault();
        let details = $(this).closest('.tqp-question-item').find('.tqp-question-details');
        details.slideToggle(200);
        if ($(this).text().includes('Mở rộng')) {
            $(this).html('⬆️ Thu gọn');
        } else {
            $(this).html('⬇️ Mở rộng');
        }
    });

    // Thêm đáp án (E -> H)
    $(document).on('click', '.tqp-btn-add-opt', function() {
        let container = $(this).closest('.tqp-options-container');
        let hiddenOpts = container.find('.tqp-opt-row:hidden');
        if (hiddenOpts.length > 0) {
            $(hiddenOpts[0]).css('display', 'flex');
        } else {
            alert('Đã đạt giới hạn tối đa 8 đáp án!');
        }
    });

    // Xóa đáp án
    $(document).on('click', '.tqp-btn-remove-opt', function() {
        let row = $(this).closest('.tqp-opt-row');
        row.hide();
        row.find('input[type="text"], input[type="number"]').val('');
    });

    // Di chuyển nhóm lên/xuống
    function updateGroupNumbers() {
        $('.tqp-group-item').each(function(idx) {
            $(this).find('.group-num').text(idx + 1);
        });
    }

    $(document).on('click', '.tqp-move-group-up', function() {
        let groupEl = $(this).closest('.tqp-group-item');
        let prevEl = groupEl.prev('.tqp-group-item');
        if (prevEl.length) {
            groupEl.insertBefore(prevEl);
            updateGroupNumbers();
        }
    });

    $(document).on('click', '.tqp-move-group-down', function() {
        let groupEl = $(this).closest('.tqp-group-item');
        let nextEl = groupEl.next('.tqp-group-item');
        if (nextEl.length) {
            groupEl.insertAfter(nextEl);
            updateGroupNumbers();
        }
    });

    // Lấy dữ liệu preload từ PHP
    let preloadedIntervals = <?php echo json_encode($exam_intervals); ?>;
    let preloadedGroups = <?php echo json_encode($exam_groups); ?>;

    // Hàm render Intervals
    if (preloadedIntervals && preloadedIntervals.length > 0) {
        preloadedIntervals.forEach(function(inv) {
            $('#tqp-add-interval-btn').trigger('click');
            let tr = $('#tqp-intervals-container .tqp-interval-item').last();
            tr.find('input[name$="[min]"]').val(inv.min);
            tr.find('input[name$="[max]"]').val(inv.max);
            tr.find('textarea[name$="[text]"]').val(inv.text);
        });
    } else {
        $('#tqp-add-interval-btn').trigger('click');
    }

    // Hàm render Groups & Questions
    if (preloadedGroups && preloadedGroups.length > 0) {
        preloadedGroups.forEach(function(g) {
            $('#tqp-add-group-btn').trigger('click');
            let groupEl = $('.tqp-group-item').last();
            
            groupEl.find('select[name$="[group_type]"]').val(g.group_type);
            groupEl.find('textarea[name$="[instruction]"]').val(g.instruction);
            groupEl.find('textarea[name$="[passage_text]"]').val(g.passage_text);
            groupEl.find('input[name$="[media_url]"]').val(g.media_url);
            groupEl.find('input[name$="[points_per_q]"]').val(g.points_per_q);
            
            let gBtn = groupEl.find('.tqp-add-question-btn');
            
            if (g.questions && g.questions.length > 0) {
                g.questions.forEach(function(q) {
                    gBtn.trigger('click');
                    let qEl = groupEl.find('.tqp-question-item').last();
                    
                    // Nạp ID và badge nếu là liên kết
                    if (q.question_id) {
                        qEl.find('.tqp-question-id-input').val(q.question_id);
                    }
                    if (q.is_linked) {
                        qEl.find('.tqp-question-badge').css('display', 'inline');
                    }
                    
                    qEl.find('input[name$="[question_number]"]').val(q.question_number);
                    qEl.find('input[name$="[question_text]"]').val(q.question_text);
                    qEl.find('input[name$="[opt_a]"]').val(q.opt_a);
                    qEl.find('input[name$="[opt_b]"]').val(q.opt_b);
                    qEl.find('input[name$="[opt_c]"]').val(q.opt_c);
                    qEl.find('input[name$="[opt_d]"]').val(q.opt_d);
                    qEl.find('input[name$="[opt_e]"]').val(q.opt_e);
                    qEl.find('input[name$="[opt_f]"]').val(q.opt_f);
                    qEl.find('input[name$="[opt_g]"]').val(q.opt_g);
                    qEl.find('input[name$="[opt_h]"]').val(q.opt_h);
                    qEl.find('input[name$="[correct_ans]"]').val(q.correct_ans);
                    qEl.find('input[name$="[explanation]"]').val(q.explanation);
                    
                    // Hiện các option E -> H nếu có dữ liệu
                    if (q.opt_e && q.opt_e.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="e"]').css('display', 'flex'); }
                    if (q.opt_f && q.opt_f.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="f"]').css('display', 'flex'); }
                    if (q.opt_g && q.opt_g.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="g"]').css('display', 'flex'); }
                    if (q.opt_h && q.opt_h.trim() !== '') { qEl.find('.tqp-opt-row[data-opt="h"]').css('display', 'flex'); }

                    // Options meta
                    if (q.options_meta) {
                        if(q.options_meta.opt_a) {
                            qEl.find('input[name$="[meta_a_weight]"]').val(q.options_meta.opt_a.weight);
                            qEl.find('input[name$="[meta_a_keyword]"]').val(q.options_meta.opt_a.keyword);
                        }
                        if(q.options_meta.opt_b) {
                            qEl.find('input[name$="[meta_b_weight]"]').val(q.options_meta.opt_b.weight);
                            qEl.find('input[name$="[meta_b_keyword]"]').val(q.options_meta.opt_b.keyword);
                        }
                        if(q.options_meta.opt_c) {
                            qEl.find('input[name$="[meta_c_weight]"]').val(q.options_meta.opt_c.weight);
                            qEl.find('input[name$="[meta_c_keyword]"]').val(q.options_meta.opt_c.keyword);
                        }
                        if(q.options_meta.opt_d) {
                            qEl.find('input[name$="[meta_d_weight]"]').val(q.options_meta.opt_d.weight);
                            qEl.find('input[name$="[meta_d_keyword]"]').val(q.options_meta.opt_d.keyword);
                        }
                        if(q.options_meta.opt_e) {
                            qEl.find('input[name$="[meta_e_weight]"]').val(q.options_meta.opt_e.weight);
                            qEl.find('input[name$="[meta_e_keyword]"]').val(q.options_meta.opt_e.keyword);
                        }
                        if(q.options_meta.opt_f) {
                            qEl.find('input[name$="[meta_f_weight]"]').val(q.options_meta.opt_f.weight);
                            qEl.find('input[name$="[meta_f_keyword]"]').val(q.options_meta.opt_f.keyword);
                        }
                        if(q.options_meta.opt_g) {
                            qEl.find('input[name$="[meta_g_weight]"]').val(q.options_meta.opt_g.weight);
                            qEl.find('input[name$="[meta_g_keyword]"]').val(q.options_meta.opt_g.keyword);
                        }
                        if(q.options_meta.opt_h) {
                            qEl.find('input[name$="[meta_h_weight]"]').val(q.options_meta.opt_h.weight);
                            qEl.find('input[name$="[meta_h_keyword]"]').val(q.options_meta.opt_h.keyword);
                        }
                    }
                });
            }
        });
    } else {
        if ($('.tqp-group-item').length === 0) {
            $('#tqp-add-group-btn').trigger('click');
            $('.tqp-add-question-btn').first().trigger('click');
        }
    }
    
    // Kích hoạt thay đổi giao diện theo method hiện tại
    $('#tqp_exam_scoring_method').trigger('change');
});
</script>

<?php require_once TQP_PATH . 'inc/admin-modal-question-bank.php'; ?>

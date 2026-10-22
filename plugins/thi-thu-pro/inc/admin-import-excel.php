<?php
if (!defined('ABSPATH')) exit;

require_once TQP_PATH . 'inc/libs/SimpleXLSX.php';
require_once TQP_PATH . 'inc/libs/SimpleXLSXGen.php';

global $wpdb;
$table_exams = $wpdb->prefix . 'tq_exams';
$table_groups = $wpdb->prefix . 'tq_groups';
$table_questions = $wpdb->prefix . 'tq_questions';

// ============================================================
// 1. TẢI FILE EXCEL MẪU
// ============================================================
if (isset($_GET['download_template']) && $_GET['download_template'] == '1') {
    $header = [
        'Nhóm Câu Hỏi (VD: 1, 2)', 
        'Loại Nhóm (reading/listening/general)', 
        'Hướng dẫn (Instruction)', 
        'Đoạn văn (Passage)', 
        'Link Media (Audio/Img)', 
        'STT Câu', 
        'Nội dung Câu hỏi', 
        'Đáp án A', 
        'Đáp án B', 
        'Đáp án C', 
        'Đáp án D', 
        'Đáp án Đúng (A/B/C/D)', 
        'Giải thích'
    ];
    
    $sample_data1 = [
        '1', 'listening', 'Listen and choose', '', 'https://example.com/audio.mp3', 
        '1', 'What is he doing?', 'Walking', 'Running', 'Sleeping', 'Eating', 'B', 'He is running in the park.'
    ];
    $sample_data2 = [
        '2', 'reading', 'Read and answer', 'This is a test passage...', '', 
        '2', 'What is this passage about?', 'A test', 'A game', 'A book', 'A movie', 'A', 'It clearly says test passage.'
    ];
    
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray([$header, $sample_data1, $sample_data2]);
    $xlsx->downloadAs('TestSpace_Excel_Template.xlsx');
    exit;
}

// ============================================================
// 2. XỬ LÝ IMPORT EXCEL
// ============================================================
if (isset($_POST['tqp_import_excel']) && check_admin_referer('tqp_import_excel_action', 'tqp_excel_nonce')) {
    $title = sanitize_text_field(wp_unslash($_POST['exam_title']));
    $duration = intval($_POST['exam_duration']);
    $pass_tag = sanitize_text_field(wp_unslash($_POST['exam_pass_tag']));
    $category = sanitize_text_field(wp_unslash($_POST['exam_category'] ?? 'Khác'));

    if (isset($_FILES['tqp_excel_file']) && $_FILES['tqp_excel_file']['error'] == 0) {
        $file_tmp = $_FILES['tqp_excel_file']['tmp_name'];
        
        if ($xlsx = Shuchkin\SimpleXLSX::parse($file_tmp)) {
            $rows = $xlsx->rows();
            
            if (count($rows) <= 1) {
                echo '<div class="notice notice-error is-dismissible"><p>File Excel không có dữ liệu câu hỏi!</p></div>';
            } else {
                // Xóa header (dòng 1)
                array_shift($rows);
                
                // Gom nhóm dữ liệu dựa trên Cột 0 (Nhóm Câu Hỏi)
                $grouped_data = [];
                foreach ($rows as $row) {
                    // Đảm bảo row đủ 13 cột để tránh lỗi index
                    $row = array_pad($row, 13, '');
                    
                    $group_id = trim($row[0]);
                    if (empty($group_id)) $group_id = 'group_default'; // Nếu quên nhập nhóm
                    
                    if (!isset($grouped_data[$group_id])) {
                        $grouped_data[$group_id] = [
                            'group_type' => trim($row[1]),
                            'instruction' => trim($row[2]),
                            'passage_text' => trim($row[3]),
                            'media_url' => trim($row[4]),
                            'questions' => []
                        ];
                    }
                    
                    // Thêm câu hỏi vào nhóm
                    if (!empty(trim($row[6]))) { // Cột 6 là Nội dung câu hỏi
                        $grouped_data[$group_id]['questions'][] = [
                            'question_number' => intval($row[5]),
                            'question_text' => trim($row[6]),
                            'opt_a' => trim($row[7]),
                            'opt_b' => trim($row[8]),
                            'opt_c' => trim($row[9]),
                            'opt_d' => trim($row[10]),
                            'correct_ans' => strtoupper(trim($row[11])),
                            'explanation' => trim($row[12])
                        ];
                    }
                }
                
                // Tiến hành lưu vào Database
                $wpdb->insert($table_exams, [
                    'title' => $title,
                    'duration_minutes' => $duration,
                    'pass_tag' => $pass_tag,
                    'category' => $category
                ]);
                $exam_id = $wpdb->insert_id;
                
                if ($exam_id) {
                    $g_order = 0;
                    foreach ($grouped_data as $g) {
                        $wpdb->insert($table_groups, [
                            'exam_id' => $exam_id,
                            'group_type' => sanitize_text_field($g['group_type'] ?: 'general'),
                            'instruction' => wp_kses_post($g['instruction']),
                            'passage_text' => wp_kses_post($g['passage_text']),
                            'media_url' => esc_url_raw($g['media_url']),
                            'sort_order' => $g_order++
                        ]);
                        $db_group_id = $wpdb->insert_id;
                        
                        foreach ($g['questions'] as $q) {
                            $wpdb->insert($table_questions, [
                                'group_id' => $db_group_id,
                                'question_number' => $q['question_number'],
                                'question_text' => wp_kses_post($q['question_text']),
                                'opt_a' => wp_kses_post($q['opt_a']),
                                'opt_b' => wp_kses_post($q['opt_b']),
                                'opt_c' => wp_kses_post($q['opt_c']),
                                'opt_d' => wp_kses_post($q['opt_d']),
                                'correct_ans' => sanitize_text_field($q['correct_ans']),
                                'explanation' => wp_kses_post($q['explanation'])
                            ]);
                        }
                    }
                    echo '<div class="notice notice-success is-dismissible"><p>Nhập đề thi từ Excel thành công! ID đề: <strong>#' . $exam_id . '</strong></p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Lỗi đọc file Excel: ' . Shuchkin\SimpleXLSX::parseError() . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Vui lòng chọn file Excel để tải lên.</p></div>';
    }
}
?>

<div class="card" style="max-width:1200px;padding:20px 25px; margin-top:20px; background:#fff;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Nhập Đề Thi Bằng Excel (.xlsx)</h2>
        <a href="<?php echo esc_url($base_url . '&tab=import_excel&download_template=1'); ?>" class="button button-secondary">📥 Tải File Mẫu (.xlsx)</a>
    </div>
    
    <p class="description">Tính năng này cho phép bạn tạo đề thi siêu nhanh bằng cách điền câu hỏi vào file Excel mẫu và tải lên.</p>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($base_url . '&tab=import_excel'); ?>">
        <?php wp_nonce_field('tqp_import_excel_action', 'tqp_excel_nonce'); ?>
        <input type="hidden" name="tqp_import_excel" value="1">
        
        <?php
        // Fetch existing categories and tags for datalist and merge with default pool
        $db_categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_exams WHERE category != ''");
        $default_categories = ['GPLX Hạng A1', 'GPLX Hạng A2', 'GPLX Hạng B1', 'GPLX Hạng B2', 'GPLX Hạng C', 'GPLX Hạng D', 'Tiếng Anh (TOEIC)', 'Tiếng Trung (HSK)', 'Trắc nghiệm IQ', 'Trắc nghiệm MBTI', 'Khác'];
        $existing_categories = array_unique(array_filter(array_merge($default_categories, $db_categories)));

        $db_tags = $wpdb->get_col("SELECT DISTINCT pass_tag FROM $table_exams WHERE pass_tag != ''");
        $default_tags = ['gplx_a1_lock', 'gplx_b2_lock', 'toeic_lock', 'hsk_lock', 'iq_lock', 'mbti_lock', 'quiz_locker_key'];
        $existing_tags = array_unique(array_filter(array_merge($default_tags, $db_tags)));
        ?>
        <datalist id="tqp_excel_category_list">
            <?php foreach ($existing_categories as $cat) { echo '<option value="' . esc_attr($cat) . '">'; } ?>
        </datalist>
        <datalist id="tqp_excel_pass_tag_list">
            <?php foreach ($existing_tags as $tag) { echo '<option value="' . esc_attr($tag) . '">'; } ?>
        </datalist>

        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 30px;">1. Thông tin chung</h3>
        <table class="form-table">
            <tr>
                <th style="width:200px;"><label>Tên đề thi *</label></th>
                <td><input type="text" name="exam_title" class="large-text" required placeholder="VD: Đề thi hạng B2 năm 2024"></td>
            </tr>
            <tr>
                <th><label>Thời gian làm bài (phút) *</label></th>
                <td><input type="number" name="exam_duration" class="regular-text" required value="45"></td>
            </tr>
            <tr>
                <th><label>Chuyên mục *</label></th>
                <td>
                    <input type="text" name="exam_category" list="tqp_excel_category_list" class="regular-text" required value="Khác">
                    <p class="description">Chọn từ danh sách hoặc gõ mới</p>
                </td>
            </tr>
            <tr>
                <th><label>Tag bảo vệ (Quiz Locker)</label></th>
                <td><input type="text" name="exam_pass_tag" list="tqp_excel_pass_tag_list" class="regular-text" placeholder="Để trống nếu không khóa"></td>
            </tr>
        </table>

        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 30px;">2. Tải lên danh sách câu hỏi</h3>
        <table class="form-table">
            <tr>
                <th style="width:200px;"><label>Chọn file Excel (.xlsx) *</label></th>
                <td>
                    <input type="file" name="tqp_excel_file" accept=".xlsx" required>
                    <p class="description">Hãy đảm bảo file Excel của bạn tuân thủ đúng định dạng các cột theo <a href="<?php echo esc_url($base_url . '&tab=import_excel&download_template=1'); ?>">File Mẫu</a>.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <button type="submit" class="button button-primary button-hero">🚀 Nhập Dữ Liệu Excel</button>
        </p>
    </form>
</div>

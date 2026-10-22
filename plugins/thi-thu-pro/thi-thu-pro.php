<?php
/**
 * Plugin Name: TestSpace - Thi Thử Online
 * Description: test system
 * Version: 3.0
 * Author: User
 */

if (!defined('ABSPATH'))
    exit;

define('TQP_VER', time()); // Bust cache during development
define('TQP_PATH', plugin_dir_path(__FILE__));
define('TQP_URL', plugin_dir_url(__FILE__));

// ============================================================
// 1. TẠO BẢNG DATABASE KHI ACTIVE & NÂNG CẤP TỰ ĐỘNG
// ============================================================
define('TQP_DB_VERSION', '3.0.3');

register_activation_hook(__FILE__, 'tqp_create_tables');

add_action('admin_init', 'tqp_check_db_version');
function tqp_check_db_version() {
    if (get_option('tqp_db_version') !== TQP_DB_VERSION) {
        tqp_create_tables();
        update_option('tqp_db_version', TQP_DB_VERSION);
    }
}

function tqp_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    $table_categories = $wpdb->prefix . 'tq_question_categories';

    $sql_categories = "CREATE TABLE $table_categories (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_exams = "CREATE TABLE $table_exams (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        duration_minutes int(11) NOT NULL DEFAULT 60,
        pass_tag varchar(255) DEFAULT '',
        category varchar(255) DEFAULT 'Khác',
        description longtext DEFAULT '',
        scoring_method varchar(50) DEFAULT 'standard',
        intervals longtext DEFAULT NULL,
        show_answers tinyint(1) NOT NULL DEFAULT 1,
        video_url varchar(255) DEFAULT '',
        audio_url longtext DEFAULT '',
        max_score decimal(10,2) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_groups = "CREATE TABLE $table_groups (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) unsigned NOT NULL,
        group_type varchar(50) NOT NULL DEFAULT 'reading',
        instruction text NOT NULL,
        passage_text longtext DEFAULT NULL,
        media_url varchar(255) DEFAULT '',
        points_per_q decimal(10,2) DEFAULT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        question_ids longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY exam_id (exam_id)
    ) $charset_collate;";

    $sql_questions = "CREATE TABLE $table_questions (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        group_id bigint(20) unsigned NOT NULL,
        category_id bigint(20) unsigned NOT NULL DEFAULT 0,
        question_number int(11) NOT NULL,
        question_text text NOT NULL,
        opt_a text NOT NULL,
        opt_b text NOT NULL,
        opt_c text NOT NULL,
        opt_d text NOT NULL,
        opt_e text DEFAULT NULL,
        opt_f text DEFAULT NULL,
        opt_g text DEFAULT NULL,
        opt_h text DEFAULT NULL,
        correct_ans varchar(5) NOT NULL,
        options_meta longtext DEFAULT NULL,
        explanation longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY group_id (group_id),
        KEY category_id (category_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_categories);
    dbDelta($sql_exams);
    dbDelta($sql_groups);
    dbDelta($sql_questions);

    // Kiểm tra xem bảng tq_exams đã được tạo chưa (dùng empty để tránh lỗi so sánh chữ hoa/chữ thường)
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_exams'");
    if (!empty($table_exists)) {
        $column_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'category'");
        if (empty($column_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN category varchar(255) DEFAULT 'Khác' AFTER pass_tag");
        }

        $desc_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'description'");
        if (empty($desc_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN description longtext DEFAULT '' AFTER category");
        }

        $scoring_method_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'scoring_method'");
        if (empty($scoring_method_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN scoring_method varchar(50) DEFAULT 'standard' AFTER description");
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN intervals longtext DEFAULT NULL AFTER scoring_method");
        }

        $max_score_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'max_score'");
        if (empty($max_score_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN max_score float DEFAULT NULL AFTER intervals");
        }

        $video_url_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'video_url'");
        if (empty($video_url_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN video_url varchar(255) DEFAULT '' AFTER show_answers");
        }

        $audio_url_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'audio_url'");
        if (empty($audio_url_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN audio_url longtext DEFAULT '' AFTER video_url");
        }
    }

    $table_g_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_groups'");
    if (!empty($table_g_exists)) {
        $q_ids_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_groups\` LIKE 'question_ids'");
        if (empty($q_ids_check)) {
            $wpdb->query("ALTER TABLE \`$table_groups\` ADD COLUMN question_ids longtext DEFAULT NULL AFTER sort_order");

            // MIGRATION: Chuyển đổi group_id từ bảng questions sang question_ids ở bảng groups
            $groups = $wpdb->get_results("SELECT id FROM \`$table_groups\`");
            $table_questions = $wpdb->prefix . 'tq_questions';
            foreach ($groups as $g) {

                $q_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM `$table_questions` WHERE group_id = %d ORDER BY question_number ASC", $g->id));
                if (!empty($q_ids)) {
                    $q_ids_str = implode(',', $q_ids);
                    $wpdb->update($table_groups, ['question_ids' => $q_ids_str], ['id' => $g->id]);
                }
            }
        }

        $points_per_q_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_groups` LIKE 'points_per_q'");
        if (empty($points_per_q_check)) {
            $wpdb->query("ALTER TABLE `$table_groups` ADD COLUMN points_per_q float DEFAULT NULL AFTER question_ids");
        }
    }

    $table_questions = $wpdb->prefix . 'tq_questions';
    $table_q_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_questions'");
    if (!empty($table_q_exists)) {
        $options_meta_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_questions` LIKE 'options_meta'");
        if (empty($options_meta_check)) {
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN options_meta longtext DEFAULT NULL AFTER correct_ans");
        }

        $opt_e_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_questions` LIKE 'opt_e'");
        if (empty($opt_e_check)) {
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN opt_e text DEFAULT NULL AFTER opt_d");
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN opt_f text DEFAULT NULL AFTER opt_e");
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN opt_g text DEFAULT NULL AFTER opt_f");
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN opt_h text DEFAULT NULL AFTER opt_g");
        }

        $cat_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_questions` LIKE 'category_id'");
        if (empty($cat_check)) {
            $wpdb->query("ALTER TABLE `$table_questions` ADD COLUMN category_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER group_id");
            // Gán category_id = 1 (Chung) cho tất cả câu hỏi cũ
            $default_cat_id = $wpdb->get_var("SELECT id FROM `$table_categories` WHERE name = 'Chung' LIMIT 1");
            if ($default_cat_id) {
                $wpdb->query($wpdb->prepare("UPDATE `$table_questions` SET category_id = %d WHERE category_id = 0", $default_cat_id));
            }
        }
    }

    // 1b. GỌI INSTALLER ĐỂ NHẬP DỮ LIỆU & TẠO TRANG NẾU CẦN
    require_once TQP_PATH . 'inc/installer.php';
    tqp_install_demo_data();
}
// ============================================================
// 2. ADMIN MENU & PAGES (Xử lý Xem, Thêm, Xóa, Import Đề)
// ============================================================
add_action('admin_menu', function () {
    // Menu chính
    add_menu_page('TestSpace', 'TestSpace', 'manage_options', 'traffic-quiz-pro', 'tqp_admin_page', 'dashicons-welcome-learn-more', 28);

    // Đổi tên Submenu mặc định đầu tiên thành "Tất cả đề thi" thay vì lặp lại "TestSpace"
    add_submenu_page('traffic-quiz-pro', 'Tất cả Đề thi', 'Tất cả Đề thi', 'manage_options', 'traffic-quiz-pro', 'tqp_admin_page');

    // Submenu Danh mục (Question Categories)
    add_submenu_page('traffic-quiz-pro', 'Danh mục câu hỏi', 'Danh mục câu hỏi', 'manage_options', 'tqp-question-categories', 'tqp_admin_categories_page');

    // Submenu Ngân hàng câu hỏi (All Questions)
    add_submenu_page('traffic-quiz-pro', 'Ngân hàng câu hỏi', 'Ngân hàng câu hỏi', 'manage_options', 'tqp-question-bank', 'tqp_admin_questions_page');
});

// 2b. NẠP MEDIA UPLOADER CHO TRANG ADMIN
// ============================================================
add_action('admin_enqueue_scripts', 'tqp_admin_enqueue_scripts');
function tqp_admin_enqueue_scripts($hook)
{
    // Chỉ nạp wp.media trên các trang của plugin
    if (strpos($hook, 'traffic-quiz-pro') !== false) {
        wp_enqueue_media();
        // Nạp thêm script tùy chỉnh cho nút upload nếu cần
        // wp_enqueue_script('tqp-admin-script', TQP_URL . 'assets/admin-script.js', ['jquery'], TQP_VER, true);
    }
}

require_once TQP_PATH . 'inc/admin-pages.php';

// Include các file tính năng khác
require_once TQP_PATH . 'inc/api.php';
require_once TQP_PATH . 'inc/shortcode.php';
require_once TQP_PATH . 'inc/hsk-landing-shortcode.php';
require_once TQP_PATH . 'inc/aptis-landing-shortcode.php';
require_once TQP_PATH . 'inc/vstep-landing-shortcode.php';
require_once TQP_PATH . 'inc/topik-landing-shortcode.php';
require_once TQP_PATH . 'inc/category-landing-shortcode.php';
require_once TQP_PATH . 'inc/new-homepage-shortcode.php';

// ============================================================
// 3. HOOK OVERRIDE TRANG CHỦ WEBSITE (FRONT PAGE PORTAL)
// ============================================================
add_filter('show_admin_bar', '__return_false');
add_filter('template_include', 'tqp_override_homepage_template');
function tqp_override_homepage_template($template)
{
    // 1. Nếu có tham số exam_id trong URL, chuyển hướng nạp ngay giao diện trang thi độc lập, tràn viền
    if (isset($_GET['exam_id']) && intval($_GET['exam_id']) > 0) {
        // Đảm bảo assets được nạp sớm
        wp_enqueue_style('tqp-font-inter');
        wp_enqueue_style('tqp-style');
        wp_enqueue_style('tqp-katex');
        wp_enqueue_script('canvas-confetti');
        wp_enqueue_script('tqp-katex');
        wp_enqueue_script('tqp-katex-auto');
        wp_enqueue_script('tqp-script');

        $exam_template = TQP_PATH . 'inc/exam-page-template.php';
        if (file_exists($exam_template)) {
            return $exam_template;
        }
    }

    // 1b. Nếu là CPT phòng thi dedicated (/bai-thi/)
    if (is_singular('tq_exam')) {
        // Đảm bảo assets được nạp sớm
        wp_enqueue_style('tqp-font-inter');
        wp_enqueue_style('tqp-style');
        wp_enqueue_style('tqp-katex');
        wp_enqueue_script('canvas-confetti');
        wp_enqueue_script('tqp-katex');
        wp_enqueue_script('tqp-katex-auto');
        wp_enqueue_script('tqp-script');

        $exam_template = TQP_PATH . 'inc/exam-page-template.php';
        if (file_exists($exam_template)) {
            return $exam_template;
        }
    }

    // Forced frontend portal loading removed.
    // The portal is now available via shortcode [tqp_search_portal]

    // 3. Nạp giao diện Landing Page đồng bộ (Bỏ qua Header/Footer của Flatsome)
    if (is_page()) {
        global $post;
        if (isset($post->post_content) && has_shortcode($post->post_content, 'traffic_quiz')) {
            // Đảm bảo assets CSS/JS được nạp trên Landing Page
            wp_enqueue_style('tqp-font-inter');
            wp_enqueue_style('tqp-style');
            wp_enqueue_script('canvas-confetti');
            wp_enqueue_script('tqp-script');

            $landing_template = TQP_PATH . 'inc/landing-page-template.php';
            if (file_exists($landing_template)) {
                return $landing_template;
            }
        }
    }

    return $template;
}

// ============================================================
// 4. MIGRATION FUNCTION - CHUYỂN ĐỔI DỮ LIỆU ĐỀ THI GỐC
// ============================================================
function tqp_migrate_original_data()
{
    global $wpdb;

    // Đảm bảo cột category tồn tại trong bảng tq_exams trước khi chạy di trú!
    $table_exams = $wpdb->prefix . 'tq_exams';
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_exams` LIKE 'category'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE `$table_exams` ADD COLUMN category varchar(255) DEFAULT 'Khác' AFTER pass_tag");
    }

    // Tự động tìm bảng dữ liệu cũ có kết thúc là 'aysquiz_quizes' (không quan tâm prefix nào!)
    $all_tables = $wpdb->get_col("SHOW TABLES");
    $found_table = '';
    foreach ($all_tables as $tbl) {
        if (preg_match('/[a-zA-Z0-9_]*aysquiz_quizes$/i', $tbl)) {
            $found_table = $tbl;
            break;
        }
    }

    // Nếu không tìm thấy, thông báo lỗi cụ thể
    if (empty($found_table)) {
        return [
            'success' => false,
            'message' => 'Không tìm thấy bảng dữ liệu gốc chứa `aysquiz_quizes` trong cơ sở dữ liệu! Vui lòng kiểm tra lại xem bạn đã import tệp database.sql bằng phpMyAdmin chưa.'
        ];
    }

    // Trích xuất prefix thực tế của các bảng cũ
    $old_prefix = str_ireplace('aysquiz_quizes', '', $found_table);

    $table_quizes = $found_table;
    $table_questions_old = $old_prefix . 'aysquiz_questions';
    $table_answers_old = $old_prefix . 'aysquiz_answers';
    $table_categories_old = $old_prefix . 'aysquiz_categories';

    // Xóa sạch dữ liệu mới để bắt đầu đồng bộ mới hoàn toàn
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    $wpdb->query("TRUNCATE TABLE $table_questions");
    $wpdb->query("TRUNCATE TABLE $table_groups");
    $wpdb->query("TRUNCATE TABLE $table_exams");

    // Lấy danh sách các đề thi (quizzes) đã kích hoạt
    $quizzes = $wpdb->get_results("SELECT * FROM $table_quizes WHERE published = 1 ORDER BY id ASC");
    if (!$quizzes) {
        return ['success' => false, 'message' => "Tìm thấy bảng cũ `$found_table` nhưng không có đề thi nào ở trạng thái kích hoạt!"];
    }

    $imported_count = 0;

    foreach ($quizzes as $quiz) {
        // Phân tích options để lấy thời gian làm bài (timer) - Hỗ trợ cả định dạng JSON và Serialized (Đơn vị giây -> phút)
        $duration = 120; // Mặc định 120 phút
        if ($quiz->options) {
            $opts = json_decode($quiz->options, true);
            if (!is_array($opts)) {
                $opts = maybe_unserialize($quiz->options);
            }
            if (is_array($opts) && isset($opts['timer'])) {
                $duration = max(1, ceil(intval($opts['timer']) / 60));
            }
        }

        // Kiểm tra xem có Quiz Locker tag nào liên quan không bằng cách quét bảng posts cũ
        $pass_tag = '';
        $posts_table = $old_prefix . 'posts';
        if ($wpdb->get_var("SHOW TABLES LIKE '$posts_table'") === $posts_table) {
            $shortcode_pattern_1 = '%[ays_quiz id=\'' . $quiz->id . '\']%';
            $shortcode_pattern_2 = '%[ays_quiz id="' . $quiz->id . '"]%';
            $post_content = $wpdb->get_var($wpdb->prepare(
                "SELECT post_content FROM $posts_table 
                 WHERE post_status = 'publish' 
                   AND (post_content LIKE %s OR post_content LIKE %s)
                 LIMIT 1",
                $shortcode_pattern_1,
                $shortcode_pattern_2
            ));
            if ($post_content) {
                if (preg_match('/data-quiz-tag=["\']([^"\']+)["\']/', $post_content, $matches)) {
                    $pass_tag = sanitize_text_field($matches[1]);
                }
            }
        }

        // Lấy tên danh mục từ database cũ
        $old_cat_title = '';
        if (!empty($quiz->quiz_category_id)) {
            $old_cat_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM $table_categories_old WHERE id = %d", $quiz->quiz_category_id));
        }
        if (empty($old_cat_title)) {
            $old_cat_title = 'Khác';
        }
        $resolved_category = tqp_group_category_name($old_cat_title, $quiz->title);

        // Chèn thông tin đề thi mới (Giữ nguyên ID gốc để không bị lệch shortcode)
        $wpdb->insert($table_exams, [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'duration_minutes' => $duration,
            'pass_tag' => $pass_tag,
            'category' => $resolved_category
        ]);
        $exam_id = $quiz->id;

        // Lấy danh sách ID câu hỏi (phân tách bằng dấu phẩy)
        $q_ids_str = trim($quiz->question_ids);
        if (empty($q_ids_str))
            continue;

        $q_ids = array_map('intval', explode(',', $q_ids_str));
        $q_ids_placeholder = implode(',', array_fill(0, count($q_ids), '%d'));

        // Lấy toàn bộ câu hỏi đã kích hoạt thuộc đề thi này
        $questions_data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_questions_old WHERE id IN ($q_ids_placeholder) AND published = 1",
            $q_ids
        ));

        if (!$questions_data)
            continue;

        // Nhóm câu hỏi theo category_id để tạo cấu trúc phần thi (Groups)
        $grouped_questions = [];
        foreach ($questions_data as $q) {
            $grouped_questions[$q->category_id][] = $q;
        }

        $group_sort = 0;
        foreach ($grouped_questions as $cat_id => $qs) {
            // Lấy tên danh mục làm tiêu đề hướng dẫn (instruction)
            $cat_title = $wpdb->get_var($wpdb->prepare("SELECT title FROM $table_categories_old WHERE id = %d", $cat_id));
            if (!$cat_title) {
                $cat_title = 'Làm bài trắc nghiệm';
            }

            // Phân tích câu hỏi để tự động nhận diện phần Nghe (Listening) hay Đọc (Reading)
            $passage_text = '';
            $media_url = '';
            $group_type = 'reading';

            foreach ($qs as $q) {
                if (stripos($q->question, '<audio') !== false || stripos($q->question, '.mp3') !== false) {
                    $group_type = 'listening';
                    // Trích xuất URL file audio mp3 nếu có
                    if (preg_match('/src=["\'](https?:\/\/[^"\']+\.mp3)["\']/i', $q->question, $matches)) {
                        $media_url = $matches[1];
                    }
                }
            }

            // Chèn nhóm câu hỏi mới
            $wpdb->insert($table_groups, [
                'exam_id' => $exam_id,
                'group_type' => $group_type,
                'instruction' => $cat_title,
                'passage_text' => $passage_text,
                'media_url' => $media_url,
                'sort_order' => $group_sort++
            ]);
            $group_id = $wpdb->insert_id;

            // Chèn từng câu hỏi con thuộc nhóm này
            $q_num = 1;
            foreach ($qs as $q) {
                // Nhúng bộ lọc bảo mật nội dung, xóa bỏ backslash dư thừa và tự động parse shortcode [audio] sang HTML5 audio player
                $q_text = wp_unslash($q->question);
                $q_text = wp_kses_post($q_text);
                $q_text = preg_replace(
                    '/\[audio\s+mp3=\\\\*["\'&]?(?:quot;)?(https?:\/\/[^\]\s\\\"\'&;]+?\.mp3)\\\\*["\'&]?(?:quot;)?\](?:\[\/audio\])?/i',
                    '<audio controls class="ts-question-audio" src="$1"></audio>',
                    $q_text
                );

                // Lấy danh sách câu trả lời trong bảng cũ
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_answers_old WHERE question_id = %d ORDER BY ordering ASC, id ASC",
                    $q->id
                ));

                $opt_a = '';
                $opt_b = '';
                $opt_c = '';
                $opt_d = '';
                $correct_ans = 'A';

                if ($answers) {
                    $letters = ['A', 'B', 'C', 'D'];
                    foreach ($answers as $idx => $ans) {
                        if ($idx > 3)
                            break;
                        $letter = $letters[$idx];
                        $opt_var = 'opt_' . strtolower($letter);
                        $$opt_var = wp_kses_post(wp_unslash($ans->answer));

                        if ($ans->correct) {
                            $correct_ans = $letter;
                        }
                    }
                }

                $wpdb->insert($table_questions, [
                    'group_id' => $group_id,
                    'question_number' => $q_num++,
                    'question_text' => $q_text,
                    'opt_a' => $opt_a,
                    'opt_b' => $opt_b,
                    'opt_c' => $opt_c,
                    'opt_d' => $opt_d,
                    'correct_ans' => $correct_ans,
                    'explanation' => wp_kses_post(wp_unslash($q->explanation ?: $q->user_explanation ?: ''))
                ]);
            }
        }
        $imported_count++;
    }

    return ['success' => true, 'message' => "Đã đồng bộ thành công $imported_count đề thi gốc sang cơ sở dữ liệu TestSpace! Giao diện mới đã sẵn sàng hoạt động."];
}

if (!function_exists('tqp_group_category_name')) {
    function tqp_group_category_name($title, $quiz_title = '')
    {
        // Hỗ trợ tự động nhận diện từ khóa trong Tiêu đề Đề thi để sửa lỗi phân loại sai trên DB gốc
        if (!empty($quiz_title)) {
            $quiz_title_lower = mb_strtolower($quiz_title);
            if (strpos($quiz_title_lower, 'hsk') !== false) {
                return 'Thi thử HSK';
            }
            if (strpos($quiz_title_lower, 'hạng b') !== false || strpos($quiz_title_lower, 'hạng_b') !== false || strpos($quiz_title_lower, 'gplx b') !== false) {
                return 'GPLX Hạng B';
            }
            if (strpos($quiz_title_lower, 'hạng c') !== false || strpos($quiz_title_lower, 'hạng_c') !== false || strpos($quiz_title_lower, 'gplx c') !== false) {
                return 'GPLX Hạng C';
            }
            if (strpos($quiz_title_lower, 'a1') !== false || strpos($quiz_title_lower, 'gplx a1') !== false) {
                return 'GPLX Hạng A1';
            }
            if (strpos($quiz_title_lower, 'iq') !== false) {
                return 'Test IQ';
            }
            if (strpos($quiz_title_lower, 'eq') !== false) {
                return 'Test EQ';
            }
            if (strpos($quiz_title_lower, 'adhd') !== false) {
                return 'Test ADHD';
            }
            if (strpos($quiz_title_lower, 'mbti') !== false) {
                return 'Test MBTI';
            }
            if (strpos($quiz_title_lower, 'disc') !== false) {
                return 'Test DISC';
            }
            if (strpos($quiz_title_lower, 'holland') !== false) {
                return 'Test Holland';
            }
            if (strpos($quiz_title_lower, 'trầm cảm') !== false || strpos($quiz_title_lower, 'beck') !== false) {
                return 'Test Trầm Cảm';
            }
            if (strpos($quiz_title_lower, 'big five') !== false || strpos($quiz_title_lower, 'big_five') !== false) {
                return 'Test Big Five';
            }
            if (strpos($quiz_title_lower, 'test aq') !== false || strpos($quiz_title_lower, 'test_aq') !== false) {
                return 'Test AQ';
            }
            if (strpos($quiz_title_lower, 'test mi') !== false || strpos($quiz_title_lower, 'test_mi') !== false) {
                return 'Test MI';
            }
        }

        $title = trim($title);
        if (stripos($title, 'BLX B') !== false) {
            return 'GPLX Hạng B';
        }
        if (stripos($title, 'BLX C') !== false) {
            return 'GPLX Hạng C';
        }
        if (stripos($title, 'BLX A1') !== false) {
            return 'GPLX Hạng A1';
        }
        if (stripos($title, 'HSK') !== false) {
            return 'Thi thử HSK';
        }
        if (stripos($title, 'Trầm Cảm') !== false || stripos($title, 'Beck') !== false) {
            return 'Test Trầm Cảm';
        }
        if (stripos($title, 'toán') !== false) {
            return 'Toán tiểu học';
        }
        if (strcasecmp($title, 'Big five') === 0) {
            return 'Test Big Five';
        }
        if (strcasecmp($title, 'MBTI') === 0) {
            return 'Test MBTI';
        }
        if (strcasecmp($title, 'DISC') === 0) {
            return 'Test DISC';
        }
        return $title;
    }
}

function tqp_get_exam_page_url($exam_id)
{
    // 1. Ưu tiên kiểm tra CPT Post có cấu hình _tqp_landing_page_url không (user điền thủ công)
    $cpt_post_id = tqp_get_exam_cpt_post_id($exam_id);
    if ($cpt_post_id) {
        $landing_url = get_post_meta($cpt_post_id, '_tqp_landing_page_url', true);
        if (!empty($landing_url)) {
            return esc_url($landing_url);
        }
    }
    // 2. Trả về link phòng thi dedicated CPT (/bai-thi/...)
    // (Bỏ qua việc tìm kiếm shortcode trong các trang khác để luôn hiển thị giao diện Lobby đầy đủ)
    if ($cpt_post_id) {
        return get_permalink($cpt_post_id);
    }

    return home_url('?exam_id=' . $exam_id);

}

// Đăng ký Custom Post Type 'tq_exam' với slug 'bai-thi'
add_action('init', 'tqp_register_exam_cpt');
function tqp_register_exam_cpt()
{
    register_post_type('tq_exam', [
        'labels' => [
            'name' => 'Đề thi TestSpace',
            'singular_name' => 'Đề thi',
            'menu_name' => 'Liên kết Đề thi',
            'add_new' => 'Thêm mới',
            'add_new_item' => 'Thêm đề thi mới',
            'edit_item' => 'Sửa đề thi',
            'new_item' => 'Đề thi mới',
            'view_item' => 'Xem đề thi',
            'search_items' => 'Tìm đề thi',
            'not_found' => 'Không tìm thấy đề thi',
            'not_found_in_trash' => 'Không tìm thấy đề thi trong thùng rác'
        ],
        'public' => true,
        'has_archive' => false,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => false, // Quản lý qua TestSpace Dashboards
        'query_var' => true,
        'rewrite' => ['slug' => 'bai-thi', 'with_front' => false],
        'capability_type' => 'post',
        'supports' => ['title', 'custom-fields'],
    ]);
}

// Helper để tra cứu nhanh CPT Post ID từ Exam ID
function tqp_get_exam_cpt_post_id($exam_id)
{
    global $wpdb;
    $exam_id = intval($exam_id);
    if ($exam_id <= 0)
        return 0;

    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_tq_exam_id' AND meta_value = %d 
         LIMIT 1",
        $exam_id
    ));

    return $post_id ? intval($post_id) : 0;
}

// Tự động đồng bộ đề thi từ custom table sang CPT Posts
add_action('admin_init', 'tqp_sync_exams_to_cpt');
function tqp_sync_exams_to_cpt()
{
    global $wpdb;

    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_exams'");
    if (empty($table_exists)) {
        return;
    }

    $exams = $wpdb->get_results("SELECT id, title FROM $table_exams");
    $existing_exam_ids = [];

    if ($exams) {
        foreach ($exams as $exam) {
            $existing_exam_ids[] = intval($exam->id);

            $post_id = tqp_get_exam_cpt_post_id($exam->id);
            $expected_content = '[traffic_quiz id="' . $exam->id . '"]';

            if (!$post_id) {
                // Tạo CPT post mới tương ứng
                $post_id = wp_insert_post([
                    'post_title' => $exam->title,
                    'post_name' => sanitize_title($exam->title),
                    'post_content' => $expected_content,
                    'post_status' => 'publish',
                    'post_type' => 'tq_exam',
                ]);
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, '_tq_exam_id', $exam->id);
                }
            } else {
                // Đồng bộ title nếu thay đổi
                $post = get_post($post_id);
                if ($post && ($post->post_title !== $exam->title || $post->post_content !== $expected_content)) {
                    wp_update_post([
                        'ID' => $post_id,
                        'post_title' => $exam->title,
                        'post_name' => sanitize_title($exam->title),
                        'post_content' => $expected_content,
                    ]);
                }
            }
        }
    }

    // Dọn dẹp
    $cpt_posts = get_posts([
        'post_type' => 'tq_exam',
        'post_status' => 'any',
        'numberposts' => -1,
    ]);

    if ($cpt_posts) {
        foreach ($cpt_posts as $p) {
            $meta_exam_id = get_post_meta($p->ID, '_tq_exam_id', true);
            if ($meta_exam_id && !in_array(intval($meta_exam_id), $existing_exam_ids)) {
                wp_delete_post($p->ID, true);
            }
        }
    }

    // Flush rewrite rules khi kích hoạt
    if (get_transient('tqp_flush_rewrites')) {
        flush_rewrite_rules();
        delete_transient('tqp_flush_rewrites');
    }
}

/**
 * Hàm lọc nội dung an toàn nhưng vẫn cho phép thẻ <audio> và <source>
 */
function tqp_kses_post($content)
{
    $allowed = wp_kses_allowed_html('post');
    $allowed['audio'] = [
        'controls' => true,
        'autoplay' => true,
        'loop' => true,
        'muted' => true,
        'preload' => true,
        'src' => true,
        'class' => true,
        'style' => true,
        'id' => true
    ];
    $allowed['source'] = [
        'src' => true,
        'type' => true,
        'media' => true
    ];
    return wp_kses($content, $allowed);
}

/**
 * Proxy ảo (302 Redirect) để giấu nguồn ảnh/video/audio ở HTML (F12)
 */
add_action('wp_ajax_tqp_media', 'tqp_media_redirect');
add_action('wp_ajax_nopriv_tqp_media', 'tqp_media_redirect');
function tqp_media_redirect()
{
    if (!empty($_GET['u'])) {
        $url = base64_decode(strtr($_GET['u'], '-_', '+/'));
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            wp_redirect($url, 302);
            exit;
        }
    }
    wp_die('Invalid Media');
}
require_once TQP_PATH . 'inc/jlpt-landing-shortcode.php';
require_once TQP_PATH . 'inc/toeic-landing-shortcode.php';
require_once TQP_PATH . 'inc/homepage-template.php';

<?php
/**
 * tqp-exporter.php
 * Chạy file này trên trình duyệt: http://localhost/wordpress/wp-content/plugins/traffic-quiz-pro/tqp-exporter.php
 * Để đóng gói dữ liệu và media vào file assets/demo-data.json và assets/demo-media/
 */

require_once(dirname(__FILE__) . '/../../../wp-load.php');

if (php_sapi_name() !== 'cli' && !current_user_can('manage_options')) {
    die('Bạn phải đăng nhập với quyền Admin để chạy script này.');
}

global $wpdb;

$assets_dir = plugin_dir_path(__FILE__) . 'assets/';
$media_dir = $assets_dir . 'demo-media/';
$data_file = $assets_dir . 'demo-data.json';

if (!file_exists($media_dir)) {
    mkdir($media_dir, 0755, true);
}

// Hàm copy file media
$copied_files = [];
function tqp_process_and_copy_media(&$content, $upload_dir, $media_dir, &$copied_files) {
    if (empty($content) || !is_string($content)) return;
    
    $pattern = '/https?:\/\/[^\s"\'\>]*?\/wp-content\/uploads\/([a-zA-Z0-9_\-\.\/]+)/i';
    
    preg_match_all($pattern, $content, $matches);
    
    if (!empty($matches[0])) {
        foreach ($matches[0] as $index => $full_match) {
            $rel_path = $matches[1][$index];
            $rel_path = trim($rel_path, ' "\\\'');
            
            $source_file = $upload_dir['basedir'] . '/' . $rel_path;
            if (file_exists($source_file)) {
                $filename = basename($source_file);
                $dest_file = $media_dir . $filename;
                if (!file_exists($dest_file)) {
                    copy($source_file, $dest_file);
                    $copied_files[] = $filename;
                }
                
                $content = str_replace($full_match, '{{SITE_URL}}/wp-content/uploads/traffic-quiz-media/' . $filename, $content);
            }
        }
    }
}

$site_url = untrailingslashit(site_url());
$upload_dir = wp_upload_dir();

$data = [
    'categories' => [],
    'exams' => [],
    'groups' => [],
    'questions' => []
];

// Lấy dữ liệu Categories
$table_categories = $wpdb->prefix . 'tq_question_categories';
$categories = $wpdb->get_results("SELECT * FROM $table_categories", ARRAY_A);
foreach ($categories as $cat) {
    $data['categories'][] = $cat;
}

// Lấy dữ liệu Exams
$table_exams = $wpdb->prefix . 'tq_exams';
$exams = $wpdb->get_results("SELECT * FROM $table_exams", ARRAY_A);
foreach ($exams as &$exam) {
    tqp_process_and_copy_media($exam['description'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($exam['intervals'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($exam['video_url'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($exam['audio_url'], $upload_dir, $media_dir, $copied_files);
    $data['exams'][] = $exam;
}

// Lấy dữ liệu Groups
$table_groups = $wpdb->prefix . 'tq_groups';
$groups = $wpdb->get_results("SELECT * FROM $table_groups", ARRAY_A);
foreach ($groups as &$group) {
    tqp_process_and_copy_media($group['instruction'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($group['passage_text'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($group['media_url'], $upload_dir, $media_dir, $copied_files);
    $data['groups'][] = $group;
}

// Lấy dữ liệu Questions
$table_questions = $wpdb->prefix . 'tq_questions';
$questions = $wpdb->get_results("SELECT * FROM $table_questions", ARRAY_A);
foreach ($questions as &$question) {
    tqp_process_and_copy_media($question['question_text'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($question['opt_a'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($question['opt_b'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($question['opt_c'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($question['opt_d'], $upload_dir, $media_dir, $copied_files);
    tqp_process_and_copy_media($question['explanation'], $upload_dir, $media_dir, $copied_files);
    $data['questions'][] = $question;
}

file_put_contents($data_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "<h2>Thành công!</h2>";
echo "<p>Đã xuất " . count($data['categories']) . " categories, " . count($data['exams']) . " exams, " . count($data['groups']) . " groups, " . count($data['questions']) . " questions.</p>";
echo "<p>Đã copy " . count($copied_files) . " file media vào assets/demo-media/</p>";
echo "<pre>";
print_r($copied_files);
echo "</pre>";
echo "<p>Bây giờ bạn có thể nén thư mục traffic-quiz-pro và cài đặt lên web khác.</p>";

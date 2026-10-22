<?php
if (!defined('ABSPATH')) exit;

/**
 * Hàm import dữ liệu mẫu (JSON) và copy media khi kích hoạt plugin
 */
function tqp_install_demo_data() {
    global $wpdb;

    $assets_dir = plugin_dir_path(dirname(__FILE__)) . 'assets/';
    $data_file = $assets_dir . 'demo-data.json';
    $media_dir = $assets_dir . 'demo-media/';
    
    // 1. Kiểm tra xem DB đã có dữ liệu chưa (tránh ghi đè khi update plugin)
    $table_exams = $wpdb->prefix . 'tq_exams';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_exams");
    
    if ($count == 0 && file_exists($data_file)) {
        // Chưa có dữ liệu -> Bắt đầu import
        $json_content = file_get_contents($data_file);
        
        // Thay thế {{SITE_URL}} bằng tên miền thực tế
        $site_url = untrailingslashit(site_url());
        $json_content = str_replace('{{SITE_URL}}', $site_url, $json_content);
        
        $data = json_decode($json_content, true);
        
        if ($data) {
            // Import Categories
            if (!empty($data['categories'])) {
                $table_cat = $wpdb->prefix . 'tq_question_categories';
                foreach ($data['categories'] as $cat) {
                    $wpdb->insert($table_cat, [
                        'id' => $cat['id'],
                        'name' => $cat['name'],
                        'description' => $cat['description'],
                        'created_at' => $cat['created_at']
                    ]);
                }
            }
            
            // Import Exams
            if (!empty($data['exams'])) {
                foreach ($data['exams'] as $exam) {
                    $wpdb->insert($table_exams, [
                        'id' => $exam['id'],
                        'title' => $exam['title'],
                        'duration_minutes' => $exam['duration_minutes'],
                        'pass_tag' => $exam['pass_tag'],
                        'category' => $exam['category'],
                        'description' => $exam['description'],
                        'scoring_method' => $exam['scoring_method'],
                        'intervals' => $exam['intervals'],
                        'show_answers' => $exam['show_answers'],
                        'video_url' => $exam['video_url'],
                        'audio_url' => $exam['audio_url'],
                        'created_at' => $exam['created_at']
                    ]);
                }
            }
            
            // Import Groups
            if (!empty($data['groups'])) {
                $table_groups = $wpdb->prefix . 'tq_groups';
                foreach ($data['groups'] as $group) {
                    $wpdb->insert($table_groups, [
                        'id' => $group['id'],
                        'exam_id' => $group['exam_id'],
                        'group_type' => $group['group_type'],
                        'instruction' => $group['instruction'],
                        'passage_text' => $group['passage_text'],
                        'media_url' => $group['media_url'],
                        'sort_order' => $group['sort_order'],
                        'question_ids' => $group['question_ids']
                    ]);
                }
            }
            
            // Import Questions
            if (!empty($data['questions'])) {
                $table_questions = $wpdb->prefix . 'tq_questions';
                foreach ($data['questions'] as $q) {
                    $wpdb->insert($table_questions, [
                        'id' => $q['id'],
                        'group_id' => $q['group_id'],
                        'category_id' => $q['category_id'],
                        'question_number' => $q['question_number'],
                        'question_text' => $q['question_text'],
                        'opt_a' => $q['opt_a'],
                        'opt_b' => $q['opt_b'],
                        'opt_c' => $q['opt_c'],
                        'opt_d' => $q['opt_d'],
                        'opt_e' => $q['opt_e'],
                        'opt_f' => $q['opt_f'],
                        'opt_g' => $q['opt_g'],
                        'opt_h' => $q['opt_h'],
                        'correct_ans' => $q['correct_ans'],
                        'options_meta' => $q['options_meta'],
                        'explanation' => $q['explanation']
                    ]);
                }
            }
        }
        
        // 2. Copy Media Files
        if (file_exists($media_dir)) {
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/traffic-quiz-media';
            
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $files = scandir($media_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $source = $media_dir . $file;
                    $dest = $target_dir . '/' . $file;
                    if (is_file($source) && !file_exists($dest)) {
                        copy($source, $dest);
                    }
                }
            }
        }
        
        // 3. Tạo các Pages cần thiết
        tqp_create_required_pages();
    }
}

function tqp_create_required_pages() {
    $pages_to_create = [
        'Hệ thống thi trắc nghiệm' => '[tqp_new_homepage]',
        'Thi thử TOEIC' => '[tqp_category_landing category="TOEIC"]',
        'Thi thử JLPT' => '[tqp_category_landing category="JLPT"]',
        'Thi thử lý thuyết bằng lái B' => '[tqp_category_landing category="GPLX Hạng B"]',
        'Thi thử VSTEP' => '[tqp_category_landing category="VSTEP"]',
        'Thi thử HSK' => '[tqp_category_landing category="HSK"]',
        'Thi thử Topik' => '[tqp_category_landing category="Topik"]'
    ];

    foreach ($pages_to_create as $title => $content) {
        $page = get_page_by_title($title);
        if (!$page) {
            wp_insert_post([
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ]);
        }
    }
}

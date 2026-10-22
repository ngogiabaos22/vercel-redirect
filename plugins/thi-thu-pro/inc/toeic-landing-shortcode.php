<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('toeic_landing', 'tqp_toeic_landing_shortcode');
function tqp_toeic_landing_shortcode($atts)
{
    global $wpdb;

    // Lấy danh sách đề thi (Ví dụ: giả sử có category 'TOEIC')
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Fallback nếu cột category chưa tồn tại
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM `$table_exams` LIKE 'category'");
    if (!empty($column_check)) {
        // Query theo category TOEIC
        $exams = $wpdb->get_results("SELECT * FROM $table_exams WHERE category LIKE '%TOEIC%' OR title LIKE '%TOEIC%' ORDER BY id ASC");
    } else {
        $exams = $wpdb->get_results("SELECT * FROM $table_exams WHERE title LIKE '%TOEIC%' ORDER BY id ASC");
    }
    
    // Nếu không tìm thấy bằng chữ TOEIC, lấy tạm 12 đề mới nhất để test giao diện
    if (empty($exams)) {
        $exams = $wpdb->get_results("SELECT * FROM $table_exams ORDER BY id ASC LIMIT 12");
    }

    ob_start();
    ?>
    <style>
        /* Import FontAwesome */
        @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');

        /* Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        .ts-toeic-wrapper {
            font-family: 'Inter', sans-serif;
            color: #0F172A;
            background-color: #F8FAFC;
            padding-bottom: 60px;
        }
        
        .ts-toeic-wrapper * {
            box-sizing: border-box;
        }

        .ts-toeic-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* --- HERO SECTION --- */
        .ts-toeic-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 60px 0;
            gap: 40px;
        }

        .ts-toeic-hero-content {
            flex: 1;
            max-width: 500px;
        }

        .ts-toeic-hero-badge {
            display: inline-block;
            background: #EEF2FF;
            color: #6366F1;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }

        .ts-toeic-hero-title {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 24px;
            color: #0F172A;
        }

        .ts-toeic-hero-title span {
            color: #6366F1;
        }

        .ts-toeic-hero-desc {
            font-size: 16px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .ts-toeic-hero-btn {
            display: inline-block;
            background: #6366F1;
            color: #fff;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .ts-toeic-hero-btn:hover {
            background: #4F46E5;
            color: #fff;
        }
        
        .ts-toeic-hero-image {
            flex: 1;
            background: #1E1B4B;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            position: relative;
            color: #fff;
            font-size: 14px;
        }
        .ts-mock-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
        }
        .ts-mock-badge { background: rgba(255,255,255,0.1); padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .ts-mock-badge-success { background: #D1FAE5; color: #065F46; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .ts-mock-question { background: #312E81; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .ts-mock-question-title { color: #A5B4FC; font-size: 13px; margin-bottom: 8px; }
        .ts-mock-question-text { font-size: 16px; font-weight: 500; margin-bottom: 16px; line-height: 1.5; }
        .ts-mock-option { background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; cursor: pointer; }
        .ts-mock-option.active { background: #6366F1; }

        /* --- SECTION HEADING --- */
        .ts-toeic-section-header {
            text-align: center;
            margin-bottom: 40px;
            margin-top: 60px;
        }

        .ts-toeic-section-header h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #0F172A;
        }

        .ts-toeic-section-header p {
            font-size: 16px;
            color: #64748B;
        }

        /* --- CATEGORIES --- */
        .ts-toeic-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 60px;
        }

        .ts-toeic-category-card {
            border-radius: 16px;
            padding: 24px 16px;

            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .ts-toeic-category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .ts-toeic-category-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
            font-size: 20px;
        }

        .ts-toeic-category-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0F172A;
        }

        .ts-toeic-category-card p {
            font-size: 13px;
            color: #64748B;
            margin: 0;
        }

        /* --- EXAM GRID --- */
        .ts-toeic-exams-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .ts-toeic-hidden-exam {
            display: none !important;
        }

        .ts-toeic-exam-card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .ts-toeic-exam-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-4px);
        }
        .ts-toeic-ec-badge {
            display: inline-block;
            background: #FEE2E2;
            color: #EF4444;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .ts-toeic-ec-title {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 24px;
        }
        .ts-toeic-ec-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .ts-toeic-ec-label {
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ts-toeic-ec-val {
            font-weight: 600;
            color: #0F172A;
        }
        .ts-toeic-ec-footer {
            margin-top: 24px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
        .ts-toeic-dual-btns {
            display: flex;
            gap: 12px;
        }
        .ts-toeic-ec-btn {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
        }
        .ts-toeic-btn-doc {
            background: #FEF3C7;
            color: #D97706;
        }
        .ts-toeic-btn-nghe {
            background: #E0F2FE;
            color: #0284C7;
        }
        .ts-toeic-btn-single {
            display: block;
            background: #6366F1;
            color: #fff;
            width: 100%;
        }

        /* --- SERIES (ETS) --- */
        .ts-toeic-series-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }

        .ts-toeic-series-card {
            border-radius: 16px;
            padding: 32px 20px;
            text-align: center;
            color: #fff;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .ts-toeic-series-card:hover {
            transform: translateY(-5px);
            color: #fff;
        }

        .ts-toeic-series-icon {
            font-size: 32px;
            margin-bottom: 16px;
        }

        .ts-toeic-series-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .ts-toeic-series-btn {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-block;
        }

        /* --- ROADMAP --- */
        .ts-toeic-roadmap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            margin-top: 40px;
            padding: 0 20px;
        }
        
        .ts-toeic-roadmap::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: linear-gradient(to right, #94A3B8, #6366F1, #A855F7, #EC4899);
            z-index: 1;
        }

        .ts-toeic-roadmap-item {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .ts-toeic-roadmap-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #94A3B8;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            margin: 0 auto 16px auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .ts-toeic-roadmap-item:nth-child(2) .ts-toeic-roadmap-circle { background: #3B82F6; }
        .ts-toeic-roadmap-item:nth-child(3) .ts-toeic-roadmap-circle { background: #6366F1; }
        .ts-toeic-roadmap-item:nth-child(4) .ts-toeic-roadmap-circle { background: #8B5CF6; }
        .ts-toeic-roadmap-item:nth-child(5) .ts-toeic-roadmap-circle { background: #A855F7; }
        .ts-toeic-roadmap-item:nth-child(6) .ts-toeic-roadmap-circle { background: #D946EF; }

        .ts-toeic-roadmap-title {
            font-size: 14px;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 4px;
        }

        .ts-toeic-roadmap-desc {
            font-size: 13px;
            color: #64748B;
        }

        /* --- TESTIMONIALS MARQUEE --- */
        .ts-toeic-testi-container {
            overflow: hidden;
            margin-top: 40px;
            position: relative;
        }
        
        .ts-toeic-testi-container::before,
        .ts-toeic-testi-container::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 100px;
            z-index: 2;
            pointer-events: none;
        }
        .ts-toeic-testi-container::before {
            left: 0;
            background: linear-gradient(to right, #F8FAFC, transparent);
        }
        .ts-toeic-testi-container::after {
            right: 0;
            background: linear-gradient(to left, #F8FAFC, transparent);
        }

        .ts-toeic-testi-marquee {
            display: flex;
            width: max-content;
            animation: tsToeicMarquee 40s linear infinite;
        }
        
        .ts-toeic-testi-marquee:hover {
            animation-play-state: paused;
        }

        @keyframes tsToeicMarquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .ts-toeic-testi-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            width: 350px;
            margin-right: 24px;
        }

        .ts-toeic-testi-stars {
            color: #F59E0B;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .ts-toeic-testi-quote {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
            flex: 1;
            font-style: italic;
        }

        .ts-toeic-testi-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ts-toeic-testi-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #CBD5E1;
            object-fit: cover;
        }

        .ts-toeic-testi-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #0F172A;
            margin: 0 0 4px 0;
        }

        .ts-toeic-testi-info p {
            font-size: 12px;
            color: #64748B;
            margin: 0;
        }

        .ts-toeic-testi-score {
            margin-left: auto;
            text-align: right;
        }

        .ts-toeic-testi-score h4 {
            font-size: 18px;
            font-weight: 700;
            color: #6366F1;
            margin: 0;
        }

        .ts-toeic-testi-score p {
            font-size: 11px;
            color: #64748B;
            margin: 0;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .ts-toeic-exams-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .ts-toeic-series-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .ts-toeic-roadmap { flex-wrap: wrap; justify-content: center; gap: 32px; }
            .ts-toeic-roadmap::before { display: none; }
            .ts-toeic-roadmap-item { flex: 0 0 calc(33.333% - 32px); }
        }

        @media (max-width: 768px) {
            .ts-toeic-hero {
                flex-direction: column;
                text-align: center;
            }
            .ts-toeic-hero-content {
                max-width: 100%;
            }
            .ts-toeic-exams-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .ts-toeic-series-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .ts-toeic-testi-card { width: 300px; }
        }

        @media (max-width: 480px) {
            .ts-toeic-exams-grid {
                grid-template-columns: 1fr;
            }
            .ts-toeic-categories {
                grid-template-columns: repeat(2, 1fr);
            }
            .ts-toeic-series-grid {
                grid-template-columns: 1fr;
            }
            .ts-toeic-roadmap-item { flex: 0 0 100%; }
        }
    </style>

    <div class="ts-toeic-wrapper">
        <div class="ts-toeic-container">
            <!-- Hero Section -->
            <div class="ts-toeic-hero">
                <div class="ts-toeic-hero-content">
                    <div class="ts-toeic-hero-badge"><i class="fas fa-fire"></i> 500+ đề TOEIC cập nhật mới nhất</div>
                    <h1 class="ts-toeic-hero-title">Đề Thi Thử<br/><span>TOEIC Online</span><br/>Miễn Phí</h1>
                    <p class="ts-toeic-hero-desc">Hơn <strong>500+ đề thi TOEIC</strong> cập nhật liên tục. Chấm điểm tự động, phân tích kết quả chi tiết theo chuẩn ETS.</p>
                    <a href="#exam-list" class="ts-toeic-hero-btn">Bắt đầu thi ngay <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="ts-toeic-hero-image">
                    <!-- Mô phỏng UI bài test -->
                    <div class="ts-mock-header">
                        <div class="ts-mock-badge">Câu 47/200</div>
                        <div class="ts-mock-badge-success"><i class="fas fa-check-circle"></i> Chấm điểm tức thì</div>
                    </div>
                    <div class="ts-mock-question">
                        <div class="ts-mock-question-title"><i class="fas fa-volume-up"></i> Listening — Part 2, Question 47</div>
                        <div class="ts-mock-question-text">Where did you put the conference room key?</div>
                        <div class="ts-mock-option active">(A) It's on my desk.</div>
                        <div class="ts-mock-option">(B) The meeting starts at 3.</div>
                        <div class="ts-mock-option">(C) Yes, I can help.</div>
                    </div>
                </div>
            </div>

            <!-- Exam List Section -->
            <div class="ts-toeic-section-header" id="exam-list" style="text-align: left;">
                <h2>Đề Thi Thử TOEIC</h2>
            </div>
            <div class="ts-toeic-exams-grid">
                <?php
                if (!empty($exams)) {
                    // Group exams
                    $grouped_exams = [];
                    $table_groups = $wpdb->prefix . 'tq_groups';
                    $table_questions = $wpdb->prefix . 'tq_questions';

                    foreach ($exams as $exam) {
                        $title_lower = mb_strtolower($exam->title);
                        $skill = 'full';
                        if (strpos($title_lower, 'nghe') !== false || strpos($title_lower, 'listen') !== false) {
                            $skill = 'nghe';
                        } elseif (strpos($title_lower, 'đọc') !== false || strpos($title_lower, 'doc') !== false || strpos($title_lower, 'read') !== false) {
                            $skill = 'doc';
                        }
                        
                        $base_name = trim(preg_replace('/-(?:\s)*(nghe|đọc|doc|listening|reading|listen|read)\b/iu', '', $exam->title));
                        
                        if (!isset($grouped_exams[$base_name])) {
                            $grouped_exams[$base_name] = [
                                'base_name' => $base_name,
                                'nghe' => null,
                                'doc' => null,
                                'full' => null,
                                'total_q' => 0,
                                'duration' => 0
                            ];
                        }
                        $grouped_exams[$base_name][$skill] = $exam;

                        $total_q = 0;
                        $groups = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table_groups WHERE exam_id = %d", $exam->id));
                        if ($groups) {
                            foreach ($groups as $g) {
                                $total_q += $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_questions WHERE group_id = %d", $g->id));
                            }
                        }
                        
                        // Mock 200 questions if empty for TOEIC
                        if ($total_q == 0) $total_q = 200;
                        
                        $grouped_exams[$base_name]['total_q'] += $total_q;
                        $grouped_exams[$base_name]['duration'] += intval($exam->duration_minutes > 0 ? $exam->duration_minutes : 120);
                    }
                    
                    $exam_count = 0;
                    foreach ($grouped_exams as $g_exam) {
                        $hidden_class = ($exam_count >= 6) ? ' ts-toeic-hidden-exam' : '';
                        $exam_count++;
                        
                        // Max duration for TOEIC if grouped usually is 120 (45 listening + 75 reading)
                        $duration = $g_exam['duration'] > 120 ? 120 : $g_exam['duration'];
                        // Total Q usually 200
                        $tq = $g_exam['total_q'] > 200 ? 200 : $g_exam['total_q'];
                        
                        echo '<div class="ts-toeic-exam-card' . $hidden_class . '">';
                        echo '<div class="ts-toeic-ec-badge">TOEIC Full Test</div>';
                        echo '<h3 class="ts-toeic-ec-title">' . esc_html($g_exam['base_name']) . '</h3>';
                        echo '<div class="ts-toeic-ec-row"><span class="ts-toeic-ec-label"><i class="fas fa-list-ol"></i> Số câu:</span><span class="ts-toeic-ec-val">' . $tq . ' câu</span></div>';
                        echo '<div class="ts-toeic-ec-row"><span class="ts-toeic-ec-label"><i class="far fa-clock"></i> Thời gian:</span><span class="ts-toeic-ec-val">' . $duration . ' phút</span></div>';
                        echo '<div class="ts-toeic-ec-row"><span class="ts-toeic-ec-label"><i class="fas fa-layer-group"></i> Kỹ năng:</span><span class="ts-toeic-ec-val">Nghe • Đọc</span></div>';
                        
                        echo '<div class="ts-toeic-ec-footer">';
                        if ($g_exam['nghe'] && $g_exam['doc']) {
                            echo '<div class="ts-toeic-dual-btns">';
                            echo '<a href="' . esc_url(function_exists('tqp_get_exam_page_url') ? tqp_get_exam_page_url($g_exam['doc']->id) : '#') . '" class="ts-toeic-ec-btn ts-toeic-btn-doc">PHẦN ĐỌC</a>';
                            echo '<a href="' . esc_url(function_exists('tqp_get_exam_page_url') ? tqp_get_exam_page_url($g_exam['nghe']->id) : '#') . '" class="ts-toeic-ec-btn ts-toeic-btn-nghe">PHẦN NGHE</a>';
                            echo '</div>';
                        } else {
                            $target_exam = $g_exam['full'] ?: ($g_exam['nghe'] ?: $g_exam['doc']);
                            $url = function_exists('tqp_get_exam_page_url') ? tqp_get_exam_page_url($target_exam->id) : '#';
                            echo '<a href="' . esc_url($url) . '" class="ts-toeic-ec-btn ts-toeic-btn-single">LÀM BÀI NGAY</a>';
                        }
                        echo '</div>';
                        
                        echo '</div>'; // end card
                    }
                } else {
                    echo '<p>Chưa có đề thi nào.</p>';
                }
                ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="javascript:void(0);" id="ts-toeic-view-all-btn" style="display: inline-block; background: #EEF2FF; color: #6366F1; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">Xem tất cả <i class="fas fa-arrow-right"></i></a>
                <a href="javascript:void(0);" id="ts-toeic-collapse-btn" style="display: none; background: #F1F5F9; color: #64748B; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;"><i class="fas fa-arrow-left"></i> Thu gọn</a>
            </div>

            <!-- Categories Section -->
            <div class="ts-toeic-section-header" style="margin-top: 60px;">
                <h2>Chọn Đề Thi TOEIC Phù Hợp</h2>
                <p>Đa dạng loại đề, phù hợp với mọi mục tiêu và trình độ</p>
            </div>
            <div class="ts-toeic-categories">
                <?php
                $cats = [
                    ['name' => 'TOEIC Full Test', 'desc' => '120 đề', 'icon' => 'fa-book-open', 'bg' => '#EEF2FF', 'color' => '#6366F1'],
                    ['name' => 'TOEIC Listening', 'desc' => '85 đề', 'icon' => 'fa-headphones', 'bg' => '#F0FDF4', 'color' => '#10B981'],
                    ['name' => 'TOEIC Reading', 'desc' => '90 đề', 'icon' => 'fa-file-alt', 'bg' => '#F5F3FF', 'color' => '#8B5CF6'],
                    ['name' => 'TOEIC Mini Test', 'desc' => '60 đề', 'icon' => 'fa-bolt', 'bg' => '#FFFBEB', 'color' => '#F59E0B'],
                    ['name' => 'TOEIC ETS', 'desc' => '45 đề', 'icon' => 'fa-medal', 'bg' => '#ECFDF5', 'color' => '#059669'],
                    ['name' => 'TOEIC New Format', 'desc' => '38 đề', 'icon' => 'fa-globe', 'bg' => '#FEF2F2', 'color' => '#EF4444'],
                ];
                foreach ($cats as $cat) {
                    echo '<a href="#" class="ts-toeic-category-card" style="background-color: ' . $cat['bg'] . ';">';
                    echo '<div class="ts-toeic-category-icon" style="background-color: #fff; color: ' . $cat['color'] . '; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">';
                    echo '<i class="fas ' . $cat['icon'] . '"></i>';
                    echo '</div>';
                    echo '<h3>' . esc_html($cat['name']) . '</h3>';
                    echo '<p>' . esc_html($cat['desc']) . '</p>';
                    echo '</a>';
                }
                ?>
            </div>
            
            <!-- Series Section (ETS) -->
            <div class="ts-toeic-section-header" style="text-align: left; margin-top: 80px;">
                <h2>Bộ Đề ETS Chính Hãng</h2>
            </div>
            <div class="ts-toeic-series-grid">
                <?php
                $series = [
                    ['name' => 'ETS 2025', 'bg' => 'linear-gradient(135deg, #4F46E5, #7C3AED)'],
                    ['name' => 'ETS 2024', 'bg' => 'linear-gradient(135deg, #3B82F6, #2563EB)'],
                    ['name' => 'ETS 2023', 'bg' => 'linear-gradient(135deg, #A855F7, #9333EA)'],
                    ['name' => 'ETS 2022', 'bg' => 'linear-gradient(135deg, #EC4899, #DB2777)'],
                    ['name' => 'ETS 2021', 'bg' => 'linear-gradient(135deg, #F43F5E, #E11D48)']
                ];
                foreach ($series as $s) {
                    echo '<a href="#" class="ts-toeic-series-card" style="background: ' . $s['bg'] . ';">';
                    echo '<div class="ts-toeic-series-icon"><i class="fas fa-book-journal-whills"></i></div>';
                    echo '<h3 class="ts-toeic-series-title">' . $s['name'] . '</h3>';
                    echo '<span class="ts-toeic-series-btn">Luyện tập ngay</span>';
                    echo '</a>';
                }
                ?>
            </div>

            <!-- Roadmap Section -->
            <div class="ts-toeic-section-header" style="margin-top: 100px;">
                <h2>Lộ Trình Chinh Phục TOEIC</h2>
                <p>Từng bước tiến, từng mốc điểm — bạn không cô đơn trên hành trình này</p>
            </div>
            <div class="ts-toeic-roadmap">
                <?php
                $roadmaps = [
                    ['score' => '450+', 'title' => 'Nền tảng cơ bản', 'desc' => '1 tháng'],
                    ['score' => '550+', 'title' => 'Luyện Part cơ bản', 'desc' => '2 tháng'],
                    ['score' => '650+', 'title' => 'Thành thạo từng Part', 'desc' => '3 tháng'],
                    ['score' => '750+', 'title' => 'Tốc độ và chiến lược', 'desc' => '4 tháng'],
                    ['score' => '850+', 'title' => 'Nâng cao toàn diện', 'desc' => '5 tháng'],
                    ['score' => '900+', 'title' => 'Chinh phục đỉnh cao', 'desc' => '6 tháng'],
                ];
                foreach ($roadmaps as $r) {
                    echo '<div class="ts-toeic-roadmap-item">';
                    echo '<div class="ts-toeic-roadmap-circle">' . $r['score'] . '</div>';
                    echo '<div class="ts-toeic-roadmap-title">' . $r['title'] . '</div>';
                    echo '<div class="ts-toeic-roadmap-desc">' . $r['desc'] . '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Testimonials Section -->
            <div class="ts-toeic-section-header" style="margin-top: 100px;">
                <h2>Học Viên Nói Gì Về Chúng Tôi</h2>
                <p>Hàng nghìn học viên đã cải thiện điểm TOEIC nhờ nền tảng của chúng tôi</p>
            </div>
            <div class="ts-toeic-testi-container">
                <div class="ts-toeic-testi-marquee">
                <?php
                $testis = [
                    ['name' => 'Nguyễn Minh Anh', 'role' => 'Sinh viên', 'score' => '905', 'quote' => '"Sau 4 tháng luyện tập với hơn 60 đề full test, mình đã đạt 905 TOEIC. Hệ thống phân tích điểm yếu rất chi tiết, giúp mình tập trung đúng chỗ."', 'avatar' => 'https://i.pravatar.cc/150?u=1'],
                    ['name' => 'Trần Quốc Hùng', 'role' => 'Kỹ sư phần mềm', 'score' => '860', 'quote' => '"Giao diện làm bài rất sát với thi thật. Phần timer countdown và phân tích kết quả sau khi thi giúp mình cải thiện đáng kể."', 'avatar' => 'https://i.pravatar.cc/150?u=2'],
                    ['name' => 'Lê Thị Hương', 'role' => 'Nhân viên Marketing', 'score' => '780', 'quote' => '"Mình từ 550 lên 780 sau 3 tháng. Kho đề ETS cập nhật đầy đủ nhất, chấm điểm tức thì rất tiện lợi cho người bận rộn như mình."', 'avatar' => 'https://i.pravatar.cc/150?u=3'],
                    ['name' => 'Phạm Hoàng Khang', 'role' => 'Học sinh THPT', 'score' => '650', 'quote' => '"Chưa bao giờ học TOEIC lại thú vị đến vậy. Website không có quảng cáo, tốc độ tải rất nhanh. Rất khuyên dùng cho các bạn mới bắt đầu."', 'avatar' => 'https://i.pravatar.cc/150?u=4'],
                    ['name' => 'Vũ Thị Thanh', 'role' => 'Nhân viên Ngân hàng', 'score' => '820', 'quote' => '"Từ lúc biết nền tảng này, mình bỏ luôn sách giấy. Luyện đề trên điện thoại lúc đi xe buýt rất mượt, giúp tận dụng tối đa thời gian."', 'avatar' => 'https://i.pravatar.cc/150?u=5'],
                    ['name' => 'Đỗ Văn Thành', 'role' => 'Freelancer', 'score' => '895', 'quote' => '"Phần dịch chi tiết từng câu trong bài Reading là cứu cánh thực sự của mình. Lượng kiến thức học được sau mỗi lỗi sai là rất lớn."', 'avatar' => 'https://i.pravatar.cc/150?u=6'],
                    ['name' => 'Hồ Ngọc Mai', 'role' => 'Kế toán', 'score' => '710', 'quote' => '"Nhờ lộ trình gợi ý tự động của hệ thống, mình biết nên ôn phần nào trước. Rất tiết kiệm chi phí so với việc đi học trung tâm."', 'avatar' => 'https://i.pravatar.cc/150?u=7'],
                    ['name' => 'Đặng Hải Yến', 'role' => 'Sinh viên Ngoại ngữ', 'score' => '950', 'quote' => '"Mock test ở đây độ khó tương đương 99% thi thật ở IIG. Điểm mock test của mình lúc nào cũng xoay quanh 920-960, đi thi đạt 950 luôn."', 'avatar' => 'https://i.pravatar.cc/150?u=8']
                ];
                
                // Duplicate mảng để tạo hiệu ứng scroll mượt mà không bị ngắt quãng
                $all_testis = array_merge($testis, $testis);
                
                foreach ($all_testis as $t) {
                    echo '<div class="ts-toeic-testi-card">';
                    echo '<div class="ts-toeic-testi-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>';
                    echo '<div class="ts-toeic-testi-quote">' . $t['quote'] . '</div>';
                    echo '<div class="ts-toeic-testi-author">';
                    echo '<img class="ts-toeic-testi-avatar" src="' . $t['avatar'] . '" alt="' . $t['name'] . '">';
                    echo '<div class="ts-toeic-testi-info">';
                    echo '<h4>' . $t['name'] . '</h4>';
                    echo '<p>' . $t['role'] . '</p>';
                    echo '</div>';
                    echo '<div class="ts-toeic-testi-score">';
                    echo '<h4>' . $t['score'] . '</h4>';
                    echo '<p>TOEIC Score</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                </div>
            </div>

        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var viewAllBtn = document.getElementById("ts-toeic-view-all-btn");
        var collapseBtn = document.getElementById("ts-toeic-collapse-btn");
        var hiddenExams = document.querySelectorAll(".ts-toeic-hidden-exam");

        if(viewAllBtn && collapseBtn) {
            viewAllBtn.addEventListener("click", function(e) {
                e.preventDefault();
                hiddenExams.forEach(function(el) { el.style.setProperty('display', 'flex', 'important'); });
                viewAllBtn.style.display = "none";
                collapseBtn.style.display = "inline";
            });
            collapseBtn.addEventListener("click", function(e) {
                e.preventDefault();
                hiddenExams.forEach(function(el) { el.style.setProperty('display', 'none', 'important'); });
                collapseBtn.style.display = "none";
                viewAllBtn.style.display = "inline";
                document.getElementById("exam-list").scrollIntoView({ behavior: 'smooth' });
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

<?php
if (!defined('ABSPATH')) exit;

add_shortcode('aptis_exam', 'tqp_render_aptis_landing');

function tqp_render_aptis_landing($atts) {
    global $wpdb;
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    $exams = $wpdb->get_results("SELECT * FROM $table_exams WHERE title LIKE '%Aptis%' ORDER BY id ASC");
    
    $grouped_exams = [];
    if (!empty($exams)) {
        foreach ($exams as $exam) {
            $title_lower = mb_strtolower($exam->title);
            $skill = '';
            if (strpos($title_lower, 'listen') !== false || strpos($title_lower, 'nghe') !== false) {
                $skill = 'listening';
            } elseif (strpos($title_lower, 'read') !== false || strpos($title_lower, 'đọc') !== false || strpos($title_lower, 'doc') !== false) {
                $skill = 'reading';
            } elseif (strpos($title_lower, 'writ') !== false || strpos($title_lower, 'viết') !== false || strpos($title_lower, 'viet') !== false) {
                $skill = 'writing';
            } elseif (strpos($title_lower, 'speak') !== false || strpos($title_lower, 'nói') !== false || strpos($title_lower, 'noi') !== false) {
                $skill = 'speaking';
            } elseif (strpos($title_lower, 'gram') !== false || strpos($title_lower, 'ngữ pháp') !== false || strpos($title_lower, 'ngu phap') !== false || strpos($title_lower, 'từ vựng') !== false) {
                $skill = 'grammar';
            }
            
            if (empty($skill)) continue; 
            
            $test_number = '';
            if (preg_match('/(?:đề|test)\s*(\d+)/iu', $exam->title, $matches)) {
                $test_number = $matches[1];
            } elseif (preg_match('/(\d+)/', $exam->title, $matches)) {
                $test_number = $matches[1];
            }

            if ($test_number !== '') {
                $base_name = 'Test ' . $test_number;
            } else {
                $base_name = trim(preg_replace('/aptis|practice|listening|reading|writing|writting|speaking|grammar|ngữ pháp|nghe|đọc|nói|viết|-/iu', '', $exam->title));
                if (empty($base_name)) $base_name = 'Test Unknown';
            }
            
            if (!isset($grouped_exams[$base_name])) {
                $grouped_exams[$base_name] = [
                    'base_name' => $base_name,
                    'listening' => null,
                    'reading' => null,
                    'writing' => null,
                    'speaking' => null,
                    'grammar' => null
                ];
            }
            $grouped_exams[$base_name][$skill] = $exam;
        }
    }
    
    uksort($grouped_exams, 'strnatcasecmp');

    wp_enqueue_style('tqp-style');
    wp_enqueue_style('tqp-font-inter');

    ob_start();
    ?>
    <style>
        :root {
            --ap-blue: #2563EB;
            --ap-blue-light: #EFF6FF;
            --ap-green: #10B981;
            --ap-green-light: #ECFDF5;
            --ap-orange: #F97316;
            --ap-orange-light: #FFF7ED;
            --ap-red: #EF4444;
            --ap-red-light: #FEF2F2;
            --ap-purple: #8B5CF6;
            --ap-purple-light: #F5F3FF;
            --ap-bg: #F8FAFC;
            --ap-text: #1E293B;
            --ap-text-muted: #64748B;
            --ap-border: #E2E8F0;
        }

        .ts-aptis-wrapper {
            font-family: 'Inter', sans-serif;
            background-color: var(--ap-bg);
            color: var(--ap-text);
            line-height: 1.6;
            padding: 40px 20px 80px;
            box-sizing: border-box;
        }
        .ts-aptis-wrapper *, .ts-aptis-wrapper *::before, .ts-aptis-wrapper *::after {
            box-sizing: inherit;
        }

        .ts-ap-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* --- Stats Section --- */
        .ts-ap-stats-wrapper {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 60px;
        }
        .ts-ap-stat-item {
            display: flex;
            align-items: center;
            gap: 16px;
            background: #fff;
            padding: 20px 16px;
            border-radius: 16px;
            border: 1px solid var(--ap-border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .ts-ap-stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .ts-ap-stat-num {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .ts-ap-stat-label {
            font-size: 14px;
            color: var(--ap-text-muted);
            margin-top: 4px;
        }

        /* --- Utilities --- */
        .ts-ap-text-center { text-align: center; }
        .ts-ap-flex { display: flex; }
        .ts-ap-grid { display: grid; }
        .ts-ap-items-center { align-items: center; }
        .ts-ap-justify-center { justify-content: center; }
        .ts-ap-gap-2 { gap: 8px; }
        .ts-ap-gap-4 { gap: 16px; }
        .ts-ap-gap-6 { gap: 24px; }
        .ts-ap-mb-2 { margin-bottom: 8px; }
        .ts-ap-mb-4 { margin-bottom: 16px; }
        .ts-ap-mb-6 { margin-bottom: 24px; }
        .ts-ap-mb-8 { margin-bottom: 32px; }
        .ts-ap-mb-12 { margin-bottom: 48px; }

        .ts-ap-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 24px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        .ts-ap-btn-primary {
            background-color: var(--ap-blue);
            color: #fff;
        }
        .ts-ap-btn-primary:hover {
            background-color: #1d4ed8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .ts-ap-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }
        
        /* --- Hero Section --- */
        .ts-ap-hero {
            text-align: center;
            max-width: 900px;
            margin: 0 auto 30px;
        }
        .ts-ap-hero-badge {
            background-color: var(--ap-blue-light);
            color: var(--ap-blue);
            margin-bottom: 16px;
        }
        .ts-ap-hero-badge {
            background-color: var(--ap-blue-light);
            color: var(--ap-blue);
            margin-bottom: 16px;
        }
        .ts-ap-hero-title {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
            color: #0f172a;
        }
        .ts-ap-hero-title span {
            color: var(--ap-blue);
        }
        .ts-ap-hero-desc {
            font-size: 16px;
            color: var(--ap-text-muted);
            margin-bottom: 24px;
        }

        .ts-ap-tabs-wrapper {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .ts-ap-tab {
            padding: 10px 24px;
            border-radius: 99px;
            background-color: var(--ap-blue-light);
            color: var(--ap-blue);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ts-ap-tab.active {
            background-color: var(--ap-blue);
            color: #fff;
            box-shadow: 0 4px 12px rgba(37,99,235,0.2);
        }

        .ts-ap-features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        .ts-ap-feature-item {
            padding: 12px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 14px;
            text-align: left;
            border: 1px solid transparent;
        }
        /* Feature Colors */
        .ts-ap-feat-1 { background: var(--ap-green-light); color: var(--ap-green); border-color: rgba(16,185,129,0.2); }
        .ts-ap-feat-2 { background: var(--ap-blue-light); color: var(--ap-blue); border-color: rgba(37,99,235,0.2); }
        .ts-ap-feat-3 { background: var(--ap-purple-light); color: var(--ap-purple); border-color: rgba(139,92,246,0.2); }
        .ts-ap-feat-4 { background: var(--ap-orange-light); color: var(--ap-orange); border-color: rgba(249,115,22,0.2); }

        .ts-ap-feature-icon {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: #fff;
        }

        /* --- Section Title --- */
        .ts-ap-section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .ts-ap-section-title {
            font-size: 32px;
            font-weight: 700;
            margin-top: 12px;
            margin-bottom: 8px;
        }
        .ts-ap-section-desc {
            color: var(--ap-text-muted);
            font-size: 15px;
        }

        /* --- Test Grid --- */
        .ts-ap-test-grid-wrapper {
            margin-bottom: 80px;
        }
        .ts-ap-test-grid-scroll {
            padding-bottom: 16px;
        }
        .ts-ap-test-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .ts-ap-test-card {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid var(--ap-border);
            transition: transform 0.3s;
        }
        .ts-ap-test-card.hidden-card {
            display: none;
        }
        .ts-ap-btn-outline-blue {
            border: 1px solid var(--ap-blue);
            color: var(--ap-blue);
            background: transparent;
        }
        .ts-ap-btn-outline-blue:hover {
            background: var(--ap-blue-light);
        }
        .ts-ap-test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }
        .ts-ap-tc-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .ts-ap-tc-icon {
            width: 40px; height: 40px;
            background: var(--ap-blue-light); color: var(--ap-blue);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .ts-ap-tc-title {
            font-size: 18px; font-weight: 700;
        }
        .ts-ap-tc-meta {
            display: flex; gap: 16px; font-size: 13px; color: var(--ap-text-muted); margin-bottom: 24px;
        }
        .ts-ap-tc-skills {
            display: flex; flex-direction: column; gap: 8px;
        }
        .ts-ap-skill-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px; font-weight: 600;
            transition: all 0.2s;
        }
        .ts-ap-skill-row[href]:hover {
            opacity: 0.85;
            transform: translateX(4px);
        }
        
        /* Skill Colors */
        .skill-listening { background: var(--ap-blue-light); color: var(--ap-blue); }
        .skill-reading { background: var(--ap-green-light); color: var(--ap-green); }
        .skill-writing { background: var(--ap-orange-light); color: var(--ap-orange); }
        .skill-speaking { background: var(--ap-red-light); color: var(--ap-red); }
        .skill-grammar { background: var(--ap-purple-light); color: var(--ap-purple); }

        /* --- Skills Section --- */
        .ts-ap-skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 80px;
        }
        .ts-ap-skill-card {
            background: #fff;
            border-radius: 20px;
            padding: 30px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid var(--ap-border);
        }
        .ts-ap-sc-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 20px;
            color: #fff;
        }
        .ts-ap-sc-title {
            font-size: 20px; font-weight: 700; margin-bottom: 16px;
        }
        .ts-ap-sc-list {
            list-style: none; padding: 0; margin: 0 0 24px 0;
            font-size: 14px; color: var(--ap-text-muted);
        }
        .ts-ap-sc-list li {
            margin-bottom: 8px; position: relative; padding-left: 16px;
        }
        .ts-ap-sc-list li::before {
            content: ''; position: absolute; left: 0; top: 6px;
            width: 6px; height: 6px; border-radius: 50%;
        }
        .ts-ap-sc-btn {
            display: block; text-align: center; padding: 12px; border-radius: 8px;
            font-weight: 600; text-decoration: none; transition: 0.2s; font-size: 14px;
        }

        /* Skill specific styles */
        .sc-listening .ts-ap-sc-icon { background: var(--ap-blue); }
        .sc-listening .ts-ap-sc-list li::before { background: var(--ap-blue); }
        .sc-listening .ts-ap-sc-btn { background: var(--ap-blue-light); color: var(--ap-blue); }
        
        .sc-reading .ts-ap-sc-icon { background: var(--ap-green); }
        .sc-reading .ts-ap-sc-list li::before { background: var(--ap-green); }
        .sc-reading .ts-ap-sc-btn { background: var(--ap-green-light); color: var(--ap-green); }

        .sc-writing .ts-ap-sc-icon { background: var(--ap-orange); }
        .sc-writing .ts-ap-sc-list li::before { background: var(--ap-orange); }
        .sc-writing .ts-ap-sc-btn { background: var(--ap-orange-light); color: var(--ap-orange); }

        .sc-speaking .ts-ap-sc-icon { background: var(--ap-red); }
        .sc-speaking .ts-ap-sc-list li::before { background: var(--ap-red); }
        .sc-speaking .ts-ap-sc-btn { background: var(--ap-red-light); color: var(--ap-red); }

        .sc-grammar .ts-ap-sc-icon { background: var(--ap-purple); }
        .sc-grammar .ts-ap-sc-list li::before { background: var(--ap-purple); }
        .sc-grammar .ts-ap-sc-btn { background: var(--ap-purple-light); color: var(--ap-purple); }

        /* --- Levels Section --- */
        .ts-ap-level-tabs {
            display: flex; justify-content: center; gap: 12px; margin-bottom: 40px;
        }
        .ts-ap-level-tab {
            padding: 10px 20px; border-radius: 99px; font-weight: 600; font-size: 14px;
            background: #fff; border: 1px solid var(--ap-border); color: var(--ap-text-muted);
            cursor: pointer; transition: 0.2s;
        }
        .ts-ap-level-tab.active-green { background: var(--ap-green); color: #fff; border-color: var(--ap-green); box-shadow: 0 4px 12px rgba(16,185,129,0.2); }
        .ts-ap-level-tab.active-blue { background: var(--ap-blue); color: #fff; border-color: var(--ap-blue); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .ts-ap-level-tab.active-purple { background: var(--ap-purple); color: #fff; border-color: var(--ap-purple); box-shadow: 0 4px 12px rgba(139,92,246,0.2); }

        .ts-ap-levels-content {
            background: var(--ap-green-light);
            border: 1px solid rgba(16,185,129,0.2);
            border-radius: 24px; padding: 40px; margin-bottom: 80px;
            transition: all 0.3s;
        }
        .ts-ap-levels-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px;
        }
        .ts-ap-level-card {
            background: #fff; border-radius: 16px; padding: 20px;
        }
        .ts-ap-lc-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; background: var(--ap-green); color: #fff;
            border-radius: 8px; font-size: 12px; font-weight: 600; margin-bottom: 12px;
        }
        .ts-ap-lc-title { font-weight: 700; font-size: 16px; margin-bottom: 12px; }
        .ts-ap-lc-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--ap-text-muted); margin-bottom: 20px; }
        .ts-ap-lc-btn {
            display: block; text-align: center; padding: 10px; border-radius: 8px;
            background: var(--ap-green-light); color: var(--ap-green); font-weight: 600; text-decoration: none; font-size: 14px;
        }

        /* --- Path Section --- */
        .ts-ap-path-wrap {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
            position: relative; margin-bottom: 40px;
        }
        /* Connector line */
        .ts-ap-path-wrap::before {
            content: ''; position: absolute; top: 40px; left: 10%; right: 10%; height: 2px;
            background: var(--ap-border); z-index: 0;
        }
        .ts-ap-path-item {
            background: #fff; border-radius: 20px; padding: 30px 20px;
            text-align: center; border: 1px solid var(--ap-border);
            position: relative; z-index: 1; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .ts-ap-pi-icon {
            width: 60px; height: 60px; border-radius: 16px; margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center; font-size: 28px; color: #fff;
        }
        .ts-ap-pi-num {
            width: 24px; height: 24px; border-radius: 50%; color: #fff; font-size: 12px; font-weight: bold;
            display: flex; align-items: center; justify-content: center; margin: -32px auto 20px; position: relative; z-index: 2;
        }
        .ts-ap-pi-title { font-weight: 700; font-size: 16px; margin-bottom: 8px; }
        .ts-ap-pi-desc { font-size: 13px; color: var(--ap-text-muted); margin-bottom: 16px; min-height: 40px;}
        .ts-ap-pi-time { font-size: 12px; color: var(--ap-text-muted); display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; background: var(--ap-bg); border-radius: 99px;}

        /* --- Bottom CTA --- */
        .ts-ap-cta {
            background: linear-gradient(135deg, var(--ap-blue), #3b82f6);
            border-radius: 24px; padding: 60px 40px; text-align: center; color: #fff;
        }
        .ts-ap-cta-title { font-size: 36px; font-weight: 800; margin-bottom: 16px; color: #fff;}
        .ts-ap-cta-desc { font-size: 16px; margin-bottom: 32px; opacity: 0.9; }
        .ts-ap-cta-btns { display: flex; justify-content: center; gap: 16px; }
        .ts-ap-cta-btn-white { background: #fff; color: var(--ap-blue); }
        .ts-ap-cta-btn-outline { background: transparent; border: 1px solid #fff; color: #fff; }

        @media (max-width: 992px) {
            .ts-ap-path-wrap { grid-template-columns: repeat(2, 1fr); }
            .ts-ap-path-wrap::before { display: none; }
            .ts-ap-test-grid { grid-template-columns: repeat(2, 1fr); }
            
            .ts-ap-stats-wrapper { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .ts-ap-stat-item { flex-direction: column; text-align: center; justify-content: center; min-width: 0; padding: 12px; gap: 8px; }
            .ts-ap-stat-num { font-size: 20px; }
            .ts-ap-stat-label { font-size: 11px; }
            .ts-ap-stat-icon { width: 40px; height: 40px; font-size: 18px; }
        }
        @media (max-width: 768px) {
            .ts-ap-features-grid { grid-template-columns: 1fr; }
            .ts-ap-hero-title { font-size: 32px; }
            .ts-ap-test-grid { grid-template-columns: 1fr; }
            .ts-ap-test-grid-wrapper { padding: 16px; }
        }
    </style>

    <div class="ts-aptis-wrapper">
        <div class="ts-ap-container">
            
            <!-- Hero Section -->
            <div class="ts-ap-hero" style="margin-bottom: 60px;">
                <span class="ts-ap-badge ts-ap-hero-badge">• Chuẩn cấu trúc ESOL quốc tế · Miễn phí 100%</span>
                <h1 class="ts-ap-hero-title">Thi thử <span>APTIS ESOL</span> Online — Miễn Phí</h1>
                <p class="ts-ap-hero-desc">Hệ thống đề luyện thi APTIS cập nhật theo chuẩn quốc tế, kết hợp video hướng dẫn từ giảng viên chuyên nghiệp. Chấm điểm tự động theo từng kỹ năng: Grammar & Vocabulary, Listening, Reading, Writing và Speaking — giúp bạn đánh giá chính xác trình độ và nhanh chóng đạt mục tiêu B1, B2.</p>
                
                <div class="ts-ap-tabs-wrapper">
                    <button class="ts-ap-tab active">📘 APTIS GENERAL</button>
                    <button class="ts-ap-tab">🏅 APTIS ADVANCED</button>
                    <button class="ts-ap-tab">🎒 APTIS FOR TEENS</button>
                </div>

                <div class="ts-ap-features-grid">
                    <div class="ts-ap-feature-item ts-ap-feat-1">
                        <div class="ts-ap-feature-icon" style="color: var(--ap-green);">✓</div>
                        Chuẩn đề thi – được kiểm chứng
                    </div>
                    <div class="ts-ap-feature-item ts-ap-feat-2">
                        <div class="ts-ap-feature-icon" style="color: var(--ap-blue);">↗</div>
                        Lộ trình rõ ràng cho người mới
                    </div>
                    <div class="ts-ap-feature-item ts-ap-feat-3">
                        <div class="ts-ap-feature-icon" style="color: var(--ap-purple);">☰</div>
                        Luyện 3 bộ đề → nâng cao trình độ
                    </div>
                    <div class="ts-ap-feature-item ts-ap-feat-4">
                        <div class="ts-ap-feature-icon" style="color: var(--ap-orange);">📊</div>
                        Học online – xem kết quả tức thì
                    </div>
                </div>

                <a href="#test-grid" class="ts-ap-btn ts-ap-btn-primary" style="font-size: 16px; padding: 14px 32px; margin-top: 24px;">▶ Bắt đầu luyện đề ngay</a>
            </div>

            <!-- Stats Section -->
            <div class="ts-ap-stats-wrapper">
                <div class="ts-ap-stat-item">
                    <div class="ts-ap-stat-icon" style="background: var(--ap-blue-light); color: var(--ap-blue);">📖</div>
                    <div>
                        <div class="ts-ap-stat-num"><span class="ts-ap-counter" data-target="500">0</span>+</div>
                        <div class="ts-ap-stat-label">Đề thi thử</div>
                    </div>
                </div>
                <div class="ts-ap-stat-item">
                    <div class="ts-ap-stat-icon" style="background: var(--ap-green-light); color: var(--ap-green);">👥</div>
                    <div>
                        <div class="ts-ap-stat-num"><span class="ts-ap-counter" data-target="10000">0</span>+</div>
                        <div class="ts-ap-stat-label">Học viên</div>
                    </div>
                </div>
                <div class="ts-ap-stat-item">
                    <div class="ts-ap-stat-icon" style="background: var(--ap-orange-light); color: var(--ap-orange);">📈</div>
                    <div>
                        <div class="ts-ap-stat-num"><span class="ts-ap-counter" data-target="50000">0</span>+</div>
                        <div class="ts-ap-stat-label">Lượt thi</div>
                    </div>
                </div>
                <div class="ts-ap-stat-item">
                    <div class="ts-ap-stat-icon" style="background: var(--ap-purple-light); color: var(--ap-purple);">⭐</div>
                    <div>
                        <div class="ts-ap-stat-num"><span class="ts-ap-counter" data-target="95">0</span>%</div>
                        <div class="ts-ap-stat-label">Đánh giá tích cực</div>
                    </div>
                </div>
            </div>

            <div id="test-grid" class="ts-ap-section-header" style="text-align: left;">
                <h2 class="ts-ap-section-title" style="margin-top: 0;">Đề Thi Thử Aptis</h2>
                <p class="ts-ap-section-desc" style="margin-bottom: 0;">Mô phỏng đúng cấu trúc thi thật, cập nhật liên tục</p>
            </div>

            <div class="ts-ap-test-grid-wrapper">
                <div class="ts-ap-test-grid-scroll">
                    <div class="ts-ap-test-grid">
                        <?php if (!empty($grouped_exams)): ?>
                            <?php $i = 1; foreach ($grouped_exams as $base_name => $skills): ?>
                            <div class="ts-ap-test-card <?php echo $i > 6 ? 'hidden-card' : ''; ?>">
                                <div class="ts-ap-tc-header">
                                    <div class="ts-ap-tc-icon">📘</div>
                                    <div class="ts-ap-tc-title">Aptis Practice <?php echo esc_html($base_name); ?></div>
                                </div>
                                <div class="ts-ap-tc-skills" style="margin-top: 16px;">
                                    <?php if (!empty($skills['listening'])): ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['listening']->id)); ?>" style="text-decoration: none;" class="ts-ap-skill-row skill-listening"><span>🎧 Listening</span> <span>›</span></a>
                                    <?php else: ?>
                                        <div class="ts-ap-skill-row skill-listening" style="opacity: 0.5; cursor: not-allowed; filter: grayscale(1);"><span>🎧 Listening</span> <span>›</span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skills['reading'])): ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['reading']->id)); ?>" style="text-decoration: none;" class="ts-ap-skill-row skill-reading"><span>📖 Reading</span> <span>›</span></a>
                                    <?php else: ?>
                                        <div class="ts-ap-skill-row skill-reading" style="opacity: 0.5; cursor: not-allowed; filter: grayscale(1);"><span>📖 Reading</span> <span>›</span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skills['writing'])): ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['writing']->id)); ?>" style="text-decoration: none;" class="ts-ap-skill-row skill-writing"><span>✍️ Writing</span> <span>›</span></a>
                                    <?php else: ?>
                                        <div class="ts-ap-skill-row skill-writing" style="opacity: 0.5; cursor: not-allowed; filter: grayscale(1);"><span>✍️ Writing</span> <span>›</span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skills['speaking'])): ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['speaking']->id)); ?>" style="text-decoration: none;" class="ts-ap-skill-row skill-speaking"><span>🎙️ Speaking</span> <span>›</span></a>
                                    <?php else: ?>
                                        <div class="ts-ap-skill-row skill-speaking" style="opacity: 0.5; cursor: not-allowed; filter: grayscale(1);"><span>🎙️ Speaking</span> <span>›</span></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($skills['grammar'])): ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['grammar']->id)); ?>" style="text-decoration: none;" class="ts-ap-skill-row skill-grammar"><span>🧠 Grammar</span> <span>›</span></a>
                                    <?php else: ?>
                                        <div class="ts-ap-skill-row skill-grammar" style="opacity: 0.5; cursor: not-allowed; filter: grayscale(1);"><span>🧠 Grammar</span> <span>›</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $i++; endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; text-align: center; color: var(--ap-text-muted); padding: 40px; font-style: italic;">
                                Chưa có đề thi nào trong hệ thống.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="javascript:void(0);" id="ts-ap-view-all-tests" style="display: inline-block; background: #EEF2FF; color: #4361EE; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">Xem tất cả <i class="fas fa-arrow-right"></i></a>
                <a href="javascript:void(0);" id="ts-ap-collapse-tests" style="display: none; background: #F1F5F9; color: #64748B; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;"><i class="fas fa-arrow-left"></i> Thu gọn</a>
            </div>

            <!-- Skills Section -->
            <div class="ts-ap-section-header">
                <h2 class="ts-ap-section-title">Kỹ Năng Aptis</h2>
                <p class="ts-ap-section-desc">Luyện từng kỹ năng chuyên biệt để đạt điểm tối đa trong kỳ thi</p>
            </div>

            <div class="ts-ap-skills-grid">
                <div class="ts-ap-skill-card sc-listening">
                    <div class="ts-ap-sc-icon">🎧</div>
                    <div class="ts-ap-sc-title">Listening</div>
                    <ul class="ts-ap-sc-list">
                        <li>Listening Part 1</li>
                        <li>Listening Part 2</li>
                        <li>Listening Part 3</li>
                        <li>Listening Part 4</li>
                    </ul>
                    <a href="#" class="ts-ap-sc-btn">Luyện ngay →</a>
                </div>
                <div class="ts-ap-skill-card sc-reading">
                    <div class="ts-ap-sc-icon">📖</div>
                    <div class="ts-ap-sc-title">Reading</div>
                    <ul class="ts-ap-sc-list">
                        <li>Reading Part 1</li>
                        <li>Reading Part 2</li>
                        <li>Reading Part 3</li>
                        <li>Reading Part 4</li>
                    </ul>
                    <a href="#" class="ts-ap-sc-btn">Luyện ngay →</a>
                </div>
                <div class="ts-ap-skill-card sc-writing">
                    <div class="ts-ap-sc-icon">✍️</div>
                    <div class="ts-ap-sc-title">Writing</div>
                    <ul class="ts-ap-sc-list">
                        <li>Writing Part 1</li>
                        <li>Writing Part 2</li>
                        <li>Writing Part 3</li>
                        <li>Writing Part 4</li>
                    </ul>
                    <a href="#" class="ts-ap-sc-btn">Luyện ngay →</a>
                </div>
                <div class="ts-ap-skill-card sc-speaking">
                    <div class="ts-ap-sc-icon">🎙️</div>
                    <div class="ts-ap-sc-title">Speaking</div>
                    <ul class="ts-ap-sc-list">
                        <li>Speaking Part 1</li>
                        <li>Speaking Part 2</li>
                        <li>Speaking Part 3</li>
                        <li>Speaking Part 4</li>
                    </ul>
                    <a href="#" class="ts-ap-sc-btn">Luyện ngay →</a>
                </div>
                <div class="ts-ap-skill-card sc-grammar">
                    <div class="ts-ap-sc-icon">🧠</div>
                    <div class="ts-ap-sc-title">Grammar & Vocabulary</div>
                    <ul class="ts-ap-sc-list">
                        <li>Grammar Practice</li>
                        <li>Vocabulary Practice</li>
                        <li>Mixed Practice</li>
                    </ul>
                    <a href="#" class="ts-ap-sc-btn">Luyện ngay →</a>
                </div>
            </div>

            <!-- Levels Section -->
            <div class="ts-ap-section-header">
                <h2 class="ts-ap-section-title">Đề Thi Theo Trình Độ</h2>
                <p class="ts-ap-section-desc">Chọn cấp độ phù hợp với trình độ hiện tại của bạn</p>
            </div>

            <div class="ts-ap-level-tabs">
                <button class="ts-ap-level-tab active-green" onclick="switchLevel('green', this)">Beginner (A1–A2)</button>
                <button class="ts-ap-level-tab" onclick="switchLevel('blue', this)">Intermediate (B1–B2)</button>
                <button class="ts-ap-level-tab" onclick="switchLevel('purple', this)">Advanced (C1–C2)</button>
            </div>

            <div class="ts-ap-levels-content" id="level-content-box">
                <div class="ts-ap-levels-grid">
                    <?php for($i=1; $i<=4; $i++): ?>
                    <div class="ts-ap-level-card">
                        <div class="ts-ap-lc-badge" id="level-badge-<?php echo $i; ?>">📖 Đề 0<?php echo $i; ?></div>
                        <div class="ts-ap-lc-title" id="level-title-<?php echo $i; ?>">Aptis Beginner Test 0<?php echo $i; ?></div>
                        <div class="ts-ap-lc-meta"><span>140 câu</span><span>90 phút</span></div>
                        <a href="#" class="ts-ap-lc-btn" id="level-btn-<?php echo $i; ?>">Bắt đầu thi</a>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Study Path -->
            <div class="ts-ap-section-header">
                <h2 class="ts-ap-section-title">Lộ Trình Học Aptis</h2>
                <p class="ts-ap-section-desc">4 bước chinh phục kỳ thi Aptis từ cơ bản đến nâng cao</p>
            </div>

            <div class="ts-ap-path-wrap">
                <div class="ts-ap-path-item">
                    <div class="ts-ap-pi-icon" style="background: var(--ap-blue);">🧠</div>
                    <div class="ts-ap-pi-num" style="background: var(--ap-blue);">1</div>
                    <div class="ts-ap-pi-title">Grammar Foundation</div>
                    <div class="ts-ap-pi-desc">Nắm vững ngữ pháp cơ bản và nâng cao theo chuẩn Aptis</div>
                    <div class="ts-ap-pi-time">⏱️ 2-3 tuần</div>
                </div>
                <div class="ts-ap-path-item">
                    <div class="ts-ap-pi-icon" style="background: var(--ap-green);">📖</div>
                    <div class="ts-ap-pi-num" style="background: var(--ap-green);">2</div>
                    <div class="ts-ap-pi-title">Vocabulary Building</div>
                    <div class="ts-ap-pi-desc">Xây dựng vốn từ vựng phong phú theo chủ đề thi Aptis</div>
                    <div class="ts-ap-pi-time">⏱️ 3-4 tuần</div>
                </div>
                <div class="ts-ap-path-item">
                    <div class="ts-ap-pi-icon" style="background: var(--ap-orange);">🏅</div>
                    <div class="ts-ap-pi-num" style="background: var(--ap-orange);">3</div>
                    <div class="ts-ap-pi-title">Skill Practice</div>
                    <div class="ts-ap-pi-desc">Luyện tập từng kỹ năng: Listening, Reading, Writing, Speaking</div>
                    <div class="ts-ap-pi-time">⏱️ 4-6 tuần</div>
                </div>
                <div class="ts-ap-path-item">
                    <div class="ts-ap-pi-icon" style="background: var(--ap-purple);">📊</div>
                    <div class="ts-ap-pi-num" style="background: var(--ap-purple);">4</div>
                    <div class="ts-ap-pi-title">Mock Tests</div>
                    <div class="ts-ap-pi-desc">Thi thử toàn bộ đề Aptis mô phỏng cấu trúc thi thật</div>
                    <div class="ts-ap-pi-time">⏱️ 2-3 tuần</div>
                </div>
            </div>
            
            <div class="ts-ap-text-center ts-ap-mb-12" style="margin-bottom: 0;">
                <a href="#" class="ts-ap-btn ts-ap-btn-primary">Bắt đầu lộ trình học →</a>
            </div>
        </div>
    </div>

    <script>
        function switchLevel(color, el) {
            // Remove active classes
            document.querySelectorAll('.ts-ap-level-tab').forEach(t => {
                t.className = 'ts-ap-level-tab';
            });
            
            // Set new active class
            el.className = 'ts-ap-level-tab active-' + color;

            const box = document.getElementById('level-content-box');
            let levelName = '';

            if (color === 'green') {
                box.style.background = 'var(--ap-green-light)';
                box.style.borderColor = 'rgba(16,185,129,0.2)';
                levelName = 'Beginner';
            } else if (color === 'blue') {
                box.style.background = 'var(--ap-blue-light)';
                box.style.borderColor = 'rgba(37,99,235,0.2)';
                levelName = 'Intermediate';
            } else if (color === 'purple') {
                box.style.background = 'var(--ap-purple-light)';
                box.style.borderColor = 'rgba(139,92,246,0.2)';
                levelName = 'Advanced';
            }

            for(let i=1; i<=4; i++) {
                const badge = document.getElementById('level-badge-'+i);
                const title = document.getElementById('level-title-'+i);
                const btn = document.getElementById('level-btn-'+i);
                
                badge.style.background = 'var(--ap-' + color + ')';
                btn.style.background = 'var(--ap-' + color + '-light)';
                btn.style.color = 'var(--ap-' + color + ')';
                title.innerText = 'Aptis ' + levelName + ' Test 0' + i;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('ts-ap-view-all-tests');
            if(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.ts-ap-test-card.hidden-card').forEach(function(card) {
                        card.classList.remove('hidden-card');
                    });
                    this.style.display = 'none';
                });
            }
        });

    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý hiệu ứng đếm số (Count-up Animation)
            const counters = document.querySelectorAll('.ts-ap-counter');
            const speed = 100; // Tốc độ chạy số

            const animateCounters = () => {
                counters.forEach(counter => {
                    const updateCount = () => {
                        const target = +counter.getAttribute('data-target');
                        const count = +counter.innerText.replace(/\./g, '');
                        const inc = target / speed;

                        if (count < target) {
                            counter.innerText = Math.ceil(count + inc).toLocaleString('vi-VN');
                            setTimeout(updateCount, 20);
                        } else {
                            counter.innerText = target.toLocaleString('vi-VN');
                        }
                    };
                    updateCount();
                });
            };

            // Dùng IntersectionObserver để chỉ chạy hiệu ứng khi cuộn chuột tới vùng thống kê
            const statsSection = document.querySelector('.ts-ap-stats-wrapper');
            if (statsSection && 'IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            animateCounters();
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                observer.observe(statsSection);
            } else {
                animateCounters();
            }
        });
    </script>
    <?php
    return ob_get_clean();

}

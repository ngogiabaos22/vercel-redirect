<?php
if (!defined('ABSPATH')) exit;

add_shortcode('vstep_exam', 'tqp_render_vstep_landing');

function tqp_render_vstep_landing($atts) {
    global $wpdb;
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Lấy toàn bộ đề VSTEP
    $exams = $wpdb->get_results("SELECT * FROM $table_exams WHERE title LIKE '%VSTEP%' ORDER BY id ASC");
    
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
            }
            
            if (empty($skill)) continue; 
            
            $test_number = '';
            if (preg_match('/(?:đề|test)\s*(\d+)/iu', $exam->title, $matches)) {
                $test_number = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            } elseif (preg_match('/(\d+)/', $exam->title, $matches)) {
                $test_number = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            }

            $is_uni = false;
            $uni_name = '';
            if (preg_match('/(đh|đại học)\s+([a-zđáàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵ]+(?:\s+[a-zđáàảãạăắằẳẵặâấầẩẫậéèẻẽẹêếềểễệíìỉĩịóòỏõọôốồổỗộơớờởỡợúùủũụưứừửữựýỳỷỹỵ]+)*)/iu', $exam->title, $matches_uni)) {
                $is_uni = true;
                // Capitalize properly
                $uni_name = mb_convert_case(trim($matches_uni[2]), MB_CASE_TITLE, "UTF-8");
                $base_name = 'Đề Thi VSTEP — ĐH ' . $uni_name;
                // Append test number if present to avoid overwriting
                if ($test_number !== '') {
                    $base_name .= ' (' . $test_number . ')';
                }
            } else {
                if ($test_number !== '') {
                    $base_name = 'Đề Thi VSTEP ' . $test_number;
                } else {
                    // Extract something meaningful or generic
                    $base_name = trim(preg_replace('/vstep|practice|listening|reading|writing|speaking|nghe|đọc|nói|viết|-/iu', '', $exam->title));
                    if (empty($base_name)) $base_name = 'Đề Thi VSTEP Mới';
                }
            }
            
            // Tìm Huy hiệu Bậc (B1, B2, C1)
            $badge = '';
            if (preg_match('/\b(B1|B2|C1|A2|C2)\b/i', $exam->title, $matches)) {
                $badge = strtoupper($matches[1]);
            }
            
            if (!isset($grouped_exams[$base_name])) {
                $grouped_exams[$base_name] = [
                    'base_name' => $base_name,
                    'badge' => $badge,
                    'is_uni' => $is_uni,
                    'uni_name' => $uni_name,
                    'listening' => null,
                    'reading' => null,
                    'writing' => null,
                    'speaking' => null
                ];
            }
            
            // Cập nhật huy hiệu nếu thấy
            if (!empty($badge)) {
                $grouped_exams[$base_name]['badge'] = $badge;
            }
            
            $grouped_exams[$base_name][$skill] = $exam;
        }
    }
    
    uksort($grouped_exams, 'strnatcasecmp');

    $general_exams = [];
    $uni_exams = [];
    foreach ($grouped_exams as $base_name => $data) {
        if ($data['is_uni']) {
            $uni_exams[$base_name] = $data;
        } else {
            $general_exams[$base_name] = $data;
        }
    }

    wp_enqueue_style('tqp-style');
    wp_enqueue_style('tqp-font-inter');

    ob_start();
    ?>
    <style>
        :root {
            --vs-blue: #2563EB;
            --vs-blue-light: #EFF6FF;
            --vs-green: #10B981;
            --vs-green-light: #ECFDF5;
            --vs-yellow: #D97706;
            --vs-yellow-light: #FFFBEB;
            --vs-purple: #8B5CF6;
            --vs-purple-light: #F5F3FF;
            --vs-bg: #F8FAFC;
            --vs-text: #1E293B;
            --vs-text-muted: #64748B;
            --vs-border: #E2E8F0;
        }

        .ts-vstep-wrapper {
            font-family: 'Inter', sans-serif;
            background-color: var(--vs-bg);
            color: var(--vs-text);
            line-height: 1.5;
            padding: 20px 0 60px;
            box-sizing: border-box;
            overflow-x: hidden;
        }
        .ts-vstep-wrapper *, .ts-vstep-wrapper *::before, .ts-vstep-wrapper *::after {
            box-sizing: inherit;
        }

        .ts-vs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Utilities */
        .ts-vs-flex { display: flex; }
        .ts-vs-grid { display: grid; }
        .ts-vs-items-center { align-items: center; }
        .ts-vs-justify-center { justify-content: center; }
        
        .ts-vs-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 28px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .ts-vs-btn-primary {
            background-color: var(--vs-blue);
            color: #fff;
        }
        .ts-vs-btn-primary:hover {
            background-color: #1d4ed8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transform: translateY(-2px);
        }

        /* --- Hero Section --- */
        .ts-vs-hero-layout {
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 50px;
        }
        .ts-vs-hero-content {
            flex: 1.2;
        }
        .ts-vs-hero-visual {
            flex: 0.8;
            position: relative;
            display: flex;
            justify-content: center;
        }
        
        .ts-vs-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--vs-yellow-light);
            color: var(--vs-yellow);
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            border: 1px solid rgba(217,119,6,0.2);
        }
        
        .ts-vs-hero-title {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
            color: #0f172a;
            margin-top: 0;
        }
        .ts-vs-hero-title span {
            color: var(--vs-blue);
        }
        .ts-vs-hero-desc {
            font-size: 15px;
            color: var(--vs-text-muted);
            margin-bottom: 20px;
            max-width: 95%;
        }

        .ts-vs-skill-chips {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .ts-vs-chip {
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid;
            background: #fff;
        }
        .ts-vs-chip-green { color: var(--vs-green); border-color: rgba(16,185,129,0.3); }
        .ts-vs-chip-blue { color: var(--vs-blue); border-color: rgba(37,99,235,0.3); }
        .ts-vs-chip-yellow { color: var(--vs-yellow); border-color: rgba(217,119,6,0.3); }
        
        .ts-vs-features-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .ts-vs-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            color: var(--vs-text);
            font-weight: 500;
            background: #fff;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid var(--vs-border);
            line-height: 1.4;
        }

        /* Mockup Visual */
        .ts-vs-mockup {
            background: #fff;
            border-radius: 24px;
            padding: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            border: 1px solid var(--vs-border);
            position: relative;
        }
        .ts-vs-mockup-badge {
            position: absolute;
            top: -15px;
            right: 20px;
            background: var(--vs-green);
            color: #fff;
            padding: 8px 16px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 10px 20px rgba(16,185,129,0.3);
        }
        .ts-vs-mockup-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        .ts-vs-mockup-box {
            flex: 1;
            padding: 14px;
            border-radius: 16px;
        }
        .ts-vs-mb-blue { background: var(--vs-blue-light); }
        .ts-vs-mb-green { background: var(--vs-green-light); }
        .ts-vs-mb-yellow { background: var(--vs-yellow-light); }
        .ts-vs-mb-purple { background: var(--vs-purple-light); }
        .ts-vs-mb-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .ts-vs-mb-sub { font-size: 11px; color: var(--vs-text-muted); margin-bottom: 10px; }
        .ts-vs-mb-bar { height: 6px; border-radius: 99px; width: 100%; background: rgba(0,0,0,0.05); }
        .ts-vs-mb-bar-fill { height: 100%; border-radius: 99px; }
        
        .ts-vs-mb-score {
            margin-top: 16px;
            padding: 16px;
            background: var(--vs-bg);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .ts-vs-path-section-bg {
            background-color: #ECFDF5;
            border-radius: 32px;
            padding: 60px 40px;
            margin-top: 80px;
            margin-bottom: 80px;
        }
        .ts-vs-path-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .ts-vs-path-label {
            font-size: 13px;
            font-weight: 800;
            color: var(--vs-blue);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .ts-vs-path-title {
            font-size: 36px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 16px 0;
            line-height: 1.2;
        }
        .ts-vs-path-desc {
            font-size: 16px;
            color: var(--vs-text-muted);
            margin: 0 auto;
            max-width: 600px;
            line-height: 1.6;
        }

        /* --- Stats Section --- */
        .ts-vs-stats-wrapper {
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            background: #fff;
            border-radius: 24px;
            padding: 32px 40px;
            margin-bottom: 60px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid var(--vs-border);
        }
        .ts-vs-stat-item {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .ts-vs-stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .ts-vs-si-blue { background: var(--vs-blue-light); color: var(--vs-blue); }
        .ts-vs-si-green { background: var(--vs-green-light); color: var(--vs-green); }
        .ts-vs-si-yellow { background: var(--vs-yellow-light); color: var(--vs-yellow); }
        .ts-vs-si-purple { background: var(--vs-purple-light); color: var(--vs-purple); }
        
        .ts-vs-stat-num {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .ts-vs-stat-label {
            font-size: 14px;
            color: var(--vs-text-muted);
            margin-top: 4px;
        }

        /* --- Test List Section --- */
        .ts-vs-section-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            text-align: left;
        }
        .ts-vs-section-header-center {
            text-align: center;
            justify-content: center;
            flex-direction: column;
            align-items: center;
        }
        .ts-vs-section-header-left {
            text-align: left;
        }
        .ts-vs-section-label {
            color: var(--vs-blue);
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .ts-vs-section-title {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            margin-top: 0;
        }
        .ts-vs-section-title-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--vs-green);
            color: #fff;
            margin-right: 12px;
            vertical-align: middle;
            font-size: 20px;
        }
        .ts-vs-section-desc {
            font-size: 15px;
            color: var(--vs-text-muted);
        }

        .ts-vs-test-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 60px;
        }
        .ts-vs-hidden-card {
            display: none;
        }

        .ts-vs-test-card {
            background: #fff;
            border: 1px solid var(--vs-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
            min-width: 0;
        }
        .ts-vs-test-card:hover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.06);
            transform: translateY(-4px);
        }

        .ts-vs-tc-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .ts-vs-tc-icon {
            width: 48px;
            height: 48px;
            background: var(--vs-blue);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .ts-vs-tc-badges {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ts-vs-tc-badge {
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
        }
        .ts-vs-badge-level { background: var(--vs-green-light); color: var(--vs-green); }
        .ts-vs-badge-pop { background: var(--vs-yellow-light); color: var(--vs-yellow); }
        .ts-vs-badge-new { background: var(--vs-blue-light); color: var(--vs-blue); }

        .ts-vs-tc-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .ts-vs-tc-sub {
            font-size: 13px;
            color: var(--vs-text-muted);
            margin-bottom: 24px;
        }

        .ts-vs-skills-row {
            display: flex;
            gap: 6px;
        }
        .ts-vs-skill-col {
            flex: 1;
            min-width: 0;
            padding: 12px 4px;
            border-radius: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            transition: 0.2s;
            cursor: pointer;
            border: 1px solid transparent;
        }
        
        .ts-vs-skill-col:hover {
            transform: translateY(-2px);
        }
        
        /* Disable state */
        .ts-vs-skill-col.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            filter: grayscale(1);
        }
        .ts-vs-skill-col.disabled:hover {
            transform: none;
        }

        .ts-vs-sk-blue { background: var(--vs-blue-light); color: var(--vs-blue); border-color: rgba(37,99,235,0.1); }
        .ts-vs-sk-green { background: var(--vs-green-light); color: var(--vs-green); border-color: rgba(16,185,129,0.1); }
        .ts-vs-sk-yellow { background: var(--vs-yellow-light); color: var(--vs-yellow); border-color: rgba(217,119,6,0.1); }
        .ts-vs-sk-purple { background: var(--vs-purple-light); color: var(--vs-purple); border-color: rgba(139,92,246,0.1); }

        .ts-vs-sk-icon { font-size: 20px; margin-bottom: 6px; }
        .ts-vs-sk-name { font-size: 12px; font-weight: 700; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .ts-vs-sk-text { font-size: 10px; font-weight: 600; opacity: 0.8; white-space: nowrap; }

        /* --- Study Path Section --- */
        .ts-vs-path-wrapper {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            position: relative;
            margin-top: 40px;
        }
        .ts-vs-path-wrapper::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 10%;
            right: 10%;
            height: 1px;
            background: var(--vs-border);
            z-index: 0;
        }
        .ts-vs-path-item {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .ts-vs-pi-icon-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            background: #fff;
            border: 2px solid transparent;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .ts-vs-pi-num {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            border: 2px solid #fff;
        }
        .ts-vs-pi-step {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .ts-vs-pi-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .ts-vs-pi-desc {
            font-size: 14px;
            color: var(--vs-text-muted);
        }

        /* Path Colors */
        .ts-vs-pi-1 .ts-vs-pi-icon-wrap { background: var(--vs-blue-light); color: var(--vs-blue); border-color: rgba(37,99,235,0.2); }
        .ts-vs-pi-1 .ts-vs-pi-num { background: var(--vs-blue); }
        .ts-vs-pi-1 .ts-vs-pi-step { color: var(--vs-blue); }

        .ts-vs-pi-2 .ts-vs-pi-icon-wrap { background: var(--vs-green-light); color: var(--vs-green); border-color: rgba(16,185,129,0.2); }
        .ts-vs-pi-2 .ts-vs-pi-num { background: var(--vs-green); }
        .ts-vs-pi-2 .ts-vs-pi-step { color: var(--vs-green); }

        .ts-vs-pi-3 .ts-vs-pi-icon-wrap { background: var(--vs-yellow-light); color: var(--vs-yellow); border-color: rgba(217,119,6,0.2); }
        .ts-vs-pi-3 .ts-vs-pi-num { background: var(--vs-yellow); }
        .ts-vs-pi-3 .ts-vs-pi-step { color: var(--vs-yellow); }

        .ts-vs-pi-4 .ts-vs-pi-icon-wrap { background: var(--vs-purple-light); color: var(--vs-purple); border-color: rgba(139,92,246,0.2); }
        .ts-vs-pi-4 .ts-vs-pi-num { background: var(--vs-purple); }
        .ts-vs-pi-4 .ts-vs-pi-step { color: var(--vs-purple); }

        @media (max-width: 992px) {
            .ts-vs-hero-layout { flex-direction: column; text-align: center; gap: 20px; }
            .ts-vs-hero-visual { width: 100%; margin-top: 20px; }
            .ts-vs-skill-chips { justify-content: center; }
            .ts-vs-features-list { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto 24px; text-align: left;}
            .ts-vs-test-grid { grid-template-columns: repeat(2, 1fr); }
            
            /* Cấu trúc lại Stats Grid cho Tablet/Mobile */
            .ts-vs-stats-wrapper { 
                display: grid; 
                grid-template-columns: repeat(2, 1fr); 
                gap: 16px; 
                background: transparent; 
                border: none; 
                box-shadow: none; 
                padding: 0; 
            }
            .ts-vs-stat-item { 
                background: #fff; 
                padding: 20px 16px; 
                border-radius: 16px; 
                border: 1px solid var(--vs-border); 
                box-shadow: 0 4px 12px rgba(0,0,0,0.02); 
                justify-content: flex-start;
            }

            .ts-vs-path-wrapper { grid-template-columns: repeat(2, 1fr); gap: 40px; }
            .ts-vs-path-wrapper::before { display: none; }
        }
        @media (max-width: 768px) {
            .ts-vs-hero-title { font-size: 32px; }
            
            /* Dồn 3 skill chips thành 1 dòng trên mobile */
            .ts-vs-skill-chips { 
                flex-wrap: nowrap; 
                gap: 6px; 
            }
            .ts-vs-chip { 
                padding: 8px 4px; 
                font-size: 11px; 
                flex: 1; 
                justify-content: center; 
            }

            /* Thu nhỏ test card để không tràn màn hình */
            .ts-vs-test-grid { grid-template-columns: 1fr; }
            .ts-vs-test-card { padding: 16px; }
            .ts-vs-skills-row { gap: 4px; }
            .ts-vs-skill-col { padding: 12px 2px; }
            .ts-vs-sk-name { font-size: 11px; margin-bottom: 2px; }
            .ts-vs-sk-icon { font-size: 16px; margin-bottom: 4px; }
            .ts-vs-sk-text { font-size: 10px; letter-spacing: -0.5px; }

            /* Thu nhỏ số thống kê để không bị cắt xén khi dồn 2 cột */
            .ts-vs-stats-wrapper { gap: 10px; }
            .ts-vs-stat-item { padding: 12px 10px; gap: 8px; border-radius: 14px; }
            .ts-vs-stat-icon { width: 38px; height: 38px; font-size: 16px; border-radius: 10px; }
            .ts-vs-stat-num { font-size: 20px; }
            .ts-vs-stat-label { font-size: 11px; }

            .ts-vs-path-wrapper { grid-template-columns: 1fr; }
        }
    </style>

    <div class="ts-vstep-wrapper">
        <div class="ts-vs-container">
            
            <!-- Hero Section -->
            <div class="ts-vs-hero-layout">
                <div class="ts-vs-hero-content">
                    <div class="ts-vs-hero-badge">⭐ Đáp ứng chuẩn đầu ra & yêu cầu công việc</div>
                    <h1 class="ts-vs-hero-title">Thi Thử <span>VSTEP</span> Online<br>Miễn Phí</h1>
                    <p class="ts-vs-hero-desc">Luyện tập và thi thử chứng chỉ VSTEP theo Khung năng lực ngoại ngữ 6 bậc của Việt Nam. Hệ thống cung cấp các bài thi thử đầy đủ 4 kỹ năng, bám sát cấu trúc đề thi thật của Bộ GD&ĐT. Đây là công cụ không thể thiếu để sinh viên và công chức ôn luyện, đạt mục tiêu B1, B2, C1.</p>
                    
                    <div class="ts-vs-skill-chips">
                        <div class="ts-vs-chip ts-vs-chip-green">🎧 VSTEP Nghe</div>
                        <div class="ts-vs-chip ts-vs-chip-blue">📖 VSTEP Đọc</div>
                        <div class="ts-vs-chip ts-vs-chip-yellow">✍️ VSTEP Viết</div>
                    </div>
                    
                    <div class="ts-vs-features-list">
                        <div class="ts-vs-feature-item"><span>✅</span> Cấu trúc chuẩn Bộ GD&ĐT</div>
                        <div class="ts-vs-feature-item"><span>ℹ️</span> Thang điểm chi tiết, rõ ràng</div>
                        <div class="ts-vs-feature-item"><span>📚</span> Ngân hàng đề thi phong phú</div>
                        <div class="ts-vs-feature-item"><span>📊</span> Biết điểm ngay sau khi nộp bài</div>
                    </div>
                    
                    <a href="#test-list" class="ts-vs-btn ts-vs-btn-primary">Bắt đầu luyện đề ngay →</a>
                </div>
                
                <div class="ts-vs-hero-visual">
                    <div class="ts-vs-mockup">
                        <div class="ts-vs-mockup-badge">↗ +12 điểm hôm nay</div>
                        <div class="ts-vs-mockup-row">
                            <div class="ts-vs-mockup-box ts-vs-mb-blue">
                                <div class="ts-vs-mb-title"><span style="color: var(--vs-blue);">📖</span> Reading</div>
                                <div class="ts-vs-mb-sub">40 câu • 60 phút</div>
                                <div class="ts-vs-mb-bar"><div class="ts-vs-mb-bar-fill" style="width: 75%; background: var(--vs-blue);"></div></div>
                            </div>
                            <div class="ts-vs-mockup-box ts-vs-mb-green">
                                <div class="ts-vs-mb-title"><span style="color: var(--vs-green);">🎧</span> Listening</div>
                                <div class="ts-vs-mb-sub">35 câu • 40 phút</div>
                                <div class="ts-vs-mb-bar"><div class="ts-vs-mb-bar-fill" style="width: 85%; background: var(--vs-green);"></div></div>
                            </div>
                        </div>
                        <div class="ts-vs-mockup-row">
                            <div class="ts-vs-mockup-box ts-vs-mb-yellow">
                                <div class="ts-vs-mb-title"><span style="color: var(--vs-yellow);">✍️</span> Writing</div>
                                <div class="ts-vs-mb-sub">2 bài • 60 phút</div>
                                <div class="ts-vs-mb-bar"><div class="ts-vs-mb-bar-fill" style="width: 40%; background: var(--vs-yellow);"></div></div>
                            </div>
                            <div class="ts-vs-mockup-box ts-vs-mb-purple">
                                <div class="ts-vs-mb-title"><span style="color: var(--vs-purple);">🎙️</span> Speaking</div>
                                <div class="ts-vs-mb-sub">3 phần • 15 phút</div>
                                <div class="ts-vs-mb-bar"><div class="ts-vs-mb-bar-fill" style="width: 60%; background: var(--vs-purple);"></div></div>
                            </div>
                        </div>
                        <div class="ts-vs-mb-score">
                            <div style="font-size: 14px; color: var(--vs-text-muted);">Điểm trung bình</div>
                            <div style="font-size: 32px; font-weight: 800;">7.5 <span style="font-size: 18px; color: var(--vs-green);">/10</span></div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Stats Card -->
        <div class="ts-vs-container">
            <div class="ts-vs-stats-wrapper">
                <div class="ts-vs-stat-item">
                    <div class="ts-vs-stat-icon ts-vs-si-blue">📖</div>
                    <div>
                        <div class="ts-vs-stat-num"><span class="ts-vs-counter" data-target="500">0</span>+</div>
                        <div class="ts-vs-stat-label">Đề thi thử</div>
                    </div>
                </div>
                <div class="ts-vs-stat-item">
                    <div class="ts-vs-stat-icon ts-vs-si-green">👥</div>
                    <div>
                        <div class="ts-vs-stat-num"><span class="ts-vs-counter" data-target="10000">0</span>+</div>
                        <div class="ts-vs-stat-label">Học viên</div>
                    </div>
                </div>
                <div class="ts-vs-stat-item">
                    <div class="ts-vs-stat-icon ts-vs-si-yellow">📈</div>
                    <div>
                        <div class="ts-vs-stat-num"><span class="ts-vs-counter" data-target="50000">0</span>+</div>
                        <div class="ts-vs-stat-label">Lượt thi</div>
                    </div>
                </div>
                <div class="ts-vs-stat-item">
                    <div class="ts-vs-stat-icon ts-vs-si-purple">⭐</div>
                    <div>
                        <div class="ts-vs-stat-num"><span class="ts-vs-counter" data-target="95">0</span>%</div>
                        <div class="ts-vs-stat-label">Đánh giá tích cực</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ts-vs-container" id="test-list">
            
            <div class="ts-vs-section-header">
                <div>
                    <h2 class="ts-vs-section-title">Danh Sách Đề Thi VSTEP</h2>
                    <p class="ts-vs-section-desc" style="margin: 0;">Chọn từng kỹ năng để luyện riêng hoặc làm toàn bộ đề thi.</p>
                </div>
            </div>

            <div class="ts-vs-test-grid" id="grid-general">
                <?php if (!empty($general_exams)): ?>
                    <?php 
                    $count = 0;
                    foreach ($general_exams as $base_name => $skills): 
                        $count++;
                        $users = number_format(rand(5000, 25000), 0, ',', '.');
                        $is_new = ($count > 3) ? true : false;
                        $badge_text = $skills['badge'] ? $skills['badge'] : 'B1';
                        $hidden_class = ($count > 6) ? ' ts-vs-hidden-card' : '';
                    ?>
                    <div class="ts-vs-test-card<?php echo $hidden_class; ?>">
                        <div class="ts-vs-tc-top">
                            <div class="ts-vs-tc-icon">📄</div>
                            <div style="text-align: right;">
                                <div style="font-size: 13px; color: var(--vs-text-muted); font-weight: 600; margin-bottom: 8px;">
                                    👥 <?php echo $users; ?>
                                </div>
                                <div class="ts-vs-tc-badges">
                                    <span class="ts-vs-tc-badge ts-vs-badge-level"><?php echo esc_html($badge_text); ?></span>
                                    <?php if ($is_new): ?>
                                        <span class="ts-vs-tc-badge ts-vs-badge-new">Mới</span>
                                    <?php else: ?>
                                        <span class="ts-vs-tc-badge ts-vs-badge-pop">🔥 Phổ biến</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ts-vs-tc-title"><?php echo esc_html($base_name); ?></div>
                        <div class="ts-vs-tc-sub">Bộ Giáo dục & Đào tạo</div>

                        <div class="ts-vs-skills-row">
                            <!-- Reading -->
                            <?php if (!empty($skills['reading'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['reading']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-blue">
                                    <div class="ts-vs-sk-icon">📖</div>
                                    <div class="ts-vs-sk-name">Reading</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-blue disabled">
                                    <div class="ts-vs-sk-icon">📖</div>
                                    <div class="ts-vs-sk-name">Reading</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Listening -->
                            <?php if (!empty($skills['listening'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['listening']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-green">
                                    <div class="ts-vs-sk-icon">🎧</div>
                                    <div class="ts-vs-sk-name">Listening</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-green disabled">
                                    <div class="ts-vs-sk-icon">🎧</div>
                                    <div class="ts-vs-sk-name">Listening</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Writing -->
                            <?php if (!empty($skills['writing'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['writing']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-yellow">
                                    <div class="ts-vs-sk-icon">✍️</div>
                                    <div class="ts-vs-sk-name">Writing</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-yellow disabled">
                                    <div class="ts-vs-sk-icon">✍️</div>
                                    <div class="ts-vs-sk-name">Writing</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Speaking -->
                            <?php if (!empty($skills['speaking'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['speaking']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-purple">
                                    <div class="ts-vs-sk-icon">🎙️</div>
                                    <div class="ts-vs-sk-name">Speaking</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-purple disabled">
                                    <div class="ts-vs-sk-icon">🎙️</div>
                                    <div class="ts-vs-sk-name">Speaking</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: var(--vs-text-muted); padding: 60px; font-style: italic; background: #fff; border-radius: 20px; border: 1px solid var(--vs-border);">
                        Chưa có đề thi VSTEP nào trong hệ thống.
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="javascript:void(0)" class="ts-vs-view-all" data-target="grid-general" style="display: inline-block; background: #EEF2FF; color: var(--vs-blue); padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s ease;">Xem tất cả ›</a>
                <a href="javascript:void(0)" class="ts-vs-collapse" data-target="grid-general" style="display: none; background: #F1F5F9; color: #64748B; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s ease;">‹ Thu gọn</a>
            </div>
            
            <div class="ts-vs-section-header" style="margin-top: 20px;">
                <div>
                    <h2 class="ts-vs-section-title"><span class="ts-vs-section-title-icon">🎓</span>Thi Thử Online VSTEP Các Trường Đại Học</h2>
                    <p class="ts-vs-section-desc" style="margin: 0;">Đề thi từ các trường đại học hàng đầu Việt Nam</p>
                </div>
            </div>

            <div class="ts-vs-test-grid" id="grid-uni">
                <?php if (!empty($uni_exams)): ?>
                    <?php 
                    $count = 0;
                    foreach ($uni_exams as $base_name => $skills): 
                        $count++;
                        $users = number_format(rand(5000, 25000), 0, ',', '.');
                        $is_new = ($count > 3) ? true : false;
                        $badge_text = $skills['badge'] ? $skills['badge'] : 'B1';
                        $hidden_class = ($count > 6) ? ' ts-vs-hidden-card' : '';
                        $sub_text = $skills['uni_name'] ? 'ĐH ' . $skills['uni_name'] : 'Đại học';
                    ?>
                    <div class="ts-vs-test-card<?php echo $hidden_class; ?>">
                        <div class="ts-vs-tc-top">
                            <div class="ts-vs-tc-icon" style="background: var(--vs-blue-light); color: var(--vs-blue);">📄</div>
                            <div style="text-align: right;">
                                <div style="font-size: 13px; color: var(--vs-text-muted); font-weight: 600; margin-bottom: 8px;">
                                    👥 <?php echo $users; ?>
                                </div>
                                <div class="ts-vs-tc-badges">
                                    <span class="ts-vs-tc-badge ts-vs-badge-level"><?php echo esc_html($badge_text); ?></span>
                                    <?php if ($is_new): ?>
                                        <span class="ts-vs-tc-badge ts-vs-badge-new">Mới</span>
                                    <?php else: ?>
                                        <span class="ts-vs-tc-badge ts-vs-badge-pop">🔥 Phổ biến</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ts-vs-tc-title"><?php echo esc_html($base_name); ?></div>
                        <div class="ts-vs-tc-sub"><?php echo esc_html($sub_text); ?></div>

                        <div class="ts-vs-skills-row">
                            <!-- Reading -->
                            <?php if (!empty($skills['reading'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['reading']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-blue">
                                    <div class="ts-vs-sk-icon">📖</div>
                                    <div class="ts-vs-sk-name">Reading</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-blue disabled">
                                    <div class="ts-vs-sk-icon">📖</div>
                                    <div class="ts-vs-sk-name">Reading</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Listening -->
                            <?php if (!empty($skills['listening'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['listening']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-green">
                                    <div class="ts-vs-sk-icon">🎧</div>
                                    <div class="ts-vs-sk-name">Listening</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-green disabled">
                                    <div class="ts-vs-sk-icon">🎧</div>
                                    <div class="ts-vs-sk-name">Listening</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Writing -->
                            <?php if (!empty($skills['writing'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['writing']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-yellow">
                                    <div class="ts-vs-sk-icon">✍️</div>
                                    <div class="ts-vs-sk-name">Writing</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-yellow disabled">
                                    <div class="ts-vs-sk-icon">✍️</div>
                                    <div class="ts-vs-sk-name">Writing</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>

                            <!-- Speaking -->
                            <?php if (!empty($skills['speaking'])): ?>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($skills['speaking']->id)); ?>" class="ts-vs-skill-col ts-vs-sk-purple">
                                    <div class="ts-vs-sk-icon">🎙️</div>
                                    <div class="ts-vs-sk-name">Speaking</div>
                                    <div class="ts-vs-sk-text">Vào thi ›</div>
                                </a>
                            <?php else: ?>
                                <div class="ts-vs-skill-col ts-vs-sk-purple disabled">
                                    <div class="ts-vs-sk-icon">🎙️</div>
                                    <div class="ts-vs-sk-name">Speaking</div>
                                    <div class="ts-vs-sk-text">Chưa mở</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: var(--vs-text-muted); padding: 60px; font-style: italic; background: #fff; border-radius: 20px; border: 1px solid var(--vs-border);">
                        Chưa có đề thi VSTEP Đại học nào trong hệ thống.
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="javascript:void(0)" class="ts-vs-view-all" data-target="grid-uni" style="display: inline-block; background: #EEF2FF; color: var(--vs-blue); padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s ease;">Xem tất cả ›</a>
                <a href="javascript:void(0)" class="ts-vs-collapse" data-target="grid-uni" style="display: none; background: #F1F5F9; color: #64748B; padding: 12px 32px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s ease;">‹ Thu gọn</a>
            </div>
            
        </div>

        <!-- Study Path Section -->
        <div class="ts-vs-container ts-vs-path-section-bg">
            <div class="ts-vs-path-header">
                <div class="ts-vs-path-label">LỘ TRÌNH HỌC</div>
                <h2 class="ts-vs-path-title">4 Bước Chinh Phục VSTEP</h2>
                <p class="ts-vs-path-desc">Lộ trình học tập được thiết kế bởi các chuyên gia ngôn ngữ hàng đầu Việt Nam.</p>
            </div>
            
            <div class="ts-vs-path-wrapper">
                <div class="ts-vs-path-item ts-vs-pi-1">
                    <div class="ts-vs-pi-icon-wrap">
                        📖
                        <div class="ts-vs-pi-num">1</div>
                    </div>
                    <div class="ts-vs-pi-step">Bước 01</div>
                    <div class="ts-vs-pi-title">Ôn Ngữ Pháp</div>
                    <div class="ts-vs-pi-desc">Nắm vững cấu trúc ngữ pháp và từ vựng VSTEP cần thiết.</div>
                </div>
                
                <div class="ts-vs-path-item ts-vs-pi-2">
                    <div class="ts-vs-pi-icon-wrap">
                        🎯
                        <div class="ts-vs-pi-num">2</div>
                    </div>
                    <div class="ts-vs-pi-step">Bước 02</div>
                    <div class="ts-vs-pi-title">Luyện Từng Kỹ Năng</div>
                    <div class="ts-vs-pi-desc">Tập trung nâng cao Reading, Listening, Writing, Speaking riêng lẻ.</div>
                </div>
                
                <div class="ts-vs-path-item ts-vs-pi-3">
                    <div class="ts-vs-pi-icon-wrap">
                        📝
                        <div class="ts-vs-pi-num">3</div>
                    </div>
                    <div class="ts-vs-pi-step">Bước 03</div>
                    <div class="ts-vs-pi-title">Làm Bộ Đề</div>
                    <div class="ts-vs-pi-desc">Luyện đề từng phần theo trường đại học và cấp độ B1/B2/C1.</div>
                </div>
                
                <div class="ts-vs-path-item ts-vs-pi-4">
                    <div class="ts-vs-pi-icon-wrap">
                        🏆
                        <div class="ts-vs-pi-num">4</div>
                    </div>
                    <div class="ts-vs-pi-step">Bước 04</div>
                    <div class="ts-vs-pi-title">Thi Thử Hoàn Chỉnh</div>
                    <div class="ts-vs-pi-desc">Mô phỏng kỳ thi thật với đầy đủ 4 kỹ năng và tính giờ.</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý nút Xem tất cả
            var viewBtns = document.querySelectorAll('.ts-vs-view-all');
            viewBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = this.getAttribute('data-target');
                    var container = document.getElementById(targetId);
                    if (container) {
                        container.querySelectorAll('.ts-vs-hidden-card').forEach(function(card) {
                            card.classList.remove('ts-vs-hidden-card');
                        });
                    }
                    this.style.display = 'none';
                });
            });

            // Xử lý hiệu ứng đếm số (Count-up Animation)
            const counters = document.querySelectorAll('.ts-vs-counter');
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
            const statsSection = document.querySelector('.ts-vs-stats-wrapper');
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
                // Fallback cho trình duyệt cũ
                animateCounters();
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

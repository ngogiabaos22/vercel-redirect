<?php
if (!defined('ABSPATH')) exit;

// Đăng ký các tệp CSS và JS
add_action('wp_enqueue_scripts', 'tqp_register_assets');
function tqp_register_assets() {
    // Đăng ký Google Font Inter
    wp_register_style('tqp-font-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', [], TQP_VER);
    
    wp_register_style('tqp-style', TQP_URL . 'assets/tqp-style.css', [], TQP_VER);
    wp_register_script('tqp-script', TQP_URL . 'assets/tqp-script.js', [], TQP_VER, true);
    
    // Đăng ký thư viện KaTeX hiển thị công thức toán học LaTeX
    wp_register_style('tqp-katex', 'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css', [], '0.16.9');
    wp_register_script('tqp-katex', 'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js', [], '0.16.9', true);
    wp_register_script('tqp-katex-auto', 'https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js', ['tqp-katex'], '0.16.9', true);
    
    // Thư viện pháo hoa confetti
    wp_register_script('canvas-confetti', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js', [], '1.6.0', true);

    wp_localize_script('tqp-script', 'tqp_vars', [
        'api_url'  => esc_url_raw(rest_url('tq/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'home_url' => esc_url(home_url('/'))
    ]);

    // Tự động enqueue khi ở trang phòng thi dedicated hoặc khi có param exam_id trên URL
    if (is_singular('tq_exam') || (isset($_GET['exam_id']) && intval($_GET['exam_id']) > 0)) {
        wp_enqueue_style('tqp-font-inter');
        wp_enqueue_style('tqp-style');
        wp_enqueue_style('tqp-katex');
        wp_enqueue_script('canvas-confetti');
        wp_enqueue_script('tqp-katex');
        wp_enqueue_script('tqp-katex-auto');
        wp_enqueue_script('tqp-script');
    }
}

// Đăng ký Shortcode [traffic_quiz id="X"]
add_shortcode('traffic_quiz', 'tqp_render_quiz_shortcode');
function tqp_render_quiz_shortcode($atts) {
    global $wpdb;
    
    $args = shortcode_atts([
        'id' => 0
    ], $atts);

    $exam_id = intval($args['id']);
    if ($exam_id <= 0) {
        return '<div style="color:red;padding:15px;text-align:center;">Vui lòng nhập ID đề thi hợp lệ! VD: [traffic_quiz id="1"]</div>';
    }

    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_exams WHERE id = %d", $exam_id));
    if (!$exam) {
        return '<div style="color:red;padding:15px;text-align:center;">Đề thi không tồn tại trong hệ thống!</div>';
    }

    // TRƯỜNG HỢP: Đang hiển thị ở trang Landing Page thông thường (không phải phòng thi dedicated /bai-thi/ và không có exam_id trên URL)
    // Chúng ta sẽ tự động chuyển đổi hiển thị thành Nút bấm CTA dẫn vào phòng thi dedicated
    if (!is_singular('tq_exam') && (!isset($_GET['exam_id']) || intval($_GET['exam_id']) !== $exam_id)) {
        return tqp_render_quiz_button_shortcode(['id' => $exam_id]);
    }

    // Enqueue styles & scripts
    wp_enqueue_style('tqp-style');
    wp_enqueue_style('tqp-katex');
    wp_enqueue_script('canvas-confetti');
    wp_enqueue_script('tqp-katex');
    wp_enqueue_script('tqp-katex-auto');
    wp_enqueue_script('tqp-script');

    // === HYBRID RENDERING CHO SEO (GOOGLEBOT CRAWL) ===
    $seo_noscript = '';
    $groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_groups WHERE exam_id = %d ORDER BY sort_order ASC", $exam_id));
    if ($groups) {
        $seo_noscript .= '<noscript>';
        $seo_noscript .= '<h1>' . esc_html($exam->title) . '</h1>';
        $seo_noscript .= '<p>Thời gian làm bài: ' . $exam->duration_minutes . ' phút. Đề thi trắc nghiệm online trên TestSpace.</p>';
        
        foreach ($groups as $group) {
            $seo_noscript .= '<div class="ts-seo-group">';
            $seo_noscript .= '<h3>Hướng dẫn: ' . esc_html($group->instruction) . '</h3>';
            if ($group->passage_text) {
                $seo_noscript .= '<div class="ts-seo-passage">' . wp_kses_post($group->passage_text) . '</div>';
            }
            
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
                "SELECT * FROM $table_questions WHERE id IN ($clean_ids_str)"
            );
            
            // Sort questions based on the order in question_ids
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
            
            if ($questions) {
                $seo_noscript .= '<ul>';
                foreach ($questions as $q) {
                    $seo_noscript .= '<li>';
                    $seo_noscript .= '<strong>Câu ' . $q->question_number . ': ' . esc_html($q->question_text) . '</strong><br>';
                    $seo_noscript .= 'A. ' . esc_html($q->opt_a) . '<br>';
                    $seo_noscript .= 'B. ' . esc_html($q->opt_b) . '<br>';
                    if (trim($q->opt_c) !== '') {
                        $seo_noscript .= 'C. ' . esc_html($q->opt_c) . '<br>';
                    }
                    if (trim($q->opt_d) !== '') {
                        $seo_noscript .= 'D. ' . esc_html($q->opt_d) . '<br>';
                    }
                    $seo_noscript .= '</li>';
                }
                $seo_noscript .= '</ul>';
            }
            $seo_noscript .= '</div>';
        }
        $seo_noscript .= '</noscript>';
    }

    // Đếm tổng số câu hỏi cho metadata
    $total_questions = 0;
    if ($groups) {
        foreach ($groups as $group) {
            $total_questions += $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_questions WHERE group_id = %d",
                $group->id
            ));
        }
    }

    // Lấy tên danh mục từ category_id
    $category_label = '';
    if (!empty($exam->category_id)) {
        $table_cats = $wpdb->prefix . 'tq_categories';
        $cat = $wpdb->get_row($wpdb->prepare("SELECT name FROM $table_cats WHERE id = %d", $exam->category_id));
        if ($cat) $category_label = $cat->name;
    }

    // Format thông tin đề thi
    $access_label = (!empty($exam->requires_unlock) && $exam->requires_unlock) ? '🔒 Yêu cầu mã khóa' : '✅ Tự do làm bài';
    $duration_label = $exam->duration_minutes . ' phút';
    $questions_label = $total_questions . ' câu';

    // Xác định xem đây có phải là bài thi HSK hay không và cấp độ là mấy
    $is_hsk = false;
    $hsk_level = '';
    
    // Tìm cấp độ HSK (ví dụ: HSK 1, HSK 2...)
    if (preg_match('/HSK\s*(\d+)/i', $exam->title, $matches) || preg_match('/HSK\s*(\d+)/i', $category_label, $matches)) {
        $is_hsk = true;
        $hsk_level = $matches[1];
    } elseif (stripos($category_label, 'HSK') !== false || stripos($exam->title, 'HSK') !== false) {
        $is_hsk = true;
    }
    
    // Nếu là HSK, lấy danh sách các đề thi CÙNG CẤP ĐỘ để làm menu điều hướng
    $hsk_exams = [];
    if ($is_hsk) {
        if (!empty($hsk_level)) {
            $hsk_exams = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title FROM $table_exams WHERE title LIKE %s OR title LIKE %s ORDER BY title ASC", 
                '%HSK ' . $hsk_level . '%', 
                '%HSK' . $hsk_level . '%'
            ));
        } else {
            $hsk_exams = $wpdb->get_results("SELECT id, title FROM $table_exams WHERE category LIKE '%HSK%' OR title LIKE '%HSK%' ORDER BY title ASC");
        }
    }

    ob_start();
    ?>
<div class="ts-app-container" id="ts-app-root" data-exam-id="<?php echo esc_attr($exam_id); ?>">

    <!-- ============================================================ -->
    <!-- LOBBY / LANDING PAGE (Visible by default, for SEO & Ads)     -->
    <!-- ============================================================ -->
    <div id="ts-exam-lobby" class="ts-lobby-container <?php echo $is_hsk ? 'ts-hsk-mode' : ''; ?>">
        
        <?php if ($is_hsk): ?>
        <!-- ============================================================ -->
        <!-- HSK SPECIFIC LANDING PAGE                                    -->
        <!-- ============================================================ -->
        <div class="ts-hsk-hero">
            <div class="ts-hsk-hero-bg"></div>
            <div class="ts-hsk-hero-content">
                <div class="ts-hsk-badge">Chẩn đoán năng lực Hán Ngữ</div>
                <h1 class="ts-hsk-title"><?php echo esc_html($exam->title); ?></h1>
                <p class="ts-hsk-subtitle">Trải nghiệm bài thi chuẩn quốc tế. Hệ thống chấm điểm tự động, phân tích lỗi sai chi tiết.</p>
                
                <div class="ts-hsk-metrics">
                    <div class="ts-hsk-metric">
                        <span class="ts-hsk-metric-icon">⏱️</span>
                        <span class="ts-hsk-metric-val"><?php echo esc_html($duration_label); ?></span>
                    </div>
                    <div class="ts-hsk-metric">
                        <span class="ts-hsk-metric-icon">📝</span>
                        <span class="ts-hsk-metric-val"><?php echo esc_html($questions_label); ?></span>
                    </div>
                    <div class="ts-hsk-metric">
                        <span class="ts-hsk-metric-icon">🎓</span>
                        <span class="ts-hsk-metric-val">Chuẩn Hanban</span>
                    </div>
                </div>

                <div class="ts-lobby-cta ts-hsk-cta-wrap">
                    <button id="ts-lobby-start-btn" class="ts-lobby-start-btn ts-hsk-start-btn" type="button">
                        <span class="ts-lobby-btn-text">BẮT ĐẦU THI THỬ NGAY</span>
                    </button>
                    <p class="ts-hsk-cta-note">Hoàn toàn miễn phí - Biết điểm ngay sau khi nộp bài</p>
                </div>
            </div>
        </div>

        <?php if (!empty($hsk_exams) && count($hsk_exams) > 1): ?>
        <div class="ts-hsk-level-nav">
            <div class="ts-hsk-nav-title">
                <?php echo !empty($hsk_level) ? "Các bài thi HSK {$hsk_level} khác:" : "Chuyển đổi đề thi:"; ?>
            </div>
            <div class="ts-hsk-nav-tabs">
                <?php foreach ($hsk_exams as $h_exam): 
                    $is_active = ($h_exam->id == $exam_id);
                    $h_url = tqp_get_exam_page_url($h_exam->id);
                    // Rút gọn tên hiển thị (xóa chữ Thi thử, Đề thi, và phần - HSK X)
                    $clean_title = str_ireplace(['Thi thử ', 'Đề thi '], '', $h_exam->title);
                    if (!empty($hsk_level)) {
                        $clean_title = trim(preg_replace('/-\s*HSK\s*' . $hsk_level . '\b/i', '', $clean_title));
                    }
                ?>
                    <a href="<?php echo esc_url($h_url); ?>" class="ts-hsk-nav-tab <?php echo $is_active ? 'active' : ''; ?>">
                        <?php echo esc_html($clean_title); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- HSK Features Replaced with Sample Result Image -->
        <div class="ts-lobby-sample-result" style="text-align: center; margin-top: 20px;">
            <img src="https://via.placeholder.com/800x600.png?text=Anh+ket+qua+mau" alt="Ví dụ kết quả bài thi" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e5e7eb;">
            <p style="font-size: 12px; color: #9ca3af; margin-top: 8px; font-style: italic;">*Ảnh minh họa kết quả & đáp án mẫu</p>
        </div>

        <?php if (!empty($exam->description)): ?>
        <div class="ts-hsk-details-section">
            <h2 class="ts-hsk-section-title">Cấu trúc bài thi</h2>
            <div class="ts-hsk-desc-content">
                <?php echo wpautop(wp_kses_post($exam->description)); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ============================================================ -->
        <!-- GENERIC LANDING PAGE (Compact Layout)                        -->
        <!-- ============================================================ -->
        <style>
            .ts-lobby-compact-wrapper { text-align: center; max-width: 800px; margin: 0 auto; padding: 40px 20px; }
            .ts-lobby-compact-title { font-size: 36px; font-weight: 800; color: #111827; margin-bottom: 32px; font-family: 'Inter', sans-serif; line-height: 1.3; }
            .ts-lobby-compact-metrics { display: flex; justify-content: center; align-items: center; gap: 40px; margin-bottom: 40px; }
            .ts-lobby-compact-metric { display: flex; align-items: center; gap: 16px; }
            .ts-lobby-compact-metric-icon { color: #f97316; font-size: 32px; }
            .ts-lobby-compact-metric-val { font-size: 22px; font-weight: 800; color: #1f2937; line-height: 1.2; text-align: left; }
            .ts-lobby-compact-metric-label { font-size: 13px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
            .ts-lobby-compact-divider { width: 1px; height: 48px; background: #e5e7eb; }
            .ts-lobby-compact-btn { background: #f97316; color: white; border: none; padding: 16px 48px; border-radius: 50px; font-size: 18px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 12px; width: auto; min-width: 320px; box-shadow: 0 4px 14px rgba(249, 115, 22, 0.4); text-transform: uppercase; box-sizing: border-box; text-decoration: none; }
            .ts-lobby-compact-btn:hover { background: #ea580c; box-shadow: 0 6px 20px rgba(249, 115, 22, 0.6); color: white; }

            @media (max-width: 768px) {
                .ts-lobby-compact-wrapper { padding: 24px 16px; }
                .ts-lobby-compact-title { font-size: 24px; margin-bottom: 24px; }
                .ts-lobby-compact-metrics { gap: 20px; margin-bottom: 28px; justify-content: center; }
                .ts-lobby-compact-metric { gap: 10px; flex: 1; justify-content: center; flex-direction: column; text-align: center; }
                .ts-lobby-compact-metric-val, .ts-lobby-compact-metric-label { text-align: center; }
                .ts-lobby-compact-metric-icon { font-size: 28px; margin-bottom: 4px; }
                .ts-lobby-compact-metric-val { font-size: 18px; }
                .ts-lobby-compact-metric-label { font-size: 11px; }
                .ts-lobby-compact-divider { height: 40px; }
                .ts-lobby-compact-btn { padding: 14px 20px; font-size: 15px; min-width: 100%; width: 100%; border-radius: 12px; }
            }
        </style>
        <div class="ts-lobby-compact-wrapper">
            <h1 class="ts-lobby-compact-title">
                <?php echo esc_html($exam->title); ?>
            </h1>
            
            <div class="ts-lobby-compact-metrics">
                <!-- Time -->
                <div class="ts-lobby-compact-metric">
                    <div class="ts-lobby-compact-metric-icon"><i class="fa-solid fa-stopwatch"></i></div>
                    <div>
                        <div class="ts-lobby-compact-metric-val"><?php echo esc_html($duration_label); ?></div>
                        <div class="ts-lobby-compact-metric-label">Thời lượng</div>
                    </div>
                </div>
                
                <!-- Divider -->
                <div class="ts-lobby-compact-divider"></div>
                
                <!-- Questions -->
                <div class="ts-lobby-compact-metric">
                    <div class="ts-lobby-compact-metric-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div>
                        <div class="ts-lobby-compact-metric-val"><?php echo esc_html($questions_label); ?></div>
                        <div class="ts-lobby-compact-metric-label">Số câu hỏi</div>
                    </div>
                </div>
            </div>

            <!-- Start Button CTA -->
            <div class="ts-lobby-compact-cta" style="margin-bottom: 32px;">
                <button id="ts-lobby-start-btn" class="ts-lobby-start-btn ts-lobby-compact-btn" type="button">
                    <span class="ts-lobby-btn-text">Bắt đầu làm bài ngay</span>
                    <i class="fa-solid fa-chevron-right" style="font-size: 14px;"></i>
                </button>
            </div>
        </div>

        <!-- Custom Landing Description / Content -->
        <?php if (!empty($exam->description)): ?>
        <div class="ts-lobby-desc-card" style="background: linear-gradient(135deg, rgba(30,41,59,0.7) 0%, rgba(15,23,42,0.7) 100%); border: 1px solid rgba(99,179,255,0.15); border-radius: 16px; padding: 28px 32px; margin-bottom: 24px; color: #cbd5e1; line-height: 1.7; backdrop-filter: blur(10px);">
            <div style="font-size: 16px; font-weight: 700; color: #e2e8f0; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <span>🎯</span> Giới thiệu đề thi
            </div>
            <div class="ts-lobby-desc-content" style="font-size: 14.5px;">
                <?php echo wpautop(wp_kses_post($exam->description)); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rules Card Replaced with Sample Result Image -->
        <div class="ts-lobby-sample-result" style="text-align: center; margin-top: 20px;">
            <img src="https://via.placeholder.com/800x600.png?text=Anh+ket+qua+mau" alt="Ví dụ kết quả bài thi" style="max-width: 100%; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e5e7eb;">
            <p style="font-size: 12px; color: #9ca3af; margin-top: 8px; font-style: italic;">*Ảnh minh họa kết quả & đáp án mẫu</p>
        </div>
        
        <?php endif; ?>
    </div><!-- /.ts-exam-lobby -->

    <!-- ============================================================ -->
    <!-- EXAM WORKSPACE (Hidden until Start button is clicked)        -->
    <!-- ============================================================ -->
    <div id="ts-exam-workspace" style="display: none; opacity: 0;">

        <!-- SPA Header (Hidden as requested) -->
        <header class="ts-spa-header" style="display: none;">
            <div class="ts-spa-header-inner">
                <div class="ts-spa-header-left">
                    <div class="ts-spa-brand" style="cursor: pointer;">
                        <span class="ts-spa-logo-icon">🎯</span>
                        <span class="ts-spa-brand-name">TestSpace</span>
                    </div>
                </div>
                <div class="ts-spa-header-center">
                    <span class="ts-spa-exam-title" id="ts-header-exam-title">Cổng Luyện Thi Trắc Nghiệm Trực Tuyến</span>
                </div>
                <div class="ts-spa-header-right">
                    <div class="ts-spa-mini-stats" id="ts-header-mini-stats">
                        <div class="ts-spa-mini-stat" id="ts-mini-timer-wrap">
                            <span class="ts-spa-mini-icon">⏱</span>
                            <span class="ts-spa-mini-value" id="ts-header-timer">00:00</span>
                        </div>
                        <div class="ts-spa-mini-stat">
                            <span class="ts-spa-mini-icon">📝</span>
                            <span class="ts-spa-mini-value" id="ts-header-progress">0 / 0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ts-spa-progress-track">
                <div class="ts-spa-progress-fill" id="ts-header-progress-fill"></div>
            </div>
        </header><!-- /.ts-spa-header -->

        <!-- Main Exam Layout -->
        <div class="ts-spa-main">
            <!-- Left: Questions + Loading -->
            <div class="ts-spa-content">
                <!-- Loader -->
                <div class="ts-loader" id="ts-loader" style="display: none;">
                    <div class="ts-loader-spinner"></div>
                    <p class="ts-loader-text">Đang chuẩn bị đề thi...</p>
                </div>

                <!-- Exam wrapper (questions rendered here by JS) -->
                <div id="ts-exam-wrap" style="display: none;">
                    
                    <!-- Mobile Sticky Bar -->
                    <div class="ts-mobile-sticky-bar" id="ts-mobile-sticky-bar">
                        <div class="ts-mobile-stat" id="ts-mobile-stat-answered-block" style="cursor: pointer;" title="Nhấn để đi tới bảng câu hỏi">
                            <span class="ts-mobile-stat-label">Số câu đã làm</span>
                            <span class="ts-mobile-stat-val" id="ts-mobile-answered">0/0</span>
                        </div>
                        <div class="ts-mobile-stat">
                            <span class="ts-mobile-stat-label">Thời gian còn lại</span>
                            <span class="ts-mobile-stat-val" id="ts-mobile-timer">00:00</span>
                        </div>
                        <button class="ts-mobile-submit-btn" id="ts-mobile-submit-btn" type="button">NỘP BÀI</button>
                    </div>

                    <!-- Main 70/30 Layout -->
                    <div class="ts-main-layout">
                        <!-- Left Column — Questions -->
                        <div class="ts-content-column">
                            <div id="ts-questions-area" class="ts-questions-area"></div>
                        </div>

                        <!-- Right Column — Sidebar -->
                        <div class="ts-sidebar-column">
                            <div class="ts-sidebar-card ts-sticky-sidebar">
                                <!-- Timer -->
                                <div class="ts-timer-section">
                                    <div class="ts-timer-label">Thời gian còn lại</div>
                                    <div class="ts-timer-display" id="ts-timer-display">00:00</div>
                                    <div class="ts-timer-bar">
                                        <div class="ts-timer-bar-fill" id="ts-timer-bar-fill"></div>
                                    </div>
                                </div>

                                <!-- Question Palette -->
                                <div class="ts-palette-section">
                                    <div class="ts-section-title">Bảng câu hỏi</div>
                                    <div class="ts-palette-grid" id="ts-palette-grid"></div>
                                    <div class="ts-palette-legend">
                                        <span class="ts-legend-item"><span class="ts-legend-dot unanswered"></span>Chưa làm</span>
                                        <span class="ts-legend-item"><span class="ts-legend-dot answered"></span>Đã chọn</span>
                                        <span class="ts-legend-item"><span class="ts-legend-dot flagged"></span>Xem lại</span>
                                    </div>
                                </div>

                                <!-- Stats Box -->
                                <div class="ts-stats-grid">
                                    <div class="ts-stat-item">
                                        <div class="ts-stat-value success" id="ts-stat-answered">0</div>
                                        <div class="ts-stat-label">Đã làm</div>
                                    </div>
                                    <div class="ts-stat-item">
                                        <div class="ts-stat-value muted" id="ts-stat-unanswered">0</div>
                                        <div class="ts-stat-label">Chưa làm</div>
                                    </div>
                                    <div class="ts-stat-item">
                                        <div class="ts-stat-value warning" id="ts-stat-flagged">0</div>
                                        <div class="ts-stat-label">Xem lại</div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button class="ts-submit-btn" id="ts-submit-btn" type="button">NỘP BÀI THI</button>
                            </div>
                        </div>
                    </div>
                </div><!-- /#ts-exam-wrap -->

                <!-- Result Dashboard (Ẩn ban đầu) -->
                <div class="ts-result-container" id="ts-result-container" style="display: none;">
                    <!-- Result Header with Score Ring -->
                    <div class="ts-result-header">
                        <div class="ts-result-badge" id="ts-result-badge-text">Hoàn thành bài thi</div>
                        <h2 class="ts-result-title" id="ts-result-exam-title"></h2>
                        <div id="ts-result-total-score-text" style="display:none; margin-top:10px; font-size:16px; font-weight:600; color:rgba(255,255,255,0.95); background:rgba(255,255,255,0.15); display:inline-block; padding:6px 16px; border-radius:20px;"></div>
                        
                        <!-- Score Progress Ring -->
                        <div class="ts-score-ring-wrap">
                            <div class="ts-score-ring-container">
                                <svg class="ts-score-ring-svg" viewBox="0 0 130 130">
                                    <circle class="ts-score-ring-bg" cx="65" cy="65" r="60"></circle>
                                    <circle class="ts-score-ring-progress" id="ts-score-ring" cx="65" cy="65" r="60"></circle>
                                </svg>
                                <div class="ts-score-ring-text">
                                    <span class="ts-score-ring-number" id="ts-score-ring-number">0%</span>
                                    <span class="ts-score-ring-label">Tỷ lệ đúng</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom HTML Result (for score_range & personality) -->
                    <div id="ts-final-result-html" style="display:none; padding: 20px; background: #fff; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0;"></div>

                    <!-- Stats Grid -->
                    <div class="ts-result-stats">
                        <div class="ts-result-stat-card">
                            <div class="ts-result-stat-value correct" id="ts-result-correct">0</div>
                            <div class="ts-result-stat-label">Câu đúng</div>
                        </div>
                        <div class="ts-result-stat-card">
                            <div class="ts-result-stat-value incorrect" id="ts-result-incorrect">0</div>
                            <div class="ts-result-stat-label">Câu sai</div>
                        </div>
                        <div class="ts-result-stat-card">
                            <div class="ts-result-stat-value time" id="ts-result-time">--:--</div>
                            <div class="ts-result-stat-label">Thời gian</div>
                        </div>
                    </div>

                    <!-- Review Section -->
                    <div class="ts-review-section" id="ts-review-section-wrap">
                        <div class="ts-review-section-title" style="display:flex; justify-content:space-between; align-items:center;">
                            <span>Chi tiết đáp án & Giải thích</span>
                            <button type="button" id="ts-toggle-review-btn" class="ts-toggle-review-btn" style="display:none; font-size:13px; padding:6px 12px; border-radius:6px; background:#f1f5f9; border:1px solid #cbd5e1; cursor:pointer; color:#475569; font-weight:600; transition:all 0.2s;">Hiển thị ▾</button>
                        </div>
                        <div class="ts-review-list" id="ts-review-list"></div>
                    </div>
                </div><!-- /#ts-result-container -->

            </div><!-- /.ts-spa-content -->
        </div><!-- /.ts-spa-main -->

        <!-- Modal Quiz Locker -->
        <style>
            @media (max-width: 480px) {
                #ts-lock-modal .ts-modal-card { padding: 15px 12px !important; }
                #ts-lock-modal .title { font-size: 16px !important; margin-bottom: 5px !important; }
                #ts-lock-modal .title br { display: none; }
                #ts-lock-modal .note { font-size: 12px !important; margin-bottom: 12px !important; }
                .ts-locker-form-group { 
                    margin: 10px 0 !important; 
                    gap: 6px !important; 
                    flex-wrap: nowrap !important; 
                }
                #ts-unlock-code-input-top, #ts-unlock-code-input-bottom { 
                    max-width: unset !important; 
                    flex: 1; 
                    min-width: 0; 
                    padding: 10px 10px !important; 
                    font-size: 13px !important; 
                }
                #ts-unlock-submit-btn-top, #ts-unlock-submit-btn-bottom { 
                    width: auto !important; 
                    padding: 10px 14px !important; 
                    font-size: 12px !important; 
                    flex-shrink: 0; 
                }
            }
        </style>
        <div class="ts-modal-overlay" id="ts-lock-modal" style="display: none;">
            <div class="ts-modal-card" style="max-width: 650px; width: 95%; background: white; border-radius: 12px; padding: 25px 20px; color: #333; text-align: center; margin: auto; position: relative;">
                <span id="ts-lock-modal-close" style="position: absolute; top: 12px; right: 18px; font-size: 26px; cursor: pointer; color: #888; line-height: 1; font-weight: bold;">&times;</span>
                <h2 class="title" style="color: red; margin-bottom: 10px; font-size: 20px; font-weight: bold; line-height: 1.4;">Bạn ơi!!! Để xem được kết quả <br>bạn vui lòng làm nhiệm vụ nhỏ xíu này nha</h2>
                <p class="note" style="color: #0066ff; font-weight: bold; margin-bottom: 20px; font-size: 14px;">LƯU Ý: Không sử dụng VPN hoặc 1.1.1.1 khi làm nhiệm vụ</p>
                
                <div class="ts-task-box" style="margin-bottom: 0;">
                    <span class="ts-task-tag-badge" style="display:none;">🏷 Nhiệm vụ: <strong id="ts-task-tag-name"></strong></span>
                    <p class="ts-task-hint" id="ts-task-hint-text" style="display:none;">Nhấp vào nút dưới đây để nhận hướng dẫn lấy mã:</p>
                    <button type="button" id="ts-portal-task-btn" class="ts-task-link-btn" style="display:none; border:none; cursor:pointer; font-family:inherit; width: 100%;">
                        Làm nhiệm vụ lấy mã →
                    </button>
                    
                    <div id="ts-task-loading-spinner" style="display: none; margin: 30px 0;">
                        <svg viewBox="0 0 50 50" style="width: 40px; height: 40px; animation: ts-spin 1s linear infinite; margin: 0 auto; display: block;">
                            <circle cx="25" cy="25" r="20" fill="none" stroke="#222" stroke-width="4" stroke-dasharray="31.4 31.4" stroke-linecap="round"></circle>
                        </svg>
                        <style>@keyframes ts-spin { 100% { transform: rotate(360deg); } }</style>
                    </div>
                    
                    <div style="margin: 20px 0;" class="ts-locker-form-group">
                        <div style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="ts-unlock-code-input-top" placeholder="Nhập mã vừa kiếm được vào đây" style="padding: 10px 15px; width: 100%; max-width: 260px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; outline: none; margin: 0;">
                            <button type="button" id="ts-unlock-submit-btn-top" style="background: #222; color: white; border-radius: 99px; border: none; padding: 10px 24px; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.2s; white-space: nowrap; margin: 0;">NHẬN KẾT QUẢ</button>
                        </div>
                        <div id="ts-unlock-error-top" style="display: none; color: red; margin-top: 8px; min-height: 20px;"></div>
                    </div>
                    
                    <div id="ts-task-instructions" style="display:none; text-align: left; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1;"></div>
                </div>

                <div style="margin: 20px 0;" class="ts-locker-form-group">
                    <div style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="ts-unlock-code-input-bottom" placeholder="Nhập mật mã..." style="padding: 10px 15px; width: 100%; max-width: 260px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; outline: none; margin: 0;">
                        <button type="button" id="ts-unlock-submit-btn-bottom" style="background: #222; color: white; border-radius: 99px; border: none; padding: 10px 24px; font-size: 14px; font-weight: bold; cursor: pointer; transition: 0.2s; white-space: nowrap; margin: 0;">XÁC NHẬN!</button>
                    </div>
                    <div id="ts-unlock-error-bottom" style="display: none; color: red; margin-top: 8px; min-height: 20px;"></div>
                </div>
            </div>
        </div>

        <!-- Modal Xác Nhận Thoát Phòng Thi -->
        <div class="ts-modal-overlay" id="ts-exit-confirm-modal" style="display: none;">
            <div class="ts-modal-card">
                <div class="ts-modal-icon warning">⚠️</div>
                <h3 class="ts-modal-title">Thoát khỏi phòng thi?</h3>
                <p class="ts-modal-desc">Bạn có chắc chắn muốn rời khỏi phòng thi? Tiến trình làm bài hiện tại của bạn sẽ bị hủy bỏ hoàn toàn.</p>
                
                <div class="ts-modal-buttons">
                    <button id="ts-exit-cancel-btn" class="ts-modal-btn secondary">Ở lại làm tiếp</button>
                    <button id="ts-exit-confirm-btn" class="ts-modal-btn primary danger">Xác nhận thoát</button>
                </div>
            </div>
        </div>

        <!-- Modal Xác Nhận Nộp Bài Thi -->
        <div class="ts-modal-overlay" id="ts-submit-confirm-modal" style="display: none;">
            <div class="ts-modal-card">
                <h3 class="ts-modal-title">Nộp bài thi?</h3>
                <p class="ts-modal-desc" id="ts-submit-confirm-desc">Bạn có chắc chắn muốn nộp bài thi để chấm điểm? Hãy chắc chắn rằng bạn đã kiểm tra kỹ toàn bộ câu trả lời.</p>
                
                <div class="ts-modal-buttons">
                    <button type="button" id="ts-submit-cancel-btn" class="ts-modal-btn secondary">Kiểm tra lại</button>
                    <button type="button" id="ts-submit-confirm-btn" class="ts-modal-btn primary">Xác nhận nộp</button>
                </div>
            </div>
        </div>

        <!-- SPA Footer -->
        <footer class="ts-spa-footer">
            <div class="ts-spa-footer-inner">
                <div class="ts-spa-footer-left">
                    <span>© <?php echo date('Y'); ?> <strong>TracnghiemAZ</strong>. Hệ thống thi trắc nghiệm trực tuyến.</span>
                </div>
                <div class="ts-spa-footer-center">
                    <span>Email hỗ trợ: <strong>tracnghiemaz@gmail.com</strong></span>
                </div>
            </div>
        </footer>

    </div><!-- /#ts-exam-workspace -->

</div> <!-- Closing ts-app-root -->

    <!-- SEO Noscript -->
    <?php echo $seo_noscript; ?>
    
    <?php
    return ob_get_clean();
}

// Đăng ký Shortcode [traffic_quiz_button id="X" text="Bắt đầu làm bài thi"]
add_shortcode('traffic_quiz_button', 'tqp_render_quiz_button_shortcode');
function tqp_render_quiz_button_shortcode($atts) {
    $args = shortcode_atts([
        'id' => 0,
        'text' => 'BẮT ĐẦU NGAY'
    ], $atts);

    $exam_id = intval($args['id']);
    if ($exam_id <= 0) {
        return '<div style="color:red;padding:10px;">Lỗi: ID đề thi không hợp lệ!</div>';
    }

    $cpt_post_id = tqp_get_exam_cpt_post_id($exam_id);
    if (!$cpt_post_id) {
        return '<div style="color:red;padding:10px;">Lỗi: Không tìm thấy liên kết phòng thi cho đề ID #' . $exam_id . '!</div>';
    }

    $permalink = get_permalink($cpt_post_id);
    
    // Nạp style tối thiểu cho nút bấm (dùng chung style của plugin hoặc inline style cao cấp)
    ob_start();
    ?>
    <style>
        .ts-cta-btn-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 25px 0;
            width: 100%;
        }
        .ts-cta-redirect-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none !important;
            padding: 14px 36px;
            border-radius: 50px;
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            width: auto;
            min-width: 220px;
            text-align: center;
        }
        .ts-cta-redirect-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 16px 48px rgba(234, 88, 12, 0.65);
            background: linear-gradient(135deg, #fb923c 0%, #f97316 50%, #ea580c 100%);
        }
        .ts-cta-redirect-btn:active {
            transform: translateY(0) scale(0.99);
        }
        .ts-cta-rocket-icon {
            margin-right: 12px;
            font-size: 20px;
            display: inline-block;
            animation: tsRocketWiggle 2s infinite ease-in-out;
        }
        @keyframes tsRocketWiggle {
            0%, 100% { transform: rotate(0deg) translateY(0); }
    </style>
    <div class="ts-cta-btn-wrap">
        <a href="<?php echo esc_url($permalink); ?>" class="ts-cta-redirect-btn">
            <?php echo esc_html($args['text']); ?>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

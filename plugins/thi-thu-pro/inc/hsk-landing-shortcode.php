<?php
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_hsk_level_landing', 'tqp_render_hsk_level_landing');
add_shortcode('tqp_hsk_home', 'tqp_render_hsk_home');

function tqp_render_hsk_level_landing($atts) {
    global $wpdb;
    
    $args = shortcode_atts([
        'level' => '1'
    ], $atts);

    $level = intval($args['level']);
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Fetch exams for this level
    $exams = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id ASC",
        '%HSK ' . $level . '%'
    ));

    // Determine all available levels based on DB
    $all_levels = [1, 2, 3, 4, 5, 6];

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <div class="ts-hsk-listing-page ts-hsk-mode">
        <!-- Hero Section -->
        <div class="ts-hsk-hero ts-hsk-hero-listing">
            <div class="ts-hsk-hero-bg"></div>
            <div class="ts-hsk-hero-content" style="max-width: 1000px; display: flex; align-items: center; justify-content: space-between; gap: 40px; text-align: left; flex-wrap: wrap;">
                <div class="ts-hsk-hero-text" style="flex: 1; min-width: 300px;">
                    <div class="ts-hsk-badge">Tổng hợp đề thi</div>
                    <h1 class="ts-hsk-title" style="font-size: 48px; margin-bottom: 24px;">ĐỀ THI THỬ <span style="color: #e11d48;">HSK <?php echo $level; ?></span></h1>
                    <p class="ts-hsk-subtitle" style="font-size: 16px;">
                        Thi thử HSK <?php echo $level; ?> là bước đi quan trọng giúp bạn làm quen với cấu trúc đề thi chính thức. Tại đây, bạn không chỉ được rèn luyện các kỹ năng Nghe và Đọc mà còn có cơ hội đánh giá chính xác trình độ của mình qua hệ thống chấm điểm tự động.
                    </p>
                </div>
                <div class="ts-hsk-hero-illustration" style="flex: 0 0 auto; text-align: center;">
                    <div class="ts-hsk-mascot" style="font-size: 140px; line-height: 1; filter: drop-shadow(0 20px 30px rgba(225,29,72,0.2));">
                        🐝📚
                    </div>
                </div>
            </div>
        </div>

        <!-- Level Navigation -->
        <div class="ts-hsk-level-nav" style="justify-content: center; background: #fff; padding: 20px;">
            <div class="ts-hsk-nav-tabs">
                <?php foreach ($all_levels as $l): ?>
                    <a href="<?php echo esc_url(home_url('/thi-thu-hsk-' . $l . '/')); ?>" class="ts-hsk-nav-tab <?php echo ($l == $level) ? 'active' : ''; ?>">
                        HSK <?php echo $l; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid Section -->
        <div class="ts-hsk-grid-section">
            <div class="ts-hsk-grid-header">
                <h2>Danh sách đề thi thử HSK <?php echo $level; ?></h2>
            </div>
            
            <?php if (!empty($exams)): 
                // Group exams by base name
                $grouped_exams = [];
                $table_groups = $wpdb->prefix . 'tq_groups';
                $table_questions = $wpdb->prefix . 'tq_questions';

                foreach ($exams as $exam) {
                    $title_lower = mb_strtolower($exam->title);
                    $skill = 'full';
                    if (strpos($title_lower, 'nghe') !== false) {
                        $skill = 'nghe';
                    } elseif (strpos($title_lower, 'đọc') !== false || strpos($title_lower, 'doc') !== false) {
                        $skill = 'doc';
                    }
                    
                    // Base name (e.g. "Đề 1 - HSK 2")
                    $base_name = trim(preg_replace('/-(?:\s)*(nghe|đọc|doc)\b/iu', '', $exam->title));
                    
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

                    // Calculate questions for this exam
                    $total_q = 0;
                    $groups = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table_groups WHERE exam_id = %d", $exam->id));
                    if ($groups) {
                        foreach ($groups as $g) {
                            $total_q += $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_questions WHERE group_id = %d", $g->id));
                        }
                    }
                    
                    $grouped_exams[$base_name]['total_q'] += $total_q;
                    $grouped_exams[$base_name]['duration'] += intval($exam->duration_minutes);
                }
            ?>
            <div class="ts-hsk-exams-grid">
                <?php foreach ($grouped_exams as $g_exam): ?>
                <div class="ts-hsk-exam-card">
                    <div class="ts-hsk-ec-header">
                        <div class="ts-hsk-ec-badge">HSK <?php echo $level; ?></div>
                        <h3 class="ts-hsk-ec-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                    </div>
                    <div class="ts-hsk-ec-body">
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label">Số câu:</span>
                            <span class="ts-hsk-ec-val"><?php echo $g_exam['total_q']; ?> câu</span>
                        </div>
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label">Thời gian:</span>
                            <span class="ts-hsk-ec-val"><?php echo $g_exam['duration']; ?> phút</span>
                        </div>
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label">Kỹ năng:</span>
                            <span class="ts-hsk-ec-val">Nghe • Đọc</span>
                        </div>
                    </div>
                    <div class="ts-hsk-ec-footer">
                        <?php if ($g_exam['nghe'] && $g_exam['doc']): ?>
                            <div class="ts-hsk-dual-btns">
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['doc']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-doc">PHẦN ĐỌC</a>
                                <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['nghe']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-nghe">PHẦN NGHE</a>
                            </div>
                        <?php else: 
                            // Fallback to single exam (either full, or missing one part)
                            $target_exam = $g_exam['full'] ?: ($g_exam['nghe'] ?: $g_exam['doc']);
                            $url = tqp_get_exam_page_url($target_exam->id);
                        ?>
                            <a href="<?php echo esc_url($url); ?>" class="ts-hsk-ec-btn">LÀM BÀI NGAY</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="ts-hsk-empty-state">
                    <p>Hiện chưa có đề thi nào cho HSK <?php echo $level; ?>. Hãy quay lại sau nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- SEO Content Section -->
        <div class="ts-hsk-seo-section">
            <?php echo do_shortcode('[block id="thi-thu-hsk-' . $level . '"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------
// SHORTCODE TRANG CHỦ HSK: [tqp_hsk_home]
// ---------------------------------------------------------
function tqp_render_hsk_home($atts) {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <div class="ts-hsk-home-page">
        
        <!-- Hero Section -->
        <div class="ts-hh-hero">
            <div class="ts-hh-hero-left">
                <h1 class="ts-hh-title">Thi thử HSK Online</h1>
                <h2 class="ts-hh-subtitle">Đánh giá năng lực – Chinh phục HSK!</h2>
                <p class="ts-hh-desc">Hệ thống thi thử HSK từ cấp 1 đến cấp 6 với đề thi chuẩn cấu trúc bám sát kỳ thi thật, giúp bạn làm quen, ôn tập và đạt kết quả cao.</p>
                
                <div class="ts-hh-features">
                    <div class="ts-hh-feat">
                        <span class="feat-icon">📝</span>
                        <div class="feat-text">
                            <strong>Đề thi chuẩn HSK</strong>
                            <span>Cập nhật theo cấu trúc mới nhất</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon">📊</span>
                        <div class="feat-text">
                            <strong>Chấm điểm tự động</strong>
                            <span>Kết quả chi tiết, đánh giá năng lực chính xác</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon">🎯</span>
                        <div class="feat-text">
                            <strong>Ôn tập hiệu quả</strong>
                            <span>Luyện tập mọi lúc, nâng cao trình độ</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ts-hh-hero-right">
                <!-- Using a pure CSS layout or emojis instead of the image, as we don't have the original asset -->
                <div class="ts-hh-illustration">
                    <div class="ts-hh-monitor">
                        <div class="ts-hh-monitor-screen">
                            <span class="ts-hh-hsk-text">HSK</span>
                        </div>
                        <div class="ts-hh-monitor-base"></div>
                    </div>
                    <div class="ts-hh-books">📚</div>
                    <div class="ts-hh-pencil">✏️</div>
                </div>
            </div>
        </div>

        <!-- 6 Cấp Độ Section -->
        <div class="ts-hh-levels-section">
            <h2 class="ts-hh-section-title">Ôn luyện HSK 6 cấp bậc</h2>
            <div class="ts-hh-levels-grid">
                <?php
                $level_colors = [
                    1 => '#f59e0b', // Yellow
                    2 => '#10b981', // Green
                    3 => '#f97316', // Orange
                    4 => '#ef4444', // Red
                    5 => '#8b5cf6', // Purple
                    6 => '#e11d48'  // Rose
                ];
                for ($l = 1; $l <= 6; $l++): ?>
                <a href="<?php echo esc_url(home_url('/thi-thu-hsk-' . $l . '/')); ?>" class="ts-hh-level-card ts-hh-card-<?php echo $l; ?>" style="display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 16px; padding: 20px;">
                    <div class="ts-hh-level-icon" style="color: <?php echo $level_colors[$l]; ?>; margin: 0; font-size: 40px; line-height: 1;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div style="text-align: left;">
                        <div class="ts-hh-level-top" style="margin-bottom: 4px;">汉语水平考试</div>
                        <div class="ts-hh-level-main" style="color: <?php echo $level_colors[$l]; ?>; margin: 0; line-height: 1.2;">HSK <?php echo $l; ?></div>
                    </div>
                </a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Danh sách đề thi mới nhất -->
        <div class="ts-hh-latest-section">
            <h2 class="ts-hh-section-title">Danh sách các đề thi mới nhất</h2>
            
            <?php
            for ($l = 1; $l <= 6; $l++) {
                // Fetch latest exams for this level
                $exams = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id DESC LIMIT 20",
                    '%HSK ' . $l . '%'
                ));
                
                if (!empty($exams)) {
                    // Group exams
                    $grouped_exams = [];
                    foreach ($exams as $exam) {
                        $title_lower = mb_strtolower($exam->title);
                        $skill = 'full';
                        if (strpos($title_lower, 'nghe') !== false) {
                            $skill = 'nghe';
                        } elseif (strpos($title_lower, 'đọc') !== false || strpos($title_lower, 'doc') !== false) {
                            $skill = 'doc';
                        }
                        
                        $base_name = trim(preg_replace('/-(?:\s)*(nghe|đọc|doc)\b/iu', '', $exam->title));
                        
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
                        
                        $grouped_exams[$base_name]['total_q'] += $total_q;
                        $grouped_exams[$base_name]['duration'] += intval($exam->duration_minutes);
                    }
                    
                    // Slice to 3 exams max
                    $top_3_exams = array_slice($grouped_exams, 0, 3);
                    
                    if (!empty($top_3_exams)) {
                        echo '<h3 class="ts-hh-level-heading" style="color: '.$level_colors[$l].';">HSK '.$l.'</h3>';
                        echo '<div class="ts-hsk-exams-grid">'; // Re-use the grid class from level-landing
                        foreach ($top_3_exams as $g_exam) {
                            ?>
                            <div class="ts-hsk-exam-card">
                                <div class="ts-hsk-ec-header">
                                    <div class="ts-hsk-ec-badge" style="background: <?php echo $level_colors[$l]; ?>;">HSK <?php echo $l; ?></div>
                                    <h3 class="ts-hsk-ec-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                                </div>
                                <div class="ts-hsk-ec-body">
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label">Số câu:</span>
                                        <span class="ts-hsk-ec-val"><?php echo $g_exam['total_q']; ?> câu</span>
                                    </div>
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label">Thời gian:</span>
                                        <span class="ts-hsk-ec-val"><?php echo $g_exam['duration']; ?> phút</span>
                                    </div>
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label">Kỹ năng:</span>
                                        <span class="ts-hsk-ec-val">Nghe • Đọc</span>
                                    </div>
                                </div>
                                <div class="ts-hsk-ec-footer">
                                    <?php if ($g_exam['nghe'] && $g_exam['doc']): ?>
                                        <div class="ts-hsk-dual-btns">
                                            <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['doc']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-doc" style="background: <?php echo $level_colors[$l]; ?>; opacity: 0.9;">PHẦN ĐỌC</a>
                                            <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['nghe']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-nghe" style="background: <?php echo $level_colors[$l]; ?>;">PHẦN NGHE</a>
                                        </div>
                                    <?php else: 
                                        $target_exam = $g_exam['full'] ?: ($g_exam['nghe'] ?: $g_exam['doc']);
                                        $url = tqp_get_exam_page_url($target_exam->id);
                                    ?>
                                        <a href="<?php echo esc_url($url); ?>" class="ts-hsk-ec-btn" style="background: <?php echo $level_colors[$l]; ?>;">LÀM BÀI NGAY</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                        echo '</div>';
                        echo '<div style="text-align: right; margin-top: 10px; margin-bottom: 30px;"><a href="'.esc_url(home_url('/thi-thu-hsk-' . $l . '/')).'" style="color: '.$level_colors[$l].'; font-weight: 600; text-decoration: underline;">Xem thêm đề thi HSK '.$l.' »</a></div>';
                    }
                }
            }
            ?>
        </div>

        <!-- UX Block cho SEO -->
        <div class="ts-hh-seo-block">
            <?php echo do_shortcode('[block id="thi-thu-hsk"]'); ?>
        </div>
        
    </div>
    <?php
    return ob_get_clean();
}

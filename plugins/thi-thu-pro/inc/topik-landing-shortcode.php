<?php
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_topik_level_landing', 'tqp_render_topik_level_landing');
add_shortcode('tqp_topik_home', 'tqp_render_topik_home');

function tqp_render_topik_level_landing($atts) {
    global $wpdb;
    
    $args = shortcode_atts([
        'level' => 'I'
    ], $atts);

    $level = strtoupper(trim($args['level']));
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Fetch exams for this level
    $exams = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id ASC",
        '%TOPIK ' . $level . ' -%'
    ));

    // Determine all available levels based on DB
    $all_levels = ["I", "II"];

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <!-- FontAwesome for fresh icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <div class="ts-hsk-listing-page ts-hsk-mode">
        <!-- Hero Section -->
        <div class="ts-hsk-hero ts-hsk-hero-listing">
            <div class="ts-hsk-hero-bg"></div>
            <div class="ts-hsk-hero-content" style="max-width: 1000px; display: flex; align-items: center; justify-content: space-between; gap: 40px; text-align: left; flex-wrap: wrap;">
                <div class="ts-hsk-hero-text" style="flex: 1; min-width: 300px;">
                    <div class="ts-hsk-badge">Tổng hợp đề thi</div>
                    <h1 class="ts-hsk-title" style="font-size: 48px; margin-bottom: 24px;">ĐỀ THI THỬ <span style="color: #e11d48;">TOPIK <?php echo $level; ?></span></h1>
                    <p class="ts-hsk-subtitle" style="font-size: 16px;">
                        Thi thử TOPIK <?php echo $level; ?> là bước đi quan trọng giúp bạn làm quen với cấu trúc đề thi chính thức. Tại đây, bạn không chỉ được rèn luyện các kỹ năng Nghe và Đọc mà còn có cơ hội đánh giá chính xác trình độ của mình qua hệ thống chấm điểm tự động.
                    </p>
                </div>
                <div class="ts-hsk-hero-illustration" style="flex: 0 0 auto; text-align: center;">
                    <div class="ts-hsk-mascot" style="font-size: 140px; line-height: 1; filter: drop-shadow(0 20px 30px rgba(225,29,72,0.3)); color: #e11d48;">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Level Navigation -->
        <div class="ts-hsk-level-nav" style="justify-content: center; background: #fff; padding: 20px;">
            <div class="ts-hsk-nav-tabs">
                <?php foreach ($all_levels as $l): 
                    $url_slug = ($l == 'I') ? '1' : '2';
                ?>
                    <a href="<?php echo esc_url(home_url('/thi-thu-topik-' . $url_slug . '/')); ?>" class="ts-hsk-nav-tab <?php echo ($l == $level) ? 'active' : ''; ?>">
                        TOPIK <?php echo $l; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid Section -->
        <div class="ts-hsk-grid-section">
            <div class="ts-hsk-grid-header">
                <h2>Danh sách đề thi thử TOPIK <?php echo $level; ?></h2>
            </div>
            
            <?php if (!empty($exams)): 
                // Group exams by base name
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
                    
                    // Base name (e.g. "Đề 1 - TOPIK 2")
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
                        <div class="ts-hsk-ec-badge">TOPIK <?php echo $level; ?></div>
                        <h3 class="ts-hsk-ec-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                    </div>
                    <div class="ts-hsk-ec-body">
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label"><i class="fa-solid fa-list-ol"></i> Số câu:</span>
                            <span class="ts-hsk-ec-val"><?php echo $g_exam['total_q']; ?> câu</span>
                        </div>
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label"><i class="fa-regular fa-clock"></i> Thời gian:</span>
                            <span class="ts-hsk-ec-val"><?php echo $g_exam['duration']; ?> phút</span>
                        </div>
                        <div class="ts-hsk-ec-row">
                            <span class="ts-hsk-ec-label"><i class="fa-solid fa-layer-group"></i> Kỹ năng:</span>
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
                    <p>Hiện chưa có đề thi nào cho TOPIK <?php echo $level; ?>. Hãy quay lại sau nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- SEO Content Section -->
        <div class="ts-hsk-seo-section">
            <?php echo do_shortcode('[block id="thi-thu-topik-' . strtolower($level) . '"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------
// SHORTCODE TRANG CHỦ TOPIK: [tqp_topik_home]
// ---------------------------------------------------------
function tqp_render_topik_home($atts) {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <!-- FontAwesome for fresh icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div class="ts-hsk-home-page">
        
        <!-- Hero Section -->
        <div class="ts-hh-hero">
            <div class="ts-hh-hero-left">
                <h1 class="ts-hh-title">Thi thử TOPIK Online</h1>
                <h2 class="ts-hh-subtitle">Đánh giá năng lực – Chinh phục TOPIK!</h2>
                <p class="ts-hh-desc">Hệ thống thi thử TOPIK từ cấp 1 đến cấp 6 với đề thi chuẩn cấu trúc bám sát kỳ thi thật, giúp bạn làm quen, ôn tập và đạt kết quả cao.</p>
                
                <div class="ts-hh-features">
                    <div class="ts-hh-feat">
                        <span class="feat-icon" style="color: #3b82f6;"><i class="fa-solid fa-file-signature"></i></span>
                        <div class="feat-text">
                            <strong>Đề thi chuẩn TOPIK</strong>
                            <span>Cập nhật theo cấu trúc mới nhất</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon" style="color: #10b981;"><i class="fa-solid fa-chart-pie"></i></span>
                        <div class="feat-text">
                            <strong>Chấm điểm tự động</strong>
                            <span>Kết quả chi tiết, đánh giá năng lực chính xác</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon" style="color: #f59e0b;"><i class="fa-solid fa-ranking-star"></i></span>
                        <div class="feat-text">
                            <strong>Ôn tập hiệu quả</strong>
                            <span>Luyện tập mọi lúc, nâng cao trình độ</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ts-hh-hero-right">
                <div class="ts-hh-illustration">
                    <div class="ts-hh-monitor" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
                        <div class="ts-hh-monitor-screen" style="background: linear-gradient(135deg, #4f46e5, #ec4899); display: flex; align-items: center; justify-content: center;">
                            <span class="ts-hh-topik-text" style="color: #fff; font-weight: bold; font-size: 32px; letter-spacing: 2px;"><i class="fa-solid fa-globe"></i> TOPIK</span>
                        </div>
                        <div class="ts-hh-monitor-base" style="background: #cbd5e1;"></div>
                    </div>
                    <div class="ts-hh-books" style="color: #8b5cf6;"><i class="fa-solid fa-book-bookmark"></i></div>
                    <div class="ts-hh-pencil" style="color: #fb923c;"><i class="fa-solid fa-marker"></i></div>
                </div>
            </div>
        </div>

        <!-- Các Cấp Độ Section -->
        <div class="ts-hh-levels-section">
            <h2 class="ts-hh-section-title">Ôn luyện TOPIK</h2>
            <div class="ts-hh-levels-grid">
                <?php
                $level_colors = [
                    'I' => ['bg' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 'text' => '#d97706', 'icon' => 'fa-book-open-reader'],
                    'II' => ['bg' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)', 'text' => '#059669', 'icon' => 'fa-medal']
                ];
                $topik_levels = ['I', 'II'];
                foreach ($topik_levels as $l): 
                    $url_slug = ($l == 'I') ? '1' : '2';
                    $cfg = $level_colors[$l];
                ?>
                <a href="<?php echo esc_url(home_url('/thi-thu-topik-' . $url_slug . '/')); ?>" class="ts-hh-level-card ts-hh-card-<?php echo strtolower($l); ?>" style="display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 16px; padding: 20px; border-top: 4px solid <?php echo $cfg['text']; ?>; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-radius: 16px;">
                    <div class="ts-hh-level-icon" style="color: <?php echo $cfg['text']; ?>; margin: 0; font-size: 40px; line-height: 1;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div style="text-align: left;">
                        <div class="ts-hh-level-top" style="color: #64748b; font-weight: 600; margin-bottom: 4px;">한국어능력시험</div>
                        <div class="ts-hh-level-main" style="color: <?php echo $cfg['text']; ?>; font-size: 28px; margin: 0; line-height: 1.2;">TOPIK <?php echo $l; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Danh sách đề thi mới nhất -->
        <div class="ts-hh-latest-section">
            <h2 class="ts-hh-section-title">Danh sách các đề thi mới nhất</h2>
            
            <?php
            $topik_levels = ['I', 'II'];
            foreach ($topik_levels as $l) {
                // Fetch latest exams for this level
                $exams = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id DESC LIMIT 20",
                    '%TOPIK ' . $l . ' -%'
                ));
                
                if (!empty($exams)) {
                    // Group exams
                    $grouped_exams = [];
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
                        
                        $grouped_exams[$base_name]['total_q'] += $total_q;
                        $grouped_exams[$base_name]['duration'] += intval($exam->duration_minutes);
                    }
                    
                    // Slice to 3 exams max
                    $top_3_exams = array_slice($grouped_exams, 0, 3);
                    
                    if (!empty($top_3_exams)) {
                        echo '<h3 class="ts-hh-level-heading" style="color: '.$level_colors[$l]['text'].';">TOPIK '.$l.'</h3>';
                        echo '<div class="ts-hsk-exams-grid">'; // Re-use the grid class from level-landing
                        foreach ($top_3_exams as $g_exam) {
                            ?>
                            <div class="ts-hsk-exam-card">
                                <div class="ts-hsk-ec-header">
                                    <div class="ts-hsk-ec-badge" style="background: <?php echo $level_colors[$l]['bg']; ?>;">TOPIK <?php echo $l; ?></div>
                                    <h3 class="ts-hsk-ec-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                                </div>
                                <div class="ts-hsk-ec-body">
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label"><i class="fa-solid fa-list-ol"></i> Số câu:</span>
                                        <span class="ts-hsk-ec-val"><?php echo $g_exam['total_q']; ?> câu</span>
                                    </div>
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label"><i class="fa-regular fa-clock"></i> Thời gian:</span>
                                        <span class="ts-hsk-ec-val"><?php echo $g_exam['duration']; ?> phút</span>
                                    </div>
                                    <div class="ts-hsk-ec-row">
                                        <span class="ts-hsk-ec-label"><i class="fa-solid fa-layer-group"></i> Kỹ năng:</span>
                                        <span class="ts-hsk-ec-val">Nghe • Đọc</span>
                                    </div>
                                </div>
                                <div class="ts-hsk-ec-footer">
                                    <?php if ($g_exam['nghe'] && $g_exam['doc']): ?>
                                        <div class="ts-hsk-dual-btns">
                                            <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['doc']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-doc" style="background: <?php echo $level_colors[$l]['bg']; ?>; opacity: 0.9;">PHẦN ĐỌC</a>
                                            <a href="<?php echo esc_url(tqp_get_exam_page_url($g_exam['nghe']->id)); ?>" class="ts-hsk-ec-btn ts-hsk-btn-nghe" style="background: <?php echo $level_colors[$l]['bg']; ?>;">PHẦN NGHE</a>
                                        </div>
                                    <?php else: 
                                        $target_exam = $g_exam['full'] ?: ($g_exam['nghe'] ?: $g_exam['doc']);
                                        $url = tqp_get_exam_page_url($target_exam->id);
                                    ?>
                                        <a href="<?php echo esc_url($url); ?>" class="ts-hsk-ec-btn" style="background: <?php echo $level_colors[$l]['bg']; ?>;">LÀM BÀI NGAY</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        }
                        echo '</div>';
                        $url_slug = ($l == 'I') ? '1' : '2';
                        echo '<div style="text-align: right; margin-top: 10px; margin-bottom: 30px;"><a href="'.esc_url(home_url('/thi-thu-topik-' . $url_slug . '/')).'" style="color: '.$level_colors[$l]['text'].'; font-weight: 600; text-decoration: underline;">Xem thêm đề thi TOPIK '.$l.' »</a></div>';
                    }
                }
            }
            ?>
        </div>

        <!-- UX Block cho SEO -->
        <div class="ts-hh-seo-block">
            <?php echo do_shortcode('[block id="thi-thu-topik"]'); ?>
        </div>
        
    </div>
    <?php
    return ob_get_clean();
}

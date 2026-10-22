<?php
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_jlpt_level_landing', 'tqp_render_jlpt_level_landing');
add_shortcode('tqp_jlpt_home', 'tqp_render_jlpt_home');

function tqp_render_jlpt_level_landing($atts) {
    global $wpdb;
    
    $args = shortcode_atts([
        'level' => '1'
    ], $atts);

    $level = strtoupper(trim($args['level']));
    if(is_numeric($level)) $level = 'N' . $level;
    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Fetch exams for this level
    $exams = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id ASC",
        '%JLPT - ' . $level . '%'
    ));

    // Determine all available levels based on DB
    $all_levels = ['N1', 'N2', 'N3', 'N4', 'N5'];

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="ts-hsk-level-landing ts-hsk-mode">
        <!-- Hero Section -->
        <div class="ts-hsk-hero ts-hsk-hero-listing">
            <div class="ts-hsk-hero-bg"></div>
            <div class="ts-hsk-hero-content" style="max-width: 1000px; display: flex; align-items: center; justify-content: space-between; gap: 40px; text-align: left; flex-wrap: wrap;">
                <div class="ts-hsk-hero-text" style="flex: 1; min-width: 300px;">
                    <div class="ts-hsk-badge">Tổng hợp đề thi</div>
                    <h1 class="ts-hsk-title" style="font-size: 48px; margin-bottom: 24px;">ĐỀ THI THỬ <span style="color: #e11d48;">JLPT <?php echo $level; ?></span></h1>
                    <p class="ts-hsk-subtitle" style="font-size: 16px;">
                        Thi thử JLPT <?php echo $level; ?> là bước đi quan trọng giúp bạn làm quen với cấu trúc đề thi chính thức. Tại đây, bạn không chỉ được rèn luyện các kỹ năng Nghe và Đọc mà còn có cơ hội đánh giá chính xác trình độ của mình qua hệ thống chấm điểm tự động.
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
                    <a href="<?php echo esc_url(home_url('/thi-thu-jlpt/' . strtolower($l) . '/')); ?>" class="ts-hsk-nav-tab <?php echo ($l == $level) ? 'active' : ''; ?>">
                        JLPT <?php echo $l; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grid Section -->
        <div class="ts-hsk-grid-section">
            <div class="ts-hsk-grid-header">
                <h2>Danh sách đề thi thử JLPT <?php echo $level; ?></h2>
            </div>
            
            <?php if (!empty($exams)): 
                // Group exams by base name
                $grouped_exams = [];
                $table_groups = $wpdb->prefix . 'tq_groups';
                $table_questions = $wpdb->prefix . 'tq_questions';

                foreach ($exams as $exam) {
                    $title_lower = mb_strtolower($exam->title);
                    
                    // Regex to extract skills
                    $skills_pattern = '/-(?:\s)*(vocabulary|grammar|reading|listening|từ vựng|ngữ pháp|đọc hiểu|nghe hiểu)\b/iu';
                    if (preg_match($skills_pattern, $exam->title, $matches)) {
                        $skill = ucfirst(strtolower(trim($matches[1]))); // e.g. "Vocabulary"
                    } else {
                        $skill = 'Full';
                    }
                    
                    // Base name (e.g. "JLPT - N5 - Đề 2")
                    $base_name = trim(preg_replace($skills_pattern, '', $exam->title));
                    
                    if (!isset($grouped_exams[$base_name])) {
                        $grouped_exams[$base_name] = [
                            'base_name' => $base_name,
                            'parts' => [],
                            'total_q' => 0,
                            'duration' => 0
                        ];
                    }
                    
                    // Add this part to the parts array
                    $grouped_exams[$base_name]['parts'][] = [
                        'skill' => $skill,
                        'exam' => $exam
                    ];

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
            <style>
                .ts-jlpt-acc-list {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    width: 100%;
                }
                @media (max-width: 1024px) {
                    .ts-jlpt-acc-list {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }
                @media (max-width: 768px) {
                    .ts-jlpt-acc-list {
                        grid-template-columns: 1fr;
                    }
                }
                .ts-jlpt-acc-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                    overflow: hidden;
                    font-family: inherit;
                }
                .ts-jlpt-acc-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    padding: 16px;
                    cursor: pointer;
                    background: #fff;
                    transition: background 0.2s;
                }
                .ts-jlpt-acc-header:hover {
                    background: #f8fafc;
                }
                .ts-jlpt-acc-title-area {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    width: calc(100% - 40px);
                }
                .ts-jlpt-acc-title {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 700;
                    color: #1e293b;
                    line-height: 1.4;
                }
                .ts-jlpt-acc-tags {
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .ts-jlpt-acc-tag {
                    font-size: 12px;
                    padding: 3px 8px;
                    border-radius: 6px;
                    background: #f1f5f9;
                    color: #64748b;
                    border: 1px solid #e2e8f0;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }
                .ts-jlpt-acc-tag.blue { color: #2563eb; background: #eff6ff; border-color: #bfdbfe; }
                .ts-jlpt-acc-tag.orange { color: #ea580c; background: #fff7ed; border-color: #fed7aa; }

                .ts-jlpt-acc-toggle {
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                    color: #94a3b8;
                    transition: transform 0.3s, background 0.3s;
                    background: #fff;
                    flex-shrink: 0;
                }
                .ts-jlpt-acc-card.open .ts-jlpt-acc-toggle {
                    transform: rotate(180deg);
                    background: #f1f5f9;
                    color: #1e293b;
                }

                .ts-jlpt-acc-body {
                    display: none;
                    padding: 0 20px 20px;
                    background: #fff;
                }
                .ts-jlpt-acc-card.open .ts-jlpt-acc-body {
                    display: block;
                }

                .ts-jlpt-acc-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 12px 16px;
                    margin-top: 8px;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                    background: #f8fafc;
                    text-decoration: none !important;
                    color: #2563eb !important;
                    font-weight: 600;
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .ts-jlpt-acc-item:hover {
                    border-color: #bfdbfe;
                    background: #eff6ff;
                    color: #1d4ed8 !important;
                    box-shadow: 0 2px 4px rgba(37,99,235,0.05);
                }
                .ts-jlpt-acc-item span {
                    text-align: left;
                    line-height: 1.4;
                }
                .ts-jlpt-acc-item svg {
                    width: 16px;
                    height: 16px;
                    fill: currentColor;
                    flex-shrink: 0;
                    margin-left: 10px;
                }
                .ts-jlpt-acc-item.skill-voc { color: #8b5cf6 !important; background: #f5f3ff; border-color: #ddd6fe; }
                .ts-jlpt-acc-item.skill-voc:hover { background: #ede9fe; border-color: #c4b5fd; box-shadow: 0 2px 4px rgba(139,92,246,0.1); }
                .ts-jlpt-acc-item.skill-gra { color: #10b981 !important; background: #ecfdf5; border-color: #a7f3d0; }
                .ts-jlpt-acc-item.skill-gra:hover { background: #d1fae5; border-color: #6ee7b7; box-shadow: 0 2px 4px rgba(16,185,129,0.1); }
                .ts-jlpt-acc-item.skill-rea { color: #f59e0b !important; background: #fffbeb; border-color: #fde68a; }
                .ts-jlpt-acc-item.skill-rea:hover { background: #fef3c7; border-color: #fcd34d; box-shadow: 0 2px 4px rgba(245,158,11,0.1); }
                .ts-jlpt-acc-item.skill-lis { color: #3b82f6 !important; background: #eff6ff; border-color: #bfdbfe; }
                .ts-jlpt-acc-item.skill-lis:hover { background: #dbeafe; border-color: #93c5fd; box-shadow: 0 2px 4px rgba(59,130,246,0.1); }
            </style>
            
            <div class="ts-jlpt-acc-list">
                <?php foreach ($grouped_exams as $g_exam): ?>
                <div class="ts-jlpt-acc-card">
                    <div class="ts-jlpt-acc-header">
                        <div class="ts-jlpt-acc-title-area">
                            <h3 class="ts-jlpt-acc-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                            <div class="ts-jlpt-acc-tags">
                                <span class="ts-jlpt-acc-tag blue"><i class="fa-solid fa-graduation-cap"></i> JLPT</span>
                                <span class="ts-jlpt-acc-tag"><i class="fa-regular fa-clock"></i> <?php echo $g_exam['duration']; ?> phút</span>
                                <span class="ts-jlpt-acc-tag"><i class="fa-solid fa-list-ol"></i> <?php echo $g_exam['total_q']; ?> câu</span>
                                <span class="ts-jlpt-acc-tag orange"><i class="fa-solid fa-layer-group"></i> <?php echo count($g_exam['parts']); ?> Part luyện tập</span>
                            </div>
                        </div>
                        <div class="ts-jlpt-acc-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>
                    <div class="ts-jlpt-acc-body">
                        <?php 
                        foreach ($g_exam['parts'] as $part): 
                            $skill_lower = mb_strtolower($part['skill']);
                            $icon = 'fa-file-lines';
                            $skill_class = 'skill-default';
                            if(strpos($skill_lower, 'vocab') !== false || strpos($skill_lower, 'từ') !== false) { $icon = 'fa-spell-check'; $skill_class = 'skill-voc'; }
                            elseif(strpos($skill_lower, 'gram') !== false || strpos($skill_lower, 'ngữ') !== false) { $icon = 'fa-book'; $skill_class = 'skill-gra'; }
                            elseif(strpos($skill_lower, 'read') !== false || strpos($skill_lower, 'đọc') !== false) { $icon = 'fa-book-open'; $skill_class = 'skill-rea'; }
                            elseif(strpos($skill_lower, 'listen') !== false || strpos($skill_lower, 'nghe') !== false) { $icon = 'fa-headphones'; $skill_class = 'skill-lis'; }
                        ?>
                            <a href="<?php echo esc_url(tqp_get_exam_page_url($part['exam']->id)); ?>" class="ts-jlpt-acc-item <?php echo $skill_class; ?>">
                                <span><i class="fa-solid <?php echo $icon; ?>" style="margin-right:6px; opacity:0.7;"></i> <?php echo esc_html($part['skill'] === 'Full' ? $part['exam']->title : $part['skill']); ?></span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const headers = document.querySelectorAll('.ts-jlpt-acc-header');
                headers.forEach(header => {
                    header.addEventListener('click', function() {
                        const card = this.closest('.ts-jlpt-acc-card');
                        card.classList.toggle('open');
                    });
                });
            });
            </script>
            <?php else: ?>
                <div class="ts-hsk-empty-state">
                    <p>Hiện chưa có đề thi nào cho JLPT <?php echo $level; ?>. Hãy quay lại sau nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- SEO Content Section -->
        <div class="ts-hsk-seo-section">
            <?php echo do_shortcode('[block id="thi-thu-jlpt-' . $level . '"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------
// SHORTCODE TRANG CHỦ JLPT: [tqp_jlpt_home]
// ---------------------------------------------------------
function tqp_render_jlpt_home($atts) {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    // Enqueue CSS
    wp_enqueue_style('tqp-style');

    ob_start();
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <div class="ts-hsk-home-page">
        
        <!-- Hero Section -->
        <div class="ts-hh-hero">
            <div class="ts-hh-hero-left">
                <h1 class="ts-hh-title">Thi thử JLPT Online</h1>
                <h2 class="ts-hh-subtitle">Đánh giá năng lực – Chinh phục JLPT!</h2>
                <p class="ts-hh-desc">Hệ thống thi thử JLPT từ N5 đến N1 với đề thi chuẩn cấu trúc bám sát kỳ thi thật, giúp bạn làm quen, ôn tập và đạt kết quả cao.</p>
                
                <div class="ts-hh-features">
                    <div class="ts-hh-feat">
                        <span class="feat-icon"><i class="fa-solid fa-file-signature"></i></span>
                        <div class="feat-text">
                            <strong>Đề thi chuẩn JLPT</strong>
                            <span>Cập nhật theo cấu trúc mới nhất</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon"><i class="fa-solid fa-chart-pie"></i></span>
                        <div class="feat-text">
                            <strong>Chấm điểm tự động</strong>
                            <span>Kết quả chi tiết, đánh giá năng lực chính xác</span>
                        </div>
                    </div>
                    <div class="ts-hh-feat">
                        <span class="feat-icon"><i class="fa-solid fa-bullseye"></i></span>
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
                            <span class="ts-hh-hsk-text">JLPT</span>
                        </div>
                        <div class="ts-hh-monitor-base"></div>
                    </div>
                    <div class="ts-hh-books">📚</div>
                    <div class="ts-hh-pencil">✏️</div>
                </div>
            </div>
        </div>

        <!-- Cấp Độ Section -->
        <div class="ts-hh-levels-section">
            <h2 class="ts-hh-section-title">Ôn luyện JLPT 5 cấp độ</h2>
            <div class="ts-hh-levels-grid">
                <?php
                $level_colors = [
                    'N5' => '#f59e0b', // Yellow
                    'N4' => '#10b981', // Green
                    'N3' => '#f97316', // Orange
                    'N2' => '#ef4444', // Red
                    'N1' => '#8b5cf6'  // Purple
                ];
                foreach (['N5', 'N4', 'N3', 'N2', 'N1'] as $l): ?>
                <a href="<?php echo esc_url(home_url('/thi-thu-jlpt/' . strtolower($l) . '/')); ?>" class="ts-hh-level-card ts-hh-card-<?php echo strtolower($l); ?>" style="display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 16px; padding: 20px;">
                    <div class="ts-hh-level-icon" style="color: <?php echo $level_colors[$l]; ?>; margin: 0; font-size: 40px; line-height: 1;">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div style="text-align: left;">
                        <div class="ts-hh-level-top" style="margin-bottom: 4px;">日本語能力試験</div>
                        <div class="ts-hh-level-main" style="color: <?php echo $level_colors[$l]; ?>; margin: 0; line-height: 1.2;">JLPT <?php echo $l; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Danh sách đề thi mới nhất -->
        <div class="ts-hh-latest-section">
            <h2 class="ts-hh-section-title">Danh sách các đề thi mới nhất</h2>
            
            <?php
            foreach (['N5', 'N4', 'N3', 'N2', 'N1'] as $l) {
                // Fetch latest exams for this level
                $exams = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_exams WHERE title LIKE %s ORDER BY id DESC LIMIT 20",
                    '%JLPT - ' . $l . '%'
                ));
                
                if (!empty($exams)) {
                    // Group exams
                    $grouped_exams = [];
                    foreach ($exams as $exam) {
                        $title_lower = mb_strtolower($exam->title);
                        
                        $skills_pattern = '/-(?:\s)*(vocabulary|grammar|reading|listening|từ vựng|ngữ pháp|đọc hiểu|nghe hiểu)\b/iu';
                        if (preg_match($skills_pattern, $exam->title, $matches)) {
                            $skill = ucfirst(strtolower(trim($matches[1])));
                        } else {
                            $skill = 'Full';
                        }
                        
                        $base_name = trim(preg_replace($skills_pattern, '', $exam->title));
                        
                        if (!isset($grouped_exams[$base_name])) {
                            $grouped_exams[$base_name] = [
                                'base_name' => $base_name,
                                'parts' => [],
                                'total_q' => 0,
                                'duration' => 0
                            ];
                        }
                        $grouped_exams[$base_name]['parts'][] = [
                            'skill' => $skill,
                            'exam' => $exam
                        ];

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
                        echo '<h3 class="ts-hh-level-heading" style="color: '.$level_colors[$l].';">JLPT '.$l.'</h3>';
                        echo '<div class="ts-jlpt-acc-list">'; // Re-use the accordion class from level-landing
                        foreach ($top_3_exams as $g_exam) {
                            ?>
                            <div class="ts-jlpt-acc-card">
                                <div class="ts-jlpt-acc-header">
                                    <div class="ts-jlpt-acc-title-area">
                                        <h3 class="ts-jlpt-acc-title"><?php echo esc_html($g_exam['base_name']); ?></h3>
                                        <div class="ts-jlpt-acc-tags">
                                            <span class="ts-jlpt-acc-tag blue" style="color: <?php echo $level_colors[$l]; ?>; border-color: <?php echo $level_colors[$l]; ?>; background: transparent;"><i class="fa-solid fa-graduation-cap"></i> JLPT <?php echo $l; ?></span>
                                            <span class="ts-jlpt-acc-tag"><i class="fa-regular fa-clock"></i> <?php echo $g_exam['duration']; ?> phút</span>
                                            <span class="ts-jlpt-acc-tag"><i class="fa-solid fa-list-ol"></i> <?php echo $g_exam['total_q']; ?> câu</span>
                                            <span class="ts-jlpt-acc-tag orange"><i class="fa-solid fa-layer-group"></i> <?php echo count($g_exam['parts']); ?> Part luyện tập</span>
                                        </div>
                                    </div>
                                    <div class="ts-jlpt-acc-toggle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </div>
                                </div>
                                <div class="ts-jlpt-acc-body">
                                    <?php 
                                    foreach ($g_exam['parts'] as $part): 
                                        $skill_lower = mb_strtolower($part['skill']);
                                        $icon = 'fa-file-lines';
                                        $skill_class = 'skill-default';
                                        if(strpos($skill_lower, 'vocab') !== false || strpos($skill_lower, 'từ') !== false) { $icon = 'fa-spell-check'; $skill_class = 'skill-voc'; }
                                        elseif(strpos($skill_lower, 'gram') !== false || strpos($skill_lower, 'ngữ') !== false) { $icon = 'fa-book'; $skill_class = 'skill-gra'; }
                                        elseif(strpos($skill_lower, 'read') !== false || strpos($skill_lower, 'đọc') !== false) { $icon = 'fa-book-open'; $skill_class = 'skill-rea'; }
                                        elseif(strpos($skill_lower, 'listen') !== false || strpos($skill_lower, 'nghe') !== false) { $icon = 'fa-headphones'; $skill_class = 'skill-lis'; }
                                    ?>
                                        <a href="<?php echo esc_url(tqp_get_exam_page_url($part['exam']->id)); ?>" class="ts-jlpt-acc-item <?php echo $skill_class; ?>">
                                            <span><i class="fa-solid <?php echo $icon; ?>" style="margin-right:6px; opacity:0.7;"></i> <?php echo esc_html($part['skill'] === 'Full' ? $part['exam']->title : $part['skill']); ?></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                        }
                        echo '</div>';
                        echo '<div style="text-align: right; margin-top: 10px; margin-bottom: 30px;"><a href="'.esc_url(home_url('/thi-thu-jlpt/' . strtolower($l) . '/')).'" style="color: '.$level_colors[$l].'; font-weight: 600; text-decoration: underline;">Xem thêm đề thi JLPT '.$l.' »</a></div>';
                    }
                }
            }
            ?>
        </div>

        <!-- UX Block cho SEO -->
        <div class="ts-hh-seo-block">
            <?php echo do_shortcode('[block id="thi-thu-jlpt"]'); ?>
        </div>
        
    </div>
    <style>
        .ts-jlpt-acc-list {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            width: 100%;
        }
        @media (max-width: 1024px) {
            .ts-jlpt-acc-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .ts-jlpt-acc-list {
                grid-template-columns: 1fr;
            }
        }
        .ts-jlpt-acc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            overflow: hidden;
            font-family: inherit;
        }
        .ts-jlpt-acc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px;
            cursor: pointer;
            background: #fff;
            transition: background 0.2s;
        }
        .ts-jlpt-acc-header:hover {
            background: #f8fafc;
        }
        .ts-jlpt-acc-title-area {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: calc(100% - 40px);
        }
        .ts-jlpt-acc-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.4;
        }
        .ts-jlpt-acc-tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .ts-jlpt-acc-tag {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .ts-jlpt-acc-tag.blue { color: #2563eb; background: #eff6ff; border-color: #bfdbfe; }
        .ts-jlpt-acc-tag.orange { color: #ea580c; background: #fff7ed; border-color: #fed7aa; }

        .ts-jlpt-acc-toggle {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: #94a3b8;
            transition: transform 0.3s, background 0.3s;
            background: #fff;
            flex-shrink: 0;
        }
        .ts-jlpt-acc-card.open .ts-jlpt-acc-toggle {
            transform: rotate(180deg);
            background: #f1f5f9;
            color: #1e293b;
        }

        .ts-jlpt-acc-body {
            display: none;
            padding: 0 20px 20px;
            background: #fff;
        }
        .ts-jlpt-acc-card.open .ts-jlpt-acc-body {
            display: block;
        }

        .ts-jlpt-acc-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            margin-top: 8px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            text-decoration: none !important;
            color: #2563eb !important;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .ts-jlpt-acc-item:hover {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8 !important;
            box-shadow: 0 2px 4px rgba(37,99,235,0.05);
        }
        .ts-jlpt-acc-item span {
            text-align: left;
            line-height: 1.4;
        }
        .ts-jlpt-acc-item svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
            flex-shrink: 0;
            margin-left: 10px;
        }
        .ts-jlpt-acc-item.skill-voc { color: #8b5cf6 !important; background: #f5f3ff; border-color: #ddd6fe; }
        .ts-jlpt-acc-item.skill-voc:hover { background: #ede9fe; border-color: #c4b5fd; box-shadow: 0 2px 4px rgba(139,92,246,0.1); }
        .ts-jlpt-acc-item.skill-gra { color: #10b981 !important; background: #ecfdf5; border-color: #a7f3d0; }
        .ts-jlpt-acc-item.skill-gra:hover { background: #d1fae5; border-color: #6ee7b7; box-shadow: 0 2px 4px rgba(16,185,129,0.1); }
        .ts-jlpt-acc-item.skill-rea { color: #f59e0b !important; background: #fffbeb; border-color: #fde68a; }
        .ts-jlpt-acc-item.skill-rea:hover { background: #fef3c7; border-color: #fcd34d; box-shadow: 0 2px 4px rgba(245,158,11,0.1); }
        .ts-jlpt-acc-item.skill-lis { color: #3b82f6 !important; background: #eff6ff; border-color: #bfdbfe; }
        .ts-jlpt-acc-item.skill-lis:hover { background: #dbeafe; border-color: #93c5fd; box-shadow: 0 2px 4px rgba(59,130,246,0.1); }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const headers = document.querySelectorAll('.ts-jlpt-acc-header');
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const card = this.closest('.ts-jlpt-acc-card');
                card.classList.toggle('open');
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

<?php
/**
 * TestSpace Search Portal Shortcode
 */
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_search_portal', 'tqp_render_search_portal');

function tqp_render_search_portal() {
    global $wpdb;

    wp_enqueue_style('tqp-font-inter');
    wp_enqueue_style('tqp-style');
    wp_enqueue_style('tqp-katex');
    wp_enqueue_script('canvas-confetti');
    wp_enqueue_script('tqp-katex');
    wp_enqueue_script('tqp-katex-auto');
    wp_enqueue_script('tqp-script');

    // Now fetch the exams from the new TestSpace table directly, decoupled from old tables
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_questions = $wpdb->prefix . 'tq_questions';
    $table_groups = $wpdb->prefix . 'tq_groups';

    $exams = $wpdb->get_results("
    SELECT * 
    FROM $table_exams 
    ORDER BY id DESC
");

// Query total questions counts and categories
$exams_data = [];
$categories = [];

if ($exams) {
    foreach ($exams as $exam) {
        $total_q = 0;
        $groups = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table_groups WHERE exam_id = %d", $exam->id));
        if ($groups) {
            foreach ($groups as $g) {
                $total_q += $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_questions WHERE group_id = %d", $g->id));
            }
        }
        
        // Read the category field directly from the custom column
        $cat = isset($exam->category) && !empty($exam->category) ? $exam->category : 'Khác';
        if (!in_array($cat, $categories)) {
            $categories[] = $cat;
        }
        
        $exams_data[] = [
            'exam' => $exam,
            'total_questions' => $total_q,
            'category_title' => $cat,
            'category_slug' => sanitize_title($cat)
        ];
    }
}

// Group categories dynamically
$group_gplx = [];
$group_test = [];
$group_standalone = [];

if (!empty($categories)) {
    foreach ($categories as $cat) {
        $cat_lower = mb_strtolower($cat);
        if (strpos($cat_lower, 'gplx') !== false || strpos($cat_lower, 'lái xe') !== false) {
            $group_gplx[] = $cat;
        } elseif (strpos($cat_lower, 'test') !== false || strpos($cat_lower, 'trắc nghiệm') !== false || $cat_lower === 'mbti' || $cat_lower === 'disc') {
            $group_test[] = $cat;
        } else {
        }
    }
}

ob_start();
?>

    <!-- Main Outer Container with ts-app-container to trigger distraction-free CSS -->

    <!-- Main Outer Container with ts-app-container to trigger distraction-free CSS -->
    <div class="ts-app-container" id="ts-app-root">
        
        <!-- Main portal viewport -->
        <div class="ts-spa-main-content">
            
            <!-- PORTAL FRONT PAGE (Hidden when doing exam) -->
            <div id="ts-portal-front">
                <!-- Hero section -->
                <div class="ts-portal-hero">
                    <h1 class="ts-portal-hero-title">Đột Phá Điểm Số Của Bạn</h1>
                    <p class="ts-portal-hero-desc">Hệ thống thi thử trắc nghiệm online phong cách Minimalism chuẩn quốc tế. Tốc độ vượt trội, giải thích chi tiết 100%.</p>
                    
                    <!-- Search bar -->
                    <div class="ts-portal-search-wrap">
                        <span class="ts-portal-search-icon">🔍</span>
                        <input type="text" id="ts-portal-search-input" class="ts-portal-search-input" placeholder="Tìm kiếm đề thi (TOEIC, HSK, GPLX...)..." autocomplete="off">
                    </div>
                    
                    <!-- Quick stats chips -->
                    <div class="ts-portal-chips">
                        <span class="ts-portal-chip">✅ Tốc độ SPA cực nhanh</span>
                        <span class="ts-portal-chip">💡 Lời giải thích 100%</span>
                        <span class="ts-portal-chip">🔒 Bảo mật tuyệt đối</span>
                    </div>
                </div>

                <!-- Categories filter removed -->

                <!-- Real-time Search Status Banner -->
                <div class="ts-search-status" id="ts-portal-search-status" style="display: none;">
                    <span class="ts-search-status-icon">🔍</span>
                    <span class="ts-search-status-text" id="ts-search-status-text"></span>
                    <button class="ts-search-clear-btn" id="ts-search-clear-btn">Xóa tìm kiếm</button>
                </div>

                <!-- Exams grid -->
                <div class="ts-portal-grid" id="ts-portal-exams-grid">
                    <?php if (!empty($exams_data)): foreach ($exams_data as $item): 
                        $title_lower = mb_strtolower($item['exam']->title);
                    ?>
                        <div class="ts-portal-card" data-category="<?php echo $item['category_slug']; ?>" data-title="<?php echo esc_attr($title_lower); ?>">
                            <div class="ts-portal-card-header">
                                <!-- Badge removed as requested -->
                            </div>
                            <h3 class="ts-portal-card-title"><?php echo esc_html($item['exam']->title); ?></h3>
                            
                            <div class="ts-portal-card-meta">
                                <span class="ts-portal-meta-item">⏱ <strong><?php echo $item['exam']->duration_minutes; ?></strong> phút</span>
                                <span class="ts-portal-meta-item">📝 <strong><?php echo $item['total_questions']; ?></strong> câu hỏi</span>
                            </div>
                            
                            <div class="ts-portal-card-footer">
                                <?php $exam_url = tqp_get_exam_page_url($item['exam']->id); ?>
                                <a href="<?php echo esc_url($exam_url); ?>" class="ts-portal-start-btn" style="text-decoration: none !important; display: block !important; text-align: center;">
                                    Thi thử ngay →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="ts-portal-empty">
                            <span class="ts-portal-empty-icon">📂</span>
                            <p>Chưa có đề thi nào được nạp vào hệ thống. Vui lòng vào trang quản trị để thêm đề!</p>
                            <?php if (current_user_can('manage_options')): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=traffic-quiz-pro')); ?>" class="ts-portal-empty-btn" target="_blank">
                                    Nạp đề thi JSON mẫu ngay
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PORTAL EXAM SPA WRAP (Hidden initially, loaded via JS) -->
            <div id="ts-portal-exam-view" style="display: none;">
                <!-- Loading Screen -->
                <div class="ts-loader" id="ts-loader" style="display: none;">
                    <div class="ts-loader-spinner"></div>
                    <p class="ts-loader-text">Đang chuẩn bị đề thi...</p>
                </div>

                <!-- Main Exam Layout -->
                <div id="ts-exam-wrap" style="display: none;">
                    <!-- Mobile Sticky Bar -->
                    <div class="ts-mobile-sticky-bar" id="ts-mobile-sticky-bar">
                        <div class="ts-mobile-stat">
                            <span class="ts-mobile-stat-label">Số câu đã làm</span>
                            <span class="ts-mobile-stat-val" id="ts-mobile-answered">0/0</span>
                        </div>
                        <div class="ts-mobile-stat">
                            <span class="ts-mobile-stat-label">Thời gian còn lại</span>
                            <span class="ts-mobile-stat-val" id="ts-mobile-timer">00:00</span>
                        </div>
                        <button class="ts-mobile-submit-btn" id="ts-mobile-submit-btn">NỘP BÀI</button>
                    </div>

                    <!-- Main 70/30 Layout -->
                    <div class="ts-main-layout">
                        <!-- Left Column — Questions -->
                        <div class="ts-content-column">
                            <div id="ts-questions-area"></div>
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

                                <!-- Stats -->
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
                                <button class="ts-submit-btn" id="ts-submit-btn">NỘP BÀI THI</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Quiz Locker -->
                <div class="ts-modal-overlay" id="ts-lock-modal" style="display: none;">
                    <div class="ts-modal-card">
                        <div class="ts-modal-icon">🔒</div>
                        <h3 class="ts-modal-title">Bài thi đã nộp thành công!</h3>
                        <p class="ts-modal-desc">Hoàn thành nhiệm vụ nhanh để nhận <strong>Mã kích hoạt</strong> xem kết quả chi tiết và lời giải thích.</p>
                        
                        <div class="ts-task-box">
                            <span class="ts-task-tag-badge" style="display:none;">🏷 Nhiệm vụ: <strong id="ts-task-tag-name"></strong></span>
                            <p class="ts-task-hint" id="ts-task-hint-text">Nhấp vào nút dưới đây để nhận hướng dẫn lấy mã:</p>
                            <button type="button" id="ts-portal-task-btn" class="ts-task-link-btn" style="border:none; cursor:pointer; font-family:inherit; width: 100%;">
                                Làm nhiệm vụ lấy mã →
                            </button>
                            
                            <div id="ts-task-instructions" style="display:none; text-align: left; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1;"></div>
                        </div>

                        <div class="ts-code-input-wrap">
                            <input type="text" id="ts-unlock-code-input" class="ts-code-input" placeholder="Nhập mã xác nhận..." required>
                            <button id="ts-unlock-submit-btn" class="ts-unlock-btn">XÁC NHẬN</button>
                        </div>
                        <div id="ts-unlock-error" class="ts-unlock-error" style="display: none;"></div>
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
                        <div class="ts-modal-icon">📝</div>
                        <h3 class="ts-modal-title">Nộp bài thi?</h3>
                        <p class="ts-modal-desc" id="ts-submit-confirm-desc">Bạn có chắc chắn muốn nộp bài thi để chấm điểm? Hãy chắc chắn rằng bạn đã kiểm tra kỹ toàn bộ câu trả lời.</p>
                        
                        <div class="ts-modal-buttons">
                            <button id="ts-submit-cancel-btn" class="ts-modal-btn secondary">Kiểm tra lại</button>
                            <button id="ts-submit-confirm-btn" class="ts-modal-btn primary">Xác nhận nộp</button>
                        </div>
                    </div>
                </div>

                <!-- Result Dashboard (Ẩn ban đầu) -->
                <div class="ts-result-container" id="ts-result-container" style="display: none;">
                    <!-- Result Header with Score Ring -->
                    <div class="ts-result-header">
                        <div class="ts-result-badge">Hoàn thành bài thi</div>
                        <h2 class="ts-result-title" id="ts-result-exam-title"></h2>
                        
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
                        <div class="ts-review-section-title">Chi tiết đáp án & Giải thích</div>
                        <div class="ts-review-list" id="ts-review-list"></div>
                    </div>
                </div>
            </div>

        </div> <!-- Closing ts-spa-main-content -->
    </div> <!-- Closing ts-app-root -->

<?php 
    return ob_get_clean();
} 
?>

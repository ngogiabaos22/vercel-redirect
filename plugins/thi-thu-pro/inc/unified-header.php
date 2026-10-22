<?php
/**
 * TestSpace Unified Header Component
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

// Lấy danh mục thực tế từ bảng tq_exams
$table_exams = $wpdb->prefix . 'tq_exams';
$suppress = $wpdb->suppress_errors();
$categories_raw = $wpdb->get_col("SELECT DISTINCT category FROM $table_exams WHERE category IS NOT NULL AND category != ''");
$wpdb->suppress_errors($suppress);

if (empty($categories_raw)) {
    $categories_raw = ['Giấy phép lái xe (GPLX)', 'Trắc nghiệm & Tính cách', 'Thi thử HSK', 'Khác'];
}

// Group categories dynamically like homepage
$group_gplx = [];
$group_test = [];
$group_standalone = [];

if (!empty($categories_raw)) {
    foreach ($categories_raw as $cat) {
        $cat_lower = mb_strtolower($cat, 'UTF-8');
        if (strpos($cat_lower, 'gplx') !== false || strpos($cat_lower, 'lái xe') !== false) {
            $group_gplx[] = $cat;
        } elseif (strpos($cat_lower, 'test') !== false || strpos($cat_lower, 'trắc nghiệm') !== false || $cat_lower === 'mbti' || $cat_lower === 'disc') {
            $group_test[] = $cat;
        } else {
            $group_standalone[] = $cat;
        }
    }
}
?>
<header class="ts-unified-header">
    <div class="ts-unified-header-inner ts-full-width">
        
        <!-- Left: Branding -->
        <div class="ts-unified-brand">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="ts-unified-logo-link">
                <span class="ts-unified-logo-icon">🎯</span>
                <span class="ts-unified-brand-name">TestSpace</span>
            </a>
        </div>

        <!-- Center: Navigation Menu -->
        <nav class="ts-unified-nav">
            <ul class="ts-unified-nav-list">
                <!-- ALL EXAMS -->
                <li class="ts-unified-nav-item">
                    <a href="<?php echo esc_url(home_url('/?filter=all')); ?>" class="ts-unified-nav-link" data-filter="all">TẤT CẢ ĐỀ THI</a>
                </li>
                
                <!-- GPLX Group -->
                <?php if (!empty($group_gplx)): ?>
                <li class="ts-unified-nav-item has-dropdown">
                    <a href="#" class="ts-unified-nav-link">GIẤY PHÉP LÁI XE (GPLX) ▾</a>
                    <ul class="ts-unified-dropdown">
                        <?php foreach ($group_gplx as $cat): $slug = sanitize_title($cat); ?>
                        <li><a href="<?php echo esc_url(home_url('/?filter=' . $slug)); ?>" class="ts-unified-dropdown-link" data-filter="<?php echo esc_attr($slug); ?>"><?php echo esc_html($cat); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- TESTS Group -->
                <?php if (!empty($group_test)): ?>
                <li class="ts-unified-nav-item has-dropdown">
                    <a href="#" class="ts-unified-nav-link">TRẮC NGHIỆM & TÍNH CÁCH ▾</a>
                    <ul class="ts-unified-dropdown">
                        <?php foreach ($group_test as $cat): $slug = sanitize_title($cat); ?>
                        <li><a href="<?php echo esc_url(home_url('/?filter=' . $slug)); ?>" class="ts-unified-dropdown-link" data-filter="<?php echo esc_attr($slug); ?>"><?php echo esc_html($cat); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Standalone Groups -->
                <?php foreach ($group_standalone as $cat): $slug = sanitize_title($cat); ?>
                <li class="ts-unified-nav-item">
                    <a href="<?php echo esc_url(home_url('/?filter=' . $slug)); ?>" class="ts-unified-nav-link" data-filter="<?php echo esc_attr($slug); ?>"><?php echo esc_html(mb_strtoupper($cat, 'UTF-8')); ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Right: Actions (Search / Profile) -->
        <div class="ts-unified-actions">
            <!-- Nút search màu đỏ đã bị loại bỏ vì thừa thãi -->
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo esc_url(admin_url()); ?>" class="ts-unified-login-btn logged-in">Bảng điều khiển</a>
            <?php else: ?>
                <a href="<?php echo esc_url(wp_login_url()); ?>" class="ts-unified-login-btn">Đăng nhập</a>
            <?php endif; ?>
            
            <!-- Mobile Menu Toggle -->
            <button class="ts-unified-mobile-toggle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
        </div>

    </div>
</header>

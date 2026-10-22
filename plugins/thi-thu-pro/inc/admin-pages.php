<?php
// Hàm chính render giao diện trang Admin
function tqp_admin_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';

    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';

    // Các biến chung
    $base_url = admin_url('admin.php?page=traffic-quiz-pro');

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">TestSpace <small style="font-size:12px;color:#888;">v<?php echo TQP_VER; ?></small></h1>
        <hr class="wp-header-end">

        <h2 class="nav-tab-wrapper" style="margin-top: 20px;">
            <a href="<?php echo esc_url(add_query_arg('tab', 'list', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'list' ? 'nav-tab-active' : ''; ?>">Danh sách Đề thi</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'add_manual', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'add_manual' ? 'nav-tab-active' : ''; ?>">Tạo Đề Thi Thủ Công</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'import_excel', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'import_excel' ? 'nav-tab-active' : ''; ?>">Import từ Excel</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'import_json', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'import_json' ? 'nav-tab-active' : ''; ?>">Import từ JSON</a>
        </h2>

        <div style="margin-top: 20px;">
        <?php
        if ($active_tab == 'add_manual') {
            require_once TQP_PATH . 'inc/admin-add-manual.php';
        } elseif ($active_tab == 'edit_manual') {
            require_once TQP_PATH . 'inc/admin-edit-manual.php';
        } elseif ($active_tab == 'import_excel') {
            require_once TQP_PATH . 'inc/admin-import-excel.php';
        } elseif ($active_tab == 'import_json') {
            require_once TQP_PATH . 'inc/admin-import-json.php';
        } else {
            require_once TQP_PATH . 'inc/admin-list.php';
        }
        ?>
        </div>
    </div>
    <?php
}

function tqp_admin_categories_page() {
    if (!current_user_can('manage_options')) return;
    require_once TQP_PATH . 'inc/admin-categories.php';
}

function tqp_admin_questions_page() {
    if (!current_user_can('manage_options')) return;
    require_once TQP_PATH . 'inc/admin-questions.php';
}

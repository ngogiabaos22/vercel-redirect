<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_exams = $wpdb->prefix . 'tq_exams';
$table_groups = $wpdb->prefix . 'tq_groups';
$table_questions = $wpdb->prefix . 'tq_questions';

// Xử lý Đồng bộ đề thi gốc từ Quiz Maker
if (isset($_POST['tqp_sync_action']) && check_admin_referer('tqp_sync_original', 'tqp_sync_nonce')) {
    $result = tqp_migrate_original_data();
    if ($result['success']) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
    }
}


// X? ly T?t/B?t dp n
if (isset($_GET['toggle_answers']) && check_admin_referer('tqp_toggle_answers', 'tqp_nonce_toggle')) {
    $exam_id = intval($_GET['toggle_answers']);
    $current_status = $wpdb->get_var($wpdb->prepare("SELECT show_answers FROM $table_exams WHERE id = %d", $exam_id));
    $new_status = $current_status ? 0 : 1;
    $wpdb->update($table_exams, ['show_answers' => $new_status], ['id' => $exam_id]);
    wp_redirect(admin_url('admin.php?page=traffic-quiz-pro&toggled=1'));
    exit;
}
if (isset($_GET['toggled'])) {
    echo '<div class="notice notice-success is-dismissible"><p>Đã cập nhật trạng thái hiển thị đáp án!</p></div>';
}

// X? ly Xa d? thi

if (isset($_GET['delete_exam']) && check_admin_referer('tqp_del_exam', 'tqp_nonce_del')) {
    $exam_id = intval($_GET['delete_exam']);
    
    // Lấy danh sách các nhóm trong đề thi này
    $group_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_groups WHERE exam_id = %d", $exam_id));
    if ($group_ids) {
        $group_ids_str = implode(',', array_map('intval', $group_ids));
        // Xóa toàn bộ câu hỏi liên quan đến các nhóm
        $wpdb->query("DELETE FROM $table_questions WHERE group_id IN ($group_ids_str)");
    }
    
    // Xóa toàn bộ nhóm câu hỏi
    $wpdb->delete($table_groups, ['exam_id' => $exam_id]);
    // Xóa đề thi
    $wpdb->delete($table_exams, ['id' => $exam_id]);

    echo '<div class="notice notice-success is-dismissible"><p>Đã xóa đề thi thành công!</p></div>';
}



// Thẻ Đồng bộ Cơ sở dữ liệu gốc
?>
<div class="card" style="max-width:1200px;padding:15px 25px; margin-top:0; background: #eef2f7; border-left: 4px solid #1e3a5f;">
    <h2 style="margin-top: 5px;">📥 Đồng bộ Đề thi từ Cơ sở dữ liệu gốc</h2>
    <p class="description" style="margin-bottom: 15px;">Đồng bộ toàn bộ các đề thi, câu hỏi và đáp án từ plugin **Quiz Maker** gốc sang định dạng cơ sở dữ liệu tốc độ cao của **TestSpace** để chạy giao diện mới.</p>
    
    <form method="post" action="<?php echo esc_url($base_url); ?>">
        <?php wp_nonce_field('tqp_sync_original', 'tqp_sync_nonce'); ?>
        <input type="hidden" name="tqp_sync_action" value="1">
        <button type="submit" class="button button-primary" style="background:#1e3a5f; border-color:#1e3a5f; padding: 5px 20px; height: auto; font-size: 14px;">
            Bắt đầu Đồng bộ Đề thi gốc
        </button>
    </form>
</div>



<h2 style="margin-top:20px;">Danh sách đề thi hiện có</h2>

<?php
$per_page = isset($_GET['per_page']) ? max(10, intval($_GET['per_page'])) : 100;
?>
<form method="get" action="" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
    <input type="hidden" name="page" value="traffic-quiz-pro">
    <select name="cat_filter">
        <option value="">-- Tất cả danh mục --</option>
        <?php
        $cats = $wpdb->get_col("SELECT DISTINCT category FROM $table_exams WHERE category != '' ORDER BY category ASC");
        foreach($cats as $c) {
            $selected = (isset($_GET['cat_filter']) && $_GET['cat_filter'] === $c) ? 'selected' : '';
            echo '<option value="'.esc_attr($c).'" '.$selected.'>'.esc_html($c).'</option>';
        }
        ?>
    </select>
    <select name="per_page">
        <option value="50" <?php selected($per_page, 50); ?>>50 đề / trang</option>
        <option value="100" <?php selected($per_page, 100); ?>>100 đề / trang</option>
        <option value="200" <?php selected($per_page, 200); ?>>200 đề / trang</option>
        <option value="500" <?php selected($per_page, 500); ?>>500 đề / trang</option>
    </select>
    <button type="submit" class="button">Lọc / Áp dụng</button>
</form>

<table class="widefat fixed striped" style="max-width:1200px; margin-bottom: 10px;">
    <thead>
        <tr>
            <th style="width:50px">ID</th>
            <th>Tên đề thi</th>
            <th style="width:120px">Thời gian làm bài</th>
            <th style="width:150px">Chuyên mục</th>
            <th style="width:130px">Tag Quiz Locker</th>
            <th style="width:200px">Shortcode nhúng bài viết</th>
            <th style="width:100px">Hiện đáp án</th>
            <th style="width:160px">Hành động</th>
        </tr>
    </thead>
    <tbody>
    <?php 
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;
    
    $where = "1=1";
    if (!empty($_GET['cat_filter'])) {
        $where .= $wpdb->prepare(" AND category = %s", $_GET['cat_filter']);
    }
    
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_exams WHERE $where");
    $total_pages = ceil($total_items / $per_page);
    
    $exams = $wpdb->get_results("SELECT * FROM $table_exams WHERE $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
    if ($exams): foreach ($exams as $item):
        $del_url = wp_nonce_url(add_query_arg(['delete_exam'=>$item->id], $base_url), 'tqp_del_exam', 'tqp_nonce_del');
        
        $toggle_url = wp_nonce_url(add_query_arg(['toggle_answers'=>$item->id], $base_url), 'tqp_toggle_answers', 'tqp_nonce_toggle');
        $show_ans = isset($item->show_answers) ? $item->show_answers : 1;
        $ans_text = $show_ans ? 'ON' : 'OFF';
        $ans_color = $show_ans ? '#22c55e' : '#94a3b8';

        $edit_url = admin_url('admin.php?page=traffic-quiz-pro&tab=edit_manual&exam_id=' . $item->id);
    ?>
        <tr>
            <td><?php echo $item->id; ?></td>
            <td><strong><?php echo esc_html($item->title); ?></strong></td>
            <td><?php echo $item->duration_minutes; ?> phút</td>
            <td><span style="background: #1e3a5f; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;"><?php echo esc_html($item->category ?: 'Khác'); ?></span></td>
            <td><code><?php echo esc_html($item->pass_tag ?: 'Trống'); ?></code></td>
            <td><code style="background:#eee;padding:3px 8px;border-radius:3px;">[traffic_quiz id="<?php echo $item->id; ?>"]</code></td>
            <td><a href="<?php echo esc_url($toggle_url); ?>" class="button button-small" style="background:<?php echo $ans_color; ?>; border-color:<?php echo $ans_color; ?>; color:#fff; min-width: 45px; text-align: center;"><?php echo $ans_text; ?></a></td>
            <td>
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small" style="background:#0ea5e9; border-color:#0ea5e9; color:#fff;">Sửa</a>
                <a href="<?php echo $del_url; ?>" onclick="return confirm('Xóa đề thi này sẽ xóa toàn bộ câu hỏi và đáp án liên quan. Bạn có chắc không?');" style="color:red; margin-left: 10px;">Xóa</a>
            </td>
        </tr>
    <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;padding:20px;">Chưa có đề thi nào trong hệ thống.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php
if (isset($total_pages) && $total_pages > 1) {
    $page_links = paginate_links([
        'base' => add_query_arg('paged', '%#%'),
        'format' => '',
        'prev_text' => '&laquo; Trước',
        'next_text' => 'Sau &raquo;',
        'total' => $total_pages,
        'current' => $paged
    ]);
    if ($page_links) {
        echo '<div class="tablenav" style="max-width: 1200px;"><div class="tablenav-pages" style="margin: 1em 0; float: right;">' . $page_links . '</div></div><div style="clear:both;"></div>';
    }
}
?>

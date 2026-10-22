<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_categories = $wpdb->prefix . 'tq_question_categories';

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$message = '';

// Handle Delete
if (isset($_POST['delete_cat_id'])) {
    $del_id = intval($_POST['delete_cat_id']);
    // Check if it's the default "Chung" category
    $is_default = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_categories WHERE id = %d AND name = 'Chung'", $del_id));
    if ($is_default) {
        $message = '<div class="notice notice-error is-dismissible"><p>Không thể xóa danh mục mặc định "Chung".</p></div>';
    } else {
        $wpdb->delete($table_categories, ['id' => $del_id]);
        $message = '<div class="notice notice-success is-dismissible"><p>Đã xóa danh mục thành công.</p></div>';
    }
}

// Handle Form Submit (Add/Edit)
if (isset($_POST['save_category'])) {
    $cat_name = sanitize_text_field($_POST['cat_name']);
    $cat_desc = sanitize_textarea_field($_POST['cat_desc']);
    $cat_id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;

    if (empty($cat_name)) {
        $message = '<div class="notice notice-error is-dismissible"><p>Tên danh mục không được để trống.</p></div>';
    } else {
        if ($cat_id > 0) {
            $wpdb->update($table_categories, [
                'name' => $cat_name,
                'description' => $cat_desc
            ], ['id' => $cat_id]);
            $message = '<div class="notice notice-success is-dismissible"><p>Cập nhật danh mục thành công.</p></div>';
            $action = 'list';
        } else {
            $wpdb->insert($table_categories, [
                'name' => $cat_name,
                'description' => $cat_desc
            ]);
            $message = '<div class="notice notice-success is-dismissible"><p>Thêm danh mục mới thành công.</p></div>';
            $action = 'list';
        }
    }
}

// Lấy danh sách
$categories = $wpdb->get_results("SELECT * FROM $table_categories ORDER BY id DESC");

?>
<div class="wrap">
    <h1 class="wp-heading-inline">Danh mục Câu hỏi</h1>
    <?php if ($action == 'list'): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-categories&action=add')); ?>" class="page-title-action">Thêm mới</a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php echo $message; ?>

    <?php if ($action == 'list'): ?>
        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th class="manage-column column-primary">Tên Danh Mục</th>
                    <th class="manage-column">Mô tả</th>
                    <th class="manage-column" style="width: 150px;">Ngày tạo</th>
                    <th class="manage-column" style="width: 150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categories): ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="column-primary" data-colname="Tên Danh Mục">
                                <strong><?php echo esc_html($cat->name); ?></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-categories&action=edit&id=' . $cat->id)); ?>">Chỉnh sửa</a></span>
                                </div>
                            </td>
                            <td data-colname="Mô tả"><?php echo esc_html($cat->description); ?></td>
                            <td data-colname="Ngày tạo"><?php echo esc_html($cat->created_at); ?></td>
                            <td data-colname="Thao tác">
                                <?php if ($cat->name != 'Chung'): ?>
                                <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?');">
                                    <input type="hidden" name="delete_cat_id" value="<?php echo esc_attr($cat->id); ?>">
                                    <button type="submit" class="button button-link-delete" style="color: #b32d2e;">Xóa</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Chưa có danh mục nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php 
    if ($action == 'add' || $action == 'edit'): 
        $edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $edit_name = '';
        $edit_desc = '';
        if ($edit_id > 0) {
            $cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_categories WHERE id = %d", $edit_id));
            if ($cat) {
                $edit_name = $cat->name;
                $edit_desc = $cat->description;
            }
        }
    ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=tqp-question-categories')); ?>">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="cat_id" value="<?php echo esc_attr($edit_id); ?>">
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cat_name">Tên danh mục <span class="description">(bắt buộc)</span></label></th>
                        <td><input name="cat_name" type="text" id="cat_name" value="<?php echo esc_attr($edit_name); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cat_desc">Mô tả</label></th>
                        <td><textarea name="cat_desc" id="cat_desc" rows="5" cols="50" class="large-text"><?php echo esc_textarea($edit_desc); ?></textarea></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="save_category" id="submit" class="button button-primary" value="Lưu danh mục">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-categories')); ?>" class="button button-secondary">Hủy</a>
            </p>
        </form>
    <?php endif; ?>
</div>

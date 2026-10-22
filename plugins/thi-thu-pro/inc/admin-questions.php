<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_questions = $wpdb->prefix . 'tq_questions';
$table_categories = $wpdb->prefix . 'tq_question_categories';

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$message = '';

// Lấy danh sách danh mục
$categories = $wpdb->get_results("SELECT * FROM $table_categories ORDER BY id DESC");
$cat_options = '';
foreach ($categories as $cat) {
    $cat_options .= '<option value="' . esc_attr($cat->id) . '">' . esc_html($cat->name) . '</option>';
}

// Xử lý Xóa câu hỏi
if (isset($_POST['delete_q_id'])) {
    $del_id = intval($_POST['delete_q_id']);
    $wpdb->delete($table_questions, ['id' => $del_id]);
    $message = '<div class="notice notice-success is-dismissible"><p>Đã xóa câu hỏi thành công.</p></div>';
}

// Xử lý Lưu câu hỏi (Thêm/Sửa)
if (isset($_POST['save_question'])) {
    $q_id = isset($_POST['q_id']) ? intval($_POST['q_id']) : 0;
    $category_id = intval($_POST['category_id']);
    
    // Thu thập dữ liệu
    $q_data = [
        'category_id' => $category_id,
        'question_text' => wp_kses_post(wp_unslash($_POST['question_text'])),
        'opt_a' => wp_kses_post(wp_unslash($_POST['opt_a'])),
        'opt_b' => wp_kses_post(wp_unslash($_POST['opt_b'])),
        'opt_c' => wp_kses_post(wp_unslash($_POST['opt_c'] ?? '')),
        'opt_d' => wp_kses_post(wp_unslash($_POST['opt_d'] ?? '')),
        'opt_e' => wp_kses_post(wp_unslash($_POST['opt_e'] ?? '')),
        'opt_f' => wp_kses_post(wp_unslash($_POST['opt_f'] ?? '')),
        'opt_g' => wp_kses_post(wp_unslash($_POST['opt_g'] ?? '')),
        'opt_h' => wp_kses_post(wp_unslash($_POST['opt_h'] ?? '')),
        'correct_ans' => strtoupper(sanitize_text_field(wp_unslash($_POST['correct_ans'] ?? 'A'))),
        'explanation' => wp_kses_post(wp_unslash($_POST['explanation'] ?? ''))
    ];
    
    // Options meta (Hỗ trợ khoảng điểm / từ khóa)
    $options_meta = [];
    foreach (['a','b','c','d','e','f','g','h'] as $opt) {
        $options_meta["opt_$opt"] = [
            'weight' => floatval($_POST["meta_{$opt}_weight"] ?? 0),
            'keyword' => sanitize_text_field($_POST["meta_{$opt}_keyword"] ?? '')
        ];
    }
    $q_data['options_meta'] = wp_json_encode($options_meta);
    
    // Câu hỏi trong Ngân hàng độc lập không có group_id và question_number (được gắn ngẫu nhiên hoặc 0)
    $q_data['group_id'] = 0;
    $q_data['question_number'] = 0;

    if ($q_id > 0) {
        $wpdb->update($table_questions, $q_data, ['id' => $q_id]);
        $message = '<div class="notice notice-success is-dismissible"><p>Cập nhật câu hỏi thành công.</p></div>';
        $action = 'list';
    } else {
        $wpdb->insert($table_questions, $q_data);
        $message = '<div class="notice notice-success is-dismissible"><p>Thêm câu hỏi mới thành công.</p></div>';
        $action = 'list';
    }
}

// Xử lý bộ lọc
$filter_cat = isset($_GET['filter_cat']) ? intval($_GET['filter_cat']) : 0;
$search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where_clauses = ["1=1"];
$params = [];
if ($filter_cat > 0) {
    $where_clauses[] = "q.category_id = %d";
    $params[] = $filter_cat;
}
if (!empty($search_q)) {
    $where_clauses[] = "q.question_text LIKE %s";
    $params[] = '%' . $wpdb->esc_like($search_q) . '%';
}
$where_sql = implode(' AND ', $where_clauses);

// Tính tổng câu hỏi
$count_sql = "SELECT COUNT(*) FROM $table_questions q WHERE $where_sql";
if (!empty($params)) {
    $count_sql = $wpdb->prepare($count_sql, $params);
}
$total_items = $wpdb->get_var($count_sql);

// Phân trang
$per_page = isset($_GET['per_page']) ? max(10, intval($_GET['per_page'])) : 200;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;
$total_pages = ceil($total_items / $per_page);

// Query lấy dữ liệu
$sql = "SELECT q.*, c.name as category_name 
        FROM $table_questions q 
        LEFT JOIN $table_categories c ON q.category_id = c.id 
        WHERE $where_sql 
        ORDER BY q.id DESC 
        LIMIT %d OFFSET %d";

$query_params = array_merge($params, [$per_page, $offset]);
$sql = $wpdb->prepare($sql, $query_params);
$questions = $wpdb->get_results($sql);

?>
<div class="wrap">
    <h1 class="wp-heading-inline">Ngân hàng Câu hỏi</h1>
    <?php if ($action == 'list'): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-bank&action=add')); ?>" class="page-title-action">Thêm câu hỏi</a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php echo $message; ?>

    <?php if ($action == 'list'): ?>
        <!-- Bộ lọc -->
        <form method="get" action="admin.php" style="margin-bottom: 20px; display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="page" value="tqp-question-bank">
            <select name="filter_cat">
                <option value="0">-- Tất cả danh mục --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat->id; ?>" <?php selected($filter_cat, $cat->id); ?>><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="s" value="<?php echo esc_attr($search_q); ?>" placeholder="Tìm kiếm câu hỏi...">
            <select name="per_page">
                <option value="50" <?php selected($per_page, 50); ?>>50 câu / trang</option>
                <option value="100" <?php selected($per_page, 100); ?>>100 câu / trang</option>
                <option value="200" <?php selected($per_page, 200); ?>>200 câu / trang</option>
                <option value="500" <?php selected($per_page, 500); ?>>500 câu / trang</option>
            </select>
            <button type="submit" class="button">Lọc / Áp dụng</button>
        </form>

        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th class="manage-column column-primary">Nội dung câu hỏi</th>
                    <th class="manage-column" style="width: 150px;">Danh mục</th>
                    <th class="manage-column" style="width: 120px;">Đ.Án Đúng</th>
                    <th class="manage-column" style="width: 150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($questions): ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td class="column-primary">
                                <strong><?php echo esc_html(wp_trim_words($q->question_text, 15, '...')); ?></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-bank&action=edit&id=' . $q->id)); ?>">Chỉnh sửa</a></span>
                                </div>
                            </td>
                            <td><?php echo esc_html($q->category_name ?? 'Chưa phân loại'); ?></td>
                            <td><strong><?php echo esc_html($q->correct_ans); ?></strong></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn xóa câu hỏi này khỏi Ngân hàng? LƯU Ý: Các đề thi đang dùng câu hỏi này (liên kết) sẽ bị mất câu hỏi này!');">
                                    <input type="hidden" name="delete_q_id" value="<?php echo esc_attr($q->id); ?>">
                                    <button type="submit" class="button button-link-delete" style="color: #b32d2e;">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Chưa có câu hỏi nào trong ngân hàng.</td></tr>
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
                echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0; float: right;">' . $page_links . '</div></div><div style="clear:both;"></div>';
            }
        }
        ?>
    <?php endif; ?>

    <?php 
    if ($action == 'add' || $action == 'edit'): 
        $edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $q = null;
        if ($edit_id > 0) {
            $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_questions WHERE id = %d", $edit_id));
        }
        $opt_meta = $q && !empty($q->options_meta) ? json_decode($q->options_meta, true) : [];
    ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=tqp-question-bank')); ?>">
            <?php if ($edit_id > 0): ?>
                <input type="hidden" name="q_id" value="<?php echo esc_attr($edit_id); ?>">
            <?php endif; ?>
            
            <div style="max-width: 800px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label>Danh mục</label></th>
                            <td>
                                <select name="category_id" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat->id; ?>" <?php echo ($q && $q->category_id == $cat->id) ? 'selected' : ''; ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Nội dung câu hỏi</label></th>
                            <td>
                                <textarea name="question_text" rows="3" class="large-text tqp-q-text-input" required><?php echo $q ? esc_textarea($q->question_text) : ''; ?></textarea>
                                <div style="margin-top: 5px;">
                                    <button type="button" class="button tqp-btn-upload-img" title="Thêm ảnh từ thư viện WP">🖼 Thêm ảnh từ thư viện</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Các đáp án</h3>
                <div class="tqp-options-container" style="display:flex; flex-direction:column; gap: 10px; margin-bottom: 15px;">
                    <?php 
                    $opts = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
                    foreach ($opts as $o):
                        $val = $q ? $q->{"opt_$o"} : '';
                        $weight = $opt_meta["opt_$o"]['weight'] ?? '';
                        $keyword = $opt_meta["opt_$o"]['keyword'] ?? '';
                        $is_hidden = ($o == 'e' || $o == 'f' || $o == 'g' || $o == 'h') && empty($val);
                        $style = $is_hidden ? 'display:none; gap:10px;' : 'display:flex; gap:10px;';
                    ?>
                    <div class="tqp-opt-row" style="<?php echo $style; ?>" data-opt="<?php echo $o; ?>">
                        <span style="width:25px;font-weight:bold;margin-top:5px;"><?php echo strtoupper($o); ?>.</span>
                        <input type="text" name="opt_<?php echo $o; ?>" value="<?php echo esc_attr($val); ?>" placeholder="Nội dung đáp án <?php echo strtoupper($o); ?>" style="flex:1;" <?php echo ($o=='a'||$o=='b') ? 'required' : ''; ?>>
                        <input type="number" step="0.1" name="meta_<?php echo $o; ?>_weight" value="<?php echo esc_attr($weight); ?>" placeholder="Điểm" style="width:120px;">
                        <input type="text" name="meta_<?php echo $o; ?>_keyword" value="<?php echo esc_attr($keyword); ?>" placeholder="Từ khóa" style="width:140px;">
                        <?php if ($o != 'a' && $o != 'b'): ?>
                        <button type="button" class="button tqp-btn-remove-opt" title="Xóa đáp án này">X</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div>
                        <button type="button" class="button button-small tqp-btn-add-opt">+ Thêm đáp án</button>
                    </div>
                </div>

                <div style="display:flex; gap: 15px; align-items: center;">
                    <label><strong>Đáp Án Đúng:</strong></label>
                    <select name="correct_ans">
                        <?php foreach ($opts as $o): $uo = strtoupper($o); ?>
                            <option value="<?php echo $uo; ?>" <?php echo ($q && $q->correct_ans == $uo) ? 'selected' : ''; ?>><?php echo $uo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="explanation" value="<?php echo $q ? esc_attr($q->explanation) : ''; ?>" placeholder="Giải thích (tùy chọn)" style="flex:1;">
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="save_question" id="submit" class="button button-primary" value="Lưu câu hỏi">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tqp-question-bank')); ?>" class="button button-secondary">Hủy</a>
            </p>
        </form>

        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.tqp-btn-add-opt', function() {
                let hiddenOpts = $('.tqp-opt-row:hidden');
                if (hiddenOpts.length > 0) {
                    $(hiddenOpts[0]).css('display', 'flex');
                } else {
                    alert('Đã đạt giới hạn tối đa 8 đáp án!');
                }
            });
            $(document).on('click', '.tqp-btn-remove-opt', function() {
                let row = $(this).closest('.tqp-opt-row');
                row.hide();
                row.find('input[type="text"], input[type="number"]').val('');
            });

            // WP Media Uploader cho nội dung câu hỏi
            $(document).on('click', '.tqp-btn-upload-img', function(e) {
                e.preventDefault();
                var button = $(this);
                var custom_uploader = wp.media({
                    title: 'Chọn ảnh từ thư viện',
                    button: { text: 'Chèn ảnh' },
                    multiple: false
                }).on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    var imgTag = '<img class="ts-question-img" src="' + attachment.url + '" />';
                    var inputField = button.closest('td').find('.tqp-q-text-input');
                    inputField.val(inputField.val() + '\n' + imgTag);
                }).open();
            });
        });
        </script>
    <?php endif; ?>
</div>

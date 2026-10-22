<?php
if (!defined('ABSPATH')) {
    exit;
}

class IATS_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('wp_ajax_iats_toggle_status', array($this, 'ajax_toggle_status'));
        add_action('wp_ajax_iats_get_domain_style', array($this, 'ajax_get_domain_style'));
        add_action('wp_ajax_iats_save_domain_style', array($this, 'ajax_save_domain_style'));
    }

    public function register_admin_menu() {
        add_menu_page('Quản Lý Nhiệm Vụ', 'Quản Lý Nhiệm Vụ', 'manage_options', 'iats-tasks', array($this, 'render_tasks_page'), 'dashicons-chart-line', 26);
        add_submenu_page('iats-tasks', 'Khách Hàng & Nạp Coin', 'Khách Hàng', 'manage_options', 'iats-customers', array($this, 'render_customers_page'));
        add_submenu_page('iats-tasks', 'Thống Kê Log Truy Cập', 'Thống Kê Log', 'manage_options', 'iats-logs', array($this, 'render_statistics_page'));
        add_submenu_page('iats-tasks', 'Cài Đặt', 'Cài Đặt', 'manage_options', 'iats-settings', array($this, 'render_settings_page'));
    }

    // CHECK RESET NGÀY MỚI
    private function check_daily_reset() {
        global $wpdb;
        $today = current_time('Y-m-d');
        $sql = "UPDATE " . IATS_TABLE_TASKS . " SET count_yesterday = count, count = 0, popup_views = 0, last_reset_date = %s 
                WHERE last_reset_date < %s OR last_reset_date IS NULL";
        $wpdb->query($wpdb->prepare($sql, $today, $today));
    }

    // BẬT / TẮT AJAX
    public function ajax_toggle_status() {
        if (!current_user_can('manage_options') || !check_ajax_referer('iats_save_action', 'nonce', false)) wp_send_json_error(['message' => 'Lỗi']);
        $id = intval($_POST['id']);
        global $wpdb;
        $current = $wpdb->get_var($wpdb->prepare("SELECT active FROM " . IATS_TABLE_TASKS . " WHERE id = %d", $id));
        $new = $current ? 0 : 1;
        $wpdb->update(IATS_TABLE_TASKS, ['active' => $new], ['id' => $id]);
        wp_send_json_success(['new_status' => $new]);
    }

    public function ajax_get_domain_style() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        $domain = sanitize_text_field($_POST['domain']);
        $style = get_option('iats_btn_style_' . $domain, array());
        wp_send_json_success($style);
    }

    public function ajax_save_domain_style() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        $domain = sanitize_text_field($_POST['domain']);
        $style = array(
            'bg' => sanitize_hex_color($_POST['bg']),
            'color' => sanitize_hex_color($_POST['color']),
            'text' => sanitize_text_field($_POST['text']),
            'logo' => esc_url_raw($_POST['logo']),
            'border_radius' => isset($_POST['border_radius']) ? intval($_POST['border_radius']) : 5,
            'padding' => sanitize_text_field($_POST['padding']),
            'box_title' => sanitize_text_field($_POST['box_title']),
            'box_bg' => sanitize_hex_color($_POST['box_bg']),
            'box_border' => sanitize_hex_color($_POST['box_border']),
            'box_text_color' => sanitize_hex_color($_POST['box_text_color']),
            'box_code_color' => sanitize_hex_color($_POST['box_code_color'])
        );
        update_option('iats_btn_style_' . $domain, $style);
        wp_send_json_success();
    }

    // LƯU FORM
    private function handle_admin_actions() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;

        if (isset($_POST['iats_action']) && check_admin_referer('iats_save_action', 'iats_nonce')) {
            $data = [
                'detail'      => sanitize_text_field($_POST['detail']),
                'detail2'     => sanitize_text_field($_POST['detail2']),
                'website'     => sanitize_text_field($_POST['website']),
                'step4'       => sanitize_text_field($_POST['step4']),
                'img3'        => esc_url_raw($_POST['img3']),
                'img4'        => esc_url_raw($_POST['img4']),
                'tag'         => sanitize_text_field($_POST['tag']),
                'fixed_code'  => sanitize_text_field($_POST['fixed_code']),
                'count'       => intval($_POST['count']),
                'count_get'   => intval($_POST['count_get']),
                'count_limit' => intval($_POST['count_limit']),
                'limit_per_day' => intval($_POST['limit_per_day']),
                'priority'    => isset($_POST['priority']) ? 1 : 0,
                'active'      => isset($_POST['active']) ? 1 : 0,
                'last_reset_date' => current_time('Y-m-d')
            ];

            if ($_POST['iats_action'] === 'add') {
                $wpdb->insert(IATS_TABLE_TASKS, $data);
                echo '<div class="notice notice-success"><p>Thêm nhiệm vụ mới thành công!</p></div>';
            } elseif ($_POST['iats_action'] === 'edit' && isset($_POST['id'])) {
                $wpdb->update(IATS_TABLE_TASKS, $data, ['id' => intval($_POST['id'])]);
                echo '<div class="notice notice-success"><p>Cập nhật nhiệm vụ thành công!</p></div>';
            }
        }

        if (isset($_GET['delete']) && check_admin_referer('iats_delete_action', 'iats_nonce_delete')) {
            $wpdb->delete(IATS_TABLE_TASKS, ['id' => intval($_GET['delete'])]);
            echo '<div class="notice notice-success"><p>Đã xóa nhiệm vụ!</p></div>';
        }
    }

    // GIAO DIỆN QUẢN TRỊ TỔNG
    public function render_tasks_page() {
        if (!current_user_can('manage_options')) return;
        $this->check_daily_reset();
        wp_enqueue_media();
        $this->handle_admin_actions();

        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM " . IATS_TABLE_TASKS . " ORDER BY id DESC");
        $edit_item = isset($_GET['edit']) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM " . IATS_TABLE_TASKS . " WHERE id = %d", intval($_GET['edit']))) : null;
        ?>
        <div class="wrap iats-admin">
            <h1 class="wp-heading-inline">Traffic System (Trung Tâm Điều Hành)</h1>
            <hr class="wp-header-end">
            
            <style>
                .iats-table img { max-width: 80px; max-height: 80px; object-fit: cover; }
                .iats-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; cursor: pointer; }
                .iats-status-on { background: #c6e1c6; color: #2b6d2b; border: 1px solid #2b6d2b; }
                .iats-status-off { background: #fbeaea; color: #d63638; border: 1px solid #d63638; }
                .iats-img-preview img { margin-top: 10px; border: 1px solid #ccc; max-width: 150px; }
            </style>

            <div class="card" style="max-width: 100%; padding: 0 20px 20px;">
                <h2><?php echo $edit_item ? 'Chỉnh sửa Nhiệm vụ #' . $edit_item->id : 'Thêm nhiệm vụ mới'; ?></h2>
                <form method="post" action="<?php echo remove_query_arg(['edit', 'delete']); ?>">
                    <?php wp_nonce_field('iats_save_action', 'iats_nonce'); ?>
                    <input type="hidden" name="iats_action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                    <?php if ($edit_item): ?><input type="hidden" name="id" value="<?php echo $edit_item->id; ?>"><?php endif; ?>

                    <table class="form-table">
                        <?php if ($edit_item): ?>
                        <tr>
                            <th><label>Mã Script Nhúng cho khách</label></th>
                            <td>
                                <?php $script_url = IATS_PLUGIN_URL . 'assets/js/client-script.js?id=TS-' . $edit_item->id; ?>
                                <textarea readonly style="width:100%; height: 60px; background: #f0f0f1; padding:10px;">&lt;script src="<?php echo esc_url($script_url); ?>"&gt;&lt;/script&gt;</textarea>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr><th>Từ khóa 1*</th><td><input class="regular-text" name="detail" value="<?php echo $edit_item ? esc_attr($edit_item->detail) : ''; ?>" required></td></tr>
                        <tr><th>Từ khóa 2</th><td><input class="regular-text" name="detail2" value="<?php echo $edit_item ? esc_attr($edit_item->detail2) : ''; ?>"></td></tr>
                        <tr><th>Website URL*</th><td><input class="regular-text" name="website" value="<?php echo $edit_item ? esc_attr($edit_item->website) : ''; ?>" required></td></tr>
                        <tr>
                            <th>Ảnh bước 3</th>
                            <td>
                                <div class="iats-image-upload">
                                    <input type="text" name="img3" class="regular-text iats-img-url" value="<?php echo $edit_item ? esc_attr($edit_item->img3) : ''; ?>">
                                    <button type="button" class="button iats-upload-btn">Chọn ảnh</button>
                                    <div class="iats-img-preview"><?php if ($edit_item && $edit_item->img3) echo '<img src="' . esc_url($edit_item->img3) . '">'; ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr><th>Nội dung Bước 4*</th><td><input class="regular-text" name="step4" value="<?php echo $edit_item ? esc_attr($edit_item->step4) : 'Cuộn xuống cuối trang, bấm vào nút Lấy Mã Xác Nhận...'; ?>" required></td></tr>
                        <tr>
                            <th>Ảnh bước 4</th>
                            <td>
                                <div class="iats-image-upload">
                                    <input type="text" name="img4" class="regular-text iats-img-url" value="<?php echo $edit_item ? esc_attr($edit_item->img4) : ''; ?>">
                                    <button type="button" class="button iats-upload-btn">Chọn ảnh</button>
                                    <div class="iats-img-preview"><?php if ($edit_item && $edit_item->img4) echo '<img src="' . esc_url($edit_item->img4) . '">'; ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr><th>Tag phân loại</th><td><input class="regular-text" name="tag" value="<?php echo $edit_item ? esc_attr($edit_item->tag) : ''; ?>"></td></tr>
                        <tr><th>Mật mã cố định</th><td><input class="regular-text" name="fixed_code" value="<?php echo $edit_item ? esc_attr($edit_item->fixed_code) : ''; ?>" placeholder="Để trống nếu muốn tự động tạo ngẫu nhiên"></td></tr>
                        <tr>
                            <th>Cấu hình đếm</th>
                            <td>
                                Tổng View mua: <input type="number" class="small-text" name="count_limit" value="<?php echo $edit_item ? $edit_item->count_limit : 0; ?>"> |
                                Đã chạy Tổng: <input type="number" class="small-text" name="count_get" value="<?php echo $edit_item ? $edit_item->count_get : 0; ?>"> <br><br>
                                Giới hạn/Ngày: <input type="number" class="small-text" name="limit_per_day" value="<?php echo $edit_item ? $edit_item->limit_per_day : 0; ?>"> |
                                Đã chạy Hôm nay: <input type="number" class="small-text" name="count" value="<?php echo $edit_item ? $edit_item->count : 0; ?>">
                            </td>
                        </tr>
                        <tr><th>Ưu tiên</th><td><label><input type="checkbox" name="priority" <?php echo ($edit_item && $edit_item->priority) ? 'checked' : ''; ?>> Bật phân phối ưu tiên</label></td></tr>
                        <tr><th>Trạng thái</th><td><label><input type="checkbox" name="active" <?php echo ($edit_item && $edit_item->active) || !$edit_item ? 'checked' : ''; ?>> Kích hoạt Nhiệm vụ này</label></td></tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large"><?php echo $edit_item ? 'Cập nhật' : 'Thêm mới'; ?></button>
                        <?php if ($edit_item): ?><a href="?page=iats-tasks" class="button button-large">Hủy bỏ</a><?php endif; ?>
                    </p>
                </form>
            </div>

            <br>
            <h2>Danh sách Dự án (Nhiệm vụ SEO)</h2>
            <table class="widefat fixed striped iats-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">ID</th><th>Từ khóa</th><th>Website</th><th>Ảnh 3</th><th>Bước 4</th><th>Mã & Tag</th><th>Tổng / Giới hạn</th><th>Qua / Nay / L.Ngày</th><th>Ưu tiên</th><th>Status</th><th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="11">Chưa có nhiệm vụ nào.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): 
                        $del_url = wp_nonce_url(add_query_arg(['delete' => $item->id], menu_page_url('iats-tasks', false)), 'iats_delete_action', 'iats_nonce_delete');
                    ?>
                        <tr>
                            <td><?php echo $item->id; ?></td>
                            <td><strong><?php echo esc_html($item->detail); ?></strong><br><small><?php echo esc_html($item->detail2); ?></small></td>
                            <td><?php echo esc_html($item->website); ?></td>
                            <td><?php if ($item->img3) echo '<img src="' . esc_url($item->img3) . '">'; ?></td>
                            <td><?php echo esc_html($item->step4); ?></td>
                            <td>Mã: <?php echo !empty($item->fixed_code) ? esc_html($item->fixed_code) : 'Random'; ?><br>Tag: <?php echo esc_html($item->tag); ?></td>
                            <td><?php echo $item->count_get; ?> / <?php echo $item->count_limit > 0 ? $item->count_limit : '∞'; ?></td>
                            <td><?php echo $item->count_yesterday; ?> / <span style="color:blue;font-weight:bold;"><?php echo $item->count; ?></span> / <?php echo $item->limit_per_day > 0 ? $item->limit_per_day : '∞'; ?></td>
                            <td><?php echo $item->priority ? '<strong style="color:orange;">Ưu tiên</strong>' : 'Thường'; ?></td>
                            <td><span class="iats-badge iats-status-toggle <?php echo $item->active ? 'iats-status-on' : 'iats-status-off'; ?>" data-id="<?php echo $item->id; ?>"><?php echo $item->active ? 'Bật' : 'Tắt'; ?></span></td>
                            <td><a href="?page=iats-tasks&edit=<?php echo $item->id; ?>">Sửa</a> | <a href="<?php echo $del_url; ?>" style="color:red;" onclick="return confirm('Xóa?')">Xóa</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('.iats-upload-btn').click(function(e) {
                e.preventDefault();
                var wrapper = $(this).closest('.iats-image-upload');
                var uploader = wp.media({ title: 'Chọn ảnh', button: { text: 'Sử dụng' }, multiple: false }).on('select', function() {
                    var attachment = uploader.state().get('selection').first().toJSON();
                    wrapper.find('.iats-img-url').val(attachment.url);
                    wrapper.find('.iats-img-preview').html('<img src="' + attachment.url + '">');
                }).open();
            });
            $('.iats-status-toggle').click(function() {
                var btn = $(this); var id = btn.data('id');
                $.post(ajaxurl, { action: 'iats_toggle_status', id: id, nonce: '<?php echo wp_create_nonce("iats_save_action"); ?>' }, function(res) {
                    if(res.success) btn.toggleClass('iats-status-on iats-status-off').text(res.data.new_status == 1 ? 'Bật' : 'Tắt');
                });
            });
        });
        </script>
        <?php
    }

    // GIAO DIỆN LOGS
    public function render_statistics_page() {
        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM " . IATS_TABLE_LOGS . " ORDER BY id DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1>Thống Kê 100 Click Gần Nhất</h1>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr><th>ID</th><th>ID Nhiệm Vụ</th><th>IP Address</th><th>Nguồn (Referrer)</th><th>Thời gian</th></tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>Task #<?php echo esc_html($log->project_id); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                                <td style="word-break: break-all;"><?php echo esc_html($log->referrer); ?></td>
                                <td><?php echo esc_html($log->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">Chưa có dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['iats_settings_nonce']) && wp_verify_nonce($_POST['iats_settings_nonce'], 'save_settings')) {
            update_option('iats_view_price', intval($_POST['iats_view_price']));
            update_option('iats_result_url', sanitize_text_field($_POST['iats_result_url']));
            update_option('iats_priority_weight', intval($_POST['iats_priority_weight']));
            update_option('iats_session_mins', intval($_POST['iats_session_mins']));
            
            // Cài đặt nút và Logo
            update_option('iats_portal_logo', esc_url_raw($_POST['iats_portal_logo']));
            update_option('iats_button_logo', esc_url_raw($_POST['iats_button_logo']));

            echo '<div class="notice notice-success is-dismissible"><p>Lưu cài đặt thành công!</p></div>';
        }
        $price = get_option('iats_view_price', 3);
        $url = get_option('iats_result_url', '/ket-qua-bai-thi/');
        $weight = get_option('iats_priority_weight', 3);
        $session = get_option('iats_session_mins', 120);

        // Cài đặt Logo
        $portal_logo = get_option('iats_portal_logo', '');
        $button_logo = get_option('iats_button_logo', '');
        ?>
        <div class="wrap">
            <h1>Cài Đặt Hệ Thống Traffic PRO</h1>
            <form method="post">
                <?php wp_nonce_field('save_settings', 'iats_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>Giá cho 1 Lượt View (Coin)</th>
                        <td><input type="number" name="iats_view_price" class="small-text" value="<?php echo esc_attr($price); ?>" required></td>
                    </tr>
                    <tr>
                        <th>URL trang kết quả</th>
                        <td>
                            <input type="text" name="iats_result_url" class="regular-text" value="<?php echo esc_attr($url); ?>">
                            <p class="description">Trang này chứa shortcode [ketqua_nuttai] hoặc [iats_customer_portal].</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Trọng số nhiệm vụ ưu tiên</th>
                        <td>
                            <input type="number" name="iats_priority_weight" class="small-text" value="<?php echo esc_attr($weight); ?>">
                            <p class="description">Từ 1 đến 10. Mức càng cao thì nhiệm vụ được bật stick ưu tiên sẽ xuất hiện nhiều hơn.</p>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2"><hr><h3 style="margin-bottom:0;">Cài Đặt Hình Ảnh Mặc Định</h3></th>
                    </tr>
                    <tr>
                        <th>Ảnh Logo (Portal)</th>
                        <td>
                            <input type="text" id="iats_portal_logo" name="iats_portal_logo" class="regular-text" value="<?php echo esc_attr($portal_logo); ?>">
                            <button type="button" class="button" onclick="iatsOpenMedia('iats_portal_logo')">Tải ảnh lên</button>
                            <p class="description">Hiển thị ở góc trái Admin & Customer Portal. Thay thế chữ Mặc định.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Ảnh Nút (Mặc định)</th>
                        <td>
                            <input type="text" id="iats_button_logo" name="iats_button_logo" class="regular-text" value="<?php echo esc_attr($button_logo); ?>">
                            <button type="button" class="button" onclick="iatsOpenMedia('iats_button_logo')">Tải ảnh lên</button>
                            <p class="description">Logo hiển thị trên Nút lấy mã nếu khách hàng không nhập logo tùy chỉnh.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary button-large">Lưu Cài Đặt</button></p>
            </form>
        </div>
        <script>
            let mediaUploader;
            function iatsOpenMedia(targetId) {
                if (mediaUploader) { mediaUploader.targetId = targetId; mediaUploader.open(); return; }
                mediaUploader = wp.media({ title: 'Chọn ảnh', button: { text: 'Chọn' }, multiple: false });
                mediaUploader.targetId = targetId;
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    document.getElementById(mediaUploader.targetId).value = attachment.url;
                });
                mediaUploader.open();
            }
        </script>
        <?php
    }

    public function render_customers_page() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['iats_add_coin_nonce']) && wp_verify_nonce($_POST['iats_add_coin_nonce'], 'add_coin')) {
            $uid = intval($_POST['customer_id']);
            $coin = intval($_POST['coin_amount']);
            $current = intval(get_user_meta($uid, 'iats_wallet_balance', true));
            update_user_meta($uid, 'iats_wallet_balance', $current + $coin);
            echo '<div class="notice notice-success"><p>Đã nạp ' . $coin . ' coin cho User ID ' . $uid . '</p></div>';
        }
        $users = get_users(['role' => 'iats_customer']);
        echo '<div class="wrap"><h1>Quản Lý Khách Hàng & Nạp Coin</h1>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Email</th><th>Tên</th><th>Số Dư Coin</th><th>Nạp Thêm Coin</th></tr></thead><tbody>';
        foreach ($users as $user) {
            $balance = intval(get_user_meta($user->ID, 'iats_wallet_balance', true));
            echo '<tr><td>' . $user->ID . '</td><td>' . esc_html($user->user_email) . '</td><td>' . esc_html($user->display_name) . '</td><td><strong>' . $balance . '</strong> Coin</td>';
            echo '<td><form method="post" style="display:flex;gap:5px;">';
            wp_nonce_field('add_coin', 'iats_add_coin_nonce');
            echo '<input type="hidden" name="customer_id" value="' . $user->ID . '">';
            echo '<input type="number" name="coin_amount" value="100" style="width:80px;">';
            echo '<button type="submit" class="button">Nạp</button></form></td></tr>';
        }
        if (empty($users)) echo '<tr><td colspan="5">Chưa có khách hàng nào đăng ký.</td></tr>';
        echo '</tbody></table>';

        // PHẦN QUẢN LÝ WEBSITE & TÙY CHỈNH NÚT
        $domains = $wpdb->get_col("SELECT DISTINCT website FROM " . IATS_TABLE_TASKS . " WHERE website != ''");
        echo '<hr><h2 style="margin-top:30px;">Tùy Chỉnh Nút Vệ Tinh Theo Website</h2>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Website (Domain)</th><th>Thao tác</th></tr></thead><tbody>';
        foreach ($domains as $domain) {
            echo '<tr><td><strong>' . esc_html($domain) . '</strong></td>';
            echo '<td><a href="#" class="button iats-edit-domain-btn" data-domain="' . esc_attr($domain) . '">Tùy chỉnh Nút</a></td></tr>';
        }
        if (empty($domains)) echo '<tr><td colspan="2">Chưa có website nào đăng ký nhiệm vụ.</td></tr>';
        echo '</tbody></table></div>';
        
        // Modal HTML & JS
        ?>
        <div id="iats-domain-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999;">
            <div style="background:#fff; width:450px; margin: 100px auto; padding:20px; border-radius:8px;">
                <h3 style="margin-top:0;">Tùy Chỉnh Nút: <span id="iats-domain-title" style="color:red;"></span></h3>
                <input type="hidden" id="iats-domain-name">
                <table class="form-table">
                    <tr><th>Chữ trên nút</th><td><input type="text" id="iats-d-text" class="regular-text" placeholder="Để trống = Dùng Mặc định"></td></tr>
                    <tr><th>Màu nền (Bg)</th><td><input type="color" id="iats-d-bg"></td></tr>
                    <tr><th>Màu chữ</th><td><input type="color" id="iats-d-color"></td></tr>
                    <tr><th>Logo Image URL</th><td><input type="text" id="iats-d-logo" class="regular-text" placeholder="Link ảnh hoặc bỏ trống"></td></tr>
                </table>
                <p style="text-align:right;">
                    <button type="button" class="button" onclick="jQuery('#iats-domain-modal').hide();">Hủy</button>
                    <button type="button" class="button button-primary" id="iats-d-save">Lưu Cấu Hình</button>
                </p>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('.iats-edit-domain-btn').click(function(e){
                e.preventDefault();
                var domain = $(this).data('domain');
                $('#iats-domain-title').text(domain);
                $('#iats-domain-name').val(domain);
                
                $.post(ajaxurl, {action: 'iats_get_domain_style', domain: domain}, function(res){
                    var data = res.data || {};
                    $('#iats-d-text').val(data.text || '');
                    $('#iats-d-bg').val(data.bg || '#E91E63');
                    $('#iats-d-color').val(data.color || '#ffffff');
                    $('#iats-d-logo').val(data.logo || '');
                    $('#iats-domain-modal').show();
                });
            });
            $('#iats-d-save').click(function(){
                var btn = $(this);
                btn.text('Đang lưu...');
                var domain = $('#iats-domain-name').val();
                var postData = {
                    action: 'iats_save_domain_style',
                    domain: domain,
                    text: $('#iats-d-text').val(),
                    bg: $('#iats-d-bg').val(),
                    color: $('#iats-d-color').val(),
                    logo: $('#iats-d-logo').val()
                };
                $.post(ajaxurl, postData, function(res){
                    btn.text('Lưu Cấu Hình');
                    $('#iats-domain-modal').hide();
                    alert('Lưu thành công cấu hình cho ' + domain);
                });
            });
        });
        </script>
        <?php
    }
}


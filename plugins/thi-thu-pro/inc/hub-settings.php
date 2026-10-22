<?php
if (!defined('ABSPATH')) exit;

/**
 * Kết nối tới plugin quản lý nhiệm vụ (Traffic Hila) đặt ở website khác.
 * Chỉ cần nhập địa chỉ website chứa plugin quản lý; đổi website quản lý thì chỉ sửa ở đây.
 * Có thể cố định bằng hằng số trong wp-config.php: define('TQP_HUB_URL', 'https://example.com');
 */
define('TQP_HUB_DEFAULT_URL', 'https://traffic.io.vn');

function tqp_hub_site_url() {
    $url = defined('TQP_HUB_URL') ? TQP_HUB_URL : get_option('tqp_hub_url', TQP_HUB_DEFAULT_URL);
    return untrailingslashit(esc_url_raw(trim((string) $url)));
}

function tqp_hub_api_url($path = '') {
    return tqp_hub_site_url() . '/wp-json/iats/v1' . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

add_action('admin_menu', function () {
    add_submenu_page('traffic-quiz-pro', 'Kết nối nhiệm vụ', 'Kết nối nhiệm vụ', 'manage_options', 'tqp-hub-settings', 'tqp_hub_settings_page');
});

function tqp_hub_settings_page() {
    if (!current_user_can('manage_options')) return;
    $notice = '';
    if (isset($_POST['tqp_hub_nonce']) && wp_verify_nonce($_POST['tqp_hub_nonce'], 'tqp_hub_save') && !defined('TQP_HUB_URL')) {
        $url = untrailingslashit(esc_url_raw(trim(wp_unslash($_POST['tqp_hub_url']))));
        update_option('tqp_hub_url', $url);
        $notice = '<div class="notice notice-success"><p>Đã lưu địa chỉ hệ thống quản lý nhiệm vụ.</p></div>';
    }

    $test = '';
    if (isset($_POST['tqp_hub_test']) && check_admin_referer('tqp_hub_save', 'tqp_hub_nonce')) {
        $res = wp_remote_get(tqp_hub_api_url('popup/config'), ['timeout' => 10]);
        $json = is_wp_error($res) ? null : json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($json['success'])) {
            $test = '<div class="notice notice-success"><p>Kết nối thành công tới ' . esc_html(tqp_hub_site_url()) . '.</p></div>';
        } else {
            $why = is_wp_error($res) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($res);
            $test = '<div class="notice notice-error"><p>Không kết nối được (' . esc_html($why) . '). Kiểm tra địa chỉ và chắc chắn plugin quản lý nhiệm vụ đã bật.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Kết nối hệ thống quản lý nhiệm vụ</h1>
        <?php echo $notice . $test; ?>
        <form method="post">
            <?php wp_nonce_field('tqp_hub_save', 'tqp_hub_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Địa chỉ website quản lý nhiệm vụ</th>
                    <td>
                        <input type="url" name="tqp_hub_url" class="regular-text" value="<?php echo esc_attr(tqp_hub_site_url()); ?>" <?php disabled(defined('TQP_HUB_URL')); ?> placeholder="https://traffic.io.vn">
                        <p class="description">Website đang cài plugin Traffic Hila. Nhiệm vụ, mã xác nhận và giao diện popup đều lấy từ đó (cần bật đường dẫn đẹp - permalink).
                        <?php if (defined('TQP_HUB_URL')) echo '<br><strong>Đang được cố định bằng TQP_HUB_URL trong wp-config.php.</strong>'; ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Lưu</button>
                <button type="submit" name="tqp_hub_test" value="1" class="button">Lưu &amp; kiểm tra kết nối</button>
            </p>
        </form>
    </div>
    <?php
}

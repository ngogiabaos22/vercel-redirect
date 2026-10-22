<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Popup nhiệm vụ: toàn bộ nội dung + giao diện được cấu hình ở đây (plugin quản lý),
 * các website trắc nghiệm chỉ lấy HTML đã dựng sẵn qua REST (/popup/config và /task).
 */
class IATS_Popup {
    const OPTION = 'iats_popup_config';

    public static function defaults() {
        return array(
            // Nội dung
            'title'            => 'Bạn ơi!!! Để xem được kết quả <br>bạn vui lòng làm nhiệm vụ nhỏ xíu này nha',
            'note'             => 'LƯU Ý: Không sử dụng VPN hoặc 1.1.1.1 khi làm nhiệm vụ',
            'task_btn_label'   => 'Làm nhiệm vụ lấy mã →',
            'task_btn_loading' => 'Đang tải nhiệm vụ...',
            'loading_text'     => 'Đang lấy hướng dẫn nhiệm vụ...',
            'top_placeholder'  => 'Nhập mã vừa kiếm được vào đây',
            'top_btn_label'    => 'NHẬN KẾT QUẢ',
            'show_bottom_form' => 1,
            'bottom_placeholder' => 'Nhập mật mã...',
            'bottom_btn_label' => 'XÁC NHẬN!',
            'step_prefix'      => 'Bước',
            'step1_text'       => 'Bấm nút COPY để sao chép từ khóa',
            'step1_or_text'    => 'sao chép từ khóa:',
            'or_word'          => 'HOẶC',
            'copy_label'       => 'Copy',
            'copied_label'     => 'Đã Copy!',
            'step2_text'       => 'Mở tab mới, truy cập',
            'show_google_mock' => 1,
            'step3_text'       => 'Tìm website {website} giống hình rồi bấm vào.',
            'step3_hint'       => '(Nếu <strong>trang 1</strong> không thấy hãy tìm ở <strong>trang 2, 3</strong> là có bạn nhé)',
            'step3_hint_img'   => '',
            'step4_default'    => 'Cuộn xuống cuối trang, lấy mã (tìm đúng nút này )',
            // Thông báo
            'err_empty_code'   => 'Vui lòng nhập mã xác nhận nhiệm vụ!',
            'err_no_task'      => 'Không lấy được nhiệm vụ. Hãy thử lại!',
            'err_conn'         => 'Lỗi kết nối khi tải nhiệm vụ!',
            // Màu sắc
            'card_bg'          => '#ffffff',
            'text_color'       => '#333333',
            'title_color'      => '#ff0000',
            'note_color'       => '#0066ff',
            'step_color'       => '#ff0000',
            'keyword_bg'       => '#333333',
            'keyword_color'    => '#ffffff',
            'copy_bg'          => '#007bff',
            'copy_color'       => '#ffffff',
            'submit_bg'        => '#222222',
            'submit_color'     => '#ffffff',
            'input_border'     => '#cccccc',
            'close_color'      => '#888888',
        );
    }

    private static $text_keys = array(
        'task_btn_label', 'task_btn_loading', 'loading_text', 'top_placeholder', 'top_btn_label',
        'bottom_placeholder', 'bottom_btn_label', 'step_prefix', 'step1_text', 'step1_or_text', 'or_word',
        'copy_label', 'copied_label', 'step2_text', 'step3_text', 'step4_default',
        'err_empty_code', 'err_no_task', 'err_conn', 'note',
    );
    private static $html_keys  = array('title', 'step3_hint');
    private static $color_keys = array(
        'card_bg', 'text_color', 'title_color', 'note_color', 'step_color', 'keyword_bg', 'keyword_color',
        'copy_bg', 'copy_color', 'submit_bg', 'submit_color', 'input_border', 'close_color',
    );
    private static $flag_keys  = array('show_bottom_form', 'show_google_mock');

    public static function get_config() {
        $saved = get_option(self::OPTION, array());
        $cfg = self::defaults();
        if (is_array($saved)) {
            foreach ($saved as $k => $v) {
                if (array_key_exists($k, $cfg) && $v !== '' && $v !== null) $cfg[$k] = $v;
            }
            // Cờ bật/tắt cho phép giá trị 0
            foreach (self::$flag_keys as $k) {
                if (isset($saved[$k])) $cfg[$k] = intval($saved[$k]);
            }
            // Các trường được phép để trống
            foreach (array('note', 'step3_hint', 'step3_hint_img') as $k) {
                if (isset($saved[$k])) $cfg[$k] = $saved[$k];
            }
        }
        return $cfg;
    }

    /** Làm sạch dữ liệu thô (từ form hoặc ajax preview) thành cấu hình hợp lệ. */
    public static function sanitize($raw) {
        $d = self::defaults();
        $out = array();
        $allowed = array('br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'span' => array('style' => array()));
        foreach (self::$text_keys as $k) {
            $out[$k] = isset($raw[$k]) ? sanitize_text_field(wp_unslash($raw[$k])) : $d[$k];
        }
        foreach (self::$html_keys as $k) {
            $out[$k] = isset($raw[$k]) ? wp_kses(wp_unslash($raw[$k]), $allowed) : $d[$k];
        }
        foreach (self::$color_keys as $k) {
            $c = isset($raw[$k]) ? sanitize_hex_color(wp_unslash($raw[$k])) : '';
            $out[$k] = $c ? $c : $d[$k];
        }
        foreach (self::$flag_keys as $k) {
            $out[$k] = !empty($raw[$k]) ? 1 : 0;
        }
        $out['step3_hint_img'] = isset($raw['step3_hint_img']) ? esc_url_raw(wp_unslash($raw['step3_hint_img'])) : '';
        return $out;
    }

    public static function save_from_post() {
        if (!isset($_POST['iats_popup'])) return;
        update_option(self::OPTION, self::sanitize((array) $_POST['iats_popup']));
    }

    // ------------------------------------------------------------------
    // RENDER
    // ------------------------------------------------------------------
    private static function e($s) { return esc_html($s); }

    /** Phần hướng dẫn các bước (đổ vào #ts-task-instructions). $task: detail, detail2, website, img3, img4, step4 */
    public static function render_instructions($cfg, $task) {
        $e = array(__CLASS__, 'e');
        $step = '<strong style="color:' . esc_attr($cfg['step_color']) . '; font-weight:bold;">' . $e($cfg['step_prefix']);
        $kw = 'background:' . esc_attr($cfg['keyword_bg']) . '; color:' . esc_attr($cfg['keyword_color']) . '; padding:4px 10px; border-radius:6px; font-family:monospace; margin:0 8px;';
        $copy = 'background:' . esc_attr($cfg['copy_bg']) . '; color:' . esc_attr($cfg['copy_color']) . '; border:none; border-radius:8px; font-size:0.9rem; padding:5px 12px; cursor:pointer;';
        $copy_btn = function ($text) use ($cfg, $copy) {
            return '<button type="button" class="ts-btn-copy" data-text="' . esc_attr($text) . '" data-copied="' . esc_attr($cfg['copied_label']) . '" style="' . $copy . '">' . esc_html($cfg['copy_label']) . '</button>';
        };

        $h  = '<div class="ts-steps" style="text-align:left; line-height:1.5; font-size:14px; color:' . esc_attr($cfg['text_color']) . ';">';
        $h .= '<div class="step-items" style="display:flex; align-items:center; padding-bottom:4px; flex-wrap:wrap;">'
            . '<p style="margin:0;">' . $step . ' 1:</strong> ' . $e($cfg['step1_text']) . '</p>'
            . '<strong style="' . $kw . '">' . $e($task['detail']) . '</strong>' . $copy_btn($task['detail']) . '</div>';

        if (!empty($task['detail2'])) {
            $h .= '<div class="step-items" style="display:flex; align-items:center; padding-bottom:4px; flex-wrap:wrap;">'
                . '<p style="margin:0;"><strong>' . $e($cfg['or_word']) . '</strong> ' . $e($cfg['step1_or_text'])
                . ' <strong style="' . $kw . '">' . $e($task['detail2']) . '</strong></p>' . $copy_btn($task['detail2']) . '</div>';
        }

        $h .= '<p style="margin:10px 0; font-size:16px;">' . $step . ' 2:</strong> ' . $e($cfg['step2_text'])
            . ' <a href="https://google.com/" target="_blank" rel="noopener" style="color:#1a0dab; text-decoration:none; font-weight:500;">google.com '
            . '<img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google" style="height:20px; vertical-align:middle; margin-left:2px;"></a></p>';

        if (!empty($cfg['show_google_mock'])) {
            $h .= '<div style="border:1px solid #dfe1e5; border-radius:24px; padding:10px 14px; display:flex; align-items:center; max-width:450px; margin:10px 0 20px; background:#fff; box-shadow:0 1px 4px rgba(32,33,36,.15);">'
                . '<svg viewBox="0 0 24 24" style="fill:#9aa0a6; width:20px; height:20px; margin-right:12px; flex-shrink:0;"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path></svg>'
                . '<span style="color:' . esc_attr($cfg['step_color']) . '; font-size:15px; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Nhập từ khóa và tìm kiếm</span></div>';
        }

        if (!empty($task['img3'])) {
            $s3 = str_replace('{website}', '<strong style="color:' . esc_attr($cfg['step_color']) . ';">' . esc_html($task['website']) . '</strong>', esc_html($cfg['step3_text']));
            $h .= '<p style="margin:10px 0;">' . $step . ' 3:</strong> ' . $s3 . '</p>'
                . '<div style="margin:10px 0;"><img src="' . esc_url($task['img3']) . '" alt="" style="width:100%; border-radius:8px; border:2px solid #ddd;"></div>';
            if (!empty($cfg['step3_hint']) || !empty($cfg['step3_hint_img'])) {
                $h .= '<p style="font-size:0.9em; font-style:italic; margin:5px 0;">' . $cfg['step3_hint'];
                if (!empty($cfg['step3_hint_img'])) {
                    $h .= ' <img src="' . esc_url($cfg['step3_hint_img']) . '" alt="" width="250" style="display:inline-block; vertical-align:middle; margin-left:5px;">';
                }
                $h .= '</p>';
            }
        }

        $step4 = !empty($task['step4']) ? $task['step4'] : $cfg['step4_default'];
        $h .= '<p style="margin:10px 0;">' . $step . ' 4:</strong> ' . $e($step4) . '</p>';
        if (!empty($task['img4'])) {
            $h .= '<div style="margin:10px 0;"><img src="' . esc_url($task['img4']) . '" alt="" style="width:100%; border-radius:8px; border:2px solid #ddd;"></div>';
        }
        $h .= '</div>';
        return $h;
    }

    /** Toàn bộ thẻ popup (thay cho .ts-modal-card trong plugin trắc nghiệm). */
    public static function render_card($cfg, $instructions_html = null) {
        $inp = 'padding:10px 15px; width:100%; max-width:260px; border:1px solid ' . esc_attr($cfg['input_border']) . '; border-radius:8px; font-size:14px; outline:none; margin:0;';
        $btn = 'background:' . esc_attr($cfg['submit_bg']) . '; color:' . esc_attr($cfg['submit_color']) . '; border-radius:99px; border:none; padding:10px 24px; font-size:14px; font-weight:bold; cursor:pointer; transition:0.2s; white-space:nowrap; margin:0;';
        $group = function ($suffix, $placeholder, $label) use ($inp, $btn) {
            return '<div style="margin:20px 0;" class="ts-locker-form-group"><div style="display:flex; gap:10px; justify-content:center; align-items:center; flex-wrap:wrap;">'
                . '<input type="text" id="ts-unlock-code-input-' . $suffix . '" placeholder="' . esc_attr($placeholder) . '" style="' . $inp . '">'
                . '<button type="button" id="ts-unlock-submit-btn-' . $suffix . '" style="' . $btn . '">' . esc_html($label) . '</button></div>'
                . '<div id="ts-unlock-error-' . $suffix . '" style="display:none; color:red; margin-top:8px; min-height:20px;"></div></div>';
        };

        $h  = '<style>'
            . '@keyframes ts-spin{100%{transform:rotate(360deg);}}'
            . '@media (max-width:480px){'
            . '#ts-lock-modal-card{padding:15px 12px !important;}'
            . '#ts-lock-modal-card .title{font-size:16px !important; margin-bottom:5px !important;}'
            . '#ts-lock-modal-card .title br{display:none;}'
            . '#ts-lock-modal-card .note{font-size:12px !important; margin-bottom:12px !important;}'
            . '#ts-lock-modal-card .ts-locker-form-group{margin:10px 0 !important; gap:6px !important;}'
            . '#ts-lock-modal-card .ts-locker-form-group input{max-width:unset !important; flex:1; min-width:0; padding:10px !important; font-size:13px !important;}'
            . '#ts-lock-modal-card .ts-locker-form-group button{width:auto !important; padding:10px 14px !important; font-size:12px !important; flex-shrink:0;}'
            . '}</style>';
        $h .= '<div class="ts-modal-card" id="ts-lock-modal-card" style="max-width:650px; width:95%; background:' . esc_attr($cfg['card_bg']) . '; border-radius:12px; padding:25px 20px; color:' . esc_attr($cfg['text_color']) . '; text-align:center; margin:auto; position:relative; max-height:90vh; overflow-y:auto;">'
            . '<span id="ts-lock-modal-close" style="position:absolute; top:12px; right:18px; font-size:26px; cursor:pointer; color:' . esc_attr($cfg['close_color']) . '; line-height:1; font-weight:bold;">&times;</span>'
            . '<h2 class="title" style="color:' . esc_attr($cfg['title_color']) . '; margin-bottom:10px; font-size:20px; font-weight:bold; line-height:1.4;">' . $cfg['title'] . '</h2>'
            . '<p class="note" style="color:' . esc_attr($cfg['note_color']) . '; font-weight:bold; margin-bottom:20px; font-size:14px;">' . esc_html($cfg['note']) . '</p>'
            . '<div class="ts-task-box" style="margin-bottom:0;">'
            . '<button type="button" id="ts-portal-task-btn" style="display:none; border:none; cursor:pointer; font-family:inherit; width:100%; padding:10px 20px; border-radius:8px; font-weight:700; background:' . esc_attr($cfg['submit_bg']) . '; color:' . esc_attr($cfg['submit_color']) . ';">' . esc_html($cfg['task_btn_label']) . '</button>'
            . '<div id="ts-task-loading-spinner" style="display:none; margin:30px 0;">'
            . '<svg viewBox="0 0 50 50" style="width:40px; height:40px; animation:ts-spin 1s linear infinite; margin:0 auto; display:block;"><circle cx="25" cy="25" r="20" fill="none" stroke="#222" stroke-width="4" stroke-dasharray="31.4 31.4" stroke-linecap="round"></circle></svg>'
            . '<p style="margin-top:15px; color:#555; font-size:14px; font-weight:bold;">' . esc_html($cfg['loading_text']) . '</p></div>'
            . $group('top', $cfg['top_placeholder'], $cfg['top_btn_label'])
            . '<div id="ts-task-instructions" style="' . ($instructions_html !== null ? '' : 'display:none; ') . 'text-align:left; margin-top:15px; padding-top:15px; border-top:1px dashed #cbd5e1;">' . ($instructions_html !== null ? $instructions_html : '') . '</div>'
            . '</div>';
        if (!empty($cfg['show_bottom_form'])) {
            $h .= $group('bottom', $cfg['bottom_placeholder'], $cfg['bottom_btn_label']);
        }
        $h .= '</div>';
        return $h;
    }

    /** Dữ liệu mẫu để xem trước trong trang cài đặt. */
    public static function sample_task() {
        $ph = function ($t) {
            return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="160"><rect width="100%" height="100%" fill="#eef2f7"/><text x="50%" y="50%" fill="#8895a7" font-family="Arial" font-size="20" text-anchor="middle">' . $t . '</text></svg>');
        };
        return array(
            'detail' => 'từ khóa mẫu', 'detail2' => 'từ khóa dự phòng', 'website' => 'example.com',
            'img3' => $ph('Ảnh minh họa bước 3'), 'img4' => $ph('Ảnh minh họa bước 4'), 'step4' => '',
        );
    }

    // ------------------------------------------------------------------
    // REST + AJAX
    // ------------------------------------------------------------------
    public static function rest_config() {
        $cfg = self::get_config();
        return rest_ensure_response(array(
            'success' => true,
            'data'    => array(
                'card_html'      => self::render_card($cfg),
                'err_empty_code' => $cfg['err_empty_code'],
                'err_no_task'    => $cfg['err_no_task'],
                'err_conn'       => $cfg['err_conn'],
                'task_btn_label' => $cfg['task_btn_label'],
                'task_btn_loading' => $cfg['task_btn_loading'],
            ),
        ));
    }

    public static function ajax_preview() {
        if (!current_user_can('manage_options') || !check_ajax_referer('iats_popup_preview', 'nonce', false)) {
            wp_send_json_error();
        }
        $cfg = self::sanitize(isset($_POST['iats_popup']) ? (array) $_POST['iats_popup'] : array());
        wp_send_json_success(array('html' => self::render_card($cfg, self::render_instructions($cfg, self::sample_task()))));
    }

    // ------------------------------------------------------------------
    // ADMIN UI (nằm trong "Cài Đặt Hệ Thống")
    // ------------------------------------------------------------------
    public static function render_settings_section() {
        $c = self::get_config();
        $nonce = wp_create_nonce('iats_popup_preview');
        $txt = function ($key, $label, $desc = '') use ($c) {
            echo '<tr><th>' . esc_html($label) . '</th><td><input type="text" class="large-text iats-pp-field" name="iats_popup[' . esc_attr($key) . ']" value="' . esc_attr($c[$key]) . '">'
                . ($desc ? '<p class="description">' . wp_kses_post($desc) . '</p>' : '') . '</td></tr>';
        };
        $area = function ($key, $label, $desc = '') use ($c) {
            echo '<tr><th>' . esc_html($label) . '</th><td><textarea rows="2" class="large-text iats-pp-field" name="iats_popup[' . esc_attr($key) . ']">' . esc_textarea($c[$key]) . '</textarea>'
                . ($desc ? '<p class="description">' . wp_kses_post($desc) . '</p>' : '') . '</td></tr>';
        };
        $color = function ($key, $label) use ($c) {
            echo '<tr><th>' . esc_html($label) . '</th><td><input type="color" class="iats-pp-field" name="iats_popup[' . esc_attr($key) . ']" value="' . esc_attr($c[$key]) . '"></td></tr>';
        };
        $flag = function ($key, $label) use ($c) {
            echo '<tr><th>' . esc_html($label) . '</th><td><label><input type="checkbox" class="iats-pp-field" name="iats_popup[' . esc_attr($key) . ']" value="1" ' . checked(!empty($c[$key]), true, false) . '> Bật</label></td></tr>';
        };
        ?>
        <hr>
        <h2>Giao diện Popup Nhiệm Vụ</h2>
        <p class="description">Popup hiện trên mọi website trắc nghiệm khi người dùng nộp bài có tag nhiệm vụ. Chỉnh ở đây, các website sẽ tự cập nhật. Xem trước bên phải thay đổi ngay khi bạn gõ.</p>
        <div style="display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">
            <div style="flex:1 1 520px; min-width:320px;">
                <h3>Nội dung</h3>
                <table class="form-table" role="presentation">
                    <?php
                    $area('title', 'Tiêu đề', 'Cho phép <code>&lt;br&gt;</code>, <code>&lt;strong&gt;</code>.');
                    $txt('note', 'Dòng lưu ý');
                    $txt('top_placeholder', 'Ô nhập mã (trên) - gợi ý');
                    $txt('top_btn_label', 'Nút nhận kết quả (trên)');
                    $flag('show_bottom_form', 'Hiện ô nhập mã phía dưới');
                    $txt('bottom_placeholder', 'Ô nhập mã (dưới) - gợi ý');
                    $txt('bottom_btn_label', 'Nút xác nhận (dưới)');
                    $txt('loading_text', 'Chữ khi đang tải nhiệm vụ');
                    $txt('task_btn_label', 'Nút thử lại lấy nhiệm vụ');
                    $txt('task_btn_loading', 'Chữ nút khi đang tải');
                    ?>
                </table>
                <h3>Các bước hướng dẫn</h3>
                <table class="form-table" role="presentation">
                    <?php
                    $txt('step_prefix', 'Tiền tố bước', 'Ví dụ: "Bước" → "Bước 1:".');
                    $txt('step1_text', 'Bước 1');
                    $txt('or_word', 'Chữ "HOẶC"');
                    $txt('step1_or_text', 'Từ khóa thứ 2');
                    $txt('copy_label', 'Nút copy');
                    $txt('copied_label', 'Nút copy sau khi bấm');
                    $txt('step2_text', 'Bước 2');
                    $flag('show_google_mock', 'Hiện thanh tìm kiếm Google minh họa');
                    $txt('step3_text', 'Bước 3', 'Dùng <code>{website}</code> để chèn tên website của nhiệm vụ. Chỉ hiện khi nhiệm vụ có ảnh bước 3.');
                    $area('step3_hint', 'Ghi chú dưới ảnh bước 3', 'Cho phép <code>&lt;strong&gt;</code>.');
                    ?>
                    <tr><th>Ảnh ghi chú bước 3</th><td>
                        <input type="text" id="iats_pp_hint_img" class="regular-text iats-pp-field" name="iats_popup[step3_hint_img]" value="<?php echo esc_attr($c['step3_hint_img']); ?>">
                        <button type="button" class="button" onclick="iatsOpenMedia('iats_pp_hint_img')">Tải ảnh lên</button>
                        <p class="description">Dùng link đầy đủ (https://...), vì popup hiển thị trên website khác.</p>
                    </td></tr>
                    <?php $txt('step4_default', 'Bước 4 mặc định', 'Dùng khi nhiệm vụ không nhập nội dung bước 4.'); ?>
                </table>
                <h3>Thông báo lỗi</h3>
                <table class="form-table" role="presentation">
                    <?php
                    $txt('err_empty_code', 'Chưa nhập mã');
                    $txt('err_no_task', 'Không có nhiệm vụ khả dụng');
                    $txt('err_conn', 'Lỗi kết nối');
                    ?>
                </table>
                <h3>Màu sắc</h3>
                <table class="form-table" role="presentation">
                    <?php
                    $color('card_bg', 'Nền popup');
                    $color('text_color', 'Màu chữ');
                    $color('title_color', 'Màu tiêu đề');
                    $color('note_color', 'Màu dòng lưu ý');
                    $color('step_color', 'Màu chữ "Bước"');
                    $color('keyword_bg', 'Nền ô từ khóa');
                    $color('keyword_color', 'Chữ ô từ khóa');
                    $color('copy_bg', 'Nền nút Copy');
                    $color('copy_color', 'Chữ nút Copy');
                    $color('submit_bg', 'Nền nút xác nhận');
                    $color('submit_color', 'Chữ nút xác nhận');
                    $color('input_border', 'Viền ô nhập mã');
                    $color('close_color', 'Nút đóng (×)');
                    ?>
                </table>
            </div>
            <div style="flex:1 1 440px; min-width:320px; position:sticky; top:40px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong>Xem trước popup</strong>
                    <span>
                        <button type="button" class="button iats-pp-width" data-w="100%">Máy tính</button>
                        <button type="button" class="button iats-pp-width" data-w="380px">Điện thoại</button>
                    </span>
                </div>
                <div style="background:#dcdcde; padding:10px; border-radius:6px; text-align:center;">
                    <iframe id="iats-pp-preview" title="Xem trước popup" style="width:100%; height:640px; border:0; background:rgba(0,0,0,.55); border-radius:6px; transition:width .2s;"></iframe>
                </div>
            </div>
        </div>
        <script>
        jQuery(function($){
            var timer, $frame = $('#iats-pp-preview'), nonce = '<?php echo esc_js($nonce); ?>';
            function refresh() {
                var data = $('.iats-pp-field').serialize();
                // Checkbox chưa tick không được serialize → gửi trạng thái rõ ràng cho server
                $.post(ajaxurl, data + '&action=iats_popup_preview&nonce=' + nonce, function(res){
                    if (!res || !res.success) return;
                    $frame.attr('srcdoc', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
                        + '<body style="margin:0; padding:24px 10px; font-family:-apple-system,Segoe UI,Roboto,sans-serif; background:rgba(0,0,0,.55);">' + res.data.html + '</body></html>');
                });
            }
            $(document).on('input change', '.iats-pp-field', function(){ clearTimeout(timer); timer = setTimeout(refresh, 300); });
            $('.iats-pp-width').on('click', function(){ $frame.css({width: $(this).data('w'), margin: '0 auto', display: 'block'}); });
            refresh();
        });
        </script>
        <?php
    }
}

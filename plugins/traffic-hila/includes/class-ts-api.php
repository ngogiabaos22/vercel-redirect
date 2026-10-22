<?php
if (!defined('ABSPATH')) {
    exit;
}

class IATS_API {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'allow_cors_headers'), 15);
    }
    
    public function allow_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function($value) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-WP-Nonce');
            if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
                status_header(200); exit();
            }
            return $value;
        });
    }
    
    public function register_routes() {
        register_rest_route('iats/v1', '/task', array(
            'methods'  => 'GET',
            'callback' => array($this, 'get_task'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('iats/v1', '/popup/config', array(
            'methods'  => 'GET',
            'callback' => array('IATS_Popup', 'rest_config'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('iats/v1', '/client/init', array(
            'methods'  => 'POST',
            'callback' => array($this, 'client_init'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('iats/v1', '/code/generate', array(
            'methods'  => 'POST',
            'callback' => array($this, 'generate_code'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('iats/v1', '/code/verify', array(
            'methods'  => 'POST',
            'callback' => array($this, 'verify_code'),
            'permission_callback' => '__return_true'
        ));
    }
    
    private function get_client_ip() {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    // 1. GET /task -> Bốc 1 nhiệm vụ từ bảng iats_tasks (Có khóa theo IP)
    public function get_task($request) {
        global $wpdb;
        $ip = $this->get_client_ip();
        $tag = sanitize_text_field($request->get_param('tag'));
        $where_tag = $tag ? $wpdb->prepare("AND tag = %s", $tag) : '';
        
        // 1. Kiểm tra IP có task đang bị khóa (pending) trong 60 phút qua không
        $pending_log = $wpdb->get_row($wpdb->prepare(
            "SELECT project_id FROM " . IATS_TABLE_LOGS . " 
            WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) 
            ORDER BY id DESC LIMIT 1", $ip
        ));
        
        $item = null;
        if ($pending_log) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . IATS_TABLE_TASKS . " WHERE id = %d AND active = 1", $pending_log->project_id));
        }
        
        // 2. Nếu không có task bị khóa (hoặc task đó đã bị xóa/tắt), thì bốc task mới
        if (!$item) {
            // Hủy các task pending cũ của IP này để tránh rác
            $wpdb->query($wpdb->prepare("UPDATE " . IATS_TABLE_LOGS . " SET status = 'cancelled' WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending'", $ip));
            
            // Ưu tiên phân phối
            $item = $wpdb->get_row("SELECT * FROM " . IATS_TABLE_TASKS . " WHERE active = 1 $where_tag AND (count_limit = 0 OR count_get < count_limit) AND (limit_per_day = 0 OR count < limit_per_day) ORDER BY priority DESC, count_get ASC LIMIT 1");
            if (!$item) {
                $item = $wpdb->get_row("SELECT * FROM " . IATS_TABLE_TASKS . " WHERE active = 1 $where_tag AND (count_limit = 0 OR count_get < count_limit) AND (limit_per_day = 0 OR count < limit_per_day) ORDER BY priority DESC, RAND() LIMIT 1");
            }
            if (!$item) {
                return rest_ensure_response(array('success' => false, 'code' => 'no_data', 'message' => 'Không có nhiệm vụ nào khả dụng lúc này.'));
            }
            
            // Cập nhật lượt lấy (popup_views)
            $wpdb->query($wpdb->prepare("UPDATE " . IATS_TABLE_TASKS . " SET popup_views = popup_views + 1 WHERE id = %d", $item->id));
            
            // Ghi log khóa task
            $wpdb->insert(
                IATS_TABLE_LOGS,
                array(
                    'project_id' => $item->id,
                    'ip_address' => $ip,
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '',
                    'event_type' => 'get_task',
                    'status' => 'pending'
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }

        $data = array(
            'id' => $item->id, // Chính là project_id (hoặc task id)
            'website' => $item->website,
            'detail' => $item->detail, 
            'detail2' => $item->detail2, 
            'img3' => $item->img3,
            'img4' => $item->img4,
            'step4' => $item->step4
        );
        // HTML hướng dẫn dựng sẵn theo cấu hình popup ở plugin quản lý
        $data['instructions_html'] = IATS_Popup::render_instructions(IATS_Popup::get_config(), $data);
        
        return rest_ensure_response(array('success' => true, 'data' => $data));
    }
    
    // 2. POST /client/init
    public function client_init($request) {
        global $wpdb;
        $params = $request->get_params();
        $domain = isset($params['domain']) ? sanitize_text_field($params['domain']) : '';
        $url = isset($params['url']) ? esc_url_raw($params['url']) : '';
        $has_task = false;
        
        if ($domain && $url) {
            $ip = $this->get_client_ip();
            $pending_log = $wpdb->get_row($wpdb->prepare(
                "SELECT project_id FROM " . IATS_TABLE_LOGS . " 
                WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) 
                ORDER BY id DESC LIMIT 1", $ip
            ));

            if ($pending_log) {
                $clean_url = preg_replace('#^https?://(www\.)?#', '', rtrim($url, '/'));
                $task = $wpdb->get_row($wpdb->prepare("SELECT id FROM " . IATS_TABLE_TASKS . " 
                    WHERE id = %d AND active = 1 AND website = %s AND (count_limit = 0 OR count_get < count_limit) AND (limit_per_day = 0 OR count < limit_per_day) 
                    AND (target_url IS NULL OR target_url = '' OR target_url LIKE %s) LIMIT 1", 
                    $pending_log->project_id, $domain, '%' . $wpdb->esc_like($clean_url) . '%'
                ));
                if ($task) $has_task = true;
            }
        }

        $default_style = get_option('iats_btn_style_default', []);
        $domain_style = $domain ? get_option('iats_btn_style_' . $domain, []) : [];
        
        $merged = array(
            'bg' => '#E91E63', 'color' => '#FFFFFF', 'text' => 'Lấy Mã Xác Nhận', 'logo' => '',
            'border_radius' => 5, 'padding' => '12px 24px', 'box_title' => 'Mã của bạn là:', 
            'box_bg' => '#e0f7fa', 'box_border' => '#b2dfdb', 'box_text_color' => '#555555', 'box_code_color' => '#00796b'
        );
        
        if(is_array($default_style)) {
            foreach($default_style as $k => $v) if($v !== '' && $v !== null) $merged[$k] = $v;
        }
        if(is_array($domain_style)) {
            foreach($domain_style as $k => $v) if($v !== '' && $v !== null) $merged[$k] = $v;
        }

        // Bỏ qua tùy chỉnh logo url, bắt buộc dùng Ảnh Nút từ cài đặt hệ thống
        $merged['logo'] = get_option('iats_button_logo', '');

        return rest_ensure_response(array(
            'success' => true,
            'has_task' => $has_task,
            'data' => $merged
        ));
    }

    // 3. POST /code/generate -> Script gọi sau 60s
    public function generate_code($request) {
        global $wpdb;
        $params = $request->get_params();
        $project_id = isset($params['project_id']) ? intval($params['project_id']) : 0;
        $domain = isset($params['domain']) ? sanitize_text_field($params['domain']) : '';
        
        if (!$project_id && !$domain) {
            return rest_ensure_response(array('success' => false, 'message' => 'Thiếu Project ID hoặc Domain.'));
        }
        
        $ip = $this->get_client_ip();
        
        // Bắt buộc phải có task pending từ trước (đã lấy nhiệm vụ)
        $pending_log = $wpdb->get_row($wpdb->prepare(
            "SELECT project_id, id as log_id FROM " . IATS_TABLE_LOGS . " 
            WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) 
            ORDER BY id DESC LIMIT 1", $ip
        ));
        
        $task = null;
        if ($pending_log) {
            // Lấy task dựa vào log đã cấp
            $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . IATS_TABLE_TASKS . " WHERE id = %d", $pending_log->project_id));
        }
        
        if (!$task || !$task->active) {
            return rest_ensure_response(array('success' => false, 'message' => "Bạn chưa tạo bất kỳ nhiệm vụ nào trên thiết bị này. Vui lòng sử dụng đúng thiết bị đã tạo nhiệm vụ hoặc tạo lại một nhiệm vụ mới."));
        }
        $project_id = $task->id;
        
        if ($task->count_limit > 0 && $task->count >= $task->count_limit) {
            return rest_ensure_response(array('success' => false, 'message' => 'Nhiệm vụ này đã đạt giới hạn trong ngày.'));
        }
        

        // Ghi Log
        $ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $referrer = !empty($params['url']) ? esc_url_raw($params['url']) : (isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '');
        
        // Cập nhật lại log pending thành công, hoặc ghi log mới
        if (!empty($pending_log)) {
            $wpdb->query($wpdb->prepare("UPDATE " . IATS_TABLE_LOGS . " SET status = 'success' WHERE id = %d", $pending_log->log_id));
        }
        
        $wpdb->insert(
            IATS_TABLE_LOGS,
            array(
                'project_id' => $project_id,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'referrer' => $referrer,
                'event_type' => 'get_code',
                'status' => 'success'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Sinh Mã mở khóa ngẫu nhiên hoặc dùng mã cố định
        $code = !empty($task->fixed_code) ? $task->fixed_code : strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); 
        
        $wpdb->insert(
            IATS_TABLE_CODES,
            array(
                'code_string' => $code,
                'project_id' => $project_id,
                'ip_address' => $ip,
                'status' => 'unused'
            ),
            array('%s', '%d', '%s', '%s')
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'code' => $code,
            'message' => 'Nhận mã thành công!'
        ));
    }
    
    // 4. POST /code/verify
    public function verify_code($request) {
        global $wpdb;
        $params = $request->get_params();
        $code = isset($params['code']) ? sanitize_text_field($params['code']) : '';
        
        if (empty($code)) {
            return rest_ensure_response(array('success' => false, 'message' => 'Vui lòng nhập mã.'));
        }
        
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . IATS_TABLE_CODES . " WHERE code_string = %s", $code));
        
        if (!$row) {
            $ip = $this->get_client_ip();
            $pending_log = $wpdb->get_row($wpdb->prepare("SELECT project_id FROM " . IATS_TABLE_LOGS . " WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending' ORDER BY id DESC LIMIT 1", $ip));
            $pid = $pending_log ? $pending_log->project_id : 0;
            $wpdb->insert(
                IATS_TABLE_LOGS,
                array(
                    'project_id' => $pid,
                    'ip_address' => $ip,
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '',
                    'event_type' => 'verify_code',
                    'status' => 'wrong_code'
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
            return rest_ensure_response(array('success' => false, 'message' => 'Mã không tồn tại hoặc đã nhập sai. Vui lòng kiểm tra lại.'));
        }
        
        // Mã phải thuộc nhiệm vụ đúng tag của đề thi (nếu website trắc nghiệm có gửi tag)
        $tag = isset($params['tag']) ? sanitize_text_field($params['tag']) : '';
        if ($tag !== '') {
            $task_tag = $wpdb->get_var($wpdb->prepare("SELECT tag FROM " . IATS_TABLE_TASKS . " WHERE id = %d", $row->project_id));
            if ((string) $task_tag !== $tag) {
                return rest_ensure_response(array('success' => false, 'message' => 'Mã này không dùng được cho bài thi này. Vui lòng làm đúng nhiệm vụ được giao.'));
            }
        }

        if ($row->status === 'used') {
            return rest_ensure_response(array('success' => false, 'message' => 'Mã này đã được người khác sử dụng. Bạn phải làm nhiệm vụ mới!'));
        }
        
        // Cập nhật trạng thái mã
        $wpdb->update(
            IATS_TABLE_CODES,
            array('status' => 'used', 'used_at' => current_time('mysql')),
            array('id' => $row->id),
            array('%s', '%s'),
            array('%d')
        );

        // Ghi Log Verify Code
        $ip = $this->get_client_ip();
        $wpdb->insert(
            IATS_TABLE_LOGS,
            array(
                'project_id' => $row->project_id,
                'ip_address' => $ip,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '',
                'event_type' => 'verify_code',
                'status' => 'success'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        // [QUAN TRỌNG]: Tăng lượt View (count hôm nay) và Tổng View (count_get)
        $wpdb->query($wpdb->prepare("UPDATE " . IATS_TABLE_TASKS . " SET count = count + 1, count_get = count_get + 1 WHERE id = %d", $row->project_id));
        
        // Mở khóa task cho IP này (Đổi status 'pending' thành 'completed')
        $wpdb->query($wpdb->prepare("UPDATE " . IATS_TABLE_LOGS . " SET status = 'completed' WHERE ip_address = %s AND event_type = 'get_task' AND status = 'pending'", $ip));
        
        return rest_ensure_response(array('success' => true, 'message' => 'Mã hợp lệ! Đang mở khóa kết quả...'));
    }
}

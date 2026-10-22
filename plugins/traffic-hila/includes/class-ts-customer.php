<?php
if (!defined('ABSPATH')) exit;

class IATS_Customer_Portal {
    public function __construct() {
        add_shortcode('iats_customer_portal', array($this, 'render_portal'));
        add_shortcode('iats_auth_form', array($this, 'render_auth_form_shortcode'));
        add_action('wp_ajax_nopriv_iats_login', array($this, 'ajax_login'));
        add_action('wp_ajax_nopriv_iats_register', array($this, 'ajax_register'));
        add_action('wp_ajax_iats_customer_logout', array($this, 'ajax_logout'));
        add_action('wp_ajax_iats_customer_add_task', array($this, 'ajax_add_task'));
        add_action('wp_ajax_iats_customer_get_stats', array($this, 'ajax_customer_get_stats'));
        add_action('wp_ajax_iats_customer_get_logs', array($this, 'ajax_customer_get_logs'));
        
        add_action('wp_ajax_iats_admin_update_wallet', array($this, 'ajax_admin_update_wallet'));
        add_action('wp_ajax_iats_admin_update_settings', array($this, 'ajax_admin_update_settings'));
        add_action('wp_ajax_iats_toggle_priority', array($this, 'ajax_toggle_priority'));
        add_action('wp_ajax_iats_admin_add_task', array($this, 'ajax_admin_add_task'));
        add_action('wp_ajax_iats_admin_delete_task', array($this, 'ajax_admin_delete_task'));
        add_action('wp_ajax_iats_admin_add_user', array($this, 'ajax_admin_add_user'));
        add_action('wp_ajax_iats_admin_delete_user', array($this, 'ajax_admin_delete_user'));
        add_action('init', array($this, 'handle_export_csv'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_wp_media'));
    }

    public function enqueue_wp_media() {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            wp_enqueue_media();
        }
    }

    private function get_device_from_ua($ua) {
        if (preg_match('/Mobile|Android|BlackBerry|iPhone|Windows Phone/i', $ua)) return 'Mobile';
        return 'Desktop';
    }

    private function get_browser_from_ua($ua) {
        if (preg_match('/Edg/i', $ua)) return 'Edge';
        if (preg_match('/Chrome/i', $ua)) return 'Chrome';
        if (preg_match('/Safari/i', $ua)) return 'Safari';
        if (preg_match('/Firefox/i', $ua)) return 'Firefox';
        return 'Khác';
    }

    public function handle_export_csv() {
        if (isset($_GET['export_iats_csv']) && is_user_logged_in() && current_user_can('manage_options')) {
            global $wpdb;
            $from = isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : '';
            $to = isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : '';
            $task = isset($_GET['task_id']) ? sanitize_text_field($_GET['task_id']) : '';
            $tag = isset($_GET['log_tag']) ? sanitize_text_field($_GET['log_tag']) : '';
            $ip = isset($_GET['log_ip']) ? sanitize_text_field($_GET['log_ip']) : '';
            
            $where = "WHERE 1=1";
            if ($from) $where .= $wpdb->prepare(" AND DATE(l.created_at) >= %s", $from);
            if ($to) $where .= $wpdb->prepare(" AND DATE(l.created_at) <= %s", $to);
            if ($task) $where .= $wpdb->prepare(" AND (l.project_id = %d OR t.detail LIKE %s)", $task, '%' . $task . '%');
            if ($tag) $where .= $wpdb->prepare(" AND t.tag = %s", $tag);
            if ($ip) $where .= $wpdb->prepare(" AND l.ip_address LIKE %s", '%' . $ip . '%');
            
            $sql = "SELECT l.*, t.detail as task_name, t.tag, t.active as task_active 
                    FROM " . IATS_TABLE_LOGS . " l
                    LEFT JOIN " . IATS_TABLE_TASKS . " t ON l.project_id = t.id
                    $where ORDER BY l.id DESC LIMIT 2000";
            $logs = $wpdb->get_results($sql);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=ThongKeTraffic_' . date('Ymd_His') . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
            fputcsv($output, ['Thời gian', 'Loại sự kiện', 'Trạng thái nhiệm vụ', 'Nút (ID)', 'Nhiệm vụ', 'Tag', 'IP', 'Thiết bị', 'Trình duyệt', 'Nguồn']);
            foreach ($logs as $l) {
                $event = 'Khác';
                if ($l->event_type == 'get_task') $event = 'Lấy mã';
                if ($l->event_type == 'verify_code' && $l->status == 'wrong_code') $event = 'Wrong code';
                if ($l->event_type == 'verify_code' && $l->status == 'success') $event = 'Done';
                if ($l->event_type == 'get_code') $event = 'Sinh mã';
                
                $status_task = $l->task_active ? 'Kích hoạt' : 'Tắt';
                $dev = $this->get_device_from_ua($l->user_agent);
                $bro = $this->get_browser_from_ua($l->user_agent);
                fputcsv($output, [$l->created_at, $event, $status_task, $l->project_id, $l->task_name, $l->tag, $l->ip_address, $dev, $bro, $l->referrer]);
            }
            fclose($output);
            exit;
        }

        if (isset($_GET['export_tasks_csv']) && is_user_logged_in() && current_user_can('manage_options')) {
            global $wpdb;
            $table = IATS_TABLE_TASKS;
            $tasks = $wpdb->get_results("SELECT t.*, (SELECT COUNT(id) FROM " . IATS_TABLE_LOGS . " l WHERE l.project_id = t.id AND l.event_type = 'verify_code' AND l.status = 'success' AND DATE(l.created_at) = CURDATE() - INTERVAL 1 DAY) as count_yesterday FROM $table t ORDER BY t.id DESC");
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=DanhSachNhiemVu_' . date('Ymd_His') . '.csv');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, ['ID', 'Domain', 'Từ khóa', 'Link Đích', 'Tag', 'Mã', 'View Hôm Nay', 'View Hôm Qua', 'Tổng View', 'Giới hạn', 'Giới hạn / ngày', 'Trạng thái', 'Ưu tiên']);
            foreach ($tasks as $t) {
                $status = $t->active ? 'Đang chạy' : 'Đã dừng';
                $priority = $t->priority ? 'Ưu tiên' : 'Không';
                fputcsv($output, ['#'.$t->id, $t->website, $t->detail, $t->target_url, $t->tag, $t->fixed_code, $t->count_get, $t->count_yesterday, $t->count, $t->count_limit, $t->limit_per_day, $status, $priority]);
            }
            fclose($output);
            exit;
        }
    }

    private function check_daily_reset() {
        $today = current_time('Y-m-d');
        if (get_option('iats_last_reset') !== $today) {
            global $wpdb;
            $sql = "UPDATE " . IATS_TABLE_TASKS . " SET count_yesterday = count, count = 0, popup_views = 0, last_reset_date = %s 
                    WHERE last_reset_date < %s OR last_reset_date IS NULL";
            $wpdb->query($wpdb->prepare($sql, $today, $today));
            update_option('iats_last_reset', $today);
        }
    }

    public function render_portal() {
        ob_start();
        $this->enqueue_assets();
        $this->check_daily_reset();
        
        if (is_user_logged_in() && current_user_can('iats_customer')) {
            $this->render_dashboard();
        } elseif (is_user_logged_in() && current_user_can('manage_options')) {
            $this->render_admin_dashboard();
        } else {
            $login_url = get_option('iats_login_url', home_url('/'));
            echo '<div style="padding:40px; text-align:center; font-family:sans-serif; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(67,89,113,0.12); margin: 40px auto; max-width:500px;"><h3 style="color:#566a7f; margin-bottom:15px; font-weight:bold;">Yêu cầu Đăng nhập</h3><p style="color:#697a8d; margin-bottom:20px; font-size:15px;">Vui lòng <a href="'.esc_url($login_url).'" style="color:#696cff; font-weight:bold;">Đăng nhập</a> để truy cập vào hệ thống.</p></div>';
            echo '<script>setTimeout(()=>window.location.href="'.esc_js($login_url).'", 2000);</script>';
        }
        
        return ob_get_clean();
    }

    public function render_auth_form_shortcode() {
        ob_start();
        $this->enqueue_assets();
        
        if (is_user_logged_in()) {
            $portal_url = get_option('iats_portal_url', home_url('/'));
            echo '<div style="padding:40px; text-align:center; font-family:sans-serif; background:white; border-radius:8px; box-shadow:0 2px 6px rgba(67,89,113,0.12); margin: 40px auto; max-width:500px;"><p style="color:#697a8d; font-size:15px;">Bạn đã đăng nhập, đang chuyển hướng vào hệ thống...</p></div>';
            echo '<script>window.location.href="'.esc_js($portal_url).'";</script>';
        } else {
            $this->render_auth_forms();
        }
        
        return ob_get_clean();
    }

    private function enqueue_assets() {
        ?>
        <style>
            :root {
                --primary: #696cff; --primary-hover: #5f61e6;
                --bg: #f5f5f9; --card: #ffffff;
                --text: #697a8d; --text-light: #a1acb8;
                --sidebar-bg: #ffffff; --sidebar-text: #697a8d;
                --menu-hover: rgba(67, 89, 113, 0.04);
                --menu-active-bg: rgba(105, 108, 255, 0.16);
                --shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            }
            .iats-wrap { 
                font-family: 'Inter', system-ui, sans-serif; 
                background: var(--bg); 
                min-height: 100vh; 
                box-sizing: border-box; 
                padding: 0;
                color: var(--text);
            }
            
            /* Sidebar Layout */
            .iats-app-container { 
                display: flex; 
                min-height: 100vh; 
                width: 100%;
                background: var(--bg); 
            }
            .iats-sidebar { width: 260px; background: var(--sidebar-bg); color: var(--sidebar-text); padding: 20px 0; display: flex; flex-direction: column; border-right: 1px solid #eceef1; }
            .iats-sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #eceef1; margin-bottom: 20px; text-align: center; }
            .iats-sidebar-header h2 { color: #566a7f; margin: 0; font-size: 22px; font-weight: bold; }
            .iats-menu { list-style: none; padding: 0; margin: 0; flex: 1; }
            .iats-menu li { padding: 10px 16px; margin: 0 16px 4px 16px; cursor: pointer; transition: 0.2s; font-size: 15px; display: flex; align-items: center; gap: 10px; border-radius: 6px; }
            .iats-menu li:hover { background: var(--menu-hover); }
            .iats-menu li.active { background: var(--menu-active-bg); color: var(--primary); font-weight: 600; }
            .iats-sidebar-footer { padding: 20px; border-top: 1px solid #eceef1; text-align: center; }
            
            /* Main Content */
            .iats-content { flex: 1; padding: 30px; overflow-y: auto; background: var(--bg); }
            .iats-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
            .iats-topbar h1 { margin: 0; font-size: 24px; color: #566a7f; font-weight: 600; }
            
            /* Elements */
            .iats-card { background: var(--card); padding: 24px; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 24px; border: none; }
            .iats-btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 500; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; text-decoration:none; box-shadow: 0 2px 4px 0 rgba(105, 108, 255, 0.4); }
            .iats-btn:hover { background: var(--primary-hover); box-shadow: 0 3px 6px 0 rgba(105, 108, 255, 0.48); transform: translateY(-1px); }
            .iats-btn-outline { background: transparent; border: 1px solid #eceef1; color: var(--text); box-shadow: none; }
            .iats-btn-outline:hover { background: rgba(67, 89, 113, 0.04); color: var(--text); box-shadow: none; transform: none; }
            .iats-input { width: 100%; padding: 10px 14px; border: 1px solid #d9dee3; border-radius: 6px; font-size: 15px; box-sizing: border-box; color: #697a8d; background: #fff; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; }
            .iats-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 0.15rem rgba(105, 108, 255, 0.1); }
            .iats-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .iats-table th, .iats-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #d9dee3; font-size: 14px; }
            .iats-table th { color: #566a7f; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
            .iats-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
            .iats-status-1 { background: #e8fadf; color: #71dd37; }
            .iats-status-0 { background: #ffe0db; color: #ff3e1d; }
            .iats-hidden { display: none !important; }
            .iats-alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; display: none; }
            .iats-alert.error { background: #ffe0db; color: #ff3e1d; display: block; }
            .iats-alert.success { background: #e8fadf; color: #71dd37; display: block; }
            .iats-alert.warning { background: #fff2d6; color: #ffab00; display: block; }

            /* Stats Cards */
            .iats-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 24px; }
            .iats-stat-card { background: white; padding: 24px; border-radius: 8px; box-shadow: var(--shadow); border: none; }
            .iats-stat-card h4 { margin: 0 0 10px 0; color: #566a7f; font-size: 15px; font-weight: 500; text-transform: none; }
            .iats-stat-card .value { font-size: 28px; font-weight: 500; color: #566a7f; margin: 0; }
            
            /* Sidebar Menu Accordion */
            .iats-menu > li.has-submenu { display: block; padding: 0; border: none; margin: 0; }
            .iats-menu .submenu { list-style:none; padding-left:0; margin:0; display:none; background: transparent; }
            .iats-menu .has-submenu.active .submenu { display:block; }
            .iats-menu .submenu li { padding: 10px 16px 10px 45px; margin: 0 16px 4px 16px; cursor: pointer; color:#697a8d; font-size: 14px; border:none; display: block; border-radius: 6px; }
            .iats-menu .submenu li.active { color: var(--primary); font-weight:bold; background: var(--menu-active-bg); }
            .iats-menu .submenu li:hover { color: var(--primary); background: var(--menu-hover); }
            .iats-menu .menu-title { padding: 10px 16px; margin: 0 16px; cursor: pointer; font-weight: bold; transition: 0.2s; display: flex; align-items: center; border-radius: 6px; }
            .iats-menu .menu-title:hover { background: var(--menu-hover); }

            /* Script Modal */
            .iats-modal { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(34,48,62,0.6); display:flex; align-items:center; justify-content:center; z-index:9999; }
            .iats-modal-content { background:white; padding:30px; border-radius:8px; width:90%; max-width:600px; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
            .iats-modal-close { float:right; cursor:pointer; font-size:24px; font-weight:bold; color:#a1acb8; line-height:20px; }
            .iats-modal-close:hover { color:#697a8d; }

            /* Tooltip and Select Fix */
            select.iats-input { height: auto !important; padding: 10px 14px !important; line-height: 1.5 !important; }
            .iats-tooltip:hover .iats-tooltip-text { opacity: 1 !important; visibility: visible !important; }

            /* Auth Login Panel */
            .iats-glass-panel { background: white; border-radius: 8px; box-shadow: var(--shadow); max-width: 400px; padding: 40px; margin: 40px auto; }
            .iats-form-group { margin-bottom: 20px; }
            .iats-form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size:14px; color: #566a7f; }
        </style>
        <?php
    }

    private function render_auth_forms() {
        ?>
        <div class="iats-wrap">
            <div class="iats-glass-panel" id="iats-login-panel">
                <h2 style="text-align:center; margin-bottom:20px;">Đăng Nhập</h2>
                <div id="iats-login-msg" class="iats-alert"></div>
                <form id="iats-login-form">
                    <div class="iats-form-group"><label>Tên đăng nhập</label><input type="text" name="username" class="iats-input" required></div>
                    <div class="iats-form-group"><label>Mật khẩu</label><input type="password" name="password" class="iats-input" required></div>
                    <button type="submit" class="iats-btn" style="width:100%;">Đăng Nhập</button>
                    <div style="text-align:center; margin-top:20px; font-size:14px;">Chưa có tài khoản? <a href="javascript:void(0)" onclick="iatsToggleAuth('register')" style="color:var(--primary);font-weight:bold;">Đăng ký</a></div>
                </form>
            </div>
            <div class="iats-glass-panel iats-hidden" id="iats-register-panel">
                <h2 style="text-align:center; margin-bottom:20px;">Đăng Ký</h2>
                <div id="iats-register-msg" class="iats-alert"></div>
                <form id="iats-register-form">
                    <div class="iats-form-group"><label>Tên đăng nhập</label><input type="text" name="username" class="iats-input" required></div>
                    <div class="iats-form-group"><label>Email</label><input type="email" name="email" class="iats-input" required></div>
                    <div class="iats-form-group"><label>Mật khẩu</label><input type="password" name="password" class="iats-input" required></div>
                    <button type="submit" class="iats-btn" style="width:100%;">Tạo Tài Khoản</button>
                    <div style="text-align:center; margin-top:20px; font-size:14px;">Đã có tài khoản? <a href="javascript:void(0)" onclick="iatsToggleAuth('login')" style="color:var(--primary);font-weight:bold;">Đăng nhập</a></div>
                </form>
            </div>
        </div>
        <script>
            function iatsToggleAuth(mode) {
                document.getElementById('iats-login-panel').classList.toggle('iats-hidden', mode !== 'login');
                document.getElementById('iats-register-panel').classList.toggle('iats-hidden', mode !== 'register');
            }
            document.getElementById('iats-login-form').addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new URLSearchParams(new FormData(e.target)); fd.append('action', 'iats_login');
                const btn = e.target.querySelector('button'); btn.textContent = 'Đang xử lý...'; btn.disabled = true;
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                    if(data.success) window.location.href = data.data.redirect_url || location.href; else { const msg = document.getElementById('iats-login-msg'); msg.className = 'iats-alert error'; msg.textContent = data.data; }
                } finally { btn.textContent = 'Đăng Nhập'; btn.disabled = false; }
            });
            document.getElementById('iats-register-form').addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new URLSearchParams(new FormData(e.target)); fd.append('action', 'iats_register');
                const btn = e.target.querySelector('button'); btn.textContent = 'Đang xử lý...'; btn.disabled = true;
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                    const msg = document.getElementById('iats-register-msg');
                    if(data.success) { msg.className = 'iats-alert success'; msg.textContent = 'Thành công!'; setTimeout(() => window.location.href = data.data.redirect_url || location.href, 1500); } 
                    else { msg.className = 'iats-alert error'; msg.textContent = data.data; }
                } finally { btn.textContent = 'Tạo Tài Khoản'; btn.disabled = false; }
            });
        </script>
        <?php
    }

    private function render_dashboard() {
        $user = wp_get_current_user();
        $balance = intval(get_user_meta($user->ID, 'iats_wallet_balance', true));
        $price = intval(get_option('iats_view_price', 3));
        global $wpdb; $table = IATS_TABLE_TASKS;
        $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC", $user->ID));
        
        $projects = [];
        foreach($tasks as $t) {
            $w = $t->website ?: 'Khác';
            if (!isset($projects[$w])) $projects[$w] = ['tasks' => 0, 'get' => 0, 'limit' => 0];
            $projects[$w]['tasks']++;
            $projects[$w]['get'] += $t->count_get;
            $projects[$w]['limit'] += $t->count_limit;
        }
        ?>
        <div class="iats-wrap">
            <div class="iats-app-container">
                <!-- Sidebar -->
                <div class="iats-sidebar">
                    <?php $plogo = get_option('iats_portal_logo', ''); ?>
                    <div class="iats-sidebar-header"><h2><?php if($plogo) echo '<img src="'.esc_url($plogo).'" style="max-height:60px;max-width:100%;" onerror="this.outerHTML=\'CUSTOMER PORTAL\'">'; else echo 'CUSTOMER PORTAL'; ?></h2></div>
                    <ul class="iats-menu" id="iats-customer-menu">
                        <li class="has-submenu active">
                            <div class="menu-title" onclick="this.parentNode.classList.toggle('active')">
                                <i class="dashicons dashicons-groups" style="margin-right:8px;"></i> TRAFFIC USER 
                                <span class="arrow" style="float:right;font-size:10px;line-height:20px;">▼</span>
                            </div>
                            <ul class="submenu">
                                <li class="active" id="c-menu-projects" onclick="switchCustomerTab('projects')">Dự án</li>
                                <li id="c-menu-stats" onclick="switchCustomerTab('stats')">Thống kê</li>
                            </ul>
                        </li>
                    </ul>
                    <div class="iats-sidebar-footer">
                        <div style="padding:15px; color:#697a8d; font-size:14px; display:none;">
                            Số dư: <strong style="color:var(--primary); font-size:18px;"><?php echo number_format($balance); ?></strong> <span style="font-size:12px">Coin</span><br>
                            <small>Giá: <?php echo $price; ?> Coin / View</small>
                        </div>
                        <button class="iats-btn iats-btn-outline" style="width:100%;" onclick="iatsLogout()">Đăng Xuất</button>
                    </div>
                </div>
                <!-- Main Content -->
                <div class="iats-content">
                    <div class="iats-topbar">
                        <h1 id="c-topbar-title">Dự án - Traffic User</h1>
                        <div>Xin chào, <strong><?php echo esc_html($user->display_name); ?></strong></div>
                    </div>

                    <!-- Tab Projects -->
                    <div id="c-tab-projects">
                        <div class="iats-card">
                            <div style="background:#FEF3C7; color:#92400E; padding:15px; border-radius:8px; margin-bottom:20px; font-size:14px; line-height:1.5;">
                                <strong>LƯU Ý QUAN TRỌNG:</strong><br>
                                - Chúng tôi nghiêm cấm việc tăng traffic cho các Website CỜ BẠC/CÁ ĐỘ/18+ và tất cả các web MẢNG ĐEN.<br>
                                - Tài khoản nào khi kiểm tra thấy có vi phạm sẽ bị HỦY TÀI KHOẢN và không hoàn lại tiền.<br>
                                - Chúng tôi sẽ luôn rà soát mỗi ngày, chỉ cung cấp cho các website chính ngạch.
                            </div>
                            <table class="iats-table">
                                <thead><tr><th>STT</th><th>Dự án</th><th>Số từ khóa</th><th>Tổng traffic</th><th>Còn lại</th><th>Script</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $stt = 1;
                                    foreach($projects as $w => $p): 
                                        $remain = $p['limit'] > 0 ? ($p['limit'] - $p['get']) : 'Không giới hạn';
                                    ?>
                                    <tr>
                                        <td><?php echo $stt++; ?></td>
                                        <td><strong><?php echo esc_html($w); ?></strong></td>
                                        <td><?php echo $p['tasks']; ?></td>
                                        <td><strong style="color:var(--primary);"><?php echo $p['limit'] > 0 ? number_format($p['limit']) : '&infin;'; ?></strong></td>
                                        <td><?php echo is_numeric($remain) ? number_format($remain) : $remain; ?></td>
                                        <td><button class="iats-btn" style="background:#4B9298; padding:6px 12px; font-size:13px;" onclick="showScriptModal('<?php echo esc_js($w); ?>')">Lấy mã script</button></td>
                                    </tr>
                                    <?php endforeach; if(empty($projects)): ?>
                                    <tr><td colspan="6" style="text-align:center;">Bạn chưa có dự án nào.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Stats -->
                    <div id="c-tab-stats" class="iats-hidden">
                        <div class="iats-card">
                            <div style="display:flex; gap:10px; margin-bottom:20px; align-items:center; flex-wrap:wrap;">
                                <select id="stat-project" class="iats-input" style="max-width:200px; padding:8px;">
                                    <?php foreach(array_keys($projects) as $w): ?>
                                    <option value="<?php echo esc_attr($w); ?>"><?php echo esc_html($w); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span style="font-weight:bold;">Từ</span>
                                <input type="date" id="stat-from" class="iats-input" style="max-width:150px; padding:8px;" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                                <span style="font-weight:bold;">Tới</span>
                                <input type="date" id="stat-to" class="iats-input" style="max-width:150px; padding:8px;" value="<?php echo date('Y-m-d'); ?>">
                                <button class="iats-btn" style="background:#10B981; padding:8px 16px;" onclick="loadCustomerStats()">Kiểm tra</button>
                            </div>
                            
                            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                                <!-- Bảng bên trái -->
                                <div style="flex:1; min-width:300px; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:15px;">
                                    <table class="iats-table" style="background:white; border-radius:6px; overflow:hidden;">
                                        <thead style="background:#F3F4F6;"><tr><th>STT</th><th>Từ khóa</th><th>Lượt views</th></tr></thead>
                                        <tbody id="stat-left-tbody">
                                            <tr><td colspan="3" style="text-align:center; padding:20px;">Bấm <b>Kiểm tra</b> để lấy dữ liệu</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Bảng bên phải -->
                                <div style="flex:1.5; min-width:400px; background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:15px;">
                                    <table class="iats-table" style="background:white; border-radius:6px; overflow:hidden;">
                                        <thead style="background:#F3F4F6;"><tr><th>STT</th><th>Loại traffic</th><th>Thiết bị</th><th>IP</th><th>Time</th></tr></thead>
                                        <tbody id="stat-right-tbody">
                                            <tr><td colspan="5" style="text-align:center; padding:20px;">Chọn một số View màu xanh bên trái để xem Logs chi tiết</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Đã bỏ Tab Thêm Nhiệm Vụ Khách Hàng -->
                    
                    <!-- Script Modal -->
                    <div id="iats-script-modal" class="iats-modal iats-hidden">
                        <div class="iats-modal-content">
                            <span class="iats-modal-close" onclick="document.getElementById('iats-script-modal').classList.add('iats-hidden')">&times;</span>
                            <h3 style="margin-top:0;">Mã nhúng cho Dự Án: <span id="script-modal-domain" style="color:var(--primary);"></span></h3>
                            <textarea id="script-modal-code" class="iats-input" style="height:100px; font-family:monospace; background:#F3F4F6; margin-top: 15px;" readonly></textarea>
                            <button class="iats-btn" style="margin-top:15px; background:#4B9298;" onclick="copyScriptCode()"><i class="dashicons dashicons-admin-page"></i> Sao chép mã</button>
                            <span id="copy-msg" style="color:#10B981; margin-left:10px; font-weight:bold; display:none;">Đã sao chép vào Clipboard!</span>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <script>
            const PRICE_PER_VIEW = <?php echo $price; ?>;
            function calcPrice(views) { document.getElementById('calc-price').textContent = (views * PRICE_PER_VIEW).toLocaleString(); }
            
            function switchCustomerTab(tab) {
                const titles = { 'projects': 'Dự án - Traffic User', 'stats': 'Thống kê - Traffic User' };
                const topTitle = document.getElementById('c-topbar-title');
                if (topTitle) topTitle.textContent = titles[tab];

                ['projects', 'stats'].forEach(t => {
                    const elTab = document.getElementById('c-tab-'+t);
                    const elMenu = document.getElementById('c-menu-'+t);
                    if(elTab) elTab.classList.add('iats-hidden');
                    if(elMenu) elMenu.classList.remove('active');
                });
                document.getElementById('c-tab-'+tab).classList.remove('iats-hidden');
                document.getElementById('c-menu-'+tab).classList.add('active');
            }

            function showScriptModal(domain) {
                document.getElementById('script-modal-domain').textContent = domain;
                const scriptUrl = '<?php echo plugins_url('assets/js/client-script.js', dirname(__FILE__)); ?>?domain=' + encodeURIComponent(domain);
                const code = '<div id="iats-traffic-btn"></div>\n<script src="' + scriptUrl + '"><\/script>';
                document.getElementById('script-modal-code').value = code;
                document.getElementById('iats-script-modal').classList.remove('iats-hidden');
            }
            
            function copyScriptCode() {
                const ta = document.getElementById('script-modal-code');
                ta.select(); document.execCommand('copy');
                const msg = document.getElementById('copy-msg');
                msg.style.display = 'inline'; setTimeout(() => msg.style.display = 'none', 2000);
            }

            async function loadCustomerStats() {
                const domain = document.getElementById('stat-project').value;
                const from = document.getElementById('stat-from').value;
                const to = document.getElementById('stat-to').value;
                
                document.getElementById('stat-left-tbody').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px;">Đang tải dữ liệu...</td></tr>';
                document.getElementById('stat-right-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">...</td></tr>';
                
                const fd = new URLSearchParams(); fd.append('action', 'iats_customer_get_stats'); 
                fd.append('domain', domain); fd.append('from', from); fd.append('to', to);
                
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        let html = ''; let total = 0;
                        if (data.data.length === 0) {
                            html = '<tr><td colspan="3" style="text-align:center; padding:20px;">Không có lượt view nào trong thời gian này.</td></tr>';
                        } else {
                            data.data.forEach((item, index) => {
                                html += `<tr>
                                    <td>${index + 1}</td>
                                    <td>${item.detail}</td>
                                    <td><a href="javascript:void(0)" onclick="loadCustomerLogs(${item.id}, '${from}', '${to}')" style="color:#10B981; font-weight:bold; text-decoration:underline;">${item.views} views</a></td>
                                </tr>`;
                                total += parseInt(item.views);
                            });
                            html += `<tr><td colspan="2" style="text-align:right;font-weight:bold;">Tổng cộng</td><td style="font-weight:bold; color:var(--primary);">${total} views</td></tr>`;
                        }
                        document.getElementById('stat-left-tbody').innerHTML = html;
                    }
                } catch(e) { document.getElementById('stat-left-tbody').innerHTML = '<tr><td colspan="3">Lỗi tải dữ liệu.</td></tr>'; }
            }
            
            async function loadCustomerLogs(taskId, from, to) {
                document.getElementById('stat-right-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Đang tải chi tiết logs...</td></tr>';
                const fd = new URLSearchParams(); fd.append('action', 'iats_customer_get_logs'); 
                fd.append('task_id', taskId); fd.append('from', from); fd.append('to', to);
                
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        let html = '';
                        if (data.data.length === 0) {
                            html = '<tr><td colspan="5" style="text-align:center; padding:20px;">Chưa có lịch sử logs nào.</td></tr>';
                        } else {
                            data.data.forEach((item, index) => {
                                html += `<tr>
                                    <td>${index + 1}</td>
                                    <td>${item.event_type == 'verify_code' ? 'Trắc nghiệm' : 'Khác'}</td>
                                    <td>${item.user_agent.toLowerCase().includes('mobile') ? 'Mobile' : 'Desktop'}</td>
                                    <td>${item.ip_address}</td>
                                    <td>${item.created_at}</td>
                                </tr>`;
                            });
                        }
                        document.getElementById('stat-right-tbody').innerHTML = html;
                    }
                } catch(e) { document.getElementById('stat-right-tbody').innerHTML = '<tr><td colspan="5">Lỗi tải logs.</td></tr>'; }
            }

            async function iatsLogout() { const fd = new URLSearchParams(); fd.append('action', 'iats_customer_logout'); await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); window.location.href = window.location.href.split('?')[0]; }
        </script>
        <?php
    }

    private function render_admin_dashboard() {
        $user = wp_get_current_user();
        $price = intval(get_option('iats_view_price', 3));
        global $wpdb; $table = IATS_TABLE_TASKS;
        $tasks = $wpdb->get_results("SELECT t.*, (SELECT COUNT(id) FROM " . IATS_TABLE_LOGS . " l WHERE l.project_id = t.id AND l.event_type = 'verify_code' AND l.status = 'success' AND DATE(l.created_at) = CURDATE() - INTERVAL 1 DAY) as count_yesterday FROM $table t ORDER BY t.id DESC");
        
        // Log Filtering & Deletion
        if (isset($_POST['delete_logs']) && isset($_POST['log_ids']) && is_array($_POST['log_ids'])) {
            $ids = array_map('intval', $_POST['log_ids']);
            if (!empty($ids)) {
                $wpdb->query("DELETE FROM " . IATS_TABLE_LOGS . " WHERE id IN (" . implode(',', $ids) . ")");
            }
        }
        
        $filters = [
            'from' => isset($_GET['from_date']) ? sanitize_text_field($_GET['from_date']) : date('Y-m-d'),
            'to' => isset($_GET['to_date']) ? sanitize_text_field($_GET['to_date']) : date('Y-m-d'),
            'task' => isset($_GET['task_id']) ? sanitize_text_field($_GET['task_id']) : '',
            'tag' => isset($_GET['log_tag']) ? sanitize_text_field($_GET['log_tag']) : '',
            'ip' => isset($_GET['log_ip']) ? sanitize_text_field($_GET['log_ip']) : '',
            'event' => isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '',
            'browser' => isset($_GET['browser']) ? sanitize_text_field($_GET['browser']) : '',
            'source' => isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '',
            'status' => isset($_GET['task_status']) ? sanitize_text_field($_GET['task_status']) : ''
        ];
        
        $where = "WHERE 1=1";
        if ($filters['from']) $where .= $wpdb->prepare(" AND DATE(l.created_at) >= %s", $filters['from']);
        if ($filters['to']) $where .= $wpdb->prepare(" AND DATE(l.created_at) <= %s", $filters['to']);
        if ($filters['task']) $where .= $wpdb->prepare(" AND (l.project_id = %d OR t.detail LIKE %s)", $filters['task'], '%' . $filters['task'] . '%');
        if ($filters['tag']) $where .= $wpdb->prepare(" AND t.tag = %s", $filters['tag']);
        if ($filters['ip']) $where .= $wpdb->prepare(" AND l.ip_address LIKE %s", '%' . $filters['ip'] . '%');
        if ($filters['event']) {
            if ($filters['event'] === 'wrong_code') {
                $where .= " AND l.event_type = 'verify_code' AND l.status = 'wrong_code'";
            } elseif ($filters['event'] === 'verify_code') {
                $where .= " AND l.event_type = 'verify_code' AND l.status = 'success'";
            } else {
                $where .= $wpdb->prepare(" AND l.event_type = %s", $filters['event']);
            }
        }
        if ($filters['browser']) $where .= $wpdb->prepare(" AND l.user_agent LIKE %s", '%' . $filters['browser'] . '%');
        if ($filters['source']) $where .= $wpdb->prepare(" AND l.referrer LIKE %s", '%' . $filters['source'] . '%');
        if ($filters['status'] !== '') $where .= $wpdb->prepare(" AND t.active = %d", intval($filters['status']));

        $paged_logs = isset($_GET['paged_logs']) ? max(1, intval($_GET['paged_logs'])) : 1;
        $logs_per_page = 100;
        $logs_offset = ($paged_logs - 1) * $logs_per_page;
        $total_logs_count = $wpdb->get_var("SELECT COUNT(l.id) FROM " . IATS_TABLE_LOGS . " l LEFT JOIN " . IATS_TABLE_TASKS . " t ON l.project_id = t.id $where");
        $logs = $wpdb->get_results("SELECT l.*, t.detail as task_name, t.tag, t.active as task_active FROM " . IATS_TABLE_LOGS . " l LEFT JOIN " . IATS_TABLE_TASKS . " t ON l.project_id = t.id $where ORDER BY l.id DESC LIMIT $logs_per_page OFFSET $logs_offset");
        
        $users = get_users(['role' => 'iats_customer']);
        $total_users = count($users);
        $total_tasks = count($tasks);
        $total_views = 0; foreach($tasks as $t) { $total_views += $t->count_get; }
        ?>
        <div class="iats-wrap">
            <div class="iats-app-container">
                <!-- Sidebar -->
                <div class="iats-sidebar">
                    <?php $plogo = get_option('iats_portal_logo', ''); ?>
                    <div class="iats-sidebar-header"><h2><?php if($plogo) echo '<img src="'.esc_url($plogo).'" style="max-height:60px;max-width:100%;" onerror="this.outerHTML=\'ADMIN PORTAL\'">'; else echo 'ADMIN PORTAL'; ?></h2></div>
                    <ul class="iats-menu">
                        <li class="active" id="a-menu-overview" onclick="switchAdminTab('overview')">Tổng quan</li>
                        <li id="a-menu-tasks" onclick="switchAdminTab('tasks')">Tất cả Nhiệm vụ</li>
                        <li id="a-menu-users" onclick="switchAdminTab('users')">Khách hàng & Ví</li>
                        <li id="a-menu-settings" onclick="switchAdminTab('settings')">Cài đặt Hệ thống</li>
                        <li id="a-menu-logs" onclick="switchAdminTab('logs')">Thống Kê Nhật Ký</li>
                    </ul>
                    <div class="iats-sidebar-footer"><button class="iats-btn iats-btn-outline" style="width:100%;" onclick="iatsLogout()">Đăng Xuất</button></div>
                </div>
                <!-- Main Content -->
                <div class="iats-content">
                    <div class="iats-topbar">
                        <h1 id="a-topbar-title">Tổng quan - Admin Portal</h1>
                        <div style="white-space: nowrap;">Quyền: <strong>Quản trị</strong></div>
                    </div>

                    <!-- Tab Overview -->
                    <div id="a-tab-overview">
                        <div class="iats-stats-grid">
                            <div class="iats-stat-card purple"><h4>Tổng Khách Hàng</h4><p class="value"><?php echo $total_users; ?></p></div>
                            <div class="iats-stat-card green"><h4>Tổng Nhiệm Vụ</h4><p class="value"><?php echo $total_tasks; ?></p></div>
                            <div class="iats-stat-card orange"><h4>Tổng Lưu lượng (View)</h4><p class="value"><?php echo number_format($total_views); ?></p></div>
                        </div>
                    </div>

                    <!-- Tab Tasks -->
                    <div id="a-tab-tasks" class="iats-hidden">
                        <div style="margin-bottom:15px; text-align:right;">
                            <button class="iats-btn" style="background:#10B981;" onclick="document.getElementById('admin-add-task-box').classList.toggle('iats-hidden')">+ Tạo Nhiệm Vụ Mới</button>
                        </div>

                        <div id="admin-add-task-box" class="iats-card iats-hidden" style="margin-bottom:20px;">
                            <h3 style="margin-top:0;">Thêm Nhiệm Vụ (Admin)</h3>
                            <div id="iats-admin-task-msg" class="iats-alert"></div>
                            <form id="iats-admin-task-form">
                                <input type="hidden" name="task_id" value="">
                                <?php $tt = function($t){ return '<div class="iats-tooltip" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:#E5E7EB;color:#4B5563;border-radius:50%;font-size:11px;cursor:help;margin-left:5px;font-weight:bold;">? <div class="iats-tooltip-text" style="position:absolute;bottom:125%;left:50%;transform:translateX(-50%);width:max-content;max-width:200px;background:#374151;color:#fff;text-align:center;border-radius:4px;padding:4px 8px;font-size:12px;font-weight:normal;opacity:0;visibility:hidden;transition:0.2s;pointer-events:none;z-index:999;">'.$t.'</div></div>'; }; ?>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Chọn Khách Hàng <?php echo $tt('Tùy chọn'); ?></label>
                                    <select name="user_id" class="iats-input" style="padding:10px; height:auto; line-height:1.5;" onchange="updateDomainList(this.value)">
                                        <option value="">-- Thuộc về Admin (Hệ thống) --</option>
                                        <?php foreach($users as $u): ?>
                                            <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->display_name) . ' (Email: ' . esc_html($u->user_email) . ')'; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Từ khóa tìm kiếm <?php echo $tt('Từ khóa người dùng tìm trên Google'); ?></label><input type="text" name="detail" class="iats-input" required></div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Domain Website <?php echo $tt('Tên miền trang web'); ?></label>
                                    <input type="text" name="website" id="admin-website-input" class="iats-input" list="user-domains-list" autocomplete="off" required placeholder="VD: abc.com">
                                    <datalist id="user-domains-list"></datalist>
                                </div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Link Bài Viết Đích (Target URL) <?php echo $tt('Nhập link của bài viết cụ thể để tự động hiện đúng mã. Khuyến nghị BẮT BUỘC nhập.'); ?></label>
                                    <input type="text" name="target_url" class="iats-input" placeholder="VD: abc.com/bai-viet-1">
                                </div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Tag <?php echo $tt('Phân loại nhiệm vụ'); ?></label><input type="text" name="tag" class="iats-input" placeholder="VD: seo, backlink"></div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Mật mã cố định <?php echo $tt('Để trống sẽ tự động sinh ngẫu nhiên'); ?></label><input type="text" name="fixed_code" class="iats-input" placeholder="VD: MATKHAU123"></div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Giới hạn view/ngày <?php echo $tt('Để trống hoặc 0 = Không giới hạn'); ?></label><input type="number" name="limit_per_day" class="iats-input" min="0" value="0"></div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Link Ảnh Hướng Dẫn Bước 3 <?php echo $tt('Tùy chọn'); ?></label>
                                    <div style="display:flex; gap:5px;"><input type="text" name="img3" id="admin-img3-url" class="iats-input" placeholder="https://..."><button type="button" class="iats-btn iats-btn-outline" style="white-space:nowrap; flex-shrink:0; padding:0 15px;" onclick="openMediaUploader('admin-img3-url')">Tải ảnh</button></div>
                                </div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Link Ảnh Hướng Dẫn Bước 4 <?php echo $tt('Tùy chọn'); ?></label>
                                    <div style="display:flex; gap:5px;"><input type="text" name="img4" id="admin-img4-url" class="iats-input" placeholder="https://..."><button type="button" class="iats-btn iats-btn-outline" style="white-space:nowrap; flex-shrink:0; padding:0 15px;" onclick="openMediaUploader('admin-img4-url')">Tải ảnh</button></div>
                                </div>
                                <div class="iats-form-group"><label style="display:flex;align-items:center;">Tổng số lượng View <?php echo $tt('Cần chạy'); ?></label><input type="number" name="count_limit" class="iats-input" required min="1"></div>
                                <div class="iats-form-group"><label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="priority" value="1"> Ưu tiên nhiệm vụ này</label></div>
                                <button type="submit" class="iats-btn">Lưu Nhiệm Vụ</button>
                            </form>
                        </div>

                        <div class="iats-card" style="overflow-x: auto;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <h3 style="margin-top:0; margin-bottom:0;">Danh Sách Nhiệm Vụ</h3>
                                <div style="display:flex; gap:10px;">
                                    <a href="?export_tasks_csv=1" class="iats-btn iats-btn-outline" style="border-color:#10B981; color:#10B981; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:5px; font-size:13px; padding:6px 12px; white-space:nowrap;"><i class="dashicons dashicons-download" style="width:16px;height:16px;font-size:16px;"></i> Xuất Excel</a>
                                    <input type="text" class="iats-input" placeholder="Lọc theo Domain, Từ khoá..." style="max-width:300px; margin:0;" onkeyup="filterAdminTasks(this.value)">
                                </div>
                            </div>
                            <table class="iats-table" id="iats-admin-tasks-table">
                                <thead><tr><th>ID</th><th>Domain nhiệm vụ</th><th>Từ khóa</th><th>Mã & Tag</th><th>Ảnh 3 & 4</th><th>View</th><th>Hôm nay</th><th>Hôm qua</th><th>Tiến độ</th><th>Ưu tiên</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                <tbody>
                                    <?php if ($tasks): foreach($tasks as $t): ?>
                                    <tr>
                                        <td>#<?php echo $t->id; ?></td>
                                        <td><a href="<?php echo esc_url($t->website); ?>" target="_blank" style="color:var(--primary); font-weight:500; font-size:13px;"><?php echo esc_html($t->website); ?></a></td>
                                        <td>
                                            <strong><?php echo esc_html($t->detail); ?></strong>
                                        </td>
                                        <td>
                                            <span style="font-size:12px; color:var(--text-light);">
                                                Mã: <?php echo !empty($t->fixed_code) ? esc_html($t->fixed_code) : 'Random'; ?><br>
                                                Tag: <?php echo esc_html($t->tag); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($t->img3): ?><a href="<?php echo esc_url($t->img3); ?>" target="_blank" style="font-size:12px;">Ảnh B3</a><?php endif; ?>
                                            <?php if($t->img4): ?><br><a href="<?php echo esc_url($t->img4); ?>" target="_blank" style="font-size:12px;">Ảnh B4</a><?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight:bold; color:#F59E0B;" title="Số lượt nhiệm vụ được hiển thị hôm nay"><?php echo intval($t->popup_views); ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight:bold; color:#10B981; margin-bottom:5px;"><?php echo $t->count; ?> / <?php echo intval($t->limit_per_day) > 0 ? intval($t->limit_per_day) : '∞'; ?></div>
                                        </td>
                                        <td>
                                            <span style="color:#6B7280; font-weight:bold;"><?php echo $t->count_yesterday; ?></span>
                                        </td>
                                        <td><div style="font-weight:bold;"><?php echo $t->count_get; ?> / <?php echo $t->count_limit; ?></div></td>
                                        <td>
                                            <label style="display:flex; align-items:center; gap:5px; font-size:12px; cursor:pointer;">
                                                <input type="checkbox" onchange="togglePriority(<?php echo $t->id; ?>, this.checked)" <?php echo $t->priority ? 'checked' : ''; ?>> Ưu tiên
                                            </label>
                                        </td>
                                        <td><span class="iats-badge iats-status-<?php echo $t->active; ?>" style="cursor:pointer;" onclick="toggleTask(<?php echo $t->id; ?>)"><?php echo $t->active ? 'Đang chạy' : 'Đã dừng'; ?></span></td>
                                        <td>
                                            <button type="button" class="iats-btn iats-btn-outline" style="padding:2px 5px; font-size:11px; margin-bottom:5px;" onclick="editTask(<?php echo $t->id; ?>)">Sửa</button><br>
                                            <button type="button" class="iats-btn" style="background:#EF4444; color:white; padding:2px 5px; font-size:11px;" onclick="deleteTask(<?php echo $t->id; ?>)">Xóa</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="11" style="text-align:center;">Chưa có nhiệm vụ nào.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab Users -->
                    <div id="a-tab-users" class="iats-hidden">
                        <div class="iats-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                                <h3 style="margin-top:0; margin-bottom:0;">Khách hàng & Nạp Coin</h3>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" class="iats-input" placeholder="Lọc Khách hàng..." style="max-width:250px; margin:0;" onkeyup="filterAdminUsers(this.value)">
                                    <button class="iats-btn" style="display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; height:36px; padding:0 15px; font-size:13px; width:auto; min-width:max-content; flex-shrink:0;" onclick="document.getElementById('admin-add-user-modal').classList.remove('iats-hidden')">Thêm Khách Hàng</button>
                                </div>
                            </div>
                            <div id="admin-users-msg" class="iats-alert"></div>
                            <table class="iats-table" id="iats-admin-users-table">
                                <thead><tr><th>ID</th><th>Khách hàng</th><th>Tài Khoản</th><th>Website</th><th>Thống Kê Chiến Dịch</th><th>Số Dư Coin</th><th>Quản Lý Ví (Cộng/Trừ)</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $ustats = [];
                                    $udomains = [];
                                    foreach($tasks as $t) {
                                        if(!isset($ustats[$t->user_id])) $ustats[$t->user_id] = ['tasks' => 0, 'get' => 0, 'limit' => 0];
                                        $ustats[$t->user_id]['tasks']++;
                                        $ustats[$t->user_id]['get'] += $t->count_get;
                                        $ustats[$t->user_id]['limit'] += $t->count_limit;
                                        
                                        if(!isset($udomains[$t->user_id])) $udomains[$t->user_id] = [];
                                        if(!in_array($t->website, $udomains[$t->user_id])) $udomains[$t->user_id][] = $t->website;
                                    }
                                    foreach($users as $u): $balance = intval(get_user_meta($u->ID, 'iats_wallet_balance', true)); 
                                    $st = isset($ustats[$u->ID]) ? $ustats[$u->ID] : ['tasks' => 0, 'get' => 0, 'limit' => 0];
                                    $sites_html = [];
                                    if(isset($udomains[$u->ID])) {
                                        foreach($udomains[$u->ID] as $d) {
                                            $sites_html[] = esc_html($d) . ' <a href="#" class="iats-edit-domain-btn" data-domain="'.esc_attr($d).'" style="text-decoration:none; margin-left:5px; filter:grayscale(1); transition:0.2s;" title="Tùy chỉnh Nút" onmouseover="this.style.filter=\'grayscale(0)\'" onmouseout="this.style.filter=\'grayscale(1)\'" onclick="if(typeof window.iatsOpenDomainModal === \'function\') window.iatsOpenDomainModal(\''.esc_attr($d).'\'); return false;">⚙️</a>';
                                        }
                                    }
                                    $sites = implode('<br>', $sites_html);
                                    ?>
                                    <tr>
                                        <td>#<?php echo $u->ID; ?></td>
                                        <td><?php echo esc_html($u->display_name); ?><br><small><?php echo esc_html($u->user_email); ?></small></td>
                                        <td><strong><?php echo esc_html($u->user_login); ?></strong></td>
                                        <td style="font-size:13px; color:var(--primary); line-height:1.6; max-width:200px; word-wrap:break-word;"><?php echo $sites; ?></td>
                                        <td>
                                            <span style="font-size:13px;"><strong><?php echo $st['tasks']; ?></strong> Nhiệm vụ</span><br>
                                            <small style="color:var(--text-light);">Tiến độ: <strong style="color:var(--primary);"><?php echo number_format($st['get']); ?></strong> / <?php echo number_format($st['limit']); ?> View</small>
                                        </td>
                                        <td><strong style="color:var(--primary); font-size:16px;" id="balance-<?php echo $u->ID; ?>"><?php echo number_format($balance); ?></strong></td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <input type="number" id="coin-input-<?php echo $u->ID; ?>" class="iats-input" style="width:70px; padding:3px 6px; font-size:12px; height:26px;" value="100">
                                                <button class="iats-btn" style="padding:3px 8px; font-size:11px; height:26px; background:#10B981;" onclick="addCoin(<?php echo $u->ID; ?>, 1)">Cộng</button>
                                                <button class="iats-btn" style="padding:3px 8px; font-size:11px; height:26px; background:#EF4444; color:#fff;" onclick="addCoin(<?php echo $u->ID; ?>, -1)">Trừ</button>
                                                <button class="iats-btn iats-btn-outline" style="padding:3px 8px; font-size:11px; height:26px;" onclick="deleteUser(<?php echo $u->ID; ?>)">Xóa</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php 
                        // Danh sách domain đã chèn vào icon bánh răng ở cột Website phía trên.
                        ?>
                        
                        <!-- Modal Tùy Chỉnh Nút (Frontend) -->
                        <div id="iats-domain-modal" class="iats-hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
                            <div style="background:#f9fafb; border-radius:12px; width:900px; max-width:95%; max-height:90vh; overflow-y:auto; display:flex; flex-direction:column; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                                <div style="padding:20px; border-bottom:1px solid #e5e7eb; background:#fff; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center;">
                                    <h3 style="margin:0; font-size:18px;">Tùy Chỉnh Giao Diện: <span id="iats-domain-title" style="color:var(--primary);"></span></h3>
                                    <button type="button" onclick="document.getElementById('iats-domain-modal').classList.add('iats-hidden')" style="background:none; border:none; font-size:24px; cursor:pointer; color:#6b7280;">&times;</button>
                                </div>
                                <input type="hidden" id="iats-domain-name">
                                
                                <div style="display:flex; flex:1; overflow:hidden;">
                                    <!-- LEFT COLUMN: Form -->
                                    <div style="width:50%; padding:20px; border-right:1px solid #e5e7eb; overflow-y:auto; background:#fff;">
                                        <h4 style="margin-top:0; color:#374151;">1. Nút Lấy Mã</h4>
                                        <div class="iats-form-group"><label>Chữ trên nút</label><input type="text" id="iats-d-text" class="iats-input" placeholder="Lấy Mã Xác Nhận"></div>
                                        <div class="iats-form-group" style="display:flex; gap:15px;">
                                            <div style="flex:1;"><label>Màu nền</label><input type="color" id="iats-d-bg" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;"></div>
                                            <div style="flex:1;"><label>Màu chữ</label><input type="color" id="iats-d-color" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;"></div>
                                        </div>
                                        <div class="iats-form-group" style="display:flex; gap:15px;">
                                            <div style="flex:1;"><label>Bo góc (px)</label><input type="number" id="iats-d-radius" class="iats-input" placeholder="5"></div>
                                            <div style="flex:1;"><label>Padding</label><input type="text" id="iats-d-padding" class="iats-input" placeholder="12px 24px"></div>
                                        </div>
                                        <!-- Đã chuyển Logo xuống Hệ thống -->
                                        
                                        <hr style="border:none; border-top:1px solid #e5e7eb; margin:25px 0;">
                                        
                                        <h4 style="margin-top:0; color:#374151;">2. Hộp Kết Quả (Sau 60s)</h4>
                                        <div class="iats-form-group"><label>Chữ thông báo</label><input type="text" id="iats-d-box-title" class="iats-input" placeholder="Mã của bạn là:"></div>
                                        <div class="iats-form-group" style="display:flex; gap:15px;">
                                            <div style="flex:1;"><label>Màu nền Hộp</label><input type="color" id="iats-d-box-bg" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;" value="#e0f7fa"></div>
                                            <div style="flex:1;"><label>Màu viền</label><input type="color" id="iats-d-box-border" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;" value="#b2dfdb"></div>
                                        </div>
                                        <div class="iats-form-group" style="display:flex; gap:15px;">
                                            <div style="flex:1;"><label>Màu chữ thông báo</label><input type="color" id="iats-d-box-text-color" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;" value="#555555"></div>
                                            <div style="flex:1;"><label>Màu chữ Mã Code</label><input type="color" id="iats-d-box-code-color" style="width:100%; height:36px; padding:2px; border:1px solid #d1d5db; border-radius:6px;" value="#00796b"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- RIGHT COLUMN: Preview -->
                                    <div style="width:50%; padding:30px; background:#f3f4f6; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:40px;">
                                        <div style="width:100%; text-align:center;">
                                            <p style="margin-top:0; color:#6b7280; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:15px;">Live Preview Nút Lấy Mã</p>
                                            <button id="preview-btn" style="cursor:pointer; border:none; font-family:inherit; font-size:16px; font-weight:bold; transition:all 0.2s; box-shadow:0 4px 6px rgba(0,0,0,0.1); display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">
                                                <img id="preview-logo" src="" style="width:24px; height:24px; border-radius:50%; object-fit:cover; margin-right:8px; display:none;">
                                                <span id="preview-text">Lấy Mã Xác Nhận</span>
                                            </button>
                                        </div>
                                        
                                        <div style="width:100%; text-align:center;">
                                            <p style="margin-top:0; color:#6b7280; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:15px;">Live Preview Hộp Kết Quả</p>
                                            <div id="preview-box" style="padding:15px 30px; display:inline-block; border-radius:8px; box-shadow:0 4px 6px rgba(0,0,0,0.05);">
                                                <span id="preview-box-title" style="font-size:14px; display:block; margin-bottom:5px;">Mã của bạn là:</span>
                                                <b id="preview-box-code" style="font-size:32px; letter-spacing:3px; text-shadow:1px 1px 0px rgba(255,255,255,0.7);">394851</b>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="padding:15px 20px; border-top:1px solid #e5e7eb; background:#fff; border-radius:0 0 12px 12px; display:flex; justify-content:flex-end; gap:10px;">
                                    <button type="button" class="iats-btn iats-btn-outline" onclick="document.getElementById('iats-domain-modal').classList.add('iats-hidden')">Thoát</button>
                                    <button type="button" class="iats-btn" id="iats-d-save">Lưu Cấu Hình</button>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        var iatsDefaultBtnLogo = "<?php echo esc_js(get_option('iats_btn_logo_default', '')); ?>";
                        // KHÔNG BỌC DOMContentLoaded, script chạy ngay lập tức khi load xong đoạn HTML phía trên
                        (function() {
                            function updatePreview() {
                                // Button
                                var btn = document.getElementById('preview-btn');
                                btn.style.background = document.getElementById('iats-d-bg').value || '#E91E63';
                                btn.style.color = document.getElementById('iats-d-color').value || '#ffffff';
                                btn.style.borderRadius = (document.getElementById('iats-d-radius').value || 5) + 'px';
                                btn.style.padding = document.getElementById('iats-d-padding').value || '12px 24px';
                                
                                document.getElementById('preview-text').innerText = document.getElementById('iats-d-text').value || 'Lấy Mã Xác Nhận';
                                
                                var pLogo = document.getElementById('preview-logo');
                                if(iatsDefaultBtnLogo) {
                                    pLogo.src = iatsDefaultBtnLogo;
                                    pLogo.style.display = 'inline-block';
                                } else {
                                    pLogo.style.display = 'none';
                                }
                                
                                // Box
                                var box = document.getElementById('preview-box');
                                box.style.background = document.getElementById('iats-d-box-bg').value || '#e0f7fa';
                                box.style.borderColor = document.getElementById('iats-d-box-border').value || '#b2dfdb';
                                box.style.borderWidth = '1px';
                                box.style.borderStyle = 'solid';
                                
                                var bTitle = document.getElementById('preview-box-title');
                                bTitle.innerText = document.getElementById('iats-d-box-title').value || 'Mã của bạn là:';
                                bTitle.style.color = document.getElementById('iats-d-box-text-color').value || '#555555';
                                
                                document.getElementById('preview-box-code').style.color = document.getElementById('iats-d-box-code-color').value || '#00796b';
                            }
                            
                            // Bind events for live preview
                            var inputs = ['iats-d-bg', 'iats-d-color', 'iats-d-radius', 'iats-d-padding', 'iats-d-text', 'iats-d-box-bg', 'iats-d-box-border', 'iats-d-box-title', 'iats-d-box-text-color', 'iats-d-box-code-color'];
                            inputs.forEach(function(id) {
                                document.getElementById(id).addEventListener('input', updatePreview);
                            });

                            window.iatsOpenDomainModal = function(domain) {
                                jQuery('#iats-domain-title').text(domain === 'default' ? 'MẶC ĐỊNH (Áp dụng toàn bộ)' : domain);
                                jQuery('#iats-domain-name').val(domain);
                                
                                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {action: 'iats_get_domain_style', domain: domain}, function(res){
                                    var data = res.data || {};
                                    jQuery('#iats-d-text').val(data.text || '');
                                    jQuery('#iats-d-bg').val(data.bg || '#E91E63');
                                    jQuery('#iats-d-color').val(data.color || '#ffffff');
                                    jQuery('#iats-d-radius').val(data.border_radius !== undefined ? data.border_radius : 5);
                                    jQuery('#iats-d-padding').val(data.padding || '12px 24px');
                                    
                                    
                                    jQuery('#iats-d-box-title').val(data.box_title || 'Mã của bạn là:');
                                    jQuery('#iats-d-box-bg').val(data.box_bg || '#e0f7fa');
                                    jQuery('#iats-d-box-border').val(data.box_border || '#b2dfdb');
                                    jQuery('#iats-d-box-text-color').val(data.box_text_color || '#555555');
                                    jQuery('#iats-d-box-code-color').val(data.box_code_color || '#00796b');
                                    
                                    updatePreview();
                                    document.getElementById('iats-domain-modal').classList.remove('iats-hidden');
                                });
                            };
                            
                            jQuery('#iats-d-save').click(function(){
                                var btn = jQuery(this);
                                btn.text('Đang lưu...');
                                var domain = jQuery('#iats-domain-name').val();
                                
                                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                                    action: 'iats_save_domain_style',
                                    domain: domain,
                                    text: jQuery('#iats-d-text').val(),
                                    bg: jQuery('#iats-d-bg').val(),
                                    color: jQuery('#iats-d-color').val(),
                                    border_radius: jQuery('#iats-d-radius').val(),
                                    padding: jQuery('#iats-d-padding').val(),
                                    
                                    box_title: jQuery('#iats-d-box-title').val(),
                                    box_bg: jQuery('#iats-d-box-bg').val(),
                                    box_border: jQuery('#iats-d-box-border').val(),
                                    box_text_color: jQuery('#iats-d-box-text-color').val(),
                                    box_code_color: jQuery('#iats-d-box-code-color').val()
                                }, function(res){
                                    btn.text('Lưu Cấu Hình');
                                    document.getElementById('iats-domain-modal').classList.add('iats-hidden');
                                    iatsShowMsg('Lưu thành công cấu hình cho ' + (domain==='default'?'Mặc định':domain), 'success');
                                });
                            });
                        })();
                        </script>
                    </div>

                    <!-- Add User Modal -->
                    <div id="admin-add-user-modal" class="iats-hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; display:flex; align-items:center; justify-content:center;">
                        <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%;">
                            <h3 style="margin-top:0;">Thêm Khách Hàng</h3>
                            <form id="iats-admin-add-user-form">
                                <div class="iats-form-group"><label>Tên tài khoản (viết liền không dấu)</label><input type="text" name="username" class="iats-input" required></div>
                                <div class="iats-form-group"><label>Email</label><input type="email" name="email" class="iats-input" required></div>
                                <div class="iats-form-group"><label>Mật khẩu</label><input type="password" name="password" class="iats-input" required></div>
                                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                                    <button type="button" class="iats-btn iats-btn-outline" onclick="document.getElementById('admin-add-user-modal').classList.add('iats-hidden')">Hủy</button>
                                    <button type="submit" class="iats-btn">Thêm Khách Hàng</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tab Settings -->
                    <div id="a-tab-settings" class="iats-hidden">
                        <div class="iats-card" style="max-width:800px;">
                            <h3 style="margin-top:0;">Cấu Hình Hệ Thống</h3>
                            <div id="admin-settings-msg" class="iats-alert"></div>
                            <form id="iats-admin-settings-form">
                                <?php $tt = function($t){ return '<div class="iats-tooltip" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:#E5E7EB;color:#4B5563;border-radius:50%;font-size:11px;cursor:help;margin-left:5px;font-weight:bold;">? <div class="iats-tooltip-text" style="position:absolute;bottom:125%;left:50%;transform:translateX(-50%);width:max-content;max-width:200px;background:#374151;color:#fff;text-align:center;border-radius:4px;padding:4px 8px;font-size:12px;font-weight:normal;opacity:0;visibility:hidden;transition:0.2s;pointer-events:none;z-index:999;">'.$t.'</div></div>'; }; ?>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Giá tiền 1 Lượt View (Coin)</label>
                                    <input type="number" name="iats_view_price" class="iats-input" value="<?php echo intval(get_option('iats_view_price', 3)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">URL trang Portal <?php echo $tt('Trang Bảng Điều Khiển (chứa mã [iats_customer_portal])'); ?></label>
                                    <input type="text" name="iats_portal_url" class="iats-input" value="<?php echo esc_attr(get_option('iats_portal_url', '/traffic/portal/')); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">URL trang Đăng Nhập <?php echo $tt('Trang chứa form Đăng Nhập (chứa mã [iats_auth_form])'); ?></label>
                                    <input type="text" name="iats_login_url" class="iats-input" value="<?php echo esc_attr(get_option('iats_login_url', '/traffic/login/')); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">URL trang kết quả <?php echo $tt('Trang chứa shortcode hiển thị kết quả'); ?></label>
                                    <input type="text" name="iats_result_url" class="iats-input" value="<?php echo esc_attr(get_option('iats_result_url', '/ket-qua-bai-thi/')); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Ảnh Logo Admin & Customer Portal <?php echo $tt('Hiển thị trên cùng bên trái. Có thể upload hoặc điền link trực tiếp.'); ?></label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="iats_portal_logo" id="iats_portal_logo" class="iats-input" value="<?php echo esc_attr(get_option('iats_portal_logo', '')); ?>">
                                        <button type="button" class="iats-btn iats-btn-outline" onclick="openMediaUploader('iats_portal_logo')" style="white-space:nowrap; padding: 0 15px;">Tải Ảnh Lên</button>
                                    </div>
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Ảnh Nút (Mặc định cho toàn hệ thống) <?php echo $tt('Hiển thị bên trong nút lấy mã. Sẽ áp dụng cho tất cả tên miền.'); ?></label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="iats_btn_logo_default" id="iats_btn_logo_default" class="iats-input" value="<?php echo esc_attr(get_option('iats_btn_logo_default', '')); ?>">
                                        <button type="button" class="iats-btn iats-btn-outline" onclick="openMediaUploader('iats_btn_logo_default')" style="white-space:nowrap; padding: 0 15px;">Tải Ảnh Lên</button>
                                    </div>
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Trọng số nhiệm vụ ưu tiên <?php echo $tt('Từ 1 đến 10. Mức càng cao thì nhiệm vụ được bật stick ưu tiên sẽ xuất hiện nhiều hơn.'); ?></label>
                                    <input type="number" name="iats_priority_weight" class="iats-input" value="<?php echo intval(get_option('iats_priority_weight', 3)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Phiên nhiệm vụ (phút) <?php echo $tt('Thời gian sống của Session theo phút.'); ?></label>
                                    <input type="number" name="iats_session_mins" class="iats-input" value="<?php echo intval(get_option('iats_session_mins', 120)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Token kết quả (phút) <?php echo $tt('Thời gian sống của token sinh ra'); ?></label>
                                    <input type="number" name="iats_token_mins" class="iats-input" value="<?php echo intval(get_option('iats_token_mins', 15)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Cookie giữ nhiệm vụ (ngày) <?php echo $tt('Chặn User lấy lại nhiệm vụ trùng'); ?></label>
                                    <input type="number" name="iats_cookie_days" class="iats-input" value="<?php echo intval(get_option('iats_cookie_days', 1)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Số lần nhập sai tối đa <?php echo $tt('0 hoặc trống = Không giới hạn'); ?></label>
                                    <input type="number" name="iats_max_wrong_attempts" class="iats-input" value="<?php echo intval(get_option('iats_max_wrong_attempts', 0)); ?>">
                                </div>
                                <div class="iats-form-group">
                                    <label style="display:flex;align-items:center;">Số ngày giữ thống kê log <?php echo $tt('Tự động xoá logs sau X ngày'); ?></label>
                                    <input type="number" name="iats_log_keep_days" class="iats-input" value="<?php echo intval(get_option('iats_log_keep_days', 30)); ?>">
                                </div>

                                <button type="submit" class="iats-btn">Lưu Tất Cả Cài Đặt</button>
                            </form>
                        </div>
                    </div>

                    <!-- Tab Logs -->
                    <div id="a-tab-logs" class="iats-hidden">
                        <div class="iats-card">
                            <h3 style="margin-top:0;">Thống Kê Nhật Ký (Logs)</h3>
                            
                            <!-- Form Bộ lọc -->
                            <form method="GET" style="background:#f9fafb; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:15px; align-items:flex-end;">
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Từ ngày</label>
                                    <input type="date" name="from_date" value="<?php echo esc_attr($filters['from']); ?>" class="iats-input" style="width:140px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Đến ngày</label>
                                    <input type="date" name="to_date" value="<?php echo esc_attr($filters['to']); ?>" class="iats-input" style="width:140px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Nhiệm vụ / ID</label>
                                    <input type="text" name="task_id" value="<?php echo esc_attr($filters['task']); ?>" placeholder="ID hoặc Tên" class="iats-input" style="width:150px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Tag</label>
                                    <input type="text" name="log_tag" value="<?php echo esc_attr($filters['tag']); ?>" placeholder="Tất cả tag" class="iats-input" style="width:120px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">IP</label>
                                    <input type="text" name="log_ip" value="<?php echo esc_attr($filters['ip']); ?>" placeholder="Nhập IP" class="iats-input" style="width:130px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Loại sự kiện</label>
                                    <select name="event_type" class="iats-input" style="width:130px;">
                                        <option value="">Tất cả loại</option>
                                        <option value="get_task" <?php selected($filters['event'], 'get_task'); ?>>Lấy Mã</option>
                                        <option value="wrong_code" <?php selected($filters['event'], 'wrong_code'); ?>>Wrong code</option>
                                        <option value="verify_code" <?php selected($filters['event'], 'verify_code'); ?>>Done</option>
                                        <option value="get_code" <?php selected($filters['event'], 'get_code'); ?>>Sinh Mã</option>
                                    </select>
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Trình duyệt</label>
                                    <select name="browser" class="iats-input" style="width:130px;">
                                        <option value="">Tất cả trình duyệt</option>
                                        <option value="Chrome" <?php selected($filters['browser'], 'Chrome'); ?>>Chrome</option>
                                        <option value="Safari" <?php selected($filters['browser'], 'Safari'); ?>>Safari</option>
                                        <option value="Firefox" <?php selected($filters['browser'], 'Firefox'); ?>>Firefox</option>
                                        <option value="Edge" <?php selected($filters['browser'], 'Edge'); ?>>Edge</option>
                                    </select>
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Nguồn</label>
                                    <input type="text" name="source" value="<?php echo esc_attr($filters['source']); ?>" placeholder="Tất cả nguồn" class="iats-input" style="width:130px;">
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <label style="font-size:12px; margin-bottom:5px;">Trạng thái nhiệm vụ</label>
                                    <select name="task_status" class="iats-input" style="width:140px;">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="1" <?php selected($filters['status'], '1'); ?>>Kích hoạt</option>
                                        <option value="0" <?php selected($filters['status'], '0'); ?>>Tắt</option>
                                    </select>
                                </div>
                                
                                <div style="display:flex; gap:10px; margin-top:10px; width:100%; align-items:center;">
                                    <button type="submit" class="iats-btn" style="background:#2563EB;">Lọc thống kê</button>
                                    <button type="submit" name="export_iats_csv" value="1" class="iats-btn iats-btn-outline" style="color:#2563EB; border-color:#2563EB;">Xuất CSV theo bộ lọc</button>
                                    <button type="button" onclick="localStorage.removeItem('iats_last_log_filter'); window.location.href=window.location.pathname;" class="iats-btn iats-btn-outline" style="border-color: #d1d5db; color: #6b7280; display:flex; align-items:center; justify-content:center; gap:5px;"><i class="dashicons dashicons-dismiss" style="width:16px;height:16px;font-size:16px;"></i> Xoá bộ lọc</button>
                                    <a href="" onclick="window.location.reload(); return false;" class="iats-btn iats-btn-outline" style="border-color: #d1d5db; color: #6b7280; display:flex; align-items:center; justify-content:center; gap:5px;"><i class="dashicons dashicons-update" style="width:16px;height:16px;font-size:16px;"></i> Làm mới</a>
                                    <button type="button" onclick="document.getElementById('btn-real-delete-logs').click()" class="iats-btn iats-btn-outline" style="border-color: #FCA5A5; color: #DC2626; display:flex; align-items:center; justify-content:center; gap:5px;"><i class="dashicons dashicons-trash" style="width:16px;height:16px;font-size:16px;"></i> Xoá mục đã chọn</button>
                                </div>
                            </form>
                            
                            <!-- Bảng Thống kê -->
                            <form method="POST">
                                <p style="font-size:16px; font-weight:bold; margin-bottom:10px;">Thống kê từ <?php echo esc_html($filters['from']); ?> đến <?php echo esc_html($filters['to']); ?> <span style="font-size:14px; color:var(--text-light); font-weight:normal;">(Tổng lượt: <?php echo count($logs); ?>)</span></p>
                                <button type="submit" name="delete_logs" value="1" id="btn-real-delete-logs" style="display:none;" onclick="return confirm('Bạn có chắc chắn muốn xoá các dòng đã chọn?');">Xoá mục đã chọn</button>
                                
                                <div style="overflow-x:auto;">
                                    <table class="iats-table">
                                        <thead>
                                            <tr>
                                                <th style="width:30px;"><input type="checkbox" onclick="document.querySelectorAll('.log-chk').forEach(c=>c.checked=this.checked)"></th>
                                                <th>Thời gian</th>
                                                <th>Loại sự kiện</th>
                                                <th>Trạng thái NV</th>
                                                <th>Nút</th>
                                                <th>Nhiệm vụ</th>
                                                <th>Tag</th>
                                                <th>IP</th>
                                                <th>Thiết bị</th>
                                                <th>Trình duyệt</th>
                                                <th>Nguồn / Trang phân phối</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($logs): foreach($logs as $l): 
                                                $dev = $this->get_device_from_ua($l->user_agent);
                                                $bro = $this->get_browser_from_ua($l->user_agent);
                                            ?>
                                            <tr>
                                                <td><input type="checkbox" name="log_ids[]" value="<?php echo $l->id; ?>" class="log-chk"></td>
                                                <td style="font-size:12px;"><?php echo esc_html($l->created_at); ?></td>
                                                <td><strong><?php 
                                                    if ($l->event_type == 'get_task') {
                                                        echo '<span style="color:#F59E0B">Lấy mã</span>';
                                                    } elseif ($l->event_type == 'verify_code' && $l->status == 'wrong_code') {
                                                        echo '<span style="color:#EF4444">Wrong code</span>';
                                                    } elseif ($l->event_type == 'verify_code') {
                                                        echo '<span style="color:#10B981">Done</span>';
                                                    } elseif ($l->event_type == 'get_code') {
                                                        echo '<span style="color:#2563EB">Sinh mã</span>';
                                                    } else {
                                                        echo esc_html($l->event_type);
                                                    }
                                                ?></strong></td>
                                                <td><?php echo isset($l->task_active) && $l->task_active ? '<span style="color:green">Kích hoạt</span>' : '<span style="color:red">Tắt</span>'; ?></td>
                                                <td><?php echo $l->project_id; ?></td>
                                                <td><?php echo esc_html($l->task_name); ?></td>
                                                <td><?php echo esc_html($l->tag); ?></td>
                                                <td><?php echo esc_html($l->ip_address); ?></td>
                                                <td><?php echo $dev; ?></td>
                                                <td><?php echo $bro; ?></td>
                                                <td style="font-size:11px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($l->referrer); ?>"><?php echo esc_html($l->referrer); ?></td>
                                            </tr>
                                            <?php endforeach; else: ?>
                                            <tr><td colspan="11" style="text-align:center;">Chưa có nhật ký nào phù hợp bộ lọc.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php
                                if ($total_logs_count > $logs_per_page) {
                                    $total_logs_pages = ceil($total_logs_count / $logs_per_page);
                                    echo '<div style="margin-top:20px; text-align:center; padding-bottom:10px;">';
                                    
                                    $start_page = max(1, $paged_logs - 5);
                                    $end_page = min($total_logs_pages, $paged_logs + 5);
                                    
                                    if ($start_page > 1) {
                                        $qs = $_GET; $qs['paged_logs'] = 1;
                                        echo '<a href="?'.http_build_query($qs).'#logs" style="display:inline-block; padding:5px 12px; margin:0 3px; border:1px solid #d1d5db; border-radius:4px; text-decoration:none; background:#fff; color:#374151;">1</a>';
                                        if ($start_page > 2) echo '<span style="margin:0 5px;">...</span>';
                                    }
                                    
                                    for($i = $start_page; $i <= $end_page; $i++) {
                                        $active_style = ($i == $paged_logs) ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'background:#fff;color:#374151;border-color:#d1d5db;';
                                        $qs = $_GET; $qs['paged_logs'] = $i;
                                        echo '<a href="?'.http_build_query($qs).'#logs" style="display:inline-block; padding:5px 12px; margin:0 3px; border:1px solid; border-radius:4px; text-decoration:none; '.$active_style.'">'.$i.'</a>';
                                    }
                                    
                                    if ($end_page < $total_logs_pages) {
                                        if ($end_page < $total_logs_pages - 1) echo '<span style="margin:0 5px;">...</span>';
                                        $qs = $_GET; $qs['paged_logs'] = $total_logs_pages;
                                        echo '<a href="?'.http_build_query($qs).'#logs" style="display:inline-block; padding:5px 12px; margin:0 3px; border:1px solid #d1d5db; border-radius:4px; text-decoration:none; background:#fff; color:#374151;">'.$total_logs_pages.'</a>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </form>
                        </div>
                    </div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() { 
                            const urlParams = new URLSearchParams(window.location.search);
                            if (urlParams.has('from_date') || urlParams.has('paged_logs') || urlParams.has('delete_logs')) {
                                switchAdminTab('logs'); 
                            } else {
                                const savedTab = localStorage.getItem('iats_admin_active_tab') || 'overview';
                                switchAdminTab(savedTab);
                            }
                        });
                    </script>
                    
                </div>
            </div>
        </div>
        <script>
            async function iatsLogout() { const fd = new URLSearchParams(); fd.append('action', 'iats_customer_logout'); await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); window.location.href = window.location.href.split('?')[0]; }
            function switchAdminTab(tab) {
                const aTitles = { 'overview': 'Tổng quan', 'tasks': 'Tất cả Nhiệm vụ', 'users': 'Khách hàng & Ví', 'settings': 'Cài đặt Hệ thống', 'logs': 'Thống Kê Nhật Ký' };
                const topTitle = document.getElementById('a-topbar-title');
                if (topTitle) topTitle.textContent = aTitles[tab] + ' - Admin Portal';

                ['overview', 'tasks', 'users', 'settings', 'logs'].forEach(t => {
                    const tabEl = document.getElementById('a-tab-'+t);
                    const menuEl = document.getElementById('a-menu-'+t);
                    if (tabEl) tabEl.classList.add('iats-hidden');
                    if (menuEl) menuEl.classList.remove('active');
                });
                document.getElementById('a-tab-'+tab).classList.remove('iats-hidden');
                document.getElementById('a-menu-'+tab).classList.add('active');
                
                localStorage.setItem('iats_admin_active_tab', tab);
                if (tab !== 'logs' && window.location.search.includes('from_date')) {
                    window.history.pushState({}, '', window.location.pathname);
                } else if (tab === 'logs') {
                    const lastFilter = localStorage.getItem('iats_last_log_filter');
                    if (!window.location.search.includes('from_date') && lastFilter && lastFilter.includes('from_date')) {
                        window.location.href = window.location.pathname + lastFilter;
                        return;
                    }
                    if (window.location.search.includes('from_date')) {
                        localStorage.setItem('iats_last_log_filter', window.location.search);
                    }
                }
            }
            async function addCoin(userId, multi) {
                const amount = parseInt(document.getElementById('coin-input-' + userId).value);
                if (!amount) return;
                const finalAmt = amount * multi;
                const fd = new URLSearchParams(); fd.append('action', 'iats_admin_update_wallet'); fd.append('user_id', userId); fd.append('amount', finalAmt);
                const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                if (data.success) {
                    document.getElementById('balance-' + userId).textContent = data.data.new_balance;
                    const msg = document.getElementById('admin-users-msg'); msg.className = 'iats-alert success'; 
                    msg.textContent = (multi > 0 ? 'Đã cộng ' : 'Đã trừ ') + amount + ' Coin thành công!'; 
                    setTimeout(() => msg.style.display='none', 3000);
                } else alert('Lỗi: ' + data.data);
            }
            document.getElementById('iats-admin-settings-form')?.addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new URLSearchParams(new FormData(e.target)); fd.append('action', 'iats_admin_update_settings');
                const btn = e.target.querySelector('button[type="submit"]'); btn.textContent = 'Đang lưu...'; btn.disabled = true;
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                    const msg = document.getElementById('admin-settings-msg');
                    if (data.success) { msg.className = 'iats-alert success'; msg.textContent = 'Đã lưu cài đặt thành công!'; setTimeout(() => msg.style.display='none', 3000); } 
                    else { msg.className = 'iats-alert error'; msg.textContent = data.data || 'Lỗi'; }
                } finally { btn.textContent = 'Lưu Tất Cả Cài Đặt'; btn.disabled = false; }
            });
            async function toggleTask(taskId) {
                const fd = new URLSearchParams(); fd.append('action', 'iats_toggle_status'); fd.append('id', taskId); fd.append('nonce', '<?php echo wp_create_nonce('iats_save_action'); ?>');
                await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); location.reload();
            }
            async function togglePriority(taskId, isChecked) {
                const fd = new URLSearchParams(); fd.append('action', 'iats_toggle_priority'); fd.append('id', taskId); fd.append('priority', isChecked ? 1 : 0);
                const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
                if (!res.ok) alert('Lỗi máy chủ');
            }
            
            const iatsAdminTasks = <?php echo json_encode($tasks); ?>;
            function editTask(id) {
                const task = iatsAdminTasks.find(t => t.id == id);
                if (!task) return;
                document.getElementById('admin-add-task-box').classList.remove('iats-hidden');
                const form = document.getElementById('iats-admin-task-form');
                form.task_id.value = task.id;
                form.user_id.value = task.user_id;
                form.detail.value = task.detail || '';
                form.website.value = task.website || '';
                if(form.target_url) form.target_url.value = task.target_url || '';
                form.tag.value = task.tag || '';
                form.fixed_code.value = task.fixed_code;
                form.limit_per_day.value = task.limit_per_day;
                form.img3.value = task.img3;
                form.img4.value = task.img4;
                form.count_limit.value = task.count_limit;
                form.priority.checked = task.priority == 1;
                form.querySelector('button[type="submit"]').textContent = 'Cập nhật Nhiệm vụ';
                window.scrollTo({top: 0, behavior: 'smooth'});
            }
            
            async function deleteTask(id) {
                if (!confirm('Bạn có chắc chắn muốn xoá nhiệm vụ này? Toàn bộ dữ liệu logs cũng sẽ mất và không thể hoàn tác.')) return;
                const fd = new URLSearchParams(); fd.append('action', 'iats_admin_delete_task'); fd.append('id', id);
                const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) location.reload();
                else alert(data.data);
            }

            async function deleteUser(id) {
                if (!confirm('Bạn có chắc chắn muốn xoá Khách hàng này? Mọi chiến dịch và dữ liệu view của họ sẽ bị xóa sạch, không thể hoàn tác.')) return;
                const fd = new URLSearchParams(); fd.append('action', 'iats_admin_delete_user'); fd.append('id', id);
                const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) location.reload();
                else alert(data.data);
            }

            document.getElementById('iats-admin-task-form')?.addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new URLSearchParams(new FormData(e.target)); fd.append('action', 'iats_admin_add_task');
                const btn = e.target.querySelector('button'); btn.textContent = 'Đang xử lý...'; btn.disabled = true;
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                    const msg = document.getElementById('iats-admin-task-msg');
                    if(data.success) { msg.className = 'iats-alert success'; msg.textContent = 'Đã thêm nhiệm vụ thành công!'; setTimeout(() => location.reload(), 1000); } 
                    else { msg.className = 'iats-alert error'; msg.textContent = data.data; }
                } finally { btn.textContent = 'Thêm Nhiệm Vụ'; btn.disabled = false; }
            });

            let currentAdminTaskPage = 1;
            const adminTasksPerPage = 50;
            function renderAdminTasksPage() {
                const rows = document.querySelectorAll('#iats-admin-tasks-table tbody tr');
                if(rows.length === 0) return;
                const filteredRows = Array.from(rows).filter(r => !r.hasAttribute('data-hidden-by-filter'));
                
                const totalPages = Math.ceil(filteredRows.length / adminTasksPerPage);
                if (currentAdminTaskPage > totalPages) currentAdminTaskPage = Math.max(1, totalPages);
                
                const start = (currentAdminTaskPage - 1) * adminTasksPerPage;
                const end = start + adminTasksPerPage;
                
                rows.forEach(r => r.style.display = 'none');
                for(let i = start; i < end && i < filteredRows.length; i++) {
                    filteredRows[i].style.display = '';
                }
                
                let pHTML = '';
                if(totalPages > 1) {
                    let startPage = Math.max(1, currentAdminTaskPage - 5);
                    let endPage = Math.min(totalPages, currentAdminTaskPage + 5);
                    if(startPage > 1) pHTML += `<button type="button" onclick="currentAdminTaskPage=1; renderAdminTasksPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:4px;">1</button><span style="margin:0 5px;">...</span>`;
                    for(let i=startPage; i<=endPage; i++) {
                        pHTML += `<button type="button" onclick="currentAdminTaskPage=${i}; renderAdminTasksPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid ${i==currentAdminTaskPage?'#696cff':'#d1d5db'}; background:${i==currentAdminTaskPage?'#696cff':'#fff'}; color:${i==currentAdminTaskPage?'#fff':'#374151'}; border-radius:4px;">${i}</button>`;
                    }
                    if(endPage < totalPages) pHTML += `<span style="margin:0 5px;">...</span><button type="button" onclick="currentAdminTaskPage=${totalPages}; renderAdminTasksPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:4px;">${totalPages}</button>`;
                }
                let container = document.getElementById('admin-tasks-pagination');
                if(!container) {
                    container = document.createElement('div');
                    container.id = 'admin-tasks-pagination';
                    container.style.marginTop = '15px';
                    container.style.textAlign = 'center';
                    document.getElementById('iats-admin-tasks-table').parentNode.appendChild(container);
                }
                container.innerHTML = pHTML;
            }

            function filterAdminTasks(val) {
                val = val.toLowerCase();
                const rows = document.querySelectorAll('#iats-admin-tasks-table tbody tr');
                rows.forEach(r => {
                    if (r.textContent.toLowerCase().includes(val)) r.removeAttribute('data-hidden-by-filter');
                    else r.setAttribute('data-hidden-by-filter', 'true');
                });
                currentAdminTaskPage = 1;
                renderAdminTasksPage();
            }
            
            document.addEventListener("DOMContentLoaded", function() { 
                if(document.getElementById('iats-admin-tasks-table')) {
                    renderAdminTasksPage();
                }
            });

            const userDomains = <?php echo isset($udomains) ? json_encode($udomains) : '{}'; ?>;
            function updateDomainList(uid) {
                const dlist = document.getElementById('user-domains-list');
                if(!dlist) return;
                dlist.innerHTML = '';
                if(userDomains[uid]) {
                    userDomains[uid].forEach(d => {
                        const opt = document.createElement('option'); opt.value = d;
                        dlist.appendChild(opt);
                    });
                }
            }

            let currentAdminUserPage = 1;
            const adminUsersPerPage = 50;
            function renderAdminUsersPage() {
                const rows = document.querySelectorAll('#iats-admin-users-table tbody tr');
                if(rows.length === 0) return;
                const filteredRows = Array.from(rows).filter(r => !r.hasAttribute('data-hidden-by-filter'));
                
                const totalPages = Math.ceil(filteredRows.length / adminUsersPerPage);
                if (currentAdminUserPage > totalPages) currentAdminUserPage = Math.max(1, totalPages);
                
                const start = (currentAdminUserPage - 1) * adminUsersPerPage;
                const end = start + adminUsersPerPage;
                
                rows.forEach(r => r.style.display = 'none');
                for(let i = start; i < end && i < filteredRows.length; i++) {
                    filteredRows[i].style.display = '';
                }
                
                let pHTML = '';
                if(totalPages > 1) {
                    let startPage = Math.max(1, currentAdminUserPage - 5);
                    let endPage = Math.min(totalPages, currentAdminUserPage + 5);
                    if(startPage > 1) pHTML += `<button type="button" onclick="currentAdminUserPage=1; renderAdminUsersPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:4px;">1</button><span style="margin:0 5px;">...</span>`;
                    for(let i=startPage; i<=endPage; i++) {
                        pHTML += `<button type="button" onclick="currentAdminUserPage=${i}; renderAdminUsersPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid ${i==currentAdminUserPage?'#696cff':'#d1d5db'}; background:${i==currentAdminUserPage?'#696cff':'#fff'}; color:${i==currentAdminUserPage?'#fff':'#374151'}; border-radius:4px;">${i}</button>`;
                    }
                    if(endPage < totalPages) pHTML += `<span style="margin:0 5px;">...</span><button type="button" onclick="currentAdminUserPage=${totalPages}; renderAdminUsersPage()" style="margin: 0 3px; padding: 4px 10px; cursor:pointer; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:4px;">${totalPages}</button>`;
                }
                let container = document.getElementById('admin-users-pagination');
                if(!container) {
                    container = document.createElement('div');
                    container.id = 'admin-users-pagination';
                    container.style.marginTop = '15px';
                    container.style.textAlign = 'center';
                    document.getElementById('iats-admin-users-table').parentNode.appendChild(container);
                }
                container.innerHTML = pHTML;
            }

            function filterAdminUsers(val) {
                val = val.toLowerCase();
                const rows = document.querySelectorAll('#iats-admin-users-table tbody tr');
                rows.forEach(r => {
                    if (r.textContent.toLowerCase().includes(val)) r.removeAttribute('data-hidden-by-filter');
                    else r.setAttribute('data-hidden-by-filter', 'true');
                });
                currentAdminUserPage = 1;
                renderAdminUsersPage();
            }

            document.addEventListener("DOMContentLoaded", function() { 
                if(document.getElementById('iats-admin-users-table')) {
                    renderAdminUsersPage();
                }
            });

            document.getElementById('iats-admin-add-user-form')?.addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new URLSearchParams(new FormData(e.target)); fd.append('action', 'iats_admin_add_user');
                const btn = e.target.querySelector('button[type="submit"]'); btn.textContent = 'Đang xử lý...'; btn.disabled = true;
                try {
                    const res = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd }); const data = await res.json();
                    if(data.success) { alert('Đã thêm Khách hàng thành công!'); location.reload(); } 
                    else { alert(data.data); }
                } finally { btn.textContent = 'Thêm Khách Hàng'; btn.disabled = false; }
            });
                        
            let mediaUploader;
            function openMediaUploader(targetId) {
                if (mediaUploader) { mediaUploader.targetId = targetId; mediaUploader.open(); return; }
                mediaUploader = wp.media({ title: 'Chọn ảnh', button: { text: 'Chọn' }, multiple: false });
                mediaUploader.targetId = targetId;
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    let el = document.getElementById(mediaUploader.targetId);
                    el.value = attachment.url;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });
                mediaUploader.open();
            }
        </script>
        <?php
    }

    public function ajax_login() {
        @ob_clean(); $creds = array('user_login' => sanitize_user($_POST['username']), 'user_password' => $_POST['password'], 'remember' => true);
        $user = wp_signon($creds, false); if (is_wp_error($user)) wp_send_json_error('Sai thông tin!'); 
        wp_send_json_success(['redirect_url' => get_option('iats_portal_url', home_url('/'))]);
    }
    public function ajax_register() {
        @ob_clean(); $u = sanitize_user($_POST['username']); $e = sanitize_email($_POST['email']); $p = $_POST['password'];
        if (username_exists($u)) wp_send_json_error('Username đã tồn tại!'); if (email_exists($e)) wp_send_json_error('Email đã tồn tại!');
        $user_id = wp_create_user($u, $p, $e); if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
        $user = new WP_User($user_id); $user->set_role('iats_customer'); update_user_meta($user_id, 'iats_wallet_balance', 0);
        wp_set_current_user($user_id); wp_set_auth_cookie($user_id, true); 
        wp_send_json_success(['redirect_url' => get_option('iats_portal_url', home_url('/'))]);
    }
    public function ajax_logout() { @ob_clean(); wp_logout(); wp_send_json_success(); }
    public function ajax_add_task() {
        @ob_clean(); if (!is_user_logged_in() || !current_user_can('iats_customer')) wp_send_json_error('Lỗi quyền!');
        $uid = get_current_user_id(); $bal = intval(get_user_meta($uid, 'iats_wallet_balance', true)); $pr = intval(get_option('iats_view_price', 3));
        $limit = intval($_POST['count_limit']); if ($limit < 10) wp_send_json_error('Giới hạn View tối thiểu là 10.');
        $cost = $limit * $pr; if ($bal < $cost) wp_send_json_error("Không đủ Coin (Cần $cost)");
        update_user_meta($uid, 'iats_wallet_balance', $bal - $cost);
        $tag = !empty($_POST['tag']) ? sanitize_text_field($_POST['tag']) : 'chung';
        $fc = !empty($_POST['fixed_code']) ? sanitize_text_field($_POST['fixed_code']) : null;
        $lpd = !empty($_POST['limit_per_day']) ? intval($_POST['limit_per_day']) : 0;
        $img3 = !empty($_POST['img3']) ? esc_url_raw($_POST['img3']) : null;
        $img4 = !empty($_POST['img4']) ? esc_url_raw($_POST['img4']) : null;
        global $wpdb; $wpdb->insert(IATS_TABLE_TASKS, ['user_id' => $uid, 'detail' => sanitize_text_field($_POST['detail']), 'website' => sanitize_text_field($_POST['website']), 'count_limit' => $limit, 'count' => 0, 'count_get' => 0, 'tag' => $tag, 'fixed_code' => $fc, 'limit_per_day' => $lpd, 'img3' => $img3, 'img4' => $img4, 'step4' => 'Cuộn xuống cuối trang, lấy mã', 'active' => 1, 'last_reset_date' => current_time('Y-m-d')]);
        wp_send_json_success();
    }
    
    public function ajax_customer_get_stats() {
        @ob_clean();
        if (!is_user_logged_in() || !current_user_can('iats_customer')) wp_send_json_error('Lỗi quyền!');
        global $wpdb;
        $user_id = get_current_user_id();
        $domain = sanitize_text_field($_POST['domain']);
        $from = sanitize_text_field($_POST['from']) . ' 00:00:00';
        $to = sanitize_text_field($_POST['to']) . ' 23:59:59';
        
        $sql = $wpdb->prepare("SELECT t.id, t.detail, COUNT(l.id) as views 
                                FROM " . IATS_TABLE_TASKS . " t 
                                LEFT JOIN " . IATS_TABLE_LOGS . " l ON t.id = l.project_id AND l.created_at >= %s AND l.created_at <= %s AND l.event_type = 'verify_code' AND l.status = 'success'
                                WHERE t.user_id = %d AND t.website = %s 
                                GROUP BY t.id", $from, $to, $user_id, $domain);
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }
    
    public function ajax_customer_get_logs() {
        @ob_clean();
        if (!is_user_logged_in() || !current_user_can('iats_customer')) wp_send_json_error('Lỗi quyền!');
        global $wpdb;
        $user_id = get_current_user_id();
        $task_id = intval($_POST['task_id']);
        $from = sanitize_text_field($_POST['from']) . ' 00:00:00';
        $to = sanitize_text_field($_POST['to']) . ' 23:59:59';
        
        $check = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . IATS_TABLE_TASKS . " WHERE id = %d AND user_id = %d", $task_id, $user_id));
        if (!$check) wp_send_json_error('No permission');
        
        $sql = $wpdb->prepare("SELECT * FROM " . IATS_TABLE_LOGS . " WHERE project_id = %d AND event_type = 'verify_code' AND status = 'success' AND created_at >= %s AND created_at <= %s ORDER BY created_at DESC LIMIT 500", $task_id, $from, $to);
        $results = $wpdb->get_results($sql);
        wp_send_json_success($results);
    }
    public function ajax_admin_add_user() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) wp_send_json_error('No permission');
        $u = sanitize_user($_POST['username']); $e = sanitize_email($_POST['email']); $p = $_POST['password'];
        if (empty($u) || empty($e) || empty($p)) wp_send_json_error('Vui lòng nhập đầy đủ thông tin!');
        if (username_exists($u)) wp_send_json_error('Tên tài khoản đã tồn tại!');
        if (email_exists($e)) wp_send_json_error('Email đã tồn tại!');
        
        $user_id = wp_create_user($u, $p, $e);
        if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
        
        $user = new WP_User($user_id);
        $user->set_role('iats_customer');
        update_user_meta($user_id, 'iats_wallet_balance', 0);
        
        wp_send_json_success();
    }

    public function ajax_admin_delete_user() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) wp_send_json_error('No permission');
        $id = intval($_POST['id']);
        if (!$id) wp_send_json_error('ID không hợp lệ');
        require_once(ABSPATH.'wp-admin/includes/user.php');
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM " . IATS_TABLE_LOGS . " WHERE project_id IN (SELECT id FROM " . IATS_TABLE_TASKS . " WHERE user_id = %d)", $id));
        $wpdb->query($wpdb->prepare("DELETE FROM " . IATS_TABLE_TASKS . " WHERE user_id = %d", $id));
        wp_delete_user($id);
        wp_send_json_success();
    }

    public function ajax_admin_update_wallet() {
        @ob_clean(); if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        $uid = intval($_POST['user_id']); $amt = intval($_POST['amount']); $cur = intval(get_user_meta($uid, 'iats_wallet_balance', true));
        $nb = $cur + $amt; update_user_meta($uid, 'iats_wallet_balance', $nb); wp_send_json_success(['new_balance' => number_format($nb)]);
    }
    public function ajax_admin_update_settings() {
        @ob_clean(); if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        update_option('iats_portal_url', sanitize_text_field($_POST['iats_portal_url']));
        update_option('iats_login_url', sanitize_text_field($_POST['iats_login_url']));
        update_option('iats_view_price', intval($_POST['iats_view_price']));
        update_option('iats_result_url', sanitize_text_field($_POST['iats_result_url']));
        update_option('iats_priority_weight', intval($_POST['iats_priority_weight']));
        update_option('iats_session_mins', intval($_POST['iats_session_mins']));
        update_option('iats_token_mins', intval($_POST['iats_token_mins']));
        update_option('iats_cookie_days', intval($_POST['iats_cookie_days']));
        update_option('iats_max_wrong_attempts', intval($_POST['iats_max_wrong_attempts']));
        update_option('iats_log_keep_days', intval($_POST['iats_log_keep_days']));
        // Các cài đặt nút (bg, text_color...) đã chuyển qua API iats_save_domain_style (với tham số domain = 'default')
        if(isset($_POST['iats_portal_logo'])) update_option('iats_portal_logo', sanitize_text_field($_POST['iats_portal_logo']));
        if(isset($_POST['iats_btn_logo_default'])) update_option('iats_btn_logo_default', sanitize_text_field($_POST['iats_btn_logo_default']));
        wp_send_json_success();
    }
    public function ajax_toggle_priority() {
        @ob_clean(); if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        global $wpdb; 
        $id = intval($_POST['id']);
        $priority = intval($_POST['priority']);
        $wpdb->update(IATS_TABLE_TASKS, ['priority' => $priority], ['id' => $id]);
        wp_send_json_success();
    }
    public function ajax_admin_add_task() {
        @ob_clean(); if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        $uid = !empty($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $limit = intval($_POST['count_limit']);
        $tag = !empty($_POST['tag']) ? sanitize_text_field($_POST['tag']) : 'chung';
        $fc = !empty($_POST['fixed_code']) ? sanitize_text_field($_POST['fixed_code']) : null;
        $lpd = !empty($_POST['limit_per_day']) ? intval($_POST['limit_per_day']) : 0;
        $img3 = !empty($_POST['img3']) ? esc_url_raw($_POST['img3']) : null;
        $img4 = !empty($_POST['img4']) ? esc_url_raw($_POST['img4']) : null;
        $pri = isset($_POST['priority']) ? 1 : 0;
        global $wpdb; 
        
        $data = [
            'user_id' => $uid, 'detail' => sanitize_text_field($_POST['detail']), 'website' => sanitize_text_field($_POST['website']), 
            'target_url' => !empty($_POST['target_url']) ? esc_url_raw($_POST['target_url']) : null,
            'count_limit' => $limit, 'tag' => $tag, 'fixed_code' => $fc, 
            'limit_per_day' => $lpd, 'img3' => $img3, 'img4' => $img4, 'priority' => $pri
        ];

        if (!empty($_POST['task_id'])) {
            $wpdb->update(IATS_TABLE_TASKS, $data, ['id' => intval($_POST['task_id'])]);
        } else {
            $data['count'] = 0; $data['count_get'] = 0; $data['active'] = 1;
            $data['step4'] = 'Cuộn xuống cuối trang, lấy mã'; $data['last_reset_date'] = current_time('Y-m-d');
            $wpdb->insert(IATS_TABLE_TASKS, $data);
        }
        
        wp_send_json_success();
    }
    public function ajax_admin_delete_task() {
        @ob_clean(); if (!current_user_can('manage_options')) wp_send_json_error('No permission');
        global $wpdb; 
        $id = intval($_POST['id']);
        $wpdb->delete(IATS_TABLE_TASKS, ['id' => $id]);
        $wpdb->delete(IATS_TABLE_LOGS, ['project_id' => $id]);
        wp_send_json_success();
    }
}


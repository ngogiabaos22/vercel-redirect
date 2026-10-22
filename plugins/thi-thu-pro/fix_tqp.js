const fs = require('fs');
let file = 'c:/xampp/htdocs/wordpress/wp-content/plugins/traffic-quiz-pro/traffic-quiz-pro.php';
let content = fs.readFileSync(file, 'utf8');

let lines = content.split('\n');
let insertIndex = -1;
for (let i = 0; i < lines.length; i++) {
    if (lines[i].includes("require_once ABSPATH . 'wp-admin/includes/upgrade.php';")) {
        insertIndex = i + 1;
        break;
    }
}

if (insertIndex !== -1) {
    let missingCode = `    dbDelta($sql_categories);
    dbDelta($sql_exams);
    dbDelta($sql_groups);
    dbDelta($sql_questions);
}

// Tự động kiểm tra và nâng cấp bảng dữ liệu khi admin tải trang
add_action('admin_init', 'tqp_check_db_upgrade');
function tqp_check_db_upgrade() {
    global $wpdb;
    
    // Nâng cấp tq_question_categories
    $table_categories = $wpdb->prefix . 'tq_question_categories';
    $existing_table_cat = $wpdb->get_var("SHOW TABLES LIKE '$table_categories'");
    if (strcasecmp($existing_table_cat, $table_categories) !== 0) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql_categories = "CREATE TABLE $table_categories (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_categories);
        
        // Tạo danh mục "Chung" mặc định
        $wpdb->insert($table_categories, ['name' => 'Chung', 'description' => 'Danh mục mặc định']);
    }

    $table_exams = $wpdb->prefix . 'tq_exams';
    
    // Kiểm tra xem bảng tq_exams đã được tạo chưa (dùng empty để tránh lỗi so sánh chữ hoa/chữ thường)
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_exams'");
    if (!empty($table_exists)) {
        $column_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'category'");
        if (empty($column_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN category varchar(255) DEFAULT 'Khác' AFTER pass_tag");
        }
        
        $desc_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'description'");
        if (empty($desc_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN description longtext DEFAULT '' AFTER category");
        }

        $scoring_method_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'scoring_method'");
        if (empty($scoring_method_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN scoring_method varchar(50) DEFAULT 'standard' AFTER description");
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN intervals longtext DEFAULT NULL AFTER scoring_method");
        }
        
        $max_score_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'max_score'");
        if (empty($max_score_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN max_score float DEFAULT NULL AFTER intervals");
        }
        
        $video_url_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_exams\` LIKE 'video_url'");
        if (empty($video_url_check)) {
            $wpdb->query("ALTER TABLE \`$table_exams\` ADD COLUMN video_url varchar(255) DEFAULT '' AFTER show_answers");
        }
    }

    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_g_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_groups'");
    if (!empty($table_g_exists)) {
        $q_ids_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_groups\` LIKE 'question_ids'");
        if (empty($q_ids_check)) {
            $wpdb->query("ALTER TABLE \`$table_groups\` ADD COLUMN question_ids longtext DEFAULT NULL AFTER sort_order");
            
            // MIGRATION: Chuyển đổi group_id từ bảng questions sang question_ids ở bảng groups
            $groups = $wpdb->get_results("SELECT id FROM \`$table_groups\`");
            $table_questions = $wpdb->prefix . 'tq_questions';
            foreach ($groups as $g) {
                $q_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM \`$table_questions\` WHERE group_id = %d ORDER BY question_number ASC", $g->id));
                if (!empty($q_ids)) {
                    $q_ids_str = implode(',', $q_ids);
                    $wpdb->update($table_groups, ['question_ids' => $q_ids_str], ['id' => $g->id]);
                }
            }
        }
        
        $points_per_q_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_groups\` LIKE 'points_per_q'");
        if (empty($points_per_q_check)) {
            $wpdb->query("ALTER TABLE \`$table_groups\` ADD COLUMN points_per_q float DEFAULT NULL AFTER question_ids");
        }
    }

    $table_questions = $wpdb->prefix . 'tq_questions';
    $table_q_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_questions'");
    if (!empty($table_q_exists)) {
        $options_meta_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_questions\` LIKE 'options_meta'");
        if (empty($options_meta_check)) {
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN options_meta longtext DEFAULT NULL AFTER correct_ans");
        }
        
        $opt_e_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_questions\` LIKE 'opt_e'");
        if (empty($opt_e_check)) {
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN opt_e text DEFAULT NULL AFTER opt_d");
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN opt_f text DEFAULT NULL AFTER opt_e");
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN opt_g text DEFAULT NULL AFTER opt_f");
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN opt_h text DEFAULT NULL AFTER opt_g");
        }
        
        $cat_check = $wpdb->get_results("SHOW COLUMNS FROM \`$table_questions\` LIKE 'category_id'");
        if (empty($cat_check)) {
            $wpdb->query("ALTER TABLE \`$table_questions\` ADD COLUMN category_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER group_id");
            // Gán category_id = 1 (Chung) cho tất cả câu hỏi cũ
            $default_cat_id = $wpdb->get_var("SELECT id FROM \`$table_categories\` WHERE name = 'Chung' LIMIT 1");
            if ($default_cat_id) {
                $wpdb->query($wpdb->prepare("UPDATE \`$table_questions\` SET category_id = %d WHERE category_id = 0", $default_cat_id));
            }
        }
    }
}`;
    lines.splice(insertIndex, 0, missingCode);
    fs.writeFileSync(file, lines.join('\n'));
    console.log("Restored successfully!");
} else {
    console.log("Failed to find insertion point.");
}

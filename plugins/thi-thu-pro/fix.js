const fs = require('fs');
let content = fs.readFileSync('traffic-quiz-pro.php', 'utf8');

let newContent = `        $column_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_exams\\` LIKE 'category'");
        if (empty($column_check)) {
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN category varchar(255) DEFAULT 'Khác' AFTER pass_tag");
        }
        
        $desc_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_exams\\` LIKE 'description'");
        if (empty($desc_check)) {
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN description longtext DEFAULT '' AFTER category");
        }

        $scoring_method_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_exams\\` LIKE 'scoring_method'");
        if (empty($scoring_method_check)) {
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN scoring_method varchar(50) DEFAULT 'standard' AFTER description");
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN intervals longtext DEFAULT NULL AFTER scoring_method");
        }
        
        $max_score_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_exams\\` LIKE 'max_score'");
        if (empty($max_score_check)) {
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN max_score float DEFAULT NULL AFTER intervals");
        }
        
        $video_url_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_exams\\` LIKE 'video_url'");
        if (empty($video_url_check)) {
            $wpdb->query("ALTER TABLE \\`$table_exams\\` ADD COLUMN video_url varchar(255) DEFAULT '' AFTER show_answers");
        }
    }

    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_g_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_groups'");
    if (!empty($table_g_exists)) {
        $q_ids_check = $wpdb->get_results("SHOW COLUMNS FROM \\`$table_groups\\` LIKE 'question_ids'");
        if (empty($q_ids_check)) {
            $wpdb->query("ALTER TABLE \\`$table_groups\\` ADD COLUMN question_ids longtext DEFAULT NULL AFTER sort_order");
            
            // MIGRATION: Chuyển đổi group_id từ bảng questions sang question_ids ở bảng groups`;

content = content.replace(/ {8}\$column_check = \$wpdb->get_results\("SHOW COLUMNS FROM `\$table_exams` LIKE 'category'"\);\s+if \(empty\(\$column_check\)\) \{\s+\/\/ MIGRATION: Chuyển đổi group_id từ bảng questions sang question_ids ở bảng groups/, newContent);
fs.writeFileSync('traffic-quiz-pro.php', content);

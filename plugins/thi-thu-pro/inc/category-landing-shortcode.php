<?php
if (!defined('ABSPATH')) exit;

add_shortcode('tqp_category_landing', 'tqp_render_category_grid'); 

function tqp_render_category_grid($atts) {
    global $wpdb;
    
    wp_enqueue_style('tqp-style');
    
    $args = shortcode_atts([
        'category' => 'JLPT'
    ], $atts);

    $category = trim($args['category']);
    $table_exams = $wpdb->prefix . 'tq_exams';
    $table_groups = $wpdb->prefix . 'tq_groups';
    $table_questions = $wpdb->prefix . 'tq_questions';
    
    $exams = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_exams WHERE category = %s ORDER BY id DESC",
        $category
    ));

    ob_start();
    ?>
    <div class="ts-portal-grid">
        <?php if (!empty($exams)): 
            foreach ($exams as $exam):
                $title_lower = mb_strtolower($exam->title);
                
                $total_q = 0;
                $groups = $wpdb->get_results($wpdb->prepare("SELECT id FROM $table_groups WHERE exam_id = %d", $exam->id));
                if ($groups) {
                    foreach ($groups as $g) {
                        $total_q += $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_questions WHERE group_id = %d", $g->id));
                    }
                }
        ?>
            <div class="ts-portal-card" data-category="<?php echo esc_attr(sanitize_title($category)); ?>" data-title="<?php echo esc_attr($title_lower); ?>">
                <h3 class="ts-portal-card-title"><?php echo esc_html($exam->title); ?></h3>
                
                <div class="ts-portal-card-meta">
                    <span class="ts-portal-meta-item">⏱ <strong><?php echo $exam->duration_minutes; ?></strong> phút</span>
                    <span class="ts-portal-meta-item">📝 <strong><?php echo $total_q; ?></strong> câu hỏi</span>
                </div>
                
                <div class="ts-portal-card-footer">
                    <?php $exam_url = tqp_get_exam_page_url($exam->id); ?>
                    <a href="<?php echo esc_url($exam_url); ?>" class="ts-portal-start-btn" style="text-decoration: none !important; display: block !important; text-align: center;">
                        Thi thử ngay 
                    </a>
                </div>
            </div>
        <?php 
            endforeach;
        else: 
        ?>
            <div class="ts-portal-empty">
                <span class="ts-portal-empty-icon">📂</span>
                <p>Chưa có đề thi nào trong chuyên mục <?php echo esc_html($category); ?>.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

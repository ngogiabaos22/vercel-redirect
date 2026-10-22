<?php
/**
 * TestSpace Dedicated Fullscreen Exam Page Template
 */
if (!defined('ABSPATH')) exit;

$post_id = get_the_ID();
$exam_id = intval(get_post_meta($post_id, '_tq_exam_id', true));

if ($exam_id <= 0) {
    // Thử lấy qua query param dự phòng
    $exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
}

get_header();
?>
    <div class="ts-exam-dedicated-wrap" style="width: 100%; min-height: 100vh; display: flex; flex-direction: column;">

        <?php 
        if ($exam_id > 0) {
            echo tqp_render_quiz_shortcode(['id' => $exam_id]);
        } else {
            echo '<div style="color:#ef4444;padding:60px 20px;text-align:center;font-size:18px;font-weight:600;">Lỗi: Đề thi không khả dụng hoặc chưa được liên kết CPT!</div>';
        }
        ?>
    </div>

<?php get_footer(); ?>

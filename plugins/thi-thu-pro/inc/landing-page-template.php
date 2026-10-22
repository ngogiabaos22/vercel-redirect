<?php
/**
 * TestSpace Landing Page Template
 * This overrides the Flatsome header/footer for pages containing [traffic_quiz].
 */
if (!defined('ABSPATH')) exit;

get_header();
?>
    <!-- Main Content -->
    <?php
    while (have_posts()) :
        the_post();
            the_content();
    endwhile; // End of the loop.
    ?>

<?php get_footer(); ?>

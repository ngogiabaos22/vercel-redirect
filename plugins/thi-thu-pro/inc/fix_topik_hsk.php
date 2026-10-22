<?php
$src = 'c:/xampp/htdocs/wordpress/wp-content/plugins/traffic-quiz-pro/inc/hsk-landing-shortcode.php';
$dst = 'c:/xampp/htdocs/wordpress/wp-content/plugins/traffic-quiz-pro/inc/topik-landing-shortcode.php';

$content = file_get_contents($src);

// Basic replacements
$content = str_replace(
    ['hsk', 'HSK', 'tqp_render_hsk_', 'tqp_hsk_'],
    ['topik', 'TOPIK', 'tqp_render_topik_', 'tqp_topik_'],
    $content
);

// Level array replacement
$content = str_replace(
    '$all_levels = [1, 2, 3, 4, 5, 6];',
    '$all_levels = ["I", "II"];',
    $content
);

// Replace default level
$content = str_replace(
    "'level' => '1'",
    "'level' => 'I'",
    $content
);

// Replace HSK levels in the home page
// First let's do a naive replace of the static grid boxes for HSK 1..6
// Since HSK home page has 6 hardcoded levels, we should reduce them to 2 for TOPIK.
$content = preg_replace('/<div class="ts-hh-level-grid">(.*?)<\/div>\s*<\/div>\s*<!-- Call To Action/s', '<div class="ts-hh-level-grid">
                <!-- Level I -->
                <a href="<?php echo esc_url(home_url(\'/thi-thu-topik-i/\')); ?>" class="ts-hh-level-card ts-hh-card-1">
                    <div class="card-icon">1</div>
                    <h3 class="card-title">TOPIK I</h3>
                    <p class="card-desc">Sơ cấp</p>
                    <div class="card-arrow">→</div>
                </a>
                
                <!-- Level II -->
                <a href="<?php echo esc_url(home_url(\'/thi-thu-topik-ii/\')); ?>" class="ts-hh-level-card ts-hh-card-2">
                    <div class="card-icon">2</div>
                    <h3 class="card-title">TOPIK II</h3>
                    <p class="card-desc">Trung - Cao cấp</p>
                    <div class="card-arrow">→</div>
                </a>
            </div>
        </div>

        <!-- Call To Action', $content);

file_put_contents($dst, $content);
echo "Done";

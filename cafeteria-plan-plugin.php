<?php
/**
 * Plugin Name: Cafeteria Plan Plugin (CPT + GET-based PDF)
 * Description: A custom plugin to create cafeteria plan PDFs with a multi-step wizard, storing data in a CPT. The final PDF is generated via a GET request, similar to the "Hello World" test.
 * Version: 2.1
 * Author: Joe
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

/**
 * 1) Register the Custom Post Type: 'cafeteria_plan'
 */
function cpp_register_cpt()
{
    $labels = array(
        'name' => 'Cafeteria Plans',
        'singular_name' => 'Cafeteria Plan',
        'menu_name' => 'Cafeteria Plans',
        'name_admin_bar' => 'Cafeteria Plan',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Cafeteria Plan',
        'new_item' => 'New Cafeteria Plan',
        'edit_item' => 'Edit Cafeteria Plan',
        'view_item' => 'View Cafeteria Plan',
        'all_items' => 'All Cafeteria Plans',
        'search_items' => 'Search Cafeteria Plans',
        'parent_item_colon' => 'Parent Cafeteria Plan:',
        'not_found' => 'No Cafeteria Plans found.',
        'not_found_in_trash' => 'No Cafeteria Plans found in Trash.',
    );
    $args = array(
        'labels' => $labels,
        'public' => false, // not publicly queryable
        'show_ui' => true,  // show in admin
        'show_in_menu' => true,
        'menu_position' => 20,    // position in admin menu
        'menu_icon' => 'dashicons-clipboard',
        'supports' => array('title'),
        'has_archive' => false,
        'capability_type' => 'post',
    );
    register_post_type('cafeteria_plan', $args);
}
add_action('init', 'cpp_register_cpt');

/**
 * 2) Debug: Minimal "Hello World" Dompdf test
 *    Visit https://yoursite.com/?dompdf_test=1 to confirm Dompdf works.
 */
add_action('init', function () {
    if (isset($_GET['dompdf_test'])) {
        error_log('DEBUG: Entered dompdf_test route.');
        $testDomPdf = new Dompdf();
        try {
            $testHtml = '<h1>Hello Dompdf!</h1><p>This is a minimal test of Dompdf.</p>';
            $testDomPdf->loadHtml($testHtml);
            $testDomPdf->setPaper('A4', 'portrait');
            $testDomPdf->render();

            $pdfOutput = $testDomPdf->output();
            error_log('DEBUG: HelloWorld PDF length = ' . strlen($pdfOutput));

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="test.pdf"');
            echo $pdfOutput;
        } catch (\Exception $e) {
            error_log('DOMPDF TEST ERROR: ' . $e->getMessage());
            echo 'Dompdf test failed: ' . esc_html($e->getMessage());
        }
        exit;
    }
});

/**
 * 3) Hook to generate PDF via GET, mimicking the "Hello World" approach.
 *    e.g. https://yoursite.com/?caf_plan_pdf=1&plan_id=123
 */
add_action('init', function () {
    if (isset($_GET['caf_plan_pdf']) && !empty($_GET['plan_id'])) {
        $plan_id = (int) $_GET['plan_id'];
        error_log('DEBUG: GET-based PDF route triggered, plan_id=' . $plan_id);
        cpp_wizard_generate_pdf($plan_id);
    }
});

/**
 * 4) Optional: Skip cache on cafeteria-plan-generator page
 */
function cpp_wizard_skip_cache()
{
    if (is_page('cafeteria-plan-generator')) {
        define('DONOTCACHEPAGE', true);
    }
}
add_action('template_redirect', 'cpp_wizard_skip_cache', 1);

/**
 * 5) Enqueue CSS/JS if needed
 */
function cpp_wizard_enqueue_scripts()
{
    wp_enqueue_style('cpp-wizard-styles', plugin_dir_url(__FILE__) . 'css/style.css', [], '1.0');
    wp_enqueue_script('cpp-wizard-script', plugin_dir_url(__FILE__) . 'js/script.js', ['jquery'], '1.0', true);
}
add_action('wp_enqueue_scripts', 'cpp_wizard_enqueue_scripts');

/**
 * 6) Define Wizard Steps
 */
function cpp_get_wizard_steps()
{
    return [
        1 => [
            'slug' => 'basic-info',
            'title' => 'Basic Info',
            'fields' => [
                [
                    'type' => 'text',
                    'name' => 'company_name',
                    'label' => 'Company Name',
                ],
                [
                    'type' => 'date',
                    'name' => 'effective_date',
                    'label' => 'Effective Date',
                ],
            ],
        ],
        2 => [
            'slug' => 'additional-info',
            'title' => 'Additional Info',
            'fields' => [
                [
                    'type' => 'textarea',
                    'name' => 'plan_details',
                    'label' => 'Plan Details',
                ],
            ],
        ],
        3 => [
            'slug' => 'preview',
            'title' => 'Preview & Generate',
            'fields' => [],
        ],
    ];
}

/**
 * 7) Load sample library from JSON or array
 */
function cpp_load_plan_library()
{
    return [
        [
            'id' => 'cobra_clause',
            'trigger' => 'include_cobra',
            'title' => 'COBRA Coverage Clause',
            'body' => 'This plan provides that employees may continue coverage under COBRA...',
        ],
        // Add more paragraphs here...
    ];
}

/**
 * 8) Main shortcode [cafeteria_plan_form_wizard]
 */
function cpp_wizard_shortcode()
{
    // Default step = 1
    $current_step = isset($_POST['current_step']) ? intval($_POST['current_step']) : 1;

    // Plan ID from hidden field
    $caf_plan_id = isset($_POST['cafeteria_plan_id']) ? intval($_POST['cafeteria_plan_id']) : 0;

    $steps = cpp_get_wizard_steps();

    // Handle form submission
    cpp_wizard_process_form($steps, $current_step, $caf_plan_id);

    ob_start();
    ?>
    <div class="cpp-wizard-container" style="display: flex;">
        <!-- Sidebar -->
        <div class="cpp-wizard-sidebar"
            style="width: 200px; margin-right: 20px; border-right: 1px solid #ccc; padding-right:10px;">
            <h3>Cafeteria Plan Steps</h3>
            <ul style="list-style:none; padding-left:0;">
                <?php foreach ($steps as $stepIndex => $info):
                    $isActive = ($stepIndex == $current_step);
                    ?>
                    <li style="margin-bottom:5px;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="cafeteria_plan_id" value="<?php echo esc_attr($caf_plan_id); ?>" />
                            <input type="hidden" name="current_step" value="<?php echo $stepIndex; ?>" />
                            <button type="submit"
                                style="<?php echo $isActive ? 'font-weight:bold;' : ''; ?> background:none; border:none; cursor:pointer; text-align:left; padding:0;">
                                <?php echo $stepIndex . '. ' . esc_html($info['title']); ?>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Main content -->
        <div class="cpp-wizard-main" style="flex:1;">
            <?php
            // Render current step
            cpp_wizard_render_step($steps, $current_step, $caf_plan_id);
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('cafeteria_plan_form_wizard', 'cpp_wizard_shortcode');

/**
 * 9) Handle form submissions
 */
function cpp_wizard_process_form($steps, &$current_step, &$caf_plan_id)
{
    // Possibly user navigated via the sidebar
    if (isset($_POST['current_step'])) {
        $desiredStep = intval($_POST['current_step']);
        if ($desiredStep >= 1 && $desiredStep <= count($steps)) {
            $current_step = $desiredStep;
        }
    }

    // If a step form was submitted
    if (isset($_POST['cpp_wizard_submit_step'])) {
        $submittedStep = intval($_POST['cpp_wizard_submit_step']);

        // If no plan ID yet, create one now
        if ($caf_plan_id === 0) {
            $caf_plan_id = wp_insert_post([
                'post_type' => 'cafeteria_plan',
                'post_title' => 'Draft Cafeteria Plan - ' . current_time('mysql'),
                'post_status' => 'draft',
            ]);
        }

        if (isset($steps[$submittedStep])) {
            // Save each field in postmeta
            foreach ($steps[$submittedStep]['fields'] as $field) {
                $name = $field['name'];
                if (isset($_POST[$name])) {
                    $value = ($field['type'] === 'textarea')
                        ? sanitize_textarea_field($_POST[$name])
                        : sanitize_text_field($_POST[$name]);

                    update_post_meta($caf_plan_id, '_cpp_' . $name, $value);
                }
            }
        }

        // Go to next step if not final
        if ($submittedStep < count($steps)) {
            $current_step = $submittedStep + 1;
        }
    }

    // No more direct PDF generation here, since we switched to GET-based
}

/**
 * 10) Render current step or preview
 */
function cpp_wizard_render_step($steps, $current_step, $caf_plan_id)
{
    if (!isset($steps[$current_step])) {
        echo "<p>Invalid step.</p>";
        return;
    }

    $stepData = $steps[$current_step];

    if ($stepData['slug'] === 'preview') {
        // If final step, show preview + link to GET-based PDF
        cpp_wizard_render_preview_step($caf_plan_id);
        return;
    }

    // Otherwise, render form fields for the current step
    ?>
    <h2><?php echo esc_html($stepData['title']); ?></h2>
    <form method="post">
        <input type="hidden" name="cafeteria_plan_id" value="<?php echo esc_attr($caf_plan_id); ?>" />
        <input type="hidden" name="current_step" value="<?php echo esc_attr($current_step); ?>" />

        <?php
        // Load existing data
        foreach ($stepData['fields'] as $field):
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'];
            $value = '';

            if ($caf_plan_id) {
                $value = get_post_meta($caf_plan_id, '_cpp_' . $name, true);
            }
            ?>
            <div style="margin-bottom: 1em;">
                <label><?php echo esc_html($label); ?>:</label><br>
                <?php if ($type === 'textarea'): ?>
                    <textarea name="<?php echo esc_attr($name); ?>" rows="5"
                        cols="50"><?php echo esc_textarea($value); ?></textarea>
                <?php else: ?>
                    <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>"
                        value="<?php echo esc_attr($value); ?>" />
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" name="cpp_wizard_submit_step" value="<?php echo $current_step; ?>">
            <?php echo ($current_step < count($steps)) ? 'Next' : 'Preview'; ?>
        </button>
    </form>
    <?php
}

/**
 * 11) Render the "Preview" step
 */
function cpp_wizard_render_preview_step($caf_plan_id)
{
    ?>
    <h2>Preview Cafeteria Plan</h2>
    <p>This is an HTML preview of what the PDF will look like.</p>

    <div style="border:1px solid #ccc; padding:10px;">
        <?php
        $company_name = get_post_meta($caf_plan_id, '_cpp_company_name', true);
        $effective_date = get_post_meta($caf_plan_id, '_cpp_effective_date', true);
        $plan_details = get_post_meta($caf_plan_id, '_cpp_plan_details', true);

        $company_name = esc_html($company_name);
        $effective_date = esc_html($effective_date);
        $plan_details = esc_html($plan_details);

        $library = cpp_load_plan_library();
        ?>

        <h1>Cafeteria Plan</h1>
        <p><strong>Company:</strong> <?php echo $company_name; ?></p>
        <p><strong>Effective Date:</strong> <?php echo $effective_date; ?></p>
        <p><strong>Plan Details:</strong><br><?php echo nl2br($plan_details); ?></p>

        <?php
        // Example if there's a "include_cobra" meta
        $include_cobra = get_post_meta($caf_plan_id, '_cpp_include_cobra', true);
        if (!empty($include_cobra) && $include_cobra === 'yes') {
            foreach ($library as $paragraph) {
                if ($paragraph['trigger'] === 'include_cobra') {
                    echo '<h3>' . esc_html($paragraph['title']) . '</h3>';
                    echo '<p>' . esc_html($paragraph['body']) . '</p>';
                }
            }
        }
        ?>
    </div>

    <!-- Instead of a form post, we use GET-based link -->
    <?php if ($caf_plan_id): ?>
        <p style="margin-top: 15px;">
            <a href="<?php echo esc_url(add_query_arg([
                'caf_plan_pdf' => 1,
                'plan_id' => $caf_plan_id
            ], home_url('/'))); ?>" target="_blank" class="button">
                Generate Final PDF
            </a>
        </p>
    <?php endif; ?>
<?php
}

/**
 * 12) PDF generation function
 */
function cpp_wizard_generate_pdf($caf_plan_id)
{
    error_log('DEBUG: Entered cpp_wizard_generate_pdf function.');

    // Clear out any buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    ob_clean();

    $dompdf = new Dompdf();

    $company_name = get_post_meta($caf_plan_id, '_cpp_company_name', true);
    $effective_date = get_post_meta($caf_plan_id, '_cpp_effective_date', true);
    $plan_details = get_post_meta($caf_plan_id, '_cpp_plan_details', true);

    $company_name = esc_html($company_name);
    $effective_date = esc_html($effective_date);
    $plan_details = esc_html($plan_details);

    $library = cpp_load_plan_library();
    $include_cobra = get_post_meta($caf_plan_id, '_cpp_include_cobra', true);

    // Build the HTML
    $html = '<h1>Cafeteria Plan</h1>';
    $html .= '<p><strong>Company:</strong> ' . $company_name . '</p>';
    $html .= '<p><strong>Effective Date:</strong> ' . $effective_date . '</p>';
    $html .= '<p><strong>Plan Details:</strong><br>' . nl2br($plan_details) . '</p>';

    if (!empty($include_cobra) && $include_cobra === 'yes') {
        foreach ($library as $paragraph) {
            if ($paragraph['trigger'] === 'include_cobra') {
                $html .= '<h3>' . esc_html($paragraph['title']) . '</h3>';
                $html .= '<p>' . esc_html($paragraph['body']) . '</p>';
            }
        }
    }

    error_log('DEBUG: HTML for PDF => ' . $html);

    try {
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $length = strlen($pdfOutput);
        error_log('DEBUG: PDF length = ' . $length);

        if ($length === 0) {
            error_log('DEBUG: Dompdf returned 0 bytes. Possibly a missing PHP extension or an internal error.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="cafeteria-plan.pdf"');
        header('Accept-Ranges: none');

        echo $pdfOutput;
    } catch (\Exception $e) {
        error_log('DOMPDF ERROR: ' . $e->getMessage());
        echo '<p>Sorry, an error occurred generating the PDF: ' . esc_html($e->getMessage()) . '</p>';
    }
    exit;
}

/**
 * 13) Log if template_redirect fires after PDF attempt
 */
add_action('template_redirect', function () {
    error_log('DEBUG: template_redirect is firing...');
}, 9999);

<?php
/**
 * Plugin Name: Cafeteria Plan Plugin (CPT + Multi-Step + Conditionals)
 * Description: Demonstrates multiple question types, if/else logic, and styled PDF output for cafeteria plans.
 * Version: 2.2
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
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-clipboard',
        'supports' => array('title'),
        'has_archive' => false,
        'capability_type' => 'post',
    );
    register_post_type('cafeteria_plan', $args);
}
add_action('init', 'cpp_register_cpt');

/**
 * 2) Minimal "Hello World" Dompdf test
 *    https://yoursite.com/?dompdf_test=1
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
 * 3) GET-based PDF route: https://yoursite.com/?caf_plan_pdf=1&plan_id=123
 */
add_action('init', function () {
    if (isset($_GET['caf_plan_pdf']) && !empty($_GET['plan_id'])) {
        $plan_id = (int) $_GET['plan_id'];
        error_log('DEBUG: GET-based PDF route triggered, plan_id=' . $plan_id);
        cpp_wizard_generate_pdf($plan_id);
    }
});

/**
 * 4) Optional: Skip cache
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
 *    Now we have 4 steps: Basic Info, Additional Info, Plan Options, Preview & Generate.
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
                    'label' => 'General Plan Details',
                ],
            ],
        ],
        3 => [
            'slug' => 'plan-options',
            'title' => 'Plan Options',
            'fields' => [
                // Example: radio for COBRA
                [
                    'type' => 'radio-cobra',
                    'name' => 'include_cobra',
                    'label' => 'Include COBRA coverage?',
                ],
                // Example: radio for FSA
                [
                    'type' => 'radio-fsa',
                    'name' => 'include_fsa',
                    'label' => 'Include FSA (Flexible Spending Account)?',
                ],
                // Example: checkboxes for multiple benefits
                [
                    'type' => 'checkbox-benefits',
                    'name' => 'benefits_included',
                    'label' => 'Which benefits are included?',
                ],
                // Extra text field for special requirements
                [
                    'type' => 'textarea',
                    'name' => 'special_requirements',
                    'label' => 'Any special eligibility requirements?',
                ],
            ],
        ],
        4 => [
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
            'trigger' => 'include_cobra', // used if user selected "yes"
            'title' => 'COBRA Coverage Clause',
            'body' => 'Under this plan, employees who qualify may continue coverage per COBRA guidelines...',
        ],
        // You can add more standard paragraphs here (FSA, etc.) or just inline them in the PDF code.
    ];
}

/**
 * 8) Main shortcode [cafeteria_plan_form_wizard]
 */
function cpp_wizard_shortcode()
{
    $current_step = isset($_POST['current_step']) ? intval($_POST['current_step']) : 1;
    $caf_plan_id = isset($_POST['cafeteria_plan_id']) ? intval($_POST['cafeteria_plan_id']) : 0;

    $steps = cpp_get_wizard_steps();

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
            cpp_wizard_render_step($steps, $current_step, $caf_plan_id);
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('cafeteria_plan_form_wizard', 'cpp_wizard_shortcode');

/**
 * 9) Process form submissions
 */
function cpp_wizard_process_form($steps, &$current_step, &$caf_plan_id)
{
    if (isset($_POST['current_step'])) {
        $desiredStep = intval($_POST['current_step']);
        if ($desiredStep >= 1 && $desiredStep <= count($steps)) {
            $current_step = $desiredStep;
        }
    }

    // If a step form was submitted
    if (isset($_POST['cpp_wizard_submit_step'])) {
        $submittedStep = intval($_POST['cpp_wizard_submit_step']);

        if ($caf_plan_id === 0) {
            $caf_plan_id = wp_insert_post([
                'post_type' => 'cafeteria_plan',
                'post_title' => 'Draft Cafeteria Plan - ' . current_time('mysql'),
                'post_status' => 'draft',
            ]);
        }

        if (isset($steps[$submittedStep])) {
            foreach ($steps[$submittedStep]['fields'] as $field) {
                $name = $field['name'];
                if (isset($_POST[$name])) {
                    // handle multiple field types
                    if ($field['type'] === 'textarea') {
                        $value = sanitize_textarea_field($_POST[$name]);
                    } elseif ($field['type'] === 'radio-cobra' || $field['type'] === 'radio-fsa') {
                        // e.g. "yes" or "no"
                        $value = sanitize_text_field($_POST[$name]);
                    } elseif ($field['type'] === 'checkbox-benefits') {
                        // could be multiple checkboxes
                        // store as array or comma separated string
                        $arr = array_map('sanitize_text_field', (array) $_POST[$name]);
                        $value = implode(',', $arr);
                    } else {
                        $value = sanitize_text_field($_POST[$name]);
                    }
                    update_post_meta($caf_plan_id, '_cpp_' . $name, $value);
                } else {
                    // If field wasn't set (like no checkboxes checked), store empty
                    update_post_meta($caf_plan_id, '_cpp_' . $name, '');
                }
            }
        }

        // Move to next step if not final
        if ($submittedStep < count($steps)) {
            $current_step = $submittedStep + 1;
        }
    }
}

/**
 * 10) Render current step
 */
function cpp_wizard_render_step($steps, $current_step, $caf_plan_id)
{
    if (!isset($steps[$current_step])) {
        echo "<p>Invalid step.</p>";
        return;
    }

    $stepData = $steps[$current_step];

    if ($stepData['slug'] === 'preview') {
        cpp_wizard_render_preview_step($caf_plan_id);
        return;
    }

    ?>
    <h2><?php echo esc_html($stepData['title']); ?></h2>
    <form method="post">
        <input type="hidden" name="cafeteria_plan_id" value="<?php echo esc_attr($caf_plan_id); ?>" />
        <input type="hidden" name="current_step" value="<?php echo esc_attr($current_step); ?>" />

        <?php
        // Load existing meta from DB
        foreach ($stepData['fields'] as $field):
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'];
            $value = '';
            if ($caf_plan_id) {
                $value = get_post_meta($caf_plan_id, '_cpp_' . $name, true);
            }

            echo '<div style="margin-bottom: 1.5em;">';
            echo '<label><strong>' . esc_html($label) . '</strong></label><br>';

            if ($type === 'textarea') {
                // Simple text area
                ?>
                <textarea name="<?php echo esc_attr($name); ?>" rows="4" cols="50"><?php echo esc_textarea($value); ?></textarea>
                <?php
            } elseif ($type === 'radio-cobra' || $type === 'radio-fsa') {
                // Yes/No radio
                $yesChecked = ($value === 'yes') ? 'checked' : '';
                $noChecked = ($value === 'no') ? 'checked' : '';
                ?>
                <label><input type="radio" name="<?php echo esc_attr($name); ?>" value="yes" <?php echo $yesChecked; ?>> Yes</label>
                <label style="margin-left:1em;"><input type="radio" name="<?php echo esc_attr($name); ?>" value="no" <?php echo $noChecked; ?>> No</label>
                <?php
            } elseif ($type === 'checkbox-benefits') {
                // Multiple checkboxes
                $selectedVals = explode(',', $value); // stored as comma separated string
                $allOptions = ['Medical', 'Dental', 'Vision', 'Life'];
                foreach ($allOptions as $opt) {
                    $checked = in_array($opt, $selectedVals) ? 'checked' : '';
                    ?>
                    <label style="display:inline-block; margin-right:1em;">
                        <input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($opt); ?>" <?php echo $checked; ?>>
                        <?php echo esc_html($opt); ?>
                    </label>
                    <?php
                }
            } else {
                // Basic text input or date
                ?>
                <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>">
                <?php
            }

            echo '</div>';
        endforeach;
        ?>

        <button type="submit" name="cpp_wizard_submit_step" value="<?php echo $current_step; ?>">
            <?php echo ($current_step < count($steps)) ? 'Next' : 'Preview'; ?>
        </button>
    </form>
    <?php
}

/**
 * 11) Render Preview step
 */
function cpp_wizard_render_preview_step($caf_plan_id)
{
    ?>
    <h2>Preview Cafeteria Plan</h2>
    <p>This is an HTML preview of the final PDF.</p>


    <style>
        .pdf-preview-wrapper {
            background: #ffffff;
            padding: 72pt;
            margin: 40px auto;
            max-width: 816px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.15);
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
        }

        .pdf-preview-wrapper h1,
        .pdf-preview-wrapper h2,
        .pdf-preview-wrapper h3 {
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
            text-align: center;
            margin-top: 24pt;
            margin-bottom: 12pt;
        }

        .pdf-preview-wrapper h1 {
            font-size: 18pt;
            text-transform: uppercase;
        }

        .pdf-preview-wrapper h2 {
            font-size: 16pt;
        }

        .pdf-preview-wrapper h3 {
            font-size: 14pt;
        }

        .pdf-preview-wrapper p {
            margin: 0 0 12pt 0;
        }
    </style>
    <div class="pdf-preview-wrapper">

        <?php
        $company_name = get_post_meta($caf_plan_id, '_cpp_company_name', true);
        $effective_date = get_post_meta($caf_plan_id, '_cpp_effective_date', true);
        $plan_details = get_post_meta($caf_plan_id, '_cpp_plan_details', true);
        $special_req = get_post_meta($caf_plan_id, '_cpp_special_requirements', true);

        $company_name = esc_html($company_name);
        $effective_date = esc_html($effective_date);
        $plan_details = esc_html($plan_details);
        $special_req = esc_html($special_req);

        $include_cobra = get_post_meta($caf_plan_id, '_cpp_include_cobra', true);  // yes/no
        $include_fsa = get_post_meta($caf_plan_id, '_cpp_include_fsa', true);    // yes/no
        $benefits_str = get_post_meta($caf_plan_id, '_cpp_benefits_included', true); // comma separated
        $benefits_arr = array_filter(explode(',', $benefits_str));

        ?>
        <h1>Cafeteria Plan</h1>
        <p><strong>Company:</strong> <?php echo $company_name; ?></p>
        <p><strong>Effective Date:</strong> <?php echo $effective_date; ?></p>
        <p><strong>Plan Details:</strong><br><?php echo nl2br($plan_details); ?></p>

        <?php if ($include_cobra === 'yes'): ?>
            <h3>COBRA Coverage</h3>
            <p>This plan will include COBRA coverage clauses for eligible employees.</p>
        <?php endif; ?>

        <?php if ($include_fsa === 'yes'): ?>
            <h3>Flexible Spending Account (FSA)</h3>
            <p>Your cafeteria plan will contain FSA provisions for medical expenses, as appropriate.</p>
        <?php endif; ?>

        <?php if (!empty($benefits_arr)): ?>
            <h3>Included Benefits</h3>
            <ul>
                <?php foreach ($benefits_arr as $b): ?>
                    <li><?php echo esc_html($b); ?> coverage included.</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($special_req)): ?>
            <h3>Special Eligibility Requirements</h3>
            <p><?php echo nl2br($special_req); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($caf_plan_id): ?>
        <p style="margin-top: 15px;">
            <!-- GET-based PDF generation link -->
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
 * 12) PDF Generation
 */
function cpp_wizard_generate_pdf($caf_plan_id)
{
    error_log('DEBUG: Entered cpp_wizard_generate_pdf function.');

    if (ob_get_length()) {
        ob_end_clean();
    }
    ob_clean();

    $dompdf = new Dompdf();

    // Gather data from postmeta
    $company_name = get_post_meta($caf_plan_id, '_cpp_company_name', true);
    $effective_date = get_post_meta($caf_plan_id, '_cpp_effective_date', true);
    $plan_details = get_post_meta($caf_plan_id, '_cpp_plan_details', true);
    $special_req = get_post_meta($caf_plan_id, '_cpp_special_requirements', true);

    $include_cobra = get_post_meta($caf_plan_id, '_cpp_include_cobra', true);
    $include_fsa = get_post_meta($caf_plan_id, '_cpp_include_fsa', true);
    $benefits_str = get_post_meta($caf_plan_id, '_cpp_benefits_included', true);
    $benefits_arr = array_filter(explode(',', $benefits_str));

    // Convert to safe HTML
    $company_name = esc_html($company_name);
    $effective_date = esc_html($effective_date);
    $plan_details = esc_html($plan_details);
    $special_req = esc_html($special_req);

    // Let's load library in case we want to conditionally add text
    $library = cpp_load_plan_library();

    // Build the final PDF HTML with basic styling
    $html = '
<style>
    body {
        font-family: "Times New Roman", serif;
        font-size: 12pt;
        line-height: 1.5;
        margin: 72pt;
        color: #000;
    }
    h1, h2, h3 {
        font-family: "Times New Roman", serif;
        font-weight: bold;
        text-align: center;
        margin-top: 24pt;
        margin-bottom: 12pt;
    }
    h1 {
        font-size: 18pt;
        text-transform: uppercase;
    }
    h2 {
        font-size: 16pt;
    }
    h3 {
        font-size: 14pt;
    }
    p {
        margin: 0 0 12pt 0;
    }

    .pdf-preview-wrapper {
        background: #fff;
        padding: 72px;
        margin: 0 auto;
        max-width: 816px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        font-family: "Times New Roman", serif;
        font-size: 12pt;
        line-height: 1.5;
        color: #000;
    }
</style>
';

    $html .= '<div class="header-area">
        <h1>Cafeteria Plan</h1>
        <p style="margin:5px 0;"><strong>Company:</strong> ' . $company_name . '</p>
        <p><strong>Effective Date:</strong> ' . $effective_date . '</p>
    </div>';

    $html .= '<hr>';

    // Main content
    $html .= '<h2>Plan Details</h2>';
    $html .= '<p>' . nl2br($plan_details) . '</p>';

    // COBRA example
    if ($include_cobra === 'yes') {
        // If we had "COBRA" in library, we can add that:
        foreach ($library as $paragraph) {
            if ($paragraph['trigger'] === 'include_cobra') {
                $html .= '<h3>' . esc_html($paragraph['title']) . '</h3>';
                $html .= '<p>' . esc_html($paragraph['body']) . '</p>';
            }
        }
    }

    // FSA
    if ($include_fsa === 'yes') {
        $html .= '<h3>Flexible Spending Account (FSA)</h3>';
        $html .= '<p>Under this cafeteria plan, participants can elect to contribute a portion of their earnings to cover certain medical or dependent care expenses, as specified by IRS guidelines.</p>';
    }

    // Benefits
    if (!empty($benefits_arr)) {
        $html .= '<h3>Included Benefits</h3><ul>';
        foreach ($benefits_arr as $b) {
            $html .= '<li>' . esc_html($b) . ' coverage included</li>';
        }
        $html .= '</ul>';
    }

    // Special Requirements
    if (!empty($special_req)) {
        $html .= '<h3>Special Eligibility Requirements</h3>';
        $html .= '<p>' . nl2br($special_req) . '</p>';
    }

    // Footer
    $html .= '<div class="footer-area">
        <p>This Cafeteria Plan Document is provided for demonstration purposes.</p>
        <p>&copy; ' . date('Y') . ' Your Company. All rights reserved.</p>
    </div>';

    error_log('DEBUG: HTML for PDF => ' . $html);

    try {
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $length = strlen($pdfOutput);
        error_log('DEBUG: PDF length = ' . $length);

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
 * 13) Log template_redirect (optional debug)
 */
add_action('template_redirect', function () {
    error_log('DEBUG: template_redirect is firing...');
}, 9999);

<?php
/**
 * Plugin Name: Cafeteria Plan Plugin (Updated)
 * Description: Cafeteria Plan Wizard Plugin for Minnesota Healthcare Compliance Website.
 * Version: 2.3
 * Author: Joe
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;


// Register the Custom Post Type
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
        'public' => false,
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
// Define Wizard Steps
function cpp_get_wizard_steps()
{
    return [
        1 => [
            'slug' => 'demographics',
            'title' => '1. Demographics',
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
            'slug' => 'plan-options',
            'title' => '2. Plan Options',
            'fields' => [
                [
                    'type' => 'checkbox-multi',
                    'name' => 'plan_options',
                    'label' => 'Select Plan Options:',
                    'options' => [
                        'Pre-Tax Premiums',
                        'Health Flexible Spending Account (Health FSA)',
                        'Health Savings Account (HSA)',
                        'Dependent Care Account'
                    ],
                ],
            ],
        ],
        3 => [
            'slug' => 'preview',
            'title' => '3. Preview & Generate',
            'fields' => [],
        ],
    ];
}

// Validation to ensure at least one plan option is selected
function cpp_validate_plan_options($post_data)
{
    if (empty($post_data['plan_options'])) {
        return 'Please select at least one Plan Option.';
    }
    return '';
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

function cpp_get_template_versions()
{
    return [
        'v1' => [
            'label' => 'Version 1 (2025)',
            'components' => [
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3><p>The Premium Payment Plan allows employees to pay their share of premiums for medical, dental, or vision coverage on a pre-tax basis.</p>',
                'Health Flexible Spending Account (Health FSA)' => '<h3>Health Flexible Spending Account (Health FSA)</h3><p>The Health Flexible Spending Arrangement (Health FSA) reimburses eligible medical expenses, including dental and vision care, using pre-tax dollars.</p>',
                'Health Savings Account (HSA)' => '<h3>Health Savings Account (HSA)</h3><p>Employees may elect to contribute to a Health Savings Account (HSA), which allows tax-free contributions, growth, and withdrawals for qualified medical expenses.</p>',
                'Dependent Care Account' => '<h3>Dependent Care Account</h3><p>The Dependent Care Assistance Plan (Dependent Care FSA) reimburses qualifying child and dependent care costs to enable employees to work or seek employment.</p>',
            ]
        ],
        // Add future versions here
        'v2' => [
            'label' => 'Version 2 (2026)',
            'components' => [
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3><p>Updated details about pre-tax premiums...</p>',
                'Health Flexible Spending Account (Health FSA)' => '<h3>Health Flexible Spending Account (Health FSA)</h3><p>Updated details about Health FSA...</p>',
                'Health Savings Account (HSA)' => '<h3>Health Savings Account (HSA)</h3><p>Updated details about HSA...</p>',
                'Dependent Care Account' => '<h3>Dependent Care Account</h3><p>Updated details about Dependent Care Account...</p>',
            ]
        ],
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
    <style>
        .cpp-wizard-container {
            display: flex;
        }

        .cpp-wizard-sidebar {
            position: sticky;
            top: 50px;
            /* adjust as needed depending on your site’s header height */
            align-self: flex-start;
            z-index: 10;
            width: 243px;
            padding: 10px;
            box-sizing: border-box;
        }

        .cpp-wizard-nav-menu {
            background-color: #dfedf8;
            border: groove;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border-radius: 2px;
            overflow-y: auto;
            max-width: 93%;
            max-height: 860px;
            font-family: "Source Serif Pro", sans-serif;
            font-size: 15px;
        }

        .cpp-wizard-nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .cpp-wizard-nav-item {
            padding-bottom: 0;
            margin-bottom: 0;
            box-sizing: border-box;
            display: block;
            position: relative;
            color: rgb(75, 79, 88);
        }

        .cpp-wizard-nav-item button {
            display: block;
            width: 100%;
            height: 100%;
            padding: 13px 13px;
            background-color: transparent;
            border: none;
            font-size: 15px;
            font-weight: 700;
            font-family: "Source Serif Pro", sans-serif;
            color: rgb(75, 79, 88);
            text-align: left;
            cursor: pointer;
            transition: all 0.2s linear;
            text-decoration: none;
            outline: none;
            border-bottom: 0.6667px solid rgb(75, 79, 88);
        }

        .cpp-wizard-nav-item.active button {
            background-color: #3f444b;
            color: white;
            font-weight: 700;
            font-family: "Source Serif Pro", sans-serif;
        }
    </style>
    <div class="cpp-wizard-container">
        <!-- Sidebar -->
        <div class="cpp-wizard-sidebar">
            <nav class="cpp-wizard-nav-menu">
                <ul class="cpp-wizard-nav-list">
                    <?php foreach ($steps as $stepIndex => $info): ?>
                        <li class="cpp-wizard-nav-item <?php echo ($stepIndex == $current_step) ? 'active' : ''; ?>">
                            <form method="post">
                                <input type="hidden" name="cafeteria_plan_id" value="<?php echo esc_attr($caf_plan_id); ?>" />
                                <input type="hidden" name="current_step" value="<?php echo $stepIndex; ?>" />
                                <button type="submit"><?php echo esc_html($info['title']); ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
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

        // Lock in template version when plan is first created
        if (!get_post_meta($caf_plan_id, '_cpp_template_version', true)) {
            update_post_meta($caf_plan_id, '_cpp_template_version', 'v1');
        }


        if (isset($steps[$submittedStep])) {
            foreach ($steps[$submittedStep]['fields'] as $field) {
                $name = $field['name'];
                if (isset($_POST[$name])) {
                    // handle multiple field types

                    if ($field['type'] === 'textarea') {
                        $value = sanitize_textarea_field($_POST[$name]);
                    } elseif ($field['type'] === 'radio-cobra' || $field['type'] === 'radio-fsa') {
                        $value = sanitize_text_field($_POST[$name]);
                    } elseif ($field['type'] === 'checkbox-benefits') {
                        $arr = array_map('sanitize_text_field', (array) $_POST[$name]);
                        $value = implode(',', $arr);
                    } elseif ($field['type'] === 'checkbox-multi') {
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
                ?>
                <textarea name="<?php echo esc_attr($name); ?>" rows="4" cols="50"><?php echo esc_textarea($value); ?></textarea>
                <?php
            } elseif ($type === 'radio-cobra' || $type === 'radio-fsa') {
                $yesChecked = ($value === 'yes') ? 'checked' : '';
                $noChecked = ($value === 'no') ? 'checked' : '';
                ?>
                <label><input type="radio" name="<?php echo esc_attr($name); ?>" value="yes" <?php echo $yesChecked; ?>> Yes</label>
                <label style="margin-left:1em;"><input type="radio" name="<?php echo esc_attr($name); ?>" value="no" <?php echo $noChecked; ?>> No</label>
                <?php
            } elseif ($type === 'checkbox-benefits') {
                $selectedVals = explode(',', $value);
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
            } elseif ($type === 'checkbox-multi') {
                $selectedVals = explode(',', $value);
                foreach ($field['options'] as $option) {
                    $checked = in_array($option, $selectedVals) ? 'checked' : '';
                    ?>
                    <label style="display:block; margin-bottom: 0.5em;">
                        <input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($option); ?>" <?php echo $checked; ?>>
                        <?php echo esc_html($option); ?>
                    </label>
                    <?php
                }
            } else {
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

function cpp_build_intro_header($company_name, $effective_date, $plan_options_selected)
{
    $component_titles = [
        'Pre-Tax Premiums' => 'PREMIUM PAYMENT ARRANGEMENT',
        'Health Savings Account (HSA)' => 'HEALTH SAVINGS ACCOUNT',
        'Health Flexible Spending Account (Health FSA)' => 'HEALTH FLEXIBLE SPENDING ARRANGEMENT',
        'Dependent Care Account' => 'DEPENDENT CARE ASSISTANCE PLAN',
    ];

    $components = [];
    foreach ($plan_options_selected as $option) {
        $option = trim($option);
        if (isset($component_titles[$option])) {
            $components[] = $component_titles[$option];
        }
    }

    // Start of intro page
    $header_html = '<div style="page-break-after: always;">';

    // Company/Cover Page Heading
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: bold; margin-top: 120pt;">'
        . strtoupper($company_name) . '</div>';

    // Intro Title Line
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: normal; margin-top: 24pt;">'
        . 'CAFETERIA PLAN WITH</div>';

    // Component Lines
    $count = count($components);
    foreach ($components as $i => $comp) {
        $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; text-transform: uppercase; margin-top: 6pt;">' . $comp . '</div>';
        if ($count > 1 && $i === $count - 2) {
            $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; margin-top: 6pt;">AND</div>';
        }
    }

    // Final line: "COMPONENTS"
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; margin-top: 12pt;">COMPONENTS</div>';

    // Footer date line
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: bold; margin-top: 36pt;">'
        . 'As Amended and Restated ' . esc_html($effective_date) . '</div>';

    // Close page
    $header_html .= '</div>';

    return $header_html;
}



/**
 * 11) Render Preview step
 */
function cpp_wizard_render_preview_step($caf_plan_id)
{
    ?>
    <h2>Preview Cafeteria Plan</h2>


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


        .intro-page {
            page-break-after: always;
            margin-bottom: 120pt;
        }

        .intro-page div {
            margin-top: 12pt;
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
        <div class="intro-page">
            <?php
            $plan_options_selected_str = get_post_meta($caf_plan_id, '_cpp_plan_options', true);
            $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));
            echo cpp_build_intro_header($company_name, $effective_date, $plan_options_selected);
            ?>
        </div>

        <?php
        $plan_options_selected_str = get_post_meta($caf_plan_id, '_cpp_plan_options', true);
        $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));

        $template_version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
        $template_data = cpp_get_template_versions();
        $plan_text_blocks = $template_data[$template_version]['components'] ?? [];


        foreach ($plan_options_selected as $option) {
            $option = trim($option);
            if (isset($plan_text_blocks[$option])) {
                echo $plan_text_blocks[$option];
            }
        }
        ?>
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
    $plan_options_selected_str = get_post_meta($caf_plan_id, '_cpp_plan_options', true);
    $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));
    $html .= cpp_build_intro_header($company_name, $effective_date, $plan_options_selected);


    $html .= '<hr>';


    error_log('DEBUG: HTML for PDF => ' . $html);

    try {


        // Prepare dynamic text based on Plan Options
        $plan_options_selected_str = get_post_meta($caf_plan_id, '_cpp_plan_options', true);
        $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));

        $template_version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
        $template_data = cpp_get_template_versions();
        $plan_text_blocks = $template_data[$template_version]['components'] ?? [];


        foreach ($plan_options_selected as $option) {
            $option = trim($option);
            if (isset($plan_text_blocks[$option])) {
                $html .= $plan_text_blocks[$option];
            }
        }

        $html .= '<p style="text-align:right; font-size:10pt;"><em>Template Version: ' . esc_html($template_version) . '</em></p>';

        // Footer
        $html .= '<div class="footer-area">
        <p>This Cafeteria Plan Document is provided for demonstration purposes.</p>
        <p>&copy; ' . date('Y') . ' Your Company. All rights reserved.</p>
        </div>';

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

function cpp_render_plan_dashboard()
{
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your cafeteria plans.</p>';
    }

    $user_id = get_current_user_id();
    $plans = get_posts([
        'post_type' => 'cafeteria_plan',
        'post_status' => ['draft', 'publish'],
        'numberposts' => -1,
        'author' => $user_id,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    if (empty($plans)) {
        return '<p>You have not created any cafeteria plans yet.</p>';
    }

    ob_start();
    echo '<h2>My Cafeteria Plans</h2>';
    echo '<table class="cpp-plan-dashboard" style="width:100%; border-collapse: collapse; margin-top: 20px;">';
    echo '<thead><tr>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Plan Title</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Template Version</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Date Created</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Actions</th>
    </tr></thead><tbody>';

    foreach ($plans as $plan) {
        $version = get_post_meta($plan->ID, '_cpp_template_version', true) ?: 'v1';
        $date = get_the_date('', $plan->ID);
        $download_url = esc_url(add_query_arg(['caf_plan_pdf' => 1, 'plan_id' => $plan->ID], home_url('/')));

        echo '<tr>';
        echo '<td style="padding: 8px;">' . esc_html($plan->post_title) . '</td>';
        echo '<td style="padding: 8px;">' . esc_html($version) . '</td>';
        echo '<td style="padding: 8px;">' . esc_html($date) . '</td>';
        echo '<td style="padding: 8px;">
            <a href="' . $download_url . '" class="button" target="_blank">Download PDF</a>
        </td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode('cafeteria_plan_dashboard', 'cpp_render_plan_dashboard');


/**
 * 13) Log template_redirect (optional debug)
 */
add_action('template_redirect', function () {
    error_log('DEBUG: template_redirect is firing...');
}, 9999);

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
use Jfcherng\Diff\DiffHelper;


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

add_action('init', function () {
    if (isset($_GET['cpp_pdf_redirect']) && !empty($_GET['plan_id'])) {
        $plan_id = (int) $_GET['plan_id'];

        // Build URL to download PDF directly
        $pdf_url = add_query_arg([
            'caf_plan_pdf' => 1,
            'plan_id' => $plan_id
        ], home_url('/'));

        // Output HTML with JS redirect after triggering PDF
        ?>
        <html>

        <head>
            <title>Redirecting...</title>
        </head>

        <body>
            <script>
                window.onload = function () {
                    window.open("<?php echo esc_url_raw($pdf_url); ?>", "_blank");
                    setTimeout(function () {
                        window.location.href = "<?php echo esc_url(home_url('/user-dashboard')); ?>";
                    }, 1000);
                };
            </script>
            <p>Generating your PDF...</p>
        </body>

        </html>
        <?php
        exit;
    }
});


/**
 * 4) Optional: Skip cache
 */
function cpp_wizard_skip_cache()
{
    if (is_page('generator-wizard')) {
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
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3><p>The Premium Payment Plan allows employees to pay their share of premiums for medical, dental, or vision coverage on a pre-tax basis. <span class="cpp-template" data-key="Pre-Tax_Premiums">Up to $3,000/year</span>.</p>',
                'Health Flexible Spending Account (Health FSA)' => '<h3>Health Flexible Spending Account (Health FSA)</h3><p>The Health Flexible Spending Arrangement (Health FSA) reimburses eligible medical expenses, including dental and vision care, using pre-tax dollars.</p>',
                'Health Savings Account (HSA)' => '<h3>Health Savings Account (HSA)</h3><p>Employees may elect to contribute to a Health Savings Account (HSA), which allows tax-free contributions, growth, and withdrawals for qualified medical expenses.</p>',
                'Dependent Care Account' => '<h3>Dependent Care Account</h3><p>The Dependent Care Assistance Plan (Dependent Care FSA) reimburses qualifying child and dependent care costs to enable employees to work or seek employment.</p>',
            ]
        ],
        // Add future versions here
        'v2' => [
            'label' => 'Version 2 (2026)',
            'components' => [
                'Pre-Tax Premiums' => '<h3>Pre-Tax Premiums</h3><p>The Premium Payment Plan allows employees to pay their share of premiums for medical, dental, or vision coverage on a pre-tax basis. <span class="cpp-template" data-key="Pre-Tax_Premiums">Up to $3,500/year and is portable</span>.</p>',
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
    if (isset($_POST['cafeteria_plan_id'])) {
        $caf_plan_id = intval($_POST['cafeteria_plan_id']);
    } elseif (isset($_GET['cafeteria_plan_id'])) {
        $caf_plan_id = intval($_GET['cafeteria_plan_id']);
    } else {
        $caf_plan_id = 0;
    }

    if ($caf_plan_id) {
        $author_id = (int) get_post_field('post_author', $caf_plan_id);
        if ($author_id !== get_current_user_id()) {
            return '<p>You do not have permission to edit this plan.</p>';
        }
    }

    if ($caf_plan_id && !isset($_GET['cpp_pdf_redirect'])) {
        $current_status = get_post_meta($caf_plan_id, '_cpp_status', true);
        if ($current_status === 'Finalized') {
            update_post_meta($caf_plan_id, '_cpp_status', 'Editing');
            update_post_meta($caf_plan_id, '_cpp_last_edited', current_time('mysql'));
        }
    }



    /* if (isset($_GET['upgrade_plan_id']) && is_numeric($_GET['upgrade_plan_id'])) {
        $old_id = intval($_GET['upgrade_plan_id']);
        $author_id = (int) get_post_field('post_author', $old_id);

        if ($author_id === get_current_user_id()) {
            // Clone logic
            $new_id = wp_insert_post([
                'post_type' => 'cafeteria_plan',
                'post_title' => 'Upgraded Plan - ' . current_time('mysql'),
                'post_status' => 'draft',
                'post_author' => $author_id,
            ]);

            // Copy meta (except for version and status)
            $exclude_keys = ['_cpp_status', '_cpp_template_version'];
            $meta = get_post_meta($old_id);
            foreach ($meta as $key => $values) {
                if (in_array($key, $exclude_keys))
                    continue;
                foreach ($values as $val) {
                    add_post_meta($new_id, $key, maybe_unserialize($val));
                }
            }

            // Set new version + draft status
            $template_versions = cpp_get_template_versions();
            $latest = array_key_last($template_versions);
            update_post_meta($new_id, '_cpp_template_version', $latest);
            update_post_meta($new_id, '_cpp_status', 'Draft');
            update_post_meta($new_id, '_cpp_last_edited', current_time('mysql'));

            // --- KEY PATCH: Immediately redirect so the clone logic only runs once! ---
            $wizard_url = add_query_arg([
                'cafeteria_plan_id' => $new_id
            ], site_url('/generator-wizard/'));
            wp_redirect($wizard_url);
            exit;
        }
    }  */



    // Check if the user is logged in    

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
    <?php if ($caf_plan_id): ?>
        <div style="margin-bottom: 20px; padding: 10px; background: #f3f3f3; border: 1px solid #ccc;">
            <?php
            $post = get_post($caf_plan_id);
            $version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
            $last_edited = get_post_meta($caf_plan_id, '_cpp_last_edited', true);
            echo '<strong>Plan:</strong> ' . esc_html($post->post_title) . '<br>';
            echo '<strong>Version:</strong> ' . esc_html($version) . '<br>';
            echo '<strong>Author:</strong> ' . esc_html(get_the_author_meta('display_name', $post->post_author)) . '<br>';
            echo '<strong>Last Edited:</strong> ' . esc_html($last_edited);
            ?>
        </div>
    <?php endif; ?>

    <div class="cpp-wizard-container">
        <!-- Sidebar -->
        <div class="cpp-wizard-sidebar">
            <div style="margin-bottom: 15px;">
                <a href="/user-dashboard/" class="button">← View My Cafeteria Plans</a>
            </div>
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

        if (!$caf_plan_id || get_post_type($caf_plan_id) !== 'cafeteria_plan') {
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
            update_post_meta($caf_plan_id, '_cpp_last_edited', current_time('mysql'));
            $current_status = get_post_meta($caf_plan_id, '_cpp_status', true);
            if ($current_status !== 'Finalized') {
                update_post_meta($caf_plan_id, '_cpp_status', 'Draft');
            } elseif ($current_status === 'Finalized') {
                update_post_meta($caf_plan_id, '_cpp_status', 'Editing');
            }
            // or change to 'In Progress', etc.

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
                'cpp_pdf_redirect' => 1,
                'plan_id' => $caf_plan_id
            ], home_url('/'))); ?>" class="button">
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
    if (!$caf_plan_id || get_post_type($caf_plan_id) !== 'cafeteria_plan') {
        wp_die('Invalid or missing plan ID.');
    }
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
    update_post_meta($caf_plan_id, '_cpp_status', 'Finalized');
    update_post_meta($caf_plan_id, '_cpp_last_edited', current_time('mysql'));

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
        <p>&copy; ' . date('Y') . '  Kinney Law & Compliance. All rights reserved.</p>
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
    $order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
    $plans = get_posts([
        'post_type' => 'cafeteria_plan',
        'post_status' => ['draft', 'publish'],
        'numberposts' => -1,
        'author' => $user_id,
        'orderby' => 'date',
        'order' => $order,
    ]);

    if (empty($plans)) {
        return '<p>You have not created any cafeteria plans yet.</p>';
    }

    ob_start();
    // 🔽 Insert filter form here:
    ?>
    <form method="get" style="margin-bottom: 20px;">
        <label>Filter by Version:
            <select name="filter_version">
                <option value="">All</option>
                <?php foreach (cpp_get_template_versions() as $vKey => $vData): ?>
                    <option value="<?php echo esc_attr($vKey); ?>" <?php selected($_GET['filter_version'] ?? '', $vKey); ?>>
                        <?php echo esc_html($vKey); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="margin-left:15px;">Status:
            <select name="filter_status">
                <option value="">All</option>
                <option value="Draft" <?php selected($_GET['filter_status'] ?? '', 'Draft'); ?>>Draft</option>
                <option value="Finalized" <?php selected($_GET['filter_status'] ?? '', 'Finalized'); ?>>Finalized</option>
            </select>
        </label>
        <label style="margin-left:15px;">Sort by:
            <select name="sort_order">
                <option value="DESC" <?php selected($_GET['sort_order'] ?? '', 'DESC'); ?>>Newest First</option>
                <option value="ASC" <?php selected($_GET['sort_order'] ?? '', 'ASC'); ?>>Oldest First</option>
            </select>
        </label>

        <input type="submit" value="Apply Filters" class="button" style="margin-left:10px;">
    </form>
    <?php
    echo '<h2>My Cafeteria Plans</h2>';
    echo '<table class="cpp-plan-dashboard" style="width:100%; border-collapse: collapse; margin-top: 20px;">';
    echo '<thead><tr>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Plan Title</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Template Version</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Date Created</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Last Edited</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Status</th>
        <th style="border-bottom: 1px solid #ccc; padding: 8px;">Actions</th>
    </tr></thead><tbody>';

    foreach ($plans as $plan) {
        $version = get_post_meta($plan->ID, '_cpp_template_version', true) ?: 'v1';
        $template_versions = cpp_get_template_versions();
        $latest_version = array_key_last($template_versions);
        $is_outdated = version_compare($version, $latest_version, '<');
        $date = get_the_date('', $plan->ID);
        $last_edited = get_post_meta($plan->ID, '_cpp_last_edited', true);
        $status = get_post_meta($plan->ID, '_cpp_status', true) ?: 'Draft';

        if (!empty($_GET['filter_version']) && $_GET['filter_version'] !== $version) {
            continue;
        }
        if (!empty($_GET['filter_status']) && $_GET['filter_status'] !== $status) {
            continue;
        }

        $download_url = esc_url(add_query_arg(['caf_plan_pdf' => 1, 'plan_id' => $plan->ID], home_url('/')));
        $edit_url = esc_url(add_query_arg(['cafeteria_plan_id' => $plan->ID], home_url('generator-wizard'))); // adjust URL to your wizard page


        echo '<tr>';
        echo '<td style="padding: 8px;">' . esc_html($plan->post_title) . '</td>';
        echo '<td style="padding: 8px;">' . esc_html($version);
        if ($is_outdated) {
            echo ' <span style="color:red; font-weight:bold;">⚠ Outdated</span>';
        }
        $upgrade_url = esc_url(add_query_arg([
            'plan_id' => $plan->ID,
        ], home_url('/plan-upgrade/'))); // update URL if needed        
        echo '</td>';

        echo '<td style="padding: 8px;">' . esc_html($date) . '</td>';
        echo '<td style="padding: 8px;">' . esc_html($last_edited) . '</td>';
        echo '<td style="padding: 8px;">' . esc_html($status) . '</td>';
        echo '<td style="padding: 8px;">
            <a href="' . $download_url . '" class="button" target="_blank">Download PDF</a>
            <a href="' . $edit_url . '" class="button" style="margin-left:10px;">Edit</a>
        </td>';
        if ($is_outdated) {
            echo '<a href="' . $upgrade_url . '" class="button" style="margin-left:10px;">Upgrade Plan</a>';
        }
        echo '</tr>';
    }

    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode('cafeteria_plan_dashboard', 'cpp_render_plan_dashboard');



function cpp_diff_html_sections($old, $new)
{
    if (!$old)
        return '<ins style="background:#eaffea;">' . $new . '</ins>';
    if (!$new)
        return '<del style="background:#ffecec;">' . $old . '</del>';
    if ($old === $new)
        return $new;
    // Replace old with redline, show new as insert
    return '<del style="background:#ffecec;">' . $old . '</del><ins style="background:#eaffea;">' . $new . '</ins>';
}




// Helper: Generate a real HTML diff (returns valid HTML with <ins> and <del> tags)
/* function cpp_real_html_diff($old_html, $new_html)
{
    return DiffHelper::calculate(
        $old_html,
        $new_html,
        'Inline', // Output format
        [
            'detailLevel' => 'word',
            'insertMarkers' => ['<ins style="background:#eaffea;text-decoration:none;">', '</ins>'],
            'deleteMarkers' => ['<del style="background:#ffecec;text-decoration:line-through;">', '</del>'],
            'lineNumbers' => false,
            'resultForIdenticals' => '',
            // **The magic option below disables table output!**
            'rendererOptions' => [
                'showLineNumbers' => false,
                'detailLevel' => 'word',
                'mergeThreshold' => 0.8,
                'outputTagAsString' => false, // This tells the renderer to output HTML tags, not a table
                'separateBlock' => false,     // This disables separate blocks (i.e., disables the diff table)
            ],
        ]
    );
} */

// Section-by-section legal redline builder using DiffHelper
/* function cpp_build_sectional_redline_doc($company, $date, $options, $template_data, $old_version, $new_version)
{
    $html = cpp_build_intro_header($company, $date, $options);

    $old_blocks = $template_data[$old_version]['components'] ?? [];
    $new_blocks = $template_data[$new_version]['components'] ?? [];

    foreach ($options as $option) {
        $old = isset($old_blocks[$option]) ? $old_blocks[$option] : '';
        $new = isset($new_blocks[$option]) ? $new_blocks[$option] : '';
        // Use the simple legal-style redline for each section
        $html .= cpp_diff_html_sections($old, $new);
    }
    return $html;
} */

// Tag-based inline redline for only the <span class="cpp-template" data-key="...">...</span> regions
function cpp_redline_tagged_template_regions($old_html, $new_html)
{
    $old_doc = new DOMDocument();
    $new_doc = new DOMDocument();
    @$old_doc->loadHTML('<?xml encoding="utf-8" ?>' . $old_html);
    @$new_doc->loadHTML('<?xml encoding="utf-8" ?>' . $new_html);

    $xpath_old = new DOMXPath($old_doc);
    $xpath_new = new DOMXPath($new_doc);

    // Gather all old <span class="cpp-template" data-key="...">
    $old_spans = [];
    foreach ($xpath_old->query('//span[contains(@class,"cpp-template")]') as $span) {
        $key = $span->getAttribute('data-key');
        $old_spans[$key] = $span->nodeValue;
    }

    foreach ($xpath_new->query('//span[contains(@class,"cpp-template")]') as $span) {
        $key = $span->getAttribute('data-key');
        $old = isset($old_spans[$key]) ? $old_spans[$key] : '';
        $new = $span->nodeValue;
        if ($old !== $new) {
            $diff = cpp_diff_html_sections($old, $new);

            // Replace with diff, even if not valid XML!
            $owner = $span->ownerDocument;
            // Safely inject HTML into the span
            $tmp = new DOMDocument();
            @$tmp->loadHTML('<?xml encoding="utf-8" ?><span>' . $diff . '</span>');
            foreach ($span->childNodes as $child) {
                $span->removeChild($child);
            }
            // Import each child node from tmp into main doc
            $imported = [];
            foreach ($tmp->getElementsByTagName('span')->item(0)->childNodes as $child) {
                $imported[] = $owner->importNode($child, true);
            }
            foreach ($imported as $node) {
                $span->appendChild($node);
            }
        }
    }


    // Extract just the body’s innerHTML (no full <html> tags)
    $body = $new_doc->getElementsByTagName('body')->item(0);
    $new_html_with_diff = '';
    foreach ($body->childNodes as $child) {
        $new_html_with_diff .= $new_doc->saveHTML($child);
    }
    return $new_html_with_diff;
}




// Shortcode to show the redline/amendment adoption flow
add_shortcode('cafeteria_plan_upgrade', 'cpp_render_upgrade_flow');
function cpp_render_upgrade_flow($atts = [])
{
    if (!is_user_logged_in()) {
        return '<p>Please log in to review and adopt plan amendments.</p>';
    }

    // Get plan_id from URL (?plan_id=123)
    $plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;
    if (!$plan_id || get_post_type($plan_id) !== 'cafeteria_plan') {
        return '<p>Invalid or missing plan.</p>';
    }

    $user_id = get_current_user_id();
    $author_id = (int) get_post_field('post_author', $plan_id);
    if ($author_id !== $user_id) {
        return '<p>You do not have permission to view this plan.</p>';
    }

    // Get version info
    $current_version = get_post_meta($plan_id, '_cpp_template_version', true) ?: 'v1';
    $all_versions = cpp_get_template_versions();
    $latest_version = array_key_last($all_versions);

    if ($current_version === $latest_version) {
        return '<p>This plan already uses the latest template version.</p>';
    }

    // Get user’s plan options
    $plan_options_str = get_post_meta($plan_id, '_cpp_plan_options', true);
    $plan_options = array_filter(explode(',', $plan_options_str));

    // Build diff for each plan option
    $redlines = [];
    foreach ($plan_options as $opt) {
        $old = isset($all_versions[$current_version]['components'][$opt]) ? $all_versions[$current_version]['components'][$opt] : '';
        $new = isset($all_versions[$latest_version]['components'][$opt]) ? $all_versions[$latest_version]['components'][$opt] : '';
        $redlines[$opt] = cpp_diff_html_sections($old, $new);
    }

    // Handle form POST (adoption)
    $messages = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cpp_upgrade_accept'])) {
        $e_sign = sanitize_text_field($_POST['cpp_esignature'] ?? '');
        if (!$e_sign) {
            $messages[] = '<span style="color:red;">Please enter your full name as an e-signature.</span>';
        } else {
            // Record the upgrade and audit info
            update_post_meta($plan_id, '_cpp_template_version', $latest_version);
            update_post_meta($plan_id, '_cpp_status', 'Finalized');
            update_post_meta($plan_id, '_cpp_last_edited', current_time('mysql'));
            update_post_meta($plan_id, '_cpp_upgrade_audit', [
                'user_id' => $user_id,
                'signed_name' => $e_sign,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'datetime' => current_time('mysql'),
                'from_version' => $current_version,
                'to_version' => $latest_version
            ]);
            $messages[] = '<span style="color:green;">Amendment adopted! Your plan now uses the latest version.</span>';

            // JS: Open PDF download in new tab, then redirect after 1s
            echo '
                <script>
                window.onload = function() {
                    window.open("' . esc_url_raw(add_query_arg(['caf_plan_pdf' => 1, 'plan_id' => $plan_id], home_url('/'))) . '", "_blank");
                    setTimeout(function() {
                        window.location.href = "' . esc_url(home_url('/user-dashboard/')) . '";
                    }, 1200);
                };
                </script>
                ';
        }
    }

    ob_start();

    // Place this after ob_start();
    $company_name = get_post_meta($plan_id, '_cpp_company_name', true);
    $effective_date = get_post_meta($plan_id, '_cpp_effective_date', true);
    $plan_options_selected = $plan_options;

    if (!function_exists('cpp_build_full_doc_html')) {
        function cpp_build_full_doc_html($company, $date, $options, $template_data, $version)
        {
            $html = cpp_build_intro_header($company, $date, $options);
            $blocks = $template_data[$version]['components'] ?? [];
            foreach ($options as $option) {
                if (isset($blocks[$option])) {
                    $html .= $blocks[$option];
                }
            }
            return $html;
        }
    }
    $old_html = cpp_build_full_doc_html($company_name, $effective_date, $plan_options, $all_versions, $current_version);
    $new_html = cpp_build_full_doc_html($company_name, $effective_date, $plan_options, $all_versions, $latest_version);

    $sectional_redline = cpp_redline_tagged_template_regions($old_html, $new_html);



    ?>
    <div class="cpp-upgrade-wrapper" style="padding: 32px; margin: 40px auto; max-width: 860px;">
        <h2>Cafeteria Plan Amendment Adoption</h2>
        <p>Your current plan uses <strong><?php echo esc_html($all_versions[$current_version]['label']); ?></strong>. The
            latest version is <strong><?php echo esc_html($all_versions[$latest_version]['label']); ?></strong>.</p>
        <h3>What’s Changed?</h3>
        <?php foreach ($redlines as $section => $diff_html): ?>
            <div style="margin-bottom: 32px;">
                <h4><?php echo esc_html($section); ?></h4>
                <div class="cpp-redline-section" style="border:1px solid #ccc; padding:12px; background:#f9f9f9;">
                    <?php echo $diff_html; ?>
                </div>
            </div>
        <?php endforeach; ?>


        <h3>Full Redline Preview (Entire Document)</h3>
        <div
            style="border:2px solid #d44; background:#ffffff; padding:18px; margin-bottom:32px; font-size:14px; font-family:Times New Roman,serif;">
            <?php echo $sectional_redline; ?>
        </div>



        <form method="post" style="margin-top:32px; border-top: 1px solid #ccc; padding-top:20px;">
            <?php foreach ($messages as $msg)
                echo $msg; ?>
            <label><strong>E-signature:</strong>
                <input type="text" name="cpp_esignature" placeholder="Full legal name"
                    style="width:320px; margin-left:12px;" required>
            </label>
            <br><br>
            <label>
                <input type="checkbox" name="cpp_agree" required> I have read and agree to adopt the above amendments.
            </label>
            <br><br>
            <button type="submit" name="cpp_upgrade_accept" class="button button-primary">Adopt & Sign Amendment</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * 13) Log template_redirect (optional debug)
 */
add_action('template_redirect', function () {
    error_log('DEBUG: template_redirect is firing...');
}, 9999);

<?php
if (!defined('ABSPATH'))
    exit;
<<<<<<< HEAD
=======

add_action('wp_enqueue_scripts', function () {
    if (is_page(array('generator-wizard', 'plan-upgrade'))) {  // Add any other slugs used for preview/redline
        wp_enqueue_script(
            'pagedjs',
            'https://unpkg.com/pagedjs/dist/paged.polyfill.js',
            [],
            null,
            true // in footer
        );
    }
});

>>>>>>> 89f1b8706a63c466e96af4f70c1dc599727cdfd1
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
            'title' => 'Demographics',
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
            'title' => 'Coverage Options',
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
            'title' => 'Preview & Download',
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


    // Check if the user is logged in    

    $steps = cpp_get_wizard_steps();

    cpp_wizard_process_form($steps, $current_step, $caf_plan_id);

    ob_start();
    ?>
    <style>
        .cpp-wizard-container {
            display: flex;
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

        .cpp-progress-tracker {
            text-align: center;
            position: sticky;
            top: 0;
            /* or adjust if your admin bar/header is fixed */
            z-index: 100;
            background: #DFEDF8;
            border: 1px solid;
            padding: 20px 0 0 0;
            box-shadow: 0 2px 12px -4px rgba(0, 0, 0, 0.07);
            margin-bottom: 32px;
        }

        .cpp-progress-list {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .cpp-progress-step {
            position: relative;
            flex: 1 1 0;
            min-width: 120px;
            color: #3f444b;
        }

        .cpp-progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -60px;
            top: 24px;
            width: 60px;
            height: 3px;
            background: #D6B874;
            z-index: 0;
        }

        .cpp-progress-circle {
            display: inline-block;
            width: 32px;
            height: 32px;
            line-height: 32px;
            border-radius: 50%;
            background: #2d425c;
            border: 2px solid #3f444b;
            color: #3f444b;
            font-weight: bold;
            font-size: 16px;
            position: relative;
            z-index: 1;
            margin-bottom: 6px;
        }

        .cpp-progress-step.active .cpp-progress-circle {
            background: #D6B874;
            color: #D6B874;
            border-color: #D6B874;
        }

        .cpp-progress-step.completed .cpp-progress-circle {
            background: #3f444b;
            color: #D6B874;
            border-color: #3f444b;
        }

        .cpp-progress-label {
            display: block;
            font-size: 16px;
            font-family: "Source Serif 4", sans-serif;
            margin-top: 2px;
            font-weight: 500;
            color: #3f444b;
            white-space: nowrap;
        }

        .cpp-progress-step.completed .cpp-progress-label {
            color: #3f444b;
            opacity: 0.7;
        }

        .cpp-wizard-main {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 540px;
            padding: 48px 32px;
            background: #f8f9fb;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(44, 58, 86, 0.08);
            margin: 48px auto 32px auto;
            max-width: 520px;
            width: 100%;
            /* Remove the inline flex:1 if you have it! */
        }

        .cpp-wizard-main h2 {
            font-size: 2.1em;
            font-weight: 700;
            margin-bottom: 36px;
            color: #23374d;
            letter-spacing: -1px;
            text-align: center;
        }

        .cpp-wizard-main form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .cpp-wizard-main label {
            font-size: 1.1em;
            color: #23374d;
            margin-bottom: 8px;
        }

        .cpp-wizard-main input[type="text"],
        .cpp-wizard-main input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #b6bfc7;
            border-radius: 7px;
            background: #fff;
            font-size: 1em;
            margin-top: 4px;
            margin-bottom: 0.5em;
            transition: border-color 0.2s;
        }

        .cpp-wizard-main input:focus {
            border-color: #d5a848;
            outline: none;
        }

        .cpp-wizard-main button[type="submit"] {
            background: #d5a848;
            color: #fff;
            font-size: 1.1em;
            font-weight: 600;
            border: none;
            border-radius: 7px;
            padding: 12px 36px;
            margin-top: 18px;
            box-shadow: 0 2px 8px rgba(44, 58, 86, 0.09);
            cursor: pointer;
            transition: background 0.15s;
        }

        .cpp-wizard-main button[type="submit"]:hover {
            background: #b89735;
        }
    </style>
    <?php if ($caf_plan_id): ?>
        <div style="margin-bottom: 20px; padding: 10px; background: #ffffff; border: 1px solid #ccc;">
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

    <?php
    // Build progress tracker
    $total_steps = count($steps);
    ?>
    <div class="cpp-progress-tracker" style="margin-bottom:32px;">
        <ul class="cpp-progress-list">
            <?php foreach ($steps as $idx => $step):
                $is_active = ($idx == $current_step);
                $is_completed = ($idx < $current_step);
                ?>
                <li class="cpp-progress-step <?php
                echo $is_active ? 'active' : ($is_completed ? 'completed' : '');
                ?>">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="cafeteria_plan_id" value="<?php echo esc_attr($caf_plan_id); ?>" />
                        <input type="hidden" name="current_step" value="<?php echo $idx; ?>" />
                        <button type="submit" class="cpp-progress-circle"
                            style="background:none;border:none;cursor:pointer;padding:0;width:32px;height:32px;">
                            <?php echo $is_completed ? '✓' : $idx; ?>
                        </button>
                        <span class="cpp-progress-label"><?php echo esc_html($step['title']); ?></span>
                    </form>
                </li>

            <?php endforeach; ?>
        </ul>
    </div>


    <div class="cpp-wizard-container">
        <!-- Sidebar -->
        <!-- Main content -->
        <?php if ($steps[$current_step]['slug'] === 'preview'): ?>
            <?php cpp_wizard_render_step($steps, $current_step, $caf_plan_id); ?>
        <?php else: ?>
            <div class="cpp-wizard-main">
                <?php cpp_wizard_render_step($steps, $current_step, $caf_plan_id); ?>
            </div>
        <?php endif; ?>
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

            echo '<div style="margin-bottom: 1.5em; width: 100%;">';

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


/**
 * 11) Render Preview step
 */
function cpp_wizard_render_preview_step($caf_plan_id)
{
    ?>
    <div class="cpp-preview-step-main" style="flex: 1; display: flex; flex-direction: column; align-items: center;">
        <div style="width: 100%; max-width: 950px; text-align: center; margin-bottom: 22px;">
            <h2 style="margin-bottom: 18px;">Preview Cafeteria Plan</h2>
            <p style="margin-bottom: 22px; color: #4c5767; font-size: 1.12em;">
                This is a live preview of your cafeteria plan. Please review carefully before generating your final PDF.
            </p>
        </div>
<<<<<<< HEAD
        <div style="width: 100%; max-width: 900px; margin: 0 auto 24px auto;">
=======
        <div class="pagedjs-preview">
>>>>>>> 89f1b8706a63c466e96af4f70c1dc599727cdfd1
            <?php
            $template_version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
            $template_data = cpp_get_template_versions();
            echo cpp_build_full_doc_html($caf_plan_id, $template_data, $template_version, false, null, true);
            ?>
        </div>
        <?php if ($caf_plan_id): ?>
            <div style="width: 100%; max-width: 950px; text-align: center;">
                <a href="<?php echo esc_url(add_query_arg([
                    'cpp_pdf_redirect' => 1,
                    'plan_id' => $caf_plan_id
                ], home_url('/'))); ?>" class="button button-primary"
                    style="font-size: 1.1em; padding: 13px 38px; margin-top: 20px;">
                    Generate Final PDF
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
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
        $redlines[$opt] = cpp_dmp_word_diff(strip_tags($old), strip_tags($new));

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

    $template_data = cpp_get_template_versions();
    $old_html = cpp_build_full_doc_html($plan_id, $template_data, $current_version, false);
    $new_html = cpp_build_full_doc_html($plan_id, $template_data, $latest_version, false);
    $sectional_redline = cpp_build_full_doc_html($plan_id, $template_data, $latest_version, true, $current_version, true);


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
<<<<<<< HEAD
        <?php echo $sectional_redline; ?>
=======
        echo '<div class="pagedjs-preview">' . $sectional_redline . '</div>';

>>>>>>> 89f1b8706a63c466e96af4f70c1dc599727cdfd1




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
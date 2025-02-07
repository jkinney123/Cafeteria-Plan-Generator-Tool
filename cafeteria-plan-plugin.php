<?php
/**
 * Plugin Name: Cafeteria Plan Plugin
 * Description: Custom plugin for cafeteria plan PDFs with multi-step form on a single page (using sessions).
 * Version: 1.0
 * Author: Joe
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

/**
 * 1. Start a session so we can store step data between submissions.
 */
function cpp_start_session()
{
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'cpp_start_session', 1);

/**
 * 2. Enqueue any CSS/JS if needed
 */
function cpp_enqueue_scripts()
{
    wp_enqueue_style('cpp-styles', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0');
    // Example JS if desired
    wp_enqueue_script('cpp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'cpp_enqueue_scripts');

/**
 * 3. Main Shortcode: [cafeteria_plan_form]
 *    Displays Step 1, Step 2, Final Preview, etc., all on one page.
 */
function cpp_render_cafeteria_form()
{
    // 3a. Initialize session data array if not present
    if (!isset($_SESSION['cpp_data'])) {
        $_SESSION['cpp_data'] = array();
    }

    // 3b. Determine which step we're currently on
    //     Default to step 1 unless we see otherwise.
    $current_step = $_SESSION['cpp_data']['current_step'] ?? 1;

    // 3c. If the user just submitted Step 1
    if (isset($_POST['cpp_step1_submit'])) {
        // Store step 1 data
        $_SESSION['cpp_data']['company_name'] = sanitize_text_field($_POST['company_name'] ?? '');
        $_SESSION['cpp_data']['effective_date'] = sanitize_text_field($_POST['effective_date'] ?? '');

        // Move to Step 2
        $current_step = 2;
        $_SESSION['cpp_data']['current_step'] = 2;
    }

    // 3d. If the user just submitted Step 2 (to show the final preview)
    if (isset($_POST['cpp_step2_submit'])) {
        // Store step 2 data
        $_SESSION['cpp_data']['plan_details'] = sanitize_textarea_field($_POST['plan_details'] ?? '');

        // Move to Step 3 (final preview)
        $current_step = 3;
        $_SESSION['cpp_data']['current_step'] = 3;
    }

    // 3e. If user clicked "Generate Final PDF"
    if (isset($_POST['cpp_finalize'])) {
        // Gather all data from session
        $data = $_SESSION['cpp_data'];
        cpp_generate_pdf($data);
        exit; // Important to stop further WP rendering
    }

    // 3f. Now output the appropriate HTML for the current step
    ob_start();

    switch ($current_step) {
        case 1:
            // STEP 1 Form
            ?>
            <h2>Step 1: Basic Info</h2>
            <form method="post">
                <label>Company Name:</label><br>
                <input type="text" name="company_name" value="" /><br><br>

                <label>Effective Date:</label><br>
                <input type="date" name="effective_date" value="" /><br><br>

                <button type="submit" name="cpp_step1_submit" value="1">Next: Step 2</button>
            </form>
            <?php
            break;

        case 2:
            // STEP 2 Form
            $company_name = $_SESSION['cpp_data']['company_name'] ?? '';
            $effective_date = $_SESSION['cpp_data']['effective_date'] ?? '';
            ?>
            <h2>Step 2: Additional Info</h2>
            <p><strong>So far:</strong></p>
            <ul>
                <li>Company Name: <?php echo esc_html($company_name); ?></li>
                <li>Effective Date: <?php echo esc_html($effective_date); ?></li>
            </ul>

            <form method="post">
                <label>Plan Details:</label><br>
                <textarea name="plan_details" rows="5" cols="50"></textarea><br><br>

                <button type="submit" name="cpp_step2_submit" value="1">Preview Final Plan</button>
            </form>
            <?php
            break;

        case 3:
            // FINAL PREVIEW
            $company_name = $_SESSION['cpp_data']['company_name'] ?? '';
            $effective_date = $_SESSION['cpp_data']['effective_date'] ?? '';
            $plan_details = $_SESSION['cpp_data']['plan_details'] ?? '';
            ?>
            <h2>Final Preview</h2>
            <div style="border:1px solid #ccc; padding:10px;">
                <h3>Cafeteria Plan</h3>
                <p><strong>Company:</strong> <?php echo esc_html($company_name); ?></p>
                <p><strong>Effective Date:</strong> <?php echo esc_html($effective_date); ?></p>
                <p><strong>Plan Details:</strong><br><?php echo nl2br(esc_html($plan_details)); ?></p>
            </div>

            <form method="post">
                <button type="submit" name="cpp_finalize" value="1">Generate Final PDF</button>
            </form>
            <?php
            break;
    }

    return ob_get_clean();
}
add_shortcode('cafeteria_plan_form', 'cpp_render_cafeteria_form');

/**
 * 4. PDF Generation Function
 */
function cpp_generate_pdf($data)
{
    $dompdf = new Dompdf();

    // Pull out data safely
    $company_name = $data['company_name'] ?? '';
    $effective_date = $data['effective_date'] ?? '';
    $plan_details = $data['plan_details'] ?? '';

    // Build the final HTML
    $html = '<h1>Cafeteria Plan</h1>';
    $html .= '<p><strong>Company:</strong> ' . esc_html($company_name) . '</p>';
    $html .= '<p><strong>Effective Date:</strong> ' . esc_html($effective_date) . '</p>';
    $html .= '<p><strong>Plan Details:</strong><br>' . nl2br(esc_html($plan_details)) . '</p>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Stream the PDF
    $dompdf->stream("cafeteria-plan.pdf", array("Attachment" => 0));
}

<?php
/**
 * Plugin Name: Cafeteria Plan Plugin (Single-Page, Multi-Step with Back/Edit)
 * Description: A custom plugin to create cafeteria plan PDFs. Includes session-based multi-step flow on one page, with "Back" buttons to edit previous steps.
 * Version: 1.1
 * Author: Joe
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

/**
 * 1. Start a session so we can store step data between submissions
 *    Hook on 'plugins_loaded' to avoid "headers already sent" issues.
 */
function cpp_start_session()
{
    if (!session_id()) {
        session_start();
    }
}
add_action('plugins_loaded', 'cpp_start_session', 1);

/**
 * 2. OPTIONAL: Bypass WP Rocket cache for a specific page slug
 *    Adjust or remove if you already excluded in WP Rocket's settings.
 */
function skip_cache_on_cafeteria_page()
{
    if (is_page('cafeteria-plan-generator')) {
        define('DONOTCACHEPAGE', true);
    }
}
add_action('template_redirect', 'skip_cache_on_cafeteria_page', 1);

/**
 * 3. Enqueue any CSS/JS if needed
 */
function cpp_enqueue_scripts()
{
    // Example: CSS file
    wp_enqueue_style('cpp-styles', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0');
    // Example: JS file
    wp_enqueue_script('cpp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'cpp_enqueue_scripts');

/**
 * 4. Main Shortcode [cafeteria_plan_form]
 *    - Single-page multi-step flow (Step 1, Step 2, Final Preview)
 *    - "Back" buttons let user return to previous steps
 */
function cpp_render_cafeteria_form()
{
    // Initialize session storage array if not present
    if (!isset($_SESSION['cpp_data'])) {
        $_SESSION['cpp_data'] = array();
    }

    // Current step defaults to 1 if not set
    $current_step = $_SESSION['cpp_data']['current_step'] ?? 1;

    // --- STEP 1 submission ---
    if (isset($_POST['cpp_step1_submit'])) {
        $_SESSION['cpp_data']['company_name'] = sanitize_text_field($_POST['company_name'] ?? '');
        $_SESSION['cpp_data']['effective_date'] = sanitize_text_field($_POST['effective_date'] ?? '');
        $current_step = 2;
        $_SESSION['cpp_data']['current_step'] = 2;
    }

    // --- STEP 2 submission ---
    if (isset($_POST['cpp_step2_submit'])) {
        $_SESSION['cpp_data']['plan_details'] = sanitize_textarea_field($_POST['plan_details'] ?? '');
        $current_step = 3;
        $_SESSION['cpp_data']['current_step'] = 3;
    }

    // --- Generate PDF (Final) ---
    if (isset($_POST['cpp_finalize'])) {
        $data = $_SESSION['cpp_data'];
        cpp_generate_pdf($data);
        exit; // Stop further page rendering
    }

    // --- Back buttons to previous steps ---
    if (isset($_POST['cpp_back_to_step1'])) {
        $current_step = 1;
        $_SESSION['cpp_data']['current_step'] = 1;
    }
    if (isset($_POST['cpp_back_to_step2'])) {
        $current_step = 2;
        $_SESSION['cpp_data']['current_step'] = 2;
    }

    ob_start();

    switch ($current_step) {
        case 1:
            // Populate fields from session if they exist
            $company_name = $_SESSION['cpp_data']['company_name'] ?? '';
            $effective_date = $_SESSION['cpp_data']['effective_date'] ?? '';
            ?>
            <h2>Step 1: Basic Info</h2>
            <form method="post">
                <label>Company Name:</label><br>
                <input type="text" name="company_name" value="<?php echo esc_attr($company_name); ?>" /><br><br>

                <label>Effective Date:</label><br>
                <input type="date" name="effective_date" value="<?php echo esc_attr($effective_date); ?>" /><br><br>

                <button type="submit" name="cpp_step1_submit" value="1">Next: Step 2</button>
            </form>
            <?php
            break;

        case 2:
            $company_name = $_SESSION['cpp_data']['company_name'] ?? '';
            $effective_date = $_SESSION['cpp_data']['effective_date'] ?? '';
            $plan_details = $_SESSION['cpp_data']['plan_details'] ?? '';
            ?>
            <h2>Step 2: Additional Info</h2>
            <p><strong>So far:</strong></p>
            <ul>
                <li>Company Name: <?php echo esc_html($company_name); ?></li>
                <li>Effective Date: <?php echo esc_html($effective_date); ?></li>
            </ul>

            <form method="post">
                <label>Plan Details:</label><br>
                <textarea name="plan_details" rows="5" cols="50"><?php echo esc_textarea($plan_details); ?></textarea><br><br>

                <button type="submit" name="cpp_step2_submit" value="1">Preview Final Plan</button>
            </form>

            <!-- Back button to Step 1 -->
            <form method="post" style="margin-top:10px;">
                <button type="submit" name="cpp_back_to_step1" value="1">Back to Step 1</button>
            </form>
            <?php
            break;

        case 3:
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

            <form method="post" style="display:inline;">
                <button type="submit" name="cpp_finalize" value="1">Generate Final PDF</button>
            </form>

            <!-- Edit links to step 1 or step 2 -->
            <form method="post" style="display:inline;">
                <button type="submit" name="cpp_back_to_step2" value="1">Edit Step 2</button>
            </form>
            <form method="post" style="display:inline;">
                <button type="submit" name="cpp_back_to_step1" value="1">Edit Step 1</button>
            </form>
            <?php
            break;
    }

    return ob_get_clean();
}
add_shortcode('cafeteria_plan_form', 'cpp_render_cafeteria_form');

/**
 * 5. PDF Generation Function
 */
function cpp_generate_pdf($data)
{
    $dompdf = new Dompdf();

    // Safely extract data
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

    // Clear any output buffer to avoid corrupting PDF
    ob_end_clean();

    // Stream the PDF to the browser
    $dompdf->stream("cafeteria-plan.pdf", array("Attachment" => 0));
    exit;
}

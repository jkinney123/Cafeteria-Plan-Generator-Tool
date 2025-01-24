<?php
/**
 * Plugin Name: Cafeteria Plan Plugin
 * Description: Custom plugin for cafeteria plan PDFs.
 * Version: 1.0
 * Author: Joe
 */
if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

// 2. Enqueue Scripts and Styles (if needed)
function cpp_enqueue_scripts()
{
    wp_enqueue_style('cpp-styles', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.0');
    wp_enqueue_script('cpp-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'cpp_enqueue_scripts');

// 3. Shortcode to display multi-step form
function cpp_render_cafeteria_form()
{
    ob_start();
    ?>
    <form id="cafeteria-plan-form" method="post">
        <h3>Step 1: Basic Info</h3>
        <label>Company Name:</label>
        <input type="text" name="company_name" value="" />

        <label>Effective Date:</label>
        <input type="date" name="effective_date" value="" />

        <!-- More fields as needed -->

        <button type="submit" name="cpp_submit" value="1">Save & Preview</button>
    </form>

    <?php
    // If the user clicked Save & Preview, handle it
    if (isset($_POST['cpp_submit'])) {
        cpp_handle_form_submission($_POST);
    }

    return ob_get_clean();
}
add_shortcode('cafeteria_plan_form', 'cpp_render_cafeteria_form');

// 4. Function to handle the form submission
function cpp_handle_form_submission($data)
{
    // Sanitize and store data: in real code, store in the database or session
    $company_name = sanitize_text_field($data['company_name'] ?? '');
    $effective_date = sanitize_text_field($data['effective_date'] ?? '');

    // Display preview (HTML only for now) or generate PDF
    // For demonstration, let's do an HTML preview:
    echo '<h4>Preview of your Plan</h4>';
    echo '<p>Company: ' . esc_html($company_name) . '</p>';
    echo '<p>Effective Date: ' . esc_html($effective_date) . '</p>';

    // Optionally, add a button to generate the final PDF
    echo '<form method="post">';
    echo '<input type="hidden" name="company_name" value="' . esc_attr($company_name) . '"/>';
    echo '<input type="hidden" name="effective_date" value="' . esc_attr($effective_date) . '"/>';
    echo '<button type="submit" name="cpp_generate_pdf" value="1">Generate PDF</button>';
    echo '</form>';
}

// 5. PDF Generation
function cpp_generate_pdf($data)
{
    $dompdf = new Dompdf();

    // Build HTML template (in production, you'd probably include a separate template file)
    $html = '<h1>Cafeteria Plan</h1>';
    $html .= '<p>Company: ' . esc_html($data['company_name']) . '</p>';
    $html .= '<p>Effective Date: ' . esc_html($data['effective_date']) . '</p>';
    // Add more dynamic sections as needed

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the PDF to browser:
    $dompdf->stream("cafeteria-plan.pdf", array("Attachment" => 0));
}

// 6. Capture PDF generation request
function cpp_maybe_generate_pdf()
{
    if (isset($_POST['cpp_generate_pdf']) && $_POST['cpp_generate_pdf'] == 1) {
        $company_name = sanitize_text_field($_POST['company_name'] ?? '');
        $effective_date = sanitize_text_field($_POST['effective_date'] ?? '');

        // pass data to our function
        cpp_generate_pdf(array(
            'company_name' => $company_name,
            'effective_date' => $effective_date
        ));
        exit; // Important to stop WP from rendering the rest of the page
    }
}
add_action('init', 'cpp_maybe_generate_pdf');








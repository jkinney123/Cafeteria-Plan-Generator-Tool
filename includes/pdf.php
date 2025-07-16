<?php
if (!defined('ABSPATH'))
    exit;

use Dompdf\Dompdf;

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

    update_post_meta($caf_plan_id, '_cpp_status', 'Finalized');
    update_post_meta($caf_plan_id, '_cpp_last_edited', current_time('mysql'));

    $template_version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
    $template_data = cpp_get_template_versions();
    $html = cpp_build_full_doc_html($caf_plan_id, $template_data, $template_version, false); // false = not redline

    error_log('DEBUG: HTML for PDF => ' . $html);

    try {
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
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

function cpp_build_full_doc_html($plan_id, $template_data, $version, $redline = false, $old_version = null, $is_preview = false)
{
    // Fetch demographic tokens
    $company_name = esc_html(get_post_meta($plan_id, '_cpp_company_name', true));
    $effective_date = esc_html(get_post_meta($plan_id, '_cpp_effective_date', true));
    $plan_options_selected_str = get_post_meta($plan_id, '_cpp_plan_options', true);
    $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));

    $preview_css = '';
    if ($is_preview) {
        $preview_css = '
        .pagedjs-preview {
    background: #ddd;
    padding: 32px 0;
    min-height: 100vh;
    overflow-x: auto;
}
.pagedjs_page {
    background: #fff;
    margin: 0 auto 32px auto;
    box-shadow: 0 0 12px 2px rgba(0,0,0,0.13);
    border-radius: 6px;
    width: 816px;
    min-height: 1056px;
    position: relative;
    overflow: hidden;
    display: block;
}
.pagedjs_page .pagedjs_area {
    padding: 72pt 54pt 72pt 54pt; /* Top, Right, Bottom, Left */
}

        .pdf-preview-wrapper {
            background: #fff;
            box-shadow: 0 0 12px 2px rgba(0,0,0,0.10);
            padding: 72pt;
            margin: 40px auto;
            max-width: 816px;
            min-height: 1056px;
            border-radius: 4px;
            position: relative;
        }';
    } else {
        $preview_css = '
        .pdf-preview-wrapper {
            background: none;
            box-shadow: none;
            padding: 0;
            margin: 0;
            max-width: none;
            min-width: 0;
        }';
    }

    $html = '
    <style>
    @page { margin: 72pt; }
    ' . $preview_css . '
    .pdf-preview-wrapper {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt;
        line-height: 1.5;
        color: #000;
    }
    .pdf-preview-wrapper h1,
    .pdf-preview-wrapper h2,
    .pdf-preview-wrapper h3 {
        font-family: "Times New Roman", Times, serif;
        font-weight: bold;
        text-align: center;
        margin-top: 24pt;
        margin-bottom: 12pt;
    }
    .pdf-preview-wrapper h1 { font-size: 18pt; text-transform: uppercase; }
    .pdf-preview-wrapper h2 { font-size: 16pt; }
    .pdf-preview-wrapper h3 { font-size: 14pt; }
    .pdf-preview-wrapper p { margin: 0 0 12pt 0; }
    .pdf-preview-wrapper .intro-page { page-break-after: always; margin-bottom: 120pt; }
    .pdf-preview-wrapper .intro-page div { margin-top: 12pt; }
    .pdf-preview-wrapper .footer-area { margin-top: 40pt; text-align: center; font-size: 11pt; color: #333; }
    </style>
    ';


    $html .= '<div class="pdf-preview-wrapper">';
    // Cover page
    $html .= '<div class="intro-page">' . cpp_build_intro_header($company_name, $effective_date, $plan_options_selected) . '</div>';

    $blocks = $template_data[$version]['components'] ?? [];
    $old_blocks = $old_version ? ($template_data[$old_version]['components'] ?? []) : [];

    // Main content
    foreach ($plan_options_selected as $option) {
        $option = trim($option);
        if (!$redline) {
            if (isset($blocks[$option])) {
                $html .= $blocks[$option];
            }
        } else {
            $old = isset($old_blocks[$option]) ? $old_blocks[$option] : '';
            $new = isset($blocks[$option]) ? $blocks[$option] : '';
            $html .= cpp_redline_template_regions_dmp($old, $new);
        }
    }

    $html .= '<p style="text-align:right; font-size:10pt;"><em>Template Version: ' . esc_html($version) . '</em></p>';
    $html .= '<div class="footer-area"><p>&copy; ' . date('Y') . '  Kinney Law & Compliance. All rights reserved.</p></div>';

    $html .= '</div>'; // Close pdf-preview-wrapper
    // Always replace tokens last
    $html = cpp_replace_tokens($html, $plan_id);

    return $html;
}

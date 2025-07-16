<?php
if (!defined('ABSPATH'))
    exit;

function cpp_replace_tokens($html, $plan_id)
{
    // Fetch all needed meta
    $company_name = esc_html(get_post_meta($plan_id, '_cpp_company_name', true));
    $effective_date = esc_html(get_post_meta($plan_id, '_cpp_effective_date', true));
    // Add more tokens here as needed

    // Replace tokens in the HTML
    $html = str_replace('{{company_name}}', $company_name, $html);
    $html = str_replace('{{effective_date}}', $effective_date, $html);
    // Add more replacements as needed

    return $html;
}

function cpp_sentence_split($text)
{
    // Basic sentence split (imperfect, but handles most English)
    $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z])/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return $sentences;
}
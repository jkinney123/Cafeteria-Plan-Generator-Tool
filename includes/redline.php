<?php
if (!defined('ABSPATH'))
    exit;

use DiffMatchPatch\DiffMatchPatch;
use Jfcherng\Diff\DiffHelper;


function cpp_dmp_word_diff($old, $new)
{
    $dmp = new DiffMatchPatch();

    // Tokenize by word (preserve spaces/punctuation)
    $old_words = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE);
    $new_words = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE);
    $old_joined = implode("\t", $old_words);
    $new_joined = implode("\t", $new_words);

    // Run diff
    $diffs = $dmp->diff_main($old_joined, $new_joined);
    $dmp->diff_cleanupSemantic($diffs);

    // Process diffs to wrap in <ins> and <del>
    $html = '';
    foreach ($diffs as $part) {
        list($op, $data) = $part;
        $data = str_replace("\t", '', $data); // reassemble word chunks
        if ($op === $dmp::DIFF_INSERT) {
            $html .= '<ins style="background:#eaffea;">' . htmlspecialchars($data) . '</ins>';
        } elseif ($op === $dmp::DIFF_DELETE) {
            $html .= '<del style="background:#ffecec;">' . htmlspecialchars($data) . '</del>';
        } else {
            $html .= htmlspecialchars($data);
        }
    }
    return $html;
}
function cpp_redline_template_regions_dmp($old_html, $new_html)
{
    // Load both old and new into DOMs
    $old_doc = new DOMDocument();
    $new_doc = new DOMDocument();
    @$old_doc->loadHTML('<?xml encoding="utf-8" ?>' . $old_html);
    @$new_doc->loadHTML('<?xml encoding="utf-8" ?>' . $new_html);

    $xpath_old = new DOMXPath($old_doc);
    $xpath_new = new DOMXPath($new_doc);

    // Build associative array of old blocks by key
    $old_spans = [];
    foreach ($xpath_old->query('//span[contains(@class,"cpp-template")]') as $span) {
        $key = $span->getAttribute('data-key');
        $old_spans[$key] = $span->nodeValue;
    }

    // Replace new spans with redline diffs
    foreach ($xpath_new->query('//span[contains(@class,"cpp-template")]') as $span) {
        $key = $span->getAttribute('data-key');
        $old = isset($old_spans[$key]) ? $old_spans[$key] : '';
        $new = $span->nodeValue;

        // Generate word-level diff using your cpp_dmp_word_diff function
        $diff = cpp_dmp_word_diff($old, $new);

        // Replace all child nodes with the redline HTML
        while ($span->firstChild) {
            $span->removeChild($span->firstChild);
        }
        // Use a dummy DOM to convert diff HTML string into DOM nodes
        $tmp = new DOMDocument();
        @$tmp->loadHTML('<?xml encoding="utf-8" ?><span>' . $diff . '</span>');
        foreach ($tmp->getElementsByTagName('span')->item(0)->childNodes as $child) {
            $span->appendChild($new_doc->importNode($child, true));
        }
    }

    // Extract just the body’s innerHTML (to skip <html><body>)
    $body = $new_doc->getElementsByTagName('body')->item(0);
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $new_doc->saveHTML($child);
    }
    return $out;
}

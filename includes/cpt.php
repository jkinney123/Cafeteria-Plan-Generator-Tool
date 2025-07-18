<?php
if (!defined('ABSPATH'))
    exit;

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

<?php
if (!defined('ABSPATH'))
    exit;

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


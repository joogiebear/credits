<?php
/**
 * Credits - Inventory Module
 *
 * Allows users to view all purchased items and activate/deactivate them.
 * Supports toggling between owned items of the same type without losing them.
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

/**
 * Render the inventory page showing all user purchases grouped by type.
 *
 * @return string Evaluated template HTML
 */
function credits_page_inventory(): void
{
    global $mybb, $db, $templates, $lang, $theme, $header, $headerinclude, $footer, $credits_base_url, $credits_inventory_url;

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    if ($mybb->settings['credits_enabled'] != 1) {
        error_no_permission();
    }

    $lang->load('credits');

    // Load credits frontend JS (CSS is loaded globally in credits_global_start)
    $headerinclude .= "\n" . '<script type="text/javascript" src="' . $mybb->asset_url . '/jscripts/credits.js"></script>';

    $uid = (int)$mybb->user['uid'];

    add_breadcrumb($lang->credits, credits_url('credits'));
    add_breadcrumb($lang->credits_inventory, credits_url('inventory'));

    // Fetch ALL purchases for this user
    $query = $db->query("
        SELECT p.pid, p.iid, p.value, p.dateline, p.expires, p.active,
               s.name AS item_name, s.type AS item_type, s.data AS item_data,
               s.description AS item_description
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.uid = '{$uid}'
        ORDER BY s.type ASC, p.dateline DESC
    ");

    $items_by_type = array();
    while ($row = $db->fetch_array($query)) {
        $type = $row['item_type'] ?? 'other';
        $items_by_type[$type][] = $row;
    }

    // Type display order, labels, icons, and empty messages
    $type_order = array(
        'custom_title', 'username_color', 'icon', 'award', 'booster',
        'postbit_bg', 'username_effect', 'usergroup', 'ad_space',
    );

    $type_labels = array(
        'custom_title'    => $lang->credits_inv_type_custom_title,
        'username_color'  => $lang->credits_inv_type_username_color,
        'icon'            => $lang->credits_inv_type_icon,
        'award'           => $lang->credits_inv_type_award,
        'booster'         => $lang->credits_inv_type_booster,
        'postbit_bg'      => $lang->credits_inv_type_postbit_bg,
        'username_effect' => $lang->credits_inv_type_username_effect,
        'usergroup'       => $lang->credits_inv_type_usergroup,
        'ad_space'        => $lang->credits_inv_type_ad_space,
    );

    $type_empty_messages = array(
        'custom_title'    => $lang->credits_inv_empty_custom_title,
        'username_color'  => $lang->credits_inv_empty_username_color,
        'icon'            => $lang->credits_inv_empty_icon,
        'award'           => $lang->credits_inv_empty_award,
        'booster'         => $lang->credits_inv_empty_booster,
        'postbit_bg'      => $lang->credits_inv_empty_postbit_bg,
        'username_effect' => $lang->credits_inv_empty_username_effect,
        'usergroup'       => $lang->credits_inv_empty_usergroup,
        'ad_space'        => $lang->credits_inv_empty_ad_space,
    );

    // If user owns nothing at all, show the global empty state
    if (empty($items_by_type)) {
        $inventory_content = '';
        eval('$inventory_content = "' . $templates->get('credits_inventory_empty') . '";');

        $credits_tabs = credits_build_inventory_tabs('inventory');
        $credits_content = $credits_tabs . $inventory_content;

        eval('$page = "' . $templates->get('credits_page') . '";');
        output_page($page);
        exit;
    }

    $inventory_type_sections = '';
    $inventory_sidebar_links = '';

    foreach ($type_order as $type) {
        $type_label = $type_labels[$type] ?? $type;
        $type_icon = '';
        $type_count = count($items_by_type[$type] ?? array());
        $type_items = '';

        // Skip types the user doesn't own any items for
        if (empty($items_by_type[$type])) {
            continue;
        }

        // Build sidebar link for this type
        $sidebar_cid = htmlspecialchars_uni($type);
        $sidebar_name = $type_label;
        eval('$inventory_sidebar_links .= "' . $templates->get('credits_shop_sidebar_link') . '";');

        foreach ($items_by_type[$type] as $purchase) {
            $item_type = htmlspecialchars_uni($type);

            // Item name
            $item_name = '';
            if (!empty($purchase['item_name'])) {
                $item_name = htmlspecialchars_uni($purchase['item_name']);
            } else {
                $item_name = credits_inventory_bonus_label($purchase['value']);
            }

            // Status
            $status = credits_inventory_item_status($purchase);
            switch ($status) {
                case 'active':
                    $status_class = 'credits_inv_active';
                    break;
                case 'expired':
                    $status_class = 'credits_inv_expired';
                    break;
                default:
                    $status_class = 'credits_inv_inactive';
                    break;
            }

            // Preview thumbnail
            $item_preview = credits_inventory_item_preview($purchase, $type);

            // Meta line (status + extra info)
            $item_meta = credits_inventory_item_meta($purchase, $type, $status);

            // Action icon buttons
            $item_actions = credits_inventory_item_actions($purchase, $type, $status);

            eval('$type_items .= "' . $templates->get('credits_inventory_item') . '";');
        }

        eval('$inventory_type_sections .= "' . $templates->get('credits_inventory_type_section') . '";');
    }

    $inventory_content = '';
    eval('$inventory_content = "' . $templates->get('credits_inventory_page') . '";');

    // Build tabs
    $credits_tabs = credits_build_inventory_tabs('inventory');
    $credits_content = $credits_tabs . $inventory_content;

    eval('$page = "' . $templates->get('credits_page') . '";');
    output_page($page);
    exit;
}

/**
 * Build tab navigation for use on the inventory page.
 *
 * @param string $active_view The currently active view
 * @return string Evaluated tabs HTML
 */
function credits_build_inventory_tabs(string $active_view): string
{
    global $mybb, $templates, $lang;

    $credits_base_url = credits_url('credits');
    $credits_inventory_url = credits_url('inventory');

    $tab_leaderboard = ($active_view == 'leaderboard') ? 'credits_tab_active' : 'credits_tab';
    $tab_log = ($active_view == 'log') ? 'credits_tab_active' : 'credits_tab';

    $shop_tab = '';
    if ($mybb->settings['credits_shop_enabled'] == 1) {
        $tab_shop = ($active_view == 'shop') ? 'credits_tab_active' : 'credits_tab';
        eval('$shop_tab = "' . $templates->get('credits_tab_shop') . '";');
    }

    $gift_tab = '';

    $achievements_tab = '';
    if (!empty($mybb->settings['credits_achievements_enabled']) && $mybb->settings['credits_achievements_enabled'] == 1) {
        $tab_achievements = ($active_view == 'achievements') ? 'credits_tab_active' : 'credits_tab';
        eval('$achievements_tab = "' . $templates->get('credits_tab_achievements') . '";');
    }

    $lottery_tab = '';
    if (!empty($mybb->settings['credits_lottery_enabled']) && $mybb->settings['credits_lottery_enabled'] == 1) {
        $tab_lottery = ($active_view == 'lottery') ? 'credits_tab_active' : 'credits_tab';
        eval('$lottery_tab = "' . $templates->get('credits_tab_lottery') . '";');
    }

    $referral_tab = '';
    if (!empty($mybb->settings['credits_referral_enabled']) && $mybb->settings['credits_referral_enabled'] == 1) {
        $tab_referrals = ($active_view == 'referrals') ? 'credits_tab_active' : 'credits_tab';
        eval('$referral_tab = "' . $templates->get('credits_tab_referrals') . '";');
    }

    $inventory_tab = '';
    $tab_inventory = ($active_view == 'inventory') ? 'credits_tab_active' : 'credits_tab';
    eval('$inventory_tab = "' . $templates->get('credits_tab_inventory') . '";');

    $credits_tabs = '';
    eval('$credits_tabs = "' . $templates->get('credits_tabs') . '";');
    return $credits_tabs;
}

/**
 * Determine the status of an inventory item.
 *
 * @param array $purchase Purchase row data
 * @return string 'active', 'inactive', or 'expired'
 */
function credits_inventory_item_status(array $purchase): string
{
    if ((int)$purchase['expires'] > 0 && (int)$purchase['expires'] < TIME_NOW) {
        return 'expired';
    }
    if ((int)$purchase['active'] == 1) {
        return 'active';
    }
    return 'inactive';
}

/**
 * Generate a human-readable label for bonus/achievement/referral boosters.
 *
 * @param string $value The purchase value field
 * @return string Label
 */
function credits_inventory_bonus_label(string $value): string
{
    global $lang;

    if ($value === 'bonus_booster') {
        return $lang->credits_inv_bonus_booster ?? 'Bonus Booster';
    }
    if (strpos($value, 'achievement_booster:') === 0) {
        $mult = (int)substr($value, strlen('achievement_booster:'));
        return ($lang->credits_inv_achievement_booster ?? 'Achievement Booster') . ' (' . $mult . 'x)';
    }
    if (strpos($value, 'referral_booster:') === 0) {
        $mult = (int)substr($value, strlen('referral_booster:'));
        return ($lang->credits_inv_referral_booster ?? 'Referral Booster') . ' (' . $mult . 'x)';
    }

    return htmlspecialchars_uni($value);
}

/**
 * Generate a preview thumbnail for an inventory item.
 *
 * @param array  $purchase Purchase row
 * @param string $type     Item type
 * @return string HTML preview content
 */
function credits_inventory_item_preview(array $purchase, string $type): string
{
    switch ($type) {
        case 'custom_title':
            return '&#9998;';

        case 'username_color':
            $color = htmlspecialchars_uni($purchase['value']);
            return '<div style="width:28px; height:28px; border-radius:50%; background:' . $color . ';"></div>';

        case 'icon':
            if (!empty($purchase['value'])) {
                $src = htmlspecialchars_uni($purchase['value']);
                return '<img src="' . $src . '" alt="" />';
            }
            return '&#11088;';

        case 'award':
            if (!empty($purchase['item_data'])) {
                $data = json_decode($purchase['item_data'], true);
                if (!empty($data['image'])) {
                    $src = htmlspecialchars_uni($data['image']);
                    return '<img src="' . $src . '" alt="" />';
                }
            }
            return '&#127942;';

        case 'booster':
            return '&#9889;';

        case 'postbit_bg':
            return '<div style="width:28px; height:28px; border-radius:4px; border:1px solid #ccc; ' . credits_build_bg_css($purchase['value']) . '"></div>';

        case 'username_effect':
            $effect = htmlspecialchars_uni($purchase['value']);
            return '<span class="credits_fx_' . $effect . '"><a href="javascript:void(0);">&#10024;</a></span>';

        case 'usergroup':
            return '&#128101;';

        case 'ad_space':
            return '&#128227;';

        default:
            return '&#128230;';
    }
}

/**
 * Generate the meta line (status + details) for an inventory item.
 *
 * @param array  $purchase Purchase row
 * @param string $type     Item type
 * @param string $status   'active', 'inactive', or 'expired'
 * @return string HTML meta info
 */
function credits_inventory_item_meta(array $purchase, string $type, string $status): string
{
    global $lang, $db;

    $parts = array();

    // Status badge
    switch ($status) {
        case 'active':
            $parts[] = '<span style="color:#28a745;">&#9679; ' . $lang->credits_inv_active . '</span>';
            break;
        case 'expired':
            $parts[] = '<span style="color:#dc3545;">&#9679; ' . $lang->credits_inv_expired . '</span>';
            break;
        default:
            $parts[] = '<span style="color:#999;">&#9675; ' . $lang->credits_inv_inactive . '</span>';
            break;
    }

    // Type-specific details
    switch ($type) {
        case 'custom_title':
            $parts[] = '"' . htmlspecialchars_uni($purchase['value']) . '"';
            break;

        case 'username_color':
            $parts[] = htmlspecialchars_uni($purchase['value']);
            break;

        case 'booster':
            if (!empty($purchase['item_data'])) {
                $data = json_decode($purchase['item_data'], true);
                $mult = (int)($data['multiplier'] ?? 0);
                if ($mult > 0) {
                    $parts[] = $mult . 'x';
                }
                if ($status === 'active' && (int)$purchase['expires'] > TIME_NOW) {
                    $remaining = (int)$purchase['expires'] - TIME_NOW;
                    $parts[] = credits_format_duration($remaining) . ' ' . $lang->credits_inv_remaining;
                } elseif ($status === 'inactive' && !empty($data['duration'])) {
                    $parts[] = credits_format_duration((int)$data['duration']);
                }
            } else {
                $parts[] = credits_inventory_bonus_label($purchase['value']);
            }
            break;

        case 'username_effect':
            $parts[] = credits_effect_label($purchase['value']);
            break;

        case 'usergroup':
            if (!empty($purchase['value'])) {
                $gid = (int)$purchase['value'];
                $query = $db->simple_select('usergroups', 'title', "gid = '{$gid}'");
                $title = $db->fetch_field($query, 'title');
                if ($title) {
                    $parts[] = htmlspecialchars_uni($title);
                }
                if ((int)$purchase['expires'] > 0 && (int)$purchase['expires'] > TIME_NOW) {
                    $remaining = (int)$purchase['expires'] - TIME_NOW;
                    $parts[] = credits_format_duration($remaining) . ' ' . $lang->credits_inv_remaining;
                } elseif ((int)$purchase['expires'] == 0) {
                    $parts[] = $lang->credits_usergroup_permanent;
                }
            }
            break;

        case 'ad_space':
            $parts[] = htmlspecialchars_uni($purchase['value']);
            if ((int)$purchase['expires'] > 0 && (int)$purchase['expires'] > TIME_NOW) {
                $remaining = (int)$purchase['expires'] - TIME_NOW;
                $parts[] = credits_format_duration($remaining) . ' ' . $lang->credits_inv_remaining;
            }
            break;
    }

    return implode(' &middot; ', $parts);
}

/**
 * Generate action icon buttons for an inventory item.
 *
 * @param array  $purchase Purchase row
 * @param string $type     Item type
 * @param string $status   'active', 'inactive', or 'expired'
 * @return string HTML icon buttons
 */
function credits_inventory_item_actions(array $purchase, string $type, string $status): string
{
    global $mybb, $lang;

    $pid = (int)$purchase['pid'];
    $postkey = $mybb->post_code;
    $buttons = '';

    // No actions for expired items
    if ($status === 'expired') {
        return '';
    }

    // Display-only types
    if (in_array($type, array('usergroup', 'ad_space'))) {
        return '';
    }

    // Bonus boosters (iid = 0) that are not from the shop cannot be re-activated
    if ((int)$purchase['iid'] == 0 && $type !== 'award') {
        if ($status === 'active') {
            return '<button class="credits_inv_btn credits_inv_btn_on credits_inv_action" data-pid="' . $pid . '" data-action="deactivate" data-postkey="' . $postkey . '" title="' . $lang->credits_inv_deactivate . '">&#10003;</button>';
        }
        return '';
    }

    // Toggle button (activate/deactivate)
    if ($status === 'active') {
        $buttons .= '<button class="credits_inv_btn credits_inv_btn_on credits_inv_action" data-pid="' . $pid . '" data-action="deactivate" data-postkey="' . $postkey . '" title="' . $lang->credits_inv_deactivate . '">&#10003;</button>';
    } else {
        // For boosters: check if another booster is already active and add confirm prompt
        $confirm = '';
        if ($type === 'booster') {
            $existing = credits_get_active_booster($mybb->user['uid']);
            if ($existing) {
                $confirm = ' data-confirm="' . htmlspecialchars_uni($lang->credits_inv_booster_confirm) . '"';
            }
        }
        $buttons .= '<button class="credits_inv_btn credits_inv_btn_off credits_inv_action" data-pid="' . $pid . '" data-action="activate" data-postkey="' . $postkey . '"' . $confirm . ' title="' . $lang->credits_inv_activate . '">&#9654;</button>';
    }

    // Edit button for custom_title and username_color (when active)
    if ($status === 'active' && in_array($type, array('custom_title', 'username_color'))) {
        $edit_type = ($type === 'username_color') ? 'color' : 'text';
        $buttons .= ' <button class="credits_inv_btn credits_inv_btn_edit credits_inv_edit" data-pid="' . $pid . '" data-postkey="' . $postkey . '" data-edit-type="' . $edit_type . '" data-current="' . htmlspecialchars_uni($purchase['value']) . '" title="' . $lang->credits_inv_edit . '">&#9998;</button>';
    }

    return $buttons;
}

/**
 * Activate a purchase from the inventory.
 *
 * @param int $pid Purchase ID
 * @return array Result with 'success' and optionally 'error'
 */
function credits_inventory_activate(int $pid): array
{
    global $db, $mybb, $lang;

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        return array('success' => false, 'error' => 'Not logged in.');
    }

    // Fetch the purchase with shop data
    $query = $db->query("
        SELECT p.*, s.type, s.data
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.pid = '{$pid}' AND p.uid = '{$uid}'
    ");
    $purchase = $db->fetch_array($query);

    if (!$purchase) {
        return array('success' => false, 'error' => $lang->credits_inv_not_found ?? 'Purchase not found.');
    }

    $type = $purchase['type'];

    // Block non-toggleable types
    if (in_array($type, array('usergroup', 'ad_space'))) {
        return array('success' => false, 'error' => $lang->credits_inv_cannot_toggle ?? 'This item cannot be toggled.');
    }

    // Block expired items
    if ((int)$purchase['expires'] > 0 && (int)$purchase['expires'] < TIME_NOW) {
        return array('success' => false, 'error' => $lang->credits_inv_item_expired ?? 'This item has expired.');
    }

    // Block bonus boosters (iid = 0) from re-activation
    if ((int)$purchase['iid'] == 0 && $type !== 'award') {
        return array('success' => false, 'error' => $lang->credits_inv_cannot_toggle ?? 'This item cannot be toggled.');
    }

    // For non-stackable types, deactivate current active item of same type
    if ($type !== 'award') {
        $db->write_query("
            UPDATE " . TABLE_PREFIX . "credits_purchases p
            INNER JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
            SET p.active = 0
            WHERE p.uid = '{$uid}'
              AND p.active = '1'
              AND s.type = '" . $db->escape_string($type) . "'
        ");

        // Also deactivate bonus boosters if activating a booster
        if ($type === 'booster') {
            $db->update_query('credits_purchases',
                array('active' => 0),
                "uid = '{$uid}' AND active = '1' AND (value = 'bonus_booster' OR value LIKE 'achievement_booster:%' OR value LIKE 'referral_booster:%')"
            );
        }
    }

    // For boosters: reset the expires timer
    if ($type === 'booster') {
        $item_data = json_decode($purchase['data'], true);
        $duration = (int)($item_data['duration'] ?? 3600);
        $new_expires = TIME_NOW + $duration;

        $db->update_query('credits_purchases', array(
            'active'  => 1,
            'expires' => $new_expires,
        ), "pid = '{$pid}'");
    } else {
        $db->update_query('credits_purchases', array('active' => 1), "pid = '{$pid}'");
    }

    // Apply the purchase effect to user columns
    require_once CREDITS_PLUGIN_PATH . 'shop.php';
    credits_apply_purchase($uid, $type, $purchase['value']);

    return array('success' => true, 'status' => 'active');
}

/**
 * Deactivate a purchase from the inventory.
 *
 * @param int $pid Purchase ID
 * @return array Result with 'success' and optionally 'error'
 */
function credits_inventory_deactivate(int $pid): array
{
    global $db, $mybb, $lang;

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        return array('success' => false, 'error' => 'Not logged in.');
    }

    // Fetch the purchase
    $query = $db->query("
        SELECT p.*, s.type
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.pid = '{$pid}' AND p.uid = '{$uid}'
    ");
    $purchase = $db->fetch_array($query);

    if (!$purchase) {
        return array('success' => false, 'error' => $lang->credits_inv_not_found ?? 'Purchase not found.');
    }

    $type = $purchase['type'];

    // Block non-toggleable types
    if (in_array($type, array('usergroup', 'ad_space'))) {
        return array('success' => false, 'error' => $lang->credits_inv_cannot_toggle ?? 'This item cannot be toggled.');
    }

    // Deactivate this purchase
    $db->update_query('credits_purchases', array('active' => 0), "pid = '{$pid}'");

    // Clear the user column back to default
    credits_clear_purchase($uid, $type);

    return array('success' => true, 'status' => 'inactive');
}

/**
 * Edit the value of an active purchase (custom title or username color).
 *
 * @param int    $pid       Purchase ID
 * @param string $new_value New value to set
 * @return array Result with 'success' and optionally 'error'
 */
function credits_inventory_edit(int $pid, string $new_value): array
{
    global $db, $mybb, $lang;

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        return array('success' => false, 'error' => 'Not logged in.');
    }

    // Fetch the purchase with shop data
    $query = $db->query("
        SELECT p.*, s.type
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.pid = '{$pid}' AND p.uid = '{$uid}'
    ");
    $purchase = $db->fetch_array($query);

    if (!$purchase) {
        return array('success' => false, 'error' => $lang->credits_inv_not_found ?? 'Purchase not found.');
    }

    // Only allow editing custom_title and username_color
    $type = $purchase['type'];
    if (!in_array($type, array('custom_title', 'username_color'))) {
        return array('success' => false, 'error' => $lang->credits_inv_cannot_edit ?? 'This item cannot be edited.');
    }

    // Must be active to edit
    if ((int)$purchase['active'] != 1) {
        return array('success' => false, 'error' => $lang->credits_inv_must_be_active ?? 'Activate this item first.');
    }

    // Validate the new value
    $new_value = trim($new_value);
    if ($type === 'custom_title') {
        $new_value = htmlspecialchars_uni($new_value);
        if (empty($new_value)) {
            return array('success' => false, 'error' => $lang->credits_enter_title_error ?? 'Please enter a title.');
        }
        if (my_strlen($new_value) > 64) {
            return array('success' => false, 'error' => $lang->credits_title_too_long ?? 'Title is too long.');
        }
    } elseif ($type === 'username_color') {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $new_value)) {
            return array('success' => false, 'error' => $lang->credits_invalid_color ?? 'Invalid color.');
        }
    }

    // Update the purchase value
    $db->update_query('credits_purchases', array(
        'value' => $db->escape_string($new_value),
    ), "pid = '{$pid}'");

    // Apply the updated value to user columns
    require_once CREDITS_PLUGIN_PATH . 'shop.php';
    credits_apply_purchase($uid, $type, $new_value);

    return array('success' => true);
}

/**
 * Clear a purchase effect from user columns (reverse of credits_apply_purchase).
 *
 * @param int    $uid  User ID
 * @param string $type Item type
 */
function credits_clear_purchase(int $uid, string $type): void
{
    global $db;

    switch ($type) {
        case 'custom_title':
            // Setting to empty causes MyBB to fall back to group default title
            $db->update_query('users', array('usertitle' => ''), "uid = '{$uid}'");
            break;

        case 'username_color':
            $db->update_query('users', array('credits_username_color' => ''), "uid = '{$uid}'");
            break;

        case 'icon':
            $db->update_query('users', array('credits_icon' => ''), "uid = '{$uid}'");
            break;

        case 'award':
            // Rebuild awards JSON from remaining active purchases
            credits_rebuild_user_awards($uid);
            break;

        case 'booster':
            // Nothing to clear - booster is checked via active purchase query
            break;

        case 'postbit_bg':
            $db->update_query('users', array('credits_postbit_bg' => ''), "uid = '{$uid}'");
            break;

        case 'username_effect':
            $db->update_query('users', array('credits_username_effect' => ''), "uid = '{$uid}'");
            break;
    }
}

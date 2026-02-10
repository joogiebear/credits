<?php
/**
 * Credits - Shop Module
 *
 * Handles shop listing by category, purchase forms, purchase processing,
 * and applying purchases (custom title, username color, icons, awards,
 * boosters, postbit backgrounds, username effects).
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

/**
 * Display the shop item listing grouped by category.
 *
 * @return string Evaluated template HTML
 */
function credits_shop_listing(): string
{
    global $mybb, $db, $templates, $lang, $theme, $credits_base_url;

    // Fetch active and visible categories (visible=1 shows in shop; visible=0 is unlisted)
    $categories = array();
    $query = $db->simple_select('credits_categories', '*', "active = '1' AND visible = '1'", array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));
    while ($cat = $db->fetch_array($query)) {
        $categories[$cat['cid']] = $cat;
    }

    // Fetch all active items
    $items_by_category = array();
    $query = $db->simple_select('credits_shop', '*', "active = '1'", array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));
    while ($item = $db->fetch_array($query)) {
        $cid = (int)$item['cid'];
        $items_by_category[$cid][] = $item;
    }

    $shop_categories = '';
    $shop_sidebar_links = '';
    $has_items = false;

    // Render credit packs section at the top of the shop (if any payment gateway is enabled)
    if (($mybb->settings['credits_coinbase_enabled'] == 1 || $mybb->settings['credits_lemonsqueezy_enabled'] == 1) && $mybb->user['uid'] > 0) {
        $pack_query = $db->simple_select('credits_packs', '*', "active = '1'", array(
            'order_by'  => 'disporder',
            'order_dir' => 'ASC',
        ));

        if ($db->num_rows($pack_query) > 0) {
            $pack_rows = '';
            while ($pack = $db->fetch_array($pack_query)) {
                $alt_bg = alt_trow();
                $pack['name'] = htmlspecialchars_uni($pack['name']);
                $pack['credits'] = my_number_format($pack['credits']);
                $pack['price_usd'] = number_format((float)$pack['price_usd'], 2);
                $pack_price_display = '$' . $pack['price_usd'];
                $pack_id = (int)$pack['pack_id'];

                $pack_buy_buttons = '<a href="' . credits_url('credits', array('view' => 'shop', 'purchase_pack' => $pack_id)) . '">' . $lang->credits_purchase . '</a>';

                eval('$pack_rows .= "' . $templates->get('credits_packs_item') . '";');
            }

            // Sidebar link for packs
            $sidebar_cid = 'packs';
            $sidebar_name = $lang->credits_packs_title;
            eval('$shop_sidebar_links .= "' . $templates->get('credits_shop_sidebar_link') . '";');

            eval('$shop_categories .= "' . $templates->get('credits_packs') . '";');
            $has_items = true;
        }
    }

    // Render each category that has items
    foreach ($categories as $cid => $cat) {
        if (empty($items_by_category[$cid])) {
            continue;
        }

        $has_items = true;
        $category_name = htmlspecialchars_uni($cat['name']);
        $category_items = '';

        // Sidebar link for this category
        $sidebar_cid = (int)$cid;
        $sidebar_name = $category_name;
        eval('$shop_sidebar_links .= "' . $templates->get('credits_shop_sidebar_link') . '";');

        foreach ($items_by_category[$cid] as $item) {
            $alt_bg = alt_trow();
            $iid = (int)$item['iid'];
            $item['name'] = htmlspecialchars_uni($item['name']);
            $item['description'] = htmlspecialchars_uni($item['description']);

            // Build smart price display
            $credit_price_raw = (int)$item['price'];
            $usd_price = (float)($item['price_usd'] ?? 0);
            $item['price'] = my_number_format($item['price']);

            if ($credit_price_raw > 0 && $usd_price > 0) {
                $item_price_display = $item['price'] . ' ' . $lang->credits . ' / $' . number_format($usd_price, 2);
            } elseif ($usd_price > 0) {
                $item_price_display = '$' . number_format($usd_price, 2);
            } else {
                $item_price_display = $item['price'] . ' ' . $lang->credits;
            }

            // Show icon preview in item name for icon type items
            $item_icon_preview = '';
            if ($item['type'] == 'icon' || $item['type'] == 'award') {
                $item_data = json_decode($item['data'], true);
                if (!empty($item_data['image'])) {
                    $icon_src = htmlspecialchars_uni($item_data['image']);
                    $item_icon_preview = '<img src="' . $icon_src . '" alt="" style="vertical-align: middle; max-height: 16px; max-width: 16px; margin-right: 4px;" />';
                }
            }

            // Stock display
            $item_stock = (int)($item['stock'] ?? -1);
            if ($item_stock == 0) {
                $item['name'] .= ' <span style="color: #c00; font-size: 10px;">(' . $lang->credits_out_of_stock . ')</span>';
            } elseif ($item_stock > 0) {
                $item['name'] .= ' <span style="color: #666; font-size: 10px;">(' . $lang->sprintf($lang->credits_stock_remaining, $item_stock) . ')</span>';
            }

            $item_buy_money = '';

            eval('$category_items .= "' . $templates->get('credits_shop_item') . '";');
        }

        eval('$shop_categories .= "' . $templates->get('credits_shop_category') . '";');
    }

    // Render uncategorized items (cid = 0)
    if (!empty($items_by_category[0])) {
        $has_items = true;
        $cid = 0;
        $category_name = $lang->credits_uncategorized;
        $category_items = '';

        // Sidebar link for uncategorized
        $sidebar_cid = 0;
        $sidebar_name = $category_name;
        eval('$shop_sidebar_links .= "' . $templates->get('credits_shop_sidebar_link') . '";');

        foreach ($items_by_category[0] as $item) {
            $alt_bg = alt_trow();
            $iid = (int)$item['iid'];
            $item['name'] = htmlspecialchars_uni($item['name']);
            $item['description'] = htmlspecialchars_uni($item['description']);

            // Build smart price display
            $credit_price_raw = (int)$item['price'];
            $usd_price = (float)($item['price_usd'] ?? 0);
            $item['price'] = my_number_format($item['price']);

            if ($credit_price_raw > 0 && $usd_price > 0) {
                $item_price_display = $item['price'] . ' ' . $lang->credits . ' / $' . number_format($usd_price, 2);
            } elseif ($usd_price > 0) {
                $item_price_display = '$' . number_format($usd_price, 2);
            } else {
                $item_price_display = $item['price'] . ' ' . $lang->credits;
            }

            $item_icon_preview = '';

            if ($item['type'] == 'icon' || $item['type'] == 'award') {
                $item_data = json_decode($item['data'], true);
                if (!empty($item_data['image'])) {
                    $icon_src = htmlspecialchars_uni($item_data['image']);
                    $item_icon_preview = '<img src="' . $icon_src . '" alt="" style="vertical-align: middle; max-height: 16px; max-width: 16px; margin-right: 4px;" />';
                }
            }

            // Stock display
            $item_stock = (int)($item['stock'] ?? -1);
            if ($item_stock == 0) {
                $item['name'] .= ' <span style="color: #c00; font-size: 10px;">(' . $lang->credits_out_of_stock . ')</span>';
            } elseif ($item_stock > 0) {
                $item['name'] .= ' <span style="color: #666; font-size: 10px;">(' . $lang->sprintf($lang->credits_stock_remaining, $item_stock) . ')</span>';
            }

            $item_buy_money = '';

            eval('$category_items .= "' . $templates->get('credits_shop_item') . '";');
        }

        eval('$shop_categories .= "' . $templates->get('credits_shop_category') . '";');
    }

    if (!$has_items) {
        eval('$shop_categories = "' . $templates->get('credits_shop_empty') . '";');
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_shop') . '";');
    return $output;
}

/**
 * Display the pack purchase confirmation form.
 *
 * @param int $pack_id Credit pack ID
 * @return string Evaluated template HTML
 */
function credits_pack_purchase_form(int $pack_id): string
{
    global $mybb, $db, $templates, $lang, $theme, $credits_base_url;

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    $query = $db->simple_select('credits_packs', '*', "pack_id = '{$pack_id}' AND active = '1'");
    $pack = $db->fetch_array($query);

    if (!$pack) {
        error($lang->credits_pack_not_found);
    }

    $pack['name'] = htmlspecialchars_uni($pack['name']);
    $pack['credits'] = my_number_format($pack['credits']);
    $pack['price_usd'] = number_format((float)$pack['price_usd'], 2);
    $pack_price_display = '$' . $pack['price_usd'];

    // Build gateway options
    $gateway_options = '';
    if ($mybb->settings['credits_coinbase_enabled'] == 1) {
        $gateway_options .= '<label><input type="radio" name="gateway" value="coinbase" checked="checked" /> ' . $lang->credits_pay_coinbase . '</label><br />';
    }
    if ($mybb->settings['credits_lemonsqueezy_enabled'] == 1) {
        $checked = ($mybb->settings['credits_coinbase_enabled'] != 1) ? ' checked="checked"' : '';
        $gateway_options .= '<label><input type="radio" name="gateway" value="lemonsqueezy"' . $checked . ' /> ' . $lang->credits_pay_lemonsqueezy . '</label>';
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_pack_purchase') . '";');
    return $output;
}

/**
 * Display the purchase confirmation form for a specific item.
 *
 * @param int $iid Shop item ID
 * @return string Evaluated template HTML
 */
function credits_shop_purchase_form(int $iid): string
{
    global $mybb, $db, $templates, $lang, $theme, $credits_base_url;

    $query = $db->simple_select('credits_shop', '*', "iid = '{$iid}' AND active = '1'");
    $item = $db->fetch_array($query);

    if (!$item) {
        error($lang->credits_item_not_found);
    }

    // Check stock availability
    if ((int)$item['stock'] == 0) {
        error($lang->credits_out_of_stock);
    }

    $item['name'] = htmlspecialchars_uni($item['name']);
    $item['description'] = htmlspecialchars_uni($item['description']);
    $credit_price_raw = (int)$item['price'];
    $item['price'] = my_number_format($item['price']);

    // Build type-specific input field
    $purchase_input = '';
    switch ($item['type']) {
        case 'custom_title':
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_title') . '";');
            break;

        case 'username_color':
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_color') . '";');
            break;

        case 'icon':
            $item_data = json_decode($item['data'], true);
            $item_image = htmlspecialchars_uni($item_data['image'] ?? '');
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_icon') . '";');
            break;

        case 'award':
            $item_data = json_decode($item['data'], true);
            $item_image = htmlspecialchars_uni($item_data['image'] ?? '');
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_award') . '";');
            break;

        case 'booster':
            $item_data = json_decode($item['data'], true);
            $booster_multiplier = (int)($item_data['multiplier'] ?? 2);
            $booster_duration_display = credits_format_duration((int)($item_data['duration'] ?? 3600));
            $booster_warning = '';

            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_booster') . '";');
            break;

        case 'postbit_bg':
            $item_data = json_decode($item['data'], true);
            $bg_type = $item_data['bg_type'] ?? 'color';
            $bg_value = $item_data['bg_value'] ?? '';
            $bg_preview_style = credits_build_bg_css_for_preview($bg_type, $bg_value);
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_postbit_bg') . '";');
            break;

        case 'username_effect':
            $item_data = json_decode($item['data'], true);
            $effect_name = htmlspecialchars_uni($item_data['effect'] ?? '');
            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_effect') . '";');
            break;

        case 'usergroup':
            $item_data = json_decode($item['data'], true);
            $gid = (int)($item_data['gid'] ?? 0);
            $duration = (int)($item_data['duration'] ?? 0);

            // Get usergroup name
            $query = $db->simple_select('usergroups', 'title', "gid = '{$gid}'");
            $usergroup_name = htmlspecialchars_uni($db->fetch_field($query, 'title') ?? 'Unknown');
            $usergroup_duration_display = $duration > 0 ? credits_format_duration($duration) : $lang->credits_usergroup_permanent;

            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_usergroup') . '";');

            // Show bonus info if configured
            $bonus_credits = (int)($item_data['bonus_credits'] ?? 0);
            $bonus_mult = (int)($item_data['bonus_booster_multiplier'] ?? 0);
            $bonus_dur = (int)($item_data['bonus_booster_duration'] ?? 0);

            if ($bonus_credits > 0) {
                $purchase_input .= '<tr><td class="trow1" width="40%"><strong>' . $lang->credits_ug_bonus_credits . ':</strong></td>'
                    . '<td class="trow1">+' . my_number_format($bonus_credits) . ' ' . $lang->credits . '</td></tr>';
            }
            if ($bonus_mult > 0 && $bonus_dur > 0) {
                $purchase_input .= '<tr><td class="trow2" width="40%"><strong>' . $lang->credits_ug_bonus_booster . ':</strong></td>'
                    . '<td class="trow2">' . $bonus_mult . 'x ' . $lang->credits_booster_multiplier . ' (' . credits_format_duration($bonus_dur) . ')</td></tr>';
            }

            // Check prerequisite groups
            $required_groups = $item_data['required_groups'] ?? array();
            if (!empty($required_groups) && is_array($required_groups)) {
                $user_groups = array((int)$mybb->user['usergroup']);
                if (!empty($mybb->user['additionalgroups'])) {
                    $user_groups = array_merge($user_groups, array_map('intval', explode(',', $mybb->user['additionalgroups'])));
                }

                $missing_groups = array_diff($required_groups, $user_groups);
                if (!empty($missing_groups)) {
                    // Get missing group names
                    $missing_names = array();
                    $gids_str = implode(',', array_map('intval', $missing_groups));
                    $gquery = $db->simple_select('usergroups', 'title', "gid IN ({$gids_str})");
                    while ($g = $db->fetch_array($gquery)) {
                        $missing_names[] = htmlspecialchars_uni($g['title']);
                    }
                    $purchase_input .= '<tr><td class="trow1" colspan="2" style="color: #c00;"><strong>'
                        . $lang->sprintf($lang->credits_ug_prereq_not_met, implode(', ', $missing_names))
                        . '</strong></td></tr>';
                    $hide_buy_button = true;
                }
            }
            break;

        case 'ad_space':
            $item_data = json_decode($item['data'], true);
            $ad_position = $item_data['position'] ?? 'header';
            $ad_duration = (int)($item_data['duration'] ?? 604800);

            $position_labels = array(
                'header'        => $lang->credits_ad_pos_header,
                'thread_header' => $lang->credits_ad_pos_thread_header,
            );
            $ad_position_display = $position_labels[$ad_position] ?? $ad_position;
            $ad_duration_display = $ad_duration > 0 ? credits_format_duration($ad_duration) : $lang->credits_usergroup_permanent;

            eval('$purchase_input = "' . $templates->get('credits_shop_purchase_input_ad') . '";');
            break;
    }

    // Build smart price display and balance row
    $usd_price = (float)($item['price_usd'] ?? 0);

    if ($credit_price_raw > 0 && $usd_price > 0) {
        $price_display = $item['price'] . ' ' . $lang->credits . ' / $' . number_format($usd_price, 2) . ' USD';
    } elseif ($usd_price > 0) {
        $price_display = '$' . number_format($usd_price, 2) . ' USD';
    } else {
        $price_display = $item['price'] . ' ' . $lang->credits;
    }

    // Only show balance row when credits can be used to purchase
    $balance_row = '';
    if ($credit_price_raw > 0) {
        $user_balance = my_number_format($mybb->user['credits']);
        $balance_row = '<tr><td class="trow2" width="40%"><strong>' . $lang->credits_your_balance . ':</strong></td><td class="trow2">' . $user_balance . ' ' . $lang->credits . '</td></tr>';
    }

    // Payment method toggle logic
    $payment_method_toggle = '';
    $has_gateways = ($mybb->settings['credits_coinbase_enabled'] == 1 || $mybb->settings['credits_lemonsqueezy_enabled'] == 1);

    if ($usd_price > 0 && $mybb->user['uid'] > 0 && $has_gateways) {
        // Build gateway radio options
        $gateway_options = '';
        if ($mybb->settings['credits_coinbase_enabled'] == 1) {
            $gateway_options .= '<label><input type="radio" name="gateway" value="coinbase" checked="checked" /> ' . $lang->credits_pay_coinbase . '</label><br />';
        }
        if ($mybb->settings['credits_lemonsqueezy_enabled'] == 1) {
            $checked = ($mybb->settings['credits_coinbase_enabled'] != 1) ? ' checked="checked"' : '';
            $gateway_options .= '<label><input type="radio" name="gateway" value="lemonsqueezy"' . $checked . ' /> ' . $lang->credits_pay_lemonsqueezy . '</label>';
        }

        if ($credit_price_raw > 0) {
            // Both credits and money — show credits/money toggle with gateway sub-choice
            eval('$payment_method_toggle = "' . $templates->get('credits_payment_method_toggle') . '";');
        } else {
            // Money-only — force money payment, show gateway choice directly
            $payment_method_toggle = '<input type="hidden" name="payment_method" value="money" />'
                . '<tr><td class="trow1" width="40%"><strong>' . $lang->credits_payment_gateway . ':</strong></td>'
                . '<td class="trow1">' . $gateway_options . '</td></tr>';
        }
    }

    // Gift toggle (only for logged-in users with gifting enabled)
    $gift_toggle = '';
    if ($mybb->user['uid'] > 0 && $mybb->settings['credits_gifting_enabled'] == 1) {
        eval('$gift_toggle = "' . $templates->get('credits_gift_toggle') . '";');
    }

    // Purchase button (hidden if prerequisites not met)
    $purchase_button = '';
    if (empty($hide_buy_button)) {
        $purchase_button = '<tr><td class="trow1" colspan="2" align="center"><input type="submit" class="button" value="' . $lang->credits_confirm_purchase . '" /></td></tr>';
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_shop_purchase') . '";');
    return $output;
}

/**
 * Build inline CSS for a background preview in the purchase form.
 *
 * @param string $bg_type Type (color, gradient, image)
 * @param string $bg_value The value
 * @return string Inline CSS string
 */
function credits_build_bg_css_for_preview(string $bg_type, string $bg_value): string
{
    $bg_value = htmlspecialchars_uni($bg_value);

    switch ($bg_type) {
        case 'color':
            return 'background-color: ' . $bg_value . ';';
        case 'gradient':
            return 'background: ' . $bg_value . ';';
        case 'image':
            return 'background: url(' . $bg_value . ') center/cover no-repeat;';
        default:
            return '';
    }
}

/**
 * Process a purchase submission.
 *
 * @return string Result message HTML
 */
function credits_shop_do_purchase(): string
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $iid = $mybb->get_input('iid', MyBB::INPUT_INT);

    $query = $db->simple_select('credits_shop', '*', "iid = '{$iid}' AND active = '1'");
    $item = $db->fetch_array($query);

    if (!$item) {
        error($lang->credits_item_not_found);
    }

    $uid = (int)$mybb->user['uid'];
    $price = (int)$item['price'];

    // Handle "pay with money" path — redirect to gateway
    $payment_method = $mybb->get_input('payment_method');
    if ($payment_method == 'money' && (float)($item['price_usd'] ?? 0) > 0) {
        $gateway = $mybb->get_input('gateway');
        if (!in_array($gateway, array('coinbase', 'lemonsqueezy'))) {
            error($lang->credits_payment_gateway_error);
        }

        require_once CREDITS_PLUGIN_PATH . 'payments.php';

        $amount_usd = (float)$item['price_usd'];
        $name = $item['name'];

        $redirect_url = '';
        if ($gateway == 'coinbase' && $mybb->settings['credits_coinbase_enabled'] == 1) {
            $result = credits_coinbase_create_charge($uid, 'item', $iid, $amount_usd, $name);
            if ($result && !empty($result['hosted_url'])) {
                $redirect_url = $result['hosted_url'];
            }
        } elseif ($gateway == 'lemonsqueezy' && $mybb->settings['credits_lemonsqueezy_enabled'] == 1) {
            $result = credits_lemonsqueezy_create_checkout($uid, 'item', $iid, $amount_usd, $name);
            if ($result && !empty($result['checkout_url'])) {
                $redirect_url = $result['checkout_url'];
            }
        }

        if (empty($redirect_url)) {
            error($lang->credits_payment_gateway_error);
        }

        header('Location: ' . $redirect_url);
        exit;
    }

    // Handle gift purchase path
    $purchase_target = $mybb->get_input('purchase_target');
    if ($purchase_target == 'gift' && $mybb->settings['credits_gifting_enabled'] == 1) {
        $gift_to_username = trim($mybb->get_input('gift_to_username'));
        $gift_message = $mybb->get_input('gift_message');

        if (empty($gift_to_username)) {
            error($lang->credits_gift_no_user);
        }

        $query = $db->simple_select('users', 'uid, username', "username = '" . $db->escape_string($gift_to_username) . "'");
        $recipient = $db->fetch_array($query);

        if (!$recipient) {
            error($lang->credits_gift_user_not_found);
        }

        $to_uid = (int)$recipient['uid'];

        if ($to_uid == $uid) {
            error($lang->credits_gift_self_error);
        }

        // Check minimum posts for gifting
        $min_posts = (int)$mybb->settings['credits_gifting_min_posts'];
        if ($mybb->user['postnum'] < $min_posts) {
            error($lang->sprintf($lang->credits_gift_min_posts, $min_posts));
        }

        require_once CREDITS_PLUGIN_PATH . 'core.php';
        if (!credits_gift_item($uid, $to_uid, $iid, $gift_message)) {
            error($lang->credits_gift_insufficient);
        }

        redirect(credits_url('credits', array('view' => 'shop')), $lang->credits_gift_success);
        exit;
    }

    // Prevent free acquisition of money-only items
    if ($price == 0 && (float)($item['price_usd'] ?? 0) > 0) {
        error($lang->credits_payment_gateway_error);
    }

    // Check balance
    if (credits_get($uid) < $price) {
        error($lang->credits_insufficient);
    }

    // Get the purchase value and validate based on item type
    $purchase_value = '';
    $expires = 0;

    switch ($item['type']) {
        case 'custom_title':
            $purchase_value = $mybb->get_input('purchase_value');
            $purchase_value = htmlspecialchars_uni(trim($purchase_value));
            if (empty($purchase_value)) {
                error($lang->credits_enter_title_error);
            }
            if (my_strlen($purchase_value) > 64) {
                error($lang->credits_title_too_long);
            }
            break;

        case 'username_color':
            $purchase_value = $mybb->get_input('purchase_value_hex');
            if (empty($purchase_value)) {
                $purchase_value = $mybb->get_input('purchase_value');
            }
            $purchase_value = trim($purchase_value);
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $purchase_value)) {
                error($lang->credits_invalid_color);
            }
            break;

        case 'icon':
            $item_data = json_decode($item['data'], true);
            $purchase_value = $item_data['image'] ?? '';
            if (empty($purchase_value)) {
                error($lang->credits_item_not_found);
            }
            break;

        case 'award':
            $item_data = json_decode($item['data'], true);
            $purchase_value = $item_data['image'] ?? '';
            if (empty($purchase_value)) {
                error($lang->credits_item_not_found);
            }
            break;

        case 'booster':
            // Boosters go to inventory inactive — user activates from inventory
            break;

        case 'postbit_bg':
            $item_data = json_decode($item['data'], true);
            $bg_type = $item_data['bg_type'] ?? 'color';
            $bg_value = $item_data['bg_value'] ?? '';
            // Convert to storable CSS value
            switch ($bg_type) {
                case 'gradient':
                    $purchase_value = $bg_value;
                    break;
                case 'image':
                    $purchase_value = $bg_value;
                    break;
                default: // color
                    $purchase_value = $bg_value;
                    break;
            }
            break;

        case 'username_effect':
            $item_data = json_decode($item['data'], true);
            $purchase_value = $item_data['effect'] ?? '';
            if (empty($purchase_value)) {
                error($lang->credits_item_not_found);
            }
            break;

        case 'usergroup':
            $item_data = json_decode($item['data'], true);
            $gid = (int)($item_data['gid'] ?? 0);
            $duration = (int)($item_data['duration'] ?? 0);
            if ($gid <= 0) {
                error($lang->credits_item_not_found);
            }

            // Server-side prerequisite validation
            $required_groups = $item_data['required_groups'] ?? array();
            if (!empty($required_groups) && is_array($required_groups)) {
                $user_groups = array((int)$mybb->user['usergroup']);
                if (!empty($mybb->user['additionalgroups'])) {
                    $user_groups = array_merge($user_groups, array_map('intval', explode(',', $mybb->user['additionalgroups'])));
                }
                $missing = array_diff($required_groups, $user_groups);
                if (!empty($missing)) {
                    error($lang->credits_ug_prereq_not_met_generic);
                }
            }

            $purchase_value = (string)$gid;
            if ($duration > 0) {
                $expires = TIME_NOW + $duration;
            }
            break;

        case 'ad_space':
            $item_data = json_decode($item['data'], true);
            $ad_position = $item_data['position'] ?? 'header';
            $ad_duration = (int)($item_data['duration'] ?? 604800);
            $ad_content = trim($mybb->get_input('ad_content'));
            $ad_image = trim($mybb->get_input('ad_image'));
            $ad_url = trim($mybb->get_input('ad_url'));
            if (empty($ad_content) && empty($ad_image)) {
                error($lang->credits_ad_content_required);
            }
            $purchase_value = $ad_position;
            if ($ad_duration > 0) {
                $expires = TIME_NOW + $ad_duration;
            }
            break;
    }

    // Check and decrement stock (atomic to prevent race conditions)
    if ((int)$item['stock'] >= 0) {
        if ((int)$item['stock'] == 0) {
            error($lang->credits_out_of_stock);
        }
        $db->write_query("UPDATE " . TABLE_PREFIX . "credits_shop SET stock = stock - 1 WHERE iid = '{$iid}' AND stock > 0");
        if ($db->affected_rows() == 0) {
            error($lang->credits_out_of_stock);
        }
    }

    // Deduct credits
    if (!credits_subtract($uid, $price, 'purchase', $iid)) {
        // Restore stock if credit deduction fails
        if ((int)$item['stock'] >= 0) {
            $db->write_query("UPDATE " . TABLE_PREFIX . "credits_shop SET stock = stock + 1 WHERE iid = '{$iid}'");
        }
        error($lang->credits_insufficient);
    }

    // Deactivate any previous purchase of the same type (except boosters and awards)
    if (!in_array($item['type'], array('booster', 'award'))) {
        $db->write_query("
            UPDATE " . TABLE_PREFIX . "credits_purchases p
            INNER JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
            SET p.active = 0
            WHERE p.uid = '{$uid}'
              AND p.active = '1'
              AND s.type = '" . $db->escape_string($item['type']) . "'
        ");
    }

    // Boosters go to inventory inactive — user activates from inventory
    $purchase_active = ($item['type'] === 'booster') ? 0 : 1;

    // Record the purchase
    $purchase_data = array(
        'uid'      => $uid,
        'iid'      => $iid,
        'value'    => $db->escape_string($purchase_value),
        'dateline' => TIME_NOW,
        'expires'  => $expires,
        'active'   => $purchase_active,
    );
    $db->insert_query('credits_purchases', $purchase_data);

    $last_pid = $db->insert_id();

    // Apply the purchase effect (skip for inactive items like boosters)
    if ($purchase_active) {
        credits_apply_purchase($uid, $item['type'], $purchase_value);
    }

    // Post-purchase actions for special types
    if ($item['type'] == 'usergroup') {
        $item_data = json_decode($item['data'], true);
        $gid = (int)($item_data['gid'] ?? 0);
        $duration = (int)($item_data['duration'] ?? 0);

        // Record usergroup subscription
        $sub_data = array(
            'uid'      => $uid,
            'pid'      => $last_pid,
            'gid'      => $gid,
            'expires'  => $expires,
            'active'   => 1,
            'dateline' => TIME_NOW,
        );
        $db->insert_query('credits_usergroup_subs', $sub_data);

        // Grant bonus credits if configured
        $bonus_credits = (int)($item_data['bonus_credits'] ?? 0);
        if ($bonus_credits > 0) {
            credits_add_direct($uid, $bonus_credits, 'purchase_bonus', $iid);
        }

        // Grant bonus booster if configured
        $bonus_mult = (int)($item_data['bonus_booster_multiplier'] ?? 0);
        $bonus_dur = (int)($item_data['bonus_booster_duration'] ?? 0);
        if ($bonus_mult > 0 && $bonus_dur > 0) {
            $booster_purchase = array(
                'uid'      => $uid,
                'iid'      => $iid,
                'value'    => 'bonus_booster',
                'dateline' => TIME_NOW,
                'expires'  => TIME_NOW + $bonus_dur,
                'active'   => 1,
            );
            $db->insert_query('credits_purchases', $booster_purchase);
        }
    }

    if ($item['type'] == 'ad_space') {
        $item_data = json_decode($item['data'], true);
        $ad_active = ($mybb->settings['credits_ads_approval'] == 1) ? 0 : 1;

        $ad_data = array(
            'uid'      => $uid,
            'pid'      => $last_pid,
            'position' => $db->escape_string($item_data['position'] ?? 'header'),
            'content'  => $db->escape_string($ad_content ?? ''),
            'url'      => $db->escape_string($ad_url ?? ''),
            'image'    => $db->escape_string($ad_image ?? ''),
            'views'    => 0,
            'clicks'   => 0,
            'expires'  => $expires,
            'active'   => $ad_active,
            'dateline' => TIME_NOW,
        );
        $db->insert_query('credits_ads', $ad_data);
    }

    // Check purchase-related achievements
    credits_check_achievements($uid, 'purchases');

    // Update session balance
    $mybb->user['credits'] -= $price;

    $success_msg = $lang->credits_purchase_success;
    if ($item['type'] == 'ad_space' && $mybb->settings['credits_ads_approval'] == 1) {
        $success_msg = $lang->credits_ad_pending_approval;
    }

    redirect(credits_url('credits', array('view' => 'shop')), $success_msg);
    exit;
}

/**
 * Apply a purchase effect to the user.
 *
 * @param int    $uid   User ID
 * @param string $type  Item type
 * @param string $value Purchase value
 */
function credits_apply_purchase(int $uid, string $type, string $value): void
{
    global $db;

    switch ($type) {
        case 'custom_title':
            $db->update_query('users', array(
                'usertitle' => $db->escape_string($value),
            ), "uid = '{$uid}'");
            break;

        case 'username_color':
            $db->update_query('users', array(
                'credits_username_color' => $db->escape_string($value),
            ), "uid = '{$uid}'");
            break;

        case 'icon':
            $db->update_query('users', array(
                'credits_icon' => $db->escape_string($value),
            ), "uid = '{$uid}'");
            break;

        case 'award':
            credits_rebuild_user_awards($uid);
            break;

        case 'booster':
            // Booster is applied via the purchases table; credits_get_active_booster() handles it
            break;

        case 'postbit_bg':
            $db->update_query('users', array(
                'credits_postbit_bg' => $db->escape_string($value),
            ), "uid = '{$uid}'");
            break;

        case 'username_effect':
            $db->update_query('users', array(
                'credits_username_effect' => $db->escape_string($value),
            ), "uid = '{$uid}'");
            break;

        case 'usergroup':
            $gid = (int)$value;
            if ($gid > 0) {
                credits_add_usergroup($uid, $gid);
            }
            break;

        case 'ad_space':
            // Ad is managed via the credits_ads table, no user column to update
            break;
    }
}

/**
 * Get a user's active purchase for a given item type.
 *
 * @param int    $uid  User ID
 * @param string $type Item type
 * @return array|null
 */
function credits_get_active_purchase(int $uid, string $type): ?array
{
    global $db;

    $query = $db->query("
        SELECT p.*, s.type
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.uid = '{$uid}'
          AND p.active = '1'
          AND s.type = '" . $db->escape_string($type) . "'
        LIMIT 1
    ");

    $result = $db->fetch_array($query);
    return $result ?: null;
}

<?php
/**
 * Credits - Hook Handlers
 *
 * All frontend hook functions for earning, displaying, and page rendering.
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

/**
 * Global start hook - handles daily login bonus and CSS injection.
 */
function credits_global_start()
{
    global $mybb, $lang, $templates, $credits_nav_link, $credits_ad_header, $credits_base_url, $credits_inventory_url;

    $credits_nav_link = '';
    $credits_ad_header = '';

    if (empty($mybb->settings['credits_enabled'])) {
        return;
    }

    $lang->load('credits');

    // Set global URL variables for templates
    $credits_base_url = credits_url('credits');
    $credits_inventory_url = credits_url('inventory');

    // Ad display (header only; thread header ads are rendered in postbit)
    if ($mybb->settings['credits_ads_enabled'] == 1 && !credits_user_is_ad_exempt($mybb->user)) {
        $header_ads = credits_get_active_ads('header');
        if (!empty($header_ads)) {
            $ad = $header_ads[array_rand($header_ads)];
            credits_record_ad_view((int)$ad['ad_id']);
            $ad_content = credits_render_ad($ad);
            eval('$credits_ad_header = "' . $templates->get('credits_ad_header') . '";');
        }
    }

    // Daily login bonus for logged-in users
    if ($mybb->user['uid'] > 0 && $mybb->settings['credits_daily_login'] > 0) {
        $today_start = strtotime('today midnight');
        if ((int)$mybb->user['credits_last_login_bonus'] < $today_start) {
            credits_add($mybb->user['uid'], (int)$mybb->settings['credits_daily_login'], 'login');

            // Check reg_days achievements (once per day alongside login bonus)
            credits_check_achievements($mybb->user['uid'], 'reg_days');

            global $db;
            $db->update_query('users', array(
                'credits_last_login_bonus' => TIME_NOW,
            ), "uid = '{$mybb->user['uid']}'");

            // Update session data
            $mybb->user['credits'] += (int)$mybb->settings['credits_daily_login'];
            $mybb->user['credits_last_login_bonus'] = TIME_NOW;
        }
    }
}

/**
 * Postbit hook - display credits, icon, booster badge, awards, BG, and username effect.
 *
 * @param array $post Post data array passed by reference
 */
function credits_postbit(&$post)
{
    global $mybb, $templates, $lang;

    if (empty($mybb->settings['credits_enabled'])) {
        $post['credits_display'] = '';
        return;
    }

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    // Icon display - append to username
    if (!empty($post['credits_icon'])) {
        $icon_src = htmlspecialchars_uni($post['credits_icon']);
        $post['profilelink'] .= ' <img src="' . $icon_src . '" alt="" style="vertical-align: middle; max-height: 16px; max-width: 16px;" />';
    }

    // XP Boost badge
    $post['credits_booster_badge'] = '';
    $booster = credits_get_active_booster((int)$post['uid']);
    if ($booster) {
        $post['credits_booster_badge'] = ' <span class="credits_booster_badge" style="color: #f90; font-size: 10px;" title="' . $lang->credits_xp_boost_active . '">[' . $booster['multiplier'] . 'x]</span>';
    }

    // Awards display
    $post['credits_awards_display'] = '';
    if (!empty($post['credits_awards'])) {
        $awards = json_decode($post['credits_awards'], true);
        if (!empty($awards) && is_array($awards)) {
            $awards_images = '';
            foreach ($awards as $award_path) {
                $award_src = htmlspecialchars_uni($award_path);
                eval('$awards_images .= "' . $templates->get('credits_postbit_award_img') . '";');
            }
            eval('$post[\'credits_awards_display\'] = "' . $templates->get('credits_postbit_awards') . '";');
        }
    }

    // Postbit background style
    $post['credits_bg_style'] = '';
    if (!empty($post['credits_postbit_bg'])) {
        $bg_value = htmlspecialchars_uni($post['credits_postbit_bg']);
        $bg_css = credits_build_bg_css($post['credits_postbit_bg']);
        $post_pid = (int)$post['pid'];
        $post['credits_bg_style'] = '<style>#post_' . $post_pid . ' .post_author { ' . $bg_css . ' }</style>';
    }

    // Username color override
    if (!empty($post['credits_username_color'])) {
        $ucolor = htmlspecialchars_uni($post['credits_username_color']);
        $post['profilelink'] = '<span class="credits_ucolor" style="--credits-ucolor: ' . $ucolor . ';">' . $post['profilelink'] . '</span>';
    }

    // Username effect
    if (!empty($post['credits_username_effect'])) {
        $effect = htmlspecialchars_uni($post['credits_username_effect']);
        $post['profilelink'] = '<span class="credits_fx_' . $effect . '">' . $post['profilelink'] . '</span>';
    }

    eval('$post[\'credits_display\'] = "' . $templates->get('credits_postbit') . '";');

    // Thread header ad display (shown above the first post)
    if ($mybb->settings['credits_ads_enabled'] == 1 && !credits_user_is_ad_exempt($mybb->user)) {
        static $credits_thread_ad_shown = false;

        if (!$credits_thread_ad_shown) {
            $credits_thread_ad_shown = true;
            $thread_header_ads = credits_get_active_ads('thread_header');
            if (!empty($thread_header_ads)) {
                $ad = $thread_header_ads[array_rand($thread_header_ads)];
                credits_record_ad_view((int)$ad['ad_id']);
                $ad_content = credits_render_ad($ad);
                eval('$post[\'credits_display\'] .= "' . $templates->get('credits_ad_thread_header') . '";');
            }
        }
    }
}

/**
 * Build a CSS background declaration from a stored value.
 *
 * @param string $value The stored bg value
 * @return string CSS declaration(s)
 */
function credits_build_bg_css(string $value): string
{
    $value = trim($value);

    // Hex color
    if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $value)) {
        return 'background-color: ' . htmlspecialchars_uni($value) . ' !important;';
    }

    // Gradient (starts with linear-gradient, radial-gradient, etc.)
    if (preg_match('/^(linear|radial|conic)-gradient\(/i', $value)) {
        return 'background: ' . htmlspecialchars_uni($value) . ' !important;';
    }

    // Image path
    return 'background: url(' . htmlspecialchars_uni($value) . ') center/cover no-repeat !important;';
}

/**
 * Member profile hook - display credits section with icon, booster, awards, BG, and effect.
 */
function credits_profile()
{
    global $mybb, $db, $templates, $lang, $memprofile, $theme, $credits_profile;

    if (empty($mybb->settings['credits_enabled'])) {
        $credits_profile = '';
        return;
    }

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    // Calculate rank
    $query = $db->simple_select('users', 'COUNT(*) as rank_count', "credits > '{$memprofile['credits']}'");
    $rank_data = $db->fetch_array($query);
    $credits_rank = (int)$rank_data['rank_count'] + 1;

    // Icon row
    $credits_profile_icon_row = '';
    if (!empty($memprofile['credits_icon'])) {
        eval('$credits_profile_icon_row = "' . $templates->get('credits_profile_icon_row') . '";');
    }

    // XP Boost row
    $credits_profile_booster_row = '';
    $booster = credits_get_active_booster((int)$memprofile['uid']);
    if ($booster) {
        $booster_multiplier = $booster['multiplier'];
        $remaining = $booster['expires'] - TIME_NOW;
        $booster_time_remaining = credits_format_duration($remaining);
        eval('$credits_profile_booster_row = "' . $templates->get('credits_profile_booster_row') . '";');
    }

    // Awards row
    $credits_profile_awards_row = '';
    if (!empty($memprofile['credits_awards'])) {
        $awards = json_decode($memprofile['credits_awards'], true);
        if (!empty($awards) && is_array($awards)) {
            $profile_awards_images = '';
            foreach ($awards as $award_path) {
                $src = htmlspecialchars_uni($award_path);
                $profile_awards_images .= '<img src="' . $src . '" alt="award" style="max-height: 24px; max-width: 24px; margin: 0 3px; vertical-align: middle;" />';
            }
            eval('$credits_profile_awards_row = "' . $templates->get('credits_profile_awards_row') . '";');
        }
    }

    // Background row
    $credits_profile_bg_row = '';
    if (!empty($memprofile['credits_postbit_bg'])) {
        $profile_bg_style = credits_build_bg_css($memprofile['credits_postbit_bg']);
        eval('$credits_profile_bg_row = "' . $templates->get('credits_profile_bg_row') . '";');
    }

    // Effect row
    $credits_profile_effect_row = '';
    if (!empty($memprofile['credits_username_effect'])) {
        $profile_effect_name = htmlspecialchars_uni($memprofile['credits_username_effect']);
        $profile_effect_label = credits_effect_label($memprofile['credits_username_effect']);
        eval('$credits_profile_effect_row = "' . $templates->get('credits_profile_effect_row') . '";');
    }

    // Achievements row
    $credits_profile_achievements_row = '';
    if (!empty($mybb->settings['credits_achievements_enabled']) && $mybb->settings['credits_achievements_enabled'] == 1) {
        $ua_query = $db->query("
            SELECT a.name, a.icon
            FROM " . TABLE_PREFIX . "credits_user_achievements ua
            INNER JOIN " . TABLE_PREFIX . "credits_achievements a ON ua.aid = a.aid
            WHERE ua.uid = '{$memprofile['uid']}'
            ORDER BY ua.dateline ASC
        ");
        if ($db->num_rows($ua_query) > 0) {
            $profile_achievements_display = '';
            while ($ua = $db->fetch_array($ua_query)) {
                $ach_label = htmlspecialchars_uni($ua['name']);
                if (!empty($ua['icon'])) {
                    $icon_src = htmlspecialchars_uni($ua['icon']);
                    $profile_achievements_display .= '<img src="' . $icon_src . '" alt="' . $ach_label . '" title="' . $ach_label . '" style="max-height: 24px; max-width: 24px; margin: 0 3px; vertical-align: middle;" />';
                } else {
                    $profile_achievements_display .= '<span style="display: inline-block; padding: 2px 6px; background: #f0f0f0; border-radius: 3px; margin: 0 3px; font-size: 11px;">' . $ach_label . '</span>';
                }
            }
            eval('$credits_profile_achievements_row = "' . $templates->get('credits_profile_achievements_row') . '";');
        }
    }

    eval('$credits_profile = "' . $templates->get('credits_profile') . '";');
}

/**
 * Get a human-readable label for a username effect preset.
 *
 * @param string $effect Effect name
 * @return string
 */
function credits_effect_label(string $effect): string
{
    global $lang;

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    $labels = array(
        'rainbow'  => $lang->credits_effect_rainbow,
        'glow'     => $lang->credits_effect_glow,
        'sparkle'  => $lang->credits_effect_sparkle,
        'shadow'   => $lang->credits_effect_shadow,
        'bold'     => $lang->credits_effect_bold,
        'gradient' => $lang->credits_effect_gradient,
    );

    return $labels[$effect] ?? $effect;
}

/**
 * Render an ad for display.
 *
 * @param array $ad Ad data
 * @return string HTML content
 */
function credits_render_ad(array $ad): string
{
    $url = htmlspecialchars_uni($ad['url']);
    $click_url = 'misc.php?action=credits_ad_click&ad_id=' . (int)$ad['ad_id'];

    if (!empty($ad['image'])) {
        $image = htmlspecialchars_uni($ad['image']);
        $content = '<a href="' . $click_url . '" target="_blank" rel="nofollow"><img src="' . $image . '" alt="ad" style="max-width: 100%; max-height: 90px;" /></a>';
    } else {
        $text = htmlspecialchars_uni($ad['content']);
        $content = '<a href="' . $click_url . '" target="_blank" rel="nofollow">' . $text . '</a>';
    }

    return $content;
}

/**
 * Award credits for a new reply.
 *
 * @param object $posthandler The post data handler
 */
function credits_earn_post(&$posthandler)
{
    global $mybb;

    if (empty($mybb->settings['credits_enabled']) || $mybb->settings['credits_per_post'] <= 0) {
        return;
    }

    $post = &$posthandler->data;
    $uid = (int)$post['uid'];

    if ($uid <= 0) {
        return;
    }

    $pid = (int)$posthandler->pid;
    credits_add($uid, (int)$mybb->settings['credits_per_post'], 'post', $pid);
    credits_check_achievements($uid, 'posts');

    // Check referral reward (referred user making posts)
    credits_check_referral_reward($uid);
}

/**
 * Award credits for creating a new thread.
 *
 * @param object $posthandler The post data handler
 */
function credits_earn_thread(&$posthandler)
{
    global $mybb;

    if (empty($mybb->settings['credits_enabled']) || $mybb->settings['credits_per_thread'] <= 0) {
        return;
    }

    $post = &$posthandler->data;
    $uid = (int)$post['uid'];

    if ($uid <= 0) {
        return;
    }

    $tid = (int)$posthandler->tid;
    credits_add($uid, (int)$mybb->settings['credits_per_thread'], 'thread', $tid);
    credits_check_achievements($uid, 'threads');
}

/**
 * Award credits when a user receives a reputation point.
 *
 * @param array $reputation Reputation data
 */
function credits_earn_reputation(&$reputation)
{
    global $mybb;

    if (empty($mybb->settings['credits_enabled']) || $mybb->settings['credits_per_rep'] <= 0) {
        return;
    }

    // Only award for positive reputation
    if (!isset($reputation['reputation']) || (int)$reputation['reputation'] <= 0) {
        return;
    }

    $uid = (int)$reputation['uid'];

    if ($uid <= 0) {
        return;
    }

    $rid = isset($reputation['rid']) ? (int)$reputation['rid'] : 0;
    credits_add($uid, (int)$mybb->settings['credits_per_rep'], 'rep', $rid);
    credits_check_achievements($uid, 'reputation');
}

/**
 * Handle the Credits pages (leaderboard, shop, log) via misc.php.
 */
function credits_misc_page()
{
    global $mybb, $db, $lang, $templates, $theme, $header, $headerinclude, $footer;

    // Handle ad click tracking
    if ($mybb->get_input('action') == 'credits_ad_click') {
        $ad_id = $mybb->get_input('ad_id', MyBB::INPUT_INT);
        if ($ad_id > 0) {
            $query = $db->simple_select('credits_ads', 'url', "ad_id = '{$ad_id}' AND active = '1'");
            $ad = $db->fetch_array($query);
            if ($ad && !empty($ad['url'])) {
                credits_record_ad_click($ad_id);
                header('Location: ' . $ad['url']);
                exit;
            }
        }
        header('Location: ' . credits_url('credits'));
        exit;
    }

    if ($mybb->get_input('action') != 'credits') {
        return;
    }

    if (empty($mybb->settings['credits_enabled'])) {
        error_no_permission();
    }

    $lang->load('credits');
    add_breadcrumb($lang->credits, credits_url('credits'));

    // Load credits frontend JS (CSS is loaded globally in credits_global_start)
    $headerinclude .= "\n" . '<script type="text/javascript" src="' . $mybb->asset_url . '/jscripts/credits.js"></script>';

    $view = $mybb->get_input('view');

    // Set URL globals for templates
    global $credits_base_url, $credits_inventory_url;
    $credits_base_url = credits_url('credits');
    $credits_inventory_url = credits_url('inventory');

    // Build tab navigation
    $tab_leaderboard = ($view == '' || $view == 'leaderboard') ? 'credits_tab_active' : 'credits_tab';
    $tab_log = ($view == 'log') ? 'credits_tab_active' : 'credits_tab';

    $shop_tab = '';
    if ($mybb->settings['credits_shop_enabled'] == 1) {
        $tab_shop = ($view == 'shop') ? 'credits_tab_active' : 'credits_tab';
        eval('$shop_tab = "' . $templates->get('credits_tab_shop') . '";');
    }

    $gift_tab = '';

    $achievements_tab = '';
    if (!empty($mybb->settings['credits_achievements_enabled']) && $mybb->settings['credits_achievements_enabled'] == 1) {
        $tab_achievements = ($view == 'achievements') ? 'credits_tab_active' : 'credits_tab';
        eval('$achievements_tab = "' . $templates->get('credits_tab_achievements') . '";');
    }

    $lottery_tab = '';
    if (!empty($mybb->settings['credits_lottery_enabled']) && $mybb->settings['credits_lottery_enabled'] == 1) {
        $tab_lottery = ($view == 'lottery') ? 'credits_tab_active' : 'credits_tab';
        eval('$lottery_tab = "' . $templates->get('credits_tab_lottery') . '";');
    }

    $referral_tab = '';
    if (!empty($mybb->settings['credits_referral_enabled']) && $mybb->settings['credits_referral_enabled'] == 1) {
        $tab_referrals = ($view == 'referrals') ? 'credits_tab_active' : 'credits_tab';
        eval('$referral_tab = "' . $templates->get('credits_tab_referrals') . '";');
    }

    $inventory_tab = '';
    if ($mybb->user['uid'] > 0) {
        $tab_inventory = ($view == 'inventory') ? 'credits_tab_active' : 'credits_tab';
        eval('$inventory_tab = "' . $templates->get('credits_tab_inventory') . '";');
    }

    eval('$credits_tabs = "' . $templates->get('credits_tabs') . '";');

    switch ($view) {
        case 'shop':
            add_breadcrumb($lang->credits_shop, credits_url('credits', array('view' => 'shop')));
            $credits_content = credits_page_shop();
            break;
        case 'log':
            add_breadcrumb($lang->credits_log, credits_url('credits', array('view' => 'log')));
            $credits_content = credits_page_log();
            break;
        case 'gift':
            add_breadcrumb($lang->credits_gift, credits_url('credits', array('view' => 'gift')));
            require_once CREDITS_PLUGIN_PATH . 'gifting.php';
            if ($mybb->get_input('do') == 'send' && $mybb->request_method == 'post') {
                $credits_content = credits_do_gift();
            } else {
                $credits_content = credits_page_gift();
            }
            break;
        case 'packs':
            // Redirect to shop - packs are now displayed within the shop
            header('Location: ' . credits_url('credits', array('view' => 'shop')));
            exit;
        case 'buy_pack':
            add_breadcrumb($lang->credits_shop, credits_url('credits', array('view' => 'shop')));
            require_once CREDITS_PLUGIN_PATH . 'payments.php';
            $credits_content = credits_page_buy_pack();
            break;
        case 'buy_item':
            add_breadcrumb($lang->credits_shop, credits_url('credits', array('view' => 'shop')));
            require_once CREDITS_PLUGIN_PATH . 'payments.php';
            $credits_content = credits_page_buy_item();
            break;
        case 'achievements':
            add_breadcrumb($lang->credits_achievements, credits_url('credits', array('view' => 'achievements')));
            $credits_content = credits_page_achievements();
            break;
        case 'lottery':
            add_breadcrumb($lang->credits_lottery, credits_url('credits', array('view' => 'lottery')));
            if ($mybb->get_input('do') == 'buy_ticket' && $mybb->request_method == 'post') {
                $credits_content = credits_do_buy_ticket();
            } else {
                $credits_content = credits_page_lottery();
            }
            break;
        case 'referrals':
            add_breadcrumb($lang->credits_referrals, credits_url('credits', array('view' => 'referrals')));
            if ($mybb->get_input('do') == 'enter_code' && $mybb->request_method == 'post') {
                $credits_content = credits_do_enter_referral();
            } else {
                $credits_content = credits_page_referrals();
            }
            break;
        case 'inventory':
            require_once CREDITS_PLUGIN_PATH . 'inventory.php';
            // Inventory page handles its own breadcrumbs, output_page() and exit
            credits_page_inventory();
            return;
        default:
            add_breadcrumb($lang->credits_leaderboard, credits_url('credits'));
            $credits_content = credits_page_leaderboard();
            break;
    }

    // Prepend tabs to content
    $credits_content = $credits_tabs . $credits_content;

    eval('$page = "' . $templates->get('credits_page') . '";');
    output_page($page);
    exit;
}

/**
 * Render the leaderboard page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_leaderboard(): string
{
    global $mybb, $db, $templates, $lang, $theme;

    $limit = (int)$mybb->settings['credits_leaderboard_count'];
    if ($limit <= 0) {
        $limit = 25;
    }

    $query = $db->simple_select('users', 'uid, username, credits, postnum', '', array(
        'order_by'  => 'credits',
        'order_dir' => 'DESC',
        'limit'     => $limit,
    ));

    $leaderboard_rows = '';
    $rank = 0;

    if ($db->num_rows($query) > 0) {
        while ($user = $db->fetch_array($query)) {
            $rank++;
            $alt_bg = alt_trow();
            $profile_url = get_profile_link($user['uid']);
            $user['username'] = htmlspecialchars_uni($user['username']);
            $user['credits'] = my_number_format($user['credits']);
            $user['postnum'] = my_number_format($user['postnum']);

            eval('$leaderboard_rows .= "' . $templates->get('credits_leaderboard_row') . '";');
        }
    } else {
        eval('$leaderboard_rows = "' . $templates->get('credits_leaderboard_empty') . '";');
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_leaderboard') . '";');
    return $output;
}

/**
 * Render the shop page (listing, purchase form, or purchase action).
 *
 * @return string Evaluated template HTML
 */
function credits_page_shop(): string
{
    global $mybb, $lang;

    if ($mybb->settings['credits_shop_enabled'] != 1) {
        error($lang->credits_shop_disabled);
    }

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    require_once CREDITS_PLUGIN_PATH . 'shop.php';

    // Handle purchase submission
    $do = $mybb->get_input('do');
    if ($do == 'purchase' && $mybb->request_method == 'post') {
        return credits_shop_do_purchase();
    }

    // Handle pack purchase submission
    if ($do == 'buy_pack' && $mybb->request_method == 'post') {
        verify_post_check($mybb->get_input('my_post_key'));
        $pack_id = $mybb->get_input('pack_id', MyBB::INPUT_INT);
        $gateway = $mybb->get_input('gateway');

        require_once CREDITS_PLUGIN_PATH . 'payments.php';

        // Reuse existing buy_pack function by setting input values
        $mybb->input['pack_id'] = $pack_id;
        $mybb->input['gateway'] = $gateway;
        return credits_page_buy_pack();
    }

    // Show pack purchase confirmation form
    $purchase_pack_id = $mybb->get_input('purchase_pack', MyBB::INPUT_INT);
    if ($purchase_pack_id > 0) {
        return credits_pack_purchase_form($purchase_pack_id);
    }

    // Show purchase form for a specific item
    $purchase_iid = $mybb->get_input('purchase', MyBB::INPUT_INT);
    if ($purchase_iid > 0) {
        return credits_shop_purchase_form($purchase_iid);
    }

    // Default: show shop listing
    return credits_shop_listing();
}

/**
 * Render the personal credit log page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_log(): string
{
    global $mybb, $db, $templates, $lang, $theme;

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    $uid = (int)$mybb->user['uid'];
    $per_page = (int)$mybb->settings['credits_per_page'];
    if ($per_page <= 0) {
        $per_page = 20;
    }

    // Count total entries
    $query = $db->simple_select('credits_log', 'COUNT(*) as total', "uid = '{$uid}'");
    $total = (int)$db->fetch_field($query, 'total');

    // Pagination
    $page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($page < 1) {
        $page = 1;
    }
    $start = ($page - 1) * $per_page;

    $multipage = multipage($total, $per_page, $page, credits_url('credits', array('view' => 'log')));

    // Fetch log entries
    $query = $db->simple_select('credits_log', '*', "uid = '{$uid}'", array(
        'order_by'    => 'dateline',
        'order_dir'   => 'DESC',
        'limit'       => $per_page,
        'limit_start' => $start,
    ));

    $log_rows = '';

    if ($db->num_rows($query) > 0) {
        while ($log = $db->fetch_array($query)) {
            $alt_bg = alt_trow();
            $action_name = credits_action_name($log['action']);
            $amount_display = credits_format((int)$log['amount']);
            $amount_class = (int)$log['amount'] >= 0 ? 'credits_positive' : 'credits_negative';
            $log_date = my_date($mybb->settings['dateformat'] . ', ' . $mybb->settings['timeformat'], $log['dateline']);

            eval('$log_rows .= "' . $templates->get('credits_log_row') . '";');
        }
    } else {
        eval('$log_rows = "' . $templates->get('credits_log_empty') . '";');
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_log') . '";');
    return $output;
}

/**
 * Render the achievements page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_achievements(): string
{
    global $mybb, $db, $templates, $lang, $theme;

    if (empty($mybb->settings['credits_achievements_enabled']) || $mybb->settings['credits_achievements_enabled'] != 1) {
        error($lang->credits_disabled);
    }

    // Get all active achievements
    $query = $db->simple_select('credits_achievements', '*', "active = '1'", array(
        'order_by'  => 'type, threshold',
        'order_dir' => 'ASC',
    ));

    // Get user's earned achievements
    $earned = array();
    if ($mybb->user['uid'] > 0) {
        $earned_query = $db->simple_select('credits_user_achievements', 'aid, dateline', "uid = '{$mybb->user['uid']}'");
        while ($e = $db->fetch_array($earned_query)) {
            $earned[(int)$e['aid']] = $e['dateline'];
        }
    }

    // Cache user stats per type to avoid repeated queries
    $user_stats = array();

    $achievement_rows = '';

    if ($db->num_rows($query) > 0) {
        while ($ach = $db->fetch_array($query)) {
            $alt_bg = alt_trow();
            $aid = (int)$ach['aid'];
            $ach_name = htmlspecialchars_uni($ach['name']);
            $ach_description = htmlspecialchars_uni($ach['description']);

            // Icon
            $ach_icon = '';
            if (!empty($ach['icon'])) {
                $icon_src = htmlspecialchars_uni($ach['icon']);
                $ach_icon = '<img src="' . $icon_src . '" alt="" style="max-height: 24px; max-width: 24px;" />';
            }

            // Reward display
            $rewards = array();
            if ((int)$ach['reward_credits'] > 0) {
                $rewards[] = '+' . my_number_format((int)$ach['reward_credits']) . ' ' . $lang->credits;
            }
            if ((int)$ach['reward_booster_multiplier'] > 0 && (int)$ach['reward_booster_duration'] > 0) {
                $rewards[] = (int)$ach['reward_booster_multiplier'] . 'x ' . credits_format_duration((int)$ach['reward_booster_duration']);
            }
            $ach_reward_display = !empty($rewards) ? implode(', ', $rewards) : '-';

            // Status / progress
            if (isset($earned[$aid])) {
                $earned_date = my_date($mybb->settings['dateformat'], $earned[$aid]);
                $ach_status = '<span style="color: #090;">' . $lang->credits_achievement_earned . ' (' . $earned_date . ')</span>';
            } elseif ($mybb->user['uid'] > 0) {
                $ach_type = $ach['type'];
                if (!isset($user_stats[$ach_type])) {
                    $user_stats[$ach_type] = credits_get_user_stat($mybb->user['uid'], $ach_type);
                }
                $user_stat = $user_stats[$ach_type];
                $threshold = (int)$ach['threshold'];
                $pct = $threshold > 0 ? min(100, (int)floor($user_stat / $threshold * 100)) : 0;
                $ach_status = '<span style="color: #999;">' . my_number_format($user_stat) . ' / ' . my_number_format($threshold) . ' (' . $pct . '%)</span>';
            } else {
                $threshold = (int)$ach['threshold'];
                $ach_status = '<span style="color: #999;">' . $lang->credits_achievement_locked . '</span>';
            }

            eval('$achievement_rows .= "' . $templates->get('credits_achievements_row') . '";');
        }
    } else {
        eval('$achievement_rows = "' . $templates->get('credits_achievements_empty') . '";');
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_achievements_page') . '";');
    return $output;
}

/**
 * Render the lottery page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_lottery(): string
{
    global $mybb, $db, $lang, $templates, $theme;

    if (empty($mybb->settings['credits_lottery_enabled']) || $mybb->settings['credits_lottery_enabled'] != 1) {
        error($lang->credits_lottery_disabled);
    }

    $uid = (int)$mybb->user['uid'];

    // Active lotteries
    $active_rows = '';
    $query = $db->simple_select('credits_lottery', '*', "status = 'active'", array('order_by' => 'draw_time', 'order_dir' => 'ASC'));
    $has_active = false;
    $i = 0;

    while ($lottery = $db->fetch_array($query)) {
        $has_active = true;
        $alt_bg = ($i % 2 == 0) ? 'trow1' : 'trow2';
        $i++;

        $lottery_id = (int)$lottery['lottery_id'];
        $lottery_name = htmlspecialchars_uni($lottery['name']);
        $lottery_description = htmlspecialchars_uni($lottery['description']);

        // Count tickets and calculate pot
        $ticket_query = $db->simple_select('credits_lottery_tickets', 'COUNT(*) as cnt', "lottery_id = '{$lottery_id}'");
        $ticket_count = (int)$db->fetch_field($ticket_query, 'cnt');
        $pot = $ticket_count * (int)$lottery['ticket_price'];
        $lottery_pot = my_number_format($pot) . ' ' . $lang->credits;

        $lottery_ticket_price = my_number_format($lottery['ticket_price']) . ' ' . $lang->credits;
        $lottery_draw_time = my_date($mybb->settings['dateformat'] . ', ' . $mybb->settings['timeformat'], $lottery['draw_time']);

        // User's tickets for this lottery
        $lottery_action = '';
        if ($uid > 0) {
            $my_tickets_query = $db->simple_select('credits_lottery_tickets', 'COUNT(*) as cnt', "lottery_id = '{$lottery_id}' AND uid = '{$uid}'");
            $my_tickets = (int)$db->fetch_field($my_tickets_query, 'cnt');
            $max_tickets = (int)$lottery['max_tickets_per_user'];

            if ($max_tickets <= 0 || $my_tickets < $max_tickets) {
                $lottery_action = '<form action="misc.php?action=credits&view=lottery" method="post" style="display:inline;">'
                    . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
                    . '<input type="hidden" name="do" value="buy_ticket" />'
                    . '<input type="hidden" name="lottery_id" value="' . $lottery_id . '" />'
                    . '<input type="submit" class="button" value="' . $lang->credits_lottery_buy_ticket . '" />'
                    . '</form>';
            } else {
                $lottery_action = '<em>' . $lang->credits_lottery_max_reached . '</em>';
            }
            $lottery_action .= '<br /><small>' . $lang->sprintf($lang->credits_lottery_your_tickets, $my_tickets) . '</small>';
        } else {
            $lottery_action = '<em>' . $lang->credits_lottery_login_required . '</em>';
        }

        eval('$active_rows .= "' . $templates->get('credits_lottery_active_row') . '";');
    }

    if (!$has_active) {
        eval('$active_rows = "' . $templates->get('credits_lottery_empty') . '";');
    }

    // Past/completed lotteries
    $past_rows = '';
    $query = $db->query("
        SELECT l.*, u.username AS winner_name
        FROM " . TABLE_PREFIX . "credits_lottery l
        LEFT JOIN " . TABLE_PREFIX . "users u ON l.winner_uid = u.uid
        WHERE l.status = 'completed'
        ORDER BY l.draw_time DESC
        LIMIT 20
    ");
    $has_past = false;
    $i = 0;

    while ($lottery = $db->fetch_array($query)) {
        $has_past = true;
        $alt_bg = ($i % 2 == 0) ? 'trow1' : 'trow2';
        $i++;

        $lottery_name = htmlspecialchars_uni($lottery['name']);
        $lottery_draw_time = my_date($mybb->settings['dateformat'], $lottery['draw_time']);

        if ((int)$lottery['winner_uid'] > 0) {
            $lottery_winner = htmlspecialchars_uni($lottery['winner_name'] ?? 'Unknown');
            $pot_pct = (int)$lottery['pot_percentage'];
            if ($pot_pct <= 0 || $pot_pct > 100) $pot_pct = 100;
            $winnings = (int)floor((int)$lottery['total_pot'] * ($pot_pct / 100));
            $lottery_winnings = my_number_format($winnings) . ' ' . $lang->credits;
        } else {
            $lottery_winner = '<em>' . $lang->credits_lottery_no_winner . '</em>';
            $lottery_winnings = '-';
        }

        eval('$past_rows .= "' . $templates->get('credits_lottery_past_row') . '";');
    }

    if (!$has_past) {
        $alt_bg = 'trow1';
        $past_rows = '<tr><td class="trow1" colspan="4" align="center">' . $lang->credits_lottery_no_past . '</td></tr>';
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_lottery_page') . '";');
    return $output;
}

/**
 * Process ticket purchase for a lottery.
 *
 * @return string Evaluated template HTML
 */
function credits_do_buy_ticket(): string
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    if (empty($mybb->settings['credits_lottery_enabled']) || $mybb->settings['credits_lottery_enabled'] != 1) {
        error($lang->credits_lottery_disabled);
    }

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        error_no_permission();
    }

    $lottery_id = $mybb->get_input('lottery_id', MyBB::INPUT_INT);
    if ($lottery_id <= 0) {
        error($lang->credits_lottery_not_found);
    }

    // Get lottery
    $query = $db->simple_select('credits_lottery', '*', "lottery_id = '{$lottery_id}' AND status = 'active'");
    $lottery = $db->fetch_array($query);
    if (!$lottery) {
        error($lang->credits_lottery_not_found);
    }

    // Check max tickets
    $max_tickets = (int)$lottery['max_tickets_per_user'];
    if ($max_tickets > 0) {
        $my_tickets_query = $db->simple_select('credits_lottery_tickets', 'COUNT(*) as cnt', "lottery_id = '{$lottery_id}' AND uid = '{$uid}'");
        $my_tickets = (int)$db->fetch_field($my_tickets_query, 'cnt');
        if ($my_tickets >= $max_tickets) {
            error($lang->credits_lottery_max_reached);
        }
    }

    // Check balance
    $ticket_price = (int)$lottery['ticket_price'];
    if (credits_get($uid) < $ticket_price) {
        error($lang->credits_insufficient);
    }

    // Deduct credits
    credits_subtract($uid, $ticket_price, 'lottery_ticket', $lottery_id);

    // Insert ticket
    $db->insert_query('credits_lottery_tickets', array(
        'lottery_id' => $lottery_id,
        'uid'        => $uid,
        'dateline'   => TIME_NOW,
    ));

    redirect(credits_url('credits', array('view' => 'lottery')), $lang->credits_lottery_ticket_purchased);
}

/**
 * Render the referrals page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_referrals(): string
{
    global $mybb, $db, $lang, $templates, $theme;

    if (empty($mybb->settings['credits_referral_enabled']) || $mybb->settings['credits_referral_enabled'] != 1) {
        error($lang->credits_referral_disabled);
    }

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        error_no_permission();
    }

    // Generate/get referral code
    $referral_code = credits_generate_referral_code($uid);

    // Referral stats
    $query = $db->simple_select('credits_referrals', 'COUNT(*) as total', "referrer_uid = '{$uid}'");
    $total_referred = (int)$db->fetch_field($query, 'total');

    $query = $db->simple_select('credits_referrals', 'COUNT(*) as total', "referrer_uid = '{$uid}' AND rewarded = '1'");
    $total_rewarded = (int)$db->fetch_field($query, 'total');

    // Check if user has already been referred (hide enter form if so)
    $referral_enter_form = '';
    $already_referred = $db->simple_select('credits_referrals', 'referral_id', "referred_uid = '{$uid}'");
    if ($db->num_rows($already_referred) == 0) {
        $referral_enter_form = '<table border="0" cellspacing="' . $theme['borderwidth'] . '" cellpadding="' . $theme['tablespace'] . '" class="tborder">'
            . '<tr><td class="thead" colspan="2"><strong>' . $lang->credits_referral_enter_code . '</strong></td></tr>'
            . '<tr><td class="trow1">'
            . '<form action="misc.php?action=credits&view=referrals" method="post">'
            . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
            . '<input type="hidden" name="do" value="enter_code" />'
            . '<input type="text" name="referral_code" value="" class="textbox" style="width: 200px;" /> '
            . '<input type="submit" class="button" value="' . $lang->credits_referral_submit_code . '" />'
            . '</form>'
            . '</td></tr></table>';
    }

    // Referral history (people I referred)
    $referral_rows = '';
    $query = $db->query("
        SELECT r.*, u.username
        FROM " . TABLE_PREFIX . "credits_referrals r
        LEFT JOIN " . TABLE_PREFIX . "users u ON r.referred_uid = u.uid
        WHERE r.referrer_uid = '{$uid}'
        ORDER BY r.dateline DESC
    ");

    $i = 0;
    $has_rows = false;
    while ($ref = $db->fetch_array($query)) {
        $has_rows = true;
        $alt_bg = ($i % 2 == 0) ? 'trow1' : 'trow2';
        $i++;

        $referred_username = htmlspecialchars_uni($ref['username'] ?? 'Unknown');
        $referral_status = $ref['rewarded'] ? '<span style="color: #090;">' . $lang->credits_referral_rewarded . '</span>' : '<span style="color: #999;">' . $lang->credits_referral_pending . '</span>';
        $referral_date = my_date($mybb->settings['dateformat'], $ref['dateline']);

        eval('$referral_rows .= "' . $templates->get('credits_referral_row') . '";');
    }

    if (!$has_rows) {
        $referral_rows = '<tr><td class="trow1" colspan="3" align="center">' . $lang->credits_referral_no_referrals . '</td></tr>';
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_referrals_page') . '";');
    return $output;
}

/**
 * Process referral code entry.
 *
 * @return string Evaluated template HTML
 */
function credits_do_enter_referral(): string
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    if (empty($mybb->settings['credits_referral_enabled']) || $mybb->settings['credits_referral_enabled'] != 1) {
        error($lang->credits_referral_disabled);
    }

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        error_no_permission();
    }

    // Check if already referred
    $check = $db->simple_select('credits_referrals', 'referral_id', "referred_uid = '{$uid}'");
    if ($db->num_rows($check) > 0) {
        error($lang->credits_referral_already_referred);
    }

    $code = trim($mybb->get_input('referral_code'));
    if (empty($code)) {
        error($lang->credits_referral_invalid_code);
    }

    // Find referrer by code
    $query = $db->simple_select('users', 'uid', "credits_referral_code = '" . $db->escape_string($code) . "'");
    $referrer = $db->fetch_array($query);
    if (!$referrer) {
        error($lang->credits_referral_invalid_code);
    }

    $referrer_uid = (int)$referrer['uid'];

    // Block self-referral
    if ($referrer_uid == $uid) {
        error($lang->credits_referral_self);
    }

    // Create referral record
    $db->insert_query('credits_referrals', array(
        'referrer_uid' => $referrer_uid,
        'referred_uid' => $uid,
        'rewarded'     => 0,
        'dateline'     => TIME_NOW,
    ));

    redirect(credits_url('credits', array('view' => 'referrals')), $lang->credits_referral_code_entered);
}

/**
 * AJAX handler for Credits operations.
 */
function credits_xmlhttp()
{
    global $mybb, $db, $charset, $lang;

    if ($mybb->get_input('action') != 'credits') {
        return;
    }

    if (empty($mybb->settings['credits_enabled'])) {
        xmlhttp_error('Credits system is disabled.');
    }

    verify_post_check($mybb->get_input('my_post_key'));

    header('Content-type: application/json; charset=' . $charset);

    $operation = $mybb->get_input('operation');

    switch ($operation) {
        case 'get_balance':
            if ($mybb->user['uid'] > 0) {
                echo json_encode(array(
                    'success' => true,
                    'balance' => credits_get($mybb->user['uid']),
                ));
            } else {
                echo json_encode(array('success' => false, 'error' => 'Not logged in'));
            }
            break;

        case 'inventory_toggle':
            if ($mybb->user['uid'] <= 0) {
                echo json_encode(array('success' => false, 'error' => 'Not logged in'));
                break;
            }

            require_once CREDITS_PLUGIN_PATH . 'inventory.php';

            $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
            $toggle_action = $mybb->get_input('toggle_action');

            if ($toggle_action === 'activate') {
                $result = credits_inventory_activate($pid);
            } elseif ($toggle_action === 'deactivate') {
                $result = credits_inventory_deactivate($pid);
            } else {
                $result = array('success' => false, 'error' => 'Invalid action');
            }

            echo json_encode($result);
            break;

        case 'inventory_edit':
            if ($mybb->user['uid'] <= 0) {
                echo json_encode(array('success' => false, 'error' => 'Not logged in'));
                break;
            }

            require_once CREDITS_PLUGIN_PATH . 'inventory.php';

            $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
            $new_value = $mybb->get_input('new_value');

            $result = credits_inventory_edit($pid, $new_value);
            echo json_encode($result);
            break;

        default:
            echo json_encode(array('success' => false, 'error' => 'Unknown operation'));
            break;
    }

    exit;
}

/**
 * Who's Online activity detection for Credits pages.
 *
 * @param array $user_activity
 */
function credits_wol_activity(&$user_activity)
{
    // misc.php route
    if (my_strpos($user_activity['location'], 'misc.php') !== false
        && my_strpos($user_activity['location'], 'action=credits') !== false) {
        $user_activity['activity'] = 'credits';
    }
    // Standalone credits.php route
    if (my_strpos($user_activity['location'], 'credits.php') !== false
        && my_strpos($user_activity['location'], 'webhook') === false) {
        $user_activity['activity'] = 'credits';
    }
    // Standalone inventory.php route
    if (my_strpos($user_activity['location'], 'inventory.php') !== false) {
        $user_activity['activity'] = 'credits_inventory';
    }
}

/**
 * Who's Online friendly location string.
 *
 * @param array $plugin_array
 */
function credits_wol_location(&$plugin_array)
{
    global $lang;

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    if ($plugin_array['user_activity']['activity'] == 'credits') {
        $plugin_array['location_name'] = $lang->sprintf($lang->credits_wol_viewing, credits_url('credits'));
    }
    if ($plugin_array['user_activity']['activity'] == 'credits_inventory') {
        $plugin_array['location_name'] = $lang->sprintf($lang->credits_wol_inventory, credits_url('inventory'));
    }
}

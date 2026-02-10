<?php
/**
 * Credits - Core Functions
 *
 * Provides credit manipulation, logging, booster checks, and utility functions.
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

/**
 * Add credits to a user's balance, applying any active booster.
 *
 * @param int    $uid          User ID
 * @param int    $amount       Amount to add (positive)
 * @param string $action       Action type (post, thread, rep, login, admin_adjust)
 * @param int    $reference_id Related object ID (post ID, thread ID, etc.)
 * @return bool
 */
function credits_add(int $uid, int $amount, string $action, int $reference_id = 0): bool
{
    global $db;

    if ($amount <= 0 || $uid <= 0) {
        return false;
    }

    // Apply booster multiplier for organic earning actions only
    if (!in_array($action, array('purchase', 'admin_adjust'))) {
        $booster = credits_get_active_booster($uid);
        if ($booster) {
            $amount = (int)($amount * $booster['multiplier']);
        }
    }

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "users
        SET credits = credits + {$amount}
        WHERE uid = '{$uid}'
    ");

    $new_balance = credits_get($uid);
    credits_log($uid, $action, $amount, $new_balance, $reference_id);

    return true;
}

/**
 * Subtract credits from a user's balance.
 *
 * @param int    $uid          User ID
 * @param int    $amount       Amount to subtract (positive number)
 * @param string $action       Action type (purchase, admin_adjust)
 * @param int    $reference_id Related object ID
 * @return bool False if insufficient balance or invalid input
 */
function credits_subtract(int $uid, int $amount, string $action, int $reference_id = 0): bool
{
    global $db;

    if ($amount <= 0 || $uid <= 0) {
        return false;
    }

    $current = credits_get($uid);
    if ($current < $amount) {
        return false;
    }

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "users
        SET credits = credits - {$amount}
        WHERE uid = '{$uid}' AND credits >= {$amount}
    ");

    if ($db->affected_rows() == 0) {
        return false;
    }

    $new_balance = credits_get($uid);
    credits_log($uid, $action, -$amount, $new_balance, $reference_id);

    return true;
}

/**
 * Get a user's current credit balance.
 *
 * @param int $uid User ID
 * @return int
 */
function credits_get(int $uid): int
{
    global $db;

    $query = $db->simple_select('users', 'credits', "uid = '{$uid}'");
    $credits = $db->fetch_field($query, 'credits');

    return (int)$credits;
}

/**
 * Set a user's credit balance to a specific amount.
 *
 * @param int    $uid    User ID
 * @param int    $amount New balance
 * @param string $reason Action string for the log
 * @return bool
 */
function credits_set(int $uid, int $amount, string $reason = 'admin_adjust'): bool
{
    global $db;

    if ($uid <= 0 || $amount < 0) {
        return false;
    }

    $old_balance = credits_get($uid);
    $difference = $amount - $old_balance;

    $db->update_query('users', array('credits' => $amount), "uid = '{$uid}'");

    credits_log($uid, $reason, $difference, $amount, 0);

    return true;
}

/**
 * Record a credit transaction in the log.
 *
 * @param int    $uid          User ID
 * @param string $action       Action type
 * @param int    $amount       Signed amount (+/-)
 * @param int    $balance      Balance after transaction
 * @param int    $reference_id Related object ID
 */
function credits_log(int $uid, string $action, int $amount, int $balance, int $reference_id = 0): void
{
    global $db;

    $log_entry = array(
        'uid'          => $uid,
        'action'       => $db->escape_string($action),
        'amount'       => $amount,
        'balance'      => $balance,
        'reference_id' => $reference_id,
        'dateline'     => TIME_NOW,
    );

    $db->insert_query('credits_log', $log_entry);
}

/**
 * Generate a credits URL, preferring clean URLs when standalone files exist.
 *
 * @param string $page   Page name ('credits' or 'inventory')
 * @param array  $params Additional query parameters (e.g. ['view' => 'shop'])
 * @return string URL
 */
function credits_url(string $page = 'credits', array $params = array()): string
{
    static $has_standalone = null;
    if ($has_standalone === null) {
        $has_standalone = array(
            'credits'   => file_exists(MYBB_ROOT . 'credits.php'),
            'inventory' => file_exists(MYBB_ROOT . 'inventory.php'),
        );
    }

    if ($page === 'inventory' && !empty($has_standalone['inventory'])) {
        $base = 'inventory.php';
    } elseif ($page === 'credits' && !empty($has_standalone['credits'])) {
        $base = 'credits.php';
    } else {
        $base = 'misc.php';
        $params = array_merge(array('action' => 'credits'), $params);
        if ($page === 'inventory') {
            $params['view'] = 'inventory';
        }
    }

    if (!empty($params)) {
        $base .= '?' . http_build_query($params);
    }

    return $base;
}

/**
 * Format a credit amount for display with sign indicator.
 *
 * @param int $amount
 * @return string
 */
function credits_format(int $amount): string
{
    if ($amount > 0) {
        return '+' . my_number_format($amount);
    } elseif ($amount < 0) {
        return my_number_format($amount);
    }
    return '0';
}

/**
 * Get a human-readable action name from action code.
 *
 * @param string $action
 * @return string
 */
function credits_action_name(string $action): string
{
    global $lang;

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    $actions = array(
        'post'           => $lang->credits_action_post,
        'thread'         => $lang->credits_action_thread,
        'rep'            => $lang->credits_action_rep,
        'login'          => $lang->credits_action_login,
        'purchase'       => $lang->credits_action_purchase,
        'purchase_bonus' => $lang->credits_action_purchase_bonus ?? 'Purchase Bonus',
        'achievement'    => $lang->credits_action_achievement ?? 'Achievement',
        'lottery'        => $lang->credits_action_lottery ?? 'Lottery',
        'referral'       => $lang->credits_action_referral ?? 'Referral Reward',
        'admin_adjust'   => $lang->credits_action_admin,
        'gift_sent'      => $lang->credits_action_gift_sent,
        'gift_received'  => $lang->credits_action_gift_received,
        'payment'        => $lang->credits_action_payment,
    );

    if (isset($actions[$action])) {
        return $actions[$action];
    }

    // Check third-party registered actions
    global $credits_custom_actions;
    if (!empty($credits_custom_actions[$action])) {
        return $credits_custom_actions[$action];
    }

    return $action;
}

/**
 * Get a user's currently active booster, if any.
 *
 * @param int $uid User ID
 * @return array|null Booster data with 'multiplier' key, or null if none active
 */
function credits_get_active_booster(int $uid): ?array
{
    global $db;

    // Check standard booster purchases first
    $query = $db->query("
        SELECT p.pid, p.expires, s.data
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.uid = '{$uid}'
          AND p.active = '1'
          AND s.type = 'booster'
          AND p.expires > " . TIME_NOW . "
        ORDER BY p.dateline DESC
        LIMIT 1
    ");

    $result = $db->fetch_array($query);
    if ($result) {
        $data = json_decode($result['data'], true);
        if ($data && isset($data['multiplier'])) {
            return array(
                'pid'        => (int)$result['pid'],
                'multiplier' => (int)$data['multiplier'],
                'expires'    => (int)$result['expires'],
            );
        }
    }

    // Check bonus boosters (from usergroup purchases, achievements, referrals)
    $query = $db->query("
        SELECT p.pid, p.expires, p.value, s.data
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.uid = '{$uid}'
          AND p.active = '1'
          AND (p.value = 'bonus_booster' OR p.value LIKE 'achievement_booster:%' OR p.value LIKE 'referral_booster:%')
          AND p.expires > " . TIME_NOW . "
        ORDER BY p.dateline DESC
        LIMIT 1
    ");

    $result = $db->fetch_array($query);
    if ($result) {
        $data = json_decode($result['data'], true);
        $multiplier = 0;

        // For bonus boosters, the multiplier is stored in the item's JSON data
        if ($result['value'] == 'bonus_booster' && isset($data['bonus_booster_multiplier'])) {
            $multiplier = (int)$data['bonus_booster_multiplier'];
        } elseif (strpos($result['value'], 'achievement_booster:') === 0) {
            $multiplier = (int)substr($result['value'], strlen('achievement_booster:'));
        } elseif (strpos($result['value'], 'referral_booster:') === 0) {
            $multiplier = (int)substr($result['value'], strlen('referral_booster:'));
        }

        // Fallback: try generic multiplier field
        if ($multiplier <= 0 && isset($data['multiplier'])) {
            $multiplier = (int)$data['multiplier'];
        }

        if ($multiplier > 0) {
            return array(
                'pid'        => (int)$result['pid'],
                'multiplier' => $multiplier,
                'expires'    => (int)$result['expires'],
            );
        }
    }

    return null;
}

/**
 * Get all active award image paths for a user.
 *
 * @param int $uid User ID
 * @return array Array of image path strings
 */
function credits_get_user_awards(int $uid): array
{
    global $db;

    $awards = array();

    $query = $db->query("
        SELECT s.data
        FROM " . TABLE_PREFIX . "credits_purchases p
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        WHERE p.uid = '{$uid}'
          AND p.active = '1'
          AND s.type = 'award'
        ORDER BY p.dateline ASC
    ");

    while ($row = $db->fetch_array($query)) {
        $item_data = json_decode($row['data'], true);
        if (!empty($item_data['image'])) {
            $awards[] = $item_data['image'];
        }
    }

    return $awards;
}

/**
 * Rebuild the cached awards JSON on the users table.
 *
 * @param int $uid User ID
 */
function credits_rebuild_user_awards(int $uid): void
{
    global $db;

    $awards = credits_get_user_awards($uid);
    $json = !empty($awards) ? json_encode($awards) : '';

    $db->update_query('users', array(
        'credits_awards' => $db->escape_string($json),
    ), "uid = '{$uid}'");
}

/**
 * Gift credits from one user to another.
 *
 * @param int    $from_uid Sender user ID
 * @param int    $to_uid   Recipient user ID
 * @param int    $amount   Credits to transfer
 * @param string $message  Optional message
 * @return bool
 */
function credits_gift_credits(int $from_uid, int $to_uid, int $amount, string $message = ''): bool
{
    global $db;

    if ($amount <= 0 || $from_uid <= 0 || $to_uid <= 0) {
        return false;
    }

    // Check sender balance
    if (credits_get($from_uid) < $amount) {
        return false;
    }

    // Deduct from sender
    if (!credits_subtract($from_uid, $amount, 'gift_sent', $to_uid)) {
        return false;
    }

    // Add to receiver
    credits_add_direct($to_uid, $amount, 'gift_received', $from_uid);

    // Record in gifts table
    $gift_data = array(
        'from_uid' => $from_uid,
        'to_uid'   => $to_uid,
        'type'     => 'credits',
        'amount'   => $amount,
        'iid'      => 0,
        'message'  => $db->escape_string($message),
        'dateline' => TIME_NOW,
    );
    $db->insert_query('credits_gifts', $gift_data);

    // Send PM notification
    credits_send_gift_pm($from_uid, $to_uid, 'credits', $amount, '', $message);

    return true;
}

/**
 * Gift a shop item from one user to another.
 *
 * @param int    $from_uid Sender user ID
 * @param int    $to_uid   Recipient user ID
 * @param int    $iid      Shop item ID
 * @param string $message  Optional message
 * @return bool
 */
function credits_gift_item(int $from_uid, int $to_uid, int $iid, string $message = ''): bool
{
    global $db;

    if ($from_uid <= 0 || $to_uid <= 0 || $iid <= 0) {
        return false;
    }

    // Get item
    $query = $db->simple_select('credits_shop', '*', "iid = '{$iid}' AND active = '1'");
    $item = $db->fetch_array($query);

    if (!$item) {
        return false;
    }

    $price = (int)$item['price'];

    // Check sender balance
    if (credits_get($from_uid) < $price) {
        return false;
    }

    // Deduct from sender
    if (!credits_subtract($from_uid, $price, 'gift_sent', $iid)) {
        return false;
    }

    // Get purchase value based on item type
    $purchase_value = '';
    $item_data = json_decode($item['data'], true) ?: array();

    switch ($item['type']) {
        case 'icon':
        case 'award':
            $purchase_value = $item_data['image'] ?? '';
            break;
        case 'username_effect':
            $purchase_value = $item_data['effect'] ?? '';
            break;
        case 'postbit_bg':
            $purchase_value = $item_data['bg_value'] ?? '';
            break;
        case 'booster':
            // Handled via expires
            break;
        case 'usergroup':
            // Handled separately
            break;
    }

    $expires = 0;
    $gift_active = 1;

    if ($item['type'] == 'booster') {
        // Gifted boosters go to inventory inactive — recipient chooses when to activate
        $gift_active = 0;
        $expires = 0;
    }

    // Deactivate previous same-type purchase for recipient (except boosters, awards, and inactive gifts)
    if ($gift_active && !in_array($item['type'], array('booster', 'award'))) {
        $db->write_query("
            UPDATE " . TABLE_PREFIX . "credits_purchases p
            INNER JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
            SET p.active = 0
            WHERE p.uid = '{$to_uid}'
              AND p.active = '1'
              AND s.type = '" . $db->escape_string($item['type']) . "'
        ");
    }

    // Create purchase for recipient
    $purchase_data = array(
        'uid'      => $to_uid,
        'iid'      => $iid,
        'value'    => $db->escape_string($purchase_value),
        'dateline' => TIME_NOW,
        'expires'  => $expires,
        'active'   => $gift_active,
    );
    $db->insert_query('credits_purchases', $purchase_data);

    // Apply purchase to recipient (only if active)
    if ($gift_active) {
        require_once CREDITS_PLUGIN_PATH . 'shop.php';
        credits_apply_purchase($to_uid, $item['type'], $purchase_value);
    }

    // Record in gifts table
    $gift_data = array(
        'from_uid' => $from_uid,
        'to_uid'   => $to_uid,
        'type'     => 'item',
        'amount'   => 0,
        'iid'      => $iid,
        'message'  => $db->escape_string($message),
        'dateline' => TIME_NOW,
    );
    $db->insert_query('credits_gifts', $gift_data);

    // Send PM notification
    credits_send_gift_pm($from_uid, $to_uid, 'item', 0, $item['name'], $message);

    return true;
}

/**
 * Add credits directly without booster multiplier.
 * Used for gifts and payment fulfillment.
 *
 * @param int    $uid          User ID
 * @param int    $amount       Amount to add
 * @param string $action       Action type
 * @param int    $reference_id Reference ID
 * @return bool
 */
function credits_add_direct(int $uid, int $amount, string $action, int $reference_id = 0): bool
{
    global $db;

    if ($amount <= 0 || $uid <= 0) {
        return false;
    }

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "users
        SET credits = credits + {$amount}
        WHERE uid = '{$uid}'
    ");

    $new_balance = credits_get($uid);
    credits_log($uid, $action, $amount, $new_balance, $reference_id);

    return true;
}

/**
 * Send a PM notification for a gift.
 *
 * @param int    $from_uid  Sender UID
 * @param int    $to_uid    Recipient UID
 * @param string $type      Gift type (credits/item)
 * @param int    $amount    Credits amount (for credit gifts)
 * @param string $item_name Item name (for item gifts)
 * @param string $message   Optional message
 */
function credits_send_gift_pm(int $from_uid, int $to_uid, string $type, int $amount, string $item_name, string $message): void
{
    global $db, $lang;

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    // Get sender username
    $query = $db->simple_select('users', 'username', "uid = '{$from_uid}'");
    $sender = $db->fetch_field($query, 'username');

    if ($type == 'credits') {
        $subject = $lang->credits_gift_pm_subject_credits;
        $body = $lang->sprintf($lang->credits_gift_pm_body_credits, $sender, my_number_format($amount));
    } else {
        $subject = $lang->credits_gift_pm_subject_item;
        $body = $lang->sprintf($lang->credits_gift_pm_body_item, $sender, $item_name);
    }

    if (!empty($message)) {
        $body .= "\n\n" . $lang->credits_gift_pm_message . "\n" . $message;
    }

    require_once MYBB_ROOT . 'inc/datahandlers/pm.php';
    $pmhandler = new PMDataHandler();

    // Get recipient username
    $query = $db->simple_select('users', 'username', "uid = '{$to_uid}'");
    $to_username = $db->fetch_field($query, 'username');

    $pm = array(
        'subject'    => $subject,
        'message'    => $body,
        'fromid'     => 0,
        'toid'       => array($to_uid),
        'icon'       => 0,
        'do'         => '',
        'pmid'       => '',
        'options'    => array(
            'signature'   => 0,
            'disablesmilies' => 0,
            'savecopy'    => 0,
            'readreceipt' => 0,
        ),
        'saveasdraft' => 0,
    );

    $pm['to'] = array($to_username);

    $pmhandler->set_data($pm);
    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();
    }
}

/**
 * Add user to an additional usergroup.
 *
 * @param int $uid User ID
 * @param int $gid Group ID
 */
function credits_add_usergroup(int $uid, int $gid): void
{
    global $db;

    $query = $db->simple_select('users', 'additionalgroups', "uid = '{$uid}'");
    $user = $db->fetch_array($query);

    if (!$user) {
        return;
    }

    $groups = array_filter(explode(',', $user['additionalgroups']));
    $groups = array_map('intval', $groups);

    if (!in_array($gid, $groups)) {
        $groups[] = $gid;
    }

    $new_groups = implode(',', $groups);

    $db->update_query('users', array(
        'additionalgroups' => $db->escape_string($new_groups),
    ), "uid = '{$uid}'");
}

/**
 * Remove user from an additional usergroup.
 *
 * @param int $uid User ID
 * @param int $gid Group ID
 */
function credits_remove_usergroup(int $uid, int $gid): void
{
    global $db;

    $query = $db->simple_select('users', 'additionalgroups', "uid = '{$uid}'");
    $user = $db->fetch_array($query);

    if (!$user) {
        return;
    }

    $groups = array_filter(explode(',', $user['additionalgroups']));
    $groups = array_map('intval', $groups);
    $groups = array_diff($groups, array($gid));
    $new_groups = implode(',', $groups);

    $db->update_query('users', array(
        'additionalgroups' => $db->escape_string($new_groups),
    ), "uid = '{$uid}'");
}

/**
 * Check if a user is exempt from seeing credit-purchased ads.
 *
 * @param array $user User data array (must contain uid, usergroup, additionalgroups)
 * @return bool
 */
function credits_user_is_ad_exempt(array $user): bool
{
    global $mybb;

    $exempt_setting = trim($mybb->settings['credits_ads_exempt_groups'] ?? '');
    if (empty($exempt_setting)) {
        return false;
    }

    $exempt_groups = array_map('intval', explode(',', $exempt_setting));
    $exempt_groups = array_filter($exempt_groups);

    if (empty($exempt_groups)) {
        return false;
    }

    // Check primary group
    if (in_array((int)$user['usergroup'], $exempt_groups)) {
        return true;
    }

    // Check additional groups
    if (!empty($user['additionalgroups'])) {
        $additional = array_map('intval', explode(',', $user['additionalgroups']));
        if (array_intersect($additional, $exempt_groups)) {
            return true;
        }
    }

    return false;
}

/**
 * Get active ads for a position.
 *
 * @param string $position Ad position
 * @return array
 */
function credits_get_active_ads(string $position): array
{
    global $db;

    $now = TIME_NOW;
    $position = $db->escape_string($position);

    $query = $db->simple_select('credits_ads', '*',
        "position = '{$position}' AND active = '1' AND (expires = 0 OR expires > {$now})",
        array('order_by' => 'RAND()')
    );

    $ads = array();
    while ($ad = $db->fetch_array($query)) {
        $ads[] = $ad;
    }

    return $ads;
}

/**
 * Increment view count for an ad.
 *
 * @param int $ad_id Ad ID
 */
function credits_record_ad_view(int $ad_id): void
{
    global $db;
    $db->write_query("UPDATE " . TABLE_PREFIX . "credits_ads SET views = views + 1 WHERE ad_id = '{$ad_id}'");
}

/**
 * Increment click count for an ad.
 *
 * @param int $ad_id Ad ID
 */
function credits_record_ad_click(int $ad_id): void
{
    global $db;
    $db->write_query("UPDATE " . TABLE_PREFIX . "credits_ads SET clicks = clicks + 1 WHERE ad_id = '{$ad_id}'");
}

/**
 * Get a user's current stat value for a given achievement type.
 *
 * @param int    $uid  User ID
 * @param string $type Achievement type
 * @return int
 */
function credits_get_user_stat(int $uid, string $type): int
{
    global $db;

    switch ($type) {
        case 'posts':
            $query = $db->simple_select('users', 'postnum', "uid = '{$uid}'");
            return (int)$db->fetch_field($query, 'postnum');
        case 'threads':
            $query = $db->query("SELECT COUNT(*) as cnt FROM " . TABLE_PREFIX . "threads WHERE uid = '{$uid}'");
            return (int)$db->fetch_field($query, 'cnt');
        case 'reputation':
            $query = $db->simple_select('users', 'reputation', "uid = '{$uid}'");
            return (int)$db->fetch_field($query, 'reputation');
        case 'reg_days':
            $query = $db->simple_select('users', 'regdate', "uid = '{$uid}'");
            $regdate = (int)$db->fetch_field($query, 'regdate');
            return $regdate > 0 ? (int)floor((TIME_NOW - $regdate) / 86400) : 0;
        case 'purchases':
            $query = $db->query("SELECT COUNT(*) as cnt FROM " . TABLE_PREFIX . "credits_purchases WHERE uid = '{$uid}'");
            return (int)$db->fetch_field($query, 'cnt');
        case 'credits_earned':
            $query = $db->simple_select('users', 'credits', "uid = '{$uid}'");
            return (int)$db->fetch_field($query, 'credits');
        default:
            return 0;
    }
}

/**
 * Check and grant achievements for a user based on a specific stat type.
 *
 * @param int    $uid  User ID
 * @param string $type Achievement type (posts, threads, reputation, reg_days, purchases, credits_earned)
 */
function credits_check_achievements(int $uid, string $type): void
{
    global $db, $mybb;

    if (empty($mybb->settings['credits_achievements_enabled']) || $mybb->settings['credits_achievements_enabled'] != 1) {
        return;
    }

    // Get user stat for this achievement type
    $user_stat = 0;
    switch ($type) {
        case 'posts':
            $query = $db->simple_select('users', 'postnum', "uid = '{$uid}'");
            $user_stat = (int)$db->fetch_field($query, 'postnum');
            break;
        case 'threads':
            $query = $db->query("SELECT COUNT(*) as cnt FROM " . TABLE_PREFIX . "threads WHERE uid = '{$uid}'");
            $user_stat = (int)$db->fetch_field($query, 'cnt');
            break;
        case 'reputation':
            $query = $db->simple_select('users', 'reputation', "uid = '{$uid}'");
            $user_stat = (int)$db->fetch_field($query, 'reputation');
            break;
        case 'reg_days':
            $query = $db->simple_select('users', 'regdate', "uid = '{$uid}'");
            $regdate = (int)$db->fetch_field($query, 'regdate');
            $user_stat = $regdate > 0 ? (int)floor((TIME_NOW - $regdate) / 86400) : 0;
            break;
        case 'purchases':
            $query = $db->query("SELECT COUNT(*) as cnt FROM " . TABLE_PREFIX . "credits_purchases WHERE uid = '{$uid}'");
            $user_stat = (int)$db->fetch_field($query, 'cnt');
            break;
        case 'credits_earned':
            $query = $db->simple_select('users', 'credits', "uid = '{$uid}'");
            $user_stat = (int)$db->fetch_field($query, 'credits');
            break;
        default:
            return;
    }

    // Find unearned achievements that the user qualifies for
    $type_escaped = $db->escape_string($type);
    $query = $db->query("
        SELECT a.*
        FROM " . TABLE_PREFIX . "credits_achievements a
        LEFT JOIN " . TABLE_PREFIX . "credits_user_achievements ua
            ON ua.aid = a.aid AND ua.uid = '{$uid}'
        WHERE a.active = '1'
          AND a.type = '{$type_escaped}'
          AND a.threshold <= {$user_stat}
          AND ua.ua_id IS NULL
        ORDER BY a.threshold ASC
    ");

    while ($achievement = $db->fetch_array($query)) {
        $aid = (int)$achievement['aid'];

        // Grant the achievement (INSERT IGNORE for idempotency)
        $db->write_query("
            INSERT IGNORE INTO " . TABLE_PREFIX . "credits_user_achievements (uid, aid, dateline)
            VALUES ('{$uid}', '{$aid}', " . TIME_NOW . ")
        ");

        if ($db->affected_rows() > 0) {
            // Grant reward credits
            $reward_credits = (int)$achievement['reward_credits'];
            if ($reward_credits > 0) {
                credits_add_direct($uid, $reward_credits, 'achievement', $aid);
            }

            // Grant reward booster
            $reward_mult = (int)$achievement['reward_booster_multiplier'];
            $reward_dur = (int)$achievement['reward_booster_duration'];
            if ($reward_mult > 0 && $reward_dur > 0) {
                $booster_purchase = array(
                    'uid'      => $uid,
                    'iid'      => 0,
                    'value'    => 'achievement_booster:' . $reward_mult,
                    'dateline' => TIME_NOW,
                    'expires'  => TIME_NOW + $reward_dur,
                    'active'   => 1,
                );
                $db->insert_query('credits_purchases', $booster_purchase);
            }
        }
    }
}

/**
 * Format a duration in seconds into a human-readable string.
 *
 * @param int $seconds
 * @return string
 */
function credits_format_duration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . 'm';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    } else {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
    }
}

/**
 * Send a PM to the lottery winner.
 *
 * @param int    $uid          Winner's user ID
 * @param string $lottery_name Lottery name
 * @param int    $winnings     Amount won
 */
function credits_send_lottery_pm(int $uid, string $lottery_name, int $winnings): void
{
    global $db, $lang;

    if (!isset($lang->credits)) {
        $lang->load('credits');
    }

    $subject = $lang->credits_lottery_pm_subject;
    $body = $lang->sprintf(
        $lang->credits_lottery_pm_body,
        htmlspecialchars_uni($lottery_name),
        my_number_format($winnings)
    );

    require_once MYBB_ROOT . 'inc/datahandlers/pm.php';
    $pmhandler = new PMDataHandler();

    $query = $db->simple_select('users', 'username', "uid = '{$uid}'");
    $to_username = $db->fetch_field($query, 'username');

    $pm = array(
        'subject'    => $subject,
        'message'    => $body,
        'fromid'     => 0,
        'toid'       => array($uid),
        'icon'       => 0,
        'do'         => '',
        'pmid'       => '',
        'options'    => array(
            'signature'      => 0,
            'disablesmilies' => 0,
            'savecopy'       => 0,
            'readreceipt'    => 0,
        ),
        'saveasdraft' => 0,
    );

    $pm['recipients'] = array(
        $uid => array(
            'uid'      => $uid,
            'username' => $to_username,
        ),
    );

    $pmhandler->set_data($pm);
    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();
    }
}

/**
 * Generate a unique referral code for a user.
 *
 * @param int $uid User ID
 * @return string The generated referral code
 */
function credits_generate_referral_code(int $uid): string
{
    global $db;

    // Check if user already has a code
    $query = $db->simple_select('users', 'credits_referral_code', "uid = '{$uid}'");
    $existing = $db->fetch_field($query, 'credits_referral_code');
    if (!empty($existing)) {
        return $existing;
    }

    // Generate unique 12-char hex code
    do {
        $code = bin2hex(random_bytes(6)); // 12 hex chars
        $check = $db->simple_select('users', 'uid', "credits_referral_code = '" . $db->escape_string($code) . "'");
    } while ($db->num_rows($check) > 0);

    $db->update_query('users', array('credits_referral_code' => $db->escape_string($code)), "uid = '{$uid}'");

    return $code;
}

/**
 * Check if a referred user has met the minimum post threshold and grant reward.
 *
 * @param int $referred_uid The referred user's ID (the one making posts)
 */
function credits_check_referral_reward(int $referred_uid): void
{
    global $mybb, $db;

    if (empty($mybb->settings['credits_referral_enabled']) || $mybb->settings['credits_referral_enabled'] != 1) {
        return;
    }

    $min_posts = (int)$mybb->settings['credits_referral_min_posts'];
    if ($min_posts <= 0) {
        return;
    }

    // Check if this user was referred and hasn't been rewarded yet
    $query = $db->simple_select('credits_referrals', '*', "referred_uid = '{$referred_uid}' AND rewarded = '0'");
    $referral = $db->fetch_array($query);
    if (!$referral) {
        return;
    }

    // Check if referred user has enough posts
    $post_query = $db->simple_select('users', 'postnum', "uid = '{$referred_uid}'");
    $postnum = (int)$db->fetch_field($post_query, 'postnum');
    if ($postnum < $min_posts) {
        return;
    }

    $referrer_uid = (int)$referral['referrer_uid'];
    $referral_id = (int)$referral['referral_id'];

    // Mark as rewarded
    $db->update_query('credits_referrals', array('rewarded' => 1), "referral_id = '{$referral_id}'");

    // Grant reward credits
    $reward = (int)$mybb->settings['credits_referral_reward'];
    if ($reward > 0) {
        credits_add_direct($referrer_uid, $reward, 'referral', $referred_uid);
    }

    // Grant booster if configured
    $booster_mult = (int)$mybb->settings['credits_referral_booster_multiplier'];
    $booster_dur = (int)$mybb->settings['credits_referral_booster_duration'];
    if ($booster_mult > 0 && $booster_dur > 0) {
        $booster_purchase = array(
            'uid'      => $referrer_uid,
            'iid'      => 0,
            'value'    => 'referral_booster:' . $booster_mult,
            'dateline' => TIME_NOW,
            'expires'  => TIME_NOW + $booster_dur,
            'active'   => 1,
        );
        $db->insert_query('credits_purchases', $booster_purchase);
    }
}

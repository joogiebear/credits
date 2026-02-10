<?php

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

function credits_page_gift(): string
{
    global $mybb, $db, $templates, $lang, $theme, $credits_base_url;

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    if ($mybb->settings['credits_gifting_enabled'] != 1) {
        error($lang->credits_gifting_disabled);
    }

    $min_posts = (int)$mybb->settings['credits_gifting_min_posts'];
    if ($mybb->user['postnum'] < $min_posts) {
        error($lang->sprintf($lang->credits_gift_min_posts, $min_posts));
    }

    $gift_form = '';
    eval('$gift_form = "' . $templates->get('credits_gift_form') . '";');

    $uid = (int)$mybb->user['uid'];
    $query = $db->query("
        SELECT g.*, u_from.username AS from_username, u_to.username AS to_username, s.name AS item_name
        FROM " . TABLE_PREFIX . "credits_gifts g
        LEFT JOIN " . TABLE_PREFIX . "users u_from ON g.from_uid = u_from.uid
        LEFT JOIN " . TABLE_PREFIX . "users u_to ON g.to_uid = u_to.uid
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON g.iid = s.iid
        WHERE g.from_uid = '{$uid}' OR g.to_uid = '{$uid}'
        ORDER BY g.dateline DESC
        LIMIT 50
    ");

    $gift_rows = '';
    if ($db->num_rows($query) > 0) {
        while ($gift = $db->fetch_array($query)) {
            $alt_bg = alt_trow();

            if ((int)$gift['from_uid'] == $uid) {
                $gift_direction = $lang->credits_gift_sent;
                $gift_user = htmlspecialchars_uni($gift['to_username'] ?? 'Unknown');
            } else {
                $gift_direction = $lang->credits_gift_received;
                $gift_user = htmlspecialchars_uni($gift['from_username'] ?? 'Unknown');
            }

            if ($gift['type'] == 'credits') {
                $gift_type_display = $lang->credits;
                $gift_amount_display = my_number_format($gift['amount']);
            } else {
                $gift_type_display = $lang->credits_gift_item;
                $gift_amount_display = htmlspecialchars_uni($gift['item_name'] ?? '');
            }

            $gift_date = my_date($mybb->settings['dateformat'] . ', ' . $mybb->settings['timeformat'], $gift['dateline']);

            eval('$gift_rows .= "' . $templates->get('credits_gift_log_row') . '";');
        }
    } else {
        eval('$gift_rows = "' . $templates->get('credits_gift_log_empty') . '";');
    }

    $gift_log = '';
    eval('$gift_log = "' . $templates->get('credits_gift_log') . '";');

    return $gift_form . $gift_log;
}

function credits_do_gift(): string
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    if ($mybb->settings['credits_gifting_enabled'] != 1) {
        error($lang->credits_gifting_disabled);
    }

    $min_posts = (int)$mybb->settings['credits_gifting_min_posts'];
    if ($mybb->user['postnum'] < $min_posts) {
        error($lang->sprintf($lang->credits_gift_min_posts, $min_posts));
    }

    $from_uid = (int)$mybb->user['uid'];
    $to_username = trim($mybb->get_input('to_username'));

    if (empty($to_username)) {
        error($lang->credits_gift_no_user);
    }

    $query = $db->simple_select('users', 'uid, username', "username = '" . $db->escape_string($to_username) . "'");
    $recipient = $db->fetch_array($query);

    if (!$recipient) {
        error($lang->credits_gift_user_not_found);
    }

    $to_uid = (int)$recipient['uid'];

    if ($to_uid == $from_uid) {
        error($lang->credits_gift_self_error);
    }

    $amount = $mybb->get_input('gift_amount', MyBB::INPUT_INT);
    if ($amount <= 0) {
        error($lang->credits_gift_invalid_amount);
    }

    if (!credits_gift_credits($from_uid, $to_uid, $amount, $mybb->get_input('gift_message'))) {
        error($lang->credits_gift_insufficient);
    }

    redirect(credits_url('credits', array('view' => 'gift')), $lang->credits_gift_success);
    exit;
}

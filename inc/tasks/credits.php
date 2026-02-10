<?php

function task_credits($task)
{
    global $db;

    $now = TIME_NOW;

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "credits_purchases p
        INNER JOIN " . TABLE_PREFIX . "credits_shop s ON p.iid = s.iid
        SET p.active = 0
        WHERE p.active = '1'
          AND s.type = 'booster'
          AND p.expires > 0
          AND p.expires < {$now}
    ");

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "credits_purchases
        SET active = 0
        WHERE active = '1'
          AND (value = 'bonus_booster' OR value LIKE 'achievement_booster:%' OR value LIKE 'referral_booster:%')
          AND expires > 0
          AND expires < {$now}
    ");

    $query = $db->query("
        SELECT sub_id, uid, gid
        FROM " . TABLE_PREFIX . "credits_usergroup_subs
        WHERE active = '1'
          AND expires > 0
          AND expires < {$now}
    ");

    while ($sub = $db->fetch_array($query)) {
        credits_remove_usergroup((int)$sub['uid'], (int)$sub['gid']);

        $db->update_query('credits_usergroup_subs', array('active' => 0), "sub_id = '{$sub['sub_id']}'");
    }

    $db->write_query("
        UPDATE " . TABLE_PREFIX . "credits_ads
        SET active = 0
        WHERE active = '1'
          AND expires > 0
          AND expires < {$now}
    ");

    $lottery_query = $db->query("
        SELECT *
        FROM " . TABLE_PREFIX . "credits_lottery
        WHERE status = 'active'
          AND draw_time > 0
          AND draw_time <= {$now}
    ");

    while ($lottery = $db->fetch_array($lottery_query)) {
        $lottery_id = (int)$lottery['lottery_id'];

        $db->write_query("
            UPDATE " . TABLE_PREFIX . "credits_lottery
            SET status = 'drawing'
            WHERE lottery_id = '{$lottery_id}' AND status = 'active'
        ");
        if ($db->affected_rows() == 0) {
            continue;
        }

        $ticket_query = $db->simple_select('credits_lottery_tickets', 'COUNT(*) as cnt', "lottery_id = '{$lottery_id}'");
        $ticket_count = (int)$db->fetch_field($ticket_query, 'cnt');

        if ($ticket_count == 0) {
            $db->update_query('credits_lottery', array(
                'status'    => 'completed',
                'winner_uid' => 0,
                'total_pot'  => 0,
            ), "lottery_id = '{$lottery_id}'");
            continue;
        }

        $total_pot = $ticket_count * (int)$lottery['ticket_price'];
        $pot_percentage = (int)$lottery['pot_percentage'];
        if ($pot_percentage <= 0 || $pot_percentage > 100) $pot_percentage = 100;
        $winnings = (int)floor($total_pot * ($pot_percentage / 100));

        $winner_query = $db->query("
            SELECT uid
            FROM " . TABLE_PREFIX . "credits_lottery_tickets
            WHERE lottery_id = '{$lottery_id}'
            ORDER BY RAND()
            LIMIT 1
        ");
        $winner = $db->fetch_array($winner_query);

        if (!$winner) {
            $db->update_query('credits_lottery', array('status' => 'completed', 'winner_uid' => 0, 'total_pot' => $total_pot), "lottery_id = '{$lottery_id}'");
            continue;
        }

        $winner_uid = (int)$winner['uid'];

        require_once MYBB_ROOT . 'inc/plugins/credits/core.php';
        credits_add_direct($winner_uid, $winnings, 'lottery', $lottery_id);

        $db->update_query('credits_lottery', array(
            'status'     => 'completed',
            'winner_uid' => $winner_uid,
            'total_pot'  => $total_pot,
        ), "lottery_id = '{$lottery_id}'");

        credits_send_lottery_pm($winner_uid, $lottery['name'], $winnings);
    }

    add_task_log($task, 'Credits expiry task completed.');
}

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

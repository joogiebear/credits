<?php
/**
 * Credits - A credit/points system for MyBB 1.8
 *
 * @author joogiebear
 * @version 1.3.0
 * @license MIT
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

// Load sub-modules
define('CREDITS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/credits/');

if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

if (defined('IN_ADMINCP')) {
    require_once CREDITS_PLUGIN_PATH . 'admin.php';
} else {
    require_once CREDITS_PLUGIN_PATH . 'core.php';
    require_once CREDITS_PLUGIN_PATH . 'hooks.php';
}

// ---- Hook Registrations ----

// Global
$plugins->add_hook('global_start', 'credits_global_start');

// Earning hooks
$plugins->add_hook('datahandler_post_insert_post', 'credits_earn_post');
$plugins->add_hook('datahandler_post_insert_thread', 'credits_earn_thread');
$plugins->add_hook('reputation_added', 'credits_earn_reputation');

// Display hooks
$plugins->add_hook('postbit', 'credits_postbit');
$plugins->add_hook('postbit_prev', 'credits_postbit');
$plugins->add_hook('member_profile_end', 'credits_profile');

// Page hooks
$plugins->add_hook('misc_start', 'credits_misc_page');

// AJAX
$plugins->add_hook('xmlhttp', 'credits_xmlhttp');

// Who's Online
$plugins->add_hook('fetch_wol_activity_end', 'credits_wol_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'credits_wol_location');

// ACP module_meta is in admin/modules/credits/ (created by install)

// ---- Plugin Info ----

function credits_info()
{
    return array(
        'name'          => 'Credits',
        'description'   => 'A comprehensive credit/points economy. Earn credits, buy items from the shop, gift credits and items, purchase usergroup access, buy ad space, and accept real-money payments via Coinbase Commerce and Lemon Squeezy.',
        'website'       => 'https://github.com/joogiebear',
        'author'        => 'joogiebear',
        'authorsite'    => 'https://github.com/joogiebear',
        'version'       => '1.3.0',
        'compatibility' => '18*',
        'codename'      => 'credits',
    );
}

// ---- Install / Uninstall ----

function credits_install()
{
    global $db;

    // Create ACP module directory and meta file
    credits_create_admin_module();

    // Create credits_log table
    if (!$db->table_exists('credits_log')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_log (
                lid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                action VARCHAR(50) NOT NULL DEFAULT '',
                amount INT NOT NULL DEFAULT '0',
                balance INT UNSIGNED NOT NULL DEFAULT '0',
                reference_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (lid),
                KEY idx_uid (uid),
                KEY idx_action (action),
                KEY idx_dateline (dateline)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_categories table
    if (!$db->table_exists('credits_categories')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_categories (
                cid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                disporder INT UNSIGNED NOT NULL DEFAULT '0',
                active TINYINT(1) NOT NULL DEFAULT '1',
                visible TINYINT(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (cid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_shop table
    if (!$db->table_exists('credits_shop')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_shop (
                iid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                cid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                name VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT '',
                price INT UNSIGNED NOT NULL DEFAULT '0',
                data TEXT NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT '1',
                disporder INT UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (iid),
                KEY idx_cid (cid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_purchases table
    if (!$db->table_exists('credits_purchases')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_purchases (
                pid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                iid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                value VARCHAR(255) NOT NULL DEFAULT '',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                expires INT(10) UNSIGNED NOT NULL DEFAULT '0',
                active TINYINT(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (pid),
                KEY idx_uid (uid),
                KEY idx_iid (iid),
                KEY idx_expires (expires)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_packs table
    if (!$db->table_exists('credits_packs')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_packs (
                pack_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL DEFAULT '',
                credits INT UNSIGNED NOT NULL DEFAULT '0',
                price_usd DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                active TINYINT(1) NOT NULL DEFAULT '1',
                disporder INT UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (pack_id)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_payments table
    if (!$db->table_exists('credits_payments')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_payments (
                payment_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                gateway VARCHAR(50) NOT NULL DEFAULT '',
                type VARCHAR(50) NOT NULL DEFAULT '',
                reference_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
                external_id VARCHAR(255) NOT NULL DEFAULT '',
                amount_usd DECIMAL(10,2) NOT NULL DEFAULT '0.00',
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (payment_id),
                KEY idx_uid (uid),
                KEY idx_external (external_id),
                KEY idx_status (status)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_gifts table
    if (!$db->table_exists('credits_gifts')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_gifts (
                gift_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                from_uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                to_uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                type VARCHAR(50) NOT NULL DEFAULT '',
                amount INT NOT NULL DEFAULT '0',
                iid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                message TEXT NOT NULL,
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (gift_id),
                KEY idx_from (from_uid),
                KEY idx_to (to_uid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_ads table
    if (!$db->table_exists('credits_ads')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_ads (
                ad_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                pid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                position VARCHAR(50) NOT NULL DEFAULT '',
                content TEXT NOT NULL,
                url VARCHAR(255) NOT NULL DEFAULT '',
                image VARCHAR(255) NOT NULL DEFAULT '',
                views INT UNSIGNED NOT NULL DEFAULT '0',
                clicks INT UNSIGNED NOT NULL DEFAULT '0',
                expires INT(10) UNSIGNED NOT NULL DEFAULT '0',
                active TINYINT(1) NOT NULL DEFAULT '1',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (ad_id),
                KEY idx_position (position, active),
                KEY idx_uid (uid),
                KEY idx_expires (expires)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_usergroup_subs table
    if (!$db->table_exists('credits_usergroup_subs')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_usergroup_subs (
                sub_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                pid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                gid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                expires INT(10) UNSIGNED NOT NULL DEFAULT '0',
                active TINYINT(1) NOT NULL DEFAULT '1',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (sub_id),
                KEY idx_uid (uid),
                KEY idx_expires (expires, active)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_achievements table
    if (!$db->table_exists('credits_achievements')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_achievements (
                aid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT '',
                threshold INT UNSIGNED NOT NULL DEFAULT '0',
                reward_credits INT UNSIGNED NOT NULL DEFAULT '0',
                reward_booster_multiplier INT UNSIGNED NOT NULL DEFAULT '0',
                reward_booster_duration INT UNSIGNED NOT NULL DEFAULT '0',
                icon VARCHAR(255) NOT NULL DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT '1',
                disporder INT UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (aid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_user_achievements table
    if (!$db->table_exists('credits_user_achievements')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_user_achievements (
                ua_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                aid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (ua_id),
                UNIQUE KEY uid_aid (uid, aid),
                KEY idx_uid (uid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_lottery table
    if (!$db->table_exists('credits_lottery')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_lottery (
                lottery_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                ticket_price INT UNSIGNED NOT NULL DEFAULT '0',
                max_tickets_per_user INT UNSIGNED NOT NULL DEFAULT '0',
                pot_percentage INT UNSIGNED NOT NULL DEFAULT '100',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                winner_uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                total_pot INT UNSIGNED NOT NULL DEFAULT '0',
                draw_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
                created INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (lottery_id)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_lottery_tickets table
    if (!$db->table_exists('credits_lottery_tickets')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_lottery_tickets (
                ticket_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                lottery_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
                uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (ticket_id),
                KEY idx_lottery (lottery_id),
                KEY idx_uid (uid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Create credits_referrals table
    if (!$db->table_exists('credits_referrals')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "credits_referrals (
                referral_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                referrer_uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                referred_uid INT(10) UNSIGNED NOT NULL DEFAULT '0',
                rewarded TINYINT(1) NOT NULL DEFAULT '0',
                dateline INT(10) UNSIGNED NOT NULL DEFAULT '0',
                PRIMARY KEY (referral_id),
                UNIQUE KEY referred_uid (referred_uid),
                KEY idx_referrer (referrer_uid)
            ) ENGINE=MyISAM{$collation};
        ");
    }

    // Add columns to users table
    if (!$db->field_exists('credits', 'users')) {
        $db->add_column('users', 'credits', "INT UNSIGNED NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists('credits_last_login_bonus', 'users')) {
        $db->add_column('users', 'credits_last_login_bonus', "INT(10) UNSIGNED NOT NULL DEFAULT '0'");
    }
    if (!$db->field_exists('credits_icon', 'users')) {
        $db->add_column('users', 'credits_icon', "VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!$db->field_exists('credits_username_color', 'users')) {
        $db->add_column('users', 'credits_username_color', "VARCHAR(7) NOT NULL DEFAULT ''");
    }
    if (!$db->field_exists('credits_awards', 'users')) {
        $db->add_column('users', 'credits_awards', "TEXT NOT NULL");
    }
    if (!$db->field_exists('credits_postbit_bg', 'users')) {
        $db->add_column('users', 'credits_postbit_bg', "VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!$db->field_exists('credits_username_effect', 'users')) {
        $db->add_column('users', 'credits_username_effect', "VARCHAR(50) NOT NULL DEFAULT ''");
    }

    // Add price_usd column to credits_shop
    if (!$db->field_exists('price_usd', 'credits_shop')) {
        $db->add_column('credits_shop', 'price_usd', "DECIMAL(10,2) NOT NULL DEFAULT '0.00'");
    }

    // Add stock column to credits_shop (-1 = unlimited, 0 = out of stock, >0 = remaining)
    if (!$db->field_exists('stock', 'credits_shop')) {
        $db->add_column('credits_shop', 'stock', "INT NOT NULL DEFAULT '-1'");
    }

    // Add visible column to credits_categories (for unlisted categories)
    if (!$db->field_exists('visible', 'credits_categories')) {
        $db->add_column('credits_categories', 'visible', "TINYINT(1) NOT NULL DEFAULT '1'");
    }

    // Add referral code column to users
    if (!$db->field_exists('credits_referral_code', 'users')) {
        $db->add_column('users', 'credits_referral_code', "VARCHAR(32) NOT NULL DEFAULT ''");
    }

    // ---- Settings ----
    $setting_group = array(
        'name'        => 'credits_settings',
        'title'       => 'Credits Settings',
        'description' => 'Settings for the Credits plugin.',
        'disporder'   => 50,
        'isdefault'   => 0,
    );
    $gid = $db->insert_query('settinggroups', $setting_group);

    $settings = array(
        array(
            'name'        => 'credits_enabled',
            'title'       => 'Enable Credits System',
            'description' => 'Master switch to enable or disable the credits system.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 1,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_per_post',
            'title'       => 'Credits Per Post',
            'description' => 'Number of credits earned for each new reply.',
            'optionscode' => 'numeric',
            'value'       => '1',
            'disporder'   => 2,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_per_thread',
            'title'       => 'Credits Per Thread',
            'description' => 'Number of credits earned for creating a new thread.',
            'optionscode' => 'numeric',
            'value'       => '2',
            'disporder'   => 3,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_per_rep',
            'title'       => 'Credits Per Reputation',
            'description' => 'Number of credits earned when receiving a positive reputation.',
            'optionscode' => 'numeric',
            'value'       => '1',
            'disporder'   => 4,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_daily_login',
            'title'       => 'Daily Login Bonus',
            'description' => 'Number of credits earned once per day for logging in. Set to 0 to disable.',
            'optionscode' => 'numeric',
            'value'       => '5',
            'disporder'   => 5,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_leaderboard_count',
            'title'       => 'Leaderboard Entries',
            'description' => 'Number of users displayed on the leaderboard page.',
            'optionscode' => 'numeric',
            'value'       => '25',
            'disporder'   => 6,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_shop_enabled',
            'title'       => 'Enable Shop',
            'description' => 'Enable or disable the credits shop.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 7,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_per_page',
            'title'       => 'Items Per Page',
            'description' => 'Number of items displayed per page in the shop and log.',
            'optionscode' => 'numeric',
            'value'       => '20',
            'disporder'   => 8,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_gifting_enabled',
            'title'       => 'Enable Gifting',
            'description' => 'Allow users to gift credits and shop items to other users.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 9,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_gifting_min_posts',
            'title'       => 'Gifting Minimum Posts',
            'description' => 'Minimum number of posts required before a user can send gifts.',
            'optionscode' => 'numeric',
            'value'       => '10',
            'disporder'   => 10,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_coinbase_enabled',
            'title'       => 'Enable Coinbase Commerce',
            'description' => 'Allow cryptocurrency payments via Coinbase Commerce.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 11,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_coinbase_api_key',
            'title'       => 'Coinbase Commerce API Key',
            'description' => 'Your Coinbase Commerce API key.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 12,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_coinbase_webhook_secret',
            'title'       => 'Coinbase Webhook Shared Secret',
            'description' => 'The shared secret for verifying Coinbase Commerce webhooks.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 13,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_lemonsqueezy_enabled',
            'title'       => 'Enable Lemon Squeezy',
            'description' => 'Allow card/PayPal payments via Lemon Squeezy.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 14,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_lemonsqueezy_api_key',
            'title'       => 'Lemon Squeezy API Key',
            'description' => 'Your Lemon Squeezy API key.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 15,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_lemonsqueezy_store_id',
            'title'       => 'Lemon Squeezy Store ID',
            'description' => 'Your Lemon Squeezy store ID.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 16,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_lemonsqueezy_webhook_secret',
            'title'       => 'Lemon Squeezy Webhook Secret',
            'description' => 'The signing secret for verifying Lemon Squeezy webhooks.',
            'optionscode' => 'text',
            'value'       => '',
            'disporder'   => 17,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_ads_enabled',
            'title'       => 'Enable Ad Space',
            'description' => 'Allow users to purchase ad space with credits.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 18,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_ads_approval',
            'title'       => 'Require Ad Approval',
            'description' => 'Require admin approval before purchased ads go live.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 19,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_currency_name',
            'title'       => 'Currency Name',
            'description' => 'Display name for the credits currency.',
            'optionscode' => 'text',
            'value'       => 'Credits',
            'disporder'   => 20,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_ads_exempt_groups',
            'title'       => 'Ad-Exempt Usergroups',
            'description' => 'Comma-separated list of usergroup IDs that should not see credit-purchased ads. Leave empty to show ads to everyone.',
            'optionscode' => 'groupselect',
            'value'       => '4',
            'disporder'   => 21,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_achievements_enabled',
            'title'       => 'Enable Achievements',
            'description' => 'Enable the achievement milestone system. Users earn rewards for reaching milestones.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 22,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_lottery_enabled',
            'title'       => 'Enable Lottery',
            'description' => 'Enable the lottery/raffle system. Users can buy tickets for a chance to win the pot.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 23,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_referral_enabled',
            'title'       => 'Enable Referral Rewards',
            'description' => 'Enable the referral rewards system. Users earn credits for referring new members.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 24,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_referral_reward',
            'title'       => 'Referral Reward Amount',
            'description' => 'Credits awarded to the referrer when their referred user reaches the minimum post threshold.',
            'optionscode' => 'numeric',
            'value'       => '50',
            'disporder'   => 25,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_referral_min_posts',
            'title'       => 'Referral Minimum Posts',
            'description' => 'Number of posts the referred user must make before the referrer receives their reward.',
            'optionscode' => 'numeric',
            'value'       => '10',
            'disporder'   => 26,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_referral_booster_multiplier',
            'title'       => 'Referral Booster Multiplier',
            'description' => 'Optional booster multiplier awarded to the referrer. Set to 0 for no booster.',
            'optionscode' => 'numeric',
            'value'       => '0',
            'disporder'   => 27,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
        array(
            'name'        => 'credits_referral_booster_duration',
            'title'       => 'Referral Booster Duration (seconds)',
            'description' => 'Duration of the referral reward booster in seconds. Only applies if multiplier is > 0.',
            'optionscode' => 'numeric',
            'value'       => '3600',
            'disporder'   => 28,
            'gid'         => $gid,
            'isdefault'   => 0,
        ),
    );

    foreach ($settings as $setting) {
        $db->insert_query('settings', $setting);
    }

    rebuild_settings();

    // ---- Template Group ----
    $templategroup = array(
        'prefix'    => 'credits',
        'title'     => $db->escape_string('Credits'),
        'isdefault' => 0,
    );
    $db->insert_query('templategroups', $templategroup);

    // ---- Templates ----
    $templates = array();

    $templates[] = array(
        'title'    => 'credits_page',
        'template' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->credits}</title>
{$headerinclude}
</head>
<body>
{$header}
{$credits_content}
{$footer}
</body>
</html>'),
    );

    $templates[] = array(
        'title'    => 'credits_nav',
        'template' => $db->escape_string('<a href="{$credits_base_url}" class="credits_nav_link">{$lang->credits} ({$mybb->user[\'credits\']})</a>'),
    );

    $templates[] = array(
        'title'    => 'credits_postbit',
        'template' => $db->escape_string('{$post[\'credits_bg_style\']}<br /><strong>{$lang->credits}:</strong> {$post[\'credits\']}{$post[\'credits_booster_badge\']}{$post[\'credits_awards_display\']}'),
    );

    $templates[] = array(
        'title'    => 'credits_postbit_icon',
        'template' => $db->escape_string('<img src="{$post[\'credits_icon\']}" alt="icon" class="credits_icon" style="vertical-align: middle; max-height: 16px; max-width: 16px; margin-right: 4px;" />'),
    );

    $templates[] = array(
        'title'    => 'credits_profile',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->credits}</strong></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_balance}:</strong></td>
<td class="trow1">{$memprofile[\'credits\']}</td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_rank}:</strong></td>
<td class="trow2">#{$credits_rank}</td>
</tr>
{$credits_profile_icon_row}
{$credits_profile_awards_row}
{$credits_profile_booster_row}
{$credits_profile_bg_row}
{$credits_profile_effect_row}
{$credits_profile_achievements_row}
</table>
<br />'),
    );

    $templates[] = array(
        'title'    => 'credits_profile_icon_row',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_icon}:</strong></td>
<td class="trow1"><img src="{$memprofile[\'credits_icon\']}" alt="icon" class="credits_icon" style="max-height: 32px; max-width: 32px;" /></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_profile_booster_row',
        'template' => $db->escape_string('<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_booster_active}:</strong></td>
<td class="trow2">{$booster_multiplier}x - {$booster_time_remaining}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_leaderboard',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="4"><strong>{$lang->credits_leaderboard}</strong></td>
</tr>
<tr>
<td class="tcat" width="10%"><strong>{$lang->credits_rank_col}</strong></td>
<td class="tcat" width="50%"><strong>{$lang->credits_username}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_posts}</strong></td>
</tr>
{$leaderboard_rows}
</table>'),
    );

    $templates[] = array(
        'title'    => 'credits_leaderboard_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}" align="center">#{$rank}</td>
<td class="{$alt_bg}"><a href="{$profile_url}">{$user[\'username\']}</a></td>
<td class="{$alt_bg}" align="center">{$user[\'credits\']}</td>
<td class="{$alt_bg}" align="center">{$user[\'postnum\']}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_leaderboard_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="4" align="center">{$lang->credits_no_users}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop',
        'template' => $db->escape_string('<div class="credits_shop_layout">
<div class="credits_shop_sidebar">{$shop_sidebar_links}</div>
<div class="credits_shop_content">{$shop_categories}</div>
</div>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_sidebar_link',
        'template' => $db->escape_string('<a href="javascript:void(0)" class="credits_shop_sidebar_link" data-cid="{$sidebar_cid}" onclick="Credits.shopSelectCategory(\'{$sidebar_cid}\')">{$sidebar_name}</a>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_category',
        'template' => $db->escape_string('<div class="credits_shop_category_block" data-cid="{$cid}">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="margin-bottom: 10px;">
<tr>
<td class="thead" colspan="4"><strong>{$category_name}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->credits_item_name}</strong></td>
<td class="tcat" width="40%"><strong>{$lang->credits_item_desc}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_item_price}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_item_action}</strong></td>
</tr>
{$category_items}
</table>
</div>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_item',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}">{$item_icon_preview}{$item[\'name\']}</td>
<td class="{$alt_bg}">{$item[\'description\']}</td>
<td class="{$alt_bg}" align="center">{$item_price_display}</td>
<td class="{$alt_bg}" align="center"><a href="{$credits_base_url}?view=shop&purchase={$item[\'iid\']}">{$lang->credits_purchase}</a></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_empty',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->credits_shop}</strong></td>
</tr>
<tr>
<td class="trow1" align="center">{$lang->credits_no_items}</td>
</tr>
</table>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase',
        'template' => $db->escape_string('<form action="{$credits_base_url}?view=shop&do=purchase" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="iid" value="{$item[\'iid\']}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->credits_purchase_item}: {$item[\'name\']}</strong></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_item_price}:</strong></td>
<td class="trow1">{$price_display}</td>
</tr>
{$balance_row}
{$purchase_input}
{$payment_method_toggle}
{$gift_toggle}
{$purchase_button}
</table>
</form>
<script type="text/javascript">
$(function(){
    $("input[name=purchase_target]").on("change", function(){
        if($(this).val() == "gift") {
            $("#gift_recipient_row, #gift_message_row").show();
        } else {
            $("#gift_recipient_row, #gift_message_row").hide();
        }
    });
    $("input[name=payment_method]").on("change", function(){
        if($(this).val() == "money") {
            $("#gateway_choice_row").show();
        } else {
            $("#gateway_choice_row").hide();
        }
    });
});
</script>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_title',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_enter_title}:</strong></td>
<td class="trow1"><input type="text" name="purchase_value" class="textbox" maxlength="64" /></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_color',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_choose_color}:</strong></td>
<td class="trow1"><input type="color" name="purchase_value" value="#000000" /> <input type="text" name="purchase_value_hex" class="textbox" size="8" maxlength="7" placeholder="#000000" /></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_icon',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_icon_preview}:</strong></td>
<td class="trow1"><img src="{$item_image}" alt="{$item[\'name\']}" style="max-height: 32px; max-width: 32px;" /></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_booster',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_booster_multiplier}:</strong></td>
<td class="trow1">{$booster_multiplier}x</td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_booster_duration}:</strong></td>
<td class="trow2">{$booster_duration_display}</td>
</tr>
{$booster_warning}'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_booster_warning',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="2" style="color: #c00;"><strong>{$lang->credits_booster_replace_warning}</strong></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_award',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_award_preview}:</strong></td>
<td class="trow1"><img src="{$item_image}" alt="{$item[\'name\']}" style="max-height: 32px; max-width: 32px;" /></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_postbit_bg',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_postbit_bg_preview}:</strong></td>
<td class="trow1"><div style="width: 100px; height: 40px; border: 1px solid #ccc; {$bg_preview_style}"></div></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_effect',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_effect_preview}:</strong></td>
<td class="trow1"><span class="credits_fx_{$effect_name}"><a href="javascript:void(0);" style="font-size: 14px;">{$lang->credits_effect_sample_text}</a></span></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_postbit_award_img',
        'template' => $db->escape_string('<img src="{$award_src}" alt="award" class="credits_award" style="vertical-align: middle; max-height: 16px; max-width: 16px; margin: 0 2px;" />'),
    );

    $templates[] = array(
        'title'    => 'credits_postbit_awards',
        'template' => $db->escape_string('<br /><span class="credits_awards">{$awards_images}</span>'),
    );

    $templates[] = array(
        'title'    => 'credits_profile_awards_row',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_awards}:</strong></td>
<td class="trow1">{$profile_awards_images}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_profile_bg_row',
        'template' => $db->escape_string('<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_postbit_bg}:</strong></td>
<td class="trow2"><div style="width: 60px; height: 20px; border: 1px solid #ccc; display: inline-block; vertical-align: middle; {$profile_bg_style}"></div></td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_profile_effect_row',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_username_effect_label}:</strong></td>
<td class="trow1"><span class="credits_fx_{$profile_effect_name}"><a href="javascript:void(0);">{$profile_effect_label}</a></span></td>
</tr>'),
    );

    // Gift templates
    $templates[] = array(
        'title'    => 'credits_gift_form',
        'template' => $db->escape_string('<form action="{$credits_base_url}?view=gift&do=send" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="gift_type" value="credits" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->credits_gift_credits_title}</strong></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_gift_to_user}:</strong></td>
<td class="trow1"><input type="text" name="to_username" class="textbox" /></td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_gift_amount}:</strong></td>
<td class="trow2"><input type="number" name="gift_amount" class="textbox" min="1" value="1" /></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_gift_message}:</strong></td>
<td class="trow1"><textarea name="gift_message" rows="3" cols="50" class="textbox"></textarea></td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_your_balance}:</strong></td>
<td class="trow2">{$mybb->user[\'credits\']} {$lang->credits}</td>
</tr>
<tr>
<td class="trow1" colspan="2" align="center">
<input type="submit" class="button" value="{$lang->credits_gift_send}" />
</td>
</tr>
</table>
</form>'),
    );

    $templates[] = array(
        'title'    => 'credits_gift_log',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="margin-top: 10px;">
<tr>
<td class="thead" colspan="5"><strong>{$lang->credits_gift_history}</strong></td>
</tr>
<tr>
<td class="tcat" width="20%"><strong>{$lang->credits_gift_direction}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_username}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_gift_type}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_gift_amount}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_log_date}</strong></td>
</tr>
{$gift_rows}
</table>'),
    );

    $templates[] = array(
        'title'    => 'credits_gift_log_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}">{$gift_direction}</td>
<td class="{$alt_bg}">{$gift_user}</td>
<td class="{$alt_bg}">{$gift_type_display}</td>
<td class="{$alt_bg}">{$gift_amount_display}</td>
<td class="{$alt_bg}">{$gift_date}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_gift_log_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="5" align="center">{$lang->credits_gift_no_history}</td>
</tr>'),
    );

    // Credit packs templates
    $templates[] = array(
        'title'    => 'credits_packs',
        'template' => $db->escape_string('<div class="credits_shop_category_block" data-cid="packs">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="margin-bottom: 10px;">
<tr>
<td class="thead" colspan="4"><strong>{$lang->credits_packs_title}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->credits_item_name}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_pack_credits}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_pack_price}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_item_action}</strong></td>
</tr>
{$pack_rows}
</table>
</div>'),
    );

    $templates[] = array(
        'title'    => 'credits_packs_item',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}">{$pack[\'name\']}</td>
<td class="{$alt_bg}" align="center">{$pack[\'credits\']}</td>
<td class="{$alt_bg}" align="center">{$pack_price_display}</td>
<td class="{$alt_bg}" align="center">{$pack_buy_buttons}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_packs_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="4" align="center">{$lang->credits_no_packs}</td>
</tr>'),
    );

    // Shop item buy with money button
    $templates[] = array(
        'title'    => 'credits_shop_item_buy_money',
        'template' => $db->escape_string(' {$item_buy_money_buttons}'),
    );

    // Usergroup purchase template
    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_usergroup',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_usergroup_join}:</strong></td>
<td class="trow1">{$usergroup_name}</td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_usergroup_duration}:</strong></td>
<td class="trow2">{$usergroup_duration_display}</td>
</tr>'),
    );

    // Ad space purchase template
    $templates[] = array(
        'title'    => 'credits_shop_purchase_input_ad',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_ad_position}:</strong></td>
<td class="trow1">{$ad_position_display}</td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_ad_duration}:</strong></td>
<td class="trow2">{$ad_duration_display}</td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_ad_content}:</strong></td>
<td class="trow1"><textarea name="ad_content" rows="4" cols="50" class="textbox"></textarea></td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_ad_image}:</strong></td>
<td class="trow2"><input type="text" name="ad_image" class="textbox" placeholder="images/ads/my_ad.png" /></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_ad_url}:</strong></td>
<td class="trow1"><input type="text" name="ad_url" class="textbox" placeholder="https://example.com" /></td>
</tr>'),
    );

    // Gift toggle for purchase confirmation
    $templates[] = array(
        'title'    => 'credits_gift_toggle',
        'template' => $db->escape_string('<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_purchase_for}:</strong></td>
<td class="trow2">
<label><input type="radio" name="purchase_target" value="self" checked="checked" /> {$lang->credits_purchase_for_self}</label><br />
<label><input type="radio" name="purchase_target" value="gift" /> {$lang->credits_purchase_for_gift}</label>
</td>
</tr>
<tr id="gift_recipient_row" style="display:none;">
<td class="trow1" width="40%"><strong>{$lang->credits_gift_to_user}:</strong></td>
<td class="trow1"><input type="text" name="gift_to_username" class="textbox" /></td>
</tr>
<tr id="gift_message_row" style="display:none;">
<td class="trow2" width="40%"><strong>{$lang->credits_gift_message}:</strong></td>
<td class="trow2"><textarea name="gift_message" rows="3" cols="50" class="textbox"></textarea></td>
</tr>'),
    );

    // Payment method toggle for purchase confirmation (shown when item has USD price)
    $templates[] = array(
        'title'    => 'credits_payment_method_toggle',
        'template' => $db->escape_string('<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_payment_method}:</strong></td>
<td class="trow1">
<label><input type="radio" name="payment_method" value="credits" checked="checked" /> {$lang->credits_pay_with_credits}</label><br />
<label><input type="radio" name="payment_method" value="money" /> {$lang->credits_pay_with_money}</label>
</td>
</tr>
<tr id="gateway_choice_row" style="display:none;">
<td class="trow2" width="40%"><strong>{$lang->credits_payment_gateway}:</strong></td>
<td class="trow2">
{$gateway_options}
</td>
</tr>'),
    );

    // Pack purchase confirmation template
    $templates[] = array(
        'title'    => 'credits_pack_purchase',
        'template' => $db->escape_string('<form action="{$credits_base_url}?view=shop&do=buy_pack" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="pack_id" value="{$pack[\'pack_id\']}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->credits_purchase_item}: {$pack[\'name\']}</strong></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_pack_credits}:</strong></td>
<td class="trow1">{$pack[\'credits\']}</td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_pack_price}:</strong></td>
<td class="trow2">{$pack_price_display}</td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_payment_gateway}:</strong></td>
<td class="trow1">
{$gateway_options}
</td>
</tr>
<tr>
<td class="trow2" colspan="2" align="center">
<input type="submit" class="button" value="{$lang->credits_confirm_purchase}" />
</td>
</tr>
</table>
</form>'),
    );

    // Ad display templates
    $templates[] = array(
        'title'    => 'credits_ad_header',
        'template' => $db->escape_string('<div class="credits_ad credits_ad_header" style="text-align: center; padding: 5px; margin-bottom: 5px;">{$ad_content}</div>'),
    );

    $templates[] = array(
        'title'    => 'credits_ad_thread_header',
        'template' => $db->escape_string('<tr><td class="trow1" colspan="2" style="text-align: center; padding: 10px;"><div class="credits_ad credits_ad_thread_header">{$ad_content}</div></td></tr>'),
    );

    // Payment pending template
    $templates[] = array(
        'title'    => 'credits_payment_pending',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->credits_payment_pending}</strong></td>
</tr>
<tr>
<td class="trow1" align="center" style="padding: 20px;">
<p>{$lang->credits_payment_processing}</p>
<p><a href="{$credits_base_url}">{$lang->credits_payment_return}</a></p>
</td>
</tr>
</table>'),
    );

    $templates[] = array(
        'title'    => 'credits_log',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="4"><strong>{$lang->credits_log}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->credits_log_action}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_log_amount}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_log_balance}</strong></td>
<td class="tcat" width="30%"><strong>{$lang->credits_log_date}</strong></td>
</tr>
{$log_rows}
</table>
{$multipage}'),
    );

    $templates[] = array(
        'title'    => 'credits_log_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}">{$action_name}</td>
<td class="{$alt_bg}" align="center"><span class="{$amount_class}">{$amount_display}</span></td>
<td class="{$alt_bg}" align="center">{$log[\'balance\']}</td>
<td class="{$alt_bg}">{$log_date}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_log_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="4" align="center">{$lang->credits_no_log}</td>
</tr>'),
    );

    $templates[] = array(
        'title'    => 'credits_tabs',
        'template' => $db->escape_string('<div class="credits_tabs">
<a href="{$credits_base_url}" class="{$tab_leaderboard}">{$lang->credits_leaderboard}</a>
{$shop_tab}
{$gift_tab}
{$achievements_tab}
{$lottery_tab}
{$referral_tab}
{$inventory_tab}
<a href="{$credits_base_url}?view=log" class="{$tab_log}">{$lang->credits_log}</a>
</div>
<br />'),
    );

    $templates[] = array(
        'title'    => 'credits_tab_shop',
        'template' => $db->escape_string('<a href="{$credits_base_url}?view=shop" class="{$tab_shop}">{$lang->credits_shop}</a>'),
    );

    // Achievement tab
    $templates[] = array(
        'title'    => 'credits_tab_achievements',
        'template' => $db->escape_string('<a href="{$credits_base_url}?view=achievements" class="{$tab_achievements}">{$lang->credits_achievements}</a>'),
    );

    // Achievement page
    $templates[] = array(
        'title'    => 'credits_achievements_page',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="5"><strong>{$lang->credits_achievements_title}</strong></td>
</tr>
<tr>
<td class="tcat" width="5%">&nbsp;</td>
<td class="tcat" width="30%"><strong>{$lang->credits_item_name}</strong></td>
<td class="tcat" width="30%"><strong>{$lang->credits_item_desc}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_achievement_reward}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_achievement_progress}</strong></td>
</tr>
{$achievement_rows}
</table>'),
    );

    // Achievement row
    $templates[] = array(
        'title'    => 'credits_achievements_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}" align="center">{$ach_icon}</td>
<td class="{$alt_bg}"><strong>{$ach_name}</strong></td>
<td class="{$alt_bg}">{$ach_description}</td>
<td class="{$alt_bg}">{$ach_reward_display}</td>
<td class="{$alt_bg}" align="center">{$ach_status}</td>
</tr>'),
    );

    // Achievement page empty
    $templates[] = array(
        'title'    => 'credits_achievements_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="5" align="center">{$lang->credits_no_achievements}</td>
</tr>'),
    );

    // Profile achievements row
    $templates[] = array(
        'title'    => 'credits_profile_achievements_row',
        'template' => $db->escape_string('<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_achievements}:</strong></td>
<td class="trow2">{$profile_achievements_display}</td>
</tr>'),
    );

    // Lottery tab
    $templates[] = array(
        'title'    => 'credits_tab_lottery',
        'template' => $db->escape_string('<a href="{$credits_base_url}?view=lottery" class="{$tab_lottery}">{$lang->credits_lottery}</a>'),
    );

    // Lottery page
    $templates[] = array(
        'title'    => 'credits_lottery_page',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="5"><strong>{$lang->credits_lottery_active}</strong></td>
</tr>
<tr>
<td class="tcat" width="25%"><strong>{$lang->credits_lottery_name}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_lottery_pot}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_lottery_ticket_price}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_lottery_draw_time}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_admin_actions}</strong></td>
</tr>
{$active_rows}
</table>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="4"><strong>{$lang->credits_lottery_past}</strong></td>
</tr>
<tr>
<td class="tcat" width="25%"><strong>{$lang->credits_lottery_name}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_lottery_winner}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_lottery_winnings}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_lottery_draw_time}</strong></td>
</tr>
{$past_rows}
</table>'),
    );

    // Lottery active row
    $templates[] = array(
        'title'    => 'credits_lottery_active_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}"><strong>{$lottery_name}</strong><br /><small>{$lottery_description}</small></td>
<td class="{$alt_bg}">{$lottery_pot}</td>
<td class="{$alt_bg}">{$lottery_ticket_price}</td>
<td class="{$alt_bg}">{$lottery_draw_time}</td>
<td class="{$alt_bg}">{$lottery_action}</td>
</tr>'),
    );

    // Lottery past row
    $templates[] = array(
        'title'    => 'credits_lottery_past_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}"><strong>{$lottery_name}</strong></td>
<td class="{$alt_bg}">{$lottery_winner}</td>
<td class="{$alt_bg}">{$lottery_winnings}</td>
<td class="{$alt_bg}">{$lottery_draw_time}</td>
</tr>'),
    );

    // Lottery empty
    $templates[] = array(
        'title'    => 'credits_lottery_empty',
        'template' => $db->escape_string('<tr>
<td class="trow1" colspan="5" align="center">{$lang->credits_lottery_none}</td>
</tr>'),
    );

    // Referral tab
    $templates[] = array(
        'title'    => 'credits_tab_referrals',
        'template' => $db->escape_string('<a href="{$credits_base_url}?view=referrals" class="{$tab_referrals}">{$lang->credits_referrals}</a>'),
    );

    // Referral page
    $templates[] = array(
        'title'    => 'credits_referrals_page',
        'template' => $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->credits_referrals_title}</strong></td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_referral_your_code}:</strong></td>
<td class="trow1"><strong>{$referral_code}</strong></td>
</tr>
<tr>
<td class="trow2" width="40%"><strong>{$lang->credits_referral_total_referred}:</strong></td>
<td class="trow2">{$total_referred}</td>
</tr>
<tr>
<td class="trow1" width="40%"><strong>{$lang->credits_referral_total_rewarded}:</strong></td>
<td class="trow1">{$total_rewarded}</td>
</tr>
</table>
<br />
{$referral_enter_form}
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="3"><strong>{$lang->credits_referral_history}</strong></td>
</tr>
<tr>
<td class="tcat" width="40%"><strong>{$lang->credits_username}</strong></td>
<td class="tcat" width="30%"><strong>{$lang->credits_referral_status}</strong></td>
<td class="tcat" width="30%"><strong>{$lang->credits_log_date}</strong></td>
</tr>
{$referral_rows}
</table>'),
    );

    // Referral row
    $templates[] = array(
        'title'    => 'credits_referral_row',
        'template' => $db->escape_string('<tr>
<td class="{$alt_bg}">{$referred_username}</td>
<td class="{$alt_bg}">{$referral_status}</td>
<td class="{$alt_bg}">{$referral_date}</td>
</tr>'),
    );

    // Inventory tab
    $templates[] = array(
        'title'    => 'credits_tab_inventory',
        'template' => $db->escape_string('<a href="{$credits_inventory_url}" class="{$tab_inventory}">{$lang->credits_inventory}</a>'),
    );

    // Inventory page
    $templates[] = array(
        'title'    => 'credits_inventory_page',
        'template' => $db->escape_string('<div class="credits_shop_layout">
<div class="credits_shop_sidebar">{$inventory_sidebar_links}</div>
<div class="credits_shop_content">
<div class="credits_inv_grid">{$inventory_type_sections}</div>
</div>
</div>'),
    );

    // Inventory type section (card)
    $templates[] = array(
        'title'    => 'credits_inventory_type_section',
        'template' => $db->escape_string('<div class="credits_inv_card credits_inv_card_open" id="inv_{$sidebar_cid}">
<div class="thead credits_inv_card_header" onclick="Credits.inventoryToggleCard(this)">
<strong>{$type_label}</strong>
<span class="credits_inv_card_count">{$type_count}</span>
</div>
<div class="credits_inv_card_body">
{$type_items}
</div>
</div>'),
    );

    // Inventory item row
    $templates[] = array(
        'title'    => 'credits_inventory_item',
        'template' => $db->escape_string('<div class="credits_inv_item {$status_class}" data-type="{$item_type}">
<div class="credits_inv_item_preview">{$item_preview}</div>
<div class="credits_inv_item_info">
<div class="credits_inv_item_name">{$item_name}</div>
<div class="credits_inv_item_meta">{$item_meta}</div>
</div>
<div class="credits_inv_item_actions">{$item_actions}</div>
</div>'),
    );

    // Inventory empty
    $templates[] = array(
        'title'    => 'credits_inventory_empty',
        'template' => $db->escape_string('<div class="credits_inv_empty">
<p>{$lang->credits_inv_empty}</p>
</div>'),
    );

    foreach ($templates as $template) {
        $template['sid']      = '-2';
        $template['version']  = '';
        $template['dateline'] = TIME_NOW;
        $db->insert_query('templates', $template);
    }

    // ---- Default Categories ----
    $default_categories = array(
        array('name' => $db->escape_string('Titles'), 'description' => $db->escape_string('Custom user titles.'), 'disporder' => 1, 'active' => 1),
        array('name' => $db->escape_string('Icons'), 'description' => $db->escape_string('Profile and post icons.'), 'disporder' => 2, 'active' => 1),
        array('name' => $db->escape_string('Awards'), 'description' => $db->escape_string('Collectible badges displayed in posts and profiles.'), 'disporder' => 3, 'active' => 1),
        array('name' => $db->escape_string('Effects'), 'description' => $db->escape_string('Username animations and visual effects.'), 'disporder' => 4, 'active' => 1),
        array('name' => $db->escape_string('Backgrounds'), 'description' => $db->escape_string('Custom post background styles.'), 'disporder' => 5, 'active' => 1),
        array('name' => $db->escape_string('Boosters'), 'description' => $db->escape_string('Temporary credit gain multipliers.'), 'disporder' => 6, 'active' => 1),
    );

    $category_ids = array();
    foreach ($default_categories as $cat) {
        $category_ids[$cat['name']] = $db->insert_query('credits_categories', $cat);
    }

    // ---- Default Shop Items ----
    $default_items = array(
        array(
            'cid'         => $category_ids[$db->escape_string('Titles')] ?? 0,
            'name'        => $db->escape_string('Custom Title'),
            'description' => $db->escape_string('Set a custom title displayed under your username.'),
            'type'        => 'custom_title',
            'price'       => 50,
            'data'        => '',
            'active'      => 1,
            'disporder'   => 1,
        ),
        array(
            'cid'         => $category_ids[$db->escape_string('Titles')] ?? 0,
            'name'        => $db->escape_string('Username Color'),
            'description' => $db->escape_string('Choose a color for your username.'),
            'type'        => 'username_color',
            'price'       => 100,
            'data'        => '',
            'active'      => 1,
            'disporder'   => 2,
        ),
        array(
            'cid'         => $category_ids[$db->escape_string('Boosters')] ?? 0,
            'name'        => $db->escape_string('2x Credits (1 Hour)'),
            'description' => $db->escape_string('Double all credit gains for 1 hour.'),
            'type'        => 'booster',
            'price'       => 100,
            'data'        => json_encode(array('multiplier' => 2, 'duration' => 3600)),
            'active'      => 1,
            'disporder'   => 1,
        ),
        array(
            'cid'         => $category_ids[$db->escape_string('Boosters')] ?? 0,
            'name'        => $db->escape_string('2x Credits (24 Hours)'),
            'description' => $db->escape_string('Double all credit gains for 24 hours.'),
            'type'        => 'booster',
            'price'       => 500,
            'data'        => json_encode(array('multiplier' => 2, 'duration' => 86400)),
            'active'      => 1,
            'disporder'   => 2,
        ),
    );

    foreach ($default_items as $item) {
        $db->insert_query('credits_shop', $item);
    }

    // ---- Standalone Entry Point Files ----
    $credits_standalone = MYBB_ROOT . 'credits.php';
    if (!file_exists($credits_standalone)) {
        $credits_standalone_content = "<?php\n/**\n * Credits - Standalone Entry Point\n * Provides clean URL: credits.php instead of misc.php?action=credits\n */\ndefine('IN_MYBB', 1);\ndefine('THIS_SCRIPT', 'credits.php');\nrequire_once './global.php';\n\n\$mybb->input['action'] = 'credits';\ncredits_misc_page();\n";
        @file_put_contents($credits_standalone, $credits_standalone_content);
    }

    $inventory_standalone = MYBB_ROOT . 'inventory.php';
    if (!file_exists($inventory_standalone)) {
        $inventory_standalone_content = "<?php\n/**\n * Credits - Inventory Page Entry Point\n * Provides clean URL: inventory.php\n */\ndefine('IN_MYBB', 1);\ndefine('THIS_SCRIPT', 'inventory.php');\nrequire_once './global.php';\n\nrequire_once CREDITS_PLUGIN_PATH . 'inventory.php';\ncredits_page_inventory();\n";
        @file_put_contents($inventory_standalone, $inventory_standalone_content);
    }

    // ---- Scheduled Task ----
    $query = $db->simple_select('tasks', 'tid', "file = 'credits'");
    if ($db->num_rows($query) == 0) {
        $new_task = array(
            'title'       => 'Credits Expiry Task',
            'description' => 'Expires boosters, usergroup subscriptions, and ad placements.',
            'file'        => 'credits',
            'minute'      => '*/5',
            'hour'        => '*',
            'day'         => '*',
            'month'       => '*',
            'weekday'     => '*',
            'nextrun'     => TIME_NOW + 300,
            'lastrun'     => 0,
            'enabled'     => 1,
            'logging'     => 1,
            'locked'      => 0,
        );
        $db->insert_query('tasks', $new_task);
    }
}

function credits_is_installed()
{
    global $db;
    return $db->table_exists('credits_log');
}

function credits_uninstall()
{
    global $db, $PL;

    // Remove stylesheet
    if (file_exists(PLUGINLIBRARY)) {
        $PL or require_once PLUGINLIBRARY;
        $PL->stylesheet_delete('credits');
    }

    // Drop tables
    if ($db->table_exists('credits_log')) {
        $db->drop_table('credits_log');
    }
    if ($db->table_exists('credits_categories')) {
        $db->drop_table('credits_categories');
    }
    if ($db->table_exists('credits_shop')) {
        $db->drop_table('credits_shop');
    }
    if ($db->table_exists('credits_purchases')) {
        $db->drop_table('credits_purchases');
    }
    if ($db->table_exists('credits_packs')) {
        $db->drop_table('credits_packs');
    }
    if ($db->table_exists('credits_payments')) {
        $db->drop_table('credits_payments');
    }
    if ($db->table_exists('credits_gifts')) {
        $db->drop_table('credits_gifts');
    }
    if ($db->table_exists('credits_ads')) {
        $db->drop_table('credits_ads');
    }
    if ($db->table_exists('credits_usergroup_subs')) {
        $db->drop_table('credits_usergroup_subs');
    }
    if ($db->table_exists('credits_achievements')) {
        $db->drop_table('credits_achievements');
    }
    if ($db->table_exists('credits_user_achievements')) {
        $db->drop_table('credits_user_achievements');
    }
    if ($db->table_exists('credits_lottery')) {
        $db->drop_table('credits_lottery');
    }
    if ($db->table_exists('credits_lottery_tickets')) {
        $db->drop_table('credits_lottery_tickets');
    }
    if ($db->table_exists('credits_referrals')) {
        $db->drop_table('credits_referrals');
    }

    // Remove columns from users
    if ($db->field_exists('credits', 'users')) {
        $db->drop_column('users', 'credits');
    }
    if ($db->field_exists('credits_last_login_bonus', 'users')) {
        $db->drop_column('users', 'credits_last_login_bonus');
    }
    if ($db->field_exists('credits_icon', 'users')) {
        $db->drop_column('users', 'credits_icon');
    }
    if ($db->field_exists('credits_username_color', 'users')) {
        $db->drop_column('users', 'credits_username_color');
    }
    if ($db->field_exists('credits_awards', 'users')) {
        $db->drop_column('users', 'credits_awards');
    }
    if ($db->field_exists('credits_postbit_bg', 'users')) {
        $db->drop_column('users', 'credits_postbit_bg');
    }
    if ($db->field_exists('credits_username_effect', 'users')) {
        $db->drop_column('users', 'credits_username_effect');
    }
    if ($db->field_exists('credits_referral_code', 'users')) {
        $db->drop_column('users', 'credits_referral_code');
    }

    // Remove settings
    $db->delete_query('settinggroups', "name = 'credits_settings'");
    $db->delete_query('settings', "name LIKE 'credits_%'");
    rebuild_settings();

    // Remove templates
    $db->delete_query('templates', "title LIKE 'credits_%'");
    $db->delete_query('templategroups', "prefix = 'credits'");

    // Remove task
    $db->delete_query('tasks', "file = 'credits'");

    // Remove standalone entry point files
    $standalone_files = array('credits.php', 'inventory.php');
    foreach ($standalone_files as $filename) {
        $filepath = MYBB_ROOT . $filename;
        if (file_exists($filepath)) {
            $content = @file_get_contents($filepath);
            if ($content !== false && strpos($content, 'Credits') !== false) {
                @unlink($filepath);
            }
        }
    }

    // Remove ACP module directory
    $module_dir = MYBB_ADMIN_DIR . 'modules/credits';
    if (is_dir($module_dir)) {
        @unlink($module_dir . '/module_meta.php');
        @rmdir($module_dir);
    }
}

// ---- Activate / Deactivate ----

function credits_activate()
{
    global $db, $PL;

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message("PluginLibrary is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
    $PL or require_once PLUGINLIBRARY;

    // Ensure ACP module files exist (handles upgrades from older versions)
    credits_create_admin_module();

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Inject CSS via PluginLibrary (attached to Master Style, inherited by all themes)
    $css = <<<'CSS'
/* Credits Plugin Stylesheet */

:root {
    --credits-accent: #0066cc;
    --credits-accent-bg: #f0f4f8;
    --credits-success: #28a745;
    --credits-success-bg: #d4edda;
    --credits-text: #333;
    --credits-text-muted: #777;
    --credits-border: #ccc;
    --credits-card-bg: #fff;
    --credits-hover-bg: #f5f5f5;
}

.credits_awards img { vertical-align: middle; }

.credits_ucolor,
.credits_ucolor * { color: var(--credits-ucolor) !important; }

.credits_tabs {
    margin-bottom: 10px;
    border-bottom: 2px solid var(--credits-border);
    display: flex;
    gap: 0;
}
.credits_tabs a.credits_tab,
.credits_tabs a.credits_tab_active {
    display: inline-block;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--credits-text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    font-weight: normal;
}
.credits_tabs a.credits_tab:hover {
    color: var(--credits-text);
    border-bottom-color: #999;
}
.credits_tabs a.credits_tab_active {
    color: var(--credits-text);
    border-bottom-color: var(--credits-accent);
    font-weight: bold;
}

.credits_fx_rainbow a { animation: credits_rainbow 3s linear infinite; }
@keyframes credits_rainbow {
    0% { color: #ff0000; }
    17% { color: #ff8800; }
    33% { color: #ffff00; }
    50% { color: #00ff00; }
    67% { color: #0088ff; }
    83% { color: #8800ff; }
    100% { color: #ff0000; }
}

.credits_fx_glow a { animation: credits_glow 2s ease-in-out infinite alternate; }
@keyframes credits_glow {
    from { text-shadow: 0 0 5px currentColor; }
    to { text-shadow: 0 0 15px currentColor, 0 0 30px currentColor; }
}

.credits_fx_sparkle a {
    background: linear-gradient(90deg, currentColor 40%, #fff 50%, currentColor 60%);
    background-size: 200%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: credits_sparkle 2s linear infinite;
}
@keyframes credits_sparkle {
    0% { background-position: 200%; }
    100% { background-position: -200%; }
}

.credits_fx_shadow a { text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
.credits_fx_bold a { font-weight: 900; letter-spacing: 0.5px; }
.credits_fx_gradient a {
    background: linear-gradient(135deg, #f06, #48f, #0cf);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.credits_inv_grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 700px) {
    .credits_inv_grid { grid-template-columns: 1fr; }
}
.credits_inv_card {
    border: 1px solid var(--credits-border);
    border-radius: 6px;
    overflow: hidden;
    background: var(--credits-card-bg);
}
.credits_inv_card_header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
    user-select: none;
}
.credits_inv_card_header::after {
    content: '\25BC';
    font-size: 10px;
    margin-left: 8px;
    transition: transform 0.2s;
}
.credits_inv_card_collapsed .credits_inv_card_header::after {
    transform: rotate(-90deg);
}
.credits_inv_card_collapsed .credits_inv_card_body {
    display: none;
}
.credits_inv_card_count {
    background: rgba(255,255,255,0.25);
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
}
.credits_inv_card_body {
    padding: 6px;
}
.credits_inv_item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    border-bottom: 1px solid var(--credits-hover-bg);
    gap: 10px;
    transition: background 0.15s;
}
.credits_inv_item:last-child { border-bottom: none; }
.credits_inv_item:hover { background: var(--credits-hover-bg); }
.credits_inv_item_preview {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: var(--credits-hover-bg);
    font-size: 18px;
    overflow: hidden;
}
.credits_inv_item_preview img {
    max-width: 28px;
    max-height: 28px;
}
.credits_inv_item_info {
    flex: 1;
    min-width: 0;
}
.credits_inv_item_name {
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.credits_inv_item_meta {
    font-size: 11px;
    color: var(--credits-text-muted);
    margin-top: 1px;
}
.credits_inv_item_actions {
    flex-shrink: 0;
    display: flex;
    gap: 4px;
}
.credits_inv_btn {
    width: 30px;
    height: 30px;
    border: 1px solid var(--credits-border);
    border-radius: 6px;
    background: var(--credits-card-bg);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    transition: all 0.15s;
    padding: 0;
    line-height: 1;
}
.credits_inv_btn:hover { background: var(--credits-hover-bg); border-color: #aaa; }
.credits_inv_btn:disabled { opacity: 0.4; cursor: wait; }
.credits_inv_btn_on { background: var(--credits-success-bg); border-color: var(--credits-success); color: #155724; }
.credits_inv_btn_on:hover { background: #c3e6cb; }
.credits_inv_btn_off { background: var(--credits-card-bg); border-color: var(--credits-border); color: var(--credits-text-muted); }
.credits_inv_btn_off:hover { background: var(--credits-hover-bg); }
.credits_inv_btn_edit { color: var(--credits-accent); }
.credits_inv_btn_edit:hover { background: var(--credits-accent-bg); border-color: var(--credits-accent); }
.credits_inv_item.credits_inv_active { border-left: 3px solid var(--credits-success); }
.credits_inv_item.credits_inv_inactive { opacity: 0.7; }
.credits_inv_item.credits_inv_expired { opacity: 0.5; }
.credits_inv_empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--credits-text-muted);
}

.credits_shop_layout {
    display: flex;
    gap: 12px;
}
.credits_shop_sidebar {
    width: 200px;
    flex-shrink: 0;
}
.credits_shop_sidebar:empty {
    display: none;
}
.credits_shop_sidebar_link {
    display: block;
    padding: 8px 12px;
    text-decoration: none;
    color: var(--credits-text-muted);
    border-left: 3px solid transparent;
    margin-bottom: 2px;
    border-radius: 0 4px 4px 0;
}
.credits_shop_sidebar_link:hover {
    background: var(--credits-hover-bg);
    color: var(--credits-text);
}
.credits_shop_sidebar_link.active {
    border-left-color: var(--credits-accent);
    color: var(--credits-text);
    font-weight: bold;
    background: var(--credits-accent-bg);
}
.credits_shop_content {
    flex: 1;
    min-width: 0;
}
@media (max-width: 700px) {
    .credits_shop_layout { flex-direction: column; }
    .credits_shop_sidebar { width: 100%; display: flex; overflow-x: auto; gap: 0; border-bottom: 2px solid var(--credits-border); margin-bottom: 8px; }
    .credits_shop_sidebar_link { border-left: none; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -2px; border-radius: 0; }
    .credits_shop_sidebar_link.active { border-left-color: transparent; border-bottom-color: var(--credits-accent); }
}
CSS;

    $PL->stylesheet('credits', $css);

    // Migration: add credits_tab_shop template if missing (v1.0.1+)
    $query = $db->simple_select('templates', 'tid', "title='credits_tab_shop' AND sid='-2'", array('limit' => 1));
    if ($db->num_rows($query) == 0) {
        $db->insert_query('templates', array(
            'title'    => 'credits_tab_shop',
            'template' => $db->escape_string('<a href="{$credits_base_url}?view=shop" class="{$tab_shop}">{$lang->credits_shop}</a>'),
            'sid'      => '-2',
            'version'  => '',
            'dateline' => TIME_NOW,
        ));
    }

    // Migration: add inventory templates if missing (v1.2.0+)
    $inventory_templates = array(
        'credits_tab_inventory' => '<a href="{$credits_inventory_url}" class="{$tab_inventory}">{$lang->credits_inventory}</a>',
        'credits_inventory_page' => '<div class="credits_shop_layout">
<div class="credits_shop_sidebar">{$inventory_sidebar_links}</div>
<div class="credits_shop_content">
<div class="credits_inv_grid">{$inventory_type_sections}</div>
</div>
</div>',
        'credits_inventory_type_section' => '<div class="credits_inv_card credits_inv_card_open" id="inv_{$sidebar_cid}">
<div class="thead credits_inv_card_header" onclick="Credits.inventoryToggleCard(this)">
<strong>{$type_label}</strong>
<span class="credits_inv_card_count">{$type_count}</span>
</div>
<div class="credits_inv_card_body">
{$type_items}
</div>
</div>',
        'credits_inventory_item' => '<div class="credits_inv_item {$status_class}" data-type="{$item_type}">
<div class="credits_inv_item_preview">{$item_preview}</div>
<div class="credits_inv_item_info">
<div class="credits_inv_item_name">{$item_name}</div>
<div class="credits_inv_item_meta">{$item_meta}</div>
</div>
<div class="credits_inv_item_actions">{$item_actions}</div>
</div>',
        'credits_inventory_empty' => '<div class="credits_inv_empty">
<p>{$lang->credits_inv_empty}</p>
</div>',
    );

    foreach ($inventory_templates as $title => $tpl_content) {
        $query = $db->simple_select('templates', 'tid', "title='" . $db->escape_string($title) . "' AND sid='-2'", array('limit' => 1));
        if ($db->num_rows($query) == 0) {
            $db->insert_query('templates', array(
                'title'    => $title,
                'template' => $db->escape_string($tpl_content),
                'sid'      => '-2',
                'version'  => '',
                'dateline' => TIME_NOW,
            ));
        }
    }

    // Migration: add shop sidebar link template if missing (v1.3.0+)
    $query = $db->simple_select('templates', 'tid', "title='credits_shop_sidebar_link' AND sid='-2'", array('limit' => 1));
    if ($db->num_rows($query) == 0) {
        $db->insert_query('templates', array(
            'title'    => 'credits_shop_sidebar_link',
            'template' => $db->escape_string('<a href="javascript:void(0)" class="credits_shop_sidebar_link" data-cid="{$sidebar_cid}" onclick="Credits.shopSelectCategory(\'{$sidebar_cid}\')">{$sidebar_name}</a>'),
            'sid'      => '-2',
            'version'  => '',
            'dateline' => TIME_NOW,
        ));
    }

    // Migration: update credits_shop template to sidebar layout (v1.3.0+)
    $query = $db->simple_select('templates', 'template', "title='credits_shop' AND sid='-2'", array('limit' => 1));
    $existing = $db->fetch_field($query, 'template');
    if ($existing !== false && strpos($existing, 'credits_shop_layout') === false) {
        $db->update_query('templates', array(
            'template' => $db->escape_string('<div class="credits_shop_layout">
<div class="credits_shop_sidebar">{$shop_sidebar_links}</div>
<div class="credits_shop_content">{$shop_categories}</div>
</div>'),
            'dateline' => TIME_NOW,
        ), "title='credits_shop' AND sid='-2'");
    }

    // Migration: update credits_shop_category template with data-cid wrapper (v1.3.0+)
    $query = $db->simple_select('templates', 'template', "title='credits_shop_category' AND sid='-2'", array('limit' => 1));
    $existing = $db->fetch_field($query, 'template');
    if ($existing !== false && strpos($existing, 'credits_shop_category_block') === false) {
        $db->update_query('templates', array(
            'template' => $db->escape_string('<div class="credits_shop_category_block" data-cid="{$cid}">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="margin-bottom: 10px;">
<tr>
<td class="thead" colspan="4"><strong>{$category_name}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->credits_item_name}</strong></td>
<td class="tcat" width="40%"><strong>{$lang->credits_item_desc}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_item_price}</strong></td>
<td class="tcat" width="15%"><strong>{$lang->credits_item_action}</strong></td>
</tr>
{$category_items}
</table>
</div>'),
            'dateline' => TIME_NOW,
        ), "title='credits_shop_category' AND sid='-2'");
    }

    // Migration: update credits_packs template with data-cid wrapper (v1.3.0+)
    $query = $db->simple_select('templates', 'template', "title='credits_packs' AND sid='-2'", array('limit' => 1));
    $existing = $db->fetch_field($query, 'template');
    if ($existing !== false && strpos($existing, 'credits_shop_category_block') === false) {
        $db->update_query('templates', array(
            'template' => $db->escape_string('<div class="credits_shop_category_block" data-cid="packs">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" style="margin-bottom: 10px;">
<tr>
<td class="thead" colspan="4"><strong>{$lang->credits_packs_title}</strong></td>
</tr>
<tr>
<td class="tcat" width="30%"><strong>{$lang->credits_item_name}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_pack_credits}</strong></td>
<td class="tcat" width="25%"><strong>{$lang->credits_pack_price}</strong></td>
<td class="tcat" width="20%"><strong>{$lang->credits_item_action}</strong></td>
</tr>
{$pack_rows}
</table>
</div>'),
            'dateline' => TIME_NOW,
        ), "title='credits_packs' AND sid='-2'");
    }

    // Migration: update credits_inventory_page for sidebar layout (v1.3.0+)
    $query = $db->simple_select('templates', 'template', "title='credits_inventory_page' AND sid='-2'", array('limit' => 1));
    $existing = $db->fetch_field($query, 'template');
    if ($existing !== false && strpos($existing, 'credits_shop_layout') === false) {
        $db->update_query('templates', array(
            'template' => $db->escape_string('<div class="credits_shop_layout">
<div class="credits_shop_sidebar">{$inventory_sidebar_links}</div>
<div class="credits_shop_content">
<div class="credits_inv_grid">{$inventory_type_sections}</div>
</div>
</div>'),
            'dateline' => TIME_NOW,
        ), "title='credits_inventory_page' AND sid='-2'");
    }

    // Migration: update credits_inventory_type_section for collapsible cards + sidebar id (v1.3.0+)
    $query = $db->simple_select('templates', 'template', "title='credits_inventory_type_section' AND sid='-2'", array('limit' => 1));
    $existing = $db->fetch_field($query, 'template');
    if ($existing !== false && strpos($existing, 'inv_{$sidebar_cid}') === false) {
        $db->update_query('templates', array(
            'template' => $db->escape_string('<div class="credits_inv_card credits_inv_card_open" id="inv_{$sidebar_cid}">
<div class="thead credits_inv_card_header" onclick="Credits.inventoryToggleCard(this)">
<strong>{$type_label}</strong>
<span class="credits_inv_card_count">{$type_count}</span>
</div>
<div class="credits_inv_card_body">
{$type_items}
</div>
</div>'),
            'dateline' => TIME_NOW,
        ), "title='credits_inventory_type_section' AND sid='-2'");
    }

    // Migration: create standalone files if missing
    $credits_standalone = MYBB_ROOT . 'credits.php';
    if (!file_exists($credits_standalone)) {
        $credits_standalone_content = "<?php\n/**\n * Credits - Standalone Entry Point\n * Provides clean URL: credits.php instead of misc.php?action=credits\n */\ndefine('IN_MYBB', 1);\ndefine('THIS_SCRIPT', 'credits.php');\nrequire_once './global.php';\n\n\$mybb->input['action'] = 'credits';\ncredits_misc_page();\n";
        @file_put_contents($credits_standalone, $credits_standalone_content);
    }

    $inventory_standalone = MYBB_ROOT . 'inventory.php';
    if (!file_exists($inventory_standalone)) {
        $inventory_standalone_content = "<?php\n/**\n * Credits - Inventory Page Entry Point\n * Provides clean URL: inventory.php\n */\ndefine('IN_MYBB', 1);\ndefine('THIS_SCRIPT', 'inventory.php');\nrequire_once './global.php';\n\nrequire_once CREDITS_PLUGIN_PATH . 'inventory.php';\ncredits_page_inventory();\n";
        @file_put_contents($inventory_standalone, $inventory_standalone_content);
    }

    // Inject credits into postbit
    find_replace_templatesets(
        'postbit',
        '#\{\$post\[\'user_details\'\]\}#',
        '{$post[\'user_details\']}{$post[\'credits_display\']}'
    );

    find_replace_templatesets(
        'postbit_classic',
        '#\{\$post\[\'user_details\'\]\}#',
        '{$post[\'user_details\']}{$post[\'credits_display\']}'
    );

    // Inject credits on member profile
    find_replace_templatesets(
        'member_profile',
        '#\{\$footer\}#',
        '{$credits_profile}{$footer}'
    );

    // Inject ad spaces (header only)
    find_replace_templatesets(
        'header',
        '#\{\$awaitingusers\}#',
        '{$credits_ad_header}{$awaitingusers}'
    );
}

function credits_deactivate()
{
    global $db, $PL;

    if (file_exists(PLUGINLIBRARY)) {
        $PL or require_once PLUGINLIBRARY;
        $PL->stylesheet_deactivate('credits');
    }

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Remove postbit injection
    find_replace_templatesets(
        'postbit',
        '#\{\$post\[\'credits_display\'\]\}#',
        ''
    );

    find_replace_templatesets(
        'postbit_classic',
        '#\{\$post\[\'credits_display\'\]\}#',
        ''
    );

    // Remove profile injection
    find_replace_templatesets(
        'member_profile',
        '#\{\$credits_profile\}#',
        ''
    );

    // Remove legacy template-based CSS from headerinclude (if previously added)
    find_replace_templatesets(
        'headerinclude',
        '#\{\$credits_css\}#',
        ''
    );

    // Remove ad spaces
    find_replace_templatesets(
        'header',
        '#\{\$credits_ad_header\}#',
        ''
    );

    // Clean up legacy footer ad injection if it exists
    find_replace_templatesets(
        'footer',
        '#\{\$credits_ad_footer\}#',
        ''
    );
}

// ---- Admin Module Meta Generator ----

/**
 * Create the admin/modules/credits/ directory and module_meta.php file.
 * This registers Credits as a top-level ACP module with its own sidebar.
 */
function credits_create_admin_module()
{
    $module_dir = MYBB_ADMIN_DIR . 'modules/credits';

    if (!is_dir($module_dir)) {
        @mkdir($module_dir, 0755, true);
    }

    $meta_file = $module_dir . '/module_meta.php';

    $meta_content = <<<'PHP'
<?php
/**
 * Credits - ACP Module Meta
 * Registers Credits as a top-level admin module.
 * Auto-generated by the Credits plugin installer.
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

function credits_meta()
{
    global $page, $lang, $plugins;

    $lang->load('credits', false, true);

    $sub_menu = array();
    $sub_menu['10'] = array('id' => 'main', 'title' => $lang->credits_admin_users, 'link' => 'index.php?module=credits-main');
    $sub_menu['20'] = array('id' => 'adjust', 'title' => $lang->credits_admin_adjust, 'link' => 'index.php?module=credits-main&action=adjust');
    $sub_menu['30'] = array('id' => 'log', 'title' => $lang->credits_admin_log, 'link' => 'index.php?module=credits-main&action=log');
    $sub_menu['40'] = array('id' => 'categories', 'title' => $lang->credits_admin_categories, 'link' => 'index.php?module=credits-main&action=categories');
    $sub_menu['50'] = array('id' => 'shop', 'title' => $lang->credits_admin_shop, 'link' => 'index.php?module=credits-main&action=shop');
    $sub_menu['60'] = array('id' => 'packs', 'title' => $lang->credits_admin_packs, 'link' => 'index.php?module=credits-main&action=packs');
    $sub_menu['70'] = array('id' => 'payments', 'title' => $lang->credits_admin_payments, 'link' => 'index.php?module=credits-main&action=payments');
    $sub_menu['80'] = array('id' => 'gifts', 'title' => $lang->credits_admin_gifts, 'link' => 'index.php?module=credits-main&action=gifts');
    $sub_menu['90'] = array('id' => 'ads', 'title' => $lang->credits_admin_ads, 'link' => 'index.php?module=credits-main&action=ads');
    $sub_menu['100'] = array('id' => 'achievements', 'title' => $lang->credits_admin_achievements, 'link' => 'index.php?module=credits-main&action=achievements');
    $sub_menu['110'] = array('id' => 'lottery', 'title' => $lang->credits_admin_lottery, 'link' => 'index.php?module=credits-main&action=lottery');
    $sub_menu['120'] = array('id' => 'referrals', 'title' => $lang->credits_admin_referrals, 'link' => 'index.php?module=credits-main&action=referrals');

    $sub_menu = $plugins->run_hooks('admin_credits_menu', $sub_menu);

    $page->add_menu_item($lang->credits_admin_menu, 'credits', 'index.php?module=credits', 55, $sub_menu);

    return true;
}

function credits_action_handler($action)
{
    global $page, $mybb;

    $page->active_module = 'credits';

    // Map the action to determine active sidebar item
    $valid_actions = array('main');
    if (!in_array($action, $valid_actions)) {
        $action = 'main';
    }

    $page->active_action = $action;

    // All routing handled by admin_load hook in credits/admin.php
    return 'main.php';
}

function credits_admin_permissions()
{
    global $lang;

    $lang->load('credits', false, true);

    $admin_permissions = array(
        'main' => $lang->credits_admin_perm,
    );

    return array('name' => $lang->credits_admin_menu, 'permissions' => $admin_permissions, 'disporder' => 55);
}
PHP;

    @file_put_contents($meta_file, $meta_content);
}

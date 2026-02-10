<?php

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

global $credits_custom_actions;
if (!isset($credits_custom_actions)) {
    $credits_custom_actions = array();
}

function credits_api_is_active(): bool
{
    global $mybb, $cache;

    static $is_active = null;
    if ($is_active !== null) {
        return $is_active;
    }

    $plugins = $cache->read('plugins');
    if (empty($plugins['active']) || !in_array('credits', $plugins['active'])) {
        $is_active = false;
        return false;
    }

    if (!defined('CREDITS_PLUGIN_PATH')) {
        define('CREDITS_PLUGIN_PATH', MYBB_ROOT . 'inc/plugins/credits/');
    }
    if (!function_exists('credits_get')) {
        require_once CREDITS_PLUGIN_PATH . 'core.php';
    }

    $is_active = true;
    return true;
}

function credits_api_register_action(string $action_code, string $display_name): void
{
    global $credits_custom_actions;
    $credits_custom_actions[$action_code] = $display_name;
}

function credits_api_get_currency_name(): string
{
    global $mybb;
    return $mybb->settings['credits_currency_name'] ?? 'Credits';
}

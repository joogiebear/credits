<?php
/**
 * Credits - Payments Module
 *
 * Handles Coinbase Commerce and Lemon Squeezy payment gateway integrations
 * for purchasing credit packs and shop items with real money.
 */

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

/**
 * Display the credit packs listing page.
 *
 * @return string Evaluated template HTML
 */
function credits_page_packs(): string
{
    global $mybb, $db, $templates, $lang, $theme, $credits_base_url;

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    $query = $db->simple_select('credits_packs', '*', "active = '1'", array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));

    $pack_rows = '';
    if ($db->num_rows($query) > 0) {
        while ($pack = $db->fetch_array($query)) {
            $alt_bg = alt_trow();
            $pack['name'] = htmlspecialchars_uni($pack['name']);
            $pack['credits'] = my_number_format($pack['credits']);
            $pack['price_usd'] = number_format((float)$pack['price_usd'], 2);
            $pack_price_display = '$' . $pack['price_usd'];

            // Build buy buttons based on enabled gateways
            $pack_buy_buttons = '';
            $pack_id = (int)$pack['pack_id'];

            if ($mybb->settings['credits_coinbase_enabled'] == 1) {
                $pack_buy_buttons .= '<a href="' . credits_url('credits', array('view' => 'packs', 'do' => 'buy', 'pack_id' => $pack_id, 'gateway' => 'coinbase', 'my_post_key' => $mybb->post_code)) . '" class="button">' . $lang->credits_pay_coinbase . '</a> ';
            }
            if ($mybb->settings['credits_lemonsqueezy_enabled'] == 1) {
                $pack_buy_buttons .= '<a href="' . credits_url('credits', array('view' => 'packs', 'do' => 'buy', 'pack_id' => $pack_id, 'gateway' => 'lemonsqueezy', 'my_post_key' => $mybb->post_code)) . '" class="button">' . $lang->credits_pay_lemonsqueezy . '</a>';
            }

            eval('$pack_rows .= "' . $templates->get('credits_packs_item') . '";');
        }
    } else {
        eval('$pack_rows = "' . $templates->get('credits_packs_empty') . '";');
    }

    $output = '';
    eval('$output = "' . $templates->get('credits_packs') . '";');
    return $output;
}

/**
 * Initiate a credit pack purchase via a payment gateway.
 *
 * @return string Redirect or error
 */
function credits_page_buy_pack(): string
{
    global $mybb, $db, $lang, $templates, $theme;

    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    $pack_id = $mybb->get_input('pack_id', MyBB::INPUT_INT);
    $gateway = $mybb->get_input('gateway');

    $query = $db->simple_select('credits_packs', '*', "pack_id = '{$pack_id}' AND active = '1'");
    $pack = $db->fetch_array($query);

    if (!$pack) {
        error($lang->credits_pack_not_found);
    }

    $uid = (int)$mybb->user['uid'];
    $amount_usd = (float)$pack['price_usd'];
    $name = $pack['name'];

    $redirect_url = '';

    if ($gateway == 'coinbase' && $mybb->settings['credits_coinbase_enabled'] == 1) {
        $result = credits_coinbase_create_charge($uid, 'pack', $pack_id, $amount_usd, $name);
        if ($result && !empty($result['hosted_url'])) {
            $redirect_url = $result['hosted_url'];
        }
    } elseif ($gateway == 'lemonsqueezy' && $mybb->settings['credits_lemonsqueezy_enabled'] == 1) {
        $result = credits_lemonsqueezy_create_checkout($uid, 'pack', $pack_id, $amount_usd, $name);
        if ($result && !empty($result['checkout_url'])) {
            $redirect_url = $result['checkout_url'];
        }
    } else {
        error($lang->credits_payment_gateway_error);
    }

    if (empty($redirect_url)) {
        error($lang->credits_payment_gateway_error);
    }

    header('Location: ' . $redirect_url);
    exit;
}

/**
 * Initiate a direct shop item purchase with real money.
 *
 * @return string Redirect or error
 */
function credits_page_buy_item(): string
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    if ($mybb->user['uid'] == 0) {
        error_no_permission();
    }

    $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
    $gateway = $mybb->get_input('gateway');

    $query = $db->simple_select('credits_shop', '*', "iid = '{$iid}' AND active = '1'");
    $item = $db->fetch_array($query);

    if (!$item || (float)$item['price_usd'] <= 0) {
        error($lang->credits_item_not_found);
    }

    $uid = (int)$mybb->user['uid'];
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
    } else {
        error($lang->credits_payment_gateway_error);
    }

    if (empty($redirect_url)) {
        error($lang->credits_payment_gateway_error);
    }

    header('Location: ' . $redirect_url);
    exit;
}

// ---- Coinbase Commerce ----

/**
 * Create a Coinbase Commerce charge.
 *
 * @param int    $uid        User ID
 * @param string $type       'pack' or 'item'
 * @param int    $ref_id     Pack ID or item ID
 * @param float  $amount_usd Price in USD
 * @param string $name       Product name
 * @return array|null Array with 'hosted_url' or null on failure
 */
function credits_coinbase_create_charge(int $uid, string $type, int $ref_id, float $amount_usd, string $name): ?array
{
    global $mybb, $db;

    $api_key = $mybb->settings['credits_coinbase_api_key'];
    if (empty($api_key)) {
        return null;
    }

    $payload = array(
        'name'          => $name,
        'description'   => "Credits purchase for user #{$uid}",
        'pricing_type'  => 'fixed_price',
        'local_price'   => array(
            'amount'   => number_format($amount_usd, 2, '.', ''),
            'currency' => 'USD',
        ),
        'metadata'      => array(
            'uid'     => $uid,
            'type'    => $type,
            'ref_id'  => $ref_id,
        ),
        'redirect_url'  => $mybb->settings['bburl'] . '/' . credits_url('credits', array('view' => 'packs')),
        'cancel_url'    => $mybb->settings['bburl'] . '/' . credits_url('credits', array('view' => 'packs')),
    );

    $ch = curl_init('https://api.commerce.coinbase.com/charges');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'X-CC-Api-Key: ' . $api_key,
            'X-CC-Version: 2018-03-22',
        ),
        CURLOPT_TIMEOUT        => 30,
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 201 || empty($response)) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data['data']['id']) || empty($data['data']['hosted_url'])) {
        return null;
    }

    // Record payment
    $payment_data = array(
        'uid'          => $uid,
        'gateway'      => 'coinbase',
        'type'         => $type,
        'reference_id' => $ref_id,
        'external_id'  => $db->escape_string($data['data']['id']),
        'amount_usd'   => $amount_usd,
        'status'       => 'pending',
        'dateline'     => TIME_NOW,
    );
    $db->insert_query('credits_payments', $payment_data);

    return array('hosted_url' => $data['data']['hosted_url']);
}

/**
 * Verify a Coinbase Commerce webhook signature.
 *
 * @param string $payload   Raw POST body
 * @param string $signature X-CC-Webhook-Signature header value
 * @return bool
 */
function credits_coinbase_verify_webhook(string $payload, string $signature): bool
{
    global $mybb;

    $secret = $mybb->settings['credits_coinbase_webhook_secret'];
    if (empty($secret)) {
        return false;
    }

    $computed = hash_hmac('sha256', $payload, $secret);
    return hash_equals($computed, $signature);
}

/**
 * Handle a Coinbase Commerce webhook event.
 *
 * @param array $event The webhook event data
 */
function credits_coinbase_handle_webhook(array $event): void
{
    global $db;

    $event_type = $event['type'] ?? '';

    if ($event_type != 'charge:confirmed') {
        return;
    }

    $charge_id = $event['data']['id'] ?? '';
    if (empty($charge_id)) {
        return;
    }

    // Find the payment
    $query = $db->simple_select('credits_payments', '*',
        "external_id = '" . $db->escape_string($charge_id) . "' AND gateway = 'coinbase' AND status = 'pending'"
    );
    $payment = $db->fetch_array($query);

    if (!$payment) {
        return;
    }

    // Mark as completed
    $db->update_query('credits_payments', array('status' => 'completed'),
        "payment_id = '{$payment['payment_id']}'"
    );

    // Fulfill the payment
    credits_fulfill_payment((int)$payment['payment_id']);
}

// ---- Lemon Squeezy ----

/**
 * Create a Lemon Squeezy checkout.
 *
 * @param int    $uid        User ID
 * @param string $type       'pack' or 'item'
 * @param int    $ref_id     Pack ID or item ID
 * @param float  $amount_usd Price in USD
 * @param string $name       Product name
 * @return array|null Array with 'checkout_url' or null on failure
 */
function credits_lemonsqueezy_create_checkout(int $uid, string $type, int $ref_id, float $amount_usd, string $name): ?array
{
    global $mybb, $db;

    $api_key = $mybb->settings['credits_lemonsqueezy_api_key'];
    $store_id = $mybb->settings['credits_lemonsqueezy_store_id'];

    if (empty($api_key) || empty($store_id)) {
        return null;
    }

    // Lemon Squeezy requires a variant_id. For dynamic pricing, we use the custom price override.
    // Store admin should create a "Credits" product in Lemon Squeezy and provide the variant ID.
    // For simplicity, we use the store_id to create a custom checkout.

    $payload = array(
        'data' => array(
            'type'       => 'checkouts',
            'attributes' => array(
                'custom_price' => (int)($amount_usd * 100), // cents
                'product_options' => array(
                    'name'        => $name,
                    'description' => "Credits purchase for user #{$uid}",
                ),
                'checkout_data' => array(
                    'custom' => array(
                        'uid'    => (string)$uid,
                        'type'   => $type,
                        'ref_id' => (string)$ref_id,
                    ),
                ),
                'checkout_options' => array(
                    'button_color' => '#7047EB',
                ),
                'expires_at'   => date('c', TIME_NOW + 3600),
            ),
            'relationships' => array(
                'store' => array(
                    'data' => array(
                        'type' => 'stores',
                        'id'   => $store_id,
                    ),
                ),
                'variant' => array(
                    'data' => array(
                        'type' => 'variants',
                        'id'   => $mybb->settings['credits_lemonsqueezy_variant_id'] ?? '1',
                    ),
                ),
            ),
        ),
    );

    $ch = curl_init('https://api.lemonsqueezy.com/v1/checkouts');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Bearer ' . $api_key,
        ),
        CURLOPT_TIMEOUT        => 30,
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 201 || empty($response)) {
        return null;
    }

    $data = json_decode($response, true);
    $checkout_url = $data['data']['attributes']['url'] ?? '';

    if (empty($checkout_url)) {
        return null;
    }

    $external_id = $data['data']['id'] ?? '';

    // Record payment
    $payment_data = array(
        'uid'          => $uid,
        'gateway'      => 'lemonsqueezy',
        'type'         => $type,
        'reference_id' => $ref_id,
        'external_id'  => $db->escape_string($external_id),
        'amount_usd'   => $amount_usd,
        'status'       => 'pending',
        'dateline'     => TIME_NOW,
    );
    $db->insert_query('credits_payments', $payment_data);

    return array('checkout_url' => $checkout_url);
}

/**
 * Verify a Lemon Squeezy webhook signature.
 *
 * @param string $payload   Raw POST body
 * @param string $signature X-Signature header value
 * @return bool
 */
function credits_lemonsqueezy_verify_webhook(string $payload, string $signature): bool
{
    global $mybb;

    $secret = $mybb->settings['credits_lemonsqueezy_webhook_secret'];
    if (empty($secret)) {
        return false;
    }

    $computed = hash_hmac('sha256', $payload, $secret);
    return hash_equals($computed, $signature);
}

/**
 * Handle a Lemon Squeezy webhook event.
 *
 * @param array $data The webhook payload
 */
function credits_lemonsqueezy_handle_webhook(array $data): void
{
    global $db;

    $event_name = $data['meta']['event_name'] ?? '';

    if ($event_name != 'order_created') {
        return;
    }

    // Extract custom data
    $custom = $data['meta']['custom_data'] ?? array();
    $uid = (int)($custom['uid'] ?? 0);
    $type = $custom['type'] ?? '';
    $ref_id = (int)($custom['ref_id'] ?? 0);

    if ($uid <= 0 || empty($type)) {
        return;
    }

    $order_id = (string)($data['data']['id'] ?? '');

    // Find the payment by matching uid + type + ref_id + gateway (since external_id may be checkout ID not order ID)
    $query = $db->simple_select('credits_payments', '*',
        "uid = '{$uid}' AND gateway = 'lemonsqueezy' AND type = '" . $db->escape_string($type) . "' AND reference_id = '{$ref_id}' AND status = 'pending'",
        array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1)
    );
    $payment = $db->fetch_array($query);

    if (!$payment) {
        return;
    }

    // Update external_id to order ID and mark completed
    $db->update_query('credits_payments', array(
        'external_id' => $db->escape_string($order_id),
        'status'      => 'completed',
    ), "payment_id = '{$payment['payment_id']}'");

    // Fulfill the payment
    credits_fulfill_payment((int)$payment['payment_id']);
}

// ---- Shared Fulfillment ----

/**
 * Fulfill a completed payment - add credits for packs or apply item purchase.
 *
 * @param int $payment_id Payment ID
 */
function credits_fulfill_payment(int $payment_id): void
{
    global $db;

    $query = $db->simple_select('credits_payments', '*', "payment_id = '{$payment_id}' AND status = 'completed'");
    $payment = $db->fetch_array($query);

    if (!$payment) {
        return;
    }

    $uid = (int)$payment['uid'];
    $type = $payment['type'];
    $ref_id = (int)$payment['reference_id'];

    if ($type == 'pack') {
        // Credit pack - add credits
        $query = $db->simple_select('credits_packs', 'credits', "pack_id = '{$ref_id}'");
        $credits = (int)$db->fetch_field($query, 'credits');

        if ($credits > 0) {
            credits_add_direct($uid, $credits, 'payment', $payment_id);
        }
    } elseif ($type == 'item') {
        // Direct item purchase - create purchase and apply
        $query = $db->simple_select('credits_shop', '*', "iid = '{$ref_id}'");
        $item = $db->fetch_array($query);

        if (!$item) {
            return;
        }

        $item_data = json_decode($item['data'], true) ?: array();
        $purchase_value = '';
        $expires = 0;

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
                // Boosters go to inventory inactive — user activates from inventory
                break;
            case 'usergroup':
                $purchase_value = (string)($item_data['gid'] ?? 0);
                $duration = (int)($item_data['duration'] ?? 0);
                if ($duration > 0) {
                    $expires = TIME_NOW + $duration;
                }
                break;
        }

        // Deactivate previous same-type purchase (except boosters and awards)
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

        // Boosters go to inventory inactive
        $purchase_active = ($item['type'] === 'booster') ? 0 : 1;

        // Record purchase
        $purchase_data = array(
            'uid'      => $uid,
            'iid'      => $ref_id,
            'value'    => $db->escape_string($purchase_value),
            'dateline' => TIME_NOW,
            'expires'  => $expires,
            'active'   => $purchase_active,
        );
        $db->insert_query('credits_purchases', $purchase_data);

        // Apply (skip for inactive items like boosters)
        if ($purchase_active) {
            require_once CREDITS_PLUGIN_PATH . 'shop.php';
            credits_apply_purchase($uid, $item['type'], $purchase_value);
        }

        // Handle usergroup sub record
        if ($item['type'] == 'usergroup') {
            $gid = (int)($item_data['gid'] ?? 0);
            $last_pid = $db->insert_id();
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
                credits_add_direct($uid, $bonus_credits, 'purchase_bonus', $ref_id);
            }

            // Grant bonus booster if configured
            $bonus_mult = (int)($item_data['bonus_booster_multiplier'] ?? 0);
            $bonus_dur = (int)($item_data['bonus_booster_duration'] ?? 0);
            if ($bonus_mult > 0 && $bonus_dur > 0) {
                $booster_purchase = array(
                    'uid'      => $uid,
                    'iid'      => $ref_id,
                    'value'    => 'bonus_booster',
                    'dateline' => TIME_NOW,
                    'expires'  => TIME_NOW + $bonus_dur,
                    'active'   => 1,
                );
                $db->insert_query('credits_purchases', $booster_purchase);
            }
        }
    }
}

<?php
/**
 * Credits - ACP Module
 *
 * Admin control panel functionality for managing credits, users, shop items,
 * categories, and logs.
 */

if (!defined('IN_MYBB') || !defined('IN_ADMINCP')) {
    die('This file cannot be accessed directly.');
}

// ---- ACP Page Handler ----

// Hook into admin_load to handle the credits module page
$plugins->add_hook('admin_load', 'credits_admin_load');

function credits_admin_load()
{
    global $mybb, $db, $lang, $page, $run_module, $action_file;

    if ($run_module != 'credits') {
        return;
    }

    $lang->load('credits', false, true);

    require_once MYBB_ROOT . 'inc/plugins/credits/core.php';

    $page->add_breadcrumb_item($lang->credits_admin_menu, 'index.php?module=credits-main');

    // Determine current action and update active sidebar highlighting
    $action = $mybb->get_input('action');

    // Map sub-actions to their parent sidebar section for highlighting
    $action_to_section = array(
        'adjust'        => 'adjust',
        'do_adjust'     => 'adjust',
        'log'           => 'log',
        'categories'    => 'categories',
        'cat_add'       => 'categories',
        'cat_edit'      => 'categories',
        'cat_do_save'   => 'categories',
        'cat_delete'    => 'categories',
        'shop'          => 'shop',
        'shop_add'      => 'shop',
        'shop_edit'     => 'shop',
        'shop_do_save'  => 'shop',
        'shop_delete'   => 'shop',
        'packs'         => 'packs',
        'pack_add'      => 'packs',
        'pack_edit'     => 'packs',
        'pack_do_save'  => 'packs',
        'pack_delete'   => 'packs',
        'payments'      => 'payments',
        'gifts'         => 'gifts',
        'ads'           => 'ads',
        'ad_toggle'     => 'ads',
        'achievements'  => 'achievements',
        'ach_add'       => 'achievements',
        'ach_edit'      => 'achievements',
        'ach_do_save'   => 'achievements',
        'ach_delete'    => 'achievements',
        'lottery'       => 'lottery',
        'lottery_add'   => 'lottery',
        'lottery_edit'  => 'lottery',
        'lottery_do_save' => 'lottery',
        'lottery_delete'  => 'lottery',
        'referrals'     => 'referrals',
    );

    // Override active_action so the sidebar highlights the correct section
    $page->active_action = isset($action_to_section[$action]) ? $action_to_section[$action] : 'main';

    switch ($action) {
        case 'adjust':
            credits_admin_adjust($page);
            break;
        case 'do_adjust':
            credits_admin_do_adjust($page);
            break;
        case 'log':
            credits_admin_log($page);
            break;
        case 'categories':
            credits_admin_categories($page);
            break;
        case 'cat_add':
        case 'cat_edit':
            credits_admin_cat_form($page);
            break;
        case 'cat_do_save':
            credits_admin_cat_save($page);
            break;
        case 'cat_delete':
            credits_admin_cat_delete($page);
            break;
        case 'shop':
            credits_admin_shop($page);
            break;
        case 'shop_add':
        case 'shop_edit':
            credits_admin_shop_form($page);
            break;
        case 'shop_do_save':
            credits_admin_shop_save($page);
            break;
        case 'shop_delete':
            credits_admin_shop_delete($page);
            break;
        case 'packs':
            credits_admin_packs($page);
            break;
        case 'pack_add':
        case 'pack_edit':
            credits_admin_pack_form($page);
            break;
        case 'pack_do_save':
            credits_admin_pack_save($page);
            break;
        case 'pack_delete':
            credits_admin_pack_delete($page);
            break;
        case 'payments':
            credits_admin_payments($page);
            break;
        case 'gifts':
            credits_admin_gifts($page);
            break;
        case 'ads':
            credits_admin_ads($page);
            break;
        case 'ad_toggle':
            credits_admin_ad_toggle($page);
            break;
        case 'achievements':
            credits_admin_achievements($page);
            break;
        case 'ach_add':
        case 'ach_edit':
            credits_admin_ach_form($page);
            break;
        case 'ach_do_save':
            credits_admin_ach_save($page);
            break;
        case 'ach_delete':
            credits_admin_ach_delete($page);
            break;
        case 'lottery':
            credits_admin_lottery($page);
            break;
        case 'lottery_add':
        case 'lottery_edit':
            credits_admin_lottery_form($page);
            break;
        case 'lottery_do_save':
            credits_admin_lottery_save($page);
            break;
        case 'lottery_delete':
            credits_admin_lottery_delete($page);
            break;
        case 'referrals':
            credits_admin_referrals($page);
            break;
        default:
            credits_admin_users($page);
            break;
    }

    exit;
}

/**
 * ACP: User listing with credit balances.
 */
function credits_admin_users($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_menu);


    // Search form
    $search_username = htmlspecialchars_uni($mybb->get_input('username'));

    $form = new Form('index.php?module=credits-main', 'post');
    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right">';
    echo $form->generate_text_box('username', $search_username, array('style' => 'width: 200px;'));
    echo ' ';
    echo $form->generate_submit_button($lang->credits_admin_search);
    echo '</div></div>';
    $form->end();

    // Build query conditions
    $where = '';
    if (!empty($search_username)) {
        $where = "username LIKE '%" . $db->escape_string_like($search_username) . "%'";
    }

    // Pagination
    $per_page = 20;
    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($current_page < 1) $current_page = 1;
    $start = ($current_page - 1) * $per_page;

    $query = $db->simple_select('users', 'COUNT(*) as total', $where);
    $total = (int)$db->fetch_field($query, 'total');

    // Sorting
    $sort_by = $mybb->get_input('sort_by');
    $sort_dir = $mybb->get_input('sort_dir');
    if (!in_array($sort_by, array('username', 'credits', 'postnum'))) $sort_by = 'credits';
    if (!in_array($sort_dir, array('asc', 'desc'))) $sort_dir = 'desc';

    $query = $db->simple_select('users', 'uid, username, credits, postnum', $where, array(
        'order_by'    => $sort_by,
        'order_dir'   => $sort_dir,
        'limit'       => $per_page,
        'limit_start' => $start,
    ));

    $table = new Table;
    $table->construct_header($lang->credits_username, array('width' => '40%'));
    $table->construct_header($lang->credits, array('class' => 'align_center', 'width' => '20%'));
    $table->construct_header($lang->credits_posts, array('class' => 'align_center', 'width' => '20%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '20%'));

    while ($user = $db->fetch_array($query)) {
        $table->construct_cell('<a href="index.php?module=user-users&action=edit&uid=' . $user['uid'] . '">' . htmlspecialchars_uni($user['username']) . '</a>');
        $table->construct_cell(my_number_format($user['credits']), array('class' => 'align_center'));
        $table->construct_cell(my_number_format($user['postnum']), array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=adjust&uid=' . $user['uid'] . '">' . $lang->credits_admin_adjust . '</a>'
            . ' | <a href="index.php?module=credits-main&action=log&uid=' . $user['uid'] . '">' . $lang->credits_admin_log . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_no_users, array('colspan' => 4, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_users);

    // Pagination
    echo draw_admin_pagination($current_page, $per_page, $total, 'index.php?module=credits-main&username=' . urlencode($search_username) . '&sort_by=' . $sort_by . '&sort_dir=' . $sort_dir);

    $page->output_footer();
}

/**
 * ACP: Credit adjustment form.
 */
function credits_admin_adjust($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_adjust);


    $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    $username = '';
    $current_credits = 0;

    if ($uid > 0) {
        $query = $db->simple_select('users', 'username, credits', "uid = '{$uid}'");
        $user = $db->fetch_array($query);
        if ($user) {
            $username = $user['username'];
            $current_credits = (int)$user['credits'];
        }
    }

    $form = new Form('index.php?module=credits-main&action=do_adjust', 'post');

    $form_container = new FormContainer($lang->credits_admin_adjust);
    $form_container->output_row(
        $lang->credits_username,
        $lang->credits_admin_adjust_user_desc,
        $form->generate_text_box('username', htmlspecialchars_uni($username), array('id' => 'username'))
    );
    $form_container->output_row(
        $lang->credits_admin_current,
        '',
        '<strong>' . my_number_format($current_credits) . '</strong>'
    );
    $form_container->output_row(
        $lang->credits_admin_adjust_type,
        $lang->credits_admin_adjust_type_desc,
        $form->generate_select_box('adjust_type', array(
            'add'      => $lang->credits_admin_add,
            'subtract' => $lang->credits_admin_subtract,
            'set'      => $lang->credits_admin_set,
        ), 'add')
    );
    $form_container->output_row(
        $lang->credits_admin_amount,
        $lang->credits_admin_amount_desc,
        $form->generate_numeric_field('amount', 0, array('min' => 0))
    );
    $form_container->output_row(
        $lang->credits_admin_reason,
        $lang->credits_admin_reason_desc,
        $form->generate_text_box('reason', '', array('id' => 'reason'))
    );
    $form_container->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_adjust_submit));
    $form->output_submit_wrapper($buttons);
    $form->end();

    // AutoComplete script for username
    echo '<link rel="stylesheet" href="./jscripts/select2/select2.css" />';
    echo '<script type="text/javascript" src="./jscripts/select2/select2.min.js"></script>';

    $page->output_footer();
}

/**
 * ACP: Process credit adjustment.
 */
function credits_admin_do_adjust($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $username = $mybb->get_input('username');
    $adjust_type = $mybb->get_input('adjust_type');
    $amount = $mybb->get_input('amount', MyBB::INPUT_INT);

    if (empty($username)) {
        flash_message($lang->credits_admin_no_user, 'error');
        admin_redirect('index.php?module=credits-main&action=adjust');
    }

    $query = $db->simple_select('users', 'uid, credits', "username = '" . $db->escape_string($username) . "'");
    $user = $db->fetch_array($query);

    if (!$user) {
        flash_message($lang->credits_admin_user_not_found, 'error');
        admin_redirect('index.php?module=credits-main&action=adjust');
    }

    $uid = (int)$user['uid'];

    switch ($adjust_type) {
        case 'add':
            if ($amount > 0) {
                credits_add($uid, $amount, 'admin_adjust');
            }
            break;
        case 'subtract':
            if ($amount > 0) {
                credits_subtract($uid, $amount, 'admin_adjust');
            }
            break;
        case 'set':
            credits_set($uid, $amount, 'admin_adjust');
            break;
    }

    flash_message($lang->credits_admin_adjusted, 'success');
    admin_redirect('index.php?module=credits-main&action=adjust&uid=' . $uid);
}

/**
 * ACP: Credit log viewer.
 */
function credits_admin_log($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_log);


    $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    $filter_action = $db->escape_string($mybb->get_input('filter_action'));

    $where_clauses = array();
    if ($uid > 0) {
        $where_clauses[] = "l.uid = '{$uid}'";
    }
    if (!empty($filter_action)) {
        $where_clauses[] = "l.action = '{$filter_action}'";
    }

    $where = !empty($where_clauses) ? implode(' AND ', $where_clauses) : '1=1';

    // Pagination
    $per_page = 25;
    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($current_page < 1) $current_page = 1;
    $start = ($current_page - 1) * $per_page;

    $query = $db->query("
        SELECT COUNT(*) as total
        FROM " . TABLE_PREFIX . "credits_log l
        WHERE {$where}
    ");
    $total = (int)$db->fetch_field($query, 'total');

    $query = $db->query("
        SELECT l.*, u.username
        FROM " . TABLE_PREFIX . "credits_log l
        LEFT JOIN " . TABLE_PREFIX . "users u ON l.uid = u.uid
        WHERE {$where}
        ORDER BY l.dateline DESC
        LIMIT {$start}, {$per_page}
    ");

    // Filter form
    $form = new Form('index.php?module=credits-main&action=log', 'get');
    echo '<input type="hidden" name="module" value="credits-main" />';
    echo '<input type="hidden" name="action" value="log" />';
    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right">';
    echo $form->generate_select_box('filter_action', array(
        ''               => $lang->credits_admin_all_actions,
        'post'           => $lang->credits_action_post,
        'thread'         => $lang->credits_action_thread,
        'rep'            => $lang->credits_action_rep,
        'login'          => $lang->credits_action_login,
        'purchase'       => $lang->credits_action_purchase,
        'purchase_bonus' => $lang->credits_admin_action_purchase_bonus ?? 'Purchase Bonus',
        'achievement'    => $lang->credits_admin_action_achievement ?? 'Achievement',
        'lottery'        => $lang->credits_admin_action_lottery ?? 'Lottery',
        'referral'       => $lang->credits_admin_action_referral ?? 'Referral',
        'admin_adjust'   => $lang->credits_action_admin,
        'gift_sent'      => $lang->credits_action_gift_sent,
        'gift_received'  => $lang->credits_action_gift_received,
        'payment'        => $lang->credits_action_payment,
    ), $filter_action);
    echo ' ';
    echo $form->generate_submit_button($lang->credits_admin_filter);
    echo '</div></div>';
    $form->end();

    $table = new Table;
    $table->construct_header($lang->credits_username, array('width' => '20%'));
    $table->construct_header($lang->credits_log_action, array('width' => '20%'));
    $table->construct_header($lang->credits_log_amount, array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header($lang->credits_log_balance, array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header($lang->credits_log_date, array('width' => '30%'));

    while ($log = $db->fetch_array($query)) {
        $username_display = htmlspecialchars_uni($log['username'] ?? 'Unknown');
        $action_name = credits_action_name($log['action']);
        $amount_display = credits_format((int)$log['amount']);
        $log_date = my_date('jS M Y, g:i A', $log['dateline']);

        $table->construct_cell('<a href="index.php?module=credits-main&action=log&uid=' . $log['uid'] . '">' . $username_display . '</a>');
        $table->construct_cell($action_name);
        $table->construct_cell($amount_display, array('class' => 'align_center'));
        $table->construct_cell(my_number_format($log['balance']), array('class' => 'align_center'));
        $table->construct_cell($log_date);
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_no_log, array('colspan' => 5, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_log);

    $filter_params = '';
    if ($uid > 0) $filter_params .= '&uid=' . $uid;
    if (!empty($filter_action)) $filter_params .= '&filter_action=' . urlencode($filter_action);

    echo draw_admin_pagination($current_page, $per_page, $total, 'index.php?module=credits-main&action=log' . $filter_params);

    $page->output_footer();
}

// ---- Category Management ----

/**
 * ACP: Category listing.
 */
function credits_admin_categories($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_categories);


    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right"><a href="index.php?module=credits-main&action=cat_add" class="button">' . $lang->credits_admin_cat_add . '</a></div></div>';

    $query = $db->simple_select('credits_categories', '*', '', array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));

    // Count items per category
    $item_counts = array();
    $count_query = $db->query("
        SELECT cid, COUNT(*) as cnt
        FROM " . TABLE_PREFIX . "credits_shop
        GROUP BY cid
    ");
    while ($row = $db->fetch_array($count_query)) {
        $item_counts[(int)$row['cid']] = (int)$row['cnt'];
    }

    $table = new Table;
    $table->construct_header($lang->credits_admin_cat_name, array('width' => '25%'));
    $table->construct_header($lang->credits_admin_cat_description, array('width' => '25%'));
    $table->construct_header($lang->credits_admin_cat_items, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_disporder, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_status, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_cat_visible, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '10%'));

    while ($cat = $db->fetch_array($query)) {
        $cid = (int)$cat['cid'];
        $table->construct_cell(htmlspecialchars_uni($cat['name']));
        $table->construct_cell(htmlspecialchars_uni($cat['description']));
        $table->construct_cell($item_counts[$cid] ?? 0, array('class' => 'align_center'));
        $table->construct_cell((int)$cat['disporder'], array('class' => 'align_center'));
        $table->construct_cell($cat['active'] ? $lang->credits_admin_active : $lang->credits_admin_inactive, array('class' => 'align_center'));
        $table->construct_cell($cat['visible'] ? $lang->credits_admin_cat_listed : $lang->credits_admin_cat_unlisted, array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=cat_edit&cid=' . $cid . '">' . $lang->credits_admin_edit . '</a>'
            . ' | <a href="index.php?module=credits-main&action=cat_delete&cid=' . $cid . '&my_post_key=' . $mybb->post_code . '" onclick="return AdminCP.deleteConfirmation(this, \'' . $lang->credits_admin_cat_delete_confirm . '\')">' . $lang->credits_admin_delete . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_categories, array('colspan' => 7, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_categories);

    $page->output_footer();
}

/**
 * ACP: Category add/edit form.
 */
function credits_admin_cat_form($page)
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('action');
    $is_edit = ($action == 'cat_edit');

    $cat = array(
        'name'        => '',
        'description' => '',
        'disporder'   => 0,
        'active'      => 1,
        'visible'     => 1,
    );

    if ($is_edit) {
        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
        $query = $db->simple_select('credits_categories', '*', "cid = '{$cid}'");
        $cat = $db->fetch_array($query);

        if (!$cat) {
            flash_message($lang->credits_admin_cat_not_found, 'error');
            admin_redirect('index.php?module=credits-main&action=categories');
        }

        $page->output_header($lang->credits_admin_cat_edit);
    } else {
        $page->output_header($lang->credits_admin_cat_add);
    }



    $form = new Form('index.php?module=credits-main&action=cat_do_save', 'post');

    if ($is_edit) {
        echo $form->generate_hidden_field('cid', $cat['cid']);
    }

    $form_container = new FormContainer($is_edit ? $lang->credits_admin_cat_edit : $lang->credits_admin_cat_add);

    $form_container->output_row(
        $lang->credits_admin_cat_name,
        $lang->credits_admin_cat_name_desc,
        $form->generate_text_box('name', htmlspecialchars_uni($cat['name']))
    );
    $form_container->output_row(
        $lang->credits_admin_cat_description,
        $lang->credits_admin_cat_desc_desc,
        $form->generate_text_area('description', htmlspecialchars_uni($cat['description']))
    );
    $form_container->output_row(
        $lang->credits_admin_disporder,
        '',
        $form->generate_numeric_field('disporder', $cat['disporder'], array('min' => 0))
    );
    $form_container->output_row(
        $lang->credits_admin_status,
        '',
        $form->generate_yes_no_radio('active', $cat['active'])
    );
    $form_container->output_row(
        $lang->credits_admin_cat_visible,
        $lang->credits_admin_cat_visible_desc,
        $form->generate_yes_no_radio('visible', $cat['visible'])
    );

    $form_container->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_save));
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * ACP: Save category (add/edit).
 */
function credits_admin_cat_save($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

    $data = array(
        'name'        => $db->escape_string($mybb->get_input('name')),
        'description' => $db->escape_string($mybb->get_input('description')),
        'disporder'   => $mybb->get_input('disporder', MyBB::INPUT_INT),
        'active'      => $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0,
        'visible'     => $mybb->get_input('visible', MyBB::INPUT_INT) ? 1 : 0,
    );

    if (empty($data['name'])) {
        flash_message($lang->credits_admin_cat_name_required, 'error');
        admin_redirect('index.php?module=credits-main&action=categories');
    }

    if ($cid > 0) {
        $db->update_query('credits_categories', $data, "cid = '{$cid}'");
        flash_message($lang->credits_admin_cat_updated, 'success');
    } else {
        $db->insert_query('credits_categories', $data);
        flash_message($lang->credits_admin_cat_added, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=categories');
}

/**
 * ACP: Delete a category.
 */
function credits_admin_cat_delete($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

    if ($cid > 0) {
        $db->delete_query('credits_categories', "cid = '{$cid}'");

        // Move items in this category to uncategorized
        $db->update_query('credits_shop', array('cid' => 0), "cid = '{$cid}'");

        flash_message($lang->credits_admin_cat_deleted, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=categories');
}

// ---- Shop Item Management ----

/**
 * Helper: get all categories as id => name for select boxes.
 *
 * @return array
 */
function credits_admin_get_category_options(): array
{
    global $db, $lang;

    $options = array(0 => $lang->credits_admin_uncategorized);

    $query = $db->simple_select('credits_categories', 'cid, name', '', array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));
    while ($cat = $db->fetch_array($query)) {
        $options[(int)$cat['cid']] = htmlspecialchars_uni($cat['name']);
    }

    return $options;
}

/**
 * ACP: Shop item management listing.
 */
function credits_admin_shop($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_shop);


    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right"><a href="index.php?module=credits-main&action=shop_add" class="button">' . $lang->credits_admin_shop_add . '</a></div></div>';

    // Load category names
    $categories = array(0 => $lang->credits_admin_uncategorized);
    $cat_query = $db->simple_select('credits_categories', 'cid, name');
    while ($cat = $db->fetch_array($cat_query)) {
        $categories[(int)$cat['cid']] = htmlspecialchars_uni($cat['name']);
    }

    // Item type labels
    $type_labels = array(
        'custom_title'    => $lang->credits_admin_type_title,
        'username_color'  => $lang->credits_admin_type_color,
        'icon'            => $lang->credits_admin_type_icon,
        'award'           => $lang->credits_admin_type_award,
        'booster'         => $lang->credits_admin_type_booster,
        'postbit_bg'      => $lang->credits_admin_type_postbit_bg,
        'username_effect' => $lang->credits_admin_type_effect,
        'usergroup'       => $lang->credits_admin_type_usergroup,
        'ad_space'        => $lang->credits_admin_type_ad,
    );

    $query = $db->simple_select('credits_shop', '*', '', array(
        'order_by'  => 'cid, disporder',
        'order_dir' => 'ASC',
    ));

    $table = new Table;
    $table->construct_header($lang->credits_item_name, array('width' => '20%'));
    $table->construct_header($lang->credits_admin_category, array('width' => '13%'));
    $table->construct_header($lang->credits_admin_type, array('class' => 'align_center', 'width' => '13%'));
    $table->construct_header($lang->credits_item_price, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_stock, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_status, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_disporder, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '20%'));

    while ($item = $db->fetch_array($query)) {
        $cid = (int)$item['cid'];
        $cat_name = $categories[$cid] ?? $lang->credits_admin_uncategorized;
        $type_label = $type_labels[$item['type']] ?? $item['type'];
        $stock_display = ((int)$item['stock'] < 0) ? $lang->credits_admin_stock_unlimited : my_number_format((int)$item['stock']);

        $table->construct_cell(htmlspecialchars_uni($item['name']));
        $table->construct_cell($cat_name);
        $table->construct_cell($type_label, array('class' => 'align_center'));
        $table->construct_cell(my_number_format($item['price']), array('class' => 'align_center'));
        $table->construct_cell($stock_display, array('class' => 'align_center'));
        $table->construct_cell($item['active'] ? $lang->credits_admin_active : $lang->credits_admin_inactive, array('class' => 'align_center'));
        $table->construct_cell((int)$item['disporder'], array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=shop_edit&iid=' . $item['iid'] . '">' . $lang->credits_admin_edit . '</a>'
            . ' | <a href="index.php?module=credits-main&action=shop_delete&iid=' . $item['iid'] . '&my_post_key=' . $mybb->post_code . '" onclick="return AdminCP.deleteConfirmation(this, \'' . $lang->credits_admin_delete_confirm . '\')">' . $lang->credits_admin_delete . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_no_items, array('colspan' => 8, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_shop);

    $page->output_footer();
}

/**
 * ACP: Shop item add/edit form.
 */
function credits_admin_shop_form($page)
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('action');
    $is_edit = ($action == 'shop_edit');

    $item = array(
        'name'        => '',
        'description' => '',
        'cid'         => 0,
        'type'        => 'custom_title',
        'price'       => 0,
        'data'        => '',
        'active'      => 1,
        'disporder'   => 0,
        'stock'       => -1,
    );

    if ($is_edit) {
        $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
        $query = $db->simple_select('credits_shop', '*', "iid = '{$iid}'");
        $item = $db->fetch_array($query);

        if (!$item) {
            flash_message($lang->credits_item_not_found, 'error');
            admin_redirect('index.php?module=credits-main&action=shop');
        }

        $page->output_header($lang->credits_admin_shop_edit);
    } else {
        $page->output_header($lang->credits_admin_shop_add);
    }



    // Parse existing item data
    $item_data = array();
    if (!empty($item['data'])) {
        $item_data = json_decode($item['data'], true) ?: array();
    }

    $form = new Form('index.php?module=credits-main&action=shop_do_save', 'post');

    if ($is_edit) {
        echo $form->generate_hidden_field('iid', $item['iid']);
    }

    $form_container = new FormContainer($is_edit ? $lang->credits_admin_shop_edit : $lang->credits_admin_shop_add);

    // Basic fields
    $form_container->output_row(
        $lang->credits_item_name,
        '',
        $form->generate_text_box('name', htmlspecialchars_uni($item['name']))
    );
    $form_container->output_row(
        $lang->credits_item_desc,
        '',
        $form->generate_text_area('description', htmlspecialchars_uni($item['description']))
    );

    // Category dropdown
    $category_options = credits_admin_get_category_options();
    $form_container->output_row(
        $lang->credits_admin_category,
        $lang->credits_admin_category_desc,
        $form->generate_select_box('cid', $category_options, (int)$item['cid'])
    );

    // Type dropdown
    $form_container->output_row(
        $lang->credits_admin_type,
        $lang->credits_admin_type_desc,
        $form->generate_select_box('type', array(
            'custom_title'    => $lang->credits_admin_type_title,
            'username_color'  => $lang->credits_admin_type_color,
            'icon'            => $lang->credits_admin_type_icon,
            'award'           => $lang->credits_admin_type_award,
            'booster'         => $lang->credits_admin_type_booster,
            'postbit_bg'      => $lang->credits_admin_type_postbit_bg,
            'username_effect' => $lang->credits_admin_type_effect,
            'usergroup'       => $lang->credits_admin_type_usergroup,
            'ad_space'        => $lang->credits_admin_type_ad,
        ), $item['type'], array('id' => 'item_type'))
    );

    $form_container->output_row(
        $lang->credits_item_price,
        '',
        $form->generate_numeric_field('price', $item['price'], array('min' => 0))
    );

    $form_container->output_row(
        $lang->credits_admin_price_usd,
        $lang->credits_admin_price_usd_desc,
        $form->generate_text_box('price_usd', htmlspecialchars_uni($item['price_usd'] ?? '0.00'))
    );

    $form_container->output_row(
        $lang->credits_admin_stock,
        $lang->credits_admin_stock_desc,
        $form->generate_numeric_field('stock', (int)$item['stock'], array('min' => -1))
    );

    $form_container->output_row(
        $lang->credits_admin_status,
        '',
        $form->generate_yes_no_radio('active', $item['active'])
    );

    $form_container->output_row(
        $lang->credits_admin_disporder,
        '',
        $form->generate_numeric_field('disporder', $item['disporder'], array('min' => 0))
    );

    $form_container->end();

    // Icon-specific fields
    $icon_image = $item_data['image'] ?? '';

    $form_container_icon = new FormContainer($lang->credits_admin_icon_settings);

    $form_container_icon->output_row(
        $lang->credits_admin_icon_image,
        $lang->credits_admin_icon_image_desc,
        $form->generate_text_box('icon_image', htmlspecialchars_uni($icon_image), array('id' => 'icon_image'))
        . (!empty($icon_image) ? '<br /><img src="../' . htmlspecialchars_uni($icon_image) . '" alt="preview" style="max-height: 32px; max-width: 32px; margin-top: 5px;" />' : '')
    );

    $form_container_icon->end();

    // Booster-specific fields
    $booster_multiplier = $item_data['multiplier'] ?? 2;
    $booster_duration = $item_data['duration'] ?? 3600;

    $form_container_booster = new FormContainer($lang->credits_admin_booster_settings);

    $form_container_booster->output_row(
        $lang->credits_admin_booster_multiplier,
        $lang->credits_admin_booster_multiplier_desc,
        $form->generate_numeric_field('booster_multiplier', $booster_multiplier, array('min' => 2, 'max' => 10))
    );

    $duration_options = array(
        '1800'  => $lang->credits_admin_dur_30m,
        '3600'  => $lang->credits_admin_dur_1h,
        '10800' => $lang->credits_admin_dur_3h,
        '21600' => $lang->credits_admin_dur_6h,
        '43200' => $lang->credits_admin_dur_12h,
        '86400' => $lang->credits_admin_dur_24h,
        '0'     => $lang->credits_admin_dur_custom,
    );

    $selected_duration = array_key_exists((string)$booster_duration, $duration_options) ? (string)$booster_duration : '0';

    $form_container_booster->output_row(
        $lang->credits_admin_booster_duration,
        $lang->credits_admin_booster_duration_desc,
        $form->generate_select_box('booster_duration_preset', $duration_options, $selected_duration, array('id' => 'booster_duration_preset'))
        . '<br />'
        . $form->generate_numeric_field('booster_duration_custom', $booster_duration, array('min' => 60, 'id' => 'booster_duration_custom'))
        . ' ' . $lang->credits_admin_seconds
    );

    $form_container_booster->end();

    // Award-specific fields
    $award_image = $item_data['image'] ?? '';

    $form_container_award = new FormContainer($lang->credits_admin_award_settings);

    $form_container_award->output_row(
        $lang->credits_admin_award_image,
        $lang->credits_admin_award_image_desc,
        $form->generate_text_box('award_image', htmlspecialchars_uni($award_image), array('id' => 'award_image'))
        . (!empty($award_image) ? '<br /><img src="../' . htmlspecialchars_uni($award_image) . '" alt="preview" style="max-height: 32px; max-width: 32px; margin-top: 5px;" />' : '')
    );

    $form_container_award->end();

    // Postbit Background fields
    $bg_type = $item_data['bg_type'] ?? 'color';
    $bg_value = $item_data['bg_value'] ?? '';

    $form_container_bg = new FormContainer($lang->credits_admin_bg_settings);

    $form_container_bg->output_row(
        $lang->credits_admin_bg_type,
        $lang->credits_admin_bg_type_desc,
        $form->generate_select_box('bg_type', array(
            'color'    => $lang->credits_admin_bg_color,
            'gradient' => $lang->credits_admin_bg_gradient,
            'image'    => $lang->credits_admin_bg_image,
        ), $bg_type, array('id' => 'bg_type'))
    );

    $form_container_bg->output_row(
        $lang->credits_admin_bg_value,
        $lang->credits_admin_bg_value_desc,
        $form->generate_text_box('bg_value', htmlspecialchars_uni($bg_value), array('id' => 'bg_value'))
    );

    $form_container_bg->end();

    // Username Effect fields
    $effect_preset = $item_data['effect'] ?? 'rainbow';

    $form_container_effect = new FormContainer($lang->credits_admin_effect_settings);

    $form_container_effect->output_row(
        $lang->credits_admin_effect_preset,
        $lang->credits_admin_effect_preset_desc,
        $form->generate_select_box('effect_preset', array(
            'rainbow'  => $lang->credits_admin_effect_rainbow,
            'glow'     => $lang->credits_admin_effect_glow,
            'sparkle'  => $lang->credits_admin_effect_sparkle,
            'shadow'   => $lang->credits_admin_effect_shadow,
            'bold'     => $lang->credits_admin_effect_bold,
            'gradient' => $lang->credits_admin_effect_gradient,
        ), $effect_preset, array('id' => 'effect_preset'))
    );

    $form_container_effect->end();

    // Usergroup-specific fields
    $ug_gid = $item_data['gid'] ?? 0;
    $ug_duration = $item_data['duration'] ?? 0;

    $form_container_ug = new FormContainer($lang->credits_admin_ug_settings);

    // Build usergroup select from MyBB usergroups
    $usergroup_options = array();
    $ug_query = $db->simple_select('usergroups', 'gid, title', '', array('order_by' => 'title'));
    while ($group = $db->fetch_array($ug_query)) {
        $usergroup_options[(int)$group['gid']] = htmlspecialchars_uni($group['title']);
    }

    $form_container_ug->output_row(
        $lang->credits_admin_ug_group,
        $lang->credits_admin_ug_group_desc,
        $form->generate_select_box('ug_gid', $usergroup_options, (int)$ug_gid, array('id' => 'ug_gid'))
    );

    $ug_duration_options = array(
        '0'       => $lang->credits_admin_ug_permanent,
        '86400'   => $lang->credits_admin_dur_24h,
        '604800'  => $lang->credits_admin_dur_1w,
        '2592000' => $lang->credits_admin_dur_30d,
        '7776000' => $lang->credits_admin_dur_90d,
        '-1'      => $lang->credits_admin_dur_custom,
    );

    $selected_ug_dur = array_key_exists((string)$ug_duration, $ug_duration_options) ? (string)$ug_duration : '-1';

    $form_container_ug->output_row(
        $lang->credits_admin_ug_duration,
        $lang->credits_admin_ug_duration_desc,
        $form->generate_select_box('ug_duration_preset', $ug_duration_options, $selected_ug_dur, array('id' => 'ug_duration_preset'))
        . '<br />'
        . $form->generate_numeric_field('ug_duration_custom', $ug_duration, array('min' => 0, 'id' => 'ug_duration_custom'))
        . ' ' . $lang->credits_admin_seconds
    );

    // Bonus credits on purchase
    $ug_bonus_credits = $item_data['bonus_credits'] ?? 0;
    $form_container_ug->output_row(
        $lang->credits_admin_ug_bonus_credits,
        $lang->credits_admin_ug_bonus_credits_desc,
        $form->generate_numeric_field('ug_bonus_credits', (int)$ug_bonus_credits, array('min' => 0))
    );

    // Bonus booster multiplier
    $ug_bonus_booster_mult = $item_data['bonus_booster_multiplier'] ?? 0;
    $form_container_ug->output_row(
        $lang->credits_admin_ug_bonus_booster_mult,
        $lang->credits_admin_ug_bonus_booster_mult_desc,
        $form->generate_numeric_field('ug_bonus_booster_multiplier', (int)$ug_bonus_booster_mult, array('min' => 0, 'max' => 10))
    );

    // Bonus booster duration
    $ug_bonus_booster_dur = $item_data['bonus_booster_duration'] ?? 0;

    $bonus_dur_options = array(
        '0'     => $lang->credits_admin_ug_bonus_none,
        '1800'  => $lang->credits_admin_dur_30m,
        '3600'  => $lang->credits_admin_dur_1h,
        '10800' => $lang->credits_admin_dur_3h,
        '21600' => $lang->credits_admin_dur_6h,
        '43200' => $lang->credits_admin_dur_12h,
        '86400' => $lang->credits_admin_dur_24h,
        '-1'    => $lang->credits_admin_dur_custom,
    );

    $selected_bonus_dur = array_key_exists((string)$ug_bonus_booster_dur, $bonus_dur_options) ? (string)$ug_bonus_booster_dur : '-1';

    $form_container_ug->output_row(
        $lang->credits_admin_ug_bonus_booster_dur,
        $lang->credits_admin_ug_bonus_booster_dur_desc,
        $form->generate_select_box('ug_bonus_booster_dur_preset', $bonus_dur_options, $selected_bonus_dur, array('id' => 'ug_bonus_booster_dur_preset'))
        . '<br />'
        . $form->generate_numeric_field('ug_bonus_booster_dur_custom', (int)$ug_bonus_booster_dur, array('min' => 0, 'id' => 'ug_bonus_booster_dur_custom'))
        . ' ' . $lang->credits_admin_seconds
    );

    // Prerequisite groups (Feature 3 - Tiered Upgrades)
    $ug_required_groups = $item_data['required_groups'] ?? array();
    $form_container_ug->output_row(
        $lang->credits_admin_ug_required_groups,
        $lang->credits_admin_ug_required_groups_desc,
        $form->generate_select_box('ug_required_groups[]', $usergroup_options, $ug_required_groups, array('id' => 'ug_required_groups', 'multiple' => true, 'size' => 5))
    );

    $form_container_ug->end();

    // Ad Space-specific fields
    $ad_position = $item_data['position'] ?? 'header';
    $ad_duration = $item_data['duration'] ?? 604800;

    $form_container_ad = new FormContainer($lang->credits_admin_ad_settings);

    $form_container_ad->output_row(
        $lang->credits_admin_ad_position,
        $lang->credits_admin_ad_position_desc,
        $form->generate_select_box('ad_position', array(
            'header'        => $lang->credits_admin_ad_pos_header,
            'thread_header' => $lang->credits_admin_ad_pos_thread_header,
        ), $ad_position, array('id' => 'ad_position'))
    );

    $ad_duration_options = array(
        '86400'   => $lang->credits_admin_dur_24h,
        '604800'  => $lang->credits_admin_dur_1w,
        '2592000' => $lang->credits_admin_dur_30d,
        '0'       => $lang->credits_admin_ug_permanent,
        '-1'      => $lang->credits_admin_dur_custom,
    );

    $selected_ad_dur = array_key_exists((string)$ad_duration, $ad_duration_options) ? (string)$ad_duration : '-1';

    $form_container_ad->output_row(
        $lang->credits_admin_ad_duration,
        $lang->credits_admin_ad_duration_desc,
        $form->generate_select_box('ad_duration_preset', $ad_duration_options, $selected_ad_dur, array('id' => 'ad_duration_preset'))
        . '<br />'
        . $form->generate_numeric_field('ad_duration_custom', $ad_duration, array('min' => 0, 'id' => 'ad_duration_custom'))
        . ' ' . $lang->credits_admin_seconds
    );

    $form_container_ad->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_save));
    $form->output_submit_wrapper($buttons);
    $form->end();

    // JavaScript to show/hide type-specific sections
    echo '<script type="text/javascript">
    $(function() {
        function toggleTypeFields() {
            var type = $("#item_type").val();
            var containers = $(".form_container");
            // Indexes: 0=basic, 1=icon, 2=booster, 3=award, 4=bg, 5=effect, 6=usergroup, 7=ad_space
            containers.eq(1).toggle(type === "icon");
            containers.eq(2).toggle(type === "booster");
            containers.eq(3).toggle(type === "award");
            containers.eq(4).toggle(type === "postbit_bg");
            containers.eq(5).toggle(type === "username_effect");
            containers.eq(6).toggle(type === "usergroup");
            containers.eq(7).toggle(type === "ad_space");
        }

        function toggleDurationCustom() {
            var preset = $("#booster_duration_preset").val();
            $("#booster_duration_custom").prop("disabled", preset !== "0");
        }

        function toggleUgDuration() {
            var preset = $("#ug_duration_preset").val();
            $("#ug_duration_custom").prop("disabled", preset !== "-1");
        }

        function toggleAdDuration() {
            var preset = $("#ad_duration_preset").val();
            $("#ad_duration_custom").prop("disabled", preset !== "-1");
        }

        function toggleBonusBoosterDuration() {
            var preset = $("#ug_bonus_booster_dur_preset").val();
            $("#ug_bonus_booster_dur_custom").prop("disabled", preset !== "-1");
        }

        $("#item_type").on("change", toggleTypeFields);
        $("#booster_duration_preset").on("change", toggleDurationCustom);
        $("#ug_duration_preset").on("change", toggleUgDuration);
        $("#ad_duration_preset").on("change", toggleAdDuration);
        $("#ug_bonus_booster_dur_preset").on("change", toggleBonusBoosterDuration);

        toggleTypeFields();
        toggleDurationCustom();
        toggleUgDuration();
        toggleAdDuration();
        toggleBonusBoosterDuration();
    });
    </script>';

    $page->output_footer();
}

/**
 * ACP: Save shop item (add/edit).
 */
function credits_admin_shop_save($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $iid = $mybb->get_input('iid', MyBB::INPUT_INT);
    $type = $mybb->get_input('type');

    // Build type-specific data JSON
    $item_data = '';
    switch ($type) {
        case 'icon':
            $icon_image = trim($mybb->get_input('icon_image'));
            if (!empty($icon_image)) {
                $item_data = json_encode(array('image' => $icon_image));
            }
            break;

        case 'booster':
            $multiplier = $mybb->get_input('booster_multiplier', MyBB::INPUT_INT);
            if ($multiplier < 2) $multiplier = 2;
            if ($multiplier > 10) $multiplier = 10;

            $duration_preset = $mybb->get_input('booster_duration_preset');
            if ($duration_preset === '0') {
                $duration = $mybb->get_input('booster_duration_custom', MyBB::INPUT_INT);
            } else {
                $duration = (int)$duration_preset;
            }
            if ($duration < 60) $duration = 60;

            $item_data = json_encode(array('multiplier' => $multiplier, 'duration' => $duration));
            break;

        case 'award':
            $award_image = trim($mybb->get_input('award_image'));
            if (!empty($award_image)) {
                $item_data = json_encode(array('image' => $award_image));
            }
            break;

        case 'postbit_bg':
            $bg_type = $mybb->get_input('bg_type');
            $bg_value = trim($mybb->get_input('bg_value'));
            if (!in_array($bg_type, array('color', 'gradient', 'image'))) {
                $bg_type = 'color';
            }
            if (!empty($bg_value)) {
                $item_data = json_encode(array('bg_type' => $bg_type, 'bg_value' => $bg_value));
            }
            break;

        case 'username_effect':
            $effect_preset = $mybb->get_input('effect_preset');
            $valid_effects = array('rainbow', 'glow', 'sparkle', 'shadow', 'bold', 'gradient');
            if (!in_array($effect_preset, $valid_effects)) {
                $effect_preset = 'rainbow';
            }
            $item_data = json_encode(array('effect' => $effect_preset));
            break;

        case 'usergroup':
            $ug_gid = $mybb->get_input('ug_gid', MyBB::INPUT_INT);
            $ug_dur_preset = $mybb->get_input('ug_duration_preset');
            if ($ug_dur_preset === '-1') {
                $ug_duration = $mybb->get_input('ug_duration_custom', MyBB::INPUT_INT);
            } else {
                $ug_duration = (int)$ug_dur_preset;
            }
            if ($ug_duration < 0) $ug_duration = 0;

            $ug_data = array('gid' => $ug_gid, 'duration' => $ug_duration);

            // Bonus credits
            $bonus_credits = $mybb->get_input('ug_bonus_credits', MyBB::INPUT_INT);
            if ($bonus_credits > 0) {
                $ug_data['bonus_credits'] = $bonus_credits;
            }

            // Bonus booster
            $bonus_mult = $mybb->get_input('ug_bonus_booster_multiplier', MyBB::INPUT_INT);
            if ($bonus_mult < 0) $bonus_mult = 0;
            if ($bonus_mult > 10) $bonus_mult = 10;

            $bonus_dur_preset = $mybb->get_input('ug_bonus_booster_dur_preset');
            if ($bonus_dur_preset === '-1') {
                $bonus_dur = $mybb->get_input('ug_bonus_booster_dur_custom', MyBB::INPUT_INT);
            } else {
                $bonus_dur = (int)$bonus_dur_preset;
            }
            if ($bonus_dur < 0) $bonus_dur = 0;

            if ($bonus_mult > 0 && $bonus_dur > 0) {
                $ug_data['bonus_booster_multiplier'] = $bonus_mult;
                $ug_data['bonus_booster_duration'] = $bonus_dur;
            }

            // Required groups (prerequisites)
            $required_groups = $mybb->get_input('ug_required_groups', MyBB::INPUT_ARRAY);
            if (!empty($required_groups)) {
                $required_groups = array_map('intval', $required_groups);
                $required_groups = array_filter($required_groups);
                if (!empty($required_groups)) {
                    $ug_data['required_groups'] = array_values($required_groups);
                }
            }

            $item_data = json_encode($ug_data);
            break;

        case 'ad_space':
            $ad_position = $mybb->get_input('ad_position');
            if (!in_array($ad_position, array('header', 'thread_header'))) {
                $ad_position = 'header';
            }
            $ad_dur_preset = $mybb->get_input('ad_duration_preset');
            if ($ad_dur_preset === '-1') {
                $ad_duration = $mybb->get_input('ad_duration_custom', MyBB::INPUT_INT);
            } else {
                $ad_duration = (int)$ad_dur_preset;
            }
            if ($ad_duration < 0) $ad_duration = 0;
            $item_data = json_encode(array('position' => $ad_position, 'duration' => $ad_duration));
            break;
    }

    // Get price_usd
    $price_usd = $mybb->get_input('price_usd');
    $price_usd = number_format((float)$price_usd, 2, '.', '');

    $stock = $mybb->get_input('stock', MyBB::INPUT_INT);
    if ($stock < -1) $stock = -1;

    $data = array(
        'name'        => $db->escape_string($mybb->get_input('name')),
        'description' => $db->escape_string($mybb->get_input('description')),
        'cid'         => $mybb->get_input('cid', MyBB::INPUT_INT),
        'type'        => $db->escape_string($type),
        'price'       => $mybb->get_input('price', MyBB::INPUT_INT),
        'active'      => $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0,
        'disporder'   => $mybb->get_input('disporder', MyBB::INPUT_INT),
        'data'        => $db->escape_string($item_data),
        'price_usd'   => $price_usd,
        'stock'       => $stock,
    );

    if (empty($data['name'])) {
        flash_message($lang->credits_admin_name_required, 'error');
        admin_redirect('index.php?module=credits-main&action=shop');
    }

    if ($iid > 0) {
        $db->update_query('credits_shop', $data, "iid = '{$iid}'");
        flash_message($lang->credits_admin_item_updated, 'success');
    } else {
        $db->insert_query('credits_shop', $data);
        flash_message($lang->credits_admin_item_added, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=shop');
}

/**
 * ACP: Delete a shop item.
 */
function credits_admin_shop_delete($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $iid = $mybb->get_input('iid', MyBB::INPUT_INT);

    if ($iid > 0) {
        $db->delete_query('credits_shop', "iid = '{$iid}'");
        flash_message($lang->credits_admin_item_deleted, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=shop');
}

// ---- Credit Pack Management ----

/**
 * ACP: Credit packs listing.
 */
function credits_admin_packs($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_packs);


    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right"><a href="index.php?module=credits-main&action=pack_add" class="button">' . $lang->credits_admin_pack_add . '</a></div></div>';

    $query = $db->simple_select('credits_packs', '*', '', array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));

    $table = new Table;
    $table->construct_header($lang->credits_item_name, array('width' => '25%'));
    $table->construct_header($lang->credits_admin_pack_credits, array('class' => 'align_center', 'width' => '20%'));
    $table->construct_header($lang->credits_admin_pack_price, array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header($lang->credits_admin_status, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_disporder, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '20%'));

    while ($pack = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($pack['name']));
        $table->construct_cell(my_number_format($pack['credits']), array('class' => 'align_center'));
        $table->construct_cell('$' . number_format((float)$pack['price_usd'], 2), array('class' => 'align_center'));
        $table->construct_cell($pack['active'] ? $lang->credits_admin_active : $lang->credits_admin_inactive, array('class' => 'align_center'));
        $table->construct_cell((int)$pack['disporder'], array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=pack_edit&pack_id=' . $pack['pack_id'] . '">' . $lang->credits_admin_edit . '</a>'
            . ' | <a href="index.php?module=credits-main&action=pack_delete&pack_id=' . $pack['pack_id'] . '&my_post_key=' . $mybb->post_code . '" onclick="return AdminCP.deleteConfirmation(this, \'' . $lang->credits_admin_pack_delete_confirm . '\')">' . $lang->credits_admin_delete . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_packs, array('colspan' => 6, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_packs);

    $page->output_footer();
}

/**
 * ACP: Credit pack add/edit form.
 */
function credits_admin_pack_form($page)
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('action');
    $is_edit = ($action == 'pack_edit');

    $pack = array(
        'name'      => '',
        'credits'   => 0,
        'price_usd' => '0.00',
        'active'    => 1,
        'disporder' => 0,
    );

    if ($is_edit) {
        $pack_id = $mybb->get_input('pack_id', MyBB::INPUT_INT);
        $query = $db->simple_select('credits_packs', '*', "pack_id = '{$pack_id}'");
        $pack = $db->fetch_array($query);

        if (!$pack) {
            flash_message($lang->credits_admin_pack_not_found, 'error');
            admin_redirect('index.php?module=credits-main&action=packs');
        }

        $page->output_header($lang->credits_admin_pack_edit);
    } else {
        $page->output_header($lang->credits_admin_pack_add);
    }



    $form = new Form('index.php?module=credits-main&action=pack_do_save', 'post');

    if ($is_edit) {
        echo $form->generate_hidden_field('pack_id', $pack['pack_id']);
    }

    $form_container = new FormContainer($is_edit ? $lang->credits_admin_pack_edit : $lang->credits_admin_pack_add);

    $form_container->output_row($lang->credits_item_name, '', $form->generate_text_box('name', htmlspecialchars_uni($pack['name'])));
    $form_container->output_row($lang->credits_admin_pack_credits, $lang->credits_admin_pack_credits_desc, $form->generate_numeric_field('credits', $pack['credits'], array('min' => 1)));
    $form_container->output_row($lang->credits_admin_pack_price, $lang->credits_admin_pack_price_desc, $form->generate_text_box('price_usd', number_format((float)$pack['price_usd'], 2)));
    $form_container->output_row($lang->credits_admin_status, '', $form->generate_yes_no_radio('active', $pack['active']));
    $form_container->output_row($lang->credits_admin_disporder, '', $form->generate_numeric_field('disporder', $pack['disporder'], array('min' => 0)));

    $form_container->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_save));
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * ACP: Save credit pack.
 */
function credits_admin_pack_save($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $pack_id = $mybb->get_input('pack_id', MyBB::INPUT_INT);

    $data = array(
        'name'      => $db->escape_string($mybb->get_input('name')),
        'credits'   => $mybb->get_input('credits', MyBB::INPUT_INT),
        'price_usd' => number_format((float)$mybb->get_input('price_usd'), 2, '.', ''),
        'active'    => $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0,
        'disporder' => $mybb->get_input('disporder', MyBB::INPUT_INT),
    );

    if (empty($data['name'])) {
        flash_message($lang->credits_admin_name_required, 'error');
        admin_redirect('index.php?module=credits-main&action=packs');
    }

    if ($pack_id > 0) {
        $db->update_query('credits_packs', $data, "pack_id = '{$pack_id}'");
        flash_message($lang->credits_admin_pack_updated, 'success');
    } else {
        $db->insert_query('credits_packs', $data);
        flash_message($lang->credits_admin_pack_added, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=packs');
}

/**
 * ACP: Delete a credit pack.
 */
function credits_admin_pack_delete($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pack_id = $mybb->get_input('pack_id', MyBB::INPUT_INT);

    if ($pack_id > 0) {
        $db->delete_query('credits_packs', "pack_id = '{$pack_id}'");
        flash_message($lang->credits_admin_pack_deleted, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=packs');
}

// ---- Payment Log ----

/**
 * ACP: Payment log viewer.
 */
function credits_admin_payments($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_payments);


    $per_page = 25;
    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($current_page < 1) $current_page = 1;
    $start = ($current_page - 1) * $per_page;

    $query = $db->query("SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "credits_payments");
    $total = (int)$db->fetch_field($query, 'total');

    $query = $db->query("
        SELECT p.*, u.username
        FROM " . TABLE_PREFIX . "credits_payments p
        LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
        ORDER BY p.dateline DESC
        LIMIT {$start}, {$per_page}
    ");

    $table = new Table;
    $table->construct_header($lang->credits_username, array('width' => '15%'));
    $table->construct_header($lang->credits_admin_pay_gateway, array('width' => '12%'));
    $table->construct_header($lang->credits_admin_pay_type, array('width' => '10%'));
    $table->construct_header($lang->credits_admin_pay_amount, array('class' => 'align_center', 'width' => '12%'));
    $table->construct_header($lang->credits_admin_pay_status, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_pay_external, array('width' => '21%'));
    $table->construct_header($lang->credits_log_date, array('width' => '20%'));

    while ($payment = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($payment['username'] ?? 'Unknown'));
        $table->construct_cell(htmlspecialchars_uni(ucfirst($payment['gateway'])));
        $table->construct_cell(htmlspecialchars_uni($payment['type']));
        $table->construct_cell('$' . number_format((float)$payment['amount_usd'], 2), array('class' => 'align_center'));
        $table->construct_cell(htmlspecialchars_uni($payment['status']), array('class' => 'align_center'));
        $table->construct_cell('<small>' . htmlspecialchars_uni($payment['external_id']) . '</small>');
        $table->construct_cell(my_date('jS M Y, g:i A', $payment['dateline']));
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_payments, array('colspan' => 7, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_payments);

    echo draw_admin_pagination($current_page, $per_page, $total, 'index.php?module=credits-main&action=payments');

    $page->output_footer();
}

// ---- Gift Log ----

/**
 * ACP: Gift log viewer.
 */
function credits_admin_gifts($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_gifts);


    $per_page = 25;
    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($current_page < 1) $current_page = 1;
    $start = ($current_page - 1) * $per_page;

    $query = $db->query("SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "credits_gifts");
    $total = (int)$db->fetch_field($query, 'total');

    $query = $db->query("
        SELECT g.*, u_from.username AS from_username, u_to.username AS to_username, s.name AS item_name
        FROM " . TABLE_PREFIX . "credits_gifts g
        LEFT JOIN " . TABLE_PREFIX . "users u_from ON g.from_uid = u_from.uid
        LEFT JOIN " . TABLE_PREFIX . "users u_to ON g.to_uid = u_to.uid
        LEFT JOIN " . TABLE_PREFIX . "credits_shop s ON g.iid = s.iid
        ORDER BY g.dateline DESC
        LIMIT {$start}, {$per_page}
    ");

    $table = new Table;
    $table->construct_header($lang->credits_admin_gift_from, array('width' => '18%'));
    $table->construct_header($lang->credits_admin_gift_to, array('width' => '18%'));
    $table->construct_header($lang->credits_admin_gift_type, array('width' => '12%'));
    $table->construct_header($lang->credits_admin_gift_amount, array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header($lang->credits_admin_gift_message, array('width' => '17%'));
    $table->construct_header($lang->credits_log_date, array('width' => '20%'));

    while ($gift = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($gift['from_username'] ?? 'Unknown'));
        $table->construct_cell(htmlspecialchars_uni($gift['to_username'] ?? 'Unknown'));
        $table->construct_cell(htmlspecialchars_uni(ucfirst($gift['type'])));

        if ($gift['type'] == 'credits') {
            $table->construct_cell(my_number_format($gift['amount']), array('class' => 'align_center'));
        } else {
            $table->construct_cell(htmlspecialchars_uni($gift['item_name'] ?? ''), array('class' => 'align_center'));
        }

        $table->construct_cell(htmlspecialchars_uni(my_substr($gift['message'], 0, 50)));
        $table->construct_cell(my_date('jS M Y, g:i A', $gift['dateline']));
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_gifts, array('colspan' => 6, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_gifts);

    echo draw_admin_pagination($current_page, $per_page, $total, 'index.php?module=credits-main&action=gifts');

    $page->output_footer();
}

// ---- Ad Space Management ----

/**
 * ACP: Ad space listing.
 */
function credits_admin_ads($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_ads);


    $query = $db->query("
        SELECT a.*, u.username
        FROM " . TABLE_PREFIX . "credits_ads a
        LEFT JOIN " . TABLE_PREFIX . "users u ON a.uid = u.uid
        ORDER BY a.dateline DESC
    ");

    $table = new Table;
    $table->construct_header($lang->credits_username, array('width' => '15%'));
    $table->construct_header($lang->credits_admin_ad_position, array('width' => '12%'));
    $table->construct_header($lang->credits_admin_ad_views, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_ad_clicks, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_ad_expires, array('width' => '15%'));
    $table->construct_header($lang->credits_admin_status, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '15%'));

    while ($ad = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($ad['username'] ?? 'Unknown'));
        $table->construct_cell(htmlspecialchars_uni($ad['position']));
        $table->construct_cell(my_number_format($ad['views']), array('class' => 'align_center'));
        $table->construct_cell(my_number_format($ad['clicks']), array('class' => 'align_center'));
        $table->construct_cell($ad['expires'] > 0 ? my_date('jS M Y', $ad['expires']) : $lang->credits_admin_ug_permanent);
        $table->construct_cell($ad['active'] ? $lang->credits_admin_active : $lang->credits_admin_inactive, array('class' => 'align_center'));

        $toggle_text = $ad['active'] ? $lang->credits_admin_ad_deactivate : $lang->credits_admin_ad_approve;
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=ad_toggle&ad_id=' . $ad['ad_id'] . '&my_post_key=' . $mybb->post_code . '">' . $toggle_text . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_ads, array('colspan' => 7, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_ads);

    $page->output_footer();
}

/**
 * ACP: Toggle ad active status (approve/deactivate).
 */
function credits_admin_ad_toggle($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $ad_id = $mybb->get_input('ad_id', MyBB::INPUT_INT);

    if ($ad_id > 0) {
        $query = $db->simple_select('credits_ads', 'active', "ad_id = '{$ad_id}'");
        $ad = $db->fetch_array($query);

        if ($ad) {
            $new_status = $ad['active'] ? 0 : 1;
            $db->update_query('credits_ads', array('active' => $new_status), "ad_id = '{$ad_id}'");
            flash_message($lang->credits_admin_ad_toggled, 'success');
        }
    }

    admin_redirect('index.php?module=credits-main&action=ads');
}

// ---- Achievement Management ----

/**
 * ACP: Achievement listing.
 */
function credits_admin_achievements($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_achievements);


    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right"><a href="index.php?module=credits-main&action=ach_add" class="button">' . $lang->credits_admin_ach_add . '</a></div></div>';

    $type_labels = array(
        'posts'          => $lang->credits_admin_ach_type_posts,
        'threads'        => $lang->credits_admin_ach_type_threads,
        'reputation'     => $lang->credits_admin_ach_type_reputation,
        'reg_days'       => $lang->credits_admin_ach_type_reg_days,
        'purchases'      => $lang->credits_admin_ach_type_purchases,
        'credits_earned' => $lang->credits_admin_ach_type_credits_earned,
    );

    $query = $db->simple_select('credits_achievements', '*', '', array(
        'order_by'  => 'disporder',
        'order_dir' => 'ASC',
    ));

    // Count earned per achievement
    $earned_counts = array();
    $count_query = $db->query("
        SELECT aid, COUNT(*) as cnt
        FROM " . TABLE_PREFIX . "credits_user_achievements
        GROUP BY aid
    ");
    while ($row = $db->fetch_array($count_query)) {
        $earned_counts[(int)$row['aid']] = (int)$row['cnt'];
    }

    $table = new Table;
    $table->construct_header($lang->credits_admin_ach_name, array('width' => '18%'));
    $table->construct_header($lang->credits_admin_ach_type, array('class' => 'align_center', 'width' => '12%'));
    $table->construct_header($lang->credits_admin_ach_threshold, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_ach_reward, array('class' => 'align_center', 'width' => '12%'));
    $table->construct_header($lang->credits_admin_ach_earned_by, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_status, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_disporder, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '22%'));

    while ($ach = $db->fetch_array($query)) {
        $aid = (int)$ach['aid'];
        $type_label = $type_labels[$ach['type']] ?? $ach['type'];

        // Build reward display
        $reward_parts = array();
        if ((int)$ach['reward_credits'] > 0) {
            $reward_parts[] = my_number_format($ach['reward_credits']) . ' cr';
        }
        if ((int)$ach['reward_booster_multiplier'] > 0 && (int)$ach['reward_booster_duration'] > 0) {
            $reward_parts[] = $ach['reward_booster_multiplier'] . 'x boost';
        }
        $reward_display = !empty($reward_parts) ? implode(' + ', $reward_parts) : '-';

        $table->construct_cell(htmlspecialchars_uni($ach['name']));
        $table->construct_cell($type_label, array('class' => 'align_center'));
        $table->construct_cell(my_number_format($ach['threshold']), array('class' => 'align_center'));
        $table->construct_cell($reward_display, array('class' => 'align_center'));
        $table->construct_cell($earned_counts[$aid] ?? 0, array('class' => 'align_center'));
        $table->construct_cell($ach['active'] ? $lang->credits_admin_active : $lang->credits_admin_inactive, array('class' => 'align_center'));
        $table->construct_cell((int)$ach['disporder'], array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=ach_edit&aid=' . $aid . '">' . $lang->credits_admin_edit . '</a>'
            . ' | <a href="index.php?module=credits-main&action=ach_delete&aid=' . $aid . '&my_post_key=' . $mybb->post_code . '" onclick="return AdminCP.deleteConfirmation(this, \'' . $lang->credits_admin_ach_delete_confirm . '\')">' . $lang->credits_admin_delete . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_achievements, array('colspan' => 8, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_achievements);

    $page->output_footer();
}

/**
 * ACP: Achievement add/edit form.
 */
function credits_admin_ach_form($page)
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('action');
    $is_edit = ($action == 'ach_edit');

    $ach = array(
        'name'                     => '',
        'description'              => '',
        'type'                     => 'posts',
        'threshold'                => 0,
        'reward_credits'           => 0,
        'reward_booster_multiplier' => 0,
        'reward_booster_duration'  => 0,
        'icon'                     => '',
        'active'                   => 1,
        'disporder'                => 0,
    );

    if ($is_edit) {
        $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
        $query = $db->simple_select('credits_achievements', '*', "aid = '{$aid}'");
        $ach = $db->fetch_array($query);

        if (!$ach) {
            flash_message($lang->credits_admin_ach_not_found, 'error');
            admin_redirect('index.php?module=credits-main&action=achievements');
        }

        $page->output_header($lang->credits_admin_ach_edit);
    } else {
        $page->output_header($lang->credits_admin_ach_add);
    }



    $form = new Form('index.php?module=credits-main&action=ach_do_save', 'post');

    if ($is_edit) {
        echo $form->generate_hidden_field('aid', $ach['aid']);
    }

    $form_container = new FormContainer($is_edit ? $lang->credits_admin_ach_edit : $lang->credits_admin_ach_add);

    $form_container->output_row(
        $lang->credits_admin_ach_name,
        $lang->credits_admin_ach_name_desc,
        $form->generate_text_box('name', htmlspecialchars_uni($ach['name']))
    );

    $form_container->output_row(
        $lang->credits_admin_ach_description,
        $lang->credits_admin_ach_description_desc,
        $form->generate_text_area('description', htmlspecialchars_uni($ach['description']))
    );

    $form_container->output_row(
        $lang->credits_admin_ach_type,
        $lang->credits_admin_ach_type_desc,
        $form->generate_select_box('type', array(
            'posts'          => $lang->credits_admin_ach_type_posts,
            'threads'        => $lang->credits_admin_ach_type_threads,
            'reputation'     => $lang->credits_admin_ach_type_reputation,
            'reg_days'       => $lang->credits_admin_ach_type_reg_days,
            'purchases'      => $lang->credits_admin_ach_type_purchases,
            'credits_earned' => $lang->credits_admin_ach_type_credits_earned,
        ), $ach['type'])
    );

    $form_container->output_row(
        $lang->credits_admin_ach_threshold,
        $lang->credits_admin_ach_threshold_desc,
        $form->generate_numeric_field('threshold', (int)$ach['threshold'], array('min' => 1))
    );

    $form_container->end();

    // Reward settings
    $form_container_reward = new FormContainer($lang->credits_admin_ach_reward_settings);

    $form_container_reward->output_row(
        $lang->credits_admin_ach_reward_credits,
        $lang->credits_admin_ach_reward_credits_desc,
        $form->generate_numeric_field('reward_credits', (int)$ach['reward_credits'], array('min' => 0))
    );

    $form_container_reward->output_row(
        $lang->credits_admin_ach_reward_booster_mult,
        $lang->credits_admin_ach_reward_booster_mult_desc,
        $form->generate_numeric_field('reward_booster_multiplier', (int)$ach['reward_booster_multiplier'], array('min' => 0, 'max' => 10))
    );

    $booster_dur = (int)$ach['reward_booster_duration'];

    $ach_dur_options = array(
        '0'     => $lang->credits_admin_ach_reward_none,
        '1800'  => $lang->credits_admin_dur_30m,
        '3600'  => $lang->credits_admin_dur_1h,
        '10800' => $lang->credits_admin_dur_3h,
        '21600' => $lang->credits_admin_dur_6h,
        '43200' => $lang->credits_admin_dur_12h,
        '86400' => $lang->credits_admin_dur_24h,
        '-1'    => $lang->credits_admin_dur_custom,
    );

    $selected_ach_dur = array_key_exists((string)$booster_dur, $ach_dur_options) ? (string)$booster_dur : '-1';

    $form_container_reward->output_row(
        $lang->credits_admin_ach_reward_booster_dur,
        $lang->credits_admin_ach_reward_booster_dur_desc,
        $form->generate_select_box('reward_booster_dur_preset', $ach_dur_options, $selected_ach_dur, array('id' => 'reward_booster_dur_preset'))
        . '<br />'
        . $form->generate_numeric_field('reward_booster_dur_custom', $booster_dur, array('min' => 0, 'id' => 'reward_booster_dur_custom'))
        . ' ' . $lang->credits_admin_seconds
    );

    $form_container_reward->end();

    // Display settings
    $form_container_display = new FormContainer($lang->credits_admin_ach_display_settings);

    $form_container_display->output_row(
        $lang->credits_admin_ach_icon,
        $lang->credits_admin_ach_icon_desc,
        $form->generate_text_box('icon', htmlspecialchars_uni($ach['icon']))
        . (!empty($ach['icon']) ? '<br /><img src="../' . htmlspecialchars_uni($ach['icon']) . '" alt="preview" style="max-height: 32px; max-width: 32px; margin-top: 5px;" />' : '')
    );

    $form_container_display->output_row(
        $lang->credits_admin_status,
        '',
        $form->generate_yes_no_radio('active', $ach['active'])
    );

    $form_container_display->output_row(
        $lang->credits_admin_disporder,
        '',
        $form->generate_numeric_field('disporder', (int)$ach['disporder'], array('min' => 0))
    );

    $form_container_display->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_save));
    $form->output_submit_wrapper($buttons);
    $form->end();

    // JavaScript for duration preset toggle
    echo '<script type="text/javascript">
    $(function() {
        function toggleAchDuration() {
            var preset = $("#reward_booster_dur_preset").val();
            $("#reward_booster_dur_custom").prop("disabled", preset !== "-1");
        }
        $("#reward_booster_dur_preset").on("change", toggleAchDuration);
        toggleAchDuration();
    });
    </script>';

    $page->output_footer();
}

/**
 * ACP: Save achievement (add/edit).
 */
function credits_admin_ach_save($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

    $type = $mybb->get_input('type');
    $valid_types = array('posts', 'threads', 'reputation', 'reg_days', 'purchases', 'credits_earned');
    if (!in_array($type, $valid_types)) {
        $type = 'posts';
    }

    // Booster duration
    $dur_preset = $mybb->get_input('reward_booster_dur_preset');
    if ($dur_preset === '-1') {
        $booster_dur = $mybb->get_input('reward_booster_dur_custom', MyBB::INPUT_INT);
    } else {
        $booster_dur = (int)$dur_preset;
    }
    if ($booster_dur < 0) $booster_dur = 0;

    $booster_mult = $mybb->get_input('reward_booster_multiplier', MyBB::INPUT_INT);
    if ($booster_mult < 0) $booster_mult = 0;
    if ($booster_mult > 10) $booster_mult = 10;

    // If multiplier is 0, clear duration too
    if ($booster_mult == 0) $booster_dur = 0;

    $data = array(
        'name'                      => $db->escape_string($mybb->get_input('name')),
        'description'               => $db->escape_string($mybb->get_input('description')),
        'type'                      => $db->escape_string($type),
        'threshold'                 => max(1, $mybb->get_input('threshold', MyBB::INPUT_INT)),
        'reward_credits'            => max(0, $mybb->get_input('reward_credits', MyBB::INPUT_INT)),
        'reward_booster_multiplier' => $booster_mult,
        'reward_booster_duration'   => $booster_dur,
        'icon'                      => $db->escape_string(trim($mybb->get_input('icon'))),
        'active'                    => $mybb->get_input('active', MyBB::INPUT_INT) ? 1 : 0,
        'disporder'                 => $mybb->get_input('disporder', MyBB::INPUT_INT),
    );

    if (empty($data['name'])) {
        flash_message($lang->credits_admin_ach_name_required, 'error');
        admin_redirect('index.php?module=credits-main&action=achievements');
    }

    if ($aid > 0) {
        $db->update_query('credits_achievements', $data, "aid = '{$aid}'");
        flash_message($lang->credits_admin_ach_updated, 'success');
    } else {
        $db->insert_query('credits_achievements', $data);
        flash_message($lang->credits_admin_ach_added, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=achievements');
}

/**
 * ACP: Delete an achievement.
 */
function credits_admin_ach_delete($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $aid = $mybb->get_input('aid', MyBB::INPUT_INT);

    if ($aid > 0) {
        $db->delete_query('credits_achievements', "aid = '{$aid}'");
        // Also remove user achievement records
        $db->delete_query('credits_user_achievements', "aid = '{$aid}'");
        flash_message($lang->credits_admin_ach_deleted, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=achievements');
}

// ---- Lottery Management ----

/**
 * ACP: Lottery listing.
 */
function credits_admin_lottery($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_lottery);


    echo '<div style="overflow: hidden; margin-bottom: 8px;"><div class="float_right"><a href="index.php?module=credits-main&action=lottery_add" class="button">' . $lang->credits_admin_lottery_add . '</a></div></div>';

    $query = $db->query("
        SELECT l.*, u.username AS winner_name,
            (SELECT COUNT(*) FROM " . TABLE_PREFIX . "credits_lottery_tickets t WHERE t.lottery_id = l.lottery_id) AS ticket_count
        FROM " . TABLE_PREFIX . "credits_lottery l
        LEFT JOIN " . TABLE_PREFIX . "users u ON l.winner_uid = u.uid
        ORDER BY l.created DESC
    ");

    $table = new Table;
    $table->construct_header($lang->credits_admin_lottery_name, array('width' => '18%'));
    $table->construct_header($lang->credits_admin_lottery_ticket_price, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_lottery_max_tickets, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_lottery_tickets_sold, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_lottery_pot_pct, array('class' => 'align_center', 'width' => '8%'));
    $table->construct_header($lang->credits_admin_lottery_draw_time_col, array('width' => '14%'));
    $table->construct_header($lang->credits_admin_lottery_status, array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header($lang->credits_admin_actions, array('class' => 'align_center', 'width' => '20%'));

    while ($lottery = $db->fetch_array($query)) {
        $lid = (int)$lottery['lottery_id'];

        $status_display = htmlspecialchars_uni(ucfirst($lottery['status']));
        if ($lottery['status'] == 'completed' && (int)$lottery['winner_uid'] > 0) {
            $status_display .= '<br /><small>' . $lang->credits_admin_lottery_winner_label . ': ' . htmlspecialchars_uni($lottery['winner_name'] ?? 'Unknown') . '</small>';
        }

        $draw_display = $lottery['draw_time'] > 0 ? my_date('jS M Y, g:i A', $lottery['draw_time']) : '-';

        $table->construct_cell(htmlspecialchars_uni($lottery['name']));
        $table->construct_cell(my_number_format($lottery['ticket_price']), array('class' => 'align_center'));
        $table->construct_cell((int)$lottery['max_tickets_per_user'] > 0 ? my_number_format($lottery['max_tickets_per_user']) : $lang->credits_admin_stock_unlimited, array('class' => 'align_center'));
        $table->construct_cell(my_number_format($lottery['ticket_count']), array('class' => 'align_center'));
        $table->construct_cell((int)$lottery['pot_percentage'] . '%', array('class' => 'align_center'));
        $table->construct_cell($draw_display);
        $table->construct_cell($status_display, array('class' => 'align_center'));
        $table->construct_cell(
            '<a href="index.php?module=credits-main&action=lottery_edit&lottery_id=' . $lid . '">' . $lang->credits_admin_edit . '</a>'
            . ' | <a href="index.php?module=credits-main&action=lottery_delete&lottery_id=' . $lid . '&my_post_key=' . $mybb->post_code . '" onclick="return AdminCP.deleteConfirmation(this, \'' . $lang->credits_admin_lottery_delete_confirm . '\')">' . $lang->credits_admin_delete . '</a>',
            array('class' => 'align_center')
        );
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_lotteries, array('colspan' => 8, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_lottery);

    $page->output_footer();
}

/**
 * ACP: Lottery add/edit form.
 */
function credits_admin_lottery_form($page)
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('action');
    $is_edit = ($action == 'lottery_edit');

    $lottery = array(
        'name'                 => '',
        'description'          => '',
        'ticket_price'         => 10,
        'max_tickets_per_user' => 5,
        'pot_percentage'       => 80,
        'status'               => 'active',
        'draw_time'            => TIME_NOW + 86400,
    );

    if ($is_edit) {
        $lottery_id = $mybb->get_input('lottery_id', MyBB::INPUT_INT);
        $query = $db->simple_select('credits_lottery', '*', "lottery_id = '{$lottery_id}'");
        $lottery = $db->fetch_array($query);

        if (!$lottery) {
            flash_message($lang->credits_admin_lottery_not_found, 'error');
            admin_redirect('index.php?module=credits-main&action=lottery');
        }

        $page->output_header($lang->credits_admin_lottery_edit);
    } else {
        $page->output_header($lang->credits_admin_lottery_add);
    }



    $form = new Form('index.php?module=credits-main&action=lottery_do_save', 'post');

    if ($is_edit) {
        echo $form->generate_hidden_field('lottery_id', $lottery['lottery_id']);
    }

    $form_container = new FormContainer($is_edit ? $lang->credits_admin_lottery_edit : $lang->credits_admin_lottery_add);

    $form_container->output_row(
        $lang->credits_admin_lottery_name,
        $lang->credits_admin_lottery_name_desc,
        $form->generate_text_box('name', htmlspecialchars_uni($lottery['name']))
    );

    $form_container->output_row(
        $lang->credits_admin_lottery_description,
        $lang->credits_admin_lottery_description_desc,
        $form->generate_text_area('description', htmlspecialchars_uni($lottery['description']))
    );

    $form_container->output_row(
        $lang->credits_admin_lottery_ticket_price,
        $lang->credits_admin_lottery_ticket_price_desc,
        $form->generate_numeric_field('ticket_price', (int)$lottery['ticket_price'], array('min' => 1))
    );

    $form_container->output_row(
        $lang->credits_admin_lottery_max_tickets,
        $lang->credits_admin_lottery_max_tickets_desc,
        $form->generate_numeric_field('max_tickets_per_user', (int)$lottery['max_tickets_per_user'], array('min' => 0))
    );

    $form_container->output_row(
        $lang->credits_admin_lottery_pot_pct,
        $lang->credits_admin_lottery_pot_pct_desc,
        $form->generate_numeric_field('pot_percentage', (int)$lottery['pot_percentage'], array('min' => 1, 'max' => 100))
    );

    $form_container->output_row(
        $lang->credits_admin_lottery_status,
        $lang->credits_admin_lottery_status_desc,
        $form->generate_select_box('status', array(
            'active'    => $lang->credits_admin_active,
            'completed' => $lang->credits_admin_lottery_completed,
        ), $lottery['status'])
    );

    // Draw time as date/time inputs
    $draw_date = date('Y-m-d', (int)$lottery['draw_time']);
    $draw_time_val = date('H:i', (int)$lottery['draw_time']);

    $form_container->output_row(
        $lang->credits_admin_lottery_draw_time,
        $lang->credits_admin_lottery_draw_time_desc,
        $form->generate_text_box('draw_date', $draw_date, array('id' => 'draw_date', 'style' => 'width: 120px;'))
        . ' '
        . $form->generate_text_box('draw_time', $draw_time_val, array('id' => 'draw_time', 'style' => 'width: 80px;'))
        . ' <small>(YYYY-MM-DD HH:MM)</small>'
    );

    $form_container->end();

    $buttons = array($form->generate_submit_button($lang->credits_admin_save));
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * ACP: Save lottery (add/edit).
 */
function credits_admin_lottery_save($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->post_code);

    $lottery_id = $mybb->get_input('lottery_id', MyBB::INPUT_INT);

    $status = $mybb->get_input('status');
    if (!in_array($status, array('active', 'completed'))) {
        $status = 'active';
    }

    // Parse draw time
    $draw_date = trim($mybb->get_input('draw_date'));
    $draw_time_str = trim($mybb->get_input('draw_time'));
    $draw_timestamp = strtotime($draw_date . ' ' . $draw_time_str);
    if ($draw_timestamp === false || $draw_timestamp <= 0) {
        $draw_timestamp = TIME_NOW + 86400;
    }

    $data = array(
        'name'                 => $db->escape_string($mybb->get_input('name')),
        'description'          => $db->escape_string($mybb->get_input('description')),
        'ticket_price'         => max(1, $mybb->get_input('ticket_price', MyBB::INPUT_INT)),
        'max_tickets_per_user' => max(0, $mybb->get_input('max_tickets_per_user', MyBB::INPUT_INT)),
        'pot_percentage'       => max(1, min(100, $mybb->get_input('pot_percentage', MyBB::INPUT_INT))),
        'status'               => $db->escape_string($status),
        'draw_time'            => $draw_timestamp,
    );

    if (empty($data['name'])) {
        flash_message($lang->credits_admin_lottery_name_required, 'error');
        admin_redirect('index.php?module=credits-main&action=lottery');
    }

    if ($lottery_id > 0) {
        $db->update_query('credits_lottery', $data, "lottery_id = '{$lottery_id}'");
        flash_message($lang->credits_admin_lottery_updated, 'success');
    } else {
        $data['created'] = TIME_NOW;
        $data['winner_uid'] = 0;
        $data['total_pot'] = 0;
        $db->insert_query('credits_lottery', $data);
        flash_message($lang->credits_admin_lottery_added, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=lottery');
}

/**
 * ACP: Delete a lottery.
 */
function credits_admin_lottery_delete($page)
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $lottery_id = $mybb->get_input('lottery_id', MyBB::INPUT_INT);

    if ($lottery_id > 0) {
        $db->delete_query('credits_lottery', "lottery_id = '{$lottery_id}'");
        $db->delete_query('credits_lottery_tickets', "lottery_id = '{$lottery_id}'");
        flash_message($lang->credits_admin_lottery_deleted, 'success');
    }

    admin_redirect('index.php?module=credits-main&action=lottery');
}

// ---- Referral Log ----

/**
 * ACP: Referral log (read-only).
 */
function credits_admin_referrals($page)
{
    global $mybb, $db, $lang;

    $page->output_header($lang->credits_admin_referrals);


    $per_page = 25;
    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);
    if ($current_page < 1) $current_page = 1;
    $start = ($current_page - 1) * $per_page;

    $query = $db->query("SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "credits_referrals");
    $total = (int)$db->fetch_field($query, 'total');

    $query = $db->query("
        SELECT r.*, u_referrer.username AS referrer_name, u_referred.username AS referred_name
        FROM " . TABLE_PREFIX . "credits_referrals r
        LEFT JOIN " . TABLE_PREFIX . "users u_referrer ON r.referrer_uid = u_referrer.uid
        LEFT JOIN " . TABLE_PREFIX . "users u_referred ON r.referred_uid = u_referred.uid
        ORDER BY r.dateline DESC
        LIMIT {$start}, {$per_page}
    ");

    $table = new Table;
    $table->construct_header($lang->credits_admin_referral_referrer, array('width' => '25%'));
    $table->construct_header($lang->credits_admin_referral_referred, array('width' => '25%'));
    $table->construct_header($lang->credits_admin_referral_rewarded, array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header($lang->credits_log_date, array('width' => '35%'));

    while ($ref = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($ref['referrer_name'] ?? 'Unknown'));
        $table->construct_cell(htmlspecialchars_uni($ref['referred_name'] ?? 'Unknown'));
        $table->construct_cell($ref['rewarded'] ? $lang->credits_admin_referral_yes : $lang->credits_admin_referral_no, array('class' => 'align_center'));
        $table->construct_cell(my_date('jS M Y, g:i A', $ref['dateline']));
        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->credits_admin_no_referrals, array('colspan' => 4, 'class' => 'align_center'));
        $table->construct_row();
    }

    $table->output($lang->credits_admin_referrals);

    echo draw_admin_pagination($current_page, $per_page, $total, 'index.php?module=credits-main&action=referrals');

    $page->output_footer();
}

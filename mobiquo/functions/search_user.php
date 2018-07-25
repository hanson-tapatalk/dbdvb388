<?php
defined('IN_MOBIQUO') or exit;

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function search_user_func($xmlrpc_params)
{
    global $vbulletin, $db;
    
    $page = (empty($_POST['page']) || $_POST['page'] < 0 )? 1 : $_POST['page'];
    $perpage = (empty($_POST['perpage']) || $_POST['perpage'] < 0)? 20 : $_POST['perpage'];
    $start = ($page - 1)*$perpage;
    $limit = $page * $perpage;
    $vbulletin->input->clean_array_gpc('p', array('fragment' => TYPE_STR));

    $vbulletin->GPC['fragment'] = convert_urlencoded_unicode($vbulletin->GPC['fragment']);

    if ($vbulletin->GPC['fragment'] != '' AND strlen($vbulletin->GPC['fragment']) >= 3)
    {
        $fragment = htmlspecialchars_uni($vbulletin->GPC['fragment']);
    }
    else
    {
        $fragment = '';
    }

    if ($fragment != '')
    {
        $users = $db->query_read_slave("
            SELECT user.userid, user.username FROM " . TABLE_PREFIX . "user
            AS user WHERE username LIKE('" . $db->escape_string_like($fragment) . "%')
            ORDER BY username
            LIMIT $start, $limit
        ");
        while ($user = $db->fetch_array($users))
        {
            $icon_url = '';
            if((count($userinfos)) < 100 )
            {
                $fetch_userinfo_options = (
                    FETCH_USERINFO_AVATAR
                );
                $userinfo = fetch_userinfo($user['userid'], $fetch_userinfo_options);
                fetch_avatar_from_userinfo($userinfo, true, false);
                
                if($userinfo[avatarurl]){
                    $icon_url = get_icon_real_url($userinfo[avatarurl]);
                }
            }
            $user['icon_url'] = $icon_url;
            $user_lists[] = $user;
        }
    }
    
    $total = count($user_lists);
    $return_user_lists = array();

    if(!empty($user_lists))
        foreach ($user_lists as $user)
            $return_user_lists[] = new xmlrpcval(array(
                'username'     => new xmlrpcval($user['username'], 'base64'),
                'user_id'       => new xmlrpcval($user['userid'], 'string'),
                'icon_url'      => new xmlrpcval($user['icon_url'], 'string'),
            ), 'struct');

    $suggested_users = new xmlrpcval(array(
        'total' => new xmlrpcval($total, 'int'),
        'list'         => new xmlrpcval($return_user_lists, 'array'),
    ), 'struct');
    
    
    return new xmlrpcresp($suggested_users);
}
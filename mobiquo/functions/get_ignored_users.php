<?php
/*======================================================================*\
 || #################################################################### ||
 || # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Quoord Systems Ltd.                                              # ||
 || # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
 || #################################################################### ||
 \*======================================================================*/

defined('IN_MOBIQUO') or exit;

$phrasegroups = array('wol');
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

//define('DISABLE_HOOKS', true);
define('THIS_SCRIPT', 'online');
define('CSRF_PROTECTION', false);
define('CSRF_SKIP_LIST', '');

require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_online.php');

function get_ignored_users_func($params)
{
    global $vbulletin, $db, $permissions, $show;

    $params = php_xmlrpc_decode($params);


    $ignore = array();
    if (trim($vbulletin->userinfo['ignorelist']))
    {
        $ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($ignorelist AS $ignoreuserid)
        {
            $ignore[] = $ignoreuserid;
        }
    }
    $ignore_count = sizeof($ignore);
    $page = $params[0];
    $perPage = $params[1];
    $startnum = ($page - 1) * $perPage;
    $ignore = array_slice($ignore, $startnum, $perPage);
    $ignored_users = array();
    foreach ($ignore AS $user_id)
    {
        $vbulletin->GPC['userid'] = $user_id;
        
        $fetch_userinfo_options = (
            FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
            FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
            FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
        );

        $userinfo = mobiquo_verify_id('user', $vbulletin->GPC['userid'], 1, 1, $fetch_userinfo_options);

        if(!is_array($userinfo)){
            continue;
        }

        $show['vcard'] = ($vbulletin->userinfo['userid'] AND $userinfo['showvcard']);


        // display user info
        $userperms = cache_permissions($userinfo, false);

        require_once(DIR . '/includes/class_userprofile.php');
        require_once(CWD1."/include/mobiquo_class_profileblock.php");

        $profileobj = new vB_UserProfile($vbulletin, $userinfo);

        $blockfactory = new vB_ProfileBlockFactory($vbulletin, $profileobj);

        $prepared =& $profileobj->prepared;
        $blocks = array();
        $tabs = array();
        $tablinks = array();

        $blocklist = array(
    //    'stats_mini' => array(
    //        'class' => 'MiniStats',
    //        'title' => $vbphrase['mini_statistics'],
    //    ),
    //
    //    'albums' => array(
    //        'class' => 'Albums',
    //        'title' => $vbphrase['albums'],
    //    ),
    //    'visitors' => array(
    //        'class' => 'RecentVisitors',
    //        'title' => $vbphrase['recent_visitors'],
    //        'options' => array(
    //            'profilemaxvisitors' => $vbulletin->options['profilemaxvisitors']
    //    )
    //    ),
    //    'groups' => array(
    //        'class' => 'Groups',
    //        'title' => $vbphrase['group_memberships'],
    //    ),
    //    // PMs must come before Stats to save a query
    //    'visitor_messaging' => array(
    //        'class'   => 'VisitorMessaging',
    //        'title'   => $vbphrase['visitor_messages'],
    //        'options' => array(
    //            'pagenumber'  => $vbulletin->GPC['pagenumber'],
    //            'tab'         => $vbulletin->GPC['tab'],
    //            'vmid'        => $vbulletin->GPC['vmid'],
    //            'showignored' => $vbulletin->GPC['showignored'],
    //    )
    //    ),
        'aboutme' => array(
            'class' => 'AboutMe',
            'title' => $vbphrase['about_me'],
            'options' => array(
                'simple' => $vbulletin->GPC['simple'],
        ),
        ),
    //    'stats' => array(
    //        'class' => 'Statistics',
    //        'title' => $vbphrase['statistics'],
    //    ),
    //    'contactinfo' => array(
    //        'class' => 'ContactInfo',
    //        'title' => $vbphrase['contact_info'],
    //    ),
    //
        'infractions' => array(
            'class'   => 'Infractions',
            'title'   => $vbphrase['infractions'],
            'options' => array(
                'pagenumber' => $vbulletin->GPC['pagenumber'],
                'tab'        => $vbulletin->GPC['tab'],
        ),
        ),
        );

        if (!empty($vbulletin->GPC['tab']) AND !empty($vbulletin->GPC['perpage']) AND isset($blocklist["{$vbulletin->GPC['tab']}"]))
        {
            $blocklist["{$vbulletin->GPC['tab']}"]['options']['perpage'] = $vbulletin->GPC['perpage'];
        }

        $vbulletin->GPC['simple'] = ($prepared['myprofile'] ? $vbulletin->GPC['simple'] : false);

        $profileblock =& $blockfactory->fetch('ProfileFields');
        $profileblock->build_field_data($vbulletin->GPC['simple']);

        foreach ($profileblock->locations AS $profilecategoryid => $location)
        {
            if ($location)
            {
                $blocklist["profile_cat$profilecategoryid"] = array(
                'class'         => 'ProfileFields',
                'title'         => $vbphrase["category{$profilecategoryid}_title"],
                'options'       => array(
                    'category' => $profilecategoryid,
                    'simple'   => $vbulletin->GPC['simple'],
                ),
                'hook_location' => $location
                );
            }
        }

        if (!empty($vbulletin->GPC['tab']) AND isset($blocklist["{$vbulletin->GPC['tab']}"]))
        {
            $selected_tab = $vbulletin->GPC['tab'];
        }
        else
        {
            $selected_tab = '';
        }


        $mobiquo_return_array = array();

        foreach ($blocklist AS $blockid => $blockinfo)
        {
            $blockobj =& $blockfactory->fetch($blockinfo['class']);
            $mobiquo_block_array = $blockobj->fetch($blockinfo['title'], $blockid, $blockinfo['options'], $vbulletin->userinfo);

            if($blockid == 'aboutme'){
                if(is_array($mobiquo_block_array)){
                    foreach($mobiquo_block_array  as $mobiquo_block_item){
                        $mobiquo_return_array[] =  new xmlrpcval(array(
                            'name'  => new xmlrpcval(mobiquo_encode($mobiquo_block_item[name]), 'base64'),
                            'value' => new xmlrpcval(mobiquo_encode($mobiquo_block_item[value]), 'base64')
                        ), 'struct');
                    }
                }
            }
            else if($blockid == 'infractions')
            {
                if(is_array($mobiquo_block_array)){
                    $infractions_points = 0;
                    foreach($mobiquo_block_array as $idx => $infraction)
                    {
                        $infractions_points += $infraction['points'];
                    }
                    $mobiquo_return_array[] = new xmlrpcval(array(
                            'name' => new xmlrpcval(mobiquo_encode('Infractions'), 'base64'),
                            'value' => new xmlrpcval(mobiquo_encode($infractions_points), 'base64')
                        ), 'struct');
                }
            }
        }

        if (!empty($mobiquo_return_array) && $userinfo['signature'])
        {
            $signature = preg_replace('/\[img\].*?\[\/img\]/si', '', $userinfo['signature']);
            $signature = preg_replace('/\[[^\]]*?\]/', '', $signature);

            $mobiquo_return_array[] = new xmlrpcval(array(
                'name'  => new xmlrpcval(mobiquo_encode($vbphrase['signature']), 'base64'),
                'value' => new xmlrpcval(mobiquo_encode($signature), 'base64')
            ), 'struct');
        }

        if ($vbulletin->options['post_thanks_show_stats_profile'])
        {
            $userinfo['post_thanks_user_amount_formatted'] = vb_number_format($userinfo['post_thanks_user_amount']);
            $userinfo['post_thanks_thanked_times_formatted'] = vb_number_format($userinfo['post_thanks_thanked_times']);
            $userinfo['post_thanks_thanked_posts_formatted'] = vb_number_format($userinfo['post_thanks_thanked_posts']);

            $thanked_info =
                $userinfo['post_thanks_thanked_times'] == 1
                    ? $vbphrase['post_thanks_time_post']
                    : $userinfo['post_thanks_thanked_posts'] == 1
                        ? construct_phrase("$vbphrase[post_thanks_times_post]", "$userinfo[post_thanks_thanked_times_formatted]")
                        : construct_phrase("$vbphrase[post_thanks_times_posts]",
                                            "$userinfo[post_thanks_thanked_times_formatted]",
                                            "$userinfo[post_thanks_thanked_posts_formatted]");

            $mobiquo_return_array[] = new xmlrpcval(array(
                'name'  => new xmlrpcval(mobiquo_encode($vbphrase['post_thanks_total_thanks']), 'base64'),
                'value' => new xmlrpcval(mobiquo_encode($userinfo['post_thanks_user_amount_formatted'])." \n".mobiquo_encode($thanked_info), 'base64')
            ), 'struct');
        }


        $mobiquo_user_online = false;
        $mobiquo_user_online = (fetch_online_status($userinfo, true)) ? true : false;
        $mobiquo_return_display_text = "";
        if($prepared['usertitle']){
            $mobiquo_return_display_text .= $prepared['usertitle'];
        }

        $mobiquo_can_ban = true;
        require_once(DIR . '/includes/adminfunctions.php');
        require_once(DIR . '/includes/functions_banning.php');
        if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
        {
            $mobiquo_can_ban = false;
        }

        // check that user has permission to ban the person they want to ban
        if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
        {
            if (can_moderate(0, '', $userinfo['userid'], $userinfo['usergroupid'] . (trim($userinfo['membergroupids']) ? ",$userinfo[membergroupids]" : ''))
            OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
            OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
            OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
            {
                $mobiquo_can_ban = false;
            }
        } else {
            if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
            OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
            {
                $mobiquo_can_ban = false;
            }
        }

        $user_action = '';
        if($userinfo['where']){
            $user_action = strip_tags($userinfo['action'].": ".$userinfo['where']);
        } else {
            $user_action = strip_tags($userinfo['action']);
        }
        if(strpos($userinfo['where'], 'mobiquo.php')){
            $user_action = 'via Tapatalk Forum App';
        }

        $mobiquo_is_ban = false;
        if(!($vbulletin->usergroupcache[$userinfo['usergroupid']]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])){
            $mobiquo_is_ban = true;
        }
        $isIgnored = userIsIgnored($userinfo['userid']);
        $mobiquo_can_ban = $mobiquo_is_ban ? false : $mobiquo_can_ban;
        $return_user = array(
            'user_id'               => new xmlrpcval($userinfo['userid'], 'string'),
            'username'             => new xmlrpcval(mobiquo_encode($userinfo['username']), 'base64'),
            'user_type'             => new xmlrpcval(get_usertype_by_item($userinfo['userid']), 'base64'),
            'reg_time'              => new xmlrpcval(mobiquo_time_encode($userinfo['joindate']), 'dateTime.iso8601'),
            'timestamp_reg'         => new xmlrpcval($userinfo['joindate'], 'string'),
            'post_count'            => new xmlrpcval($userinfo['posts'], 'int'),
            'custom_fields_list'    => new xmlrpcval($mobiquo_return_array, 'array'),
            'last_activity_time'    => new xmlrpcval(mobiquo_time_encode($userinfo['lastactivity']), 'dateTime.iso8601'),
            'timestamp'             => new xmlrpcval($userinfo['lastactivity'], 'string'),
            'can_ban'               => new xmlrpcval($mobiquo_can_ban, 'boolean'),
            'is_ban'                => new xmlrpcval($mobiquo_is_ban, 'boolean'),
            'is_online'             => new xmlrpcval($mobiquo_user_online, 'boolean'),
            'is_friend'             => new xmlrpcval($userinfo['isfriend'], 'boolean'),
            'accept_pm'             => new xmlrpcval($show['pm'], 'boolean'),
            'current_activity'      => new xmlrpcval(mobiquo_encode($user_action), 'base64'),
            'display_text'          => new xmlrpcval(mobiquo_encode($mobiquo_return_display_text), 'base64'),
            'is_ignored'            => new xmlrpcval($isIgnored, 'boolean'),
        );

        fetch_avatar_from_userinfo($userinfo, true, false);

        if($userinfo[avatarurl]){
            $return_user['icon_url'] = new xmlrpcval(get_icon_real_url($userinfo['avatarurl']), 'string');
        }

        else {
            $return_user['icon_url'] = new xmlrpcval('', 'string');
        }

        $ignored_users[] = new xmlrpcval($return_user, 'struct');
    }

    $return_data = array(
        'total' => new xmlrpcval($ignore_count, 'int'),
        'list'         => new xmlrpcval($ignored_users, 'array'),
    );

    return new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}

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
require_once(DIR .'/includes/functions_bigthree.php');
require_once(DIR .'/includes/functions_forumlist.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_online.php');


function get_online_users_func($params)
{
    global $vbulletin, $db, $permissions, $show, $limitlower, $limitupper;
    
    if (!$vbulletin->options['WOLenable'])
    {
        return_fault(fetch_error('whosonlinedisabled'));
    }

    if (!($permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline']))
    {
        return_fault();
    }
    
    $params = php_xmlrpc_decode($params);
    $pagenumber = isset($params[0]) && intval($params[0]) ? intval($params[0]) : 1;
    $perpage = isset($params[1]) && intval($params[1]) ? intval($params[1]) : 20;

    $limitlower = ($pagenumber - 1) * $perpage + 1;
    $limitupper = $pagenumber * $perpage;

    $filter_str = '';
    if (isset($params[2]))
    {
        $filter_id = intval($params[2]);
        if (isset($params[3]) && $params[3] == 'topic')
            $filter_str = " AND session.inthread='$filter_id' ";
        else
            $filter_str = " AND session.inforum='$filter_id' ";
    }
    
    
    $login_users = array();
    $activeusers = '';

    $datecut = TIMENOW - $vbulletin->options['cookietimeout'];
    $numbervisible = 0;
    $numberregistered = 0;
    $numberguest = 0;

    $hook_query_fields = $hook_query_joins = $hook_query_where = '';
        
    require_once(DIR . '/includes/class_userprofile.php');
    require_once(DIR . '/includes/class_profileblock.php');
    $userperms = cache_permissions($userinfo, false);
    $forumusers = $db->query_read_slave("
        SELECT
            user.username, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible, user.usergroupid,
            session.userid, session.inforum, session.lastactivity, session.badlocation,session.location, session.useragent,
            IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
            $hook_query_fields
        FROM " . TABLE_PREFIX . "session AS session
        LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = session.userid)
        $hook_query_joins
        WHERE session.lastactivity > $datecut $filter_str
        $hook_query_where
        " . iif($vbulletin->options['displayloggedin'] == 1 OR $vbulletin->options['displayloggedin'] == 3, "ORDER BY username ASC") . "
    ");

    $inforum = array();

    while ($loggedin = $db->fetch_array($forumusers))
    {
        $userid = $loggedin['userid'];
        if (!$userid)
        {
            $numberguest++;
            if (!$loggedin['badlocation'])
            {
                $inforum["$loggedin[inforum]"]++;
            }
        }
        else if (empty($userinfos[$userid]) OR ($userinfos["$userid"]['lastactivity'] < $loggedin['lastactivity']))
        {
            unset($userinfos[$userid]); // need this to sort by lastactivity
            $userinfos[$userid] = $loggedin;
            $userinfos[$userid]['useragent'] = htmlspecialchars_uni($loggedin['useragent']);
            if ($loggedin['invisible'])
            {
                if (($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $userid == $vbulletin->userinfo['userid'])
                {
                    $userinfos[$userid]['hidden'] = '*';
                    $userinfos[$userid]['invisible'] = 0;
                }
            }
            $userinfos[$userid]['buddy'] = $buddy[$userid];
        }
    }

    
    convert_ids_to_titles();

    if (is_array($userinfos))
    {
        foreach ($userinfos AS $key => $val)
        {
            if (!$val['invisible'])
            {
                $numbervisible++;
            }
            else
            {
                $numberinvisible++;
            }
        }
    }
    
    if (!$vbulletin->userinfo['userid'] AND $numberguest == 0)
    {
        $numberguest++;
    }
    
    $count = 0;
    foreach ($userinfos AS $userid => $loggedin)
    {
        $numberregistered++;
        $count++;
        if ($count > $limitupper OR $count < $limitlower)
        {
            continue;
        }
        if (!$loggedin['invisible'])
        {
            $loggedin = process_online_location($loggedin, 1);
            $loggedin = construct_online_bit($loggedin, 0);
        }
        if ($userid != $vbulletin->userinfo['userid'] AND !$loggedin['badlocation'])
        {
            $inforum["$loggedin[inforum]"]++;
        }
        fetch_musername($loggedin);

        if (fetch_online_status($loggedin))
        {
            if($loggedin['where']){
                $display_text = strip_tags($loggedin['action'].": ".$loggedin['where']);
            } else {
                $display_text = strip_tags($loggedin['action']);
            }
            
            if(strpos($loggedin['where'], '/mobiquo.php')){
                $display_text = 'via Tapatalk';
            }

            $from = 'browser';
            if(isset($loggedin['useragent']))
            {
                $userAgent = $loggedin['useragent'];
                if(strpos($userAgent,'Android') !== false || strpos($userAgent,'iPhone') !== false || strpos($userAgent,'BlackBerry') !== false)
                    $from = 'mobile';
                if(strpos($userAgent,'Tapatalk') !== false)
                    $from = 'tapatalk';
                if(strpos($userAgent,'BYO') !== false)
                    $from = 'byo';            }

            $username = mobiquo_encode($loggedin['musername']) ? mobiquo_encode($loggedin['musername']) : mobiquo_encode($loggedin['username']);
            
            $online_user = array(
                'user_id'       => new xmlrpcval($loggedin['userid'], 'string'),
                'username'      => new xmlrpcval($username, 'base64'),
                'user_name'     => new xmlrpcval($username, 'base64'),
                'user_type'     => new xmlrpcval(get_usertype_by_item($loggedin['userid']), 'base64'),
                'icon_url'      => new xmlrpcval(mobiquo_get_user_icon($loggedin['userid']), 'string'),
                'from'         => new xmlrpcval($from, 'string'),
                'display_text'  => new xmlrpcval(mobiquo_encode($display_text), 'base64'),
            );
            
            $login_users[] = new xmlrpcval($online_user, 'struct');
            $numbervisible++;
            $show['comma_leader'] = ($activeusers != '');
        }
    }

    // memory saving
    unset($userinfos, $loggedin);
    $db->free_result($forumusers);

    $totalonline = $numberregistered + $numberguest;
    $numberinvisible = $numberregistered - $numbervisible;


    if (defined('NOSHUTDOWNFUNC'))
    {
        exec_shut_down();
    }
    
    $return_data = array(
        'member_count' => new xmlrpcval($numberregistered, 'int'),
        'guest_count'  => new xmlrpcval($numberguest, 'int'),
        'list'         => new xmlrpcval($login_users, 'array'),
    );
    
    return  new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}

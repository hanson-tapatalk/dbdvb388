<?php

defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function get_recommended_user_func($xmlrpc_params)
{
    global $vbulletin, $request_params, $db;

    $data = array();
    $data['page'] = $request_params[0];
    $data['perpage'] = $request_params[1];
    $data['mode'] = $request_params[2];

    $user_id = $vbulletin->userinfo['userid'];
    $mobi_api_key = trim($vbulletin->options['push_key']);
    if (preg_match('/[A-Z0-9]{32}/', $mobi_api_key) && !empty($user_id))
    {
        $return_user_lists = array();

        $total = 0;

        $user_lists = array();
        $user_lists = add_watched_your_thread_users($user_lists);
        $user_lists = add_coversation_users($user_lists);
        $user_lists = add_thread_watch_users($user_lists);
        $user_lists = add_friend_users($user_lists);

        $page = isset($data['page']) && !empty($data['page']) ? $data['page'] : 1;
        $perpage = isset($data['perpage']) && !empty($data['perpage']) ? $data['perpage'] : 20;
        $start = ($page-1) * $perpage;
        $end = $start + $perpage;
        $return_user_lists = array();
        if(isset($user_lists[$user_id])) unset($user_lists[$user_id]);
        
        if(!empty($user_lists))
        {
            if(isset($data['mode']) && $data['mode'] == 2)
            {
                $check_users = implode(',', array_keys($user_lists));
                $valid_users_result = $db->query_read_slave("SELECT * FROM " . TABLE_PREFIX . "tapatalk_users WHERE userid IN ($check_users)");
                while ($tapausers = $db->fetch_array($valid_users_result))
                {
                    unset($user_lists[$tapausers['userid']]);
                }
                if($is_tapa_user) continue;
            }
            $total = count($user_lists);
            arsort($user_lists);
            $num_track = 0;
            foreach ($user_lists as $uid => $score)
            {
                if($num_track > $start - 1 && $num_track < $end)
                {

                    if(empty($uid) || $uid == $vbulletin->userinfo['userid']) continue;

                    $fetch_userinfo_options = (
                        FETCH_USERINFO_AVATAR
                    );
                    $userinfo = mobiquo_verify_id('user', $uid, 1, 1, $fetch_userinfo_options);
                    fetch_avatar_from_userinfo($userinfo, true, false);
                    if($userinfo[avatarurl]){
                        $icon_url = get_icon_real_url($userinfo['avatarurl']);
                    }else {
                        $icon_url = '';
                    }
                    if(!empty($userinfo['username']) && !empty($userinfo['userid']))
                    {
                        $return_user_lists[] = new xmlrpcval(array(
                            'username'   => new xmlrpcval($userinfo['username'], 'base64'),
                            'user_id'    => new xmlrpcval($userinfo['userid'], 'string'),
                            'icon_url'   => new xmlrpcval($icon_url, 'string'),
                        ), 'struct');
                    }
                    $num_track ++;
                }
            }
        }
    }

    $suggested_users = new xmlrpcval(array(
        'total' => new xmlrpcval($total, 'int'),
        'list'  => new xmlrpcval($return_user_lists, 'array'),
    ), 'struct');

    return new xmlrpcresp($suggested_users);
}

function add_watched_your_thread_users($user_lists)
{
    global $vbulletin, $db;

    $userid = $vbulletin->userinfo['userid'];
    //from search.php line 3577 - 3588
    $threads = $db->query_read_slave("
        SELECT thread.threadid
        FROM " . TABLE_PREFIX . "thread AS thread
        WHERE thread.postuserid = $userid
        ORDER BY lastpost DESC
        LIMIT 0, 50
    ");
    while ($thread = $db->fetch_array($threads))
    {
        if(!empty($thread['threadid']))
        {
            $uids = $vbulletin->db->query_read_slave("SELECT subscribethread.userid
                FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
                WHERE subscribethread.threadid = $thread[threadid]
            ");

            while($uid = $db->fetch_array($uids))
            {
                if(!empty($uid) && $uid['userid'] != $userid)
                {
                    $user_lists = merge_users($user_lists, array($uid['userid'] => 3));
                }
            }
        }
    }

    return $user_lists;
}

function add_thread_watch_users($user_lists)
{
    global $vbulletin, $db;

    $userid = $vbulletin->userinfo['userid'];
    $uids = array();
    $thread_ids = $vbulletin->db->query_read_slave("SELECT subscribethread.threadid
        FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
        WHERE subscribethread.userid = $userid LIMIT 0, 50
    ");
    while ($thread = $db->fetch_array($thread_ids))
    {
        $posters = $db->query_read_slave("
            SELECT thread.postuserid
            FROM " . TABLE_PREFIX . "thread AS thread
            WHERE thread.threadid = $thread[threadid]
        ");
        while ($poster = $db->fetch_array($posters))
        {
            $user_lists = merge_users($user_lists, array($poster['postuserid'] => 3));
        }
    }
    return $user_lists;
}

function add_friend_users($user_lists)
{
    global $vbulletin, $db;

    $users_result = $db->query_read_slave("
        SELECT userlist.*
        FROM " . TABLE_PREFIX . "userlist AS userlist
        WHERE (userlist.userid = " . $vbulletin->userinfo['userid'] . " OR userlist.relationid = " . $vbulletin->userinfo['userid'] . ") AND userlist.type = 'buddy'"
    );

    while($buddy = $db->fetch_array($users_result))
    {
        if($buddy['friend'] == 'yes')
        {
             $score = 20;
        }
        elseif($buddy['friend'] == 'pending') 
        {
            $score = 2;
        }
        else
        {
            continue;
        }
        $user_lists = merge_users($user_lists, array($buddy['userid'] => $score));
        $user_lists = merge_users($user_lists, array($buddy['relationid'] => $score));
    }
    return $user_lists;
}

function add_coversation_users($user_lists)
{
    global $vbulletin, $db;

    if(!empty($vbulletin->userinfo['userid']))
    {
        $pms = $db->query_read_slave("SELECT pmtext.touserarray, pmtext.dateline, pmtext.fromuserid
            FROM " . TABLE_PREFIX . "pm AS pm
            LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
            WHERE pm.userid=" . $vbulletin->userinfo['userid']
        );

        while($pm = $db->fetch_array($pms))
        {
            $pm_recievers = unserialize($pm['touserarray']);
            $score = time() - $pm['dateline'] > 30*86400 ? 2 : 10;
            $user_lists = merge_users($user_lists, array($pm['fromuserid']  => $score));
            foreach($pm_recievers as $pm_reciever)
                foreach($pm_reciever as $id => $name)
                    if($id != $vbulletin->userinfo['userid']) $user_lists = merge_users($user_lists, array($id => $score));
        }
    }
    return $user_lists;
}


function merge_users($uids, $_uids)
{
    if(!empty($_uids) && is_array($_uids))
    {
        foreach($_uids as $id => $score)
        {
            if(isset($uids[$id]))
            {
                $uids[$id] += $score;
            }
            else
            {
                $uids[$id] = $score;
            }
        }
    }
    return $uids;
}

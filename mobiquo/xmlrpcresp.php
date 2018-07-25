<?php

defined('IN_MOBIQUO') or exit;

function search_func()
{
    global $search_result, $include_topic_num, $request_method, $vbulletin, $permissions;

    $can_subscribe = true;
    if (!$vbulletin->userinfo['userid']
        OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
        OR $vbulletin->userinfo['usergroupid'] == 4
        OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
        $can_subscribe = false;
    }

    $return_list = array();
    $total_unread_num = 0;
    if(isset($search_result['items']))
    {
        foreach ($search_result['items'] as $item)
        {
            $show = $item['show'];

            // fetch ban related status
            $check_userid = $search_result['showposts'] ? $item['userid'] : $item['postuserid'];
            list($mobiquo_can_ban, $mobiquo_is_ban) = mobiquo_user_ban($check_userid, $show['inlinemod']);

            if($search_result['showposts'])
            {
                $return_post = array(
                    'forum_id'          => new xmlrpcval($item['forumid'], 'string'),
                    'forum_name'        => new xmlrpcval(mobiquo_encode($item['forumtitle']), 'base64'),
                    'topic_id'          => new xmlrpcval($item['threadid'], 'string'),
                    'topic_title'       => new xmlrpcval(mobiquo_encode($item['threadtitle']), 'base64'),
                    'post_id'           => new xmlrpcval($item['postid'], 'string'),
                    'post_title'        => new xmlrpcval(mobiquo_encode($item['posttitle']), 'base64'),
                    'post_author_id'    => new xmlrpcval($item['userid'], 'string'),
                    'post_author_name'  => new xmlrpcval(mobiquo_encode($item['username']), 'base64'),
                    'post_author_user_type'             => new xmlrpcval(get_usertype_by_item($item['userid']), 'string'),
                    'post_time'         => new xmlrpcval(mobiquo_time_encode($item['postdateline']), 'dateTime.iso8601'),
                    'icon_url'          => new xmlrpcval(mobiquo_get_user_icon($item['userid']), 'string'),
                    'short_content'     => new xmlrpcval(mobiquo_encode(mobiquo_chop(post_content_clean($item['pagetext']))), 'base64'),
                    'timestamp'         => new xmlrpcval($item['postdateline'], 'string'),

                    'is_approved'       => new xmlrpcval($item['visible'], 'boolean'),
                );

                if ($show['gotonewpost'])   $return_post['new_post']    = new xmlrpcval(true, 'boolean');
                if ($show['approvepost'])   $return_post['can_approve'] = new xmlrpcval(true, 'boolean');
                if ($show['deleted'])       $return_post['is_deleted']  = new xmlrpcval(true, 'boolean');
                if ($show['managepost'])    $return_post['can_delete']  = new xmlrpcval(true, 'boolean');
                if ($show['managepost'])    $return_post['can_move']    = new xmlrpcval(true, 'boolean');
                if ($mobiquo_can_ban)       $return_post['can_ban']     = new xmlrpcval(true, 'boolean');
                if ($mobiquo_is_ban)        $return_post['is_ban']      = new xmlrpcval(true, 'boolean');

                $xmlrpc_post = new xmlrpcval($return_post, 'struct');

                array_push($return_list, $xmlrpc_post);
            }
            else
            {
                if ($show['gotonewpost']) $total_unread_num++;

                $return_thread = array(
                    'forum_id'              => new xmlrpcval($item['forumid'], 'string'),
                    'forum_name'            => new xmlrpcval(mobiquo_encode($item['forumtitle']), 'base64'),
                    'topic_id'              => new xmlrpcval($item['threadid'], 'string'),
                    'topic_title'           => new xmlrpcval(mobiquo_encode($item['threadtitle']), 'base64'),
                    'prefix'                => new xmlrpcval(mobiquo_encode($item['prefix_plain_html']), 'base64'),
                    'post_author_id'        => new xmlrpcval($item['postuserid'], 'string'),
                    'post_author_name'      => new xmlrpcval(mobiquo_encode($item['lastposter']), 'base64'),
                    'post_author_user_type'             => new xmlrpcval(get_usertype_by_item($item['postuserid']), 'string'),
                    'post_time'             => new xmlrpcval(mobiquo_time_encode($item['lastpost']), 'dateTime.iso8601'),

                    // compatibility data
                    'last_reply_author_id'  => new xmlrpcval(mobiquo_encode($item['postuserid']), 'string'),
                    'last_reply_author_name'  => new xmlrpcval(mobiquo_encode($item['lastposter']), 'base64'),
                    'last_reply_author_user_type'  => new xmlrpcval(get_usertype_by_item($item['postuserid']), 'string'),
                    'last_reply_time'       => new xmlrpcval(mobiquo_time_encode($item['lastpost']), 'dateTime.iso8601'),

                    'timestamp'             => new xmlrpcval($item['lastpost'], 'string'),
                    'icon_url'              => new xmlrpcval(mobiquo_get_user_icon($item['postuserid']) , 'string'),
                    'reply_number'          => new xmlrpcval($item['replycount'], 'int'),
                    'view_number'           => new xmlrpcval($item['views'], 'int'),
                    'attachment'            => new xmlrpcval($item['attach'], 'string'),
                    'can_subscribe'         => new xmlrpcval($can_subscribe, 'boolean'),
                    'short_content'         => new xmlrpcval(mobiquo_encode(mobiquo_chop(post_content_clean($item['originalpreview']))), 'base64'),

                    'is_approved'           => new xmlrpcval($item['visible'], 'boolean'),
                );

                if (!$item['open'])         $return_thread['is_closed']     = new xmlrpcval(true, 'boolean');
                if ($show['gotonewpost'])   $return_thread['new_post']      = new xmlrpcval(true, 'boolean');
                if ($show['openthread'])    $return_thread['can_close']     = new xmlrpcval(true, 'boolean');
                if ($show['deletethread'])  $return_thread['can_delete']    = new xmlrpcval(true, 'boolean');
                if ($item['visible'] == 2)  $return_thread['is_deleted']    = new xmlrpcval(true, 'boolean');
                if ($show['movethread'])    $return_thread['can_stick']     = new xmlrpcval(true, 'boolean');
                if ($show['sticky'])        $return_thread['is_sticky']     = new xmlrpcval(true, 'boolean');
                if ($show['movethread'])    $return_thread['can_move']      = new xmlrpcval(true, 'boolean');
                if ($show['approvethread']) $return_thread['can_approve']   = new xmlrpcval(true, 'boolean');
                if ($show['approvethread']) $return_thread['can_rename']    = new xmlrpcval(true, 'boolean');
                if ($show['subscribed'])    $return_thread['is_subscribed'] = new xmlrpcval(true, 'boolean');
                if ($mobiquo_can_ban)       $return_thread['can_ban']       = new xmlrpcval(true, 'boolean');
                if ($mobiquo_is_ban)        $return_thread['is_ban']        = new xmlrpcval(true, 'boolean');
                if ($item['vbseo_likes'])   $return_thread['like_count']      = new xmlrpcval($item['vbseo_likes'], 'int');

                $xmlrpc_thread = new xmlrpcval($return_thread, 'struct');

                array_push($return_list, $xmlrpc_thread);
            }
        }
    }
    if ($request_method == 'get_unread_topic') $total_unread_num = $search_result['result_num'];

    if ($include_topic_num) {
        if($search_result['showposts']) {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_result['searchid'], 'string'),
                'total_post_num'    => new xmlrpcval($search_result['result_num'], 'int'),
                'posts'             => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        } else {
            return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(true, 'boolean'),
                'search_id'         => new xmlrpcval($search_result['searchid'], 'string'),
                'total_topic_num'   => new xmlrpcval($search_result['result_num'], 'int'),
                'total_unread_num'  => new xmlrpcval($total_unread_num, 'int'),
                'topics'            => new xmlrpcval($return_list, 'array'),
            ), 'struct'));
        }
    } else {
        return new xmlrpcresp(new xmlrpcval($return_list, 'array'));
    }
}


function return_mod_true()
{
    return new xmlrpcresp(new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'is_login_mod'  => new xmlrpcval(true, 'boolean'),
    ), 'struct'));
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function return_mod_for_mergetopic_true()
{
			global $vbulletin;
	 return new xmlrpcresp(new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'topic_id'  		=> new xmlrpcval($vbulletin->GPC['destthreadid'], 'string'),
        'is_login_mod'  => new xmlrpcval(true, 'boolean'),
   ), 'struct'));
}
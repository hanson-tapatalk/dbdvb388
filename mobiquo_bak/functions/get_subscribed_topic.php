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
defined('CWD1') or exit;

defined('IN_MOBIQUO') or exit;


// ####################### SET PHP ENVIRONMENT ###########################

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'subscription');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('user', 'forumdisplay');

// get special data templates from the datastore
$specialtemplates = array(
    'iconcache',
    'noavatarperms'
);

// pre-cache templates used by all actions
$globaltemplates = array(
    'USERCP_SHELL',
    'usercp_nav_folderbit',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
    'viewsubscription' => array(
        'forumdisplay_sortarrow',
        'threadbit',
        'SUBSCRIBE'
        ),
    'addsubscription' => array(
        'subscribe_choosetype'
        ),
    'editfolders' => array(
        'subscribe_folderbit',
        'subscribe_showfolders'
        ),
    'dostuff' => array(
        'subscribe_move'
        )
);

$actiontemplates['none'] =& $actiontemplates['viewsubscription'];

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################
function get_subscribed_topic_func($params)
{
    global $vbulletin, $permissions, $db, $show, $stylevar, $newthreads, $dotthreads, $perpage, $ignore;
    global $vbphrase, $folderid, $folderselect, $foldernames, $messagecounters, $subscribecounters, $folder;

    if (empty($_REQUEST['do']))
    {
        $_REQUEST['do'] = 'viewsubscription';
    }

    if ((!$vbulletin->userinfo['userid'] AND $_REQUEST['do'] != 'removesubscription') OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])) OR $userinfo['usergroupid'] == 3 OR $vbulletin->userinfo['usergroupid'] == 4 OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
        $return = array(20, 'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }

    $decode_params = php_xmlrpc_decode($params);
    
    if (isset($decode_params[1]))
    {
        list($start_num, $thread_num) = process_page($decode_params[0], $decode_params[1]);
    }
    else
    {
        $start_num = 0;
        $thread_num = 20;
    }
    
    $vbulletin->input->clean_array_gpc('r', array(
        'folderid'   => TYPE_NOHTML,
        'perpage'    => TYPE_UINT,
        'pagenumber' => TYPE_UINT,
        'sortfield'  => TYPE_NOHTML,
        'sortorder'  => TYPE_NOHTML,
    ));

    // Values that are reused in templates
    $sortfield  =& $vbulletin->GPC['sortfield'];
    $perpage    =& $vbulletin->GPC['perpage'];
    $pagenumber =& $vbulletin->GPC['pagenumber'];
    $folderid   =& $vbulletin->GPC['folderid'];

    /////////////edit for mobiquo//
    $getallfolders = true;
    $show['allfolders'] = true;
    /////////////edit for mobiquo//
    $folderselect["$folderid"] = 'selected="selected"';
    require_once(DIR . '/includes/functions_misc.php');
    
    $folderjump = construct_folder_jump(1, $folderid); // This is the "Jump to Folder"

    if ($vbulletin->GPC['sortorder'] != 'asc')
    {
        $vbulletin->GPC['sortorder'] = 'desc';
        $sqlsortorder = 'DESC';
        $order = array('desc' => 'selected="selected"');
    }
    else
    {
        $sqlsortorder = '';
        $order = array('asc' => 'selected="selected"');
    }

    switch ($sortfield)
    {
        case 'title':
        case 'lastpost':
        case 'replycount':
        case 'views':
        case 'postusername':
            $sqlsortfield = 'thread.' . $sortfield;
            break;
        default:
            $handled = false;

            if (!$handled)
            {
                $sqlsortfield = 'thread.lastpost';
                $sortfield = 'lastpost';
            }
    }
    $sort = array($sortfield => 'selected="selected"');

    if ($getallfolders)
    {
        if(isset($subscribecounters)){
            $totalallthreads = array_sum($subscribecounters);
        }
    }
    else
    {
        $totalallthreads = $subscribecounters["$folderid"];
    }

    sanitize_pageresults($totalallthreads, $pagenumber, $perpage, 200, $vbulletin->options['maxthreads']);

    $hook_query_fields = $hook_query_joins = $hook_query_where = '';

    $getthreads = $db->query_read_slave("
        SELECT thread.threadid, emailupdate, subscribethreadid, thread.forumid, thread.postuserid
        FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
        LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = subscribethread.threadid)
        WHERE subscribethread.userid = " . $vbulletin->userinfo['userid'] . "
            AND thread.visible = 1
            AND canview = 1
        " . iif(!$getallfolders, "    AND folderid = $folderid") . "
        ORDER BY $sqlsortfield $sqlsortorder
        LIMIT $start_num, $thread_num
    ");

    if ($totalthreads = $db->num_rows($getthreads))
    {
        $forumids = array();
        $threadids = array();
        $emailupdate = array();
        $killthreads = array();
        while ($getthread = $db->fetch_array($getthreads))
        {
            $forumperms = fetch_permissions($getthread['forumid']);

            if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR ($getthread['postuserid'] != $vbulletin->userinfo['userid'] AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
            {
                $killthreads["$getthread[subscribethreadid]"] = $getthread['subscribethreadid'];
                $totalallthreads--;
                continue;
            }
            $forumids["$getthread[forumid]"] = true;
            $threadids[] = $getthread['threadid'];
            $emailupdate["$getthread[threadid]"] = $getthread['emailupdate'];
            $subscribethread["$getthread[threadid]"] = $getthread['subscribethreadid'];
        }
        $threadids = implode(', ', $threadids);
    }
    unset($getthread);
    $db->free_result($getthreads);

    if (!empty($killthreads))
    {  // Update thread subscriptions
        $vbulletin->db->query_write("
            UPDATE " . TABLE_PREFIX . "subscribethread
            SET canview = 0
            WHERE subscribethreadid IN (" . implode(', ', $killthreads) . ")
        ");
    }

    if (!empty($threadids))
    {
        cache_ordered_forums(1);
        $colspan = 5;
        $show['threadicons'] = false;
    
        // get last read info for each thread
        $lastread = array();
        foreach (array_keys($forumids) AS $forumid)
        {
            if ($vbulletin->options['threadmarking'])
            {
                $lastread["$forumid"] = max($vbulletin->forumcache["$forumid"]['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
            }
            else
            {
                $lastread["$forumid"] = max(intval(fetch_bbarray_cookie('forum_view', $forumid)), $vbulletin->userinfo['lastvisit']);
            }
            if ($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['allowicons'])
            {
                $show['threadicons'] = true;
                $colspan = 6;
            }
        }
    
        // get thread preview?
        if ($vbulletin->options['threadpreview'] > 0)
        {
            $previewfield = 'post.pagetext AS preview, ';
            $previewjoin = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
        }
        else
        {
            $previewfield = '';
            $previewjoin = '';
        }
    
        $hasthreads = true;
        $threadbits = '';
        $pagenav = '';
        $counter = 0;
        $toread = 0;
    
        $vbulletin->options['showvotes'] = intval($vbulletin->options['showvotes']);
    
        if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
        {
            $lastpost_info = "IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost, " .
                             "IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter, " .
                             "IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid";
    
            $tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON " .
                         "(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')';
        }
        else
        {
            $lastpost_info = 'thread.lastpost, thread.lastposter, thread.lastpostid';
            $tachyjoin = '';
        }
    
        $hook_query_fields = $hook_query_joins = $hook_query_where = '';
    
        $threads = $db->query_read_slave("
            SELECT
                IF(votenum >= " . $vbulletin->options['showvotes'] . ", votenum, 0) AS votenum,
                IF(votenum >= " . $vbulletin->options['showvotes'] . " AND votenum > 0, votetotal / votenum, 0) AS voteavg,
                $previewfield thread.threadid, thread.title AS threadtitle, forumid, pollid, open, replycount, postusername, thread.prefixid,thread.sticky,
                $lastpost_info, postuserid, thread.dateline, views, thread.iconid AS threadiconid, notes, thread.visible, thread.attach,
                thread.taglist
                " . ($vbulletin->options['threadmarking'] ? ", threadread.readtime AS threadread" : '') . "
                $hook_query_fields
            FROM " . TABLE_PREFIX . "thread AS thread
            $previewjoin
            " . ($vbulletin->options['threadmarking'] ? " LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
            $tachyjoin
            $hook_query_joins
            WHERE thread.threadid IN ($threadids)
            ORDER BY $sqlsortfield $sqlsortorder
        ");
        unset($sqlsortfield, $sqlsortorder);
        
        require_once(DIR . '/includes/functions_forumdisplay.php');
        
        // Get Dot Threads
        $dotthreads = fetch_dot_threads_array($threadids);
        if ($vbulletin->options['showdots'] AND $vbulletin->userinfo['userid'])
        {
            $show['dotthreads'] = true;
        }
        else
        {
            $show['dotthreads'] = false;
        }
        
        if ($vbulletin->options['threadpreview'] AND $vbulletin->userinfo['ignorelist'])
        {
            // Get Buddy List
            $buddy = array();
            if (trim($vbulletin->userinfo['buddylist']))
            {
                $buddylist = preg_split('/( )+/', trim($vbulletin->userinfo['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($buddylist AS $buddyuserid)
                {
                    $buddy["$buddyuserid"] = 1;
                }
            }
            DEVDEBUG('buddies: ' . implode(', ', array_keys($buddy)));
            // Get Ignore Users
            $ignore = array();
            if (trim($vbulletin->userinfo['ignorelist']))
            {
                $ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($ignorelist AS $ignoreuserid)
                {
                    if (!$buddy["$ignoreuserid"])
                    {
                        $ignore["$ignoreuserid"] = 1;
                    }
                }
            }
            DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));
        }
        
        $foruminfo['allowratings'] = true;
        $show['notificationtype'] = true;
        $show['threadratings'] = true;
        $show['threadrating'] = true;
        $return_thread = array();
        while ($thread = $db->fetch_array($threads))
        {
            // unset the thread preview if it can't be seen
            $forumperms = fetch_permissions($thread['forumid']);
            if ($vbulletin->options['threadpreview'] > 0 AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
            {
                $thread['preview'] = '';
            }
        
            $mobiquo_can_delete = false;
            if (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts'))
            {
                $mobiquo_can_delete = true;
            }
            $mobiquo_can_close = false;
            if (can_moderate($item['forumid'], 'canopenclose'))
            {
                $mobiquo_can_close = true;
            }
            $mobiquo_can_sticky = false;
            if (can_moderate($item['forumid'], 'canmanagethreads'))
            {
                $mobiquo_can_sticky = true;
            }
            $mobiquo_can_approve = false;
            if (can_moderate($item['forumid'], 'canmoderateposts'))
            {
                $mobiquo_can_approve = true;
            }
            $thread_replycount = $thread[replycount];
            $thread_views      = $thread[views];
            $threadid = $thread['threadid'];
            // build thread data
            $thread = process_thread_array($thread, $lastread["$thread[forumid]"]);
        
            if($thread[lastpostid]){
                $last_topic = $db->query_first_slave("
                            SELECT post.pagetext,post.userid
                            FROM " . TABLE_PREFIX . "post AS post
                            WHERE post.postid =$thread[lastpostid]
                                AND post.visible = 1
                                 ");
            } else {
                $last_topic = $db->query_first_slave("
                            SELECT post.pagetext,post.userid
                            FROM " . TABLE_PREFIX . "post AS post
                            WHERE post.threadid =$thread[threadid]
                                AND post.visible = 1
                            ORDER BY postid DESC
                            LIMIT 1
                                 ");
            }
            if($show['gotonewpost']){
                $mobiquo_new_post = 1;
            } else{
                $mobiquo_new_post = 0;
            }
            $fetch_userinfo_options = (
                FETCH_USERINFO_AVATAR
            );
            $lastuserinfo = mobiquo_verify_id('user', $last_topic['userid'], 0, 1, $fetch_userinfo_options);
            if(!is_array($lastuserinfo)){
                $lastuserinfo = array();
            }
            fetch_avatar_from_userinfo($lastuserinfo,true,false);
        
            if($lastuserinfo[avatarurl]){
                $icon_url=get_icon_real_url($lastuserinfo['avatarurl']);
            } else {
                $icon_url = '';
            }
            $is_deleted = false;
            if($thread['visible'] == 2){
                $is_deleted = true;
            }
            $is_approved = true;
            if($thread['visible'] == 0){
                $is_approved = false;
            }
        
            if($mobiquo_can_delete  OR $mobiquo_can_close OR $mobiquo_can_sticky OR $mobiquo_can_approve)
            {
                require_once(DIR . '/includes/adminfunctions.php');
                require_once(DIR . '/includes/functions_banning.php');
                cache_permissions($lastuserinfo, false);
        
                $mobiquo_can_ban = true;
                if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
                {
                    $mobiquo_can_ban = false;
                }
        
                // check that user has permission to ban the person they want to ban
                if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
                {
                    if (can_moderate(0, '', $lastuserinfo['userid'], $lastuserinfo['usergroupid'] . (trim($lastuserinfo['membergroupids']) ? ", $lastuserinfo[membergroupids]" : ''))
                    OR $lastuserinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
                    OR $lastuserinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
                    OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
                    {
                        $mobiquo_can_ban = false;
                    }
                } else {
                    if ($lastuserinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
                    OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
                    {
                        $mobiquo_can_ban = false;
                    }
                }
            } else {
                $mobiquo_can_ban = false;
            }
            $mobiquo_is_ban = false;
            if(!($vbulletin->usergroupcache[$lastuserinfo['usergroupid']]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])){
                $mobiquo_is_ban = true;
            }
            $mobiquo_can_rename = isset($thread['title_editable']) && $thread['title_editable'];
            $mobiquo_isclosed = iif($thread['open'], false, true);
            $return_topic = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($thread['forumid'], 'string'),
                'forum_name'        => new xmlrpcval(mobiquo_encode($thread['forumtitle']), 'base64'),
                'topic_id'          => new xmlrpcval($thread['threadid'], 'string'),
                'topic_title'       => new xmlrpcval(mobiquo_encode($thread['threadtitle']), 'base64'),
                'prefix'            => new xmlrpcval(mobiquo_encode($thread['prefix_plain_html']), 'base64'),
                'post_author_id'    => new xmlrpcval($last_topic['userid'], 'string'),
                'post_author_name'  => new xmlrpcval(mobiquo_encode($thread['lastposter']), 'base64'),
                'reply_number'      => new xmlrpcval($thread_replycount, 'int'),
                'view_number'       => new xmlrpcval($thread_views, "int"),
                'is_closed'         => new xmlrpcval($mobiquo_isclosed, 'boolean'),
                'new_post'          => new xmlrpcval($mobiquo_new_post, 'boolean'),
                'can_delete'        => new xmlrpcval($mobiquo_can_delete, 'boolean'),
                'can_sticky'        => new xmlrpcval($mobiquo_can_sticky, 'boolean'),
                'can_stick'         => new xmlrpcval($mobiquo_can_sticky, 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'], 'boolean'),
                'can_close'         => new xmlrpcval($mobiquo_can_close, 'boolean'),
                'can_move'          => new xmlrpcval($mobiquo_can_sticky, 'boolean'),
                'can_rename'        => new xmlrpcval($mobiquo_can_rename, 'boolean'),
                'can_approve'       => new xmlrpcval($mobiquo_can_approve, 'boolean'),
                'is_deleted'        => new xmlrpcval($is_deleted, 'boolean'),
                'can_ban'           => new xmlrpcval($mobiquo_can_ban, 'boolean'),
                'is_ban'            => new xmlrpcval($mobiquo_is_ban, 'boolean'),
                'is_approve'        => new xmlrpcval($is_approved, 'boolean'),
                'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
                'icon_url'          => new xmlrpcval($icon_url , 'string'),
                'subscribe_mode'    => new xmlrpcval(intval($emailupdate[$thread['threadid']]), 'int'),
                'short_content'     => new xmlrpcval(mobiquo_encode(mobiquo_chop(($last_topic['pagetext']))), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_time_encode($thread['lastpost']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($thread['lastpost'], 'string'),
            ), 'struct');
            
            array_push($return_thread, $return_topic);
        }
        
        $db->free_result($threads);
        unset($threadids);
        $oppositesort = iif($vbulletin->GPC['sortorder'] == 'asc', 'desc', 'asc');
        
        $show['havethreads'] = true;
    }
    else
    {
        $totalallthreads = 0;
        $show['havethreads'] = false;
    }
    
    if (defined('NOSHUTDOWNFUNC'))
    {
        exec_shut_down();
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($totalallthreads, 'int'),
        'topics' => new xmlrpcval($return_thread, 'array'),
    ), 'struct'));
}

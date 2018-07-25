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
defined('CWD1') or exit;
require_once(CWD1."/include/functions_get_forum.php");

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
require_once(DIR .'/includes/functions_forumlist.php');


function get_subscribed_forum_func()
{
    global $vbulletin, $permissions, $db, $show, $stylevar, $newthreads, $dotthreads, $perpage, $ignore;
    global $vbphrase, $folderid, $folderselect, $foldernames, $messagecounters, $subscribecounters, $folder;

    if (empty($_REQUEST['do']))
    {
        $_REQUEST['do'] = 'viewsubscription';
    }

    if ((!$vbulletin->userinfo['userid'] AND $_REQUEST['do'] != 'removesubscription') OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])) OR $userinfo['usergroupid'] == 3 OR $vbulletin->userinfo['usergroupid'] == 4 OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }
    
    cache_ordered_forums(1, 0, $vbulletin->userinfo['userid']);
    $show['forums'] = false;
    foreach ($vbulletin->forumcache AS $forumid => $forum)
    {
        if ($forum['subscribeforumid'] != '')
        {
            $show['forums'] = true;
        }
    }
    
    if ($show['forums'])
    {
        if ($vbulletin->options['showmoderatorcolumn'])
        {
            cache_moderators();
        }
        else
        {
            cache_moderators($vbulletin->userinfo['userid']);
        }
        fetch_last_post_array();

        
        // get notification type
        $subsforums = $db->query_read_slave("
            SELECT forumid, emailupdate
            FROM " . TABLE_PREFIX . "subscribeforum
            WHERE userid = " . $vbulletin->userinfo['userid']
        );
        
        global $emailupdate;
        $forumbits = array();
        if ($db->num_rows($subsforums))
        {
            while ($subsforum = $db->fetch_array($subsforums))
            {
                if($forum = get_single_forum($subsforum['forumid'],$subsforum['emailupdate']))
                {
                    $forumbits[] = $forum;
                }
            }
        }
        unset($subsforum);
        $db->free_result($subsforums);


        $show['collapsable_forums'] = true;

        //$forumbits = construct_forum_bit_mobiquo(-1,0,1);

        if (defined('NOSHUTDOWNFUNC'))
        {
            exec_shut_down();
        }
        
        return new xmlrpcresp(new xmlrpcval(array(
            'total_forums_num' => new xmlrpcval(sizeof($forumbits), 'int'),
            'forums' => new xmlrpcval($forumbits, 'array'),
        ), 'struct'));
    }
    else
    {
        return new xmlrpcresp(new xmlrpcval(array(
            'total_forums_num' => new xmlrpcval(0,'int'),
            'forums' => new xmlrpcval(array(),'array'),
        ), 'struct'));
    }
}

function get_single_forum($forumid, $emailupdate)
{
    global $tt_config,$vbulletin;
   $forum = $vbulletin->forumcache["$forumid"];
   if(empty($forum))
   {
       return false;
   }
   //winter001 -start
   $hideForumList = preg_split('/\s*,\s*/', trim($tt_config['hide_forum_id']));
   //winter001 -end

    //winter001 -start
    if (in_array($forum['forumid'], $hideForumList)) {
        return false;
    }
    //winter001 -end

    //$lastpostforum = $vbulletin->forumcache["$lastpostarray[$forumid]"];
    $lastpostforum = (empty($lastpostarray[$forumid]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);
    if(!$show_hide){
        if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
        {
            return false;
        }
    }
    $forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
    $lastpostforumperms = (empty($lastpostarray[$forumid]) ? 0 : $vbulletin->userinfo['forumpermissions']["$lastpostarray[$forumid]"]);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums'])))
    { // no permission to view current forum
        return false;
    }

    $lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];
    $forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
    $newPost = (($forum['statusicon'] == 'new') ? true : false);

    $GLOBALS['forumshown'] = true; // say that we have shown at least one forum

    if (($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
    { // get appropriate suffix for template name
        $tempext = '_post';
        $only_sub = 0;
    }
    else
    {
        $tempext = '_nopost';
        $only_sub = 1;
    }

  
    $forum['subforums'] ="";

    $children = explode(',', $forum['childlist']);

    $forum_locked = false;    
    if ($vbulletin->options['showlocks'] // show locks to users who can't post
        AND !$forum['link'] // forum is not a link
            AND(
                !($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']) // forum does not allow posting
                OR(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) // can't post new threads
                AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) // can't reply to own threads
                AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) // can't reply to others' threads
            )
        )
    ) {
        $forum_locked = true;
    }
  
    $mobiquo_can_subscribe =  iif($only_sub == 0, true, false);
 
    //get the permission of can_post 
    $TT_foruminfo = fetch_foruminfo($forumid);
    $TT_forumperms = fetch_permissions($forumid);
    if (!($TT_forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
    {
        // $return = array(20, 'security error (user may not have permission to access this feature)');
        // return return_fault($return);
        return false;
    }
    $TT_can_post = true;
    if (!($TT_forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !$TT_foruminfo['allowposting'])
    {
        $TT_can_post = false;
    }   
    //set in read only options?
    $TT_is_read_only_forums = false; 
    if (isset($vbulletin->options['tapatalk_readonly_forums']))
    {
        $TT_read_only_forums = unserialize($vbulletin->options['tapatalk_readonly_forums']);
        if (is_array($TT_read_only_forums))
            $TT_is_read_only_forums = in_array($forumid, $TT_read_only_forums);
    }
    $TT_can_post = !$TT_is_read_only_forums && $TT_can_post;
    //if being link forum, can_post should be false
    $TT_forumlink = trim($TT_foruminfo['link']);
    if(isset($TT_foruminfo['link']) && !empty($TT_forumlink))
        $TT_can_post = false;


    $forumbits_list = array(
        'forum_id'      => new xmlrpcval($forum['forumid'],"string"),
        'forum_name'    => new xmlrpcval(mobiquo_encode($forum['title']),"base64"),
        'description'   => new xmlrpcval(mobiquo_encode($forum['description']),"base64"),
        'sub_only'      => new xmlrpcval($only_sub,'boolean'),
        'parent_id'     => new xmlrpcval($forum['parentid'],"string"),
        'new_post'      => new xmlrpcval($newPost,'boolean'),
        'is_subscribed' => new xmlrpcval(true,'boolean'),
        'can_subscribe' => new xmlrpcval($mobiquo_can_subscribe,'boolean'),
        'url'           => new xmlrpcval($forum[link],"string"),
        'can_post'      => new xmlrpcval($TT_can_post, 'boolean'),
    );

    $forumbits_list['is_protected'] = new xmlrpcval(!verify_forum_password($forum['forumid'], $forum['password'], false), 'boolean');
            
    $icon_filename = tp_get_forum_icon($forumid, $forum['depth'] === 0 ?'category':($forum['link']?'link':'forum'), $forum['depth'] !== 0 && $forum_locked ? true : false, $newPost);
    if($icon_filename)
    {
        $logo_url = $vbulletin->options['bburl'].'/'.$vbulletin->options['tapatalk_directory'].'/forum_icons/'.$icon_filename;
        $forumbits_list['logo_url'] = new xmlrpcval($logo_url, 'string');
    }
            
    if(isset($emailupdate)) {
        $forumbits_list['subscribe_mode'] = new xmlrpcval(intval($emailupdate), 'int');
    }
    return new xmlrpcval($forumbits_list,'struct');
}
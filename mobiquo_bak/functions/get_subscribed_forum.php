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
        if ($db->num_rows($subsforums))
        {
            while ($subsforum = $db->fetch_array($subsforums))
            {
                $emailupdate[$subsforum['forumid']] = $subsforum['emailupdate'];
            }
        }
        unset($subsforum);
        $db->free_result($subsforums);


        $show['collapsable_forums'] = true;
        $forumbits = construct_forum_bit_mobiquo(-1,0,1);

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

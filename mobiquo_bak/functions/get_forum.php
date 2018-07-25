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

$phrasegroups = array();
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'mobiquo');
define('CSRF_PROTECTION', false);
define('CSRF_SKIP_LIST', '');

require_once('./global.php');
require_once(DIR .'/includes/functions_bigthree.php');
require_once(DIR .'/includes/functions_forumlist.php');


function get_forum_all_func($params) {
    return get_forum_func($params,1);
}

function get_forum_func($params,$show_hide = 0)
{
    global $vbulletin, $db;

    if (empty($foruminfo['forumid']))
    {
        // show all forums
        $forumid = -1;
    }
    else
    {
        // check forum permissions
        $_permsgetter_ = 'index';
        $forumperms = fetch_permissions($foruminfo['forumid']);

        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
        {
            print_no_permission();
        }
        verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
    }

    $params = php_xmlrpc_decode($params);
    $forumid = isset($params[1]) && $params[1] !== '' ? $params[1] : -1;
    if ($forumid == 0) $forumid = -1;
    
    cache_ordered_forums(1, 1);
    $subscribe_forums = array();
    if ( $vbulletin->userinfo['userid'])
    {
        $query = "
            SELECT subscribeforumid, forumid
            FROM " . TABLE_PREFIX . "subscribeforum
            WHERE userid = 
        " . $vbulletin->userinfo['userid'];
        $getthings = $db->query_read_slave($query);
        if ($db->num_rows($getthings))
        {
            while ($getthing = $db->fetch_array($getthings))
            {
                $subscribe_forums["$getthing[forumid]"] = $getthing;
            }
        }
    }

    if ($vbulletin->options['showmoderatorcolumn'])
    {
        cache_moderators();
    }
    else if ($vbulletin->userinfo['userid'])
    {
        cache_moderators($vbulletin->userinfo['userid']);
    }

    // define max depth for forums display based on $vbulletin->options[forumhomedepth]
    define('MAXFORUMDEPTH', 1000);

    $forumbits = construct_forum_bit_mobiquo($forumid,0,0,$subscribe_forums,$show_hide);
    if (defined('NOSHUTDOWNFUNC'))
    {
        exec_shut_down();
    }
    
    return new xmlrpcresp(new xmlrpcval($forumbits,"array"));
}


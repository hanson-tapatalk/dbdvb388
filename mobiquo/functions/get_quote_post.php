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
require_once(CWD1."/include/functions_create_topic.php");


// ####################### SET PHP ENVIRONMENT ###########################


// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newreply');
define('CSRF_PROTECTION', false);


// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
    'threadmanage',
    'posting',
    'postbit',
    'reputationlevel',
);

// get special data templates from the datastore
$specialtemplates = array(
    'smiliecache',
    'bbcodecache',
    'ranks'
);

// pre-cache templates used by all actions
$globaltemplates = array(
    'newreply',
    'newpost_attachment',
    'newreply_reviewbit',
    'newreply_reviewbit_ignore',
    'newreply_reviewbit_ignore_global',
    'newpost_attachmentbit',
    'im_aim',
    'im_icq',
    'im_msn',
    'im_yahoo',
    'im_skype',
    'postbit',
    'postbit_wrapper',
    'postbit_attachment',
    'postbit_attachmentimage',
    'postbit_attachmentthumbnail',
    'postbit_attachmentmoderated',
    'postbit_ip',
    'postbit_onlinestatus',
    'postbit_reputation',
    'bbcode_code',
    'bbcode_html',
    'bbcode_php',
    'bbcode_quote',
    'humanverify',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_bigthree.php');

function get_quote_post_func($xmlrpc_params)
{
    global $vbulletin, $db, $forumperms;

    $decode_params = php_xmlrpc_decode($xmlrpc_params);

    $postids = explode('-', $decode_params[0]);
    $quote_postids = array();
    foreach ($postids as $quote_postid)
    {
        $quote_postid = intval($quote_postid);

        // automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
        if ($quote_postid AND $postinfo = mobiquo_verify_id('post', $quote_postid, 0, 1))
        {
            $postid =& $postinfo['postid'];
            $vbulletin->GPC['threadid'] =& $postinfo['threadid'];
        }
        // automatically query $threadinfo & $foruminfo if $threadid exists
        if ($vbulletin->GPC['threadid'] AND $threadinfo = mobiquo_verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
        {
    
            $threadid =& $threadinfo['threadid'];
            $vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
            if ($forumid)
            {
                $foruminfo = fetch_foruminfo($threadinfo['forumid']);
                if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
                {
                    $codestyleid = $foruminfo['styleid'];
                }
            }
    
            if ($vbulletin->GPC['pollid'])
            {
                $pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
                $pollid =& $pollinfo['pollid'];
            }
        }
    
        //===================================================
        //($hook = vBulletinHook::fetch_hook('newreply_start')) ? eval($hook) : false;
        // ### CHECK IF ALLOWED TO POST ###
        if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
        {
            //eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
            $return = array(6,'invalid thread id');
            return return_fault($return);
        }
    
        if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
        {
            //eval(standard_error(fetch_error('forumclosed')));
            $return = array(4,'forum is closed');
            return return_fault($return);
        }
    
        if (!$threadinfo['open'])
        {
            if (!can_moderate($threadinfo['forumid'], 'canopenclose'))
            {
                //$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadid";
                //eval(standard_error(fetch_error('threadclosed')));
                $return = array(11,'this thread has been closed');
                return return_fault($return);
            }
        }
    
    
        $forumperms = fetch_permissions($foruminfo['forumid']);
    
        if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
        {
            $return = array(20,'you do not have permission to access this page.');
            return return_fault($return);
        }
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid']))
        {
            $return = array(20,'you do not have permission to access this page.');
            return return_fault($return);
        }
    
        // check if there is a forum password and if so, ensure the user has it set
        verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
    
        // *********************************************************************************
        // Tachy goes to coventry
        if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
        {
            //eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
            $return = array(4,'invalid forum id');
            return return_fault($return);
        }
    
        $quote_postids[] = $postinfo['postid'];
    }
    // fetch the quoted post title
    $post_title = htmlspecialchars_uni(vbchop(fetch_quote_title($postinfo['title'], $threadinfo['title']), $vbulletin->options['titlemaxchars']));
    $specifiedpost = 1; // the post we're replying to we explicitly picked

    if ($quote_postids)
    {
        $post_content = fetch_quotable_posts($quote_postids, $threadinfo['threadid'], $unquoted_post_count, $quoted_post_ids, 'only');

        $return_data = array(
            'post_id'       =>  new xmlrpcval(implode('-', $quoted_post_ids),'string'),
            'post_title'    =>  new xmlrpcval(mobiquo_encode($post_title),'base64'),
            'post_content'  =>  new xmlrpcval(mobiquo_encode($post_content),'base64'),
        );
        return  new xmlrpcresp(new xmlrpcval( $return_data,'struct'));
    }
}

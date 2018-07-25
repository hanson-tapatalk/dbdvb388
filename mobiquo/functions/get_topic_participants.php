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

require_once('./global.php');
require_once('./includes/functions_user.php');

function get_topic_participants_func($xmlrpc_params)
{
	global $db, $vbulletin, $permissions, $vbphrase;
	
	$params = php_xmlrpc_decode($xmlrpc_params);
	
	$threadid = intval($params[0]);
	if(empty($threadid)) return_fault(fetch_error('invalidid', $vbphrase['thread']));
	$shownum = (isset($params[1]) && (intval($params[1])>0)) ? $params[1] : 20;
	$shownum = intval($shownum);
	
	// *******************************************************************thread infor process
	$thread = mobiquo_verify_id('thread', $threadid, 1, 1);
	if(!is_array($thread)) return_fault(fetch_error('invalidid', $vbphrase['thread']));
	$threadinfo =& $thread;
	$presentthreadid = intval($thread['threadid']);
	
	// jump page if thread is actually a redirect
	$is_moved = false;
	if ($thread['open'] == 10)
	{
		if($thread['pollid'] != 0){
			$is_moved = true;
		}
		$thread = fetch_threadinfo($threadinfo['pollid']);
	}
	
	// check for visible / deleted thread
	if (((!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))) OR ($thread['isdeleted'] AND !can_moderate($thread['forumid'])))
	{
		return_fault(fetch_error('invalidid', $vbphrase['thread']));
	}
	
	// Tachy goes to coventry
	if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
	{
		return_fault(fetch_error('invalidid', $vbphrase['thread']));
	}
	
	// ******************************************************************* forum infor process
	// get forum info
	$forum = fetch_foruminfo($thread['forumid']);
	$foruminfo =& $forum;
	
	// check forum permissions
	$forumperms = fetch_permissions($thread['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		return_fault();
	}
	
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		return_fault();
	}
	
	// check if there is a forum password and if so, ensure the user has it set
	if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
	{
		return_fault('Your administrator has required a password to access this forum.');
	}
	
	// do query
	$_users = $db->query_read_slave("
			SELECT s.userid, u.username, u.email
			FROM " . TABLE_PREFIX . "subscribethread AS s
			INNER JOIN " . TABLE_PREFIX . "user AS u
			WHERE s.threadid = $presentthreadid and s.userid = u.userid LIMIT $shownum
		");
	$userlist = array();
	$mobi_api_key = $vbulletin->options['push_key'];
	if (preg_match('/[A-Z0-9]{32}/', $mobi_api_key))
    {
    	$cipher = new TT_Cipher();
		while ($user = $db->fetch_array($_users))
	    {
	        $userlist[] = new xmlrpcval(array(
	        	'user_id' => new xmlrpcval($user['userid'], 'string'),
	        	'username'=> new xmlrpcval(mobiquo_encode($user['username']), 'base64'),
	        	'icon_url'=> new xmlrpcval(mobiquo_get_user_icon($user['userid']), 'string'),
	        	'enc_email'=> new xmlrpcval(base64_encode($cipher->encrypt(trim($user['email']), $mobi_api_key)), 'string'),
	        ), 'struct');
	    }
		return new xmlrpcresp(new xmlrpcval(array(
	        'result' => new xmlrpcval(true, 'boolean'),
	        'list' => new xmlrpcval($userlist, 'array'),
	    ), 'struct'));
	}
	else return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(false, 'boolean'),
        'result_text' => new xmlrpcval("Invalid API Key", 'string'),
    ), 'struct'));
}

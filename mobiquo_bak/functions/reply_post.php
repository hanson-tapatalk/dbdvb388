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

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');


// ############################### start post reply ###############################
function reply_post_func($xmlrpc_params)
{
	global $vbulletin, $db, $xmlrpcerruser, $forumperms, $vbphrase, $threadinfo, $foruminfo;

	$decode_params = php_xmlrpc_decode($xmlrpc_params);
	$reply_threadid = intval($decode_params[1]);
	
	if (!$reply_threadid)
		return return_fault(fetch_error('invalidid', $vbphrase['thread']));
//	$reply_title = in_text_clean(mobiquo_encode($decode_params[2],'to_local')); //Don't use this any more
	$reply_message = in_text_clean(mobiquo_encode($decode_params[3],'to_local'));
	$html_content= false;

	if(isset($decode_params[6]) && $decode_params[6]){
		$html_content = true;
	}
	
	$coventry = fetch_coventry('string');
	$post_quoted = false;
	if(preg_match_all('/\[quote=(.*?);(\d+)\]/si', $reply_message, $quote_matches))
	{
		$quote_postids = $quote_matches[2];
		$valid_quote_postids = array();
		foreach($quote_postids as $post_id)
		{
			if(is_numeric($post_id) && $post_id == intval($post_id))
			{
				$valid_quote_postids[] =  $vbulletin->db->escape_string($post_id);
			} 
		}
		if(!empty($valid_quote_postids))
		{
			$sql_in_thread_postid = "SELECT postid FROM " . TABLE_PREFIX . "post 
		WHERE threadid = $reply_threadid AND postid IN (".(implode(',',$valid_quote_postids)).")";
			$in_thread_postids = array();
			$in_thread_postids_rs = $db->query_read_slave($sql_in_thread_postid);
			while ($in_thread_postid = $db->fetch_array($in_thread_postids_rs))
			{
				$in_thread_postids[] = $in_thread_postid['postid'];
			}
			$vbulletin->GPC['postid'] =  max($in_thread_postids);

			$post_quoted = true;
		}
	}
	if(!$post_quoted || $vbulletin->GPC['postid'] == 0)
	{
		$threadtemp = fetch_threadinfo($reply_threadid);
		if	($threadtemp['open'] == 10) 
		{
				$reply_threadid = $threadtemp['pollid'];
		}
		$sql_first_topic = "
		SELECT thread.firstpostid
		FROM " . TABLE_PREFIX . "thread AS thread
		WHERE thread.threadid = '$reply_threadid'
		";
	
		$first_topic = $db->query_first_slave($sql_first_topic);
		$postidbythreadid = $first_topic['firstpostid'];
	
		$vbulletin->GPC['postid'] = $postidbythreadid;
	}

	// #######################################################################
	// ######################## START MAIN SCRIPT ############################
	// #######################################################################

	// ### STANDARD INITIALIZATIONS ###
	$checked = array();
	$newpost = array();
	$postattach = array();

	// sanity checks...
	if (empty($_REQUEST['do']))
	{
		$_REQUEST['do'] = 'newreply';
	}

	$vbulletin->GPC['noquote'] = true;

	//==============================================================
	// automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
	if ($vbulletin->GPC['postid'] AND $postinfo = mobiquo_verify_id('post', $vbulletin->GPC['postid'], 0, 1))
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

	// ### GET QUOTE FEATURES (WITH MQ SUPPORT) ###
	// This section must exist before $_POST[do] == postreply because of the $newpost stuff
	$newpost['message'] = '';
	$unquoted_posts = 0;
	$multiquote_empty = '';
	$specifiedpost = 0;



	$_POST['do'] = 'postreply';


	// ############################### start post reply ###############################
	if ($_POST['do'] == 'postreply')
	{
		// Variables reused in templates
		if($decode_params[5]){
			$posthash = $decode_params[5];
		}
		$poststarttime =& $vbulletin->input->clean_gpc('p', poststarttime, TYPE_UINT);

		$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'        => TYPE_BOOL,
		'message'        => TYPE_STR,
		'quickreply'     => TYPE_BOOL,
		'fromquickreply' => TYPE_BOOL,
		'ajaxqrfailed'   => TYPE_BOOL,
		'folderid'       => TYPE_UINT,
		'emailupdate'    => TYPE_UINT,
		'title'          => TYPE_STR,
		'iconid'         => TYPE_UINT,
		'parseurl'       => TYPE_BOOL,
		'signature'      => TYPE_BOOL,
		'preview'        => TYPE_STR,
		'disablesmilies' => TYPE_BOOL,
		'username'       => TYPE_STR,
		'rating'         => TYPE_UINT,
		'stickunstick'   => TYPE_BOOL,
		'openclose'      => TYPE_BOOL,
		'ajax'           => TYPE_BOOL,
		'ajax_lastpost'  => TYPE_INT,
		'loggedinuser'   => TYPE_INT,
		'humanverify'    => TYPE_ARRAY,
		'multiquoteempty'=> TYPE_NOHTML,
		'specifiedpost'  => TYPE_BOOL
		));

		$vbulletin->GPC['message'] = $reply_message;

		$vbulletin->GPC['quickreply'] = false;
		$vbulletin->GPC['fromquickreply'] = false;
		$vbulletin->GPC['specifiedpost'] = 0;
		$vbulletin->GPC['loggedinuser'] = 1;

		//$vbulletin->GPC['ajax'] = true;


		if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
		{
			// User was logged in when writing post but isn't now. If we got this
			// far, guest posts are allowed, but they didn't enter a username so
			// they'll get an error. Force them to log back in.
			//standard_error(fetch_error('session_timed_out_login'), '', false, 'STANDARD_ERROR_LOGIN');
			$return = array(20,'User was logged in when writing post but is not now');
			return return_fault($return);
		}

		//($hook = vBulletinHook::fetch_hook('newreply_post_start')) ? eval($hook) : false;

		// ### PREP INPUT ###
		require_once(DIR . '/includes/functions_wysiwyg.php');
		
		$newpost['message'] = convert_wysiwyg_html_to_bbcode($reply_message, $foruminfo['allowhtml']);

		if ($vbulletin->GPC['ajax'])
		{
			// posting via ajax so we need to handle those %u0000 entries
			$newpost['message'] = convert_urlencoded_unicode($newpost['message']);
		}

		if ($vbulletin->GPC['quickreply'])
		{
			$originalposter = fetch_quote_username($postinfo['username'] . ";$postinfo[postid]");
			$pagetext = trim(strip_quotes($postinfo['pagetext']));

			//($hook = vBulletinHook::fetch_hook('newreply_post_quote')) ? eval($hook) : false;

			//eval('$quotemessage = "' . fetch_template('newpost_quote', 0, false) . '";');
			$newpost['message'] = trim($quotemessage) . "\n$newpost[message]";
		}

		if ($vbulletin->GPC['fromquickreply'])
		{
			// We only add notifications to threads that don't have one if the user defaults to it, do nothing else!
			if ($vbulletin->userinfo['autosubscribe'] != -1 AND !$threadinfo['issubscribed'])
			{
				$vbulletin->GPC['folderid'] = 0;
				$vbulletin->GPC['emailupdate'] = $vbulletin->userinfo['autosubscribe'];
			}
			else if ($threadinfo['issubscribed'])
			{ // Don't alter current settings
				$vbulletin->GPC['folderid'] = $threadinfo['folderid'];
				$vbulletin->GPC['emailupdate'] = $threadinfo['emailupdate'];
			}
			else
			{ // Don't don't add!
				$vbulletin->GPC['emailupdate'] = 9999;
			}
		}
		$vbulletin->GPC['title'] = fetch_quote_title($postinfo['title'], $threadinfo['title']);

		$newpost['title']          =& $vbulletin->GPC['title'];
		$newpost['iconid']         =& $vbulletin->GPC['iconid'];
		$newpost['parseurl']       = $foruminfo['allowbbcode'];
		$newpost['signature']      = $GLOBALS['tt_config']['forum_signature'];
		$newpost['preview']        =& $vbulletin->GPC['preview'];
		$newpost['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
		$newpost['rating']         =& $vbulletin->GPC['rating'];
		$newpost['username']       =& $vbulletin->GPC['username'];
		$newpost['folderid']       =& $vbulletin->GPC['folderid'];
		$newpost['quickreply']     =& $vbulletin->GPC['quickreply'];
		$newpost['poststarttime']  =& $poststarttime;
		$newpost['posthash']       =& $posthash;
		$newpost['humanverify']    =& $vbulletin->GPC['humanverify'];
		// moderation options
		$newpost['stickunstick']   =& $vbulletin->GPC['stickunstick'];
		$newpost['openclose']      =& $vbulletin->GPC['openclose'];

		$newpost['ajaxqrfailed']   = $vbulletin->GPC['ajaxqrfailed'];

		if ($vbulletin->GPC_exists['emailupdate'])
		{
			$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
		}
		else
		{
			$newpost['emailupdate'] = array_pop($array = array_keys(fetch_emailchecked($threadinfo, $vbulletin->userinfo)));
		}

		if ($vbulletin->GPC['specifiedpost'] AND $postinfo)
		{
			$postinfo['specifiedpost'] = true;
		}

		//var_dump($postinfo);

		//		$foruminfo = mobiquo_verify_id('forum', $threadinfo['threadid'], 0, 1);
		mobiquo_build_new_post('reply', $foruminfo, $threadinfo, $postinfo, $newpost, $errors);

		if (sizeof($errors) > 0)
		{
			if (defined('NOSHUTDOWNFUNC'))
			{
				exec_shut_down();
			}
			$error_string = mobiquo_encode(implode('',$errors));
			$return = array(18,$error_string);

			return return_fault($return);
		}
		else
		{
			if ($newpost['postid']==0){
				if (defined('NOSHUTDOWNFUNC'))
				{
					exec_shut_down();
				}
				$return = array(19,'duplicate create/reply thread error, or the system restrict creating multiple thread within short time-frame');
				return return_fault($return);
			}else{
				$threadinfo =	fetch_threadinfo($reply_threadid ); // need the forumread variable from this
					
				mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
				$new_post = get_post_from_id(	$newpost['postid'],$html_content);
				if (defined('NOSHUTDOWNFUNC'))
				{
					exec_shut_down();
				}
				if (!$newpost['visible'] and !can_moderate($foruminfo['forumid'], 'canmoderateposts')){
					$new_post['stat'] = new xmlrpcval(1,'int');
				}
				$new_post['result'] = new xmlrpcval(true,'boolean');
				return new xmlrpcresp(new xmlrpcval($new_post,'struct'));
			}
		}
	}
}

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
define('THIS_SCRIPT', 'newthread');
define('CSRF_PROTECTION', false);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'threadmanage',
	'postbit',
	'posting',
	'prefix'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'ranks',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'newpost_attachment',
	'newpost_attachmentbit',
	'newthread',
	'humanverify',
	'optgroup',
	'postbit_attachment',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_bigthree.php');

// ### STANDARD INITIALIZATIONS ###
function new_topic_func($xmlrpc_params)
{
	$checked = array();
	$newpost = array();
	$postattach = array();



	global $db;
	global $vbulletin, $vbphrase, $forumperms, $foruminfo;

	global $xmlrpcerruser, $newpost;
	$params = php_xmlrpc_decode($xmlrpc_params);

	$forum_id =  $params[0];
	// $forum_id =  3;
	$subject=    mobiquo_encode($params[1],'to_local');
	//  $subject=   'mobiquo test';

	$text_body = in_text_clean(mobiquo_encode($params[2],'to_local'));

	//  $text_body = 'mobiquo test';
	$_POST['do'] = 'postthread';
	$foruminfo = mobiquo_verify_id('forum', $forum_id, 1, 1);
	// get decent textarea size for user's browser


	// sanity checks...
	if (empty($_REQUEST['do']))
	{
		$_REQUEST['do'] = 'newthread';
	}

	//($hook = vBulletinHook::fetch_hook('newthread_start')) ? eval($hook) : false;
	if(!is_array($foruminfo)){
		return $foruminfo;
	}
	if (!$foruminfo['forumid'])
	{
		$return = array(4,'invalid forum id');
		return return_fault($return);
	}

	if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
	{
		$return = array(4,'forum closed');
		return return_fault($return);
	}

	$forumperms = fetch_permissions($forum_id);

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']))
	{
		$return = array(20,'security error (user may not have permission to access this feature)');
		return return_fault($return);
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$show['tag_option'] = ($vbulletin->options['threadtagging'] AND ($forumperms & $vbulletin->bf_ugp_forumpermissions['cantagown']));

	// ############################### start post thread ###############################
	if ($_POST['do'] == 'postthread')
	{
		// Variables reused in templates
		if($params[5]){
			$posthash = $params[5];
			$posthash = $vbulletin->input->clean($posthash, TYPE_NOHTML);
	    }
		if($params[3]){
			$prefix_id= $params[3];
		}

		$poststarttime = $vbulletin->input->clean_gpc('p', 'poststarttime', TYPE_UINT);

		$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'         => TYPE_BOOL,
		'preview'         => TYPE_STR,
		'message'         => TYPE_STR,
		'subject'         => TYPE_STR,
		'iconid'          => TYPE_UINT,
		'rating'          => TYPE_UINT,
		'prefixid'        => TYPE_NOHTML,
		'taglist'         => TYPE_NOHTML,

		'postpoll'        => TYPE_BOOL,
		'polloptions'     => TYPE_UINT,

		'signature'       => TYPE_BOOL,
		'disablesmilies'  => TYPE_BOOL,
		'parseurl'        => TYPE_BOOL,
		'folderid'        => TYPE_UINT,
		'emailupdate'     => TYPE_UINT,
		'stickunstick'    => TYPE_BOOL,
		'openclose'       => TYPE_BOOL,

		'username'        => TYPE_STR,
		'loggedinuser'    => TYPE_INT,

		'humanverify'     => TYPE_ARRAY,

		'podcasturl'      => TYPE_STR,
		'podcastsize'     => TYPE_UINT,
		'podcastexplicit' => TYPE_BOOL,
		'podcastkeywords' => TYPE_STR,
		'podcastsubtitle' => TYPE_STR,
		'podcastauthor'   => TYPE_STR,
		));

		if ($vbulletin->GPC['loggedinuser'] != 0 AND $vbulletin->userinfo['userid'] == 0)
		{
			// User was logged in when writing post but isn't now. If we got this
			// far, guest posts are allowed, but they didn't enter a username so
			// they'll get an error. Force them to log back in.
			//standard_error(fetch_error('session_timed_out_login'), '', false, 'STANDARD_ERROR_LOGIN');
			$return = array(20,'User was logged in when writing post but is not now');
			return return_fault($return);
		}

		//($hook = vBulletinHook::fetch_hook('newthread_post_start')) ? eval($hook) : false;

		//	if ($vbulletin->GPC['wysiwyg'])
		//	{
		require_once(DIR . '/includes/functions_wysiwyg.php');

		$newpost['message'] = convert_wysiwyg_html_to_bbcode($text_body, $foruminfo['allowhtml']);

		//	}
		//	else
		//	{
		//		$newpost['message'] = $text_body;
		//	}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']))
		{
			$vbulletin->GPC['postpoll'] = false;
		}

		$newpost['title'] =$subject;
		$newpost['iconid'] =& $vbulletin->GPC['iconid'];
		require_once(DIR . '/includes/functions_prefix.php');

		if (!function_exists('can_use_prefix') || can_use_prefix($prefix_id))
		{
			$newpost['prefixid'] =& $prefix_id;
		}
		if ($show['tag_option'])
		{
			$newpost['taglist'] =& $vbulletin->GPC['taglist'];
		}
		$newpost['parseurl']        = $foruminfo['allowbbcode'];
		$newpost['signature']       = $GLOBALS['tt_config']['forum_signature'];
		$newpost['preview']         =& $vbulletin->GPC['preview'];
		$newpost['disablesmilies']  =& $vbulletin->GPC['disablesmilies'];
		$newpost['rating']          =& $vbulletin->GPC['rating'];
		$newpost['username']        =& $vbulletin->GPC['username'];
		$newpost['postpoll']        =& $vbulletin->GPC['postpoll'];
		$newpost['polloptions']     =& $vbulletin->GPC['polloptions'];
		$newpost['folderid']        =& $vbulletin->GPC['folderid'];
		$newpost['humanverify']     =& $vbulletin->GPC['humanverify'];
		$newpost['poststarttime']   = $poststarttime;
		$newpost['posthash']        = $posthash;
		// moderation options
		$newpost['stickunstick']    =& $vbulletin->GPC['stickunstick'];
		$newpost['openclose']       =& $vbulletin->GPC['openclose'];
		$newpost['podcasturl']      =& $vbulletin->GPC['podcasturl'];
		$newpost['podcastsize']     =& $vbulletin->GPC['podcastsize'];
		$newpost['podcastexplicit'] =& $vbulletin->GPC['podcastexplicit'];
		$newpost['podcastkeywords'] =& $vbulletin->GPC['podcastkeywords'];
		$newpost['podcastsubtitle'] =& $vbulletin->GPC['podcastsubtitle'];
		$newpost['podcastauthor']   =& $vbulletin->GPC['podcastauthor'];


		if ($vbulletin->GPC_exists['emailupdate'])
		{
			$newpost['emailupdate'] =& $vbulletin->GPC['emailupdate'];
		}
		else
		{
            $array = array_keys(fetch_emailchecked(array(), $vbulletin->userinfo));
			$newpost['emailupdate'] = array_pop($array);
		}

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			$newpost['emailupdate'] = 0;
		}

		$foruminfo = mobiquo_verify_id('forum', $forum_id, 0, 1);

		mobiquo_build_new_post('thread', $foruminfo, array(), array(), $newpost, $errors);

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
			if ($newpost['threadid']==0){
				if (defined('NOSHUTDOWNFUNC'))
				{
					exec_shut_down();
				}
				$return = array(19,'duplicate create/reply thread error, or the system restrict creating multiple thread within short time-frame');
				return return_fault($return);
			}else{
				//$threadinfo = mobiquo_verify_id('thread', $newpost['threadid'], 0, 1);
				$threadinfo =	fetch_threadinfo($newpost['threadid']); // need the forumread variable from this

				mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
				if (defined('NOSHUTDOWNFUNC'))
				{
					exec_shut_down();
				}
				$post_stat = 0;
				if (!$newpost['visible']){
					$post_stat = 1;
				}
				return new xmlrpcresp(
				new xmlrpcval(
				array(
                                        'topic_id' => new xmlrpcval($newpost['threadid'],'string'),
                    					'stat' =>  new xmlrpcval($post_stat,'int'),
                                        'result' => new xmlrpcval(true,'boolean'),
				),
                     'struct'));
			}
		} // end if
	}



}
/*======================================================================*\
 || ####################################################################

 || # CVS: $RCSfile$ - $Revision: 26399 $
 || ####################################################################
 \*======================================================================*/
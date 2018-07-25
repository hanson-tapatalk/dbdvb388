<?php
defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');
    

function activate_account_func($xmlrpc_params)
{
    global $vbulletin, $db;

    $params = php_xmlrpc_decode($xmlrpc_params);

    $_POST['email'] = mobiquo_encode($params[0], 'to_local');
    $_POST['token'] = mobiquo_encode($params[1], 'to_local');
    $_POST['code']  = mobiquo_encode($params[2], 'to_local');
    
    $tapatalk_directory = empty($vbulletin->options['tapatalk_directory']) ? 'mobiquo' : $vbulletin->options['tapatalk_directory'];
    include_once('./'.$tapatalk_directory.'/include/function_push.php');
    if(!empty($_POST['token']))
        $email_response = getEmailFromScription($_POST['token'], $_POST['code'], $vbulletin->options['push_key']);

    $response_verified = $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']);
    if(!$response_verified)
    {
        if (isset($email_response['inactive']) && $email_response['inactive']) return error_status(2);
    }
    if(!$response_verified)
    {
        return error_status(4);
    }
    if(strtolower($email_response['email'])!==strtolower($_POST['email']))
    {
        return error_status(3);
    }
    $user = get_user_by_NameorEmail($_POST['email']);
    if(empty($user))
    {
        return error_status(1);
    }

    $vbulletin->input->clean_array_gpc('r', array(
    	'u'		=> TYPE_UINT,
    ));
    
    $vbulletin->GPC['u'] = $user['userid'];
    
    $userinfo = verify_id('user', $vbulletin->GPC['u'], 1, 1);
    
    if ($userinfo['usergroupid'] == 3)
    {
    	// check valid activation id
    	$user = $db->query_first("
    		SELECT activationid, usergroupid, emailchange
    		FROM " . TABLE_PREFIX . "useractivation
    		WHERE userid = $userinfo[userid]
    			AND type = 0
    	");
    	
    	// delete activationid
    	$db->query_write("DELETE FROM " . TABLE_PREFIX . "useractivation WHERE userid=$userinfo[userid] AND type=0");
    
    	if (empty($user['usergroupid']))
    	{
    		$user['usergroupid'] = 2; // sanity check
    	}
    
    	// ### DO THE UG/TITLE UPDATE ###
    
    	$getusergroupid = iif($userinfo['displaygroupid'] != $userinfo['usergroupid'], $userinfo['displaygroupid'], $user['usergroupid']);
    
    	$user_usergroup =& $vbulletin->usergroupcache["$user[usergroupid]"];
    	$display_usergroup =& $vbulletin->usergroupcache["$getusergroupid"];
    
    	// init user data manager
    	$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
    	$userdata->set_existing($userinfo);
    	$userdata->set('usergroupid', $user['usergroupid']);
    	$userdata->set_usertitle(
    		$user['customtitle'] ? $user['usertitle'] : '',
    		false,
    		$display_usergroup,
    		($user_usergroup['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canusecustomtitle']) ? true : false,
    		($user_usergroup['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cancontrolpanel']) ? true : false
    	);
    
    	require_once(DIR . '/includes/functions_ranks.php');
    	if ($user['userid'] == $vbulletin->userinfo['userid'])
    	{
    		$vbulletin->userinfo['usergroupid'] = $user['usergroupid'];
    		$vbulletin->userinfo['displaygroupid'] = $user['usergroupid'];
    	}
    
    	// see 3.6.x bug #176
    	//$userinfo['usergroupid'] = $user['usergroupid'];
    	$userdata->save();
    }
    else
    {
    	if ($userinfo['usergroupid'] == 4)
    	{
    		// In Moderation Queue
    		$result_text = mobiquo_encode(fetch_phrase('activate_moderation', 'error'));
    		return error_status(5,$result_text);
        }
    	else
    	{
    		// Already activated
    		$result_text = mobiquo_encode(fetch_phrase('activate_wrongusergroup', 'error'));
    		return error_status(5,$result_text);
    	}
    }
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'status'        => new xmlrpcval('', 'string'),
        'result_text'   => new xmlrpcval('', 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);

}

function error_status($status = 0, $result_text = '')
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'status'        => new xmlrpcval($status, 'string'),
        'result_text'   => new xmlrpcval($result_text, 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}
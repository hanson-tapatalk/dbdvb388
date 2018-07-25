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
require_once(CWD1.'/include/functions_logout_user.php');


$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require_once('./global.php');
require_once(DIR . '/includes/functions_login.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');


function tt_login_func($params) {
    return mobiquo_login($params);
}

function login_mod_func($params) {
    return mobiquo_login($params, 'modcplogin');
}

function mobiquo_login($params,$mode = null)
{
    global $vbulletin, $tt_config, $vbphrase;
    $result_text = "";
    $code = '';

    if(isset($_SERVER['HTTP_X_TT']))
    {
        $code = trim($_SERVER['HTTP_X_TT']);
    }
    else if(isset($_COOKIE['X-TT']))
    {
        $code = trim($_COOKIE['X-TT']);
    }

    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'login');
    if($connection->success && $response !== true)
    {
        if ($response === false && empty($connection->errors)){
            $result_text = 'Unauthorized app detected.';
        }
        else
        {
            $result_text = 'The forum failed to connect to Tapatalk servers and some features might not work properly. Please contact your site admin to resolve this issue.';
        }
    }
    $decode_params = php_xmlrpc_decode($params);
    $username = mobiquo_encode($decode_params[0], 'to_local');
    $password = mobiquo_encode($decode_params[1], 'to_local');

    if ($vbulletin->options['attachlimit'] === 0)
        $vbulletin->options['attachlimit'] = 20;

    $vbulletin->GPC['logintype'] = $mode;
    if ($username && $password)
    {
        $return = array();
        $vbulletin->GPC['username'] = $username;
        if(strlen($password) == 32){
            $vbulletin->GPC['md5password'] = $password;
            $vbulletin->GPC['md5password_utf'] = $password;
        } else {
            $vbulletin->GPC['password'] = $password;
        }

        $strikes = mobiquo_verify_strike_status($vbulletin->GPC['username']);
        if ($vbulletin->GPC['username'] == '')
        {
            $return = array( 7, 'invalid user name/id.');
            return return_fault($return);
        }

        if(!$strikes)
        {
            $return_text= "Wrong username or password. You have used up your failed login quota! Please wait 15 minutes before trying again.";
            $return =new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(mobiquo_encode($return_text), 'base64')
            ), 'struct');

            return new xmlrpcresp($return);
        }
        // make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
        $original_userinfo = $vbulletin->userinfo;

        if (!verify_authentication($vbulletin->GPC['username'], $vbulletin->GPC['password'], $vbulletin->GPC['md5password'], $vbulletin->GPC['md5password_utf'], TRUE, true))
        {
            exec_strike_user($vbulletin->userinfo['username']);
            if ($vbulletin->options['usestrikesystem'])
            {
                $return_text= sprintf("You have entered an invalid username or password. Please enter the correct details and try again. Don't forget that the password is case sensitive.
You have used %3s out of 5 login attempts. After all 5 have been used, you will be unable to login for 15 minutes",$strikes['strikes'] + 1);
            }
            else
            {
                $return_text= "You have entered an invalid username or password. Please press the back button, enter the correct details and try again. Don't forget that the password is case sensitive.";
            }

            $user_exist = get_userid_by_name($vbulletin->GPC['username']);

            $return = array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(mobiquo_encode($return_text), 'base64')
            );

            if(!$user_exist)
                $return['status'] = new xmlrpcval(2, 'string');

            return new xmlrpcresp(new xmlrpcval($return, 'struct'));
        } else {

            exec_unstrike_user($vbulletin->GPC['username']);


            $member_groups = preg_split("/,/",$vbulletin->userinfo['membergroupids']);
            $group_block = false;

            if(trim($tt_config['allowed_usergroup']) != "")
            {
                $group_block = true;
                $support_group = explode(',', $tt_config['allowed_usergroup']);

                foreach($support_group as $support_group_id)
                {
                    $support_group_id = trim($support_group_id);
                    if($vbulletin->userinfo['usergroupid'] == $support_group_id || in_array($support_group_id,$member_groups)) {
                        $group_block = false;
                    }
                }
            }

            $return_group_ids = array();
            foreach($member_groups AS $id)
            {
                if($id) {
                    array_push($return_group_ids, new xmlrpcval($id, 'string'));
                }
            }

            array_push($return_group_ids, new xmlrpcval($vbulletin->userinfo['usergroupid'], 'string'));

            if($group_block){
                $return_text = 'The usergroup you belong to does not have permission to login. Please contact your administrator. ';
                $return = new xmlrpcresp(new xmlrpcval(array(
                    'result'        => new xmlrpcval(false, 'boolean'),
                    'result_text'   => new xmlrpcval(mobiquo_encode($return_text), 'base64'),
                ), 'struct'));

            } else {
                if (!empty($vbulletin->session->userinfo) && $vbulletin->session->userinfo['userid'] == 0 ) $vbulletin->session->userinfo = false;
                process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);
                $vbulletin->session->save();
                vbsetcookie('userid', $vbulletin->userinfo['userid'], false, true, true);
                vbsetcookie('password', md5($vbulletin->userinfo['password'] . COOKIE_SALT), false, true, true);
                $permissions = cache_permissions($vbulletin->userinfo);
                $pmcount = $vbulletin->db->query_first("
                    SELECT
                        COUNT(pmid) AS pmtotal
                    FROM " . TABLE_PREFIX . "pm AS pm
                    WHERE pm.userid = '" . $vbulletin->userinfo['userid'] . "'
                ");

                $pmcount['pmtotal'] = intval($pmcount['pmtotal']);
                $show['pmmainlink'] = ($vbulletin->options['enablepms'] AND ($vbulletin->userinfo['permissions']['pmquota'] OR $pmcount['pmtotal']));
                $show['pmsendlink'] = ($vbulletin->userinfo['permissions']['pmquota']);
                if (!($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])){
                    if (!($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
                    {
                		$reason = $vbulletin->db->query_first_slave("
                			SELECT reason, liftdate
                			FROM " . TABLE_PREFIX . "userban
                			WHERE userid = " . $vbulletin->userinfo['userid']
                		);

                		// Check for a date or a perm ban
                		if ($reason['liftdate'])
                		{
                			$date = vbdate($vbulletin->options['dateformat'] . ', ' . $vbulletin->options['timeformat'], $reason['liftdate']);
                		}
                		else
                		{
                			$date = $vbphrase['never'];
                		}

                		if (!$reason['reason'])
                		{
                			$reason['reason'] = fetch_phrase('no_reason_specified', 'error');
                		}
                        $result_text = mobiquo_encode(fetch_error('nopermission_banned', $reason['reason'], $date));
                    } else {
                        $result_text = "You do not have permission to access this forum.";
                    }
                }

                $mobiquo_userinfo = mobiquo_verify_id('user', $vbulletin->userinfo['userid'], 0, 1, $fetch_userinfo_options);

                if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['passwordexpires'])
                {
                    $passworddaysold = floor((TIMENOW - $mobiquo_userinfo['passworddate']) / 86400);

                    if ($passworddaysold >= $vbulletin->userinfo['permissions']['passwordexpires'])
                    {
                        $result_text = "Your password is ".$passworddaysold." days old, and has therefore expired.";
                    }
                }

                if (isset($decode_params[2]) && $decode_params[2])
                {
                    return new xmlrpcresp(new xmlrpcval(array(
                        'result'        => new xmlrpcval(true, 'boolean'),
                        'result_text'   => new xmlrpcval($result_text, 'base64'),
                    ), 'struct'));
                }

                $max_png_size = $vbulletin->userinfo['attachmentpermissions']['png']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['png']['size'] : 0;
                $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpeg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpeg']['size'] : 0;
                if(empty($max_jpg_size)) $max_jpg_size = $vbulletin->userinfo['attachmentpermissions']['jpg']['permissions'] ? $vbulletin->userinfo['attachmentpermissions']['jpg']['size'] : 0;
                $max_attachment = $vbulletin->options['attachlimit'] ? $vbulletin->options['attachlimit'] : 100;
                $can_whosonline = $vbulletin->options['WOLenable'] && $permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline'];
                $can_search = $vbulletin->options['enablesearches'] && $permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['cansearch'];
                $can_upload_avatar = $permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'];

                //fake push status
                $push_status = array();
                $supported_types = array(
                    'pm'       => 'pm',
                    'subscribe'=> 'sub',
                    'liked'    => 'like',
                    'quote'    => 'quote',
                    'newtopic' => 'newtopic',
                    'tag'      => 'tag',
                );
                foreach($supported_types as $support_type)
                    $push_status[] = new xmlrpcval(array(
                        'name'  => new xmlrpcval($support_type, 'string'),
                        'value' => new xmlrpcval(true, 'boolean')
                    ), 'struct');

                update_push();

                $ignore_users = get_ignore_ids($vbulletin->userinfo['userid']);
                $ignore_user_ids = '';
                if(!empty($ignore_users))
                	$ignore_user_ids = implode(',', $ignore_users);
                $user_type = get_usertype_by_item($vbulletin->userinfo['userid']);
                $return_array = array(
                    'result'            => new xmlrpcval(true, 'boolean'),
                    'result_text'       => new xmlrpcval($result_text, 'base64'),
                    'user_id'           => new xmlrpcval($vbulletin->userinfo['userid'], 'string'),
                    'login_name'        => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                    'username'          => new xmlrpcval(mobiquo_encode($vbulletin->userinfo['username']), 'base64'),
                    'user_type'         => new xmlrpcval($user_type, 'base64'),
                    'email'             => new xmlrpcval(mobiquo_encode($mobiquo_userinfo['email']), 'base64'),
                    'usergroup_id'      => new xmlrpcval($return_group_ids, 'array'),
                    'icon_url'          => new xmlrpcval(mobiquo_get_user_icon($vbulletin->userinfo['userid']), 'string'),
                    'ignored_uids'      => new xmlrpcval($ignore_user_ids, 'string'),
                    'post_count'        => new xmlrpcval($mobiquo_userinfo['posts'], 'int'),
                    'can_pm'            => new xmlrpcval($show['pmmainlink'], 'boolean'),
                    'can_send_pm'       => new xmlrpcval(($show['pmmainlink'] AND $show['pmsendlink']), 'boolean'),
                    'can_moderate'      => new xmlrpcval(can_moderate(), 'boolean'),
                    'can_search'        => new xmlrpcval($can_search, 'boolean'),
                    'can_whosonline'    => new xmlrpcval($can_whosonline, 'boolean'),
                    'can_upload_avatar' => new xmlrpcval($can_upload_avatar, 'boolean'),
                    'max_attachment'    => new xmlrpcval($max_attachment, 'int'),
                    'max_png_size'      => new xmlrpcval(intval($max_png_size), 'int'),
                    'max_jpg_size'      => new xmlrpcval(intval($max_jpg_size), 'int'),
                    'max_avatar_size'   => new xmlrpcval($vbulletin->userinfo['permissions']['avatarmaxsize'], 'int'),
                    'max_avatar_width'  => new xmlrpcval($vbulletin->userinfo['permissions']['avatarmaxwidth'], 'int'),
                    'max_avatar_height' => new xmlrpcval($vbulletin->userinfo['permissions']['avatarmaxheight'], 'int'),
                );
                if($user_type != 'admin' && $user_type != 'mod')
                {
                    $return_array['post_countdown'] =  new xmlrpcval($vbulletin->options['floodchecktime'], 'int');
                }
                if(isset($vbulletin->userinfo['attachmentextensions']) && !empty($vbulletin->userinfo['attachmentextensions']))
                {
                    $extensions = $vbulletin->userinfo['attachmentextensions'];
                    $extensions = str_replace(' ',',', $extensions);
                    $return_array['allowed_extensions'] = new xmlrpcval($extensions, 'string');
                }

                if(isset($push_status) && !empty($push_status))
                    $return_array['push_type'] =  new xmlrpcval($push_status, 'array');
                $return = new xmlrpcresp(new xmlrpcval($return_array, 'struct'));
            }
        }
    }
    else
    {
        $return =new xmlrpcval(array(
            'result' => new xmlrpcval(false, 'boolean'),
        ), 'struct');
    }

    return $return;
}

function getStarndardNameByTableKey($key)
{
    $starndard_key_map = array(
        'conv'     => 'conv',
        'pm'       => 'pm',
        'subscribe'=> 'sub',
        'liked'    => 'like',
        'quote'    => 'quote',
        'newtopic' => 'newtopic',
        'tag'      => 'tag',
    );
    return isset($starndard_key_map[$key])? $starndard_key_map[$key]: '';
}
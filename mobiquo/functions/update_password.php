<?php
defined('IN_MOBIQUO') or exit;

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function update_password_func($xmlrpc_params)
{
    global $vbulletin, $db;

    $params = php_xmlrpc_decode($xmlrpc_params);

    if (!isset($params[2]) && empty($vbulletin->userinfo['userid']))
    {
        print_no_permission();
    }

    if($vbulletin->userinfo['usergroupid'] == 6)
        return return_fault('Sorry, you are administrator of this forum,please try to get password via browser!');

    if(isset($params[2]))
    {
        include(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');

        //Check if push key exist

        $email_response = getEmailFromScription($params[1], $params[2], $vbulletin->options['push_key']);

        $response_verified = $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']);
        if(!$response_verified)
        {
            if(!isset($vbulletin->options['push_key']) || empty($vbulletin->options['push_key']))
                return return_fault('Sorry, this community has not yet full configured to work with Tapatalk, this feature has been disabled.');
            else if(empty($email_response))
                return return_fault('Failed to connect to tapatalk server, please try again later.');
            else
                return return_fault(isset($email_response['result_text'])? $email_response['result_text'] : 'Tapatalk ID session expired, please re-login Tapatalk ID and try again, if the problem persist please tell us.');
        }
    
        $tapatalkid_email = isset($email_response['result']) && $email_response['result'] && isset($email_response['email']) && !empty($email_response['email'])? $email_response['email'] : '' ;

        if(empty($tapatalkid_email))
            return return_fault($email_response['result_text']);

        $userid = 0;
        //fetch userid
        $query = "SELECT userid
              FROM " . TABLE_PREFIX . "user
              WHERE email = '" . $db->escape_string($tapatalkid_email) . "'" ;
    
        require_once( DIR . '/includes/functions_bigthree.php');
        $coventry = fetch_coventry();
        $users = $db->query_read_slave($query);
    
        if ($db->num_rows($users))
        {
            $user = $db->fetch_array($users);
            $userid = (in_array($user['userid'], $coventry) AND !can_moderate()) ? 0 : $user['userid'];
        }
    
        if(!$userid)
            return return_fault('No email user matches!');

        // init data manager
        $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
        $userdata->adminoverride = true;
        $userinfo = fetch_userinfo($userid);
        if (!$userinfo)
        {
            return return_fault('invalid user specified');
        }
        $userinfo['posts'] = intval($vbulletin->GPC['user']['posts']);
        $userdata->set_existing($userinfo);
        $_POST['password'] = $params[0];
        $_POST['password_md5'] = md5($_POST['password']);
        $vbulletin->input->clean_array_gpc('p', array(
            'password'        => TYPE_STR,
            'password_md5'    => TYPE_STR,));
        $vbulletin->GPC['password'] = ($vbulletin->GPC['password_md5'] ? $vbulletin->GPC['password_md5'] : $vbulletin->GPC['password']);
        if (!empty($vbulletin->GPC['password']))
        {
            $userdata->set('password', $vbulletin->GPC['password']);
        }
        $userid = $userdata->save();
        return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval($userid != 0, 'boolean'),
            'result_text'       => new xmlrpcval('', 'base64'),
        ), 'struct'));
    }
    
    $_POST['emailconfirm'] = $_POST['email'] = $vbulletin->userinfo['email'];
    $_POST['newpasswordconfirm'] =  $_POST['newpassword'] = $params[1];
    $_POST['newpasswordconfirm_md5'] = $_POST['newpassword_md5'] = md5($_POST['newpassword']);
    $_POST['currentpassword'] = $params[0];
    $_POST['currentpassword_md5'] = md5($_POST['currentpassword']);

    $vbulletin->input->clean_array_gpc('p', array(
        'currentpassword'        => TYPE_STR,
        'currentpassword_md5'    => TYPE_STR,
        'newpassword'            => TYPE_STR,
        'newpasswordconfirm'     => TYPE_STR,
        'newpassword_md5'        => TYPE_STR,
        'newpasswordconfirm_md5' => TYPE_STR,
        'email'                  => TYPE_STR,
        'emailconfirm'           => TYPE_STR
    ));

    // instanciate the data manager class
    $userdata =& datamanager_init('user', $vbulletin, ERRTYPE_STANDARD);
    $userdata->set_existing($vbulletin->userinfo);

    // if not Facebook user, validate old password(if email validated by tapatalk, don't check current pwd)
    if (!isset($tapatalkid_email) && $userdata->hash_password($userdata->verify_md5($vbulletin->GPC['currentpassword_md5']) ? $vbulletin->GPC['currentpassword_md5'] : $vbulletin->GPC['currentpassword'], $vbulletin->userinfo['salt']) != $vbulletin->userinfo['password'])
    {
        eval(standard_error(fetch_error('badpassword', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
    }

    // update password
    if (!empty($vbulletin->GPC['newpassword']) OR !empty($vbulletin->GPC['newpassword_md5']))
    {
        // are we using javascript-hashed password strings?
        if ($userdata->verify_md5($vbulletin->GPC['newpassword_md5']))
        {
            $vbulletin->GPC['newpassword'] =& $vbulletin->GPC['newpassword_md5'];
            $vbulletin->GPC['newpasswordconfirm'] =& $vbulletin->GPC['newpasswordconfirm_md5'];
        }
        else
        {
            $vbulletin->GPC['newpassword'] =& md5($vbulletin->GPC['newpassword']);
            $vbulletin->GPC['newpasswordconfirm'] =& md5($vbulletin->GPC['newpasswordconfirm']);
        }

        // check that new passwords match
        if ($vbulletin->GPC['newpassword'] != $vbulletin->GPC['newpasswordconfirm'])
        {
            eval(standard_error(fetch_error('passwordmismatch')));
        }

        // check to see if the new password is invalid due to previous use
        if ($userdata->check_password_history($userdata->hash_password($vbulletin->GPC['newpassword'], $vbulletin->userinfo['salt']), $permissions['passwordhistory']))
        {
            eval(standard_error(fetch_error('passwordhistory', $permissions['passwordhistory'])));
        }

        // everything is good - send the singly-hashed MD5 to the password update routine
        $userdata->set('password', $vbulletin->GPC['newpassword']);

        // Update cookie if we have one
        $vbulletin->input->clean_array_gpc('c', array(
            COOKIE_PREFIX . 'password' => TYPE_STR,
            COOKIE_PREFIX . 'userid'   => TYPE_UINT)
        );

        if (md5($vbulletin->userinfo['password'] . COOKIE_SALT) == $vbulletin->GPC[COOKIE_PREFIX . 'password'] AND
            $vbulletin->GPC[COOKIE_PREFIX . 'userid'] == $vbulletin->userinfo['userid']
        )
        {
            vbsetcookie('password', md5(md5($vbulletin->GPC['newpassword'] . $vbulletin->userinfo['salt']) . COOKIE_SALT), true, true, true);
        }
        $activate = false;
    }

    $userdata->verify_useremail($vbulletin->userinfo['email']);
 
    // save the data
    $userdata->save();

    return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval(isset($res) ? $res : true, 'boolean'),
            'result_text'       => new xmlrpcval($activate ? ($res ? '' : 'An email for active account change send failed') : '', 'base64'),
        ), 'struct'));
}
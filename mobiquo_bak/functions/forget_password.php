<?php
defined('IN_MOBIQUO') or exit;

require_once('./global.php');

function forget_password_func($xmlrpc_params)
{
    global $vbulletin, $db;

    $verified = false;
    $userid = 0;
    $result_text = '';

    $params = php_xmlrpc_decode($xmlrpc_params);
    $user_name = mobiquo_encode($params[0], 'to_local');
    $username = htmlspecialchars_uni($user_name);

    $query = "SELECT userid,email,languageid,usergroupid,membergroupids
          FROM " . TABLE_PREFIX . "user
          WHERE username = '" . $db->escape_string($username) . "'" ;

    require_once( DIR . '/includes/functions_bigthree.php');
    $coventry = fetch_coventry();
    $users = $db->query_read_slave($query);

    if ($db->num_rows($users))
    {
        $user = $db->fetch_array($users);
        $userid = (in_array($user['userid'], $coventry) AND !can_moderate()) ? 0 : $user['userid'];
    }

    if(!$userid)
        return return_fault('Username doesn\'t exist!');
    if( (!isset($user['email']) || empty($user['email'])))
        return return_fault('This user has no email, cannot process this action!');
    if($user['usergroupid'] == 6)
        return return_fault('Administrator cannot reset password via Tapatalk!');
    if(isset($user['membergroupids']) && !empty($user['membergroupids']))
    {
        $membergroupids = explode(',', $user['membergroupids']);
        if(in_array(6, $membergroupids))
            return return_fault('Administrator cannot reset password via Tapatalk!');
    }

    if(isset($params[1]))
    {
        include(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');
        $email_response = getEmailFromScription($params[1], $params[2], $vbulletin->options['push_key']);
        $verified = isset($email_response['result']) && $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']) && ($email_response['email'] == $user['email']);
    }

    if(!$verified)
    {
        require_once(DIR . '/includes/functions_user.php');
        $user['activationid'] = build_user_activation_id($user['userid'], 2, 1);
        eval(fetch_email_phrases('lostpw', $user['languageid']));
        $res = vbmail($user['email'], $subject, $message, true);
        $result_text = 'An reset password email has been sent, please check your email to continue.';
    }
    return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval(isset($res) ? $res : true, 'boolean'),
            'result_text'       => new xmlrpcval($result_text, 'base64'),
            'verified'          => new xmlrpcval($verified, 'boolean'),
        ), 'struct'));
}
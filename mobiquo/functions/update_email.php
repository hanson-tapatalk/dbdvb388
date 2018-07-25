<?php
defined('IN_MOBIQUO') or exit;

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

function update_email_func($xmlrpc_params)
{
    global $vbulletin, $permissions, $db;

    $params = php_xmlrpc_decode($xmlrpc_params);

    if (empty($vbulletin->userinfo['userid']))
    {
        print_no_permission();
    }

    if($vbulletin->userinfo['usergroupid'] == 1)
        return return_fault('Sorry, you are administrator of this forum,please try to get password via browser!');

    if(!isset($params[1]) && empty($params[1]))
        return return_fault('email cannot be empty!');

    $_POST['emailconfirm'] = $_POST['email'] = $params[1];
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

    // if this is a Facebook only user, we will only use this form to add a password
    // so we will ignore old password, email, and set the user logintype to be a vB user
    if (function_exists('is_facebookenabled') && is_facebookenabled() AND $vbulletin->userinfo['logintype'] == 'fb')
    {
        $userdata->set('logintype', 'vb');
        // if a new email was not submitted, use whats already in the DB
        if (!$vbulletin->GPC_exists['email'])
        {
            $vbulletin->GPC['email'] = $vbulletin->GPC['emailconfirm'] = $vbulletin->userinfo['email'];
        }
    }

    // if not Facebook user, validate old password(if email validated by tapatalk, don't check current pwd)
    else if ($userdata->hash_password($userdata->verify_md5($vbulletin->GPC['currentpassword_md5']) ? $vbulletin->GPC['currentpassword_md5'] : $vbulletin->GPC['currentpassword'], $vbulletin->userinfo['salt']) != $vbulletin->userinfo['password'])
    {
        eval(standard_error(fetch_error('badpassword', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
    }

    // update email only if user is not banned (see bug 2142) and email is changed
    // also, do not update
    if ($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] AND ($vbulletin->GPC['email'] != $vbulletin->userinfo['email'] OR $vbulletin->GPC['emailconfirm'] != $vbulletin->userinfo['email']))
    {
        // check that new email addresses match
        if ($vbulletin->GPC['email'] != $vbulletin->GPC['emailconfirm'])
        {
            eval(standard_error(fetch_error('emailmismatch')));
        }

        // set the email field to be updated
        $userdata->set('email', $vbulletin->GPC['email']);

        // generate an activation ID if required
        if ($vbulletin->options['verifyemail'] AND !can_moderate())
        {
            $userdata->set('usergroupid', 3);
            $userdata->set_info('override_usergroupid', true);

            $activate = true;

            // wait lets check if we have an entry first!
            $activation_exists = $db->query_first("
                SELECT * FROM " . TABLE_PREFIX . "useractivation
                WHERE userid = " . $vbulletin->userinfo['userid'] . "
                AND type = 0
            ");

            if (!empty($activation_exists['usergroupid']) AND $vbulletin->userinfo['usergroupid'] == 3)
            {
                $usergroupid = $activation_exists['usergroupid'];
            }
            else
            {
                $usergroupid = $vbulletin->userinfo['usergroupid'];
            }
            $activateid = build_user_activation_id($vbulletin->userinfo['userid'], $usergroupid, 0, 1);

            $username = unhtmlspecialchars($vbulletin->userinfo['username']);
            $userid = $vbulletin->userinfo['userid'];

            eval(fetch_email_phrases('activateaccount_change'));
            $res = vbmail($vbulletin->GPC['email'], $subject, $message, true);
        }
        else
        {
            $activate = false;
        }
    }
    else
    {
        $userdata->verify_useremail($vbulletin->userinfo['email']);
    }


    // save the data
    $userdata->save();

    return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval(isset($res) ? $res : true, 'boolean'),
            'result_text'       => new xmlrpcval($activate ? ($res ? '' : 'An email for active account change send failed') : '', 'base64'),
        ), 'struct'));
}
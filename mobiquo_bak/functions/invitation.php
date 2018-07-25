<?php
defined('IN_MOBIQUO') or exit;

error_reporting(0);

if (function_exists('set_magic_quotes_runtime'))
@set_magic_quotes_runtime(0);
ini_set('max_execution_time', '120');

require_once(CWD1.'/include/classTTConnection.php');
require_once(CWD1.'/include/function_push.php');
require_once(CWD1.'/include/common.php');

define('SCRIPT_ROOT', get_root_dir());
chdir(SCRIPT_ROOT);

define('THIS_SCRIPT', 'register');
define('CSRF_PROTECTION', false);
define('CSRF_SKIP_LIST', 'login');

require_once('./global.php');
global $vbulletin;
$invite_response['result'] = false;

if(!empty($_POST['session']) && !empty($_POST['api_key']) && !empty($_POST['subject']) && !empty($_POST['body']))
{
    $push_url = "https://tapatalk.com/forum_owner_invite.php?PHPSESSID=$_POST[session]&api_key=$_POST[api_key]&url=".urlencode($vbulletin->options['bburl'])."&action=verify";
    $connection = new classTTConnection();
    $response = $connection->getContentFromSever($push_url, array(), 'get');
    $error = $connection->errors;
    $_POST['subject'] = mobiquo_encode($_POST['subject'],'to_local');
    $_POST['body'] = mobiquo_encode($_POST['body'],'to_local');
    if($response) $result = @json_decode($response, true);
    if(empty($result) || empty($result['result']))
        if(preg_match('/\{"result":true/', $response))
            $result = array('result' => true); 
    if(isset($result) && isset($result['result']) && $result['result'])
    {
        if(isset($_POST['username']))
        {
            if(!empty($_POST['username']))
            {
                $user = get_user_by_NameorEmail($_POST['username']);
                if(!empty($user['email']) && ($user['options'] & $vbulletin->bf_misc_useroptions['adminemail']))
                {
                    require_once('./includes/functions.php');
                    $invite_response['result'] = vbmail($user['email'], mobiquo_encode($_POST['subject'], 'to_local', false),  mobiquo_encode($_POST['body'], 'to_local', false), true);
                    $invite_response['result_text'] = "Sent successfully for $_POST[username]";
                }
                else
                {
                    $invite_response['result_text'] = 'Username does not exist or user don\'t allow admin emails!';
                }
            }
            else
            {
                $invite_response['result_text'] = 'Username does not exist!';
            }
        }
        else
        {
            $usergroups = $vbulletin->db->query_read("SELECT usergroupid, title, (forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview'] . ") AS CANVIEW FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
            while ($usergroup = $vbulletin->db->fetch_array($usergroups))
            {
                if ($usergroup['CANVIEW'])
                {
                    $userarray['membergroupids'] .= "$usergroup[usergroupid],";
                }
            }
            unset($usergroup);
            $vbulletin->db->free_result($usergroups);
            $groupids = explode(',', $userarray['membergroupids']);
        
            foreach($groupids as $idx => $id)
                if(empty($id)) unset($groupids[$idx]);
        
            $groupids = implode(',', $groupids);
            $emails = $vbulletin->db->query_read("select email, options FROM " . TABLE_PREFIX . "user where usergroupid IN ($groupids) AND email <> ''");
            $number = 0;
            while($email = $vbulletin->db->fetch_array($emails))
            {
                if(!($email['options'] & $vbulletin->bf_misc_useroptions['adminemail']))
                    continue;
                if(vbmail($email['email'], $_POST['subject'], $_POST['body']))
                {
                    $number++;
                }
            }
            
            $invite_response['result'] = $number ? true : false;
            $invite_response['number'] = $number;
            $invite_response['result_text'] = "Sent email to $number users";
        }
    }
    else
    {
        $invite_response['result_text'] = $error;
    }
}
else if(!empty($_POST['email_target']))
{
    //get email targe
    $usergroups = $vbulletin->db->query_read("SELECT usergroupid, title, (forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['canview'] . ") AS CANVIEW FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
    while ($usergroup = $vbulletin->db->fetch_array($usergroups))
    {
        if ($usergroup['CANVIEW'])
        {
            $userarray['membergroupids'] .= "$usergroup[usergroupid],";
        }
    }
    unset($usergroup);
    $vbulletin->db->free_result($usergroups);
    $groupids = explode(',', $userarray['membergroupids']);

    foreach($groupids as $idx => $id)
        if(empty($id)) unset($groupids[$idx]);

    $groupids = implode(',', $groupids);
    $user_count = $vbulletin->db->query_read("select count(*) as c FROM " . TABLE_PREFIX . "user where usergroupid IN ($groupids) AND email <> ''");
    $user_count = $vbulletin->db->fetch_array($user_count);
    $user_count = $user_count['c'];
    echo $user_count;
    exit;
}

header('Content-type: application/json');
echo json_encode($invite_response);
exit;



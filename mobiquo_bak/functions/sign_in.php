<?php
defined('IN_MOBIQUO') or exit;
include (dirname(dirname(__FILE__))."/include/classTTSSO.php");

require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php');

function sign_in_func($xmlrpc_params)
{
    global $vbulletin, $userinfo, $tt_config, $db;
    $params = php_xmlrpc_decode($xmlrpc_params);
    $InputParams = array(
        'token'       => mobiquo_encode($params[0], 'to_local'),
        'code'        => mobiquo_encode($params[1], 'to_local'),
        'email'       => mobiquo_encode($params[2], 'to_local'),
        'username'    => mobiquo_encode($params[3], 'to_local'),
        'password'    => mobiquo_encode($params[4], 'to_local'),
        'custom_register_fields'    =>  $params[5],
    );
    $ssoHandle = new TTSSOBase(new TTSSOForum());
    $ssoHandle->signIn($InputParams);
    if(!empty($ssoHandle->result) && $ssoHandle->result)
    {
        $return_array = $ssoHandle->result;
        foreach($ssoHandle->errors as $error)
        {
            $finalError .= $error."\n";
        }
        $return_array['result_text'] = new xmlrpcval($finalError, 'base64');
        $return = new xmlrpcresp(new xmlrpcval($return_array, 'struct'));
        return $return;
    }
    else
    {
        $finalError = '';
        foreach($ssoHandle->errors as $error)
        {
            $finalError .= $error."\n";
        }

        $result = new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval($finalError, 'base64'),
        ), 'struct');

        return new xmlrpcresp($result); 
    }
}

class TTSSOForum implements TTSSOForumInterface
{
    function __construct(){}

    public function getEmailByUserInfo($userInfo)
    {
        return $userInfo['email'];
    }  
    
    // return user info array, including key 'email', 'id', etc.
    public function getUserByEmail($email)
    {
        $user = get_user_by_NameorEmail($email);
        return $user;
    }
    public function getUserByName($username)
    {
        $user = get_user_by_NameorEmail($username);
        return $user;
    }

    // the response should be bool to indicate if the username meet the forum requirement
    public function validateUsernameHandle($username)
    {
        $user = get_user_by_NameorEmail($username);
        $username_exist = isset($user['userid']) && !empty($user['userid']);
        return $username_exist ? false : true; 
    }

    // the response should be bool to indicate if the password meet the forum requirement
    public function validatePasswordHandle($password)
    {
        return true;
    }

    // create a user, $verified indicate if it need user activation
    public function createUserHandle($email, $username, $password, $verified, $custom_register_fields, $profile, &$errors)
    {
        global $vbulletin, $userinfo, $tt_config, $db;
        $_POST['email'] = mobiquo_encode($email, 'to_local');
        $_POST['username'] = mobiquo_encode($username, 'to_local');
        $_POST['password'] = mobiquo_encode($password, 'to_local');
        $_POST['password_md5'] = md5($_POST['password']);
        $_POST['agree'] = 1;

        //gavatar? vb don't support
        if(!$tt_config['sso_signin'])
        {
            $errors[] = 'Application Error : social sign in is not supported currently.';
            return false;
        }
        //formating custom fields    
        $custom_fields = $custom_register_fields;
        if(!empty($custom_fields))
        {
            foreach($custom_fields as $filed_name => $field_value)
            {
                if($filed_name == "COPPA_BIRTHDAY")
                {
                    $birthday = explode('-', $field_value);
                    $_POST['month'] = $birthday[1];
                    $_POST['year']= $birthday[0];
                    $_POST['day']= $birthday[2];
                    continue;
                }
                
                
                if(is_array($field_value))
                {
                    $orgnized_value = array();
                    foreach($field_value as $key => $value)
                    {
                        $orgnized_value[] = $key;
                    }
                    $custom_fields[$filed_name] = $orgnized_value;
                }
                else
                {
                    $custom_fields[$filed_name] = mobiquo_encode($field_value, 'to_local');
                }
                $custom_fields[$filed_name.'_set'] = 1;
            }
            $profilefields = $db->query_read_slave("
                SELECT *
                FROM " . TABLE_PREFIX . "profilefield
                WHERE editable > 0 AND required <> 0
                ORDER BY displayorder
            ");
            while ($profilefield = $db->fetch_array($profilefields))
            {
                $profilefieldname = "field$profilefield[profilefieldid]";
                if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select')
                {
                    if(isset($custom_fields[$profilefieldname]))
                    {
                        $custom_fields[$profilefieldname] = $custom_fields[$profilefieldname][0];
                    }
                }
                if(isset($custom_fields[$profilefieldname]) && $profilefield['regex'])
                {//$profilefield['title'])
                    if (!preg_match('#' . str_replace('#', '\#', $profilefield['regex']) . '#siU', $custom_fields[$profilefieldname]))
                    {
                        $profilefield['title'] = fetch_phrase($profilefieldname . '_title', 'cprofilefield');
                        eval(standard_error(fetch_error('regexincorrect', $profilefield['title'])));
                    }
                }
                
            }
            $_POST['userfield'] = $custom_fields;
        }

        if (!$verified)
            $reg_response = register_user(true, false, $profile);
        else
            $reg_response = register_user(false, false, $profile,$verified);

        if(is_array($reg_response))
        {
            list($user_id, $result_text) = $reg_response;

            if($user_id != 0) 
            {
                // register succeed, try to add custom avatar
                if(isset($profile['avatar_url'])&& !empty($profile['avatar_url']))
                {
                    try
                    {
                        $_POST['userid'] = $user_id;
                        $_POST['avatarid'] = 0;
                        $_POST['avatarurl'] = $profile['avatar_url'];
                        $_POST['resize'] = 1;
                        $vbulletin->input->clean_array_gpc('p', array(
                            'userid'    => TYPE_UINT,
                            'avatarid'  => TYPE_INT,
                            'avatarurl' => TYPE_STR,
                            'resize'    => TYPE_BOOL,
                        ));
                            $useavatar = iif($vbulletin->GPC['avatarid'] == -1, 0, 1);

                        $userinfo = fetch_userinfo($vbulletin->GPC['userid']);

                        // init user datamanager
                        $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
                        $userdata->set_existing($userinfo);

                        // custom avatar
                        $vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);
            
                        require_once(DIR . '/includes/class_upload.php');
                        require_once(DIR . '/includes/class_image.php');
            
                        $upload = new vB_Upload_Userpic($vbulletin);
            
                        $upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
                        $upload->image =& vB_Image::fetch_library($vbulletin);
                        $upload->userinfo =& $userinfo;
            
                        cache_permissions($userinfo, false);
            
                        // user's group doesn't have permission to use custom avatars
                        if ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar'])
                        {
                            if ($vbulletin->GPC['resize'])
                            {
                                $upload->maxwidth = $userinfo['permissions']['avatarmaxwidth'];
                                $upload->maxheight = $userinfo['permissions']['avatarmaxheight'];
                            }
                            if ($upload->process_upload($vbulletin->GPC['avatarurl']))
                            {
                                $userdata->set('avatarid', $vbulletin->GPC['avatarid']);
                                $userdata->save();
                            }
                        }
                    }
                    catch(Exception $e){}
                }
                
                $user = mobiquo_verify_id('user', $user_id, 0, 1);
                return $user;//login_user($user, true); // login if registered
            }
            else
            {
                $errors[] = $result_text;
                return false;//error_status($username_exist ? '1' : '0', $result_text);
            }
        }
        else
        {
            $result_text = (string) $reg_response;
            $errors[] = $result_text;
            return false;//error_status($username_exist ? '1' : '0', $result_text);
        }
    }

    // login to an existing user, return result as bool
    public function loginUserHandle($userInfo, $register)
    {
        return login_user($userInfo,$register);
    }
    
    // return forum api key
    public function getAPIKey()
    {
        global $vbulletin;
        return $vbulletin->options['push_key'];
    }

    // return forum url
    public function getForumUrl()
    {
        global $vbulletin;
        return $vbulletin->options['homeurl'];
    }
}

function login_user($user, $new_register = false)
{
    global $vbulletin, $userinfo, $tt_config, $db;

    $username = $user['username'];
    $password = $user['password'];
    $vbulletin->userinfo = $user;
    $vbulletin->GPC['logintype'] = null;
    require_once(DIR . '/includes/functions_login.php');
    require_once(DIR . '/includes/functions_user.php');
    require_once(DIR . '/includes/functions_misc.php');

    if (true)
    {
        $return = array();
        $vbulletin->GPC['username'] =$username;
        if(strlen($password) == 32){
            $vbulletin->GPC['md5password'] = $password;
            $vbulletin->GPC['md5password_utf'] = $password;
        } else {
            $vbulletin->GPC['password'] = $password;
        }

        if ($vbulletin->GPC['username'] == '')
        {
            return_fault('You have entered an invalid username or password.');
        }


        // make sure our user info stays as whoever we were (for example, we might be logged in via cookies already)
        $original_userinfo = $vbulletin->userinfo;

        exec_unstrike_user($vbulletin->GPC['username']);

        $member_groups = preg_split("/,/", $vbulletin->userinfo['membergroupids']);

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
            if($id)
            {
                array_push($return_group_ids, new xmlrpcval($id, 'string'));
            }
        }
        array_push($return_group_ids,new xmlrpcval($vbulletin->userinfo['usergroupid'], 'string'));

        if($group_block)
        {
            $return_text = 'The usergroup you belong to does not have permission to login. Please contact your administrator. ';
            $return = new xmlrpcresp(new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'result_text'   => new xmlrpcval(mobiquo_encode($return_text), 'base64'),
            ), 'struct'));

        } 
        else 
        {
            if (!empty($vbulletin->session->userinfo) && $vbulletin->session->userinfo['userid'] == 0 ) $vbulletin->session->userinfo = false;
            process_new_login($vbulletin->GPC['logintype'], $vbulletin->GPC['cookieuser'], $vbulletin->GPC['cssprefs']);
            $vbulletin->session->save();
            $permissions = cache_permissions($vbulletin->userinfo);
            $pmcount = $vbulletin->db->query_first("
                SELECT COUNT(pmid) AS pmtotal
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
            $push_status = array();

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
                'register'          => new xmlrpcval($new_register, 'boolean'),
                'icon_url'          => new xmlrpcval(mobiquo_get_user_icon($vbulletin->userinfo['userid']), 'string'),
                'ignore_uids'      => new xmlrpcval($ignore_user_ids, 'string'),
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
            if(isset($push_status) && !empty($push_status))
                $return_array['push_type'] =  new xmlrpcval($push_status, 'array');

            $return = $return_array;
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
//        'announcement'      => 'ann',
    );
    return isset($starndard_key_map[$key])? $starndard_key_map[$key]: '';
}
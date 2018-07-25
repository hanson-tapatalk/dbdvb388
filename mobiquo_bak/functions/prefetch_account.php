<?php
defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');

function prefetch_account_func($xmlrpc_params)
{
    global $vbulletin, $db, $vbphrase;

    $params = php_xmlrpc_decode($xmlrpc_params);

    $email = mobiquo_encode($params[0], 'to_local');
    $return_profile_fields = array();
	$profilefields = $db->query_read_slave("
		SELECT *
		FROM " . TABLE_PREFIX . "profilefield
		WHERE editable > 0 AND required <> 0
		ORDER BY displayorder
	");
	while ($profilefield = $db->fetch_array($profilefields))
	{
	    if($profilefield['required'] == 2) continue;

	    $profilefieldname = "field$profilefield[profilefieldid]";
	    //fetch_phrase();
		$profilefield['title'] = fetch_phrase($profilefieldname . '_title', 'cprofilefield');
		$profilefield['description'] = fetch_phrase($profilefieldname . '_desc', 'cprofilefield');
		// input/textarea/radio/cbox/drop
        if(in_array($profilefield['type'], array('checkbox', 'select_multiple')))
        {
            $profilefield['type'] = 'cbox';
        }
        else if($profilefield['type'] == 'select')
        {
            $profilefield['type'] = 'radio';
        }
        else if($profilefield['type'] == 'textarea')
        {
            $profilefield['type'] = 'input';
        }
        $option = '';
        if(in_array($profilefield['type'], array('radio', 'cbox')))
        {
			$data = unserialize($profilefield['data']);

			foreach ($data AS $key => $val)
			{
			    $val = mobiquo_encode($val);
			    $key += 1;
                $option .= "$key=$val|";
			}
			$option = chop($option, '|');
			if($profilefield['type'] == 'radio' && isset($profilefield['def']) && !empty($profilefield['def']))
			{
			    $default = $profilefield['def'];
			}
        }
        else if($profilefield['type'] == 'input')
        {
            $default = $profilefield['data'];
        }
        $field_res = array(
	        'name' => new xmlrpcval(mobiquo_encode($profilefield['title']), 'base64'),
	        'description' => new xmlrpcval(mobiquo_encode($profilefield['description']), 'base64'),
	        'type' => new xmlrpcval($profilefield['type'], 'string'),
	        'key' => new xmlrpcval($profilefieldname, 'string'),
	    );
	    if(isset($default) && !empty($default))
	    {
	        $field_res['default'] = new xmlrpcval(mobiquo_encode($default), 'base64');
	    }
	    if(!empty($option))
	    {
	        $field_res['options'] = new xmlrpcval($option, 'base64');
	    }
	    $return_profile_fields[] = new xmlrpcval($field_res,'struct');
	}
	if($vbulletin->options['usecoppa'])
    {
        $field_res = array(
           'name' => new xmlrpcval(mobiquo_encode('Date of birth'), 'base64'),
           'description' => new xmlrpcval(mobiquo_encode('This forum require your date of birth to register'), 'base64'),
           'type' => new xmlrpcval('input', 'string'),
           'key' => new xmlrpcval('COPPA_BIRTHDAY', 'string'),
           'format' => new xmlrpcval('nnnn-nn-nn', 'string'),
           'is_birthday' => new xmlrpcval(1, 'int'),
       );
       $return_profile_fields[] = new xmlrpcval($field_res,'struct');
      
    }
    if(!empty($email))
    {
        $user = get_user_by_NameorEmail($email);
        if(!empty($user))
        {
            $fetch_userinfo_options = (
                FETCH_USERINFO_AVATAR | FETCH_USERINFO_LOCATION |
                FETCH_USERINFO_PROFILEPIC | FETCH_USERINFO_SIGPIC |
                FETCH_USERINFO_USERCSS | FETCH_USERINFO_ISFRIEND
            );
    
            $userinfo = mobiquo_verify_id('user', $user['userid'], 0, 1, $fetch_userinfo_options);
            if(!empty($userinfo) && is_array($userinfo))
            {
                fetch_avatar_from_userinfo($userinfo, true, false);
                $result = new xmlrpcval(array(
                    'result'        => new xmlrpcval(true, 'boolean'),
                    'user_id'         => new xmlrpcval($userinfo['userid'], 'string'),
                    'login_name'        => new xmlrpcval(mobiquo_encode($userinfo['username']), 'base64'),
                    'display_name'        => new xmlrpcval(mobiquo_encode($userinfo['username']), 'base64'),
                    'avatar'                => new xmlrpcval(isset($userinfo['avatarurl']) && !empty($userinfo['avatarurl'])? get_icon_real_url($userinfo['avatarurl']) : '', 'string'),
                    'custom_register_fields'  => new xmlrpcval($return_profile_fields,'array'),
                ), 'struct');
            
            }
            else
            {
                $result = false;
            }
        }
        else
        {
            $result = false;
        }
    }
    else
    {
        $result = false;
    }

    if(!$result)
    {
        $result = new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval('Invalid email' , 'base64'),
            'custom_register_fields'  => new xmlrpcval($return_profile_fields,'array'),
        ), 'struct');
    }
    return new xmlrpcresp($result);
}


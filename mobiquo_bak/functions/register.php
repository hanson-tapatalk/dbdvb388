<?php
defined('IN_MOBIQUO') or exit;
require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php');

function register_func($xmlrpc_params)
{
    global $vbulletin, $tt_config, $db;

    $params = php_xmlrpc_decode($xmlrpc_params);

    $need_email_verification = true;
    $_POST['username'] = mobiquo_encode($params[0], 'to_local');
    $_POST['password'] = mobiquo_encode($params[1], 'to_local');
    $_POST['email'] = mobiquo_encode($params[2], 'to_local');
    $_POST['password_md5'] = md5($_POST['password']);

    if(!$tt_config['native_register']) return return_fault('Application Error : native registration is not supported currently.');

    if(isset($params[3]))
    {
        if(!$tt_config['sso_register']) return return_fault('Application Error : social registration is not supported currently.');
        include(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');
        $email_response = getEmailFromScription($params[3], $params[4], $vbulletin->options['push_key']);
        $need_email_verification = isset($email_response['result']) && $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']) && ($email_response['email'] == $_POST['email']) ? false : true;
    }
    //formating custom fields    
    $custom_fields = $params[5];
    if(!empty($custom_fields))
    {
        foreach($custom_fields as $filed_name => $field_value)
        {
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
    
    $reg_response = register_user($need_email_verification);

    if(is_array($reg_response))
    {
        list($userid, $result_text) = $reg_response;
        $result = new xmlrpcval(array(
            'result'            => new xmlrpcval( $userid != 0 , 'boolean'),
            'result_text'       => new xmlrpcval(mobiquo_encode($result_text), 'base64'),
        ), 'struct');
    }
    else
    {
        $result_text = (string) $reg_response;
        $result = new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(mobiquo_encode($result_text), 'base64'),
        ), 'struct');

    }
    return new xmlrpcresp($result);
}


<?php

defined('CWD1') or exit;
defined('IN_MOBIQUO') or exit;

require_once (get_root_dir().'/includes/functions.php');

function update_profile_func($xmlrpc_params) {
    
    global $vbulletin;

    $params = php_xmlrpc_decode($xmlrpc_params);
    $userid = $params[0];
    $custom_fields = $params[1];

    if ($vbulletin->userinfo['userid'] != $userid) {
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }

    foreach ($custom_fields as $field_name => $field_value) {
        if (is_array($field_value)) {
            $orgnized_value = array();
            foreach ($field_value as $key => $value) {
                $orgnized_value[] = $key;
            }
            $custom_fields[$field_name] = $orgnized_value;
        } else {
            $custom_fields[$field_name] = mobiquo_encode($field_value, 'to_local');
        }
    }

    $profilefields = $vbulletin->db->query_read_slave("
        SELECT *
        FROM " . TABLE_PREFIX . "profilefield
        WHERE editable > 0 AND required <> 0
        ORDER BY displayorder
    ");
    while ($profilefield = $vbulletin->db->fetch_array($profilefields)) {
        $profilefieldname = "field$profilefield[profilefieldid]";
        if ($profilefield['type'] == 'radio' OR $profilefield['type'] == 'select') {
            if (isset($custom_fields[$profilefieldname])) {
                $custom_fields[$profilefieldname] = $custom_fields[$profilefieldname][0];
            }
        }
    }

    $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $userdata->set_existing($vbulletin->userinfo);
    $userdata->set_userfields($custom_fields);

    $userdata->save();
    $return = new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($return);
}
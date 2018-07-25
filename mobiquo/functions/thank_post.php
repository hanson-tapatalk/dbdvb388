<?php

defined('IN_MOBIQUO') or exit;

define('CSRF_PROTECTION', false);
define('LOCATION_BYPASS', 1);
define('NOPMPOPUP', 1);


$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array(
    'post_thanks_box', 
    'post_thanks_box_bit', 
    'post_thanks_button', 
    'post_thanks_postbit', 
    'post_thanks_postbit_legacy'
);

$actiontemplates = array();

require_once('./global.php');


function thank_post_func($xmlrpc_params)
{
    global $vbulletin;
    
    $params = php_xmlrpc_decode($xmlrpc_params);
    $postid = intval($params[0]);
    
    if(($vbulletin->options['dbtech_thanks_features'] == 1 || $vbulletin->options['dbtech_thanks_features'] > 2) && class_exists('THANKS'))
    {
        require_once('dbtTL.php');
        process_action($postid, 'thanks');
    } else {
        require_once(DIR . '/includes/functions_post_thanks.php');
        $postinfo = mobiquo_verify_id('post', $postid, 0, 1);
        $threadinfo = mobiquo_verify_id('thread', $postinfo['threadid'], 0, 1);
        $postinfo = array_merge($postinfo, fetch_userinfo($postinfo['userid']));
        
        if (post_thanks_off($threadinfo['forumid'], $postinfo, $threadinfo['firstpostid']) || !can_thank_this_post($postinfo, $threadinfo['isdeleted'], false) || thanked_already($postinfo))
        {
            return_fault(array('', 'Post thanks action deny'));
        }
        
        add_thanks($postinfo);
    }
  
    return new xmlrpcresp(
        new xmlrpcval(array(
            'result'        => new xmlrpcval(true, 'boolean'),
            'result_text'   => new xmlrpcval('', 'base64'),
        ), 'struct')
    );
}
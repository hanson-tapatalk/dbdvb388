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

defined('IN_MOBIQUO') or exit;

@set_time_limit(0);

define('GET_EDIT_TEMPLATES', true);
define('THIS_SCRIPT', 'newattachment');
define('CSRF_PROTECTION', false);

require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_file.php');

function remove_attach_func($xmlrpc_params)
{
    global $vbulletin, $db, $forumperms, $permissions;

    $decode_params = php_xmlrpc_decode($xmlrpc_params);
    $attachmentid = intval($decode_params[0]);
    $posthash = $decode_params[2];
    $vbulletin->input->clean($posthash, TYPE_NOHTML);
    $forumid = intval($decode_params[1]);
    $postid = isset($decode_params[3]) && intval($decode_params[3]) > 0 ? intval($decode_params[3]) : 0;
    if (!$vbulletin->userinfo['userid']) // Guests can not post attachments
    {
        return_fault();
    }

    $foruminfo = mobiquo_verify_id('forum', $forumid, 1, 1);
    $forumperms = fetch_permissions($foruminfo['forumid']);

    // No permissions to post attachments in this forum or no permission to view threads in this forum.
    if (empty($vbulletin->userinfo['attachmentextensions']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
        return_fault();
    }

    if ((!$postid AND !$foruminfo['allowposting']) OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
        return_fault(fetch_error('forumclosed'));
    }

    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew'])) // newthread.php
    {
        return_fault();
    }

    $attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_STANDARD);

    if(empty($postid))
        $attachdata->condition = "attachmentid = $attachmentid AND attachment.posthash = '" . $db->escape_string($posthash) . "'";
    else
        $attachdata->condition = "attachmentid = $attachmentid AND (attachment.postid = $postid OR attachment.posthash = '" . $db->escape_string($posthash) . "')";

    
    $status = $attachdata->delete();
    
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval($status, 'boolean'),
    ), 'struct'));
}

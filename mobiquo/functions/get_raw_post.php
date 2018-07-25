<?php

defined('CWD1') or exit;

defined('IN_MOBIQUO') or exit;

define('GET_EDIT_TEMPLATES', true);
define('CSRF_PROTECTION', false);
define('THIS_SCRIPT', 'editpost');

$phrasegroups = array();
$specialtemplates = array();
$globaltemplates = array();
$actiontemplates = array();

require_once('./global.php');
require_once(DIR . '/includes/functions_newpost.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_editor.php');
require_once(DIR . '/includes/functions_log_error.php');

$checked = array();
$edit = array();
$postattach = array();

function get_raw_post_func($xmlrpc_params)
{
    global $vbulletin;
    global $db;
    global $xmlrpcerruser;
    global $forumperms;


    $decode_params = php_xmlrpc_decode($xmlrpc_params);
    $postid = intval($decode_params[0]);
    //$reply_postid = $decode_params[1];

    $vbulletin->GPC['postid'] = $postid;

    if ($vbulletin->GPC['postid'] AND $postinfo = mobiquo_verify_id('post', $vbulletin->GPC['postid'], 0, 1))
    {
        $postid =& $postinfo['postid'];
        $vbulletin->GPC['threadid'] =& $postinfo['threadid'];
    }

    // automatically query $threadinfo & $foruminfo if $threadid exists
    if ($vbulletin->GPC['threadid'] AND $threadinfo = mobiquo_verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
    {

        $threadid =& $threadinfo['threadid'];
        $vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
        if ($forumid)
        {
            $foruminfo = fetch_foruminfo($threadinfo['forumid']);
            if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
            {
                $codestyleid = $foruminfo['styleid'];
            }
        }

        if ($vbulletin->GPC['pollid'])
        {
            $pollinfo = verify_id('poll', $vbulletin->GPC['pollid'], 0, 1);
            $pollid =& $pollinfo['pollid'];
        }
    }
    if (!$postinfo['postid'] OR $postinfo['isdeleted'] OR (!$postinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
        $return = array(6,'invalid post id');
        return return_fault($return);
    }

    if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
        $return = array(6,'invalid post id');
        return return_fault($return);
    }

    if ($vbulletin->options['wordwrap'])
    {
        $threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
    }

    // get permissions info
    $_permsgetter_ = 'edit post';
    $forumperms = fetch_permissions($threadinfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
    OR
    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR
    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND
    ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    )
    {
        $return = array(20,'you do not have permission to access this page.');
        return return_fault($return);
    }
    $return_attachments = array();
    $group_id = '';
    // edit / add attachment
    if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions']))
    {
        require_once(DIR . '/includes/functions_file.php');
        $inimaxattach = fetch_max_upload_size();
        $maxattachsize = vb_number_format($inimaxattach, 1, true);

        if (!$posthash OR !$poststarttime)
        {
            $poststarttime = TIMENOW;
            $posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);
        }

        if (empty($postattach))
        {
            // <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table
            // A poor man's UNION
            // Attachments that existed before the edit began.
            $currentattaches1 = $db->query_read_slave("
                SELECT dateline, filename, filesize, attachmentid, thumbnail IS NOT NULL as has_thumb
                FROM " . TABLE_PREFIX . "attachment
                WHERE postid = $postinfo[postid]
                ORDER BY attachmentid
            ");
            // Attachments added since the edit began. Used when editpost is reloaded due to an error on the user side
            $currentattaches2 = $db->query_read_slave("
                SELECT dateline, filename, filesize, attachmentid, thumbnail IS NOT NULL as has_thumb
                FROM " . TABLE_PREFIX . "attachment
                WHERE posthash = '" . $db->escape_string($posthash) . "'
                    AND userid = " . $vbulletin->userinfo['userid'] . "
                ORDER BY attachmentid
            ");
            $attachcount = 0;
            for ($x = 1; $x <= 2; $x++)
            {
                $currentattaches =& ${currentattaches . $x};
                while ($attach = $db->fetch_array($currentattaches))
                {
                    $postattach["$attach[attachmentid]"] = $attach;
                }
            }
        }
        // DON'T PUT AN ELSE HERE! OR ELSE!! ;)
        if (!empty($postattach))
        {
            foreach($postattach AS $attachmentid => $attach)
            {
                $attachcount++;
                $attach['filename'] = htmlspecialchars_uni($attach['filename']);
                $attach['filesize'] = $attach['filesize'];
                $attach['extension'] = strtolower(file_extension($attach['filename']));
                $type = $attach['extension'] == 'pdf' ? 'pdf' : (in_array($attach['extension'], array('bmp','gif','jpe','jpeg','jpg','png')) ? 'image' : 'other');
                //tapatalk
                $attachment_url = $vbulletin->options['bburl']."/attachment.php?attachmentid=$attachmentid&stc=1&d=$attach[dateline]";
                $attachment_thumbnail_url = $attach['has_thumb'] ? $attachment_url."&thumb=1" : '';
                $return_attachment = new xmlrpcval(array(
                    'attachment_id' => new xmlrpcval($attachmentid, "string"),
                    'filename'      => new xmlrpcval(htmlspecialchars_uni($attach['filename']), "base64"),
                    'filesize'      => new xmlrpcval(intval($attach['filesize']), 'int'),
                    'url'           => new xmlrpcval($attachment_url, "string"),
                    'thumbnail_url' => new xmlrpcval($attachment_thumbnail_url, "string"),
                    'content_type'  => new xmlrpcval($type, "string")
                ), 'struct');
                array_push($return_attachments,$return_attachment);
            }
        }
        $group_id = $posthash;
    }

    $foruminfo = fetch_foruminfo($threadinfo['forumid'], false);

    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    // need to get last post-type information
    cache_ordered_forums(1);
    if (!can_moderate($threadinfo['forumid'], 'caneditposts'))
    { // check for moderator
        if (!$threadinfo['open'])
        {
            $return = array(20,'you do not have permission to access this page.');
            return return_fault($return);
        }
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['caneditpost']))
        {
            $return = array(20,'you do not have permission to access this page.');
            return return_fault($return);
        }
        else
        {
            if ($vbulletin->userinfo['userid'] != $postinfo['userid'])
            {
                // check user owns this post
                $return = array(20,'you do not have permission to access this page.');
                return return_fault($return);
            }
            else
            {
                // check for time limits
                if ($postinfo['dateline'] < (TIMENOW - ($vbulletin->options['edittimelimit'] * 60)) AND $vbulletin->options['edittimelimit'] != 0)
                {
                    $return = array(20,'you do not have permission to access this page.');
                    return return_fault($return);
                }
            }
        }
    }
    $post_content = mobiquo_encode($postinfo['pagetext']);
    $post_title   = mobiquo_encode($postinfo['title']);
    $return_data = array(
        'post_id'       =>  new xmlrpcval($postid, 'string'),
        'post_title'    =>  new xmlrpcval($post_title, 'base64'),
        'post_content'  =>  new xmlrpcval($post_content, 'base64'),
        'group_id'      =>  new xmlrpcval($group_id, 'string'),
        'show_reason'   =>  new xmlrpcval(true,'boolean'),
        'edit_reason'   =>  new xmlrpcval(mobiquo_encode($postinfo['edit_reason']), 'base64'),
        'attachments'   =>  new xmlrpcval($return_attachments, 'array'),
    );

    return  new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}

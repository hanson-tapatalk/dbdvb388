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

require_once('./global.php');

if(defined('VBSEO_ENABLED') && VBSEO_ENABLED && VBSEO_LIKE_POST)
{
    if(!vbseo_vb_userid())
        return_fault('Not logged in');
    
    vbseo_extra_inc('ui');
    
    $cid = intval($_POST['contentid']);
    $ctype = VBSEO_UI_THREAD;

    $pinfo = vbseo_get_post_info($cid);
    if($pinfo)
    {
        $cgroup = $pinfo['threadid'];
        $cduser = $pinfo['userid'];
    }
    else
        return_fault('Content not found');
    
    if($cduser == vbseo_vb_userid())
        return_fault('You can not like post of yourself');
    
    $tinfos= vbseo_get_thread_info($cgroup);
    $flist = vbseo_allowed_forums();
    if(!in_array($tinfos[$cgroup]['forumid'], $flist))
    {
        return_fault('Access denied in this forum');
    }
    
    if ($li = tt_get_like($cid, $cgroup, $ctype) && $request_method == 'like_post')
        return_fault('You already liked this post');
    
    if($request_method == 'unlike_post')
        $res = tt_remove_like($cid, $ctype, $cgroup, 0, $cduser);
    else
        $res = tt_add_like($cid, $ctype, $cgroup, 0, $cduser);
    
    if(!$res)
        return_fault('Error processing request');
}
else if($vbulletin->options['dbtech_thanks_features'] == 2 || $vbulletin->options['dbtech_thanks_features'] > 2)
{
    // Grab these
    $contentid = $vbulletin->input->clean_gpc('p', 'contentid', TYPE_UINT);
    // Add dbTech Support
    require_once('dbtTL.php');
    process_action($contentid, 'likes');
}
else
    return_fault('Like feature was not enabled');


function tt_get_like($cid, $cgroup, $ctype, $userid = 0)
{
    if(!$userid && (!$userid = vbseo_vb_userid()))
        return false;
    
    $db = vbseo_get_db();
    $larr = $db->vbseodb_query_first("
        SELECT *
        FROM " . vbseo_tbl_prefix('vbseo_likes') . " 
        WHERE l_contentid = " . intval($cid) . " AND l_cgroup = ".intval($cgroup)." AND l_ctype = " . intval($ctype) . " 
        AND l_from_userid = ".intval($userid)."
        LIMIT 1
    ");
    
    return $larr;
}

function tt_remove_like($cid, $ctype, $groupid = 0, $userid = 0, $duserid = 0)
{
    if(!$userid && (!$userid = vbseo_vb_userid()))
    return false;
    $db = vbseo_get_db();
    $db->vbseodb_query("
    DELETE FROM " . vbseo_tbl_prefix('vbseo_likes') . " 
    WHERE  l_ctype = " . intval($ctype) . " 
    AND l_cgroup = " . intval($groupid) . " 
    AND l_contentid = " . intval($cid) . " 
    AND l_from_userid = ".intval($userid)."
    ");
    $dn = $db->vbseo_affected_rows();
    vBSEO_UI::like_counter_type($ctype, $groupid, -$dn);
    vBSEO_UI::like_counter_user($userid, $duserid, -$dn);
    return true;
}

function tt_add_like($cid, $ctype, $groupid = 0, $userid = 0, $duserid = 0, $dateline = 0, $from_username = '')
{
    if(!$userid)
    {
        $userid = vbseo_vb_userid();
        $from_username = vbseo_vb_userinfo('username');
    }
    if(!$userid || !$from_username)
        return false;
    
    if(!$dateline) $dateline = time();
    
    $db = vbseo_get_db();
    $db->vbseodb_query($q="
        INSERT INTO " . vbseo_tbl_prefix('vbseo_likes') . " 
        SET l_contentid = " . intval($cid) . ",
        l_ctype = " . intval($ctype) . ",
        l_cgroup = " . intval($groupid) . ",
        l_from_userid = " . intval($userid) . ",
        l_from_username= '" . vbseo_db_escape($from_username) . "',
        l_dest_userid = " . intval($duserid) . ",
        l_dateline = " . intval($dateline) . "
    ");
    $dn = $db->vbseo_affected_rows();
    vBSEO_UI::like_counter_user($userid, $duserid, $dn);
    vBSEO_UI::like_counter_type($ctype, $groupid, $dn);
    return true;
}
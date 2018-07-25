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
defined('IN_MOBIQUO');
defined('CWD1') or exit;
require_once(CWD1. '/include/function_text_parse.php');

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'announcement');
define('CSRF_PROTECTION', false);
// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
    'postbit',
    'reputationlevel',
    'posting',
);

// get special data templates from the datastore
$specialtemplates = array(
    'smiliecache',
    'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
    'view' => array(
        'announcement',
        'im_aim',
        'im_icq',
        'im_msn',
        'im_yahoo',
        'im_skype',
        'postbit',
        'postbit_wrapper',
        'postbit_onlinestatus',
        'postbit_reputation',
        'bbcode_code',
        'bbcode_html',
        'bbcode_php',
        'bbcode_quote',
    ),
    'edit' => array(
        'announcement_edit',
    ),
);

require_once('./global.php');
require_once(DIR .'/includes/functions_bigthree.php');


function get_announcement_func($xmlrpc_params)
{
    global $db, $vbulletin;
    
    $params = php_xmlrpc_decode($xmlrpc_params);

    if(!$params[0])
    {
        $return = array(2, 'Invalid announcement id.');
        return return_fault($return);
    }
    
    if(isset($params[1]) && $params[1] >= 0) {
        $start_num = $params[1] ; }
    else{
        $start_num = 0;
    }
    if(isset($params[2])){
        $end_num = $params[2];
    } else {
        $end_num = 19;
    }
    
    $html_content= false;
    if(isset($params[3]) && $params[3]){
        $html_content = true;
    }

    $post_num = $end_num-$start_num+1;
    $vbulletin->GPC['announcementid'] =  intval($params[0]);

    $announcementinfo = mobiquo_verify_id('announcement', $vbulletin->GPC['announcementid'], 1, 1);
    if ($announcementinfo['forumid'] != -1 AND $_POST['do'] != 'update')
    {
        $vbulletin->GPC['forumid'] = $announcementinfo['forumid'];
    }
    $announcementinfo = array_merge($announcementinfo , convert_bits_to_array($announcementinfo['announcementoptions'], $vbulletin->bf_misc_announcementoptions));

    // verify that the visiting user has permission to view this announcement
    if (($announcementinfo['startdate'] > TIMENOW OR $announcementinfo['enddate'] < TIMENOW) AND !can_moderate($vbulletin->GPC['forumid'], 'canannounce'))
    {
        // announcement date is out of range and user is not a moderator
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }

    $forumlist = '';
    if ($announcementinfo['forumid'] > -1 OR $vbulletin->GPC['forumid'])
    {
        $foruminfo = mobiquo_verify_id('forum', $vbulletin->GPC['forumid'], 1, 1);
        $curforumid = $foruminfo['forumid'];
        $forumperms = fetch_permissions($foruminfo['forumid']);

        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
        {
         $return = array(20,'security error (user may not have permission to access this feature)');
         return return_fault($return);
        }

        // check if there is a forum password and if so, ensure the user has it set
        verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
        $forumlist = fetch_forum_clause_sql($foruminfo['forumid'], 'announcement.forumid');
    }
    else if (!$announcementinfo['announcementid'])
    {
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }


    $announcements = $db->query_read_slave("
        SELECT announcement.announcementid, announcement.announcementid AS postid, startdate, enddate, announcement.title, pagetext, announcementoptions, views,
            user.*, userfield.*, usertextfield.*,
            sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
            IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
            " . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
            " . (($vbulletin->userinfo['userid']) ? ", NOT ISNULL(announcementread.announcementid) AS readannouncement" : "") . "
            $hook_query_fields
        FROM  " . TABLE_PREFIX . "announcement AS announcement
        " . (($vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "announcementread AS announcementread ON(announcementread.announcementid = announcement.announcementid AND announcementread.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
        LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid=announcement.userid)
        LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid=announcement.userid)
        LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid=announcement.userid)
        LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = announcement.userid)
        " . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid=user.avatarid)
        LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid=announcement.userid)" : "") . "
        $hook_query_joins
        WHERE
            " . ($vbulletin->GPC['announcementid'] ?
                "announcement.announcementid = " . $vbulletin->GPC['announcementid'] :
                "startdate <= " . TIMENOW . " AND enddate >= " . TIMENOW . " " . (!empty($forumlist) ? "AND $forumlist" : "")
        ) . "
        $hook_query_where
        ORDER BY startdate DESC, announcementid DESC
    ");

    if ($db->num_rows($announcements) == 0)
    { // no announcements
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }
    if (!$vbulletin->options['oneannounce'] AND $vbulletin->GPC['announcementid'] AND !empty($forumlist))
    {
        $anncount = $db->query_first_slave("
        SELECT COUNT(*) AS total
        FROM " . TABLE_PREFIX . "announcement AS announcement
        WHERE startdate <= " . TIMENOW . "
            AND enddate >= " . TIMENOW . "
            AND $forumlist
    ");
        $anncount['total'] = intval($anncount['total']);
        $show['viewall'] = $anncount['total'] > 1 ? true : false;
    }
    else
    {
        $show['viewall'] = false;
    }

    require_once(DIR . '/includes/class_postbit.php');

    $show['announcement'] = true;

    $counter = 0;
    $anncids = array();
    $announcebits = '';
    $announceread = array();

    $postbit_factory = new vB_Postbit_Factory();
    $postbit_factory->registry =& $vbulletin;
    $postbit_factory->forum =& $foruminfo;
    $postbit_factory->cache = array();
    $postbit_factory->bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
    $topic_title = '';
    while ($post = $db->fetch_array($announcements))
    {
        $postbit_obj =& $postbit_factory->fetch_postbit('announcement');

        $post['counter'] = ++$counter;

        $announcebits .= $postbit_obj->construct_postbit($post);
        $anncids[] = $post['announcementid'];
        $announceread[] = "($post[announcementid], " . $vbulletin->userinfo['userid'] . ")";
        $post_content = mobiquo_encode(post_content_clean($post['pagetext'], $html_content));
        if ($html_content)
            $post_content = str_replace("\n", '<br />', $post_content);
        if($announcementinfo['allowhtml'] && !$html_content)
            $post_content = @strip_tags($post_content,"<b>");
        $topic_title = $post['title'];
        list($month, $day, $year) = split('-', $post['startdate']); 
        $timeStamp = mktime(0, 0, 0, $month, $day, $year); 
        $return_post = array(
            'topic_id'          => new xmlrpcval($vbulletin->GPC['announcementid'], 'string'),
            'post_id'           => new xmlrpcval($post['postid'], 'string'),
            'post_title'        => new xmlrpcval(mobiquo_encode($post['title']), 'base64'),
            'post_content'      => new xmlrpcval($post_content, 'base64'),
            'post_author_id'    => new xmlrpcval($post['userid'], 'string'),
            'post_author_name'  => new xmlrpcval(mobiquo_encode($post['username']), 'base64'),
            'post_time'         => new xmlrpcval(mobiquo_time_encode($post['startdate']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($timeStamp, 'string'),
            'post_count'        => new xmlrpcval(0, 'int'),
            'can_delete'        => new xmlrpcval(false, 'boolean'),
            'can_edit'          => new xmlrpcval(false, 'boolean'),
            'attachments'       => new xmlrpcval($return_attachments, 'array')
        );
        $return_post['icon_url'] = new xmlrpcval('','string');
        if($post['avatarurl']){
            $return_post['icon_url']=new xmlrpcval(get_icon_real_url($post['avatarurl']), 'string');
        }
        $return_post['attachment_authority'] = new xmlrpcval(0,'int');
        if(!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment'])){
            $return_post['attachment_authority'] = new xmlrpcval(4, 'int');
        }

        $xmlrpc_return_post = new xmlrpcval($return_post, 'struct');
        $return_posts_list[] = $xmlrpc_return_post;
    }

    if (!empty($anncids))
    {
        $db->shutdown_query("
            UPDATE " . TABLE_PREFIX . "announcement
            SET views = views + 1
            WHERE announcementid IN (" . implode(', ', $anncids) . ")
        ");

        if ($vbulletin->userinfo['userid'])
        {
            $db->shutdown_query("
                REPLACE INTO " . TABLE_PREFIX . "announcementread
                    (announcementid, userid)
                VALUES
                    " . implode(', ', $announceread)
            );
        }
    }

    return new xmlrpcresp(new xmlrpcval(array(
        'sort_order'    => new xmlrpcval($mobiquo_postorder,'string'),
        'issubscribed'  => new xmlrpcval(false,'boolean'),
        'is_subscribed' => new xmlrpcval(false,'boolean'),
        'can_subscribe' => new xmlrpcval(false,'boolean'),
        'total_post_num'=> new xmlrpcval(1,'int'),
        'forum_id'      => new xmlrpcval('-1','string'),
        'topic_id'      => new xmlrpcval($vbulletin->GPC['announcementid'], 'string'),
        'topic_title'   => new xmlrpcval(mobiquo_encode($topic_title), 'base64'),
        'position'      => new xmlrpcval(1, 'int'),
        'can_upload'    => new xmlrpcval(false,'boolean'),
        'can_delete'    => new xmlrpcval(false,'boolean'),
        'can_reply'     => new xmlrpcval(false,'boolean'),
        'can_close'     => new xmlrpcval(false,'boolean'),
        'can_sticky'    => new xmlrpcval(false,'boolean'),
        'can_stick'     => new xmlrpcval(false,'boolean'),
        'is_closed'     => new xmlrpcval(false ,'boolean'),
        'posts'         => new xmlrpcval($return_posts_list,'array'),
    ), 'struct'));
}

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
defined('CWD1') or exit;

defined('IN_MOBIQUO') or exit;
require_once(CWD1. '/include/function_text_parse.php');

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'showthread');
define('CSRF_PROTECTION', false);
$phrasegroups = array(
    'posting',
    'postbit',
    'showthread',
    'inlinemod',
    'reputationlevel'
);

// get special data templates from the datastore
$specialtemplates = array(
    'smiliecache',
    'bbcodecache',
    'mailqueue',
    'bookmarksitecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
    'ad_showthread_beforeqr',
    'ad_showthread_firstpost',
    'ad_showthread_firstpost_start',
    'ad_showthread_firstpost_sig',
    'forumdisplay_loggedinuser',
    'forumrules',
    'im_aim',
    'im_icq',
    'im_msn',
    'im_yahoo',
    'im_skype',
    'postbit',
    'postbit_wrapper',
    'postbit_attachment',
    'postbit_attachmentimage',
    'postbit_attachmentthumbnail',
    'postbit_attachmentmoderated',
    'postbit_deleted',
    'postbit_ignore',
    'postbit_ignore_global',
    'postbit_ip',
    'postbit_onlinestatus',
    'postbit_reputation',
    'bbcode_code',
    'bbcode_html',
    'bbcode_php',
    'bbcode_quote',
    'SHOWTHREAD',
    'showthread_list',
    'showthread_similarthreadbit',
    'showthread_similarthreads',
    'showthread_quickreply',
    'showthread_bookmarksite',
    'tagbit',
    'tagbit_wrapper',
    'polloptions_table',
    'polloption',
    'polloption_multiple',
    'pollresults_table',
    'pollresult',
    'threadadmin_imod_menu_post',
    'editor_css',
    'editor_clientscript',
    'editor_jsoptions_font',
    'editor_jsoptions_size',
);

// pre-cache templates used by specific actions
$actiontemplates = array();
require_once('./global.php');
require_once(DIR .'/includes/functions_bigthree.php');
require_once(DIR .'/includes/class_postbit.php');
require_once(DIR .'/includes/functions_user.php');

function get_thread_func($xmlrpc_params)
{
    global $db, $vbulletin, $html_content;

    $params = php_xmlrpc_decode($xmlrpc_params);

    if(!$params[0])
    {
        $return = array( 2,'No thread id param.');
        return return_fault($return);
    }
    
    $threadid= $params[0];
    if(isset($params[1]) && $params[1] >= 0) {
        $start_num = $params[1] ;
    } else {
        $start_num = 0;
    }
    
    if(isset($params[2])) {
        $end_num = $params[2];
    } else {
        $end_num = 19;
    }
    
    $html_content = isset($params[3]) && $params[3];
    
    return get_thread_content($threadid, $start_num, $end_num, 0);
}

function get_thread_by_unread_func($xmlrpc_params)
{
    global $db, $vbulletin, $html_content;

    $params = php_xmlrpc_decode($xmlrpc_params);

    if(!$params[0])
    {
        $return = array(2, 'No thread id param.');
        return return_fault($return);
    }
    
    $threadid = $params[0];
    if(isset($params[1]) && $params[1] >= 0) {
        $perpage = $params[1];
    } else {
        $perpage = 20;
    }

    $threadinfo = mobiquo_verify_id('thread', $threadid, 1, 1);

    if(!$threadinfo['threadid'])
    {
        $return = array(2, 'Thread not exist.');
        return return_fault($return);
    }

    if(!is_array($threadinfo)){
        $return = array(4, 'Invalid thread id ' . $threadid);
        return return_fault($return);
    }
    
    if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
    {
        $vbulletin->userinfo['lastvisit'] = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
    }
    else if (($tview = intval(fetch_bbarray_cookie('thread_lastview', $threadid))) > $vbulletin->userinfo['lastvisit'])
    {
        $vbulletin->userinfo['lastvisit'] = $tview;
    }

    $coventry = fetch_coventry('string');
    $posts = $db->query_first("
        SELECT MIN(postid) AS postid
        FROM " . TABLE_PREFIX . "post
        WHERE threadid = $threadinfo[threadid]
            AND visible = 1
            AND dateline > " . intval($vbulletin->userinfo['lastvisit']) . "
            ". ($coventry ? "AND userid NOT IN ($coventry)" : "") . "
        LIMIT 1
    ");

    if ($posts['postid'])
    {
        $vbulletin->GPC['postid'] = $posts['postid'];
    }
    else
    {
        $vbulletin->GPC['postid']= $threadinfo['lastpostid'];
    }
    
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
        }
    }

    // set post order
    if ($vbulletin->userinfo['postorder'] == 0)
    {
        $postorder = '';
        $vbulletin->GPC['pagenumber'] = ceil(($threadinfo['replycount'] + 1) / $perpage);
        $position = $threadinfo['replycount'] + 1;
    }
    else
    {
        $postorder = 'DESC';
        $vbulletin->GPC['pagenumber'] = 1;
        $position = 1;
    }

    if (!empty($postid) AND $threadedmode == 0)
    {
        $postinfo = mobiquo_verify_id('post', $postid, 1, 1);
        $threadid = $postinfo['threadid'];

        $getpagenum = $db->query_first("
            SELECT COUNT(*) AS posts
            FROM " . TABLE_PREFIX . "post AS post
            WHERE threadid = $threadid AND visible = 1
            AND dateline " . iif(!$postorder, '<=', '>=') . " $postinfo[dateline]
        ");

        $vbulletin->GPC['pagenumber'] = ceil($getpagenum['posts'] / $perpage);
        $position = $getpagenum['posts'];
    }
    
    $limitlower = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
    $limitupper = ($vbulletin->GPC['pagenumber']) * $perpage - 1;
    $html_content = isset($params[2]) && $params[2];
    
    return get_thread_content($threadid, $limitlower, $limitupper, $position);
}

function get_thread_by_post_func($xmlrpc_params)
{
    global $db, $vbulletin, $html_content;

    $params = php_xmlrpc_decode($xmlrpc_params);

    if(!$params[0])
    {
        $return = array( 2,'No post id param.');
        return return_fault($return);
    }
    
    $postid= $params[0];
    if(isset($params[1]) && $params[1] >= 0) {
        $perpage = $params[1] ;
    } else{
        $perpage = 20;
    }

    // Init post/thread/forum values
    $postinfo = array();
    $threadinfo = array();
    $foruminfo = array();
    $vbulletin->GPC['postid'] = $params[0];
    // automatically query $postinfo, $threadinfo & $foruminfo if $threadid exists
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
        }
    }

    // set post order
    if ($vbulletin->userinfo['postorder'] == 0)
    {
        $postorder = '';
        $vbulletin->GPC['pagenumber'] = ceil(($threadinfo['replycount'] + 1) / $perpage);
        $position = $threadinfo['replycount'] + 1;
    }
    else
    {
        $postorder = 'DESC';
        $vbulletin->GPC['pagenumber'] = 1;
        $position = 1;
    }

    if (!empty($postid) AND $threadedmode == 0)
    {
        $postinfo = mobiquo_verify_id('post', $postid, 1, 1);
        if(!is_array($postinfo)){
            return $postinfo ;
        }
        $threadid = $postinfo['threadid'];

        $getpagenum = $db->query_first("
            SELECT COUNT(*) AS posts
            FROM " . TABLE_PREFIX . "post AS post
            WHERE threadid = $threadid AND visible = 1
            AND dateline " . iif(!$postorder, '<=', '>=') . " $postinfo[dateline]
        ");

        $vbulletin->GPC['pagenumber'] = ceil($getpagenum['posts'] / $perpage);
        $position = $getpagenum['posts'];
    }
    
    $limitlower = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;
    $limitupper = ($vbulletin->GPC['pagenumber']) * $perpage - 1;
    $html_content = isset($params[2]) && $params[2];
    
    return get_thread_content($threadid, $limitlower, $limitupper, $position);
}

function get_thread_content($threadid, $start_num, $end_num, $position = 0)
{
    global $db, $vbulletin, $permissions, $html_content, $forum, $show, $postids;

    $posts_list = array();

    list($start_num, $post_num, $page) = process_page($start_num, $end_num);
    $end_num = $post_num + $start_num -1;

    $vbulletin->input->clean_array_gpc('r', array(
        'perpage'    => TYPE_UINT,
        'pagenumber' => TYPE_UINT,
        'highlight'  => TYPE_STR,
        'posted'     => TYPE_BOOL,
    ));

    // *********************************************************************************
    // set $threadedmode (continued from global.php)
    if ($vbulletin->options['allowthreadedmode'] AND !$show['search_engine'])
    {
        if (!isset($threadedmode))
        {
            // Set threaded mode from user options if it doesn't exist in cookie or url passed form
            DEVDEBUG('$threadedmode is empty');
            if ($vbulletin->userinfo['threadedmode'] == 3)
            {
                $threadedmode = 0;
            }
            else
            {
                $threadedmode = $vbulletin->userinfo['threadedmode'];
            }
        }

        switch ($threadedmode)
        {
            case 1:
                $show['threadedmode'] = true;
                $show['hybridmode'] = false;
                $show['linearmode'] = false;
                break;
            case 2:
                $show['threadedmode'] = false;
                $show['hybridmode'] = true;
                $show['linearmode'] = false;
                break;
            default:
                $show['threadedmode'] = false;
                $show['hybridmode'] = false;
                $show['linearmode'] = true;
                break;
        }
    }
    else
    {
        DEVDEBUG('Threadedmode disabled by admin');
        $threadedmode = 0;
        $vbulletin->options['allowthreadedmode'] = false;
        $show['threadedmode'] = false;
        $show['linearmode'] = true;
        $show['hybridmode'] = false;
    }

    // make an alternate class for the selected threadedmode
    $modeclass = array();
    for ($i = 0; $i < 3; $i++)
    {
        $modeclass["$i"] = iif($i == $threadedmode, 'alt2', 'alt1');
    }


    $onload = '';

    // *********************************************************************************
    // set $perpage

    $perpage = sanitize_maxposts($vbulletin->GPC['perpage']);

    // *********************************************************************************
    // set post order
    if ($vbulletin->userinfo['postorder'] == 0)
    {
        $postorder = '';
    }
    else
    {
        $postorder = 'DESC';
    }

    // *********************************************************************************
    // get thread info
    $vbulletin->options['threadsubscribed'] = 1;
    $thread = mobiquo_verify_id('thread', $threadid, 1, 1);
    if(!is_array($thread)){
        return $thread;
    }
    $threadinfo =& $thread;
    $presentthreadid = $thread['threadid'];

    // *********************************************************************************
    // check for visible / deleted thread
    if (!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))
    {
        $return = array(6, "Thread (ID: $threadid) was invisible to you!");
        return return_fault($return);
        //    eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
    }
    
    if ($thread['isdeleted'] AND !can_moderate($thread['forumid']))
    {
        $return = array(6, "Thread (ID: $threadid) was deleted!");
        return return_fault($return);
        //    eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
    }


    // *********************************************************************************
    // jump page if thread is actually a redirect
    $is_moved = false;
    if ($thread['open'] == 10)
    {
    	  if($thread['pollid'] != 0){
        		$is_moved = true;
    		}
    		$thread = fetch_threadinfo($threadinfo['pollid']);
        //exec_header_redirect('showthread.php?' . $vbulletin->session->vars['sessionurl_js'] . "t=$thread[pollid]");
    }

    // *********************************************************************************
    // Tachy goes to coventry
    if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
    {
        $return = array(6, "Thread (ID: $threadid) poster (ID: $thread[postuserid]) is in coventry!");
        return return_fault($return);
        //    eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
    }

    // *********************************************************************************
    // do word wrapping for the thread title
    if ($vbulletin->options['wordwrap'] != 0)
    {
        $thread['title'] = fetch_word_wrapped_string($thread['title']);
    }

    $thread['title'] = fetch_censored_text($thread['title']);

    // *********************************************************************************
    // words to highlight from the search engine
    if (!empty($vbulletin->GPC['highlight']))
    {
        $highlight = preg_replace('#\*+#s', '*', $vbulletin->GPC['highlight']);
        if ($highlight != '*')
        {
            $regexfind = array('\*', '\<', '\>');
            $regexreplace = array('[\w.:@*/?=]*?', '<', '>');
            $highlight = preg_quote(strtolower($highlight), '#');
            $highlight = explode(' ', $highlight);
            $highlight = str_replace($regexfind, $regexreplace, $highlight);
            foreach ($highlight AS $val)
            {
                if ($val = trim($val))
                {
                    $replacewords[] = htmlspecialchars_uni($val);
                }
            }
        }
    }

    // *********************************************************************************
    // make the forum jump in order to fill the forum caches
    $curforumid = $thread['forumid'];
    //construct_forum_jump();

    // *********************************************************************************
    // get forum info
    $forum = fetch_foruminfo($thread['forumid']);
    $foruminfo =& $forum;

    // *********************************************************************************
    // check forum permissions
    $forumperms = fetch_permissions($thread['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
        $return = array(20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }
    
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    {
        $return = array( 20,'security error (user may not have permission to access this feature)');
        return return_fault($return);
    }

    // *********************************************************************************
    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

    // *********************************************************************************
    // get ignored users
    $ignore = array();
    if (trim($vbulletin->userinfo['ignorelist']))
    {
        $ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($ignorelist AS $ignoreuserid)
        {
            $ignore["$ignoreuserid"] = 1;
        }
    }
    DEVDEBUG('ignored users: ' . implode(', ', array_keys($ignore)));

    // *********************************************************************************
    // filter out deletion notices if can't be seen
    if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] OR can_moderate($threadinfo['forumid']))
    {
        $deljoin = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND deletionlog.type = 'post')";
    }
    else
    {
        $deljoin = '';
    }

    $show['viewpost'] = (can_moderate($threadinfo['forumid'])) ? true : false;
    $show['managepost'] = iif(can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'), true, false);
    $show['approvepost'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts')) ? true : false;
    $show['managethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads')) ? true : false;
    $show['approveattachment'] = (can_moderate($threadinfo['forumid'], 'canmoderateattachments')) ? true : false;
    $show['inlinemod'] = (!$show['threadedmode'] AND ($show['managethread'] OR $show['managepost'] OR $show['approvepost'])) ? true : false;
    $show['spamctrls'] = ($show['inlinemod'] AND $show['managepost']);
    $url = $show['inlinemod'] ? SCRIPTPATH : '';

    // build inline moderation popup
    if ($show['popups'] AND $show['inlinemod'])
    {
        //eval('$threadadmin_imod_menu_post = "' . fetch_template('threadadmin_imod_menu_post') . '";');
    }
    else
    {
        $threadadmin_imod_menu_post = '';
    }

    // *********************************************************************************
    // find the page that we should be on to display this post

    // *********************************************************************************
    // update views counter
    if ($vbulletin->options['threadviewslive'])
    {
        // doing it as they happen; for optimization purposes, this cannot use a DM!
        $db->shutdown_query("
        UPDATE " . TABLE_PREFIX . "thread
        SET views = views + 1
        WHERE threadid = " . intval($threadinfo['threadid'])
        );
    }
    else
    {
        // or doing it once an hour
        $db->shutdown_query("
        INSERT INTO " . TABLE_PREFIX . "threadviews (threadid)
        VALUES (" . intval($threadinfo['threadid']) . ')'
        );
    }

    // *********************************************************************************
    // display ratings if enabled
    $show['rating'] = false;
    if ($forum['allowratings'] == 1)
    {
        if ($thread['votenum'] > 0)
        {
            $thread['voteavg'] = vb_number_format($thread['votetotal'] / $thread['votenum'], 2);
            $thread['rating'] = intval(round($thread['votetotal'] / $thread['votenum']));

            if ($thread['votenum'] >= $vbulletin->options['showvotes'])
            {
                $show['rating'] = true;
            }
        }

        devdebug("threadinfo[vote] = $threadinfo[vote]");

        if ($threadinfo['vote'])
        {
            $voteselected["$threadinfo[vote]"] = 'selected="selected"';
            $votechecked["$threadinfo[vote]"] = 'checked="checked"';
        }
        else
        {
            $voteselected[0] = 'selected="selected"';
            $votechecked[0] = 'checked="checked"';
        }
    }

    if ($vbulletin->GPC['pagenumber'] < 1)
    {
        $vbulletin->GPC['pagenumber'] = 1;
    }
    else if ($vbulletin->GPC['pagenumber'] > ceil(($thread['replycount'] + 1) / $perpage))
    {
        $vbulletin->GPC['pagenumber'] = ceil(($thread['replycount'] + 1) / $perpage);
    }

    $limitlower = $start_num;
    $limitupper = $end_num;
    $counter = 0;
    if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
    {
        $threadview = max($threadinfo['threadread'], $threadinfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
    }
    else
    {
        $threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));
        if (!$threadview)
        {
            $threadview = $vbulletin->userinfo['lastvisit'];
        }
    }
    $threadinfo['threadview'] = intval($threadview);
    $displayed_dateline = 0;

    ################################################################################
    ############################### SHOW POLL ######################################
    ################################################################################
    $poll = '';
    if ($thread['pollid'])
    {
        $pollbits = '';
        $counter = 1;
        $pollid = $thread['pollid'];

        $show['editpoll'] = iif(can_moderate($threadinfo['forumid'], 'caneditpoll'), true, false);

        // get poll info
        $pollinfo = $db->query_first_slave("
            SELECT *
            FROM " . TABLE_PREFIX . "poll
            WHERE pollid = $pollid
        ");
        
        require_once(DIR . '/includes/class_bbcode.php');
        
        $bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

        $pollinfo['question'] = $bbcode_parser->parse(unhtmlspecialchars($pollinfo['question']), $forum['forumid'], true);

        $splitoptions = explode('|||', $pollinfo['options']);
        $splitvotes = explode('|||', $pollinfo['votes']);

        $showresults = 0;
        $uservoted = 0;
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
        {
            $nopermission = 1;
        }

        if (!$pollinfo['active'] OR !$thread['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0) OR $nopermission)
        {
            //thread/poll is closed, ie show results no matter what
            $showresults = 1;
        }
        else
        {
            //get userid, check if user already voted
            $voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
            if ($voted)
            {
                $uservoted = 1;
            }
        }

        if ($pollinfo['timeout'] AND !$showresults)
        {
            $pollendtime = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
            $pollenddate = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
            $show['pollenddate'] = true;
        }
        else
        {
            $show['pollenddate'] = false;
        }

        foreach ($splitvotes AS $index => $value)
        {
            $pollinfo['numbervotes'] += $value;
        }

        if ($vbulletin->userinfo['userid'] > 0)
        {
            $pollvotes = $db->query_read_slave("
            SELECT voteoption
            FROM " . TABLE_PREFIX . "pollvote
            WHERE userid = " . $vbulletin->userinfo['userid'] . " AND pollid = $pollid
        ");
            if ($db->num_rows($pollvotes) > 0)
            {
                $uservoted = 1;
            }
        }

        if ($showresults OR $uservoted)
        {
            if ($uservoted)
            {
                $uservote = array();
                while ($pollvote = $db->fetch_array($pollvotes))
                {
                    $uservote["$pollvote[voteoption]"] = 1;
                }
            }
        }

        $option['open'] = $stylevar['left'][0];
        $option['close'] = $stylevar['right'][0];

        foreach ($splitvotes AS $index => $value)
        {
            $arrayindex = $index + 1;
            $option['uservote'] = iif($uservote["$arrayindex"], true, false);
            $option['question'] = $bbcode_parser->parse($splitoptions["$index"], $forum['forumid'], true);

            // public link
            if ($pollinfo['public'] AND $value)
            {
                $option['votes'] = '<a href="poll.php?' . $vbulletin->session->vars['sessionurl'] . 'do=showresults&amp;pollid=' . $pollinfo['pollid'] . '">' . vb_number_format($value) . '</a>';
            }
            else
            {
                $option['votes'] = vb_number_format($value);   //get the vote count for the option
            }

            $option['number'] = $counter;  //number of the option

            //Now we check if the user has voted or not
            if ($showresults OR $uservoted)
            { // user did vote or poll is closed

                if ($value <= 0)
                {
                    $option['percent'] = 0;
                }
                else if ($pollinfo['multiple'])
                {
                    $option['percent'] = vb_number_format(($value < $pollinfo['voters']) ? $value / $pollinfo['voters'] * 100 : 100, 2);
                }
                else
                {
                    $option['percent'] = vb_number_format(($value < $pollinfo['numbervotes']) ? $value / $pollinfo['numbervotes'] * 100 : 100, 2);
                }

                $option['graphicnumber'] = $option['number'] % 6 + 1;
                $option['barnumber'] = round($option['percent']) * 2;
                $option['remainder'] = 201 - $option['barnumber'];

                // Phrase parts below
                if ($nopermission)
                {
                    $pollstatus = $vbphrase['you_may_not_vote_on_this_poll'];
                }
                else if ($showresults)
                {
                    $pollstatus = $vbphrase['this_poll_is_closed'];
                }
                else if ($uservoted)
                {
                    $pollstatus = $vbphrase['you_have_already_voted_on_this_poll'];
                }
                //eval('$pollbits .= "' . fetch_template('pollresult') . '";');
            }
            else
            {
                if ($pollinfo['multiple'])
                {
                    //eval('$pollbits .= "' . fetch_template('polloption_multiple') . '";');
                }
                else
                {
                    //eval('$pollbits .= "' . fetch_template('polloption') . '";');
                }
            }
            $counter++;
        }

        if ($pollinfo['multiple'])
        {
            $pollinfo['numbervotes'] = $pollinfo['voters'];
            $show['multiple'] = true;
        }

        if ($pollinfo['public'])
        {
            $show['publicwarning'] = true;
        }
        else
        {
            $show['publicwarning'] = false;
        }

        $displayed_dateline = $threadinfo['lastpost'];

        if ($showresults OR $uservoted)
        {
            //eval('$poll = "' . fetch_template('pollresults_table') . '";');
        }
        else
        {
            //eval('$poll = "' . fetch_template('polloptions_table') . '";');
        }

    }

    // work out if quickreply should be shown or not
    if (
    $vbulletin->options['quickreply']
    AND
    !$thread['isdeleted'] AND !is_browser('netscape') AND $vbulletin->userinfo['userid']
    AND (
    ($vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown'])
    OR
    ($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])
    ) AND
    ($thread['open'] OR can_moderate($threadinfo['forumid'], 'canopenclose'))
    )
    {
        $show['quickreply'] = true;
    }
    else
    {
        $show['quickreply'] = false;
        $show['wysiwyg'] = 0;
        $quickreply = '';
    }
    $show['largereplybutton'] = (!$thread['isdeleted'] AND !$show['threadedmode'] AND $forum['allowposting'] AND !$show['search_engine']);
    if (!$forum['allowposting'])
    {
        $show['quickreply'] = false;
    }

    $show['multiquote_global'] = ($vbulletin->options['multiquote'] AND $vbulletin->userinfo['userid']);
    if ($show['multiquote_global'])
    {
        $vbulletin->input->clean_array_gpc('c', array('vbulletin_multiquote' => TYPE_STR));
        $vbulletin->GPC['vbulletin_multiquote'] = explode(',', $vbulletin->GPC['vbulletin_multiquote']);
    }

    // post is cachable if option is enabled, last post is newer than max age, and this user
    // isn't showing a sessionhash
    $post_cachable = (
    $vbulletin->options['cachemaxage'] > 0 AND
    (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $thread['lastpost'] AND
    $vbulletin->session->vars['sessionurl'] == '');
    
    $saveparsed = '';
    $save_parsed_sigs = '';

    ################################################################################
    ####################### SHOW THREAD IN LINEAR MODE #############################
    ################################################################################
    //if ($threadedmode == 0)
    //{
    // allow deleted posts to not be counted in number of posts displayed on the page;
    // prevents issue with page count on forum display being incorrect
    $ids = '';
    $lastpostid = 0;
    $hook_query_joins = $hook_query_where = '';
    $threadid = $thread['threadid'];

    if (empty($deljoin) AND !$show['approvepost'])
    {
        $totalposts = $threadinfo['replycount'] + 1;

        if (can_moderate($thread['forumid']))
        {
            $coventry = '';
        }
        else
        {
            $coventry = fetch_coventry('string');
        }

        $getpostids = $db->query_read("
            SELECT post.postid
            FROM " . TABLE_PREFIX . "post AS post
            $hook_query_joins
            WHERE post.threadid = $threadid
                AND post.visible = 1
                " . ($coventry ? "AND post.userid NOT IN ($coventry)" : '') . "
                $hook_query_where
            ORDER BY post.dateline $postorder
            LIMIT $start_num, $post_num

        ");
        //  LIMIT $start_num, $post_num

        //  LIMIT $limitlower, $perpage

        while ($post = $db->fetch_array($getpostids))
        {
            if (!isset($qrfirstpostid))
            {
                $qrfirstpostid = $post['postid'];
            }
            $qrlastpostid = $post['postid'];
            $ids .= ',' . $post['postid'];

        }
        $db->free_result($getpostids);

        $lastpostid = $qrlastpostid;
    }
    else
    {
        $getpostids = $db->query_read("
            SELECT post.postid, post.visible, post.userid
            FROM " . TABLE_PREFIX . "post AS post
            $hook_query_joins
            WHERE post.threadid = $threadid
                AND post.visible IN (1
                " . (!empty($deljoin) ? ",2" : "") . "
                " . ($show['approvepost'] ? ",0" : "") . "
                )
                $hook_query_where
            ORDER BY post.dateline $postorder
        ");
        
        $totalposts = 0;
        if ($limitlower != 0)
        {
            $limitlower++;
        }
        $return_posts_list = array();
        while ($post = $db->fetch_array($getpostids))
        {
            if (!isset($qrfirstpostid))
            {
                $qrfirstpostid = $post['postid'];
            }
            
            $qrlastpostid = $post['postid'];
            if ($post['visible'] == 1 AND !in_coventry($post['userid']))
            {
                $totalposts++;
            }
            
            if ($totalposts < $limitlower OR $totalposts > ($limitupper+1))
            {
                continue;
            }
            
            if($totalposts < $start_num  or $start_num > $end_num) {
                $return = array(3,'out of range');
                return return_fault($return);
            }
            $posts_list[total_post_num] = $totalposts;

            // remember, these are only added if they're going to be displayed
            $ids .= ',' . $post['postid'];
            $lastpostid = $post['postid'];
        }
        $db->free_result($getpostids);
    }
    $postids = "post.postid IN (0" . $ids . ")";

    // load attachments
    if ($thread['attach'])
    {
        $attachments = $db->query_read("
            SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
                postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
                attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
            FROM " . TABLE_PREFIX . "attachment
            LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
            WHERE postid IN (-1" . $ids . ")
            ORDER BY attachmentid
        ");
        $postattach = array();
        while ($attachment = $db->fetch_array($attachments))
        {
            if (!$attachment['build_thumbnail'])
            {
                $attachment['hasthumbnail'] = false;
            }
            $postattach["$attachment[postid]"]["$attachment[attachmentid]"] = $attachment;
        }
    }

    $hook_query_fields = $hook_query_joins = '';
    if(isset($vbulletin->options['vbseo_like_enable']) && $vbulletin->options['vbseo_like_enable'])
    {
        $hook = vBulletinHook::fetch_hook('showthread_query');
        preg_match('/(if\(defined\(\'VBSEO_ENABLED\'\) && VBSEO_ENABLED\).*?}.*?})/siU',$hook,$matches);
        $hook = $matches[1];
        eval($hook);    
    }

    $posts = $db->query_read("
        SELECT
            post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
            user.*, userfield.*, usertextfield.*,
            " . iif($forum['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
            " . iif($vbulletin->options['avatarenabled'], 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight,') . "
            " . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? 'spamlog.postid AS spamlog_postid,' : '') . "
            " . iif($deljoin, 'deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason,') . "
            editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline,
            editlog.reason AS edit_reason, editlog.hashistory,
            postparsed.pagetext_html, postparsed.hasimages,
            sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
            sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight,
            IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
            " . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
            $hook_query_fields
        FROM " . TABLE_PREFIX . "post AS post
        LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
        LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
        LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
        " . iif($forum['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
        " . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
        " . ((can_moderate($thread['forumid'], 'canmoderateposts') OR can_moderate($thread['forumid'], 'candeleteposts')) ? "LEFT JOIN " . TABLE_PREFIX . "spamlog AS spamlog ON(spamlog.postid = post.postid)" : '') . "
        $deljoin
        LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
        LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
        LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
        LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
        $hook_query_joins
        WHERE $postids
        ORDER BY post.dateline $postorder
    ");

    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
    {
        $vbulletin->options['viewattachedimages'] = 0;
        $vbulletin->options['attachthumbs'] = 0;
    }

    $postcount = $start_num;
    if ($position == 0) $position = $start_num + 1;

    $postbits = '';

    $postbit_factory =& new vB_Postbit_Factory();
    $postbit_factory->registry =& $vbulletin;
    $postbit_factory->forum =& $foruminfo;
    $postbit_factory->thread =& $thread;
    $postbit_factory->cache = array();
    $postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
    $show['deleteposts'] = can_moderate($threadinfo['forumid'], 'candeleteposts') ? true : false;
    $show['editthread'] = can_moderate($threadinfo['forumid'], 'caneditthreads') ? true : false;
    $show['movethread'] = (can_moderate($threadinfo['forumid'], 'canmanagethreads') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canmove'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) ? true : false;
    $show['openclose'] = (can_moderate($threadinfo['forumid'], 'canopenclose') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['canopenclose'] AND $threadinfo['postuserid'] == $vbulletin->userinfo['userid'])) ? true : false;
    $show['moderatethread'] = (can_moderate($threadinfo['forumid'], 'canmoderateposts') ? true : false);
    $show['deletethread'] = (($threadinfo['visible'] != 2 AND can_moderate($threadinfo['forumid'], 'candeleteposts')) OR can_moderate($threadinfo['forumid'], 'canremoveposts') OR ($forumperms & $vbulletin->bf_ugp_forumpermissions['candeletepost'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['candeletethread'] AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid'] AND ($vbulletin->options['edittimelimit'] == 0 OR $threadinfo['dateline'] > (TIMENOW - ($vbulletin->options['edittimelimit'] * 60))))) ? true : false;
    $show['adminoptions'] = ($show['editpoll'] OR $show['movethread'] OR $show['deleteposts'] OR $show['editthread'] OR $show['managethread'] OR $show['openclose'] OR $show['deletethread']) ? true : false;

    if($show['adminoptions']){
        require_once(DIR . '/includes/adminfunctions.php');
        require_once(DIR . '/includes/functions_banning.php');
    }

    while ($post = $db->fetch_array($posts))
    {
        if ($tachyuser = in_coventry($post['userid']) AND !can_moderate($thread['forumid']))
        {
            continue;
        }

        $post['postcount'] = ++$postcount;

        if ($tachyuser)
        {
            $fetchtype = 'post_global_ignore';
        }
        else if ($ignore["$post[userid]"])
        {
            $fetchtype = 'post_ignore';
        }
        else if ($post['visible'] == 2)# OR ($thread['visible'] == 2 AND $postcount == 1))
        {
            $fetchtype = 'post_deleted';
        }
        else
        {
            $fetchtype = 'post';
        }

        $postbit_obj =& $postbit_factory->fetch_postbit($fetchtype);
        if ($fetchtype == 'post')
        {
            $postbit_obj->highlight =& $replacewords;
        }
        $postbit_obj->cachable = $post_cachable;

        $post['islastshown'] = ($post['postid'] == $lastpostid);
        $post['attachments'] =& $postattach["$post[postid]"];

        $parsed_postcache = array('text' => '', 'images' => 1, 'skip' => false);
        //advanced quote
        $post['pagetext'] = preg_replace('/\[quote=(.*?);(.*?)\](.*?)/si', '[quote name="$1" post=$2]$3',$post['pagetext']);
        $post['pagetext'] = mobiquo_handle_bbcode_attach($post['pagetext'] ,true,$post);
        $mobiquo_attachments = $post['attachments'];
        $postbits .= $postbit_obj->construct_postbit($post);

        if ( $fetchtype == 'post' OR ($fetchtype == 'post_deleted' AND $show['viewpost']))
        {
            $return_attachments = array();

            if(is_array($mobiquo_attachments)){
                foreach($mobiquo_attachments as $attach) {
                    $attachment_url = $attachment_thumbnail_url = "";
                    preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachmentlinks]),$image_attachment_matchs);
                    preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[otherattachments]),$other_attachment_matchs);
                    preg_match_all('/href=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\".+img.+?src=\"(.+attachmentid='.$attach[attachmentid].'.+?)\"/s',unhtmlspecialchars($post[thumbnailattachments]),$thumbnail_attachment_matchs);
                    preg_match_all('/src=\"([^\s]+attachmentid='.$attach[attachmentid].'.+?)\"/',unhtmlspecialchars($post[imageattachments]),$small_image_attachment_matchs);
                    
                    if (in_array(strtolower(pathinfo($attach['filename'], PATHINFO_EXTENSION)), array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp'))) {
                        $type = "image";
                    } else {
                        $type = strtolower(pathinfo($attach['filename'], PATHINFO_EXTENSION));
                    }

                    if($image_attachment_matchs[1][0]) {
                        $type = "image";
                        $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$image_attachment_matchs[1][0];
                    }
                    if($other_attachment_matchs[1][0]){
                        $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$other_attachment_matchs[1][0];
                    }
                    if($small_image_attachment_matchs[1][0]) {
                        $type = "image";
                        $attachment_thumbnail_url= $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
                        $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$small_image_attachment_matchs[1][0];
                    }
                    if($thumbnail_attachment_matchs[1][0]){
                        $type = "image";

                        $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[1][0];
                        $attachment_thumbnail_url = $GLOBALS[vbulletin]->options[bburl].'/'.$thumbnail_attachment_matchs[2][0];
                    }

                    if(empty($attachment_url)){
                        $attachment_url = $GLOBALS[vbulletin]->options[bburl].'/'."attachment.php?attachmentid=".$attach[attachmentid];
                    }

                    $return_attachment = new xmlrpcval(array(
                        'filename'      => new xmlrpcval($attach['filename'], "base64"),
                        'filesize'      => new xmlrpcval($attach['filesize'], 'int'),
                        'url'           => new xmlrpcval(unhtmlspecialchars($attachment_url), "string"),
                        'thumbnail_url' => new xmlrpcval(unhtmlspecialchars($attachment_thumbnail_url), "string"),
                        'content_type'  => new xmlrpcval($type, "string")
                    ), 'struct');
                    
                    array_push($return_attachments,$return_attachment);
                }
            }

            if ($foruminfo['allowhtml'])
            {
                require_once(DIR . '/includes/functions_wysiwyg.php');
                $post['pagetext'] = str_replace("\n",'<br>',$post['pagetext']);
                $post['pagetext'] = convert_wysiwyg_html_to_bbcode($post['pagetext'], $foruminfo['allowhtml']);
                $post['pagetext'] = preg_replace('/<object.*?<embed[^>]*?src=[\'"]([^\?]*?)\?.*?[\'"].*?<\/embed>.*?<\/object>/s', '[video]$1[/video]', $post['pagetext']);
                $post['pagetext'] = preg_replace('/<embed[^>]*?src=[\'"]([^\?]*?)\?.*?[\'"].*?<\/embed>/s', '[video]$1[/video]', $post['pagetext']);
            }


            if($html_content){
                $a = fetch_tag_list();
                unset($a['option']['quote']);
                unset($a['no_option']['quote']);
                unset($a['option']['url']);
                unset($a['no_option']['url']);
                unset($a['option']['email']);
                unset($a['no_option']['email']);
                
                $vbulletin->options['wordwrap'] = 0;
                
                $post_content = post_content_clean_html($post['pagetext']);
                $post_content = preg_replace("/\[\/img\]/siU",'[/img1]', $post_content);
                $bbcode_parser =& new vB_BbCodeParser($vbulletin, $a, false);
                $post_content = $bbcode_parser->parse( $post_content, $thread['forumid'], false);
                $post_content = preg_replace("/\[\/img1\]/siU",'[/IMG]', $post_content);
                $post_content = str_replace('&quot;', '"', $post_content);
                $post_content = htmlspecialchars_uni($post_content);
                $post_content = preg_replace('/\[(TP_LIGHT)\](.*?)\[\/\1\]/si','<font color="red"><b>$2<b></font>', $post_content);
                $post_content = mobiquo_encode($post_content, '', false);

            } else {
                $post_content = mobiquo_encode(post_content_clean($post['pagetext']));
            }

            $post_content = preg_replace('/\[tp_noparse\](.*?)\[\/tp_noparse\]/sie', "base64_decode('$1')", $post_content);
            if(SHORTENQUOTE == 1 && preg_match('/^(.*\[quote\])(.+)(\[\/quote\].*)$/si', $post_content)){
                $new_content = "";
                $segments = preg_split('/(\[quote\].+\[\/quote\])/isU',$post_content,-1, PREG_SPLIT_DELIM_CAPTURE);

                foreach($segments as $segment){
                    $short_quote = $segment;
                    if(preg_match('/^(\[quote\])(.+)(\[\/quote\])$/si', $segment,$quote_matches)){
                        if(function_exists('mb_strlen') && function_exists('mb_substr')){
                            if(mb_strlen($quote_matches[2], 'UTF-8') > 170){
                                $short_quote = $quote_matches[1].mb_substr($quote_matches[2],0,150,'UTF-8').$quote_matches[3];
                            }
                        }
                        else{
                            if(strlen($quote_matches[2]) > 170){
                                $short_quote = $quote_matches[1].substr($quote_matches[2],0,150).$quote_matches[3];
                            }
                        }
                        $new_content .= $short_quote;
                    } else {
                        $new_content .= $segment;
                    }
                }

                $post_content = $new_content;
            }
            $mobiquo_can_edit = false;
            if(isset($post['editlink']) AND strlen($post['editlink']) > 0){
                $mobiquo_can_edit = true;
            }

            $mobiquo_user_online = (fetch_online_status($post, false)) ? true : false;
            $is_deleted = false;
            if($post['visible'] == 2){
                $is_deleted = true;
            }
            $is_approved = true;
            if($post['visible'] == 0 or (!$thread['visible'] AND $post['postcount'] == 1)){
                $is_approved = false;
            }
            
            $userinfo = fetch_userinfo($post['userid']);
            cache_permissions($userinfo, false);
            
            if($show['adminoptions']){
                
                $mobiquo_can_ban = true;
                if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] OR can_moderate(0, 'canbanusers')))
                {
                    $mobiquo_can_ban = false;
                }

                // check that user has permission to ban the person they want to ban
                if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
                {
                    if (can_moderate(0, '', $userinfo['userid'], $userinfo['usergroupid'] . (trim($userinfo['membergroupids']) ? ",$userinfo[membergroupids]" : ''))
                    OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
                    OR $userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']
                    OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
                    {
                        $mobiquo_can_ban = false;
                    }
                } else {
                    if ($userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
                    OR (function_exists('is_unalterable_user') ? is_unalterable_user($userinfo['userid']) : ($userinfo['usergroupid'] == 5 OR $userinfo['ismoderator'])))
                    {
                        $mobiquo_can_ban = false;
                    }
                }
            } else {
                $mobiquo_can_ban = false;
            }
            
            $mobiquo_is_ban = false;
            if(!($vbulletin->usergroupcache[$userinfo['usergroupid']]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])){
                $mobiquo_is_ban = true;
            }
 
            $return_post = array(
                'topic_id'          => new xmlrpcval($presentthreadid, 'string'),
                'post_id'           => new xmlrpcval($post['postid'], 'string'),
                'post_title'        => new xmlrpcval(mobiquo_encode($post['title']), 'base64'),
                'post_content'      => new xmlrpcval($post_content, 'base64'),
                'post_author_id'    => new xmlrpcval($post['userid'], 'string'),
                'post_author_name'  => new xmlrpcval(mobiquo_encode($post['postusername']), 'base64'),
                'user_type'         => new xmlrpcval(get_usertype_by_item($post['userid'],$post['usergroupid']), 'base64'),
                'editor_id'         => new xmlrpcval($post['edit_userid'], 'string'),
                'editor_name'       => new xmlrpcval($post['edit_username'], 'base64'),
                'edit_time'         => new xmlrpcval($post['edit_dateline'], 'string'),
                'edit_reason'       => new xmlrpcval(mobiquo_encode($post['edit_reason']), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_time_encode($post['dateline']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($post['dateline'], 'string'),
                'post_count'        => new xmlrpcval($post['postcount'], 'int'),
                'attachments'       => new xmlrpcval($return_attachments, 'array'),
                'is_approved'       => new xmlrpcval($is_approved, 'boolean'),
                'allow_smilies'     => new xmlrpcval($post['allowsmilie'], 'boolean'),
            );
            
            if ($show['deleteposts'])  $return_post['can_delete']    = new xmlrpcval(true, 'boolean');
            if ($is_deleted)           $return_post['is_deleted']    = new xmlrpcval(true, 'boolean');
            if ($show['approvepost'])  $return_post['can_approve']   = new xmlrpcval(true, 'boolean');
            if ($mobiquo_can_ban)      $return_post['can_ban']       = new xmlrpcval(true, 'boolean');
            if ($mobiquo_is_ban)       $return_post['is_ban']        = new xmlrpcval(true, 'boolean');
            if ($show['managethread']) $return_post['can_move']      = new xmlrpcval(true, 'boolean');
            if ($mobiquo_can_edit)     $return_post['can_edit']      = new xmlrpcval(true, 'boolean');
            if ($mobiquo_user_online)  $return_post['is_online']     = new xmlrpcval(true, 'boolean');
            
            
            // vbseo like support
            if(defined('VBSEO_ENABLED') && VBSEO_ENABLED && VBSEO_LIKE_POST && class_exists(vBSEO_UI)  && $forum['vbseo_enable_likes'])
            {
                if (vBSEO_UI::lcache_get(VBSEO_UI_THREAD, $post['threadid'], $post['postid'], 'ownlike'))
                    $return_post['is_liked'] = new xmlrpcval(true, 'boolean');
                if ($post['userid'] != $vbulletin->userinfo['userid'] && $show['member'])
                    $return_post['can_like'] = new xmlrpcval(true, 'boolean');
                
                $like_count  = vBSEO_UI::lcache_get(VBSEO_UI_THREAD, $post['threadid'], $post['postid'], 'count' );
                if ($like_count)
                {
                    $alikes = vBSEO_UI::lcache_get(VBSEO_UI_THREAD, $post['threadid'], $post['postid'], 'likes');
                    $like_list = array();
                    foreach($alikes as $like)
                    {
                        $like_list[] = new xmlrpcval(array(
                            'userid'    => new xmlrpcval($like['l_from_userid'], 'string'),
                            'username'  => new xmlrpcval(mobiquo_encode($like['l_from_username']), 'base64'),
                        ), 'struct');
                    }
                    
                    $return_post['like_count'] = new xmlrpcval($like_count, 'int');
                    $return_post['likes_info'] = new xmlrpcval($like_list, 'array');
                }
            }
            
            if (isset($vbulletin->products['post_thanks']) && $vbulletin->products['post_thanks'] && file_exists(DIR . '/includes/functions_post_thanks.php'))
            {
                require_once(DIR . '/includes/functions_post_thanks.php');
                $thanks = fetch_thanks($post['postid'], '', true);
                if (!post_thanks_off($threadinfo['forumid'], $post, $threadinfo['firstpostid']) && can_thank_this_post($post, $threadinfo['isdeleted'], false) && !thanked_already($post))
                {
                    $return_post['can_thank'] = new xmlrpcval(true, 'boolean');
                }

                if (!empty($thanks))
                {
                    $thank_list = array();
                    foreach ($thanks as $thank)
                    {
                        $thank_list[] = new xmlrpcval(array(
                            'userid'    => new xmlrpcval($thank['userid'], 'string'),
                            'username'  => new xmlrpcval(mobiquo_encode($thank['username']), 'base64'),
                        ), 'struct');
                    }

                    $return_post['thanks_info'] = new xmlrpcval($thank_list, 'array');
                }
            }

            $return_post['icon_url'] = new xmlrpcval('','string');
            if($post['avatarurl']) {
                $return_post['icon_url'] = new xmlrpcval(get_icon_real_url($post['avatarurl']),'string');
            }
            $return_post[attachment_authority] = new xmlrpcval(0, 'int');
            if(!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment'])){
                $return_post[attachment_authority] = new xmlrpcval(4, 'int');
            }

            $xmlrpc_return_post =new xmlrpcval( $return_post,'struct');
            $return_posts_list[] =$xmlrpc_return_post;
            // get first and last post ids for this page (for big reply buttons)
            if (!isset($FIRSTPOSTID))
            {
                $FIRSTPOSTID = $post['postid'];
            }
            $LASTPOSTID = $post['postid'];

            if ($post['dateline'] > $displayed_dateline)
            {
                $displayed_dateline = $post['dateline'];
                if ($displayed_dateline <= $threadview)
                {
                    $updatethreadcookie = true;
                }
            }
        }
    }
    if($vbulletin->userinfo['postorder'] == 0){
        $mobiquo_postorder = 'DATE_ASC';
    }
    else{
        $mobiquo_postorder = 'DATE_DESC';
    }

    if ($thread['pollid'] AND $vbulletin->options['updatelastpost'] AND ($displayed_dateline == $thread['lastpost'] OR $threadview == $thread['lastpost']) AND $pollinfo['lastvote'] > $thread['lastpost'])
    {
        $displayed_dateline = $pollinfo['lastvote'];
    }

    if ((!$vbulletin->GPC['posted'] OR $updatethreadcookie) AND $displayed_dateline AND $displayed_dateline > $threadview)
    {
        mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], $displayed_dateline);
    }

    $mobiquo_can_upload = false;
    if ($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostattachment'] AND $vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['attachmentextensions'])){
        $mobiquo_can_upload = true;
    }

    $mobiquo_can_reply = true;
    if($thread['isdeleted'] OR !$forum['allowposting']){
        $mobiquo_can_reply = false;
    }

    if (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers'])))
    {
        $mobiquo_can_reply = false;
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) AND $vbulletin->userinfo['userid'] == $threadinfo['postuserid']))
    {
        $mobiquo_can_reply = false;
    }
    $is_approved = true;
    if($threadinfo['visible'] == 0){
        $is_approved = false;
    }
    
    $can_subscribe = true;
    if (!$vbulletin->userinfo['userid']
        OR ($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
        OR $vbulletin->userinfo['usergroupid'] == 4
        OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
        $can_subscribe = false;
    }
    
    $return_data = array(
        'total_post_num'=> new xmlrpcval($totalposts, 'int'),
        'forum_id'      => new xmlrpcval($thread['forumid'], 'string'),
        'forum_name'    => new xmlrpcval(mobiquo_encode($foruminfo['title']), 'base64'),
        'topic_id'      => new xmlrpcval($presentthreadid, 'string'),
        'topic_title'   => new xmlrpcval(mobiquo_encode($threadinfo['title']), 'base64'),
        'position'      => new xmlrpcval($position, 'int'),
        'view_number'  => new xmlrpcval($threadinfo['views'], 'int'),
        'can_report'    => new xmlrpcval(true, 'boolean'),
        'can_reply'     => new xmlrpcval($mobiquo_can_reply, 'boolean'),
        'can_upload'    => new xmlrpcval($mobiquo_can_upload, 'boolean'),
        'can_merge'     => new xmlrpcval($show['movethread'], 'boolean'),
        'can_merge_post'=> new xmlrpcval($show['approvepost'], 'boolean'),
        'is_approved'   => new xmlrpcval($is_approved, 'boolean'),
        'is_poll'       => new xmlrpcval($thread['pollid']? true : false, 'boolean'),
        'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
        'is_moved'      => new xmlrpcval($is_moved, 'boolean'),
        'real_topic_id' => new xmlrpcval($thread['threadid'], 'string'),
        'topic_author_id'     => new xmlrpcval($thread['postuserid'], 'string'),
        'topic_author_name'   => new xmlrpcval(mobiquo_encode($thread['postusername']), 'base64'),
        'topic_author_avatar' => new xmlrpcval(mobiquo_get_user_icon($thread['postuserid']), 'string'),
    );
    
    if (defined('NOSHUTDOWNFUNC'))
    {
        exec_shut_down();
    }
    
    $breadcrumbs = array();
    $parentlist = fetch_forum_parent_list($thread['forumid']);
    $breadcrumbids = explode(',', $parentlist);
    $sorted_breadcrumbids = array();
    $size_of_bc = count($breadcrumbids);
    $idx = $size_of_bc;
    while(!($idx < 0))
    {
        $idx--;
        if(isset($breadcrumbids[$idx]))
            $sorted_breadcrumbids[] = $breadcrumbids[$idx];
    }
    foreach($sorted_breadcrumbids as $node_id)
    {
        if($node_id == -1)
            continue;
        $node = fetch_foruminfo($node_id);
        $sub_only = !($node['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']);
        $breadcrumbs[] = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($node_id, 'string'),
            'forum_name'    => new xmlrpcval(mobiquo_encode($node['title']), 'base64'),
            'sub_only'      => new xmlrpcval($sub_only, 'boolean'),
        ), 'struct');
    }

    if(!empty($breadcrumbs))
        $return_data['breadcrumb'] = new xmlrpcval($breadcrumbs, 'array');
    if ($threadinfo['issubscribed'])    $return_data['is_subscribed']   = new xmlrpcval(true, 'boolean');
    if ($show['movethread'])            $return_data['can_move']        = new xmlrpcval(true, 'boolean');
    if ($show['deletethread'])          $return_data['can_delete']      = new xmlrpcval(true, 'boolean');
    if ($thread['isdeleted'])           $return_data['is_deleted']      = new xmlrpcval(true, 'boolean');
    if ($show['openclose'])             $return_data['can_close']       = new xmlrpcval(true, 'boolean');
    if (!$thread['open'])               $return_data['is_closed']       = new xmlrpcval(true, 'boolean');
    if ($show['movethread'])            $return_data['can_stick']       = new xmlrpcval(true, 'boolean');
    if ($threadinfo['sticky'])          $return_data['is_sticky']       = new xmlrpcval(true, 'boolean');
    if ($show['moderatethread'])        $return_data['can_approve']     = new xmlrpcval(true, 'boolean');
    if ($show['moderatethread'])        $return_data['can_rename']      = new xmlrpcval(true, 'boolean');
    if ($thread['vbseo_likes'])         $return_data['like_count']      = new xmlrpcval($thread['vbseo_likes'], 'int');
    if ($thread['prefix_plain_html'])   $return_data['prefix']          = new xmlrpcval(mobiquo_encode($thread['prefix_plain_html']), 'base64');
    if ($thread['prefixid'])            $return_data['prefix_id']       = new xmlrpcval($thread['prefixid'], 'string');

    $return_data['posts'] = new xmlrpcval($return_posts_list, 'array');
    
    return new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}

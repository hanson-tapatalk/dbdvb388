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

function construct_forum_bit_mobiquo($parentid, $depth = 0, $subsonly = 0, $subscribe_forums = array(),$show_hide = 0)
{
    global $vbulletin, $stylevar, $vbphrase, $show, $emailupdate;
    global $imodcache, $lastpostarray, $counters, $inforum, $tt_config;
    
    $return_forum = array();
    // this function takes the constant MAXFORUMDEPTH as its guide for how
    // deep to recurse down forum lists. if MAXFORUMDEPTH is not defined,
    // it will assume a depth of 2.

    // call fetch_last_post_array() first to get last post info for forums
    if (!is_array($lastpostarray))
    {
        fetch_last_post_array($parentid);
    }
    
    if (empty($vbulletin->iforumcache["$parentid"]))
    {
        return;
    }

    define('MAXFORUMDEPTH', 2);

    $forumbits = '';
    $return_forumbits = array();
    $depth++;

    //winter001 -start
    $hideForumList = $tt_config['hide_forum_id'];
    //winter001 -end

    foreach ($vbulletin->iforumcache["$parentid"] AS $forumid)
    {
        // grab the appropriate forum from the $vbulletin->forumcache
        $forum = $vbulletin->forumcache["$forumid"];

        //winter001 -start
        if (in_array($forum['forumid'], $hideForumList)) {
            continue;
        }
        //winter001 -end

        //$lastpostforum = $vbulletin->forumcache["$lastpostarray[$forumid]"];
        $lastpostforum = (empty($lastpostarray[$forumid]) ? array() : $vbulletin->forumcache["$lastpostarray[$forumid]"]);
        if(!$show_hide){
            if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
            {
                continue;
            }
        }
        $forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
        $lastpostforumperms = (empty($lastpostarray[$forumid]) ? 0 : $vbulletin->userinfo['forumpermissions']["$lastpostarray[$forumid]"]);
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums'])))
        { // no permission to view current forum
            continue;
        }

        $lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];
        $forum['statusicon'] = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
        $newPost = (($forum['statusicon'] == 'new') ? true : false);

        if ($subsonly)
        {
            $childforumbits = construct_forum_bit_mobiquo($forum['forumid'], 1, $subsonly,$subscribe_forums);
        }
        else if ($depth < MAXFORUMDEPTH)
        {
            $childforumbits = construct_forum_bit_mobiquo($forum['forumid'], $depth, $subsonly,$subscribe_forums);
        }
        else
        {
            $childforumbits = '';
        }

        // do stuff if we are not doing subscriptions only, or if we ARE doing subscriptions,
        // and the forum has a subscribedforumid
        if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
        {
            $GLOBALS['forumshown'] = true; // say that we have shown at least one forum

            if (($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads']))
            { // get appropriate suffix for template name
                $tempext = '_post';
                $only_sub = 0;
            }
            else
            {
                $tempext = '_nopost';
                $only_sub = 1;
            }

            if ($subsonly OR $depth == MAXFORUMDEPTH )
            {
                // $forum['subforums'] = construct_subforum_bit_mobiquo($forumid, ($forum['options'] & $vbulletin->bf_misc_forumoptions['cancontainthreads'] ) );
                $forum['subforums'] = construct_forum_bit_mobiquo($forum['forumid'], 1, 0,$subscribe_forums);
            }
            else
            {
                $forum['subforums'] ="";
            }

            $children = explode(',', $forum['childlist']);

            $forum_locked = false;    
            if ($vbulletin->options['showlocks'] // show locks to users who can't post
                AND !$forum['link'] // forum is not a link
                    AND(
                        !($forum['options'] & $vbulletin->bf_misc_forumoptions['allowposting']) // forum does not allow posting
                        OR(!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) // can't post new threads
                        AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyown']) // can't reply to own threads
                        AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canreplyothers']) // can't reply to others' threads
                    )
                )
            ) {
                $forum_locked = true;
            }
            $mobiquo_is_subscribed = false;

            if(isset($subscribe_forums) && !empty($subscribe_forums[$forum[forumid]])){
                $mobiquo_is_subscribed = true;
            }
            $mobiquo_can_subscribe =  iif($only_sub == 0, true, false);
 
            //get the permission of can_post 
            $TT_foruminfo = fetch_foruminfo($forumid);
            $TT_forumperms = fetch_permissions($forumid);
            if (!($TT_forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
            {
                // $return = array(20, 'security error (user may not have permission to access this feature)');
                // return return_fault($return);
                continue;
            }
            $TT_can_post = true;
            if (!($TT_forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !$TT_foruminfo['allowposting'])
            {
                $TT_can_post = false;
            }   
            //set in read only options?
            $TT_is_read_only_forums = false; 
            if (isset($vbulletin->options['tapatalk_readonly_forums']))
            {
                $TT_read_only_forums = unserialize($vbulletin->options['tapatalk_readonly_forums']);
                if (is_array($TT_read_only_forums))
                    $TT_is_read_only_forums = in_array($forumid, $TT_read_only_forums);
            }
            $TT_can_post = !$TT_is_read_only_forums && $TT_can_post;
            //if being link forum, can_post should be false
            $TT_forumlink = trim($TT_foruminfo['link']);
            if(isset($TT_foruminfo['link']) && !empty($TT_forumlink))
                $TT_can_post = false;


            $forumbits_list = array(
                'forum_id'      => new xmlrpcval($forum['forumid'],"string"),
                'forum_name'    => new xmlrpcval(mobiquo_encode($forum['title']),"base64"),
                'description'   => new xmlrpcval(mobiquo_encode($forum['description']),"base64"),
                'sub_only'      => new xmlrpcval($only_sub,'boolean'),
                'parent_id'     => new xmlrpcval($forum['parentid'],"string"),
                'new_post'      => new xmlrpcval($newPost,'boolean'),
                'is_subscribed' => new xmlrpcval($mobiquo_is_subscribed,'boolean'),
                'can_subscribe' => new xmlrpcval($mobiquo_can_subscribe,'boolean'),
                'url'           => new xmlrpcval($forum[link],"string"),
                'can_post'      => new xmlrpcval($TT_can_post, 'boolean'),
            );

            $forumbits_list['is_protected'] = new xmlrpcval(!verify_forum_password($forum['forumid'], $forum['password'], false), 'boolean');
            
            $icon_filename = tp_get_forum_icon($forumid, $forum['depth'] === 0 ?'category':($forum['link']?'link':'forum'), $forum['depth'] !== 0 && $forum_locked ? true : false, $newPost);
            if($icon_filename)
            {
                $logo_url = $vbulletin->options['bburl'].'/'.$vbulletin->options['tapatalk_directory'].'/forum_icons/'.$icon_filename;
                $forumbits_list['logo_url'] = new xmlrpcval($logo_url, 'string');
            }
            
            if(isset($emailupdate[$forum['forumid']])) {
                $forumbits_list['subscribe_mode'] = new xmlrpcval(intval($emailupdate[$forum['forumid']]), 'int');
            }
            
            if($childforumbits){
                $forumbits_list[child] = new xmlrpcval($childforumbits,"array");
            }
            
            if($forum[subforums]){
                $forumbits_list[child] = new xmlrpcval($forum[subforums],"array");
            }

            $return_forumbits[$forum[forumid]]  = new xmlrpcval($forumbits_list,'struct');
        } // end if (!$subsonly OR ($subsonly AND !empty($forum['subscribeforumid'])))
        else
        {
            if(isset($childforumbits)){
                $return_forumbits =  array_merge($return_forumbits,$childforumbits);
            }
        }
    }
    
    if(sizeof($return_forumbits)>0){
        return array_values($return_forumbits);
    } else {
        return;
    }
}

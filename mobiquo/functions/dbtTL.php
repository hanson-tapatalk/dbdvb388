<?php

defined('IN_MOBIQUO') or exit;

// get the current thanks/likes data
function dbTTL($return_post, $post, $forumid, $varname, $can, $is, $count, $info)
{
    global $vbulletin;

    try
    {
        
        if(!function_exists('THANKS::checkPermissions'))
        {
            $nocan = false;
        }
        // get the button info, maybe can't use in this thread/forum
        foreach (THANKS::$cache['button'] as $button)
        {
            if (!$button['active'])
            {
                // Inactive button
                continue;
            }

            if ($button['varname'] == $varname)
            {
                // Copy this
                break;
            }
        }

        if(!$button['varname'] || $button['varname'] != $varname)
            return($return_post);

        if (!$nocan && !THANKS::checkPermissions($vbulletin->userinfo, $button['permissions'], 'canclick'))
            $nocan = true;

        $foruminfo = $vbulletin->forumcache[$forumid];
        if (!THANKS::$isPro)
        {
            // Lite-only shit
            $parentlist = explode(',', $foruminfo['parentlist']);
            if ($parentlist[0] == -1)
            {
                // This forum
                $noticeforum = $foruminfo['forumid'];
            }
            else
            {
                $key = (count($parentlist) - 2);
                $noticeforum = $parentlist["$key"];
            }
        }
        else
        {
            // This forum
            $noticeforum = $foruminfo['forumid'];
        }

        if ((int)$vbulletin->forumcache[$noticeforum]['dbtech_thanks_disabledbuttons'] & (int)$button['bitfield'])
            $nocan = true;

        if ((int)$post['disabledbuttons_thread'] & (int)$button['bitfield'])
            $nocan = true;

        if ((int)$post['dbtech_thanks_disabledbuttons'] & (int)$button['bitfield'])
            $nocan = true;

        // If thread already liked but NO unlike permission, then hide the 'unlike' button
        if (is_array(THANKS::$entrycache['data'][$post['postid']][$varname][$vbulletin->userinfo['userid']]) && !$nocan && !THANKS::checkPermissions($vbulletin->userinfo, $button['permissions'], 'canunclick'))
            $nocan = true;

        if ($post['userid'] != $vbulletin->userinfo['userid'] && $can && !$nocan && $vbulletin->userinfo['userid'])
            $return_post[$can] = new xmlrpcval(true, 'boolean');

        if (is_array(THANKS::$entrycache['data'][$post['postid']][$varname][$vbulletin->userinfo['userid']]) && $is)
            $return_post[$is] = new xmlrpcval(true, 'boolean');

        $like_count = count(THANKS::$entrycache['data'][$post['postid']][$varname]);

        if ($like_count)
        {
            $like_list = array();
            foreach(THANKS::$entrycache['data'][$post['postid']][$varname] as $like)
            {
                $like_list[] = new xmlrpcval(array(
                    'userid'    => new xmlrpcval($like['userid'], 'string'),
                    'username'  => new xmlrpcval(mobiquo_encode($like['username']), 'base64'),
                ), 'struct');
            }
            if ($count)
                $return_post[$count] = new xmlrpcval($like_count, 'int');
            $return_post[$info] = new xmlrpcval($like_list, 'array');
        }

    }catch(Exception $ex){}
    return $return_post;
}


// Function to add remove likes/thanks/whatever
function process_action($contentid, $varname)
{
    global $vbulletin;

    foreach (THANKS::$cache['button'] as $button)
    {
        if (!$button['active'])
        {
            // Inactive button
            continue;
        }

        if ($button['varname'] == $varname)
        {
            // Copy this
            break;
        }
    }

    if (empty($button))
    {
        // Invalid varname
        return_fault($vbphrase['dbtech_thanks_invalid_button']);
    }

    if (!$vbulletin->userinfo['userid'])
    {
        // We can't click this button
        return_fault($vbphrase['dbtech_thanks_no_permissions_click']);
    }

    if (!THANKS::checkPermissions($vbulletin->userinfo, $button['permissions'], 'canclick'))
    {
        // We can't click this button
        return_fault($vbphrase['dbtech_thanks_no_permissions_click']);
    }

    // Grab the post info
    if (!$post = THANKS::$db->fetchRow('
        SELECT
            post.postid,
            post.userid,
            post.dbtech_thanks_disabledbuttons,
            thread.firstpostid,
            thread.forumid,
            thread.dbtech_thanks_disabledbuttons AS disabledbuttons_thread,
            user.username,
            user.userid,
            user.usergroupid,
            user.displaygroupid,
            user.membergroupids,
            user.customtitle
        FROM $post AS post
        LEFT JOIN $thread AS thread ON(thread.threadid = post.threadid)
        LEFT JOIN $user AS user ON(user.userid = post.userid)
        WHERE post.postid = ?
    ', array(
        $contentid
    )))
    {
        // Invalid post id
        return_fault($vbphrase['dbtech_thanks_invalid_postid']);
    }

    if ($post['userid'] == $vbulletin->userinfo['userid'])
    {
        // Can't click for own posts
        return_fault($vbphrase['dbtech_thanks_cant_click_own_posts']);
    }

    $foruminfo = $vbulletin->forumcache[$post['forumid']];
    if (!THANKS::$isPro)
    {
        // Lite-only shit
        $parentlist = explode(',', $foruminfo['parentlist']);
        if ($parentlist[0] == -1)
        {
            // This forum
            $noticeforum = $foruminfo['forumid'];
        }
        else
        {
            $key = (count($parentlist) - 2);
            $noticeforum = $parentlist["$key"];
        }
    }
    else
    {
        // This forum
        $noticeforum = $foruminfo['forumid'];
    }

    if ((int)$vbulletin->forumcache[$noticeforum]['dbtech_thanks_disabledbuttons'] & (int)$button['bitfield'])
    {
        // Button was disabled for this forum
        return_fault($vbphrase['dbtech_thanks_button_disabled_forum']);
    }

    if ((int)$post['disabledbuttons_thread'] & (int)$button['bitfield'])
    {
        // Button was disabled for this thread
        return_fault($vbphrase['dbtech_thanks_button_disabled_thread']);
    }

    if ((int)$post['dbtech_thanks_disabledbuttons'] & (int)$button['bitfield'])
    {
        // Button was disabled for this post
        return_fault($vbphrase['dbtech_thanks_button_disabled_post']);
    }

    // Refresh AJAX post data
    $excluded = THANKS::refreshAjaxPost($contentid);

    if (!THANKS::$entrycache['data'][$post['postid']][$button['varname']][$vbulletin->userinfo['userid']] AND
        THANKS::$entrycache['clickcount'][$button['varname']] >= (int)$button['clicksperday'] AND
        $button['clicksperday'] AND
        THANKS::$isPro)
    {
        // We've clicked the maximum amount of buttons allowed
        return_fault($vbphrase['dbtech_thanks_clicked_too_many']);
    }

    // We now have everything we need to build the entry info
    $entryinfo = array(
        'varname'           => $varname,
        'userid'            => $vbulletin->userinfo['userid'],
        'contenttype'       => 'post',
        'contentid'         => $contentid,
        'receiveduserid'    => $post['userid']
    );

    if (!in_array($entryinfo['varname'], $excluded))
    {
        // We clicked another button that prevented this button click
        if ($button['reputation'])
        {
            $userinfo = fetch_userinfo($post['userid']);
        }

        if ($existing = THANKS::$db->fetchRow('
            SELECT *
            FROM $dbtech_thanks_entry
            WHERE varname = ?
                AND userid = ?
                AND contenttype = \'post\'
                AND contentid = ?
        ', array(
            $varname,
            $vbulletin->userinfo['userid'],
            $contentid
        )))
        {
            if (!THANKS::checkPermissions($vbulletin->userinfo, $button['permissions'], 'canunclick') OR !THANKS::$isPro)
            {
                // We can't un-click this button
                return_fault($vbphrase['dbtech_thanks_no_permissions_unclick']);
            }

            // init data manager
            $dm =& THANKS::initDataManager('Entry', $vbulletin, ERRTYPE_CP);
                $dm->set_existing($existing);
            $dm->delete();

            if ($button['reputation'])
            {
                // Subtract reputation
                $userinfo['reputation'] -= $button['reputation'];
            }
        }
        else
        {
            // init data manager
            $dm =& THANKS::initDataManager('Entry', $vbulletin, ERRTYPE_CP);

            // button fields
            foreach ($entryinfo AS $key => $val)
            {
                // These values are always fresh
                $dm->set($key, $val);
            }

            // Save! Hopefully.
            $entryid = $dm->save();

            if (!$entryid)
            {
                // Unknown error
                return_fault($vbphrase['dbtech_thanks_unknown_click_error']);
            }

            if ($button['reputation'])
            {
                // Add reputation
                $userinfo['reputation'] += $button['reputation'];
            }

            ($hook = vBulletinHook::fetch_hook('dbtech_thanks_postsave')) ? eval($hook) : false;
        }

        if ($button['reputation'])
        {
            // Determine this user's reputationlevelid.
            $reputationlevel = THANKS::$db->fetchRow('
                SELECT reputationlevelid
                FROM $reputationlevel
                WHERE ? >= minimumreputation
                ORDER BY minimumreputation DESC
            ', array($userinfo['reputation']));

            // init user data manager
            $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
                $userdata->set_existing($userinfo);
                $userdata->set('reputation', $userinfo['reputation']);
                $userdata->set('reputationlevelid', intval($reputationlevel['reputationlevelid']));
            $userdata->save();
        }
    }

}

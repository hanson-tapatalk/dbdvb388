<?php

global $headinclude, $header, $threadinfo, $foruminfo;

$script_to_page = array(
   'index'          => 'home',
   'showthread'     => 'topic',
   'showpostpost'   => 'post',
   'forumdisplay'   => 'forum',
);
$page_type = defined('THIS_SCRIPT') && isset($script_to_page[THIS_SCRIPT]) ? $script_to_page[THIS_SCRIPT] : 'others';
if($page_type == 'others')
{
    return;
}
$board_url = $vbulletin->options['bburl'];
$app_forum_name = $vbulletin->options['bbtitle'] ? $vbulletin->options['bbtitle'] : '';
$tapatalk_dir = isset($vbulletin->options['tapatalk_directory']) ? $vbulletin->options['tapatalk_directory'] : 'mobiquo';
$api_key = $vbulletin->options['push_key'];

$TT_AllowSmartbanner = true;

$TT_bannerControlData = isset($vbulletin->options['tapatalk_banner_data']) ? unserialize($vbulletin->options['tapatalk_banner_data']) :  array('banner_enable' => -1);
include_once(CWD .'/'.$tapatalk_dir . '/include/classTTConnection.php');


$app_banner_enable =  $vbulletin->options['tapatalk_smartbanner'];
$google_indexing_enabled = $vbulletin->options['tapatalk_mobilegoogle'];
$facebook_indexing_enabled = $vbulletin->options['tapatalk_mobilefacebook'];
$twitter_indexing_enabled = $vbulletin->options['tapatalk_mobiletwitter'];
$TT_connection = new classTTConnection();
$TT_bannerControlData = $TT_connection->calcSwitchOptions($TT_bannerControlData, $app_banner_enable, $google_indexing_enabled, $facebook_indexing_enabled, $twitter_indexing_enabled);

if(isset($TT_bannerControlData['byo_info']) && !empty($TT_bannerControlData['byo_info']))
{
    $app_rebranding_id = $TT_bannerControlData['byo_info']['app_rebranding_id'];
    $app_url_scheme = $TT_bannerControlData['byo_info']['app_url_scheme'];
    $app_icon_url = $TT_bannerControlData['byo_info']['app_icon_url'];
    $app_name = $TT_bannerControlData['byo_info']['app_name'];
    $app_alert_status = $TT_bannerControlData['byo_info']['app_alert_status'];
    $app_alert_message = $TT_bannerControlData['byo_info']['app_alert_message'];

    $app_android_id = $TT_bannerControlData['byo_info']['app_android_id'];
    $app_android_description = $TT_bannerControlData['byo_info']['app_android_description'];
    $app_banner_message_android = $TT_bannerControlData['byo_info']['app_banner_message_android'];
    $app_banner_message_android = preg_replace('/\r\n/','<br>',$app_banner_message_android);

    $app_ios_id = $TT_bannerControlData['byo_info']['app_ios_id'];
    $app_ios_description = $TT_bannerControlData['byo_info']['app_ios_description'];
    $app_banner_message_ios = $TT_bannerControlData['byo_info']['app_banner_message_ios'];
    $app_banner_message_ios = preg_replace('/\r\n/','<br>',$app_banner_message_ios);
}

$twc_title = isset($threadinfo['title']) ? $threadinfo['title'] : (isset($foruminfo['title_clean']) ? $foruminfo['title_clean'] : '');
$twc_description = empty($threadinfo) && isset($foruminfo['description_clean']) ? $foruminfo['description_clean'] : '';
$twc_site = isset($TT_bannerControlData['twitter_account']) && !empty($TT_bannerControlData['twitter_account']) ? $TT_bannerControlData['twitter_account'] : 'tapatalk';

// if in hide forums, not display the banner
$TT_hide_fids = $vbulletin->options['tapatalk_hide_forum'];
$TT_hide_array = unserialize($TT_hide_fids);
if (!empty($TT_hide_array))
{
    $TT_forumid = $vbulletin->GPC['forumid'];
    if (!empty($TT_forumid))
    {
        $TT_parentlist = $vbulletin->forumcache[$TT_forumid]['parentlist'];
        $TT_parentarray = explode(',', $TT_parentlist);
        foreach ($TT_parentarray as $value) {
            if(in_array($value, $TT_hide_array))
            {
                $TT_AllowSmartbanner = false;
            }   
        }
    }
}
$app_location = get_scheme_url();

if ($TT_AllowSmartbanner && file_exists(CWD .'/'.$tapatalk_dir . '/smartbanner/head.inc.php'))
    include_once(CWD .'/'.$tapatalk_dir . '/smartbanner/head.inc.php');

$headinclude .= isset($app_head_include) ? $app_head_include : '';


$header = '
<!-- Tapatalk Detect body start -->
<script type="text/javascript">if (typeof(tapatalkDetect) == "function") tapatalkDetect()</script>
<!-- Tapatalk Detect banner body end -->

'.$header;




function get_scheme_url()
{
    global $vbulletin;

    $baseUrl = $vbulletin->options['bburl'];
    $baseUrl = preg_replace('/https?:\/\//', '', $baseUrl);
    $location = 'index';
    $other_info = array();
    $gpc = $vbulletin->GPC;

    $has_forumid = isset($vbulletin->GPC['forumid']) && !empty($vbulletin->GPC['forumid']);
    $has_threadid = isset($vbulletin->GPC['threadid']) && !empty($vbulletin->GPC['threadid']);
    $has_postid = isset($vbulletin->GPC['postid']) && !empty($vbulletin->GPC['postid']);
    if($has_forumid)
    {
        $location = 'forum';
        $other_info[] = 'fid='.$vbulletin->GPC['forumid'];
        $perpage = $vbulletin->options['maxthreads'];
        $page = $gpc['pagenumber'] > 0 ? $gpc['pagenumber']:  1;
        if($has_threadid)
        {
            $location = 'topic';
            $perpage = $vbulletin->options['maxposts'];
            $page = $gpc['pagenumber'];
            $other_info[] = 'tid='.$vbulletin->GPC['threadid'];
            if($has_postid)
            {
                $perpage = $vbulletin->options['maxposts'];
                $page = $gpc['pagenumber'];
                $location = 'post';
                $other_info[] = 'pid='.$vbulletin->GPC['postid'];
            }
        }
    }
    else if(isset($vbulletin->GPC['userid']) && !empty($vbulletin->GPC['userid']))
    {
        $location = 'profile';
        $other_info[] = 'uid='.$vbulletin->GPC['userid'];
    }
    else if(isset($_REQUEST['pmid']) && !empty($_REQUEST['pmid']))
    {
        $location = 'message';
        $other_info[] = 'mid='.$_REQUEST['pmid'];
    }
    else if(isset($vbulletin->GPC['who']))
       $location = 'online';
    else if(isset($vbulletin->GPC['searchid']))
       $location = 'search';
    else if(isset($vbulletin->GPC['logintype']))
       $location = 'login';


    $other_info_str = implode('&', $other_info);
    $scheme_url = $baseUrl. (!empty($vbulletin->userinfo['userid']) ? '?user_id='.$vbulletin->userinfo['userid'].'&' : '?') . 'location='.$location.(!empty($page) && !empty($perpage) ? "&page=$page&perpage=$perpage" : '').(!empty($other_info_str) ? '&'.$other_info_str : '');

    return $scheme_url;
}

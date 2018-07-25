<?php

global $headinclude, $header, $threadinfo, $foruminfo;

$app_android_id = $vbulletin->options['tp_app_android_url'] ? $vbulletin->options['tp_app_android_url'] : '';
$app_ios_id = $vbulletin->options['tp_app_ios_id'] ? $vbulletin->options['tp_app_ios_id'] : '';
$app_banner_message = $vbulletin->options['tp_app_banner_message'] ? $vbulletin->options['tp_app_banner_message'] : '';
$app_banner_message = preg_replace('/\r\n/','<br>',$app_banner_message);
$app_location_url = get_scheme_url();
$script_to_page = array(
   'index'          => 'home',
   'showthread'     => 'topic',
   'showpostpost'   => 'post',
   'forumdisplay'   => 'forum',
);

$page_type = defined('THIS_SCRIPT') && isset($script_to_page[THIS_SCRIPT]) ? $script_to_page[THIS_SCRIPT] : 'others';
$is_mobile_skin = false;
$app_forum_name = $vbulletin->options['bbtitle'] ? $vbulletin->options['bbtitle'] : '';
$board_url = $vbulletin->options['bburl'];
$tapatalk_dir = $vbulletin->options['tapatalk_directory'];  // default as 'mobiquo'
$tapatalk_dir_url = $board_url.'/'.$tapatalk_dir;
$api_key = $vbulletin->options['push_key'];

// controll banner show from tapatalk server
$tapatalk_banner_controll = $vbulletin->options['tapatalk_banner_controll'];
$banner_aways_show = true;
if (isset($tapatalk_banner_controll) && $tapatalk_banner_controll['expire'] > time())
{  
    if($tapatalk_banner_controll['banner_control'] == 1)
    {
        $banner_aways_show = false;
    }
} else if (empty($vbulletin->options['push_key'])) {
    $banner_aways_show = true;
} else {
        include_once("mobiquo/include/classTTConnection.php");
        $connection = new classTTConnection();
        $url = "https://tapatalk.com/get_forum_info.php";
        $data['key'] = md5($vbulletin->options['push_key']);
        $data['url'] = $vbulletin->options['bburl'];
        $response = $connection->getContentFromSever($url,$data,'post',true);
        
        if (!empty($response)) 
        {
            $rsp_array = explode("\n", $response);
            $banner_control = 1;
            foreach ($rsp_array as $line) {
                $result = explode(":", $line, 2);
                if ($result[0] == 'banner_control')
                    $banner_control = $result[1];
            }
            // $result = explode(":", $response, 2);
            // if ($result[0] == 'banner_control')
            //     $banner_control = $result[1];
            // else
                // $banner_control = 1;

            $expire         = strtotime('+1 day');
            $vbulletin->options['tapatalk_banner_controll'] = array('banner_control' => $banner_control, 'expire' => $expire);
            build_datastore('options', serialize($vbulletin->options), 1);
            if ($vbulletin->options['tapatalk_banner_controll']['banner_control'] == 1)
            {
                $banner_aways_show = false;
            }
        } else {
            $banner_aways_show = false;
        }
}
if ($banner_aways_show)
{
    $app_ads_enable = 1;
    $app_banner_enable = 1;
} else {
    $app_ads_enable = $vbulletin->options['full_ads'];
    $app_banner_enable = $vbulletin->options['tapatalk_smartbanner'];
}

$twitterfacebook_card_enabled =  $vbulletin->options['twitterfacebook_card_enabled'];
$twc_title = isset($threadinfo['title']) ? $threadinfo['title'] : (isset($foruminfo['title_clean']) ? $foruminfo['title_clean'] : '');
$twc_description = empty($threadinfo) && isset($foruminfo['description_clean']) ? $foruminfo['description_clean'] : '';

// if in hide forums, not display the banner
$display = true;
$hide_fids = $vbulletin->options['tapatalk_hide_forum'];
$hide_array = unserialize($hide_fids);
if (!empty($hide_array))
{
    $forumid = $vbulletin->GPC['forumid'];
    if (!empty($forumid))
    {
        $parentlist = $vbulletin->forumcache[$forumid]['parentlist'];
        $parentarray = explode(',', $parentlist);
        foreach ($parentarray as $value) {
            if(in_array($value, $hide_array))
            {
                $display = false;
            }   
        }
    }
}
$forumid = $vbulletin->GPC['forumid'];
if (!empty($forumid))
{
    $forum = $vbulletin->forumcache["$forumid"];
    if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
        $display = false;
}
if ($display && file_exists(CWD .'/'.$tapatalk_dir . '/smartbanner/head.inc.php'))
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
    $baseUrl = preg_replace('/https?:\/\//', 'tapatalk://', $baseUrl);
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
?>

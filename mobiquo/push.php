<?php

error_reporting(E_ALL & ~E_NOTICE);
ini_set('max_execution_time', '120');
if(isset($_GET['allowAccess']))
{
    exit;
}
if (!is_object($vbulletin->db))
{
    if (isset($_GET['checkip']))
    {
        include './include/function_push.php';
        print do_post_request(array('ip' => 1), true);
    }
    else
    {
        ob_start();
        $output = 'Tapatalk Push Notification Status Monitor<br><br>';
        $output .= 'Push notification test: <b>';
        include './include/function_push.php';
        chdir(get_root_dir());
        require_once('./global.php');
        global $vbulletin;

        
        if(isset($vbulletin->options['push_key']) && !empty($vbulletin->options['push_key']))
        {
            $push_key = $vbulletin->options['push_key'];
            $return_status = do_post_request(array('test' => 1, 'key' => $push_key), true);
            if ($return_status === '1')
                $output .= 'Success</b>';
            else
                $output .= 'Failed</b><br />'.$return_status;
        }
        else
        {
            $output .= 'Failed</b><br />Please set Tapatalk API Key at forum option/setting<br />';
        }
        
        $forum_url =  get_forum_path();

        $result = $vbulletin->db->query_first("SHOW TABLES LIKE '" . TABLE_PREFIX . "tapatalk_users'");
        $table_exist = empty($result) ? 'No' : 'Yes';
    
        $output .="<br>Current forum url: ".$forum_url."<br>";
        $output .="Tapatalk user table existence:".$table_exist."<br>";
        $query = "SELECT title as push_slug FROM ". TABLE_PREFIX . "tapatalk_push  WHERE userid = 0 LIMIT 1 ";
        $results = $vbulletin->db->query_read_slave($query);
        $ex = $vbulletin->db->fetch_row($results);
        if(isset($ex[0]))
        {
            $push_slug = unserialize($ex[0]);
            if(!empty($push_slug) && is_array($push_slug))
                $output .= 'Push Slug Status : ' . ($push_slug[5] == 1 ? 'Stick' : 'Free') . '<br />';
            if(isset($_GET['slug']))
                $output .= 'Push Slug Value: ' . $ex[0] . "<br /><br />";
        }
        $output .="<br>
<a href=\"https://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a><br>
For more details, please visit <a href=\"https://tapatalk.com\" target=\"_blank\">https://tapatalk.com</a>";
        ob_end_clean();
        echo $output;
    }
}


function get_forum_path()
{
    $path =  '../';

    if (!empty($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['HTTP_HOST']))
    {
        $path = $_SERVER['HTTP_HOST'];
        $path .= dirname(dirname($_SERVER['SCRIPT_NAME']));
    }
    return $path;
}

function get_root_dir()
{
    $dir = '../';

    if (!empty($_SERVER['SCRIPT_FILENAME']))
    {
        $dir = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!file_exists($dir.'/global.php'))
        $dir = dirname($dir);

        $dir = $dir.'/';
    }

    return $dir;
}
<?php

defined('IN_MOBIQUO') or exit;

// get special phrase groups
// if ($_POST['method_name'] == 'sync_user')
// {
//     $phrasegroups = array(
//     'wol',
//     'user',
//     'messaging',
//     'cprofilefield',
//     'reputationlevel',
//     'infractionlevel',
//     'posting',
//     );
// }
require_once('./global.php');
require_once(CWD1.'/config/conf_init.php');

function reset_push_slug_func()
{
    global $vbulletin;

    $code = trim($_REQUEST['code']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'reset_push_slug');   
    $result = array( 'result' => false );
    if($response === true)
    {
        $query = "UPDATE ". TABLE_PREFIX . "tapatalk_push SET title = '0' WHERE userid = 0 LIMIT 1 ";
        $vbulletin->db->query_write($query);
        $result['result'] = true;
    }
    else if($response)
    {
        $result['result_text'] = $response;
    }
    $response = ($format == 'json') ? json_encode($result) : serialize($result);
    @ob_end_clean();
    echo $response;
    exit;
}
function set_api_key_func()
{
    require_once(DIR . '/includes/adminfunctions.php');
    require_once(DIR . '/includes/adminfunctions_options.php');
    global $vbulletin;
    $code = trim($_REQUEST['code']);
    $key  = trim($_REQUEST['key']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_api_key');
    $result = false;
    if($response === true)
    {
        $vbulletin->input->clean_array_gpc('p', array(
            'setting'  => TYPE_ARRAY,
            'advanced' => TYPE_BOOL
        ));
        $vbulletin->GPC['setting'] = array('push_key' => $key);
        save_settings($vbulletin->GPC['setting']);
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
     );
    
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    @ob_end_clean();
    echo $response;
}
function set_forum_info_func()
{
    require_once(DIR . '/includes/adminfunctions.php');
    require_once(DIR . '/includes/adminfunctions_options.php');
    global $vbulletin;
    $code = trim($_REQUEST['code']);
    $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : 'json';
    $connection = new classTTConnection();
    $response = $connection->actionVerification($code,'set_forum_info');
    $result = false;
    if($response === true)
    {
        if(isset($_REQUEST['api_key']))
        {
            $vbulletin->input->clean_array_gpc('p', array(
                'setting'  => TYPE_ARRAY,
                 'advanced' => TYPE_BOOL
             ));
            $vbulletin->GPC['setting'] = array('push_key' => $_REQUEST['api_key']);
            save_settings($vbulletin->GPC['setting']);
            $result = true;
        }
        if(isset($_REQUEST['banner_info']))
        {
            $TT_bannerControlData  = json_decode($_REQUEST['banner_info'], true);
            if($TT_bannerControlData === true)
            {
                $board_url = $vbulletin->options['bburl'];
                $api_key = $vbulletin->options['push_key'];
                $TT_bannerControlData = $connection->getForumInfo($board_url, $api_key);
            }
            global $vbulletin;
            $tapatalk_dir = isset($vbulletin->options['tapatalk_directory']) ? $vbulletin->options['tapatalk_directory'] : 'mobiquo';
            $vbulletin->db->query_write("
                    INSERT IGNORE INTO " . TABLE_PREFIX . "setting (varname, grouptitle, defaultvalue, product) values
                    ('tapatalk_banner_data','tapatalk_banner_data', '', 'tapatalk'),('tapatalk_banner_updatetime','tapatalk_banner_updatetime', '0', 'tapatalk')");
            include_once(CWD .'/includes/adminfunctions.php');
            include_once(CWD .'/includes/adminfunctions_options.php');
            $vbulletin->input->clean_array_gpc('p', array(
                'setting'  => TYPE_ARRAY,
                'advanced' => TYPE_BOOL
            ));
            $vbulletin->GPC['setting'] = array('tapatalk_banner_data' => serialize($TT_bannerControlData), 'tapatalk_banner_updatetime' => time());
            save_settings($vbulletin->GPC['setting']);
        }
        $result = true;
    }
    $data = array(
         'result' => $result,
         'result_text' => $response,
         'api_key' => $vbulletin->options['push_key'],
         'forum_info' => unserialize($vbulletin->options['tapatalk_banner_data'])
     );

    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    @ob_end_clean();
    echo $response;
}
function user_subscription_func(){
    global $db;

    $code = trim($_POST['code']);
    $uid = intval(trim($_POST['uid']));
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';

    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'user_subscription');

        $data = array( 'result' => false );
        if ($response !== true){
            $data['result_text'] = $response;
            echo ($format == 'json') ? json_encode($data) : serialize($data);
            exit;
        }

        $subsforumsQ = $db->query_read_slave("
            SELECT sf.forumid as fid, f.title_clean as name
            FROM " . TABLE_PREFIX . "subscribeforum as sf
            LEFT JOIN " . TABLE_PREFIX . "forum as f ON sf.forumid = f.forumid
            WHERE sf.userid = " . $uid
        );
        while ($forum = $db->fetch_array($subsforumsQ)){
            $data['forums'][] = $forum;
        }
        $substhreadQ = $db->query_read_slave("
            SELECT threadid
            FROM " . TABLE_PREFIX . "subscribethread
            WHERE userid = " . $uid
        );
        while ($thread = $db->fetch_array($substhreadQ)){
            $data['topics'][] = $thread['threadid'];
        }
        $data['result'] = true;
    }catch (Exception $e){
        $data = array(
                'result' => false,
                'result_text' => $e->getMessage(),
        );
    }
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}

function push_content_check_func(){
    global $vbulletin, $db;
    $code = trim($_POST['code']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';
    $data = unserialize(trim($_POST['data']));

    $result = array( 'result' => false );
    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'push_content_check');
        if ($response !== true){
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if(!isset($vbulletin->options['push_key']) || !isset($data['key']) || $vbulletin->options['push_key'] != $data['key']){
            $result['result_text'] = 'incorrect api key';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        if (!isset($data['dateline']) || time() - intval($data['dateline']) > 86400){
            $result['result_text'] = 'time out';
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }
        switch ($data['type']){
            case 'newtopic':
            case 'sub':
            case 'quote':
            case 'tag':
                $query = "
                    SELECT p.postid
                    FROM " . TABLE_PREFIX . "post AS p
                    WHERE p.postid={$data['subid']}
                        AND p.threadid={$data['id']}
                        AND p.userid={$data['authorid']}
                        AND p.dateline={$data['dateline']}
                ";
                break;
            case 'pm':
                $id = $data['id'];
                if (preg_match('/_(\d+)$/', $id, $matches)){
                    $id = $matches[1];
                }
                $query = "
                    SELECT pmt.pmtextid
                    FROM " . TABLE_PREFIX. "pmtext as pmt
                    WHERE pmt.pmtextid={$id}
                        AND pmt.fromuserid={$data['authorid']}
                        AND pmt.dateline={$data['dateline']}
                ";
                break;
        }
        if (isset($query) && !empty($query)){
            $query_result = $db->query_first($query);
            if (!empty($query_result)){
                $result['result']=true;
            }
        }
    }catch (Exception $e){
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;
}

function get_contact_func()
{
    global $vbulletin, $db;

    $code = trim($_POST['code']);
    $user_id = trim($_POST['uid']);
    $format = isset($_POST['format']) ? trim($_POST['format']) : '';

    $result = array( 'result' => false );
    if ($vbulletin->options['tapatalk_email_notifications'] != 1) {
        $result['result_text'] = 'Admin dont enable email notifications';
        echo ($format == 'json') ? json_encode($result) : serialize($result);
        exit;
    }

    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'get_contact');
        if ($response != true) {
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }

        if (!empty($user_id)) {

            $cipher = new TT_Cipher();
            $api_key = trim($vbulletin->options['push_key']);
            // include_once get_root_dir().'includes/function_misc.php';
            // $all_languages = fetch_language_titles_array();
            $users = array();
            $users_result = $db->query_read_slave("
                SELECT userid as uid, username , email , user.options , title as language, user.joindate, user.posts, user.lastactivity
                FROM " . TABLE_PREFIX . "user as user
                INNER JOIN " . TABLE_PREFIX . "language as lang
                WHERE userid in (" . $db->escape_string($user_id) . ") and (user.languageid = lang.languageid or user.languageid = 0)
                ORDER BY uid ASC
            ");
            while ($user = $db->fetch_array($users_result))
            {
                $tuser = array(
                    'uid'           => $user['uid'],
                    'username'      => mobiquo_encode($user['username']),
                    'language'      => $user['language'],
                    'allow_email'   => ((intval($user['options'])&16) == 0)? false : true,
                    'encrypt_email' => base64_encode($cipher->encrypt($user['email'],$api_key)),
                    'reg_date'      => $user['joindate'],
                    'post_num'      => $user['posts'],
                    'last_active'   => $user['lastactivity'],
                    'user_type'     => get_usertype_by_id($user['uid']),
                );
                $users[] = $tuser;
            }
            $result = array(
                'result'  => true,
                'encrypt' => true,
                'users'   => $users,
            );
        }
    } catch (Exception $e) {
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;

}

function sync_user_func()
{
    global $db, $vbulletin;
    $code = trim($_POST['code']);
    $start = intval(isset($_POST['start']) ? $_POST['start'] : 0);
    $limit = intval(isset($_POST['limit']) ? $_POST['limit'] : 1000);
    $format = trim($_POST['format']);

    $result = array( 'result' => false );
    if ($vbulletin->options['tapatalk_email_notifications'] != 1) {
        $result['result_text'] = 'Admin dont enable email notifications';
        echo ($format == 'json') ? json_encode($result) : serialize($result);
        exit;
    }

    try {
        $connection = new classTTConnection();
        $response = $connection->actionVerification($code, 'sync_user');
        if($response != true)
        {
            $result['result_text'] = $response;
            echo ($format == 'json') ? json_encode($result) : serialize($result);
            exit;
        }

        // Get cipher
        $cipher = new TT_Cipher();
        $api_key = trim($vbulletin->options['push_key']);
        // Get users...
        $users = array();
        $users_result = $db->query_read_slave("
            SELECT userid as uid, username , email , user.options , title as language, user.joindate, user.posts, user.lastactivity
            FROM " . TABLE_PREFIX . "user as user
            INNER JOIN " . TABLE_PREFIX . "language as lang
            WHERE userid > $start and (user.languageid = lang.languageid or user.languageid = 0) and email <> ''
            ORDER BY uid ASC LIMIT $limit
        ");
        while ($user = $db->fetch_array($users_result))
        {
            $tuser = array(
                'uid'           => $user['uid'],
                'username'      => mobiquo_encode($user['username']),
                'language'      => $user['language'],
                'allow_email'   => ((intval($user['options'])&16) == 0)? false : true,
                'encrypt_email' => base64_encode($cipher->encrypt($user['email'],$api_key)),
                'reg_date'      => $user['joindate'],
                'post_num'      => $user['posts'],
                'last_active'   => $user['lastactivity'],
                'user_type'     => get_usertype_by_id($user['uid']),
            );
            $users[] = $tuser;
        }
        $result = array(
            'result'  => true,
            'encrypt' => true,
            'users'   => $users,
        );
    } catch (Exception $e) {
        $result['result_text'] = $e->getMessage();
    }
    echo ($format == 'json') ? json_encode($result) : serialize($result);
    exit;
}
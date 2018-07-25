<?php
require_once ( dirname(__FILE__)."/classTTConnection.php" );
// For tapatalk push hook only
function tt_hook_encode($str)
{
    $str = strip_tags($str);
    
    if (empty($str)) return $str;
    
    static $charset, $charset_89, $charset_AF, $charset_8F, $charset_chr, $charset_html, $support_mb, $charset_entity;
    
    if (!isset($charset))
    {
        global $vbulletin, $stylevar;
        $charset = trim($stylevar['charset']);
        
        include_once(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/charset.php');
        
        if (preg_match('/iso-?8859-?1/i', $charset))
        {
            $charset = 'Windows-1252';
            $charset_chr = $charset_8F;
        }
        if (preg_match('/iso-?8859-?(\d+)/i', $charset, $match_iso))
        {
            $charset = 'ISO-8859-' . $match_iso[1];
            $charset_chr = $charset_AF;
        }
        else if (preg_match('/windows-?125(\d)/i', $charset, $match_win))
        {
            $charset = 'Windows-125' . $match_win[1];
            $charset_chr = $charset_8F;
        }
        else
        {
            // x-sjis is not acceptable, but sjis do
            $charset = preg_replace('/^x-/i', '', $charset);
            $support_mb = function_exists('mb_convert_encoding') && @mb_convert_encoding('test', $charset, 'UTF-8');
        }
    }
    
    
    if (preg_match('/utf-?8/i', $charset))
    {
        $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }
    else if (function_exists('mb_convert_encoding') && (strpos($charset, 'ISO-8859-') === 0 || strpos($charset, 'Windows-125') === 0) && isset($charset_html[$charset]))
    {
        if ($mode == 'to_local')
        {
            $str = @mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
            $str = str_replace($charset_html[$charset], $charset_chr, $str);
        }
        else
        {
            if (strpos($charset, 'ISO-8859-') === 0)
            {
                // windows-1252 issue on ios
                $str = str_replace(array(chr(129), chr(141), chr(143), chr(144), chr(157)),
                                   array('&#129;', '&#141;', '&#143;', '&#144;', '&#157;'), $str);
            }
            
            $str = str_replace($charset_chr, $charset_html[$charset], $str);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else if ($support_mb)
    {
        if ($mode == 'to_local')
        {
            $str = @mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8');
            $str = @mb_convert_encoding($str, $charset, 'UTF-8');
        }
        else
        {
            $str = @mb_convert_encoding($str, 'UTF-8', $charset);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else if (function_exists('iconv') && @iconv($charset, 'UTF-8', 'test-str'))
    {
        if ($mode == 'to_local')
        {
            $str = @htmlentities($str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8');
            $str = @iconv('UTF-8', $charset.'//IGNORE', $str);
        }
        else
        {
            $str = @iconv($charset, 'UTF-8//IGNORE', $str);
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
    }
    else
    {
        if ($mode == 'to_local')
        {
            $str = @htmlentities($str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8');
            if($charset == 'Windows-1252') $str = utf8_decode($str);
            $str = @html_entity_decode($str, ENT_QUOTES, $charset);
        }
        else
        {
            $str = @html_entity_decode($str, ENT_QUOTES, 'UTF-8');
            if ($charset == 'Windows-1252') $str = utf8_encode($str);
        }
    }
    
    // html entity convert
    if ($mode == 'to_local')
    {
        $str = str_replace(array_keys($charset_entity), array_values($charset_entity), $str);
    }
    
    // remove_unknown_char
    for ($i = 1; $i < 32; $i++)
    {
        if (in_array($i, array(10, 13))) continue;
        $str = str_replace(chr($i), '', $str);
    }
    
    return $str;
}

function updateSettings($key)
{
    global $vbulletin;

    $query = "UPDATE ". TABLE_PREFIX . "tapatalk_push SET title = '". ($key['push_slug']). "' WHERE userid = 0 LIMIT 1 ";
    $results = $vbulletin->db->query_write($query);
    
}

function load_push_slug()
{
    global $vbulletin;

    $query = "SELECT title as push_slug FROM ". TABLE_PREFIX . "tapatalk_push  WHERE userid = 0 LIMIT 1 ";
    $results = $vbulletin->db->query_read_slave($query);
    $ex = $vbulletin->db->fetch_row($results);
    if($ex)
        return ($ex[0]);
    else
    {
        $vbulletin->db->query_write("
            INSERT INTO " . TABLE_PREFIX . "tapatalk_push
                (userid, type, id, subid, title, author, dateline)
            VALUES
                ('0', '', '', '', '', '', ".time().")"
        );
        return array();
    }
}

function push_slug($push_v, $method = 'NEW')
{
    if(empty($push_v))
        $push_v = serialize(array());
    $push_v_data = unserialize($push_v);
    $current_time = time();
    if(!is_array($push_v_data))
        return serialize(array(2 => 0, 3 => 'Invalid v data', 5 => 0));
    if($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
        return serialize(array(2 => 0, 3 => 'Invalid method', 5 => 0));

    if($method != 'NEW' && !empty($push_v_data))
    {
        $push_v_data[8] = $method == 'UPDATE';
        if($push_v_data[5] == 1)
        {
            if($push_v_data[6] + $push_v_data[7] > $current_time)
                return $push_v;
            else
                $method = 'NEW';
        }
    }

    if($method == 'NEW' || empty($push_v_data))
    {
        $push_v_data = array();     //Slug
        $push_v_data[0] = 3;        //        $push_v_data['max_times'] = 3;                //max push failed attempt times in period  
        $push_v_data[1] = 300;      //        $push_v_data['max_times_in_period'] = 300;     //the limitation period
        $push_v_data[2] = 1;        //        $push_v_data['result'] = 1;                   //indicate if the output is valid of not
        $push_v_data[3] = '';       //        $push_v_data['result_text'] = '';             //invalid reason
        $push_v_data[4] = array();  //        $push_v_data['stick_time_queue'] = array();   //failed attempt timestamps
        $push_v_data[5] = 0;        //        $push_v_data['stick'] = 0;                    //indicate if push attempt is allowed
        $push_v_data[6] = 0;        //        $push_v_data['stick_timestamp'] = 0;          //when did push be sticked
        $push_v_data[7] = 600;      //        $push_v_data['stick_time'] = 600;             //how long will it be sticked
        $push_v_data[8] = 1;        //        $push_v_data['save'] = 1;                     //indicate if you need to save the slug into db
        return serialize($push_v_data);
    }

    if($method == 'UPDATE')
    {
        $push_v_data[4][] = $current_time;
    }
    $sizeof_queue = count($push_v_data[4]);
    
    $period_queue = $sizeof_queue > 1 ? ($push_v_data[4][$sizeof_queue - 1] - $push_v_data[4][0]) : 0;

    $times_overflow = $sizeof_queue > $push_v_data[0];
    $period_overflow = $period_queue > $push_v_data[1];

    if($period_overflow)
    {
        if(!array_shift($push_v_data[4]))
            $push_v_data[4] = array();
    }
    
    if($times_overflow && !$period_overflow)
    {
        $push_v_data[5] = 1;
        $push_v_data[6] = $current_time;
    }

    return serialize($push_v_data);
}

function do_post_request($data, $pushTest = false)
{
    //don't send push to users who ignore current user
    global $vbulletin;
    $current_uid = $vbulletin->userinfo['userid'];
    $ignore_results = $vbulletin->db->query_read_slave("
        SELECT userid FROM " . TABLE_PREFIX . "userlist AS userlist
        WHERE type = 'ignore' and userlist.relationid = " . $vbulletin->db->escape_string($current_uid));
    $data_users = explode(",", $data['userid']);
    $ignore_users = array();
    while ($ignore = $vbulletin->db->fetch_array($ignore_results))
    {
        $ignore_users[] = $ignore['userid'];
    }
    $push_users = array_diff($data_users,$ignore_users);
    $data['userid'] = implode(",", $push_users);
    
    $push_url = 'http://push.tapatalk.com/push.php';

    $connection = new classTTConnection();
    if($pushTest){
        $response = $connection->getContentFromSever($push_url, $data, 'post');
        if ($response){
            return $response;
        }else{
            return $connection->errors;
        }
    }

    //Initial this key in modSettings
    $modSettings = load_push_slug();

    //Get push_slug from db
    $push_slug =0;// isset($modSettings)? $modSettings : 0;
    $slug = $push_slug;
    $slug = push_slug($slug, 'CHECK');
    $check_res = unserialize($slug);

    //If it is valide(result = true) and it is not sticked, we try to send push
    if($check_res[2] && !$check_res[5])
    {
        //Slug is initialed or just be cleared
        if($check_res[8])
        {
            updateSettings(array('push_slug' => ($slug)));
        }

        //Send push
        $push_resp = $connection->getContentFromSever($push_url, $data, 'post');
        if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
        if($connection->success == false || !is_numeric($push_resp))
        {
            //Sending push failed, try to update push_slug to db
            $slug = push_slug($slug, 'UPDATE');
            $update_res = unserialize($slug);
            if($update_res[2] && $update_res[8])
            {
                updateSettings(array('push_slug' => ($slug)));
            }
        }
    }
    
    return ;
}

function getEmailFromScription($token, $code, $key)
{
    global $vbulletin;
    @include_once('classTTJson.php');

    $verification_url = 'http://directory.tapatalk.com/au_reg_verify.php?token='.$token.'&'.'code='.$code.'&key='.$key.'&url='.urlencode($vbulletin->options['bburl']);
    $connection = new classTTConnection();
    $response = $connection->getContentFromSever($verification_url, array(), 'get');
    $error = $connection->errors;
    if($response)
        $result = json_decode($response, true);
    if(isset($result) && isset($result['result']))
        return $result;
    else
    {
        $data = array(
            'token' => $token,
            'code'  => $code,
            'key'   => $key,
            'url'   => $vbulletin->options['bburl'],
        );
        $response = $connection->getContentFromSever('http://directory.tapatalk.com/au_reg_verify.php', $data, 'post');
        $error = $connection->errors;
        if($response)
            $result = json_decode($response, true);
        if(isset($result) && isset($result['result']))
            return $result;
        else
            return 0; //No connection to Tapatalk Server.
    }
}

function loadAPIKey()
{
    global $mobi_api_key, $vbulletin;
    
    if(empty($mobi_api_key))
    {
        $option_key = $vbulletin->options['push_key'];
        if(isset($option_key) && !empty($option_key))
        {
            $mobi_api_key = $option_key;
        }
        else
        {
            @include_once('classTTJson.php');

            $boardurl = $vbulletin->options['bburl'];
            $boardurl = urlencode($boardurl);
            $connection = new classTTConnection();
            $response = $connection->getContentFromSever("http://directory.tapatalk.com/au_reg_verify.php?url=$boardurl", array(), 'get');
            $error = $connection->errors;
            if($response)
                $result = json_decode($response, true);
            if(isset($result) && isset($result['result']))
                $mobi_api_key = $result['api_key'];
            else
            {
                $data = array(
                    'url'   =>  urlencode($vbulletin->options['bburl']),
                );
                $response = $connection->getContentFromSever('http://directory.tapatalk.com/au_reg_verify.php', $data, 'post');
                $error = $connection->errors;
                if($response)
                    $result = json_decode($response, true);
                if(isset($result) && isset($result['result']))
                    $mobi_api_key = $result['api_key'];
                else
                    $mobi_api_key = 0;
            }
        }
    }
    return $mobi_api_key;
}

function build_query($data, $a, $b = '&')
{
    if(function_exists('http_build_query'))
    {
        return http_build_query($data, $a, $b);
    }
    else
    {
        $ret = array();
        foreach ((array)$data as $k => $v)
        {
            if (is_int($k) && $prefix != null) $k = urlencode($prefix . $k);
            if (!empty($key)) $k = $key.'['.urlencode($k).']';
            
            if (is_array($v) || is_object($v))
                array_push($ret, build_query($v, '', $sep, $k));
            else
                array_push($ret, $k.'='.urlencode($v));
        }
        
        if (empty($sep)) $sep = ini_get('arg_separator.output');
        return implode($sep, $ret);
    }
}

function is_spam($email, $ip='')
{
    // if($email || checkipaddres($ip))
    // {
    //     $params = '';
    //     if($email)
    //     {
    //         $params = "&email=".urlencode($email);
    //     }

    //     if(checkipaddres($ip))
    //     {
    //         $params .= "&ip=$ip";
    //     }

    //     $connection = new classTTConnection();
    //     $connection->timeout = 3;
    //     $resp = $connection->getContentFromSever("http://www.stopforumspam.com/api?f=serial".$params, array(), 'get');
    //     $error = $connection->errors;
    //     $resp = unserialize($resp);
    //     if ((isset($resp['email']['confidence']) && $resp['email']['confidence'] > 50) ||
    //         (isset($resp['ip']['confidence']) && $resp['ip']['confidence'] > 60))
    //     {
    //         return true;
    //     }
    // }
    
    // return false;
    $connection = new classTTConnection();
    return $connection->checkSpam($email, $ip);
}

function checkipaddres ($ipaddres) {
    $preg="/\A((([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\.){3}(([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\Z/";
    if(preg_match($preg,$ipaddres))
        return true;
    return false;
}

function parse_content($str, $html_content = true, $forum_id = 0)
{
    global $vbphrase,$vbulletin;
    
    if($html_content)
    {
        if(!function_exists('fetch_tag_list'))
            include_once(DIR.'/includes/class_bbcode.php');
        $a = fetch_tag_list();
        unset($a['option']['quote']);
        unset($a['no_option']['quote']);
        unset($a['option']['url']);
        unset($a['no_option']['url']);

        $vbulletin->options['wordwrap'] = 0;
        
        $post_content = post_content_clean($str, $html_content);
        $post_content = preg_replace("/\[\/img\]/siU", '[/img1]', $post_content);
        $post_content = preg_replace("/\[\/url\]/siU", '[/url1]', $post_content);
        $bbcode_parser = new vB_BbCodeParser($vbulletin, $a, false);
        $post_content = $bbcode_parser->parse( $post_content, $forum_id, false);
        $post_content = preg_replace("/\[\/img1\]/siU", '[/IMG]', $post_content);
        $post_content = preg_replace("/\[\/url1\]/siU", '[/url]', $post_content);
        $post_content = preg_replace('/\[(TP_LIGHT)\](.*?)\[\/\1\]/si','<font color="red"><b>$2<b></font>', $post_content);
        $post_content = strip_tags($post_content, '<br><b><i><u><font>');
        $post_content = htmlspecialchars_uni($post_content);
    }
    else
    {
        $post_content = post_content_clean($str);
    }
    
    $post_content = preg_replace_callback('/\[tp_noparse\](.*?)\[\/tp_noparse\]/si', "callback_push_base64_decode", $post_content);
    
    // add spoiler for user ignored post
    if ($fetchtype == 'post_ignore')
    {
        $post_content = strip_tags(construct_phrase($vbphrase['message_hidden_x_on_ignore_list'], $post['postusername']))
                        . "[spoiler]{$post_content}[/spoiler]";
    }

    $post_content = mobiquo_encode($post_content, '', $html_content);
    if(SHORTENQUOTE == 1 && preg_match('/^(.*\[quote\])(.+)(\[\/quote\].*)$/si', $post_content))
    {
        $new_content = "";
        $segments = preg_split('/(\[quote\].+\[\/quote\])/isU', $post_content,-1, PREG_SPLIT_DELIM_CAPTURE);

        foreach($segments as $segment)
        {
            $short_quote = $segment;
            if(preg_match('/^(\[quote\])(.+)(\[\/quote\])$/si', $segment, $quote_matches)){
                if(function_exists('mb_strlen') && function_exists('mb_substr')){
                    if(mb_strlen($quote_matches[2], 'UTF-8') > 170){
                        $short_quote = $quote_matches[1].mb_substr($quote_matches[2],0,150, 'UTF-8').$quote_matches[3];
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
    return $post_content;
} 

function getClientIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
function getClienUserAgent()
{
    $useragent = $_SERVER['HTTP_USER_AGENT'];
    return $useragent;
}  
function getIsFromApp()
{
    return defined('IN_MOBIQUO') ? 1 : 0;
}

function callback_push_base64_decode($matches)
{
    return base64_decode($matches[1]);
}
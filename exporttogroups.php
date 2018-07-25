<?php

set_error_handler('MyErrorHandler');

ini_set('display_errors', '1');
date_default_timezone_set('Asia/Dhaka');
function MyErrorHandler($errno, $errstr, $errfile, $errline)
{
    if ($errno != E_DEPRECATED && $errno != E_NOTICE)
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo $errstr;
    }
}

define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

$config = [];
include(CWD . '/includes/config.php');

$vbulletin = new stdClass();

$vbulletin->config = $config;

// if a configuration exists for this exact HTTP host, use it
if (isset($vbulletin->config["$_SERVER[HTTP_HOST]"]))
{
    $vbulletin->config['MasterServer'] = $vbulletin->config["$_SERVER[HTTP_HOST]"];
}

// define table and cookie prefix constants
define('TABLE_PREFIX', trim($vbulletin->config['Database']['tableprefix']));
//define('COOKIE_PREFIX', (empty($vbulletin->config['Misc']['cookieprefix']) ? 'bb' : $vbulletin->config['Misc']['cookieprefix']));

//
//// fetch the core includes
//require_once(CWD . '/includes/class_core.php');
//global $vbulletin;
//// initialize the data registry
//$vbulletin = new vB_Registry();
//
//// load the IP data & constants
//$vbulletin->fetch_config();
//
//if (CWD == '.')
//{
//    // getcwd() failed and so we need to be told the full forum path in config.php
//    if (!empty($vbulletin->config['Misc']['forumpath']))
//    {
//        define('DIR', $vbulletin->config['Misc']['forumpath']);
//    }
//    else
//    {
//        trigger_error('<strong>Configuration</strong>: You must insert a value for <strong>forumpath</strong> in config.php', E_USER_ERROR);
//    }
//}
//else
//{
//    define('DIR', CWD);
//}
//
//if (!empty($vbulletin->config['Misc']['datastorepath']))
//{
//    define('DATASTORE', $vbulletin->config['Misc']['datastorepath']);
//}
//else
//{
//    define('DATASTORE', DIR . '/includes/datastore');
//}
//
//if ($vbulletin->debug)
//{
//    restore_error_handler();
//}
//
//$dbtype = strtolower($vbulletin->config['Database']['dbtype']);
//
//// Force MySQL to MySQLi
//if ($dbtype == 'mysql')
//{
//    $dbtype = 'mysqli';
//}
//else if ($dbtype == 'mysql_slave')
//{
//    $dbtype = 'mysqli_slave';
//}
//
////If type is missing, Force MySQLi 
//$dbtype = $dbtype ? $dbtype : 'mysqli';
//
//// #############################################################################
//// Load database class
//switch ($dbtype)
//{
//    // Load standard MySQL class
//    case 'mysql':
//        {
//            if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
//            {
//                // load 'explain' database class
//                require_once(DIR . '/includes/class_database_explain.php');
//                $db = new vB_Database_Explain($vbulletin);
//            }
//            else
//            {
//                $db = new vB_Database($vbulletin);
//            }
//            break;
//        }
//
//    case 'mysql_slave':
//        {
//            require_once(DIR . '/includes/class_database_slave.php');
//            $db = new vB_Database_Slave($vbulletin);
//            break;
//        }
//
//    // Load MySQLi class
//    case 'mysqli':
//        {
//            if ($vbulletin->debug AND ($vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
//            {
//                // load 'explain' database class
//                require_once(DIR . '/includes/class_database_explain.php');
//                $db = new vB_Database_MySQLi_Explain($vbulletin);
//            }
//            else
//            {
//                $db = new vB_Database_MySQLi($vbulletin);
//            }
//            break;
//        }
//
//    case 'mysqli_slave':
//        {
//            require_once(DIR . '/includes/class_database_slave.php');
//            $db = new vB_Database_Slave_MySQLi($vbulletin);
//            break;
//        }
//
//    // Load extended, non MySQL class
//    default:
//        {
//            @include_once(DIR . "/includes/class_database_$dbtype.php");
//            $dbclass = "vB_Database_$dbtype";
//            $db = new $dbclass($vbulletin);
//        }
//}
//
//// get core functions
//if (!empty($db->explain))
//{
//    $db->timer_start('Including Functions.php');
//    require_once(DIR . '/includes/functions.php');
//    $db->timer_stop(false);
//}
//else
//{
//    require_once(DIR . '/includes/functions.php');
//}
//
//// make database connection
//$db->connect(
//    $vbulletin->config['Database']['dbname'],
//    $vbulletin->config['MasterServer']['servername'],
//    $vbulletin->config['MasterServer']['port'],
//    $vbulletin->config['MasterServer']['username'],
//    $vbulletin->config['MasterServer']['password'],
//    $vbulletin->config['MasterServer']['usepconnect'],
//    $vbulletin->config['SlaveServer']['servername'],
//    $vbulletin->config['SlaveServer']['port'],
//    $vbulletin->config['SlaveServer']['username'],
//    $vbulletin->config['SlaveServer']['password'],
//    $vbulletin->config['SlaveServer']['usepconnect'],
//    $vbulletin->config['Mysqli']['ini_file'],
//    (isset($vbulletin->config['Mysqli']['charset']) ? $vbulletin->config['Mysqli']['charset'] : '')
//);
//
//// Allow setting of SQL mode, not generally required
//if (isset($vbulletin->config['Database']['set_sql_mode']))
//{
//    $db->force_sql_mode($vbulletin->config['Database']['set_sql_mode']);
//}
//else
//{
//    $db->force_sql_mode(''); // Force blank mode if none set, avoids Strict Mode issues.
//}
//
//if (defined('DEMO_MODE') AND DEMO_MODE AND function_exists('vbulletin_demo_init_db'))
//{
//    vbulletin_demo_init_db();
//}
//
//// make $db a member of $vbulletin
//$vbulletin->db =& $db;
//
//// #############################################################################
//// fetch options and other data from the datastore
//if (!empty($db->explain))
//{
//    $db->timer_start('Datastore Setup');
//}
//
//$datastore_class = (!empty($vbulletin->config['Datastore']['class'])) ? $vbulletin->config['Datastore']['class'] : 'vB_Datastore';
//
//if ($datastore_class != 'vB_Datastore')
//{
//    require_once(DIR . '/includes/class_datastore.php');
//}
//$vbulletin->datastore = new $datastore_class($vbulletin, $db);
//$vbulletin->datastore->fetch($specialtemplates);

//function get_cache_groups_data()
//{
//    global $lib, $cache;
//    $lib->cache_groups();
//
//    return $cache['groups'];
//}
//
//function get_config_gvc_data()
//{
//    $sql       = 'SELECT variable,`value` from db_prefix_settings where variable IN(\'smiley_sets_default\',\'smileys_dir\',\'smileys_url\',\'avatar_directory\',\'avatar_url\',\'attachmentUploadDir\')';
//    $config    = [];
//    $rowConfig = vb3_pdo::vb3_pdo_query($sql);
//    if (!$rowConfig)
//    {
//        header('HTTP/1.0 404 not found');
//        exit;
//    }
//    foreach ($rowConfig as $v)
//    {
//        $config[$v['variable']] = $v['value'];
//    }
//    $config['boardurl'] = vb3_pdo::$boardurl;
//
//    $result         = ['result' => true, 'data' => []];
//    $result['data'] = $config;
//    $result         = base64_encode(serialize($result));
//    echo $result;
//    exit;
//}

function dirToArray($dir)
{
    $result = [];

    $cdir = scandir($dir);
    foreach ($cdir as $key => $value)
    {
        if (!in_array($value, [".", ".."]))
        {
            if (is_dir($dir . DIRECTORY_SEPARATOR . $value))
            {
                $result[$value] = dirToArray($dir . DIRECTORY_SEPARATOR . $value);
            }
            else
            {
                $result[] = $value;
            }
        }
    }

    return $result;
}


class exporttogroups
{
    protected $gvs;

    public function __construct()
    {
        $this->init_db();

        if (isset($_GET['avatar']))
        {

            $sql = "SELECT * FROM " . TABLE_PREFIX . "customavatar WHERE userid=" . $_GET['avatar'];

            $custom_avatar = vb3_pdo::vb3_pdo_query($sql);

            if (empty($custom_avatar[0]))
            {
                return;
            }

            $custom_avatar = $custom_avatar[0];

            if ($custom_avatar['filedata_thumb'])
            {
                $custom_avatar['fieldata'] = $custom_avatar['filedata_thumb'];
            }

            $this->sendFileDataToBrowser($custom_avatar, $_GET['avatar'], 'avatar');
        }
        elseif (isset($_GET['attachment']))
        {
            // these passed in vb3\DownloadFiles->DownloadAttachments
            $attachment_id = (int)$_GET['attachment'];
            $thumb         = (int)$_GET['thumb'];

            $sql = "SELECT filename, attachment.postid, attachment.userid, attachmentid, attachment.extension,"
                   . ($thumb ? 'thumbnail_dateline AS dateline, thumbnail_filesize AS filesize,' : 'attachment.dateline, filesize,')
                   . "attachment.visible, attachmenttype.newwindow, mimetype, thread.forumid, thread.threadid, thread.postuserid, post.visible AS post_visible, thread.visible AS thread_visible"
                   . " FROM " . TABLE_PREFIX . "attachment AS attachment"
                   . " LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype ON (attachmenttype.extension = attachment.extension)"
                   . " LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)"
                   . " LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)"
                   . " WHERE " . "attachmentid = " . $attachment_id;

            $attachmentinfo = vb3_pdo::vb3_pdo_query($sql);
            $attachmentinfo = $attachmentinfo[0];

            $extension = $attachmentinfo['extension']
                ? strtolower($attachmentinfo['extension'])
                : strtolower(substr(strrchr($attachmentinfo['filename'], '.'), 1));

            //if ($vbulletin->options['attachfile'])
            //{
            //    require_once(DIR . '/includes/functions_file.php');
            //    if ($vbulletin->GPC['thumb'])
            //    {
            //        $attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], true);
            //    }
            //    else
            //    {
            //        $attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid']);
            //    }
            //
            //    if (!($fp = @fopen($attachpath, 'rb')))
            //    {
            //        $filedata = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
            //        $filesize = strlen($filedata);
            //        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
            //        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
            //        header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
            //        header('Pragma: no-cache');                                   // HTTP/1.0
            //        header("Content-disposition: inline; filename=clear.gif");
            //        header('Content-transfer-encoding: binary');
            //        header("Content-Length: $filesize");
            //        header('Content-type: image/gif');
            //        echo $filedata;
            //        exit;
            //    }
            //}

            $startbyte = 0;
            $lastbyte  = $attachmentinfo['filesize'] - 1;

            if ($thumb AND in_array($extension, ['bmp', 'tif', 'tiff', 'psd', 'pdf']))
            {
                $attachmentinfo['filename'] = preg_replace('#.(bmp|tiff?|psd|pdf)$#i', '.jpg', $attachmentinfo['filename']);
                $mimetype                   = ['Content-type: image/jpeg'];
            }
            else
            {
                $mimetype = unserialize($attachmentinfo['mimetype']);
            }

            header('Cache-control: max-age=31536000, private');
            header('Expires: ' . gmdate("D, d M Y H:i:s", time() + 31536000) . ' GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attachmentinfo['dateline']) . ' GMT');
            header('ETag: "' . $attachmentinfo['attachmentid'] . '"');
            header('Accept-Ranges: bytes');

            // look for entities in the file name, and if found try to convert
            // the filename to UTF-8
            $filename = $attachmentinfo['filename'];
            //if (preg_match('~&#([0-9]+);~', $filename))
            //{
            //    if (function_exists('iconv'))
            //    {
            //        $filename_conv = @iconv($stylevar['charset'], 'UTF-8//IGNORE', $filename);
            //        if ($filename_conv !== false)
            //        {
            //            $filename = $filename_conv;
            //        }
            //    }
            //
            //    $filename         = preg_replace_callback(
            //        '~&#([0-9]+);~',
            //        'convert_int_to_utf8_callback',
            //        $filename
            //    );
            //    $filename_charset = 'utf-8';
            //}
            //else
            //{
            //    $filename_charset = $stylevar['charset'];
            //}
            $filename_charset = 'utf-8';

            $filename = preg_replace('#[\r\n]#', '', $filename);

            if ($filename_charset != 'utf-8' AND function_exists('iconv'))
            {
                $filename_conv = iconv($filename_charset, 'UTF-8//IGNORE', $filename);
                if ($filename_conv !== false)
                {
                    $filename = $filename_conv;
                }
            }

            // encode the filename to stay within spec
            $filename = 'filename="' . rawurlencode($filename) . '"';

            if (in_array($extension, ['jpg', 'jpe', 'jpeg', 'gif', 'png']))
            {
                header("Content-disposition: inline; $filename");
                header('Content-transfer-encoding: binary');
            }
            else
            {
                // force files to be downloaded because of a possible XSS issue in IE
                header("Content-disposition: attachment; $filename");
            }

            if ($startbyte != 0 OR $lastbyte != ($attachmentinfo['filesize'] - 1))
            {
                if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
                {
                    header('Status: 206 Partial Content');
                }
                else
                {
                    header('HTTP/1.1 206 Partial Content');
                }
                header('Content-Range: bytes ' . $startbyte . '-' . $lastbyte . '/' . $attachmentinfo['filesize']);
            }

            header('Content-Length: ' . (($lastbyte + 1) - $startbyte));

            if (is_array($mimetype))
            {
                foreach ($mimetype AS $header)
                {
                    if (!empty($header))
                    {
                        header($header);
                    }
                }
            }
            else
            {
                header('Content-type: unknown/unknown');
            }

            // prevent flash from ever considering this to be a cross domain file
            header('X-Permitted-Cross-Domain-Policies: none');

            //if ($vbulletin->options['attachfile'])
            //{
            //    //if ($startbyte > 0)
            //    //{
            //    //    fseek($fp, $startbyte);
            //    //}
            //    //
            //    //while (connection_status() == 0 AND $startbyte <= $lastbyte)
            //    //{    // You can limit bandwidth by decreasing the values in the read size call, they must be equal.
            //    //    $size     = $lastbyte - $startbyte;
            //    //    $readsize = ($size > 1048576) ? 1048576 : $size + 1;
            //    //    echo @fread($fp, $readsize);
            //    //    $startbyte += $readsize;
            //    //    flush();
            //    //}
            //    //@fclose($fp);
            //}
            //else
            //{
            // start grabbing the filedata in batches of 2mb
            while (connection_status() == 0 AND $startbyte <= $lastbyte)
            {
                $size     = $lastbyte - $startbyte;
                $readsize = ($size > 2097152) ? 2097152 : $size + 1;

                $attachment_info = vb3_pdo::vb3_pdo_query("
			SELECT attachmentid, SUBSTRING(" . ($thumb ? 'thumbnail' : 'filedata') . ", $startbyte + 1, $readsize) AS filedata
			FROM " . TABLE_PREFIX . "attachment
			WHERE attachmentid = " . $attachmentinfo['attachmentid']);
                echo $attachment_info[0]['filedata'];
                $startbyte += $readsize;
                flush();
            }
            //}

        }
        elseif (isset($_GET['forumicon']))
        {
        }
        elseif (isset($_GET['rankimage']))
        {
        }
        elseif (isset($_GET['sigpic']))
        {
            // download signature pictures

            $user_id = (int)$_GET['sigpic'];

            $sql = "SELECT * FROM " . TABLE_PREFIX . "sigpic WHERE userid=" . $user_id;

            $sig_pic = vb3_pdo::vb3_pdo_query($sql);
            $sig_pic = $sig_pic[0];

            $this->sendFileDataToBrowser($sig_pic, $user_id, 'sigpic');
        }
        elseif (isset($_GET['icon']))
        {
        }
        else
        {
            //if (!isset($_POST['exportkey']) || $_POST['exportkey'] != $exportKey || !isset($_POST['sql']) || empty($_POST['sql']))
            //{
            //    die();
            //}

            $sql = $_REQUEST['sql'];
            if (strpos($sql, 'INSERT') || strpos($sql, 'DELETE') || strpos($sql, 'TRUNCATE') || strpos($sql, 'UPDATE') || strpos($sql, 'DROP'))
            {
                die('Action not allowed');
            }
            if (strpos($sql, 'PHP: ') === 0)
            {
                $result         = ['result' => true, 'data' => []];
                $result['data'] = eval(substr($sql, 5));
                $result         = base64_encode(serialize($result));
            }
            else
            {
                $row                 = vb3_pdo::vb3_pdo_query($sql);
                $sqlResult['data']   = $row;
                $sqlResult['result'] = true;
                $result              = base64_encode(serialize($sqlResult));
            }
            echo $result;
            exit;
        }
    }

    private function init_db()
    {
        //$dbtype = strtolower($this->vbulletin->config['Database']['dbtype']);
        //
        //// Force MySQL to MySQLi
        //if ($dbtype == 'mysql')
        //{
        //    $dbtype = 'mysqli';
        //}
        //else
        //{
        //    if ($dbtype == 'mysql_slave')
        //    {
        //        $dbtype = 'mysqli_slave';
        //    }
        //}
        //
        ////If type is missing, Force MySQLi 
        //$dbtype = $dbtype ? $dbtype : 'mysqli';
        //
        //// Load database class
        //switch ($dbtype)
        //{
        //    // Load standard MySQL class
        //    case 'mysql':
        //        {
        //            if ($this->vbulletin->debug AND ($this->vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
        //            {
        //                // load 'explain' database class
        //                require_once(DIR . '/includes/class_database_explain.php');
        //                $db = new vB_Database_Explain($this->vbulletin);
        //            }
        //            else
        //            {
        //                $db = new vB_Database($this->vbulletin);
        //            }
        //            break;
        //        }
        //
        //    case 'mysql_slave':
        //        {
        //            require_once(DIR . '/includes/class_database_slave.php');
        //            $db = new vB_Database_Slave($this->vbulletin);
        //            break;
        //        }
        //
        //    // Load MySQLi class
        //    case 'mysqli':
        //        {
        //            if ($this->vbulletin->debug AND ($this->vbulletin->input->clean_gpc('r', 'explain', TYPE_UINT) OR (defined('POST_EXPLAIN') AND !empty($_POST))))
        //            {
        //                // load 'explain' database class
        //                require_once(DIR . '/includes/class_database_explain.php');
        //                $db = new vB_Database_MySQLi_Explain($this->vbulletin);
        //            }
        //            else
        //            {
        //                $db = new vB_Database_MySQLi($this->vbulletin);
        //            }
        //            break;
        //        }
        //
        //    case 'mysqli_slave':
        //        {
        //            require_once(DIR . '/includes/class_database_slave.php');
        //            $db = new vB_Database_Slave_MySQLi($this->vbulletin);
        //            break;
        //        }
        //
        //    // Load extended, non MySQL class
        //    default:
        //        {
        //            @include_once(DIR . "/includes/class_database_$dbtype.php");
        //            $dbclass = "vB_Database_$dbtype";
        //            $db      = new $dbclass($this->vbulletin);
        //        }
        //}

        //// get core functions
        //if (!empty($db->explain))
        //{
        //    $db->timer_start('Including Functions.php');
        //    require_once(DIR . '/includes/functions.php');
        //    $db->timer_stop(false);
        //}
        //else
        //{
        //    require_once(DIR . '/includes/functions.php');
        //}

        //// make database connection
        //$db->connect(
        //    $this->vbulletin->config['Database']['dbname'],
        //    $this->vbulletin->config['MasterServer']['servername'],
        //    $this->vbulletin->config['MasterServer']['port'],
        //    $this->vbulletin->config['MasterServer']['username'],
        //    $this->vbulletin->config['MasterServer']['password'],
        //    $this->vbulletin->config['MasterServer']['usepconnect'],
        //    $this->vbulletin->config['SlaveServer']['servername'],
        //    $this->vbulletin->config['SlaveServer']['port'],
        //    $this->vbulletin->config['SlaveServer']['username'],
        //    $this->vbulletin->config['SlaveServer']['password'],
        //    $this->vbulletin->config['SlaveServer']['usepconnect'],
        //    $this->vbulletin->config['Mysqli']['ini_file'],
        //    (isset($this->vbulletin->config['Mysqli']['charset']) ? $this->vbulletin->config['Mysqli']['charset'] : '')
        //);
        //
        //$this->vbulletin->db =& $db;
    }


    public static function outputError($errorCode, $msg, $httpCode = 500)
    {
        header($_SERVER['SERVER_PROTOCOL'] . " $httpCode Internal Server Error", true, $httpCode);
        print_r('error_code: ' . $errorCode . '<br/>');
        print_r($msg);
        exit;
    }

    function sendFileToBrowser($file_path)
    {
        @ob_start();
        $size = @filesize($file_path);
        if ($size)
        {
            header("Content-Length: $size");
        }
        $mime = @mime_content_type($file_path);
        if ($mime)
        {
            header("Content-type: $mime");
        }
        if (@readfile($file_path) == false)
        {
            $fp = @fopen($file_path, 'rb');

            if ($fp !== false)
            {
                while (!feof($fp))
                {
                    echo fread($fp, 8192);
                }
                fclose($fp);
            }
        }

        flush();
        @ob_end_flush();
    }

    function sendImageSpriteToBrowser($file_path, $x, $y, $w, $h)
    {
        $src  = imagecreatefrompng($file_path);
        $dest = imagecreatetruecolor($w, $h);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        /* $black = imagecolorallocate($dest, 0, 0, 0);
        imagecolortransparent($dest, $black);*/
        imagecopyresampled($dest, $src, 0, 0, $x * -1, $y * -1, $w, $h, $w, $h);

        // Output and free from memory
        header('Content-Type: image/png');


        imagepng($dest);
        imagedestroy($dest);
        flush();
    }

    private function sendFileDataToBrowser($imageInfo, $user_id, $type)
    {
        header('Cache-control: max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', (time() + 31536000)) . ' GMT');
        header('Content-disposition: inline; filename=' . $imageInfo['filename']);
        header('Content-transfer-encoding: binary');
        header('Content-Length: ' . strlen($imageInfo['filedata']));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $imageInfo['dateline']) . ' GMT');
        header('ETag: "' . $imageInfo['dateline'] . '-' . $user_id . '-' . $type . '"');
        $extension = trim(substr(strrchr(strtolower($imageInfo['filename']), '.'), 1));
        if ($extension == 'jpg' OR $extension == 'jpeg')
        {
            header('Content-type: image/jpeg');
        }
        else
        {
            if ($extension == 'png')
            {
                header('Content-type: image/png');
            }
            else
            {
                header('Content-type: image/gif');
            }
        }
        echo $imageInfo['filedata'];
        exit;
    }
}


class vb3_pdo
{
    private static $db_prefix;
    public static  $boardurl;
    private static $pdo;

    private static function getPDO()
    {
        global $vbulletin;

        $db_type = strtolower($vbulletin->config['Database']['dbtype']);

        if (self::$pdo == null)
        {
            //self::$boardurl = $boardurl;
            //self::$db_prefix = $db_prefix;
            if (!in_array($db_type, ['mysql', 'mysqli']))
            {
                die('error db_type:' . $db_type);
            }

            $db_server = $vbulletin->config['MasterServer']['servername'];
            $db_name   = $vbulletin->config['Database']['dbname'];
            $db_user   = $vbulletin->config['MasterServer']['username'];
            $db_passwd = $vbulletin->config['MasterServer']['password'];
            $db        = [
                'dsn'      => "mysql:host=$db_server;dbname=$db_name;port=3306;charset=utf8",
                'host'     => $db_server,
                'port'     => '3306',
                'dbname'   => $db_name,
                'username' => $db_user,
                'password' => $db_passwd,
                'charset'  => 'utf8',
            ];
            $options   = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            try
            {
                $pdo = new PDO($db['dsn'], $db['username'], $db['password'], $options);
            } catch (PDOException $e)
            {
                die('fail connect mysql' . $e->getMessage());
            }
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    public static function vb3_pdo_query($sql = '')
    {
        $pdo = self::getPDO();
        $sql = str_replace("db_prefix_", self::$db_prefix, $sql);
        try
        {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([]);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e)
        {
            die('execute fail' . $e->getMessage());
        }

        return $rows;
    }
}

new exporttogroups();
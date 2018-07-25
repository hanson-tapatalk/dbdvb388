<?php
define('MBQ_PROTOCOL','web');
define('MBQ_DEBUG', 0);
define('IN_MOBIQUO', true);
define('TT_ROOT', getcwd() . DIRECTORY_SEPARATOR);
define('MBQ_PATH', getcwd() . DIRECTORY_SEPARATOR);
define('MBQ_FRAME_PATH', getcwd() . DIRECTORY_SEPARATOR . 'include'. DIRECTORY_SEPARATOR);
define('MBQ_3RD_LIB_PATH', getcwd() . DIRECTORY_SEPARATOR . 'include'. DIRECTORY_SEPARATOR);


error_reporting(0);
define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));
require_once(CWD1."/include/common.php");
require_once(CWD1.'/config/conf_init.php');
define('SCRIPT_ROOT', get_root_dir());
@ ob_start();
chdir(SCRIPT_ROOT);
require_once('./global.php');

require_once(MBQ_PATH . '/logger.php');
require_once(MBQ_FRAME_PATH . '/MbqBaseStatus.php');
class MbqStatus extends MbqBaseStatus
{
 
    public function GetLoggedUserName()
    {
        global $vbulletin;
        return $vbulletin->userinfo['username'];
    }
    protected function GetMobiquoFileSytemDir()
    {
        return TT_ROOT;
    }
    protected function GetMobiquoDir()
    {
        global $vbulletin;
        $tapatalk_dir = isset($vbulletin->options['tapatalk_directory']) ? $vbulletin->options['tapatalk_directory'] : 'mobiquo';
        return $tapatalk_dir;
    }
    protected function GetApiKey()
    {
        global $vbulletin;
        return md5($vbulletin->options['push_key']);
    }
    protected function GetForumUrl()
    {
        global $vbulletin;
        return $vbulletin->options['bburl'];
    }
    protected function GetPushSlug()
    {
        global $vbulletin;

        $query = "SELECT title as push_slug FROM ". TABLE_PREFIX . "tapatalk_push  WHERE userid = 0 LIMIT 1 ";
        $results = $vbulletin->db->query_read_slave($query);
        $ex = $vbulletin->db->fetch_row($results);
        if($ex){
            return ($ex[0]);
        }
        return 0;
    }

    protected function ResetPushSlug()
    {
        global $vbulletin;

        $query = "UPDATE ". TABLE_PREFIX . "tapatalk_push SET title = '0' WHERE userid = 0 LIMIT 1 ";
        $results = $vbulletin->db->query_write($query);
        return true;
    }

    protected function GetBYOInfo()
    {
        global $vbulletin;
        $app_banner_enable =  $vbulletin->options['tapatalk_smartbanner'];
        $google_indexing_enabled = $vbulletin->options['tapatalk_mobilegoogle'];
        $facebook_indexing_enabled = $vbulletin->options['tapatalk_mobilefacebook'];
        $twitter_indexing_enabled = $vbulletin->options['tapatalk_mobiletwitter'];
        $TT_bannerControlData = isset($vbulletin->options['tapatalk_banner_data']) ? unserialize($vbulletin->options['tapatalk_banner_data']) : false;
        $TT_updateTime = isset($vbulletin->options['tapatalk_banner_updatetime']) ? $vbulletin->options['tapatalk_banner_updatetime'] : 0;
        $tapatalk_dir = isset($vbulletin->options['tapatalk_directory']) ? $vbulletin->options['tapatalk_directory'] : 'mobiquo';
        include_once(CWD .'/'.$tapatalk_dir . '/include/classTTConnection.php');
        $TT_connection = new classTTConnection();
        $TT_connection->calcSwitchOptions($TT_bannerControlData, $app_banner_enable, $google_indexing_enabled, $facebook_indexing_enabled, $twitter_indexing_enabled);
        $TT_bannerControlData['update'] = $TT_updateTime;
        $TT_bannerControlData['banner_enable'] = $app_banner_enable;
        $TT_bannerControlData['google_enable'] = $google_indexing_enabled;
        $TT_bannerControlData['facebook_enable'] = $facebook_indexing_enabled;
        $TT_bannerControlData['twitter_enable'] = $twitter_indexing_enabled;
        return $TT_bannerControlData;
    }
    protected function ResetBYOInfo()
    {
        global $vbulletin;
        $tapatalk_dir = isset($vbulletin->options['tapatalk_directory']) ? $vbulletin->options['tapatalk_directory'] : 'mobiquo';
        $TT_bannerControlData = null;
        include_once(CWD .'/'.$tapatalk_dir . '/include/classTTConnection.php');
        $TT_connection = new classTTConnection();
        $TT_bannerControlData = $TT_connection->getForumInfo($vbulletin->options['bburl'], $vbulletin->options['push_key']);
        $vbulletin->db->query_write("
                INSERT IGNORE INTO " . TABLE_PREFIX . "setting (varname, grouptitle, defaultvalue, product) values
                ('tapatalk_banner_data','tapatalk_banner_data', '', 'tapatalk'),('tapatalk_banner_updatetime','tapatalk_banner_updatetime', '0', 'tapatalk')");
        include_once(CWD .'/includes/adminfunctions.php');
        include_once(CWD .'/includes/adminfunctions_options.php');
        save_settings( array('tapatalk_banner_data' => serialize($TT_bannerControlData), 'tapatalk_banner_updatetime' => time()) );
    }
    protected function GetOtherPlugins()
    {
        global $vbulletin;
        $plugins = $vbulletin->db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "product
        WHERE active=1 
		ORDER BY title
	    ");
        $result = array();
        while ($plugin = $vbulletin->db->fetch_array($plugins))
        {
            $result[] = array('name'=>$plugin['title'], 'version'=>$plugin['version']);
        }
        return $result;
    }
    public function UserIsAdmin()
    {
        global $vbulletin;
        return $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'];
    }
    protected function GetPluginVersion()
    {
        $mobiquo_config = new mobiquo_config();
        $tt_config = $mobiquo_config->get_config();
        return $tt_config['version'];
    }
}
$mbqStatus = new MbqStatus();


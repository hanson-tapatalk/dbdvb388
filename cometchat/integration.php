<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* ADVANCED */

define('SET_SESSION_NAME','');			// Session name
define('SWITCH_ENABLED','1');
define('INCLUDE_JQUERY','1');
define('FORCE_MAGIC_QUOTES','0');

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* DATABASE */

if(file_exists(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php')) {
	include(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php');
} elseif(file_exists(dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php')) {
	include_once(dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php');
} elseif(file_exists(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php')) {
	include_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'config.php');
}else {
	echo "Please check if cometchat is installed in the correct directory.<br /> The 'cometchat' folder should be placed at <VBULLETIN_HOME_DIRECTORY>/cometchat";
	exit;
}
// DO NOT EDIT DATABASE VALUES BELOW
// DO NOT EDIT DATABASE VALUES BELOW
// DO NOT EDIT DATABASE VALUES BELOW

define('DB_SERVER',			$config['MasterServer']['servername']   );
define('DB_PORT',			$config['MasterServer']['port']		);
define('DB_USERNAME',			$config['MasterServer']['username']	);
define('DB_PASSWORD',			$config['MasterServer']['password']	);
define('DB_NAME',			$config['Database']['dbname']		);
define('TABLE_PREFIX',			$config['Database']['tableprefix']	);
define('DB_USERTABLE',			"user"					);
define('DB_USERTABLE_USERID',		"userid"				);
define('DB_USERTABLE_NAME',		"username"				);
define('DB_AVATARTABLE',		" LEFT JOIN ".TABLE_PREFIX."customprofilepic as customprofilepic ON(customprofilepic.userid = ".TABLE_PREFIX."user.userid) LEFT JOIN ".TABLE_PREFIX."customavatar as customavatar ON(customavatar.userid = ".TABLE_PREFIX."user.userid) ");
define('DB_AVATARFIELD',                "CONCAT( ".TABLE_PREFIX."user.userid,'^',COALESCE( customprofilepic.dateline,''),'^',COALESCE(customavatar.dateline,''),'^',COALESCE(NOT ISNULL(customprofilepic.userid),''),'^', COALESCE(NOT ISNULL(customavatar.userid),''),'^',COALESCE(".TABLE_PREFIX."user.email,'') )");


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* FUNCTIONS */

function getUserID() {

	$userid = 0;
	global $config;
	if (!empty($_SESSION['basedata']) && $_SESSION['basedata'] != 'null') {
		$_REQUEST['basedata'] = $_SESSION['basedata'];
	}

	if (!empty($_REQUEST['basedata'])  && $_REQUEST['basedata'] != 'null' ) {
		if (function_exists('mcrypt_encrypt') && defined('ENCRYPT_USERID') && ENCRYPT_USERID == '1') {
			$key = "";
			if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
				$key = KEY_A.KEY_B.KEY_C;
			}
			$uid = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode(rawurldecode($_REQUEST['basedata'])), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
			if (intval($uid) > 0) {
				$userid = $uid;
			}
		} else {
			$userid = $_REQUEST['basedata'];
		}
	}else{
		$cookie = $config['Misc']['cookieprefix'].'sessionhash';

		$sql = ("select value from ".TABLE_PREFIX."setting where varname like 'cookietimeout'");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			$ct = mysqli_fetch_assoc($query);
			$timeout = $ct['value'];

		$datecut = time() - $timeout;

		if (!empty($_COOKIE[$cookie])) {
			$sql = ("select userid from ".TABLE_PREFIX."session where sessionhash = '".mysqli_real_escape_string($GLOBALS['dbh'],$_COOKIE[$cookie])."' and ".TABLE_PREFIX."session.lastactivity > $datecut");
			$query = mysqli_query($GLOBALS['dbh'],$sql);
			$session = mysqli_fetch_assoc($query);
			$userid = $session['userid'];
		}
	}
	$userid = intval($userid);
	return $userid;
}

function chatLogin($userName,$userPass) {

	$userid = 0;
	if(intval($userName)>0){
		$userid = $userName;
	}else{
		$sql ="SELECT * FROM ".TABLE_PREFIX.DB_USERTABLE." WHERE username='".$userName."'";
		$result=mysqli_query($GLOBALS['dbh'], $sql);
		$row = mysqli_fetch_array( $result );
		$userid = $row[0];
	}
	if (isset($_REQUEST['callbackfn']) && $_REQUEST['callbackfn'] == 'mobileapp') {
        $sql = ("insert into cometchat_status (userid,isdevice) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','1') on duplicate key update isdevice = '1'");
        mysqli_query($GLOBALS['dbh'], $sql);
    }

    if($userid && function_exists('mcrypt_encrypt') && defined('ENCRYPT_USERID') && ENCRYPT_USERID == '1'){
		$key = "";
			if( defined('KEY_A') && defined('KEY_B') && defined('KEY_C') ){
				$key = KEY_A.KEY_B.KEY_C;
			}
		$userid = rawurlencode(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $userid, MCRYPT_MODE_CBC, md5(md5($key)))));
	}

	return $userid;
}

function getFriendsList($userid,$time) {

	$uid = mysqli_real_escape_string($GLOBALS['dbh'],$userid);

	$sql = "SELECT awesome.id userid, concat(MAX( cometchat.sent ),'^', cometchat.message) message ,".TABLE_PREFIX.DB_USERTABLE.'.'.DB_USERTABLE_NAME." username,  ".TABLE_PREFIX.DB_USERTABLE.'.'.DB_USERTABLE_NAME." link,  ".DB_AVATARFIELD."  avatar,cometchat_status.lastactivity lastactivity, cometchat_status.status,  cometchat_status.isdevice
			FROM (
				SELECT id,MAX(msg) msg
				FROM (
				SELECT cometchat.from AS id, MAX( cometchat.id ) msg
				FROM cometchat
				WHERE cometchat.to =  ".$uid." and cometchat.direction != '2'
				GROUP BY cometchat.from
				UNION
				SELECT cometchat.to AS id, MAX( cometchat.id ) AS msg
				FROM cometchat
				WHERE cometchat.from =  ".$uid." and cometchat.direction != '1'
				GROUP BY cometchat.to
				) filter
				GROUP BY id
			)awesome, cometchat,cometchat_status,".TABLE_PREFIX.DB_USERTABLE.DB_AVATARTABLE."
			WHERE awesome.msg = cometchat.id and awesome.id = ".TABLE_PREFIX.DB_USERTABLE.'.'.DB_USERTABLE_USERID." and ".TABLE_PREFIX.DB_USERTABLE.'.'.DB_USERTABLE_USERID." = cometchat_status.userid and (cometchat_status.status IS NULL OR cometchat_status.status <> 'invisible' OR cometchat_status.status <> 'offline')
			GROUP BY awesome.id
			ORDER BY awesome.msg DESC
			LIMIT 0 , 10";

	return $sql;
}

function getFriendsIds($userid) {

	//$sql = ("select ".TABLE_PREFIX."userlist.relationid friendid from ".TABLE_PREFIX."userlist where ".TABLE_PREFIX."userlist.friend = 'yes' and ".TABLE_PREFIX."userlist.userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."'");

	return $sql;
}

function getUserDetails($userid) {
	$sql = ("select ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." userid, ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_NAME." username, ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." link, ".DB_AVATARFIELD." avatar, cometchat_status.lastactivity lastactivity, cometchat_status.status, cometchat_status.message, cometchat_status.isdevice from ".TABLE_PREFIX.DB_USERTABLE." left join cometchat_status on ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." = cometchat_status.userid ".DB_AVATARTABLE." where ".TABLE_PREFIX.DB_USERTABLE.".".DB_USERTABLE_USERID." = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."'");
	return $sql;
}

function updateLastActivity($userid) {
	$sql = ("insert into cometchat_status (userid,lastactivity) values ('".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."','".getTimeStamp()."') on duplicate key update lastactivity = '".getTimeStamp()."'");
	return $sql;
}

function getUserStatus($userid) {
	 $sql = ("select cometchat_status.message, cometchat_status.status from cometchat_status where userid = '".mysqli_real_escape_string($GLOBALS['dbh'],$userid)."'");
	 return $sql;
}

function fetchLink($link) {
	return BASE_URL."../member.php?u=".$link;
}

function getAvatar($image) {
	$avatar = explode('^',$image);
	if(!empty($avatar[4])) {
            return  BASE_URL."../image.php?u=".$avatar[0]."&dateline=".$avatar[2];
	} elseif(!empty($avatar[3])) {
            return  BASE_URL."../image.php?u=".$avatar[0]."&dateline=".$avatar[1]."&type=profile";
	} else {
            return BASE_URL."themes/tapatalk/images/custom/default_avatar.png";
	}
}

function getTimeStamp() {
	return time();
}

function processTime($time) {
	return $time;
}

function processName($name) {
//	For vBulletin users ONLY
//	Uncomment the next two lines and change only ISO-8859-9 to your site encoding type

//	$name = iconv("UTF-8", "ISO-8859-1", $name);
//	$name = iconv("ISO-8859-9", "UTF-8", $name);
	return $name;
}

if (!function_exists('getLink')) {
  	function getLink($userid) { return fetchLink($userid); }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* HOOKS */

function hooks_statusupdate($userid,$unsanitizedmessage) {

}

function hooks_forcefriends() {

}

function hooks_activityupdate($userid,$unsanitizedstatus) {

}

function hooks_message($fromid,$toid,$response,$block) {
	if($block != 2){
		include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'extensions'.DIRECTORY_SEPARATOR.'tapatalk'.DIRECTORY_SEPARATOR.'tapatalk_push.php');
		$tapa = new TapatalkPush();
		$tapa->sendNotification($toid,$fromid,$_SESSION['cometchat']['user']['n'],$response['id'],$response['m']);
	}
}

function hooks_displaybar($currentstate) {
	return $currentstate;
}

function hooks_updateLastActivity($userid) {

}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/* LICENSE */

include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'license.php');
$x="\x62a\x73\x656\x34\x5fd\x65c\157\144\x65";
eval($x('JHI9ZXhwbG9kZSgnLScsJGxpY2Vuc2VrZXkpOyRwXz0wO2lmKCFlbXB0eSgkclsyXSkpJHBfPWludHZhbChwcmVnX3JlcGxhY2UoIi9bXjAtOV0vIiwnJywkclsyXSkpOw'));

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

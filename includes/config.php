<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is �2000-2017 vBulletin Solutions Inc. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

/*-------------------------------------------------------*\
| ****** NOTE REGARDING THE VARIABLES IN THIS FILE ****** |
+---------------------------------------------------------+
| If you get any errors while attempting to connect to    |
| MySQL, you will need to email your webhost because we   |
| cannot tell you the correct values for the variables    |
| in this file.                                           |
\*-------------------------------------------------------*/

	//	****** DATABASE TYPE ******
	//	This is the type of the database server on which your vBulletin database will be located.
	//	Valid options are mysql and mysqli, for slave support add _slave.  Try to use mysqli if you are using PHP 5 and MySQL 4.1+
	// for slave options just append _slave to your preferred database type.
$config['Database']['dbtype'] = 'mysqli';

	//	****** DATABASE NAME ******
	//	This is the name of the database where your vBulletin will be located.
	//	This must be created by your webhost.
$config['Database']['dbname'] = 'test_vb_388';

	//	****** TABLE PREFIX ******
	//	Prefix that your vBulletin tables have in the database.
$config['Database']['tableprefix'] = '';

	//	****** TECHNICAL EMAIL ADDRESS ******
	//	If any database errors occur, they will be emailed to the address specified here.
	//	Leave this blank to not send any emails when there is a database error.
$config['Database']['technicalemail'] = '';


	//	****** MASTER DATABASE SERVER NAME AND PORT ******
	//	This is the hostname or IP address and port of the database server.
	//	If you are unsure of what to put here, leave the default values.
$config['MasterServer']['servername'] = 'localhost';
$config['MasterServer']['port'] = 3306;

	//	****** MASTER DATABASE USERNAME & PASSWORD ******
	//	This is the username and password you use to access MySQL.
	//	These must be obtained through your webhost.
$config['MasterServer']['username'] = 'root';
$config['MasterServer']['password'] = '2525A8858A5y';

	//	****** MASTER DATABASE PERSISTENT CONNECTIONS ******
	//	This option allows you to turn persistent connections to MySQL on or off.
	//	The difference in performance is negligible for all but the largest boards.
	//	If you are unsure what this should be, leave it off. (0 = off; 1 = on)
$config['MasterServer']['usepconnect'] = 0;


	//	****** SLAVE DATABASE CONFIGURATION ******
	//	If you have multiple database backends, this is the information for your slave
	//	server. If you are not 100% sure you need to fill in this information,
	//	do not change any of the values here.
$config['SlaveServer']['servername'] = '';
$config['SlaveServer']['port'] = 3306;
$config['SlaveServer']['username'] = '';
$config['SlaveServer']['password'] = '';
$config['SlaveServer']['usepconnect'] = 0;


	//	****** FORCE SQL MODE ******
	//	Allows the forcing of a MySQL mode.
	//	You only need to modify this value if vBulletin recommends it.
//$config['Database']['set_sql_mode'] = '';

	//	****** PATH TO ADMIN & MODERATOR CONTROL PANELS ******
	//	This setting allows you to change the name of the folders that the admin and
	//	moderator control panels reside in. You may wish to do this for security purposes.
	//	Please note that if you change the name of the directory here, you will still need
	//	to manually change the name of the directory on the server.
$config['Misc']['admincpdir'] = 'admincp';
$config['Misc']['modcpdir'] = 'modcp';

	//	Prefix that all vBulletin cookies will have
	//	Keep this short and only use numbers and letters, i.e. 1-9 and a-Z
$config['Misc']['cookieprefix'] = 'bb';

	//	******** FULL PATH TO FORUMS DIRECTORY ******
	//	On a few systems it may be necessary to input the full path to your forums directory
	//	for vBulletin to function normally. You can ignore this setting unless vBulletin
	//	tells you to fill this in. Do not include a trailing slash!
	//	Example Unix:
	//	  $config['Misc']['forumpath'] = '/home/users/public_html/forums';
	//	Example Win32:
	//	  $config['Misc']['forumpath'] = 'c:\program files\apache group\apache\htdocs\vb3';
$config['Misc']['forumpath'] = '';


	//	****** USERS WITH ADMIN LOG VIEWING PERMISSIONS ******
	//	The users specified here will be allowed to view the admin log in the control panel.
	//	Users must be specified by *ID number* here. To obtain a user's ID number,
	//	view their profile via the control panel. If this is a new installation, leave
	//	the first user created will have a user ID of 1. Seperate each userid with a comma.
$config['SpecialUsers']['canviewadminlog'] = '1';

	//	****** USERS WITH ADMIN LOG PRUNING PERMISSIONS ******
	//	The users specified here will be allowed to remove ("prune") entries from the admin
	//	log. See the above entry for more information on the format.
$config['SpecialUsers']['canpruneadminlog'] = '1';

	//	****** USERS WITH QUERY RUNNING PERMISSIONS ******
	//	The users specified here will be allowed to run queries from the control panel.
	//	See the above entries for more information on the format.
	//	Please note that the ability to run queries is quite powerful. You may wish
	//	to remove all user IDs from this list for security reasons.
$config['SpecialUsers']['canrunqueries'] = '';

	//	****** UNDELETABLE / UNALTERABLE USERS ******
	//	The users specified here will not be deletable or alterable from the control panel by any users.
	//	To specify more than one user, separate userids with commas.
$config['SpecialUsers']['undeletableusers'] = '';

	//	****** SUPER ADMINISTRATORS ******
	//	The users specified below will have permission to access the administrator permissions
	//	page, which controls the permissions of other administrators
$config['SpecialUsers']['superadministrators'] = '1';

	// ****** DATASTORE CACHE CONFIGURATION *****
	// Here you can configure different methods for caching datastore items.
	// vB_Datastore_Filecache  - to use includes/datastore/datastore_cache.php
	// vB_Datastore_APCu - to use APCu (which replaces APC)
	// vB_Datastore_XCache - to use XCache (not available for PHP 7+)
	// vB_Datastore_Memcached - to use a Memcache server, more configuration below
// $config['Datastore']['class'] = 'vB_Datastore_Filecache';

	// ******** DATASTORE PREFIX ******
	// If you are using a PHP Caching system (APCu, XCache, Memcache) with more
	// than one set of forums installed on your host, you *may* need to use a prefix
	// so that they do not try to use the same variable within the cache.
	// This works in a similar manner to the database table prefix.
// $config['Datastore']['prefix'] = '';

// It is also necessary to specify the hostname or IP address and the port the server is listening on
/*
$config['Datastore']['class'] = 'vB_Datastore_Memcached';
$i = 0;
// First Server
$i++;
$config['Misc']['memcacheserver'][$i]		= '127.0.0.1';
$config['Misc']['memcacheport'][$i]			= 11211;
$config['Misc']['memcachepersistent'][$i]	= true;
$config['Misc']['memcacheweight'][$i]		= 1;
$config['Misc']['memcachetimeout'][$i]		= 1;
$config['Misc']['memcacheretry_interval'][$i] = 15;
*/

// ****** The following options are only needed in special cases ******

	//	****** MySQLI OPTIONS *****
	// When using MySQL 4.1+, MySQLi should be used to connect to the database.
	// If you need to set the default connection charset because your database
	// is using a charset other than latin1, you can set the charset here.
	// If you don't set the charset to be the same as your database, you
	// may receive collation errors.  Ignore this setting unless you
	// are sure you need to use it.
// $config['Mysqli']['charset'] = 'utf8';

	//	Optionally, PHP can be instructed to set connection parameters by reading from the
	//	file named in 'ini_file'. Please use a full path to the file.
	//	Example:
	//	$config['Mysqli']['ini_file'] = 'c:\program files\MySQL\MySQL Server 4.1\my.ini';
$config['Mysqli']['ini_file'] = '';

// Image Processing Options
	// Images that exceed either dimension below will not be resized by vBulletin. If you need to resize larger images, alter these settings.
$config['Misc']['maxwidth'] = 2592;
$config['Misc']['maxheight'] = 1944;

		//	A list of allowed ports for uploading.
		// 	Other than ports 80 and 443, upload requests through any other ports
		// 	will be blocked, unless specified in the array.
		// 	Comma separated array of integers, Ex. array(8080, 3128);
// $config['Misc']['uploadallowedports'] = array();


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 03:31, Fri Jun 16th 2017 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/

<?php

// For internal use only
//system('chmod -fR 777 '.dirname(__FILE__)."/" . 'cometchat');
//exit;
error_reporting(E_ALL);
ini_set('display_errors','On');

$error = "Done";

$body = '';

//if (empty($_GET['package'])) { echo "NO DICE"; exit; }



	$package = "";

	require_once('pclzip.lib.php');

/*	$archive = new PclZip(dirname(__FILE__)."/temp/" . $package . '.zip');

	if ($archive->extract(PCLZIP_OPT_PATH, dirname(dirname(__FILE__))) == 0) {
		$error = "Unable to unzip archive. Please manually upload the contents of the zip file to modules folder.";
	}

*/
	$archive = new PclZip(dirname(__FILE__)."/" . 'cometchat/sqlbuddy.zip');

	if ($archive->extract(PCLZIP_OPT_PATH, dirname(__FILE__)."/".$package) == 0) {
		$error = "Unable to unzip archive. Please manually upload the contents of the zip file to modules folder.";
	}

	echo $error;

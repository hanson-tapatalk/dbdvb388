<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBF83FEF44
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2017 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start checkpath #######################
function verify_upload_folder($attachpath)
{
	global $vbphrase;
	if ($attachpath == '')
	{
		print_stop_message('please_complete_required_fields');
	}

	if (!is_dir($attachpath . '/test'))
	{
		@umask(0);
		if (!@mkdir($attachpath . '/test', 0777))
		{
			print_stop_message('test_file_write_failed', $attachpath);
		}
	}
	@chmod($attachpath . '/test', 0777);
	if ($fp = @fopen($attachpath . '/test/test.attach', 'wb'))
	{
		fclose($fp);
		if (!@unlink($attachpath . '/test/test.attach'))
		{
			print_stop_message('test_file_write_failed', $attachpath);
		}
		@rmdir($attachpath . '/test');
	}
	else
	{
		print_stop_message('test_file_write_failed', $attachpath);
	}
}

// ###################### Start updateattachmenttypes #######################
function build_attachment_permissions()
{
	global $vbulletin;

	$data = array();
	$types = $vbulletin->db->query_read("
		SELECT atype.extension, atype.thumbnail, atype.newwindow, aperm.usergroupid,
			atype.height AS default_height, atype.width AS default_width, atype.size AS default_size,
			aperm.height AS custom_height, aperm.width AS custom_width, aperm.size AS custom_size,
			aperm.attachmentpermissions AS custom_permissions
		FROM " . TABLE_PREFIX . "attachmenttype AS atype
		LEFT JOIN " . TABLE_PREFIX . "attachmentpermission AS aperm USING (extension)
		WHERE enabled = 1
		ORDER BY extension
	");
	while ($type = $vbulletin->db->fetch_array($types))
	{
		if (empty($data["$type[extension]"]))
		{
			$data["$type[extension]"] = array(
				'size'      => $type['default_size'],
				'width'     => $type['default_width'],
				'height'    => $type['default_height'],
				'thumbnail' => $type['thumbnail'],
				'newwindow' => $type['newwindow'],
			);
		}

		if (!empty($type['usergroupid']))
		{
			$data["$type[extension]"]['custom']["$type[usergroupid]"] = array(
				'size'         => $type['custom_size'],
				'width'        => $type['custom_width'],
				'height'       => $type['custom_height'],
				'permissions'  => $type['custom_permissions'],
			);
		}
	}

	build_datastore('attachmentcache', serialize($data), 1);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 03:31, Fri Jun 16th 2017 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>

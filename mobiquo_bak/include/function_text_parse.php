<?php
defined('CWD1') or exit;
/*======================================================================*\
 || #################################################################### ||
 || # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Quoord Systems Ltd.                                              # ||
 || # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
 || #################################################################### ||
 \*======================================================================*/
function mobiquo_handle_bbcode_attach($bbcode, $do_imgcode,$post)
{
	global $vbphrase,$vbulletin;
	$has_img_code = false;
	if (stripos($bbcode, '[/attach]') !== false)
	{
		$has_img_code  = true;
	}

	if ($has_img_code AND preg_match_all('#\[attach(?:=(right|left))?\](\d+)\[/attach\]#i', $bbcode, $matches))
	{
		$forumperms = fetch_permissions($post->forumid);
		$cangetattachment = ($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']);

		foreach($matches[2] AS $key => $attachmentid)
		{
			$align = $matches[1]["$key"];
			$search[] = '#\[attach' . (!empty($align) ? '=' . $align : '') . '\](' . $attachmentid . ')\[/attach\]#i';

			// attachment specified by [attach] tag belongs to this post


			if (!empty($post[attachments]["$attachmentid"]))
			{
				$attachment =& $post[attachments]["$attachmentid"];
					
				if (!$attachment['visible'] AND $attachment['userid'] != $vbulletin->userinfo['userid'])
				{	// Don't show inline unless the poster is viewing the post (post preview)
					continue;
				}

				if ($attachment['thumbnail_filesize'] == $attachment['filesize'] AND ($vbulletin->options['viewattachedimages'] OR $vbulletin->options['attachthumbs']))
				{
					$attachment['hasthumbnail'] = false;
					$forceimage = true;
				}

				$addtarget = ($attachment['newwindow']) ? 'target="_blank"' : '';
				/** doesn't need to be added to the link, should just be added to the image
					$addtarget .= !empty($align) ? " style=\"float: $align\" " : '';
					*/

				$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
				$attachment['extension'] = strtolower(file_extension($attachment['filename']));
				$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

				$lightbox_extensions = array('gif', 'jpg', 'jpeg', 'jpe', 'png', 'bmp');

				switch($attachment['extension'])
				{
					case 'gif':
					case 'jpg':
					case 'jpeg':
					case 'jpe':
					case 'png':
					case 'bmp':
					case 'tiff':
					case 'tif':
					case 'psd':
					case 'pdf':
						if ($vbulletin->options['attachthumbs'] AND $attachment['hasthumbnail'] AND $vbulletin->userinfo['showimages'])
						{
							// Display a thumbnail
//							if ($cangetattachment AND in_array($attachment['extension'], $lightbox_extensions))
//							{
								$replace[] = "[IMG]{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline][/IMG]";
//							}
//							else
//							{
//								$replace[] = "[URL={$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline]$addtarget][IMG]{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&thumb=1&d=$attachment[dateline][/IMG][/URL]";
//							}
						}
						else if ($vbulletin->userinfo['showimages'] AND ($forceimage OR $vbulletin->options['viewattachedimages']) AND !in_array($attachment['extension'], array('tiff', 'tif', 'psd', 'pdf')))
						{	// Display the attachment with no link to bigger image
							$replace[] = "[IMG]{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline][/IMG]";
						}
						else
						{	// Display a link
							$replace[] = "[IMG]{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline][/IMG]";
						}
						unset($post[attachments]["$attachmentid"]);
						break;
					default:
						$replace[] = "[URL={$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\1&amp;d=$attachment[dateline]$addtarget]$attachment[filename][/URL]";
				}
			}
			else
			{	// Belongs to another post so we know nothing about it ... or we are not displying images so always show a link
				$addtarget = (empty($post->attachments["$attachmentid"]) OR $attachment['newwindow']) ? 'target="_blank"' : '';
				/** doesn't need to be added to the link, should just be added to the image
					$addtarget .= !empty($align) ? " style=\"float: $align\" " : '';
					*/
				$replace[] = "[URL={$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1]$vbphrase[attachment] \\1[/URL]";
			}



		}

		$bbcode = preg_replace($search, $replace, $bbcode);

	}
	return $bbcode;
}
?>
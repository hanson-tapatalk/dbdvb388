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
function mobiquo_handle_bbcode_attach($bbcode, $do_imgcode,$post, &$inlineAttachments)
{
	global $vbphrase,$vbulletin,$db;
    $inlineAttachments = array();
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
                $attachmentUrl = "{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&d=$attachment[dateline]";
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
                            $replace[] = "[IMG]" . $attachmentUrl . "[/IMG]";
						}
						else if ($vbulletin->userinfo['showimages'] AND ($forceimage OR $vbulletin->options['viewattachedimages']) AND !in_array($attachment['extension'], array('tiff', 'tif', 'psd', 'pdf')))
						{	// Display the attachment with no link to bigger image
							$replace[] = "[IMG]" . $attachmentUrl . "[/IMG]";
						}
						else
						{	// Display a link
							$replace[] = "[IMG]" . $attachmentUrl . "[/IMG]";
						}
                        $inlineAttachments[$attachmentid] = $post['attachments']["$attachmentid"];
                        unset($post[attachments]["$attachmentid"]);
						break;
					default:
                        {
                            $inlineAttachments[$attachmentid] = $post['attachments']["$attachmentid"];
                            $replace[] =  "[url=". $attachmentUrl . "]" . $attachment['filename']  . "[/url]";
                            unset($post[attachments]["$attachmentid"]);
                            break;
                        }
				}
			}
			else
			{
                $attachment = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "attachment
				WHERE attachmentid=$attachmentid
			        ");
                $addtarget = ($attachment['newwindow']) ? 'target="_blank"' : '';
                $extension = strtolower(file_extension($attachment['filename']));
                switch($extension)
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
                        {
                            $replace[] = "[IMG]". "{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl_js']}attachmentid=\\1&d=$attachment[dateline]" . "[/IMG]";
                            break;
                        }
                    default:
                        {
                            $replace[] = "[URL=" . "{$vbulletin->options['bburl']}/attachment.php?{$vbulletin->session->vars['sessionurl']}attachmentid=\\1&d=$attachment[dateline]" . "]$vbphrase[attachment] \\1[/URL]";
                            break;
                        }
                }
			}



		}

		$bbcode = preg_replace($search, $replace, $bbcode);

	}
	return $bbcode;
}

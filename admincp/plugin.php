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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 92875 $');
define('FORCE_HOOKS', true);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('plugins');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_hook.php');
require_once(DIR . '/includes/adminfunctions_plugin.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
// don't allow demo version or admin with no permission to administer plugins
if (is_demo_mode() OR !can_administer('canadminplugins'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array('pluginid' => TYPE_UINT));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['pluginid'] != 0, 'plugin id = ' . $vbulletin->GPC['pluginid']));

// #############################################################################
// ########################### START MAIN SCRIPT ###############################
// #############################################################################

if ($_REQUEST['do'] != 'download' AND $_REQUEST['do'] != 'productexport')
{
	print_cp_header($vbphrase['plugin_products_system']);
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

if (in_array($_REQUEST['do'], array('modify', 'files', 'edit', 'add', 'product', 'productadd', 'productedit')))
{
	if (!$vbulletin->options['enablehooks'] OR defined('DISABLE_HOOKS'))
	{
		print_table_start();
		if (!$vbulletin->options['enablehooks'])
		{
			print_description_row($vbphrase['plugins_disabled_options']);
		}
		else
		{
			print_description_row($vbphrase['plugins_disable_config']);
		}
		print_table_footer(2, '', '', false);
	}
}

// ###################### Start import plugin XML #######################
if ($_REQUEST['do'] == 'files')
{
	$products = fetch_product_list();

	// download form
	print_form_header('plugin', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_input_row($vbphrase['filename'], 'filename', 'vbulletin-plugins.xml');

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin ORDER BY hookname, title");
	$prevhook = '';
	while ($plugin = $db->fetch_array($plugins))
	{
		if ($plugin['hookname'] != $prevhook)
		{
			$prevhook = $plugin['hookname'];
			print_description_row("$vbphrase[hook_location] : " . $plugin['hookname'], 0, 2, 'tfoot');
		}

		$title = htmlspecialchars_uni($plugin['title']);
		$title = $plugin['active'] ? $title : "<strike>$title</strike>";

		$product = $products[($plugin['product'] ? $plugin['product'] : 'vbulletin')];
		if (!$product)
		{
			$product = "<em>$plugin[product]</em>";
		}

		print_label_row("
			<label for=\"cb$plugin[pluginid]\">
			<input type=\"checkbox\" id=\"cb$plugin[pluginid]\" name=\"download[]\" value=\"$plugin[pluginid]\" />$title</label>
		", $product);
	}

	print_submit_row($vbphrase['download']);

	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	//-->
	</script>
	<?php

	print_form_header('plugin', 'doimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.pluginfile);');
	print_table_header($vbphrase['import_plugin_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'pluginfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './includes/xml/plugins.xml');
	print_submit_row($vbphrase['import'], 0);
}

// #############################################################################
if ($_POST['do'] == 'doimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile' => TYPE_STR,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'pluginfile' => TYPE_FILE
	));

	// got an uploaded file?
	if (file_exists($vbulletin->GPC['pluginfile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['pluginfile']['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	print_dots_start('<b>' . $vbphrase['importing_plugins'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new vB_XML_Parser($xml);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (empty($arr['plugin']))
	{
		print_dots_stop();
		if (!empty($arr['productid']))
		{
			print_stop_message('this_file_appears_to_be_a_product');
		}
		else
		{
			print_stop_message('invalid_file_specified');
		}
	}

	if (!is_array($arr['plugin'][0]))
	{
		$arr['plugin'] = array($arr['plugin']);
	}

	$maxid = $db->query_first("SELECT MAX(pluginid) AS max FROM " . TABLE_PREFIX . "plugin");

	foreach ($arr['plugin'] AS $plugin)
	{
		unset($plugin['devkey']); // make sure we don't try to set this as it's no longer used

		$db->query_write(fetch_query_sql($plugin, 'plugin'));
	}

	// rebuild the $vboptions array
	vBulletinHook::build_datastore($db);

	// stop the 'dots' counter feedback
	print_dots_stop();

	print_cp_redirect("plugin.php?" . $vbulletin->session->vars['sessionurl'], 0);
}

// #############################################################################
if ($_POST['do'] == 'download')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'filename' => TYPE_STR,
		'download' => TYPE_ARRAY_UINT,
	));

	if (empty($vbulletin->GPC['download']) OR empty($vbulletin->GPC['filename']))
	{
		print_stop_message('please_complete_required_fields');
	}

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder($vbulletin);
	$xml->add_group('plugins');

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin WHERE pluginid IN (" . implode(', ', $vbulletin->GPC['download']) . ")");
	while ($plugin = $db->fetch_array($plugins))
	{
		$params = array('active' => $plugin['active'], 'executionorder' => $plugin['executionorder']);
		if ($plugin['product'])
		{
			$params['product'] = $plugin['product'];
		}

		$xml->add_group('plugin', $params);

		$xml->add_tag('title', $plugin['title']);
		$xml->add_tag('hookname', $plugin['hookname']);
		$xml->add_tag('phpcode', $plugin['phpcode']);

		$xml->close_group();
	}

	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";

	$doc .= $xml->output();
	$xml = null;

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, $vbulletin->GPC['filename'], 'text/xml');
}

// #############################################################################

if ($_POST['do'] == 'updateactive')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'active' => TYPE_ARRAY_UINT,
	));

	$cond = '';

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin");
	while ($plugin = $db->fetch_array($plugins))
	{
		$cond .= "WHEN $plugin[pluginid] THEN " . (isset($vbulletin->GPC['active']["$plugin[pluginid]"]) ? 1 : 0) . "\n";
	}

	if (!empty($cond))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "plugin SET active = CASE pluginid
			$cond
			ELSE active END
		");
	}

	// update the datastore
	vBulletinHook::build_datastore($db);

	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "plugin WHERE pluginid = " . $vbulletin->GPC['pluginid']);

	vBulletinHook::build_datastore($db);

	define('CP_REDIRECT', 'plugin.php');
	print_stop_message('deleted_plugin_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pluginid' => TYPE_UINT,
		'name'    => TYPE_STR
	));

	print_delete_confirmation('plugin', $vbulletin->GPC['pluginid'], 'plugin', 'kill');
}

// #############################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'hookname'       => TYPE_STR,
		'title'          => TYPE_STR,
		'phpcode'        => TYPE_STR,
		'active'         => TYPE_BOOL,
		'product'        => TYPE_STR,
		'executionorder' => TYPE_UINT,
		'return'         => TYPE_STR
	));

	if (!$vbulletin->GPC['hookname'] OR !$vbulletin->GPC['title'] OR !$vbulletin->GPC['phpcode'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['pluginid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "plugin
			SET
				hookname = '" . $db->escape_string($vbulletin->GPC['hookname']) . "',
				title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
				phpcode = '" . $db->escape_string($vbulletin->GPC['phpcode']) . "',
				product = '" . $db->escape_string($vbulletin->GPC['product']) . "',
				active = " . intval($vbulletin->GPC['active']) . ",
				executionorder = " . intval($vbulletin->GPC['executionorder']) . "
			WHERE pluginid = " . $vbulletin->GPC['pluginid'] . "
		");
	}
	else
	{
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "plugin
				(hookname, title, phpcode, product, active, executionorder)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['hookname']) . "',
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($vbulletin->GPC['phpcode']) . "',
				'" . $db->escape_string($vbulletin->GPC['product']) . "',
				" . intval($vbulletin->GPC['active']) . ",
				" . intval($vbulletin->GPC['executionorder']) . ")
		");
		$vbulletin->GPC['pluginid'] = $db->insert_id();
	}

	// update the datastore
	vBulletinHook::build_datastore($db);

	// stuff to handle the redirect
	if ($vbulletin->GPC['return'])
	{
		define('CP_REDIRECT', "plugin.php?do=edit&amp;pluginid=" . $vbulletin->GPC['pluginid']);
	}
	else
	{
		define('CP_REDIRECT', 'plugin.php');
	}

	print_stop_message('saved_plugin_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{

	$products = fetch_product_list();

	$hooklocations = array();

	require_once(DIR . '/includes/class_xml.php');
	$handle = opendir(DIR . '/includes/xml/');
	while (($file = readdir($handle)) !== false)
	{
		if (!preg_match('#^hooks_(.*).xml$#i', $file, $matches))
		{
			continue;
		}
		$product = $matches[1];

		$phrased_product = $products[($product ? $product : 'vbulletin')];
		if (!$phrased_product)
		{
			$phrased_product = $product;
		}

		$xmlobj = new vB_XML_Parser(false, DIR . "/includes/xml/$file");
		$xml = $xmlobj->parse();

		if (!is_array($xml['hooktype'][0]))
		{
			// ugly kludge but it works...
			$xml['hooktype'] = array($xml['hooktype']);
		}

		foreach ($xml['hooktype'] AS $key => $hooks)
		{
			if (!is_numeric($key))
			{
				continue;
			}
			$phrased_type = isset($vbphrase["hooktype_$hooks[type]"]) ? $vbphrase["hooktype_$hooks[type]"] : $hooks['type'];

			$hooktype = $phrased_product . ' : ' . $phrased_type;

			if (!is_array($hooks['hook']))
			{
				$hooks['hook'] = array($hooks['hook']);
			}

			foreach ($hooks['hook'] AS $hook)
			{
				$hookid = trim(is_string($hook) ? $hook : $hook['value']);
				if ($hookid !== '')
				{
					$hooklocations["$hookid"] = $hookid . ($product != 'vbulletin' ? " ($phrased_product)" : '');
				}
			}
		}
	}

	uksort($hooklocations, 'strnatcasecmp');

	$plugin = $db->query_first("
		SELECT plugin.*,
			IF(product.productid IS NULL, 0, 1) AS foundproduct,
			IF(plugin.product = 'vbulletin', 1, product.active) AS productactive
		FROM " . TABLE_PREFIX . "plugin AS plugin
		LEFT JOIN " . TABLE_PREFIX . "product AS product ON(product.productid = plugin.product)
		WHERE pluginid = " . $vbulletin->GPC['pluginid']
	);
	if (!$plugin)
	{
		$plugin = array('executionorder' => 5);
	}

	print_form_header('plugin', 'update');
	construct_hidden_code('pluginid', $plugin['pluginid']);

	if ($_REQUEST['do'] == 'add')
	{
		$heading = $vbphrase['add_new_plugin'];
	}
	else
	{
		$heading = construct_phrase($vbphrase['edit_plugin_x'], htmlspecialchars_uni($plugin['title']));
	}

	print_table_header($heading);

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $plugin['product'] ? $plugin['product'] : 'vbulletin');
	print_select_row("$vbphrase[hook_location] <dfn>$vbphrase[hook_location_desc]</dfn>",
		'hookname',
		array_merge(array('' => ''), $hooklocations),
		$plugin['hookname']
	);
	print_input_row("$vbphrase[title] <dfn>$vbphrase[plugin_title_desc]</dfn>", 'title', $plugin['title'], 1, 60);
	print_input_row("$vbphrase[plugin_execution_order] <dfn>$vbphrase[plugin_order_desc]</dfn>", 'executionorder', $plugin['executionorder'], 1, 10);
	print_textarea_row(
		"$vbphrase[plugin_php_code] <dfn>$vbphrase[plugin_code_desc]</dfn>",
		'phpcode',
		htmlspecialchars_uni($plugin['phpcode']),
		10, '45" style="width:100%',
		false,
		true,
		'ltr',
		'code'
	);

	if ($plugin['foundproduct'] AND !$plugin['productactive'])
	{
		print_description_row(construct_phrase($vbphrase['plugin_inactive_due_to_product_disabled'], $products["$plugin[product]"]));
	}
	print_yes_no_row("$vbphrase[plugin_is_active] <dfn>$vbphrase[plugin_active_desc]</dfn>", 'active', $plugin['active']);
	print_submit_row($vbphrase['save'], '_default_', 2, '', "<input type=\"submit\" class=\"button\" tabindex=\"1\" name=\"return\" value=\"$vbphrase[save_and_reload]\" accesskey=\"e\" />");

	if ($plugin['phpcode'] != '')
	{
		// highlight the string
		$code = $plugin['phpcode'];

		// do we have an opening <? tag?
		if (!preg_match('#^\s*<\?#si', $code))
		{
			// if not, replace leading newlines and stuff in a <?php tag and a closing tag at the end
			$code = "<?php BEGIN__VBULLETIN__CODE__SNIPPET $code \r\nEND__VBULLETIN__CODE__SNIPPET ?>";
			$addedtags = true;
		}
		else
		{
			$addedtags = false;
		}

		// highlight the string
		$oldlevel = error_reporting();
		$code = highlight_string($code, true);
		error_reporting($oldlevel);

		// if we added tags above, now get rid of them from the resulting string
		if ($addedtags)
		{
			$search = array(
				'#(<|&lt;)\?php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#(<(span|font).*>)(<|&lt;)\?(</\\2>(<\\2.*>))php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#END__VBULLETIN__CODE__SNIPPET( |&nbsp;)\?(>|&gt;)#siU'
			);
			$replace = array(
				'',
				'\\5',
				''
			);
			$code = preg_replace($search, $replace, $code);
		}

		print_form_header('', '');
		print_table_header($vbphrase['plugin_php_code']);
		print_description_row("<div dir=\"ltr\">$code</div>");
		print_table_footer();
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'sort' => TYPE_NOHTML
	));

	$products = fetch_product_list(true);

	print_form_header('plugin', 'updateactive');
	print_table_header($vbphrase['plugin_system'], 4);

	switch ($vbulletin->GPC['sort'])
	{
		case 'hook':
		{
			$plugins = $db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "plugin
				ORDER BY hookname, title
			");

			print_cells_row(
				array(
					$vbphrase['title'],
					"<a href=\"plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;sort=product\">$vbphrase[product]</a>",
					$vbphrase['active'],
					$vbphrase['controls']
				),
				1
			);

			$group_by = 'hook';
		}
		break;

		case 'product':
		default:
		{
			$plugins = $db->query_read("
				SELECT plugin.*, IF(plugin.product = '', 'vbulletin', product.title) AS producttitle
				FROM " . TABLE_PREFIX . "plugin AS plugin
				LEFT JOIN " . TABLE_PREFIX . "product AS product ON (plugin.product = product.productid)
				ORDER BY producttitle, plugin.title
			");

			print_cells_row(
				array(
					$vbphrase['title'],
					"<a href=\"plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=modify&amp;sort=hook\">$vbphrase[hook_location]</a>",
					$vbphrase['active'],
					$vbphrase['controls']
				),
				1
			);
			$group_by = 'product';
		}

	}

	$prevgroup = '';
	while ($plugin = $db->fetch_array($plugins))
	{
		$product = $products[($plugin['product'] ? $plugin['product'] : 'vbulletin')];
		if (!$product)
		{
			$product = array('title' => "<em>$plugin[product]</em>", 'active' => 1);
		}
		else
		{
			$product['title'] = htmlspecialchars_uni($product['title']);
		}

		if ($group_by == 'hook')
		{
			if ($plugin['hookname'] != $prevgroup)
			{
				$prevgroup = $plugin['hookname'];
				print_description_row("$vbphrase[hook_location] : " . $plugin['hookname'], 0, 4, 'tfoot');
			}
		}
		else if ($group_by == 'product')
		{
			if ($product['title'] != $prevgroup)
			{
				$prevgroup = $product['title'];
				print_description_row("$vbphrase[product] : " . $product['title'], 0, 4, 'tfoot');
			}
		}

		if (!$product['active'])
		{
			$product['title'] = "<strike>$product[title]</strike>";
		}

		$title = htmlspecialchars_uni($plugin['title']);
		$title = ($plugin['active'] AND $product['active']) ? $title : "<strike>$title</strike>";

		print_cells_row(array(
			"<a href=\"plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;pluginid=$plugin[pluginid]\">$title</a>",
			($group_by == 'hook' ? $product['title'] : $plugin['hookname']),
			"<input type=\"checkbox\" name=\"active[$plugin[pluginid]]\" value=\"1\"" . ($plugin['active'] ? ' checked="checked"' : '') . " />",
			construct_link_code($vbphrase['edit'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;pluginid=$plugin[pluginid]") .
			construct_link_code($vbphrase['delete'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;pluginid=$plugin[pluginid]")
		));
	}

	print_submit_row($vbphrase['save_active_status'], false, 4);

	echo '<p align="center">' . construct_link_code($vbphrase['add_new_plugin'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=add") . '</p>';
}

// #############################################################################
// #############################################################################
// #############################################################################

if ($_REQUEST['do'] == 'product')
{
	?>
	<script type="text/javascript">
	function js_page_jump(i, sid)
	{
		var sel = fetch_object("prodsel" + i);
		var act = sel.options[sel.selectedIndex].value;
		if (act != '')
		{
			switch (act)
			{
				case 'productdisable': page = "plugin.php?do=productdisable&productid="; break;
				case 'productenable': page = "plugin.php?do=productenable&productid="; break;
				case 'productedit': page = "plugin.php?do=productedit&productid="; break;
				case 'productversioncheck': page = "plugin.php?do=productversioncheck&productid="; break;
				case 'productexport':
					document.cpform.productid.value = sid;
					document.cpform.submit();
					return;
				case 'productdelete': page = "plugin.php?do=productdelete&productid="; break;
				default: return;
			}
			document.cpform.reset();
			jumptopage = page + sid + "&s=<?php echo $vbulletin->session->vars['sessionhash']; ?>";
			window.location = jumptopage;
		}
		else
		{
			alert('<?php echo addslashes_js($vbphrase['invalid_action_specified']); ?>');
		}
	}
	</script>
	<?php

	print_form_header('plugin', 'productexport', false, true, 'cpform', '90%', 'download');
	construct_hidden_code('productid', '');

	print_table_header($vbphrase['installed_products'], 4); # Phrase me
	print_cells_row(array($vbphrase['title'], $vbphrase['version'], $vbphrase['description'], $vbphrase['controls']), 1);

	print_cells_row(array('<strong>vBulletin</strong>', $vbulletin->options['templateversion'], '', ''), false, '', -2);

	// used for <select> id attribute
	$i = 0;

	$products = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		ORDER BY title
	");

	while ($product = $db->fetch_array($products))
	{
		$title = htmlspecialchars_uni($product['title']);
		if (!$product['active'])
		{
			$title = "<strike>$title</strike>";
		}
		if ($product['url'])
		{
			$title = '<a href="' . htmlspecialchars_uni($product['url']) . "\" target=\"_blank\">$title</a>";
		}

		$options = array('productedit' => $vbphrase['edit']);
		if ($product['versioncheckurl'])
		{
			$options['productversioncheck'] = $vbphrase['check_version'];
		}
		if ($product['active'])
		{
			$options['productdisable'] = $vbphrase['disable'];
		}
		else
		{
			$options['productenable'] = $vbphrase['enable'];
		}
		$options['productexport'] = $vbphrase['export'];
		$options['productdelete'] = $vbphrase['uninstall'];

		$safeid = preg_replace('#[^a-z0-9_]#', '', $product['productid']);
		if (file_exists(DIR . '/includes/version_' . $safeid . '.php'))
		{
			include_once(DIR . '/includes/version_' . $safeid . '.php');
		}
		$define_name = 'FILE_VERSION_' . strtoupper($safeid);
		if (defined($define_name) AND constant($define_name) !== '')
		{
			$product['version'] = constant($define_name);
		}

		$i++;
		print_cells_row(array(
			$title,
			htmlspecialchars_uni($product['version']),
			htmlspecialchars_uni($product['description']),
			"<div align=\"$stylevar[right]\">
				<select name=\"s$product[productid]\" id=\"prodsel$i\" onchange=\"js_page_jump($i, '$product[productid]')\" class=\"bginput\">
					" . construct_select_options($options) . "
				</select>&nbsp;<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_page_jump($i, '$product[productid]');\" />
			</div>"
		), false, '', -2);
	}

	print_table_footer();
	echo '<p align="center">' . construct_link_code($vbphrase['add_import_product'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=productadd") . '</p>';
}

// #############################################################################

if ($_REQUEST['do'] == 'productversioncheck')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid' => TYPE_STR
	));

	$product = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product OR empty($product['versioncheckurl']))
	{
		print_stop_message('invalid_product_specified');
	}

	$version_url = @parse_url($product['versioncheckurl']);
	if (!$version_url)
	{
		print_stop_message('invalid_version_check_url_specified');
	}

	if (!$version_url['port'])
	{
		$version_url['port'] = 80;
	}
	if (!$version_url['path'])
	{
		$version_url['path'] = '/';
	}

	$fp = @fsockopen($version_url['host'], ($version_url['port'] ? $version_url['port'] : 80), $errno, $errstr, 10);
	if (!$fp)
	{
		print_stop_message('version_check_connect_failed_host_x_error_y',
			htmlspecialchars_uni($version_url['host']),
			htmlspecialchars_uni($errstr)
		);
	}

	$send_headers = "POST $version_url[path] HTTP/1.0\r\n";
	$send_headers .= "Host: $version_url[host]\r\n";
	$send_headers .= "User-Agent: vBulletin Product Version Check\r\n";
	if ($version_url['query'])
	{
		$send_headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
	}
	$send_headers .= "Content-Length: " . strlen($version_url['query']) . "\r\n";
	$send_headers .= "\r\n";

	fwrite($fp, $send_headers . $version_url['query']);

	$full_result = '';
	while (!feof($fp))
	{
		$result = fgets($fp, 1024);
		$full_result .= $result;
	}

	fclose($fp);

	preg_match('#^(.*)\r\n\r\n(.*)$#sU', $full_result, $matches);
	$headers = trim($matches[1]);
	$body = trim($matches[2]);

	if (preg_match('#<version productid="' . preg_quote($product['productid'], '#') . '">(.+)</version>#iU', $body, $matches))
	{
		$latest_version = $matches[1];
	}
	else if (preg_match('#<version>(.+)</version>#iU', $body, $matches))
	{
		$latest_version = $matches[1];
	}
	else
	{
		print_stop_message('version_check_failed_not_found');
	}

	// see if we have a patch or something
	$safeid = preg_replace('#[^a-z0-9_]#', '', $product['productid']);
	if (file_exists(DIR . '/includes/version_' . $safeid . '.php'))
	{
		include_once(DIR . '/includes/version_' . $safeid . '.php');
	}
	$define_name = 'FILE_VERSION_' . strtoupper($safeid);
	if (defined($define_name) AND constant($define_name) !== '')
	{
		$product['version'] = constant($define_name);
	}

	print_form_header('', '');

	if (is_newer_version($latest_version, $product['version']))
	{
		print_table_header(construct_phrase($vbphrase['product_x_out_of_date'], htmlspecialchars_uni($product['title'])));
		print_label_row($vbphrase['installed_version'], htmlspecialchars_uni($product['version']));
		print_label_row($vbphrase['latest_version'], htmlspecialchars_uni($latest_version));
		if ($product['url'])
		{
			print_description_row(
				'<a href="' . htmlspecialchars_uni($product['url']) . '" target="_blank">' . $vbphrase['click_here_for_more_info'] . '</a>',
				false,
				2,
				'',
				'center'
			);
		}
	}
	else
	{
		print_table_header(construct_phrase($vbphrase['product_x_up_to_date'], htmlspecialchars_uni($product['title'])));
		print_label_row($vbphrase['installed_version'], htmlspecialchars_uni($product['version']));
		print_label_row($vbphrase['latest_version'], htmlspecialchars_uni($latest_version));
	}

	print_table_footer();
}

// #############################################################################

if ($_REQUEST['do'] == 'productdisable' OR $_REQUEST['do'] == 'productenable')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid' => TYPE_STR,
		'confirmswitch' => TYPE_BOOL
	));

	$product = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product)
	{
		print_stop_message('invalid_product_specified');
	}

	$product_list = fetch_product_list(true);

	$dependency_result = $db->query_read("
		SELECT productid, parentproductid
		FROM " . TABLE_PREFIX . "productdependency
		WHERE dependencytype = 'product' AND parentproductid <> ''
	");

	if ($_REQUEST['do'] == 'productdisable')
	{
		$newstate = 0;

		// disabling a product -- disable all children

		// list with parents as keys, good for traversing downward
		$dependency_list = array();
		while ($dependency = $db->fetch_array($dependency_result))
		{
			$dependency_list["$dependency[parentproductid]"][] = $dependency['productid'];
		}

		$children = fetch_product_dependencies($vbulletin->GPC['productid'], $dependency_list);

		$need_switch = array();
		foreach ($children AS $childproductid)
		{
			$childproduct = $product_list["$childproductid"];
			if ($childproduct AND $childproduct['active'] == 1)
			{
				// product exists and is enabled -- needs to be disabled
				$need_switch[$db->escape_string("$childproductid")] = $childproduct['title'];
			}
		}

		$phrase_name = 'additional_products_disable_x_y';
	}
	else
	{
		$newstate = 1;

		// enabling a product -- enable all parents

		// list with children as keys, good for traversing upward
		$dependency_list = array();
		while ($dependency = $db->fetch_array($dependency_result))
		{
			$dependency_list["$dependency[productid]"][] = $dependency['parentproductid'];
		}

		$parents = fetch_product_dependencies($vbulletin->GPC['productid'], $dependency_list);

		$need_switch = array();
		foreach ($parents AS $parentproductid)
		{
			$parentproduct = $product_list["$parentproductid"];
			if ($parentproduct AND $childproduct['active'] == 0)
			{
				// product exists and is disabled -- needs to be enabled
				$need_switch[$db->escape_string("$parentproductid")] = $parentproduct['title'];
			}
		}

		$phrase_name = 'additional_products_enable_x_y';
	}

	if (!$vbulletin->GPC['confirmswitch'] AND count($need_switch) > 0)
	{
		// to do this, we need to update the status of some additional products,
		// so make sure the user knows what's going on
		$need_switch_str = '<li>' . implode('</li><li>', $need_switch) . '</li>';
		print_stop_message(
			$phrase_name,
			htmlspecialchars_uni($product['title']),
			$need_switch_str,
			'plugin.php?' . $vbulletin->session->vars['sessionurl'] .
				'do=' . urlencode($_REQUEST['do']) .
				'&amp;productid=' . urlencode($vbulletin->GPC['productid']) .
				'&amp;confirmswitch=1'
		);
	}

	// $need_switch is already escaped
	$product_update = array_keys($need_switch);
	$product_update[] = $db->escape_string($vbulletin->GPC['productid']);

	// Do the product table
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "product
		SET active = $newstate
		WHERE productid IN ('" . implode("', '", $product_update) . "')
	");

	vBulletinHook::build_datastore($db);
	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	// this could enable a cron entry, so we need to rebuild that as well
	require_once(DIR . '/includes/functions_cron.php');
	build_cron_next_run();

	define('CP_REDIRECT', 'index.php?loc=' . urlencode('plugin.php?do=product'));

	if ($_REQUEST['do'] == 'productdisable')
	{
		print_stop_message('product_disabled_successfully');
	}
	else
	{
		print_stop_message('product_enabled_successfully');
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'productadd' OR $_REQUEST['do'] == 'productedit')
{
	$allowoverwrite = 0;

	$vbulletin->input->clean_array_gpc('r', array(
		'productid'		=> TYPE_STR
	));

	if ($vbulletin->GPC['productid'])
	{
		$product = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "product
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
	}
	else
	{
		$product = array();
	}
	
	($hook = vBulletinHook::fetch_hook('admin_product_add')) ? eval($hook) : false;

	if (!$product)
	{
		?>
		<script type="text/javascript">
		<!--
		function js_confirm_upload(tform, filefield)
		{
			if (filefield.value == "")
			{
				return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
			}
			return true;
		}
		//-->
		</script>
		<?php

		print_form_header('plugin', 'productimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.productfile);');
		print_table_header($vbphrase['import_product']);
		print_upload_row($vbphrase['upload_xml_file'], 'productfile', 999999999);
		print_input_row($vbphrase['import_xml_file'], 'serverfile', './includes/xml/product.xml');
		print_yes_no_row($vbphrase['allow_overwrite_upgrade_product'], 'allowoverwrite', $allowoverwrite);
		print_submit_row($vbphrase['import']);
	}

	print_form_header('plugin', 'productsave');

	if ($product)
	{
		print_table_header(construct_phrase($vbphrase['edit_product_x'], $product['productid']));
		print_label_row($vbphrase['product_id'], $product['productid']);

		construct_hidden_code('productid', $product['productid']);
		construct_hidden_code('editing', 1);
	}
	else
	{
		print_table_header($vbphrase['add_new_product']);
		print_input_row($vbphrase['product_id'], 'productid', '', true, 50, 25); // max length = 25
	}

	print_input_row($vbphrase['title'], 'title', $product['title'], true, 50, 50);
	print_input_row($vbphrase['version'], 'version', $product['version'], true, 50, 25);
	print_input_row($vbphrase['description'], 'description', $product['description'], true, 50, 250);
	print_input_row($vbphrase['product_url'], 'url', $product['url'], true, 50, 250);
	print_input_row($vbphrase['version_check_url'], 'versioncheckurl', $product['versioncheckurl'], true, 50, 250);

	($hook = vBulletinHook::fetch_hook('admin_product_edit')) ? eval($hook) : false;

	print_submit_row();

	// if we're editing a product, show the install/uninstall code options
	if ($product)
	{
		echo '<hr />';

		print_form_header('plugin', 'productdependency');
		construct_hidden_code('productid', $vbulletin->GPC['productid']);

		// the <label> tags in the product type are for 3.6 bug 349
		$dependency_types = array(
			'php'       => $vbphrase['php_version'],
			'mysql'     => $vbphrase['mysql_version'],
			'vbulletin' => $vbphrase['vbulletin_version'],
			'product'   => $vbphrase['product_id'] . '</label>&nbsp;<input type="text" class="bginput" name="parentproductid" id="it_parentproductid" value="" size="15" maxlength="25" tabindex="1" /><label>',
		);

		$product_dependencies = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "productdependency
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			ORDER BY dependencytype, parentproductid, minversion
		");

		if ($db->num_rows($product_dependencies))
		{
			print_table_header($vbphrase['existing_product_dependencies'], 4);
			print_cells_row(array(
				$vbphrase['dependency_type'],
				$vbphrase['compatibility_starts'],
				$vbphrase['incompatible_with'],
				$vbphrase['delete']
			), 1);

			while ($product_dependency = $db->fetch_array($product_dependencies))
			{
				if ($product_dependency['dependencytype'] != 'product')
				{
					$dep_type = $dependency_types["$product_dependency[dependencytype]"];
				}
				else
				{
					$dep_type = $vbphrase['product'] . ' - ' . htmlspecialchars_uni($product_dependency['parentproductid']);
				}

				$depid = $product_dependency['productdependencyid'];

				print_cells_row(array(
					$dep_type,
					"<input type=\"text\" name=\"productdependency[$depid][minversion]\" value=\"" . htmlspecialchars_uni($product_dependency['minversion']) . "\" size=\"25\" maxlength=\"50\" tabindex=\"1\" />",
					"<input type=\"text\" name=\"productdependency[$depid][maxversion]\" value=\"" . htmlspecialchars_uni($product_dependency['maxversion']) . "\" size=\"25\" maxlength=\"50\" tabindex=\"1\" />",
					"<input type=\"checkbox\" name=\"productdependency[$depid][delete]\" value=\"1\" />"
				));
			}

			print_table_break();
		}

		print_table_header($vbphrase['add_new_product_dependency']);
		print_radio_row($vbphrase['dependency_type'], 'dependencytype', $dependency_types);
		print_input_row($vbphrase['compatibility_starts_with_version'], 'minversion', '', true, 25, 50);
		print_input_row($vbphrase['incompatible_with_version_and_newer'], 'maxversion', '', true, 25, 50);

		print_submit_row();

		// #############################################
		echo '<hr />';

		print_form_header('plugin', 'productcode');
		construct_hidden_code('productid', $vbulletin->GPC['productid']);

		$productcodes = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "productcode
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			ORDER BY version
		");

		if ($db->num_rows($productcodes))
		{
			print_table_header($vbphrase['existing_install_uninstall_code'], 4);
			print_cells_row(array(
				$vbphrase['version'],
				$vbphrase['install_code'],
				$vbphrase['uninstall_code'],
				$vbphrase['delete']
			), 1);

			$productcodes_grouped = array();
			$productcodes_versions = array();

			while ($productcode = $db->fetch_array($productcodes))
			{
				// have to be careful here, as version numbers are not necessarily unique
				$productcodes_versions["$productcode[version]"] = 1;
				$productcodes_grouped["$productcode[version]"][] = $productcode;
			}

			$productcodes_versions = array_keys($productcodes_versions);
			usort($productcodes_versions, 'version_sort');

			foreach ($productcodes_versions AS $version)
			{
				foreach ($productcodes_grouped["$version"] AS $productcode)
				{
					print_cells_row(array(
						"<input type=\"text\" name=\"productcode[$productcode[productcodeid]][version]\" value=\"" . htmlspecialchars_uni($productcode['version']) . "\" style=\"width:100%\" size=\"10\" />",
						"<textarea name=\"productcode[$productcode[productcodeid]][installcode]\" rows=\"5\" cols=\"40\" style=\"width:100%\" wrap=\"virtual\" tabindex=\"1\">" . htmlspecialchars_uni($productcode['installcode']) . "</textarea>",
						"<textarea name=\"productcode[$productcode[productcodeid]][uninstallcode]\" rows=\"5\" cols=\"40\" style=\"width:100%\" wrap=\"virtual\" tabindex=\"1\">" . htmlspecialchars_uni($productcode['uninstallcode']) . "</textarea>",
						"<input type=\"checkbox\" name=\"productcode[$productcode[productcodeid]][delete]\" value=\"1\" />"
					));
				}
			}

			print_table_break();
		}

		print_table_header($vbphrase['add_new_install_uninstall_code']);

		print_input_row($vbphrase['version'], 'version');
		print_textarea_row($vbphrase['install_code'], 'installcode', '', 5, '70" style="width:100%');
		print_textarea_row($vbphrase['uninstall_code'], 'uninstallcode', '', 5, '70" style="width:100%');

		print_submit_row();
	}
}

// #############################################################################

if ($_POST['do'] == 'productsave')
{
	// Check to see if it is a duplicate.
	$vbulletin->input->clean_array_gpc('p', array(
		'productid'       => TYPE_STR,
		'editing'         => TYPE_BOOL,
		'title'           => TYPE_STR,
		'version'         => TYPE_STR,
		'description'     => TYPE_STR,
		'url'             => TYPE_STR,
		'versioncheckurl' => TYPE_STR,
		'confirm'         => TYPE_BOOL,
	));

	if ($vbulletin->GPC['url'] AND !preg_match('#^[a-z0-9]+:#i', $vbulletin->GPC['url']))
	{
		$vbulletin->GPC['url'] = 'http://' . $vbulletin->GPC['url'];
	}
	if ($vbulletin->GPC['versioncheckurl'] AND !preg_match('#^[a-z0-9]+:#i', $vbulletin->GPC['versioncheckurl']))
	{
		$vbulletin->GPC['versioncheckurl'] = 'http://' . $vbulletin->GPC['versioncheckurl'];
	}

	if (!$vbulletin->GPC['productid'] OR !$vbulletin->GPC['title'] OR !$vbulletin->GPC['version'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_stop_message('product_x_installed_version_y_z', 'vBulletin', $vbulletin->options['templateversion'], $vbulletin->GPC['version']);
	}

	if (!$vbulletin->GPC['editing'] AND $existingprod = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'"
	))
	{
		print_stop_message('product_x_installed_version_y_z', $vbulletin->GPC['title'], $existingprod['version'], $vbulletin->GPC['version']);
	}

	require_once(DIR . '/includes/adminfunctions_template.php');

	$invalid_version_structure = array(0, 0, 0, 0, 0, 0);
	if (fetch_version_array($vbulletin->GPC['version']) == $invalid_version_structure)
	{
		print_stop_message('invalid_product_version');
	}

	if ($vbulletin->GPC['editing'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "product SET
				title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
				description = '" . $db->escape_string($vbulletin->GPC['description']) . "',
				version = '" . $db->escape_string($vbulletin->GPC['version']) . "',
				url = '" . $db->escape_string($vbulletin->GPC['url']) . "',
				versioncheckurl = '" . $db->escape_string($vbulletin->GPC['versioncheckurl']) . "'
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
	}
	else
	{
		// product IDs must match #^[a-z0-9_]+$# and must be max 25 chars
		if (!preg_match('#^[a-z0-9_]+$#s', $vbulletin->GPC['productid']) OR strlen($vbulletin->GPC['productid']) > 25)
		{
			$sugg = preg_replace('#\s+#s', '_', strtolower($vbulletin->GPC['productid']));
			$sugg = preg_replace('#[^\w]#s', '', $sugg);
			$sugg = str_replace('__', '_', $sugg);
			$sugg = substr($sugg, 0, 25);
			print_stop_message('product_id_invalid', htmlspecialchars_uni($vbulletin->GPC['productid']), $sugg);
		}

		// reserve 'vb' prefix for official vBulletin products
		if (!$vbulletin->GPC['confirm'] AND strtolower(substr($vbulletin->GPC['productid'], 0, 2)) == 'vb')
		{
			print_form_header('plugin', 'productsave');
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(
				htmlspecialchars_uni($vbulletin->GPC['title']) . ' ' . htmlspecialchars_uni($vbulletin->GPC['version']) .
				'<dfn>' . htmlspecialchars_uni($vbulletin->GPC['description']) . '</dfn>'
			);
			print_input_row($vbphrase['vb_prefix_reserved'], 'productid', $vbulletin->GPC['productid'], true, 35, 25);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('description', $vbulletin->GPC['description']);
			construct_hidden_code('version', $vbulletin->GPC['version']);
			construct_hidden_code('confirm', 1);
			print_submit_row();
			print_cp_footer();

			// execution terminates here
		}

		/* insert query */
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "product
				(productid, title, description, version, active, url, versioncheckurl)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['productid']) . "',
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($vbulletin->GPC['description']) . "',
				'" . $db->escape_string($vbulletin->GPC['version']) . "',
				1,
				'" . $db->escape_string($vbulletin->GPC['url']) . "',
				'" . $db->escape_string($vbulletin->GPC['versioncheckurl']) . "')
		");
	}

	// update the products datastore
	build_product_datastore();

	define('CP_REDIRECT', 'plugin.php?do=product');
	print_stop_message('product_x_updated', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_POST['do'] == 'productdependency')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'productid'			=> TYPE_STR,
		'dependencytype'	=> TYPE_STR,
		'parentproductid'	=> TYPE_STR,
		'minversion'		=> TYPE_STR,
		'maxversion'		=> TYPE_STR,
		'productdependency'	=> TYPE_ARRAY
	));

	$product = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product)
	{
		print_stop_message('invalid_product_specified');
	}

	if ($vbulletin->GPC['dependencytype'] != 'product')
	{
		$vbulletin->GPC['parentproductid'] = '';
	}

	if ($vbulletin->GPC['dependencytype'] OR $vbulletin->GPC['parentproductid'])
	{
		if ($vbulletin->GPC['minversion'] OR $vbulletin->GPC['maxversion'])
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productdependency
					(productid, dependencytype, parentproductid, minversion, maxversion)
				VALUES
					('" . $db->escape_string($vbulletin->GPC['productid']) . "',
					'" . $db->escape_string($vbulletin->GPC['dependencytype']) . "',
					'" . $db->escape_string($vbulletin->GPC['parentproductid']) . "',
					'" . $db->escape_string($vbulletin->GPC['minversion']) . "',
					'" . $db->escape_string($vbulletin->GPC['maxversion']) . "')
			");
		}
		else
		{
			print_stop_message('please_complete_required_fields');
		}
	}

	foreach ($vbulletin->GPC['productdependency'] AS $productdependencyid => $product_dependency)
	{
		$productdependencyid = intval($productdependencyid);

		if ($product_dependency['delete'])
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "productdependency
				WHERE productdependencyid = $productdependencyid
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "productdependency SET
					minversion = '" . $db->escape_string($product_dependency['minversion']) . "',
					maxversion = '" . $db->escape_string($product_dependency['maxversion']) . "'
				WHERE productdependencyid = $productdependencyid
			");
		}
	}

	define('CP_REDIRECT', 'plugin.php?do=productedit&productid=' . $vbulletin->GPC['productid']);
	print_stop_message('product_x_updated', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_POST['do'] == 'productcode')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'productid'		=> TYPE_STR,
		'version'		=> TYPE_STR,
		'installcode'	=> TYPE_STR,
		'uninstallcode'	=> TYPE_STR,
		'productcode'	=> TYPE_ARRAY
	));

	$product = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product)
	{
		print_stop_message('invalid_product_specified');
	}

	if ($vbulletin->GPC['version'] AND ($vbulletin->GPC['installcode'] OR $vbulletin->GPC['uninstallcode']))
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "productcode
				(productid, version, installcode, uninstallcode)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['productid']) . "',
				'" . $db->escape_string($vbulletin->GPC['version']) . "',
				'" . $db->escape_string($vbulletin->GPC['installcode']) . "',
				'" . $db->escape_string($vbulletin->GPC['uninstallcode']) . "')
		");
	}

	foreach ($vbulletin->GPC['productcode'] AS $productcodeid => $productcode)
	{
		$productcodeid = intval($productcodeid);

		if ($productcode['delete'])
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "productcode
				WHERE productcodeid = $productcodeid
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "productcode SET
					version = '" . $db->escape_string($productcode['version']) . "',
					installcode = '" . $db->escape_string($productcode['installcode']) . "',
					uninstallcode = '" . $db->escape_string($productcode['uninstallcode']) . "'
				WHERE productcodeid = $productcodeid
			");
		}
	}

	define('CP_REDIRECT', 'plugin.php?do=productedit&productid=' . $vbulletin->GPC['productid']);
	print_stop_message('product_x_updated', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_POST['do'] == 'productkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'productid' => TYPE_STR
	));

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_cp_redirect('plugin.php?do=product');
	}

	// run uninstall code first; try to undo things in the opposite order they were done
	$productcodes = $db->query_read("
		SELECT version, uninstallcode
		FROM " . TABLE_PREFIX . "productcode
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND uninstallcode <> ''
	");

	$productcodes_grouped = array();
	$productcodes_versions = array();

	while ($productcode = $db->fetch_array($productcodes))
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	unset($productcodes_versions['*']);
	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');
	$productcodes_versions = array_reverse($productcodes_versions);

	if (!empty($productcodes_grouped['*']))
	{
		// run * entries first
		foreach ($productcodes_grouped['*'] AS $productcode)
		{
			eval($productcode['uninstallcode']);
		}
	}

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			eval($productcode['uninstallcode']);
		}
	}

	// need to remove the language columns for this product as well
	require_once(DIR . '/includes/class_dbalter.php');

	$db_alter = new vB_Database_Alter_MySQL($db);
	if ($db_alter->fetch_table_info('language'))
	{
		$phrasetypes = $db->query_read("
			SELECT fieldname
			FROM " . TABLE_PREFIX . "phrasetype
			WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
		while ($phrasetype = $db->fetch_array($phrasetypes))
		{
			$db_alter->drop_field("phrasegroup_$phrasetype[fieldname]");
		}
	}

	delete_product($vbulletin->GPC['productid']);

	build_all_styles();

	vBulletinHook::build_datastore($db);

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_options();

	require_once(DIR . '/includes/functions_cron.php');
	build_cron_next_run();

	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!defined('DISABLE_PRODUCT_REDIRECT'))
	{
		define('CP_REDIRECT', 'index.php?loc=' . urlencode('plugin.php?do=product'));
	}
	print_stop_message('product_x_uninstalled', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_REQUEST['do'] == 'productdelete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid' => TYPE_STR
	));

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_cp_redirect('plugin.php?do=product');
	}

	$dependency_result = $db->query_read("
		SELECT productid, parentproductid
		FROM " . TABLE_PREFIX . "productdependency
		WHERE dependencytype = 'product' AND parentproductid <> ''
	");

	// find child products -- these may break if we uninstall this
	$dependency_list = array();
	while ($dependency = $db->fetch_array($dependency_result))
	{
		$dependency_list["$dependency[parentproductid]"][] = $dependency['productid'];
	}

	$children = fetch_product_dependencies($vbulletin->GPC['productid'], $dependency_list);

	$product_list = fetch_product_list(true);

	$children_text = array();
	foreach ($children AS $childproductid)
	{
		$childproduct = $product_list["$childproductid"];
		if ($childproduct)
		{
			$children_text[] = $childproduct['title'];
		}
	}

	if ($children_text)
	{
		$affected_children = construct_phrase(
			$vbphrase['uninstall_product_break_products_x'],
			'<li>' . implode('</li><li>', $children_text) . '</li>'
		);
	}
	else
	{
		$affected_children = '';
	}

	print_delete_confirmation(
		'product',
		$vbulletin->GPC['productid'],
		'plugin',
		'productkill',
		'',
		0,
		$affected_children
	);
}

// #############################################################################

if ($_POST['do'] == 'productimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile'   => TYPE_STR,
		'allowoverwrite' => TYPE_BOOL
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'productfile' => TYPE_FILE
	));

	require_once(DIR . '/includes/class_bitfield_builder.php');

	if (file_exists($vbulletin->GPC['productfile']['tmp_name']))
	{
		// got an uploaded file?
		$xml = file_read($vbulletin->GPC['productfile']['tmp_name']);
	}
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		// no uploaded file - got a local file?
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	print_dots_start('<b>' . $vbphrase['importing_product'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new vB_XML_Parser($xml);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	// ############## general product information
	$info = array(
		'productid'       => substr(preg_replace('#[^a-z0-9_]#', '', strtolower($arr['productid'])), 0, 25),
		'title'           => $arr['title'],
		'description'     => $arr['description'],
		'version'         => $arr['version'],
		'active'          => $arr['active'],
		'url'             => $arr['url'],
		'versioncheckurl' => $arr['versioncheckurl']
	);

	if (!$info['productid'])
	{
		print_dots_stop();
		if (!empty($arr['plugin']))
		{
			print_stop_message('this_file_appears_to_be_a_plugin');
		}
		else
		{
			print_stop_message('invalid_file_specified');
		}
	}

	if (strtolower($info['productid']) == 'vbulletin')
	{
		print_dots_stop();
		print_stop_message('product_x_installed_no_overwrite', 'vBulletin');
	}

	// check for bitfield conflicts on install
	$bitfields = vB_Bitfield_Builder::return_data();
	if (!$bitfields)
	{
		$bfobj = vB_Bitfield_Builder::init();
		if ($bfobj->errors)
		{
			print_dots_stop();
			print_stop_message(
				'bitfield_conflicts_x',
				'<li>' . implode('</li><li>', $bfobj->errors) . '</li>'
			);
		}
	}

	// get system version info
	$system_versions = array(
		'php' => PHP_VERSION,
		'vbulletin' => $vbulletin->options['templateversion'],
		'products' => fetch_product_list(true)
	);
	$mysql_version = $db->query_first("SELECT VERSION() AS version");
	$system_versions['mysql'] = $mysql_version['version'];

	// ############## import dependencies
	if (isset($arr['dependencies']['dependency']) AND is_array($arr['dependencies']['dependency']))
	{
		$dependencies =& $arr['dependencies']['dependency'];
		if (!isset($dependencies[0]))
		{
			$dependencies = array($dependencies);
		}

		$dependency_errors = array();
		$ignore_dependency_errors = array();

		// let's check the dependencies
		foreach ($dependencies AS $dependency)
		{
			// if we get an error, we haven't met this dependency
			// if we go through without a problem, we have automatically met
			// all dependencies for this "class" (mysql, php, vb, a specific product, etc)
			$this_dependency_met = true;

			// build a phrase for the version compats -- will look like (minver / maxver)
			if ($dependency['minversion'])
			{
				$compatible_phrase = construct_phrase(
					$vbphrase['compatible_starting_with_x'],
					htmlspecialchars_uni($dependency['minversion'])
				);
			}
			else
			{
				$compatible_phrase = '';
			}

			if ($dependency['maxversion'])
			{
				$incompatible_phrase = construct_phrase(
					$vbphrase['incompatible_with_x_and_greater'],
					htmlspecialchars_uni($dependency['maxversion'])
				);
			}
			else
			{
				$incompatible_phrase = '';
			}

			if ($compatible_phrase OR $incompatible_phrase)
			{
				$required_version_info = "($compatible_phrase";
				if ($compatible_phrase AND $incompatible_phrase)
				{
					$required_version_info .= ' / ';
				}
				$required_version_info .= "$incompatible_phrase)";
			}

			// grab the appropriate installed version string
			if ($dependency['dependencytype'] == 'product')
			{
				// group dependencies into types -- individual products get their own group
				$dependency_type_key = "product-$dependency[parentproductid]";

				// undocumented feature -- you can put a producttitle attribute in a dependency so the id isn't displayed
				$parent_product_title = (!empty($dependency['producttitle']) ? $dependency['producttitle'] : $dependency['parentproductid']);

				$parent_product = $system_versions['products']["$dependency[parentproductid]"];
				if (!$parent_product)
				{
					// required product is not installed
					$dependency_errors["$dependency_type_key"] = construct_phrase(
						$vbphrase['product_x_must_be_installed'],
						htmlspecialchars_uni($parent_product_title),
						$required_version_info
					);
					continue; // can't do version checks if the product isn't installed
				}
				else if ($parent_product['active'] == 0)
				{
					// product is installed, but inactive
					$dependency_errors["{$dependency_type_key}-inactive"] = construct_phrase(
						$vbphrase['product_x_must_be_activated'],
						htmlspecialchars_uni($parent_product_title)
					);
					$this_dependency_met = false;
					// allow version checks to continue
				}

				$sys_version_str = $parent_product['version'];
				$version_incompatible_phrase = 'product_incompatible_version_x_product_y';
			}
			else
			{
				$dependency_type_key = $dependency['dependencytype'];
				$parent_product_title = '';
				$sys_version_str = $system_versions["$dependency[dependencytype]"];
				$version_incompatible_phrase = 'product_incompatible_version_x_' . $dependency['dependencytype'];
			}

			// if no version string, we are trying to do an unsupported dep check
			if ($sys_version_str == '')
			{
				continue;
			}

			$sys_version = fetch_version_array($sys_version_str);


			// error if installed version < minversion
			if ($dependency['minversion'])
			{
				$dep_version = fetch_version_array($dependency['minversion']);

				for ($i = 0; $i <= 5; $i++)
				{
					if ($sys_version["$i"] < $dep_version["$i"])
					{
						// installed version is too old
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$vbphrase["$version_incompatible_phrase"],
							htmlspecialchars_uni($sys_version_str),
							$required_version_info,
							$parent_product_title
						);
						$this_dependency_met = false;
						break;
					}
					else if ($sys_version["$i"] > $dep_version["$i"])
					{
						break;
					}
				}
			}

			// error if installed version >= maxversion
			if ($dependency['maxversion'])
			{
				$dep_version = fetch_version_array($dependency['maxversion']);

				$all_equal = true;

				for ($i = 0; $i <= 5; $i++)
				{
					if ($sys_version["$i"] > $dep_version["$i"])
					{
						// installed version is newer than the maxversion
						$dependency_errors["$dependency_type_key"] = construct_phrase(
							$vbphrase["$version_incompatible_phrase"],
							htmlspecialchars_uni($sys_version_str),
							$required_version_info,
							$parent_product_title
						);
						$this_dependency_met = false;
						break;
					}
					else if ($sys_version["$i"] < $dep_version["$i"])
					{
						// not every part is the same and since we've got less we can exit
						$all_equal = false;
						break;
					}
					else if ($sys_version["$i"] != $dep_version["$i"])
					{
						// not every part is the same
						$all_equal = false;
					}
				}

				if ($all_equal == true)
				{
					// installed version is same as the max version, which is the first incompat version
					$dependency_errors["$dependency_type_key"] = construct_phrase(
						$vbphrase["$version_incompatible_phrase"],
						htmlspecialchars_uni($sys_version_str),
						$required_version_info,
						$parent_product_title
					);
					$this_dependency_met = false;
				}
			}

			if ($this_dependency_met)
			{
				// we met 1 dependency for this type -- this emulates or'ing together groups
				$ignore_dependency_errors["$dependency_type_key"] = true;
			}
		}

		// for any group we met a dependency for, ignore any errors we might
		// have gotten for the group
		foreach ($ignore_dependency_errors AS $dependency_type_key => $devnull)
		{
			unset($dependency_errors["$dependency_type_key"]);
		}

		if ($dependency_errors)
		{
			$dependency_errors = array_unique($dependency_errors);
			$dependency_errors = '<ol><li>' . implode('</li><li>', $dependency_errors) . '</li></ol>';

			print_dots_stop();
			print_stop_message('dependencies_not_met_x', $dependency_errors);
		}
	}

	// look to see if we already have this product installed
	if ($existingprod = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($info['productid']) . "'"
	))
	{
		if (!$vbulletin->GPC['allowoverwrite'])
		{
			print_dots_stop();
			print_stop_message('product_x_installed_no_overwrite', $info['title']);
		}

		$active = $existingprod['active'];

		// not sure what we're deleting, so rebuild everything
		$rebuild = array(
			'templates' => true,
			'plugins'   => true,
			'phrases'   => true,
			'options'   => true,
			'cron'      => true
		);

		$installed_version = $existingprod['version'];
	}
	else
	{
		$active = ($info['active'] ? 1 : 0);

		$rebuild = array(
			'templates' => false,
			'plugins'   => false,
			'phrases'   => false,
			'options'   => false,
			'cron'      => false
		);

		$installed_version = null;
	}

	// ############## import install/uninstall code
	if (isset($arr['codes']['code']) AND is_array($arr['codes']['code']))
	{
		$codes =& $arr['codes']['code'];
		if (!isset($codes[0]))
		{
			$codes = array($codes);
		}

		// run each of the codes
		foreach ($codes AS $code)
		{
			// Run if: code version is * (meaning always run), no version
			//		previously installed, or if the code is for a newer version
			//		than is currently installed
			if ($code['version'] == '*' OR $installed_version === null OR is_newer_version($code['version'], $installed_version))
			{
				eval($code['installcode']);
			}
		}
	}

	// dependencies checked, install code run. Now clear out the old product info;
	// settings should be retained in memory already
	delete_product($info['productid']);

	if (is_array($codes))
	{
		// we've now run all the codes, if execution is still going
		// then it's going to complete fully, so insert the codes
		foreach ($codes AS $code)
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productcode
					(productid, version, installcode, uninstallcode)
				VALUES
					('" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($code['version']) . "',
					'" . $db->escape_string($code['installcode']) . "',
					'" . $db->escape_string($code['uninstallcode']) . "')
			");
		}
	}

	if (is_array($dependencies))
	{
		// dependencies met, codes run -- now we can insert the dependencies into the DB
		foreach ($dependencies AS $dependency)
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productdependency
					(productid, dependencytype, parentproductid, minversion, maxversion)
				VALUES
					('" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($dependency['dependencytype']) . "',
					'" . $db->escape_string($dependency['parentproductid']) . "',
					'" . $db->escape_string($dependency['minversion']) . "',
					'" . $db->escape_string($dependency['maxversion']) . "')
			");
		}
	}

	/* insert query */
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "product
			(productid, title, description, version, active, url, versioncheckurl)
		VALUES
			('" . $db->escape_string($info['productid']) . "',
			'" . $db->escape_string($info['title']) . "',
			'" . $db->escape_string($info['description']) . "',
			'" . $db->escape_string($info['version']) . "',
			" . intval($active) . ",
			'" . $db->escape_string($info['url']) . "',
			'" . $db->escape_string($info['versioncheckurl']) . "')
	");

	// ############## import templates
	if (isset($arr['templates']['template']) AND is_array($arr['templates']['template']))
	{
		$querybits = array();
		$querytemplates = 0;

		$templates =& $arr['templates']['template'];
		if (!isset($templates[0]))
		{
			$templates = array($templates);
		}

		foreach ($templates AS $template)
		{
			$title = $db->escape_string($template['name']);
			$template['template'] = $db->escape_string($template['value']);
			$template['username'] = $db->escape_string($template['username']);
			$template['templatetype'] = $db->escape_string($template['templatetype']);
			$template['date'] = intval($template['date']);

			if ($template['templatetype'] != 'template')
			{
				// template is a special template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '$template[template]', '', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}
			else
			{
				// template is a standard template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '" . $db->escape_string(compile_template($template['value'], false)) . "', '$template[template]', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}
			if (++$querytemplates % 20 == 0)
			{
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "template
						(styleid, templatetype, title, template, template_un, dateline, username, version, product)
					VALUES
						" . implode(',', $querybits) . "
				");
				$querybits = array();
			}
		}

		// insert any remaining templates
		if (!empty($querybits))
		{
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "template
					(styleid, templatetype, title, template, template_un, dateline, username, version, product)
				VALUES
					" . implode(',', $querybits) . "
			");
		}
		unset($querybits);

		$rebuild['templates'] = true;
	}

	// ############## import hooks/plugins
	if (isset($arr['plugins']['plugin']) AND is_array($arr['plugins']['plugin']))
	{
		$plugins =& $arr['plugins']['plugin'];
		if (!isset($plugins[0]))
		{
			$plugins = array($plugins);
		}

		foreach ($plugins AS $plugin)
		{
			$plugin['product'] = $info['productid'];
			unset($plugin['devkey']);

			$db->query_write(fetch_query_sql($plugin, 'plugin'));
		}

		$rebuild['plugins'] = true;
	}

	// ############## import phrases
	if (isset($arr['phrases']['phrasetype']) AND is_array($arr['phrases']['phrasetype']))
	{
		require_once(DIR . '/includes/adminfunctions_language.php');

		$master_phrasetypes = array();
		$master_phrasefields = array();
		foreach(fetch_phrasetypes_array(false) as $phrasetype)
		{
			$master_phrasefields["$phrasetype[fieldname]"] = true;
		}

		$phrasetypes =& $arr['phrases']['phrasetype'];
		if (!isset($phrasetypes[0]))
		{
			$phrasetypes = array($phrasetypes);
		}

		foreach ($phrasetypes AS $phrasetype)
		{
			if (empty($phrasetype['phrase']))
			{
				continue;
			}

			if ($phrasetype['fieldname'] == '' OR !preg_match('#^[a-z0-9_]+$#i', $phrasetype['fieldname'])) // match a-z, A-Z, 0-9,_ only
			{
				continue;
			}

			$fieldname = $master_phrasefields["$phrasetype[fieldname]"];

			if (!$fieldname)
			{
				$db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
						(fieldname, title, editrows, product)
					VALUES
						('" . $db->escape_string($phrasetype['fieldname']) . "',
						'" . $db->escape_string($phrasetype['name']) . "',
						3,
						'" . $db->escape_string($info['productid']) . "')
				");

				// need to add the column to the language table as well
				require_once(DIR . '/includes/class_dbalter.php');

				$db_alter = new vB_Database_Alter_MySQL($db);
				if ($db_alter->fetch_table_info('language'))
				{
					$db_alter->add_field(array(
						'name' => "phrasegroup_$phrasetype[fieldname]",
						'type' => 'mediumtext'
					));
				}
			}

			$phrases =& $phrasetype['phrase'];
			if (!isset($phrases[0]))
			{
				$phrases = array($phrases);
			}

			$sql = array();

			foreach ($phrases AS $phrase)
			{
				$sql[] = "
					(-1,
					'" . $db->escape_string($phrasetype['fieldname']) . "',
					'" . $db->escape_string($phrase['name']) . "',
					'" . $db->escape_string($phrase['value']) . "',
					'" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($phrase['username']) . "',
					" . intval($phrase['date']) . ",
					'" . $db->escape_string($phrase['version']) . "')
				";
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					" . implode(',', $sql)
			);
		}

		$rebuild['phrases'] = true;
	}

	// ############## import settings
	if (isset($arr['options']['settinggroup']) AND is_array($arr['options']['settinggroup']))
	{
		$settinggroups =& $arr['options']['settinggroup'];
		if (!isset($settinggroups[0]))
		{
			$settinggroups = array($settinggroups);
		}

		foreach ($settinggroups AS $group)
		{
			if (empty($group['setting']))
			{
				continue;
			}

			// create the setting group if it doesn't already exist
			/*insert query*/
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
					(grouptitle, displayorder, volatile, product)
				VALUES
					('" . $db->escape_string($group['name']) . "',
					" . intval($group['displayorder']) . ",
					1,
					'" . $db->escape_string($info['productid']) . "')
			");

			$settings =& $group['setting'];
			if (!isset($settings[0]))
			{
				$settings = array($settings);
			}

			$setting_bits = array();

			foreach ($settings AS $setting)
			{
				if (isset($vbulletin->options["$setting[varname]"]))
				{
					$newvalue = $vbulletin->options["$setting[varname]"];
				}
				else
				{
					$newvalue = $setting['defaultvalue'];
				}

				$setting_bits[] = "(
					'" . $db->escape_string($setting['varname']) . "',
					'" . $db->escape_string($group['name']) . "',
					'" . $db->escape_string(trim($newvalue)) . "',
					'" . $db->escape_string(trim($setting['defaultvalue'])) . "',
					'" . $db->escape_string(trim($setting['datatype'])) . "',
					'" . $db->escape_string($setting['optioncode']) . "',
					" . intval($setting['displayorder']) . ",
					" . intval($setting['advanced']) . ",
					1,
					'" . $db->escape_string($setting['validationcode']) . "',
					" . intval($setting['blacklist']) . ",
					'" . $db->escape_string($info['productid']) . "'\n\t)";
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "setting
					(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, validationcode, blacklist, product)
				VALUES
					" . implode(",\n\t", $setting_bits)
			);
		}

		$rebuild['options'] = true;
	}

	// ############## import admin help
	if (isset($arr['helptopics']['helpscript']) AND is_array($arr['helptopics']['helpscript']))
	{
		$help_scripts =& $arr['helptopics']['helpscript'];
		if (!isset($help_scripts[0]))
		{
			$help_scripts = array($help_scripts);
		}

		foreach ($help_scripts AS $help_script)
		{
			// Deal with single entry
			if (!is_array($help_script['helptopic'][0]))
			{
				$help_script['helptopic'] = array($help_script['helptopic']);
			}

			$help_sql = array();
			foreach ($help_script['helptopic'] AS $topic)
			{
				$helpsql[] = "
					('" . $db->escape_string($help_script['name']) . "',
					'" . $db->escape_string($topic['act']) . "',
					'" . $db->escape_string($topic['opt']) . "',
					" . intval($topic['disp']) . ",
					1,
					'" . $db->escape_string($info['productid']) . "')
				";
			}

			if (!empty($helpsql))
			{
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "adminhelp
						(script, action, optionname, displayorder, volatile, product)
					VALUES
						" . implode(",\n\t", $helpsql)
				);
			}
		}
	}

	// ############## import cron
	if (isset($arr['cronentries']['cron']) AND is_array($arr['cronentries']['cron']))
	{
		require_once(DIR . '/includes/functions_cron.php');

		$cron_entries =& $arr['cronentries']['cron'];
		if (!isset($cron_entries[0]))
		{
			$cron_entries = array($cron_entries);
		}

		foreach ($cron_entries AS $cron)
		{
			$cron['varname'] = preg_replace('#[^a-z0-9_]#i', '', $cron['varname']);
			if (!$cron['varname'])
			{
				continue;
			}

			$cron['active'] = ($cron['active'] ? 1 : 0);
			$cron['loglevel'] = ($cron['loglevel'] ? 1 : 0);

			$scheduling = $cron['scheduling'];
			$scheduling['weekday'] = intval($scheduling['weekday']);
			$scheduling['day'] = intval($scheduling['day']);
			$scheduling['hour'] = intval($scheduling['hour']);
			$scheduling['minute'] = explode(',', preg_replace('#[^0-9,-]#i', '', $scheduling['minute']));
			if (count($scheduling['minute']) == 0)
			{
				$scheduling['minute'] = array(0);
			}
			else
			{
				$scheduling['minute'] = array_map('intval', $scheduling['minute']);
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "cron
					(weekday, day, hour, minute, filename, loglevel, active, varname, volatile, product)
				VALUES
					($scheduling[weekday],
					$scheduling[day],
					$scheduling[hour],
					'" . $db->escape_string(serialize($scheduling['minute'])) . "',
					'" . $db->escape_string($cron['filename']) . "',
					$cron[loglevel],
					$cron[active],
					'" . $db->escape_string($cron['varname']) . "',
					1,
					'" . $db->escape_string($info['productid']) . "')
			");
			$cronid = $db->insert_id(); // replace either inserts, or deletes+inserts
			if ($cronid)
			{
				build_cron_item($cronid);
			}

			$rebuild['cron'] = true;
		}
	}

	// ############## import faq
	if (isset($arr['faqentries']['faq']) AND is_array($arr['faqentries']['faq']))
	{
		$faq_entries =& $arr['faqentries']['faq'];
		if (!isset($faq_entries[0]))
		{
			$faq_entries = array($faq_entries);
		}

		$sql = array();
		foreach ($faq_entries AS $faq)
		{
			$sql[] = "
				('" . $db->escape_string($faq['faqname']) . "',
				'" . $db->escape_string($faq['faqparent']) . "',
				" . intval($faq['displayorder']) . ",
				1,
				'" . $db->escape_string($info['productid']) . "')
			";
		}

		if ($sql)
		{
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "faq
					(faqname, faqparent, displayorder, volatile, product)
				VALUES
					" . implode(',', $sql) . "
			");
		}
	}

	// Check if the plugin system is disabled. If it is, enable it.
	if (!$vbulletin->options['enablehooks'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = '1'
			WHERE varname = 'enablehooks'
		");

		$rebuild['options'] = true;
	}

	// Now rebuild everything we need...
	if ($rebuild['plugins'])
	{
		vBulletinHook::build_datastore($db);

		if ($active)
		{
			$plugin_data = $db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "datastore
				WHERE title IN ('pluginlist', 'pluginlistadmin')
			");
			while ($plugin_info = $db->fetch_array($plugin_data))
			{
				if ($plugin_info['title'] == 'pluginlist')
				{
					$vbulletin->pluginlist = vb_unserialize($plugin_info['data']);
				}
				else if ($plugin_info['title'] == 'pluginlistadmin')
				{
					$vbulletin->pluginlistadmin = vb_unserialize($plugin_info['data']);
				}
			}

			// enable any hooks -- this is mainly necessary for importing templates (template_safe_functions hook)
			$hookobj = vBulletinHook::init();
			if (!empty($vbulletin->pluginlistadmin) AND is_array($vbulletin->pluginlistadmin))
			{
				$vbulletin->pluginlist = array_merge($vbulletin->pluginlist, $vbulletin->pluginlistadmin);
				unset($vbulletin->pluginlistadmin);
			}
			$hookobj->set_pluginlist($vbulletin->pluginlist);
		}
	}

	if ($rebuild['templates'])
	{
		build_all_styles();
	}
	if ($rebuild['phrases'])
	{
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();
	}
	if ($rebuild['options'])
	{
		build_options();
	}
	if ($rebuild['cron'])
	{
		require_once(DIR . '/includes/functions_cron.php');
		build_cron_next_run();
	}

	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	vB_Bitfield_Builder::save($db);

	print_dots_stop();

	if (!defined('DISABLE_PRODUCT_REDIRECT'))
	{
		define('CP_REDIRECT', 'index.php?loc=' . urlencode('plugin.php?do=product'));
	}
	print_stop_message('product_x_imported', $info['productid']);
}

// #############################################################################
if ($_REQUEST['do'] == 'productexport')
{
	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_XML_Builder($vbulletin);

	$vbulletin->input->clean_array_gpc('r', array(
		'productid'	=> TYPE_STR
	));

	//	Set up the parent tag
	$product_details = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product_details)
	{
		print_stop_message('invalid_product_specified');
	}

	$export_styleids = array(-1);
	$export_languageids = array(-1, 0);

	// ############## main product info
	$xml->add_group('product', array(
		'productid' => strtolower($product_details['productid']),
		'active' => $product_details['active']
	)); // Parent for product

	$xml->add_tag('title', $product_details['title']);
	$xml->add_tag('description', $product_details['description']);
	$xml->add_tag('version', $product_details['version']);
	$xml->add_tag('url', $product_details['url']);
	$xml->add_tag('versioncheckurl', $product_details['versioncheckurl']);

	($hook = vBulletinHook::fetch_hook('admin_product_export')) ? eval($hook) : false;

	// ############## dependencies
	$product_dependencies = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "productdependency
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		ORDER BY dependencytype, parentproductid, minversion
	");

	$xml->add_group('dependencies');

	while ($product_dependency = $db->fetch_array($product_dependencies))
	{
		$deps = array('dependencytype' => $product_dependency['dependencytype']);
		if ($product_dependency['dependencytype'] == 'product')
		{
			$deps['parentproductid'] = $product_dependency['parentproductid'];
		}
		$deps['minversion'] = $product_dependency['minversion'];
		$deps['maxversion'] = $product_dependency['maxversion'];

		$xml->add_tag('dependency', '', $deps);
	}

	$xml->close_group();

	// ############## install / uninstall codes
	$productcodes = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "productcode
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	$xml->add_group('codes');

	$productcodes_grouped = array();
	$productcodes_versions = array();

	while ($productcode = $db->fetch_array($productcodes))
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			$xml->add_group('code', array('version' => $productcode['version']));
				$xml->add_tag('installcode', $productcode['installcode']);
				$xml->add_tag('uninstallcode', $productcode['uninstallcode']);
			$xml->close_group();
		}
	}

	$xml->close_group();

	// ############## templates
	$gettemplates = $db->query_read("
		SELECT title, templatetype, username, dateline, version, product,
			IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND styleid IN (" . implode(',', $export_styleids) . ")
		ORDER BY title
	");

	$xml->add_group('templates');

	while ($template = $db->fetch_array($gettemplates))
	{
		if (is_newer_version($template['version'], $product_details['version']))
		{
			// version in the template is newer than the version of the product,
			// which probably means it's using the vB version
			$template['version'] = $product_details['version'];
		}

		$xml->add_tag('template', $template['template'], array(
			'name' => htmlspecialchars_uni($template['title']),
			'templatetype' => $template['templatetype'],
			'date' => $template['dateline'],
			'username' => $template['username'],
			'version' => htmlspecialchars_uni($template['version'])
		), true);
	}

	$xml->close_group();

	// ############## plugins
	$xml->add_group('plugins');

	$plugins = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "plugin
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		ORDER BY hookname
	");
	while ($plugin = $db->fetch_array($plugins))
	{
		$params = array('active' => $plugin['active'], 'executionorder' => $plugin['executionorder']);

		$xml->add_group('plugin', $params);
			$xml->add_tag('title', $plugin['title']);
			$xml->add_tag('hookname', $plugin['hookname']);
			$xml->add_tag('phpcode', $plugin['phpcode']);
		$xml->close_group();
	}

	unset($plugin);
	$db->free_result($plugins);
	$xml->close_group();

	// ############## phrases
	require_once(DIR . '/includes/adminfunctions_language.php');

	$phrasetypes = fetch_phrasetypes_array(false);

	$phrases = array();
	$getphrases = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "phrase
		WHERE languageid IN (" . implode(',', $export_languageids) . ")
			AND product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		ORDER BY languageid, fieldname, varname
	");
	while ($getphrase = $db->fetch_array($getphrases))
	{
		$phrases["$getphrase[fieldname]"]["$getphrase[varname]"] = $getphrase;
	}
	unset($getphrase);
	$db->free_result($getphrases);

	$xml->add_group('phrases');

	foreach ($phrases AS $_fieldname => $typephrases)
	{
		// create a group for each phrase type that we have phrases for
		// then insert the phrases

		$xml->add_group('phrasetype', array('name' => $phrasetypes["$_fieldname"]['title'], 'fieldname' => $_fieldname));

		foreach ($typephrases AS $phrase)
		{
			$xml->add_tag('phrase', $phrase['text'], array(
				'name' => $phrase['varname'],
				'date' => $phrase['dateline'],
				'username' => $phrase['username'],
				'version' => htmlspecialchars_uni($phrase['version'])
			), true);
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## options
	$setting = array();
	$settinggroup = array();

	$groups = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "settinggroup
		WHERE volatile = 1
		ORDER BY displayorder
	");
	while ($group = $db->fetch_array($groups))
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	$options = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "setting
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND volatile = 1
		ORDER BY displayorder
	");
	while ($set = $db->fetch_array($options))
	{
		$setting["$set[grouptitle]"][] = $set;
	}
	unset($set);
	$db->free_result($sets);

	$xml->add_group('options');

	foreach ($settinggroup AS $grouptitle => $group)
	{
		if (empty($setting["$grouptitle"]))
		{
			continue;
		}

		// add a group for each setting group we have settings for

		$xml->add_group('settinggroup', array('name' => htmlspecialchars_uni($group['grouptitle']), 'displayorder' => $group['displayorder']));

		foreach($setting["$grouptitle"] AS $set)
		{
			$arr = array('varname' => $set['varname'], 'displayorder' => $set['displayorder']);
			if ($set['advanced'])
			{
				$arr['advanced'] = 1;
			}
			$xml->add_group('setting', $arr);

			if ($set['datatype'])
			{
				$xml->add_tag('datatype', $set['datatype']);
			}
			if ($set['optioncode'] != '')
			{
				$xml->add_tag('optioncode', $set['optioncode']);
			}
			if ($set['validationcode'])
			{
				$xml->add_tag('validationcode', $set['validationcode']);
			}
			if ($set['defaultvalue'] !== '')
			{
				$xml->add_tag('defaultvalue', $set['defaultvalue']);
			}
			if ($set['blacklist'])
			{
				$xml->add_tag('blacklist', 1);
			}

			$xml->close_group();
		}

		$xml->close_group();
	}

	$xml->close_group();

	// ############## admin help
	$help_topics_results = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "adminhelp
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND volatile = 1
		ORDER BY script, action, displayorder
	");
	$help_topics = array();
	while ($help_topic = $db->fetch_array($help_topics_results))
	{
		$help_topics["$help_topic[script]"][] = $help_topic;
	}
	$db->free_result($help_topics_results);
	ksort($help_topics);

	$xml->add_group('helptopics');

	foreach ($help_topics AS $script => $script_topics)
	{
		$xml->add_group('helpscript', array('name' => $script));
		foreach ($script_topics AS $topic)
		{
			$attr = array('disp' => $topic['displayorder']);
			if ($topic['action'])
			{
				$attr['act'] = $topic['action'];
			}
			if ($topic['optionname'])
			{
				$attr['opt'] = $topic['optionname'];
			}
			$xml->add_tag('helptopic', '', $attr);
		}
		$xml->close_group();
	}

	$xml->close_group();

	// ############## Cron entries
	$cron_results = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "cron
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND volatile = 1
			AND varname <> ''
		ORDER BY varname
	");

	$xml->add_group('cronentries');

	while ($cron = $db->fetch_array($cron_results))
	{
		$minutes = vb_unserialize($cron['minute']);
		if (!is_array($minutes))
		{
			$minutes = array();
		}

		$xml->add_group('cron', array(
			'varname' => $cron['varname'],
			'active' => $cron['active'],
			'loglevel' => $cron['loglevel']
		));
		$xml->add_tag('filename', $cron['filename']);
		$xml->add_tag('scheduling', '', array(
			'weekday' => $cron['weekday'],
			'day' => $cron['day'],
			'hour' => $cron['hour'],
			'minute' => implode(',', $minutes)
		));
		$xml->close_group();
	}

	$xml->close_group();

	$db->free_result($cron_results);

	// ############## FAQ entries

	$faq_results = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "faq
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND volatile = 1
		ORDER BY faqname
	");

	$xml->add_group('faqentries');

	while ($faq = $db->fetch_array($faq_results))
	{
		$xml->add_tag('faq', '', array(
			'faqname' => $faq['faqname'],
			'faqparent' => $faq['faqparent'],
			'displayorder' => $faq['displayorder'],
		));
	}

	$xml->close_group();

	$db->free_result($faq_results);

	// ############## Finish up
	$xml->close_group();
	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n" . $xml->output();

	unset($xml);

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, "product-" . $vbulletin->GPC['productid'] . '.xml', 'text/xml');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 03:31, Fri Jun 16th 2017 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>

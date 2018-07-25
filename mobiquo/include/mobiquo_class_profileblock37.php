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

/**
 * Factory class to create blocks for the Profile Display
 *
 * @package	vBulletin
 */

class vB_ProfileBlockFactory
{
	/**
	 * Registry object
	 *
	 * @var	vB_Registry
	 */
	var $registry;

	/**
	 * The UserProfile Object
	 *
	 * @var	vB_UserProfile
	 */
	var $profile;

	/**
	 * Cache of the Profile Blocks already loaded
	 *
	 * @var	array
	 */
	var $cache = array();

	/**
	 * Constructor
	 *
	 * @param	vB_Registry
	 * @param	vB_UserProfile
	 */
	function vB_ProfileBlockFactory(&$registry, &$profile)
	{
		$this->registry =& $registry;
		$this->profile =& $profile;
	}

	/**
	 * Fetches a Profile Block object
	 *
	 * @param	string	The name of the class
	 */
	function &fetch($class)
	{
		if (!isset($this->cache["$class"]) OR !is_object($this->cache["$class"]))
		{
			$classname = "vB_ProfileBlock_$class";
			$this->cache["$class"] =& new $classname($this->registry, $this->profile, $this);
		}

		return $this->cache["$class"];
	}
}
/**
 * Abstract Class for Profile Blocks
 *
 * @package vBulletin
 */
class vB_ProfileBlock
{
	/**
	 * Registry object
	 *
	 * @var	vB_Registry
	 */
	var $registry;

	/**
	 * User Profile Object
	 *
	 * @var	vB_UserProfile
	 */
	var $profile;

	/**
	 * Factory Object
	 *
	 * @var	vB_ProfileBlockFactory
	 */
	var $factory;

	/**
	 * Default Options for the block
	 *
	 * @var	array
	 */
	var $option_defaults = array();

	/**
	 * The name of the template to be used for the block
	 *
	 * @var string
	 */
	var $template_name = '';

	/**
	 * Variables to automatically prepare
	 *
	 * @var array
	 */
	var $auto_prepare = array();

	/**
	 * Data that is only used within the block itself
	 *
	 * @var array
	 */
	var $block_data = array();


	var $nowrap;
	var $mobiquo_array;

	/**
	 * Constructor - Prepares the block, and automatically prepares needed data
	 *
	 * @param	vB_Registry
	 * @param	vB_UserProfile
	 * @param	vB_ProfileBlockFactory
	 */
	function vB_ProfileBlock(&$registry, &$profile, &$factory)
	{
		$this->registry =& $registry;
		$this->profile =& $profile;
		$this->factory =& $factory;

		foreach ($this->auto_prepare AS $prepare)
		{
			$profile->prepare($prepare);
		}

		$this->fetch_default_options();
	}

	/**
	 * Whether to return an empty wrapper if there is no content in the blocks
	 *
	 * @return bool
	 */
	function confirm_empty_wrap()
	{
		return true;
	}

	/**
	 * Whether or not the block is enabled
	 *
	 * @return bool
	 */
	function block_is_enabled($id)
	{
		return true;
	}

	/**
	 * Fetch the block
	 *
	 * @param	string	The title of the Block
	 * @param	string	The id of the Block
	 * @param	array	Options specific to the block
	 *
	 * @return	string	The Block's output to be shown on the profile page
	 */
	function fetch($title, $id = '', $options = array())
	{
		if ($this->block_is_enabled($id))
		{
			$html = $this->fetch_unwrapped($title, $id, $options);

			if (trim($html) === '' AND !$this->confirm_empty_wrap())
			{
				return '';
			}
			else
			{
				return $this->mobiquo_array;
			}
		}
		else
		{
			return '';
		}
	}

	/**
	 * Prepare any data needed for the output
	 *
	 * @param	string	The id of the block
	 * @param	array	Options specific to the block
	 */
	function prepare_output($id = '', $options = array())
	{
	}

	/**
	 * Should we actually display anything?
	 *
	 * @return	bool
	 */
	function confirm_display()
	{
		return true;
	}

	/**
	 * Sets/Fetches the default options for the block
	 *
	 */
	function fetch_default_options()
	{
	}

	/**
	 * Fetches the unwrapped (no box around it) version of the block
	 *
	 * @param	string	The title of the block
	 * @param	string	The id of the block
	 * @param	array	Options specific to the block
	 *
	 * @return	string
	 */
	function fetch_unwrapped($title, $id = '', $options = array())
	{
		global $show, $vbphrase, $stylevar, $vbcollapse;

		$this->prepare_output($id, $options);

		if (!$this->confirm_display())
		{
			return '';
		}

		$prepared = $this->profile->prepared;
		$userinfo = $this->profile->userinfo;
		$block_data = $this->block_data;

		//($hook = vBulletinHook::fetch_hook('member_profileblock_fetch_unwrapped')) ? eval($hook) : false;

		eval('$output = "' . fetch_template($this->template_name) . '";');
		return $output;
	}

	/**
	 * Wraps the given HTML in it's containing block
	 *
	 * @param	string	The title of the block
	 * @param	string	The id of the block
	 * @param	string	The HTML to be wrapped
	 *
	 * @return	string
	 */
	function wrap($title, $id = '', $html = '')
	{
		global $show, $vbphrase, $stylevar, $vbcollapse;

		$template = 'memberinfo_block';

		eval('$wrapped = "' . fetch_template($template) . '";');
		return $wrapped;
	}
}

/**
 * Profile Block for Profile Fields
 *
 * @package vBulletin
 */
class vB_ProfileBlock_ProfileFields extends vB_ProfileBlock
{
	/**
	 * The name of the template to be used for the block
	 *
	 * @var string
	 */
	var $template_name = 'memberinfo_block_profilefield';

	/**
	 * The categories to show in this block
	 *
	 * @var array
	 */
	var $categories = array(0 => array());

	/**
	 * The Locations of the fields within the block
	 *
	 * @var array
	 */
	var $locations = array();

	/**
	 * Whether the data has been built already
	 *
	 * @var bool
	 */
	var $data_built = false;

	/**
	 * Sets/Fetches the default options for the block
	 *
	 */
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'category' => 'all'
			);
	}

	/**
	 * Whether to return an empty wrapper if there is no content in the blocks
	 *
	 * @return bool
	 */
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	 * Should we actually display anything?
	 *
	 * @return	bool
	 */
	function confirm_display()
	{
		return ($this->block_data['fields'] != '');
	}

	/**
	 * Builds the custom Profile Field Data
	 *
	 * @param	boolean	Should we show hidden fields if we're allowed to view them?
	 */
	function build_field_data($showhidden)
	{
		if ($this->data_built)
		{
			return;
		}

		$this->categories = array(0 => array());
		$this->locations = array();

		$profilefields_result = $this->registry->db->query_read_slave("
			SELECT pf.profilefieldcategoryid, pfc.location, pf.*
			FROM " . TABLE_PREFIX . "profilefield AS pf
			LEFT JOIN " . TABLE_PREFIX . "profilefieldcategory AS pfc ON(pfc.profilefieldcategoryid = pf.profilefieldcategoryid)
			WHERE pf.form = 0 " . iif($showhidden OR !($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehiddencustomfields']), "
					AND pf.hidden = 0") . "
			ORDER BY pfc.displayorder, pf.displayorder
		");
		while ($profilefield = $this->registry->db->fetch_array($profilefields_result))
		{
			$this->categories["$profilefield[profilefieldcategoryid]"][] = $profilefield;
			$this->locations["$profilefield[profilefieldcategoryid]"] = $profilefield['location'];
		}

		$this->data_built = true;
	}

	/**
	 * Prepare any data needed for the output
	 *
	 * @param	string	The id of the block
	 * @param	array	Options specific to the block
	 */
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase, $stylevar;

		if (is_array($options))
		{
			$options = array_merge($this->option_defaults, $options);
		}
		else
		{
			$options = $this->option_defaults;
		}

		$options['simple'] = ($this->profile->prepared['myprofile'] ? $options['simple'] : false);

		$this->build_field_data($options['simple']);

		if ($options['category'] == 'all')
		{
			$categories = $this->categories;
			$show['profile_category_title'] = true;
			$enable_ajax_edit = true;
		}
		else
		{
			$categories = isset($this->categories["$options[category]"]) ?
			array($options['category'] => $this->categories["$options[category]"]) :
			array();
			$show['profile_category_title'] = false;
			$enable_ajax_edit = false;
		}

		$profilefields = '';

		foreach ($categories AS $profilefieldcategoryid => $profilefield_items)
		{
			$category = array(
				'title' => (
			$profilefieldcategoryid == 0 ?
			construct_phrase($vbphrase['about_x'], $this->profile->userinfo['username']) :
			$vbphrase["category{$profilefieldcategoryid}_title"]
			),
				'description' => $vbphrase["category{$profilefieldcategoryid}_desc"],
				'fields' => ''
				);

				foreach ($profilefield_items AS $profilefield)
				{
					$field_value = $this->profile->userinfo["field$profilefield[profilefieldid]"];
					fetch_profilefield_display($profilefield, $field_value);

					$this->mobiquo_array[] = array("name" => $profilefield['title'], "value" =>  $profilefield['value']);
					// can edit if viewing own profile and field is actually editable
					$show['profilefield_edit'] = (!$options['simple'] AND $enable_ajax_edit
					AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']
					AND ($profilefield['editable'] == 1 OR ($profilefield['editable'] == 2 AND empty($field_value)))
					);
					if ($show['profilefield_edit'] AND $profilefield['value'] == '')
					{
						// this field is to be editable but there's no value -- we need to show the field
						$profilefield['value'] = $vbphrase['n_a'];
					}

					($hook = vBulletinHook::fetch_hook('member_profileblock_profilefieldbit')) ? eval($hook) : false;

					if ($profilefield['value'] != '')
					{
						$show['extrainfo'] = true;

						eval('$category[\'fields\'] .= "' . fetch_template('memberinfo_profilefield') . '";');
					}
				}

				($hook = vBulletinHook::fetch_hook('member_profileblock_profilefield_category')) ? eval($hook) : false;

				if ($category['fields'])
				{
					eval('$profilefields .= "' . fetch_template('memberinfo_profilefield_category') . '";');
				}
		}

		$this->block_data['fields'] = $profilefields;
	}
}

/**
 * Profile Block for "About Me"
 *
 * @package vBulletin
 */
class vB_ProfileBlock_AboutMe extends vB_ProfileBlock
{
	/**
	 * The name of the template to be used for the block
	 *
	 * @var string
	 */
	var $template_name = 'memberinfo_block_aboutme';

	/**
	 * Variables to automatically prepare
	 *
	 * @var array
	 */
	var $auto_prepare = array('signature');

	/**
	 * Whether to return an empty wrapper if there is no content in the blocks
	 *
	 * @return bool
	 */
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	 * Should we actually display anything?
	 *
	 * @return	bool
	 */
	function confirm_display()
	{
		return (!empty($this->profile->prepared['signature']) OR $this->block_data['fields']);
	}

	/**
	 * Prepare any data needed for the output
	 *
	 * @param	string	The id of the block
	 * @param	array	Options specific to the block
	 */
	function prepare_output($id = '', $options = array())
	{
		global $show;

		$show['simple_link'] = (!$options['simple'] AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']);
		$show['edit_link'] = ($options['simple'] AND $this->registry->userinfo['userid'] == $this->profile->userinfo['userid']);
		$blockobj =& $this->factory->fetch('ProfileFields');
		$blockobj->prepare_output($id, $options);

		$this->mobiquo_array= $blockobj->mobiquo_array;
		$this->block_data['fields'] = $blockobj->block_data['fields'];
	}
}

/**
* Profile Block for Infractions
*
* @package vBulletin
*/
class vB_ProfileBlock_Infractions extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'memberinfo_block_infractions';

	/**
	* Sets/Fetches the default options for the block
	*
	*/
	function fetch_default_options()
	{
		$this->option_defaults = array(
			'pagenumber'	=> 1,
		);
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled()
	{
		if (
			!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction'])
		AND
			!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction'])
		AND
			$this->profile->userinfo['userid'] != $this->registry->userinfo['userid']
		)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		global $show;
		return ($this->block_data['infractionbits'] OR $show['giveinfraction']);
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase, $stylevar;

		$show['infractions'] = false;

		//($hook = vBulletinHook::fetch_hook('member_infraction_start')) ? eval($hook) : false;

		$perpage = $options['perpage'];
		$pagenumber = $options['pagenumber'];

		$totalinfractions = $this->registry->db->query_first_slave("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "infraction AS infraction
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON (infraction.postid = post.postid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
			WHERE infraction.userid = " . $this->profile->userinfo['userid'] . "
		");

		if ($totalinfractions['count'])
		{
			if (!$pagenumber OR ($options['tab'] != $id))
			{
				$pagenumber = 1;
			}

			// set defaults
			sanitize_pageresults($totalinfractions['count'], $pagenumber, $perpage, 100, 5);
			$limitlower = ($pagenumber - 1) * $perpage + 1;
			$limitupper = $pagenumber * $perpage;
			if ($limitupper > $totalinfractions['count'])
			{
				$limitupper = $totalinfractions['count'];
				if ($limitlower > $totalinfractions['count'])
				{
					$limitlower = $totalinfractions['count'] - $perpage;
				}
			}
			if ($limitlower <= 0)
			{
				$limitlower = 1;
			}

			if ($this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canreverseinfraction'])
			{
				$show['reverse'] = true;
			}

			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode_parser =& new vB_BbCodeParser($this->registry, fetch_tag_list());

			$infractions = $this->registry->db->query_read_slave("
				SELECT infraction.*, thread.title, user.username, thread.visible AS thread_visible, post.visible,
					forumid, postuserid, IF(ISNULL(post.postid) AND infraction.postid != 0, 1, 0) AS postdeleted
				FROM " . TABLE_PREFIX . "infraction AS infraction
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (infraction.postid = post.postid)
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
				INNER JOIN " . TABLE_PREFIX . "user AS user ON (infraction.whoadded = user.userid)
				WHERE infraction.userid = " . $this->profile->userinfo['userid'] . "
				ORDER BY infraction.dateline DESC
				LIMIT " . ($limitlower - 1) . ", $perpage
			");
			while ($infraction = $this->registry->db->fetch_array($infractions))
			{
				$show['expired'] = $show['reversed'] = $show['neverexpires'] = false;
				$card = ($infraction['points'] > 0) ? 'redcard' : 'yellowcard';
				$infraction['timeline'] = vbdate($this->registry->options['timeformat'], $infraction['dateline']);
				$infraction['dateline'] = vbdate($this->registry->options['dateformat'], $infraction['dateline']);
				switch($infraction['action'])
				{
					case 0:
						if ($infraction['expires'] != 0)
						{
							$infraction['expires_timeline'] = vbdate($this->registry->options['timeformat'], $infraction['expires']);
							$infraction['expires_dateline'] = vbdate($this->registry->options['dateformat'], $infraction['expires']);
							$show['neverexpires'] = false;
						}
						else
						{
							$show['neverexpires'] = true;
						}
						break;
					case 1:
						$show['expired'] = true;
						break;
					case 2:
						$show['reversed'] = true;
						break;
				}
				if (vbstrlen($infraction['title']) > 25)
				{
					$infraction['title'] = fetch_trimmed_title($infraction['title'], 24);
				}
				$infraction['reason'] = !empty($vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title']) ? $vbphrase['infractionlevel' . $infraction['infractionlevelid'] . '_title'] : ($infraction['customreason'] ? $infraction['customreason'] : $vbphrase['n_a']);

				$show['threadtitle'] = true;
				$show['postdeleted'] = false;
				if ($infraction['postid'] != 0)
				{
					if ($infraction['postdeleted'])
					{
						$show['postdeleted'] = true;
					}
					else if ((!$infraction['visible'] OR !$infraction['thread_visible']) AND !can_moderate($infraction['forumid'], 'canmoderateposts'))
					{
						$show['threadtitle'] = false;
					}
					else if (($infraction['visible'] == 2 OR $infraction['thread_visible'] == 2) AND !can_moderate($infraction['forumid'], 'candeleteposts'))
					{
						$show['threadtitle'] = false;
					}
					else
					{
						$forumperms = fetch_permissions($infraction['forumid']);
						if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canview']))
						{
							$show['threadtitle'] = false;
						}
						if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers']) AND ($infraction['postuserid'] != $this->registry->userinfo['userid'] OR $this->registry->userinfo['userid'] == 0))
						{
							$show['threadtitle'] = false;
						}
					}
				}
				$infractions_arr[] = $infraction;
				//($hook = vBulletinHook::fetch_hook('member_infractionbit')) ? eval($hook) : false;

				eval('$infractionbits .= "' . fetch_template('memberinfo_infractionbit') . '";');
			}
			unset($bbcode_parser);

			$this->block_data['pagenav'] = construct_page_nav(
				$pagenumber,
				$perpage,
				$totalinfractions['count'],
				'member.php?' . $this->registry->session->vars['sessionurl'] . "u=" . $this->profile->userinfo['userid'] . "&amp;tab=$id" .
				(!empty($options['perpage']) ? "&amp;pp=$perpage" : ""), '', $id
			);
			$this->block_data['infractionbits'] = $infractionbits;
		}
		$this->mobiquo_array =  $infractions_arr;

		$show['giveinfraction'] = (
				// Must have 'cangiveinfraction' permission. Branch dies right here majority of the time
				$this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['cangiveinfraction']
				// Can not give yourself an infraction
				AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid']
				// Can not give an infraction to a post that already has one
				// Can not give an admin an infraction
				AND !($this->profile->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
				// Only Admins can give a supermod an infraction
				AND (
					!($this->profile->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['ismoderator'])
					OR $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
				)
			);

		//($hook = vBulletinHook::fetch_hook('member_infraction_complete')) ? eval($hook) : false;
	}
}
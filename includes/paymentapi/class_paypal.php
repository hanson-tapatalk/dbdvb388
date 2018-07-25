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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Class that provides payment verification and form generation functions
*
* @package	vBulletin
* @version	$Revision: 93801 $
* @date		$Date: 2017-04-25 06:47:39 -0700 (Tue, 25 Apr 2017) $
*/
class vB_PaidSubscriptionMethod_paypal extends vB_PaidSubscriptionMethod
{
	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = true;

	/**
	* Perform verification of the payment, this is called from the payment gateway
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		// Leave all of these values as TYPE_STR since we have to send them back to paypal exactly how we received them!
		$this->registry->input->clean_array_gpc('p', array(
			'item_number'    => TYPE_STR,
			'business'       => TYPE_STR,
			'receiver_email' => TYPE_STR,
			'tax'            => TYPE_STR,
			'txn_type'       => TYPE_STR,
			'payment_status' => TYPE_STR,
			'mc_currency'    => TYPE_STR,
			'mc_gross'       => TYPE_STR,
			'txn_id'         => TYPE_STR
		));

		$this->transaction_id = $this->registry->GPC['txn_id'];

		$mc_gross = doubleval($this->registry->GPC['mc_gross']);
		$tax = doubleval($this->registry->GPC['tax']);

		$query[] = 'cmd=_notify-validate';
		foreach($_POST AS $key => $val)
		{
			$query[] = $key . '=' . urlencode ($val);
		}
		$query = implode('&', $query);

		$used_curl = false;

		if (function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, 'https://ipnpb.paypal.com/cgi-bin/webscr');
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

			$result = curl_exec($ch);
			curl_close($ch);
			if ($result !== false)
			{
				$used_curl = true;
			}
		}

		if (!$used_curl)
		{
			$this->error_code = 'curl_failure';
		}

		if ($result == 'VERIFIED' AND (strtolower($this->registry->GPC['business']) == strtolower($this->settings['ppemail']) OR strtolower($this->registry->GPC['receiver_email']) == strtolower($this->settings['primaryemail'])))
		{
			$this->paymentinfo = $this->registry->db->query_first("
				SELECT paymentinfo.*, user.username
				FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['item_number']) . "'
			");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$this->paymentinfo['currency'] = strtolower($this->registry->GPC['mc_currency']);
				$this->paymentinfo['amount'] = floatval($this->registry->GPC['mc_gross']);
				//its a paypal payment and we have some valid ids
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$cost = vb_unserialize($sub['cost']);
				if ($tax > 0)
				{
					$mc_gross -= $tax;
				}

				// Check if its a payment or if its a reversal
				if (($this->registry->GPC['txn_type'] == 'web_accept' OR $this->registry->GPC['txn_type'] == 'subscr_payment') AND $this->registry->GPC['payment_status'] == 'Completed')
				{
					if ($mc_gross == doubleval($cost["{$this->paymentinfo[subscriptionsubid]}"]['cost'][strtolower($this->registry->GPC['mc_currency'])]))
					{
						$this->type = 1;
					}
					else
					{
						$this->error_code = 'invalid_payment_amount';
					}
				}
				else if ($this->registry->GPC['payment_status'] == 'Reversed' OR $this->registry->GPC['payment_status'] == 'Refunded')
				{
					$this->type = 2;
				}
				else
				{
					$this->error_code = 'unhandled_payment_status_or_type';
				}
			}
			else
			{
				$this->error_code = 'invalid_subscriptionid';
			}

			$status_code = '200 OK';

			// Paypal likes to get told its message has been received
			if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
			{
				header('Status: ' . $status_code);
			}
			else
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status_code);
			}
			return ($this->type > 0);
		}
		else
		{
			$this->error_code = 'authentication_failure';
			$this->error = 'Invalid Request';
		}

		$status_code = '503 Service Unavailable';
		// Paypal likes to get told its message has been received
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: ' . $status_code);
		}
		else
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status_code);
		}

		return false;
	}

	/**
	* Test that required settings are available, and if we can communicate with the server (if required)
	*
	* @return	bool	If the vBulletin has all the information required to accept payments
	*/
	function test()
	{
		$communication = false;
		$query = 'cmd=_notify-validate';

		if (function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, 'https://ipnpb.paypal.com/cgi-bin/webscr');
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

			$result = curl_exec($ch);
			curl_close($ch);
			if ($result !== false)
			{
				$communication = true;
			}
		}

		return (!empty($this->settings['ppemail']) AND $communication);
	}

	/**
	* Generates HTML for the subscription form page
	*
	* @param	string		Hash used to indicate the transaction within vBulletin
	* @param	string		The cost of this payment
	* @param	string		The currency of this payment
	* @param	array		Information regarding the subscription that is being purchased
	* @param	array		Information about the user who is purchasing this subscription
	* @param	array		Array containing specific data about the cost and time for the specific subscription period
	*
	* @return	array		Compiled form information
	*/
	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbphrase, $vbulletin, $stylevar, $show;

		$item = $hash;
		$currency = strtoupper($currency);

		$show['notax'] = ($subinfo['options'] & $this->settings['_SUBSCRIPTIONOPTIONS']['tax']) ? false : true;
		$show['recurring'] = ($this->supports_recurring AND $timeinfo['recurring']) ? true : false;
		$no_shipping = ($subinfo['options'] & $this->settings['_SUBSCRIPTIONOPTIONS']['shipping1']) ? 0 : (($subinfo['options'] & $this->settings['_SUBSCRIPTIONOPTIONS']['shipping2']) ? 2 : 1);

		$form['action'] = 'https://www.paypal.com/cgi-bin/webscr';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings =& $this->settings;

		eval('$form[\'hiddenfields\'] .= "' . fetch_template('subscription_payment_paypal') . '";');
		return $form;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 03:31, Fri Jun 16th 2017 : $Revision: 93801 $
|| # $Date: 2017-04-25 06:47:39 -0700 (Tue, 25 Apr 2017) $
|| ####################################################################
\*======================================================================*/
?>

<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2012 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Nicholas J. Kisseberth <nkissebe@purdue.edu>
 * @copyright Copyright 2005-2012 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

/**
 * System plugin for hubzero
 */
class plgSystemHubzero extends \Hubzero\Plugin\Plugin
{
	/**
	 * Hook for after app routing
	 *
	 * @return   void
	 */
	public function onAfterRoute()
	{
	}

	/**
	 * Hook for after app initialization
	 *
	 * @return   void
	 */
	public function onAfterInitialise()
	{
		// Get the session object
		$session = App::get('session');

		if ($session->isNew())
		{
			$tracker = array();

			// Transfer tracking cookie data to session
			jimport('joomla.utilities.utility');
			jimport('joomla.user.helper');

			$hash = App::hash(App::get('client')->name . ':tracker');

			$key = App::hash('');
			$crypt = new \Hubzero\Encryption\Encrypter(
				new \Hubzero\Encryption\Cipher\Simple,
				new \Hubzero\Encryption\Key('simple', $key, $key)
			);

			if ($str = Request::getString($hash, '', 'cookie', 1 | 2))
			{
				$sstr = $crypt->decrypt($str);
				$tracker = @unserialize($sstr);

				if ($tracker === false) // old tracking cookies encrypted with UA which is too short term for a tracking cookie
				{
					//Create the encryption key, apply extra hardening using the user agent string
					$key = App::hash(@$_SERVER['HTTP_USER_AGENT']);
					$crypt = new \Hubzero\Encryption\Encrypter(
						new \Hubzero\Encryption\Cipher\Simple,
						new \Hubzero\Encryption\Key('simple', $key, $key)
					);
					$sstr = $crypt->decrypt($str);
					$tracker = @unserialize($sstr);
				}
			}

			if (!is_array($tracker))
			{
				$tracker = array();
			}

			if (empty($tracker['user_id']))
			{
				$session->clear('tracker.user_id');
			}
			else
			{
				$session->set('tracker.user_id', $tracker['user_id']);
			}

			if (empty($tracker['username']))
			{
				$session->clear('tracker.username');
			}
			else
			{
				$session->set('tracker.username', $tracker['username']);
			}

			if (empty($tracker['sid']))
			{
				$session->clear('tracker.psid');
			}
			else
			{
				$session->set('tracker.psid', $tracker['sid']);
			}

			$session->set('tracker.sid', $session->getId());

			if (empty($tracker['ssid']))
			{
				$session->set('tracker.ssid', $session->getId());
			}
			else
			{
				$session->set('tracker.ssid', $tracker['ssid']);
			}

			if (empty($tracker['rsid']))
			{
				$session->set('tracker.rsid', $session->getId());
			}
			else
			{
				$session->set('tracker.rsid', $tracker['rsid']);
			}

			// log tracking cookie detection to auth log

			$username = (empty($tracker['username'])) ? '-' : $tracker['username'];
			$user_id  = (empty($tracker['user_id']))  ? 0   : $tracker['user_id'];

			App::get('log')->logger('auth')->info($username . ' ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . ' detect');

			// set new tracking cookie with current data
			$tracker = array();
			$tracker['user_id'] = $session->get('tracker.user_id');
			$tracker['username'] = $session->get('tracker.username');
			$tracker['sid']  = $session->get('tracker.sid');
			$tracker['rsid'] = $session->get('tracker.rsid');
			$tracker['ssid'] = $session->get('tracker.ssid');

			$cookie = $crypt->encrypt(serialize($tracker));
			$lifetime = time() + 365*24*60*60*10;

			// Determine whether cookie should be 'secure' or not
			$secure   = false;
			$forceSsl = \Config::get('force_ssl', false);

			if (\App::isAdmin() && $forceSsl >= 1)
			{
				$secure = true;
			}
			else if (\App::isSite() && $forceSsl == 2)
			{
				$secure = true;
			}

			setcookie($hash, $cookie, $lifetime, '/', '', $secure, true);
		}

		// all page loads set apache log data
		if (strpos(php_sapi_name(),'apache') !== false)
		{
			apache_note('jsession', $session->getId());

			if (User::get('id') != 0)
			{
				apache_note('auth','session');
				apache_note('userid', User::get('id'));
			}
			else if (!empty($tracker['user_id']))
			{
				apache_note('auth','cookie');
				apache_note('userid', $tracker['user_id']);
			}
		}
	}

	/**
	 * Hook for login failure
	 *
	 * @param   unknown  $response
	 * @return  boolean
	 */
	public function onUserLoginFailure($response)
	{
		App::get('log')->logger('auth')->info((isset($_POST['username']) ? $_POST['username'] : '[unknown]') . ' ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . ' invalid');

		apache_note('auth','invalid');

		return true;
	}
}
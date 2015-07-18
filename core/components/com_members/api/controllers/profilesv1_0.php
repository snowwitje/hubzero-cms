<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2014 Purdue University. All rights reserved.
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
 * @author    Christopher Smoak <csmoak@purdue.edu>
 * @copyright Copyright 2005-2014 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Members\Api\Controllers;

use Hubzero\Component\ApiController;
use Component;
use Exception;
use stdClass;
use Request;
use Route;
use Lang;
use App;

/**
 * Members API controller class
 */
class Profilesv1_0 extends ApiController
{
	/**
	 * Display a list of members
	 *
	 * @apiMethod GET
	 * @apiUri    /members/list
	 * @apiParameter {
	 * 		"name":          "limit",
	 * 		"description":   "Number of result to return.",
	 * 		"type":          "integer",
	 * 		"required":      false,
	 * 		"default":       25
	 * }
	 * @apiParameter {
	 * 		"name":          "start",
	 * 		"description":   "Number of where to start returning results.",
	 * 		"type":          "integer",
	 * 		"required":      false,
	 * 		"default":       0
	 * }
	 * @apiParameter {
	 * 		"name":          "search",
	 * 		"description":   "A word or phrase to search for.",
	 * 		"type":          "string",
	 * 		"required":      false,
	 * 		"default":       ""
	 * }
	 * @apiParameter {
	 * 		"name":          "sort",
	 * 		"description":   "Field to sort results by.",
	 * 		"type":          "string",
	 * 		"required":      false,
	 *      "default":       "name",
	 * 		"allowedValues": "name, id"
	 * }
	 * @apiParameter {
	 * 		"name":          "sort_Dir",
	 * 		"description":   "Direction to sort results by.",
	 * 		"type":          "string",
	 * 		"required":      false,
	 * 		"default":       "desc",
	 * 		"allowedValues": "asc, desc"
	 * }
	 * @return  void
	 */
	public function listTask()
	{
		include_once(dirname(dirname(__DIR__)) . DS . 'tables' . DS . 'profile.php');

		$filters = array(
			'limit'      => Request::getInt('limit', 25),
			'start'      => Request::getInt('limitstart', 0),
			'search'     => Request::getVar('search', ''),
			'sortby'     => Request::getWord('sort', 'name'),
			'sort_Dir'   => strtoupper(Request::getWord('sortDir', 'DESC')),
			'authorized' => false,
			'emailConfirmed' => true,
			'public'     => 1,
			'show'       => 'members'
		);
		if ($filters['sortby'] == 'id')
		{
			$filters['sortby'] = 'uidNumber';
		}

		$database = App::get('db');
		$c = new \Components\Members\Tables\Profile($database);

		$response = new stdClass;
		$response->members = array();
		$response->total = $c->getCount($filters, false);

		if ($response->total)
		{
			$base = rtrim(Request::base(), '/');

			foreach ($c->getRecords($filters, false) as $i => $entry)
			{
				$obj = new stdClass;
				$obj->id        = $entry->uidNumber;
				$obj->name      = $entry->name;
				$obj->organization = $entry->organization;
				$obj->uri       = str_replace('/api', '', $base . '/' . ltrim(Route::url('index.php?option=' . $this->_option . '&id=' . $entry->uidNumber), '/'));

				$response->members[] = $obj;
			}
		}

		$response->success = true;

		$this->send($response);
	}

	/**
	 * Create a user profile
	 *
	 * @apiMethod POST
	 * @apiUri    /members
	 * @return    void
	 */
	public function createTask()
	{
		$this->requiresAuthentication();

		// Initialize new usertype setting
		$usersConfig = Component::params('com_users');
		$newUsertype = $usersConfig->get('new_usertype');
		if (!$newUsertype)
		{
			$db = App::get('db');
			$query = $db->getQuery(true)
				->select('id')
				->from('#__usergroups')
				->where('title = "Registered"');
			$db->setQuery($query);
			$newUsertype = $db->loadResult();
		}

		// Incoming
		$user = User::getRoot();
		$user->set('id', 0);
		$user->set('groups', array($newUsertype));
		$user->set('registerDate', Date::toSql());

		/*$user->set('name', Request::getVar('name', '', 'post'));
		if (!$user->get('name'))
		{
			return $this->error(500, Lang::txt('No name provided.'));
		}

		$user->set('username', Request::getVar('username', '', 'post'));
		if (!$user->get('username'))
		{
			return $this->error(500, Lang::txt('No username provided.'));
		}
		if (!\Hubzero\Utility\Validate::username($user->get('username')))
		{
			return $this->error(500, Lang::txt('Username not valid.'));
		}

		$user->set('email', Request::getVar('email', '', 'post'));
		if (!$user->get('email'))
		{
			return $this->error(500, Lang::txt('No email provided.'));
		}
		if (!\Hubzero\Utility\Validate::email($user->get('email')))
		{
			return $this->error(500, Lang::txt('Email not valid.'));
		}

		$user->set('password', $password);
		$user->set('password_clear', $password);
		$user->save();
		$user->set('password_clear', '');

		// Attempt to get the new user
		$profile = \Hubzero\User\Profile::getInstance($user->get('id'));
		$result  = is_object($profile);

		// Did we successfully create an account?
		if ($result)
		{
			$name = explode(' ', $user->get('name'));
			$surname    = $user->get('name');
			$givenName  = '';
			$middleName = '';
			if (count($name) > 1)
			{
				$surname    = array_pop($name);
				$givenName  = array_shift($name);
				$middleName = implode(' ', $name);
			}

			// Set the new info
			$profile->set('givenName', $givenName);
			$profile->set('middleName', $middleName);
			$profile->set('surname', $surname);
			$profile->set('name', $user->get('name'));
			$profile->set('emailConfirmed', -rand(1, pow(2, 31)-1));
			$profile->set('public', 0);
			$profile->set('password', '');

			$result = $profile->store();
		}

		if ($result)
		{
			$result = \Hubzero\User\Password::changePassword($profile->get('uidNumber'), $password);

			// Set password back here in case anything else down the line is looking for it
			$profile->set('password', $password);
			$profile->store();
		}

		// Did we successfully create/update an account?
		if (!$result)
		{
			return $this->error(500, Lang::txt('Account creation failed.'));
		}

		if ($groups = Request::getVar('groups', array(), 'post'))
		{
			foreach ($groups as $id)
			{
				$group = \Hubzero\User\Group::getInstance($id);
				if ($group)
				{
					if (!in_array($user->get('id'), $group->get('members'))
					{
						$group->add('members', array($user->get('id')));
						$group->update();
					}
				}
			}
		}*/

		// Create a response object
		$response = new stdClass;
		$response->id       = $user->get('id');
		$response->name     = $user->get('name');
		$response->email    = $user->get('email');
		$response->username = $user->get('username');

		$this->seend($response);
	}

	/**
	 * Get user profile info
	 *
	 * @apiMethod GET
	 * @apiUri    /members/{id}
	 * @apiParameter {
	 * 		"name":        "id",
	 * 		"description": "Member identifier",
	 * 		"type":        "integer",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @return  void
	 */
	public function readTask()
	{
		$userid = Request::getInt('id', 0);
		$result = \Hubzero\User\Profile::getInstance($userid);

		if ($result === false)
		{
			throw new Exception(Lang::txt('COM_MEMBERS_ERROR_USER_NOT_FOUND'), 404);
		}

		// Get any request vars
		$format = Request::getVar('format', 'json');

		$profile = array(
			'id'                => $result->get('uidNumber'),
			'username'          => $result->get('username'),
			'name'              => $result->get('name'),
			'first_name'        => $result->get('givenName'),
			'middle_name'       => $result->get('middleName'),
			'last_name'         => $result->get('surname'),
			'bio'               => $result->getBio('clean'),
			'email'             => $result->get('email'),
			'phone'             => $result->get('phone'),
			'url'               => $result->get('url'),
			'gender'            => $result->get('gender'),
			'organization'      => $result->get('organization'),
			'organization_type' => $result->get('orgtype'),
			'country_resident'  => $result->get('countryresident'),
			'country_origin'    => $result->get('countryorigin'),
			'member_since'      => $result->get('registerDate'),
			'orcid'             => $result->get('orcid'),
			'picture' => array(
				'thumb' => \Hubzero\User\Profile\Helper::getMemberPhoto($result, 0, true),
				'full'  => \Hubzero\User\Profile\Helper::getMemberPhoto($result, 0, false)
			)
		);

		/* Is this correct ? */
		//corrects image path, API application breaks Route::url() in the Helper::getMemberPhoto() method.
		$profile['picture']['thumb'] = str_replace('/api', '', $profile['picture']['thumb']);
		$profile['picture']['full'] = str_replace('/api', '', $profile['picture']['full']);


		// Encode and return result
		$object = new stdClass();
		$object->profile = $profile;

		$this->send($object);
	}

	/**
	 * Get a member's groups
	 *
	 * @apiMethod GET
	 * @apiUri    /members/{id}/groups
	 * @apiParameter {
	 * 		"name":        "id",
	 * 		"description": "Member identifier",
	 * 		"type":        "integer",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @return  void
	 */
	public function groupsTask()
	{
		$this->requiresAuthentication();

		$userid = Request::getInt('id', 0);
		$result = \Hubzero\User\Profile::getInstance($userid);

		if ($result === false)
		{
			throw new Exception(Lang::txt('COM_MEMBERS_ERROR_USER_NOT_FOUND'), 404);
		}

		$groups = \Hubzero\User\Helper::getGroups($result->get('uidNumber'), 'members', 0);

		$g = array();
		foreach ($groups as $k => $group)
		{
			$g[$k]['gidNumber']   = $group->gidNumber;
			$g[$k]['cn']          = $group->cn;
			$g[$k]['description'] = $group->description;
		}

		// Encode and return result
		$obj = new stdClass();
		$obj->groups = $g;

		$this->send($obj);
	}

	/**
	 * Check password
	 *
	 * @apiMethod GET
	 * @apiUri    /members/checkpass
	 * @apiParameter {
	 * 		"name":        "password1",
	 * 		"description": "Password to validate",
	 * 		"type":        "string",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @return  void
	 */
	public function checkpassTask()
	{
		$userid = App::get('authn')['user_id'];

		if (!isset($userid) || empty($userid))
		{
			// We don't have a logged in user, but this may be a password reset
			// If so, check session for a user id
			$session  = App::get('session');
			$registry = $session->get('registry');
			$userid   = (!is_null($registry)) ? $registry->get('com_users.reset.user', null) : null;
		}

		$result = \Hubzero\User\Profile::getInstance($userid);

		if ($result === false)
		{
			throw new Exception(Lang::txt('COM_MEMBERS_ERROR_USER_NOT_FOUND'), 404);
		}

		// Get the password rules
		$password_rules = \Hubzero\Password\Rule::getRules();

		$pw_rules = array();

		// Get the password rule descriptions
		foreach ($password_rules as $rule)
		{
			if (!empty($rule['description']))
			{
				$pw_rules[] = $rule['description'];
			}
		}

		// Get the password
		$pw = Request::getCmd('password1', null, 'post');

		// Validate the password
		if (!empty($pw))
		{
			$msg = \Hubzero\Password\Rule::validate($pw, $password_rules, $userid);
		}
		else
		{
			$msg = array();
		}

		$html = '';

		// Iterate through the rules and add the appropriate classes (passed/error)
		if (count($pw_rules) > 0)
		{
			foreach ($pw_rules as $rule)
			{
				if (!empty($rule))
				{
					if (!empty($msg) && is_array($msg))
					{
						$err = in_array($rule, $msg);
					}
					else
					{
						$err = '';
					}
					$mclass = ($err)  ? ' class="error"' : 'class="passed"';
					$html .= "<li $mclass>" . $rule . '</li>';
				}
			}

			if (!empty($msg) && is_array($msg))
			{
				foreach ($msg as $message)
				{
					if (!in_array($message, $pw_rules))
					{
						$html .= '<li class="error">' . $message . '</li>';
					}
				}
			}
		}

		// Encode sessions for return
		$object = new stdClass();
		$object->html = $html;

		$this->send($object);
	}

	/**
	 * Get a list of oranizations used throughout member profiles
	 *
	 * @apiMethod GET
	 * @apiUri    /members/organizations
	 *
	 * @apiParameter {
	 * 		"name":        "orgID",
	 * 		"description": "The row ID of an organization",
	 * 		"type":        "integer",
	 * 		"required":    false,
	 * 		"default":     null
	 * }
	 * @return  void
	 */
	public function organizationsTask()
	{
		include_once(dirname(dirname(__DIR__)) . DS . 'tables' . DS . 'organization.php');
		$database = App::get('db');

		$filters = array();

		$obj = new \Components\Members\Tables\Organization($database);

		$organizations = $obj->find('all', $filters);

		// Encode sessions for return
		$object = new stdClass();
		$object->organizations = $organizations;

		$this->send($object);
	}

	/**
	 * Get a resource based on tool name
	 *
	 * @param   string  $appname
	 * @param   object  $database
	 * @return  object
	 */
	private function getResourceFromAppname($appname, $database)
	{
		$sql = "SELECT r.*, tv.id as revisionid FROM `#__resources` as r, `#__tool_version` as tv WHERE tv.toolname=r.alias and tv.instance=" . $database->quote($appname);
		$database->setQuery($sql);
		return $database->loadObject();
	}
}
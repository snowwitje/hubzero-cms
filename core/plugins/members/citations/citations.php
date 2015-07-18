<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
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
 * @author    Alissa Nedossekina <alisa@purdue.edu>, Kevin Wojkovich <kevinw@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

// Include needed libs
require_once(PATH_CORE . DS . 'components' . DS . 'com_citations' . DS . 'helpers' . DS . 'format.php');

foreach (array('citation', 'association', 'author', 'secondary', 'sponsor', 'tags', 'format', 'type') as $inc)
{
	require_once(PATH_CORE . DS . 'components' . DS . 'com_citations' . DS . 'tables' . DS . $inc . '.php');
}

/**
 * Groups plugin class for citations
 */
class plgMembersCitations extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Return the alias and name for this category of content
	 *
	 * @param   object  $user    Current user
	 * @param   object  $member  Current member page
	 * @return  array
	 */
	public function &onMembersAreas($user, $member)
	{
		$areas = array(
			'citations' => Lang::txt('PLG_MEMBERS_CITATIONS'),
			'icon'      => '275D'
		);
		return $areas;
	}

	/**
	 * Perform actions when viewing a member profile
	 *
	 * @param   object  $user    Current user
	 * @param   object  $member  Current member page
	 * @param   string  $option  Start of records to pull
	 * @param   array   $areas   Active area(s)
	 * @return  array
	 */
	public function onMembers($user, $member, $option, $areas)
	{
		$returnhtml = true;

		// Check if our area is in the array of areas we want to return results for
		if (is_array($areas))
		{
			if (!array_intersect($areas, $this->onMembersAreas($user, $member))
			 && !array_intersect($areas, array_keys($this->onMembersAreas($user, $member))))
			{
				$returnhtml = false;
			}
		}

		$arr = array(
			'html'     => '',
			'metadata' => ''
		);

		$this->database = App::get('db');

		// Instantiate citations object and get count
		$obj = new \Components\Citations\Tables\Citation($this->database);
		$this->grand_total = $obj->getCount(array(
			'scope'    => 'member',
			'scope_id' => $member->get('uidNumber')
		), true);

		$arr['metadata']['count'] = $this->grand_total;

		//if we want to return content
		if ($returnhtml)
		{
			$this->member   = $member;
			$this->option   = $option;

			if (User::get('id') == $this->member->get('uidNumber'))
			{
				$this->params->set('access-manage', true);
			}

			$this->action = Request::getCmd('action', 'browse');

			if (!$this->params->get('access-manage'))
			{
				$this->action = 'browse';
			}

			if (in_array($this->action, array('import', 'upload', 'review', 'process', 'saved')))
			{
				include_once(Component::path('com_citations') . DS . 'models' . DS . 'importer.php');

				$this->importer = new \Components\Citations\Models\Importer(
					App::get('db'),
					App::get('filesystem'),
					App::get('config')->get('tmp_path') . DS . 'citations',
					App::get('session')->getId()
				);
				$this->importer->set('scope', 'member');
				$this->importer->set('scope_id', $this->member->get('uidNumber'));
				$this->importer->set('user', $this->member->get('uidNumber'));
				$this->importer->set('published', 1);
			}

			// Run task based on action
			switch ($this->action)
			{
				case 'save':   $arr['html'] .= $this->saveAction();      break;
				case 'add':
				case 'edit':   $arr['html'] .= $this->editAction();      break;
				case 'delete': $arr['html'] .= $this->deleteAction();    break;
				case 'browse': $arr['html'] .= $this->browseAction();    break;

				case 'import':  $arr['html'] .= $this->importAction();   break;
				case 'upload':  $arr['html'] .= $this->uploadAction();   break;
				case 'review':  $arr['html'] .= $this->reviewAction();   break;
				case 'process': $arr['html'] .= $this->processAction();  break;
				case 'saved':   $arr['html'] .= $this->savedAction();   break;

				default:       $arr['html'] .= $this->browseAction(); break;
			}
		}

		// Return the output
		return $arr;
	}

	/**
	 * Display a list of all citations, with filtering&search options.
	 *
	 * @return  string  HTML
	 */
	private function browseAction()
	{
		//initialize the view
		$view = $this->view('browse');

		// push objects to the view
		$view->member            = $this->member;
		$view->option            = $this->option;
		$view->grand_total       = $this->grand_total;
		$view->database          = $this->database;
		$view->config            = Component::params('com_citations');
		$view->isAdmin           = $this->params->get('access-manage');

		// Instantiate a new citations object
		$citations = new \Components\Citations\Tables\Citation($this->database);

		//get the earliest year we have citations for
		$view->earliest_year = $citations->getEarliestYear();

		// Incoming
		$view->filters = array(
			// Paging filters
			'limit'           => Request::getInt('limit', Config::get('list_limit')),
			'start'           => Request::getInt('limitstart', 0, 'get'),
			// Search/filtering params
			'scope'           => 'member',
			'scope_id'        => $this->member->get('uidNumber'),
			'id'              => Request::getInt('citation', 0),
			'tag'             => Request::getVar('tag', '', 'request', 'none', 2),
			'search'          => Request::getVar('search', ''),
			'type'            => Request::getVar('type', ''),
			'author'          => Request::getVar('author', ''),
			'publishedin'     => Request::getVar('publishedin', ''),
			'year_start'      => Request::getInt('year_start', $view->earliest_year),
			'year_end'        => Request::getInt('year_end', gmdate("Y")),
			'filter'          => Request::getVar('filter', ''),
			'sort'            => Request::getVar('sort', 'year DESC'),
			'reftype'         => Request::getVar('reftype', array('research' => 1, 'education' => 1, 'eduresearch' => 1, 'cyberinfrastructure' => 1)),
			'geo'             => Request::getVar('geo', array('us' => 1, 'na' => 1,'eu' => 1, 'as' => 1)),
			'aff'             => Request::getVar('aff', array('university' => 1, 'industry' => 1, 'government' => 1)),
			'startuploaddate' => Request::getVar('startuploaddate', '0000-00-00'),
			'enduploaddate'   => Request::getVar('enduploaddate', '0000-00-00')
		);

		// Affiliation filter
		$view->filter = array(
			'all'    => Lang::txt('PLG_MEMBERS_CITATIONS_ALL'),
			'aff'    => Lang::txt('PLG_MEMBERS_CITATIONS_AFFILIATED'),
			'nonaff' => Lang::txt('PLG_MEMBERS_CITATIONS_NONAFFILIATED')
		);
		if (!in_array($view->filters['filter'], array_keys($view->filter)))
		{
			$view->filters['filter'] = '';
		}

		// Sort Filter
		$view->sorts = array(
			'sec_cnt DESC' => Lang::txt('PLG_MEMBERS_CITATIONS_CITEDBY'),
			'year DESC'    => Lang::txt('PLG_MEMBERS_CITATIONS_YEAR'),
			'created DESC' => Lang::txt('PLG_MEMBERS_CITATIONS_NEWEST'),
			'title ASC'    => Lang::txt('PLG_MEMBERS_CITATIONS_TITLE'),
			'author ASC'   => Lang::txt('PLG_MEMBERS_CITATIONS_AUTHOR'),
			'journal ASC'  => Lang::txt('PLG_MEMBERS_CITATIONS_JOURNAL')
		);
		if (!in_array($view->filters['sort'], array_keys($view->sorts)))
		{
			$view->filters['sort'] = 'created DESC';
		}

		// Handling ids of the the boxes checked for download
		$referer = (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';
		$session = App::get('session');

		// If it's new search remove all user citation checkmarks
		if (isset($_POST['filter']))
		{
			$view->filters['idlist'] = "";
			$session->set('idlist', $view->filters['idlist']);
		}
		else
		{
			$view->filters['idlist'] = Request::getVar('idlist', $session->get('idlist'));
			$session->set('idlist', $view->filters['idlist']);
		}

		// Reset the filter if the user came from a different section
		if (strpos($referer, "/citations/browse") == false)
		{
			$view->filters['idlist'] = "";
			$session->set('idlist', $view->filters['idlist']);
		}

		//Convert upload dates to correct time format
		if ($view->filters['startuploaddate'] == '0000-00-00'
			|| $view->filters['startuploaddate'] == '0000-00-00 00:00:00'
			|| $view->filters['startuploaddate'] == '')
		{
			$view->filters['startuploaddate'] = '0000-00-00 00:00:00';
		}
		else
		{
			$view->filters['startuploaddate'] = Date::of($view->filters['startuploaddate'])->format('Y-m-d 00:00:00');
		}
		if ($view->filters['enduploaddate'] == '0000-00-00'
			|| $view->filters['enduploaddate'] == '0000-00-00 00:00:00'
			|| $view->filters['enduploaddate'] == '')
		{
			$view->filters['enduploaddate'] = Date::modify('+1 DAY')->format('Y-m-d 00:00:00');
		}
		else
		{
			$view->filters['enduploaddate'] = Date::of($view->filters['enduploaddate'])->format('Y-m-d 00:00:00');
		}

		//Make sure the end date for the upload search isn't before the start date
		if ($view->filters['startuploaddate'] > $view->filters['enduploaddate'])
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=browse'),
				Lang::txt('PLG_MEMBERS_CITATIONS_END_DATE_MUST_BE_AFTER_START_DATE'),
				'error'
			);
			return;
		}

		// Get record count
		$view->total     = $citations->getCount($view->filters, $view->isAdmin);

		$view->citations = $citations->getRecords($view->filters, $view->isAdmin);

		// Add some data to our view for form filtering/sorting
		$ct = new \Components\Citations\Tables\Type($this->database);
		$view->types = $ct->getType();

		//get the users id to make lookup
		$users_ip = Request::ip();

		//get the param for ip regex to use machine ip
		$ip_regex = array('10.\d{2,5}.\d{2,5}.\d{2,5}');

		$use_machine_ip = false;
		foreach ($ip_regex as $ipr)
		{
			$match = preg_match('/' . $ipr . '/i', $users_ip);
			if ($match)
			{
				$use_machine_ip = true;
			}
		}

		//make url based on if were using machine ip or users
		if ($use_machine_ip)
		{
			$url = 'http://worldcatlibraries.org/registry/lookup?IP=' . $_SERVER['SERVER_ADDR'];
		}
		else
		{
			$url = 'http://worldcatlibraries.org/registry/lookup?IP=' . $users_ip;
		}

		//get the resolver
		$r = null;
		if (function_exists('curl_init'))
		{
			$cURL = curl_init();
			curl_setopt($cURL, CURLOPT_URL, $url);
			curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($cURL, CURLOPT_TIMEOUT, 10);
			$r = curl_exec($cURL);
			curl_close($cURL);
		}

		//parse the returned xml
		$view->openurl = array(
			'link' => '',
			'text' => '',
			'icon' => ''
		);

		//parse the return from resolver lookup
		$resolver = null;
		$xml = simplexml_load_string($r);
		if (isset($xml->resolverRegistryEntry))
		{
			$resolver = $xml->resolverRegistryEntry->resolver;
		}

		//if we have resolver set vars for creating open urls
		if ($resolver != null)
		{
			$view->openurl['link'] = $resolver->baseURL;
			$view->openurl['text'] = $resolver->linkText;
			$view->openurl['icon'] = $resolver->linkIcon;
		}

		// Output HTML
		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		return $view->loadTemplate();
	}

	/**
	 * Display the form allowing to edit a citation
	 *
	 * @return  string  HTML
	 */
	private function editAction($row=null)
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		// Create view object
		$view = $this->view('edit');

		$view->member   = $this->member;
		$view->option   = $this->option;
		$view->database = $this->database;
		$view->config   = Component::params('com_citations');

		// Get the citation types
		$citationsType = new \Components\Citations\Tables\Type($this->database);
		$view->types = $citationsType->getType();

		$fields = array();
		foreach ($view->types as $type)
		{
			if (isset($type['fields']))
			{
				$f = $type['fields'];
				if (strpos($f, ',') !== false)
				{
					$f = str_replace(',', "\n", $f);
				}

				$f = array_map('trim', explode("\n", $f));
				$f = array_values(array_filter($f));

				$fields[strtolower(str_replace(' ', '', $type['type_title']))] = $f;
			}
		}

		// add an empty value for the first type
		array_unshift($view->types, array(
			'id'         => 0,
			'type'       => '',
			'type_title' => ' - Select a Type &mdash;'
		));

		// Incoming
		$id = Request::getInt('citation', 0);

		// Load the object
		if (is_object($row))
		{
			$view->row = $row;
		}
		else
		{
			$view->row = new \Components\Citations\Tables\Citation($this->database);
			$view->row->load($id);
		}

		//make sure title isnt too long
		$maxTitleLength = 30;
		$shortenedTitle = (strlen($view->row->title) > $maxTitleLength)
			? substr($view->row->title, 0, $maxTitleLength) . '&hellip;'
			: $view->row->title;

		// Set the pathway
		if ($id && $id != 0)
		{
			Pathway::append($shortenedTitle, 'index.php?option=com_citations&task=view&id=' . $view->row->id);
			Pathway::append(Lang::txt('PLG_MEMBERS_CITATIONS_EDIT'));
		}
		else
		{
			Pathway::append(Lang::txt('PLG_MEMBERS_CITATIONS_ADD'));
		}

		// Set the page title
		Document::setTitle( Lang::txt('PLG_MEMBERS_CITATIONS_CITATION') . $shortenedTitle );

		//push jquery to doc
		Document::addScriptDeclaration('var fields = ' . json_encode($fields) . ';');

		// Instantiate a new view
		$view->title  = Lang::txt('PLG_MEMBERS_CITATIONS') . ': ' . Lang::txt('PLG_MEMBERS_CITATIONS_' . strtoupper($this->action));

		// No ID, so we're creating a new entry
		// Set the ID of the creator
		if (!$id)
		{
			$view->row->uid = User::get('id');

			// It's new - no associations to get
			$view->assocs = array();

			//tags & badges
			$view->tags   = array();
			$view->badges = array();

			$view->row->id = -time();

			/*$author = new \Components\Citations\Tables\Author($this->database);
			$author->cid          = $view->row->id;
			$author->author       = $this->member->get('name');
			$author->uidNumber    = $this->member->get('uidNumber');
			$author->organization = $this->member->get('organization');
			$author->givenName    = $this->member->get('givenName');
			$author->middleName   = $this->member->get('middleName');
			$author->surname      = $this->member->get('surname');
			$author->email        = $this->member->get('email');
			if (!$author->check())
			{
				$this->setError($author->getError());
			}
			if (!$author->store())
			{
				$this->setError($author->getError());
			}*/
		}
		else
		{
			$assoc = new \Components\Citations\Tables\Association($this->database);
			$view->assocs = $assoc->getRecords(array('cid' => $id), true);

			//tags & badges
			$view->tags   = \Components\Citations\Helpers\Format::citationTags($view->row, $this->database, false);
			$view->badges = \Components\Citations\Helpers\Format::citationBadges($view->row, $this->database, false);
		}

		// Output HTML
		foreach ($this->getErrors() as $error)
		{
			$view->setError($error);
		}

		return $view->loadTemplate();
	}

	/**
	 * Save an entry
	 *
	 * @return  void
	 */
	private function saveAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(\Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		//get the posted vars
		$c = Request::getVar('fields', array(), 'post');
		if (isset($c['format_type']))
		{
			$c['format'] = $c['format_type'];
		}

		// set scope & scope id in save so no one can mess with hidden form inputs
		$c['scope']    = 'member';
		$c['scope_id'] = $this->member->get('uidNumber');

		// Bind incoming data to object
		$row = new \Components\Citations\Tables\Citation($this->database);
		if (!$row->bind($c))
		{
			$this->setError($row->getError());
			$this->browseAction();
			return;
		}

		$auths = array();
		$authors = $row->authors($row->id);
		foreach ($authors as $a)
		{
			$auths[] = $a->author;
		}
		$row->author = implode(', ', $auths);

		// New entry so set the created date
		$isNew = 0;
		if (!$row->id || $row->id < 0)
		{
			$isNew = $row->id;
			$row->id = 0;
			$row->created = Date::toSql();
		}

		// Field named 'uri' due to conflict with existing 'url' variable
		$row->url = Request::getVar('uri', '', 'post');

		// Check content for missing required data
		if (!$row->check())
		{
			$this->setError($row->getError());
			$this->editAction($row);
			return;
		}

		// Store new content
		if (!$row->store())
		{
			$this->setError($row->getError());
			$this->editAction($row);
			return;
		}

		if ($isNew < 0)
		{
			// Update all Citation ID for authors.
			//
			// This will happen if a citation is new and a temp
			// ID was used.
			foreach ($authors as $a)
			{
				$author = new \Components\Citations\Tables\Author($this->database);
				$author->id  = $a->id;
				$author->cid = $row->id;
				$author->store();
			}
		}

		// Incoming associations
		$arr     = Request::getVar('assocs', array());
		$ignored = array();

		foreach ($arr as $a)
		{
			$a = array_map('trim', $a);

			// Initiate extended database class
			$assoc = new \Components\Citations\Tables\Association($this->database);

			//check to see if we should delete
			if (isset($a['id']) && $a['tbl'] == '' && $a['oid'] == '')
			{
				// Delete the row
				if (!$assoc->delete($a['id']))
				{
					$this->setError($assoc->getError());
					$this->browseAction();
					return;
				}
			}
			else if ($a['tbl'] != '' || $a['oid'] != '')
			{
				$a['cid'] = $row->id;

				// bind the data
				if (!$assoc->bind($a))
				{
					$this->setError($assoc->getError());
					$this->browseAction();
					return;
				}

				// Check content
				if (!$assoc->check())
				{
					$this->setError($assoc->getError());
					$this->browseAction();
					return;
				}

				// Store new content
				if (!$assoc->store())
				{
					$this->setError($assoc->getError());
					$this->browseAction();
					return;
				}
			}
		}

		$config = Component::params('com_citations');

		//check if we are allowing tags
		if ($config->get('citation_allow_tags', 'no') == 'yes')
		{
			//get tags
			$tags = trim(Request::getVar('tags', ''));
			unset($c['tags']);

			$ct1 = new \Components\Citations\Tables\Tags($row->id);
			$ct1->setTags($tags, User::get('id'), 0, 1, '');
		}

		//check if we are allowing badges
		if ($config->get('citation_allow_badges', 'no') == 'yes')
		{
			//get badges
			$badges = trim(Request::getVar('badges', ''));
			unset($c['badges']);

			$ct2 = new \Components\Citations\Tables\Tags($row->id);
			$ct2->setTags($badges, User::get('id'), 0, 1, 'badge');
		}

		// resdirect after save
		App::redirect(
			Route::url($this->member->getLink() . '&active=' . $this->_name),
			($this->getError() ? $this->getError() : Lang::txt('PLG_MEMBERS_CITATIONS_CITATION_SAVED')),
			($this->getError() ? 'error' : 'success')
		);
		return;
	}

	/**
	 * Save an entry
	 *
	 * @return  void
	 */
	private function deleteAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(\Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		// Incoming
		$id = Request::getInt('citation', 0);

		// Load the object
		$row = new \Components\Citations\Tables\Citation($this->database);
		$row->load($id);

		if ($row->id)
		{
			if (!$row->delete())
			{
				App::redirect(
					Route::url($this->member->getLink() . '&active=' . $this->_name),
					$row->getError(),
					'error'
				);
				return;
			}

			$author = new \Components\Citations\Tables\Author($this->database);
			$author->deleteForCitation($id);

			$assoc = new \Components\Citations\Tables\Association($this->database);
			$assoc->deleteForCitation($id);
		}

		App::redirect(
			Route::url($this->member->getLink() . '&active=' . $this->_name),
			Lang::txt('PLG_MEMBERS_CITATIONS_CITATION_DELETED'),
			'success'
		);
		return;
	}

	/**
	 * Redirect to login form
	 *
	 * @return  void
	 */
	private function loginAction()
	{
		App::redirect(
			Route::url('index.php?option=com_users&view=login&return=' . base64_encode(Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=' . $this->action, false, true))),
			Lang::txt('PLG_MEMBERS_CITATIONS_NOT_LOGGEDIN'),
			'warning'
		);
		return;
	}

	/**
	 * Display a form for importing citations
	 *
	 * @return  void
	 */
	private function importAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		//are we allowing importing
		$config = Component::params('com_citations');
		$importParam = $config->get('citation_bulk_import', 1);

		// If importing is turned off go to intro page
		if (!$importParam)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name)
			);
			return;
		}

		$view = $this->view('display', 'import');

		// push objects to the view
		$view->member   = $this->member;
		$view->option   = $this->option;
		$view->database = $this->database;
		$view->config   = $config;
		$view->isAdmin  = $this->params->get('access-manage');

		// citation temp file cleanup
		$this->importer->cleanup();

		$view->accepted_files = Event::trigger('citation.onImportAcceptedFiles' , array());

		$view->messages = Notify::messages('plg_members_citations');

		return $view->loadTemplate();
	}

	/**
	 * Upload a file
	 *
	 * @return  void
	 */
	private function uploadAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		Request::checkToken();

		// get file
		$file = Request::file('citations_file');

		// make sure we have a file
		$filename = $file->getClientOriginalName();
		if ($filename == '')
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_MISSING_FILE'),
				'error'
			);
			return;
		}

		// make sure file is under 4MB
		if ($file->getSize() > 4000000)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_FILE_TOO_BIG'),
				'error'
			);
			return;
		}

		// make sure we dont have any file errors
		if ($file->getError() > 0)
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_UPLOAD_FAILURE'), 500);
		}

		// call the plugins
		$citations = Event::trigger('citation.onImport' , array($file));
		$citations = array_values(array_filter($citations));

		// did we get citations from the citation plugins
		if (!$citations)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_PROCESS_FAILURE'),
				'error'
			);
			return;
		}

		if (!isset($citations[0]['attention']))
		{
			$citations[0]['attention'] = '';
		}
		if (!isset($citations[0]['no_attention']))
		{
			$citations[0]['no_attention'] = '';
		}

		if (!$this->importer->writeRequiresAttention($citations[0]['attention']))
		{
			Notify::error(Lang::txt('Unable to write temporary file.'), 'plg_members_citations');
		}

		if (!$this->importer->writeRequiresNoAttention($citations[0]['no_attention']))
		{
			Notify::error(Lang::txt('Unable to write temporary file.'), 'plg_members_citations');
		}

		App::redirect(
			Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=review')
		);
	}

	/**
	 * Review import items
	 *
	 * @return  void
	 */
	private function reviewAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		$citations_require_attention    = $this->importer->readRequiresAttention();
		$citations_require_no_attention = $this->importer->readRequiresNoAttention();

		// make sure we have some citations
		if (!$citations_require_attention && !$citations_require_no_attention)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_MISSING_FILE_CONTINUE'),
				'error'
			);
			return;
		}

		$view = $this->view('review', 'import');
		$view->citations_require_attention    = $citations_require_attention;
		$view->citations_require_no_attention = $citations_require_no_attention;

		$view->member   = $this->member;
		$view->option   = $this->option;
		$view->database = $this->database;
		$view->isAdmin  = $this->params->get('access-manage');

		$view->messages = Notify::messages('plg_members_citations');

		return $view->loadTemplate();
	}

	/**
	 * Process import selections
	 *
	 * @return  void
	 */
	private function processAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		Request::checkToken();

		$cites_require_attention    = $this->importer->readRequiresAttention();
		$cites_require_no_attention = $this->importer->readRequiresNoAttention();

		// action for citations needing attention
		$citations_action_attention    = Request::getVar('citation_action_attention', array());

		// action for citations needing no attention
		$citations_action_no_attention = Request::getVar('citation_action_no_attention', array());

		// check to make sure we have citations
		if (!$cites_require_attention && !$cites_require_no_attention)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_MISSING_FILE_CONTINUE'),
				'error'
			);
			return;
		}

		// vars
		$config = Component::params('com_citations');
		$allow_tags   = $config->get('citation_allow_tags', 'no');
		$allow_badges = $config->get('citation_allow_badges', 'no');

		$this->importer->setTags($allow_tags == 'yes');
		$this->importer->setBadges($allow_badges == 'yes');

		// Process
		$results = $this->importer->process(
			$citations_action_attention,
			$citations_action_no_attention
		);

		// success message a redirect
		Notify::success(
			Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_RESULTS_SAVED', count($results['saved'])),
			'plg_citations'
		);

		// if we have citations not getting saved
		if (count($results['not_saved']) > 0)
		{
			Notify::warning(
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_RESULTS_NOT_SAVED', count($results['not_saved'])),
				'plg_citations'
			);
		}

		if (count($results['error']) > 0)
		{
			Notify::error(
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_RESULTS_SAVE_ERROR', count($results['error'])),
				'plg_citations'
			);
		}

		//get the session object
		$session = App::get('session');

		//ids of sessions saved and not saved
		$session->set('citations_saved', $results['saved']);
		$session->set('citations_not_saved', $results['not_saved']);
		$session->set('citations_error', $results['error']);

		//delete the temp files that hold citation data
		$this->importer->cleanup(true);

		//redirect
		App::redirect(
			Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=saved')
		);
	}

	/**
	 * Show the results of the import
	 *
	 * @return  void
	 */
	private function savedAction()
	{
		// Check if they're logged in
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (!$this->params->get('access-manage'))
		{
			throw new Exception(Lang::txt('PLG_MEMBERS_CITATIONS_NOT_AUTHORIZED'), 403);
		}

		// Get the session object
		$session = App::get('session');

		// Get the citations
		$citations_saved     = $session->get('citations_saved');
		$citations_not_saved = $session->get('citations_not_saved');
		$citations_error     = $session->get('citations_error');

		// Check to make sure we have citations
		if (!$citations_saved && !$citations_not_saved)
		{
			App::redirect(
				Route::url($this->member->getLink() . '&active=' . $this->_name . '&action=import'),
				Lang::txt('PLG_MEMBERS_CITATIONS_IMPORT_MISSING_FILE_CONTINUE'),
				'error'
			);
			return;
		}

		$view = $this->view('saved', 'import');
		$view->member   = $this->member;
		$view->option   = $this->option;
		$view->isAdmin  = $this->params->get('access-manage');
		$view->config   = Component::params('com_citations');
		$view->database = $this->database;
		$view->filters  = array(
			'start'  => 0,
			'search' => ''
		);
		$view->citations = array();

		foreach ($citations_saved as $cs)
		{
			$cc = new \Components\Citations\Tables\Citation($this->database);
			$cc->load($cs);
			$view->citations[] = $cc;
		}

		$view->openurl['link'] = '';
		$view->openurl['text'] = '';
		$view->openurl['icon'] = '';

		//take care fo type
		$ct = new \Components\Citations\Tables\Type($this->database);
		$view->types = $ct->getType();

		$view->messages = Notify::messages('plg_members_citations');

		return $view->loadTemplate();
	}
}
<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
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
 * @author    Alissa Nedossekina <alisa@purdue.edu>
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

/**
 * Manage publication master types
 */
class PublicationsControllerTypes extends \Hubzero\Component\AdminController
{
	/**
	 * List types
	 *
	 * @return     void
	 */
	public function displayTask()
	{
		// Get configuration
		$app 	= JFactory::getApplication();
		$config = JFactory::getConfig();

		// Incoming
		$this->view->filters = array();
		$this->view->filters['limit']    = $app->getUserStateFromRequest(
			$this->_option . '.categories.limit',
			'limit',
			$config->getValue('config.list_limit'),
			'int'
		);
		$this->view->filters['start']    = $app->getUserStateFromRequest(
			$this->_option . '.categories.limitstart',
			'limitstart',
			0,
			'int'
		);
		$this->view->filters['search']     = trim($app->getUserStateFromRequest(
			$this->_option . '.categories.search',
			'search',
			''
		));
		$this->view->filters['sort']     = trim($app->getUserStateFromRequest(
			$this->_option . '.categories.sort',
			'filter_order',
			'id'
		));
		$this->view->filters['sort_Dir'] = trim($app->getUserStateFromRequest(
			$this->_option . '.categories.sortdir',
			'filter_order_Dir',
			'ASC'
		));

		$this->view->filters['state'] = 'all';

		// Push some styles to the template
		$document = JFactory::getDocument();
		$document->addStyleSheet('components' . DS . $this->_option . DS
			. 'assets' . DS . 'css' . DS . 'publications.css');

		// Instantiate an object
		$rt = new PublicationMasterType($this->database);

		// Get a record count
		$this->view->total = $rt->getCount($this->view->filters);

		// Get records
		$this->view->rows = $rt->getRecords($this->view->filters);

		// initiate paging
		jimport('joomla.html.pagination');
		$this->view->pageNav = new JPagination(
			$this->view->total,
			$this->view->filters['start'],
			$this->view->filters['limit']
		);

		// Set any errors
		if ($this->getError())
		{
			$this->view->setError($this->getError());
		}

		$this->view->config = $this->config;

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Add a new type
	 *
	 * @return     void
	 */
	public function addTask()
	{
		$this->view->setLayout('edit');
		$this->editTask();
	}

	/**
	 * Edit a type
	 *
	 * @return     void
	 */
	public function editTask( $row = null )
	{
		// Use new curation flow?
		$useBlocks  = $this->config->get('curation', 0);
		if ($useBlocks)
		{
			$this->view->setLayout('curation');
		}

		if ($row)
		{
			$this->view->row = $row;
		}
		else
		{
			// Incoming (expecting an array)
			$id = JRequest::getVar('id', array(0));
			if (is_array($id))
			{
				$id = $id[0];
			}
			else
			{
				$id = 0;
			}

			// Load the object
			$this->view->row = new PublicationMasterType($this->database);
			$this->view->row->load($id);

			// Get curation
			if ($useBlocks)
			{
				$this->view->curation = new PublicationsCuration(
					$this->database,
					$this->view->row->curation
				);

				// Get blocks model
				$blocksModel = new PublicationsModelBlocks($this->database);

				// Get available blocks
				$this->view->blocks = $blocksModel->getBlocks('*',
					" WHERE status=1",
					" ORDER BY ordering, id"
				);

			}
		}

		// Set any errors
		if ($this->getError())
		{
			$this->view->setError($this->getError());
		}

		$this->view->config = $this->config;

		// Get all active categories
		$objC = new PublicationCategory($this->database);
		$this->view->cats = $objC->getCategories();

		// Push some styles to the template
		$document = JFactory::getDocument();
		$document->addStyleSheet('components' . DS . $this->_option . DS . 'assets'
			. DS . 'css' . DS . 'publications.css');

		$this->view->config = $this->config;

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Save a publication and fall through to edit view
	 *
	 * @return void
	 */
	public function applyTask()
	{
		$this->saveTask(true);
	}

	/**
	 * Save a type
	 *
	 * @return     void
	 */
	public function saveTask($redirect = false)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		// Use new curation flow?
		$useBlocks  = $this->config->get('curation', 0);

		$fields = JRequest::getVar('fields', array(), 'post');
		$fields = array_map('trim', $fields);

		// Initiate extended database class
		$row = new PublicationMasterType($this->database);

		$url = 'index.php?option=' . $this->_option . '&controller=' . $this->_controller
			. '&task=edit&id[]=' . $fields['id'];

		if (!$row->bind($fields))
		{
			$this->addComponentMessage($row->getError(), 'error');
			$this->setRedirect($url);
			return;
		}

		// Save curation config
		if ($useBlocks && $row->id)
		{
			// Incoming
			$curatorGroup = JRequest::getVar('curatorgroup', '');
			if ($group = \Hubzero\User\Group::getInstance($curatorGroup))
			{
				$row->curatorGroup = $group->get('gidNumber');
			}
			if (!$curatorGroup)
			{
				$row->curatorGroup = 0;
			}

			$objC = new PublicationsCuration($this->database, $row->curation);

			$manifest = $objC->_manifest;

			// Get curation configs
			$curation = JRequest::getVar('curation', array(), 'post');

			// Collect modifications
			if (is_array($curation))
			{
				// Save params
				if (isset($curation['params']))
				{
					foreach ($curation['params'] as $cpName => $cpValue)
					{
						$manifest->params->$cpName = trim($cpValue);
					}
				}
			}

			// Store modified curation
			$row->curation = json_encode($manifest);
		}
		else
		{
			// Get parameters
			$params = JRequest::getVar('params', '', 'post');
			if (is_array($params))
			{
				$txt = array();
				foreach ($params as $k => $v)
				{
					$txt[] = "$k=$v";
				}
				$row->params = implode("\n", $txt);
			}
		}

		// Check content
		if (!$row->check())
		{
			$this->setRedirect($url, $row->getError(), 'error');
			return;
		}

		// Store new content
		if (!$row->store())
		{
			$this->setRedirect($url, $row->getError(), 'error');
			return;
		}

		// Redirect to edit view?
		if ($redirect)
		{
			$this->setRedirect(
				$url,
				JText::_('COM_PUBLICATIONS_SUCCESS_TYPE_SAVED')
			);
		}
		else
		{
			$this->setRedirect(
				'index.php?option=' . $this->_option . '&controller=' . $this->_controller,
				JText::_('COM_PUBLICATIONS_SUCCESS_TYPE_SAVED')
			);
		}
		return;
	}

	public function orderupTask()
	{
		$this->reorderTask(-1);
	}

	public function orderdownTask()
	{
		$this->reorderTask(1);
	}

	/**
	 * Reorders licenses
	 * Redirects to license listing
	 *
	 * @return     void
	 */
	public function reorderTask($dir = 0)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		// Incoming
		$id = JRequest::getVar('id', array(0), '', 'array');

		// Load row
		$row = new PublicationMasterType($this->database);
		$row->load( (int) $id[0]);

		// Update order
		$row->changeOrder($dir);

		$this->setRedirect(
			'index.php?option=' . $this->_option . '&controller=' . $this->_controller
		);
	}

	/**
	 * Cancel a task (redirects to default task)
	 *
	 * @return	void
	 */
	public function cancelTask()
	{
		$this->setRedirect('index.php?option=' . $this->_option . '&controller=' . $this->_controller);
	}
}

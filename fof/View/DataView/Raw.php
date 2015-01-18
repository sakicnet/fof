<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\View\DataView;

use FOF30\Container\Container;
use FOF30\Model\DataModel;
use FOF30\Model\DataModel\Collection;
use FOF30\View\View;

defined('_JEXEC') or die;

/**
 * View for a raw data-driven view
 */
class Raw extends View implements DataViewInterface
{
	/** @var   \stdClass  Data lists */
	protected $lists = null;

	/** @var \JPagination The pagination object */
	protected $pagination = null;

	/** @var \JRegistry Page parameters object, for front-end views */
	protected $pageParams = null;

	/** @var Collection The records loaded (browse views) */
	protected $items = null;

	/** @var DataModel The record loaded (read, edit, add views) */
	protected $item = null;

	/** @var int The total number of items in the model (more than those loaded) */
	protected $itemCount = 0;

	/** @var \stdClass ACL permissions map */
	protected $permissions = null;

	/** @var array Additional permissions to fetch on object creation, see getPermissions() */
	protected $additionalPermissions = array();

	/**
	 * Overrides the constructor to apply Joomla! ACL permissions
	 *
	 * @param \FOF30\Container\Container $container
	 */
	public function __construct(Container $container)
	{
		parent::__construct($container);

		$this->permissions = $this->getPermissions(null, $this->additionalPermissions);
	}

	/**
	 * Returns a permissions object.
	 *
	 * The additionalPermissions array is a hashed array of local key => Joomla! ACL key value pairs. Local key is the
	 * name of the permission in the permissions object, whereas Joomla! ACL key is the name of the ACL permission
	 * known to Joomla! e.g. "core.manage", "foobar.something" and so on.
	 *
	 * Note: on CLI applications all permissions are set to TRUE. There is no ACL check there.
	 *
	 * @param   null|string  $component              The name of the component. Leave empty for automatic detection.
	 * @param   array        $additionalPermissions  Any additional permissions you want to add to the object.
	 *
	 * @return  object
	 */
	protected function getPermissions($component = null, array $additionalPermissions = array())
	{
		// Make sure we have a component
		if (empty($component))
		{
			$component = $this->container->componentName;
		}

		// Initialise with all true
		$permissions = array(
			'create'	 => true,
			'edit'		 => true,
			'editown'	 => true,
			'editstate'	 => true,
			'delete'	 => true,
		);

		if (!empty($additionalPermissions))
		{
			foreach ($additionalPermissions as $localKey => $joomlaPermission)
			{
				$permissions[$localKey] = true;
			}
		}

		$platform = $this->container->platform;

		// If this is a CLI application we don't make any ACL checks
		if ($platform->isCli())
		{
			return (object) $permissions;
		}

		// Get the core permissions
		$permissions = array(
			'create'	 => $platform->authorise('core.create'    , $component),
			'edit'		 => $platform->authorise('core.edit'      , $component),
			'editown'	 => $platform->authorise('core.edit.own'  , $component),
			'editstate'	 => $platform->authorise('core.edit.state', $component),
			'delete'	 => $platform->authorise('core.delete'    , $component),
		);

		if (!empty($additionalPermissions))
		{
			foreach ($additionalPermissions as $localKey => $joomlaPermission)
			{
				$permissions[$localKey] = $platform->authorise($joomlaPermission, $component);
			}
		}

		return (object)$permissions;
	}

	/**
	 * Determines if the current Joomla! version and your current table support AJAX-powered drag and drop reordering.
	 * If they do, it will set up the drag & drop reordering feature.
	 *
	 * @return  boolean|array  False if not supported, otherwise a table with necessary information (saveOrder: should
	 * 						   you enable DnD reordering; orderingColumn: which column has the ordering information).
	 */
	public function hasAjaxOrderingSupport()
	{
		/** @var DataModel $model */
		$model = $this->getModel();

		if (!$model->hasField('ordering'))
		{
			return false;
		}

		$listOrder = $this->escape($model->getState('filter_order', null, 'cmd'));
		$listDirn = $this->escape($model->getState('filter_order_Dir', 'ASC', 'cmd'));
		$saveOrder = $listOrder == $model->getFieldAlias('ordering');

		if ($saveOrder)
		{
			$saveOrderingUrl = 'index.php?option=' . $this->container->componentName . '&view=' . $this->getName() . '&task=saveorder&format=json';
			\JHtml::_('sortablelist.sortable', 'itemsList', 'adminForm', strtolower($listDirn), $saveOrderingUrl);
		}

		return array(
			'saveOrder'		 => $saveOrder,
			'orderingColumn' => $model->getFieldAlias('ordering')
		);
	}

	/**
	 * Returns the internal list of useful variables to the benefit of header fields.
	 *
	 * @return array
	 */
	public function getLists()
	{
		return $this->lists;
	}

	/**
	 * Returns a reference to the permissions object of this view
	 *
	 * @return \stdClass
	 */
	public function getPerms()
	{
		return $this->permissions;
	}

	/**
	 * Returns a reference to the pagination object of this view
	 *
	 * @return \JPagination
	 */
	public function getPagination()
	{
		return $this->pagination;
	}

	/**
	 * Executes before rendering the page for the Browse task.
	 *
	 * @return  boolean  Return true to allow rendering of the page
	 */
	protected function onBeforeBrowse()
	{
		// Create the lists object
		$this->lists = new \stdClass();

		// Load the model
		/** @var DataModel $model */
		$model = $this->getModel();

		// We want to persist the state in the session
		$model->savestate(1);

		// Ordering information
		$this->lists->order = $model->getState('filter_order', $model->getIdFieldName(), 'cmd');
		$this->lists->order_Dir = $model->getState('filter_order_Dir', 'DESC', 'cmd');

		// Display limits
		$this->lists->limitStart = $model->getState('limitstart', 0, 'int');
		$this->lists->limit = $model->getState('limit', 0, 'int');

		// Assign items to the view
		$this->items = $model->get();
		$this->itemsCount = $model->count();

		// Pagination
		$this->pagination = new \JPagination($this->itemCount, $this->lists->limitStart, $this->lists->limit);

		// Pass page params on frontend only
		if ($this->container->platform->isFrontend())
		{
			/** @var \JApplicationSite $app */
			$app = \JFactory::getApplication();
			$params = $app->getParams();
			$this->pageParams = $params;
		}

		return true;
	}

	/**
	 * Executes before rendering the page for the add task.
	 *
	 * @return  boolean  Return true to allow rendering of the page
	 */
	protected function onBeforeAdd()
	{
		/** @var DataModel $model */
		$model = $this->getModel();

		$this->item = $model->reset(true, true);

		return true;
	}

	/**
	 * Executes before rendering the page for the Edit task.
	 *
	 * @return  boolean  Return true to allow rendering of the page
	 */
	protected function onBeforeEdit()
	{
		/** @var DataModel $model */
		$model = $this->getModel();

		// It seems that I can't edit records, maybe I can edit only this one due asset tracking?
		if (!$this->permissions->edit || !$this->permissions->editown)
		{
			if($model)
			{
				// Ok, record is tracked, let's see if I can this record
				if ($model->isAssetsTracked())
				{
					$platform = $this->container->platform;

					if (!$this->permissions->edit)
					{
						$this->permissions->edit = $platform->authorise('core.edit', $model->getAssetName());
					}

					if (!$this->permissions->editown)
					{
						$this->permissions->editown = $platform->authorise('core.edit.own', $model->getAssetName());
					}
				}
			}
		}

		$this->item = $model->findOrFail();

		return true;
	}

	/**
	 * Executes before rendering the page for the Read task.
	 *
	 * @return  boolean  Return true to allow rendering of the page
	 */
	protected function onBeforeRead()
	{
		/** @var DataModel $model */
		$model = $this->getModel();

		$this->item = $model->findOrFail();

		return true;
	}
} 
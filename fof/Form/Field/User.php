<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Form\Field;

use FOF30\Form\FieldInterface;
use FOF30\Form\Form;
use FOF30\Model\DataModel;

defined('_JEXEC') or die;

\JFormHelper::loadFieldClass('user');

/**
 * Form Field class for the FOF framework
 * A user selection box / display field
 */
class User extends \JFormFieldUser implements FieldInterface
{
	/**
	 * @var  string  Static field output
	 */
	protected $static;

	/**
	 * @var  string  Repeatable field output
	 */
	protected $repeatable;

	/**
	 * The Form object of the form attached to the form field.
	 *
	 * @var    Form
	 */
	protected $form;

	/**
	 * A monotonically increasing number, denoting the row number in a repeatable view
	 *
	 * @var  int
	 */
	public $rowid;

	/**
	 * The item being rendered in a repeatable form field
	 *
	 * @var  DataModel
	 */
	public $item;

	/**
	 * Method to get certain otherwise inaccessible properties from the form field object.
	 *
	 * @param   string  $name  The property name for which to the the value.
	 *
	 * @return  mixed  The property value or null.
	 *
	 * @since   2.0
	 */
	public function __get($name)
	{
		switch ($name)
		{
			case 'static':
				if (empty($this->static))
				{
					$this->static = $this->getStatic();
				}

				return $this->static;
				break;

			case 'repeatable':
				if (empty($this->repeatable))
				{
					$this->repeatable = $this->getRepeatable();
				}

				return $this->repeatable;
				break;

			default:
				return parent::__get($name);
		}
	}

	/**
	 * Get the rendering of this field type for static display, e.g. in a single
	 * item view (typically a "read" task).
	 *
	 * @since 2.0
	 *
	 * @return  string  The field HTML
	 */
	public function getStatic()
	{
		// Initialise
		$show_username = true;
		$show_email    = false;
		$show_name     = false;
		$show_id       = false;
		$class         = '';

		// Get the field parameters
		if ($this->element['class'])
		{
			$class = ' class="' . (string) $this->element['class'] . '"';
		}

		if ($this->element['show_username'] == 'false')
		{
			$show_username = false;
		}

		if ($this->element['show_email'] == 'true')
		{
			$show_email = true;
		}

		if ($this->element['show_name'] == 'true')
		{
			$show_name = true;
		}

		if ($this->element['show_id'] == 'true')
		{
			$show_id = true;
		}

		// Get the user record
		$user = $this->form->getContainer()->platform->getUser($this->value);

		// Render the HTML
		$html = '<div id="' . $this->id . '" ' . $class . '>';

		if ($show_username)
		{
			$html .= '<span class="fof-userfield-username">' . $user->username . '</span>';
		}

		if ($show_id)
		{
			$html .= '<span class="fof-userfield-id">' . $user->id . '</span>';
		}

		if ($show_name)
		{
			$html .= '<span class="fof-userfield-name">' . $user->name . '</span>';
		}

		if ($show_email)
		{
			$html .= '<span class="fof-userfield-email">' . $user->email . '</span>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get the rendering of this field type for a repeatable (grid) display,
	 * e.g. in a view listing many item (typically a "browse" task)
	 *
	 * @since 2.0
	 *
	 * @return  string  The field HTML
	 */
	public function getRepeatable()
	{
		// Initialise
		$show_username = true;
		$show_email    = true;
		$show_name     = true;
		$show_id       = true;
		$show_avatar   = true;
		$show_link     = false;
		$link_url      = null;
		$avatar_method = 'gravatar';
		$avatar_size   = 64;
		$class         = '';

		// Get the user record
		$user = $this->form->getContainer()->platform->getUser($this->value);

		// Get the field parameters
		if ($this->element['class'])
		{
			$class = ' class="' . (string) $this->element['class'] . '"';
		}

		if ($this->element['show_username'] == 'false')
		{
			$show_username = false;
		}

		if ($this->element['show_email'] == 'false')
		{
			$show_email = false;
		}

		if ($this->element['show_name'] == 'false')
		{
			$show_name = false;
		}

		if ($this->element['show_id'] == 'false')
		{
			$show_id = false;
		}

		if ($this->element['show_avatar'] == 'false')
		{
			$show_avatar = false;
		}

		if ($this->element['avatar_method'])
		{
			$avatar_method = strtolower($this->element['avatar_method']);
		}

		if ($this->element['avatar_size'])
		{
			$avatar_size = $this->element['avatar_size'];
		}

		if ($this->element['show_link'] == 'true')
		{
			$show_link = true;
		}

		if ($this->element['link_url'])
		{
			$link_url = $this->element['link_url'];
		}
		else
		{
			if ($this->form->getContainer()->platform->isBackend())
			{
				// If no link is defined in the back-end, assume the user edit
				// link in the User Manager component
				$link_url = 'index.php?option=com_users&task=user.edit&id=[USER:ID]';
			}
			else
			{
				// If no link is defined in the front-end, we can't create a
				// default link. Therefore, show no link.
				$show_link = false;
			}
		}

		// Post-process the link URL
		if ($show_link)
		{
			$replacements = array(
				'[USER:ID]'			 => $user->id,
				'[USER:USERNAME]'	 => $user->username,
				'[USER:EMAIL]'		 => $user->email,
				'[USER:NAME]'		 => $user->name,
			);

			foreach ($replacements as $key => $value)
			{
				$link_url = str_replace($key, $value, $link_url);
			}
		}

		// Get the avatar image, if necessary
		$avatar_url = '';

		if ($show_avatar)
		{
			if ($avatar_method == 'plugin')
			{
				// Use the user plugins to get an avatar
				$this->form->getContainer()->platform->importPlugin('user');
				$jResponse = $this->form->getContainer()->platform->runPlugins('onUserAvatar', array($user, $avatar_size));

				if (!empty($jResponse))
				{
					foreach ($jResponse as $response)
					{
						if ($response)
						{
							$avatar_url = $response;
						}
					}
				}

				if (empty($avatar_url))
				{
					$show_avatar = false;
				}
			}
			else
			{
				// Fall back to the Gravatar method
				$md5 = md5($user->email);

				if ($this->form->getContainer()->platform->isCli())
				{
					$scheme = 'http';
				}
				else
				{
					$scheme = \JUri::getInstance()->getScheme();
				}

				if ($scheme == 'http')
				{
					$avatar_url = 'http://www.gravatar.com/avatar/' . $md5 . '.jpg?s='
						. $avatar_size . '&d=mm';
				}
				else
				{
					$avatar_url = 'https://secure.gravatar.com/avatar/' . $md5 . '.jpg?s='
						. $avatar_size . '&d=mm';
				}
			}
		}

		// Generate the HTML
		$html = '<div id="' . $this->id . '" ' . $class . '>';

		if ($show_avatar)
		{
			$html .= '<img src="' . $avatar_url . '" align="left" class="fof-usersfield-avatar" />';
		}

		if ($show_link)
		{
			$html .= '<a href="' . $link_url . '">';
		}

		if ($show_username)
		{
			$html .= '<span class="fof-usersfield-username">' . $user->username
				. '</span>';
		}

		if ($show_id)
		{
			$html .= '<span class="fof-usersfield-id">' . $user->id
				. '</span>';
		}

		if ($show_name)
		{
			$html .= '<span class="fof-usersfield-name">' . $user->name
				. '</span>';
		}

		if ($show_email)
		{
			$html .= '<span class="fof-usersfield-email">' . $user->email
				. '</span>';
		}

		if ($show_link)
		{
			$html .= '</a>';
		}

		$html .= '</div>';

		return $html;
	}
}

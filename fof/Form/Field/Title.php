<?php
/**
 * @package     FOF
 * @copyright   2010-2015 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license     GNU GPL version 2 or later
 */

namespace FOF30\Form\Field;

use FOF30\Form\FieldInterface;
use FOF30\Model\DataModel;
use \JText;

defined('_JEXEC') or die;

\JFormHelper::loadFieldClass('text');

/**
 * Form Field class for the FOF framework
 * Supports a title field with an optional slug display below it.
 */
class Title extends Text implements FieldInterface
{
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
		$slug_field		= 'slug';
		$slug_format	= '(%s)';
		$slug_class		= 'small';

		// Get field parameters
		if ($this->element['slug_field'])
		{
			$slug_field = (string) $this->element['slug_field'];
		}

		if ($this->element['slug_format'])
		{
			$slug_format = (string) $this->element['slug_format'];
		}

		if ($this->element['slug_class'])
		{
			$slug_class = (string) $this->element['slug_class'];
		}

		// Get the regular display
		$html = parent::getRepeatable();

		$slug = $this->item->$slug_field;

		$html .= '<br />' . '<span class="' . $slug_class . '">';
		$html .= JText::sprintf($slug_format, $slug);
		$html .= '</span>';

		return $html;
	}
}

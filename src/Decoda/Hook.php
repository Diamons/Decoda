<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/decoda
 */

namespace Decoda;

/**
 * Defines the methods for all Hooks to implement.
 */
interface Hook extends Component {

	/**
	 * Process the content after the parsing has finished.
	 *
	 * @param string $content
	 * @return string
	 */
	public function afterParse($content);

	/**
	 * Process the content after the stripping has finished.
	 *
	 * @param string $content
	 * @return string
	 */
	public function afterStrip($content);

	/**
	 * Process the content before the parsing begins.
	 *
	 * @param string $content
	 * @return string
	 */
	public function beforeParse($content);

	/**
	 * Process the content before the stripping begins.
	 *
	 * @param string $content
	 * @return string
	 */
	public function beforeStrip($content);

	/**
	 * Start up the Hook by initializing or loading any data before parsing begins.
	 *
	 * @return void
	 */
	public function startup();

	/**
	 * Add any filter dependencies.
	 *
	 * @param \Decoda\Decoda $decoda
	 * @return \Decoda\Hook
	 */
	public function setupFilters(Decoda $decoda);

}
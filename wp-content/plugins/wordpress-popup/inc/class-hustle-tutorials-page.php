<?php
/**
 * File for Hustle_Admin_Page_Abstract class.
 *
 * @package Hustle
 * @since 4.4.6
 */

/**
 * Class Hustle_Tutorials_Page
 *
 * @since 4.4.6
 */
class Hustle_Tutorials_Page extends Hustle_Admin_Page_Abstract {

	/**
	 * Initiates the page's properties
	 *
	 * @since 4.4.6
	 */
	public function init() {

		$this->page = 'hustle_tutorials';

		$this->page_title = __( 'Tutorials', 'hustle' );

		$this->page_menu_title = __( 'Tutorials', 'hustle' );

		$this->page_capability = 'hustle_menu';

		$this->page_template_path = 'admin/tutorials';
	}

	/**
	 * Get the arguments used when rendering the main page.
	 *
	 * @since 4.4.6
	 * @return array
	 */
	public function get_page_template_args() {
		return array();
	}

}

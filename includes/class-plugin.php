<?php
/**
 * Plugin definitions setup and general hooks calls.
 *
 * @package WordPress
 * @subpackage Private Media
 */

namespace PrivateMedia;

/**
 * Class to load hooks and set/get base plugin definitions.
 *
 * @package    WordPress
 * @subpackage Evans
 * @author     Old Town Media
 */
class Plugin {

	/**
	 * Plugin definitions.
	 *
	 * @var object
	 */
	public $definitions;

	/**
	 * Retrieve the plugin definitions from the main plugin directory.
	 *
	 * @return object Defitions
	 */
	public function get_definitions() {
		return $this->definitions;
	}

	/**
	 * Set the plugin definitions.
	 *
	 * @param  object $definitions Information about the plugin
	 * @return object $this
	 */
	public function set_definitions( $definitions ) {

		$this->definitions = $definitions;
		return $this;

	}

	/**
	 * Register hook function.
	 *
	 * @param  object $provider Hook provider.
	 * @return object $this
	 */
	public function register_hooks( $provider ) {

		if ( method_exists( $provider, 'set_plugin' ) ) {
			$provider->set_plugin( $this );
		}

		$provider->hooks();
		return $this;

	}

	/**
	 * Load the textdomain for this plugin if translation is available
	 *
	 * @see load_plugin_textdomain
	 */
	public function load_textdomain() {
	    load_plugin_textdomain( 'private-media', false, basename( dirname( $this->definitions->file ) ) . '/languages/' );
	}
}

<?php
/**
 * Plugin Name: Private Media
 * Plugin URI:
 * Description: Make items in the Media Library private.
 * Version: 1.0
 * Author: Mike Selander (originally Matthew Haines-Young)
 * Author URI: https://mikeselander.com
 * Text Domain: private-media
 * License: GPL3+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace PrivateMedia;

/**
 * Autoloader callback.
 *
 * Converts a class name to a file path and requires it if it exists.
 *
 * @param string $class Class name.
 */
function private_media_autoloader( $class ) {
	$namespace = explode( '\\', $class );

 	if ( __NAMESPACE__ !== $namespace[0] ){
 		return;
 	}

    $class = str_replace( __NAMESPACE__ . '\\', '', $class );

	$nss = array(
		'Views'
	);

	if ( in_array( $namespace[1], $nss ) ){
        $class = strtolower( preg_replace( '/(?<!^)([A-Z])/', '/\1', $class ) );
        $class = str_replace( '\\', '', $class );
     	$file  = dirname( __FILE__ ) . '/' . $class . '.php';
    } else {
        $class = strtolower( preg_replace( '/(?<!^)([A-Z])/', '-\\1', $class ) );
     	$file  = dirname( __FILE__ ) . '/includes/class-' . $class . '.php';
    }

 	if ( is_readable( $file ) ) {
 		require_once( $file );
 	}
 }
 spl_autoload_register( __NAMESPACE__ . '\private_media_autoloader' );



 /**
  * Retrieve the plugin instance.
  *
  * @return object Plugin
  */
 function plugin() {
 	static $instance;

 	if ( null === $instance ) {
 		$instance = new Plugin();
 	}

 	return $instance;
 }

 // Set our definitions for later use
  plugin()->set_definitions(
 	(object) array(
 		'basename'	=> plugin_basename( __FILE__ ),
 		'directory'	=> plugin_dir_path( __FILE__ ),
 		'file'		=> __FILE__,
 		'slug' 		=> 'private-media',
 		'url'		=> plugin_dir_url( __FILE__ )
 	)
 );

 // Register hook providers.
 plugin()->register_hooks( new Rewrites() )
 		 ->register_hooks( new Settings() );

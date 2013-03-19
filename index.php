<?php
/**
 * @package autoflow
 */
/*
  Plugin Name: AutoFlow
  Plugin URI: https://github.com/david-coombes/wp-plugin-framework
  Description: Framework for writing wordpress plugins
  Version: 0.1
  Author: Daithi Coombes
  Author URI: http://david-coombes.com
 */
//autoloader
$autoloader = create_function('$class', '
	$PLUGIN_DIR =  WP_PLUGIN_DIR . "/" . basename(dirname( __FILE__ ));
	$PLUGIN_URL =  WP_PLUGIN_URL . "/" . basename(dirname( __FILE__ ));
	$class = ucfirst($class);
	@include_once( $PLUGIN_DIR . "/application/{$class}.class.php");
	@include_once( $PLUGIN_DIR . "/application/modules/{$class}.class.php");
');
spl_autoload_register($autoloader,true);

add_action('init', function(){
	
	//define plugin constants
	$PLUGIN_DIR =  WP_PLUGIN_DIR . "/" . basename(dirname( __FILE__ ));
	$PLUGIN_URL =  WP_PLUGIN_URL . "/" . basename(dirname( __FILE__ ));

	//load framework core
	require_once( $PLUGIN_DIR . "/application/Config.class.php");
	require_once( $PLUGIN_DIR . "/application/Controller.class.php");

	//load config
	if(class_exists("WPPluginFrameWorkConfig"))
		$config = new WPPluginFrameWorkConfig();

	//setup plugin config
	$config->namespace = "wp-plugin-framework";
	$config->action_key = "{$config->namespace}-action";
	$config->debug = true;
	$config->init_modules = array(
		//'AutoFlow_API',
		'AutoFlow_Privacy'
	);
	$config->plugin_dir = $PLUGIN_DIR;
	$config->plugin_url = $PLUGIN_URL;
	$config->build();
});
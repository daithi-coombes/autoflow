<?php

if(!class_exists("WPPluginFrameWorkController")):
	/**
	 * Plugin modules that want to make use of the framework methods should
	 * extend this class.
	 * 
	 * The purpose of this framework is allow the easy creation of plugins for
	 * wordpress by simply dropping in a class and modulising your project. The 
	 * index.php file acts as a bootstrap using the
	 * @see WPPluginFrameWorkConfig class
	 * 
	 * By modulising your plugin, then creating a module class file and
	 * extending @see WPPluginFrameWorkConfig class it will be easier to access 
	 * front and admin functionality. Using a nameing scheme and giving view 
	 * files, javascript and css files the same name they are automatically 
	 * hooked up to your class.
	 * 
	 * By keeping the framework as a wrapper around your plugin this allows your
	 * code to be in full control only use what you need from the framework.
	 * 
	 * -----------
	 * JavaScripts
	 * -----------
	 * The class will automatically try to load:
	 * public_html/js/${module}.js
	 * by default. If this is not there then it will try to load:
	 * public_html/js/${module}.min.js
	 * 
	 * 3rd party scrips are registered using the config class in the index.php
	 * file to allow registering of 3rd party scripts plugin wide. To register a
	 * 3rd party script use:
	 * $config->third_party['scripts'] = array( handle=>src )
	 * 
	 * Dependencies can be loaded by the child class in the __construct like so:
	 * $this->scripts = array( 'jquery' );
	 * 
	 * @see WPPluginFrameWorkController::load_scripts()
	 * @see WPPluginFrameWorkConfig::register_3rd_parties()
	 * ----------------------------------------------------
	 * 
	 * @author daithi
	 * @package cityindex
	 * @subpackage ci-wp-login
	*/
	class WPPluginFrameWorkController{

		/** @var string Holds actions from $_REQUEST[$action_key] for calling method
		* in child class.
		*/
		public $action = false;
		/** @var array The configuration struct defined in plugin index.php file */
		public $config = array();
		/** @var array Array of js dependencies called in child class */
		public $script_deps = array();
		/** @var array Associative array of shortcode=>value pairs */
		public $shortcodes = array();
		/** @var array Array of css dependencies called in child class */
		public $style_deps = array();
		/** @var array An associative array of wordpress actions and callbacks */
		public $wp_action = array();
		/** @var array An associative array of wordpress filters and callbacks */
		public $wp_filter = array();
		/** @var string lowercase child class name */
		private $class = false;
		/** @var string Camel case child class name */
		private $class_name = false;
		/** @var string The html code for the view file */
		private $html = false;

		/**
		* Construct controller.
		*
		* @param string $class The class name being extended
		* @param string $plugin_dir The plugin dir
		* @param string $plugin_url The plugin url
		*/
		function __construct( $class=false ){
			
			if($class==false) return;

			//set default fields
			global $config;

			$this->class = $class;
			$this->class_name = ucfirst( @array_pop(explode("\\", $class)) );
			$this->config = $config;
			(@$_REQUEST[$this->config->action_key])
				? $this->action = $_REQUEST[$this->config->action_key]
				: $this->action = false;

			//default methods
			$this->do_action();
			$this->set_wp_actions();
			$this->set_wp_filters();
		}

		/**
		* Activation callback.
		* 
		* Installs tables 
		*/
		public function activate(){

			require_once( ABSPATH . '/wp-admin/includes/upgrade.php');

			/**
			*CREATE TABLE IF NOT EXISTS `wp_CityIndex\WP\Login_WP_Groups_Group` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`name` varchar(20) COLLATE utf8_bin NOT NULL,
	`description` text COLLATE utf8_bin NOT NULL,
	PRIMARY KEY (`id`)
	) 
			*/

			foreach($this->modal_tables as $table=>$fields){
				$sql = "
					CREATE TABLE IF NOT EXISTS `{$this->modal_prefix}_{$table}`("
					. implode(",", $fields)
					. ");";
				dbDelta($sql);
			}
		}

		/**
		* Creates nonces for the view file.
		* 
		* @see Controller::set_shortcodes()
		* @return string
		*/
		public function create_nonce( $arr ){
			return wp_create_nonce($arr[0]);
		}

		/**
		* Log an error.
		* 
		* @param string $err The error message.
		* @return boolean false Method always returns false.
		*/
		public function error( $err ){
			$this->config->errors[] = $err;
			return false;
		}

		/**
		* Checks $_REQUEST[_wpnonce] against the param $action.
		* 
		* Will die() if verification fails.
		*  
		* @see wp_verify_nonce()
		* @param string $action The action word used setting up the nonce.
		* @param boolean $json Default True. Whether to return json error or plain
		* text.
		*/
		public function check_nonce($action, $json=false){
			if(!wp_verify_nonce($_REQUEST['_wpnonce'], $action)){
				if($json){
					print json_encode(array('error' => 'invalid nonce'));
					die();
				}
				else{
					$this->error("Invalid Nonce");
					return false;
				}
			}
			return true;
		}

		/**
		* Looks for an action method to call in child class. 
		*/
		public function do_action(){
			$action = $this->action;
			if(method_exists($this, $action))
				add_action('init', array($this, $action));
		}

		/**
		* Prints the view html.
		* 
		* Loads the html then sets shortcodes,loads scripts and styles then prints 
		* html.
		* 
		* @return void
		*/
		public function get_page() {
			
			//vars
			$this->html = file_get_contents("{$this->config->plugin_dir}/public_html/{$this->class_name}.php");

			//clean out phpDoc
			$this->html = preg_replace("/<\?php.+\?>/msU", "", $this->html);

			$this->shortcodes['errors'] = $this->get_errors();
			$this->shortcodes['messages'] = $this->get_messages();

			$this->set_shortcodes();			
			$this->load_scripts();
			$this->load_styles();

			print $this->html;
		}

		/**
		 * Add message to config messages array.
		 * 
		 * @param string $msg The message to add.
		 * @return true Always returns true. 
		 */
		public function message($msg){
			$this->config->messages[] = $msg;
			return true;
		}
		
		/**
		* Return html list of errors reported by plugin globally.
		* 
		* @return string Returns an html <ul> list of errors.
		*/
		private function get_errors(){
			
			$html = "<div id=\"message\" class=\"error\"><ul>\n";
			$errors = $this->config->errors;
			
			if(@$_REQUEST['error'])
				$errors[] = $_REQUEST['error'];

			if(!count($errors)) return false;
			foreach($errors as $err)
				$html .= stripslashes ("<li>{$err}</li>\n");

			wp_enqueue_style('colors');
			return $html .= "</ul>\n</div>\n";
		}

		/**
		 * Return html list of messages reported by plugin globally.
		 * 
		 * @return string Returns an html ul list of messages. 
		 */
		private function get_messages(){

			$html = "<div id=\"message\" class=\"message updated\"><ul>\n";
			$messages = $this->config->messages;

			if(@$_REQUEST['message'])
				$messages[] = $_REQUEST['message'];

			if(!count($messages)) return false;
			foreach($messages as $msg)
				$html .= stripslashes ("<li>{$msg}</li>\n");

			wp_enqueue_style('colors');
			return $html .= "</ul>\n</div>\n";
		}

		/**
		* Registers action calls with wp core.
		* 
		* Loops through $this->action_wp array, takes the key as the action and the
		* value as the callback.
		* 
		* @return void
		*/
		private function set_wp_actions(){

			if(!count($this->wp_action)) return;
			foreach($this->wp_action as $action=>$call){
				add_action($action, $call);
			}
		}

		/**
		* Registers action calls with wp core.
		* 
		* Loops through $this->action_wp array, takes the key as the action and the
		* value as the callback.
		* 
		* @return void
		*/
		private function set_wp_filters(){

			if(!count($this->wp_filter)) return;
			foreach($this->wp_filter as $action=>$call){
				add_action($action, $call);
			}
		}

		/**
		 * Loads javascript files.
		 * 
		 * Will look for {$module}.js in public/js folder by default. If not
		 * found will look for the {$module}.min.js in the same folder.
		 * 
		 * @return void 
		*/
		private function load_scripts() {
			
			//vars
			$js_orig = "public_html/js/{$this->class_name}.js";
			$js_min = "public_html/js/{$this->class_name}.min.js";
			$js = false;
			
			//check if js file found (priority to $js_orig)
			if(@file_exists("{$this->config->plugin_dir}/{$js_orig}")) 
				$js=$js_orig;
			elseif(@file_exists("{$this->config->plugin_dir}/{$js_min}"))
				$js=$js_min;
			
			//if js file found
			if($js){
				wp_register_script($this->class_name, "{$this->config->plugin_url}/{$js}", $this->script_deps);
				wp_enqueue_script( $this->class_name );
			}
		}

		/**
		* Loads css files
		* 
		* @return void 
		*/
		public function load_styles() {

			//include dependencies
			foreach($this->style_deps as $dep)
				wp_enqueue_style ( $dep );

			//look for class specific style
			if(!file_exists("{$this->config->plugin_dir}/public_html/css/{$this->class_name}.css")) return;

			wp_register_style($this->class_name, "{$this->config->plugin_url}/public_html/css/{$this->class_name}.css", $this->style_deps);
			wp_enqueue_style( $this->class_name );
		}

		/**
		* Sets values for the shortcodes in the view file.
		* 
		* Replaces the codes with values in $this->html. To add shortcodes to the 
		* view file use the syntax:
		* <code> <!--[--identifying string--]--> </code>. 
		* In $this->get_page() add the value to the array $this->shortcodes[].
		* eg: $this->shortcodes['identifying string'] = "value".
		* 
		* @return void
		*/
		private function set_shortcodes() {

			$this->shortcodes['errors'] = $this->get_errors();
			$this->shortcodes['messages'] = $this->get_messages();
			
			//replace values
			if(count(@$this->shortcodes))
				foreach ($this->shortcodes as $code => $val){

					//if val is method call
					if(is_array($val)){
						$method = $val[1];
						if(method_exists($this, $method))
							$val = $this->$method( $val );
						else $val = "unkown shortcode <em>{$code}</em>";
					}
					$this->html = str_replace("<!--[--{$code}--]-->", $val, $this->html);
				}
		}

	}
endif;
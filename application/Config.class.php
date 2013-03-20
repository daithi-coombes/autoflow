<?php

if(!class_exists("WPPluginFrameWorkConfig")):
	/**
	* Class description 
	*/
	class WPPluginFrameWorkConfig{

		/** @var string The key name for looking for action requests */
		public $action_key = false;
		/** @var string Name of activation function declared in plugin index */
		public $activation_function = false;
		/** @var string Name of the deactivation function declared in plugin index */
		public $deactivation_function = false;
		/** @var boolean Print debug messages to stdout */
		public $debug = false;
		/** @var array An array of errors. Used in the global $controller object */
		public $errors = array();
		/** @var array An array of modules to be loaded always */
		public $init_modules = array();
		/** @var array An array of messages. Used in the $controller object */
		public $messages = array();
		/** @var array Associative array of tables to be created in plugin
		* activation. */
		public $modal_tables = array();
		/** @var string The plugin prefix to append to db tables */
		public $modal_prefix = false;
		/** @var string The plugin namespace */
		public $namespace = "WPPluginFrameWork";
		/** @var array An array to be stored as wp_options */
		public $options = array();
		/** @var array Associative array of 3rd party scripts to register */
		public $third_party = array('scripts', 'styles');
		/** @var string The directory of the plugin base */
		public $plugin_dir = false;
		/** @var string The full url of the plugin base */
		public $plugin_url = false;

		/**
		* constructor.
		*/
		function __construct(){
			;
		}

		/**
		* Build plugin configuration.
		*  - registers activation hook
		*  - registers 3rd party scripts & styles
		* 
		* @global wpdb $wpdb The wordpress database class.
		* @return void 
		*/
		public function build(){
			
			global $wpdb;

			//set params
			if($this->debug) $this->set_debug();
			$this->modal_prefix = $wpdb->prefix . $this->namespace;

			//register activation hooks
			//register_activation_hook( "{$this->plugin_dir}/index.php", array(&$this, 'activate'));
			register_deactivation_hook("{$this->plugin_dir}/index.php", $this->deactivation_function);
			
			//register 3rd parties
			$this->register_3rd_parties();

			//set options
			$this->get_options();

			//load modules
			$this->load_modules();
		}

		/**
		* Activation callback.
		* 
		* Installs tables 
		*/
		public function activate(){

			require_once( ABSPATH . '/wp-admin/includes/upgrade.php');
			
			//see if activation code is set
			if($this->activation_function){
				$func = $this->activation_function;
				$func();
			}

			//create tables
			foreach($this->modal_tables as $table => $fields){
				$sql = "
					CREATE TABLE IF NOT EXISTS `{$this->modal_prefix}_{$table}`("
					. implode(",", $fields)
					. ");";
				dbDelta($sql);
			}
		}

		/**
		* Add an error to the errors array.
		*
		* @see Config::errors
		* @param string $err The error string to report
		* @return false
		*/
		public function error( $err ){
			$this->errors[] = $err;
			return false;
		}

		/**
		* Get the site options for this plugin.
		*
		* @return object->array 
		*/
		public function get_options(){
			$option = str_replace("\\", "_", __NAMESPACE__) . "_options";
			return $this->options = get_site_option($option);
		}

		/**
		* Add message to the messages array
		* 
		* @see Config::messages
		* @param string $msg The message to add
		* @return true
		*/
		public function message( $msg ){
			$this->messages[] = $msg;
			return true;
		}

		/**
		* Sets php error reporting to E_ALL and php ini display_errors to on.
		* 
		* @return void 
		*/
		public function set_debug(){
			
			//debug on
			if($this->debug){
				require_once( $this->plugin_dir . "/application/includes/debug.func.php");
				error_reporting(E_ALL ^ E_STRICT);
				ini_set('display_errors', 'on');
			}
		}

		/**
		* Set a plugin option.
		* 
		* @param string $key The option key
		* @param string $val The option value
		*/
		public function set_option($key, $val){
			$this->options[$key] = $val;
			$this->set_options();
		}

		/**
		* Construct default modules needed for plugin init().
		* 
		* Reads ClassNames from $this->init_modules() array and creates a global
		* variable with the same name as the class name.
		* 
		* @see Config::init_modules
		* @return void
		*/
		private function load_modules(){

			if(!@count($this->init_modules)) return;
			
			foreach($this->init_modules as $module){
				global ${$module};
				${$module} = new $module();
			}
		}

		/**
		* Registers third parties.
		* 
		* @return void
		*/
		private function register_3rd_parties(){
			
			//register scripts
			if(count(@$this->third_party['script']))
				foreach($this->third_party['script'] as $handle => $src)
					wp_register_script($handle, "{$this->plugin_url}/application/includes/{$src}");

			//register styles
			if(count(@$this->third_party['css']))
				foreach($this->third_party['css'] as $handle => $src)
				wp_register_style($handle, "{$this->plugin_url}/application/includes/{$src}");
		}

		/**
		* Sets the options for this plugin.
		* 
		* @uses update_option()
		* @return void
		*/
		private function set_options(){
			$option = str_replace("\\", "_", __NAMESPACE__) . "_options";
			update_site_option($option, $this->options);
		}
	}
endif;
?>

<?php

//include api-connection-manager class
require_once( WP_PLUGIN_DIR . "/api-connection-manager/class-api-connection-manager.php");

class AutoFlow_API extends WPPluginFrameWorkController{

	/** @var string The role assigned to new users */
	public $new_user_role = "subscriber";
	/** @var API_Connection_Manager The api connection manager */
	private $api;
	/** @var string The prefix to use for meta and option keys */
	private $option_name = "AutoFlow";

	/**
	 * Construct.
	 * 
	 * @global API_Connection_Manager $API_Connection_Manager 
	 */
	function __construct(){
		
		global $API_Connection_Manager;
		$this->api = $API_Connection_Manager;
		$action = @$_REQUEST['action'];
		
		if($action)
			if(method_exists($this, $action))
				$this->$action();
		
		$this->shortcodes = array(
			'list services' => array(&$this, 'list_services')
		);
		
		//add settings page
		add_action('wp_ajax_nopriv_autoflow_api', array(&$this, 'email_form_callback'));
		add_action('admin_menu', array(&$this, 'get_menu'));
		
		/**
		 * add login form hook
		 * login form error messages hook
		 * add shortcode for custom theme forms
		 */
		add_action( 'login_footer', array( &$this, 'print_login_buttons' ) );
		//add_filter( 'login_message', array(&$this, 'print_login_errors' ) );
		add_shortcode( 'AutoFlow', array( &$this, 'print_login_buttons' ) );
		
		parent::__construct( get_class($this));
	}
	
	/**
	 * Create Account
	 * If user is not logged in and there is no matching uid then create
	 * a new account for this social network. If there is no email
	 * returned from the service then show form to grab email
	 * @see AutoFlow_API::parse_dto()
	 * @link http://tommcfarlin.com/create-a-user-in-wordpress/
	 */
	public function create_account($email_address, $username, $slug, $uid){
		
		$username = preg_replace("/[^a-zA-Z0-9\s\.-]+/", "", $username);
		$username = preg_replace("/[\s\.-]+/", "_", $username); //str_replace(" ", "_", $username);
		$password = wp_generate_password( 12, false );
		
		// Generate the password and create the user
		$user_id = wp_create_user( $username, $password, $email_address );

		// Set the nickname
		$user_data = wp_update_user(array(
			'ID' => $user_id,
			'nickname' => $username
		));

		// Set the role
		$user = new WP_User( $user_id );
		$user->set_role( $this->new_user_role ); //'contributor' );

		// Email the user
		wp_mail( $email_address, 'Welcome!', 'Your Password: ' . $password );
		
		//print head
		?><html><head><?php
		wp_enqueue_style('media');
		wp_enqueue_style('colors');
		@wp_head();
		?></head><?php
		
		//iframe body
		?><body id="media-upload" class="js"><?php
		
		//if not userdata
		if(!$user_data)
			print "<h2>Error creating account</h2>";
		//success
		else{
			print "<h2>Your account has been created successfully</h2>";
			print "<h4>Details, please store these safely:
				<ul>
					<li>Username: {$username}</li>
					<li>Password: {$password}</li>
					<li>Email: {$email_address}</li>
				</ul>
				<a href=\"" . wp_login_url() . "\" title=\"Login\">Login</a>";

			/**
			* Set service uid to newly created user for future logins.
			*/
			$connections = get_option($this->option_name, array());
			$connections[$slug][$user_id] = $uid;
			update_option($this->option_name, $connections);
			//end set service uid
		}
		//footer and die()
		@wp_footer();
		?></body></html><?php
		die();
	}
	
	/**
	 * Disconnect a user from a service 
	 */
	public function disconnect(){
		$user_id = $this->api->get_current_user()->ID;
		$meta = get_option($this->option_name, array());
		unset($meta[$_REQUEST['slug']][$user_id]);
		update_option($this->option_name, $meta);
	}
	
	/**
	 * Process form requesting email address and then create new account.
	 */
	public function email_form_callback(){
		
		//check nonce
		if(!wp_verify_nonce($_REQUEST['wp_nonce'],"autoflow_get_email"))
			die("invalid nonce");
		//check email
		if($_REQUEST['email']!=$_REQUEST['email2'])
			die("Emails don't match");
		
		//create account and die
		$this->create_account($_REQUEST['email'], $_REQUEST['username'], $_REQUEST['slug'], $_REQUEST['uid']);
		die();
	}
	
	/**
	 * Build the admin menu 
	 */
	public function get_menu(){
		add_menu_page("AutoFlow", "AutoFlow", "manage_options", "autoflow", array(&$this, 'get_page'));
	}
	
	/**
	 * Callback for displaying the html in the dashboard settings page
	 * @global API_Connection_Manager $API_Connection_Manager
	 * @global type $current_user
	 * @return type 
	 */
	public function list_services(){
		
		global $API_Connection_Manager;
		global $current_user;
		
		$count=1;
		$html = "<div id=\"dashboard-widgets\" class=\"metabox-holder columns-1\">\n";
		$meta = get_option($this->option_name, array());
		$modules = $API_Connection_Manager->get_services();
		
		foreach($modules as $slug=>$module){
			
			/**
			 * get status icon and params
			 */
			if(@$meta[$slug][$current_user->ID]){
				$valid = true;
				$status = "status_icon_green_12x12.png";
			}
			else{
				$valid = false;
				$status = "status_icon_red_12x12.png";
			}
			//end get status icona  and params
			$html .= "<div id=\"postbox-container-{$count}\" class=\"postbox-container\">
					<div class=\"postbox\">
						<h3>
							<img src=\"".WP_PLUGIN_URL."/api-connection-manager/images/{$status}\" width=\"12\" height=\"12\"/>
							{$module->Name}</h3>
						<div class=\"inside\">";
							
			//print delete access tokens / show login link
			if($valid)
				$html .= "
					<form method=\"post\">
						<input type=\"hidden\" name=\"action\" value=\"disconnect\"/>
						<input type=\"hidden\" name=\"slug\" value=\"{$slug}\"/>
						<input type=\"submit\" value=\"Disconnect\"/>
					</form>";
			else
				$html .= "<p>You are not connected to {$module->Name}</p>
					<p><a href=\"" . $module->get_login_button(__FILE__, array(&$this, 'parse_dto', false)) . "\" target=\"_new\">
						Connect your wordpress account with {$module->Name}</a>";
					
			//close container
			$html .= "	</div>
					</div>
				</div>";
			$count++;
		}
		
		return $html .= "</ul>\n";
	}
	
	/**
	 * Prints the login buttons.
	 * 
	 * Callback for login_footer hook
	 */
	public function print_login_buttons(){
		
		//vars
		$services = $this->api->get_services();
		$res = "<ul>\n";
		
		//build list of buttons
		foreach($services as $slug => $service){
			if(is_object($service))
				$res .= "<li><a href=\"" . $service->get_login_button( __FILE__, array(&$this, 'parse_dto') ) . "\">Login with {$service->Name}</a></li>\n";
			else
				continue;
		}
		
		//print result
		print "{$res}\n</ul>\n";
	}
	
	/**
	 * Callback that will redirect user.
	 * 
	 * The dto passed by API_Connection_Manager will be in the format:
	 * $dto stdClass
	 *     ->response (array)
	 *         [code] (string)
	 *         [state] (string)
	 *     ->slug (string)
	 *     ->user (integer|NULL)
	 *     ->token (string)
	 * 
	 * @param stdClass $dto The response dto.
	 */
	public function parse_dto( stdClass $dto ){
		
		global $API_Connection_Manager;
		$module = $API_Connection_Manager->get_service($dto->slug);
		
		//make request for email
		switch ($dto->slug) {
			
			case 'dropbox/index.php':
				
				$module->set_params($dto->response);
				$res = $module->request(
						"https://api.dropbox.com/1/account/info",
						"get"
				);
				
				$body = json_decode($res['body']);
				$uid = $body->uid;
				$username = $body->display_name;
				$emails = false;
				break;
			
			/**
			 * Github 
			 */
			case 'github/index.php':

				$res = $module->request(
					"https://api.github.com/user/emails?access_token={$dto->response['access_token']}&scope=user,public_repo",
					"get"
				);
				
				$emails = (array) json_decode($res['body']);
				break;
			//end Github

			/**
			 * Google 
			 */
			case 'google/index.php':
				
				$res = $module->request(
						"https://www.googleapis.com/oauth2/v1/userinfo?access_token={$dto->response['access_token']}",
						"GET"
						);
				$emails = array( json_decode($res['body'])->email );
				break;
			//end Google
			
			/**
			 * Facebook 
			 */
			case 'facebook/index.php':
				$res = $module->request(
					"https://graph.facebook.com/me?access_token={$dto->response['access_token']}",
					'get'
				);
				
				$body = json_decode($res['body']);
				$uid = $body->id;
				$emails = (array) $body->email;
				$username = $body->username;
				break;
			//end Facebook
			
			/**
			 * Twitter 
			 */
			case 'twitter/index.php':
				
				$module->set_params($dto->response);
				$res = $module->request("https://api.twitter.com/1.1/account/verify_credentials.json", "GET");
				$body = $module->parse_response($res);
				$uid = $body->id;
				$username = $body->screen_name;
				$emails = false;
				break;
			
			/**
			 * Default die( error ) 
			 */
			default:
				die("<b>AutoFlow API</b>: Unkown service <em>{$dto->slug}</em><br/>
					Please add code to get account emails in <em>AutoFlow_API::parse_dto()</em>");
				break;
		}//end switch slug
		

		//vars
		if(@$API_Connection_Manager->get_current_user()->id)
			$user_id = $API_Connection_Manager->get_current_user()->id;
		else
			$user_id = false;
		$connections = get_option($this->option_name, array());
		
		/**
		 * Update meta.
		 * If user logged in then request must be from autoflow dashboard so
		 * get uid from service and save to meta
		 */
		if($user_id){
			$connections[$dto->slug][$user_id] = $uid;
			update_option($this->option_name, $connections);
		}
		//end update meta
		
		else{
			$data = @$connections[$dto->slug];
			
			/**
			 * Login
			 * Check if service uid is matched to user, if it is then login user
			 * and redirect to wp-admin (dashboard)
			 */
			if(count($data))
				foreach($data as $user_id => $service_id)
					if($uid==$service_id){

						//get user
						$user = get_userdata( $user_id );
						if(!$user || (!get_class($user)=="WP_User"))
							continue;

						//login
						wp_set_current_user( $user->data->ID );
						wp_set_auth_cookie( $user->data->ID );
						do_action('wp_login', $user->data->user_login, $user);

						//update module and redirect
						$module->user = $user;
						$module->set_params($dto->response);
						wp_redirect(admin_url());
						exit();
					}
			//end Login
			
			/**
			 * Create new account 
			 */
			//if emails provided
			if(count($emails) && is_array($emails)){
				$this->create_account($emails[0], $username, $dto->slug, $uid);
			}
			//else print email form
			else{
				$nonce = wp_create_nonce("autoflow_get_email");
				print "<form method=\"post\" action=\"".admin_url('admin-ajax.php')."?action=autoflow_api\">
						<input type=\"hidden\" name=\"wp_nonce\" value=\"{$nonce}\"/>
						<input type=\"hidden\" name=\"slug\" value=\"{$dto->slug}\"/>
						<input type=\"hidden\" name=\"uid\" value=\"{$uid}\"/>
						<input type=\"hidden\" name=\"username\" value=\"{$username}\"/>
						<label>Please enter your email address:
							<input type=\"text\" name=\"email\"/>
						</label>
						<label>Re-enter email address:
							<input type=\"text\" name=\"email2\"/>
						</label>
						<input type=\"submit\" value=\"Create Account\"/>
					</form>";
			}
			//end Create new account
				
		}		
	}
}
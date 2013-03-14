<?php

//include api-connection-manager class
//require_once( WP_PLUGIN_DIR . "/api-connection-manager/class-api-connection-manager.php");

class AutoFlow_API{

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
		$action = @$_REQUEST['autoflow_action'];
		
		if($action)
			if(method_exists($this, $action))
				$this->$action();
		
		$this->shortcodes = array(
			'list services' => array(&$this, 'list_services')
		);
		
		//actions
		add_action('wp_ajax_nopriv_autoflow_api', array(&$this, 'email_form_callback'));
		//add_action('admin_menu', array(&$this, 'get_menu'));
		
		/**
		 * login form hooks
		 */
		add_action('login_enqueue_scripts', array(&$this, 'get_styles'));
		add_action( 'login_footer', array( &$this, 'print_login_buttons' ) );
		//add_action( 'login_form', array( &$this, 'print_login_buttons' ) );
		//add_filter( 'login_message', array(&$this, 'get_login_buttons' ) );
		add_filter( 'login_message', array(&$this, 'print_login_errors' ) );
		add_shortcode( 'AutoFlow', array( &$this, 'print_login_buttons' ) );
		
		//set redirect
		add_action('init', array( &$this, 'set_redirect'));
		
		//parent::__construct( get_class($this));
	}
	
	/**
	 * Create Account
	 * If user is not logged in and there is no matching uid then create
	 * a new account for this social network. If there is no email
	 * returned from the service then show form to grab email
	 * @see AutoFlow_API::parse_dto()
	 * @uses API_Con_Mngr_View To print the results
	 * @link http://tommcfarlin.com/create-a-user-in-wordpress/
	 */
	public function create_account($user_data, $slug, $uid){
		
		//vars
		global $API_Connection_Manager;
		$view = new API_Con_Mngr_View();
		$username = wp_generate_password(6, false);
		$password = wp_generate_password( 12, false );
		while(username_exists($username))	//make sure username is unique 
			$username = wp_generate_password(6);
		
		// Generate the password and create the user
		$user_id = wp_create_user( $username, $password, $user_data['email'] );
		$API_Connection_Manager->log("Creating new user account");
		$API_Connection_Manager->log($user_id);
		//if error creating user, print and die()
		if(is_wp_error($user_id)){
			//set autoflow error
			$_REQUEST['error'] = $user_id->get_error_message();
			$_REQUEST['username'] = $_REQUEST['nickname'];
			$this->new_acc_form($_REQUEST);
			/**
			$view->body[] = $user_id->get_error_message();
			$view->body[] = "
				<a href=\"javascript:history.back()\" 
				   title=\"Back\"
				   class=\"btn btn-large btn-primary\">
				Back</a>";
			$view->get_html();
			die();
			 * 
			 */
		}
		
		// Set the nickname
		$user_data = wp_update_user(array(
			'ID' => $user_id,
			'nickname' => $user_data['nickname'],
			'display_name' => $user_data['nickname'],
			'first_name' => $user_data['firstname'],
			'last_name' => $user_data['surname']
		));

		//link up user with module uid and set tokens
		$service = $API_Connection_Manager->get_service($slug);
		$tokens = $_SESSION['Autoflow-tokens'];
		unset($_SESSION['Autoflow-tokens']);
		$login = $service->login_connect($user_id,$uid);
		$service->user = get_userdata($user_id);
		$service->set_params($tokens);
		
		//look for custom params taken during authentication
		if(@$_REQUEST['extra_params']){
			$extra_params = (array) json_decode(urldecode($_REQUEST['extra_params']));
			$service->set_params($extra_params);
		}
		
		/**
		 * user created successfully
		 */
		if($user_data && !is_wp_error($user_id) && !is_wp_error($login)){

			// Set the role
			$user = new WP_User( $user_id );
			$user->set_role( $this->new_user_role ); //'contributor' );

			/**
			* Set service uid to newly created user for future logins.
			*/
			$connections = get_option($this->option_name, array());
			$connections[$slug][$user_id] = $uid;
			update_option($this->option_name, $connections);
			//end set service uid
			
			//login user
			wp_set_current_user( $user_id, $username );
			wp_set_auth_cookie( $user_id );
			do_action( 'wp_login', $username );
			wp_redirect( admin_url() . "admin.php?page=api-connection-manager-user" );
			die();
		}
		//end user created successfully
		
		
		//default print error
		$view->body[] = "<h2>Error creating account</h2>";
		//if error creating user, print and die()
		if(is_wp_error($user_id))
			$view->body[] = $user_id->get_error_message ();
		if(is_wp_error($login))
			$view->body[] = $login->get_error_message();
		$view->body[] = "
			<a href=\"" . wp_login_url() . "\" 
			   title=\"Login\"
			   class=\"btn btn-large btn-primary\">
			Login</a>";
		$view->get_html();
		die();
	}
	
	/**
	 * Disconnect a user from a service 
	 */
	public function disconnect(){
		$user_id = $this->api->get_current_user()->ID;
		$meta = get_option("API_Con_Mngr_Module-connections", array());
		unset($meta[$_REQUEST['slug']][$user_id]);
		if(empty($meta[$_REQUEST['slug']]))
			unset($meta[$_REQUEST['slug']]);
		update_option("API_Con_Mngr_Module-connections", $meta);
	}
	
	/**
	 * Prints/returns the new account bootstrap form
	 * 
	 * The params are:
	 * <code>
	 * array(
	 *	'slug',
	 *	'uid',
	 *	'username',
	 *	'extra_params',
	 *	'firstname',
	 *	'surname',
	 *	'email',
	 *	'error'
	 * );
	 * </code>
	 * @uses API_Con_Mngr_View
	 * @param array $params
	 * @param type $die
	 * @return type
	 */
	public function new_acc_form( array $params, $die=true ){
		
		$nonce = wp_create_nonce("autoflow_get_email");
		$view = new API_Con_Mngr_View();
		$view->body[] = @"
			<p class=\"lead\">
				Creating new account. Please fill out the form below
			</p>";
		if(@$params['error'])
			$view->body[] = "
			<p class=\"alert-error\">
				{$params['error']}
			</p>
				";
		$view->body[] = "
			<form method=\"post\" class=\"form-horizontal\">
				<fieldset>
					<input type=\"hidden\" name=\"wp_nonce\" value=\"{$nonce}\"/>
					<input type=\"hidden\" name=\"slug\" value=\"{$params['slug']}\"/>
					<input type=\"hidden\" name=\"uid\" value=\"{$params['uid']}\"/>
					<input type=\"hidden\" name=\"autoflow_action\" value=\"new_acc_form_callback\"/>
					<input type=\"hidden\" name=\"api-con-mngr\" value=\"false\"/>";

		//extra params for custom services
		if(count(@$params['extra_params'])){
			$encoded_params = urlencode(json_encode($params['extra_params']));
			$view->body[] = "
					<input type=\"hidden\" name=\"extra_params\" value=\"{$encoded_params}\"/>\n";
		}//end extra params for custom services

		$view->body[] = @"			
					<div class=\"control-group\">
						<label class=\"control-label\" for=\"firstname\">
							Firstname</label>
						<div class=\"controls\">
							<input type=\"text\" name=\"firstname\" id=\"firstname\" value=\"{$params['firstname']}\" placeholder=\"Please enter your firstname\" required/>
						</div>
					</div>
					<div class=\"control-group\">
						<label class=\"control-label\" for=\"Surname\">
							Surname</label>
						<div class=\"controls\">
							<input type=\"text\" name=\"surname\" id=\"surname\" value=\"{$params['surname']}\" placeholder=\"Please enter your surname\" required/>
						</div>
					</div>
					<div class=\"control-group\">
						<label class=\"control-label\" for=\"nickname\">
							Nickname</label>
						<div class=\"controls\">
							<input type=\"text\" name=\"nickname\" id=\"nickname\" value=\"{$params['username']}\" placeholder=\"Please enter your nickanme\" required/>
						</div>
					</div>
					<div class=\"control-group\">
						<label class=\"control-label\" for=\"email\">
							Email</label>
						<div class=\"controls\">
							<input type=\"email\" 
								name=\"email\" 
								id=\"email\" 
								value=\"{$params['email']}\"
								placeholder=\"Please enter your email\"
								required/>
						</div>
					</div>
					<div class=\"control-group\">
						<label class=\"control-label\" for=\"email2\">
							Re-Type Email</label>
						<div class=\"controls\">
							<input type=\"email\" 
								name=\"email2\" 
								id=\"email2\" 
								value=\"{$params['email']}\"
								placeholder=\"Please retype your email\"
								data-validation-matches-match=\"email\"
								data-validation-matches-message=\"Must match email address entered above\"
								/>
						</div>
					</div>
					<div class=\"control-group\">
						<div class=\"controls\">
							<button type=\"submit\" class=\"btn\">Create Account</button>
						</div>
					</div>
				</fieldset>
			</form>
			";
		return $view->get_html( $die );
	}
	
	/**
	 * Process form requesting email address and then create new account.
	 */
	public function new_acc_form_callback(){
		
		//check nonce
		if(!wp_verify_nonce($_REQUEST['wp_nonce'],"autoflow_get_email"))
			die("invalid nonce");
		//check email
		if($_REQUEST['email']!=$_REQUEST['email2'])
			die("Emails don't match");
		
		//create account and die
		$this->create_account(array(
				'firstname' => $_REQUEST['firstname'],
				'surname' => $_REQUEST['surname'],
				'email' => $_REQUEST['email'],
				'nickname' => $_REQUEST['nickname']
			), $_REQUEST['slug'], $_REQUEST['uid']);
		die();
	}
	
	/**
	 * Styles to hide the wp login form on wp-login.php
	 * Does not work for forms printed with wp_login_form()
	 */
	public function get_styles(){
		?>
		<style type="text/css">
			div#login form#loginform, div#login p#nav {
				/*display: none;*/
			}
			div#autoflow-links{
				text-align: center;
			}
			div#autoflow-links h2{
				margin: 20px auto 0 auto;
			}
			div#autoflow-links ul{
				max-width: 800px;
				margin: 15px auto;
			}
			div#autoflow-links ul li{
				float: left;
				margin: 10px 25px;
			}
		</style>
		<?php
	}
	
	/**
	 * Build the admin menu 
	 * @depcrated
	 */
	public function get_menu(){
		add_menu_page("AutoFlow", "AutoFlow", "read", "autoflow", array(&$this, 'get_page'));
	}
	
	/**
	 * Callback for displaying the html in the dashboard settings page
	 * @deprecated Replaced by the API Con User Connections page
	 * @global API_Connection_Manager $API_Connection_Manager
	 * @global type $current_user
	 * @return type 
	 */
	public function list_services(){
		
		global $API_Connection_Manager;
		global $current_user;
		
		$count=1;
		$html = "<div id=\"dashboard-widgets\" class=\"metabox-holder columns-1\">\n";
		if(is_multisite())
			$meta = get_site_option("API_Con_Mngr_Module-connections", array());
		else
			$meta = get_option("API_Con_Mngr_Module-connections", array());
		//$meta = get_option("API_Con_Mngr_Module-connections", array());
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
						<input type=\"hidden\" name=\"autoflow_action\" value=\"disconnect\"/>
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
		$res = "<div id=\"autoflow-links\">
			<h2>Connect with...</h2>
			<ul>\n";
		
		//build list of buttons
		foreach($services as $slug => $service)
				//$res .= "<li><a href=\"" . $service->get_login_button( __FILE__, array(&$this, 'parse_dto') ) . "\">Login with {$service->Name}</a></li>\n";
				$res .= "<li>
					<a href=\"" . $service->get_login_button( __FILE__, array(&$this, 'parse_dto') ) . "\" border=\"0\">
						{$service->button}<br/>
						{$service->Name}
					</a></li>\n";
		
		//print/return result
		$res = "{$res}\n</ul>\n</div>\n";
		print $res;
	}
	
	/**
	 * Handles errors reported by api-con-mngr.
	 * Api Con will store errors in $_SESSION, print error box and reset session
	 */
	public function print_login_errors(){
		if(count(@$_SESSION['Api-Con-Errors'])){
			$html = "<div id=\"login_error\">\n<ul>\n";
			foreach($_SESSION['Api-Con-Errors'] as $err)
				$html .= "<li>{$err}</li>\n";
			print "{$html}</ul>\n</div>\n";
			wp_shake_js();
		}
				
		unset($_SESSION['Api-Con-Errors']);
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
		$extra_params = array();
		
		//make request for email
		switch ($dto->slug) {
			
			/**
			 * CityIndex
			 */
			case 'ci-login/index.php':
				
				$module->set_params($dto->response);
				$res = $module->request("https://ciapi.cityindex.com/tradingapi/useraccount/ClientAndTradingAccount");
				$body = json_decode($res['body']);
				$username = $body->LogonUserName;
				$uid = $module->get_uid();
				$email = $body->PersonalEmailAddress;
				break;
			//end cityindex
			
			/**
			 * Dropbox
			 */
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
			//end dropbox
			
			/**
			 * Facebook 
			 */
			case 'facebook/index.php':
				$module->set_params(array(
					'access_token' => $dto->response['access_token']
				));
				$res = $module->request(
					"https://graph.facebook.com/me",
					'get'
				);
				
				$body = json_decode($res['body']);
				$uid = $body->id;
				$email = $body->email;
				$username = $body->username;
				$firstname = $body->first_name;
				$surname = $body->last_name;
				break;
			//end Facebook
			
			/**
			 * Github 
			 */
			case 'github/index.php':
				
				//get user details
				$res = $module->request(
					"https://api.github.com/user?access_token={$dto->response['access_token']}&scope=user,user:email",
					"get"
				);
				$body = json_decode($res['body']);
				$username = $body->login;
				$uid = $body->id;
				
				/**
				 * get email
				 */
				$res = $module->request(
					"https://api.github.com/user/emails?access_token={$dto->response['access_token']}&scope=user,public_repo",
					"get"
				);
				$body = json_decode($res['body']);
				if(is_array($body))
					$email = $body[0];
				else
					$email = $body;
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
				
				
				$profile = json_decode($res['body']);
				$email = $profile->email;
				$firstname = $profile->given_name;
				$surname = $profile->family_name;
				$uid = $profile->id;
				$username = $profile->name;
				break;
			//end Google
			
			/**
			 * MailChimp
			 */
			case 'mailchimp/index.php':
				
				$res = $module->request(
						"getAccountDetails",
						"post",
						array(
							'apikey' => $module->apikey
						)
					);
				$body = json_decode($res['body']);
				$extra_params['api_endpoint'] = $module->api_endpoint;
				$uid = $body->user_id;
				$username = $body->username;
				$firstname = $body->contact->fname;
				$surname = $body->contact->lname;
				$email = $body->contact->email;
				
				break;
			//end mailchimp
			
			/**
			 * Twitter 
			 */
			case 'twitter/index.php':
				
				$module->set_params($dto->response);
				$res = $module->request("https://api.twitter.com/1.1/account/verify_credentials.json", "GET");
				$body = $module->parse_response($res);
				$uid = $body->id;
				$username = $body->screen_name;
				list($firstname, $surname) = @explode(" ", $body->name, 2);
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
		
		/**
		 * If logged in user then connect the account.
		 * Request must be from dashboard autoflow settings page.
		 */
		$login = $module->login($uid);
		
		/**
		 * If no logged in user then create new account
		 */
		if(!$login){
			
			//store tokens as session
			$_SESSION['Autoflow-tokens'] = $dto->response;
			
			$this->new_acc_form(array(
				'username' => @$username,
				'uid' => @$uid,
				'email' => @$email,
				'firstname' => @$firstname,
				'surname' => @$surname,
				'extra_params' => @$extra_params,
				'slug' => @$dto->slug
			));

		}	
	}
	
	/**
	 * Sets the redirect_to.
	 * 
	 * After a successfull login the user will be redirected to their initial
	 * request.
	 */
	public function set_redirect(){
		
		if(basename(wp_login_url()) != $GLOBALS['pagenow'])
			$_SESSION['Autoflow_redirect'] = $_SERVER['REQUEST_URI'];
		elseif(isset($_REQUEST['redirect_uri']))
			$_SESSION['Autoflow_redirect'] = $_REQUEST['redirect_uri'];
	}
}
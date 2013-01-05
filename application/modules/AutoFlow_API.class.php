<?php

//include api-connection-manager class
require_once( WP_PLUGIN_DIR . "/api-connection-manager/class-api-connection-manager.php");

class AutoFlow_API{

	/** @var API_Connection_Manager The api connection manager */
	private $api;

	/**
	 * Construct.
	 * 
	 * @global API_Connection_Manager $API_Connection_Manager 
	 */
	function __construct(){

		//load api connection manager
		global $API_Connection_Manager;
		if(!$API_Connection_Manager)
			$API_Connection_Manager = new API_Connection_Manager();
		$this->api = $API_Connection_Manager;
		
		/**
		 * add login form hook
		 * login form error messages hook
		 * add shortcode for custom theme forms
		 */
		add_action( 'login_footer', array( &$this, 'print_login_buttons' ) );
		//add_filter( 'login_message', array(&$this, 'print_login_errors' ) );
		add_shortcode( 'AutoFlow', array( &$this, 'print_login_buttons' ) );		
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
				$res .= "<li>" . $service->get_login_button( array(&$this, 'parse_dto') );
			else
				$res .= "<li>" . $this->api->get_login_button( $slug, __FILE__, array(&$this, 'parse_dto') );
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
		
		//make request for email
		switch ($dto->slug) {
			
			/**
			 * Github 
			 */
			case 'github/index.php':

				$res = $this->api->request( $dto->slug, array(
					'uri' => "https://api.github.com/user/emails?access_token={$dto->access_token}&scope=user,public_repo",
					'headers' => array(
						'Accept' => 'application/json'
					),
					'method' => 'get',
					'access_token' => $dto->access_token
				));
				
				$emails = (array) json_decode($res['body']);
				break;
			//end Github

			/**
			 * Google 
			 */
			case 'google/index.php':
				
				$res = $this->api->request($dto->slug, array(
					'uri' => "https://www.googleapis.com/oauth2/v1/userinfo?access_token={$dto->access_token}", 
					'headers' => array(
						'Authorization' => "Bearer {$dto->access_token}"
					),
					'method' => 'get',
					'access_token' => $dto->access_token
				));
				
				$emails = array( json_decode($res['body'])->email );
				break;
			//end Google
			
			/**
			 * Facebook 
			 */
			case 'facebook/index.php':
				
				$res = $this->api->request($dto->slug, array(
					'uri' => "https://graph.facebook.com/me?access_token={$dto->access_token}",
					'method' => 'get',
					'access_token' => $dto->access_token
				));
				
				$emails = (array) json_decode($res['body'])->email;
				break;
			//end Facebook
			
			/**
			 * Default die( error ) 
			 */
			default:
				die("<b>AutoFlow API</b>: Unkown service <em>{$dto->slug}</em><br/>
					Please add code to get account emails in <em>AutoFlow_API::parse_dto()</em>");
				break;
		}//end switch slug
		
		//get user object by email
		foreach ( (array) $emails as $email ) {
			$user_id = email_exists( $email );
			if ( $user_id ){
				$user = get_userdata( $user_id );
				break;
			}
		}

		//error report
		if(!$user)
			die("<b>AutoFlow API</b>:<br/>
				Email on service account <em>{$email}</em> does not match any on profiles on this blog");
		
		//log in user
		wp_set_current_user( $user->data->ID );
		wp_set_auth_cookie( $user->data->ID );
		$this->api->set_user_token($dto->slug, $dto->access_token, 'access', $user->data->ID);
		if(@$dto->refresh_token)
			$this->api->set_user_token($dto->slug, $dto->refresh_token, 'refresh', $user->data->ID);
		wp_redirect("/wp-admin");
		exit();
	}
	
	/**
	 * Returns the emails for an account on a service.
	 * 
	 * Is used for logging and testing access_tokens.
	 * 
	 * @todo change so always returns an array instead of string|array
	 * @todo use oauth2's token checking calls instead
	 * @param string $slug The service slug
	 * @param string $access_token The access token
	 * @return emails 
	 * @deprecated
	 * @subpackage service-method
	 */
	private function _service_get_account_emails($slug, $access_token){
		
		$service = $this->get_service($slug);
		$params = $service['params'];
		$req = array();
		$req['access_token'] = $access_token;
		$emails = false;
		$user = false;

		//get request for account info
		if("get"==strtolower($params['account-method'])){
			$url = url_query_append($params['account-uri'], $req);
			$res = wp_remote_get($url);
		}
		
		//look for email from result
		if('json'==strtolower($params['account-response-type'])){
			$res = json_decode($res['body']);
			
			$key = $params['account-response-email-var'];
			if(@$res->$key)
				$emails = $res->$key;
			if(is_array($res))
				$emails = $res;
		}
		
		if(!$emails) return $this->_error("No emails returned");
		return $emails;
	}	
}
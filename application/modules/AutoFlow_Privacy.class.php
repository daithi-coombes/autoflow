<?php
/**
 * - Prevents logged in users from accessing sub-sites they are not registered on.
 * - If user is not connected to any services, when they login they can only
 * access the `user connections` page.
 * 
 * Will display a form to request permission. This form will be auto emailed to
 * the registerd admin of that site, with the users email as the reply-to
 * header.
 * 
 * Users that are not logged in will be notified and redirected to the login
 * page of the root site.
 *
 * @author daithi coombes
 */
class AutoFlow_Privacy {
	
	/** @var string The email user for this blog */
	private $admin_email;
	/** @var stdClass The current blog information */
	private $blog;
	/** @var WP_User The logged in user */
	private $user;
	/** @var API_Con_Mngr_View The api-con view class to display network permission denied */
	private $view;

	public function __construct(){

		/**
		 * bootstrap
		 */
		require_once( WP_PLUGIN_DIR . '/api-connection-manager/class-api-con-mngr-view.php' );
		$this->admin_email = get_option( 'admin_email' );
		if ( is_multisite() )
			$this->blog = get_blog_details();
		$this->user = $this->get_user();
		$this->view = new API_Con_Mngr_View();
		$action = @$_REQUEST['autoflow_action'];

		//if user no connections redirect to user connections dash page
		$this->check_has_connections();
		//if on root blog return
		if ( get_current_blog_id() == 1 ) return;

		//do actions
		if ( method_exists( $this, $action ) )
			$this->$action();
		//end bootstrap

		//logged in users
		if ( $this->user->ID > 0 ){
			//if not in blog
			if ( !$this->is_user_in_blog() )
				$this->get_form();
		}
		
		//logged out users, permission denied by default
		else
			$this->get_form();
			
	}

	/**
	 * Force user to connect to at least one service.
	 * If user is not connected to any services then they will be redirected to
	 * the user connections page with an error message to connect.
	 */
	private function check_has_connections(){

		require_once( WP_PLUGIN_DIR . '/api-connection-manager/class-api-connection-manager.php' );
		require_once( WP_PLUGIN_DIR . '/api-connection-manager/class-api-con-mngr-error.php' );
		$api = new API_Connection_Manager();
		$connected = false;
		$user = wp_get_current_user();

		$connections = get_site_option('API_Con_Mngr_Module-connections');
		if ( !empty($connections) )
			foreach($connections as $slug => $connection)
				foreach($connection as $id => $profile_id)
					if( $id == $user->ID )
						$connected = true;
		
		if ( !$connected ){
			var_dump($_SERVER);
			if( 
				$_GET['page'] != 'api-connection-manager-user' &&
				$_GET['page'] != 'api-connection-manager-service' &&
				$_GET['page'] != 'api-connection-manager-setup' &&
				$_SERVER['PHP_SELF'] != '/wp-login.php'
			){
				$error = new API_Con_Mngr_Error('You must connect to at least one service to continue'); //@see API_Connection_Manager::admin_notices()
				wp_redirect( admin_url('admin.php?page=api-connection-manager-user') );
				die();
			}
		}
	}
	
	/**
	 * returns the admin user of the current blog.
	 * 
	 * @link http://www.thinkinginwp.com/2010/02/get-the-list-of-all-admins-for-a-blog-on-wpmu/
	 * @global wpdb
	 * @deprecated Only need the email in construct from <code>get_option('admin_email');</code>
	 * @return WP_User
	 */
	private function get_blog_admin(){
		global $wpdb;

		if ( !is_multisite() )
			return get_userdata( 1 );

		$blog = get_blog_details();
		$key = 'wp_' . $blog->blog_id . '_user_level';
		$admins = $wpdb->get_results( "SELECT user_id from $wpdb->usermeta AS um WHERE um.meta_key ='". $key."' AND um.meta_value=10" );
		return get_userdata( $admins[0]->user_id );
	}

	/**
	 * Prints the request permission form to stdout
	 * @return void
	 */
	private function get_form(){

		//vars
		$nonce = wp_create_nonce( 'autoflow privacy request permission' );
		$blogs = get_blogs_of_user( $this->user->ID );

		//error message
		$this->view->body[] = '
		<p class="alert">
			You do not have permission to view this site.';
		if ( $this->user->ID > 0 )
		$this->view->body[] = ' You can request permission
			by filling out the form below.';
		$this->view->body[] = '
			</p>'; //end error message

		//list sites with permission
		if ( count( $blogs ) ) {
			$this->view->body[] = '
				<p>
					Current blogs you have permission to view:
				</p>
				<ul>';

			foreach ( $blogs as $blog )
				$this->view->body[] = '<li>
					<a href="' . get_site_url( $blog->userblog_id ) . '">' . $blog->blogname . '</a>
				</li>';

			$this->view->body[] = '</ul>';
		} //end list sites with permission
		
		//request permission form
		if ( $this->user->ID > 0 ) {
			$this->view->body[] = '
			<form method="post" class="form-horizontal">
				<input type="hidden" name="autoflow_action" value="request_permission"/>
				<input type="hidden" name="_wpnonce" value="' . $nonce . '"/>
				<fieldset>
					<legend>Request Permission Form</legend>
					<div class="control-group">
						<label for="message" class="control-label">message</label>
						<div class="controls">
							<textarea name="message" id="message" placeholder="Enter your message here..." required></textarea>
							<p class="help-block">The site admin will respond to your email address ' . $this->user->data->user_email . '</p>
						</div>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn btn-primary">Request Permission</button>
					</div>
				</fieldset>
			</form>
		';
		}	

		//logged out users
		else {
			switch_to_blog( 1 );
			$this->view->body[] = '
			<p>
				<a href="' . wp_login_url() . '" class="btn btn-primary">Login or Create Account</a>
			</p>';
			restore_current_blog();
		}

		$this->view->get_html();	//will die()
	}

	/**
	 * returns the current user, or use 0 if none logged in
	 * @return WP_User
	 */
	private function get_user(){
		global $current_user;
		$current_user = wp_get_current_user();
		return $current_user;
	}

	/**
	 * Check if the current user is in the current blog's user list
	 * @uses $this->user
	 * @uses $this->blog
	 * @return boolean
	 */
	private function is_user_in_blog(){

		//no logged in user
		if ( $this->user->ID == 0 )
			return false;
		
		//single site installs, all users are in blog
		if ( !is_multisite() )
			return true;

		//iterate through current blog users, return true if found
		foreach ( get_users(
			array(
				'blog_id' => $this->blog->blog_id,
			)
		) as $user)
			if ( $user->ID == $this->user->ID )
				return true;
		
		//default return false
		return false;
	}

	/**
	 * Process a permission request.
	 * Will send email to blog admin and report success/fail with list of blog links assigned to current user
	 * @return die()
	 */
	private function request_permission(){

		//check nonce
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'autoflow privacy request permission' ) )
			die( 'Invalid Nonce' );
		
		//vars
		$headers = array(
			'From: ' . $this->user->data->display_name . ' <' . $this->user->data->user_email . '>',
			'reply-to: ' . $this->user->data->display_name . ' <' . $this->user->data->user_email . '>',
		);	
		
		$message = $_REQUEST['message'];
		$subject = 'Permission request for ' . $this->blog->blogname;
		$user_blogs = get_blogs_of_user( $this->user->ID );

		/**
		 * send email
		 */
		$sent = wp_mail(
			$this->admin_email,
			$subject,
			$message,
			$headers
			);
		//end send email

		//success
		if ( $sent )
			$this->view->body[] = '<p>Your request was sent successfully</p>';
		//fail
		else
			$this->view->body[] = '<p>There was an error sending your request</p>';

		//$this->view->body[] = list of blogs assigned to user
		$this->view->body[] = '<p>You can continue to one of the following blogs</p>
			<ul>';
		foreach ( $user_blogs as $blog )
			$this->view->body[] = '<li><a href="' . get_site_url( $blog->userblog_id ) . '">' . $blog->blogname . '</a></li>';
		$this->view->body[] = '</ul>';

		//print view file and die()
		$this->view->get_html();
	}
}
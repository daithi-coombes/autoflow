<?php
/**
 * Prevents logged in users from accessing sub-sites they are not registered on.
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

	public function __construct(){

		/**
		 * bootstrap
		 */
		$this->admin_email = get_option('admin_email');
		$this->blog = get_blog_details();
		$this->user = $this->get_user();
		$action = @$_REQUEST['autoflow_action'];
		if(method_exists($this, $action))
			$this->$action();
		//end bootstrap

		//logged in users
		if($this->user->ID > 0){
			
			//if not in blog
			if(!$this->is_user_in_blog())
				$this->get_form();

			//if in blog, then do nothing ;)
			else
				;
			}

		//logged out users
		else
			ar_print("No user logged in");
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
		$blog = get_blog_details();
		$key="wp_".$blog->blog_id."_user_level";
		$admins = $wpdb->get_results("SELECT user_id from $wpdb->usermeta AS um WHERE um.meta_key ='". $key."' AND um.meta_value=10");
		return get_userdata($admins[0]->user_id);
	}

	/**
	 * Prints the request permission form to stdout
	 * @return void
	 */
	private function get_form(){

		//vars
		$nonce = wp_create_nonce('autoflow privacy request permission');
		
		//print form
		?>
		<div>
			<h2><?php echo $this->blog->blogname; ?> Permission Denied</h2>
			<h3>You do not have permission for this site.<br/>
			Please fill out the form below to request permission</h3>
		</div>
		<form method="post">
			<input type="hidden" name="autoflow_action" value="request_permission"/>
			<input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>"/>
			<ul>
				<li>
					<label for="message">Request Permission</label>
					<textarea name="message" id="message"></textarea>
				</li>
				<li>
					<input type="submit" value="Request Permission"/>
				</li>
			</ul>
		</form>
		<?php
		die();
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
		if($this->user->ID == 0)
			return false;
		
		//iterate through current blog users, return true if found
		foreach(get_users(array(
			'blog_id' => $this->blog->blog_id
		)) as $user)
			if($user->ID==$this->user->ID)
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
		if(!wp_verify_nonce( $_REQUEST['_wpnonce'], 'autoflow privacy request permission'))
			die('Invalid Nonce');
		
		//vars
		$headers = array(
			"From: {$this->user->data->user_nicename} <{$this->user->data->user_email}>"
			);
		$message = $_REQUEST['message'];
		$subject = "Permission request for {$this->blog->blogname}";
		$user_blogs = get_blogs_of_user( $this->user->ID );

		//send email
		$sent = wp_mail(
			$this->admin_email,
			$subject,
			$message,
			$headers
			);

		//success
		if($sent)
			print "<p>Your request was sent successfully</p>";
		//fail
		else
			print "<p>There was an error sending your request</p>";

		//print list of blogs assigned to user
		print "<ul>\n";
		foreach($user_blogs as $blog)
			print "<li><a href=\"".@get_blog_permalink($blog->userblog_id, null)."\">{$blog->blogname}</a></li>\n";
		print "</ul>";

		//die
		die();
	}
}
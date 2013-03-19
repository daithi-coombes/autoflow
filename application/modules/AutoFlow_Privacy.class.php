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
	
	/** @var WP_User The admin user for this blog */
	private $admin;
	/** @var stdClass The current blog information */
	private $blog;
	/** @var WP_User The logged in user */
	private $user;

	function __construct(){
		
		/**
		 * bootstrap
		 */
		$this->admin = $this->get_blog_admin();
		$this->blog = get_blog_details();
		$this->user = $this->get_user();
		//end bootstrap

		//logged in users
		if($this->user->ID > 0){
			
			//if not in blog
			if(!$this->is_user_in_blog()){
				$this->get_form();
			}
		}

		//logged out users
		else{
			ar_print("No user logged in");
		}
	}
	
	/**
	 * returns the admin user of the current blog.
	 * 
	 * @link http://www.thinkinginwp.com/2010/02/get-the-list-of-all-admins-for-a-blog-on-wpmu/
	 * @global wpdb
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
	 * returns the current user, or use 0 if none logged in
	 * @return WP_User
	 */
	private function get_user(){
		global $current_user;
		$current_user = wp_get_current_user();
		return $current_user;
	}

	/**
	 *
	 */
	private function is_user_in_blog(){

		//no logged in user
		if($this->user->ID == 0)
			return false;

		$users = get_users_of_blog();
		ar_print($users);
	}
}

?>

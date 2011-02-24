<?php
/*
Plugin Name: Add Existing Users
Plugin URI:
Description:
Author: Andrew Billits, Ulrich Sossou
Version: 1.1.1
Text Domain: add_users
Author URI: http://premium.wpmudev.org
WDP ID:
*/

/**
 * Main plugin class
 *
 **/
class Add_Users {

	/**
	 * Current version number
	 *
	 **/
	var $current_version = '1.1.1';

	/**
	 * For supporters only
	 *
	 **/
	var $supporter_only = 'no'; // Either 'yes' OR 'no'

	/**
	 * Number of field sets to display
	 *
	 **/
	var $fields = '';

	/**
	 * PHP4 Constructor
	 *
	 **/
	function Add_Users() {
		__construct();
	}

	/**
	 * PHP5 Constructor
	 *
	 **/
	function __construct() {

		// get number of field sets
		$this->fields = isset( $_GET['fields'] ) ? $_GET['fields'] : '';

		// default to 15 field sets
		if ( $this->fields == '' )
			$this->fields = 15;

		// no more than 50 fields sets
		if ( $this->fields > 50 )
			$this->fields = 50;

		// activate or upgrade
		if ( isset( $_GET['page'] ) && $_GET['page'] == 'add-users' )
			$this->make_current();

		// add admin menu page
		add_action( 'admin_menu', array( &$this, 'plug_pages' ) );

		// load text domain
		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/add-users.php' ) ) {
			load_muplugin_textdomain( 'add_users', 'add-users-files/languages' );
		} else {
			load_plugin_textdomain( 'add_users', false, dirname( plugin_basename( __FILE__ ) ) . '/add-users-files/languages' );
		}
	}

	/**
	 * Update database
	 *
	 **/
	function make_current() {
		// create global database table
		$this->global_install();

		if ( get_site_option( 'add_users_version' ) == '' )
			add_site_option( 'add_users_version', $this->current_version );

		if ( get_site_option( 'add_users_version' ) !== $this->current_version )
			update_site_option( 'add_users_version', $this->current_version );

		if ( get_option( 'add_users_version' ) == '' )
			add_option( 'add_users_version', $this->current_version );

		if ( get_option( 'add_users_version' ) !== $this->current_version )
			update_option( 'add_users_version', $this->current_version );

	}

	/**
	 * Create global database if it doesn't exist
	 *
	 **/
	function global_install() {
		global $wpdb;

		if( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) )
			include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		else
			die( __( 'We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'', 'add_users' ) );

		// choose correct table charset and collation
		$charset_collate = '';
		if( $wpdb->supports_collation() ) {
			if( !empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if( !empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}
		}

		$table = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}add_users_queue` (
			`add_users_ID` bigint(20) unsigned NOT NULL auto_increment,
			`add_users_site_ID` bigint(20),
			`add_users_blog_ID` bigint(20),
			`add_users_batch_ID` varchar(255),
			`add_users_user_email` varchar(255),
			`add_users_user_role` varchar(255),
			PRIMARY KEY  (`add_users_ID`)
		) $charset_collate;";

		maybe_create_table( "{$wpdb->base_prefix}add_users_queue", $table );
	}

	/**
	 * Add admin menu
	 *
	 **/
	function plug_pages() {
		add_submenu_page( 'users.php', 'Add Existing Users', 'Add Existing Users', 'edit_users', 'add-users', array( &$this, 'page_output' ) );
	}

	/**
	 * Add one row of data to the queue
	 *
	 **/
	function queue_insert( $batch_ID, $user_email, $user_role ) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}add_users_queue ( add_users_site_ID, add_users_blog_ID, add_users_batch_ID, add_users_user_email, add_users_user_role ) VALUES ( %d, %d, %d, %s, %s )", $wpdb->siteid, $wpdb->blogid, $batch_ID, $user_email, $user_role ) );
	}

	/**
	 * Process queue for one blog
	 *
	 **/
	function queue_process( $blog_ID, $site_ID ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}add_users_queue WHERE add_users_site_ID = '%d' AND add_users_blog_ID = '%d' LIMIT 1", $site_ID, $blog_ID );

		$users = $wpdb->get_results( $query, ARRAY_A );

		if( count( $users ) > 0 ) {
			foreach ( $users as $user ) {
				$tmp_user = get_user_by_email( $user['add_users_user_email'] );
				add_user_to_blog( $wpdb->blogid, $tmp_user->ID, $user['add_users_user_role'] );

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}add_users_queue WHERE add_users_blog_ID = '%d' AND add_users_site_ID = '%d' AND add_users_ID = '%d'", $wpdb->blogid, $wpdb->siteid, $user['add_users_ID'] ) );
			}
		}
	}

	/**
	 * Check if current blog is a supporter blog
	 *
	 **/
	function is_supporter() {
		if ( function_exists( 'is_supporter' ) )
			return is_supporter();

		return false;
	}

	/**
	 * Display plugin admin page
	 *
	 **/
	function page_output() {
		global $wpdb;

		// display error message if supporter only
		if ( !$this->is_supporter() && 'yes' == $this->supporter_only ) {
			supporter_feature_notice();
			return;
		}

		// display message when successful
		if( isset( $_GET['updated'] ) )
			echo '<div id="message" class="updated fade"><p>' . urldecode( $_GET['updatedmsg'] ) . '</p></div>';

		echo '<div class="wrap">';

		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';
		switch( $action ) {

			case 'process_queue': // process queue from database
				check_admin_referer( 'add-users-process_queue_new_users' );

				echo '<p>' . __( 'Adding Users...', 'add_users' ) . '</p>';
				$this->queue_process( $wpdb->blogid, $wpdb->siteid );
				$queue_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}add_users_queue WHERE add_users_site_ID = '%d' AND add_users_blog_ID = '%d'", $wpdb->siteid, $wpdb->blogid ) );

				if ( $queue_count > 0 )
					echo '<script language=\'javascript\'>window.location=\'' . htmlspecialchars_decode( wp_nonce_url( 'users.php?page=add-users&action=process_queue', 'add-users-process_queue_new_users' ) ) . '\';</script>';
				else
					echo '<script language=\'javascript\'>window.location=\'users.php?page=add-users&updated=true&updatedmsg=' . urlencode( __( 'Users Added.', 'add_users' ) ) . '\';</script>';
			break;

			case 'process': // add entries to queue
				if ( isset( $_POST['Cancel'] ) )
					echo "<script language='javascript'>window.location='users.php?page=add-users';</script>";

				$batch_ID = md5( $wpdb->blogid . time() . '0420i203zm' );
				$errors = '';
				$error_fields = '';
				$error_messages = '';
				$global_errors = 0;
				$add_users_items = '';

				// validate users names, emails and passwords
				for ( $counter = 1; $counter <= $this->fields; $counter += 1 ) {
					$user_email = stripslashes( $_POST['user_email_' . $counter] );
					$user_role = stripslashes( $_POST['user_role_' . $counter] );
					$error = 0;
					$error_field = '';
					$error_msg = '';

					if ( ! empty( $user_email ) ) {
						$user = get_user_by_email( $user_email );
						if ( ! $user ) {
							$error = 1;
							$error_field = 'user_email';
							$error_msg = __( 'A user with that email address could not be found' , 'add_users' );
						}

						$add_users_items[$counter]['user_email'] = $user_email;
						$add_users_items[$counter]['user_role'] = $user_role;

						$errors[$counter] = $error;
						$error_fields[$counter] = $error_field;
						$error_messages[$counter] = $error_msg;
						if ( $error )
							$global_errors = $global_errors + 1;
					}
				}

				// if there are errors, display them
				if ( $global_errors > 0 ) {

					echo '<h2>' . __( 'Add Existing Users', 'add_users' ) . '</h2>';
					echo '<div class="message error"><p>' . __( 'Errors were found. Please fix the errors and hit Next.', 'add_users' ) . '</p></div>';

					if ( ! empty( $_GET['fields'] ) )
						echo "<form name='form1' method='POST' action='users.php?page=add-users&action=process&fields=$_GET[fields]'>";
					else
						echo '<form name="form1" method="POST" action="users.php?page=add-users&action=process">';

					wp_nonce_field( 'add-users-process_new_users' );

					$this->formfields( $errors, $error_messages );
					?>
					<p class="submit">
					<input type="submit" name="Submit" value="<?php _e( 'Next', 'add_users' ) ?>" />
					<input type="submit" name="Cancel" value="<?php _e( 'Cancel', 'add_users' ) ?>" />
					</p>
					<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'add_users' ) ?></p>
					</form>
					<?php

				// if emails are all goods, add them to queue
				} else {

					check_admin_referer( 'add-users-process_new_users' );

					// process
					if ( count( $add_users_items ) > 0 && is_array($add_users_items) ) {
						echo '<p>' . __( 'Adding Users...', 'add_users' ) . '</p>';
						foreach( $add_users_items as $add_users_item ) {
							$this->queue_insert( $batch_ID, $add_users_item['user_email'], $add_users_item['user_role'] );
						}
					}
					$queue_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}add_users_queue WHERE add_users_site_ID = '%d' AND add_users_blog_ID = '%d'", $wpdb->siteid, $wpdb->blogid ) );

					if ( $queue_count > 0 )
						echo '<script language=\'javascript\'>window.location=\'' . htmlspecialchars_decode( wp_nonce_url( 'users.php?page=add-users&action=process_queue', 'add-users-process_queue_new_users' ) ) . '\';</script>';
					else
						echo '<script language=\'javascript\'>window.location=\'users.php?page=add-users\';</script>';

				}
			break;

			default:
				echo '<h2>' . __( 'Add Existing Users', 'add_users'  ) . '</h2>';
				echo '<p>' . __( 'This tool allows you to create existing users on this site to your blog.', 'add_users'  ) . '</p>';
				echo class_exists( 'Add_New_Users' ) ? '<p>' . __( 'To add new users that have not already been created, please use the <a href="users.php?page=add-new-users">Add New Users functionality here</a>.', 'add_users'  ) . '</p>' : '';

				echo '<p>' . __( 'To add users simply enter each user\'s email and select a role for them on this blog - you can find out more about different levels of access for different roles <a href="http://help.edublogs.org/2009/08/24/what-are-the-different-roles-of-users/">here</a>.', 'add_users'  ) . '</p>';

				$fields = !empty( $_GET['fields'] ) ? "&fields=$_GET[fields]" : '';

				echo "<form name='form1' method='POST' action='users.php?page=add-users&action=process$fields'>";
				wp_nonce_field( 'add-users-process_new_users' );

				$this->formfields();
				?>
				<p class="submit">
				<input <?php if ( !$this->is_supporter() && $this->supporter_only == 'yes' ) { echo 'disabled="disabled"'; } ?> type="submit" name="Submit" value="<?php _e( 'Next', 'add_users' ) ?>" />
				</p>
				<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'add_users' ) ?></p>
				</form>
				<?php
			break;
		}
		echo '</div>';
	}

	function formfields( $errors = '', $error_messages = '' ) {
		global $wp_roles;

		for ( $counter = 1; $counter <= $this->fields; $counter += 1) {
			if( isset( $errors[$counter] ) && 1 == $errors[$counter] ) {
				?>
				<h3 style="background-color:#F79696; padding:5px 5px 5px 5px;"><?php echo $counter . ': ' ?><?php echo $error_messages[$counter]; ?></h3>
				<?php
			} else {
				?>
				<h3><?php echo $counter . ':' ?></h3>
				<?php
			}
			?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'User Email', 'add_users' ) ?></th>
					<td><input <?php if ( !$this->is_supporter() && $this->supporter_only == 'yes' ) { echo 'disabled="disabled"'; } ?> type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" style="width: 95%"  maxlength="200" value="<?php echo isset( $_POST['user_email_' . $counter] ) ? $_POST['user_email_' . $counter] : ''; ?>" />
					<br />
					<?php _e( 'Required', 'add_users' ) ?></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php _e( 'User Role', 'add_users' ) ?></th>
					<td><select <?php if ( !$this->is_supporter() && $this->supporter_only == 'yes' ) { echo 'disabled="disabled"'; } ?> name="user_role_<?php echo $counter; ?>" style="width: 25%;">
						<?php
						foreach( $wp_roles->role_names as $role => $name ) {
							$selected = '';
							if ( isset( $_POST['user_role_' . $counter] ) && $_POST['user_role_' . $counter] == $role )
								$selected = 'selected="selected"';

							echo "<option {$selected} value=\"{$role}\">{$name}</option>";
						}
						?>
					</select></td>
				</tr>
			</table>
			<?php
		}
	}

}

$add_users =& new Add_Users;

/**
 * Show notification if WPMUDEV Update Notifications plugin is not installed
 *
 **/
if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}

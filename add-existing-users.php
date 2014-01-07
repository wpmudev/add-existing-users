<?php
/*
Plugin Name: Add Existing Users
Plugin URI:
Description:
Author: Andrew Billits, Ulrich Sossou, Ignacio Cruz
Version: 1.2
Text Domain: add_users
Author URI: http://premium.wpmudev.org
WDP ID: 175
*/

/**
 * Main plugin class
 *
 **/
class Incsub_Add_Users {

	/**
	 * Language domain
	 */
	var $lang_domain = 'add_users';

	/**
	 * Current version number
	 *
	 **/
	var $current_version = '1.2';

	/**
	 * Version slug for options table
	 */
	var $version_option_slug = 'add_users_version';

	/**
	 * For Pro Sites Only only
	 *
	 **/
	var $pro_site_only; // Either true OR false (set in __construct() )

	/**
	 * Number of field sets to display
	 *
	 **/
	var $fields = '';

	/**
	 * Menu slug
	 */
	var $menu_slug = 'add-existing-users';

	/**
	 * Save the errors that occurs in the form
	 */
	var $form_errors = array();
	

	/**
	 * Constructor
	 *
	 **/
	function __construct() {
		global $wpdb;

		if ( ! defined( 'ADD_EXISTING_USERS_PRO_SITES_ONLY' ) )
			$this->pro_site_only = false;
		else
			$this->pro_site_only = ADD_EXISTING_USERS_PRO_SITES_ONLY;

		// get number of field sets
		$this->fields = isset( $_GET['fields'] ) ? $_GET['fields'] : '';

		// default to 15 field sets
		if ( $this->fields == '' )
			$this->fields = 10;

		// no more than 50 fields sets
		if ( $this->fields > 50 )
			$this->fields = 50;

		global $wpmudev_notices;
		$wpmudev_notices[] = array( 'id'=> 175,'name'=> 'Add Existing Users', 'screens' => array( 'users_page_add-existing-users' ) );
		include_once( MULTISTE_CC_INCLUDES_DIR . 'dash-notice/wpmudev-dash-notification.php' );

		// add admin menu page
		add_action( 'admin_menu', array( &$this, 'plug_pages' ) );

		// Load text domain
		add_action( 'plugins_loaded', array( &$this, 'load_text_domain' ) );

		// Need upgrade?
		add_action( 'init', array( &$this, 'maybe_upgrade' ) );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );

	}

	/**
	 * Load the plugin text domain and MO files
	 * 
	 * These can be uploaded to the main WP Languages folder
	 * or the plugin one
	 */
	public function load_text_domain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->lang_domain );

		load_textdomain( $this->lang_domain, WP_LANG_DIR . '/' . $this->lang_domain . '/' . $this->lang_domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $this->lang_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	public function activate() {
		update_site_option( $this->version_option_slug, $this->current_version );
	}

	/**
	 * Manage the plugin upgrades
	 */
	public function maybe_upgrade() {
		$current_version = get_site_option( $this->version_option_slug, '1.0' );

		if ( version_compare( $current_version, '1.1.1', '<=' ) ) {
			global $wpdb;
			$table = $wpdb->base_prefix . 'add_users_queue';
			$wpdb->query( "DROP TABLE $table" );
			update_site_option( $this->version_option_slug, $this->current_version );
		}
	}


	/**
	 * Add admin menu
	 *
	 **/
	function plug_pages() {
		$this->page_id = add_submenu_page( 'users.php', __( 'Add Existing Users', $this->lang_domain ), __( 'Add Existing Users', $this->lang_domain ), 'edit_users', $this->menu_slug, array( &$this, 'page_output' ) );
		add_action( 'load-' . $this->page_id, array( &$this, 'sanitize_form' ) );
	}

	/**
	 * Check if current blog is a Pro Site blog
	 *
	 **/
	function is_pro_site() {
		if ( function_exists( 'is_pro_site' ) )
			return is_pro_site();

		return false;
	}

	/**
	 * Display plugin admin page
	 *
	 **/
	function page_output() {
		global $wpdb, $wp_roles;

		// display error message if Pro Site only
		if ( ! $this->is_pro_site() &&  $this->pro_site_only ) {
			global $psts;
			if ( is_object( $psts ) )
				$psts->feature_notice();
			return;
		}

		$fields = ! empty( $_GET['fields'] ) ? $_GET['fields'] : '';
		$form_url = add_query_arg( 
			array( 
				'fields' => $fields,
				'action' => 'process'
			)
		);

		// display message when successful
		if ( isset( $_GET['updated'] ) ) {
			?>
				<div class="updated fade"><p><?php _e( 'Users have been added.', $this->lang_domain ); ?></p></div>
			<?php
		}

		if ( ! empty( $this->form_errors ) ) {
			if ( isset( $this->form_errors['no_data'] ) ) {
				?>
					<div class="error"><p><?php echo $this->form_errors['no_data']->get_error_message(); ?></p></div>
				<?php
			}
			else {
				?>
					<div class="error">
						<ul>
							<?php foreach ( $this->form_errors as $key => $error ): ?>
								<li><?php echo $error->get_error_message(); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php
			}
		}
			
		?>
		<div class="wrap">
			<h2><?php _e( 'Add Existing Users', $this->lang_domain  ); ?></h2>
			<p><?php _e( 'This tool allows you to create existing users on this site to your blog.', $this->lang_domain  ); ?></p>

			<?php if ( class_exists( 'Add_New_Users' ) ): ?>
				<?php $add_new_users_url = add_query_arg( 'page', 'add-new-users', admin_url( 'users.php' ) ); ?>
				<p><?php printf( __( 'To add new users that have not already been created, please use the <a href="%s">Add New Users functionality here</a>.', $this->lang_domain ), $add_new_users_url ); ?></p>
			<?php endif; ?>
			
			<?php $help_url = 'http://help.edublogs.org/2009/08/24/what-are-the-different-roles-of-users/'; ?>
			<p><?php printf( __( 'To add users simply enter each user\'s email and select a role for them on this blog - you can find out more about different levels of access for different roles <a href="%s">here</a>.', $this->lang_domain ), $help_url ); ?></p>

			<form name='form1' method='POST' action='<?php echo $form_url; ?>'>
				<?php wp_nonce_field( 'add-users-process_new_users' ); ?>

				<?php for ( $counter = 1; $counter <= $this->fields; $counter += 1 ): ?>
					<?php $user_email = ! empty( $_POST['user_email_' . $counter] ) ? $_POST['user_email_' . $counter] : ''; ?>
					<h3><?php echo $counter . ':' ?></h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e( 'User Email', $this->lang_domain ) ?></th>
							<td>
								<input type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" class="medium-text" <?php echo $color; ?> value="<?php echo esc_attr( $user_email ); ?>" />
								<span class="description"> <?php _e( 'Required', $this->lang_domain ) ?></span>
								<?php if ( isset( $this->form_errors[ 'user_email_' . $counter ] ) ): ?>
									<br/><span style="color:red"><?php echo $this->form_errors[ 'user_email_' . $counter ]->get_error_message(); ?></span>
								<?php endif; ?>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row"><?php _e( 'User Role', $this->lang_domain ) ?></th>
							<td>
								<select name="user_role_<?php echo $counter; ?>">
									<?php wp_dropdown_roles(); ?>
 								</select>
 							</td>
						</tr>
					</table>
				<?php endfor; ?>
					
				<?php submit_button( __( 'Submit', $this->lang_domain ), 'primary', 'process-users-submit' ); ?>
			
				<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', $this->lang_domain ) ?></p>
			</form>
		</div>
			<?php
	}

	function sanitize_form() {
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';

		if ( empty( $action ) )
			return;

		if ( $action == 'process' && isset( $_POST['process-users-submit'] ) ) {
			
			check_admin_referer( 'add-users-process_new_users' );

			if ( isset( $_POST['Cancel'] ) ) {
				$cancel_url = add_query_arg( 'page', $this->menu_slug, admin_url( 'users.php' ) );
				wp_redirect( $cancel_url );
				exit;
			}

			$batch_ID = md5( $wpdb->blogid . time() . '0420i203zm' );
			$errors = '';
			$error_fields = '';
			$error_messages = '';
			$global_errors = 0;
			$add_users_items = array();

			for ( $counter = 1; $counter <= $this->fields; $counter += 1 ) {
				$user_email = trim( stripslashes( $_POST['user_email_' . $counter] ) );

				if ( empty( $user_email ) )
					continue;

				$user_role = stripslashes( $_POST['user_role_' . $counter] );
				$error = false;
				$error_field = '';
				$error_msg = '';

				if ( is_email( $user_email ) ) {
					$user = get_user_by( 'email', $user_email );
					if ( ! $user ) {
						$error = true;
						$this->form_errors[ 'user_email_' . $counter ] = new WP_Error( 
							'user_email_' . $counter, 
							sprintf( __( 'The user with email address <strong>%s</strong> could not be found', $this->lang_domain ), $user_email )
						);
					}

					if ( ! $error ) {
						$add_users_items[ $counter ] = array(
							'user_email' => $user_email,
							'user_role' => $user_role
						);
					}

				}
				else {
					$error = true;
					$this->form_errors[ 'user_email_' . $counter ] = new WP_Error( 
						'user_email_' . $counter, 
						sprintf( __( 'Email <strong>%s</strong> is not a valid one', $this->lang_domain ), $user_email )
					);
				}
			}

			if ( count( $this->form_errors ) == 0 ) {

				if ( empty( $add_users_items ) ) {
					$this->form_errors['no_data'] = new WP_Error( 
						'no_data', 
						__( 'No data to process', $this->lang_domain )
					);
					return false;
				}

				foreach( $add_users_items as $add_users_item ) {
					$user = get_user_by( 'email', $add_users_item['user_email'] );
					add_user_to_blog( get_current_blog_id(), $user->ID, $add_users_item['user_role'] );
				}

				$redirect_to = add_query_arg( 
					array( 
						'page' => $this->menu_slug,
						'updated' => 'true',
					),
					admin_url( 'users.php' ) 
				);
				wp_redirect( $redirect_to );
				exit;
			}
		}
	}

}

global $incsub_add_users;
$incsub_add_users = new Incsub_Add_Users;

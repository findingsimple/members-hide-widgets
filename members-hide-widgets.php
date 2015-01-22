<?php
/*
Plugin Name: Members Hide Widgets
Plugin URI: http://plugins.findingsimple.com
Description: Adds ability to select what roles are allowed to view specific widgets - works with the content permission feature in Justin Tadlock's [Members plugin] (https://github.com/justintadlock/members) 
Version: 1.0
Author: Finding Simple
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2015  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Members_Hide_Widget' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Members_Hide_Menu_items
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @package Members Hide Widget
 * @since 1.0
 */
function initialize_mhw(){

	// Check if the Members plugin is active and the content permission feature is active
	if( function_exists( 'members_get_setting' ) ) {

		if ( members_get_setting( 'content_permissions' ) ) {
			Members_Hide_Widget::init();
		}
	}

}
add_action( 'init', 'initialize_mhw', -1 );

/**
 * Plugin Main Class.
 *
 * @package Members Hide Widget
 * @since 1.0
 */
class Members_Hide_Widget {

	static $text_domain;
	
	/**
	 * Initialise
	 */
	public static function init() {

		self::$text_domain = apply_filters( 'mhw_text_domain', 'mhw' );

		// Add input fields(priority 5, 3 parameters)
		add_action( 'in_widget_form', array( __CLASS__, 'add_widget_role_fields'), 5, 3 );

		// Callback function for options update
		add_filter( 'widget_update_callback', array( __CLASS__, 'update_widget_options'), 5, 3 );

		// Actually hide the widgets from the front end depending on roles
		add_filter( 'widget_display_callback', array( __CLASS__, 'maybe_hide_widget' ), 10, 1 );			

	}

	/**
	 * Add fields for selecting roles allowed to view a specific widget
	 */
	public static function add_widget_role_fields( $t, $return, $instance ){

		// Only add the input fields if the current user has the 'restrict_content' capability.
		if ( current_user_can( 'restrict_content' ) ) {

			global $wp_roles;

			// Get the roles/options saved for this instance of the widget.
		    $instance = wp_parse_args( (array) $instance, array( 'members_access_role' => '') );

		    if ( !isset($instance['members_access_role']) )
		        $instance['members_access_role'] = null;

		    $roles = $instance['members_access_role'];

		?>

		<div style="overflow: hidden; margin-left: 5px;">
		<p><?php _e( "Limit access/visibility to users of the selected roles:", 'members' ); ?></p>
		<?php
		// Loop through each of the available roles.
		foreach ( $wp_roles->role_names as $role => $name ) {

			$checked = false;
			
			// If the role has been selected, make sure it's checked.
			if ( is_array( $roles ) && in_array( $role, $roles ) ) {
				$checked = ' checked="checked" '; 
			}

			?>
			<div style="width: 32%; float: left; margin: 0 0 5px 0;">
				<label for="<?php echo $t->get_field_id('members_access_role'); ?>-<?php echo $role; ?>">
				<input type="checkbox" name="<?php echo $t->get_field_name('members_access_role'); ?>[<?php echo $role; ?>]" id="<?php echo $t->get_field_id('members_access_role'); ?>-<?php echo $role; ?>" <?php echo $checked; ?> value="<?php echo $role; ?>" />
				<?php echo esc_html( $name ); ?>
				</label>
			</div>
		<?php } ?>
		</div>
		<?php

		    $return = null;

		} // end if current_user_can

		return array( $t, $return, $instance );

	}

	/**
	 * Updating the widget options
	 */
	public static function update_widget_options( $instance, $new_instance, $old_instance ){

		$instance['members_access_role'] = $new_instance['members_access_role'];

		return $instance;

	}

	/**
	 * Hide the widget if the current user does not have the required role/capabilities
	 */
	public static function maybe_hide_widget( $instance ) {

		$current_user = wp_get_current_user();

		return ( ! self::members_can_user_view_widget( $current_user, $instance ) ) ? false : $instance;

	}

	/**
	 * Returns true if this widget should be hidden for the current user.
	 */
	public static function members_can_user_view_widget( $user_id, $instance ) {

		//Assume the user can view the widget at this point.
		$can_view = true;

		// Get the roles selected by the user.
		$roles = $instance['members_access_role'];

		// If we have an array of roles, let's get to work.
		if ( !empty( $roles ) && is_array( $roles ) ) {

			/**
			 * Since specific roles were given, let's assume the user can't view the post at 
			 * this point.  The rest of this functionality should try to disprove this.
			 */
			$can_view = false;

			// If the user's not logged in, assume it's blocked at this point.
			if ( !is_user_logged_in() ) {
				$can_view = false;
			}

			// If the current user can 'restrict_content', return true.
			elseif ( user_can( $user_id, 'restrict_content' ) ) {
				$can_view = true;
			}

			// Else, let's check the user's role against the selected roles.
			else {

				// Loop through each role and set $can_view to true if the user has one of the roles.
				foreach ( $roles as $role ) {

					if ( user_can( $user_id, $role ) ) {
						$can_view = true;
					}
				}
			}
		}

		// Allow developers to overwrite the final return value.
		return apply_filters( 'members_can_user_view_widget', $can_view, $user_id, $instance );
	}

}; // end class

endif;
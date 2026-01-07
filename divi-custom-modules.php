<?php
/*
Plugin Name: Divi Custom Modules
Plugin URI:  https://garciajoemari.com/
Description: Plugin for Divi custom modules
Version:     1.0.0
Author:      Jo Garcia
Author URI:  https://garciajoemari.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dicm-divi-custom-modules
Domain Path: /languages

Divi Custom Modules is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Divi Custom Modules is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Divi Custom Modules. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/


if ( ! function_exists( 'dicm_initialize_extension' ) ):
/**
 * Creates the extension's main class instance.
 *
 * @since 1.0.0
 */
function dicm_initialize_extension() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/DiviCustomModules.php';
}
add_action( 'divi_extensions_init', 'dicm_initialize_extension' );
endif;

// Simple AJAX handlers for TimesheetTracker (procedural approach)
function dicm_timesheet_save_entry() {
	error_log('TimesheetTracker: dicm_timesheet_save_entry called');
	
	// Verify user is logged in
	if ( ! is_user_logged_in() ) {
		error_log('TimesheetTracker: User not logged in');
		wp_send_json_error( array( 'message' => 'User not logged in' ) );
	}
	
	// Debug nonce
	if ( ! isset( $_POST['nonce'] ) ) {
		error_log('TimesheetTracker: No nonce in POST data');
		wp_send_json_error( array( 'message' => 'No nonce provided' ) );
	}
	
	$nonce_check = wp_verify_nonce( $_POST['nonce'], 'timesheet_tracker_nonce' );
	error_log('TimesheetTracker: Nonce check result: ' . ($nonce_check ? 'PASS' : 'FAIL'));
	
	if ( ! $nonce_check ) {
		error_log('TimesheetTracker: Nonce verification failed');
		wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
	}
	
	global $wpdb;
	$table_name = $wpdb->prefix . 'timesheet_entries';
	$current_user_id = get_current_user_id();

	// Create table if it doesn't exist
	dicm_create_timesheet_table();

	// Sanitize input data
	$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;
	$entry_date = sanitize_text_field( $_POST['entry_date'] );
	$project = sanitize_text_field( $_POST['project'] );
	$tasks = sanitize_textarea_field( $_POST['tasks'] );
	$notes = sanitize_textarea_field( $_POST['notes'] );
	$hours = floatval( $_POST['hours'] );
	$billable_rate = floatval( $_POST['billable_rate'] );
	$billable_amount = $hours * $billable_rate;

	$data = array(
		'user_id' => $current_user_id,
		'entry_date' => $entry_date,
		'project' => $project,
		'tasks' => $tasks,
		'notes' => $notes,
		'hours' => $hours,
		'billable_rate' => $billable_rate,
		'billable_amount' => $billable_amount,
	);

	if ( $entry_id > 0 ) {
		// Update existing entry
		$existing = $wpdb->get_row( $wpdb->prepare( 
			"SELECT user_id FROM $table_name WHERE id = %d", 
			$entry_id 
		) );
		
		if ( $existing && $existing->user_id == $current_user_id ) {
			$result = $wpdb->update( $table_name, $data, array( 'id' => $entry_id ) );
			wp_send_json_success( array( 
				'entry_id' => $entry_id,
				'message' => 'Entry updated successfully' 
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Entry not found or access denied' ) );
		}
	} else {
		// Create new entry
		$result = $wpdb->insert( $table_name, $data );
		if ( $result !== false ) {
			wp_send_json_success( array( 
				'entry_id' => $wpdb->insert_id,
				'message' => 'Entry saved successfully' 
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to save entry. Database error: ' . $wpdb->last_error ) );
		}
	}
}

function dicm_timesheet_load_entries() {
	error_log('TimesheetTracker: dicm_timesheet_load_entries called');
	
	if ( ! is_user_logged_in() || ! wp_verify_nonce( $_POST['nonce'], 'timesheet_tracker_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'timesheet_entries';
	$current_user_id = get_current_user_id();

	// Get optional date range parameters for filtering
	$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
	$end_date = sanitize_text_field( $_POST['end_date'] ?? '' );

	$where_clause = "WHERE user_id = %d";
	$params = array( $current_user_id );

	if ( $start_date ) {
		$where_clause .= " AND entry_date >= %s";
		$params[] = $start_date;
	}
	if ( $end_date ) {
		$where_clause .= " AND entry_date <= %s";
		$params[] = $end_date;
	}

	$entries = $wpdb->get_results( $wpdb->prepare( 
		"SELECT * FROM $table_name $where_clause ORDER BY entry_date DESC, created_at DESC",
		$params
	) );

	wp_send_json_success( array( 'entries' => $entries ) );
}

function dicm_timesheet_delete_entry() {
	error_log('TimesheetTracker: dicm_timesheet_delete_entry called');
	
	if ( ! is_user_logged_in() || ! wp_verify_nonce( $_POST['nonce'], 'timesheet_tracker_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'timesheet_entries';
	$current_user_id = get_current_user_id();
	$entry_id = intval( $_POST['entry_id'] );

	$existing = $wpdb->get_row( $wpdb->prepare( 
		"SELECT user_id FROM $table_name WHERE id = %d", 
		$entry_id 
	) );

	if ( $existing && $existing->user_id == $current_user_id ) {
		$result = $wpdb->delete( $table_name, array( 'id' => $entry_id ) );
		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => 'Entry deleted successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete entry' ) );
		}
	} else {
		wp_send_json_error( array( 'message' => 'Entry not found or access denied' ) );
	}
}

function dicm_create_timesheet_table() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'timesheet_entries';
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			entry_date date NOT NULL,
			project varchar(255) DEFAULT '',
			tasks text DEFAULT '',
			notes text DEFAULT '',
			hours decimal(8,2) DEFAULT 0.00,
			billable_rate decimal(10,2) DEFAULT 0.00,
			billable_amount decimal(12,2) DEFAULT 0.00,
			timer_seconds int DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_date (user_id, entry_date),
			KEY user_id (user_id)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		error_log('TimesheetTracker: Database table created');
	}
}

function dicm_timesheet_load_public_entries() {
	error_log('TimesheetTracker: dicm_timesheet_load_public_entries called');
	
	global $wpdb;
	$table_name = $wpdb->prefix . 'timesheet_entries';
	
	// Get filter parameter and custom date ranges
	$filter = sanitize_text_field( $_POST['filter'] ?? 'this_week' );
	$custom_start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
	$custom_end_date = sanitize_text_field( $_POST['end_date'] ?? '' );
	
	// Calculate date ranges based on filter
	$start_date = '';
	$end_date = '';
	
	// Handle custom date range
	if ( $filter === 'custom' && $custom_start_date && $custom_end_date ) {
		$start_date = $custom_start_date;
		$end_date = $custom_end_date;
	} else {
		// Handle predefined filters
		switch( $filter ) {
		case 'this_week':
			$start_date = date('Y-m-d', strtotime('monday this week'));
			$end_date = date('Y-m-d', strtotime('sunday this week'));
			break;
		case 'last_week':
			$start_date = date('Y-m-d', strtotime('monday last week'));
			$end_date = date('Y-m-d', strtotime('sunday last week'));
			break;
		case 'this_month':
			$start_date = date('Y-m-01');
			$end_date = date('Y-m-t');
			break;
		case 'last_month':
			$start_date = date('Y-m-01', strtotime('first day of last month'));
			$end_date = date('Y-m-t', strtotime('last day of last month'));
			break;
		default:
			// Default to this week
			$start_date = date('Y-m-d', strtotime('monday this week'));
			$end_date = date('Y-m-d', strtotime('sunday this week'));
		}
	}
	
	error_log("TimesheetTracker: Loading public entries from $start_date to $end_date");
	
	// Query entries within date range - exclude user info and financial data for privacy
	$entries = $wpdb->get_results( $wpdb->prepare( 
		"SELECT 
			entry_date, 
			project, 
			tasks, 
			notes, 
			hours 
		FROM $table_name 
		WHERE entry_date BETWEEN %s AND %s 
		ORDER BY entry_date DESC, id DESC",
		$start_date, 
		$end_date 
	) );
	
	// Get user details of contributors who have timesheet entries (for public transparency)
	$contributors = $wpdb->get_results( 
		"SELECT DISTINCT 
			u.display_name as name,
			u.user_email as email,
			MAX(t.updated_at) as last_activity,
			SUM(t.hours) as total_hours,
			COUNT(t.id) as total_entries
		FROM {$wpdb->users} u 
		JOIN $table_name t ON u.ID = t.user_id 
		GROUP BY u.ID, u.display_name, u.user_email 
		ORDER BY last_activity DESC"
	);
	
	if ( $entries ) {
		error_log('TimesheetTracker: Found ' . count($entries) . ' public entries and ' . count($contributors) . ' contributors');
		wp_send_json_success( array( 
			'entries' => $entries,
			'contributors' => $contributors,
			'filter' => $filter,
			'date_range' => array(
				'start' => $start_date,
				'end' => $end_date
			)
		) );
	} else {
		error_log('TimesheetTracker: No public entries found');
		wp_send_json_success( array( 
			'entries' => array(),
			'contributors' => $contributors || array(),
			'filter' => $filter,
			'date_range' => array(
				'start' => $start_date,
				'end' => $end_date
			)
		) );
	}
}

// Register AJAX handlers
add_action( 'wp_ajax_timesheet_save_entry', 'dicm_timesheet_save_entry' );
add_action( 'wp_ajax_timesheet_load_entries', 'dicm_timesheet_load_entries' );
add_action( 'wp_ajax_timesheet_delete_entry', 'dicm_timesheet_delete_entry' );

// Public data endpoint - available to both logged and non-logged users
add_action( 'wp_ajax_timesheet_load_public_entries', 'dicm_timesheet_load_public_entries' );
add_action( 'wp_ajax_nopriv_timesheet_load_public_entries', 'dicm_timesheet_load_public_entries' );

// Create table on plugin load
add_action( 'init', 'dicm_create_timesheet_table' );

// Track user login times for user details display
function dicm_track_user_login( $user_login, $user ) {
	update_user_meta( $user->ID, 'last_login', current_time( 'mysql' ) );
}
add_action( 'wp_login', 'dicm_track_user_login', 10, 2 );

error_log('TimesheetTracker: Procedural AJAX handlers registered');

// ==================== PROJECT MANAGER AJAX HANDLERS ====================

/**
 * Verify user access and nonce for Project Manager AJAX requests
 */
function dicm_pm_verify_request() {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Authentication required' ), 401 );
		exit;
	}
	
	$nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '' );
	
	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'project_manager_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ), 403 );
		exit;
	}
	
	return get_current_user_id();
}

/**
 * Check if user has access to view a project
 * All logged-in users can view projects and tasks
 */
function dicm_pm_user_has_project_access( $project_id, $user_id ) {
	// All logged-in users can view any project and its tasks
	return is_user_logged_in();
}

/**
 * Check if user can manage project settings (edit/delete project, manage columns)
 * Only project owners and admins can manage projects
 */
function dicm_pm_user_can_manage_project( $project_id, $user_id ) {
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	// Admins can manage any project
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	
	// Check if user is the owner
	$is_owner = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $projects_table WHERE id = %d AND owner_id = %d",
		$project_id, $user_id
	) );
	
	return $is_owner > 0;
}

/**
 * Create Project Manager database tables if they don't exist
 */
function dicm_pm_ensure_tables() {
	global $wpdb;
	
	$charset_collate = $wpdb->get_charset_collate();
	
	$projects_table = $wpdb->prefix . 'pm_projects';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$projects_table'" ) != $projects_table ) {
		$sql = "CREATE TABLE $projects_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			color varchar(20) DEFAULT '#3b82f6',
			owner_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			archived tinyint(1) DEFAULT 0,
			PRIMARY KEY (id),
			KEY owner_id (owner_id)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$statuses_table'" ) != $statuses_table ) {
		$sql = "CREATE TABLE $statuses_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED NOT NULL,
			name varchar(100) NOT NULL,
			color varchar(20) DEFAULT '#6b7280',
			order_index int DEFAULT 0,
			is_default tinyint(1) DEFAULT 0,
			is_done tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY order_index (order_index)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$tasks_table'" ) != $tasks_table ) {
		$sql = "CREATE TABLE $tasks_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED NOT NULL,
			status_id bigint(20) UNSIGNED NOT NULL,
			title varchar(500) NOT NULL,
			description text,
			priority varchar(20) DEFAULT 'Medium',
			due_date date DEFAULT NULL,
			assignee_id bigint(20) UNSIGNED DEFAULT NULL,
			creator_id bigint(20) UNSIGNED NOT NULL,
			order_index int DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY project_id (project_id),
			KEY status_id (status_id),
			KEY assignee_id (assignee_id),
			KEY order_index (order_index),
			KEY due_date (due_date)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	$members_table = $wpdb->prefix . 'pm_project_members';
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$members_table'" ) != $members_table ) {
		$sql = "CREATE TABLE $members_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			role varchar(50) DEFAULT 'member',
			added_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY project_user (project_id, user_id),
			KEY project_id (project_id),
			KEY user_id (user_id)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}

// ==================== PROJECT HANDLERS ====================

function dicm_pm_ajax_get_projects() {
	$user_id = dicm_pm_verify_request();
	dicm_pm_ensure_tables();
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	// Get ALL non-archived projects - visible to all logged-in users
	$projects = $wpdb->get_results(
		"SELECT * FROM $projects_table WHERE archived = 0 ORDER BY created_at DESC"
	);
	
	foreach ( $projects as &$project ) {
		$owner = get_userdata( $project->owner_id );
		$project->owner_name = $owner ? $owner->display_name : 'Unknown';
		// Mark if current user is owner (for edit/delete permissions in UI)
		$project->is_owner = ( $project->owner_id == $user_id );
		// Also mark if user can manage (owner or admin)
		$project->can_manage = ( $project->owner_id == $user_id || current_user_can( 'manage_options' ) );
	}
	
	wp_send_json_success( array( 'projects' => $projects ) );
}
add_action( 'wp_ajax_pm_get_projects', 'dicm_pm_ajax_get_projects' );

function dicm_pm_ajax_create_project() {
	$user_id = dicm_pm_verify_request();
	dicm_pm_ensure_tables();
	
	// Only admins can create projects
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Only administrators can create projects' ) );
	}
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$name = sanitize_text_field( $_POST['name'] );
	$description = sanitize_textarea_field( isset( $_POST['description'] ) ? $_POST['description'] : '' );
	$color = sanitize_hex_color( isset( $_POST['color'] ) ? $_POST['color'] : '#3b82f6' );
	$default_statuses = isset( $_POST['default_statuses'] ) ? $_POST['default_statuses'] : array();
	
	if ( empty( $name ) ) {
		wp_send_json_error( array( 'message' => 'Project name is required' ) );
	}
	
	$result = $wpdb->insert( $projects_table, array(
		'name' => $name,
		'description' => $description,
		'color' => $color,
		'owner_id' => $user_id
	) );
	
	if ( $result === false ) {
		wp_send_json_error( array( 'message' => 'Failed to create project' ) );
	}
	
	$project_id = $wpdb->insert_id;
	
	if ( ! empty( $default_statuses ) ) {
		foreach ( $default_statuses as $index => $status ) {
			$wpdb->insert( $statuses_table, array(
				'project_id' => $project_id,
				'name' => sanitize_text_field( $status['name'] ),
				'color' => sanitize_hex_color( isset( $status['color'] ) ? $status['color'] : '#6b7280' ),
				'order_index' => $index,
				'is_default' => $index === 0 ? 1 : 0,
				'is_done' => $index === count( $default_statuses ) - 1 ? 1 : 0
			) );
		}
	}
	
	wp_send_json_success( array(
		'message' => 'Project created successfully',
		'project_id' => $project_id
	) );
}
add_action( 'wp_ajax_pm_create_project', 'dicm_pm_ajax_create_project' );

function dicm_pm_ajax_update_project() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	$project_id = intval( $_POST['project_id'] );
	
	// Only owners and admins can update project settings
	if ( ! dicm_pm_user_can_manage_project( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Only project owners can edit project settings' ) );
	}
	
	$data = array();
	if ( isset( $_POST['name'] ) ) $data['name'] = sanitize_text_field( $_POST['name'] );
	if ( isset( $_POST['description'] ) ) $data['description'] = sanitize_textarea_field( $_POST['description'] );
	if ( isset( $_POST['color'] ) ) $data['color'] = sanitize_hex_color( $_POST['color'] );
	if ( isset( $_POST['archived'] ) ) $data['archived'] = intval( $_POST['archived'] );
	
	if ( empty( $data ) ) {
		wp_send_json_error( array( 'message' => 'No data to update' ) );
	}
	
	$wpdb->update( $projects_table, $data, array( 'id' => $project_id ) );
	
	wp_send_json_success( array( 'message' => 'Project updated successfully' ) );
}
add_action( 'wp_ajax_pm_update_project', 'dicm_pm_ajax_update_project' );

function dicm_pm_ajax_delete_project() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	$project_id = intval( $_POST['project_id'] );
	
	$project = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $projects_table WHERE id = %d",
		$project_id
	) );
	
	if ( ! $project || ! dicm_pm_user_can_manage_project( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Only the project owner or admin can delete this project' ) );
	}
	
	$wpdb->delete( $wpdb->prefix . 'pm_tasks', array( 'project_id' => $project_id ) );
	$wpdb->delete( $wpdb->prefix . 'pm_statuses', array( 'project_id' => $project_id ) );
	$wpdb->delete( $wpdb->prefix . 'pm_project_members', array( 'project_id' => $project_id ) );
	$wpdb->delete( $projects_table, array( 'id' => $project_id ) );
	
	wp_send_json_success( array( 'message' => 'Project deleted successfully' ) );
}
add_action( 'wp_ajax_pm_delete_project', 'dicm_pm_ajax_delete_project' );

function dicm_pm_ajax_toggle_project_share() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	$project_id = intval( $_POST['project_id'] );
	
	// Get project to check ownership
	$project = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $projects_table WHERE id = %d",
		$project_id
	) );
	
	if ( ! $project || ( $project->owner_id != $user_id && ! current_user_can( 'manage_options' ) ) ) {
		wp_send_json_error( array( 'message' => 'Only the project owner can manage sharing' ) );
	}
	
	$is_public = isset( $_POST['is_public'] ) ? intval( $_POST['is_public'] ) : 0;
	
	// Generate share token if enabling public sharing and token doesn't exist
	if ( $is_public && empty( $project->share_token ) ) {
		$share_token = bin2hex( random_bytes( 16 ) ); // 32 character token
		$wpdb->update( 
			$projects_table, 
			array( 
				'is_public' => $is_public,
				'share_token' => $share_token
			), 
			array( 'id' => $project_id ) 
		);
	} else {
		$wpdb->update( 
			$projects_table, 
			array( 'is_public' => $is_public ), 
			array( 'id' => $project_id ) 
		);
	}
	
	// Get updated project
	$updated_project = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $projects_table WHERE id = %d",
		$project_id
	) );
	
	// Generate share URL
	$share_url = $is_public && $updated_project->share_token 
		? home_url( '?pm_share=' . $updated_project->share_token )
		: null;
	
	wp_send_json_success( array( 
		'message' => 'Share settings updated successfully',
		'is_public' => intval( $updated_project->is_public ),
		'share_token' => $updated_project->share_token,
		'share_url' => $share_url
	) );
}
add_action( 'wp_ajax_pm_toggle_project_share', 'dicm_pm_ajax_toggle_project_share' );

function dicm_pm_ajax_regenerate_share_token() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$projects_table = $wpdb->prefix . 'pm_projects';
	
	$project_id = intval( $_POST['project_id'] );
	
	// Get project to check ownership
	$project = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $projects_table WHERE id = %d",
		$project_id
	) );
	
	if ( ! $project || ( $project->owner_id != $user_id && ! current_user_can( 'manage_options' ) ) ) {
		wp_send_json_error( array( 'message' => 'Only the project owner can manage sharing' ) );
	}
	
	// Generate new share token
	$share_token = bin2hex( random_bytes( 16 ) );
	$wpdb->update( 
		$projects_table, 
		array( 'share_token' => $share_token ), 
		array( 'id' => $project_id ) 
	);
	
	$share_url = home_url( '?pm_share=' . $share_token );
	
	wp_send_json_success( array( 
		'message' => 'Share link regenerated successfully',
		'share_token' => $share_token,
		'share_url' => $share_url
	) );
}
add_action( 'wp_ajax_pm_regenerate_share_token', 'dicm_pm_ajax_regenerate_share_token' );

// ==================== STATUS HANDLERS ====================

function dicm_pm_ajax_get_statuses() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$project_id = intval( $_POST['project_id'] );
	
	if ( ! dicm_pm_user_has_project_access( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$statuses = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $statuses_table WHERE project_id = %d ORDER BY order_index ASC",
		$project_id
	) );
	
	wp_send_json_success( array( 'statuses' => $statuses ) );
}
add_action( 'wp_ajax_pm_get_statuses', 'dicm_pm_ajax_get_statuses' );

function dicm_pm_ajax_create_status() {
	$user_id = dicm_pm_verify_request();
	
	// Only admins can create columns/statuses
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Only administrators can create columns' ) );
	}
	
	global $wpdb;
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$project_id = intval( $_POST['project_id'] );
	$name = sanitize_text_field( $_POST['name'] );
	$color = sanitize_hex_color( isset( $_POST['color'] ) ? $_POST['color'] : '#6b7280' );
	
	// Check if user has access to the project
	if ( ! dicm_pm_user_has_project_access( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$max_order = $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(order_index) FROM $statuses_table WHERE project_id = %d",
		$project_id
	) );
	
	$result = $wpdb->insert( $statuses_table, array(
		'project_id' => $project_id,
		'name' => $name,
		'color' => $color,
		'order_index' => ( $max_order ?? -1 ) + 1
	) );
	
	if ( $result === false ) {
		wp_send_json_error( array( 'message' => 'Failed to create status' ) );
	}
	
	wp_send_json_success( array(
		'message' => 'Status created successfully',
		'status_id' => $wpdb->insert_id
	) );
}
add_action( 'wp_ajax_pm_create_status', 'dicm_pm_ajax_create_status' );

function dicm_pm_ajax_update_status() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$status_id = intval( $_POST['status_id'] );
	
	$status = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $statuses_table WHERE id = %d",
		$status_id
	) );
	
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => 'Status not found' ) );
	}
	
	// Only admins and project owners can edit columns
	if ( ! dicm_pm_user_can_manage_project( $status->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Only project owners and admins can edit columns' ) );
	}
	
	$data = array();
	if ( isset( $_POST['name'] ) ) $data['name'] = sanitize_text_field( $_POST['name'] );
	if ( isset( $_POST['color'] ) ) $data['color'] = sanitize_hex_color( $_POST['color'] );
	if ( isset( $_POST['is_done'] ) ) $data['is_done'] = intval( $_POST['is_done'] );
	
	$wpdb->update( $statuses_table, $data, array( 'id' => $status_id ) );
	
	wp_send_json_success( array( 'message' => 'Status updated successfully' ) );
}
add_action( 'wp_ajax_pm_update_status', 'dicm_pm_ajax_update_status' );

function dicm_pm_ajax_delete_status() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$status_id = intval( $_POST['status_id'] );
	$move_tasks_to = isset( $_POST['move_tasks_to'] ) ? intval( $_POST['move_tasks_to'] ) : null;
	
	$status = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $statuses_table WHERE id = %d",
		$status_id
	) );
	
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => 'Status not found' ) );
	}
	
	// Only admins and project owners can delete columns
	if ( ! dicm_pm_user_can_manage_project( $status->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Only project owners and admins can delete columns' ) );
	}
	
	if ( $move_tasks_to ) {
		$wpdb->update( $tasks_table,
			array( 'status_id' => $move_tasks_to ),
			array( 'status_id' => $status_id )
		);
	} else {
		$wpdb->delete( $tasks_table, array( 'status_id' => $status_id ) );
	}
	
	$wpdb->delete( $statuses_table, array( 'id' => $status_id ) );
	
	wp_send_json_success( array( 'message' => 'Status deleted successfully' ) );
}
add_action( 'wp_ajax_pm_delete_status', 'dicm_pm_ajax_delete_status' );

function dicm_pm_ajax_reorder_statuses() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$status_order = isset( $_POST['status_order'] ) ? $_POST['status_order'] : array();
	$project_id = intval( $_POST['project_id'] );
	
	// Only admins and project owners can reorder columns
	if ( ! dicm_pm_user_can_manage_project( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Only project owners and admins can reorder columns' ) );
	}
	
	foreach ( $status_order as $index => $status_id ) {
		$wpdb->update( $statuses_table,
			array( 'order_index' => $index ),
			array( 'id' => intval( $status_id ), 'project_id' => $project_id )
		);
	}
	
	wp_send_json_success( array( 'message' => 'Statuses reordered successfully' ) );
}
add_action( 'wp_ajax_pm_reorder_statuses', 'dicm_pm_ajax_reorder_statuses' );

// ==================== TASK HANDLERS ====================

function dicm_pm_ajax_get_tasks() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$project_id = intval( $_POST['project_id'] );
	
	if ( ! dicm_pm_user_has_project_access( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$tasks = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE project_id = %d ORDER BY status_id, order_index ASC",
		$project_id
	) );
	
	foreach ( $tasks as &$task ) {
		if ( $task->assignee_id ) {
			$assignee = get_userdata( $task->assignee_id );
			$task->assignee_name = $assignee ? $assignee->display_name : 'Unknown';
			$task->assignee_avatar = get_avatar_url( $task->assignee_id, array( 'size' => 32 ) );
		}
		$creator = get_userdata( $task->creator_id );
		$task->creator_name = $creator ? $creator->display_name : 'Unknown';
	}
	
	wp_send_json_success( array( 'tasks' => $tasks ) );
}
add_action( 'wp_ajax_pm_get_tasks', 'dicm_pm_ajax_get_tasks' );

function dicm_pm_ajax_create_task() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$project_id = intval( $_POST['project_id'] );
	$status_id = intval( $_POST['status_id'] );
	$title = sanitize_text_field( $_POST['title'] );
	$description = sanitize_textarea_field( isset( $_POST['description'] ) ? $_POST['description'] : '' );
	$priority = sanitize_text_field( isset( $_POST['priority'] ) ? $_POST['priority'] : 'Medium' );
	$due_date = isset( $_POST['due_date'] ) && ! empty( $_POST['due_date'] ) ? sanitize_text_field( $_POST['due_date'] ) : null;
	$assignee_id = isset( $_POST['assignee_id'] ) && ! empty( $_POST['assignee_id'] ) ? intval( $_POST['assignee_id'] ) : null;
	
	if ( ! dicm_pm_user_has_project_access( $project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	if ( empty( $title ) ) {
		wp_send_json_error( array( 'message' => 'Task title is required' ) );
	}
	
	$max_order = $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(order_index) FROM $tasks_table WHERE status_id = %d",
		$status_id
	) );
	
	$result = $wpdb->insert( $tasks_table, array(
		'project_id' => $project_id,
		'status_id' => $status_id,
		'title' => $title,
		'description' => $description,
		'priority' => $priority,
		'due_date' => $due_date,
		'assignee_id' => $assignee_id,
		'creator_id' => $user_id,
		'order_index' => ( $max_order ?? -1 ) + 1
	) );
	
	if ( $result === false ) {
		wp_send_json_error( array( 'message' => 'Failed to create task' ) );
	}
	
	$task_id = $wpdb->insert_id;
	$task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tasks_table WHERE id = %d", $task_id ) );
	
	if ( $task->assignee_id ) {
		$assignee = get_userdata( $task->assignee_id );
		$task->assignee_name = $assignee ? $assignee->display_name : 'Unknown';
		$task->assignee_avatar = get_avatar_url( $task->assignee_id, array( 'size' => 32 ) );
	}
	
	wp_send_json_success( array(
		'message' => 'Task created successfully',
		'task' => $task
	) );
}
add_action( 'wp_ajax_pm_create_task', 'dicm_pm_ajax_create_task' );

function dicm_pm_ajax_update_task() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$task_id = intval( $_POST['task_id'] );
	
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$data = array();
	if ( isset( $_POST['title'] ) ) $data['title'] = sanitize_text_field( $_POST['title'] );
	if ( isset( $_POST['description'] ) ) $data['description'] = sanitize_textarea_field( $_POST['description'] );
	if ( isset( $_POST['priority'] ) ) $data['priority'] = sanitize_text_field( $_POST['priority'] );
	if ( isset( $_POST['due_date'] ) ) $data['due_date'] = ! empty( $_POST['due_date'] ) ? sanitize_text_field( $_POST['due_date'] ) : null;
	if ( isset( $_POST['assignee_id'] ) ) $data['assignee_id'] = ! empty( $_POST['assignee_id'] ) ? intval( $_POST['assignee_id'] ) : null;
	
	if ( empty( $data ) ) {
		wp_send_json_error( array( 'message' => 'No data to update' ) );
	}
	
	$wpdb->update( $tasks_table, $data, array( 'id' => $task_id ) );
	
	$updated_task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tasks_table WHERE id = %d", $task_id ) );
	
	if ( $updated_task->assignee_id ) {
		$assignee = get_userdata( $updated_task->assignee_id );
		$updated_task->assignee_name = $assignee ? $assignee->display_name : 'Unknown';
		$updated_task->assignee_avatar = get_avatar_url( $updated_task->assignee_id, array( 'size' => 32 ) );
	}
	
	wp_send_json_success( array(
		'message' => 'Task updated successfully',
		'task' => $updated_task
	) );
}
add_action( 'wp_ajax_pm_update_task', 'dicm_pm_ajax_update_task' );

function dicm_pm_ajax_delete_task() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$task_id = intval( $_POST['task_id'] );
	
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$wpdb->delete( $tasks_table, array( 'id' => $task_id ) );
	
	wp_send_json_success( array( 'message' => 'Task deleted successfully' ) );
}
add_action( 'wp_ajax_pm_delete_task', 'dicm_pm_ajax_delete_task' );

function dicm_pm_ajax_move_task() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	$statuses_table = $wpdb->prefix . 'pm_statuses';
	
	$task_id = intval( $_POST['task_id'] );
	$new_status_id = intval( $_POST['new_status_id'] );
	$new_order = intval( isset( $_POST['new_order'] ) ? $_POST['new_order'] : 0 );
	
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$status = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $statuses_table WHERE id = %d AND project_id = %d",
		$new_status_id, $task->project_id
	) );
	
	if ( ! $status ) {
		wp_send_json_error( array( 'message' => 'Invalid status' ) );
	}
	
	$update_data = array(
		'status_id' => $new_status_id,
		'order_index' => $new_order
	);
	
	if ( $status->is_done ) {
		$update_data['completed_at'] = current_time( 'mysql' );
	} else {
		$update_data['completed_at'] = null;
	}
	
	$wpdb->update( $tasks_table, $update_data, array( 'id' => $task_id ) );
	
	wp_send_json_success( array( 'message' => 'Task moved successfully' ) );
}
add_action( 'wp_ajax_pm_move_task', 'dicm_pm_ajax_move_task' );

function dicm_pm_ajax_reorder_tasks() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$task_order = isset( $_POST['task_order'] ) ? $_POST['task_order'] : array();
	$status_id = intval( $_POST['status_id'] );
	
	if ( ! empty( $task_order ) ) {
		$first_task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			intval( $task_order[0] )
		) );
		
		if ( ! $first_task || ! dicm_pm_user_has_project_access( $first_task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
	}
	
	foreach ( $task_order as $index => $task_id ) {
		$wpdb->update( $tasks_table,
			array( 'order_index' => $index, 'status_id' => $status_id ),
			array( 'id' => intval( $task_id ) )
		);
	}
	
	wp_send_json_success( array( 'message' => 'Tasks reordered successfully' ) );
}
add_action( 'wp_ajax_pm_reorder_tasks', 'dicm_pm_ajax_reorder_tasks' );

// ==================== USER HANDLER ====================

function dicm_pm_ajax_get_users() {
	dicm_pm_verify_request();
	
	$users = get_users( array(
		'number' => 50,
		'orderby' => 'display_name',
		'order' => 'ASC'
	) );
	
	$user_list = array();
	foreach ( $users as $user ) {
		$user_list[] = array(
			'id' => $user->ID,
			'name' => $user->display_name,
			'email' => $user->user_email,
			'avatar' => get_avatar_url( $user->ID, array( 'size' => 32 ) )
		);
	}
	
	wp_send_json_success( array( 'users' => $user_list ) );
}
add_action( 'wp_ajax_pm_get_users', 'dicm_pm_ajax_get_users' );

// ==================== DAILY TASK ENTRIES HANDLERS ====================

function dicm_pm_ajax_get_daily_task_entries() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$task_id = intval( $_POST['task_id'] );
	
	// Verify access to task's project
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$entries = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $daily_tasks_table WHERE task_id = %d ORDER BY start_time ASC",
		$task_id
	) );
	
	wp_send_json_success( array( 'entries' => $entries ) );
}
add_action( 'wp_ajax_pm_get_daily_task_entries', 'dicm_pm_ajax_get_daily_task_entries' );

function dicm_pm_ajax_create_daily_task_entry() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$task_id = intval( $_POST['task_id'] );
	$description = sanitize_textarea_field( $_POST['description'] ?? '' );
	$start_time = sanitize_text_field( $_POST['start_time'] );
	$end_time = sanitize_text_field( $_POST['end_time'] );
	
	// Verify access to task's project
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$result = $wpdb->insert( $daily_tasks_table, array(
		'task_id' => $task_id,
		'description' => $description,
		'start_time' => $start_time,
		'end_time' => $end_time
	) );
	
	if ( $result === false ) {
		wp_send_json_error( array( 'message' => 'Failed to create daily task entry' ) );
	}
	
	$entry_id = $wpdb->insert_id;
	$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $daily_tasks_table WHERE id = %d", $entry_id ) );
	
	wp_send_json_success( array(
		'message' => 'Daily task entry created successfully',
		'entry' => $entry
	) );
}
add_action( 'wp_ajax_pm_create_daily_task_entry', 'dicm_pm_ajax_create_daily_task_entry' );

function dicm_pm_ajax_update_daily_task_entry() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$entry_id = intval( $_POST['entry_id'] );
	
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $daily_tasks_table WHERE id = %d",
		$entry_id
	) );
	
	if ( ! $entry ) {
		wp_send_json_error( array( 'message' => 'Entry not found' ) );
	}
	
	// Verify access to task's project
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$entry->task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$data = array();
	if ( isset( $_POST['description'] ) ) $data['description'] = sanitize_textarea_field( $_POST['description'] );
	if ( isset( $_POST['start_time'] ) ) $data['start_time'] = sanitize_text_field( $_POST['start_time'] );
	if ( isset( $_POST['end_time'] ) ) $data['end_time'] = sanitize_text_field( $_POST['end_time'] );
	
	if ( empty( $data ) ) {
		wp_send_json_error( array( 'message' => 'No data to update' ) );
	}
	
	$wpdb->update( $daily_tasks_table, $data, array( 'id' => $entry_id ) );
	
	$updated_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $daily_tasks_table WHERE id = %d", $entry_id ) );
	
	wp_send_json_success( array(
		'message' => 'Daily task entry updated successfully',
		'entry' => $updated_entry
	) );
}
add_action( 'wp_ajax_pm_update_daily_task_entry', 'dicm_pm_ajax_update_daily_task_entry' );

function dicm_pm_ajax_delete_daily_task_entry() {
	$user_id = dicm_pm_verify_request();
	
	global $wpdb;
	$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
	$tasks_table = $wpdb->prefix . 'pm_tasks';
	
	$entry_id = intval( $_POST['entry_id'] );
	
	$entry = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $daily_tasks_table WHERE id = %d",
		$entry_id
	) );
	
	if ( ! $entry ) {
		wp_send_json_error( array( 'message' => 'Entry not found' ) );
	}
	
	// Verify access to task's project
	$task = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $tasks_table WHERE id = %d",
		$entry->task_id
	) );
	
	if ( ! $task || ! dicm_pm_user_has_project_access( $task->project_id, $user_id ) ) {
		wp_send_json_error( array( 'message' => 'Access denied' ) );
	}
	
	$wpdb->delete( $daily_tasks_table, array( 'id' => $entry_id ) );
	
	wp_send_json_success( array( 'message' => 'Daily task entry deleted successfully' ) );
}
add_action( 'wp_ajax_pm_delete_daily_task_entry', 'dicm_pm_ajax_delete_daily_task_entry' );

// Initialize Project Manager tables on plugin load
add_action( 'init', 'dicm_pm_ensure_tables' );

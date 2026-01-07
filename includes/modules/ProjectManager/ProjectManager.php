<?php

class DICM_ProjectManager extends ET_Builder_Module {

	public $slug       = 'dicm_project_manager';
	public $vb_support = 'on';

	protected $module_credits = array(
		'module_uri' => 'https://garciajoemari.com/',
		'author'     => 'Jo Garcia',
		'author_uri' => 'https://garciajoemari.com/',
	);

	public function init() {
		$this->name = esc_html__( 'Project Manager', 'dicm-divi-custom-modules' );
		$this->icon_path = plugin_dir_path( __FILE__ ) . 'icon.svg';
		
		$this->main_css_element = '%%order_class%%';
		
		// Enqueue frontend script for React initialization
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		
		// Create database tables on init
		$this->create_tables();
		
		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Project Settings', 'dicm-divi-custom-modules' ),
					'default_statuses' => esc_html__( 'Default Statuses', 'dicm-divi-custom-modules' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'kanban_style' => esc_html__( 'Kanban Board Style', 'dicm-divi-custom-modules' ),
					'card_style' => esc_html__( 'Task Card Style', 'dicm-divi-custom-modules' ),
				),
			),
		);

		$this->advanced_fields = array(
			'fonts' => array(
				'header' => array(
					'label' => esc_html__( 'Header', 'dicm-divi-custom-modules' ),
					'css' => array(
						'main' => "%%order_class%% .pm-header h2",
					),
					'toggle_slug' => 'header',
				),
				'column_header' => array(
					'label' => esc_html__( 'Column Header', 'dicm-divi-custom-modules' ),
					'css' => array(
						'main' => "%%order_class%% .pm-column-header",
					),
					'toggle_slug' => 'kanban_style',
				),
				'task_title' => array(
					'label' => esc_html__( 'Task Title', 'dicm-divi-custom-modules' ),
					'css' => array(
						'main' => "%%order_class%% .pm-task-title",
					),
					'toggle_slug' => 'card_style',
				),
			),
			'background' => array(
				'settings' => array(
					'color' => 'alpha',
				),
			),
			'borders' => array(
				'default' => array(),
				'card' => array(
					'css' => array(
						'main' => array(
							'border_radii' => "%%order_class%% .pm-task-card",
							'border_styles' => "%%order_class%% .pm-task-card",
						),
					),
					'label_prefix' => esc_html__( 'Task Card', 'dicm-divi-custom-modules' ),
					'toggle_slug' => 'card_style',
				),
			),
			'box_shadow' => array(
				'default' => array(),
				'card' => array(
					'css' => array(
						'main' => "%%order_class%% .pm-task-card",
					),
					'label_prefix' => esc_html__( 'Task Card', 'dicm-divi-custom-modules' ),
					'toggle_slug' => 'card_style',
				),
			),
			'margin_padding' => array(
				'css' => array(
					'important' => 'all',
				),
			),
		);

		$this->custom_css_fields = array(
			'kanban_board' => array(
				'label'    => esc_html__( 'Kanban Board', 'dicm-divi-custom-modules' ),
				'selector' => '.pm-kanban-board',
			),
			'kanban_column' => array(
				'label'    => esc_html__( 'Kanban Column', 'dicm-divi-custom-modules' ),
				'selector' => '.pm-kanban-column',
			),
			'task_card' => array(
				'label'    => esc_html__( 'Task Card', 'dicm-divi-custom-modules' ),
				'selector' => '.pm-task-card',
			),
		);
	}

	public function get_fields() {
		return array(
			'board_title' => array(
				'label' => esc_html__( 'Board Title', 'dicm-divi-custom-modules' ),
				'type' => 'text',
				'default' => 'Project Manager',
				'toggle_slug' => 'main_content',
				'description' => esc_html__( 'Enter the title for your project board.', 'dicm-divi-custom-modules' ),
			),
			'allow_create_projects' => array(
				'label' => esc_html__( 'Allow Project Creation', 'dicm-divi-custom-modules' ),
				'type' => 'yes_no_button',
				'option_category' => 'basic_option',
				'options' => array(
					'on'  => esc_html__( 'Yes', 'dicm-divi-custom-modules' ),
					'off' => esc_html__( 'No', 'dicm-divi-custom-modules' ),
				),
				'default' => 'on',
				'toggle_slug' => 'main_content',
				'description' => esc_html__( 'Allow users to create new projects.', 'dicm-divi-custom-modules' ),
			),
			'default_statuses' => array(
				'label' => esc_html__( 'Default Statuses', 'dicm-divi-custom-modules' ),
				'type' => 'textarea',
				'default' => "To Do|#6b7280\nIn Progress|#3b82f6\nIn Review|#f59e0b\nDone|#10b981",
				'toggle_slug' => 'default_statuses',
				'description' => esc_html__( 'Default statuses for new projects (one per line, format: Name|Color).', 'dicm-divi-custom-modules' ),
			),
			'priority_colors' => array(
				'label' => esc_html__( 'Priority Colors', 'dicm-divi-custom-modules' ),
				'type' => 'textarea',
				'default' => "Low|#10b981\nMedium|#f59e0b\nHigh|#ef4444\nUrgent|#7c3aed",
				'toggle_slug' => 'default_statuses',
				'description' => esc_html__( 'Priority levels and their colors (one per line, format: Name|Color).', 'dicm-divi-custom-modules' ),
			),
			'column_min_width' => array(
				'label' => esc_html__( 'Column Min Width', 'dicm-divi-custom-modules' ),
				'type' => 'range',
				'range_settings' => array(
					'min' => 200,
					'max' => 400,
					'step' => 10,
				),
				'default' => '280',
				'toggle_slug' => 'kanban_style',
				'description' => esc_html__( 'Minimum width for Kanban columns in pixels.', 'dicm-divi-custom-modules' ),
			),
			'column_bg_color' => array(
				'label' => esc_html__( 'Column Background Color', 'dicm-divi-custom-modules' ),
				'type' => 'color-alpha',
				'default' => '#f3f4f6',
				'toggle_slug' => 'kanban_style',
				'description' => esc_html__( 'Background color for Kanban columns.', 'dicm-divi-custom-modules' ),
			),
			'card_bg_color' => array(
				'label' => esc_html__( 'Card Background Color', 'dicm-divi-custom-modules' ),
				'type' => 'color-alpha',
				'default' => '#ffffff',
				'toggle_slug' => 'card_style',
				'description' => esc_html__( 'Background color for task cards.', 'dicm-divi-custom-modules' ),
			),
		);
	}

	/**
	 * Create database tables
	 */
	public function create_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Projects table
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
				is_public tinyint(1) DEFAULT 0,
				share_token varchar(32) DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY share_token (share_token),
				KEY owner_id (owner_id)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		
		// Add is_public and share_token columns if they don't exist (for existing installations)
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $projects_table LIKE 'is_public'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $projects_table ADD is_public tinyint(1) DEFAULT 0 AFTER archived" );
		}
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $projects_table LIKE 'share_token'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $projects_table ADD share_token varchar(32) DEFAULT NULL AFTER is_public, ADD UNIQUE KEY share_token (share_token)" );
		}
		
		// Statuses table (Kanban columns)
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
		
		// Tasks table
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
		
		// Project members table
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
		
		// Daily task entries table
		$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$daily_tasks_table'" ) != $daily_tasks_table ) {
			$sql = "CREATE TABLE $daily_tasks_table (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				task_id bigint(20) UNSIGNED NOT NULL,
				description text,
				end_of_day_report text,
				start_time time DEFAULT NULL,
				end_time time DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY task_id (task_id)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
		
		// Add end_of_day_report column if it doesn't exist (for existing installations)
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $daily_tasks_table LIKE 'end_of_day_report'" );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $daily_tasks_table ADD end_of_day_report text AFTER description" );
		}
	}

	/**
	 * Enqueue frontend scripts
	 */
	public function enqueue_frontend_scripts() {
		// Ensure jQuery is available (WordPress includes it)
		wp_enqueue_script( 'jquery' );
		
		// Register React
		wp_enqueue_script(
			'react',
			'https://unpkg.com/react@18/umd/react.production.min.js',
			array( 'jquery' ),
			'18.3.1',
			true
		);
		
		wp_enqueue_script(
			'react-dom',
			'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
			array( 'react' ),
			'18.3.1',
			true
		);
		
		// Then enqueue the frontend bundle with jQuery and React as dependencies
		wp_enqueue_script(
			'dicm-frontend-bundle',
			plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'scripts/frontend-bundle.min.js',
			array( 'jquery', 'react', 'react-dom' ),
			'1.0.0',
			true
		);
		
		// Localize script with AJAX data as backup
		wp_localize_script( 'dicm-frontend-bundle', 'dicmProjectManager', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'project_manager_nonce' ),
			'userId'  => get_current_user_id(),
		) );
	}

	/**
	 * Verify user access and nonce
	 */
	private function verify_request() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Authentication required' ) );
		}
		
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'project_manager_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}
		
		return get_current_user_id();
	}

	/**
	 * Check if user is admin
	 */
	private function is_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user has access to project
	 */
	private function user_has_project_access( $project_id, $user_id ) {
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		$members_table = $wpdb->prefix . 'pm_project_members';
		
		// Check if owner
		$is_owner = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $projects_table WHERE id = %d AND owner_id = %d",
			$project_id, $user_id
		) );
		
		if ( $is_owner > 0 ) return true;
		
		// Check if member
		$is_member = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $members_table WHERE project_id = %d AND user_id = %d",
			$project_id, $user_id
		) );
		
		return $is_member > 0 || $this->is_admin_user();
	}

	// ==================== PROJECT AJAX HANDLERS ====================

	public function ajax_get_projects() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		$members_table = $wpdb->prefix . 'pm_project_members';
		
		// Get projects where user is owner or member
		$projects = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT p.* FROM $projects_table p
			LEFT JOIN $members_table m ON p.id = m.project_id
			WHERE (p.owner_id = %d OR m.user_id = %d) AND p.archived = 0
			ORDER BY p.created_at DESC",
			$user_id, $user_id
		) );
		
		// Add owner info to each project
		foreach ( $projects as &$project ) {
			$owner = get_userdata( $project->owner_id );
			$project->owner_name = $owner ? $owner->display_name : 'Unknown';
			$project->is_owner = ( $project->owner_id == $user_id );
		}
		
		wp_send_json_success( array( 'projects' => $projects ) );
	}

	public function ajax_create_project() {
		$user_id = $this->verify_request();
		
		// Only admins can create projects
		if ( ! $this->is_admin_user() ) {
			wp_send_json_error( array( 'message' => 'Only administrators can create projects' ) );
		}
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$name = sanitize_text_field( $_POST['name'] );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$color = sanitize_hex_color( $_POST['color'] ?? '#3b82f6' );
		$default_statuses = isset( $_POST['default_statuses'] ) ? $_POST['default_statuses'] : array();
		
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'Project name is required' ) );
		}
		
		// Create project
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
		
		// Create default statuses
		if ( ! empty( $default_statuses ) ) {
			foreach ( $default_statuses as $index => $status ) {
				$wpdb->insert( $statuses_table, array(
					'project_id' => $project_id,
					'name' => sanitize_text_field( $status['name'] ),
					'color' => sanitize_hex_color( $status['color'] ?? '#6b7280' ),
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

	public function ajax_update_project() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		
		$project_id = intval( $_POST['project_id'] );
		
		// Check access
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$data = array();
		if ( isset( $_POST['name'] ) ) $data['name'] = sanitize_text_field( $_POST['name'] );
		if ( isset( $_POST['description'] ) ) $data['description'] = sanitize_textarea_field( $_POST['description'] );
		if ( isset( $_POST['color'] ) ) $data['color'] = sanitize_hex_color( $_POST['color'] );
		if ( isset( $_POST['archived'] ) ) $data['archived'] = intval( $_POST['archived'] );
		
		if ( empty( $data ) ) {
			wp_send_json_error( array( 'message' => 'No data to update' ) );
		}
		
		$result = $wpdb->update( $projects_table, $data, array( 'id' => $project_id ) );
		
		wp_send_json_success( array( 'message' => 'Project updated successfully' ) );
	}

	public function ajax_delete_project() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		
		$project_id = intval( $_POST['project_id'] );
		
		// Only owner can delete
		$project = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $projects_table WHERE id = %d",
			$project_id
		) );
		
		if ( ! $project || ( $project->owner_id != $user_id && ! $this->is_admin_user() ) ) {
			wp_send_json_error( array( 'message' => 'Only the project owner can delete' ) );
		}
		
		// Delete related data
		$wpdb->delete( $wpdb->prefix . 'pm_tasks', array( 'project_id' => $project_id ) );
		$wpdb->delete( $wpdb->prefix . 'pm_statuses', array( 'project_id' => $project_id ) );
		$wpdb->delete( $wpdb->prefix . 'pm_project_members', array( 'project_id' => $project_id ) );
		$wpdb->delete( $projects_table, array( 'id' => $project_id ) );
		
		wp_send_json_success( array( 'message' => 'Project deleted successfully' ) );
	}

	public function ajax_toggle_project_share() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		
		$project_id = intval( $_POST['project_id'] );
		
		// Get project to check ownership
		$project = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $projects_table WHERE id = %d",
			$project_id
		) );
		
		if ( ! $project || ( $project->owner_id != $user_id && ! $this->is_admin_user() ) ) {
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

	public function ajax_regenerate_share_token() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$projects_table = $wpdb->prefix . 'pm_projects';
		
		$project_id = intval( $_POST['project_id'] );
		
		// Get project to check ownership
		$project = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $projects_table WHERE id = %d",
			$project_id
		) );
		
		if ( ! $project || ( $project->owner_id != $user_id && ! $this->is_admin_user() ) ) {
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

	// ==================== STATUS AJAX HANDLERS ====================

	public function ajax_get_statuses() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$project_id = intval( $_POST['project_id'] );
		
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$statuses = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $statuses_table WHERE project_id = %d ORDER BY order_index ASC",
			$project_id
		) );
		
		wp_send_json_success( array( 'statuses' => $statuses ) );
	}

	public function ajax_create_status() {
		$user_id = $this->verify_request();
		
		// Only admins can create columns/statuses
		if ( ! $this->is_admin_user() ) {
			wp_send_json_error( array( 'message' => 'Only administrators can create columns' ) );
		}
		
		global $wpdb;
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$project_id = intval( $_POST['project_id'] );
		$name = sanitize_text_field( $_POST['name'] );
		$color = sanitize_hex_color( $_POST['color'] ?? '#6b7280' );
		
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		// Get max order_index
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

	public function ajax_update_status() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$status_id = intval( $_POST['status_id'] );
		
		// Get status to check project access
		$status = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $statuses_table WHERE id = %d",
			$status_id
		) );
		
		if ( ! $status || ! $this->user_has_project_access( $status->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$data = array();
		if ( isset( $_POST['name'] ) ) $data['name'] = sanitize_text_field( $_POST['name'] );
		if ( isset( $_POST['color'] ) ) $data['color'] = sanitize_hex_color( $_POST['color'] );
		if ( isset( $_POST['is_done'] ) ) $data['is_done'] = intval( $_POST['is_done'] );
		
		$wpdb->update( $statuses_table, $data, array( 'id' => $status_id ) );
		
		wp_send_json_success( array( 'message' => 'Status updated successfully' ) );
	}

	public function ajax_delete_status() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$status_id = intval( $_POST['status_id'] );
		$move_tasks_to = isset( $_POST['move_tasks_to'] ) ? intval( $_POST['move_tasks_to'] ) : null;
		
		$status = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $statuses_table WHERE id = %d",
			$status_id
		) );
		
		if ( ! $status || ! $this->user_has_project_access( $status->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		// Move tasks to another status if specified
		if ( $move_tasks_to ) {
			$wpdb->update( $tasks_table,
				array( 'status_id' => $move_tasks_to ),
				array( 'status_id' => $status_id )
			);
		} else {
			// Delete tasks in this status
			$wpdb->delete( $tasks_table, array( 'status_id' => $status_id ) );
		}
		
		$wpdb->delete( $statuses_table, array( 'id' => $status_id ) );
		
		wp_send_json_success( array( 'message' => 'Status deleted successfully' ) );
	}

	public function ajax_reorder_statuses() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$status_order = isset( $_POST['status_order'] ) ? $_POST['status_order'] : array();
		$project_id = intval( $_POST['project_id'] );
		
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		foreach ( $status_order as $index => $status_id ) {
			$wpdb->update( $statuses_table,
				array( 'order_index' => $index ),
				array( 'id' => intval( $status_id ), 'project_id' => $project_id )
			);
		}
		
		wp_send_json_success( array( 'message' => 'Statuses reordered successfully' ) );
	}

	// ==================== TASK AJAX HANDLERS ====================

	public function ajax_get_tasks() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$project_id = intval( $_POST['project_id'] );
		
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$tasks = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE project_id = %d ORDER BY status_id, order_index ASC",
			$project_id
		) );
		
		// Add user info
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

	public function ajax_create_task() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$project_id = intval( $_POST['project_id'] );
		$status_id = intval( $_POST['status_id'] );
		$title = sanitize_text_field( $_POST['title'] );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$priority = sanitize_text_field( $_POST['priority'] ?? 'Medium' );
		$due_date = isset( $_POST['due_date'] ) && ! empty( $_POST['due_date'] ) ? sanitize_text_field( $_POST['due_date'] ) : null;
		$assignee_id = isset( $_POST['assignee_id'] ) && ! empty( $_POST['assignee_id'] ) ? intval( $_POST['assignee_id'] ) : null;
		
		if ( ! $this->user_has_project_access( $project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'Task title is required' ) );
		}
		
		// Get max order_index for this status
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
		
		// Add user info
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

	public function ajax_update_task() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$task_id = intval( $_POST['task_id'] );
		
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			$task_id
		) );
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
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
		
		// Get updated task
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

	public function ajax_delete_task() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$task_id = intval( $_POST['task_id'] );
		
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			$task_id
		) );
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$wpdb->delete( $tasks_table, array( 'id' => $task_id ) );
		
		wp_send_json_success( array( 'message' => 'Task deleted successfully' ) );
	}

	public function ajax_move_task() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		$statuses_table = $wpdb->prefix . 'pm_statuses';
		
		$task_id = intval( $_POST['task_id'] );
		$new_status_id = intval( $_POST['new_status_id'] );
		$new_order = intval( $_POST['new_order'] ?? 0 );
		
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			$task_id
		) );
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		// Check if status belongs to same project
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
		
		// Mark as completed if moved to done status
		if ( $status->is_done ) {
			$update_data['completed_at'] = current_time( 'mysql' );
		} else {
			$update_data['completed_at'] = null;
		}
		
		$wpdb->update( $tasks_table, $update_data, array( 'id' => $task_id ) );
		
		wp_send_json_success( array( 'message' => 'Task moved successfully' ) );
	}

	public function ajax_reorder_tasks() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$task_order = isset( $_POST['task_order'] ) ? $_POST['task_order'] : array();
		$status_id = intval( $_POST['status_id'] );
		
		// Verify access via first task
		if ( ! empty( $task_order ) ) {
			$first_task = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $tasks_table WHERE id = %d",
				intval( $task_order[0] )
			) );
			
			if ( ! $first_task || ! $this->user_has_project_access( $first_task->project_id, $user_id ) ) {
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

	// ==================== USER AJAX HANDLER ====================

	public function ajax_get_users() {
		$this->verify_request();
		
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

	// ==================== DAILY TASK ENTRIES AJAX HANDLERS ====================

	public function ajax_get_daily_task_entries() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$task_id = intval( $_POST['task_id'] );
		
		// Verify access to task's project
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			$task_id
		) );
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$entries = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $daily_tasks_table WHERE task_id = %d ORDER BY start_time ASC",
			$task_id
		) );
		
		wp_send_json_success( array( 'entries' => $entries ) );
	}

	public function ajax_create_daily_task_entry() {
		$user_id = $this->verify_request();
		
		global $wpdb;
		$daily_tasks_table = $wpdb->prefix . 'pm_daily_task_entries';
		$tasks_table = $wpdb->prefix . 'pm_tasks';
		
		$task_id = intval( $_POST['task_id'] );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$end_of_day_report = sanitize_textarea_field( $_POST['end_of_day_report'] ?? '' );
		$start_time = sanitize_text_field( $_POST['start_time'] );
		$end_time = sanitize_text_field( $_POST['end_time'] );
		
		// Verify access to task's project
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $tasks_table WHERE id = %d",
			$task_id
		) );
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$result = $wpdb->insert( $daily_tasks_table, array(
			'task_id' => $task_id,
			'description' => $description,
			'end_of_day_report' => $end_of_day_report,
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

	public function ajax_update_daily_task_entry() {
		$user_id = $this->verify_request();
		
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
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$data = array();
		if ( isset( $_POST['description'] ) ) $data['description'] = sanitize_textarea_field( $_POST['description'] );
		if ( isset( $_POST['end_of_day_report'] ) ) $data['end_of_day_report'] = sanitize_textarea_field( $_POST['end_of_day_report'] );
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

	public function ajax_delete_daily_task_entry() {
		$user_id = $this->verify_request();
		
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
		
		if ( ! $task || ! $this->user_has_project_access( $task->project_id, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Access denied' ) );
		}
		
		$wpdb->delete( $daily_tasks_table, array( 'id' => $entry_id ) );
		
		wp_send_json_success( array( 'message' => 'Daily task entry deleted successfully' ) );
	}

	public function render( $attrs, $content, $render_slug ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="dicm-project-manager pm-login-required">
				<p>Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to access the Project Manager.</p>
			</div>';
		}
		
		$current_user_id = get_current_user_id();
		$post_id = get_the_ID();
		$module_index = isset( $attrs['_order_number'] ) ? $attrs['_order_number'] : rand(1000, 9999);
		$module_instance_id = 'pm_' . $post_id . '_' . $module_index;
		
		// Parse default statuses
		$default_statuses_raw = $this->props['default_statuses'];
		$default_statuses = array();
		$lines = array_filter( array_map( 'trim', explode( "\n", $default_statuses_raw ) ) );
		foreach ( $lines as $line ) {
			$parts = explode( '|', $line );
			$default_statuses[] = array(
				'name' => trim( $parts[0] ),
				'color' => isset( $parts[1] ) ? trim( $parts[1] ) : '#6b7280'
			);
		}
		
		// Parse priority colors
		$priority_colors_raw = $this->props['priority_colors'];
		$priority_colors = array();
		$lines = array_filter( array_map( 'trim', explode( "\n", $priority_colors_raw ) ) );
		foreach ( $lines as $line ) {
			$parts = explode( '|', $line );
			$priority_colors[ trim( $parts[0] ) ] = isset( $parts[1] ) ? trim( $parts[1] ) : '#6b7280';
		}
		
		$config = array(
			'moduleInstanceId' => $module_instance_id,
			'boardTitle' => $this->props['board_title'],
			'allowCreateProjects' => $this->props['allow_create_projects'] === 'on',
			'defaultStatuses' => $default_statuses,
			'priorityColors' => $priority_colors,
			'columnMinWidth' => intval( $this->props['column_min_width'] ),
			'columnBgColor' => $this->props['column_bg_color'],
			'cardBgColor' => $this->props['card_bg_color'],
			'userId' => $current_user_id,
			'isAdmin' => current_user_can( 'manage_options' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'project_manager_nonce' ),
			'userName' => wp_get_current_user()->display_name,
			'userAvatar' => get_avatar_url( $current_user_id, array( 'size' => 32 ) )
		);
		
		$config_json = wp_json_encode( $config );
		
		$output = sprintf(
			'<div class="dicm-project-manager" data-config="%s">
				<div class="pm-loading">
					<div class="pm-loading-spinner"></div>
					<p>Loading Project Manager...</p>
				</div>
			</div>',
			esc_attr( $config_json )
		);
		
		return $output;
	}
}

// Create instance for Divi
new DICM_ProjectManager;

// Note: AJAX handlers have been moved to divi-custom-modules.php
// to ensure they are always loaded during AJAX requests.

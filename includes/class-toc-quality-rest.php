<?php
declare(strict_types=1);

class TOC_Quality_REST {

	public static function register_routes(): void {
		$namespace = 'tasteofcinemascraped/v1';

		// POST /quality/run
		register_rest_route( $namespace, '/quality/run', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'run_job' ),
			'permission_callback' => array( self::class, 'check_edit_posts' ),
			'args'                => array(
				'post_id'      => array( 'required' => true, 'type' => 'integer' ),
				'content_type' => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			),
		) );

		// GET /quality/jobs/{id}
		register_rest_route( $namespace, '/quality/jobs/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_job' ),
			'permission_callback' => array( self::class, 'check_edit_posts' ),
		) );

		// POST /quality/jobs/{id}/resolve
		register_rest_route( $namespace, '/quality/jobs/(?P<id>\d+)/resolve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'resolve_job' ),
			'permission_callback' => array( self::class, 'check_edit_posts' ),
			'args'                => array(
				'action'         => array( 'required' => true, 'type' => 'string' ),
				'rejection_note' => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			),
		) );

		// GET /quality/audit
		register_rest_route( $namespace, '/quality/audit', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_audit' ),
			'permission_callback' => array( self::class, 'check_edit_posts' ),
		) );

		// GET /quality/settings
		register_rest_route( $namespace, '/quality/settings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_settings' ),
			'permission_callback' => array( self::class, 'check_manage_options' ),
		) );

		// PUT /quality/settings
		register_rest_route( $namespace, '/quality/settings', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( self::class, 'update_settings' ),
			'permission_callback' => array( self::class, 'check_manage_options' ),
		) );
	}

	public static function check_edit_posts() {
		return current_user_can( 'edit_posts' ) ? true : new WP_Error( 'rest_forbidden', 'Forbidden', array( 'status' => 403 ) );
	}

	public static function check_manage_options() {
		return current_user_can( 'manage_options' ) ? true : new WP_Error( 'rest_forbidden', 'Forbidden', array( 'status' => 403 ) );
	}

	public static function run_job( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( ! get_post( $post_id ) ) {
			return new WP_Error( 'rest_post_invalid_id', 'Invalid post ID.', array( 'status' => 404 ) );
		}

		$content_type_raw = $request->get_param( 'content_type' ) ?: '';
		$hint_provided = ! empty( $content_type_raw );
		$content_type = $hint_provided ? sanitize_text_field( $content_type_raw ) : 'editorial';

		$text = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
		$word_count = TOC_Quality_DB::toc_count_arabic_words( $text );

		if ( $word_count > 5000 ) {
			return new WP_Error( 'toc_word_count_exceeded', 'Article exceeds 5,000 words limit.', array( 'status' => 400 ) );
		}

		$job_id = TOC_Quality_DB::create_job( $post_id, $content_type, get_current_user_id() );

		if ( $word_count <= 2000 ) {
			// Sync processing
			TOC_Quality_Engine::process_job( $job_id, $hint_provided );
			$updated_job = TOC_Quality_DB::get_job( $job_id );
			return new WP_REST_Response( $updated_job, 201 );
		} else {
			// Async processing
			TOC_Quality_Scheduler::enqueue_async_job( $job_id, $hint_provided );
			return new WP_REST_Response( array( 'job_id' => $job_id, 'status' => 'pending' ), 202 );
		}
	}

	public static function get_job( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		$job = TOC_Quality_DB::get_job( $id );
		if ( ! $job ) {
			return new WP_Error( 'rest_not_found', 'Job not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $job );
	}

	public static function resolve_job( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		$action = $request->get_param( 'action' );
		$rejection_note = sanitize_textarea_field( $request->get_param( 'rejection_note' ) );

		$job = TOC_Quality_DB::get_job( $id );
		if ( ! $job ) {
			return new WP_Error( 'rest_not_found', 'Job not found.', array( 'status' => 404 ) );
		}

		if ( ! in_array( $job['status'], array( 'flagged-for-review', 'engine-unavailable' ), true ) ) {
			return new WP_Error( 'toc_job_already_resolved', 'Job is not in a resolvable state.', array( 'status' => 409 ) );
		}

		if ( $action === 'approve' ) {
			wp_update_post( array( 'ID' => (int) $job['post_id'], 'post_status' => 'publish' ) );
			TOC_Quality_DB::transition_status( $id, 'human-approved' );
		} elseif ( $action === 'reject' ) {
			wp_update_post( array( 'ID' => (int) $job['post_id'], 'post_status' => 'draft' ) );
			TOC_Quality_DB::transition_status( $id, 'rejected', array( 'rejection_note' => $rejection_note ) );
		} else {
			return new WP_Error( 'rest_invalid_param', 'Action must be approve or reject.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( TOC_Quality_DB::get_job( $id ) );
	}

	public static function get_audit( WP_REST_Request $request ) {
		$filters = array(
			'post_id'      => $request->get_param( 'post_id' ),
			'status'       => $request->get_param( 'status' ),
			'content_type' => $request->get_param( 'content_type' ),
			'from'         => $request->get_param( 'from' ),
			'to'           => $request->get_param( 'to' ),
			'min_score'    => $request->get_param( 'min_score' ),
			'max_score'    => $request->get_param( 'max_score' ),
			'per_page'     => $request->get_param( 'per_page' ),
			'page'         => $request->get_param( 'page' ),
		);
		$result = TOC_Quality_DB::get_audit_log( $filters );
		
		foreach ( $result['items'] as &$item ) {
			$item['post_title'] = get_the_title( (int) $item['post_id'] );
		}
		
		return rest_ensure_response( $result );
	}

	public static function get_settings( WP_REST_Request $request ) {
		return rest_ensure_response( array(
			'quality_threshold'      => TOC_Quality_DB::get_threshold(),
			'model'                  => TOC_Quality_DB::get_model(),
			'audit_retention_days'   => get_option( 'toc_quality_audit_retention_days', 90 ),
			'auto_run_on_import'     => TOC_Quality_DB::get_auto_run_on_import(),
		) );
	}

	public static function update_settings( WP_REST_Request $request ) {
		$settings = $request->get_json_params() ?: $request->get_body_params();
		TOC_Quality_DB::update_settings( $settings );
		return self::get_settings( $request );
	}
}

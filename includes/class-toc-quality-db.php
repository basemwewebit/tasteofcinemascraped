<?php
declare(strict_types=1);

class TOC_Quality_DB {

	public static function install_schema(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_jobs = $wpdb->prefix . 'toc_translation_jobs';
		$sql_jobs = "CREATE TABLE $table_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			content_type varchar(20) NOT NULL DEFAULT 'editorial',
			initiating_user_id bigint(20) unsigned NOT NULL,
			word_count int(10) unsigned NOT NULL,
			input_hash char(64) NOT NULL,
			pre_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			post_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			change_count smallint(5) unsigned NOT NULL DEFAULT 0,
			change_manifest longtext DEFAULT NULL,
			model_version varchar(80) NOT NULL,
			status varchar(30) NOT NULL,
			rejection_note text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_post_id (post_id),
			KEY idx_status (status),
			KEY idx_user_status (initiating_user_id, status),
			KEY idx_created_at (created_at),
			KEY idx_input_hash (input_hash)
		) $charset_collate;";

		$table_audit = $wpdb->prefix . 'toc_audit_log';
		$sql_audit = "CREATE TABLE $table_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			event_type varchar(40) NOT NULL,
			previous_status varchar(30) DEFAULT NULL,
			new_status varchar(30) DEFAULT NULL,
			metadata text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_job_id (job_id),
			KEY idx_post_id (post_id),
			KEY idx_user_id (user_id),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_jobs );
		dbDelta( $sql_audit );
	}

	public static function create_job( int $post_id, string $content_type, int $user_id ): int {
		global $wpdb;

		$allowed_types = array( 'synopsis', 'dialogue', 'review', 'editorial' );
		if ( ! in_array( $content_type, $allowed_types, true ) ) {
			$content_type = 'editorial';
		}

		$content = get_post_field( 'post_content', $post_id ) ?: '';
		$text    = wp_strip_all_tags( $content );
		$word_count = self::toc_count_arabic_words( $text );
		$input_hash = hash( 'sha256', $text );

		$table_jobs = $wpdb->prefix . 'toc_translation_jobs';
		$wpdb->insert(
			$table_jobs,
			array(
				'post_id'            => $post_id,
				'content_type'       => $content_type,
				'initiating_user_id' => $user_id,
				'word_count'         => $word_count,
				'input_hash'         => $input_hash,
				'status'             => 'pending',
				'model_version'      => '', // will be updated later
				'created_at'         => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		$job_id = (int) $wpdb->insert_id;

		self::log_audit_event( $job_id, $post_id, $user_id, 'job_created', null, 'pending' );

		return $job_id;
	}

	public static function toc_count_arabic_words( string $text ): int {
		preg_match_all( '/\p{Arabic}+/u', $text, $m );
		$arabic_count = count( $m[0] );
		if ( $arabic_count > 0 ) {
			return $arabic_count;
		}
		return str_word_count( $text );
	}

	private static function log_audit_event( int $job_id, int $post_id, int $user_id, string $event_type, ?string $previous_status, ?string $new_status, ?array $metadata = null ): void {
		global $wpdb;
		$table_audit = $wpdb->prefix . 'toc_audit_log';
		$wpdb->insert(
			$table_audit,
			array(
				'job_id'          => $job_id,
				'post_id'         => $post_id,
				'user_id'         => $user_id,
				'event_type'      => $event_type,
				'previous_status' => $previous_status,
				'new_status'      => $new_status,
				'metadata'        => $metadata !== null ? wp_json_encode( $metadata ) : null,
				'created_at'      => current_time( 'mysql', true ),
			),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	public static function get_job( int $job_id, bool $bypass_permissions = false ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'toc_translation_jobs';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $job_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( ! $bypass_permissions && ! current_user_can( 'manage_options' ) ) {
			if ( (int) $row['initiating_user_id'] !== get_current_user_id() ) {
				return null;
			}
		}
		return self::format_job_row( $row );
	}

	public static function get_jobs_for_post( int $post_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'toc_translation_jobs';
		
		if ( current_user_can( 'manage_options' ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at DESC", $post_id ), ARRAY_A );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE post_id = %d AND initiating_user_id = %d ORDER BY created_at DESC", $post_id, get_current_user_id() ), ARRAY_A );
		}

		return array_map( array( self::class, 'format_job_row' ), $results ?: [] );
	}

	private static function format_job_row( array $row ): array {
		$row['id']                 = (int) $row['id'];
		$row['post_id']            = (int) $row['post_id'];
		$row['initiating_user_id'] = (int) $row['initiating_user_id'];
		$row['word_count']         = (int) $row['word_count'];
		$row['pre_score']          = (int) $row['pre_score'];
		$row['post_score']         = (int) $row['post_score'];
		$row['change_count']       = (int) $row['change_count'];
		$row['change_manifest']    = ! empty( $row['change_manifest'] ) ? json_decode( $row['change_manifest'], true ) : null;
		return $row;
	}

	public static function transition_status( int $job_id, string $new_status, array $data = [] ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'toc_translation_jobs';
		
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT id, post_id, status, initiating_user_id FROM $table WHERE id = %d", $job_id ) );
		if ( ! $job ) {
			return false;
		}

		$current = $job->status;
		$valid_transitions = array(
			'pending'            => array( 'processing', 'engine-unavailable' ),
			'processing'         => array( 'auto-approved', 'flagged-for-review', 'engine-unavailable' ),
			'flagged-for-review' => array( 'human-approved', 'rejected', 'processing' ),
			'engine-unavailable' => array( 'human-approved', 'rejected', 'processing' ),
		);

		if ( ! isset( $valid_transitions[ $current ] ) || ! in_array( $new_status, $valid_transitions[ $current ], true ) ) {
			error_log( "TOC_Quality_DB: Invalid status transition from $current to $new_status for job $job_id" );
			return false;
		}

		$update_data = array(
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql', true ),
		);
		$update_format = array( '%s', '%s' );

		// Update fields if provided
		$updatable = array( 'pre_score', 'post_score', 'change_count', 'change_manifest', 'model_version', 'rejection_note' );
		foreach ( $updatable as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				if ( $field === 'change_manifest' ) {
					$update_data[ $field ] = wp_json_encode( $data[ $field ] );
					$update_format[] = '%s';
				} elseif ( in_array( $field, array( 'pre_score', 'post_score', 'change_count' ), true ) ) {
					$update_data[ $field ] = (int) $data[ $field ];
					$update_format[] = '%d';
				} else {
					$update_data[ $field ] = (string) $data[ $field ];
					$update_format[] = '%s';
				}
			}
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $job_id ),
			$update_format,
			array( '%d' )
		);

		if ( $result === false ) {
			return false;
		}

		$event_type_map = array(
			'processing'         => 'processing_started',
			'auto-approved'      => 'auto_approved',
			'flagged-for-review' => 'flagged',
			'engine-unavailable' => 'engine_unavailable',
			'human-approved'     => 'human_approved',
			'rejected'           => 'rejected',
		);
		
		$event_type = $event_type_map[ $new_status ] ?? $new_status;
		
		$metadata = $data['metadata'] ?? [];
		if ( in_array( $event_type, array('score_assigned', 'auto_approved', 'flagged'), true ) ) {
			if ( isset( $data['model_version'] ) ) {
				$metadata['model_version'] = $data['model_version'];
			}
		}

		self::log_audit_event( $job_id, (int) $job->post_id, get_current_user_id() ?: (int) $job->initiating_user_id, $event_type, $current, $new_status, empty($metadata) ? null : $metadata );

		return true;
	}

	public static function get_audit_log( array $filters ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'toc_translation_jobs';
		$table_audit = $wpdb->prefix . 'toc_audit_log';
		
		$where = array( '1=1' );
		$args = array();

		// Enforce manage_options vs own-only
		if ( ! current_user_can( 'manage_options' ) ) {
			$where[] = 'a.user_id = %d';
			$args[] = get_current_user_id();
		}

		if ( ! empty( $filters['post_id'] ) ) {
			$where[] = 'a.post_id = %d';
			$args[] = (int) $filters['post_id'];
		}
		
		if ( ! empty( $filters['status'] ) ) {
			$status_list = array_map( 'trim', explode( ',', $filters['status'] ) );
			$status_placeholders = implode( ',', array_fill( 0, count( $status_list ), '%s' ) );
			$where[] = "j.status IN ($status_placeholders)";
			$args = array_merge( $args, $status_list );
		}
		
		if ( ! empty( $filters['content_type'] ) ) {
			$where[] = 'j.content_type = %s';
			$args[] = $filters['content_type'];
		}

		if ( ! empty( $filters['from'] ) ) {
			$where[] = 'a.created_at >= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', strtotime( $filters['from'] ) );
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = 'a.created_at <= %s';
			$args[] = gmdate( 'Y-m-d H:i:s', strtotime( $filters['to'] ) );
		}
		
		if ( isset( $filters['min_score'] ) && $filters['min_score'] !== '' ) {
			$where[] = 'j.post_score >= %d';
			$args[] = (int) $filters['min_score'];
		}
		if ( isset( $filters['max_score'] ) && $filters['max_score'] !== '' ) {
			$where[] = 'j.post_score <= %d';
			$args[] = (int) $filters['max_score'];
		}

		$where_sql = implode( ' AND ', $where );
		
		$per_page = isset( $filters['per_page'] ) ? min( 100, max( 1, (int) $filters['per_page'] ) ) : 20;
		$page = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		$sql = "SELECT DISTINCT a.*, j.post_score, j.content_type, j.status as current_job_status
				FROM $table_audit a
				LEFT JOIN $table j ON a.job_id = j.id
				WHERE $where_sql
				ORDER BY a.created_at DESC
				LIMIT %d, %d";
		
		$count_sql = "SELECT COUNT(DISTINCT a.id) 
					  FROM $table_audit a
					  LEFT JOIN $table j ON a.job_id = j.id
					  WHERE $where_sql";

		$prepared_count = $count_sql;
		if ( count( $args ) > 0 ) {
			$prepared_count = $wpdb->prepare( $count_sql, $args );
		}
		$total = (int) $wpdb->get_var( $prepared_count );

		$args[] = $offset;
		$args[] = $per_page;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		
		if ( $results ) {
			foreach ( $results as &$r ) {
				$r['metadata'] = ! empty( $r['metadata'] ) ? json_decode( $r['metadata'], true ) : null;
			}
		}

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'items'    => $results ?: array(),
		);
	}

	public static function schedule_audit_purge(): void {
		if ( ! wp_next_scheduled( 'toc_quality_audit_purge' ) ) {
			wp_schedule_event( time(), 'daily', 'toc_quality_audit_purge' );
		}
	}

	public static function run_audit_purge(): void {
		global $wpdb;
		$days = (int) get_option( 'toc_quality_audit_retention_days', 90 );
		$table = $wpdb->prefix . 'toc_audit_log';
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $cutoff ) );
	}

	public static function get_threshold(): int {
		$data = get_option( 'toc_quality_threshold', array( 'value' => 70 ) );
		return isset( $data['value'] ) ? (int) $data['value'] : 70;
	}

	public static function get_model(): string {
		$model = get_option( 'toc_quality_model', '' );
		if ( empty( $model ) && isset( $_SERVER['OPENROUTER_QUALITY_MODEL'] ) ) {
			$model = $_SERVER['OPENROUTER_QUALITY_MODEL'];
		}
		if ( empty( $model ) && isset( $_SERVER['OPENROUTER_MODEL'] ) ) {
			$model = $_SERVER['OPENROUTER_MODEL'];
		}
		return (string) $model;
	}

	public static function get_auto_run_on_import(): bool {
		return (bool) get_option( 'toc_quality_auto_run_on_import', true );
	}

	public static function update_settings( array $settings ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $settings['quality_threshold'] ) ) {
			$val = max( 0, min( 100, (int) $settings['quality_threshold'] ) );
			update_option( 'toc_quality_threshold', array(
				'value'            => $val,
				'last_modified_by' => get_current_user_id(),
				'last_modified_at' => current_time( 'mysql', true ),
			) );
		}

		if ( isset( $settings['model'] ) ) {
			update_option( 'toc_quality_model', sanitize_text_field( $settings['model'] ) );
		}

		if ( isset( $settings['audit_retention_days'] ) ) {
			$days = max( 7, min( 365, (int) $settings['audit_retention_days'] ) );
			update_option( 'toc_quality_audit_retention_days', $days );
		}

		if ( isset( $settings['auto_run_on_import'] ) ) {
			// 'true' string or boolean true mapped to bool
			update_option( 'toc_quality_auto_run_on_import', filter_var( $settings['auto_run_on_import'], FILTER_VALIDATE_BOOLEAN ) );
		}
	}
}

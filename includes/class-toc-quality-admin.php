<?php
declare(strict_types=1);

class TOC_Quality_Admin {

	public static function register_menus(): void {
		add_options_page(
			__( 'TOC Quality Settings', 'tasteofcinemascraped-wp' ),
			__( 'TOC Quality Settings', 'tasteofcinemascraped-wp' ),
			'manage_options',
			'toc-quality-settings',
			array( self::class, 'render_settings_page' )
		);

		add_menu_page(
			__( 'Translation Review Queue', 'tasteofcinemascraped-wp' ),
			__( 'Translation Reviews', 'tasteofcinemascraped-wp' ),
			'edit_posts',
			'toc-review-queue',
			array( self::class, 'render_review_queue_page' ),
			'dashicons-translation',
			6
		);
		add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );
	}

	public static function register_ajax_hooks(): void {
		add_action( 'wp_ajax_toc_quality_test_engine', array( self::class, 'ajax_test_engine' ) );
	}

	public static function register_list_table_hooks(): void {
		add_filter( 'manage_posts_columns', array( self::class, 'add_quality_column' ) );
		add_action( 'manage_posts_custom_column', array( self::class, 'render_quality_column' ), 10, 2 );
	}

	public static function add_quality_column( array $columns ): array {
		$columns['toc_quality'] = __( 'جودة الترجمة', 'tasteofcinemascraped-wp' );
		return $columns;
	}

	public static function render_quality_column( string $column_name, int $post_id ): void {
		if ( $column_name === 'toc_quality' ) {
			$jobs = TOC_Quality_DB::get_jobs_for_post( $post_id );
			if ( ! empty( $jobs ) ) {
				$latest_job = $jobs[0];
				$score = $latest_job['post_score'] ?: $latest_job['pre_score'];
				if ( $score > 0 ) {
					$threshold = TOC_Quality_DB::get_threshold();
					$color = $score >= $threshold ? 'green' : ( $score < 50 ? 'red' : 'orange' );
					echo sprintf( '<span style="color: %s; font-weight: bold;">%d</span>', esc_attr( $color ), (int) $score );
				} else {
					if ( $latest_job['status'] === 'pending' || $latest_job['status'] === 'processing' ) {
					    echo '<span style="color: gray;">قيد التقييم...</span>';
					} else {
					    echo '<span style="color: red;">خطأ / غير متاح</span>';
					}
				}
			} else {
				echo '<span style="color: #ccc;">-</span>';
			}
		}
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$threshold = TOC_Quality_DB::get_threshold();
		$retention = get_option( 'toc_quality_audit_retention_days', 90 );
		$model = get_option( 'toc_quality_model', '' );
		$auto_run = TOC_Quality_DB::get_auto_run_on_import();
		
		wp_enqueue_script( 'wp-api-fetch' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TOC Quality Settings', 'tasteofcinemascraped-wp' ); ?></h1>
			<p id="toc-quality-settings-msg"></p>
			<form id="toc-quality-settings-form">
				<table class="form-table">
					<tr>
						<th><label for="quality_threshold">Quality Threshold (0-100)</label></th>
						<td><input type="number" id="quality_threshold" min="0" max="100" value="<?php echo esc_attr( (string) $threshold ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="model">OpenRouter Model Override</label></th>
						<td><input type="text" id="model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" /><p class="description">Leave blank to use OPENROUTER_QUALITY_MODEL or anthropic/claude-3.5-sonnet.</p></td>
					</tr>
					<tr>
						<th><label for="audit_retention_days">Audit Log Retention (Days)</label></th>
						<td><input type="number" id="audit_retention_days" min="7" max="365" value="<?php echo esc_attr( (string) $retention ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="auto_run_on_import">Auto Run On Import</label></th>
						<td><input type="checkbox" id="auto_run_on_import" value="1" <?php checked( $auto_run ); ?> /></td>
					</tr>
				</table>
				<button type="button" class="button button-primary" id="toc-quality-save-btn">Save Settings</button>
			</form>
			
			<hr style="margin-top: 30px; margin-bottom: 30px;">
			
			<h2>Test Engine Connection</h2>
			<button type="button" class="button" id="toc-test-engine-btn">Test Engine Connection</button>
			<span id="toc-test-engine-result" style="margin-left: 10px;"></span>
		</div>
		
		<script>
		document.getElementById('toc-quality-save-btn').addEventListener('click', function() {
			const data = {
				quality_threshold: document.getElementById('quality_threshold').value,
				model: document.getElementById('model').value,
				audit_retention_days: document.getElementById('audit_retention_days').value,
				auto_run_on_import: document.getElementById('auto_run_on_import').checked
			};
			
			this.disabled = true;
			this.textContent = 'Saving...';
			
			wp.apiFetch({
				path: '/tasteofcinemascraped/v1/quality/settings',
				method: 'PUT',
				data: data
			}).then(response => {
				const msg = document.getElementById('toc-quality-settings-msg');
				msg.textContent = 'Settings saved.';
				msg.style.color = 'green';
				this.disabled = false;
				this.textContent = 'Save Settings';
			}).catch(error => {
				const msg = document.getElementById('toc-quality-settings-msg');
				msg.textContent = 'Error: ' + error.message;
				msg.style.color = 'red';
				this.disabled = false;
				this.textContent = 'Save Settings';
			});
		});
		
		document.getElementById('toc-test-engine-btn').addEventListener('click', function() {
			const resSpan = document.getElementById('toc-test-engine-result');
			resSpan.textContent = 'Testing...';
			resSpan.style.color = 'inherit';
			this.disabled = true;
			
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'toc_quality_test_engine',
					_ajax_nonce: '<?php echo wp_create_nonce( 'toc_test_engine' ); ?>'
				})
			}).then(r => r.json()).then(data => {
				if(data.success) {
					resSpan.textContent = '✓ اتصال ناجح — النموذج: ' + data.data.model_version;
					resSpan.style.color = 'green';
				} else {
					resSpan.textContent = '✗ تعذّر الاتصال: ' + (data.data || 'Unknown error');
					resSpan.style.color = 'red';
				}
				this.disabled = false;
			}).catch(err => {
				resSpan.textContent = '✗ تعذّر الاتصال: ' + err;
				resSpan.style.color = 'red';
				this.disabled = false;
			});
		});
		</script>
		<?php
	}

	public static function render_review_queue_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require_once __DIR__ . '/../templates/review-queue.php';
	}

	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && $screen->id === 'toplevel_page_toc-review-queue' ) {
			return;
		}

		$filters = array(
			'status' => 'flagged-for-review,engine-unavailable',
			'per_page' => 1
		);
		$log = TOC_Quality_DB::get_audit_log( $filters );
		if ( $log['total'] > 0 ) {
			$url = admin_url( 'admin.php?page=toc-review-queue' );
			$total = (int) $log['total'];
			echo "<div class='notice notice-warning is-dismissible'><p>لديك $total مقالات معلّقة بانتظار المراجعة — <a href='{$url}'>[عرض قائمة المراجعة]</a></p></div>";
		}
	}

	public static function ajax_test_engine(): void {
		check_ajax_referer( 'toc_test_engine' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		try {
			$assessment = TOC_Quality_Engine::assess( 'مرحبا', 'editorial' );
			$model = TOC_Quality_DB::get_model() ?: 'anthropic/claude-3.5-sonnet';
			wp_send_json_success( array( 'model_version' => $model, 'response' => $assessment ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}

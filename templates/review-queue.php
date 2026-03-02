<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filters = array(
	'status' => 'flagged-for-review,engine-unavailable',
	'per_page' => 100,
);

$audit_data = TOC_Quality_DB::get_audit_log( $filters );
$jobs = $audit_data['items'] ?? [];

$post_counts = array_count_values( array_column( $jobs, 'post_id' ) );

wp_enqueue_script( 'wp-api-fetch' );
?>
<div class="wrap" id="toc-review-queue-app">
	<h1>Translation Review Queue</h1>
	
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>Title</th>
				<th>Content Type</th>
				<th>Status</th>
				<th>Pre Score</th>
				<th>Post Score</th>
				<th>Created At</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $jobs ) ) : ?>
				<tr><td colspan="7">No items pending review.</td></tr>
			<?php else: ?>
				<?php foreach ( $jobs as $job ) : 
					$title = get_the_title( (int) $job['post_id'] );
					$has_multiple = ( $post_counts[ $job['post_id'] ] > 1 );
				?>
				<tr id="job-row-<?php echo (int) $job['job_id']; ?>">
					<td>
						<?php echo esc_html( $title ); ?>
						<?php if ( $has_multiple ) : ?>
							<br><span class="badge" style="background:orange;color:white;padding:2px 4px;border-radius:3px;font-size:10px;">⚠ Multiple active jobs</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $job['content_type'] ); ?></td>
					<td><?php echo esc_html( $job['current_job_status'] ); ?></td>
					<td><?php echo (int) $job['pre_score']; ?></td>
					<td><?php echo (int) $job['post_score']; ?></td>
					<td><?php echo esc_html( $job['created_at'] ); ?></td>
					<td>
						<button class="button button-primary resolve-btn" data-action="approve" data-id="<?php echo (int) $job['job_id']; ?>">Approve</button>
						<button class="button resolve-btn" data-action="reject" data-id="<?php echo (int) $job['job_id']; ?>">Reject</button>
						<span class="action-result" style="display:block;margin-top:5px;font-size:11px;"></span>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.resolve-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			const action = this.dataset.action;
			const jobId = this.dataset.id;
			const row = document.getElementById('job-row-' + jobId);
			const resultSpan = row.querySelector('.action-result');
			
			let note = '';
			if (action === 'reject') {
				note = prompt('Enter rejection note (optional):');
				if (note === null) return; // Cancelled
			}
			
			this.disabled = true;
			resultSpan.textContent = 'Processing...';
			resultSpan.style.color = 'inherit';
			
			wp.apiFetch({
				path: '/tasteofcinemascraped/v1/quality/jobs/' + jobId + '/resolve',
				method: 'POST',
				data: { action: action, rejection_note: note }
			}).then(response => {
				resultSpan.textContent = 'Success: Job ' + action + 'd';
				resultSpan.style.color = 'green';
				setTimeout(() => row.remove(), 1500);
			}).catch(err => {
				resultSpan.textContent = 'Error: ' + err.message;
				resultSpan.style.color = 'red';
				this.disabled = false;
			});
		});
	});
});
</script>

<?php
declare(strict_types=1);

class TOC_Quality_Scheduler {

	public static function register_hooks(): void {
		add_action( 'toc_process_quality_job', array( self::class, 'handle_async_job' ), 10, 2 );
	}

	public static function enqueue_async_job( int $job_id, bool $hint_provided = true ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), 'toc_process_quality_job', array( 'job_id' => $job_id, 'hint_provided' => $hint_provided ) );
		}
	}

	public static function handle_async_job( int $job_id, bool $hint_provided = true ): void {
		TOC_Quality_Engine::process_job( $job_id, $hint_provided );
	}
}

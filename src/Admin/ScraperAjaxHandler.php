<?php

namespace TasteOfCinema\Admin;

use TasteOfCinema\Core\ScraperRunner;

/**
 * Handles the asynchronous background tasks requests from Javascript.
 */
class ScraperAjaxHandler {

    /**
     * Target runner object reference.
     */
    private ScraperRunner $runner;

    /**
     * Initializes hooks.
     *
     * @param ScraperRunner $runner Instance of the runner core class.
     * @return void
     */
    public function init( ScraperRunner $runner ): void {
        $this->runner = $runner;

        add_action( 'wp_ajax_toc_validate_env', [ $this, 'ajax_validate_env' ] );
        add_action( 'wp_ajax_toc_start_scraper', [ $this, 'ajax_start_scraper' ] );
        add_action( 'wp_ajax_toc_poll_progress', [ $this, 'ajax_poll_progress' ] );
        add_action( 'wp_ajax_toc_cleanup_run', [ $this, 'ajax_cleanup_run' ] );
    }

    /**
     * Verifies the nonce header / post data. Non-static wrapper for wp_verify_nonce.
     * Automatically kills execution strictly calling wp_send_json_error if invalid.
     */
    private function verify_security(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        $nonce = $_POST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'toc_scraper_nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid Security Token' ], 401 );
        }
    }

    /**
     * AJAX endpoint to check Python environment.
     * 
     * @return void
     */
    public function ajax_validate_env(): void {
        $this->verify_security();
        
        $env_status = $this->runner->checkEnvironment();

        if ( ! $env_status['valid'] ) {
            wp_send_json_error( [
                'valid'   => false,
                'message' => $env_status['message']
            ], 400 );
        }

        wp_send_json_success( [
            'valid'   => true,
            'message' => $env_status['message']
        ] );
    }

    /**
     * AJAX endpoint to launch the background Python scraper task
     *
     * @return void
     */
    public function ajax_start_scraper(): void {
        $this->verify_security();

        $url   = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $year  = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';

        if ( empty($url) && empty($year) ) {
            wp_send_json_error(['message' => 'URL or Year required']);
        }

        $run_id = $this->runner->start([
            'url'   => $url,
            'year'  => $year,
            'month' => $month
        ]);

        wp_send_json_success( [
            'run_id' => $run_id
        ] );
    }

    /**
     * AJAX endpoint to fetch chunks of process output from the server.
     *
     * @return void
     */
    public function ajax_poll_progress(): void {
        $this->verify_security();

        $run_id = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';
        if ( empty($run_id) ) {
            wp_send_json_error(['message' => 'run_id required']);
        }

        $progress = $this->runner->poll( $run_id );

        wp_send_json_success( [
            'status' => $progress['status'],
            'output' => $progress['output']
        ] );
    }

    /**
     * AJAX endpoint to delete temp logs.
     *
     * @return void
     */
    public function ajax_cleanup_run(): void {
        $this->verify_security();

        $run_id = isset($_POST['run_id']) ? sanitize_text_field($_POST['run_id']) : '';
        if ( ! empty($run_id) ) {
            $this->runner->cleanup( $run_id );
        }

        wp_send_json_success();
    }
}

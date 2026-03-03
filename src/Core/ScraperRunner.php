<?php

namespace TasteOfCinema\Core;

/**
 * Encapsulates execution log logic and environment validation for the Background Python Process.
 * Strictly isolating file executions and terminal outputs below this boundary.
 */
class ScraperRunner {

    /**
     * @var string
     */
    private string $plugin_root;

    /**
     * Initializes setting paths correctly.
     *
     * @param string $plugin_root
     */
    public function __construct( string $plugin_root ) {
        $this->plugin_root = rtrim( $plugin_root, '/' );
    }

    /**
     * Validates if the ".venv" environment exists and dependencies behave logically.
     *
     * @return array Contains boolean "valid" and string "message"
     */
    public function checkEnvironment(): array {
        $python_bin = $this->plugin_root . '/.venv/bin/python3';

        if ( ! is_file( $python_bin ) || ! is_executable( $python_bin ) ) {
            return [
                'valid'   => false,
                'message' => 'Python virtual environment missing or not executable. Please run: python3 -m venv .venv && source .venv/bin/activate && pip install -r tasteofcinemascraped/requirements.txt inside the plugin directory.',
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Environment validated.',
        ];
    }

    /**
     * Spin up background scraping process.
     *
     * @param array $args Keys holding url, year, or month
     * @return string Unique task ID generated for tracking.
     */
    public function start( array $args ): string {
        $run_id    = str_replace( '.', '', uniqid( 'toc_run_', true ) );
        $log_file  = sys_get_temp_dir() . '/' . $run_id . '.log';
        $lock_file = sys_get_temp_dir() . '/' . $run_id . '.lock';
        $sh_file   = sys_get_temp_dir() . '/' . $run_id . '.sh';

        $python_bin = $this->plugin_root . '/.venv/bin/python3';
        $script_dir = $this->plugin_root;

        // Write initial log
        file_put_contents( $log_file, "Starting scraper run ID: {$run_id}\n" );

        $py_args = '';
        if ( ! empty( $args['url'] ) ) {
            $py_args = escapeshellarg( $args['url'] );
            file_put_contents( $log_file, "Target URL: " . $args['url'] . "\n", FILE_APPEND );
        } elseif ( ! empty( $args['year'] ) ) {
            $py_args = '--year ' . escapeshellarg( $args['year'] );
            if ( ! empty( $args['month'] ) ) {
                $py_args .= ' --month ' . escapeshellarg( $args['month'] );
            }
            file_put_contents( $log_file, "Target Batch -> Year: " . $args['year'] . ( ! empty( $args['month'] ) ? " Month: " . $args['month'] : '' ) . "\n", FILE_APPEND );
        } else {
            file_put_contents( $log_file, "Failed: No valid arguments provided.\n", FILE_APPEND );
            return $run_id;
        }

        // Write a shell wrapper script to reliably run in background
        $sh_content = "#!/bin/sh\n";
        $sh_content .= "cd " . escapeshellarg( $script_dir ) . "\n";
        $sh_content .= escapeshellarg( $python_bin ) . " -u -m tasteofcinemascraped " . $py_args . " >> " . escapeshellarg( $log_file ) . " 2>&1\n";
        $sh_content .= "echo done >> " . escapeshellarg( $lock_file ) . "\n";

        file_put_contents( $sh_file, $sh_content );
        chmod( $sh_file, 0755 );

        // Write 'running' to lock file before launching
        file_put_contents( $lock_file, "running\n" );

        // Launch the wrapper script in the background. Redirect stdio to /dev/null so exec() returns immediately.
        exec( 'sh ' . escapeshellarg( $sh_file ) . ' > /dev/null 2>&1 &' );

        return $run_id;
    }

    /**
     * Read the output logs of the given task ID log file to determine completion.
     *
     * @param string $run_id Task Tracking ID
     * @return array Keys: status, output
     */
    public function poll( string $run_id ): array {
        $run_id    = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $run_id );
        $log_file  = sys_get_temp_dir() . '/' . $run_id . '.log';
        $lock_file = sys_get_temp_dir() . '/' . $run_id . '.lock';

        if ( ! file_exists( $log_file ) ) {
            return [
                'status' => 'failed',
                'output' => 'Log file not found. Process may have been cancelled or did not start.'
            ];
        }

        $output = file_get_contents( $log_file );

        // The wrapper shell script appends "done" to lock file when python exits.
        $status = 'running';
        if ( file_exists( $lock_file ) ) {
            $lock_content = file_get_contents( $lock_file );
            if ( str_contains( $lock_content, 'done' ) ) {
                $status = 'completed';
            }
        } else {
            // Lock file was never created - startup failed
            $status = 'failed';
        }

        // Override: if Python printed a traceback the run failed regardless
        if ( str_contains( (string) $output, 'Traceback (most recent call last):' ) ) {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'output' => $output
        ];
    }

    /**
     * Delete temporary log and lock files for a completed run.
     *
     * @param string $run_id
     * @return void
     */
    public function cleanup( string $run_id ): void {
        $run_id    = preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $run_id );
        $log_file  = sys_get_temp_dir() . '/' . $run_id . '.log';
        $lock_file = sys_get_temp_dir() . '/' . $run_id . '.lock';
        $sh_file   = sys_get_temp_dir() . '/' . $run_id . '.sh';

        foreach ( [ $log_file, $lock_file, $sh_file ] as $file ) {
            if ( file_exists( $file ) ) {
                @unlink( $file );
            }
        }
    }
}

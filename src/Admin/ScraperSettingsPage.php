<?php

namespace TasteOfCinema\Admin;

/**
 * Handles the registration and rendering of the Scraper admin interface.
 */
class ScraperSettingsPage {

    /**
     * @var string
     */
    private string $page_slug = 'toc-scraper-settings';

    /**
     * Initializes hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Adds the scraper options page to the admin menu.
     *
     * @return void
     */
    public function add_plugin_page(): void {
        add_submenu_page(
            'toc-review-queue',
            'Run Scraper',
            'Run Scraper',
            'manage_options',
            $this->page_slug,
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Enqueues the necessary CSS and JS for the scraper page.
     *
     * @param string $hook_suffix The current admin page.
     *
     * @return void
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( strpos( $hook_suffix, $this->page_slug ) === false ) {
            return;
        }

        wp_enqueue_style(
            'toc-scraper-admin-css',
            plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin/css/scraper-admin.css',
            [],
            filemtime( plugin_dir_path( dirname( __DIR__ ) ) . 'assets/admin/css/scraper-admin.css' )
        );

        wp_enqueue_script(
            'toc-scraper-admin-js',
            plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin/js/scraper-admin.js',
            [ 'jquery' ],
            filemtime( plugin_dir_path( dirname( __DIR__ ) ) . 'assets/admin/js/scraper-admin.js' ),
            true
        );

        // Send a localized object to JS with nonces and admin-ajax URL
        wp_localize_script( 'toc-scraper-admin-js', 'tocScraperVars', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'toc_scraper_nonce' )
        ] );
    }

    /**
     * Renders the basic HTML structure for the scraper interface.
     *
     * @return void
     */
    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap toc-scraper-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div id="toc-env-status" class="notice notice-info">
                <p><strong>Status:</strong> <span class="toc-env-msg">Checking environment...</span></p>
            </div>

            <div class="toc-scraper-dashboard">
                <div class="toc-scraper-controls">
                    <!-- Single URL Form -->
                    <div class="postbox">
                        <div class="inside">
                            <h2>Run by Specific URL</h2>
                            <p class="description">Paste the Taste of Cinema URL to import a single article.</p>
                            <input type="url" id="toc-scraper-single-url" placeholder="https://www.tasteofcinema.com/..." class="regular-text" style="width: 100%; margin-bottom: 10px;" />
                            <button type="button" id="toc-scraper-run-btn" class="button button-primary" disabled>Run Scraper</button>
                        </div>
                    </div>

                    <!-- Batch Form -->
                    <div class="postbox">
                        <div class="inside">
                            <h2>Run Batch Import</h2>
                            <p class="description">Scrape an entire year, or a specific month in a year.</p>
                            
                            <label for="toc-scraper-batch-year">Year:</label>
                            <input type="number" id="toc-scraper-batch-year" placeholder="2014" min="2000" max="<?php echo date('Y'); ?>" style="margin-bottom: 10px;" />
                            
                            <label for="toc-scraper-batch-month">Month (Optional):</label>
                            <input type="number" id="toc-scraper-batch-month" placeholder="01" min="1" max="12" style="margin-bottom: 10px;" />
                            <br>
                            <button type="button" id="toc-scraper-batch-btn" class="button button-secondary" disabled>Import Batch</button>
                        </div>
                    </div>
                </div>

                <!-- Log output area -->
                <div class="toc-scraper-logs-container postbox">
                    <h2 class="hndle">Process Output log</h2>
                    <div class="inside">
                        <pre id="toc-scraper-logs">Waiting to run...</pre>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

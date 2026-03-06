<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WP_Mock setup
WP_Mock::bootstrap();

// Include classes being tested
require_once dirname( __DIR__ ) . '/src/Models/PostPayload.php';
require_once dirname( __DIR__ ) . '/src/Services/RemotePublisherService.php';
require_once dirname( __DIR__ ) . '/src/Services/PublishRetryQueue.php';
require_once dirname( __DIR__ ) . '/src/Providers/DualPublishingServiceProvider.php';

// Mock WP_Error class for testing without WP Core
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code;
        public $message;
        public $data;
        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return ( $thing instanceof WP_Error );
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TasteOfCinema\Services\PublishRetryQueue;
use TasteOfCinema\Models\PostPayload;
use WP_Mock;

class PublishRetryQueueTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_schedule_retry_adds_to_queue() {
        $queueService = new PublishRetryQueue();
        $payload = new PostPayload( 'Retry Title', 'Retry Content' );

        WP_Mock::userFunction( 'get_option', [
            'args'   => [ PublishRetryQueue::OPTION_NAME, [] ],
            'times'  => 1,
            'return' => [],
        ] );

        WP_Mock::userFunction( 'update_option', [
            'times'  => 1,
            'return' => true,
        ] );

        $queueService->scheduleRetry( 10, $payload, 'Connection timeout' );
        
        // WP_Mock handles asserting that the update_option hook was called
        $this->assertTrue( true );
    }
}

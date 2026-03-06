<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TasteOfCinema\Services\RemotePublisherService;
use TasteOfCinema\Models\PostPayload;
use WP_Mock;

class RemotePublisherServiceTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
        $_ENV['REMOTE_WP_URL'] = 'http://remote.test';
        $_ENV['REMOTE_WP_USER'] = 'testuser';
        $_ENV['REMOTE_WP_APP_PASSWORD'] = 'testpass';
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    public function test_upload_media_fails_if_file_not_found() {
        $service = new RemotePublisherService();
        
        $result = $service->uploadMedia( '/non/existent/file.jpg' );
        
        $this->assertTrue( is_wp_error( $result ) );
        $this->assertEquals( 'file_not_found', $result->code );
    }

    public function test_publish_post_success() {
        $service = new RemotePublisherService();
        $payload = new PostPayload( 'Test Title', 'Test Content' );

        WP_Mock::userFunction( 'wp_remote_post', [
            'times'  => 1,
            'return' => [ 'response' => [ 'code' => 201 ] ],
        ] );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code', [
            'times'  => 1,
            'return' => 201,
        ] );

        WP_Mock::userFunction( 'wp_remote_retrieve_body', [
            'times'  => 1,
            'return' => json_encode( [ 'id' => 123 ] ),
        ] );

        WP_Mock::userFunction( 'wp_json_encode', [
            'return' => json_encode( $payload->toArray() ),
        ] );

        $result = $service->publishPost( $payload );

        $this->assertEquals( 123, $result );
    }
}

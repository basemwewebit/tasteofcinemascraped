<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use TasteOfCinema\Models\PostPayload;

class PostPayloadTest extends TestCase {

    public function test_to_array_serialization_basic() {
        $payload = new PostPayload(
            'Test Title',
            'Test Content'
        );

        $array = $payload->toArray();

        $this->assertEquals( 'Test Title', $array['title'] );
        $this->assertEquals( 'Test Content', $array['content'] );
        $this->assertEquals( 'publish', $array['status'] );
        $this->assertArrayNotHasKey( 'featured_media', $array );
        $this->assertArrayNotHasKey( 'meta', $array );
    }

    public function test_to_array_serialization_full() {
        $payload = new PostPayload(
            'Title 2',
            'Content 2',
            'draft',
            [ 1, 2 ],
            [ 'tag' ],
            15,
            [ 'source_url' => 'http://example.com' ]
        );

        $array = $payload->toArray();
        $this->assertEquals( 'draft', $array['status'] );
        $this->assertEquals( [ 1, 2 ], $array['categories'] );
        $this->assertEquals( [ 'tag' ], $array['tags'] );
        $this->assertEquals( 15, $array['featured_media'] );
        $this->assertEquals( [ 'source_url' => 'http://example.com' ], $array['meta'] );
    }
}

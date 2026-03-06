<?php

namespace TasteOfCinema\Models;

/**
 * DTO representing the post payload for dual site publishing.
 */
class PostPayload {
    public string $title;
    public string $content;
    public string $status;
    public array $categories;
    public array $tags;
    public ?int $featured_media;
    public array $meta;
    public ?int $author;
    public ?string $date;
    public ?string $slug;

    public function __construct(
        string $title,
        string $content,
        string $status = 'publish',
        array $categories = [],
        array $tags = [],
        ?int $featured_media = null,
        array $meta = [],
        ?int $author = null,
        ?string $date = null,
        ?string $slug = null
    ) {
        $this->title          = $title;
        $this->content        = $content;
        $this->status         = $status;
        $this->categories     = $categories;
        $this->tags           = $tags;
        $this->featured_media = $featured_media;
        $this->meta           = $meta;
        $this->author         = $author;
        $this->date           = $date;
        $this->slug           = $slug;
    }

    /**
     * Convert payload to array format expected by WP REST API.
     */
    public function toArray(): array {
        $data = [
            'title'      => $this->title,
            'content'    => $this->content,
            'status'     => $this->status,
            'categories' => $this->categories,
            'tags'       => $this->tags,
        ];

        if ( $this->featured_media !== null ) {
            $data['featured_media'] = $this->featured_media;
        }

        if ( ! empty( $this->meta ) ) {
            $data['meta'] = $this->meta;
        }

        if ( $this->author !== null ) {
            $data['author'] = $this->author;
        }

        if ( $this->date !== null ) {
            $data['date'] = $this->date;
        }

        if ( $this->slug !== null ) {
            $data['slug'] = $this->slug;
        }

        return $data;
    }
}

<?php

namespace TasteOfCinema\Providers;

use TasteOfCinema\Services\RemotePublisherService;
use TasteOfCinema\Services\PublishRetryQueue;
use TasteOfCinema\Models\PostPayload;

class DualPublishingServiceProvider {

    public function init(): void {
        add_action( 'init', [ $this, 'registerCronSchedule' ] );
        add_action( PublishRetryQueue::HOOK_NAME, [ $this, 'processRetryQueue' ] );
        add_action( 'transition_post_status', [ $this, 'onPostStatusChange' ], 10, 3 );
    }

    public function registerCronSchedule(): void {
        if ( ! wp_next_scheduled( PublishRetryQueue::HOOK_NAME ) ) {
            wp_schedule_event( time(), 'hourly', PublishRetryQueue::HOOK_NAME );
        }
    }

    public function processRetryQueue(): void {
        $queue = new PublishRetryQueue();
        $queue->processQueue();
    }

    public function onPostStatusChange( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }

        // Check if this is a scraped and translated post
        if ( ! defined( 'TCOC_SCRAPED_META_SOURCE_URL' ) ) {
            return;
        }

        $source_url = get_post_meta( $post->ID, TCOC_SCRAPED_META_SOURCE_URL, true );
        if ( empty( $source_url ) ) {
            return;
        }

        // Also check if we've already synced it to prevent infinite loops or multiple syncs
        $has_synced = get_post_meta( $post->ID, '_toc_dual_publish_synced', true );
        if ( ! empty( $has_synced ) ) {
            return;
        }
        
        $payload = $this->createPayloadFromPost( $post );
        $remotePublisher = new RemotePublisherService();

        // Resolve Author ID on Remote Site
        $local_author = get_userdata( $post->post_author );
        if ( $local_author ) {
            $remote_author_id = $remotePublisher->resolveAuthor( $local_author );
            if ( ! is_wp_error( $remote_author_id ) ) {
                $payload->author = $remote_author_id;
            } else {
                error_log( 'Taste Of Cinema Dual Publish - Author Resolve Failed: ' . $remote_author_id->get_error_message() );
                // Fallback to null (it will use the remote authenticated user by default)
                $payload->author = null;
            }
        }

        // Handle Featured Image Upload First
        $featured_image_id = get_post_thumbnail_id( $post->ID );
        if ( $featured_image_id ) {
            $local_path = get_attached_file( $featured_image_id );
            if ( $local_path ) {
                $remote_media_id = $remotePublisher->uploadMedia( $local_path );
                if ( ! is_wp_error( $remote_media_id ) ) {
                    $payload->featured_media = $remote_media_id['id'];
                } else {
                    error_log( 'Taste Of Cinema Dual Publish - Media Upload Failed: ' . $remote_media_id->get_error_message() );
                }
            }
        }

        // Inline Images Processing
        $content = $payload->content;
        if ( ! empty( $content ) && strpos( $content, '<img' ) !== false ) {
            $upload_dir = wp_upload_dir();
            $baseurl    = $upload_dir['baseurl'];
            $basedir    = $upload_dir['basedir'];
            
            if ( preg_match_all( '/src="([^"]+)"/i', $content, $matches ) ) {
                $unique_srcs = array_unique( $matches[1] );
                foreach ( $unique_srcs as $src ) {
                    if ( strpos( $src, $baseurl ) === 0 ) {
                        $relative = str_replace( $baseurl, '', $src );
                        $relative = explode( '?', $relative )[0];
                        $local_path = $basedir . $relative;
                        
                        if ( file_exists( $local_path ) ) {
                            $remote_image = $remotePublisher->uploadMedia( $local_path );
                            if ( ! is_wp_error( $remote_image ) && ! empty( $remote_image['source_url'] ) ) {
                                $content = str_replace( $src, $remote_image['source_url'], $content );
                            }
                        }
                    }
                }
                $content = preg_replace( '/srcset="([^"]*)"/i', '', $content );
                $content = preg_replace( '/sizes="([^"]*)"/i', '', $content );
                $payload->content = $content;
            }
        }

        $result = $remotePublisher->publishPost( $payload );
        
        // Mark as processed regardless of success to prevent resyncs on local update, conforming to FR-007
        update_post_meta( $post->ID, '_toc_dual_publish_synced', current_time( 'mysql', true ) );

        if ( is_wp_error( $result ) ) {
            $queue = new PublishRetryQueue();
            $queue->scheduleRetry( $post->ID, $payload, $result->get_error_message() );
        }
    }

    private function createPayloadFromPost( \WP_Post $post ): PostPayload {
        $categories = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
        
        // FR-008: Default to Uncategorized if empty
        if ( empty( $categories ) ) {
            $default_cat = get_option( 'default_category' );
            if ( $default_cat ) {
                $categories = [ (int) $default_cat ];
            }
        }

        $tags_data = [];
        $tags = wp_get_post_tags( $post->ID );
        if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
            foreach ( $tags as $tag ) {
                $tags_data[] = [ 'name' => $tag->name, 'slug' => $tag->slug ];
            }
        }

        global $wpdb;
        $quality_score = $wpdb->get_var( $wpdb->prepare( "SELECT post_score FROM {$wpdb->prefix}toc_translation_jobs WHERE post_id = %d ORDER BY id DESC LIMIT 1", $post->ID ) );
        $meta = [];
        if ( $quality_score !== null ) {
            $meta['_toc_translation_quality_score'] = (int) $quality_score;
        }

        return new PostPayload(
            $post->post_title,
            $post->post_content,
            'publish',
            $categories,
            $tags_data,
            null,
            $meta,
            null,
            $post->post_date,
            $post->post_name
        );
    }
}

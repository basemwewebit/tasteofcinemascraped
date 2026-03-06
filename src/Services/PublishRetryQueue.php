<?php

namespace TasteOfCinema\Services;

use TasteOfCinema\Models\PostPayload;

class PublishRetryQueue {

    const HOOK_NAME = 'tasteofcinema_dual_publish_retry';

    const OPTION_NAME = 'tasteofcinema_dual_publish_queue';
    const MAX_ATTEMPTS = 5;

    /**
     * Schedule a payload for retry.
     *
     * @param int         $local_post_id The local post ID.
     * @param PostPayload $payload       The payload to retry.
     * @param string      $last_error    The error message from the failed attempt.
     */
    public function scheduleRetry( int $local_post_id, PostPayload $payload, string $last_error ): void {
        $queue = get_option( self::OPTION_NAME, [] );
        if ( ! is_array( $queue ) ) {
            $queue = [];
        }

        if ( isset( $queue[ $local_post_id ] ) ) {
            $queue[ $local_post_id ]['attempts']++;
            $queue[ $local_post_id ]['last_error'] = $last_error;
        } else {
            $queue[ $local_post_id ] = [
                'payload'    => serialize( $payload ),
                'attempts'   => 1,
                'last_error' => $last_error,
                'added_at'   => time(),
            ];
        }

        update_option( self::OPTION_NAME, $queue, false );
    }

    /**
     * Process the queue, attempting to re-publish scheduled payloads.
     */
    public function processQueue(): void {
        $queue = get_option( self::OPTION_NAME, [] );
        if ( empty( $queue ) || ! is_array( $queue ) ) {
            return;
        }

        $remotePublisher = new RemotePublisherService();
        $updated_queue   = $queue;

        foreach ( $queue as $post_id => $job ) {
            if ( $job['attempts'] >= self::MAX_ATTEMPTS ) {
                unset( $updated_queue[ $post_id ] );
                continue;
            }

            $payload = unserialize( $job['payload'] );
            if ( ! $payload instanceof PostPayload ) {
                unset( $updated_queue[ $post_id ] );
                continue;
            }

            $result = $remotePublisher->publishPost( $payload );

            if ( ! is_wp_error( $result ) ) {
                unset( $updated_queue[ $post_id ] );
            } else {
                $updated_queue[ $post_id ]['attempts']++;
                $updated_queue[ $post_id ]['last_error'] = $result->get_error_message();
            }
        }

        update_option( self::OPTION_NAME, $updated_queue, false );
    }
}

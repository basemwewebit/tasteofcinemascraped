<?php

namespace TasteOfCinema\Services;

use TasteOfCinema\Models\PostPayload;
use WP_Error;

class RemotePublisherService {

    private string $remote_url;
    private string $remote_user;
    private string $remote_password;

    public function __construct() {
        $this->remote_url      = rtrim( $_ENV['REMOTE_WP_URL'] ?? '', '/' );
        $this->remote_user     = $_ENV['REMOTE_WP_USER'] ?? '';
        $this->remote_password = $_ENV['REMOTE_WP_APP_PASSWORD'] ?? '';

        if ( empty( $this->remote_url ) ) {
            $this->loadEnvVariables();
        }
    }

    private function loadEnvVariables(): void {
        $env_path = dirname( __DIR__, 2 ) . '/tasteofcinemascraped/.env';
        if ( ! file_exists( $env_path ) ) {
            return;
        }

        $lines = file( $env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        foreach ( $lines as $line ) {
            if ( strpos( trim( $line ), '#' ) === 0 ) {
                continue;
            }
            if ( strpos( $line, '=' ) !== false ) {
                list( $name, $value ) = explode( '=', $line, 2 );
                $name  = trim( $name );
                $value = trim( $value, " \t\n\r\0\x0B\"'" );

                if ( $name === 'REMOTE_WP_URL' ) {
                    $this->remote_url = rtrim( $value, '/' );
                } elseif ( $name === 'REMOTE_WP_USER' ) {
                    $this->remote_user = $value;
                } elseif ( $name === 'REMOTE_WP_APP_PASSWORD' ) {
                    $this->remote_password = $value;
                }
            }
        }
    }

    /**
     * Get the basic auth header for WP REST API requests.
     */
    private function getAuthHeader(): array {
        if ( empty( $this->remote_user ) || empty( $this->remote_password ) ) {
            return [];
        }
        return [
            'Authorization' => 'Basic ' . base64_encode( $this->remote_user . ':' . $this->remote_password ),
        ];
    }

    /**
     * Upload an attachment to the remote site.
     *
     * @param string $local_file_path The absolute path to the local file.
     * @return array|WP_Error An array containing 'id' and 'source_url', or a WP_Error on failure.
     */
    public function uploadMedia( string $local_file_path ) {
        if ( empty( $this->remote_url ) ) {
            return new WP_Error( 'missing_remote_url', 'Remote WP URL is not configured.' );
        }

        if ( ! file_exists( $local_file_path ) ) {
            return new WP_Error( 'file_not_found', 'Local file does not exist.' );
        }

        $filename = wp_basename( $local_file_path );
        $file_contents = file_get_contents( $local_file_path );

        $headers = array_merge( $this->getAuthHeader(), [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Type'        => function_exists('mime_content_type') ? mime_content_type( $local_file_path ) : 'application/octet-stream',
        ] );

        $response = wp_remote_post( $this->remote_url . '/wp-json/wp/v2/media', [
            'headers' => $headers,
            'body'    => $file_contents,
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( $response_code !== 201 ) {
            return new WP_Error( 'remote_media_upload_failed', 'Failed to upload media to remote site. HTTP ' . $response_code, $data );
        }

        if ( empty( $data['id'] ) || empty( $data['source_url'] ) ) {
            return new WP_Error( 'invalid_remote_response', 'Invalid response from remote site, missing attachment ID or URL.', $data );
        }

        return [
            'id'         => (int) $data['id'],
            'source_url' => $data['source_url'],
        ];
    }

    /**
     * Publish a post payload to the remote site.
     *
     * @param PostPayload $payload
     * @return int|WP_Error The remote post ID or a WP_Error on failure.
     */
    public function publishPost( PostPayload $payload ) {
        if ( empty( $this->remote_url ) ) {
            return new WP_Error( 'missing_remote_url', 'Remote WP URL is not configured.' );
        }

        $headers = array_merge( $this->getAuthHeader(), [
            'Content-Type' => 'application/json',
        ] );

        $response = wp_remote_post( $this->remote_url . '/wp-json/wp/v2/posts', [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload->toArray() ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( $response_code !== 201 ) {
            return new WP_Error( 'remote_publish_failed', 'Failed to publish post to remote site. HTTP ' . $response_code, $data );
        }

        if ( empty( $data['id'] ) ) {
            return new WP_Error( 'invalid_remote_response', 'Invalid response from remote site, missing post ID.', $data );
        }

        return (int) $data['id'];
    }

    /**
     * Resolve the remote author ID by finding or creating the user.
     *
     * @param \WP_User $local_user
     * @return int|\WP_Error
     */
    public function resolveAuthor( \WP_User $local_user ) {
        if ( empty( $this->remote_url ) ) {
            return new WP_Error( 'missing_remote_url', 'Remote WP URL is not configured.' );
        }

        $headers = $this->getAuthHeader();

        // 1. Search for the user by email
        $search_url = $this->remote_url . '/wp-json/wp/v2/users?search=' . urlencode( $local_user->user_email );
        $response = wp_remote_get( $search_url, [
            'headers' => $headers,
            'timeout' => 15,
        ] );

        if ( ! is_wp_error( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $data ) && is_array( $data ) && ! empty( $data[0]['id'] ) ) {
                return (int) $data[0]['id'];
            }
        }

        // 2. Search for the user by exact login just in case email search missed
        $search_url = $this->remote_url . '/wp-json/wp/v2/users?search=' . urlencode( $local_user->user_login );
        $response = wp_remote_get( $search_url, [
            'headers' => $headers,
            'timeout' => 15,
        ] );

        if ( ! is_wp_error( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $data ) && is_array( $data ) && ! empty( $data[0]['id'] ) ) {
                return (int) $data[0]['id'];
            }
        }

        // 3. Create the user
        $create_payload = [
            'username'    => $local_user->user_login,
            'email'       => $local_user->user_email,
            'password'    => wp_generate_password( 24 ),
            'name'        => $local_user->display_name,
            'first_name'  => $local_user->first_name,
            'last_name'   => $local_user->last_name,
            'description' => $local_user->description,
            'roles'       => [ 'author' ],
        ];

        $create_url = $this->remote_url . '/wp-json/wp/v2/users';
        $create_response = wp_remote_post( $create_url, [
            'headers' => array_merge( $headers, [ 'Content-Type' => 'application/json' ] ),
            'body'    => wp_json_encode( $create_payload ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $create_response ) ) {
            return $create_response;
        }

        $create_code = wp_remote_retrieve_response_code( $create_response );
        $create_data = json_decode( wp_remote_retrieve_body( $create_response ), true );

        if ( $create_code !== 201 ) {
            return new WP_Error( 'remote_user_creation_failed', 'Failed to create user on remote site. HTTP ' . $create_code, $create_data );
        }

        if ( empty( $create_data['id'] ) ) {
            return new WP_Error( 'invalid_remote_response', 'Invalid response when creating user on remote site.', $create_data );
        }

        return (int) $create_data['id'];
    }
}

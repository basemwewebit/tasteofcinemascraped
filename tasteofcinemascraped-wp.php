<?php
/**
 * Plugin Name: Taste of Cinema Scraped Import
 * Description: REST endpoint to import scraped + translated articles from tasteofcinemascraped (Python). Requires secret key.
 * Version: 1.1.0
 * Author: Taste of Cinema Arabi
 */

/**
 * CHANGELOG:
 * 1.1.0: [BREAKING] Disabled dynamic category creation for 'category' taxonomy. 
 *        Implemented predefined taxonomy (10 categories).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

require_once __DIR__ . '/includes/class-toc-quality-db.php';
// Load other classes when they are implemented, or load them here now since they are empty
require_once __DIR__ . '/includes/class-toc-quality-engine.php';
require_once __DIR__ . '/includes/class-toc-quality-scheduler.php';
require_once __DIR__ . '/includes/class-toc-quality-rest.php';
require_once __DIR__ . '/includes/class-toc-quality-admin.php';
require_once __DIR__ . '/includes/class-toc-category-config.php';
require_once __DIR__ . '/includes/class-toc-category-manager.php';

register_activation_hook(
	__FILE__,
	function () {
		TOC_Quality_DB::install_schema();
		TOC_Category_Manager::seed_all();
	}
);

define( 'TCOC_SCRAPED_OPTION_SECRET', 'tasteofcinemascraped_secret' );
define( 'TCOC_SCRAPED_META_SOURCE_URL', '_tasteofcinema_source_url' );
define( 'TCOC_SCRAPED_WEBP_QUALITY', 82 );

add_action( 'rest_api_init', 'tasteofcinemascraped_register_routes' );
add_action( 'admin_menu', 'tasteofcinemascraped_admin_menu' );
add_action( 'admin_init', 'tasteofcinemascraped_save_settings' );

// Quality Engine Hooks
add_action( 'rest_api_init', array( 'TOC_Quality_REST', 'register_routes' ) );
add_action( 'admin_menu', array( 'TOC_Quality_Admin', 'register_menus' ) );
add_action( 'admin_init', array( 'TOC_Quality_Admin', 'register_ajax_hooks' ) );
add_action( 'admin_init', array( 'TOC_Quality_Admin', 'register_list_table_hooks' ) );
add_action( 'add_meta_boxes', array( 'TOC_Quality_Admin', 'register_category_metabox' ) );
add_action( 'save_post', array( 'TOC_Quality_Admin', 'save_category_metabox' ) );
add_action( 'init', array( 'TOC_Quality_Scheduler', 'register_hooks' ) );
add_action( 'tasteofcinemascraped_seed_categories', array( 'TOC_Category_Manager', 'seed_all' ) );

function tasteofcinemascraped_admin_menu() {
	add_options_page(
		__( 'Taste of Cinema Scraped', 'tasteofcinemascraped-wp' ),
		__( 'TOC Scraped', 'tasteofcinemascraped-wp' ),
		'manage_options',
		'tasteofcinemascraped-wp',
		'tasteofcinemascraped_settings_page'
	);
}

function tasteofcinemascraped_settings_page() {
	$secret = get_option( TCOC_SCRAPED_OPTION_SECRET, '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Taste of Cinema Scraped Import', 'tasteofcinemascraped-wp' ); ?></h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'tasteofcinemascraped_save', 'tasteofcinemascraped_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="tasteofcinemascraped_secret"><?php esc_html_e( 'Import secret key', 'tasteofcinemascraped-wp' ); ?></label></th>
					<td><input type="password" id="tasteofcinemascraped_secret" name="tasteofcinemascraped_secret" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" autocomplete="off" /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p><?php esc_html_e( 'Use this secret in the Python client (header X-Tasteofcinema-Secret or param secret).', 'tasteofcinemascraped-wp' ); ?></p>
	</div>
	<?php
}

function tasteofcinemascraped_save_settings() {
	if ( ! isset( $_POST['tasteofcinemascraped_nonce'] ) || ! wp_verify_nonce( $_POST['tasteofcinemascraped_nonce'], 'tasteofcinemascraped_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['tasteofcinemascraped_secret'] ) ) {
		update_option( TCOC_SCRAPED_OPTION_SECRET, sanitize_text_field( $_POST['tasteofcinemascraped_secret'] ) );
	}
}

function tasteofcinemascraped_register_routes() {
	register_rest_route( 'tasteofcinemascraped/v1', '/import', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'tasteofcinemascraped_permission_check',
		'callback'            => 'tasteofcinemascraped_import_callback',
		'args'                => array(
			'source_url'    => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ),
			'title'         => array( 'required' => true, 'type' => 'string' ),
			'content'       => array( 'required' => true, 'type' => 'string' ),
			'post_name'     => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			'author_name'   => array( 'required' => true, 'type' => 'string' ),
			'author_bio'    => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			'author_slug'   => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			'date'          => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			'tags'          => array( 'required' => false, 'type' => 'array', 'default' => array() ),
			'categories'    => array( 'required' => false, 'type' => 'array', 'default' => array() ),
			'images'        => array( 'required' => false, 'type' => 'array', 'default' => array() ),
		),
	) );
}

function tasteofcinemascraped_permission_check( WP_REST_Request $request ) {
	$secret = get_option( TCOC_SCRAPED_OPTION_SECRET, '' );
	if ( empty( $secret ) ) {
		return new WP_Error( 'tasteofcinemascraped_not_configured', __( 'Import secret not set.', 'tasteofcinemascraped-wp' ), array( 'status' => 503 ) );
	}
	$header = $request->get_header( 'X-Tasteofcinema-Secret' );
	$param  = $request->get_param( 'secret' );
	if ( (string) $header !== (string) $secret && (string) $param !== (string) $secret ) {
		return new WP_Error( 'tasteofcinemascraped_forbidden', __( 'Invalid secret.', 'tasteofcinemascraped-wp' ), array( 'status' => 403 ) );
	}
	return true;
}

function tasteofcinemascraped_import_callback( WP_REST_Request $request ) {
	TOC_Category_Manager::seed_all(); // Ensure categories exist before resolution.

	$source_url = $request->get_param( 'source_url' );
	$existing   = tasteofcinemascraped_find_post_by_source_url( $source_url );
	if ( $existing ) {
		return new WP_REST_Response( array(
			'skipped' => true,
			'message' => __( 'Post already imported for this URL.', 'tasteofcinemascraped-wp' ),
			'post_id' => $existing,
		), 200 );
	}

	$author_id = tasteofcinemascraped_get_or_create_author(
		$request->get_param( 'author_name' ),
		$request->get_param( 'author_bio' ),
		$request->get_param( 'author_slug' )
	);
	if ( is_wp_error( $author_id ) ) {
		return new WP_REST_Response( array( 'error' => $author_id->get_error_message() ), 500 );
	}

	$content = $request->get_param( 'content' );
	$images  = $request->get_param( 'images' );
	if ( ! empty( $images ) && is_array( $images ) ) {
		$content = tasteofcinemascraped_replace_remote_images_with_uploads( $content, $images );
	}

	$post_data = array(
		'post_title'   => $request->get_param( 'title' ),
		'post_content' => $content,
		'post_status'  => 'draft',
		'post_author'  => $author_id,
		'post_type'    => 'post',
	);
	$post_name = $request->get_param( 'post_name' );
	if ( ! empty( $post_name ) && is_string( $post_name ) ) {
		$post_data['post_name'] = sanitize_title( $post_name );
	}
	if ( ! empty( $request->get_param( 'date' ) ) ) {
		$date_str = trim( (string) $request->get_param( 'date' ) );
		$tz       = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		// Parse as date in site timezone (e.g. 2026-02-18) so original publish date is preserved
		$d = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date_str, $tz );
		if ( ! $d ) {
			$ts = strtotime( $date_str );
			$d = $ts ? \DateTimeImmutable::createFromFormat( 'U', (string) $ts, $tz ) : null;
		} else {
			$d = $d->setTime( 0, 0, 0 );
		}
		if ( $d ) {
			$post_data['post_date']     = $d->format( 'Y-m-d H:i:s' );
			$post_data['post_date_gmt'] = $d->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}
	}

	$post_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( array( 'error' => $post_id->get_error_message() ), 500 );
	}

	update_post_meta( $post_id, TCOC_SCRAPED_META_SOURCE_URL, $source_url );

	$tag_ids = tasteofcinemascraped_get_or_create_terms( $request->get_param( 'tags' ), 'post_tag' );
	$cat_id  = TOC_Category_Manager::resolve( $request->get_param( 'categories' ), (int) $post_id );
	if ( ! empty( $tag_ids ) ) {
		wp_set_post_terms( $post_id, $tag_ids, 'post_tag' );
	}
	if ( ! empty( $cat_id ) ) {
		wp_set_post_terms( $post_id, array( $cat_id ), 'category' );
	}

	$featured_attachment_id = tasteofcinemascraped_get_first_uploaded_attachment_id();
	if ( $featured_attachment_id ) {
		set_post_thumbnail( $post_id, $featured_attachment_id );
	}

	do_action( 'tasteofcinemascraped_post_imported', $post_id, 'editorial' );

	return new WP_REST_Response( array(
		'success' => true,
		'post_id' => $post_id,
	), 201 );
}

add_action( 'tasteofcinemascraped_post_imported', 'toc_quality_auto_run', 10, 2 );

function toc_quality_auto_run( int $post_id, string $content_type = 'editorial' ) {
	if ( ! class_exists( 'TOC_Quality_DB' ) ) return;
	
	if ( TOC_Quality_DB::get_auto_run_on_import() ) {
		$job_id = TOC_Quality_DB::create_job( $post_id, $content_type, get_current_user_id() ?: 1 );
		if ( class_exists( 'TOC_Quality_Scheduler' ) ) {
			TOC_Quality_Scheduler::enqueue_async_job( $job_id, true );
		}
	}
}

function tasteofcinemascraped_find_post_by_source_url( $url ) {
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'any',
		'meta_key'       => TCOC_SCRAPED_META_SOURCE_URL,
		'meta_value'     => $url,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	return ! empty( $posts ) ? (int) $posts[0] : 0;
}

function tasteofcinemascraped_slug_from_name( $name ) {
	$slug = sanitize_title( $name );
	$slug = preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $slug ) );
	return trim( preg_replace( '/-+/', '-', $slug ), '-' );
}

function tasteofcinemascraped_get_or_create_author( $author_name, $author_bio, $author_slug = '' ) {
	if ( empty( $author_name ) ) {
		return get_current_user_id() ?: 1;
	}
	// Prefer English slug for login so permalinks stay in English
	$login_base = ! empty( $author_slug ) ? tasteofcinemascraped_slug_from_name( $author_slug ) : tasteofcinemascraped_slug_from_name( $author_name );
	$user      = get_user_by( 'login', $login_base );
	if ( $user ) {
		// Always update bio when provided (e.g. from last page of article)
		if ( $author_bio !== '' && $author_bio !== null ) {
			wp_update_user( array( 'ID' => $user->ID, 'description' => $author_bio ) );
		}
		return $user->ID;
	}
	$login = $login_base;
	$i     = 0;
	while ( get_user_by( 'login', $login ) ) {
		$i++;
		$login = $login_base . '-' . $i;
	}
	$email   = $login . '@tasteofcinema.local';
	$user_id = wp_create_user( $login, wp_generate_password( 24, true ), $email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}
	wp_update_user( array(
		'ID'          => $user_id,
		'display_name'=> $author_name,
		'description' => $author_bio,
		'role'        => 'author',
	) );
	return $user_id;
}

function tasteofcinemascraped_get_or_create_terms( $items, $taxonomy ) {
	if ( $taxonomy === 'category' ) {
		// INVARIANT: categories are now handled by TOC_Category_Manager::resolve()
		// and must never be created dynamically in this pipeline.
		return array();
	}

	if ( empty( $items ) || ! is_array( $items ) ) {
		return array();
	}
	$ids = array();
	foreach ( $items as $item ) {
		$name = '';
		$slug = '';
		if ( is_array( $item ) && isset( $item['name'] ) ) {
			$name = trim( (string) $item['name'] );
			$slug = isset( $item['slug'] ) ? tasteofcinemascraped_slug_from_name( (string) $item['slug'] ) : '';
		} elseif ( is_string( $item ) ) {
			$name = trim( $item );
		}
		if ( $name === '' ) {
			continue;
		}
		if ( $slug === '' ) {
			$slug = tasteofcinemascraped_slug_from_name( $name );
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term ) {
			$ids[] = $term->term_id;
			continue;
		}
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term ) {
			$ids[] = $term->term_id;
			continue;
		}
		$r = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( ! is_wp_error( $r ) ) {
			$ids[] = $r['term_id'];
		}
	}
	return array_unique( array_filter( $ids ) );
}

/**
 * Convert a downloaded image file to WebP (compressed). Returns path to WebP temp file or original on failure.
 *
 * @param string      $tmp_path   Path to temp image file (e.g. from download_url).
 * @param string|null $source_url Optional original image URL (used for base filename).
 * @return array{path: string, name: string} Path and filename for sideload; path may be original if conversion failed.
 */
function tasteofcinemascraped_convert_to_webp( $tmp_path, $source_url = null ) {
	$quality  = (int) TCOC_SCRAPED_WEBP_QUALITY;
	$out_path = $tmp_path . '.webp';
	$base_name = 'image.jpg';
	if ( ! empty( $source_url ) ) {
		$path = parse_url( $source_url, PHP_URL_PATH );
		if ( $path ) {
			$base_name = wp_basename( $path ) ?: $base_name;
		}
	}
	if ( ! $base_name || preg_match( '/^\./', $base_name ) ) {
		$base_name = 'image.jpg';
	}
	$out_name = preg_replace( '/\.(jpe?g|png|gif|webp)$/i', '.webp', $base_name );
	if ( $out_name === $base_name ) {
		$out_name = $base_name . '.webp';
	}

	if ( ! function_exists( 'imagewebp' ) || ! is_readable( $tmp_path ) ) {
		return array( 'path' => $tmp_path, 'name' => $base_name );
	}

	$image = false;
	$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $tmp_path ) : '';
	if ( empty( $mime ) && function_exists( 'finfo_open' ) ) {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime = $finfo ? finfo_file( $finfo, $tmp_path ) : '';
		if ( $finfo ) {
			finfo_close( $finfo );
		}
	}
	switch ( $mime ) {
		case 'image/jpeg':
		case 'image/jpe':
			$image = @imagecreatefromjpeg( $tmp_path );
			break;
		case 'image/png':
			$image = @imagecreatefrompng( $tmp_path );
			if ( $image ) {
				imagealphablending( $image, true );
				imagesavealpha( $image, true );
			}
			break;
		case 'image/gif':
			$image = @imagecreatefromgif( $tmp_path );
			break;
		case 'image/webp':
			if ( function_exists( 'imagecreatefromwebp' ) ) {
				$image = @imagecreatefromwebp( $tmp_path );
			}
			break;
	}
	if ( ! $image ) {
		$image = @imagecreatefromjpeg( $tmp_path ) ?: @imagecreatefrompng( $tmp_path ) ?: ( function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $tmp_path ) : false );
		if ( ! $image && function_exists( 'imagecreatefromgif' ) ) {
			$image = @imagecreatefromgif( $tmp_path );
		}
	}
	if ( ! $image ) {
		return array( 'path' => $tmp_path, 'name' => $base_name );
	}

	$written = imagewebp( $image, $out_path, $quality );
	imagedestroy( $image );
	if ( ! $written || ! is_readable( $out_path ) ) {
		@unlink( $out_path );
		return array( 'path' => $tmp_path, 'name' => $base_name );
	}
	@unlink( $tmp_path );
	return array( 'path' => $out_path, 'name' => $out_name );
}

function tasteofcinemascraped_replace_remote_images_with_uploads( $content, $images ) {
	global $tasteofcinemascraped_url_to_attachment;
	$tasteofcinemascraped_url_to_attachment = array();
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( $images as $img ) {
		$url = is_array( $img ) ? ( $img['url'] ?? '' ) : (string) $img;
		if ( $url === '' ) {
			continue;
		}
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}
		$converted = tasteofcinemascraped_convert_to_webp( $tmp, $url );
		$file_array = array(
			'name'     => $converted['name'],
			'tmp_name' => $converted['path'],
		);
		$id = media_handle_sideload( $file_array, 0, null );
		if ( is_wp_error( $id ) ) {
			@unlink( $converted['path'] );
			continue;
		}
		$new_url = wp_get_attachment_url( $id );
		if ( $new_url ) {
			$tasteofcinemascraped_url_to_attachment[ $url ] = array( 'id' => $id, 'url' => $new_url );
		}
	}

	if ( empty( $tasteofcinemascraped_url_to_attachment ) ) {
		return $content;
	}
	foreach ( $tasteofcinemascraped_url_to_attachment as $old_url => $data ) {
		$content = str_replace( $old_url, $data['url'], $content );
	}
	return $content;
}

function tasteofcinemascraped_get_first_uploaded_attachment_id() {
	global $tasteofcinemascraped_url_to_attachment;
	if ( empty( $tasteofcinemascraped_url_to_attachment ) ) {
		return 0;
	}
	$first = reset( $tasteofcinemascraped_url_to_attachment );
	return isset( $first['id'] ) ? (int) $first['id'] : 0;
}

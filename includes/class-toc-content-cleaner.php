<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cleaning and normalizing scraped HTML content.
 */
class TOC_Content_Cleaner {

	/**
	 * Tailwind typography mappings for normalized HTML.
	 */
	public const CLASS_MAP = [
		'p'          => 'mb-4 text-base leading-relaxed',
		'h2'         => 'text-2xl font-bold mb-4 mt-8',
		'h3'         => 'text-xl font-bold mb-3 mt-6',
		'h4'         => 'text-lg font-bold mb-3 mt-6',
		'h5'         => 'text-md font-bold mb-2 mt-4',
		'h6'         => 'text-sm font-bold mb-2 mt-4',
		'ul'         => 'list-disc list-inside mb-4',
		'ol'         => 'list-decimal list-inside mb-4',
		'li'         => 'mb-1',
		'blockquote' => 'border-l-4 border-primary pl-4 italic bg-gray-50 p-4 mb-4',
		'a'          => 'text-primary hover:text-primary-focus underline',
		'img'        => 'my-4 max-w-full h-auto rounded',
		'figure'     => 'my-4',
		'figcaption' => 'text-sm text-gray-500 italic mt-2 text-center',
		'table'      => 'w-full text-left border-collapse my-6',
		'th'         => 'border-b pb-2 font-bold',
		'td'         => 'border-b py-2',
	];

	/**
	 * Cleans the scraped HTML and standardizes styling.
	 *
	 * @param string $content            The raw HTML content
	 * @param string $featured_image_url Optional URL of the featured image to detect & remove duplicates
	 *
	 * @return string The sanitized HTML
	 */
	public static function clean( string $content, string $featured_image_url = '' ): string {
		if ( empty( trim( $content ) ) ) {
			return $content;
		}

		// Suppress libxml errors to gracefully handle malformed HTML
		$previous_value = libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		// Ensure UTF-8 is parsed correctly by DOMDocument by injecting a meta tag if needed,
		// or using mb_convert_encoding. For HTML5 snippets, wrapping in a proper element helps.
		$html = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );

		// We wrap it securely to avoid adding html/body tags randomly or stripping text
		@$dom->loadHTML( '<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_value );

		$xpath = new DOMXPath( $dom );

		// 1. Remove duplicate leading thumbnail image (US1)
		if ( ! empty( $featured_image_url ) ) {
			self::remove_duplicate_thumbnail( $dom, $xpath, $featured_image_url );
		}

		// 2. Normalize classes and remove styles (US2)
		self::normalize_nodes( $dom, $xpath );

		// 3. Remove now empty elements (like a <figure> that had the removed image) (US1 cleanup)
		self::cleanup_empty_elements( $dom, $xpath );

		// Output returning only inner content of the wrapper <div>
		$result = '';
		if ( $dom->documentElement ) {
			foreach ( $dom->documentElement->childNodes as $child ) {
				$result .= $dom->saveHTML( $child );
			}
		}

		return trim( $result );
	}

	/**
	 * Removes the first image if it visually matches the featured image.
	 */
	private static function remove_duplicate_thumbnail( DOMDocument $dom, DOMXPath $xpath, string $featured_image_url ): void {
		$images = $xpath->query( '//img' );
		if ( $images !== false && $images->length > 0 ) {
			// Find the first visible image. Often scrapes might have tracking pixels; assume first real one.
			for ( $i = 0; $i < $images->length; $i++ ) {
				$image = $images->item( $i );
				
				if ( ! $image instanceof DOMElement ) {
					continue;
				}

				$src = $image->getAttribute( 'src' );
				if ( empty( $src ) ) {
					continue;
				}

				$featured_base = wp_basename( wp_parse_url( $featured_image_url, PHP_URL_PATH ) ?? '' );
				$src_base      = wp_basename( wp_parse_url( $src, PHP_URL_PATH ) ?? '' );

				// A simpler approach: extract filename without ext, as scaling/formats could alter the suffix
				$featured_no_ext = preg_replace( '/\.[a-z0-9]+$/i', '', $featured_base );
				$src_no_ext      = preg_replace( '/\.[a-z0-9]+$/i', '', $src_base );

				if ( ! empty( $featured_no_ext ) && $featured_no_ext === $src_no_ext ) {
					$image->parentNode?->removeChild( $image );
					break; // Only remove the first duplicated occurrence
				}
				
				// Once we inspect the first valid image, stop
				break;
			}
		}
	}

	/**
	 * Normalizes node styling by stripping class/style and applying Tailwind.
	 */
	private static function normalize_nodes( DOMDocument $dom, DOMXPath $xpath ): void {
		$nodes = $xpath->query( '//*' );
		if ( $nodes === false ) {
			return;
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			// Strip style and original class attributes
			if ( $node->hasAttribute( 'style' ) ) {
				$node->removeAttribute( 'style' );
			}
			if ( $node->hasAttribute( 'class' ) ) {
				$node->removeAttribute( 'class' );
			}

			// Apply designated utility classes based on tag
			$tag = strtolower( $node->nodeName );
			if ( isset( self::CLASS_MAP[ $tag ] ) ) {
				$node->setAttribute( 'class', self::CLASS_MAP[ $tag ] );
			}
		}
	}

	/**
	 * Recursively removes specific empty wrapper elements.
	 */
	private static function cleanup_empty_elements( DOMDocument $dom, DOMXPath $xpath ): void {
		$wrappers    = [ 'p', 'div', 'figure', 'span', 'strong', 'em', 'b', 'i' ];
		$removed_any = true;

		while ( $removed_any ) {
			$removed_any = false;
			foreach ( $wrappers as $tag ) {
				$nodes = $xpath->query( "//{$tag}" );
				if ( $nodes === false ) {
					continue;
				}

				// Iterate backwards to safely remove children without shifting indices
				for ( $i = $nodes->length - 1; $i >= 0; $i -- ) {
					$node = $nodes->item( $i );
					if ( ! $node instanceof DOMElement ) {
						continue;
					}

					// Check if node has visual elements inside it
					$visuals = $xpath->query( './/img | .//iframe | .//video | .//audio | .//br | .//canvas | .//svg', $node );
					if ( $visuals !== false && $visuals->length > 0 ) {
						continue;
					}

					// Check if it only has whitespace (strip non-breaking spaces as well)
					$text = trim( str_replace( "\xC2\xA0", '', $node->textContent ) );
					if ( $text === '' ) {
						$node->parentNode?->removeChild( $node );
						$removed_any = true;
					}
				}
			}
		}
	}
}

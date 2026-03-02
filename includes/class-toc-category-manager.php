<?php
/**
 * TOC Category Manager
 *
 * @package TasteOfCinemaScraped
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TOC_Category_Manager
 *
 * Handles category seeding, enforcement, and two-phase matching.
 */
class TOC_Category_Manager {

	/**
	 * Seeds all predefined categories into WordPress.
	 *
	 * @return void
	 */
	public static function seed_all(): void {
		$categories = TOC_Category_Config::CATEGORIES;

		// Basic invariant checks (Phase 2).
		if ( count( $categories ) > 15 ) {
			error_log( '[TOC-CATEGORY] CRITICAL: More than 15 predefined categories defined. Skipping seed.' );
			return;
		}

		$defaults = array_filter(
			$categories,
			function ( $cat ) {
				return ! empty( $cat['is_default'] );
			}
		);

		if ( count( $defaults ) !== 1 ) {
			throw new \RuntimeException( '[TOC-CATEGORY] CRITICAL: Exactly one default category must be defined in TOC_Category_Config.' );
		}

		foreach ( $categories as $cat ) {
			$term = get_term_by( 'slug', $cat['slug'], 'category' );

			if ( false === $term ) {
				wp_insert_term(
					$cat['name'],
					'category',
					array(
						'slug'        => $cat['slug'],
						'description' => $cat['description'],
					)
				);
			}
		}
	}

	/**
	 * Resolves source category names to a predefined local category term ID.
	 *
	 * @param array $source_names List of category names from the scraper.
	 * @param int   $post_id      Optional WordPress post ID (used for logging).
	 * @return int WordPress term ID.
	 */
	public static function resolve( array $source_names, int $post_id = 0 ): int {
		// INVARIANT: this method must never call wp_insert_term()
		$categories       = TOC_Category_Config::CATEGORIES;
		$source_slug_map  = TOC_Category_Config::SOURCE_SLUG_MAP;
		$keyword_map      = TOC_Category_Config::KEYWORD_MAP;

		$default_cat = null;
		foreach ( $categories as $cat ) {
			if ( ! empty( $cat['is_default'] ) ) {
				$default_cat = $cat;
				break;
			}
		}

		if ( null === $default_cat ) {
			error_log( '[TOC-CATEGORY] CRITICAL: No default category defined in config.' );
			return 0;
		}

		$clean_names = array();
		foreach ( $source_names as $item ) {
			$name = '';
			if ( is_array( $item ) && isset( $item['name'] ) ) {
				$name = trim( (string) $item['name'] );
			} elseif ( is_string( $item ) ) {
				$name = trim( $item );
			}
			if ( $name !== '' ) {
				$clean_names[] = $name;
			}
		}

		// Edge case: empty input (FR-004/FR-005 rationale line 98).
		if ( empty( $clean_names ) ) {
			$term = get_term_by( 'slug', $default_cat['slug'], 'category' );
			return $term ? (int) $term->term_id : 0;
		}

		// Phase 1: Static Map.
		foreach ( $clean_names as $name ) {
			$source_slug = sanitize_title( $name );
			if ( isset( $source_slug_map[ $source_slug ] ) ) {
				$local_slug = $source_slug_map[ $source_slug ];
				$term       = get_term_by( 'slug', $local_slug, 'category' );
				if ( $term ) {
					return (int) $term->term_id;
				}
			}
		}

		// Phase 2: Keyword Match.
		$scores = array();
		foreach ( $categories as $cat ) {
			$slug            = $cat['slug'];
			$keywords        = $keyword_map[ $slug ] ?? array();
			$scores[ $slug ] = 0;

			foreach ( $clean_names as $name ) {
				$tokens = preg_split( '/[\s\-]+/', strtolower( $name ), -1, PREG_SPLIT_NO_EMPTY );
				foreach ( $tokens as $token ) {
					if ( in_array( $token, $keywords, true ) ) {
						$scores[ $slug ]++;
					}
				}
			}
		}

		arsort( $scores );
		$best_slug  = key( $scores );
		$best_score = current( $scores );

		if ( $best_score > 0 ) {
			$term = get_term_by( 'slug', $best_slug, 'category' );
			if ( $term ) {
				return (int) $term->term_id;
			}
		}

		// Default fallback.
		$term = get_term_by( 'slug', $default_cat['slug'], 'category' );
		if ( $term ) {
			error_log(
				sprintf(
					'[TOC-CATEGORY] WARNING: post=%d unmatched source_category="%s" → applied default "%s" (%s)',
					$post_id,
					implode( ', ', $clean_names ),
					$default_cat['name'],
					$default_cat['slug']
				)
			);
			return (int) $term->term_id;
		}

		return 0;
	}

	/**
	 * Gets all term IDs for predefined categories.
	 *
	 * @return array List of term IDs.
	 */
	public static function get_predefined_term_ids(): array {
		$term_ids = array();

		foreach ( TOC_Category_Config::CATEGORIES as $cat ) {
			$term = get_term_by( 'slug', $cat['slug'], 'category' );
			if ( $term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		return $term_ids;
	}
}

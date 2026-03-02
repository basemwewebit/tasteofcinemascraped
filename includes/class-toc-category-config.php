<?php
/**
 * TOC Category Config
 *
 * @package TasteOfCinemaScraped
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TOC_Category_Config
 *
 * Central taxonomy definition for the predefined category feature.
 */
class TOC_Category_Config {

	/**
	 * Predefined categories list.
	 *
	 * [slug => string, name => string, description => string, is_default => bool]
	 */
	public const CATEGORIES = array(
		array(
			'slug'        => 'film-lists',
			'name'        => 'قوائم أفلام',
			'description' => 'قوائم الأفلام الموضوعية والتحريرية',
			'is_default'  => true,
		),
		array(
			'slug'        => 'features',
			'name'        => 'مقالات وتحليلات',
			'description' => 'مقالات تحليلية وتعمقية في عالم السينما',
			'is_default'  => false,
		),
		array(
			'slug'        => 'people-lists',
			'name'        => 'قوائم مخرجين وممثلين',
			'description' => 'قوائم تتمحور حول صناع ونجوم السينما',
			'is_default'  => false,
		),
		array(
			'slug'        => 'other-lists',
			'name'        => 'قوائم متنوعة',
			'description' => 'قوائم متنوعة تشمل كتباً وموسيقى وتلفزيون',
			'is_default'  => false,
		),
		array(
			'slug'        => 'reviews',
			'name'        => 'مراجعات أفلام',
			'description' => 'مراجعات نقدية للأفلام',
			'is_default'  => false,
		),
		array(
			'slug'        => 'best-of-year',
			'name'        => 'أفضل أفلام السنة',
			'description' => 'أبرز وأفضل أفلام كل عام',
			'is_default'  => false,
		),
		array(
			'slug'        => 'by-genre',
			'name'        => 'أفلام حسب النوع',
			'description' => 'تصنيفات الأفلام مقسّمة حسب النوع السينمائي',
			'is_default'  => false,
		),
		array(
			'slug'        => 'by-country',
			'name'        => 'أفلام حسب البلد',
			'description' => 'تصنيفات الأفلام مقسّمة حسب بلد الإنتاج',
			'is_default'  => false,
		),
		array(
			'slug'        => 'by-decade',
			'name'        => 'أفلام حسب العقد',
			'description' => 'تصنيفات الأفلام مقسّمة حسب حقبة زمنية',
			'is_default'  => false,
		),
		array(
			'slug'        => 'rankings',
			'name'        => 'مقارنات وتصنيفات',
			'description' => 'قوائم مقارِنة ومصنِّفة للأفلام والمخرجين',
			'is_default'  => false,
		),
	);

	/**
	 * Static slug map for source category matching.
	 *
	 * source-slug => local-slug
	 */
	public const SOURCE_SLUG_MAP = array(
		// Direct source categories (tasteofcinema.com).
		'film-lists'    => 'film-lists',
		'lists'         => 'film-lists',
		'features'      => 'features',
		'feature'       => 'features',
		'people-lists'  => 'people-lists',
		'other-lists'   => 'other-lists',
		'reviews'       => 'reviews',
		'review'        => 'reviews',

		// Derived patterns (common scraped sub-category names).
		'best-of'       => 'best-of-year',
		'best-films'    => 'best-of-year',
		'best-movies'   => 'best-of-year',
		'by-genre'      => 'by-genre',
		'genre-lists'   => 'by-genre',
		'by-country'    => 'by-country',
		'country-lists' => 'by-country',
		'by-decade'     => 'by-decade',
		'decade-lists'  => 'by-decade',
		'ranked'        => 'rankings',
		'rankings'      => 'rankings',
		'ranking'       => 'rankings',
	);

	/**
	 * Keyword map for fallback category matching.
	 *
	 * local-slug => [keyword1, keyword2, ...]
	 */
	public const KEYWORD_MAP = array(
		'film-lists'   => array( 'list', 'films', 'movies', 'cinema', 'ranking', 'top', 'best' ),
		'features'     => array( 'feature', 'article', 'essay', 'analysis', 'guide', 'opinion', 'interview', 'editorial' ),
		'people-lists' => array( 'director', 'actor', 'actress', 'filmmaker', 'people', 'person', 'auteur' ),
		'other-lists'  => array( 'books', 'music', 'tv', 'television', 'series', 'albums', 'songs' ),
		'reviews'      => array( 'review', 'critique', 'rating', 'assessment' ),
		'best-of-year' => array( 'best', 'year', 'annual', '2024', '2025', '2023' ),
		'by-genre'     => array( 'genre', 'horror', 'drama', 'comedy', 'thriller', 'western', 'action', 'sci-fi' ),
		'by-country'   => array( 'country', 'french', 'korean', 'japanese', 'italian', 'american', 'british', 'iranian' ),
		'by-decade'    => array( 'decade', '70s', '80s', '90s', '60s', '50s', 'century' ),
		'rankings'     => array( 'ranked', 'ranking', 'versus', 'comparison', 'vs', 'compared' ),
	);
}

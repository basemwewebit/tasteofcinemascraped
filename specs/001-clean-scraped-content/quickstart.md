# Quickstart: Clean Scraped Content (`TOC_Content_Cleaner`)

A utility class used immediately upon processing a scraped WordPress article payload to format raw imported HTML into the standard Tailwind CSS magazine layout.

## 1. Using the Cleaner

The `TOC_Content_Cleaner` is a static helper meant for backend use. It takes a raw string and an optional `first_image_url` string to target for removal.

```php
// Use within REST API endpoint right before saving the post content.
$raw_scraped_html = $request->get_param( 'content' );
$uploaded_thumbnail_url = $tasteofcinemascraped_url_to_attachment[0]['url'] ?? '';

// Sanitize styles and classes & drop duplicate first matching image
require_once __DIR__ . '/includes/class-toc-content-cleaner.php';
$clean_html = TOC_Content_Cleaner::clean( $raw_scraped_html, $uploaded_thumbnail_url );

// Final insert into WordPress
wp_insert_post( [
    'post_content' => $clean_html
] );
```

## 2. Configuration & Utilities Map

To change the default Tailwind typography mapping applied to raw tags, simply modify the `CLASS_MAP` constant within the top of the `includes/class-toc-content-cleaner.php` file itself:

```php
const CLASS_MAP = [
    'p' => 'mb-4 text-base leading-relaxed',
    'h2' => 'text-2xl font-bold mb-4 mt-8',
    'h3' => 'text-xl font-bold mb-3 mt-6',
    // ...
];
```

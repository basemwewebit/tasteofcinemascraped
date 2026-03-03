# Research: Clean Scraped Content

## 1. Removing Redundant Images & Styling the Output
**Needs Clarification**: What is the most reliable approach in PHP/WordPress to manipulate the raw HTML payload of a scraped post to strip predefined elements and re-assign `class` tags without breaking the markup?

**Decision**: Use `DOMDocument` and `DOMXPath` rather than regular expressions or `wp_kses()`.
**Rationale**: Native PHP `DOMDocument` correctly handles malformed, nested, or erratic HTML typically found in scraped source articles. Regular expressions for HTML manipulation are notoriously fragile and can easily destroy innocent data or fail on nested structures. Using `DOMDocument` allows us to target specific tags, query their positions (e.g., retrieving the first `<img>` tag and checking its `src` constraint), and loop through all nodes to safely remove `style` or existing `class` attributes.
**Alternatives considered**: 
- `preg_replace`: Too risky. Could strip `<style>` text inadvertently or miss edge cases like empty spaces.
- `wp_kses()`: Focuses on allowing basic HTML tags but doesn't easily let us rename classes across all paragraph tags dynamically.

## 2. Best Practices for Tailwind CSS Utility Classes
**Needs Clarification**: Which utility classes should be mapped to which HTML elements when standardizing the scraped output?

**Decision**: 
We will apply standard typographic Tailwind classes tailored for readability in both light and dark modes, suitable for an Arabic cinema magazine. 
- Elements (`h2`-`h6`): Applied classes should use `text-xl/2xl/3xl font-bold mb-4`.
- Paragraphs (`p`): Should have consistent line-height and spacing (`mb-4 text-base leading-relaxed`).
- Lists (`ul`, `ol`, `li`): Specific spacing and bullet types (`list-disc list-inside mb-4`).
- Blockquotes: Styled uniquely for the magazine context (`border-l-4 border-primary pl-4 italic bg-gray-50 dark:bg-gray-800 p-4 mb-4`).
- Links (`a`): Standardized coloring (`text-primary hover:text-primary-focus underline`).

**Rationale**: Since the user requested stripping all `inline style` and replacing them with Tailwind classes, assigning specific base utility classes ensures a pixel-perfect, cohesive reading experience across all imported articles without needing raw CSS files. 

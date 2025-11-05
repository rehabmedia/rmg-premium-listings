# RMG Premium Listings

Premium facility listings plugin with ElasticSearch integration, advanced filtering, customizable card displays, and embeddable widgets.

## Features

- **Listing Cards Block**: Gutenberg block for displaying facility listings
- **Embed Generator**: WordPress admin interface for creating embeddable listing widgets
- **ElasticSearch Integration**: Fast, filtered facility queries with Premium+ pacing
- **Location-Based Sorting**: Geo-distance sorting for proximity-based results
- **Registry System**: Prevents duplicate listings on the same page
- **REST API**: Custom endpoints for dynamic listing retrieval
- **Premium Sorting**: Automatic sorting by premium level and pacing scores
- **Helper Functions**: Namespaced helper utilities for location/mapping functionality

## Structure

```
rmg-premium-listings/
├── inc/
│   ├── class-rmg-premium-listings.php         # Main plugin class
│   ├── class-asset-manager.php                # Asset enqueuing
│   ├── class-block-migration.php              # Legacy block compatibility
│   ├── class-embed.php                        # Embed endpoint handler
│   ├── class-cards-renderer.php               # Card rendering logic
│   ├── admin/
│   │   ├── class-admin.php                    # Admin pages
│   │   └── views/
│   │       └── settings-page.php              # Embed generator UI
│   ├── es/                                    # ElasticSearch integration
│   │   ├── README.md                          # Detailed ES documentation
│   │   ├── class-es-query.php                 # Query builder
│   │   ├── class-es-utils.php                 # Premium+ scoring utilities
│   │   └── class-cards-registry.php           # Duplicate prevention
│   ├── rest/                                  # REST API endpoints
│   │   └── class-cards-endpoint.php           # Dynamic card queries
│   └── svg/                                   # SVG icons
├── templates/
│   └── embed-listing-cards.php                # Embed iframe template
├── src/
│   ├── listing-cards/                         # Gutenberg block
│   │   ├── block.json
│   │   ├── edit.js
│   │   ├── render.php
│   │   ├── view.js
│   │   └── style.scss
│   ├── js/
│   │   └── admin.js                           # Admin page JavaScript
│   └── scss/
│       └── admin.scss                         # Admin page styles
└── build/                                     # Compiled assets (generated)
```

## Documentation

- **[ElasticSearch Integration & Scoring](inc/es/README.md)** - Detailed documentation on ES queries, Premium+ pacing, caching, and filters
- **[Embed System](templates/README.md)** - How the embed iframe resizing works

## Installation

1. Copy plugin to `/wp-content/plugins/rmg-premium-listings/`
2. Run `npm install` to install dependencies
3. Run `npm run build` to compile assets
4. Activate plugin in WordPress admin

## Development

```bash
# Install dependencies
npm install

# Start development (watch mode)
npm start

# Build for production
npm run build

# Run PHPCS
./vendor/bin/phpcs --standard=phpcs.xml

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css

# Format code
npm run format
```

## Embed Generator

The plugin includes a WordPress admin page for generating embeddable listing widgets:

**Location**: WP Admin → Settings → RMG Premium Listings

**Features**:
- Visual configuration builder with live preview
- Save/load configuration presets with reference IDs
- JSON editor with validation
- Override parameters (state, city, referrer)
- Copy embed code or URL to clipboard
- Auto-resize iframes for dynamic content
- Support for both ref-based and config-based URLs

**Usage**:
1. Configure display options (layout, filters, card options)
2. Save configuration with a descriptive name
3. Click "Generate Embed Code"
4. Copy the embed code to your website
5. The iframe automatically resizes to fit content

**URL Formats**:

The embed endpoint supports three URL formats:

1. **Reference-based** (recommended for saved configs):
   ```
   /embed/listing-cards/?ref=homepage-featured&state=california
   ```
   - Cleanest URL format
   - Configuration stored in database
   - Easy to update centrally
   - Display options included in saved config

2. **Config-based** (backward compatible):
   ```
   /embed/listing-cards/?config=eyJsYXlvdXQiOiJ0aHJl...
   ```
   - Full configuration in URL (base64-encoded JSON)
   - No database dependency
   - Good for testing/one-off embeds
   - Display options included in config JSON

3. **Defaults** (fallback):
   ```
   /embed/listing-cards/
   ```
   - Uses default configuration
   - No parameters needed

**Supported URL Parameters**:
- `ref` - Reference ID for saved configuration
- `config` - Base64-encoded JSON configuration
- `referrer` - Referrer site (for tracking)
- `state` - State override for location-based results
- `city` - City override for location-based results

**Note**: Display options (colors, fonts, padding, etc.) are configured in the JSON and stored with the configuration—they are not passed as URL parameters.

See [templates/README.md](templates/README.md) for technical details on how iframe resizing works.

## Dependencies

### Required
- WordPress 6.0+
- PHP 8.1+
- ElasticSearch 7.x+ (configured separately)
- ACF Pro (for facility custom fields)

### Optional Integration
- **RMG Impression Tracking**: Tracks views/clicks on listing cards and manages Premium+ budgets
- **ElasticPress**: Syncs WordPress posts to Elasticsearch

## Using the Listing Cards Class

The `Cards_Renderer` class can be used directly in PHP to render listing cards programmatically.

### Basic Usage

```php
use RMG_Premium_Listings\Cards_Renderer;

// Basic rendering.
$cards = new Cards_Renderer();
$cards->render();

// Rendering with custom arguments.
$cards = new Cards_Renderer();
$cards->render( array(
	'layout'     => 'slider',
	'card_count' => 6,
	'headline'   => array(
		'show' => true,
		'text' => 'Featured Treatment Centers',
	),
) );

// Get rendered output as string.
$html = $cards->get_render( $args );
```

### Available Arguments

All arguments are optional and will fall back to defaults if not provided.

| Argument | Type | Default | Description |
|----------|------|---------|-------------|
| `action_type` | string | `'none'` | Filter mode: `'all'`, `'filtered'`, `'tabs'`, or `'none'` |
| `card_count` | int | `3` | Number of cards to display (auto-adjusts to `8` for slider layout) |
| `card_options` | array | See below | Display options for individual cards |
| `context` | array | Auto-generated | Contextual information (post ID, type, admin status, etc.) |
| `exclude_displayed` | bool | `false` | Prevent duplicate listings on the same page |
| `has_background` | bool | `false` | Add background styling to the cards container |
| `headline` | array | See below | Headline configuration |
| `is_inline` | bool | `false` | Apply inline styling |
| `layout` | string | `'three-column'` | Layout type: `'three-column'`, `'slider'`, or `'vertical'` |
| `render_id` | string | Auto-generated | Unique ID for this render instance |
| `selected_terms` | array | Empty arrays | Filter by taxonomy terms (see below) |
| `slides_to_show` | int | `3` | Number of slides visible in slider layout |
| `user_location` | array | Empty | User location data for geo-sorting |
| `wrapper_classes` | array | Empty | Additional CSS classes for wrapper |
| `wrapper_attributes` | array | Empty | Additional HTML attributes for wrapper |

### Card Options

The `card_options` array controls the display of individual card elements:

```php
'card_options' => array(
	'hasBackground' => false,  // Show card background.
	'showRank'      => true,   // Display ranking badge.
	'showAddress'   => true,   // Show facility address.
	'showInsurance' => true,   // Display insurance information.
),
```

### Headline Configuration

The `headline` array controls the section heading:

```php
'headline' => array(
	'show'      => false,                                // Display headline.
	'text'      => 'Featured Facilities Near You',      // Headline text.
	'alignment' => 'left',                               // Text alignment: 'left', 'center', 'right'.
	'tag'       => 2,                                    // Heading level (1-6, generates h1-h6).
),
```

### Selected Terms (Filtering)

The `selected_terms` array filters results by taxonomy terms. Use term slugs:

```php
'selected_terms' => array(
	'amenities'        => array( 'pet-friendly', 'yoga' ),
	'clinicalServices' => array(),
	'levelsOfCare'     => array( 'inpatient', 'outpatient' ),
	'paymentOptions'   => array(),
	'programs'         => array( 'mens-program' ),
	'treatmentOptions' => array( 'detox', 'dual-diagnosis' ),
),
```

### Complete Example

```php
use RMG_Premium_Listings\Cards_Renderer;

$cards = new Cards_Renderer();
$cards->render( array(
	'layout'            => 'slider',
	'card_count'        => 8,
	'action_type'       => 'filtered',
	'exclude_displayed' => true,
	'has_background'    => true,
	'slides_to_show'    => 3,
	'headline'          => array(
		'show'      => true,
		'text'      => 'Top-Rated Treatment Centers Near You',
		'alignment' => 'center',
		'tag'       => 2,
	),
	'card_options'      => array(
		'hasBackground' => true,
		'showRank'      => true,
		'showAddress'   => true,
		'showInsurance' => true,
	),
	'selected_terms'    => array(
		'levelsOfCare'     => array( 'inpatient' ),
		'treatmentOptions' => array( 'detox' ),
	),
	'wrapper_classes'   => array( 'custom-class' ),
) );
```

## Block Migration Class

The `Block_Migration` class handles backward compatibility for sites migrating from the legacy `rmg-blocks/listing-cards-v2` block to the new `rmg-premium-listings/cards` block.

### What It Does

The migration class provides seamless backward compatibility by:

1. **Class Aliasing** - Creates aliases so old class references continue to work
2. **Block Name Migration** - Automatically converts legacy blocks during render
3. **CSS Class Preservation** - Maintains old CSS classes for style compatibility
4. **REST API Proxying** - Redirects legacy endpoint requests to the new endpoint
5. **Editor Transformation** - Provides block transformation in the editor

### Class Aliases

The following class aliases are automatically registered:

| Legacy Class | New Class | Purpose |
|--------------|-----------|---------|
| `Listing_Cards_V2` | `RMG_Premium_Listings\Cards_Renderer` | Global namespace alias |
| `RMG_Blocks\Listing_Cards_V2` | `RMG_Premium_Listings\Cards_Renderer` | Namespaced alias |

This means existing code using the old class names will continue to work:

```php
// These all work and use the same class.
$cards = new Listing_Cards_V2();
$cards = new RMG_Blocks\Listing_Cards_V2();
$cards = new RMG_Premium_Listings\Cards_Renderer();
```

### Block Migration

**Automatic Runtime Conversion:**
- Legacy block name: `rmg-blocks/listing-cards-v2`
- New block name: `rmg-premium-listings/cards`
- All legacy blocks automatically render using the new implementation
- No manual migration required for existing content

**CSS Class Compatibility:**

Legacy CSS classes are automatically added to maintain styling:
- `wp-block-rmg-blocks-listing-cards-v2`
- `listing-cards-v2`

These classes are prepended to the wrapper, so existing custom CSS continues to work.

### REST API Proxying

**Legacy Endpoint:**
```
POST /wp-json/rmg/v1/listing-cards-v2
```

**New Endpoint:**
```
POST /wp-json/rmg/v1/premium-listing-cards
```

All requests to the legacy endpoint are automatically proxied to the new endpoint with all parameters and headers preserved. Debug logging is enabled when `WP_DEBUG` is true.

### Removing Migration Support

When all sites have migrated, you can safely remove backward compatibility:

1. Delete `inc/class-block-migration.php`
2. Delete `build/js/block-migration.js` (and source file)
3. Remove `Block_Migration::init()` call from `inc/class-rmg-premium-listings.php`

All migration code is isolated in the `Block_Migration` class for easy removal.

## Available Filters

See [inc/es/README.md](inc/es/README.md) for complete filter documentation.

### Key Filters

**`rmg_premium_listings_wrapper_classes`**
- Modify CSS classes applied to the listing cards block wrapper
- Useful for backward compatibility or custom styling
- Parameters: `$class_parts` (array), `$args` (array)
- Example:
  ```php
  add_filter( 'rmg_premium_listings_wrapper_classes', function( $classes, $args ) {
      $classes[] = 'my-custom-class';
      return $classes;
  }, 10, 2 );
  ```

**`rmg_listing_cards_v2_displayed_post_ids`**
- Tracks displayed post IDs to prevent duplicates
- Applied after each query

**`rmg_listing_randomization_interval`**
- Controls randomization seed interval (default: 900 seconds)
- Affects cache effectiveness vs content variety

**`rmg_impression_tracking_config`**
- Passes parent page data to impression tracking script
- Includes parent URL, host, referrer, and admin preview flag

## Premium+ Pacing System

The plugin implements an intelligent budget pacing system for Premium+ listings:

**Key Concepts**:
- Pre-calculated scores stored in ACF (`premium_plus_pacing_score`)
- Field-based ES sorting (no expensive scripts)
- Dynamic prioritization based on budget health
- Automatic demotion when budget exhausted

**Score Ranges**:
- Active Premium+: 3000-3999 (dynamic)
- Exhausted Premium+: 2000 (demoted to Premium tier)
- Premium: 2000 (static)
- Free: 1000 (static)

**Budget Management**:
- Handled by `rmg-impression-tracking` plugin
- Score calculation by `RMG_Premium_Listings_ES_Utils`
- Updates on impression tracking events
- Supports budget overrides (additive model)

See [inc/es/README.md](inc/es/README.md) for complete Premium+ documentation.

## Block Migration & Backward Compatibility

The plugin includes automatic migration from the legacy `rmg-blocks/listing-cards-v2` block:

### What's Migrated

**Block Name**: `rmg-blocks/listing-cards-v2` → `rmg-premium-listings/cards`
- Automatic runtime conversion during render
- Editor transformation for block updates
- No manual migration required

**PHP Class Aliases**:
- `Listing_Cards_V2` → `RMG_Premium_Listings\Cards_Renderer`
- `RMG_Blocks\Listing_Cards_V2` → `RMG_Premium_Listings\Cards_Renderer`

**REST API Endpoint**:
- Legacy: `/wp-json/rmg/v1/listing-cards-v2`
- New: `/wp-json/rmg/v1/premium-listing-cards`
- Legacy endpoint automatically proxies to new endpoint

**CSS Classes**: Legacy classes automatically added via filter
- `wp-block-rmg-blocks-listing-cards-v2`
- `listing-cards-v2`

### Removing Migration Support

When all sites have migrated, you can safely remove backward compatibility:

1. Delete `inc/class-block-migration.php`
2. Delete `src/js/block-migration.js`
3. Remove initialization in `inc/class-rmg-premium-listings.php`
4. Run `npm run build`

All migration code is isolated for easy removal.

## REST API

### Endpoints

**`POST /wp-json/rmg/v1/premium-listing-cards`**

Retrieves filtered listing cards with Premium+ prioritization.

### Request Parameters

All parameters are optional and will fall back to defaults if not provided.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `action_type` | string | `'none'` | Filter mode: `'none'`, `'tabs'`, or `'filter'` |
| `card_count` | int | `3` (or `8` for slider) | Number of cards to return (min: 1, max: 60) |
| `layout` | string | `'three-column'` | Layout type: `'three-column'`, `'slider'`, or `'vertical'` |
| `exclude_displayed` | bool | `false` | Exclude already displayed post IDs from results |
| `has_background` | bool | `false` | Add background styling to cards container |
| `is_inline` | bool | `false` | Apply inline styling |
| `slides_to_show` | float | `3` | Number of slides visible in slider layout |
| `fetch_location` | bool | `false` | Fetch user location from CloudFlare headers for geo-sorting |
| `user_location` | object | `{}` | User location data (see below) |
| `card_options` | object | See below | Display options for individual cards |
| `headline` | object | See below | Headline configuration |
| `selected_terms` | object | `{}` | Filter by taxonomy terms (see below) |
| `context` | object | `{}` | Contextual information (post ID, type, etc.) |
| `display_context` | string | `''` | Display context identifier |
| `already_displayed` | array | `[]` | Array of post IDs already displayed |
| `excluded_post_ids` | array | `[]` | Array of post IDs to exclude from results |
| `wrapper_classes` | array | `[]` | Additional CSS classes for wrapper |
| `wrapper_attributes` | object | `{}` | Additional HTML attributes for wrapper |

### Card Options Object

```json
{
  "hasBackground": false,
  "showRank": true,
  "showAddress": true,
  "showInsurance": true
}
```

### Headline Object

```json
{
  "show": false,
  "text": "Featured Facilities Near You",
  "alignment": "left",
  "tag": 2
}
```

- `alignment` accepts: `'left'`, `'center'`, `'right'`
- `tag` accepts: `1-6` (generates h1-h6 elements)

### Selected Terms Object

Filter results by taxonomy terms. Use term slugs:

```json
{
  "amenities": ["pet-friendly", "yoga"],
  "clinicalServices": [],
  "levelsOfCare": ["inpatient", "outpatient"],
  "paymentOptions": [],
  "programs": ["mens-program"],
  "treatmentOptions": ["detox", "dual-diagnosis"]
}
```

### User Location Object

```json
{
  "lat": 34.0522,
  "lon": -118.2437,
  "city": "Los Angeles",
  "region": "CA",
  "country": "US",
  "type": "user"
}
```

### Context Object

```json
{
  "post_id": 123,
  "post_type": "page",
  "requires_location_data": false
}
```

### Response Format

**Success Response (200)**:

```json
{
  "success": true,
  "html": "<ul class=\"listing-cards\">...</ul>",
  "displayed_ids": [123, 456, 789],
  "location": {
    "lat": 34.0522,
    "lon": -118.2437,
    "city": "Los Angeles",
    "region": "CA",
    "country": "US",
    "type": "user"
  },
  "meta": {
    "card_count": 6,
    "cards_from_query": 6,
    "cards_rendered": 6,
    "layout": "three-column",
    "action_type": "filtered"
  },
  "debug": {
    "notes": [
      "Location fetched from headers",
      "Cards returned from ES query: 6"
    ],
    "warnings": []
  }
}
```

**Error Response (500)**:

```json
{
  "code": "render_error",
  "message": "Error message here",
  "data": {
    "status": 500,
    "debug": {
      "notes": [],
      "warnings": [],
      "trace": "Stack trace..."
    }
  }
}
```

### Request Examples

**Basic Request:**

```bash
curl -X POST https://example.com/wp-json/rmg/v1/premium-listing-cards \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Filtered Request with Location:**

```bash
curl -X POST https://example.com/wp-json/rmg/v1/premium-listing-cards \
  -H "Content-Type: application/json" \
  -d '{
    "action_type": "filtered",
    "card_count": 6,
    "layout": "three-column",
    "fetch_location": true,
    "selected_terms": {
      "treatmentOptions": ["detox"],
      "levelsOfCare": ["inpatient"]
    },
    "card_options": {
      "hasBackground": true,
      "showRank": true,
      "showAddress": true,
      "showInsurance": true
    },
    "headline": {
      "show": true,
      "text": "Top Treatment Centers Near You",
      "alignment": "center",
      "tag": 2
    }
  }'
```

**Slider Layout with Custom Location:**

```bash
curl -X POST https://example.com/wp-json/rmg/v1/premium-listing-cards \
  -H "Content-Type: application/json" \
  -d '{
    "layout": "slider",
    "card_count": 8,
    "slides_to_show": 3,
    "user_location": {
      "lat": 34.0522,
      "lon": -118.2437,
      "city": "Los Angeles",
      "region": "CA",
      "country": "US",
      "type": "user"
    },
    "selected_terms": {
      "programs": ["mens-program"]
    }
  }'
```

**Exclude Already Displayed:**

```bash
curl -X POST https://example.com/wp-json/rmg/v1/premium-listing-cards \
  -H "Content-Type: application/json" \
  -d '{
    "card_count": 3,
    "exclude_displayed": true,
    "already_displayed": [123, 456, 789]
  }'
```

### Location Data

The endpoint can fetch user location from CloudFlare headers when `fetch_location` is `true`. The following headers are used:

- `HTTP_CF_IPLATITUDE` - User latitude
- `HTTP_CF_IPLONGITUDE` - User longitude
- `HTTP_CF_IPCITY` - User city
- `HTTP_CF_IPREGION` - User region/state
- `HTTP_CF_IPCOUNTRY` - User country code

Location data is used for geo-distance sorting to show nearest facilities first.

**Debug Location:**

Add `?debug-location` query parameter to prevent location fetching during testing.

### Debug Mode

When `WP_DEBUG` is enabled, the response includes additional debugging information:

- `debug.notes` - Array of debugging messages
- `debug.warnings` - Array of warnings about unfulfilled requests
- Query vs rendered card counts
- ID mismatches between query and HTML
- Active filter information

### Legacy Endpoint

**`POST /wp-json/rmg/v1/listing-cards-v2`**

The legacy endpoint is automatically proxied to the new endpoint with all parameters preserved. This maintains backward compatibility for sites migrating from `rmg-blocks/listing-cards-v2`.

See [Block Migration](#block-migration--backward-compatibility) for more details on backward compatibility features.

## Version

1.0.0 - Initial release with embed generator and Premium+ pacing

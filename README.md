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
- Save/load configuration presets
- JSON editor with validation
- Override parameters (state, city, referrer)
- Copy embed code or URL to clipboard
- Auto-resize iframes for dynamic content

**Usage**:
1. Configure display options (layout, filters, card options)
2. Click "Generate Embed Code"
3. Copy the embed code to your website
4. The iframe automatically resizes to fit content

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

**Query Parameters**:
- `action_type` - Filter mode: 'all', 'filtered', 'tabs', 'none'
- `card_count` - Number of cards to return (default: 3)
- `exclude_displayed` - Boolean to exclude already displayed IDs
- `selected_terms[taxonomy]` - Array of term slugs to filter by
- `fetch_location` - Boolean to fetch user location from headers

**Example**:
```bash
curl -X POST https://example.com/wp-json/rmg/v1/premium-listing-cards \
  -H "Content-Type: application/json" \
  -d '{"action_type":"filtered","card_count":6,"selected_terms":{"treatmentOptions":["detox"],"levelsOfCare":["inpatient"]}}'
```

**Legacy Endpoint** (backward compatibility):
```
POST /wp-json/rmg/v1/listing-cards-v2
```
Automatically proxied to the new endpoint with all parameters preserved.

## Version

1.0.0 - Initial release with embed generator and Premium+ pacing

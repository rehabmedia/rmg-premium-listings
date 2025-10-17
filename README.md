# RMG Premium Listings

Premium facility listings plugin with ElasticSearch integration, advanced filtering, and customizable card displays.

## Features

- **Listing Cards Block**: Gutenberg block for displaying facility listings
- **ElasticSearch Integration**: Fast, filtered facility queries
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
│   ├── class-rmg-premium-listings-helpers.php # Helper functions
│   ├── es/                                     # ElasticSearch integration
│   │   ├── class-rmg-listing-es-query.php
│   │   └── class-rmg-listing-cards-registry.php
│   ├── rest/                                   # REST API endpoints
│   │   └── class-rmg-listing-cards-endpoint.php
│   └── svg/                                    # SVG icons
├── src/
│   └── listing-cards/                          # Gutenberg block
│       ├── block.json
│       ├── edit.js
│       ├── render.php
│       ├── render-class.php
│       ├── view.js
│       ├── style.scss
│       └── templates/
└── build/                                # Compiled assets (generated)
```

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

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css

# Format code
npm run format
```

## Dependencies

This plugin requires:
- WordPress 6.0+
- PHP 8.1+
- ElasticSearch 7.x+ (configured separately)
- ACF Pro (for facility custom fields)

## Integration

Works with:
- **RMG Impression Tracking**: Tracks views/clicks on listing cards
- **Elasticsearch**: Queries facility data
- **ACF**: Facility custom fields

## Version

1.0.0 - Initial release extracted from rmg-premium-listings

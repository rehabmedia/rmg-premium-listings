# RMG Listing ES Query - Documentation

## Overview

The `RMG_Premium_Listings_ES_Query` class is a WordPress/Elasticsearch integration that handles retrieving and displaying rehab center listing cards with intelligent geo-location sorting, premium prioritization, and term-based filtering. The system supports multiple page contexts (state pages, city pages, individual rehab centers) and provides both standard and tabbed display modes.

## Key Features

- **Geo-location based sorting** - Prioritizes listings by proximity to user or page location
- **Premium listing support** - Handles multiple premium tiers (PPV, Premium, Free)
- **Smart caching** - Dual-layer caching system with processing cache and transients
- **Multi-search capabilities** - Efficient tabbed interface using Elasticsearch `_msearch`
- **Randomization with consistency** - Provides variety while maintaining cache efficiency
- **Exclusion tracking** - Prevents duplicate listings across multiple card blocks

## Class Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `PAGE_TYPE_DEFAULT` | 'default' | Standard pages without specific location context |
| `PAGE_TYPE_REHAB_CENTER` | 'rehab-center' | Individual rehab center pages |
| `PAGE_TYPE_STATE` | 'state' | State-level taxonomy pages |
| `PAGE_TYPE_CITY` | 'city' | City-level taxonomy pages |
| `CACHE_DURATION` | 900 | Cache duration in seconds (15 minutes) |

## Available Filters

### 1. `rmg_listing_cards_v2_displayed_post_ids`

**Purpose**: Tracks which post IDs have already been displayed to prevent duplicates across multiple listing blocks on the same page.

**Parameters**:
- `$ids` (array): Array of post IDs that have been displayed

**Usage Example**:
```php
add_filter( 'rmg_listing_cards_v2_displayed_post_ids', function( $ids ) {
    // Add custom IDs to exclude
    $ids[] = 123;
    $ids[] = 456;
    return $ids;
}, 10, 1 );
```

**When Applied**:
- Applied after each successful query to track displayed cards
- Can be used to pre-populate excluded IDs before queries run

### 2. `rmg_listing_randomization_interval`

**Purpose**: Controls how often the randomization seed changes, affecting how frequently the listing order varies.

**Parameters**:
- `$interval` (int): Time interval in seconds for randomization changes

**Default**: 900 seconds (15 minutes)

**Usage Example**:
```php
// Change randomization every hour instead of 15 minutes
add_filter( 'rmg_listing_randomization_interval', function( $interval ) {
    return 3600; // 1 hour
} );

// Randomize on every request (disable caching benefits)
add_filter( 'rmg_listing_randomization_interval', function( $interval ) {
    return 0;
} );
```

**Notes**:
- Setting to 0 randomizes every request but reduces cache effectiveness
- Should typically match or be a multiple of `CACHE_DURATION`
- Affects the balance between content variety and cache efficiency

## Method: `init()`

### Parameters

The `init()` method accepts an associative array with the following options:

```php
$args = [
    'action_type'        => 'all',           // 'all', 'filtered', 'tabs', 'none'
    'card_count'         => 3,               // Number of cards to display
    'card_options'       => [],              // Display options passed to cards
    'context'            => [
        'post_id' => 123                     // Current post ID for context
    ],
    'excluded_post_ids'  => [],              // Manual IDs to exclude
    'exclude_displayed'  => false,           // Use displayed post tracking
    'selected_terms'     => [                // Taxonomy term filters
        'amenities'        => [],
        'clinicalServices' => [],
        'levelsOfCare'     => [],
        'paymentOptions'   => [],
        'programs'         => [],
        'treatmentOptions' => [],
    ]
];
```

### Return Value

Returns an array of card data, where each card contains:

```php
[
    'id'                    => 123,
    'title'                 => 'Rehab Center Name',
    'listing_link'          => 'https://...',
    'listing_image'         => 'https://...',
    'premium'               => true/false,
    'original_total_points' => 85,
    'phone'                 => '555-0123',
    'tracking_number'       => '555-0456',
    'address'               => '123 Main St...',
    'city'                  => 'Los Angeles',
    'state'                 => 'CA',
    'zip'                   => '90210',
    'rating'                => 4.5,
    'reviews'               => 23,
    'award'                 => '1',
    'award_description'     => 'Top 10 Rehab In California',
    'accepts_insurance'     => true/false,
    'website'               => 'https://...',
    'overview'              => 'Description text...',
    'claimed'               => true/false,
    'card_options'          => [],
    'program'               => 'program_name'
]
```

## Sorting Logic

The system uses a multi-tier sorting approach with optimized Premium+ budget pacing.

### Performance Optimization Strategy

**Key Innovation**: Pre-calculated scores stored in ACF fields to avoid expensive Elasticsearch script execution on every query.

### 1. **Premium Tier Sorting** (Highest Priority)

The system uses field-based sorting for maximum performance:

#### ES Sort Order:
1. **Primary**: `rmg.premium_plus_pacing_score` (desc, missing = `_last`)
   - Active Premium+: 3000-3999 (dynamic, based on budget/pacing)
   - Exhausted Premium+: 2000 (static, same as Premium)
   - Premium: 2000 (static)
   - Free: 1000 (static)

2. **Fallback**: `rmg.premium_level.keyword` (desc, missing = `_last`)
   - Sorts string values: "Premium+" > "Premium" > "Free"
   - Used as secondary sort for ties (e.g., multiple exhausted Premium+ at score 2000)

3. **Secondary Fallback**: `rmg.budgeted_views` (desc, missing = `_last`)
   - Further tiebreaker: Higher budget = higher priority

#### Score Ranges:
- **Active Premium+** (3000-3999): Dynamic score based on budget and pacing
- **Exhausted Premium+** (2000): Budget depleted - sorts with regular Premium tier
- **Premium** (2000): Static score - same level as exhausted Premium+
- **Free** (1000): Static score - baseline tier

**Key Behavior**: When Premium+ budget is exhausted (`views_remaining = 0`), the score drops to 2000, placing it in the same tier as regular Premium listings. Secondary sort criteria (premium_level.keyword, budgeted_views, distance) determine order within the tier.

### 2. **Premium+ Pacing Algorithm** (Budget Management)

#### How It Works (In Simple Terms)

Think of Premium+ like a monthly advertising budget that needs to last the entire period:

**The Goal**: Ensure advertisers' budgets last while prioritizing those who paid more.

**The Challenge**: Balance between:
- Giving priority to bigger spenders
- Preventing budget exhaustion
- Maintaining fair distribution

#### The Three Factors

1. **Budget Size** (Who paid more?)
   - Larger budgets get higher base scores
   - Uses logarithmic scaling to prevent dominance
   - Example: 50,000 budget scores higher than 10,000 budget

2. **Remaining Budget** (How much is left?)
   - More remaining views = better score
   - Acts as a health indicator

3. **Pacing Multiplier** (Are they burning too fast?)
   - **Healthy (50%+ remaining)**: Full priority (100%)
   - **Moderate (25-50% remaining)**: Slight penalty (85%)
   - **Low (10-25% remaining)**: Moderate penalty (60%)
   - **Critical (<10% remaining)**: Severe penalty (30%)

#### Real Examples

| Advertiser | Total Budget | Remaining | % Left | Pacing | Final Score |
|------------|-------------|-----------|--------|--------|-------------|
| Big Spender | 44,000 | 22,000 | 50% | Good ✓ | 3,678 |
| Medium Buyer | 25,000 | 12,500 | 50% | Good ✓ | 3,644 |
| Small Budget | 7,800 | 3,900 | 50% | Good ✓ | 3,568 |
| Almost Out | 500,000 | 200 | 0.04% | Critical ⚠️ | 3,302 |

Notice how the "Almost Out" advertiser, despite having the largest initial budget, ranks last because they've nearly exhausted their impressions.

### 3. **Geographic Distance + Quality**

When location data is available:
- Combines facility quality score (`total_points`) with proximity
- Distance bonuses:
  - 0-25 miles: +10 points
  - 25-50 miles: +5 points
  - 50-100 miles: +2 points
  - 100+ miles: No bonus
- Includes small randomization (1-3 points) for variety

### 4. **Pure Distance Sort** (Final Tiebreaker)

Used as the last criterion when all else is equal.

## Caching Strategy

### Dual-Layer Cache System

1. **Processing Cache** (Request-level)
   - Prevents duplicate queries within same request
   - Stored in static class property
   - Immediate response for repeated queries

2. **Transient Cache** (WordPress)
   - 15-minute duration by default
   - Keyed by query parameters and randomization seed
   - Shared across requests

### Cache Key Components
- Action type
- Card count
- Selected terms
- Current URL
- Randomization seed

## Debug Parameters

| Parameter | Purpose |
|-----------|---------|
| `?debug-bypass-cache` | Bypasses all caching |
| `?debug-location` | Disables geo-location features |

## Page Type Detection

The class automatically detects the current page context:

- **State Pages**: Parent-level `rehab-centers` taxonomy terms
- **City Pages**: Child-level `rehab-centers` taxonomy terms
- **Rehab Center Pages**: Single `rehab-center` post type
- **Default Pages**: All other page types

## Location Data Sources

### Priority Order

1. **Taxonomy Location** (City/Rehab Center pages)
   - ACF fields: `_pronamic_google_maps_latitude/longitude`
   - Attached to taxonomy terms

2. **User Location** (Default pages)
   - CloudFlare headers: `HTTP_CF_IPLATITUDE/LONGITUDE`
   - Includes city, region, country data

## Term Filtering

### Supported Taxonomies

| Frontend Key | Elasticsearch Field | Description |
|--------------|-------------------|-------------|
| `treatmentOptions` | `rmg.treatment` | Treatment modalities |
| `paymentOptions` | `rmg.payment` | Payment methods accepted |
| `programs` | `rmg.programs` | Specialized programs |
| `levelsOfCare` | `rmg.levels_of_care` | Care intensity levels |
| `clinicalServices` | `rmg.clinical_services` | Clinical offerings |
| `amenities` | `rmg.amenities` | Facility amenities |

### Filter Modes

- **Standard (`filtered`)**: OR logic across all selected terms
- **Tabbed (`tabs`)**: Separate query per term for tab interface

## Performance Considerations

- Queries fetch 3x the requested card count for better selection
- Uses Elasticsearch `_msearch` for efficient multi-query operations
- Limits source fields to only required data
- Implements ignore_unmapped for optional geo fields

## Error Handling

- Returns empty array on query failure
- Logs errors when `WP_DEBUG` is enabled
- Gracefully handles missing location data
- Validates Elasticsearch response codes

## Usage Examples

### Basic Usage
```php
$query = new RMG_Premium_Listings_ES_Query();
$cards = $query->init([
    'action_type' => 'all',
    'card_count' => 6
]);
```

### With Term Filtering
```php
$cards = $query->init([
    'action_type' => 'filtered',
    'card_count' => 3,
    'selected_terms' => [
        'treatmentOptions' => ['Detox', 'Residential'],
        'paymentOptions' => ['Insurance', 'Private Pay']
    ]
]);
```

### Tabbed Interface
```php
$tabbed_results = $query->init([
    'action_type' => 'tabs',
    'card_count' => 3,
    'selected_terms' => [
        'levelsOfCare' => ['Inpatient', 'Outpatient', 'Intensive Outpatient']
    ]
]);
```

### Excluding Displayed Posts
```php
$cards = $query->init([
    'action_type' => 'all',
    'card_count' => 3,
    'exclude_displayed' => true,
]);
```

## Premium+ Technical Implementation

### Architecture Overview

**New Optimized Approach** (v2.0):
- **Pre-calculation**: Scores calculated in PHP by `RMG_Premium_Listings_ES_Utils::calculate_premium_plus_pacing_score()`
- **Storage**: Stored in ACF field `premium_plus_pacing_score` (only for Premium+ listings)
- **ES Query**: Simple field sort - no expensive scripts!
- **Auto-update**: Recalculated automatically when impressions decrement budget

**Why This Is Better**:
- 🚀 10-100x faster queries (no Painless script execution)
- 📊 Consistent scoring across all queries
- 🔄 Updates only when budget changes (efficient)
- 💾 Reduced ES cluster load

### Required ACF Fields (WordPress)

| Field Name | Type | Values | Description |
|------------|------|--------|-------------|
| `premium` | Button Group | 0=Free, 1=Premium, 2=Premium+ | Premium tier (array with value/label) |
| `budgeted_views` | Number | Integer | Total impression budget purchased (source of truth) |
| `views_remaining` | Number | Integer | Remaining views (decrements from total on each impression) |
| `views_consumed` | Number | Integer | Total impressions delivered (increments from 0) |
| `override_views` | Number | Integer | Bonus views to add (additive: total = budgeted + override) |
| `premium_plus_pacing_score` | Number | 1000-3999 | Calculated score (auto-populated for all tiers) |

**Pacing Score Values:**
- Premium+ (active): 3000-3999 (dynamic based on budget/pacing)
- Premium+ (exhausted): 2000 (same as regular Premium)
- Premium: 2000 (static)
- Free: 1000 (static)

**Budget Calculation**: Total available views = `budgeted_views + override_views`. The `views_remaining` field is recalculated when budget or override fields change: `views_remaining = (budgeted_views + override_views) - views_consumed`.

### Required Elasticsearch Fields

```json
{
  "rmg.premium_level.keyword": "Premium+",        // String: "Premium+", "Premium", or "Free"
  "rmg.premium_plus_pacing_score": 3678,          // Integer: 1000-3999 (all tiers)
  "rmg.budgeted_views": 50000,                    // Integer: total budget purchased
  "rmg.views_remaining": 47000,                   // Integer: views left (decrements)
  "rmg.views_consumed": 3000,                     // Integer: impressions delivered
  "rmg.override_views": 0                         // Integer: bonus views added
}
```

**Score Examples:**
- Active Premium+ with budget: `premium_plus_pacing_score: 3456`
- Exhausted Premium+: `premium_plus_pacing_score: 2000`
- Regular Premium: `premium_plus_pacing_score: 2000`
- Free: `premium_plus_pacing_score: 1000`

### Budget Tracking Flow

**Architecture**: Budget management is handled by `rmg-impression-tracking` plugin, ES scoring by this plugin.

**On Impression** (managed by `RMG_Budget_Manager` in rmg-impression-tracking):
1. **User Interaction**: View/click tracked via REST API endpoint
2. **Budget Update**: `views_consumed` incremented, `views_remaining` decremented
3. **Score Trigger**: Calls `RMG_Premium_Listings_ES_Utils::update_premium_plus_pacing_score()` if class exists
4. **ACF Update**: New score stored in `premium_plus_pacing_score` field
5. **ES Reindex**: Post automatically reindexed via ElasticPress `save_post` hook
6. **Next Query**: Uses pre-calculated score for fast field-based sorting

**On ACF Save** (managed by `RMG_Budget_Manager` in rmg-impression-tracking):
- When `override_views` or `budgeted_views` is updated via ACF save
- `RMG_Budget_Manager::recalculate_views_remaining()` runs:
  - Recalculates: `views_remaining = (budgeted_views + override_views) - views_consumed`
  - Calls `RMG_Premium_Listings_ES_Utils::update_premium_plus_pacing_score()` if class exists
  - Triggered via ACF `save_post` action hook (priority 20)

**Plugin Separation**:
- `rmg-impression-tracking`: Owns `budgeted_views`, `views_remaining`, `views_consumed`, `override_views` fields
- `rmg-premium-listings` (this plugin): Owns `premium_plus_pacing_score` field and ES scoring logic
- Communication via `class_exists()` checks for graceful degradation

### Score Calculation Logic (PHP)

The `RMG_Premium_Listings_ES_Utils::calculate_premium_plus_pacing_score()` method implements this algorithm:

```php
// Only for Premium+ listings (ACF 'premium' field value = 2)
// Calculate total available views (additive model)
$effective_budget = $budgeted_views + $override_views;

if (premium_level === 2 && $effective_budget > 0 && $views_remaining > 0) {
    // 1. Base score from total budget size (log scale prevents dominance)
    budgetScore = log10($effective_budget + 1) * 150

    // 2. Pacing multiplier (penalty for burning too fast)
    remaining_ratio = $views_remaining / $effective_budget

    if (remaining_ratio < 0.1)       pacingMultiplier = 0.3   // Critical
    else if (remaining_ratio < 0.25) pacingMultiplier = 0.6   // Low
    else if (remaining_ratio < 0.5)  pacingMultiplier = 0.85  // Moderate
    else                              pacingMultiplier = 1.0   // Healthy

    // 3. Apply pacing to budget score
    finalScore = budgetScore * pacingMultiplier

    // 4. Add small boost for absolute remaining (capped at 100)
    remainingBoost = min(log10($views_remaining + 1) * 20, 100)
    finalScore += remainingBoost

    // 5. Final score (capped at 3999)
    return 3000 + min(finalScore, 999)
}

// Premium+ with exhausted budget or no budget = treat as Premium
return 2000
```

### Utility Class: RMG_Premium_Listings_ES_Utils

**Location**: `/inc/es/class-rmg-es-utils.php`

**Key Methods**:

1. `calculate_premium_plus_pacing_score(int $post_id, ?int $premium_level, ?int $budgeted_views, ?int $views_remaining, ?int $override_views): int`
   - Calculates the Premium+ pacing score using additive budget model
   - Uses `budgeted_views + override_views` for total budget calculation
   - Returns: 3000-3999 (Premium+), 2000 (Premium), 1000 (Free)

2. `update_premium_plus_pacing_score(int $post_id): bool`
   - Updates the `premium_plus_pacing_score` ACF field for all premium tiers
   - Premium+ (2): Calculates dynamic score 3000-3999 (or 2000 if exhausted)
   - Premium (1): Stores static score 2000
   - Free (0): Stores static score 1000
   - Called by `RMG_Budget_Manager` from rmg-impression-tracking plugin
   - Returns: true if updated, false if skipped

3. `batch_update_premium_plus_pacing_scores(bool $premium_plus_only, int $batch_size): array`
   - Batch updates scores for existing facilities
   - Useful for initial population or mass updates
   - Returns: array with counts (total, updated, skipped, errors)

**Note**: Budget field management (`views_remaining`, `views_consumed`) has been moved to `RMG_Budget_Manager` class in rmg-impression-tracking plugin. This class now focuses solely on ES scoring calculations.

**Important**: All premium tiers now store a `premium_plus_pacing_score` value. This ensures consistent ES sorting and allows exhausted Premium+ listings to properly demote to Premium tier (both have score 2000).

**Usage Example**:
```php
// Manually trigger score update for a post
RMG_Premium_Listings_ES_Utils::update_premium_plus_pacing_score(123);

// Batch update all Premium+ facilities
$results = RMG_Premium_Listings_ES_Utils::batch_update_premium_plus_pacing_scores(true);
// Returns: ['total' => 50, 'updated' => 48, 'skipped' => 2, 'errors' => []]
```

## Troubleshooting Premium+ Sorting

### Common Issues and Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Premium+ not prioritized | Missing `premium_plus_pacing_score` in ES | Run batch update: `RMG_Premium_Listings_ES_Utils::batch_update_premium_plus_pacing_scores(false)` to update all tiers |
| Score not updating | ACF field update failing | Check `premium` field returns array format `['value' => 2, 'label' => 'Premium+']` |
| 400 ES errors | Field type mismatch | Use `rmg.premium_level.keyword` not `rmg.premium_level` in queries |
| Exhausted Premium+ ranks too high | Budget depleted but still prioritized | Score should be 2000 when exhausted - check `views_remaining = 0` triggers score update |
| Override not working | Views not recalculated | Verify `RMG_Budget_Manager` class exists in rmg-impression-tracking plugin, check ACF `save_post` hook |
| Wrong sort order | Cache issues | Add `?debug-bypass-cache` to URL for testing |
| Premium treated as Free | ACF button group misconfigured | Verify `premium` field has values: 0=Free, 1=Premium, 2=Premium+ |
| All listings have same score | Missing tier-specific scores | Premium should be 2000, Free should be 1000, Premium+ should be 3000-3999 |

### Debug Mode

Enable detailed logging:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Watch for these log messages:

**Budget Management** (from rmg-impression-tracking):
```
RMG Budget Manager: View counts updated for post 12345: consumed: 5000 -> 5001, remaining: 45000 -> 44999
RMG Budget Manager: Triggered pacing score update for post 12345
```

**Score Calculation** (from rmg-premium-listings):
```
RMG ES Utils: update_premium_plus_pacing_score() called for post 12345
RMG ES Utils: premium_level for post 12345: 2 (Premium+)
RMG ES Utils: Calculated premium_plus_pacing_score for post 12345: 3678
RMG ES Utils: update returned: true
RMG ES Utils: Verified stored value: 3678
```

**Override Recalculation** (from rmg-impression-tracking):
```
RMG Budget Manager: Recalculated views_remaining for post 12345: total_views=75000, consumed=5000, remaining=70000
RMG Budget Manager: Triggered pacing score update for post 12345
```

### Verifying Setup

**1. Check ACF Field Setup**:
```php
$premium_raw = get_field('premium', $post_id);
// Should return: ['value' => 2, 'label' => 'Premium+']

$score = get_field('premium_plus_pacing_score', $post_id);
// Active Premium+: Should return 3000-3999
// Exhausted Premium+: Should return 2000
// Premium: Should return 2000
// Free: Should return 1000
```

**2. Check ES Index**:
```bash
# Query ES to see if fields are indexed
curl -X GET "localhost:9200/rehab-center/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "query": { "match_all": {} },
  "_source": [
    "rmg.premium_plus_pacing_score",
    "rmg.premium_level",
    "rmg.budgeted_views",
    "rmg.views_remaining",
    "rmg.views_consumed",
    "rmg.override_views"
  ],
  "size": 10
}
'
```

**3. Manual Score Update**:
```php
// Force update for a single post (works for all tiers)
$result = RMG_Premium_Listings_ES_Utils::update_premium_plus_pacing_score(12345);
var_dump($result); // Should be true

// Batch update all posts (all tiers)
$results = RMG_Premium_Listings_ES_Utils::batch_update_premium_plus_pacing_scores(false);
print_r($results);
// Expected: ['total' => X, 'updated' => X, 'skipped' => 0, 'errors' => []]

// Batch update only Premium+ posts
$results = RMG_Premium_Listings_ES_Utils::batch_update_premium_plus_pacing_scores(true);
print_r($results);
// Expected: ['total' => Y, 'updated' => Y, 'skipped' => 0, 'errors' => []]
```

## Dependencies

- WordPress Core
- Elasticsearch server
- ElasticPress plugin (for post syncing)
- `RehabMediaGroup\Elasticsearch\Elasticsearch` class
- `RehabMediaGroup\Elasticsearch\Utilities` class
- `RMG_Premium_Listings_Cards_Renderer` render class
- ACF (Advanced Custom Fields) for location and budget fields

## Notes

- The class uses WordPress coding standards
- Implements proper escaping and sanitization
- Supports WP_DEBUG for development logging
- Thread-safe with static processing cache

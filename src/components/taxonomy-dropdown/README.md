# GroupedTaxonomyDropdown

A reusable WordPress React component for rendering a grouped taxonomy dropdown with support for multi-select and single-select modes.
This component fetches taxonomy terms dynamically from a custom WordPress REST API endpoint powered by Elasticsearch, and organizes them into predefined groups (e.g., Treatment Options, Payment Options, Programs).

## Features

- Fetches taxonomy terms for multiple groups in a single Elasticsearch query.
- Displays a dropdown with grouped sections (`MenuGroup`).
- Supports **multi-select** (checkboxes) and **single-select** (radio-style menu).
- Provides a customizable label and placeholder.
- Displays loading states and error messages.
- Integrates with `@wordpress/components` and `@wordpress/i18n`.
- Supports translations for UI strings.

## Installation

Place this component in your project’s component library and import it where needed:

```js
import GroupedTaxonomyDropdown from './GroupedTaxonomyDropdown';
```

Ensure your project includes the following dependencies:

- `@wordpress/element`
- `@wordpress/components`
- `@wordpress/i18n`
- `prop-types`

## Usage

```jsx
import { useState } from '@wordpress/element';
import GroupedTaxonomyDropdown from './GroupedTaxonomyDropdown';

export default function Example() {
    const [selections, setSelections] = useState({});

    return (
        <GroupedTaxonomyDropdown
            label="Filter by Options"
            selections={selections}
            onChange={setSelections}
            placeholder="Select an option"
            multiSelect
        />
    );
}
```

## Props

| Prop          | Type     | Required | Default            | Description                                                                 |
|---------------|----------|----------|--------------------|-----------------------------------------------------------------------------|
| `label`       | string   | Yes      | —                  | The label displayed above the dropdown.                                     |
| `selections`  | object   | Yes      | `{}`               | Current selections grouped by taxonomy key.                                 |
| `onChange`    | function | Yes      | —                  | Callback fired when selections change.                                      |
| `placeholder` | string   | No       | `"Select an option"` | Text shown when no selections are made.                                     |
| `multiSelect` | bool     | No       | `false`            | If `true`, multiple selections per group are allowed (checkbox mode).       |

## Taxonomy Groups

This component defines six static taxonomy groups, each linked to an Elasticsearch field:

| Key              | Field Name                         | Label              |
|------------------|------------------------------------|--------------------|
| `treatmentOptions` | `rmg.treatment.keyword`             | Treatment Options  |
| `paymentOptions`   | `rmg.payment.keyword`               | Payment Options    |
| `programs`         | `rmg.programs.keyword`              | Programs           |
| `levelsOfCare`     | `rmg.levels_of_care.keyword`        | Levels of Care     |
| `clinicalServices` | `rmg.clinical_services.keyword`     | Clinical Services  |
| `amenities`        | `rmg.amenities.keyword`             | Amenities          |

## Behavior

- **Loading State:** Displays a spinner until terms are fetched.
- **Error Handling:** Displays an error message if the API call fails.
- **Single-Select Mode:** Selecting a term clears other selections across all groups.
- **Multi-Select Mode:** Allows multiple selections per group via checkboxes.
- **Display Text:** Updates button label based on number of selections:
  - `0` → placeholder
  - `1` → group label + term label
  - `>1` → `n options selected`

## API Dependency

This component expects a custom REST API endpoint at:

```
POST /wp-json/rehab/v1/elasticsearch
```

with the following request payload:

```json
{
  "query_string": "_search",
  "payload": "{...Elasticsearch aggregation query...}"
}
```

The API should return Elasticsearch-style aggregations for each taxonomy group.

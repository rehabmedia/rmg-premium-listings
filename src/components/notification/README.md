# Notification Component

A simple, lightweight notification component for displaying contextual messages in your application.
This is a slimmed-down version of the [WordPress Notice component](https://developer.wordpress.org/block-editor/reference-guides/components/notice/).

## Features
- Supports multiple notification types (`info`, `success`, `warning`, `error`).
- Provides contextual background and text colors automatically.
- Simple API with minimal props.

## Usage
```jsx
import Notification from './Notification';

function App() {
  return (
    <div>
      <Notification
        text="This is an info message."
        type="info"
      />

      <Notification
        text="Operation completed successfully."
        type="success"
      />

      <Notification
        text="Please double-check your input."
        type="warning"
      />

      <Notification
        text="An error occurred."
        type="error"
      />
    </div>
  );
}
```

## Props
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `text` | `string` | Yes | The message to display inside the notification. |
| `type` | `string` | Yes | The type of notification. Supports: `info`, `success`, `warning`, `error`. Defaults to a neutral style if not recognized. |

## Customization
The `getNotificationColors(type)` function determines the colors based on the notification type. You can update the color mappings to match your design system.

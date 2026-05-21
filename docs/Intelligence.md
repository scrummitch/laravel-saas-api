Our billing analytics system provides comprehensive tracking across all billing-related user interactions. The system is designed to handle any type of billing flow, whether it's a traditional paywall, an in-product upgrade prompt, or a multi-page checkout process.

## Entry Event

The entry event marks the beginning of a potential billing interaction. It tracks when and why users encounter billing-related interfaces.

### Example Usage

```javascript
// User is entering the 'upgrade-button' workflow
plandalf.intel.action('entry', {
  flow: "upgrade-button",
});
```

### Attributes

| Name | Type | Required | Example | Description                                                            |
|------|------|-------|---------|------------------------------------------------------------------------|
| `flow` | string | ✅ | usage-upgrade | Identifies the billing workflow flow the user has entered              |
| `placement` | string |  | editor-overlay | Where in your application the entry point appears                      |
| `scenario` | string |  | first-time | The variant of the flow shown to this user based on their context      |
| `element` | string |  | pro-features-wall | A reference of the specific UI element where the entry occurred        |
| `restriction` | object |  | *see below* | Optional context about why the user encountered this billing interface |

### Restriction Object

| Name | Type   | Required | Example | Description                                                                                       |
|------|--------|--------|---------|---------------------------------------------------------------------------------------------------|
| `reason` | enum   | ✅ | usage_limit | Why the billing interface is being shown. (gate, usage, geo, rate, seat, lockout, segment, offer) |
| `message` | string |  | "You've reached your template limit" | The user-facing message explaining the restriction                                                |
| `current` | number |  | 45 | Current value (e.g., templates used). Required for usage-based restrictions                       |
| `limit` | number |  | 50 | Maximum allowed value. Required for usage-based restrictions                                      |
| `step` | number |  | 2 | Progress indicator for multi-step flows. Starts at 1                                              |
| `entitlement` | string |  | templates | The specific feature or capability being restricted                                               |

### Common Examples

#### User hits Usage Limit
```javascript
plandalf.intel.action('entry', {
  flow: "template-editor",
  scenario: "free-user",
  element: "usage-wall",
  restriction: {
    reason: "usage_limit",
    message: "You've reached your monthly template limit",
    current: 45,
    limit: 50,
    entitlement: "templates"
  },
  placement: "editor-overlay"
});
```

#### User hits a Feature Gate
```javascript
plandalf.intel.action('entry', {
  flow: "team-settings",
  scenario: "single-user",
  element: "invite-wall",
  restriction: {
    reason: "gate",
    message: "Upgrade to invite team members",
    entitlement: "team_seats"
  },
  placement: "settings-page"
});
```

## Start Event

The start event indicates user intent to proceed with a billing action. It marks the transition from just viewing to actively engaging with the checkout process.

If you have previously identified the `flow` in the entry event, it is not required for all consecutive actions.

### Example Usage

```javascript
plandalf.intel.action('start');
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|--------|---------|-------------|
| `element` | string |  | upgrade-cta | References the specific UI element that initiated the start action |

### Common Examples

#### Starting from a Paywall
```javascript
plandalf.intel.action('start', {
  flow: "template-editor",
  scenario: "usage-limit",
  element: "see-plans-button"
});
```

#### Starting from Settings
```javascript
plandalf.intel.action('start', {
  flow: "team-settings",
  scenario: "expansion",
  element: "add-seats-button"
});
```

## Cancel Event

The cancel event tracks when a user exits out of a started workflow. 

This will stop the tracking of the current billing activity.

### Example Usage

```javascript
plandalf.intel.action('cancel', {
  type: "unload",
  reason: "user_exit",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type   | Required | Example | Description                                             |
|------|--------|--------|--------|---------------------------------------------------------|
| `type` | enum   | ✅ | unload | How the cancellation occurred (unload, close, navigate) |
| `reason` | string | ✅ | user_exit | Why the flow was cancelled                              |
| `element` | string |  | close-button | UI element that triggered the cancellation              |

### Common Examples

#### User Navigation
```javascript
plandalf.intel.action('cancel', {
  type: "navigate",
  reason: "user_exit",
  flow: "template-editor",
  scenario: "usage-limit",
  element: "header-logo"
});
```

#### Payment Failure
```javascript
plandalf.intel.action('cancel', {
  type: "close",
  reason: "transaction_failed",
  flow: "team-settings",
  scenario: "expansion",
  element: "modal-close"
});
```

## Complete Event

The complete event indicates successful completion of a billing workflow.

### Example Usage

```javascript
plandalf.intel.action('complete', {
  result: "transaction_successful",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|--------|---------|-------------|
| `result` | string | ✅ | transaction_successful" |The outcome of the workflow |

### Common Examples

#### Successful Purchase
```javascript
plandalf.intel.action('complete', {
  result: "transaction_successful",
  flow: "team-settings",
  scenario: "expansion"
});
```

## Comparison Event

The comparison event tracks when users view or interact with plan comparison interfaces.

### Example Usage

```javascript
plandalf.intel.action('comparison', {
  element: "upgrade-today-grid",
  renew_interval: "P1Y",
  currency: "AUD",
  variant_filters: {
    billing: "annual",
    team_size: "small"
  },
  selection: [
    {
      type: "plan",
      id: "pro_yearly",
      quantity: 1
    }
  ]
});
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|-------|-----|-------------|
| `element` | string | ✅ | upgrade-today-grid | Identifies the comparison interface being viewed |
| `renew_interval` | string | ✅ | P1Y | ISO 8601 duration format for billing period |
| `currency` | string | ✅ | AUD | Three-letter ISO currency code |
| `variant_filters` | object |  | `{billing: "annual"}` | Key-value pairs of active filters |
| `selection` | array |  | *see below* | Currently selected items in the comparison |

### Selection Object

| Name | Type   | Required | Example | Description |
|------|--------|--------|-------|-------------|
| `type` | enum   | ✅       | plan | Type of item (plan) |
| `id` | string | ✅       | pro_yearly | Identifier of the item |
| `quantity` | number | ✅      | 1 | Number of units selected |

## Error Event

The error event captures any issues that occur during the billing flow.

### Example Usage

```javascript
plandalf.intel.action('error', {
  message: "Payment verification failed",
  reason: "card_declined",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type | Required | Example | Description                       |
|------|------|------|---------|-----------------------------------|
| `message` | string | ✅ | "Payment verification failed" | User-facing error message         |
| `reason` | string | ✅ | card_declined | Technical reason for the error    |

# Preauthorize Event

The preauthorize event tracks when users add new payment methods.

### Example Usage

```javascript
plandalf.intel.action('preauthorize', {
  id: "pm_xxxxx",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|--------|---------|-------------|
| `id` | string | ✅ | pm_xxxxx | Payment method identifier |

## Interaction Event

The interaction event captures user interactions with billing flows that don't fit other categories.

### Example Usage

```javascript
plandalf.intel.action('interaction', {
  scene: "pricing-page",
  element: "currency-selector",
  value: "EUR",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|----------|-------|-------------|
| `scene` | string | ✅ | pricing-page | The context where the interaction occurred |
| `element` | string |  | currency-selector | Specific element interacted with |
| `value` | any |  | EUR | Any relevant value associated with the interaction |

## Transaction Event

The transaction event records purchase attempts and completions.

### Example Usage

```javascript
plandalf.intel.action('transaction', {
  id: "checkout_123",
  items: [
    {
      id: "plan_xxx",
      quantity: 12
    }
  ],
  renew_interval: "P1M",
  currency: "USD",
  variant_filters: {
    billing: "monthly",
    region: "na"
  },
  intent: "upgrade",
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

| Name | Type | Required | Example | Description |
|------|------|--------|-----|-------------|
| `id` | string | ✅ | checkout_123 | Unique identifier for this transaction |
| `items` | array | ✅ | *see below* | List of items in the transaction |
| `renew_interval` | string |  | P1M | ISO 8601 duration format |
| `currency` | string | ✅ | USD | Three-letter ISO currency code |
| `variant_filters` | object |  | `{billing: "monthly"}` | Key-value pairs affecting pricing |
| `intent` | string |  | upgrade | Purpose of the transaction |

### Transaction Item

| Name | Type | Required | Example | Description |
|-----|------|----------|---------|-------------|
| `id` | string | ✅ | plan_xxx | Item identifier |
| `quantity` | number | ✅ | 12 | Number of units |

## Custom Event

The custom event allows tracking of any additional billing-related interactions.

### Example Usage

```javascript
plandalf.intel.action('custom', {
  type: "feature_comparison",
  feature: "api_access",
  duration_ms: 5000,
  flow: "usage-upgrade",
  scenario: "first-time"
});
```

### Attributes

Custom events accept any attributes, but it's recommended to:
- Include standard fields (flow, scenario) when applicable
- Use consistent naming conventions
- Document custom event schemas for your team

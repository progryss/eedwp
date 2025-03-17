# Hide Prices for Guests - Developer Documentation

This document provides a comprehensive overview of the Hide Prices for Guests plugin structure, files, and functionality. Use this as a reference for future development or to understand the plugin architecture.

## Plugin Overview

Hide Prices for Guests is a WordPress plugin that hides product prices and disables purchasing for non-logged-in users in WooCommerce stores. It's designed to encourage user registration by restricting price visibility to logged-in users only.

## File Structure

```
hide-prices-for-guests/
├── assets/
│   ├── css/
│   │   ├── hide-prices.css
│   │   └── index.php
│   ├── images/
│   │   └── index.php
│   └── index.php
├── includes/
│   ├── class-admin-notices.php
│   ├── class-settings.php
│   └── index.php
├── languages/
│   ├── hide-prices-for-guests.pot
│   └── index.php
├── hide-prices-for-guests.php
├── index.php
├── readme.txt
├── uninstall.php
└── DEVELOPER-DOCUMENTATION.md (this file)
```

## Core Files and Their Functionality

### 1. `hide-prices-for-guests.php`

**Purpose**: Main plugin file that initializes the plugin and contains core functionality.

**Key Components**:
- Plugin header with metadata
- Constants definition (`HPFG_VERSION`, `HPFG_PLUGIN_DIR`, etc.)
- Main `Hide_Prices_For_Guests` class with:
  - Singleton pattern implementation
  - WooCommerce dependency check
  - Text domain loading for translations
  - Hooks initialization
  - Price hiding functionality
  - Cart price hiding functionality
  - Purchase disabling for guests
  - Product exclusion logic
  - Activation hook for initial setup

**Key Methods**:
- `get_instance()`: Implements singleton pattern
- `activate()`: Runs on plugin activation
- `hide_price_for_guests()`: Replaces prices with custom message
- `hide_cart_price_for_guests()`: Hides prices in cart
- `disable_purchase_for_guests()`: Prevents guests from purchasing
- `is_product_excluded()`: Checks if a product should be excluded from price hiding

### 2. `includes/class-settings.php`

**Purpose**: Handles the admin settings page and options management.

**Key Components**:
- Settings page registration in WooCommerce submenu
- Settings fields registration
- Settings sanitization
- Settings rendering
- Options retrieval

**Key Methods**:
- `add_settings_page()`: Adds settings page to WooCommerce menu
- `register_settings()`: Registers all settings fields
- `sanitize_settings()`: Sanitizes user input
- `render_settings_page()`: Outputs the settings page HTML
- `get_options()`: Static method to retrieve plugin options with defaults

### 3. `includes/class-admin-notices.php`

**Purpose**: Manages admin notices, particularly the activation welcome notice.

**Key Components**:
- Activation notice display
- Notice dismissal handling
- Transient management for notices

**Key Methods**:
- `activation_notice()`: Displays welcome notice after activation
- `dismiss_notices()`: Handles notice dismissal via AJAX
- `set_activation_notice()`: Static method to set the activation notice

### 4. `assets/css/hide-prices.css`

**Purpose**: Provides styling for the hidden price message and other UI elements.

**Key Components**:
- Styling for the hidden price message container
- Styling for links within the message
- Hide add to cart button styles
- Responsive styles for mobile devices

### 5. `languages/hide-prices-for-guests.pot`

**Purpose**: Template file for translations containing all translatable strings.

**Key Components**:
- Plugin metadata strings
- Admin interface strings
- User-facing message strings

### 6. `uninstall.php`

**Purpose**: Cleans up plugin data when the plugin is uninstalled.

**Key Components**:
- Option deletion
- Cache flushing

### 7. `readme.txt`

**Purpose**: WordPress.org compatible readme file with plugin information.

**Key Components**:
- Plugin description
- Installation instructions
- FAQ
- Changelog
- Screenshots information

### 8. Various `index.php` files

**Purpose**: Security measure to prevent directory listing.

**Content**: Simple "Silence is golden" PHP comment.

## Functionality Overview

### Price Hiding Logic

1. The plugin hooks into WooCommerce filters:
   - `woocommerce_get_price_html`
   - `woocommerce_cart_item_price`
   - `woocommerce_cart_item_subtotal`
   - `woocommerce_get_variation_price_html`

2. For non-logged-in users, it replaces the price HTML with a custom message that includes a login link.

3. The message is customizable through the settings page.

### Purchase Prevention

1. The plugin hooks into the `woocommerce_is_purchasable` filter.

2. For non-logged-in users, it returns `false` to prevent purchasing.

3. This can be enabled/disabled independently of price hiding.

### Product/Category Exclusion

1. Administrators can specify product IDs or category IDs to exclude from price hiding.

2. The `is_product_excluded()` method checks if a product should be excluded based on:
   - Direct product ID match
   - Product belonging to an excluded category

### Settings Management

1. Settings are stored in a single option array `hpfg_options` with the following structure:
   ```php
   [
       'enable' => true/false,
       'message' => 'Custom message with %login_url% placeholder',
       'hide_add_to_cart' => true/false,
       'excluded_products' => 'comma,separated,ids',
       'excluded_categories' => 'comma,separated,ids'
   ]
   ```

2. The `HPFG_Settings::get_options()` method provides a centralized way to access these settings with defaults.

## Hooks and Filters

### Actions

- `plugins_loaded`: Initializes the plugin
- `init`: Loads text domain
- `wp_enqueue_scripts`: Enqueues CSS styles
- `admin_menu`: Adds settings page
- `admin_init`: Registers settings
- `admin_notices`: Displays notices

### Filters

- `woocommerce_get_price_html`: Hides product prices
- `woocommerce_cart_item_price`: Hides cart prices
- `woocommerce_cart_item_subtotal`: Hides cart subtotals
- `woocommerce_get_variation_price_html`: Hides variation prices
- `woocommerce_is_purchasable`: Disables purchasing
- `body_class`: Adds CSS class for styling
- `plugin_action_links_*`: Adds settings link to plugins page

## Development Guidelines

1. **Adding New Features**:
   - Extend the main class or create new classes in the `includes` directory
   - Follow WordPress coding standards
   - Add new options to the settings page as needed

2. **Translation**:
   - Use `__()`, `esc_html__()`, or `esc_attr__()` for translatable strings
   - Update the POT file when adding new strings

3. **Security**:
   - Sanitize all inputs with appropriate WordPress functions
   - Escape all outputs with `esc_html()`, `esc_attr()`, etc.
   - Use nonces for form submissions

4. **Performance**:
   - Minimize database queries by using the cached options
   - Enqueue assets only when needed 
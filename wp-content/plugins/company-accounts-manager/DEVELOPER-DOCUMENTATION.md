# Company Accounts Manager - Developer Documentation

This document provides a comprehensive overview of the Company Accounts Manager plugin structure, files, and functionality. Use this as a reference for future development or to understand the plugin architecture.

## Plugin Overview

Company Accounts Manager is a WordPress plugin that enables businesses to create company accounts with child accounts, manage orders, apply tiered discounts, and provides an admin approval system. It's designed for B2B e-commerce sites using WooCommerce.

## File Structure

```
company-accounts-manager/
├── assets/
│   ├── css/
│   │   └── [CSS files]
│   ├── js/
│   │   └── [JavaScript files]
│   └── images/
│       └── [Image files]
├── includes/
│   ├── class-cam-admin.php
│   ├── class-cam-child-account.php
│   ├── class-cam-company-admin.php
│   ├── class-cam-install.php
│   ├── class-cam-order-manager.php
│   ├── class-cam-pricing.php
│   ├── class-cam-roles.php
│   └── class-cam-tiers.php
├── languages/
│   └── [Translation files]
├── templates/
│   ├── admin/
│   │   ├── all-companies.php
│   │   ├── company-details.php
│   │   ├── dashboard.php
│   │   ├── manage-tiers.php
│   │   └── pending-companies.php
│   ├── company-dashboard.php
│   └── registration-form.php
└── company-accounts-manager.php
```

## Database Structure

The plugin creates the following database tables:

1. **`{prefix}cam_companies`**
   - Stores company information
   - Fields: id, user_id, company_name, industry, company_info, registration_date, status, tier_id, admin_status

2. **`{prefix}cam_child_accounts`**
   - Stores child account information
   - Fields: id, user_id, company_id, created_date, status

3. **`{prefix}cam_company_orders`**
   - Tracks orders placed by company users
   - Fields: id, order_id, company_id, user_id, order_total, order_date

4. **`{prefix}cam_discount_tiers`**
   - Stores discount tier information
   - Fields: id, tier_name, discount_percentage, created_date

## Core Files and Their Functionality

### 1. `company-accounts-manager.php`

**Purpose**: Main plugin file that initializes the plugin and loads all required components.

**Key Components**:
- Plugin header with metadata
- Constants definition (`CAM_VERSION`, `CAM_PLUGIN_DIR`, etc.)
- Main `CompanyAccountsManager` class with:
  - Singleton pattern implementation
  - WooCommerce dependency check
  - Loading of required files
  - Initialization of plugin features
  - Text domain loading for translations

**Key Methods**:
- `instance()`: Implements singleton pattern
- `init()`: Initializes the plugin
- `includes()`: Loads required files
- `init_features()`: Sets up hooks and initializes components
- `load_textdomain()`: Loads translation files

### 2. `includes/class-cam-install.php`

**Purpose**: Handles plugin installation, database creation, and updates.

**Key Components**:
- Database table creation
- Default data setup
- Registration page creation
- Version tracking

**Key Methods**:
- `activate()`: Runs on plugin activation
- `deactivate()`: Runs on plugin deactivation
- `create_tables()`: Creates database tables
- `create_registration_page()`: Creates the company registration page

### 3. `includes/class-cam-roles.php`

**Purpose**: Manages user roles and capabilities for company admins and child accounts.

**Key Components**:
- Role creation and management
- User capability filtering
- Company-user relationship management

**Key Methods**:
- `add_roles()`: Creates custom user roles
- `remove_roles()`: Removes custom user roles
- `is_company_admin()`: Checks if user is a company admin
- `is_child_account()`: Checks if user is a child account
- `get_user_company_id()`: Gets the company ID for a user
- `get_company_child_accounts()`: Gets all child accounts for a company
- `add_child_account()`: Adds a child account to a company
- `update_child_account_status()`: Updates a child account's status

### 4. `includes/class-cam-company-admin.php`

**Purpose**: Handles company admin registration, management, and dashboard functionality.

**Key Components**:
- Company registration processing
- Company dashboard rendering
- Company approval checking
- Child account management

**Key Methods**:
- `check_company_approval()`: Checks if a company is approved before login
- `registration_form_shortcode()`: Renders the registration form
- `process_registration()`: Processes company registration
- `company_dashboard_content()`: Renders the company dashboard
- `get_company_details()`: Gets company details
- `add_child_account()`: Adds a child account to the company
- `update_company_details()`: Updates company information

### 5. `includes/class-cam-child-account.php`

**Purpose**: Handles child account functionality and order tracking.

**Key Components**:
- Order linking to companies
- Order filtering for child accounts
- Child account status checking

**Key Methods**:
- `link_order_to_company()`: Links an order to a company
- `filter_orders_query()`: Filters orders for child accounts
- `display_company_info()`: Displays company information for child accounts
- `check_child_account_status()`: Checks if a child account is active

### 6. `includes/class-cam-admin.php`

**Purpose**: Handles site administrator functionality for managing companies.

**Key Components**:
- Admin menu creation
- Company approval/rejection
- Company management interface
- Settings management

**Key Methods**:
- `add_admin_menu()`: Adds admin menu items
- `render_dashboard_page()`: Renders the admin dashboard
- `render_pending_companies_page()`: Renders the pending companies page
- `render_all_companies_page()`: Renders the all companies page
- `render_company_details_page()`: Renders the company details page
- `ajax_approve_company()`: Approves a company via AJAX
- `ajax_reject_company()`: Rejects a company via AJAX

### 7. `includes/class-cam-tiers.php`

**Purpose**: Handles discount tiers management.

**Key Components**:
- Tier creation and management
- Tier assignment to companies
- Tier retrieval

**Key Methods**:
- `create_tiers_table()`: Creates the tiers database table
- `get_all_tiers()`: Gets all discount tiers
- `get_tier()`: Gets a specific tier
- `add_tier()`: Adds a new tier
- `update_tier()`: Updates an existing tier
- `delete_tier()`: Deletes a tier
- `assign_tier_to_company()`: Assigns a tier to a company
- `get_company_tier()`: Gets the tier for a company
- `get_current_user_tier()`: Gets the tier for the current user

### 8. `includes/class-cam-pricing.php`

**Purpose**: Handles pricing and discount functionality.

**Key Components**:
- Price modification based on tiers
- Price display with original price
- Discount badge styling

**Key Methods**:
- `apply_tier_discount()`: Applies tier discount to product price
- `price_html_with_original()`: Displays price with original price strikethrough
- `cart_price_html_with_original()`: Displays cart price with original price
- `add_discount_badge_css()`: Adds CSS for discount badge

### 9. `includes/class-cam-order-manager.php`

**Purpose**: Handles order tracking and statistics.

**Key Components**:
- Order statistics tracking
- Order filtering by company
- Order export functionality

**Key Methods**:
- `update_order_stats()`: Updates order statistics
- `get_company_orders()`: Gets orders for a company
- `get_company_child_orders()`: Gets orders for child accounts
- `get_company_order_stats()`: Gets order statistics for a company
- `export_company_orders_csv()`: Exports company orders as CSV
- `get_company_orders_by_date()`: Gets company orders by date range

## Template Files

### 1. `templates/company-dashboard.php`

**Purpose**: Renders the company dashboard for company admins.

**Key Components**:
- Company statistics display
- Child account management
- Order history display
- Child account creation form

### 2. `templates/registration-form.php`

**Purpose**: Renders the company registration form.

**Key Components**:
- Company information fields
- Registration submission

### 3. `templates/admin/company-details.php`

**Purpose**: Renders the company details page in the admin area.

**Key Components**:
- Company information display
- Company admin details
- Child account listing
- Order history
- Tier assignment
- Company status management

### 4. `templates/admin/all-companies.php`

**Purpose**: Renders the all companies page in the admin area.

**Key Components**:
- Company listing
- Quick status view
- Company filtering

### 5. `templates/admin/pending-companies.php`

**Purpose**: Renders the pending companies page in the admin area.

**Key Components**:
- Pending company listing
- Approval/rejection buttons

### 6. `templates/admin/manage-tiers.php`

**Purpose**: Renders the manage tiers page in the admin area.

**Key Components**:
- Tier listing
- Tier creation form
- Tier editing

## User Roles and Capabilities

### 1. Company Admin
- Can manage their company profile
- Can create and manage child accounts
- Can view all orders from their company
- Has access to company dashboard

### 2. Child Account
- Can place orders
- Can view their own orders
- Cannot manage company settings
- Cannot create other accounts

## Functionality Overview

### Company Registration and Approval

1. Users register as a company admin through the registration form.
2. Registration creates a pending company entry in the database.
3. Site administrators approve or reject company registrations.
4. Approved companies can log in and access the company dashboard.

### Child Account Management

1. Company admins can create child accounts from their dashboard.
2. Child accounts are associated with the company in the database.
3. Company admins can suspend or activate child accounts.
4. Child accounts can place orders that are tracked under the company.

### Discount Tiers

1. Site administrators can create discount tiers with different percentages.
2. Tiers can be assigned to companies.
3. All users from a company (admin and child accounts) receive the discount.
4. Discounts are applied automatically to product prices.

### Order Tracking

1. Orders placed by company admins or child accounts are tracked.
2. Orders are linked to the company in the database.
3. Company admins can view all orders from their company.
4. Order statistics are calculated and displayed on the dashboard.

### Suspension System

1. Site administrators can suspend company admin accounts.
2. Company admins can suspend child accounts.
3. Suspended accounts cannot log in.
4. Suspended companies prevent all associated users from logging in.

## Hooks and Filters

### Actions

- `plugins_loaded`: Initializes the plugin
- `init`: Loads text domain
- `admin_menu`: Adds admin menu items
- `admin_init`: Registers settings
- `admin_enqueue_scripts`: Enqueues admin scripts and styles
- `wp_enqueue_scripts`: Enqueues frontend scripts and styles
- `woocommerce_checkout_update_order_meta`: Links orders to companies
- `woocommerce_order_status_changed`: Updates order statistics
- `woocommerce_register_form`: Adds company registration fields
- `woocommerce_created_customer`: Processes company registration

### Filters

- `authenticate`: Checks company approval and account status
- `woocommerce_my_account_my_orders_query`: Filters orders for child accounts
- `woocommerce_product_get_price`: Applies tier discount
- `woocommerce_get_price_html`: Displays price with original price
- `woocommerce_cart_item_price`: Displays cart price with original price
- `user_has_cap`: Modifies user capabilities
- `editable_roles`: Filters editable roles

## Development Guidelines

1. **Adding New Features**:
   - Extend the appropriate class or create new classes in the `includes` directory
   - Follow WordPress coding standards
   - Add new options to the settings page as needed

2. **Translation**:
   - Use `__()`, `esc_html__()`, or `esc_attr__()` for translatable strings
   - Update the POT file when adding new strings

3. **Security**:
   - Sanitize all inputs with appropriate WordPress functions
   - Escape all outputs with `esc_html()`, `esc_attr()`, etc.
   - Use nonces for form submissions

4. **Database Interactions**:
   - Use `$wpdb->prepare()` for all database queries with variables
   - Follow WordPress database schema standards
   - Use transactions for multiple related operations

5. **Performance**:
   - Cache expensive database queries
   - Minimize database operations
   - Use WordPress transients for temporary data storage 
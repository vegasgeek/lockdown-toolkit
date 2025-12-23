# Lockdown Toolkit by VegasGeek

A comprehensive WordPress plugin that provides essential hardening tools to protect your WordPress site from common security threats.

## Features

### 1. REST Endpoint Hiding
Hide sensitive WordPress REST API endpoints from public access, preventing information disclosure and reducing your site's attack surface.

**Automatically Hidden on Activation:**
- `/wp/v2/users` - Prevents public enumeration of user accounts
- `/wp/v2/media` - Restricts public access to media library endpoints

**How It Works:**
- Blocks access to hidden endpoints with a 403 Forbidden response
- Completely transparent to site functionality
- Works with any REST endpoint

### 2. Hidden Login Page
Obscure your WordPress login page by moving it to a custom URL and redirecting direct access attempts to `wp-login.php`.

**Configuration:**
Located in **Settings > General** under the "Hide Login Page" section.

**Fields:**
- **Login Page URL** - Set a custom path where your login form will be accessible (e.g., `my-login`)
  - Format: `https://yoursite.com/[your-path]`
  - Leave empty to disable

- **Redirect URL** - Where to send users who try to access `wp-login.php` directly (e.g., `404` or `homepage`)
  - Format: `https://yoursite.com/[redirect-path]`
  - Leave empty to disable

**Benefits:**
- Stops automated brute-force attacks targeting the standard login page
- Reduces server load from login-targeted exploits
- Makes your site less obvious as a WordPress installation
- Maintains full WordPress login functionality at your custom URL

## How It Works

### REST Endpoint Hiding
When someone attempts to access a hidden REST endpoint:
1. The plugin intercepts the request at the REST pre-dispatch hook
2. Checks if the endpoint is in the hidden list
3. Returns a 403 Forbidden error response
4. The actual endpoint remains untouched but inaccessible

### Hidden Login Page
When a user visits a login-related URL:
1. **Accessing `wp-login.php` (GET)** → Redirected to your configured redirect path
2. **Accessing your custom login path** → Full WordPress login page loads with all styling intact
3. **Submitting login form (POST)** → Processes normally at your custom login path
4. After login → User is logged in and redirected as normal

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Admin Plugins page
3. (Optional) Configure the Hidden Login Page in **Settings > General**

## Configuration

### REST Endpoint Hiding
By default, `/wp/v2/users` and `/wp/v2/media` are hidden on activation. These endpoints are blocked automatically with no additional configuration needed.

To add or remove hidden endpoints, you would need to modify the plugin code directly or use a filter hook (in future versions).

### Hidden Login Page
Go to **Settings > General** and find the "Hide Login Page" section:

1. Enter your desired login path in the "Login Page URL" field
2. Enter the redirect path in the "Redirect URL" field
3. Click **Save Changes**

**Example Configuration:**
```
Login Page URL:  https://yoursite.com / secret-login
Redirect URL:    https://yoursite.com / page-not-found
```

Then:
- Users log in at: `https://yoursite.com/secret-login`
- Attempts to `wp-login.php` are redirected to: `https://yoursite.com/page-not-found`

## Security Considerations

### REST Endpoint Hiding
- Hiding endpoints only prevents access through the REST API
- Does not affect WordPress admin functionality
- Does not affect logged-in users or WordPress internal operations
- Endpoints return 403 Forbidden, which is a proper HTTP response

### Hidden Login Page
- Obfuscation provides defense-in-depth, not complete security
- Should be used in combination with other security measures:
  - Strong passwords
  - Two-factor authentication
  - Login attempt limiting
  - Regular WordPress updates
- The custom login path should be kept confidential
- Consider using a non-obvious path name

## Compatibility

- **WordPress:** 5.0 and above
- **PHP:** 7.2 and above
- **License:** GPL v2 or later

## Database Options

The plugin uses the following WordPress options to store settings:

- `rest_hider_hidden_endpoints` - List of hidden REST endpoints
- `lockdown_toolkit_login_page_url` - Custom login page path
- `lockdown_toolkit_redirect_url` - Redirect path for wp-login.php attempts

## Support

For issues, feature requests, or feedback, please contact the plugin author.

## Changelog

### Version 1.0.0
- Initial release
- REST endpoint hiding with automatic hiding of `/wp/v2/users` and `/wp/v2/media`
- Custom hidden login page functionality
- Settings integration with WordPress General Settings page

### Version 1.0.4
- Handle the password reset flow
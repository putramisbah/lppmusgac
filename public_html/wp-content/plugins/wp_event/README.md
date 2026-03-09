# Event Registration & Payment

A comprehensive WordPress plugin for managing events with built-in registration system and Midtrans payment gateway integration.

## Features

- ✅ Custom Event Post Type
- ✅ Event Details Management (Date, Time, Location, Capacity, Fee, Deadline)
- ✅ Multi-Attendee Registration System
- ✅ Midtrans Payment Gateway Integration
- ✅ Automatic Capacity Management
- ✅ Registration Deadline Enforcement
- ✅ Admin Dashboard with Search & Filters
- ✅ Support for Free and Paid Events
- ✅ Modern, Responsive UI
- ✅ Server-Side Validation
- ✅ Individual Attendee Tracking

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"
5. **Configure WordPress Timezone** (Important!)
   - Go to Settings → General
   - Set "Timezone" to your local timezone (e.g., Asia/Jakarta)
   - This ensures attendance timestamps display correctly
6. Configure Midtrans settings at Settings → Event Payment

## Important: Timezone Configuration

⚠️ **Before using attendance tracking features:**

WordPress timezone **must be configured** for accurate attendance timestamps.

**Steps:**
1. Go to **Settings → General**
2. Scroll to **Timezone** setting
3. Select your timezone (e.g., `Asia/Jakarta` for WIB, `Asia/Makassar` for WITA)
4. Save changes

**Why this matters:**
- Attendance timestamps use WordPress timezone
- Without proper configuration, times will show in UTC (server time)
- mysql2date() function requires timezone_string to be set

**Check if configured correctly:**
- The plugin will show a warning banner if timezone is not set
- Attendance times should match your local timezone

## Midtrans Setup

1. Register at [Midtrans Dashboard](https://dashboard.midtrans.com/)
2. Get your Server Key and Client Key from Settings → Access Keys
3. In WordPress, go to Settings → Event Payment
4. Enter your Midtrans credentials
5. Choose environment (Sandbox for testing, Production for live)

## Usage

### Creating an Event

1. Go to Events → Add New
2. Enter event title and description
3. Fill in event details:
   - Event Date
   - Event Time
   - Location
   - Capacity (max attendees)
   - Entrance Fee (0 for free events)
   - Registration Deadline
4. Publish the event

### Managing Registrations

1. Go to Events → Registrations
2. View all registrations with attendee details
3. Search by name, email, phone, job, or event name
4. Filter by event, status, or payment status
5. Export data (coming soon)

### Payment Settings

1. Go to Settings → Event Payment (Admin only)
2. Configure:
   - Midtrans Server Key
   - Midtrans Client Key
   - Environment (Sandbox/Production)
3. Save settings

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Midtrans account (for paid events)
- SSL certificate (recommended for payments)

## File Structure

```
wp_event/
├── event-registration-payment.php  # Main plugin file
├── registration-page.php           # Registration form template
├── payment-page.php                # Payment page template
├── readme.txt                      # WordPress plugin readme
└── README.md                       # This file
```

## Database

The plugin creates a custom table `wp_event_registrations` to store:
- Event ID
- Attendee information (name, email, phone, job)
- Ticket quantity
- Registration date
- Status (pending/confirmed/cancelled)
- Payment status (pending/paid/failed)

## Development

### Hooks & Filters

The plugin provides various hooks for customization:
- `the_content` - Event details display
- `init` - Custom post type registration
- `add_meta_boxes` - Event meta boxes
- `save_post` - Save event details

### AJAX Endpoints

- `erp_handle_registration` - Free event registration
- `erp_process_payment` - Paid event registration
- `erp_get_snap_token` - Get Midtrans Snap token

## Security

- Nonce verification on all forms
- Capability checks for admin functions
- Input sanitization and validation
- Server-side validations
- Admin-only access to sensitive settings

## Changelog

### Version 1.0.0
- Initial release
- Event management system
- Registration with multi-attendee support
- Midtrans payment integration
- Admin dashboard
- Search and filter functionality
- Registration deadline feature
- Capacity management

## License

GPLv2 or later

## Support

For support, feature requests, or bug reports, please contact the developer.

## Credits

- Developer: Habib Putra
- Payment Gateway: Midtrans
- Icons: WordPress Dashicons

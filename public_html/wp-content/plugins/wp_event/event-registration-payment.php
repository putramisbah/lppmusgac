<?php
/**
 * Plugin Name: Event Registration & Payment
 * Plugin URI: https://example.com/event-plugin
 * Description: Simple event management plugin with custom post type for events
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: event-registration
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EVENT_PLUGIN_VERSION', '1.0.0');
define('EVENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EVENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ERP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ERP_ADMIN_EMAIL', 'admin@lppm.usg.ac.id');

/**
 * Email Configuration - Environment Aware
 * Works with MailHog in local development, normal SMTP in production
 */
function erp_configure_email() {
    // Check if we're in local development environment
    $is_local = (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') || 
                (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
    
    if ($is_local) {
        // DEVELOPMENT: Use MailHog SMTP
        add_action('phpmailer_init', function($phpmailer) {
            $phpmailer->isSMTP();
            $phpmailer->Host = 'mailhog';
            $phpmailer->Port = 1025;
            $phpmailer->SMTPAuth = false;
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
            $phpmailer->Timeout = 10;
        }, 1);
    }
    
    // Set proper From address for both local and production
    add_filter('wp_mail_from', function($from_email) {
        return 'noreply@lppm.usg.ac.id';
    }, 999);
    
    add_filter('wp_mail_from_name', function($from_name) {
        return 'LPPM USG Events';
    }, 999);
}
add_action('plugins_loaded', 'erp_configure_email', 1);

/**
 * Initialize Midtrans configuration after WordPress loads
 */
function erp_init_midtrans_config() {
    // Midtrans Configuration - Load from settings or use defaults
    if (!defined('MIDTRANS_SERVER_KEY')) {
        $server_key = get_option('erp_midtrans_server_key', '');
        define('MIDTRANS_SERVER_KEY', $server_key);
    }
    if (!defined('MIDTRANS_CLIENT_KEY')) {
        $client_key = get_option('erp_midtrans_client_key', '');
        define('MIDTRANS_CLIENT_KEY', $client_key);
    }
    if (!defined('MIDTRANS_IS_PRODUCTION')) {
        $is_production = get_option('erp_midtrans_environment', 'sandbox') === 'production';
        define('MIDTRANS_IS_PRODUCTION', $is_production);
    }
}
add_action('plugins_loaded', 'erp_init_midtrans_config', 1);
add_action('admin_init', 'erp_init_midtrans_config', 1);

/**
 * Helper function to ensure Midtrans constants are available
 */
function erp_ensure_midtrans_constants() {
    if (!defined('MIDTRANS_SERVER_KEY')) {
        erp_init_midtrans_config();
    }
}

/**
 * Register Event Custom Post Type
 */
function erp_register_event_post_type() {
    $labels = array(
        'name'                  => _x('Events', 'Post Type General Name', 'event-registration'),
        'singular_name'         => _x('Event', 'Post Type Singular Name', 'event-registration'),
        'menu_name'             => __('Events', 'event-registration'),
        'name_admin_bar'        => __('Event', 'event-registration'),
        'archives'              => __('Event Archives', 'event-registration'),
        'attributes'            => __('Event Attributes', 'event-registration'),
        'parent_item_colon'     => __('Parent Event:', 'event-registration'),
        'all_items'             => __('All Events', 'event-registration'),
        'add_new_item'          => __('Add New Event', 'event-registration'),
        'add_new'               => __('Add New', 'event-registration'),
        'new_item'              => __('New Event', 'event-registration'),
        'edit_item'             => __('Edit Event', 'event-registration'),
        'update_item'           => __('Update Event', 'event-registration'),
        'view_item'             => __('View Event', 'event-registration'),
        'view_items'            => __('View Events', 'event-registration'),
        'search_items'          => __('Search Event', 'event-registration'),
        'not_found'             => __('Not found', 'event-registration'),
        'not_found_in_trash'    => __('Not found in Trash', 'event-registration'),
        'featured_image'        => __('Featured Image', 'event-registration'),
        'set_featured_image'    => __('Set featured image', 'event-registration'),
        'remove_featured_image' => __('Remove featured image', 'event-registration'),
        'use_featured_image'    => __('Use as featured image', 'event-registration'),
        'insert_into_item'      => __('Insert into event', 'event-registration'),
        'uploaded_to_this_item' => __('Uploaded to this event', 'event-registration'),
        'items_list'            => __('Events list', 'event-registration'),
        'items_list_navigation' => __('Events list navigation', 'event-registration'),
        'filter_items_list'     => __('Filter events list', 'event-registration'),
    );

    $args = array(
        'label'               => __('Event', 'event-registration'),
        'description'         => __('Event management', 'event-registration'),
        'labels'              => $labels,
        'supports'            => array('title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments'),
        'taxonomies'          => array('category', 'post_tag'),
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-calendar-alt',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => 'events',
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'show_in_rest'        => true,
        'rewrite'             => array('slug' => 'events'),
    );

    register_post_type('event', $args);
}
add_action('init', 'erp_register_event_post_type', 0);

/**
 * Include events in category and tag archives
 */
function erp_include_events_in_archives($query) {
    if (!is_admin() && $query->is_main_query()) {
        // Include events in category archives
        if ($query->is_category() || $query->is_tag()) {
            $query->set('post_type', array('post', 'event'));
        }
    }
}
add_action('pre_get_posts', 'erp_include_events_in_archives');

/**
 * Send confirmation email to attendee and admin
 */
function erp_send_confirmation_email($event_id, $attendee_data, $is_paid = false, $payment_status = 'confirmed') {
    $event = get_post($event_id);
    if (!$event) {
        error_log('Event not found for email: ' . $event_id);
        return false;
    }
    
    // Get event details
    $event_date = get_post_meta($event_id, '_event_date', true);
    $event_time = get_post_meta($event_id, '_event_time', true);
    $event_location = get_post_meta($event_id, '_event_location', true);
    $entrance_fee = get_post_meta($event_id, '_entrance_fee', true);
    
    // Format date
    $formatted_date = date('l, F j, Y', strtotime($event_date));
    
    // Prepare email content
    $to = $attendee_data['email'];
    $subject = 'Registration Confirmation - ' . $event->post_title;
    
    // Determine status message
    if ($is_paid) {
        if ($payment_status === 'paid') {
            $status_message = '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;"><strong>✓ Payment Confirmed</strong><br>Your payment has been successfully processed.</div>';
        } else {
            $status_message = '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;"><strong>⏳ Payment Pending</strong><br>Your registration is confirmed once payment is completed.</div>';
        }
    } else {
        $status_message = '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;"><strong>✓ Registration Confirmed</strong><br>You have been successfully registered for this free event.</div>';
    }
    
    // Build HTML email
    $message = '
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #2271b1; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">Event Registration Confirmation</h1>
        </div>
        
        <div style="background-color: #f8f9fa; padding: 30px; border: 1px solid #ddd; border-top: none;">
            ' . $status_message . '
            
            <h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Event Details</h2>
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; width: 30%;">Event:</td>
                    <td style="padding: 8px 0;">' . esc_html($event->post_title) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Date:</td>
                    <td style="padding: 8px 0;">' . esc_html($formatted_date) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Time:</td>
                    <td style="padding: 8px 0;">' . esc_html($event_time ? $event_time : 'TBA') . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Location:</td>
                    <td style="padding: 8px 0;">' . esc_html($event_location ? $event_location : 'TBA') . '</td>
                </tr>
                ' . ($is_paid && $entrance_fee ? '<tr><td style="padding: 8px 0; font-weight: bold;">Fee:</td><td style="padding: 8px 0;">Rp ' . number_format($entrance_fee, 0, ',', '.') . '</td></tr>' : '') . '
            </table>
            
            <h2 style="color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">Attendee Information</h2>
            <table style="width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold; width: 30%;">Name:</td>
                    <td style="padding: 8px 0;">' . esc_html($attendee_data['name']) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Email:</td>
                    <td style="padding: 8px 0;">' . esc_html($attendee_data['email']) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Phone:</td>
                    <td style="padding: 8px 0;">' . esc_html($attendee_data['phone'] ? $attendee_data['phone'] : '-') . '</td>
                </tr>
                ' . (isset($attendee_data['job']) && $attendee_data['job'] ? '<tr><td style="padding: 8px 0; font-weight: bold;">Job/Position:</td><td style="padding: 8px 0;">' . esc_html($attendee_data['job']) . '</td></tr>' : '') . '
            </table>
            
            <div style="background-color: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-top: 20px; border-radius: 4px;">
                <p style="margin: 0;"><strong>Questions or need assistance?</strong></p>
                <p style="margin: 5px 0 0 0;">Contact us at: <a href="mailto:' . ERP_ADMIN_EMAIL . '" style="color: #2271b1;">' . ERP_ADMIN_EMAIL . '</a></p>
            </div>
        </div>
        
        <div style="background-color: #2271b1; color: white; padding: 15px; border-radius: 0 0 5px 5px; text-align: center; font-size: 12px;">
            <p style="margin: 0;">© ' . date('Y') . ' LPPM USG. All rights reserved.</p>
        </div>
    </body>
    </html>
    ';
    
    // Set headers for HTML email
    $headers = array(
        'Content-Type: text/html; charset=UTF-8'
    );
    
    // Send email
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        error_log('Confirmation email sent to: ' . $to);
    } else {
        error_log('Failed to send email to: ' . $to);
    }
    
    return $sent;
}

/**
 * Add Event Meta Boxes
 */
function erp_add_event_meta_boxes() {
    add_meta_box(
        'event_details',
        __('Event Details', 'event-registration'),
        'erp_event_details_callback',
        'event',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'erp_add_event_meta_boxes');

/**
 * Event Details Meta Box Callback
 */
function erp_event_details_callback($post) {
    wp_nonce_field('erp_save_event_details', 'erp_event_details_nonce');
    
    $event_date = get_post_meta($post->ID, '_event_date', true);
    $event_time = get_post_meta($post->ID, '_event_time', true);
    $event_location = get_post_meta($post->ID, '_event_location', true);
    $event_capacity = get_post_meta($post->ID, '_event_capacity', true);
    $entrance_fee = get_post_meta($post->ID, '_entrance_fee', true);
    $registration_deadline = get_post_meta($post->ID, '_registration_deadline', true);
    $registration_deadline_time = get_post_meta($post->ID, '_registration_deadline_time', true);
    $event_zoom_ticket_link = get_post_meta($post->ID, '_event_zoom_ticket_link', true);
    ?>
    <style>
        .erp-event-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: -6px -12px -12px;
        }
        .erp-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2271b1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .erp-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .erp-section-title .dashicons {
            color: #2271b1;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .erp-field-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .erp-field-row:last-child {
            margin-bottom: 0;
        }
        .erp-field-group {
            display: flex;
            flex-direction: column;
        }
        .erp-field-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #1d2327;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .erp-field-group label .required {
            color: #dc3545;
        }
        .erp-field-group label .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #646970;
        }
        .erp-field-group input[type="date"],
        .erp-field-group input[type="time"],
        .erp-field-group input[type="text"],
        .erp-field-group input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 2px solid #ddd;
            border-radius: 6px;
            transition: all 0.2s;
            background: #fff;
        }
        .erp-field-group input:hover {
            border-color: #8c8f94;
        }
        .erp-field-group input:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        }
        .erp-field-group small {
            margin-top: 6px;
            color: #646970;
            font-size: 13px;
        }
        .erp-help-text {
            background: #e7f3ff;
            border-left: 3px solid #2271b1;
            padding: 12px 15px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .erp-help-text p {
            margin: 0;
            color: #1d2327;
            font-size: 13px;
        }
        .erp-help-text .dashicons {
            color: #2271b1;
            margin-right: 5px;
        }
    </style>
    <div class="erp-event-details">
        
        <!-- Event Schedule Section -->
        <div class="erp-section">
            <div class="erp-section-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php _e('Event Schedule', 'event-registration'); ?>
            </div>
            
            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="event_date">
                        <span class="dashicons dashicons-calendar"></span>
                        <?php _e('Event Date', 'event-registration'); ?> 
                        <span class="required">*</span>
                    </label>
                    <input 
                        type="date" 
                        id="event_date" 
                        name="event_date" 
                        value="<?php echo esc_attr($event_date); ?>" 
                        required
                    />
                    <small><?php _e('When will the event take place?', 'event-registration'); ?></small>
                </div>

                <div class="erp-field-group">
                    <label for="event_time">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Start Time', 'event-registration'); ?>
                    </label>
                    <input 
                        type="time" 
                        id="event_time" 
                        name="event_time" 
                        value="<?php echo esc_attr($event_time); ?>" 
                    />
                    <small><?php _e('What time does it start?', 'event-registration'); ?></small>
                </div>
            </div>
            
            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="registration_deadline">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Registration Deadline', 'event-registration'); ?>
                    </label>
                    <input 
                        type="date" 
                        id="registration_deadline" 
                        name="registration_deadline" 
                        value="<?php echo esc_attr($registration_deadline); ?>" 
                    />
                    <small><?php _e('Last date for registration (optional)', 'event-registration'); ?></small>
                </div>
                <div class="erp-field-group">
                    <label for="registration_deadline_time">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Deadline Time', 'event-registration'); ?>
                    </label>
                    <input 
                        type="time" 
                        id="registration_deadline_time" 
                        name="registration_deadline_time" 
                        value="<?php echo esc_attr($registration_deadline_time); ?>" 
                    />
                    <small><?php _e('Time on deadline date (optional)', 'event-registration'); ?></small>
                </div>
            </div>

            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="event_zoom_ticket_link">
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php _e('Zoom Link (for Webinars)', 'event-registration'); ?>
                    </label>
                    <input 
                        type="url" 
                        id="event_zoom_ticket_link" 
                        name="event_zoom_ticket_link" 
                        value="<?php echo esc_attr($event_zoom_ticket_link); ?>" 
                        placeholder="https://zoom.us/j/123456789"
                    />
                    <small><?php _e('Zoom meeting link will be included in invitation emails (optional)', 'event-registration'); ?></small>
                </div>
            </div>
            
            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="event_ticket_info">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <?php _e('Event Ticket/Access Information', 'event-registration'); ?>
                    </label>
                    <textarea 
                        id="event_ticket_info" 
                        name="event_ticket_info" 
                        rows="3"
                        placeholder="<?php _e('e.g., Entry requirements, dress code, parking info, etc.', 'event-registration'); ?>"
                        style="width: 100%; padding: 10px 12px; font-size: 14px; border: 2px solid #ddd; border-radius: 6px;"
                    ><?php echo esc_textarea(get_post_meta($post->ID, '_event_ticket_info', true)); ?></textarea>
                    <small><?php _e('Additional information for attendees (will be included in invitation)', 'event-registration'); ?></small>
                </div>
            </div>
        </div>

        <!-- Event Location Section -->
        <div class="erp-section">
            <div class="erp-section-title">
                <span class="dashicons dashicons-location"></span>
                <?php _e('Location Details', 'event-registration'); ?>
            </div>
            
            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="event_location">
                        <span class="dashicons dashicons-admin-site"></span>
                        <?php _e('Venue', 'event-registration'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="event_location" 
                        name="event_location" 
                        value="<?php echo esc_attr($event_location); ?>" 
                        placeholder="<?php _e('e.g., Grand Hall, Jakarta Convention Center', 'event-registration'); ?>" 
                    />
                    <small><?php _e('Where is the event being held?', 'event-registration'); ?></small>
                </div>
            </div>
        </div>

        <!-- Registration Settings Section -->
        <div class="erp-section">
            <div class="erp-section-title">
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Registration Settings', 'event-registration'); ?>
            </div>
            
            <div class="erp-field-row">
                <div class="erp-field-group">
                    <label for="event_capacity">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php _e('Maximum Capacity', 'event-registration'); ?>
                    </label>
                    <input 
                        type="number" 
                        id="event_capacity" 
                        name="event_capacity" 
                        value="<?php echo esc_attr($event_capacity); ?>" 
                        min="1"
                        placeholder="<?php _e('e.g., 100', 'event-registration'); ?>" 
                    />
                    <small><?php _e('How many people can attend? (leave empty for unlimited)', 'event-registration'); ?></small>
                </div>

                <div class="erp-field-group">
                    <label for="entrance_fee">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php _e('Entrance Fee', 'event-registration'); ?>
                    </label>
                    <input 
                        type="number" 
                        id="entrance_fee" 
                        name="entrance_fee" 
                        value="<?php echo esc_attr($entrance_fee); ?>" 
                        min="0"
                        step="1"
                        placeholder="<?php _e('e.g., 50000', 'event-registration'); ?>" 
                    />
                    <small><?php _e('Price in IDR (leave empty or 0 for free event)', 'event-registration'); ?></small>
                </div>
            </div>
        </div>

        <!-- Help Text -->
        <div class="erp-help-text">
            <p>
                <span class="dashicons dashicons-info"></span>
                <strong><?php _e('Tip:', 'event-registration'); ?></strong> 
                <?php _e('After publishing, visitors can register through the registration button that appears on the event page.', 'event-registration'); ?>
            </p>
        </div>
        
    </div>
    <?php
}

/**
 * Save Event Meta Data
 */
function erp_save_event_details($post_id) {
    // Check nonce
    if (!isset($_POST['erp_event_details_nonce']) || !wp_verify_nonce($_POST['erp_event_details_nonce'], 'erp_save_event_details')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save event date
    if (isset($_POST['event_date'])) {
        update_post_meta($post_id, '_event_date', sanitize_text_field($_POST['event_date']));
    }

    // Save event time
    if (isset($_POST['event_time'])) {
        update_post_meta($post_id, '_event_time', sanitize_text_field($_POST['event_time']));
    }
    
    // Save registration deadline
    if (isset($_POST['registration_deadline'])) {
        update_post_meta($post_id, '_registration_deadline', sanitize_text_field($_POST['registration_deadline']));
    }
    // Save registration deadline time
    if (isset($_POST['registration_deadline_time'])) {
        update_post_meta($post_id, '_registration_deadline_time', sanitize_text_field($_POST['registration_deadline_time']));
    }
    // Save Zoom/ticket link
    if (isset($_POST['event_zoom_ticket_link'])) {
        update_post_meta($post_id, '_event_zoom_ticket_link', esc_url_raw($_POST['event_zoom_ticket_link']));
    }
    
    // Save ticket info
    if (isset($_POST['event_ticket_info'])) {
        update_post_meta($post_id, '_event_ticket_info', sanitize_textarea_field($_POST['event_ticket_info']));
    }

    // Save event location
    if (isset($_POST['event_location'])) {
        update_post_meta($post_id, '_event_location', sanitize_text_field($_POST['event_location']));
    }

    // Save event capacity
    if (isset($_POST['event_capacity'])) {
        update_post_meta($post_id, '_event_capacity', absint($_POST['event_capacity']));
    }

    // Save entrance fee
    if (isset($_POST['entrance_fee'])) {
        update_post_meta($post_id, '_entrance_fee', sanitize_text_field($_POST['entrance_fee']));
    }
    
    // Regenerate certificates for this event if title or date changed
    if (isset($_POST['event_date']) || isset($_POST['post_title'])) {
        erp_regenerate_event_certificates($post_id);
    }
}
add_action('save_post_event', 'erp_save_event_details');

/**
 * Regenerate all certificates for an event
 */
function erp_regenerate_event_certificates($event_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Get all registrations with certificates for this event
    $registrations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE event_id = %d AND certificate_url IS NOT NULL AND certificate_url != ''",
        $event_id
    ));
    
    foreach ($registrations as $registration) {
        // Delete old certificate files
        $pattern = WP_CONTENT_DIR . '/uploads/certificates/certificate-' . $registration->id . '-*.html';
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Generate new certificate with updated event data
        erp_auto_generate_certificate($registration);
    }
}

/**
 * Add custom columns to Events list
 */
function erp_event_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['event_date'] = __('Event Date', 'event-registration');
            $new_columns['event_location'] = __('Location', 'event-registration');
        }
    }
    
    return $new_columns;
}
add_filter('manage_event_posts_columns', 'erp_event_columns');

/**
 * Display custom column content
 */
function erp_event_column_content($column, $post_id) {
    if ($column === 'event_date') {
        $event_date = get_post_meta($post_id, '_event_date', true);
        $event_time = get_post_meta($post_id, '_event_time', true);
        
        if ($event_date) {
            echo esc_html(date_i18n('F j, Y', strtotime($event_date)));
            if ($event_time) {
                echo '<br><small>' . esc_html($event_time) . '</small>';
            }
        } else {
            echo '—';
        }
    }
    
    if ($column === 'event_location') {
        $location = get_post_meta($post_id, '_event_location', true);
        echo $location ? esc_html($location) : '—';
    }
}
add_action('manage_event_posts_custom_column', 'erp_event_column_content', 10, 2);

/**
 * Make event columns sortable
 */
function erp_event_sortable_columns($columns) {
    $columns['event_date'] = 'event_date';
    return $columns;
}
add_filter('manage_edit-event_sortable_columns', 'erp_event_sortable_columns');

/**
 * Display event details on single event page
 */
function erp_display_event_details($content) {
    // Only show on actual single event pages, not in menus, excerpts, or widgets
    if (!is_singular('event') || !is_main_query() || !in_the_loop()) {
        return $content;
    }
    
    global $post;
    
    $event_date = get_post_meta($post->ID, '_event_date', true);
    $event_time = get_post_meta($post->ID, '_event_time', true);
    $event_location = get_post_meta($post->ID, '_event_location', true);
    $event_capacity = get_post_meta($post->ID, '_event_capacity', true);
    $entrance_fee = get_post_meta($post->ID, '_entrance_fee', true);
    $registration_deadline = get_post_meta($post->ID, '_registration_deadline', true);
    
    // Check if deadline has passed
    $is_deadline_passed = false;
    if ($registration_deadline) {
        $deadline_timestamp = strtotime($registration_deadline . ' 23:59:59');
        $current_timestamp = current_time('timestamp');
        $is_deadline_passed = ($current_timestamp > $deadline_timestamp);
    }
    
    // Build event details HTML
    $details = '<div class="event-details-box" style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #2271b1;">';
    $details .= '<h3 style="margin-top: 0; color: #1d2327; font-size: 24px; margin-bottom: 20px;">📅 Event Details</h3>';
    $details .= '<div class="event-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
    
    // Date
    if ($event_date) {
        $formatted_date = date_i18n('l, F j, Y', strtotime($event_date));
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">📆 DATE</div>';
        $details .= '<div style="font-size: 16px; color: #1d2327;">' . esc_html($formatted_date) . '</div>';
        $details .= '</div>';
    }
    
    // Time
    if ($event_time) {
        $formatted_time = date_i18n('g:i A', strtotime($event_time));
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">⏰ TIME</div>';
        $details .= '<div style="font-size: 16px; color: #1d2327;">' . esc_html($formatted_time) . '</div>';
        $details .= '</div>';
    }
    
    // Location
    if ($event_location) {
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">📍 LOCATION</div>';
        $details .= '<div style="font-size: 16px; color: #1d2327;">' . esc_html($event_location) . '</div>';
        $details .= '</div>';
    }
    
    // Capacity
    if ($event_capacity) {
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">👥 CAPACITY</div>';
        $details .= '<div style="font-size: 16px; color: #1d2327;">' . esc_html(number_format($event_capacity)) . ' attendees</div>';
        $details .= '</div>';
    }
    
    // Entrance Fee
    if ($entrance_fee && $entrance_fee > 0) {
        $formatted_fee = 'Rp ' . number_format($entrance_fee, 0, ',', '.');
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">💰 ENTRANCE FEE</div>';
        $details .= '<div style="font-size: 16px; color: #1d2327; font-weight: 600;">' . esc_html($formatted_fee) . '</div>';
        $details .= '</div>';
    } else {
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">💰 ENTRANCE FEE</div>';
        $details .= '<div style="font-size: 16px; color: #28a745; font-weight: 600;">FREE</div>';
        $details .= '</div>';
    }
    
    // Registration Deadline
    if ($registration_deadline) {
        $formatted_deadline = date_i18n('F j, Y', strtotime($registration_deadline));
        $details .= '<div class="event-info-item">';
        $details .= '<div style="font-weight: 600; color: #646970; margin-bottom: 5px; font-size: 14px;">⏰ REGISTRATION DEADLINE</div>';
        if ($is_deadline_passed) {
            $details .= '<div style="font-size: 16px; color: #dc3545; font-weight: 600;">CLOSED (' . esc_html($formatted_deadline) . ')</div>';
        } else {
            $details .= '<div style="font-size: 16px; color: #1d2327;">' . esc_html($formatted_deadline) . '</div>';
        }
        $details .= '</div>';
    }
    
    $details .= '</div>'; // Close event-info
    
    // Add registration button as a link
    $registration_url = home_url('/event-registration/?event_id=' . $post->ID);
    $details .= '<div style="margin-top: 30px; text-align: center;">';
    
    if ($is_deadline_passed) {
        // Show button with warning styling but still clickable
        $details .= '<a href="' . esc_url($registration_url) . '" style="display: inline-block; background: #dc3545; color: white; text-decoration: none; padding: 15px 40px; font-size: 18px; font-weight: 600; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: all 0.3s ease;" onmouseover="this.style.background=\'#bb2d3b\';" onmouseout="this.style.background=\'#dc3545\';">⏰ Registration Closed</a>';
        $details .= '<p style="margin-top: 10px; color: #dc3545; font-size: 14px;">Deadline has passed - Click to see details</p>';
    } else {
        // Show active button
        $details .= '<a href="' . esc_url($registration_url) . '" style="display: inline-block; background: #2271b1; color: white; text-decoration: none; padding: 15px 40px; font-size: 18px; font-weight: 600; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: all 0.3s ease;" onmouseover="this.style.background=\'#135e96\';" onmouseout="this.style.background=\'#2271b1\';">🎫 Register for this Event</a>';
    }
    
    $details .= '</div>';
    
    $details .= '</div>'; // Close event-details-box
    
    // Add details before content
    return $details . $content;
}
add_filter('the_content', 'erp_display_event_details');

/**
 * Add registration page rewrite rule
 */
function erp_add_registration_rewrite() {
    add_rewrite_rule('^event-registration/?', 'index.php?erp_registration=1', 'top');
    add_rewrite_rule('^event-payment/?', 'index.php?erp_payment=1', 'top');
}
add_action('init', 'erp_add_registration_rewrite');

/**
 * Add query vars
 */
function erp_query_vars($vars) {
    $vars[] = 'erp_registration';
    $vars[] = 'erp_payment';
    return $vars;
}
add_filter('query_vars', 'erp_query_vars');

/**
 * Handle registration page template
 */
function erp_registration_template($template) {
    if (get_query_var('erp_registration')) {
        return ERP_PLUGIN_DIR . 'registration-page.php';
    }
    if (get_query_var('erp_payment')) {
        return ERP_PLUGIN_DIR . 'payment-page.php';
    }
    return $template;
}
add_filter('template_include', 'erp_registration_template');



/**
 * Handle event registration submission via AJAX
 */
function erp_handle_registration() {
    error_log('erp_handle_registration called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!isset($_POST['erp_registration_nonce']) || !wp_verify_nonce($_POST['erp_registration_nonce'], 'erp_register_event')) {
        error_log('Nonce verification failed');
        wp_send_json_error(['message' => 'Security check failed. Please refresh and try again.']);
    }
    
    $event_id = intval($_POST['event_id']);
    $ticket_quantity = intval($_POST['ticket_quantity']);
    
    // Check registration deadline
    $registration_deadline = get_post_meta($event_id, '_registration_deadline', true);
    if ($registration_deadline) {
        $deadline_timestamp = strtotime($registration_deadline . ' 23:59:59');
        $current_timestamp = current_time('timestamp');
        if ($current_timestamp > $deadline_timestamp) {
            error_log("Registration deadline passed for event $event_id");
            wp_send_json_error(['message' => 'Sorry, the registration deadline for this event has passed.']);
        }
    }
    
    // Collect all attendees
    $attendees = array();
    
    // Primary attendee
    $attendees[] = array(
        'name' => sanitize_text_field($_POST['attendee_name']),
        'email' => sanitize_email($_POST['attendee_email']),
        'phone' => sanitize_text_field($_POST['attendee_phone']),
        'job' => sanitize_text_field($_POST['attendee_job'])
    );
    
    // Additional attendees (if ticket_quantity > 1)
    for ($i = 2; $i <= $ticket_quantity; $i++) {
        if (isset($_POST["attendee_name_$i"])) {
            $attendees[] = array(
                'name' => sanitize_text_field($_POST["attendee_name_$i"]),
                'email' => sanitize_email($_POST["attendee_email_$i"]),
                'phone' => sanitize_text_field($_POST["attendee_phone_$i"]),
                'job' => sanitize_text_field($_POST["attendee_job_$i"])
            );
        }
    }
    
    error_log("Free Event Registration - Event ID: $event_id, Total attendees: " . count($attendees));
    
    // Validate all attendees
    foreach ($attendees as $index => $attendee) {
        if (empty($attendee['name']) || empty($attendee['email']) || empty($attendee['job'])) {
            wp_send_json_error(['message' => 'Please fill in all required fields for attendee #' . ($index + 1)]);
        }
        if (!is_email($attendee['email'])) {
            wp_send_json_error(['message' => 'Please enter a valid email address for attendee #' . ($index + 1)]);
        }
    }
    
    // Check capacity before registering
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $event_capacity = get_post_meta($event_id, '_event_capacity', true);
    
    if ($event_capacity && $event_capacity > 0) {
        $registered_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ticket_quantity), 0) FROM $table_name 
             WHERE event_id = %d AND status IN ('confirmed', 'pending')",
            $event_id
        ));
        
        $remaining_spots = $event_capacity - $registered_count;
        
        if ($remaining_spots <= 0) {
            error_log("Event $event_id is full. Capacity: $event_capacity, Registered: $registered_count");
            wp_send_json_error(['message' => 'Sorry, this event is now full. Registration closed.']);
        }
        
        if ($ticket_quantity > $remaining_spots) {
            error_log("Ticket quantity ($ticket_quantity) exceeds remaining spots ($remaining_spots)");
            wp_send_json_error(['message' => "Only $remaining_spots ticket(s) remaining. Please reduce your quantity."]);
        }
    }
    
    error_log("Inserting free registrations into table: $table_name");
    
    // Insert each attendee as a separate row with ticket_quantity = 1
    $inserted_count = 0;
    foreach ($attendees as $attendee) {
        $inserted = $wpdb->insert(
            $table_name,
            [
                'event_id' => $event_id,
                'attendee_name' => $attendee['name'],
                'attendee_email' => $attendee['email'],
                'attendee_phone' => $attendee['phone'],
                'attendee_job' => $attendee['job'],
                'ticket_quantity' => 1, // Each row represents 1 ticket
                'registration_date' => current_time('mysql'),
                'status' => 'confirmed',
                'payment_status' => 'free'
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if ($inserted) {
            $inserted_count++;
            $registration_id = $wpdb->insert_id;
            error_log('Free registration inserted for: ' . $attendee['name'] . ', ID: ' . $registration_id);
            
            // Send confirmation email automatically
            $email_sent = erp_send_confirmation_email($event_id, $attendee, false, 'confirmed');
            
            // Update notification_sent timestamp if email was sent successfully
            if ($email_sent) {
                $wpdb->update(
                    $table_name,
                    array('notification_sent' => current_time('mysql')),
                    array('id' => $registration_id),
                    array('%s'),
                    array('%d')
                );
                error_log('Notification email sent and timestamp updated for registration ID: ' . $registration_id);
            } else {
                error_log('Failed to send notification email for registration ID: ' . $registration_id);
            }
        } else {
            error_log('Database insert failed for ' . $attendee['name'] . '. Error: ' . $wpdb->last_error);
        }
    }
    
    if ($inserted_count == count($attendees)) {
        wp_send_json_success([
            'message' => 'Registration successful! ' . $inserted_count . ' ticket(s) registered. You will receive confirmation emails shortly.'
        ]);
    } else {
        wp_send_json_error(['message' => 'Some registrations failed. Please contact support.']);
    }
}
add_action('wp_ajax_erp_submit_registration', 'erp_handle_registration');
add_action('wp_ajax_nopriv_erp_submit_registration', 'erp_handle_registration');

/**
 * Handle payment processing and save registration to database
 */
function erp_process_payment() {
    error_log('erp_process_payment called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!isset($_POST['erp_payment_nonce']) || !wp_verify_nonce($_POST['erp_payment_nonce'], 'erp_process_payment')) {
        error_log('Payment nonce verification failed');
        wp_send_json_error(['message' => 'Security check failed. Please try again.']);
    }
    
    $event_id = intval($_POST['event_id']);
    $ticket_quantity = intval($_POST['ticket_quantity']);
    $total_amount = floatval($_POST['total_amount']);
    
    // Check registration deadline
    $registration_deadline = get_post_meta($event_id, '_registration_deadline', true);
    if ($registration_deadline) {
        $deadline_timestamp = strtotime($registration_deadline . ' 23:59:59');
        $current_timestamp = current_time('timestamp');
        if ($current_timestamp > $deadline_timestamp) {
            error_log("Registration deadline passed for event $event_id");
            wp_send_json_error(['message' => 'Sorry, the registration deadline for this event has passed.']);
        }
    }
    
    // Get payment result from Midtrans
    $payment_result = isset($_POST['payment_result']) ? json_decode(stripslashes($_POST['payment_result']), true) : null;
    
    // Collect all attendees
    $attendees = array();
    
    // Primary attendee
    $attendees[] = array(
        'name' => sanitize_text_field($_POST['attendee_name']),
        'email' => sanitize_email($_POST['attendee_email']),
        'phone' => sanitize_text_field($_POST['attendee_phone']),
        'job' => sanitize_text_field($_POST['attendee_job'])
    );
    
    // Additional attendees (if ticket_quantity > 1)
    for ($i = 2; $i <= $ticket_quantity; $i++) {
        if (isset($_POST["attendee_name_$i"])) {
            $attendees[] = array(
                'name' => sanitize_text_field($_POST["attendee_name_$i"]),
                'email' => sanitize_email($_POST["attendee_email_$i"]),
                'phone' => sanitize_text_field($_POST["attendee_phone_$i"]),
                'job' => sanitize_text_field($_POST["attendee_job_$i"])
            );
        }
    }
    
    error_log("Payment - Event ID: $event_id, Total attendees: " . count($attendees) . ", Amount: $total_amount");
    error_log("Midtrans payment result: " . print_r($payment_result, true));
    
    // Validate all attendees
    foreach ($attendees as $index => $attendee) {
        if (empty($attendee['name']) || empty($attendee['email']) || empty($attendee['job'])) {
            wp_send_json_error(['message' => 'Please fill in all required fields for attendee #' . ($index + 1)]);
        }
        if (!is_email($attendee['email'])) {
            wp_send_json_error(['message' => 'Please enter a valid email address for attendee #' . ($index + 1)]);
        }
    }
    
    // Check capacity before processing payment
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $event_capacity = get_post_meta($event_id, '_event_capacity', true);
    
    if ($event_capacity && $event_capacity > 0) {
        $registered_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ticket_quantity), 0) FROM $table_name 
             WHERE event_id = %d AND status IN ('confirmed', 'pending')",
            $event_id
        ));
        
        $remaining_spots = $event_capacity - $registered_count;
        
        if ($remaining_spots <= 0) {
            error_log("Event $event_id is full. Capacity: $event_capacity, Registered: $registered_count");
            wp_send_json_error(['message' => 'Sorry, this event is now full. Registration closed.']);
        }
        
        if ($ticket_quantity > $remaining_spots) {
            error_log("Ticket quantity ($ticket_quantity) exceeds remaining spots ($remaining_spots)");
            wp_send_json_error(['message' => "Only $remaining_spots ticket(s) remaining. Please reduce your quantity."]);
        }
    }
    
    // Determine payment status from Midtrans result
    $payment_status = 'pending';
    if ($payment_result) {
        if (isset($payment_result['transaction_status'])) {
            switch ($payment_result['transaction_status']) {
                case 'capture':
                case 'settlement':
                    $payment_status = 'paid';
                    break;
                case 'pending':
                    $payment_status = 'pending';
                    break;
                case 'deny':
                case 'cancel':
                case 'expire':
                    $payment_status = 'failed';
                    break;
            }
        }
    }
    
    error_log("Inserting payment registrations into table: $table_name");
    
    // Insert each attendee as a separate row with ticket_quantity = 1
    $inserted_count = 0;
    foreach ($attendees as $attendee) {
        $inserted = $wpdb->insert(
            $table_name,
            [
                'event_id' => $event_id,
                'attendee_name' => $attendee['name'],
                'attendee_email' => $attendee['email'],
                'attendee_phone' => $attendee['phone'],
                'attendee_job' => $attendee['job'],
                'ticket_quantity' => 1, // Each row represents 1 ticket
                'registration_date' => current_time('mysql'),
                'status' => ($payment_status === 'paid') ? 'confirmed' : 'pending',
                'payment_status' => $payment_status
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if ($inserted) {
            $inserted_count++;
            $registration_id = $wpdb->insert_id;
            error_log('Paid registration inserted for: ' . $attendee['name'] . ', ID: ' . $registration_id);
            
            // Send confirmation email automatically
            $email_sent = erp_send_confirmation_email($event_id, $attendee, true, $payment_status);
            
            // Update notification_sent timestamp if email was sent successfully
            if ($email_sent) {
                $wpdb->update(
                    $table_name,
                    array('notification_sent' => current_time('mysql')),
                    array('id' => $registration_id),
                    array('%s'),
                    array('%d')
                );
                error_log('Notification email sent and timestamp updated for registration ID: ' . $registration_id);
            } else {
                error_log('Failed to send notification email for registration ID: ' . $registration_id);
            }
        } else {
            error_log('Database insert failed for ' . $attendee['name'] . '. Error: ' . $wpdb->last_error);
        }
    }
    
    if ($inserted_count == count($attendees)) {
        wp_send_json_success([
            'message' => 'Payment confirmed! ' . $inserted_count . ' ticket(s) registered. You will receive confirmation emails shortly.'
        ]);
    } else {
        wp_send_json_error(['message' => 'Some registrations failed. Please contact support.']);
    }
}
add_action('wp_ajax_erp_process_payment', 'erp_process_payment');
add_action('wp_ajax_nopriv_erp_process_payment', 'erp_process_payment');

/**
 * Get Midtrans Snap Token for payment
 */
function erp_get_snap_token() {
    error_log('erp_get_snap_token called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Ensure Midtrans constants are initialized
    erp_ensure_midtrans_constants();
    
    // Verify nonce
    if (!isset($_POST['erp_payment_nonce']) || !wp_verify_nonce($_POST['erp_payment_nonce'], 'erp_process_payment')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Check if Midtrans credentials are configured
    if (MIDTRANS_SERVER_KEY === 'YOUR_MIDTRANS_SERVER_KEY' || MIDTRANS_CLIENT_KEY === 'YOUR_MIDTRANS_CLIENT_KEY') {
        error_log('Midtrans credentials not configured');
        wp_send_json_error(['message' => 'Midtrans credentials not configured. Please visit /configure-midtrans.php']);
    }
    
    $event_id = intval($_POST['event_id']);
    $attendee_name = sanitize_text_field($_POST['attendee_name']);
    $attendee_email = sanitize_email($_POST['attendee_email']);
    $ticket_quantity = intval($_POST['ticket_quantity']);
    $total_amount = floatval($_POST['total_amount']);
    
    error_log("Snap Token Request - Event: $event_id, Amount: $total_amount, Server Key: " . substr(MIDTRANS_SERVER_KEY, 0, 10) . '...');
    
    $event = get_post($event_id);
    
    // Prepare transaction data for Midtrans
    $transaction_details = array(
        'order_id' => 'EVENT-' . $event_id . '-' . time(),
        'gross_amount' => (int)$total_amount, // Must be integer
    );
    
    // Truncate event title to meet Midtrans 50-character limit
    $event_title = $event->post_title;
    if (strlen($event_title) > 50) {
        $event_title = substr($event_title, 0, 47);
        // Truncate at last word boundary to avoid cutting words
        $last_space = strrpos($event_title, ' ');
        if ($last_space !== false && $last_space > 20) {
            $event_title = substr($event_title, 0, $last_space);
        }
        $event_title .= '...';
    }
    
    $item_details = array(
        array(
            'id' => 'EVENT-' . $event_id,
            'price' => (int)($total_amount / $ticket_quantity),
            'quantity' => $ticket_quantity,
            'name' => $event_title
        )
    );
    
    $customer_details = array(
        'first_name' => $attendee_name,
        'email' => $attendee_email,
        'phone' => sanitize_text_field($_POST['attendee_phone'])
    );
    
    // Set callback URLs for payment redirect
    $site_url = home_url();
    $callbacks = array(
        'finish' => add_query_arg(array('payment_status' => 'success', 'order_id' => $transaction_details['order_id']), $site_url),
        'error' => add_query_arg(array('payment_status' => 'error', 'order_id' => $transaction_details['order_id']), $site_url),
        'pending' => add_query_arg(array('payment_status' => 'pending', 'order_id' => $transaction_details['order_id']), $site_url)
    );
    
    $transaction = array(
        'transaction_details' => $transaction_details,
        'item_details' => $item_details,
        'customer_details' => $customer_details,
        'callbacks' => $callbacks
    );
    
    error_log('Transaction data: ' . json_encode($transaction));
    
    // Call Midtrans Snap API
    $snap_url = MIDTRANS_IS_PRODUCTION 
        ? 'https://app.midtrans.com/snap/v1/transactions' 
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    
    error_log('Calling Midtrans API: ' . $snap_url);
    
    $response = wp_remote_post($snap_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
        ),
        'body' => json_encode($transaction),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        error_log('Midtrans API error: ' . $error_msg);
        wp_send_json_error(['message' => 'Failed to connect to payment gateway: ' . $error_msg]);
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    error_log('Midtrans response code: ' . $http_code);
    error_log('Midtrans response body: ' . print_r($body, true));
    
    if (isset($body['token'])) {
        error_log('Snap token generated successfully: ' . $body['token']);
        wp_send_json_success(array(
            'snap_token' => $body['token'],
            'order_id' => $transaction_details['order_id']
        ));
    } else {
        // Return detailed error from Midtrans
        $error_message = 'Failed to initialize payment.';
        if (isset($body['error_messages']) && is_array($body['error_messages'])) {
            $error_message .= ' ' . implode(', ', $body['error_messages']);
        } elseif (isset($body['status_message'])) {
            $error_message .= ' ' . $body['status_message'];
        }
        
        error_log('Midtrans Snap token error: ' . $error_message);
        wp_send_json_error(['message' => $error_message, 'debug' => $body]);
    }
}
add_action('wp_ajax_erp_get_snap_token', 'erp_get_snap_token');
add_action('wp_ajax_nopriv_erp_get_snap_token', 'erp_get_snap_token');

/**
 * Create registrations table on plugin activation
 */
function erp_create_registrations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        attendee_name varchar(255) NOT NULL,
        attendee_email varchar(255) NOT NULL,
        attendee_phone varchar(50) DEFAULT NULL,
        attendee_job varchar(255) DEFAULT NULL,
        ticket_quantity int(11) DEFAULT 1,
        registration_date datetime NOT NULL,
        status varchar(50) DEFAULT 'pending',
        payment_status varchar(50) DEFAULT 'pending',
        notification_sent datetime DEFAULT NULL,
        certificate_sent datetime DEFAULT NULL,
        invitation_sent datetime DEFAULT NULL,
        attendance_status varchar(50) DEFAULT 'not_attended',
        attendance_time datetime DEFAULT NULL,
        check_in_method varchar(50) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id),
        KEY attendee_email (attendee_email)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Check and add missing columns to event_registrations table
 */
function erp_update_table_structure() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        erp_create_registrations_table();
        return;
    }
    
    // Get existing columns
    $columns = $wpdb->get_col("DESCRIBE $table_name");
    
    // Add missing columns
    if (!in_array('notification_sent', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN notification_sent datetime DEFAULT NULL");
        error_log("Added column: notification_sent");
    }
    
    if (!in_array('certificate_sent', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN certificate_sent datetime DEFAULT NULL");
        error_log("Added column: certificate_sent");
    }
    
    if (!in_array('invitation_sent', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN invitation_sent datetime DEFAULT NULL");
        error_log("Added column: invitation_sent");
    }
    
    // Add attendance tracking columns
    if (!in_array('attendance_status', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN attendance_status varchar(50) DEFAULT 'not_attended'");
        error_log("Added column: attendance_status");
    }
    
    if (!in_array('attendance_time', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN attendance_time datetime DEFAULT NULL");
        error_log("Added column: attendance_time");
    }
    
    if (!in_array('check_in_method', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN check_in_method varchar(50) DEFAULT NULL");
        error_log("Added column: check_in_method");
    }
}
add_action('admin_init', 'erp_update_table_structure');

/**
 * Plugin activation hook
 */
function erp_activation() {
    // Register post type
    erp_register_event_post_type();
    
    // Add custom rewrite rules
    erp_add_registration_rewrite();
    
    // Create registrations table
    erp_create_registrations_table();
    
    // Update table structure (add missing columns)
    erp_update_table_structure();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'erp_activation');

/**
 * Helper functions for managing favorite events
 */
function erp_get_user_favorite_events($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $favorites = get_user_meta($user_id, 'erp_favorite_events', true);
    return is_array($favorites) ? $favorites : array();
}

function erp_add_favorite_event($event_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $favorites = erp_get_user_favorite_events($user_id);
    if (!in_array($event_id, $favorites)) {
        $favorites[] = intval($event_id);
        update_user_meta($user_id, 'erp_favorite_events', $favorites);
    }
    return true;
}

function erp_remove_favorite_event($event_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $favorites = erp_get_user_favorite_events($user_id);
    $favorites = array_diff($favorites, array(intval($event_id)));
    update_user_meta($user_id, 'erp_favorite_events', array_values($favorites));
    return true;
}

function erp_is_favorite_event($event_id, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $favorites = erp_get_user_favorite_events($user_id);
    return in_array(intval($event_id), $favorites);
}

/**
 * AJAX handler for toggling favorite events
 */
function erp_ajax_toggle_favorite_event() {
    check_ajax_referer('erp_favorite_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in'));
    }
    
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $is_favorite = isset($_POST['is_favorite']) ? $_POST['is_favorite'] === 'true' : false;
    
    if ($event_id <= 0) {
        wp_send_json_error(array('message' => 'Invalid event ID'));
    }
    
    if ($is_favorite) {
        erp_add_favorite_event($event_id);
        wp_send_json_success(array('message' => 'Event added to favorites', 'is_favorite' => true));
    } else {
        erp_remove_favorite_event($event_id);
        wp_send_json_success(array('message' => 'Event removed from favorites', 'is_favorite' => false));
    }
}
add_action('wp_ajax_erp_toggle_favorite_event', 'erp_ajax_toggle_favorite_event');

/**
 * Add Registrations and Settings submenus to Events menu
 */
function erp_add_registrations_menu() {
    // All Registrations submenu - accessible by all authenticated users
    add_submenu_page(
        'edit.php?post_type=event',
        'All Event Registrations',
        '📋 All Registrations',
        'read',
        'event-registrations',
        'erp_registrations_page'
    );
    
    // My Events submenu - accessible by all authenticated users
    add_submenu_page(
        'edit.php?post_type=event',
        'My Events Registrations',
        '⭐ My Events',
        'read',
        'event-my-events',
        'erp_my_events_page'
    );
    
    // Export/Import submenu - accessible by all authenticated users
    add_submenu_page(
        'edit.php?post_type=event',
        'Export/Import Registrations',
        'Export/Import',
        'read',
        'event-export-import',
        'erp_export_import_page'
    );
    
    // Certificate Template Editor - admin only
    add_submenu_page(
        'edit.php?post_type=event',
        'Certificate Template',
        'Certificate Template',
        'manage_options',
        'event-certificate-template',
        'erp_certificate_template_page'
    );
    
    // Payment Settings moved to main Settings menu (admin only)
    add_options_page(
        'Event Payment Settings',
        'Event Payment',
        'manage_options',
        'event-payment-settings',
        'erp_settings_page'
    );
}
add_action('admin_menu', 'erp_add_registrations_menu');

/**
 * Certificate Template Editor Page
 */
function erp_certificate_template_page() {
    // Check user capabilities (admin only)
    if (!current_user_can('manage_options')) {
        wp_die(__('Maaf, Anda tak diizinkan mengakses laman ini.'));
    }
    
    // Handle template save
    if (isset($_POST['erp_save_template'])) {
        check_admin_referer('erp_certificate_template_nonce');
        
        // Save custom CSS/styles if provided
        if (isset($_POST['certificate_custom_css'])) {
            $custom_css = wp_unslash($_POST['certificate_custom_css']);
            // Remove any potentially harmful code but preserve CSS
            $custom_css = strip_tags($custom_css);
            update_option('erp_certificate_custom_css', $custom_css);
            
            // Also save to file for easy editing
            $css_file = plugin_dir_path(__FILE__) . 'templates/certificate.css';
            file_put_contents($css_file, $custom_css);
        }
        
        // Save custom HTML template if provided
        if (isset($_POST['certificate_custom_html'])) {
            $custom_html = wp_unslash($_POST['certificate_custom_html']);
            // Basic sanitization that preserves placeholders and structure
            update_option('erp_certificate_custom_html', $custom_html);
            
            // Also save to file for easy editing
            $html_file = plugin_dir_path(__FILE__) . 'templates/certificate.html';
            file_put_contents($html_file, $custom_html);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Certificate template saved successfully! (Saved to database and files)</p></div>';
    }
    
    // Handle reset to default
    if (isset($_POST['erp_reset_template'])) {
        check_admin_referer('erp_certificate_template_nonce');
        delete_option('erp_certificate_custom_css');
        delete_option('erp_certificate_custom_html');
        
        // Also reset template files to defaults
        $css_file = plugin_dir_path(__FILE__) . 'templates/certificate.css';
        $html_file = plugin_dir_path(__FILE__) . 'templates/certificate.html';
        
        file_put_contents($css_file, erp_get_certificate_default_css());
        file_put_contents($html_file, erp_get_default_certificate_body());
        
        echo '<div class="notice notice-success is-dismissible"><p>Certificate template reset to default! (Database and files restored)</p></div>';
    }
    
    // Get current custom CSS and HTML - if empty, load defaults for editing
    $custom_css = get_option('erp_certificate_custom_css', '');
    $custom_html = get_option('erp_certificate_custom_html', '');
    
    // If empty, pre-fill with code from template files
    if (empty($custom_css)) {
        $css_file = plugin_dir_path(__FILE__) . 'templates/certificate.css';
        if (file_exists($css_file)) {
            $custom_css = file_get_contents($css_file);
        } else {
            $custom_css = erp_get_certificate_default_css();
        }
    }
    if (empty($custom_html)) {
        $html_file = plugin_dir_path(__FILE__) . 'templates/certificate.html';
        if (file_exists($html_file)) {
            $custom_html = file_get_contents($html_file);
        } else {
            $custom_html = erp_get_default_certificate_body();
        }
    }
    
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-awards" style="font-size: 32px; width: 32px; height: 32px;"></span> Certificate Template Editor</h1>
        <p>Customize the certificate design by modifying the CSS styles and HTML structure. Changes will apply to all newly generated certificates.</p>
        
        <!-- Template Files Info -->
        <div style="background: #d7f0ff; border: 1px solid #2271b1; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #0073aa;">📁 Easy File Editing</h3>
            <p style="margin: 10px 0;"><strong>You can edit templates directly from these files:</strong></p>
            <ul style="margin: 10px 0 10px 20px; line-height: 1.8;">
                <li><strong>HTML Template:</strong> <code>wp-content/plugins/wp_event/templates/certificate.html</code></li>
                <li><strong>CSS Styles:</strong> <code>wp-content/plugins/wp_event/templates/certificate.css</code></li>
            </ul>
            <p style="color: #646970; font-size: 13px; margin: 10px 0 0 0;">
                💡 <strong>Tip:</strong> Edit these files directly with your code editor for faster development, or use the editor below.
            </p>
        </div>
        
        <!-- Preview Button -->
        <div style="margin: 20px 0;">
            <a href="<?php echo admin_url('admin-ajax.php?action=erp_preview_certificate'); ?>" target="_blank" class="button button-secondary" style="height: auto; padding: 10px 20px;">
                <span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-top: -2px;"></span>
                Preview Certificate
            </a>
            <span style="color: #646970; font-size: 13px; margin-left: 10px;">Opens in new tab with sample data</span>
        </div>
        
        <div style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin: 20px 0;">
            <h2>Current Certificate Layout</h2>
            <p><strong>Page Size:</strong> A4 Landscape (297mm × 210mm)</p>
            <p><strong>Template:</strong> Professional certificate with colorful geometric sidebar</p>
            
            <h3 style="margin-top: 20px;">Template Features:</h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Geometric sidebar with colorful shapes (circles, triangles, bars) in yellow, navy, red, and blue</li>
                <li>Cream/beige background (#FFF9E6)</li>
                <li>Company logo and name at top left</li>
                <li>"Best Award" red circle badge with black ribbons (top right)</li>
                <li>Large navy "Certificate" title with "OF ACHIEVEMENT" subtitle</li>
                <li>Coral/pink recipient name (#E88B8B)</li>
                <li>Lorem ipsum description area</li>
                <li>Footer with date, handwritten signature SVG, and QR code</li>
            </ul>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('erp_certificate_template_nonce'); ?>
            
            <!-- Tab Navigation -->
            <div style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <div style="border-bottom: 1px solid #ccd0d4; padding: 0;">
                    <button type="button" class="erp-tab-btn" data-tab="css" style="padding: 15px 25px; background: #0073aa; color: white; border: none; font-weight: 600; cursor: pointer; font-size: 14px;">
                        <span class="dashicons dashicons-art" style="vertical-align: middle; margin-top: -2px;"></span>
                        CSS Editor
                    </button>
                    <button type="button" class="erp-tab-btn" data-tab="html" style="padding: 15px 25px; background: transparent; color: #2c3338; border: none; font-weight: 600; cursor: pointer; font-size: 14px;">
                        <span class="dashicons dashicons-editor-code" style="vertical-align: middle; margin-top: -2px;"></span>
                        HTML Editor
                    </button>
                </div>
                
                <!-- CSS Tab Content -->
                <div id="css-tab" class="erp-tab-content" style="padding: 20px; display: block;">
                    <h2 style="margin-top: 0;">Custom CSS Styles</h2>
                    <p>Add custom CSS to override default styles. Use this to change colors, fonts, sizes, etc.</p>
                    
                    <div style="margin: 15px 0;">
                        <label for="certificate_custom_css" style="display: block; font-weight: 600; margin-bottom: 10px;">
                            Custom CSS (Advanced)
                        </label>
                        <textarea 
                            name="certificate_custom_css" 
                            id="certificate_custom_css" 
                            rows="20" 
                            style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; background: #f6f7f7;"
                            placeholder="/* Example:
.attendee-name {
    color: #c0392b;
    font-size: 42px;
}

.sidebar-geometric {
    background: #f9f9f9;
}

.certificate-container {
    border-color: #3498db;
}
*/"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p style="color: #646970; font-size: 13px; margin-top: 8px;">
                            <strong>Tips:</strong> Target CSS classes like <code>.cert-title h1</code>, <code>.attendee-name</code>, <code>.award-circle</code>, etc.
                        </p>
                    </div>
                </div>
                
                <!-- HTML Tab Content -->
                <div id="html-tab" class="erp-tab-content" style="padding: 20px; display: none;">
                    <h2 style="margin-top: 0;">Custom HTML Structure</h2>
                    <p><strong style="color: #d63638;">⚠️ Advanced:</strong> Modify the certificate HTML structure. Leave empty to use default template.</p>
                    
                    <div style="margin: 15px 0;">
                        <label for="certificate_custom_html" style="display: block; font-weight: 600; margin-bottom: 10px;">
                            Custom HTML Body Content
                        </label>
                        <textarea 
                            name="certificate_custom_html" 
                            id="certificate_custom_html" 
                            rows="25" 
                            style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; background: #f6f7f7;"
                            placeholder="<!-- Leave empty to use default template -->
<!-- Available placeholders:
{{attendee_name}} - Participant name
{{event_title}} - Event name  
{{registration_date}} - Registration date
{{registration_code}} - Registration code
{{site_name}} - Website name
{{logo_url}} - Logo URL
{{qr_url}} - QR code URL
-->"><?php echo esc_textarea($custom_html); ?></textarea>
                        <p style="color: #646970; font-size: 13px; margin-top: 8px;">
                            <strong>Note:</strong> Only edit if you know HTML. Use placeholders like <code>{{attendee_name}}</code> for dynamic content.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Save Buttons -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="erp_save_template" class="button button-primary" style="height: auto; padding: 10px 20px;">
                        <span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
                        Save Template
                    </button>
                    <button type="submit" name="erp_reset_template" class="button" style="height: auto; padding: 10px 20px;" onclick="return confirm('Are you sure you want to reset to default template? This cannot be undone.');">
                        <span class="dashicons dashicons-image-rotate" style="vertical-align: middle; margin-top: -2px;"></span>
                        Reset to Default
                    </button>
                </div>
            </div>
        </form>
        
        <div style="background: #f0f6fc; border: 1px solid #c3e0ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #0073aa;">📚 CSS Class Reference</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 13px;">
                <div>
                    <strong>Layout:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li><code>.certificate-container</code> - Main container (cream background)</li>
                        <li><code>.geometric-sidebar</code> - Left sidebar with shapes</li>
                        <li><code>.geo-shape</code> - Individual shape container</li>
                        <li><code>.cert-main-content</code> - Main content area</li>
                    </ul>
                    <strong>Geometric Shapes:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li><code>.shape-circle</code> - Circle elements</li>
                        <li><code>.shape-triangle</code> - Triangle shapes</li>
                        <li><code>.shape-bars</code> - Horizontal bars</li>
                        <li><code>.shape-semicircle</code> - Semi-circles</li>
                    </ul>
                </div>
                <div>
                    <strong>Header:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li><code>.cert-header</code> - Header section</li>
                        <li><code>.logo-company</code> - Logo and company area</li>
                        <li><code>.company-logo</code> - Company logo image</li>
                        <li><code>.company-info</code> - Company name text</li>
                        <li><code>.award-badge</code> - Best Award badge</li>
                        <li><code>.badge-circle</code> - Red circle border</li>
                        <li><code>.badge-ribbons</code> - Black ribbons</li>
                    </ul>
                </div>
                <div>
                    <strong>Title & Content:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li><code>.cert-title</code> - Certificate title section</li>
                        <li><code>.cert-title h1</code> - "Certificate" heading</li>
                        <li><code>.cert-subtitle</code> - "OF ACHIEVEMENT"</li>
                        <li><code>.presented-section</code> - Presented to area</li>
                        <li><code>.recipient-name</code> - Recipient name (coral)</li>
                        <li><code>.description-text</code> - Description section</li>
                    </ul>
                </div>
                <div>
                    <strong>Footer:</strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <li><code>.cert-footer</code> - Footer container</li>
                        <li><code>.date-section</code> - Date block</li>
                        <li><code>.signature-section</code> - Signature area</li>
                        <li><code>.signature-svg</code> - SVG signature</li>
                        <li><code>.qr-section</code> - QR code area</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div style="background: #fffbcc; border: 1px solid #ffeb3b; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0;">⚠️ Important Notes</h3>
            <ul style="margin: 5px 0 0 20px; line-height: 1.6;">
                <li>Changes only apply to <strong>newly generated certificates</strong></li>
                <li>Test your changes by generating a certificate after saving</li>
                <li>Invalid CSS may break the certificate layout</li>
                <li>Keep a backup of your custom CSS before making major changes</li>
                <li>Use browser developer tools to inspect certificate HTML structure</li>
            </ul>
        </div>
        
        <!-- Tab Switching JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            $('.erp-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                
                // Update button styles
                $('.erp-tab-btn').css({
                    'background': 'transparent',
                    'color': '#2c3338'
                });
                $(this).css({
                    'background': '#0073aa',
                    'color': 'white'
                });
                
                // Show/hide tab content
                $('.erp-tab-content').hide();
                $('#' + tab + '-tab').show();
            });
        });
        </script>
    </div>
    <?php
}

/**
 * AJAX Preview Certificate Handler
 */
function erp_ajax_preview_certificate() {
    // Simple preview - generates certificate HTML with sample data
    $event_title = 'Sample Workshop Event 2026';
    $attendee_name = 'John Do Sample Name';
    $registration_date = date('F jS, Y'); // This represents event date in preview
    $registration_code = 'REG-PREVIEW-' . time();
    
    // Get site info
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
    $site_name = get_bloginfo('name');
    
    // Generate QR code (200x200 for better scanning)
    $qr_data = home_url('/verify-certificate?code=' . $registration_code);
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_data);
    $logo_qr_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'thumbnail') : '';
    
    // Get custom HTML or use default
    $custom_html_body = get_option('erp_certificate_custom_html', '');
    
    // If custom HTML exists, use it with placeholder replacement
    if (!empty($custom_html_body)) {
        $body_content = str_replace(
            ['{{attendee_name}}', '{{event_title}}', '{{registration_date}}', '{{registration_code}}', '{{site_name}}', '{{logo_url}}', '{{qr_url}}', '{{logo_qr_url}}'],
            [esc_html($attendee_name), esc_html($event_title), esc_html($registration_date), esc_html($registration_code), esc_html($site_name), esc_url($logo_url), esc_url($qr_url), esc_url($logo_qr_url)],
            $custom_html_body
        );
    } else {
        // Use default template function with direct parameters
        $body_content = erp_get_default_certificate_body($attendee_name, $event_title, $registration_date, $registration_code, $site_name, $logo_url, $qr_url, $logo_qr_url);
    }
    
    // Get custom CSS
    $custom_css = get_option('erp_certificate_custom_css', '');
    
    // Output the full HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Preview</title>
    <style>' . erp_get_certificate_default_css() . '
        
        /* Custom CSS from template editor */
        ' . $custom_css . '
    </style>
</head>
<body>
    <div style="background: #f0f0f0; padding: 20px; text-align: center; position: fixed; top: 0; left: 0; right: 0; z-index: 9999; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div style="background: white; display: inline-block; padding: 10px 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <strong>🔍 PREVIEW MODE</strong> - Sample certificate with test data
        </div>
    </div>
    
    <div style="padding-top: 80px;">
        ' . $body_content . '
    </div>

    <div style="background: #f0f0f0; padding: 20px; text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="background: #0073aa; color: white; border: none; padding: 12px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">
            📥 Print / Save as PDF
        </button>
        <button onclick="window.close()" style="background: #646970; color: white; border: none; padding: 12px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; margin-left: 10px;">
            Close Preview
        </button>
    </div>
</body>
</html>';
    
    exit;
}
add_action('wp_ajax_erp_preview_certificate', 'erp_ajax_preview_certificate');

/**
 * Get default certificate body HTML
 * Loads from external template file for easy editing
 */
function erp_get_default_certificate_body($attendee_name = '{{attendee_name}}', $event_title = '{{event_title}}', $registration_date = '{{registration_date}}', $registration_code = '{{registration_code}}', $site_name = '{{site_name}}', $logo_url = '{{logo_url}}', $qr_url = '{{qr_url}}', $logo_qr_url = '{{logo_qr_url}}') {
    // Load HTML template from file
    $template_file = plugin_dir_path(__FILE__) . 'templates/certificate.html';
    
    if (file_exists($template_file)) {
        $html = file_get_contents($template_file);
    } else {
        // Fallback if file doesn't exist
        $html = '<div class="certificate-container">
    <div class="left-sidebar">
        <div class="picture-box">
            <div class="picture-placeholder">
                <div class="picture-text">REPLACEABLE PICTURE</div>
            </div>
        </div>
        <div class="qr-box">
            <img src="{{qr_url}}" alt="QR Code" class="qr-code">
            <div class="qr-label">QR CODE</div>
        </div>
    </div>
    <div class="main-content">
        <div class="top-logos">
            <div class="logo-box">
                <img src="{{logo_url}}" alt="Logo" class="top-logo" style="{{logo_display}}">
                <div class="logo-placeholder" style="{{logo_placeholder_display}}">Replace<br>LOGO</div>
            </div>
            <div class="logo-box">
                <img src="{{logo_url}}" alt="Logo" class="top-logo" style="{{logo_display}}">
                <div class="logo-placeholder" style="{{logo_placeholder_display}}">Replace<br>LOGO</div>
            </div>
        </div>
        <div class="organization-name">
            <div>Lembaga Penelitian dan Pengabdian kepada Masyarakat</div>
            <div>Universitas Sunan Gresik</div>
        </div>
        <h1 class="certificate-title">Certificate</h1>
        <div class="recipient-name">{{attendee_name}}</div>
        <div class="event-details">
            <div class="event-row">
                <span class="event-label">Event Name</span>
                <span class="event-value">{{event_title}}</span>
            </div>
            <div class="event-row">
                <span class="event-label">Theme of event</span>
                <span class="event-value">{{site_name}}</span>
            </div>
            <div class="event-row">
                <span class="event-label">Date of event</span>
                <span class="event-value">{{registration_date}}</span>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-label">Signature</div>
        </div>
    </div>
</div>';
    }
    
    // Determine logo display styles
    $logo_display = (empty($logo_url) || $logo_url === '{{logo_url}}') ? 'display:none;' : '';
    $logo_placeholder_display = (empty($logo_url) || $logo_url === '{{logo_url}}') ? '' : 'display:none;';
    
    // Replace placeholders with actual values
    $html = str_replace(
        ['{{attendee_name}}', '{{event_title}}', '{{registration_date}}', '{{registration_code}}', '{{site_name}}', '{{logo_url}}', '{{qr_url}}', '{{logo_qr_url}}', '{{logo_display}}', '{{logo_placeholder_display}}'],
        [esc_html($attendee_name), esc_html($event_title), esc_html($registration_date), esc_html($registration_code), esc_html($site_name), esc_url($logo_url), esc_url($qr_url), esc_url($logo_qr_url), $logo_display, $logo_placeholder_display],
        $html
    );
    
    return $html;
}

/**
 * Get default certificate CSS
 * Loads from external CSS file for easy editing
 */
function erp_get_certificate_default_css() {
    // Load CSS from file
    $css_file = plugin_dir_path(__FILE__) . 'templates/certificate.css';
    
    if (file_exists($css_file)) {
        return file_get_contents($css_file);
    }
    
    // Fallback CSS if file doesn't exist
    return '@page { size: A4 landscape; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    padding: 0;
    margin: 0;
}
.certificate-container {
    width: 297mm;
    height: 210mm;
    background: white;
    position: relative;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(0,0,0,0.15);
    page-break-after: always;
    display: flex;
    border: 3px solid #000;
}
.left-sidebar {
    width: 145px;
    background: #87CEEB;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}
.picture-box {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.signature-box {
    position: absolute;
    bottom: 30px;
    right: 40px;
    width: 120px;
    height: 90px;
    background: #87CEEB;
    border: 2px solid #333;
    display: flex;
    align-items: center;
    justify-content: center;
}
@media print {
    body { padding: 0; margin: 0; background: white; }
    .certificate-container { box-shadow: none; margin: 0; width: 297mm; height: 210mm; }
    .no-print, .pdf-button { display: none; }
    @page { size: A4 landscape; margin: 0; }
}';
}

/**
 * Payment Settings Page
 */
function erp_settings_page() {
    // Check user capabilities (admin only)
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Save settings
    if (isset($_POST['erp_save_settings'])) {
        check_admin_referer('erp_settings_nonce');
        
        update_option('erp_midtrans_server_key', sanitize_text_field($_POST['midtrans_server_key']));
        update_option('erp_midtrans_client_key', sanitize_text_field($_POST['midtrans_client_key']));
        update_option('erp_midtrans_environment', sanitize_text_field($_POST['midtrans_environment']));
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $server_key = get_option('erp_midtrans_server_key', '');
    $client_key = get_option('erp_midtrans_client_key', '');
    $environment = get_option('erp_midtrans_environment', 'sandbox');
    
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">
            <span class="dashicons dashicons-admin-settings" style="font-size: 32px; width: 32px; height: 32px;"></span>
            Payment Settings
        </h1>
        
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 30px; max-width: 800px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">
                <span class="dashicons dashicons-money-alt" style="color: #2271b1;"></span>
                Midtrans Payment Gateway Configuration
            </h2>
            
            <p style="color: #646970; font-size: 14px; margin-bottom: 25px;">
                Configure your Midtrans payment gateway credentials. These settings are only accessible to administrators.
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('erp_settings_nonce'); ?>
                
                <!-- Server Key -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                        <span class="dashicons dashicons-admin-network" style="color: #2271b1;"></span>
                        Server Key <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="midtrans_server_key" 
                        value="<?php echo esc_attr($server_key); ?>" 
                        class="regular-text"
                        style="width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px;"
                        required
                    >
                    <p style="margin: 8px 0 0 0; color: #646970; font-size: 13px;">
                        Your Midtrans server key (used for backend API calls)
                    </p>
                </div>
                
                <!-- Client Key -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                        <span class="dashicons dashicons-smartphone" style="color: #2271b1;"></span>
                        Client Key <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="midtrans_client_key" 
                        value="<?php echo esc_attr($client_key); ?>" 
                        class="regular-text"
                        style="width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px;"
                        required
                    >
                    <p style="margin: 8px 0 0 0; color: #646970; font-size: 13px;">
                        Your Midtrans client key (used for frontend Snap UI)
                    </p>
                </div>
                
                <!-- Environment -->
                <div style="margin-bottom: 30px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                        <span class="dashicons dashicons-admin-tools" style="color: #2271b1;"></span>
                        Environment <span style="color: red;">*</span>
                    </label>
                    <select 
                        name="midtrans_environment" 
                        style="width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #8c8f94; border-radius: 4px;"
                    >
                        <option value="sandbox" <?php selected($environment, 'sandbox'); ?>>
                            Sandbox (Testing)
                        </option>
                        <option value="production" <?php selected($environment, 'production'); ?>>
                            Production (Live)
                        </option>
                    </select>
                    <p style="margin: 8px 0 0 0; color: #646970; font-size: 13px;">
                        Use <strong>Sandbox</strong> for testing, <strong>Production</strong> for real transactions
                    </p>
                </div>
                
                <!-- Info Box -->
                <div style="background: #e7f5fe; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                    <p style="margin: 0; color: #1d2327; font-size: 13px; line-height: 1.6;">
                        <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                        <strong>How to get your Midtrans credentials:</strong><br>
                        1. Login to <a href="https://dashboard.midtrans.com/" target="_blank">Midtrans Dashboard</a><br>
                        2. Go to <strong>Settings</strong> → <strong>Access Keys</strong><br>
                        3. Copy your Server Key and Client Key<br>
                        4. Use Sandbox keys for testing, Production keys when you're ready to go live
                    </p>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button 
                        type="submit" 
                        name="erp_save_settings" 
                        class="button button-primary button-hero"
                        style="padding: 12px 30px; font-size: 16px; height: auto;"
                    >
                        <span class="dashicons dashicons-yes" style="margin-top: 4px;"></span>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Current Status -->
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; max-width: 800px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #1d2327;">
                <span class="dashicons dashicons-admin-generic"></span>
                Current Configuration Status
            </h3>
            <table class="widefat" style="border: none;">
                <tr>
                    <td style="padding: 10px; font-weight: 600; width: 200px;">Server Key:</td>
                    <td style="padding: 10px; font-family: monospace; color: #646970;">
                        <?php echo esc_html(substr($server_key, 0, 20)) . '...'; ?>
                    </td>
                </tr>
                <tr style="background: #f6f7f7;">
                    <td style="padding: 10px; font-weight: 600;">Client Key:</td>
                    <td style="padding: 10px; font-family: monospace; color: #646970;">
                        <?php echo esc_html(substr($client_key, 0, 20)) . '...'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; font-weight: 600;">Environment:</td>
                    <td style="padding: 10px;">
                        <?php if ($environment === 'production'): ?>
                            <span style="background: #d63638; color: white; padding: 4px 12px; border-radius: 3px; font-weight: 600; font-size: 12px;">
                                🔴 PRODUCTION - LIVE
                            </span>
                        <?php else: ?>
                            <span style="background: #f0ad4e; color: white; padding: 4px 12px; border-radius: 3px; font-weight: 600; font-size: 12px;">
                                🟡 SANDBOX - TESTING
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Handle bulk actions for registrations
 */
function erp_handle_bulk_actions() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    if (!isset($_POST['registration_ids']) || !isset($_POST['bulk_action']) || !isset($_POST['_wpnonce'])) {
        wp_die('Invalid request');
    }
    
    if (!wp_verify_nonce($_POST['_wpnonce'], 'erp_bulk_actions')) {
        wp_die('Security check failed');
    }
    
    $registration_ids = array_map('intval', $_POST['registration_ids']);
    $bulk_action = sanitize_text_field($_POST['bulk_action']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($registration_ids as $reg_id) {
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $reg_id
        ));
        
        if (!$registration) {
            $error_count++;
            continue;
        }
        
        switch ($bulk_action) {
            case 'generate_certificates':
                // Auto-generate certificate PDF
                $certificate_generated = erp_auto_generate_certificate($registration);
                if ($certificate_generated) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            case 'send_notifications':
                $event = get_post($registration->event_id);
                $attendee_data = array(
                    'name' => wp_unslash($registration->attendee_name),
                    'email' => $registration->attendee_email,
                    'phone' => $registration->attendee_phone,
                    'job' => $registration->attendee_job
                );
                
                $is_paid = (get_post_meta($registration->event_id, '_entrance_fee', true) > 0);
                $sent = erp_send_confirmation_email($registration->event_id, $attendee_data, $is_paid, $registration->payment_status);
                
                if ($sent) {
                    $wpdb->update(
                        $table_name,
                        array('notification_sent' => current_time('mysql')),
                        array('id' => $reg_id),
                        array('%s'),
                        array('%d')
                    );
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            case 'send_certificates':
                $sent = erp_send_certificate($registration);
                if ($sent) {
                    $wpdb->update(
                        $table_name,
                        array('certificate_sent' => current_time('mysql')),
                        array('id' => $reg_id),
                        array('%s'),
                        array('%d')
                    );
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            case 'send_invitations':
                $sent = erp_send_invitation($registration);
                if ($sent) {
                    $wpdb->update(
                        $table_name,
                        array('invitation_sent' => current_time('mysql')),
                        array('id' => $reg_id),
                        array('%s'),
                        array('%d')
                    );
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            case 'mark_attended':
                $updated = $wpdb->update(
                    $table_name,
                    array(
                        'attendance_status' => 'attended',
                        'attendance_time' => current_time('mysql'),
                        'check_in_method' => 'manual'
                    ),
                    array('id' => $reg_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                if ($updated) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
                
            case 'unmark_attended':
                $updated = $wpdb->update(
                    $table_name,
                    array(
                        'attendance_status' => 'not_attended',
                        'attendance_time' => NULL,
                        'check_in_method' => NULL
                    ),
                    array('id' => $reg_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                if ($updated) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                break;
        }
    }
    
    $redirect_url = add_query_arg(
        array(
            'page' => 'event-registrations',
            'bulk_action' => $bulk_action,
            'success' => $success_count,
            'error' => $error_count,
            '_wpnonce' => wp_create_nonce('bulk_action_result')
        ),
        admin_url('admin.php')
    );
    
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_erp_bulk_actions', 'erp_handle_bulk_actions');

/**
 * Handle bulk certificate ZIP import
 */
function erp_handle_import_certificates_zip() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('erp_import_certificates_zip');
    
    $event_id = intval($_POST['event_id']);
    
    if (empty($_FILES['certificates_zip']['name'])) {
        wp_die('No ZIP file uploaded');
    }
    
    $file = $_FILES['certificates_zip'];
    
    // Validate file type
    if ($file['type'] !== 'application/zip' && $file['type'] !== 'application/x-zip-compressed') {
        wp_die('Only ZIP files are allowed');
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_die('File size must be less than 10MB');
    }
    
    // Handle upload
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload = wp_handle_upload($file, array('test_form' => false));
    
    if (isset($upload['error'])) {
        wp_die('Upload error: ' . $upload['error']);
    }
    
    $zip_path = $upload['file'];
    
    // Extract ZIP
    $extract_to = WP_CONTENT_DIR . '/uploads/temp_certificates_' . time();
    
    // Create extraction directory
    if (!wp_mkdir_p($extract_to)) {
        @unlink($zip_path);
        wp_die('Could not create temporary extraction directory');
    }
    
    $zip = new ZipArchive();
    $zip_open_result = $zip->open($zip_path);
    
    if ($zip_open_result !== TRUE) {
        @unlink($zip_path);
        @rmdir($extract_to);
        wp_die('Could not open ZIP file. Error code: ' . $zip_open_result);
    }
    
    // Extract all files
    if (!$zip->extractTo($extract_to)) {
        $zip->close();
        @unlink($zip_path);
        @rmdir($extract_to);
        wp_die('Failed to extract ZIP contents');
    }
    
    $zip->close();
    
    // Get registrations for this event
    $registrations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE event_id = %d",
        $event_id
    ));
    
    $imported_count = 0;
    $skipped_count = 0;
    $matched_files = array(); // Track matched files for debugging
    
    // Scan directory for PDF files
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extract_to),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'pdf') {
            continue;
        }
        
        // Verify it's a valid PDF file by checking header
        $handle = @fopen($file->getPathname(), 'rb');
        if ($handle) {
            $header = fread($handle, 5);
            fclose($handle);
            
            // PDF files start with "%PDF-"
            if (substr($header, 0, 5) !== '%PDF-') {
                continue; // Skip invalid PDF files
            }
        } else {
            continue; // Skip unreadable files
        }
        
        $filename = $file->getFilename();
        $matched = false;
        
        // Try to match by email or name in filename
        foreach ($registrations as $registration) {
            $email = strtolower($registration->attendee_email);
            $name_slug = sanitize_title(wp_unslash($registration->attendee_name));
            $name_lower = strtolower(wp_unslash($registration->attendee_name));
            
            // Remove .pdf extension for comparison
            $filename_lower = strtolower(str_replace('.pdf', '', $filename));
            
            // Multiple matching strategies
            $email_parts = explode('@', $email);
            $name_parts = explode(' ', $name_lower);
            
            $matches = false;
            
            // Strategy 1: Full email match
            if (strpos($filename_lower, $email) !== false) {
                $matches = true;
            }
            // Strategy 2: Email username match
            elseif (strpos($filename_lower, $email_parts[0]) !== false) {
                $matches = true;
            }
            // Strategy 3: Slugified name match
            elseif (strpos($filename_lower, $name_slug) !== false) {
                $matches = true;
            }
            // Strategy 4: Direct name match (spaces or hyphens)
            elseif (strpos($filename_lower, str_replace(' ', '-', $name_lower)) !== false) {
                $matches = true;
            }
            // Strategy 5: Name without spaces
            elseif (strpos($filename_lower, str_replace(' ', '', $name_lower)) !== false) {
                $matches = true;
            }
            // Strategy 6: First and last name match
            elseif (count($name_parts) >= 2) {
                $first_last = $name_parts[0] . $name_parts[count($name_parts)-1];
                if (strpos($filename_lower, $first_last) !== false) {
                    $matches = true;
                }
            }
            
            if ($matches) {
                // Use WordPress media upload system for proper file handling
                $upload_dir = wp_upload_dir();
                
                // Clean filename - keep only alphanumeric, dash, underscore, and extension
                $clean_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $filename);
                $new_filename = 'certificate_' . $registration->id . '_' . time() . '_' . $clean_filename;
                $new_filepath = $upload_dir['path'] . '/' . $new_filename;
                
                // Copy file with error checking
                if (@copy($file->getPathname(), $new_filepath)) {
                    // Verify file was actually copied and has content
                    if (file_exists($new_filepath) && filesize($new_filepath) > 0) {
                        // Set proper file permissions
                        @chmod($new_filepath, 0644);
                        
                        $file_url = $upload_dir['url'] . '/' . $new_filename;
                        update_option('erp_certificate_pdf_' . $registration->id, $file_url);
                        
                        $matched_files[] = $filename . ' → ' . wp_unslash($registration->attendee_name) . ' (' . size_format(filesize($new_filepath)) . ')';
                        $imported_count++;
                        $matched = true;
                        break;
                    } else {
                        // File copy failed or resulted in empty file
                        @unlink($new_filepath);
                    }
                }
            }
        }
        
        if (!$matched) {
            $skipped_count++;
        }
    }
    
    // Cleanup
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extract_to, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            @unlink($file->getRealPath());
        }
    }
    @rmdir($extract_to);
    @unlink($zip_path);
    
    // Store matched files info in transient for display (30 seconds)
    if (!empty($matched_files)) {
        set_transient('erp_zip_matched_files', $matched_files, 30);
    }
    
    $redirect_url = add_query_arg(
        array(
            'page' => 'event-registrations',
            'zip_imported' => $imported_count,
            'zip_skipped' => $skipped_count,
            'event_filter' => $event_id
        ),
        admin_url('admin.php')
    );
    
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_erp_import_certificates_zip', 'erp_handle_import_certificates_zip');

/**
 * Auto-generate certificate PDF for a registration
 */
function erp_auto_generate_certificate($registration) {
    $event = get_post($registration->event_id);
    $event_title = $event ? $event->post_title : 'Event';
    $attendee_name = wp_unslash($registration->attendee_name);
    
    // Use event date instead of registration date
    $event_date_raw = get_post_meta($registration->event_id, '_event_date', true);
    $registration_date = $event_date_raw ? date('F jS, Y', strtotime($event_date_raw)) : date('F jS, Y');
    
    $registration_code = 'REG-' . str_pad($registration->id, 6, '0', STR_PAD_LEFT);
    
    // Get site logo or use default
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
    $site_name = get_bloginfo('name');
    
    // Generate QR code URL (200x200 for better scanning)
    $qr_data = home_url('/verify-certificate?code=' . $registration_code);
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_data);
    
    // Get LPPM logo URL
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_qr_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'thumbnail') : '';
    
    // Get custom HTML or use default
    $custom_html_body = get_option('erp_certificate_custom_html', '');
    
    // If custom HTML exists, use it with placeholder replacement
    if (!empty($custom_html_body)) {
        $body_content = str_replace(
            ['{{attendee_name}}', '{{event_title}}', '{{registration_date}}', '{{registration_code}}', '{{site_name}}', '{{logo_url}}', '{{qr_url}}', '{{logo_qr_url}}'],
            [esc_html($attendee_name), esc_html($event_title), esc_html($registration_date), esc_html($registration_code), esc_html($site_name), esc_url($logo_url), esc_url($qr_url), esc_url($logo_qr_url)],
            $custom_html_body
        );
    } else {
        // Use default template function with direct parameters
        $body_content = erp_get_default_certificate_body($attendee_name, $event_title, $registration_date, $registration_code, $site_name, $logo_url, $qr_url, $logo_qr_url);
    }
    
    // Get custom CSS
    $custom_css = get_option('erp_certificate_custom_css', '');
    
    // Generate professional certificate HTML
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - ' . esc_html($attendee_name) . '</title>
    <style>' . erp_get_certificate_default_css() . '
        
        /* PDF Button */
        .pdf-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0073aa;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,115,170,0.3);
            transition: transform 0.2s;
            z-index: 1000;
        }
        
        .pdf-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,115,170,0.4);
        }
        
        /* Custom CSS from template editor */
        ' . $custom_css . '
    </style>
</head>
<body>
    <a href="#" onclick="window.print(); return false;" class="pdf-button no-print">📥 Download PDF</a>
    
    ' . $body_content . '
</body>
</html>';
    
    // Save HTML certificate
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates';
    
    if (!file_exists($cert_dir)) {
        wp_mkdir_p($cert_dir);
    }
    
    $filename = 'certificate-' . $registration->id . '-' . time() . '.html';
    $file_path = $cert_dir . '/' . $filename;
    $file_url = $upload_dir['baseurl'] . '/certificates/' . $filename;
    
    if (file_put_contents($file_path, $html)) {
        update_option('erp_certificate_pdf_' . $registration->id, $file_url);
        return true;
    }
    
    return false;
}

/**
 * Send certificate to attendee
 */
function erp_send_certificate($registration) {
    $event = get_post($registration->event_id);
    if (!$event) {
        error_log('Certificate send failed: Event not found for registration ' . $registration->id);
        return false;
    }
    
    // Check for certificate (HTML or PDF)
    $certificate_url = get_option('erp_certificate_pdf_' . $registration->id);
    
    if (!$certificate_url) {
        error_log('Certificate send failed: No certificate found for registration ' . $registration->id);
        return false;
    }
    
    // Prepare email
    $to = $registration->attendee_email;
    $subject = 'Certificate of Attendance - ' . $event->post_title;
    
    $event_date = get_post_meta($registration->event_id, '_event_date', true);
    $formatted_date = date('F j, Y', strtotime($event_date));
    
    $message = '
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; padding: 20px;">
        <div style="max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 30px; border-radius: 8px;">
            <div style="background-color: #2271b1; color: white; padding: 20px; text-align: center; border-radius: 5px;">
                <h1 style="margin: 0; font-size: 24px;">Certificate of Attendance</h1>
            </div>
            
            <div style="background: white; padding: 30px; margin-top: 20px; border-radius: 5px;">
                <p>Dear ' . esc_html(wp_unslash($registration->attendee_name)) . ',</p>
                
                <p>Congratulations on successfully attending <strong>' . esc_html($event->post_title) . '</strong>!</p>
                
                <p>Your certificate of attendance is ready. Please click the button below to view and download your certificate:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($certificate_url) . '" style="display: inline-block; background: #2271b1; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                        📜 View & Download Certificate
                    </a>
                </div>
                
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0; color: #856404;"><strong>💡 How to save as PDF:</strong></p>
                    <ol style="margin: 10px 0 0 0; padding-left: 20px; color: #856404;">
                        <li>Click the button above to open your certificate</li>
                        <li>Click the "📥 Download PDF" button (bottom right)</li>
                        <li>Or use your browser: Press Ctrl+P (Windows) or Cmd+P (Mac)</li>
                        <li>Select "Save as PDF" as the printer</li>
                        <li>Click Save</li>
                    </ol>
                </div>
                
                <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0;"><strong>Event Details:</strong></p>
                    <p style="margin: 5px 0 0 0;">📅 Date: ' . esc_html($formatted_date) . '</p>
                    <p style="margin: 5px 0 0 0;">📍 ' . esc_html($event->post_title) . '</p>
                </div>
                
                <p>Thank you for your participation!</p>
                
                <p style="color: #646970; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    Best regards,<br>
                    <strong>LPPM USG Events Team</strong><br>
                    <a href="mailto:' . ERP_ADMIN_EMAIL . '" style="color: #2271b1;">' . ERP_ADMIN_EMAIL . '</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8'
    );
    
    // Send email with certificate link (no attachment)
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        error_log('Certificate email sent successfully to: ' . $to . ' (Registration ID: ' . $registration->id . ')');
    } else {
        error_log('Certificate email send failed to: ' . $to . ' (Registration ID: ' . $registration->id . ')');
    }
    
    return $sent;
}

/**
 * Send certificate as HTML email (DEPRECATED - kept for reference)
 * Certificates must now be uploaded by admin, not auto-generated
 */
function erp_send_certificate_html($registration) {
    // This function is no longer used
    // Certificates must be manually uploaded via the hamburger menu
    error_log('erp_send_certificate_html called but is deprecated. Use manual upload instead.');
    return false;
}

/**
 * Send invitation to attendee
 */
function erp_send_invitation($registration) {
    $event = get_post($registration->event_id);
    if (!$event) {
        error_log('Invitation send failed: Event not found for registration ' . $registration->id);
        return false;
    }
    
    // Get event details
    $event_date = get_post_meta($registration->event_id, '_event_date', true);
    $event_time = get_post_meta($registration->event_id, '_event_time', true);
    $event_location = get_post_meta($registration->event_id, '_event_location', true);
    $zoom_link = get_post_meta($registration->event_id, '_event_zoom_ticket_link', true);
    $ticket_info = get_post_meta($registration->event_id, '_event_ticket_info', true);
    $entrance_fee = get_post_meta($registration->event_id, '_entrance_fee', true);
    
    $formatted_date = date('F j, Y', strtotime($event_date));
    $formatted_time = $event_time ? date('g:i A', strtotime($event_time)) : 'TBA';
    
    // Generate check-in QR code
    $checkin_url = home_url('/event-checkin?reg_id=' . $registration->id . '&code=' . md5($registration->id . $registration->attendee_email));
    $checkin_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($checkin_url);
    
    // Build Zoom link section
    $zoom_section = '';
    if ($zoom_link) {
        $zoom_section = '
        <div style="background: #e7f3ff; border: 2px solid #2271b1; padding: 25px; border-radius: 8px; margin: 25px 0; text-align: center;">
            <div style="font-size: 32px; margin-bottom: 10px;">🎥</div>
            <h3 style="margin: 0 0 15px 0; color: #1d2327; font-size: 20px;">Join via Zoom</h3>
            <a href="' . esc_url($zoom_link) . '" style="display: inline-block; background: #2271b1; color: white; padding: 15px 40px; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; margin: 10px 0;">
                Click to Join Meeting
            </a>
            <p style="margin: 15px 0 0 0; color: #646970; font-size: 13px; word-break: break-all;">
                <strong>Meeting Link:</strong><br>' . esc_html($zoom_link) . '
            </p>
        </div>';
    }
    
    // Build ticket/access info section
    $ticket_section = '';
    if ($ticket_info) {
        $ticket_section = '
        <div style="background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 20px; margin: 25px 0; border-radius: 4px;">
            <h3 style="margin: 0 0 10px 0; color: #856404; font-size: 16px;">
                <span style="font-size: 20px;">🎫</span> Event Information
            </h3>
            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.8;">
                ' . nl2br(esc_html($ticket_info)) . '
            </p>
        </div>';
    }
    
    $to = $registration->attendee_email;
    $subject = 'Event Invitation - ' . $event->post_title;
    
    $message = '
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; background-color: #f5f5f5;">
        <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="background-color: #2271b1; color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">🎉 You\'re Invited!</h1>
            </div>
            
            <div style="padding: 30px;">
                <p style="font-size: 16px; color: #1d2327;">Dear <strong>' . esc_html(wp_unslash($registration->attendee_name)) . '</strong>,</p>
                
                <p style="font-size: 15px; color: #1d2327;">
                    We are excited to invite you to attend:
                </p>
                
                <div style="background: #f8f9fa; border-left: 4px solid #2271b1; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h2 style="margin: 0 0 15px 0; color: #2271b1; font-size: 22px;">' . esc_html($event->post_title) . '</h2>
                    
                    <p style="margin: 8px 0; color: #1d2327; font-size: 15px;">
                        <strong>📅 Date:</strong> ' . esc_html($formatted_date) . '
                    </p>
                    <p style="margin: 8px 0; color: #1d2327; font-size: 15px;">
                        <strong>⏰ Time:</strong> ' . esc_html($formatted_time) . '
                    </p>
                    ' . ($event_location ? '
                    <p style="margin: 8px 0; color: #1d2327; font-size: 15px;">
                        <strong>📍 Location:</strong> ' . esc_html($event_location) . '
                    </p>' : '') . '
                    ' . ($entrance_fee && $entrance_fee > 0 ? '
                    <p style="margin: 8px 0; color: #1d2327; font-size: 15px;">
                        <strong>💳 Fee:</strong> Rp ' . number_format($entrance_fee, 0, ',', '.') . '
                    </p>' : '') . '
                </div>
                
                ' . $zoom_section . '
                ' . $ticket_section . '
                
                <div style="background: #d4edda; border: 2px solid #28a745; padding: 25px; border-radius: 8px; margin: 25px 0; text-align: center;">
                    <div style="font-size: 32px; margin-bottom: 10px;">✅</div>
                    <h3 style="margin: 0 0 15px 0; color: #155724; font-size: 20px;">Event Check-In</h3>
                    <p style="margin: 0 0 15px 0; color: #155724; font-size: 14px; line-height: 1.6;">
                        <strong>Please check in when you arrive!</strong><br>
                        Scan the QR code below or click the check-in link to mark your attendance.
                    </p>
                    <div style="background: white; padding: 15px; display: inline-block; border-radius: 8px; margin: 10px 0;">
                        <img src="' . esc_url($checkin_qr_url) . '" alt="Check-in QR Code" style="width: 200px; height: 200px; display: block;">
                    </div>
                    <p style="margin: 15px 0 0 0; color: #155724; font-size: 13px;">
                        Or click: <a href="' . esc_url($checkin_url) . '" style="color: #155724; font-weight: 600;">Check-in Now</a>
                    </p>
                    
                    <div style="background: #fff3cd; border: 1px solid #f0ad4e; border-radius: 6px; padding: 15px; margin: 20px 0 0 0; text-align: left;">
                        <p style="margin: 0 0 8px 0; color: #856404; font-size: 13px; font-weight: 600;">
                            ⏰ Check-in Window
                        </p>
                        <p style="margin: 0; color: #856404; font-size: 12px; line-height: 1.6;">
                            <strong>Opens:</strong> 24 hours before the event<br>
                            <strong>Closes:</strong> 2 hours after event starts<br>
                            <em>Please make sure to check in during this time to receive your certificate.</em>
                        </p>
                    </div>
                </div>
                
                <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 20px; margin: 25px 0; border-radius: 4px;">
                    <h3 style="margin: 0 0 12px 0; color: #1d2327; font-size: 16px;">
                        📝 Important Reminders
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; color: #1d2327; font-size: 14px; line-height: 1.8;">
                        <li>Please arrive <strong>at least 15 minutes early</strong> for registration</li>
                        <li>Don\'t forget to <strong>check in using the QR code</strong> when you arrive</li>
                        <li>Attendance check-in is <strong>required to receive your certificate</strong></li>
                        <li>Keep this email for easy access to your check-in link</li>
                        ' . ($event_location ? '<li>Event location: <strong>' . esc_html($event_location) . '</strong></li>' : '') . '
                    </ul>
                </div>
                
                <p style="font-size: 15px; color: #1d2327; margin-top: 25px;">
                    We look forward to seeing you at the event!
                </p>
                
                <p style="color: #646970; font-size: 13px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    Best regards,<br>
                    <strong>LPPM USG Events Team</strong><br>
                    <a href="mailto:' . ERP_ADMIN_EMAIL . '" style="color: #2271b1; text-decoration: none;">' . ERP_ADMIN_EMAIL . '</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8'
    );
    
    // Set up error capture
    global $phpmailer;
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        error_log('Invitation sent successfully to: ' . $to . ' (Registration ID: ' . $registration->id . ')');
    } else {
        $error_message = 'Unknown error';
        if (isset($phpmailer) && method_exists($phpmailer, 'getError')) {
            $error_message = $phpmailer->ErrorInfo;
        }
        error_log('Invitation send failed to: ' . $to . ' (Registration ID: ' . $registration->id . ') - Error: ' . $error_message);
    }
    
    return $sent;
}

/**
 * Handle event check-in
 */
function erp_handle_event_checkin() {
    // Check if this is a check-in request
    if (!isset($_GET['reg_id']) || !isset($_GET['code'])) {
        return;
    }
    
    if (!is_numeric($_GET['reg_id'])) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $reg_id = intval($_GET['reg_id']);
    $code = sanitize_text_field($_GET['code']);
    
    // Get registration
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $reg_id
    ));
    
    if (!$registration) {
        wp_die('<h1>Invalid Registration</h1><p>Registration not found.</p>', 'Check-in Error', array('response' => 404));
    }
    
    // Verify code
    $expected_code = md5($registration->id . $registration->attendee_email);
    if ($code !== $expected_code) {
        wp_die('<h1>Invalid Code</h1><p>Check-in code is invalid.</p>', 'Check-in Error', array('response' => 403));
    }
    
    // Get event details
    $event = get_post($registration->event_id);
    if (!$event) {
        wp_die('<h1>Event Not Found</h1><p>The event associated with this registration could not be found.</p>', 'Check-in Error', array('response' => 404));
    }
    
    // Get event date and time for validation
    $event_date = get_post_meta($registration->event_id, '_event_date', true);
    $event_time = get_post_meta($registration->event_id, '_event_time', true);
    
    // Check 2-hour check-in window (only if event has date/time)
    $checkin_allowed = true;
    $checkin_message = '';
    if ($event_date && $event_time) {
        $event_datetime = strtotime($event_date . ' ' . $event_time);
        $current_time = current_time('timestamp');
        $hours_until_event = ($event_datetime - $current_time) / 3600;
        $hours_since_event = ($current_time - $event_datetime) / 3600;
        
        // Check-in window: from 24 hours before to 2 hours after event start
        if ($hours_until_event > 24) {
            $checkin_allowed = false;
            $days_until = ceil($hours_until_event / 24);
            $checkin_message = 'Check-in opens 24 hours before the event. Please return in ' . $days_until . ' day' . ($days_until > 1 ? 's' : '') . '.';
        } elseif ($hours_since_event > 2) {
            $checkin_allowed = false;
            $checkin_message = 'Check-in window has closed. The check-in period ended 2 hours after the event start time.';
        }
    }
    
    // Check if already checked in
    $already_checked_in = ($registration->attendance_status === 'attended');
    
    // Handle check-in submission
    if (isset($_POST['checkin_confirm']) && check_admin_referer('checkin_' . $reg_id)) {
        if (!$checkin_allowed && !$already_checked_in) {
            wp_die('<h1>Check-in Not Available</h1><p>' . esc_html($checkin_message) . '</p><p><a href="javascript:history.back()" class="button">Go Back</a></p>', 'Check-in Closed', array('response' => 403));
        }
        
        $wpdb->update(
            $table_name,
            array(
                'attendance_status' => 'attended',
                'attendance_time' => current_time('mysql'),
                'check_in_method' => 'qr_code'
            ),
            array('id' => $reg_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        $already_checked_in = true;
        $registration->attendance_time = current_time('mysql');
    }
    
    // Display check-in page
    $event_location = get_post_meta($registration->event_id, '_event_location', true);
    $formatted_date = $event_date ? date('F j, Y', strtotime($event_date)) : 'TBA';
    $formatted_time = $event_time ? date('g:i A', strtotime($event_time)) : 'TBA';
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Event Check-In - <?php echo esc_html($event->post_title); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .checkin-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 100%;
                overflow: hidden;
                animation: slideUp 0.5s ease-out;
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .checkin-header {
                background: <?php echo $already_checked_in ? '#28a745' : '#2271b1'; ?>;
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            .checkin-icon {
                font-size: 64px;
                margin-bottom: 15px;
                animation: bounce 1s ease-in-out infinite;
            }
            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .checkin-header h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            .checkin-header p {
                font-size: 16px;
                opacity: 0.95;
            }
            .checkin-body {
                padding: 30px;
            }
            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #2271b1;
                padding: 20px;
                margin-bottom: 25px;
                border-radius: 8px;
            }
            .info-box.success {
                border-left-color: #28a745;
                background: #d4edda;
            }
            .info-row {
                margin-bottom: 15px;
                display: flex;
                align-items: start;
            }
            .info-row:last-child {
                margin-bottom: 0;
            }
            .info-label {
                font-weight: 600;
                color: #495057;
                min-width: 120px;
                font-size: 14px;
            }
            .info-value {
                color: #212529;
                font-size: 14px;
                flex: 1;
            }
            .checkin-button {
                background: #28a745;
                color: white;
                border: none;
                padding: 16px 30px;
                border-radius: 10px;
                font-size: 18px;
                font-weight: 600;
                width: 100%;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            }
            .checkin-button:hover {
                background: #218838;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            }
            .checkin-button:active {
                transform: translateY(0);
            }
            .already-checked {
                background: #6c757d;
                cursor: not-allowed;
            }
            .already-checked:hover {
                background: #6c757d;
                transform: none;
            }
            .timestamp {
                text-align: center;
                color: #6c757d;
                font-size: 13px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
            }
            .success-checkmark {
                font-size: 80px;
                color: #28a745;
                text-align: center;
                margin: 20px 0;
                animation: scaleIn 0.5s ease-out;
            }
            @keyframes scaleIn {
                from { transform: scale(0); }
                to { transform: scale(1); }
            }
        </style>
    </head>
    <body>
        <div class="checkin-container">
            <div class="checkin-header">
                <div class="checkin-icon"><?php echo $already_checked_in ? '✅' : '🎫'; ?></div>
                <h1><?php echo $already_checked_in ? 'Already Checked In!' : 'Event Check-In'; ?></h1>
                <p><?php echo esc_html($event->post_title); ?></p>
            </div>
            
            <div class="checkin-body">
                <?php if ($already_checked_in): ?>
                    <div class="success-checkmark">✓</div>
                    <div class="info-box success">
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value"><strong style="color: #28a745;">Checked In</strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Check-in Time:</div>
                            <div class="info-value"><?php echo mysql2date('F j, Y g:i A', $registration->attendance_time); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Attendee:</div>
                            <div class="info-value"><?php echo esc_html(wp_unslash($registration->attendee_name)); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo esc_html($registration->attendee_email); ?></div>
                        </div>
                    </div>
                    <button class="checkin-button already-checked" disabled>✓ Attendance Recorded</button>
                <?php else: ?>
                    <?php if (!$checkin_allowed): ?>
                        <div style="background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 20px; margin: 0 0 20px 0; border-radius: 4px;">
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <span style="font-size: 28px; line-height: 1;">⚠️</span>
                                <div>
                                    <h3 style="margin: 0 0 8px 0; color: #856404; font-size: 16px;">Check-in Not Available</h3>
                                    <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                        <?php echo esc_html($checkin_message); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-box">
                        <div class="info-row">
                            <div class="info-label">Attendee:</div>
                            <div class="info-value"><strong><?php echo esc_html(wp_unslash($registration->attendee_name)); ?></strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo esc_html($registration->attendee_email); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Event Date:</div>
                            <div class="info-value"><?php echo esc_html($formatted_date); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Event Time:</div>
                            <div class="info-value"><?php echo esc_html($formatted_time); ?></div>
                        </div>
                        <?php if ($event_location): ?>
                        <div class="info-row">
                            <div class="info-label">Location:</div>
                            <div class="info-value"><?php echo esc_html($event_location); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($checkin_allowed): ?>
                    <form method="post">
                        <?php wp_nonce_field('checkin_' . $reg_id); ?>
                        <input type="hidden" name="checkin_confirm" value="1">
                        <button type="submit" class="checkin-button">
                            ✓ Confirm Check-In
                        </button>
                    </form>
                    <?php else: ?>
                    <div style="background: #f0f0f1; padding: 15px; border-radius: 8px; text-align: center; color: #646970; font-size: 14px;">
                        <strong>Check-in Window:</strong><br>
                        Opens 24 hours before event • Closes 2 hours after event starts
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="timestamp">
                    Registration ID: <?php echo esc_html($registration->id); ?><br>
                    <?php echo date('l, F j, Y - g:i A'); ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
add_action('template_redirect', 'erp_handle_event_checkin', 1);

/**
 * Handle registration deletion
 */
function erp_handle_delete_registration() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    if (!isset($_GET['registration_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('Invalid request');
    }
    
    $registration_id = intval($_GET['registration_id']);
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_registration_' . $registration_id)) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    $deleted = $wpdb->delete(
        $table_name,
        array('id' => $registration_id),
        array('%d')
    );
    
    $redirect_url = add_query_arg(
        array(
            'page' => 'event-registrations',
            'deleted' => $deleted ? 'success' : 'error'
        ),
        admin_url('admin.php')
    );
    
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_erp_delete_registration', 'erp_handle_delete_registration');

/**
 * Registrations admin page
 */
function erp_registrations_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Show notifications
    if (isset($_GET['deleted'])) {
        if ($_GET['deleted'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Registration deleted successfully!</strong></p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Failed to delete registration.</strong></p></div>';
        }
    }
    
    // Show bulk action notifications
    if (isset($_GET['bulk_action']) && isset($_GET['success'])) {
        $action_name = sanitize_text_field($_GET['bulk_action']);
        $success = intval($_GET['success']);
        $error = isset($_GET['error']) ? intval($_GET['error']) : 0;
        
        $action_label = '';
        switch ($action_name) {
            case 'generate_certificates': $action_label = 'Certificates generated for'; break;
            case 'send_notifications': $action_label = 'Notifications'; break;
            case 'send_certificates': $action_label = 'Certificates'; break;
            case 'send_invitations': $action_label = 'Invitations'; break;
            case 'mark_attended': $action_label = 'Attendance marked for'; break;
            case 'unmark_attended': $action_label = 'Attendance removed for'; break;
        }
        
        if ($success > 0) {
            if ($action_name === 'generate_certificates') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $success . ' certificate(s) generated successfully!</strong>';
            } elseif (in_array($action_name, ['mark_attended', 'unmark_attended'])) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $action_label . ' ' . $success . ' registration(s)!</strong>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $action_label . ' sent to ' . $success . ' registration(s)!</strong>';
            }
            if ($error > 0) echo ' (' . $error . ' failed)';
            echo '</p></div>';
        } elseif ($error > 0) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Failed to process ' . $action_label . ' (' . $error . ' errors)</strong></p></div>';
        }
    }
    
    // Show ZIP import notifications
    if (isset($_GET['zip_imported'])) {
        $imported = intval($_GET['zip_imported']);
        $skipped = isset($_GET['zip_skipped']) ? intval($_GET['zip_skipped']) : 0;
        $matched_files = get_transient('erp_zip_matched_files');
        
        if ($imported > 0) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>✓ Successfully imported ' . $imported . ' certificate(s) from ZIP!</strong>';
            if ($skipped > 0) echo ' (' . $skipped . ' file(s) could not be matched)';
            echo '</p>';
            
            // Show detailed matches
            if (!empty($matched_files)) {
                echo '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #2271b1; font-weight: 600;">View matched files</summary>';
                echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                foreach ($matched_files as $match) {
                    echo '<li style="font-family: monospace; font-size: 12px;">' . esc_html($match) . '</li>';
                }
                echo '</ul></details>';
                delete_transient('erp_zip_matched_files');
            }
            echo '</div>';
        } elseif ($skipped > 0) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>⚠ No certificates were imported. ' . $skipped . ' file(s) could not be matched to any registrations.</strong></p>';
            echo '<p style="margin-top: 10px; color: #646970; font-size: 13px;">';
            echo '<strong>Matching Tips:</strong><br>';
            echo '• Filename should contain attendee <strong>email</strong> (e.g., john@example.com.pdf)<br>';
            echo '• OR attendee <strong>name</strong> (e.g., John-Doe.pdf, johndoe.pdf)<br>';
            echo '• Check that you selected the correct event<br>';
            echo '• Remove any special characters from filenames';
            echo '</p>';
            echo '</div>';
        }
    }
    
    // Check timezone configuration and show warning if not set
    $timezone_string = get_option('timezone_string');
    if (empty($timezone_string)) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>⚠️ Timezone Not Configured!</strong></p>';
        echo '<p>WordPress timezone is not set. Attendance timestamps may be incorrect. ';
        echo 'Go to <a href="' . admin_url('options-general.php') . '" target="_blank"><strong>Settings > General</strong></a> ';
        echo 'and set your timezone to ensure correct times are displayed.</p>';
        echo '<p style="color: #646970; font-size: 12px; margin-top: 8px;">';
        echo '📍 Current Server Time: <strong>' . date('Y-m-d H:i:s') . ' (UTC)</strong><br>';
        echo '📍 Expected: Should reflect your local timezone (e.g., Asia/Jakarta for WIB)';
        echo '</p>';
        echo '</div>';
    }
    
    // Get filter parameters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_event = isset($_GET['filter_event']) ? intval($_GET['filter_event']) : 0;
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_payment = isset($_GET['filter_payment']) ? sanitize_text_field($_GET['filter_payment']) : '';
    
    // Build SQL query with filters
    $where_clauses = array();
    
    if (!empty($search)) {
        $where_clauses[] = $wpdb->prepare(
            "(r.attendee_name LIKE %s OR r.attendee_email LIKE %s OR r.attendee_phone LIKE %s OR r.attendee_job LIKE %s OR p.post_title LIKE %s)",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }
    
    if ($filter_event > 0) {
        $where_clauses[] = $wpdb->prepare("r.event_id = %d", $filter_event);
    }
    
    if (!empty($filter_status)) {
        $where_clauses[] = $wpdb->prepare("r.status = %s", $filter_status);
    }
    
    if (!empty($filter_payment)) {
        $where_clauses[] = $wpdb->prepare("r.payment_status = %s", $filter_payment);
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Handle sorting
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'registration_date';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Map column names to database fields
    $sortable_columns = array(
        'id' => 'r.id',
        'event_name' => 'p.post_title',
        'attendee_name' => 'r.attendee_name',
        'email' => 'r.attendee_email',
        'phone' => 'r.attendee_phone',
        'job' => 'r.attendee_job',
        'tickets' => 'r.ticket_quantity',
        'registration_date' => 'r.registration_date',
        'status' => 'r.status',
        'payment' => 'r.payment_status',
        'attendance' => 'r.attendance_status'
    );
    
    $order_sql = 'ORDER BY r.registration_date DESC';
    if (isset($sortable_columns[$orderby])) {
        $order_sql = 'ORDER BY ' . $sortable_columns[$orderby] . ' ' . $order;
    }
    
    // Clear cache before fetching to get fresh data
    wp_cache_flush();
    
    // Get ALL registrations for overall dashboard (no filters)
    $all_registrations = $wpdb->get_results("
        SELECT r.*, p.post_title as event_name
        FROM $table_name r
        LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID
        ORDER BY r.registration_date DESC
    ", OBJECT);
    
    // Calculate overall statistics (all events)
    $overall_total = count($all_registrations);
    $overall_confirmed = 0;
    $overall_pending = 0;
    $overall_cancelled = 0;
    $overall_paid = 0;
    $overall_unpaid = 0;
    $overall_attended = 0;
    $overall_not_attended = 0;
    $overall_tickets = 0;
    
    foreach ($all_registrations as $reg) {
        if ($reg->status === 'confirmed') $overall_confirmed++;
        elseif ($reg->status === 'pending') $overall_pending++;
        elseif ($reg->status === 'cancelled') $overall_cancelled++;
        
        if (($reg->payment_status ?? 'unpaid') === 'paid') $overall_paid++;
        else $overall_unpaid++;
        
        if (($reg->attendance_status ?? 'not_attended') === 'attended') $overall_attended++;
        else $overall_not_attended++;
        
        $overall_tickets += intval($reg->ticket_quantity);
    }
    
    // Get filtered registrations (with filters applied)
    $registrations = $wpdb->get_results("
        SELECT r.*, p.post_title as event_name
        FROM $table_name r
        LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID
        $where_sql
        $order_sql
    ", OBJECT);
    
    // Calculate statistics for filtered results
    $total_registrations = count($registrations);
    $total_confirmed = 0;
    $total_pending = 0;
    $total_cancelled = 0;
    $total_paid = 0;
    $total_unpaid = 0;
    $total_attended = 0;
    $total_not_attended = 0;
    $total_tickets = 0;
    
    foreach ($registrations as $reg) {
        // Status counts
        if ($reg->status === 'confirmed') $total_confirmed++;
        elseif ($reg->status === 'pending') $total_pending++;
        elseif ($reg->status === 'cancelled') $total_cancelled++;
        
        // Payment counts
        if (($reg->payment_status ?? 'unpaid') === 'paid') $total_paid++;
        else $total_unpaid++;
        
        // Attendance counts
        if (($reg->attendance_status ?? 'not_attended') === 'attended') $total_attended++;
        else $total_not_attended++;
        
        // Total tickets
        $total_tickets += intval($reg->ticket_quantity);
    }
    
    // Get all events for filter dropdown
    $events = $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title
        FROM {$wpdb->posts} p
        INNER JOIN $table_name r ON p.ID = r.event_id
        WHERE p.post_type = 'event'
        ORDER BY p.post_title ASC
    ");
    
    // Get unique statuses
    $statuses = $wpdb->get_col("SELECT DISTINCT status FROM $table_name WHERE status IS NOT NULL ORDER BY status");
    
    // Get unique payment statuses
    $payment_statuses = $wpdb->get_col("SELECT DISTINCT payment_status FROM $table_name WHERE payment_status IS NOT NULL ORDER BY payment_status");
    
    // Helper function to generate sortable column header
    function erp_sortable_column($column_key, $column_label, $current_orderby, $current_order) {
        $base_url = remove_query_arg(array('orderby', 'order'));
        $new_order = ($current_orderby === $column_key && $current_order === 'ASC') ? 'DESC' : 'ASC';
        $url = add_query_arg(array('orderby' => $column_key, 'order' => $new_order), $base_url);
        
        $arrow = '';
        if ($current_orderby === $column_key) {
            $arrow = $current_order === 'ASC' ? ' ▲' : ' ▼';
        }
        
        return '<a href="' . esc_url($url) . '" style="text-decoration: none; color: inherit; font-weight: 600;">' . 
               esc_html($column_label) . '<span style="font-size: 10px;">' . $arrow . '</span></a>';
    }
    
    ?>
    <div class="wrap">
        <h1 style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <span>📋 Event Registrations</span>
            <?php 
            $user_favorites = erp_get_user_favorite_events($current_user_id);
            $fav_count = count($user_favorites);
            ?>
            <a href="<?php echo admin_url('admin.php?page=event-my-events'); ?>" class="button button-secondary" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; height: auto; line-height: normal;">
                <span class="dashicons dashicons-star-filled" style="color: #ffc107; font-size: 18px;"></span>
                <span>My Events <?php if ($fav_count > 0): ?><span style="background: #ffc107; color: #1d2327; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 700; margin-left: 5px;"><?php echo $fav_count; ?></span><?php endif; ?></span>
            </a>
        </h1>
        
        <!-- Overall Dashboard Statistics (All Events) -->
        <div style="margin: 20px 0;">
            <h2 style="font-size: 16px; color: #1d2327; margin: 0 0 15px 0; font-weight: 600;">
                📊 Overall Statistics (All Events)
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(34,113,177,0.3);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Total Registrations</div>
                    <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($overall_total); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;"><?php echo number_format($overall_tickets); ?> tickets</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(74,144,226,0.3);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Attendance</div>
                    <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($overall_attended); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;">
                        <?php echo $overall_total > 0 ? round(($overall_attended / $overall_total) * 100, 1) : 0; ?>% attendance rate
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #72a8d8 0%, #5a93c7 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(114,168,216,0.3);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Status</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($overall_confirmed); ?> Confirmed</div>
                    <div style="font-size: 12px; opacity: 0.8;">
                        <?php echo number_format($overall_pending); ?> Pending · <?php echo number_format($overall_cancelled); ?> Cancelled
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #9ec5e8 0%, #7fb3db 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(158,197,232,0.3);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Payment</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($overall_paid); ?> Paid</div>
                    <div style="font-size: 12px; opacity: 0.8;">
                        <?php echo number_format($overall_unpaid); ?> Unpaid
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($filter_event > 0): 
            // Get the filtered event name
            $filtered_event_name = '';
            foreach ($events as $event) {
                if ($event->ID == $filter_event) {
                    $filtered_event_name = $event->post_title;
                    break;
                }
            }
        ?>
        <!-- Filtered Event Dashboard Statistics -->
        <div style="margin: 20px 0;">
            <h2 style="font-size: 16px; color: #1d2327; margin: 0 0 15px 0; font-weight: 600;">
                🎯 <?php echo esc_html($filtered_event_name); ?> Statistics
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="background: white; color: #1d2327; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #2271b1;">
                    <div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">Total Registrations</div>
                    <div style="font-size: 32px; font-weight: 700; color: #2271b1;"><?php echo number_format($total_registrations); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.7;"><?php echo number_format($total_tickets); ?> tickets</div>
                </div>
                
                <div style="background: white; color: #1d2327; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #4a90e2;">
                    <div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">Attendance</div>
                    <div style="font-size: 32px; font-weight: 700; color: #4a90e2;"><?php echo number_format($total_attended); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.7;">
                        <?php echo $total_registrations > 0 ? round(($total_attended / $total_registrations) * 100, 1) : 0; ?>% attendance rate
                    </div>
                </div>
                
                <div style="background: white; color: #1d2327; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #72a8d8;">
                    <div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">Status</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #72a8d8;"><?php echo number_format($total_confirmed); ?> Confirmed</div>
                    <div style="font-size: 12px; opacity: 0.7;">
                        <?php echo number_format($total_pending); ?> Pending · <?php echo number_format($total_cancelled); ?> Cancelled
                    </div>
                </div>
                
                <div style="background: white; color: #1d2327; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #9ec5e8;">
                    <div style="font-size: 13px; opacity: 0.8; margin-bottom: 5px;">Payment</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #9ec5e8;"><?php echo number_format($total_paid); ?> Paid</div>
                    <div style="font-size: 12px; opacity: 0.7;">
                        <?php echo number_format($total_unpaid); ?> Unpaid
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Search and Filters -->
        <form method="get" action="<?php echo admin_url('admin.php'); ?>" style="margin: 20px 0;">
            <input type="hidden" name="page" value="event-registrations">
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <!-- Search Bar -->
                <div style="flex: 1; min-width: 250px;">
                    <input 
                        type="search" 
                        name="s" 
                        value="<?php echo esc_attr($search); ?>" 
                        placeholder="🔍 Search by name, email, phone, job, or event..." 
                        style="width: 100%; padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px;"
                    >
                </div>
                
                <!-- Event Filter with Improved Star Button -->
                <div style="display: flex; gap: 5px; align-items: center;">
                    <select name="filter_event" id="erp-event-filter" style="padding: 8px 12px; padding-right: 36px; border: 1px solid #8c8f94; border-radius: 4px; min-width: 200px; max-width: 350px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">
                        <option value="">All Events</option>
                        <?php 
                        $current_user_id = get_current_user_id();
                        foreach ($events as $event): 
                            $is_favorite = erp_is_favorite_event($event->ID, $current_user_id);
                            $star = $is_favorite ? '⭐ ' : '';
                        ?>
                            <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($filter_event, $event->ID); ?> data-event-id="<?php echo esc_attr($event->ID); ?>">
                                <?php echo $star . esc_html($event->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php 
                    $current_event_id = $filter_event > 0 ? $filter_event : 0;
                    $is_current_fav = $current_event_id > 0 ? erp_is_favorite_event($current_event_id, $current_user_id) : false;
                    $button_disabled = $current_event_id == 0;
                    ?>
                    <button type="button" 
                            id="erp-star-toggle-btn"
                            class="erp-favorite-toggle <?php echo $is_current_fav ? 'is-favorite' : ''; ?>"
                            data-event-id="<?php echo esc_attr($current_event_id); ?>"
                            title="<?php echo $button_disabled ? 'Select an event to favorite' : ($is_current_fav ? 'Remove from My Events' : 'Add to My Events'); ?>"
                            <?php echo $button_disabled ? 'disabled' : ''; ?>
                            style="background: <?php echo $is_current_fav ? 'linear-gradient(135deg, #ffc107 0%, #ff9800 100%)' : '#fff'; ?>; 
                                   border: 2px solid <?php echo $is_current_fav ? '#ffc107' : '#ddd'; ?>; 
                                   cursor: <?php echo $button_disabled ? 'not-allowed' : 'pointer'; ?>; 
                                   padding: 8px 12px; 
                                   border-radius: 6px; 
                                   transition: all 0.3s ease;
                                   box-shadow: <?php echo $is_current_fav ? '0 2px 8px rgba(255, 193, 7, 0.3)' : '0 1px 3px rgba(0,0,0,0.1)'; ?>;
                                   min-width: 44px;
                                   height: 38px;
                                   display: flex;
                                   align-items: center;
                                   justify-content: center;">
                        <span class="dashicons <?php echo $is_current_fav ? 'dashicons-star-filled' : 'dashicons-star-empty'; ?>" 
                              style="color: <?php echo $is_current_fav ? '#fff' : '#ffc107'; ?>; 
                                     font-size: 20px; 
                                     width: 20px; 
                                     height: 20px;
                                     transition: all 0.3s ease;"></span>
                    </button>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <select name="filter_status" style="padding: 8px 36px 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; max-width: 200px; text-overflow: ellipsis; overflow: hidden;">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($filter_status, $status); ?>>
                                <?php echo esc_html(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Payment Filter -->
                <div>
                    <select name="filter_payment" style="padding: 8px 36px 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; max-width: 200px; text-overflow: ellipsis; overflow: hidden;">
                        <option value="">All Payments</option>
                        <?php foreach ($payment_statuses as $payment_status): ?>
                            <option value="<?php echo esc_attr($payment_status); ?>" <?php selected($filter_payment, $payment_status); ?>>
                                <?php echo esc_html(ucfirst($payment_status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Attendance Filter -->
                <div>
                    <select name="filter_attendance" style="padding: 8px 36px 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; max-width: 200px; text-overflow: ellipsis; overflow: hidden;">
                        <option value="">All Attendance</option>
                        <option value="attended" <?php selected($filter_attendance, 'attended'); ?>>✓ Present</option>
                        <option value="not_attended" <?php selected($filter_attendance, 'not_attended'); ?>>✗ Absent</option>
                    </select>
                </div>
                
                <!-- Filter Button -->
                <div>
                    <button type="submit" class="button button-primary" style="height: 36px;">
                        Filter
                    </button>
                </div>
                
                <!-- Clear Filters -->
                <?php if ($search || $filter_event || $filter_status || $filter_payment || $filter_attendance): ?>
                    <div>
                        <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>" class="button" style="height: 36px; line-height: 34px;">
                            Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Quick Favorites Section -->
        <?php if (!empty($user_favorites) && count($user_favorites) > 0): ?>
        <div style="background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 16px;">
                    <span class="dashicons dashicons-star-filled" style="color: #ffc107; font-size: 24px;"></span>
                    Quick Access: My Favorite Events
                </h3>
                <span style="background: rgba(255,255,255,0.2); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                    <?php echo count($user_favorites); ?> event<?php echo count($user_favorites) !== 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($events as $event): 
                    if (in_array($event->ID, $user_favorites)):
                        // Get stats for this event
                        $event_regs = array_filter($all_registrations, function($reg) use ($event) {
                            return $reg->event_id == $event->ID;
                        });
                        $event_count = count($event_regs);
                ?>
                <a href="<?php echo admin_url('admin.php?page=event-registrations&filter_event=' . $event->ID); ?>" 
                   class="erp-favorite-chip"
                   style="background: rgba(255,255,255,0.95); 
                          color: #1d2327; 
                          padding: 10px 16px; 
                          border-radius: 20px; 
                          text-decoration: none; 
                          display: flex; 
                          align-items: center; 
                          gap: 8px;
                          transition: all 0.3s ease;
                          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                          border: 2px solid transparent;">
                    <span style="color: #ffc107; font-size: 16px;">⭐</span>
                    <span style="font-weight: 600; font-size: 14px;"><?php echo esc_html($event->post_title); ?></span>
                    <span style="background: #2271b1; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700;">
                        <?php echo $event_count; ?>
                    </span>
                </a>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
            <p style="margin: 15px 0 0 0; color: rgba(255,255,255,0.9); font-size: 12px;">
                💡 Click any event to view its registrations, or visit <a href="<?php echo admin_url('admin.php?page=event-my-events'); ?>" style="color: #ffc107; text-decoration: underline;">My Events dashboard</a> for detailed statistics
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Bulk Certificate ZIP Import -->
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; display: none;" id="bulk-import-section">
            <h2 style="margin-top: 0; color: #1d2327; font-size: 16px;">
                <span class="dashicons dashicons-media-archive" style="color: #2271b1;"></span>
                Bulk Import Certificates (ZIP)
            </h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                <input type="hidden" name="action" value="erp_import_certificates_zip">
                <?php wp_nonce_field('erp_import_certificates_zip'); ?>
                
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Select Event:</label>
                    <select name="event_id" required style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                        <option value="">Choose an event...</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo esc_attr($event->ID); ?>">
                                <?php echo esc_html($event->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="flex: 1; min-width: 250px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Upload ZIP file (max 10MB):</label>
                    <input type="file" name="certificates_zip" accept=".zip" required style="padding: 4px; border: 1px solid #8c8f94; border-radius: 4px; width: 100%;">
                </div>
                
                <div>
                    <button type="submit" class="button button-primary" style="height: 36px;">
                        <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
                        Import Certificates
                    </button>
                </div>
            </form>
            <div style="margin: 15px 0 0 0; padding: 12px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 4px;">
                <p style="margin: 0 0 8px 0; color: #1d2327; font-size: 13px; font-weight: 600;">
                    <span class="dashicons dashicons-info" style="color: #2271b1; font-size: 16px; vertical-align: middle;"></span>
                    Filename Matching Rules:
                </p>
                <ul style="margin: 0; padding-left: 25px; color: #646970; font-size: 13px; line-height: 1.8;">
                    <li><strong>By Email:</strong> john@example.com.pdf or john.pdf</li>
                    <li><strong>By Name:</strong> John-Doe.pdf, JohnDoe.pdf, or john-doe.pdf</li>
                    <li><strong>Flexible matching:</strong> Works with spaces, hyphens, or no separators</li>
                    <li><strong>Case insensitive:</strong> JOHN.pdf = john.pdf = John.pdf</li>
                </ul>
                <p style="margin: 8px 0 0 0; color: #646970; font-size: 12px; font-style: italic;">
                    💡 Tip: After import, you'll see which files were matched to which attendees
                </p>
            </div>
        </div>
        
        <!-- Results Count -->
        <p style="margin: 15px 0; color: #646970;">
            <strong><?php echo count($registrations); ?></strong> registration(s) found
            <?php if ($search || $filter_event || $filter_status || $filter_payment || $filter_attendance): ?>
                (filtered)
            <?php endif; ?>
        </p>
        
        <?php if (empty($registrations)): ?>
            <div class="notice notice-info">
                <p>
                    <?php if ($search || $filter_event || $filter_status || $filter_payment || $filter_attendance): ?>
                        No registrations match your filters. <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>">Clear filters</a> to see all registrations.
                    <?php else: ?>
                        No registrations yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Bulk Actions Form -->
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="bulk-action-form">
                <input type="hidden" name="action" value="erp_bulk_actions">
                <?php wp_nonce_field('erp_bulk_actions'); ?>
                
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px; background: #fff; padding: 10px; border: 1px solid #ccd0d4; border-radius: 4px; flex-wrap: wrap;">
                    <label style="font-weight: 600;">Bulk Actions:</label>
                    <select name="bulk_action" required style="padding: 6px; border: 1px solid #8c8f94; border-radius: 4px;">
                        <option value="">-- Select Action --</option>
                        <option value="generate_certificates">📄 Generate Certificates</option>
                        <option value="send_notifications">📧 Send Notifications</option>
                        <option value="send_certificates">📜 Send Certificates</option>
                        <option value="send_invitations">🎟️ Send Invitations</option>
                        <option value="mark_attended">✓ Mark as Present</option>
                        <option value="unmark_attended">✗ Remove Attendance</option>
                    </select>
                    <button type="submit" class="button button-primary" onclick="return confirmBulkAction();">Apply to Selected</button>
                    <span style="color: #646970; font-size: 13px; margin-left: 10px;" id="selected-count">0 selected</span>
                    
                    <div style="margin-left: auto; display: flex; gap: 5px; align-items: center;">
                        <span style="color: #646970; font-size: 13px; margin-right: 5px;">Quick Select:</span>
                        <button type="button" class="button button-small" onclick="erpSelectByAttendance('attended')" style="background: #d4edda; border-color: #28a745; color: #155724;">
                            ✓ All Present
                        </button>
                        <button type="button" class="button button-small" onclick="erpSelectByAttendance('not_attended')" style="background: #f8d7da; border-color: #dc3545; color: #721c24;">
                            ✗ All Absent
                        </button>
                        <button type="button" class="button button-small" onclick="erpSelectByAttendance('all')">
                            Select All
                        </button>
                        <button type="button" class="button button-small" onclick="erpSelectByAttendance('none')">
                            Deselect All
                        </button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all" /></th>
                            <th><?php echo erp_sortable_column('id', 'ID', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('attendee_name', 'Attendee Name', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('email', 'Email', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('phone', 'Phone', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('job', 'Job', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('tickets', 'Tickets', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('registration_date', 'Registration Date', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('status', 'Status', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('payment', 'Payment', $orderby, $order); ?></th>
                            <th><?php echo erp_sortable_column('attendance', 'Attendance', $orderby, $order); ?></th>
                            <th>Sent</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <?php $attendance_status = $reg->attendance_status ?? 'not_attended'; ?>
                            <tr>
                                <td><input type="checkbox" name="registration_ids[]" value="<?php echo esc_attr($reg->id); ?>" class="reg-checkbox" data-attendance="<?php echo esc_attr($attendance_status); ?>" /></td>
                                <td><?php echo esc_html($reg->id); ?></td>
                                <td><?php echo esc_html(wp_unslash($reg->attendee_name)); ?><?php if (!$filter_event): ?>
                                    <br><small style="color: #646970;"><?php echo esc_html($reg->event_name); ?></small><?php endif; ?>
                                </td>
                                <td><?php echo esc_html($reg->attendee_email); ?></td>
                                <td><?php echo esc_html($reg->attendee_phone); ?></td>
                                <td><?php echo esc_html($reg->attendee_job ? wp_unslash($reg->attendee_job) : '-'); ?></td>
                                <td><?php echo esc_html($reg->ticket_quantity); ?></td>
                                <td><?php echo esc_html(date_i18n('F j, Y g:i A', strtotime($reg->registration_date))); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($reg->status); ?>">
                                        <?php echo esc_html(ucfirst($reg->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-<?php echo esc_attr($reg->payment_status ?? 'pending'); ?>">
                                        <?php echo esc_html(ucfirst($reg->payment_status ?? 'pending')); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $attendance_status = $reg->attendance_status ?? 'not_attended';
                                    if ($attendance_status === 'attended'):
                                        $method = $reg->check_in_method === 'qr_code' ? 'QR Code' : 'Manual';
                                        if ($reg->attendance_time):
                                            $attendance_time = mysql2date('M j, g:i A', $reg->attendance_time);
                                    ?>
                                        <span style="color: #28a745; font-weight: 600;">✓ Present</span><br>
                                        <small style="color: #646970; font-size: 11px;"><?php echo esc_html($attendance_time); ?></small><br>
                                        <small style="color: #646970; font-size: 10px;">(<?php echo esc_html($method); ?>)</small>
                                        <?php else: ?>
                                        <span style="color: #28a745; font-weight: 600;" title="Method: <?php echo esc_attr($method); ?>">✓ Present</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #dc3545;" title="Not yet checked in">✗ Absent</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; line-height: 1.6;">
                                    <?php 
                                    // Notification
                                    if ($reg->notification_sent): ?>
                                        <span style="color: #28a745;" title="Notification sent: <?php echo esc_attr(mysql2date('M j, Y g:i A', $reg->notification_sent)); ?>">✓ Notif</span><br>
                                    <?php else: ?>
                                        <span style="color: #f0ad4e;" title="Notification not sent yet">⏳ Notif</span><br>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Invitation
                                    if ($reg->invitation_sent): ?>
                                        <span style="color: #28a745;" title="Invitation sent: <?php echo esc_attr(mysql2date('M j, Y g:i A', $reg->invitation_sent)); ?>">✓ Invit</span><br>
                                    <?php else: ?>
                                        <span style="color: #f0ad4e;" title="Invitation not sent yet">⏳ Invit</span><br>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Certificate
                                    $cert_pdf = get_option('erp_certificate_pdf_' . $reg->id);
                                    if ($cert_pdf): 
                                        if ($reg->certificate_sent): ?>
                                            <span style="color: #28a745;" title="Certificate sent: <?php echo esc_attr(mysql2date('M j, Y g:i A', $reg->certificate_sent)); ?>">✓ Cert</span>
                                        <?php else: ?>
                                            <span style="color: #f0ad4e;" title="Certificate not sent yet">⏳ Cert</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #dc3545;" title="No certificate uploaded">— Cert</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="erp-actions-menu" style="position: relative; display: inline-block;">
                                        <button type="button" class="button erp-menu-toggle" data-reg-id="<?php echo esc_attr($reg->id); ?>" style="padding: 5px 10px;">
                                            <span class="dashicons dashicons-menu" style="font-size: 16px; line-height: 1.2;"></span>
                                        </button>
                                        <div class="erp-dropdown-menu" id="menu-<?php echo esc_attr($reg->id); ?>" style="display: none; position: absolute; right: 0; background: white; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); min-width: 200px; z-index: 1000;">
                                            <a href="#" class="erp-menu-item" onclick="erpEditRegistration(<?php echo esc_js($reg->id); ?>, '<?php echo esc_js(wp_unslash($reg->attendee_name)); ?>', '<?php echo esc_js($reg->attendee_email); ?>', '<?php echo esc_js($reg->attendee_phone); ?>', '<?php echo esc_js($reg->attendee_job ? wp_unslash($reg->attendee_job) : ''); ?>', <?php echo esc_js($reg->ticket_quantity); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-edit" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Edit
                                            </a>
                                            <a href="#" class="erp-menu-item" onclick="erpViewNotification(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-email" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> View Notification
                                            </a>
                                            <a href="#" class="erp-menu-item" onclick="erpViewCertificate(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-media-document" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> View Certificate
                                            </a>

                                            <a href="#" class="erp-menu-item" onclick="erpUploadCertificate(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-upload" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Upload Certificate PDF
                                            </a>
                                            <?php if (get_option('erp_certificate_pdf_' . $reg->id)): ?>
                                            <a href="#" class="erp-menu-item" onclick="erpSendCertificateIndividual(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #00a32a; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-email-alt" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Send Certificate Now
                                            </a>
                                            <?php endif; ?>
                                            <a href="#" class="erp-menu-item" onclick="erpViewInvitation(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-tickets-alt" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> View Invitation
                                            </a>
                                            <a href="#" class="erp-menu-item" onclick="erpSendInvitationIndividual(<?php echo esc_js($reg->id); ?>); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #00a32a; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-email-alt" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Send Invitation Now
                                            </a>
                                            <?php
                                            // Attendance options
                                            $attended = ($reg->attendance_status ?? 'not_attended') === 'attended';
                                            $checkin_code = md5($reg->id . $reg->attendee_email);
                                            $checkin_url = home_url('/event-checkin?reg_id=' . $reg->id . '&code=' . $checkin_code);
                                            ?>
                                            <a href="<?php echo esc_url($checkin_url); ?>" target="_blank" class="erp-menu-item" style="display: block; padding: 10px 15px; text-decoration: none; color: #2271b1; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-smartphone" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> View Check-In Link
                                            </a>
                                            <?php if (!$attended): ?>
                                            <a href="#" class="erp-menu-item" onclick="erpMarkAttendance(<?php echo esc_js($reg->id); ?>, '<?php echo esc_js(wp_unslash($reg->attendee_name)); ?>'); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #00a32a; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Mark as Present
                                            </a>
                                            <?php else: ?>
                                            <a href="#" class="erp-menu-item" onclick="erpUnmarkAttendance(<?php echo esc_js($reg->id); ?>, '<?php echo esc_js(wp_unslash($reg->attendee_name)); ?>'); return false;" style="display: block; padding: 10px 15px; text-decoration: none; color: #d63638; border-bottom: 1px solid #f0f0f1;">
                                                <span class="dashicons dashicons-no" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Remove Attendance
                                            </a>
                                            <?php endif; ?>
                                            <?php
                                            $delete_url = wp_nonce_url(
                                                admin_url('admin-post.php?action=erp_delete_registration&registration_id=' . $reg->id),
                                                'delete_registration_' . $reg->id
                                            );
                                            ?>
                                            <a href="<?php echo esc_url($delete_url); ?>" class="erp-menu-item" style="display: block; padding: 10px 15px; text-decoration: none; color: #d63638;" onclick="return confirm('Are you sure you want to delete this registration?\\n\\nAttendee: <?php echo esc_js(wp_unslash($reg->attendee_name)); ?>\\nEvent: <?php echo esc_js($reg->event_name); ?>\\n\\nThis action cannot be undone.');">
                                                <span class="dashicons dashicons-trash" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <!-- Certificate Template Editor Button -->
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 15px; margin: 20px 0;">
                <a href="<?php echo admin_url('admin.php?page=event-certificate-template'); ?>" class="button button-primary" style="height: auto; line-height: normal; padding: 10px 20px;">
                    <span class="dashicons dashicons-edit" style="vertical-align: middle; margin-top: -2px;"></span>
                    Edit Certificate Template
                </a>
                <p style="margin: 10px 0 0 0; color: #646970; font-size: 13px;">Customize the design, colors, and layout of auto-generated certificates.</p>
            </div>
            
            <!-- Bulk Certificate ZIP Import (Moved Below Table) -->
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
                <h2 style="margin-top: 0; color: #1d2327; font-size: 16px;">
                    <span class="dashicons dashicons-media-archive" style="color: #2271b1;"></span>
                    Bulk Import Certificates (ZIP)
                </h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="action" value="erp_import_certificates_zip">
                    <?php wp_nonce_field('erp_import_certificates_zip'); ?>
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Select Event:</label>
                        <select name="event_id" required style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;">
                            <option value="">Choose an event...</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo esc_attr($event->ID); ?>">
                                    <?php echo esc_html($event->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="flex: 1; min-width: 250px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Upload ZIP file (max 10MB):</label>
                        <input type="file" name="certificates_zip" accept=".zip" required style="padding: 4px; border: 1px solid #8c8f94; border-radius: 4px; width: 100%;">
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary" style="height: 36px;">
                            <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
                            Import Certificates
                        </button>
                    </div>
                </form>
                <div style="margin: 15px 0 0 0; padding: 12px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 4px;">
                    <p style="margin: 0 0 8px 0; color: #1d2327; font-size: 13px; font-weight: 600;">
                        <span class="dashicons dashicons-info" style="color: #2271b1; font-size: 16px; vertical-align: middle;"></span>
                        Filename Matching Rules:
                    </p>
                    <ul style="margin: 0; padding-left: 25px; color: #646970; font-size: 13px; line-height: 1.8;">
                        <li>Match by <strong>email</strong>: certificate-<em>email@example.com</em>.pdf</li>
                        <li>Match by <strong>name</strong>: certificate-<em>John_Doe</em>.pdf or <em>John_Doe</em>-certificate.pdf</li>
                        <li>Match by <strong>ID</strong>: certificate-<em>7</em>.pdf</li>
                    </ul>
                </div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('select-all');
                const checkboxes = document.querySelectorAll('.reg-checkbox');
                const selectedCount = document.getElementById('selected-count');
                
                function updateCount() {
                    const count = document.querySelectorAll('.reg-checkbox:checked').length;
                    selectedCount.textContent = count + ' selected';
                }
                
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateCount();
                });
                
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', updateCount);
                });
            });
            
            // Quick select by attendance status
            function erpSelectByAttendance(filter) {
                const checkboxes = document.querySelectorAll('.reg-checkbox');
                const selectedCount = document.getElementById('selected-count');
                
                checkboxes.forEach(cb => {
                    if (filter === 'all') {
                        cb.checked = true;
                    } else if (filter === 'none') {
                        cb.checked = false;
                    } else if (filter === 'attended') {
                        cb.checked = cb.getAttribute('data-attendance') === 'attended';
                    } else if (filter === 'not_attended') {
                        cb.checked = cb.getAttribute('data-attendance') === 'not_attended';
                    }
                });
                
                // Update count
                const count = document.querySelectorAll('.reg-checkbox:checked').length;
                selectedCount.textContent = count + ' selected';
                
                // Update select-all checkbox state
                const selectAll = document.getElementById('select-all');
                const totalCheckboxes = checkboxes.length;
                const checkedCheckboxes = document.querySelectorAll('.reg-checkbox:checked').length;
                selectAll.checked = checkedCheckboxes === totalCheckboxes;
            }
            
            function confirmBulkAction() {
                const selected = document.querySelectorAll('.reg-checkbox:checked').length;
                if (selected === 0) {
                    alert('Please select at least one registration.');
                    return false;
                }
                
                const action = document.querySelector('select[name="bulk_action"]').value;
                if (!action) {
                    alert('Please select a bulk action.');
                    return false;
                }
                
                let actionText = '';
                if (action === 'send_notifications') actionText = 'notifications';
                else if (action === 'send_certificates') actionText = 'certificates';
                else if (action === 'send_invitations') actionText = 'invitations';
                
                return confirm('Send ' + actionText + ' to ' + selected + ' selected registration(s)?');
            }
            
            // Hamburger menu toggle
            document.addEventListener('click', function(e) {
                if (e.target.closest('.erp-menu-toggle')) {
                    e.preventDefault();
                    const button = e.target.closest('.erp-menu-toggle');
                    const regId = button.getAttribute('data-reg-id');
                    const menu = document.getElementById('menu-' + regId);
                    
                    // Close all other menus
                    document.querySelectorAll('.erp-dropdown-menu').forEach(m => {
                        if (m.id !== 'menu-' + regId) m.style.display = 'none';
                    });
                    
                    // Toggle this menu
                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                }
                
                // Close menu when clicking outside
                if (!e.target.closest('.erp-actions-menu')) {
                    document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                }
            });
            
            // Edit Registration
            function erpEditRegistration(regId, name, email, phone, job, tickets) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                const html = `
                    <form id="edit-registration-form" style="padding: 20px;">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                Attendee Name: <span style="color: #d63638;">*</span>
                            </label>
                            <input type="text" id="edit_name" value="${name}" required
                                   style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                   placeholder="Enter attendee name">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                Email: <span style="color: #d63638;">*</span>
                            </label>
                            <input type="email" id="edit_email" value="${email}" required
                                   style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                   placeholder="Enter email address">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                Phone:
                            </label>
                            <input type="text" id="edit_phone" value="${phone}" 
                                   style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                   placeholder="Enter phone number">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                Job/Position:
                            </label>
                            <input type="text" id="edit_job" value="${job}" 
                                   style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                   placeholder="Enter job or position">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                Number of Tickets: <span style="color: #d63638;">*</span>
                            </label>
                            <input type="number" id="edit_tickets" value="${tickets}" required min="1"
                                   style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                   placeholder="Enter number of tickets">
                        </div>
                        
                        <div style="text-align: right; padding-top: 15px; border-top: 1px solid #ddd;">
                            <button type="button" class="button" onclick="jQuery('#erp-modal').hide();" style="margin-right: 10px;">Cancel</button>
                            <button type="submit" class="button button-primary">Save Changes</button>
                        </div>
                    </form>
                `;
                
                showModal('Edit Registration', html);
                
                // Handle form submission
                jQuery('#edit-registration-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const btn = jQuery(this).find('button[type="submit"]');
                    btn.prop('disabled', true).text('Saving...');
                    
                    jQuery.post(ajaxurl, {
                        action: 'erp_update_registration',
                        registration_id: regId,
                        attendee_name: jQuery('#edit_name').val(),
                        attendee_email: jQuery('#edit_email').val(),
                        attendee_phone: jQuery('#edit_phone').val(),
                        attendee_job: jQuery('#edit_job').val(),
                        num_tickets: jQuery('#edit_tickets').val(),
                        _wpnonce: '<?php echo wp_create_nonce("erp_update_registration"); ?>'
                    }, function(response) {
                        btn.prop('disabled', false).text('Save Changes');
                        if (response.success) {
                            alert('Registration updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    });
                });
            }
            
            // Mark Attendance
            function erpMarkAttendance(regId, attendeeName) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                if (!confirm('Mark "' + attendeeName + '" as present?')) {
                    return;
                }
                
                jQuery.post(ajaxurl, {
                    action: 'erp_mark_attendance',
                    registration_id: regId,
                    _wpnonce: '<?php echo wp_create_nonce("erp_mark_attendance"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Attendance marked successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // Unmark Attendance
            function erpUnmarkAttendance(regId, attendeeName) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                if (!confirm('Remove attendance record for "' + attendeeName + '"?')) {
                    return;
                }
                
                jQuery.post(ajaxurl, {
                    action: 'erp_unmark_attendance',
                    registration_id: regId,
                    _wpnonce: '<?php echo wp_create_nonce("erp_unmark_attendance"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Attendance removed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // View Notification
            function erpViewNotification(regId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                jQuery.post(ajaxurl, {
                    action: 'erp_get_registration_data',
                    registration_id: regId,
                    data_type: 'notification',
                    _wpnonce: '<?php echo wp_create_nonce("erp_get_registration_data"); ?>'
                }, function(response) {
                    if (response.success) {
                        showModal('Notification Preview', response.data.content);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // View Certificate
            function erpViewCertificate(regId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                jQuery.post(ajaxurl, {
                    action: 'erp_get_registration_data',
                    registration_id: regId,
                    data_type: 'certificate',
                    _wpnonce: '<?php echo wp_create_nonce("erp_get_registration_data"); ?>'
                }, function(response) {
                    if (response.success) {
                        showModal('Certificate Preview', response.data.content);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // View Invitation
            function erpViewInvitation(regId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                jQuery.post(ajaxurl, {
                    action: 'erp_get_registration_data',
                    registration_id: regId,
                    data_type: 'invitation',
                    _wpnonce: '<?php echo wp_create_nonce("erp_get_registration_data"); ?>'
                }, function(response) {
                    if (response.success) {
                        showModal('Invitation Preview', response.data.content);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // Edit Invitation
            function erpEditInvitation(regId, eventId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                // Get current invitation data
                jQuery.post(ajaxurl, {
                    action: 'erp_get_invitation_edit_data',
                    registration_id: regId,
                    event_id: eventId,
                    _wpnonce: '<?php echo wp_create_nonce("erp_get_invitation_edit_data"); ?>'
                }, function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        const html = `
                            <form id="edit-invitation-form">
                                <input type="hidden" name="registration_id" value="${regId}">
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                        Event Location / Venue:
                                    </label>
                                    <input type="text" name="event_location" id="event_location" value="${data.event_location || ''}" 
                                           style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                           placeholder="e.g., Grand Hall, Building A">
                                    <p style="color: #646970; font-size: 13px; margin: 5px 0 0 0;">
                                        Physical location for in-person events
                                    </p>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                        <span class="dashicons dashicons-video-alt3" style="color: #2271b1;"></span>
                                        Zoom Link (for Webinars):
                                    </label>
                                    <input type="url" name="zoom_link" id="zoom_link" value="${data.zoom_link || ''}" 
                                           style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                           placeholder="https://zoom.us/j/123456789">
                                    <p style="color: #646970; font-size: 13px; margin: 5px 0 0 0;">
                                        Zoom meeting link for online events
                                    </p>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327;">
                                        Additional Notes:
                                    </label>
                                    <textarea name="invitation_notes" id="invitation_notes" rows="4" 
                                              style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px;" 
                                              placeholder="Any additional information for this attendee...">${data.invitation_notes || ''}</textarea>
                                    <p style="color: #646970; font-size: 13px; margin: 5px 0 0 0;">
                                        Optional custom message for this specific attendee
                                    </p>
                                </div>
                                
                                <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                                    <p style="margin: 0; color: #1d2327; font-size: 13px; line-height: 1.6;">
                                        <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                                        <strong>Note:</strong> This information will be included in the invitation email sent to the attendee.
                                        For webinars, the Zoom link will be prominently displayed.
                                    </p>
                                </div>
                                
                                <div style="text-align: right;">
                                    <button type="button" class="button" onclick="closeModal()" style="margin-right: 10px;">Cancel</button>
                                    <button type="submit" class="button button-primary">Save Invitation</button>
                                </div>
                            </form>
                        `;
                        
                        showModal('Edit Invitation Details', html);
                        
                        // Handle form submission
                        document.getElementById('edit-invitation-form').addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const formData = new FormData(this);
                            formData.append('action', 'erp_save_invitation_edit');
                            formData.append('_wpnonce', '<?php echo wp_create_nonce("erp_save_invitation_edit"); ?>');
                            
                            jQuery.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: jQuery(this).serialize() + '&action=erp_save_invitation_edit&_wpnonce=<?php echo wp_create_nonce("erp_save_invitation_edit"); ?>',
                                success: function(response) {
                                    if (response.success) {
                                        alert('Invitation details saved successfully!');
                                        closeModal();
                                        location.reload();
                                    } else {
                                        alert('Error: ' + response.data.message);
                                    }
                                },
                                error: function() {
                                    alert('Save failed. Please try again.');
                                }
                            });
                        });
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                });
            }
            
            // Upload Certificate PDF
            function erpUploadCertificate(regId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                const html = `
                    <form id="upload-cert-form" enctype="multipart/form-data">
                        <input type="hidden" name="registration_id" value="${regId}">
                        <p><strong>Upload Certificate PDF (Max 2MB)</strong></p>
                        <input type="file" name="certificate_pdf" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; margin: 10px 0;">
                        <p style="color: #646970; font-size: 13px;">Only PDF files up to 2MB are allowed.</p>
                        <button type="submit" class="button button-primary">Upload</button>
                        <button type="button" class="button" onclick="closeModal()">Cancel</button>
                    </form>
                `;
                
                showModal('Upload Certificate PDF', html);
                
                document.getElementById('upload-cert-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'erp_upload_certificate_pdf');
                    formData.append('_wpnonce', '<?php echo wp_create_nonce("erp_upload_certificate_pdf"); ?>');
                    
                    const fileInput = this.querySelector('input[type="file"]');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        alert('Please select a file');
                        return;
                    }
                    
                    if (file.size > 2 * 1024 * 1024) {
                        alert('File size must be less than 2MB');
                        return;
                    }
                    
                    if (file.type !== 'application/pdf') {
                        alert('Only PDF files are allowed');
                        return;
                    }
                    
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Certificate uploaded successfully!');
                                closeModal();
                                location.reload();
                            } else {
                                alert('Error: ' + response.data.message);
                            }
                        },
                        error: function() {
                            alert('Upload failed. Please try again.');
                        }
                    });
                });
            }
            
            // Send Certificate Individual
            function erpSendCertificateIndividual(regId) {
                console.log('erpSendCertificateIndividual called with ID:', regId);
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                if (!confirm('Send certificate email to this attendee now?')) {
                    console.log('User cancelled');
                    return;
                }
                
                console.log('Preparing AJAX request...');
                const button = document.querySelector('[data-reg-id="' + regId + '"]');
                const originalHTML = button ? button.innerHTML : '';
                if (button) button.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>';
                
                console.log('Sending AJAX to:', ajaxurl);
                jQuery.post(ajaxurl, {
                    action: 'erp_send_certificate_individual',
                    registration_id: regId,
                    _wpnonce: '<?php echo wp_create_nonce("erp_send_certificate_individual"); ?>'
                }, function(response) {
                    console.log('AJAX response received:', response);
                    if (button) button.innerHTML = originalHTML;
                    
                    if (response.success) {
                        alert('✓ Certificate sent successfully!');
                        console.log('Redirecting to admin page...');
                        // Reload to admin page with cache-busting
                        location.href = '<?php echo admin_url("admin.php?page=event-registrations"); ?>&_=' + Date.now();
                    } else {
                        console.error('AJAX error:', response.data.message);
                        alert('Error: ' + response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX failed:', status, error, xhr);
                    if (button) button.innerHTML = originalHTML;
                    alert('Failed to send certificate. Please try again.');
                });
            }
            
            // Send Invitation Individual
            function erpSendInvitationIndividual(regId) {
                document.querySelectorAll('.erp-dropdown-menu').forEach(m => m.style.display = 'none');
                
                if (!confirm('Send invitation email with event details to this attendee now?')) {
                    return;
                }
                
                const button = document.querySelector('[data-reg-id="' + regId + '"]');
                const originalHTML = button ? button.innerHTML : '';
                if (button) button.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>';
                
                jQuery.post(ajaxurl, {
                    action: 'erp_send_invitation_individual',
                    registration_id: regId,
                    _wpnonce: '<?php echo wp_create_nonce("erp_send_invitation_individual"); ?>'
                }, function(response) {
                    if (button) button.innerHTML = originalHTML;
                    
                    if (response.success) {
                        alert('✓ Invitation sent successfully!');
                        // Reload to admin page with cache-busting
                        location.href = '<?php echo admin_url("admin.php?page=event-registrations"); ?>&_=' + Date.now();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }).fail(function() {
                    if (button) button.innerHTML = originalHTML;
                    alert('Failed to send invitation. Please try again.');
                });
            }
            
            // Show modal
            function showModal(title, content) {
                let modal = document.getElementById('erp-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'erp-modal';
                    modal.innerHTML = `
                        <div class="erp-modal-overlay" onclick="closeModal()"></div>
                        <div class="erp-modal-content">
                            <div class="erp-modal-header">
                                <h2 id="erp-modal-title"></h2>
                                <button class="erp-modal-close" onclick="closeModal()">&times;</button>
                            </div>
                            <div class="erp-modal-body" id="erp-modal-body"></div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
                
                document.getElementById('erp-modal-title').textContent = title;
                document.getElementById('erp-modal-body').innerHTML = content;
                modal.style.display = 'block';
            }
            
            // Close modal
            function closeModal() {
                const modal = document.getElementById('erp-modal');
                if (modal) modal.style.display = 'none';
            }
            </script>
            
            <style>
                .status-pending { color: #f0ad4e; }
                .status-confirmed { color: #28a745; }
                .status-cancelled { color: #dc3545; }
                .payment-pending { color: #f0ad4e; font-weight: 600; }
                .payment-paid { color: #28a745; font-weight: 600; }
                .payment-failed { color: #dc3545; font-weight: 600; }
                .payment-free { color: #17a2b8; font-weight: 600; }
                .delete-registration:hover {
                    color: #d63638 !important;
                    border-color: #d63638 !important;
                }
                .erp-menu-item:hover {
                    background: #f6f7f7;
                }
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                #erp-modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 100000;
                }
                .erp-modal-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                }
                .erp-modal-content {
                    position: relative;
                    background: white;
                    max-width: 800px;
                    margin: 50px auto;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }
                .erp-modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #ddd;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .erp-modal-header h2 {
                    margin: 0;
                    font-size: 20px;
                }
                .erp-modal-close {
                    background: none;
                    border: none;
                    font-size: 28px;
                    cursor: pointer;
                    color: #666;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    line-height: 28px;
                }
                .erp-modal-close:hover {
                    color: #d63638;
                }
                .erp-modal-body {
                    padding: 20px;
                    overflow-y: auto;
                    flex: 1;
                }
            </style>
        <?php endif; ?>
        
        <!-- Enhanced AJAX Script for Favorite Toggle with Animations -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Update star button when event dropdown changes
            $('#erp-event-filter').on('change', function() {
                var selectedEventId = $(this).val();
                var starButton = $('#erp-star-toggle-btn');
                
                if (selectedEventId) {
                    var selectedOption = $(this).find('option:selected');
                    var isFavorite = selectedOption.text().startsWith('⭐');
                    
                    starButton.prop('disabled', false);
                    starButton.data('event-id', selectedEventId);
                    starButton.css('cursor', 'pointer');
                    updateStarButton(starButton, isFavorite);
                    starButton.attr('title', isFavorite ? 'Remove from My Events' : 'Add to My Events');
                } else {
                    starButton.prop('disabled', true);
                    starButton.data('event-id', '0');
                    starButton.css('cursor', 'not-allowed');
                    starButton.removeClass('is-favorite');
                    starButton.css({
                        'background': '#fff',
                        'border-color': '#ddd',
                        'box-shadow': '0 1px 3px rgba(0,0,0,0.1)'
                    });
                    starButton.find('.dashicons').removeClass('dashicons-star-filled').addClass('dashicons-star-empty').css('color', '#ffc107');
                    starButton.attr('title', 'Select an event to favorite');
                }
            });
            
            // Handle favorite toggle with animation
            $(document).on('click', '.erp-favorite-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var button = $(this);
                var eventId = button.data('event-id');
                
                if (!eventId || eventId == '0') {
                    return;
                }
                
                var isFavorite = button.hasClass('is-favorite');
                
                // Disable button and add loading state
                button.prop('disabled', true);
                button.css('opacity', '0.6');
                
                // Add bounce animation
                button.find('.dashicons').css({
                    'animation': 'erp-star-bounce 0.5s ease',
                    'transform-origin': 'center'
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'erp_toggle_favorite_event',
                        nonce: '<?php echo wp_create_nonce('erp_favorite_nonce'); ?>',
                        event_id: eventId,
                        is_favorite: !isFavorite
                    },
                    success: function(response) {
                        if (response.success) {
                            var newFavoriteState = response.data.is_favorite;
                            
                            // Update button state with animation
                            updateStarButton(button, newFavoriteState);
                            button.attr('title', newFavoriteState ? 'Remove from My Events' : 'Add to My Events');
                            
                            // Update dropdown option
                            var option = $('#erp-event-filter option[value="' + eventId + '"]');
                            var text = option.text().replace('⭐ ', '');
                            option.text(newFavoriteState ? '⭐ ' + text : text);
                            
                            // Show success notification
                            showNotification(newFavoriteState ? '⭐ Added to My Events!' : '✓ Removed from My Events', newFavoriteState ? 'success' : 'info');
                            
                            // Reload page after a short delay to update quick access section
                            setTimeout(function() {
                                location.reload();
                            }, 800);
                        }
                        button.prop('disabled', false);
                        button.css('opacity', '1');
                    },
                    error: function() {
                        showNotification('⚠ Failed to update favorite', 'error');
                        button.prop('disabled', false);
                        button.css('opacity', '1');
                    }
                });
            });
            
            // Function to update star button styling
            function updateStarButton(button, isFavorite) {
                if (isFavorite) {
                    button.addClass('is-favorite');
                    button.css({
                        'background': 'linear-gradient(135deg, #ffc107 0%, #ff9800 100%)',
                        'border-color': '#ffc107',
                        'box-shadow': '0 2px 8px rgba(255, 193, 7, 0.3)'
                    });
                    button.find('.dashicons')
                        .removeClass('dashicons-star-empty')
                        .addClass('dashicons-star-filled')
                        .css('color', '#fff');
                } else {
                    button.removeClass('is-favorite');
                    button.css({
                        'background': '#fff',
                        'border-color': '#ddd',
                        'box-shadow': '0 1px 3px rgba(0,0,0,0.1)'
                    });
                    button.find('.dashicons')
                        .removeClass('dashicons-star-filled')
                        .addClass('dashicons-star-empty')
                        .css('color', '#ffc107');
                }
            }
            
            // Function to show toast notification
            function showNotification(message, type) {
                var bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#2271b1');
                var toast = $('<div>')
                    .text(message)
                    .css({
                        'position': 'fixed',
                        'top': '80px',
                        'right': '20px',
                        'background': bgColor,
                        'color': 'white',
                        'padding': '12px 20px',
                        'border-radius': '6px',
                        'box-shadow': '0 4px 12px rgba(0,0,0,0.2)',
                        'z-index': '100000',
                        'font-weight': '600',
                        'animation': 'erp-slide-in 0.3s ease',
                        'min-width': '200px',
                        'text-align': 'center'
                    })
                    .appendTo('body');
                
                setTimeout(function() {
                    toast.css('animation', 'erp-slide-out 0.3s ease');
                    setTimeout(function() {
                        toast.remove();
                    }, 300);
                }, 2000);
            }
            
            // Hover effect for favorite chips
            $('.erp-favorite-chip').hover(
                function() {
                    $(this).css({
                        'transform': 'translateY(-2px)',
                        'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                        'border-color': '#ffc107'
                    });
                },
                function() {
                    $(this).css({
                        'transform': 'translateY(0)',
                        'box-shadow': '0 2px 4px rgba(0,0,0,0.1)',
                        'border-color': 'transparent'
                    });
                }
            );
        });
        </script>
        
        <!-- CSS Animations -->
        <style>
        @keyframes erp-star-bounce {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.3) rotate(15deg); }
            50% { transform: scale(0.9) rotate(-15deg); }
            75% { transform: scale(1.2) rotate(10deg); }
        }
        
        @keyframes erp-slide-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes erp-slide-out {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        #erp-star-toggle-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4) !important;
        }
        
        #erp-star-toggle-btn:active:not(:disabled) {
            transform: scale(0.95);
        }
        
        .erp-favorite-chip {
            position: relative;
            overflow: hidden;
        }
        
        .erp-favorite-chip::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .erp-favorite-chip:hover::before {
            left: 100%;
        }
        </style>
    </div>
    <?php
}

/**
 * AJAX: Get registration data for preview
 */
function erp_ajax_get_registration_data() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_get_registration_data');
    
    $registration_id = intval($_POST['registration_id']);
    $data_type = sanitize_text_field($_POST['data_type']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Clear cache to get fresh data
    wp_cache_flush();
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    $event = get_post($registration->event_id);
    if (!$event) {
        wp_send_json_error(['message' => 'Event not found']);
    }
    
    $event_date = get_post_meta($registration->event_id, '_event_date', true);
    $event_time = get_post_meta($registration->event_id, '_event_time', true);
    $event_location = get_post_meta($registration->event_id, '_event_location', true);
    $entrance_fee = get_post_meta($registration->event_id, '_entrance_fee', true);
    $formatted_date = date('F j, Y', strtotime($event_date));
    
    $content = '';
    
    switch ($data_type) {
        case 'notification':
            // Generate notification email preview
            $is_paid = ($entrance_fee > 0);
            $status_message = $is_paid 
                ? '<div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;"><strong>Payment Status:</strong> ' . ucfirst($registration->payment_status) . '</div>'
                : '<div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;"><strong>Registration Confirmed</strong> (Free Event)</div>';
            
            $content = '
            <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                <div style="background-color: #2271b1; color: white; padding: 20px; text-align: center;">
                    <h1>Event Registration Confirmation</h1>
                </div>
                ' . $status_message . '
                <h3 style="color: #2271b1;">Event Details</h3>
                <p><strong>Event:</strong> ' . esc_html($event->post_title) . '</p>
                <p><strong>Date:</strong> ' . esc_html($formatted_date) . '</p>
                <p><strong>Time:</strong> ' . esc_html($event_time ? $event_time : 'TBA') . '</p>
                <p><strong>Location:</strong> ' . esc_html($event_location ? $event_location : 'TBA') . '</p>
                ' . ($is_paid ? '<p><strong>Fee:</strong> Rp ' . number_format($entrance_fee, 0, ',', '.') . '</p>' : '') . '
                
                <h3 style="color: #2271b1;">Attendee Information</h3>
                <p><strong>Name:</strong> ' . esc_html(wp_unslash($registration->attendee_name)) . '</p>
                <p><strong>Email:</strong> ' . esc_html($registration->attendee_email) . '</p>
                <p><strong>Phone:</strong> ' . esc_html($registration->attendee_phone ? $registration->attendee_phone : '-') . '</p>
                <p><strong>Job/Position:</strong> ' . esc_html($registration->attendee_job ? wp_unslash($registration->attendee_job) : '-') . '</p>
            </div>';
            break;
            
        case 'certificate':
            // Check for uploaded certificate
            $certificate_pdf_url = get_option('erp_certificate_pdf_' . $registration->id);
            
            if (!$certificate_pdf_url) {
                $content = '
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 64px; color: #dc3545; margin-bottom: 20px;">⚠️</div>
                    <h3 style="color: #dc3545; margin-bottom: 15px;">No Certificate Uploaded</h3>
                    <p style="color: #646970; font-size: 16px; line-height: 1.6;">
                        A certificate has not been uploaded for this registration yet.<br>
                        Upload individually or use bulk ZIP import.
                    </p>
                </div>';
            } else {
                // Show certificate info and download link
                $filename = basename($certificate_pdf_url);
                $sent_status = $registration->certificate_sent 
                    ? '<span style="color: #28a745;">✓ Sent on ' . mysql2date('F j, Y g:i A', $registration->certificate_sent) . '</span>'
                    : '<span style="color: #f0ad4e;">⏳ Not sent yet</span>';
                
                $content = '
                <div style="padding: 30px; text-align: center;">
                    <div style="font-size: 64px; color: #28a745; margin-bottom: 20px;">📄</div>
                    <h3 style="color: #2271b1; margin-bottom: 20px;">Certificate Available</h3>
                    
                    <div style="background: #f8f9fa; border: 2px solid #2271b1; border-radius: 8px; padding: 25px; margin: 20px 0;">
                        <p style="margin: 0 0 15px 0; color: #646970;"><strong>Attendee:</strong> ' . esc_html(wp_unslash($registration->attendee_name)) . '</p>
                        <p style="margin: 0 0 15px 0; color: #646970;"><strong>Event:</strong> ' . esc_html($event->post_title) . '</p>
                        <p style="margin: 0 0 15px 0; color: #646970;"><strong>Date:</strong> ' . esc_html($formatted_date) . '</p>
                        <p style="margin: 0; color: #646970;"><strong>Status:</strong> ' . $sent_status . '</p>
                    </div>
                    
                    <div style="margin: 30px 0;">
                        <a href="' . esc_url($certificate_pdf_url) . '" target="_blank" class="button button-primary button-large" style="display: inline-block; background: #2271b1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: 600;">
                            📥 Download Certificate PDF
                        </a>
                    </div>
                    
                    <p style="color: #8c8f94; font-size: 13px; margin-top: 20px;">
                        File: ' . esc_html($filename) . '
                    </p>
                </div>';
            }
            break;
            
        case 'invitation':
            // Get event details for invitation preview
            $zoom_link = get_post_meta($registration->event_id, '_event_zoom_ticket_link', true);
            $ticket_info = get_post_meta($registration->event_id, '_event_ticket_info', true);
            $entrance_fee = get_post_meta($registration->event_id, '_entrance_fee', true);
            
            $sent_status = $registration->invitation_sent 
                ? '<span style="color: #28a745;">✓ Sent on ' . mysql2date('F j, Y g:i A', $registration->invitation_sent) . '</span>'
                : '<span style="color: #f0ad4e;">⏳ Not sent yet</span>';
            
            // Build Zoom section preview
            $zoom_section = '';
            if ($zoom_link) {
                $zoom_section = '
                <div style="background: #e7f3ff; border: 2px solid #2271b1; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <div style="font-size: 24px; margin-bottom: 10px;">🎥</div>
                    <h4 style="margin: 0 0 10px 0; color: #2271b1;">Join via Zoom</h4>
                    <p style="margin: 0; color: #646970; font-size: 13px; word-break: break-all;">
                        <strong>Link:</strong> ' . esc_html($zoom_link) . '
                    </p>
                </div>';
            }
            
            // Build ticket info section preview
            $ticket_section = '';
            if ($ticket_info) {
                $ticket_section = '
                <div style="background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">🎫 Event Information</h4>
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        ' . nl2br(esc_html($ticket_info)) . '
                    </p>
                </div>';
            }
            
            $content = '
            <div style="padding: 20px;">
                <div style="background: #f8f9fa; border: 2px solid #2271b1; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <p style="margin: 0 0 10px 0;"><strong>Attendee:</strong> ' . esc_html(wp_unslash($registration->attendee_name)) . '</p>
                    <p style="margin: 0 0 10px 0;"><strong>Event:</strong> ' . esc_html($event->post_title) . '</p>
                    <p style="margin: 0 0 10px 0;"><strong>Date:</strong> ' . esc_html($formatted_date) . '</p>
                    <p style="margin: 0;"><strong>Status:</strong> ' . $sent_status . '</p>
                </div>
                
                <h3 style="color: #2271b1; margin: 20px 0 15px 0;">Invitation Details</h3>
                
                <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 4px;">
                    <p style="margin: 0 0 10px 0;"><strong>📅 Date:</strong> ' . esc_html($formatted_date) . '</p>
                    <p style="margin: 0 0 10px 0;"><strong>⏰ Time:</strong> ' . esc_html($event_time ? date('g:i A', strtotime($event_time)) : 'TBA') . '</p>
                    ' . ($event_location ? '<p style="margin: 0 0 10px 0;"><strong>📍 Location:</strong> ' . esc_html($event_location) . '</p>' : '') . '
                    ' . ($entrance_fee && $entrance_fee > 0 ? '<p style="margin: 0;"><strong>💳 Fee:</strong> Rp ' . number_format($entrance_fee, 0, ',', '.') . '</p>' : '') . '
                </div>
                
                ' . $zoom_section . '
                ' . $ticket_section . '
                
                <p style="color: #646970; font-size: 13px; margin-top: 20px; font-style: italic;">
                    This is a preview of what will be sent to the attendee.
                </p>
            </div>';
            break;
            
        default:
            wp_send_json_error(['message' => 'Invalid data type']);
    }
    
    wp_send_json_success(['content' => $content]);
}
add_action('wp_ajax_erp_get_registration_data', 'erp_ajax_get_registration_data');

/**
 * AJAX: Upload certificate PDF
 */
function erp_ajax_upload_certificate_pdf() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_upload_certificate_pdf');
    
    $registration_id = intval($_POST['registration_id']);
    
    if (empty($_FILES['certificate_pdf']['name'])) {
        wp_send_json_error(['message' => 'No file uploaded']);
    }
    
    $file = $_FILES['certificate_pdf'];
    
    // Validate file type
    if ($file['type'] !== 'application/pdf') {
        wp_send_json_error(['message' => 'Only PDF files are allowed']);
    }
    
    // Validate file size (2MB max)
    if ($file['size'] > 2 * 1024 * 1024) {
        wp_send_json_error(['message' => 'File size must be less than 2MB']);
    }
    
    // Validate registration exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    // Handle file upload
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array('pdf' => 'application/pdf')
    );
    
    // Rename file to include registration info
    add_filter('wp_handle_upload_prefilter', function($file) use ($registration) {
        $file['name'] = 'certificate-reg-' . $registration->id . '-' . sanitize_file_name(wp_unslash($registration->attendee_name)) . '.pdf';
        return $file;
    });
    
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        wp_send_json_error(['message' => $uploaded_file['error']]);
    }
    
    // Save file URL to registration meta (create new meta table or use options)
    update_option('erp_certificate_pdf_' . $registration_id, $uploaded_file['url']);
    
    // Update certificate_sent timestamp
    $wpdb->update(
        $table_name,
        array('certificate_sent' => current_time('mysql')),
        array('id' => $registration_id),
        array('%s'),
        array('%d')
    );
    
    wp_send_json_success([
        'message' => 'Certificate uploaded successfully',
        'url' => $uploaded_file['url']
    ]);
}
add_action('wp_ajax_erp_upload_certificate_pdf', 'erp_ajax_upload_certificate_pdf');

/**
 * AJAX: Send certificate to individual attendee
 */
function erp_ajax_send_certificate_individual() {
    error_log('=== AJAX HANDLER CALLED: erp_ajax_send_certificate_individual ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
    
    if (!current_user_can('manage_options')) {
        error_log('AJAX ERROR: Unauthorized user');
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    error_log('About to check nonce...');
    check_ajax_referer('erp_send_certificate_individual');
    error_log('Nonce verified successfully');
    
    $registration_id = intval($_POST['registration_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Debug: Check if registration exists
    error_log("AJAX: Send certificate request for registration ID: $registration_id");
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        error_log("AJAX ERROR: Registration not found for ID: $registration_id");
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    error_log("AJAX: Attempting to send certificate to: " . $registration->attendee_email);
    
    // Send certificate
    $sent = erp_send_certificate($registration);
    
    error_log("AJAX: Certificate send result: " . ($sent ? 'SUCCESS' : 'FAILED'));
    
    if ($sent) {
        $current_timestamp = current_time('mysql');
        error_log("AJAX: Updating certificate_sent to: $current_timestamp");
        error_log("AJAX: Table name: $table_name");
        error_log("AJAX: Registration ID: $registration_id");
        
        // Use direct query for better debugging
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET certificate_sent = %s WHERE id = %d",
            $current_timestamp,
            $registration_id
        ));
        
        error_log("AJAX: Database update result: " . ($updated !== false ? "SUCCESS (rows: $updated)" : "FAILED - " . $wpdb->last_error));
        error_log("AJAX: wpdb->last_error: " . ($wpdb->last_error ? $wpdb->last_error : 'NO ERROR'));
        
        // Verify the update immediately
        $verify = $wpdb->get_var($wpdb->prepare(
            "SELECT certificate_sent FROM $table_name WHERE id = %d",
            $registration_id
        ));
        error_log("AJAX: Verified certificate_sent value: " . ($verify ? $verify : 'NULL'));
        
        if ($updated === false) {
            error_log("AJAX ERROR: Database update failed completely");
            wp_send_json_error([
                'message' => 'Certificate sent but failed to update database',
                'sql_error' => $wpdb->last_error
            ]);
        }
        
        // Clear WordPress object cache
        wp_cache_delete($registration_id, 'event_registrations');
        wp_cache_flush();
        
        wp_send_json_success([
            'message' => 'Certificate sent successfully to ' . $registration->attendee_email,
            'timestamp' => $current_timestamp,
            'updated_rows' => $updated,
            'verified_value' => $verify
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to send certificate. Check error log for details.']);
    }
}
add_action('wp_ajax_erp_send_certificate_individual', 'erp_ajax_send_certificate_individual');
error_log('AJAX action registered: wp_ajax_erp_send_certificate_individual → erp_ajax_send_certificate_individual');

/**
 * AJAX: Send invitation to individual attendee
 */
function erp_ajax_send_invitation_individual() {
    error_log('=== AJAX HANDLER CALLED: erp_ajax_send_invitation_individual ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Current user can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
    
    if (!current_user_can('manage_options')) {
        error_log('AJAX ERROR: Unauthorized user');
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    error_log('About to check nonce...');
    check_ajax_referer('erp_send_invitation_individual');
    error_log('Nonce verified successfully');
    
    $registration_id = intval($_POST['registration_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Debug: Check if registration exists
    error_log("AJAX: Send invitation request for registration ID: $registration_id");
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        error_log("AJAX ERROR: Registration not found for ID: $registration_id");
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    error_log("AJAX: Attempting to send invitation to: " . $registration->attendee_email);
    
    // Send invitation
    $sent = erp_send_invitation($registration);
    
    error_log("AJAX: Invitation send result: " . ($sent ? 'SUCCESS' : 'FAILED'));
    
    if ($sent) {
        $current_timestamp = current_time('mysql');
        error_log("AJAX: Updating invitation_sent to: $current_timestamp");
        error_log("AJAX: Table name: $table_name");
        error_log("AJAX: Registration ID: $registration_id");
        
        // Use direct query for better debugging
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET invitation_sent = %s WHERE id = %d",
            $current_timestamp,
            $registration_id
        ));
        
        error_log("AJAX: Database update result: " . ($updated !== false ? "SUCCESS (rows: $updated)" : "FAILED - " . $wpdb->last_error));
        error_log("AJAX: wpdb->last_error: " . ($wpdb->last_error ? $wpdb->last_error : 'NO ERROR'));
        
        // Verify the update immediately
        $verify = $wpdb->get_var($wpdb->prepare(
            "SELECT invitation_sent FROM $table_name WHERE id = %d",
            $registration_id
        ));
        error_log("AJAX: Verified invitation_sent value: " . ($verify ? $verify : 'NULL'));
        
        if ($updated === false) {
            error_log("AJAX ERROR: Database update failed completely");
            wp_send_json_error([
                'message' => 'Invitation sent but failed to update database',
                'sql_error' => $wpdb->last_error
            ]);
        }
        
        // Clear WordPress object cache
        wp_cache_delete($registration_id, 'event_registrations');
        wp_cache_flush();
        
        wp_send_json_success([
            'message' => 'Invitation sent successfully to ' . $registration->attendee_email,
            'timestamp' => $current_timestamp,
            'updated_rows' => $updated,
            'verified_value' => $verify
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to send invitation. Check error log for details.']);
    }
}
add_action('wp_ajax_erp_send_invitation_individual', 'erp_ajax_send_invitation_individual');
error_log('AJAX action registered: wp_ajax_erp_send_invitation_individual → erp_ajax_send_invitation_individual');

/**
 * AJAX: Get invitation edit data
 */
function erp_ajax_get_invitation_edit_data() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_get_invitation_edit_data');
    
    $registration_id = intval($_POST['registration_id']);
    $event_id = intval($_POST['event_id']);
    
    // Get event default location
    $event_location = get_post_meta($event_id, '_event_location', true);
    
    // Get custom invitation data if exists
    $invitation_data = get_option('erp_invitation_data_' . $registration_id, array());
    
    // Merge with defaults
    $data = array(
        'event_location' => isset($invitation_data['event_location']) ? $invitation_data['event_location'] : $event_location,
        'zoom_link' => isset($invitation_data['zoom_link']) ? $invitation_data['zoom_link'] : '',
        'invitation_notes' => isset($invitation_data['invitation_notes']) ? $invitation_data['invitation_notes'] : ''
    );
    
    wp_send_json_success($data);
}
add_action('wp_ajax_erp_get_invitation_edit_data', 'erp_ajax_get_invitation_edit_data');

/**
 * AJAX: Save invitation edit data
 */
function erp_ajax_save_invitation_edit() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_save_invitation_edit');
    
    $registration_id = intval($_POST['registration_id']);
    $event_location = sanitize_text_field($_POST['event_location']);
    $zoom_link = esc_url_raw($_POST['zoom_link']);
    $invitation_notes = sanitize_textarea_field($_POST['invitation_notes']);
    
    // Validate registration exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    // Save custom invitation data
    $invitation_data = array(
        'event_location' => $event_location,
        'zoom_link' => $zoom_link,
        'invitation_notes' => $invitation_notes,
        'updated_at' => current_time('mysql')
    );
    
    update_option('erp_invitation_data_' . $registration_id, $invitation_data);
    
    wp_send_json_success([
        'message' => 'Invitation details saved successfully'
    ]);
}
add_action('wp_ajax_erp_save_invitation_edit', 'erp_ajax_save_invitation_edit');

/**
 * AJAX: Update registration information
 */
function erp_ajax_update_registration() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_update_registration');
    
    $registration_id = intval($_POST['registration_id']);
    $attendee_name = sanitize_text_field($_POST['attendee_name']);
    $attendee_email = sanitize_email($_POST['attendee_email']);
    $attendee_phone = sanitize_text_field($_POST['attendee_phone']);
    $attendee_job = sanitize_text_field($_POST['attendee_job']);
    $num_tickets = intval($_POST['num_tickets']);
    
    // Validate required fields
    if (empty($attendee_name) || empty($attendee_email)) {
        wp_send_json_error(['message' => 'Name and email are required']);
    }
    
    if (!is_email($attendee_email)) {
        wp_send_json_error(['message' => 'Invalid email address']);
    }
    
    if ($num_tickets < 1) {
        wp_send_json_error(['message' => 'Number of tickets must be at least 1']);
    }
    
    // Update registration in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    $updated = $wpdb->update(
        $table_name,
        array(
            'attendee_name' => $attendee_name,
            'attendee_email' => $attendee_email,
            'attendee_phone' => $attendee_phone,
            'attendee_job' => $attendee_job,
            'ticket_quantity' => $num_tickets
        ),
        array('id' => $registration_id),
        array('%s', '%s', '%s', '%s', '%d'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to update registration']);
    }
    
    // Regenerate certificate if it exists
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $registration_id
    ));
    
    if ($registration && $registration->certificate_url) {
        // Delete old certificate files
        $pattern = WP_CONTENT_DIR . '/uploads/certificates/certificate-' . $registration_id . '-*.html';
        $files = glob($pattern);
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Generate new certificate with updated data
        erp_auto_generate_certificate($registration);
    }
    
    wp_send_json_success([
        'message' => 'Registration updated successfully',
        'certificate_regenerated' => !empty($registration->certificate_url)
    ]);
}
add_action('wp_ajax_erp_update_registration', 'erp_ajax_update_registration');

/**
 * AJAX: Mark attendance for a registration
 */
function erp_ajax_mark_attendance() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_mark_attendance');
    
    $registration_id = intval($_POST['registration_id']);
    
    if (!$registration_id) {
        wp_send_json_error(['message' => 'Invalid registration ID']);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Check if registration exists
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    // Mark as attended
    $updated = $wpdb->update(
        $table_name,
        array(
            'attendance_status' => 'attended',
            'attendance_time' => current_time('mysql'),
            'check_in_method' => 'manual'
        ),
        array('id' => $registration_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to mark attendance']);
    }
    
    wp_send_json_success(['message' => 'Attendance marked successfully']);
}
add_action('wp_ajax_erp_mark_attendance', 'erp_ajax_mark_attendance');

/**
 * AJAX: Unmark attendance for a registration
 */
function erp_ajax_unmark_attendance() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    check_ajax_referer('erp_unmark_attendance');
    
    $registration_id = intval($_POST['registration_id']);
    
    if (!$registration_id) {
        wp_send_json_error(['message' => 'Invalid registration ID']);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Check if registration exists
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'Registration not found']);
    }
    
    // Remove attendance
    $updated = $wpdb->update(
        $table_name,
        array(
            'attendance_status' => 'not_attended',
            'attendance_time' => NULL,
            'check_in_method' => NULL
        ),
        array('id' => $registration_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to remove attendance']);
    }
    
    wp_send_json_success(['message' => 'Attendance removed successfully']);
}
add_action('wp_ajax_erp_unmark_attendance', 'erp_ajax_unmark_attendance');

/**
 * Plugin deactivation hook
 */
function erp_deactivation() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'erp_deactivation');

/**
 * My Events Page - Shows only favorited events
 */
function erp_my_events_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    $current_user_id = get_current_user_id();
    $favorite_events = erp_get_user_favorite_events($current_user_id);
    
    // Show notifications
    if (isset($_GET['deleted'])) {
        if ($_GET['deleted'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Registration deleted successfully!</strong></p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Failed to delete registration.</strong></p></div>';
        }
    }
    
    // Show bulk action notifications
    if (isset($_GET['bulk_action']) && isset($_GET['success'])) {
        $action_name = sanitize_text_field($_GET['bulk_action']);
        $success = intval($_GET['success']);
        $error = isset($_GET['error']) ? intval($_GET['error']) : 0;
        
        $action_label = '';
        switch ($action_name) {
            case 'generate_certificates': $action_label = 'Certificates generated for'; break;
            case 'send_notifications': $action_label = 'Notifications'; break;
            case 'send_certificates': $action_label = 'Certificates'; break;
            case 'send_invitations': $action_label = 'Invitations'; break;
            case 'mark_attended': $action_label = 'Attendance marked for'; break;
            case 'unmark_attended': $action_label = 'Attendance removed for'; break;
        }
        
        if ($success > 0) {
            if ($action_name === 'generate_certificates') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $success . ' certificate(s) generated successfully!</strong>';
            } elseif (in_array($action_name, ['mark_attended', 'unmark_attended'])) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $action_label . ' ' . $success . ' registration(s)!</strong>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . $action_label . ' sent to ' . $success . ' registration(s)!</strong>';
            }
            if ($error > 0) echo ' (' . $error . ' failed)';
            echo '</p></div>';
        } elseif ($error > 0) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Failed to process ' . $action_label . ' (' . $error . ' errors)</strong></p></div>';
        }
    }
    
    // Check if user has any favorite events
    if (empty($favorite_events)) {
        ?>
        <div class="wrap">
            <h1>⭐ My Events</h1>
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 40px; text-align: center; margin: 40px 0;">
                <span class="dashicons dashicons-star-empty" style="font-size: 80px; color: #ddd; width: 80px; height: 80px;"></span>
                <h2 style="margin: 20px 0 10px 0; color: #1d2327;">No Favorite Events Yet</h2>
                <p style="color: #646970; font-size: 14px; max-width: 500px; margin: 0 auto 30px;">
                    Mark events as favorites to track them here. Visit the <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>">All Registrations</a> page and click the star icon next to any event to add it to your favorites.
                </p>
                <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>" class="button button-primary button-hero">
                    Browse All Events
                </a>
            </div>
        </div>
        <?php
        return;
    }
    
    // Get filter parameters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_event = isset($_GET['filter_event']) ? intval($_GET['filter_event']) : 0;
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_payment = isset($_GET['filter_payment']) ? sanitize_text_field($_GET['filter_payment']) : '';
    
    // Build SQL query with filters - ONLY for favorite events
    $where_clauses = array();
    
    // Most important: ONLY show favorite events
    $favorite_ids = array_map('intval', $favorite_events);
    $where_clauses[] = "r.event_id IN (" . implode(',', $favorite_ids) . ")";
    
    if (!empty($search)) {
        $where_clauses[] = $wpdb->prepare(
            "(r.attendee_name LIKE %s OR r.attendee_email LIKE %s OR r.attendee_phone LIKE %s OR r.attendee_job LIKE %s OR p.post_title LIKE %s)",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }
    
    if ($filter_event > 0 && in_array($filter_event, $favorite_events)) {
        $where_clauses[] = $wpdb->prepare("r.event_id = %d", $filter_event);
    }
    
    if (!empty($filter_status)) {
        $where_clauses[] = $wpdb->prepare("r.status = %s", $filter_status);
    }
    
    if (!empty($filter_payment)) {
        $where_clauses[] = $wpdb->prepare("r.payment_status = %s", $filter_payment);
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Handle sorting
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'registration_date';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Map column names to database fields
    $sortable_columns = array(
        'id' => 'r.id',
        'event_name' => 'p.post_title',
        'attendee_name' => 'r.attendee_name',
        'email' => 'r.attendee_email',
        'phone' => 'r.attendee_phone',
        'job' => 'r.attendee_job',
        'tickets' => 'r.ticket_quantity',
        'registration_date' => 'r.registration_date',
        'status' => 'r.status',
        'payment' => 'r.payment_status',
        'attendance' => 'r.attendance_status'
    );
    
    $order_sql = 'ORDER BY r.registration_date DESC';
    if (isset($sortable_columns[$orderby])) {
        $order_sql = 'ORDER BY ' . $sortable_columns[$orderby] . ' ' . $order;
    }
    
    // Clear cache before fetching to get fresh data
    wp_cache_flush();
    
    // Get registrations for favorite events
    $registrations = $wpdb->get_results("
        SELECT r.*, p.post_title as event_name
        FROM $table_name r
        LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID
        $where_sql
        $order_sql
    ", OBJECT);
    
    // Calculate statistics
    $total_registrations = count($registrations);
    $total_confirmed = 0;
    $total_pending = 0;
    $total_cancelled = 0;
    $total_paid = 0;
    $total_unpaid = 0;
    $total_attended = 0;
    $total_not_attended = 0;
    $total_tickets = 0;
    
    foreach ($registrations as $reg) {
        if ($reg->status === 'confirmed') $total_confirmed++;
        elseif ($reg->status === 'pending') $total_pending++;
        elseif ($reg->status === 'cancelled') $total_cancelled++;
        
        if (($reg->payment_status ?? 'unpaid') === 'paid') $total_paid++;
        else $total_unpaid++;
        
        if (($reg->attendance_status ?? 'not_attended') === 'attended') $total_attended++;
        else $total_not_attended++;
        
        $total_tickets += intval($reg->ticket_quantity);
    }
    
    // Get favorite events for filter dropdown
    $events = $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'event' AND p.ID IN (" . implode(',', $favorite_ids) . ")
        ORDER BY p.post_title ASC
    ");
    
    // Get unique statuses
    $statuses = $wpdb->get_col("SELECT DISTINCT status FROM $table_name WHERE status IS NOT NULL AND event_id IN (" . implode(',', $favorite_ids) . ") ORDER BY status");
    
    // Get unique payment statuses
    $payment_statuses = $wpdb->get_col("SELECT DISTINCT payment_status FROM $table_name WHERE payment_status IS NOT NULL AND event_id IN (" . implode(',', $favorite_ids) . ") ORDER BY payment_status");
    
    // Helper function to generate sortable column header
    function erp_my_events_sortable_column($column_key, $column_label, $current_orderby, $current_order) {
        $base_url = remove_query_arg(array('orderby', 'order'));
        $new_order = ($current_orderby === $column_key && $current_order === 'ASC') ? 'DESC' : 'ASC';
        $url = add_query_arg(array('orderby' => $column_key, 'order' => $new_order), $base_url);
        
        $arrow = '';
        if ($current_orderby === $column_key) {
            $arrow = $current_order === 'ASC' ? ' ▲' : ' ▼';
        }
        
        return '<a href="' . esc_url($url) . '" style="text-decoration: none; color: inherit; font-weight: 600;">' . 
               esc_html($column_label) . '<span style="font-size: 10px;">' . $arrow . '</span></a>';
    }
    
    ?>
    <div class="wrap">
        <h1 style="display: flex; align-items: center; gap: 10px;">
            ⭐ My Events
            <span style="background: #2271b1; color: white; padding: 4px 12px; border-radius: 12px; font-size: 14px; font-weight: 600;">
                <?php echo count($favorite_events); ?> Favorite<?php echo count($favorite_events) !== 1 ? 's' : ''; ?>
            </span>
        </h1>
        
        <!-- Info Banner -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-star-filled" style="font-size: 40px; width: 40px; height: 40px;"></span>
                <div>
                    <h3 style="margin: 0 0 5px 0; font-size: 16px;">My Favorite Events Dashboard</h3>
                    <p style="margin: 0; opacity: 0.9; font-size: 13px;">
                        This dashboard shows registrations only for your <?php echo count($favorite_events); ?> favorite event<?php echo count($favorite_events) !== 1 ? 's' : ''; ?>. 
                        <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>" style="color: white; text-decoration: underline;">View all events</a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Statistics -->
        <div style="margin: 20px 0;">
            <h2 style="font-size: 16px; color: #1d2327; margin: 0 0 15px 0; font-weight: 600;">
                📊 My Events Statistics
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Total Registrations</div>
                    <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($total_registrations); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;"><?php echo number_format($total_tickets); ?> tickets</div>
                </div>
                
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Attendance</div>
                    <div style="font-size: 32px; font-weight: 700;"><?php echo number_format($total_attended); ?></div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;">
                        <?php echo $total_registrations > 0 ? round(($total_attended / $total_registrations) * 100, 1) : 0; ?>% attendance rate
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Status</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($total_confirmed); ?> Confirmed</div>
                    <div style="font-size: 12px; opacity: 0.8;">
                        <?php echo number_format($total_pending); ?> Pending · <?php echo number_format($total_cancelled); ?> Cancelled
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Payment</div>
                    <div style="font-size: 20px; font-weight: 700; margin-bottom: 8px;"><?php echo number_format($total_paid); ?> Paid</div>
                    <div style="font-size: 12px; opacity: 0.8;">
                        <?php echo number_format($total_unpaid); ?> Unpaid
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Simple Info Box -->
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 30px; text-align: center; margin: 20px 0;">
            <p style="font-size: 16px; color: #1d2327; margin-bottom: 20px;">
                📊 You have <strong><?php echo number_format($total_registrations); ?> registrations</strong> across your<?php echo count($favorite_events); ?> favorite event<?php echo count($favorite_events) !== 1 ? 's' : ''; ?>.
            </p>
            <p style="color: #646970; margin-bottom: 25px;">
                To view detailed registration data and manage your favorite events, please visit the main registrations page.
            </p>
            <a href="<?php echo admin_url('admin.php?page=event-registrations'); ?>" class="button button-primary button-large">
                View All Registrations →
            </a>
        </div>
        
        <!-- Favorite Events List -->
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #1d2327; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-star-filled" style="color: #ffc107;"></span>
                Your Favorite Events
            </h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($events as $event): 
                    // Get event-specific stats
                    $event_regs = array_filter($registrations, function($reg) use ($event) {
                        return $reg->event_id == $event->ID;
                    });
                    $event_count = count($event_regs);
                    $event_attended = count(array_filter($event_regs, function($reg) {
                        return ($reg->attendance_status ?? 'not_attended') === 'attended';
                    }));
                ?>
                <li style="padding: 15px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2271b1; font-size: 15px;"><?php echo esc_html($event->post_title); ?></strong>
                        <div style="color: #646970; font-size: 13px; margin-top: 5px;">
                            <?php echo $event_count; ?> registrations · <?php echo $event_attended; ?> attended
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="<?php echo admin_url('admin.php?page=event-registrations&filter_event=' . $event->ID); ?>" class="button button-small">
                            View Details
                        </a>
                        <button type="button" 
                                class="erp-favorite-toggle is-favorite"
                                data-event-id="<?php echo esc_attr($event->ID); ?>"
                                title="Remove from favorites"
                                style="background: transparent; border: none; cursor: pointer; padding: 5px;">
                            <span class="dashicons dashicons-star-filled" style="color: #ffc107; font-size: 20px;"></span>
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <!-- Add inline AJAX script for favorite toggle -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle favorite toggle
        $(document).on('click', '.erp-favorite-toggle', function(e) {
            e.preventDefault();
            var button = $(this);
            var eventId = button.data('event-id');
            var isFavorite = button.hasClass('is-favorite');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'erp_toggle_favorite_event',
                    nonce: '<?php echo wp_create_nonce('erp_favorite_nonce'); ?>',
                    event_id: eventId,
                    is_favorite: !isFavorite
                },
                success: function(response) {
                    if (response.success) {
                        if (!response.data.is_favorite) {
                            // If unfavorited, reload the page to update the list
                            location.reload();
                        }
                    }
                },
                error: function() {
                    alert('Failed to update favorite. Please try again.');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Export/Import Page
 */
function erp_export_import_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Handle notifications
    if (isset($_GET['exported'])) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Registrations exported successfully!</strong></p></div>';
    }
    
    if (isset($_GET['imported'])) {
        $count = intval($_GET['imported']);
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . $count . ' registration(s) imported successfully!</strong></p></div>';
    }
    
    if (isset($_GET['import_error'])) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Import error: ' . esc_html($_GET['import_error']) . '</strong></p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">
            <span class="dashicons dashicons-download" style="font-size: 32px; width: 32px; height: 32px;"></span>
            Export/Import Registrations
        </h1>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 1200px;">
            
            <!-- Export Section -->
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 30px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #28a745; padding-bottom: 10px;">
                    <span class="dashicons dashicons-upload" style="color: #28a745;"></span>
                    Export Registrations
                </h2>
                
                <p style="color: #646970; margin-bottom: 25px;">
                    Download all event registrations as a CSV file, including complete event details.
                </p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="erp_export_registrations">
                    <?php wp_nonce_field('erp_export_registrations'); ?>
                    
                    <div style="background: #e7f5fe; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                        <p style="margin: 0; color: #1d2327; font-size: 13px; line-height: 1.6;">
                            <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                            <strong>Export includes:</strong><br>
                            • Registration details (name, email, phone, job)<br>
                            • Event information (title, date, time, location)<br>
                            • Payment and status information<br>
                            • Notification/certificate/invitation tracking
                        </p>
                    </div>
                    
                    <button type="submit" class="button button-primary button-hero" style="background: #28a745; border-color: #28a745; padding: 12px 30px; font-size: 16px; height: auto;">
                        <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                        Export to CSV
                    </button>
                </form>
            </div>
            
            <!-- Import Section -->
            <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 30px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0; color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">
                    <span class="dashicons dashicons-download" style="color: #2271b1;"></span>
                    Import Registrations
                </h2>
                
                <p style="color: #646970; margin-bottom: 25px;">
                    Upload a CSV file to bulk import event registrations.
                </p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="erp_import_registrations">
                    <?php wp_nonce_field('erp_import_registrations'); ?>
                    
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                            CSV File <span style="color: red;">*</span>
                        </label>
                        <input type="file" name="import_file" accept=".csv" required style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px;" />
                        <p style="margin: 8px 0 0 0; color: #646970; font-size: 13px;">
                            Select a CSV file with registration data
                        </p>
                    </div>
                    
                    <div style="background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                        <p style="margin: 0; color: #1d2327; font-size: 13px; line-height: 1.6;">
                            <span class="dashicons dashicons-warning" style="color: #f0ad4e;"></span>
                            <strong>CSV Format Required:</strong><br>
                            <code style="background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                event_id,attendee_name,attendee_email,attendee_phone,attendee_job,status,payment_status
                            </code><br>
                            <small>Download an export to see the exact format.</small>
                        </p>
                    </div>
                    
                    <button type="submit" class="button button-primary button-hero" style="padding: 12px 30px; font-size: 16px; height: auto;">
                        <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                        Import from CSV
                    </button>
                </form>
            </div>
            
        </div>
        
        <!-- Sample Export Link -->
        <div style="background: #f8f9fa; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px; max-width: 1200px;">
            <h3 style="margin-top: 0; color: #1d2327;">
                <span class="dashicons dashicons-editor-help"></span>
                Need Help?
            </h3>
            <p style="margin: 0; color: #646970; line-height: 1.8;">
                <strong>Tips:</strong><br>
                • Export first to see the correct CSV format before importing<br>
                • Ensure event_id values match existing events in your database<br>
                • Email addresses must be valid and unique<br>
                • Status values: pending, confirmed, cancelled<br>
                • Payment status values: pending, paid, failed, free
            </p>
        </div>
    </div>
    <?php
}

/**
 * Handle Export Registrations
 */
function erp_handle_export_registrations() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    check_admin_referer('erp_export_registrations');
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Get all registrations with event data
    $registrations = $wpdb->get_results("
        SELECT 
            r.*,
            p.post_title as event_name,
            pm1.meta_value as event_date,
            pm2.meta_value as event_time,
            pm3.meta_value as event_location,
            pm4.meta_value as entrance_fee
        FROM $table_name r
        LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID
        LEFT JOIN {$wpdb->postmeta} pm1 ON r.event_id = pm1.post_id AND pm1.meta_key = '_event_date'
        LEFT JOIN {$wpdb->postmeta} pm2 ON r.event_id = pm2.post_id AND pm2.meta_key = '_event_time'
        LEFT JOIN {$wpdb->postmeta} pm3 ON r.event_id = pm3.post_id AND pm3.meta_key = '_event_location'
        LEFT JOIN {$wpdb->postmeta} pm4 ON r.event_id = pm4.post_id AND pm4.meta_key = '_entrance_fee'
        ORDER BY r.registration_date DESC
    ");
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=event-registrations-' . date('Y-m-d') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, array(
        'ID',
        'Event ID',
        'Event Name',
        'Event Date',
        'Event Time',
        'Event Location',
        'Entrance Fee',
        'Attendee Name',
        'Attendee Email',
        'Attendee Phone',
        'Attendee Job',
        'Ticket Quantity',
        'Registration Date',
        'Status',
        'Payment Status',
        'Notification Sent',
        'Certificate Sent',
        'Invitation Sent'
    ));
    
    // Add data rows
    foreach ($registrations as $reg) {
        fputcsv($output, array(
            $reg->id,
            $reg->event_id,
            $reg->event_name,
            $reg->event_date,
            $reg->event_time,
            $reg->event_location,
            $reg->entrance_fee,
            wp_unslash($reg->attendee_name),
            $reg->attendee_email,
            $reg->attendee_phone,
            wp_unslash($reg->attendee_job),
            $reg->ticket_quantity,
            $reg->registration_date,
            $reg->status,
            $reg->payment_status,
            $reg->notification_sent,
            $reg->certificate_sent,
            $reg->invitation_sent
        ));
    }
    
    fclose($output);
    exit;
}
add_action('admin_post_erp_export_registrations', 'erp_handle_export_registrations');

/**
 * Handle Import Registrations
 */
function erp_handle_import_registrations() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    check_admin_referer('erp_import_registrations');
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'event-export-import',
            'import_error' => 'File upload failed'
        ), admin_url('admin.php')));
        exit;
    }
    
    $file = $_FILES['import_file']['tmp_name'];
    
    if (!is_uploaded_file($file)) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'event-export-import',
            'import_error' => 'Invalid file'
        ), admin_url('admin.php')));
        exit;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'event_registrations';
    
    // Open CSV file
    $handle = fopen($file, 'r');
    if (!$handle) {
        wp_safe_redirect(add_query_arg(array(
            'page' => 'event-export-import',
            'import_error' => 'Cannot read file'
        ), admin_url('admin.php')));
        exit;
    }
    
    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        wp_safe_redirect(add_query_arg(array(
            'page' => 'event-export-import',
            'import_error' => 'Empty file'
        ), admin_url('admin.php')));
        exit;
    }
    
    $imported_count = 0;
    $row_number = 1;
    
    // Read data rows
    while (($row = fgetcsv($handle)) !== false) {
        $row_number++;
        
        // Skip empty rows
        if (count(array_filter($row)) === 0) {
            continue;
        }
        
        // Map CSV columns to array
        $data = array_combine($header, $row);
        
        // Validate required fields
        if (empty($data['Event ID']) || empty($data['Attendee Name']) || empty($data['Attendee Email'])) {
            error_log("Row $row_number: Missing required fields");
            continue;
        }
        
        // Validate email
        if (!is_email($data['Attendee Email'])) {
            error_log("Row $row_number: Invalid email");
            continue;
        }
        
        // Validate event exists
        $event_id = intval($data['Event ID']);
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            error_log("Row $row_number: Event not found");
            continue;
        }
        
        // Insert registration
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'event_id' => $event_id,
                'attendee_name' => sanitize_text_field($data['Attendee Name']),
                'attendee_email' => sanitize_email($data['Attendee Email']),
                'attendee_phone' => sanitize_text_field($data['Attendee Phone'] ?? ''),
                'attendee_job' => sanitize_text_field($data['Attendee Job'] ?? ''),
                'ticket_quantity' => isset($data['Ticket Quantity']) ? intval($data['Ticket Quantity']) : 1,
                'registration_date' => current_time('mysql'),
                'status' => sanitize_text_field($data['Status'] ?? 'confirmed'),
                'payment_status' => sanitize_text_field($data['Payment Status'] ?? 'free')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($inserted) {
            $imported_count++;
        }
    }
    
    fclose($handle);
    
    wp_safe_redirect(add_query_arg(array(
        'page' => 'event-export-import',
        'imported' => $imported_count
    ), admin_url('admin.php')));
    exit;
}
add_action('admin_post_erp_import_registrations', 'erp_handle_import_registrations');

/**
 * Certificate Verification Endpoint Handler
 * Handles QR code scanning - verifies certificate authenticity
 */
function erp_handle_certificate_verification() {
    // Check if this is a verification request
    if (isset($_GET['verify-certificate']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/verify-certificate') !== false)) {
        // Get the registration code from query parameter
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
        
        if (empty($code)) {
            wp_die('Invalid certificate code.', 'Certificate Verification', array('response' => 400));
        }
        
        // Extract registration ID from code (format: REG-000123)
        if (preg_match('/REG-(\d+)/', $code, $matches)) {
            $registration_id = intval($matches[1]);
        } else {
            wp_die('Invalid certificate code format.', 'Certificate Verification', array('response' => 400));
        }
        
        // Query the database for the registration
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_registrations';
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $registration_id
        ));
        
        if (!$registration) {
            wp_die('Certificate not found. This certificate may not exist or has been revoked.', 'Certificate Verification', array('response' => 404));
        }
        
        // Get event details
        $event = get_post($registration->event_id);
        $event_title = $event ? $event->post_title : 'Unknown Event';
        
        // Get event date
        $event_date_raw = get_post_meta($registration->event_id, '_event_date', true);
        $event_date_formatted = $event_date_raw ? date('F j, Y', strtotime($event_date_raw)) : date('F j, Y', strtotime($registration->registration_date));
        
        // Display verification page
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Certificate Verification - <?php bloginfo('name'); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .verification-container {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 600px;
                    width: 100%;
                    padding: 40px;
                }
                .verification-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .verification-badge {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                    animation: scaleIn 0.5s ease;
                }
                .verification-badge svg {
                    width: 40px;
                    height: 40px;
                    fill: white;
                }
                @keyframes scaleIn {
                    from { transform: scale(0); }
                    to { transform: scale(1); }
                }
                h1 {
                    color: #2d3436;
                    font-size: 28px;
                    margin-bottom: 10px;
                }
                .subtitle {
                    color: #636e72;
                    font-size: 16px;
                }
                .certificate-details {
                    background: #f8f9fa;
                    border-radius: 12px;
                    padding: 25px;
                    margin: 25px 0;
                }
                .detail-row {
                    display: flex;
                    padding: 12px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .detail-row:last-child {
                    border-bottom: none;
                }
                .detail-label {
                    font-weight: 600;
                    color: #636e72;
                    width: 140px;
                    flex-shrink: 0;
                }
                .detail-value {
                    color: #2d3436;
                    font-weight: 500;
                }
                .status-badge {
                    display: inline-block;
                    background: #00b894;
                    color: white;
                    padding: 6px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                }
                .footer-note {
                    text-align: center;
                    color: #95a5a6;
                    font-size: 14px;
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 1px solid #e9ecef;
                }
                .back-button {
                    display: inline-block;
                    background: #667eea;
                    color: white;
                    padding: 12px 30px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    margin-top: 20px;
                    transition: transform 0.2s;
                }
                .back-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
                }
            </style>
        </head>
        <body>
            <div class="verification-container">
                <div class="verification-header">
                    <div class="verification-badge">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                        </svg>
                    </div>
                    <h1>Certificate Verified ✓</h1>
                    <p class="subtitle">This certificate is authentic and valid</p>
                </div>
                
                <div class="certificate-details">
                    <div class="detail-row">
                        <div class="detail-label">Certificate Code:</div>
                        <div class="detail-value"><?php echo esc_html($code); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Recipient:</div>
                        <div class="detail-value"><?php echo esc_html(wp_unslash($registration->attendee_name)); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?php echo esc_html($registration->attendee_email); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Event:</div>
                        <div class="detail-value"><?php echo esc_html($event_title); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Event Date:</div>
                        <div class="detail-value"><?php echo esc_html($event_date_formatted); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value"><span class="status-badge">Verified</span></div>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="back-button">← Back to Home</a>
                </div>
                
                <div class="footer-note">
                    <strong>Issued by:</strong> <?php bloginfo('name'); ?><br>
                    <small>Certificate verification system powered by QR technology</small>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
add_action('template_redirect', 'erp_handle_certificate_verification', 1);

/**
 * Add verify-certificate as a rewrite rule
 */
function erp_add_verification_rewrite_rule() {
    add_rewrite_rule('^verify-certificate/?', 'index.php?verify-certificate=1', 'top');
}
add_action('init', 'erp_add_verification_rewrite_rule');

/**
 * Add custom query var for certificate verification
 */
function erp_add_verification_query_var($vars) {
    $vars[] = 'verify-certificate';
    return $vars;
}
add_filter('query_vars', 'erp_add_verification_query_var');

/**
 * Flush rewrite rules on plugin activation
 */
function erp_activation_flush_rules() {
    erp_add_verification_rewrite_rule();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'erp_activation_flush_rules');


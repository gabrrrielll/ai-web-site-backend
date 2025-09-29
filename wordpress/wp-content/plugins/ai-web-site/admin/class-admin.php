<?php

/**
 * Admin interface class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_Admin
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Handle form submissions
        add_action('admin_post_save_ai_web_site_options', array($this, 'save_options'));
        add_action('admin_post_test_cpanel_connection', array($this, 'test_connection'));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'settings_page_ai-web-site') {
            return;
        }

        wp_enqueue_script(
            'ai-web-site-admin',
            AI_WEB_SITE_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            AI_WEB_SITE_VERSION,
            true
        );

        wp_localize_script('ai-web-site-admin', 'aiWebSite', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_web_site_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this subdomain?', 'ai-web-site'),
                'creating' => __('Creating...', 'ai-web-site'),
                'deleting' => __('Deleting...', 'ai-web-site'),
                'testing' => __('Testing...', 'ai-web-site')
            )
        ));

        wp_enqueue_style(
            'ai-web-site-admin',
            AI_WEB_SITE_PLUGIN_URL . 'assets/admin.css',
            array(),
            AI_WEB_SITE_VERSION
        );
    }

    /**
     * Save plugin options
     */
    public function save_options()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get current options
        $options = get_option('ai_web_site_options', array());

        // Update options
        $options['cpanel_username'] = sanitize_text_field($_POST['cpanel_username']);
        $options['cpanel_password'] = sanitize_text_field($_POST['cpanel_password']);
        $options['cpanel_host'] = sanitize_text_field($_POST['cpanel_host']);
        $options['cpanel_api_token'] = sanitize_text_field($_POST['cpanel_api_token']);
        $options['main_domain'] = sanitize_text_field($_POST['main_domain']);

        // Save options
        update_option('ai_web_site_options', $options);

        // Redirect back with success message
        wp_redirect(add_query_arg('message', 'options_saved', admin_url('options-general.php?page=ai-web-site')));
        exit;
    }

    /**
     * Test cPanel connection
     */
    public function test_connection()
    {
        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'ai_web_site_options')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Test connection
        $cpanel_api = AI_Web_Site_CPanel_API::get_instance();
        $result = $cpanel_api->test_connection();

        if ($result['success']) {
            $message = 'connection_success';
        } else {
            $message = 'connection_failed';
        }

        // Redirect back with result message
        wp_redirect(add_query_arg('message', $message, admin_url('options-general.php?page=ai-web-site')));
        exit;
    }

    /**
     * Get admin messages
     */
    public function get_admin_messages()
    {
        $messages = array();

        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'options_saved':
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('Options saved successfully.', 'ai-web-site')
                    );
                    break;
                case 'connection_success':
                    $messages[] = array(
                        'type' => 'success',
                        'text' => __('cPanel connection test successful.', 'ai-web-site')
                    );
                    break;
                case 'connection_failed':
                    $messages[] = array(
                        'type' => 'error',
                        'text' => __('cPanel connection test failed. Please check your settings.', 'ai-web-site')
                    );
                    break;
            }
        }

        return $messages;
    }
}

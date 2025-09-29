<?php

/**
 * cPanel API integration class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Web_Site_CPanel_API
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * cPanel configuration
     */
    private $config;

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
        $this->load_config();
    }

    /**
     * Load cPanel configuration
     */
    private function load_config()
    {
        $options = get_option('ai_web_site_options', array());

        $this->config = array(
            'username' => $options['cpanel_username'] ?? '',
            'password' => $options['cpanel_password'] ?? '',
            'host' => $options['cpanel_host'] ?? 'ai-web.site',
            'api_token' => $options['cpanel_api_token'] ?? '',
            'main_domain' => $options['main_domain'] ?? 'ai-web.site'
        );
    }

    /**
     * Create subdomain
     */
    public function create_subdomain($subdomain, $domain, $target_ip = null)
    {
        if (empty($this->config['api_token'])) {
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Prepare API URL
        $api_url = "https://{$this->config['host']}:2083/execute/SubDomain/addsubdomain";

        // Prepare parameters
        $params = array(
            'domain' => $subdomain,
            'rootdomain' => $domain,
            'dir' => '/editor.ai-web.site', // All subdomains point to editor
            'disallowdot' => 0
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'sslverify' => false,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            return array(
                'success' => true,
                'message' => 'Subdomain created successfully'
            );
        } else {
            $error_message = 'Unknown error';
            if (isset($result['errors']) && is_array($result['errors'])) {
                $error_message = implode(', ', $result['errors']);
            }

            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Delete subdomain
     */
    public function delete_subdomain($subdomain, $domain)
    {
        if (empty($this->config['api_token'])) {
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Prepare API URL
        $api_url = "https://{$this->config['host']}:2083/execute/SubDomain/delsubdomain";

        // Prepare parameters
        $params = array(
            'domain' => $subdomain,
            'rootdomain' => $domain
        );

        // Make API request
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'body' => $params,
            'sslverify' => false,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            return array(
                'success' => true,
                'message' => 'Subdomain deleted successfully'
            );
        } else {
            $error_message = 'Unknown error';
            if (isset($result['errors']) && is_array($result['errors'])) {
                $error_message = implode(', ', $result['errors']);
            }

            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }

    /**
     * Test API connection
     */
    public function test_connection()
    {
        if (empty($this->config['api_token'])) {
            return array(
                'success' => false,
                'message' => 'cPanel API token not configured'
            );
        }

        // Test with a simple API call
        $api_url = "https://{$this->config['host']}:2083/execute/StatsBar/get_stats";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'cpanel ' . $this->config['username'] . ':' . $this->config['api_token']
            ),
            'sslverify' => false,
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['status']) && $result['status'] === 1) {
            return array(
                'success' => true,
                'message' => 'API connection successful'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'API connection failed'
            );
        }
    }
}

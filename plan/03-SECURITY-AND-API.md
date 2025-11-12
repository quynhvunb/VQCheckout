# FILE 03: SECURITY & API - BẢO MẬT & REST API

## VQ CHECKOUT FOR WOO - SECURITY ARCHITECTURE & REST API

**Version:** 3.0.0-OPTIMIZED  
**Date:** November 5, 2025  
**Status:** ✅ PRODUCTION-READY

---

## I. OVERVIEW - TỔNG QUAN

Bảo mật là ưu tiên hàng đầu. File này định nghĩa:
- ✅ **REST API** thay vì legacy AJAX/PHP files
- ✅ **reCAPTCHA** server-side verification
- ✅ **Rate Limiting** protection
- ✅ **Input Sanitization** standards
- ✅ **Output Escaping** rules
- ✅ **Nonce Management** best practices

---

## II. REST API ARCHITECTURE

### 2.1. Why REST API instead of Direct PHP?

**❌ OLD Approach (Bản cũ):**
```php
// inc/get-address.php
<?php
require_once('../../../wp-load.php');
$phone = $_POST['phone'];
// Direct query, no proper auth, no rate-limit
```

**Problems:**
- No authentication/authorization
- No rate limiting
- No proper error handling
- Hard to test
- Security risks

**✅ NEW Approach (REST API):**
```php
// REST endpoint
POST /wp-json/vqcheckout/v1/address-by-phone
Headers: X-WP-Nonce: xxx
Body: {phone, recaptcha_token}
```

**Benefits:**
- ✅ Built-in WP authentication
- ✅ Proper error responses
- ✅ Easy to rate-limit
- ✅ Testable
- ✅ Standard format

---

## III. REST API ENDPOINTS

### 3.1. Address Auto-fill Endpoint

#### **Endpoint Definition**

```php
namespace VQ\Rest;

class Address_Controller extends \WP_REST_Controller {
    
    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route('vqcheckout/v1', '/address-by-phone', [
            'methods'  => 'POST',
            'callback' => [$this, 'get_address_by_phone'],
            'permission_callback' => '__return_true', // Public but protected
            'args' => [
                'phone' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [$this, 'validate_phone']
                ],
                'recaptcha_token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    /**
     * Handle address lookup by phone
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_address_by_phone($request) {
        // Step 1: Verify reCAPTCHA
        $captcha_result = $this->verify_captcha($request);
        if (is_wp_error($captcha_result)) {
            $this->log_security_event('captcha_fail', $request);
            return $captcha_result;
        }
        
        // Step 2: Check nonce (optional for public endpoint, but recommended)
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && !wp_verify_nonce($nonce, 'wp_rest')) {
            $this->log_security_event('invalid_nonce', $request);
            return new \WP_Error(
                'invalid_nonce',
                __('Invalid security token', 'vq-checkout'),
                ['status' => 403]
            );
        }
        
        // Step 3: Rate limiting
        $rate_limit_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_limit_check)) {
            $this->log_security_event('rate_limit', $request);
            return $rate_limit_check;
        }
        
        // Step 4: Normalize phone
        $phone = $request->get_param('phone');
        $normalized_phone = $this->normalize_phone($phone);
        
        if (!$normalized_phone) {
            return new \WP_Error(
                'invalid_phone',
                __('Invalid phone number format', 'vq-checkout'),
                ['status' => 400]
            );
        }
        
        // Step 5: Query address (privacy-by-design)
        $address_data = $this->find_address_by_phone($normalized_phone);
        
        if (!$address_data) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No address found for this phone number', 'vq-checkout')
            ], 404);
        }
        
        // Step 6: Return minimal data only
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'province_code' => $address_data['province_code'],
                'province_name' => $address_data['province_name'],
                'ward_code' => $address_data['ward_code'],
                'ward_name' => $address_data['ward_name']
                // NOTE: NO full address, customer name, email, etc.
            ]
        ], 200);
    }
    
    /**
     * Verify reCAPTCHA token (server-side)
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    private function verify_captcha($request) {
        $token = $request->get_param('recaptcha_token');
        $settings = get_option('vqcheckout_settings', []);
        
        if (empty($settings['recaptcha_enabled'])) {
            return true; // Disabled
        }
        
        $secret_key = $settings['recaptcha_secret_key'] ?? '';
        if (empty($secret_key)) {
            return new \WP_Error(
                'recaptcha_not_configured',
                __('reCAPTCHA not configured', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        // Verify with Google
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $this->get_client_ip($request)
            ]
        ]);
        
        if (is_wp_error($response)) {
            return new \WP_Error(
                'recaptcha_verify_failed',
                __('Failed to verify reCAPTCHA', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check success
        if (empty($body['success'])) {
            return new \WP_Error(
                'recaptcha_failed',
                __('reCAPTCHA verification failed', 'vq-checkout'),
                ['status' => 403]
            );
        }
        
        // For v3: Check score
        if ($settings['recaptcha_type'] === 'v3') {
            $threshold = $settings['recaptcha_threshold'] ?? 0.5;
            $score = $body['score'] ?? 0;
            
            if ($score < $threshold) {
                return new \WP_Error(
                    'recaptcha_low_score',
                    sprintf(
                        __('reCAPTCHA score too low: %s (threshold: %s)', 'vq-checkout'),
                        $score,
                        $threshold
                    ),
                    ['status' => 403, 'score' => $score]
                );
            }
        }
        
        // Verify action (v3)
        if (!empty($body['action']) && $body['action'] !== 'address_lookup') {
            return new \WP_Error(
                'recaptcha_invalid_action',
                __('Invalid reCAPTCHA action', 'vq-checkout'),
                ['status' => 403]
            );
        }
        
        // Verify hostname
        if (!empty($body['hostname'])) {
            $allowed_hosts = [
                parse_url(home_url(), PHP_URL_HOST),
                parse_url(site_url(), PHP_URL_HOST)
            ];
            
            if (!in_array($body['hostname'], $allowed_hosts, true)) {
                return new \WP_Error(
                    'recaptcha_invalid_hostname',
                    __('Invalid reCAPTCHA hostname', 'vq-checkout'),
                    ['status' => 403]
                );
            }
        }
        
        return true;
    }
    
    /**
     * Check rate limit
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    private function check_rate_limit($request) {
        $settings = get_option('vqcheckout_settings', []);
        
        if (empty($settings['rate_limit_enabled'])) {
            return true; // Disabled
        }
        
        $ip = $this->get_client_ip($request);
        $phone = $request->get_param('phone');
        
        // Create unique key per IP + phone hash
        $key = 'vq_rl_' . md5($ip . ':' . $phone);
        
        // Get current attempts
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            // First attempt
            set_transient($key, 1, 600); // 10 minutes
            return true;
        }
        
        // Check limit
        $max_attempts = $settings['rate_limit_requests'] ?? 10;
        
        if ($attempts >= $max_attempts) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Try again in %d minutes.', 'vq-checkout'),
                    ceil((600 - (time() - get_option('_transient_timeout_' . $key, 0))) / 60)
                ),
                ['status' => 429]
            );
        }
        
        // Increment
        set_transient($key, $attempts + 1, 600);
        
        return true;
    }
    
    /**
     * Get client IP (handle proxies)
     *
     * @param \WP_REST_Request $request
     * @return string
     */
    private function get_client_ip($request) {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Standard proxy
            'HTTP_X_REAL_IP',         // Nginx
            'REMOTE_ADDR'             // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Normalize phone number
     *
     * @param string $phone
     * @return string|false
     */
    private function normalize_phone($phone) {
        // Remove all non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Vietnam formats:
        // +84912345678 → 0912345678
        // 84912345678 → 0912345678
        // 0912345678 → 0912345678
        
        if (substr($phone, 0, 2) === '84') {
            $phone = '0' . substr($phone, 2);
        }
        
        // Validate length (10-11 digits)
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            return false;
        }
        
        // Must start with 0
        if ($phone[0] !== '0') {
            return false;
        }
        
        return $phone;
    }
    
    /**
     * Validate phone format
     *
     * @param string $phone
     * @param \WP_REST_Request $request
     * @param string $param
     * @return bool
     */
    public function validate_phone($phone, $request, $param) {
        return $this->normalize_phone($phone) !== false;
    }
    
    /**
     * Find address by phone (privacy-aware)
     *
     * @param string $phone
     * @return array|null
     */
    private function find_address_by_phone($phone) {
        global $wpdb;
        
        // Query most recent order with this phone
        // HPOS compatible
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            
            // HPOS query
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta
                 WHERE meta_key = '_billing_phone'
                 AND meta_value = %s
                 ORDER BY order_id DESC
                 LIMIT 1",
                $phone
            ));
            
        } else {
            // Legacy postmeta query
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_billing_phone'
                 AND meta_value = %s
                 AND post_id IN (
                     SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order'
                 )
                 ORDER BY post_id DESC
                 LIMIT 1",
                $phone
            ));
        }
        
        if (!$order_id) {
            return null;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        // Get ward code from order meta
        $province_code = $order->get_meta('_vq_province_code');
        $ward_code = $order->get_meta('_vq_ward_code');
        
        if (!$province_code || !$ward_code) {
            return null;
        }
        
        // Get names from dataset
        $address_dataset = new \VQ\Data\Address_Dataset();
        $province = $address_dataset->get_province($province_code);
        $ward = $address_dataset->get_ward($ward_code);
        
        return [
            'province_code' => $province_code,
            'province_name' => $province['name'] ?? '',
            'ward_code' => $ward_code,
            'ward_name' => $ward['name'] ?? ''
        ];
    }
    
    /**
     * Log security event
     *
     * @param string $action
     * @param \WP_REST_Request $request
     */
    private function log_security_event($action, $request) {
        global $wpdb;
        
        $ip = $this->get_client_ip($request);
        
        $wpdb->insert(
            $wpdb->prefix . 'vqcheckout_security_log',
            [
                'ip' => inet_pton($ip),  // Binary format
                'action' => $action,
                'ctx' => $request->get_route(),
                'created_at' => current_time('mysql'),
                'data_json' => wp_json_encode([
                    'user_agent' => $request->get_header('user_agent'),
                    'referer' => $request->get_header('referer'),
                    'params' => array_keys($request->get_params())
                ])
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
}
```

---

### 3.2. Admin CRUD Endpoints

#### **Rates Controller**

```php
namespace VQ\Rest;

class Rates_Controller extends \WP_REST_Controller {
    
    /**
     * Register routes
     */
    public function register_routes() {
        // List rates
        register_rest_route('vqcheckout/v1', '/rates', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_rates'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        // Create rate
        register_rest_route('vqcheckout/v1', '/rates', [
            'methods'  => 'POST',
            'callback' => [$this, 'create_rate'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_rate_schema()
        ]);
        
        // Update rate
        register_rest_route('vqcheckout/v1', '/rates/(?P<id>\d+)', [
            'methods'  => 'PUT',
            'callback' => [$this, 'update_rate'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_rate_schema()
        ]);
        
        // Delete rate
        register_rest_route('vqcheckout/v1', '/rates/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [$this, 'delete_rate'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        // Batch update orders (drag-drop)
        register_rest_route('vqcheckout/v1', '/rates/batch-order', [
            'methods'  => 'POST',
            'callback' => [$this, 'batch_update_orders'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'orders' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
    }
    
    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('manage_woocommerce');
    }
    
    /**
     * Get rate schema (for validation)
     */
    private function get_rate_schema() {
        return [
            'instance_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'rate_order' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'label' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    return !empty($value) && strlen($value) <= 190;
                }
            ],
            'base_cost' => [
                'required' => true,
                'type' => 'number',
                'sanitize_callback' => 'floatval',
                'validate_callback' => function($value) {
                    return $value >= 0;
                }
            ],
            'is_block_rule' => [
                'type' => 'boolean',
                'default' => false
            ],
            'stop_processing' => [
                'type' => 'boolean',
                'default' => true
            ],
            'ward_codes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'validate_callback' => function($value) {
                    return is_array($value) && !empty($value);
                }
            ],
            'conditions' => [
                'type' => 'array',
                'default' => null
            ]
        ];
    }
    
    /**
     * Get rates
     */
    public function get_rates($request) {
        $instance_id = $request->get_param('instance_id');
        
        if (!$instance_id) {
            return new \WP_Error(
                'missing_instance_id',
                __('Instance ID is required', 'vq-checkout'),
                ['status' => 400]
            );
        }
        
        $repository = new \VQ\Data\Repositories\Rate_Repository();
        $rates = $repository->find_by_instance($instance_id, [
            'load_wards' => true
        ]);
        
        $data = array_map(function($rate) {
            return $rate->to_array();
        }, $rates);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }
    
    /**
     * Create rate
     */
    public function create_rate($request) {
        $data = [
            'instance_id' => $request->get_param('instance_id'),
            'rate_order' => $request->get_param('rate_order'),
            'label' => $request->get_param('label'),
            'base_cost' => $request->get_param('base_cost'),
            'is_block_rule' => $request->get_param('is_block_rule') ?? false,
            'stop_processing' => $request->get_param('stop_processing') ?? true,
            'ward_codes' => $request->get_param('ward_codes'),
            'conditions' => $request->get_param('conditions')
        ];
        
        $repository = new \VQ\Data\Repositories\Rate_Repository();
        $rate_id = $repository->insert($data);
        
        if (!$rate_id) {
            return new \WP_Error(
                'create_failed',
                __('Failed to create rate', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        // Invalidate cache
        $this->invalidate_cache($data['instance_id']);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => ['rate_id' => $rate_id],
            'message' => __('Rate created successfully', 'vq-checkout')
        ], 201);
    }
    
    /**
     * Update rate
     */
    public function update_rate($request) {
        $rate_id = (int) $request->get_param('id');
        
        $data = [];
        foreach (['rate_order', 'label', 'base_cost', 'is_block_rule', 
                  'stop_processing', 'ward_codes', 'conditions'] as $key) {
            if ($request->has_param($key)) {
                $data[$key] = $request->get_param($key);
            }
        }
        
        $repository = new \VQ\Data\Repositories\Rate_Repository();
        $success = $repository->update($rate_id, $data);
        
        if (!$success) {
            return new \WP_Error(
                'update_failed',
                __('Failed to update rate', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        // Invalidate cache
        $rate = $repository->find($rate_id);
        if ($rate) {
            $this->invalidate_cache($rate->instance_id);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Rate updated successfully', 'vq-checkout')
        ], 200);
    }
    
    /**
     * Delete rate
     */
    public function delete_rate($request) {
        $rate_id = (int) $request->get_param('id');
        
        $repository = new \VQ\Data\Repositories\Rate_Repository();
        
        // Get instance_id before delete (for cache invalidation)
        $rate = $repository->find($rate_id);
        $instance_id = $rate ? $rate->instance_id : null;
        
        $success = $repository->delete($rate_id);
        
        if (!$success) {
            return new \WP_Error(
                'delete_failed',
                __('Failed to delete rate', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        // Invalidate cache
        if ($instance_id) {
            $this->invalidate_cache($instance_id);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Rate deleted successfully', 'vq-checkout')
        ], 200);
    }
    
    /**
     * Batch update orders (drag-drop)
     */
    public function batch_update_orders($request) {
        $orders = $request->get_param('orders');
        
        if (!is_array($orders) || empty($orders)) {
            return new \WP_Error(
                'invalid_orders',
                __('Invalid orders data', 'vq-checkout'),
                ['status' => 400]
            );
        }
        
        // Sanitize
        $sanitized = [];
        foreach ($orders as $rate_id => $order) {
            $sanitized[absint($rate_id)] = absint($order);
        }
        
        $repository = new \VQ\Data\Repositories\Rate_Repository();
        $success = $repository->batch_update_orders($sanitized);
        
        if (!$success) {
            return new \WP_Error(
                'batch_update_failed',
                __('Failed to update orders', 'vq-checkout'),
                ['status' => 500]
            );
        }
        
        // Invalidate cache (get instance_id from first rate)
        $first_rate_id = array_key_first($sanitized);
        $rate = $repository->find($first_rate_id);
        if ($rate) {
            $this->invalidate_cache($rate->instance_id);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Orders updated successfully', 'vq-checkout')
        ], 200);
    }
    
    /**
     * Invalidate cache for instance
     */
    private function invalidate_cache($instance_id) {
        $cache_manager = new \VQ\Cache\Cache_Manager();
        $cache_manager->invalidate_instance($instance_id);
    }
}
```

---

## IV. RECAPTCHA SERVICE

### 4.1. Captcha Service Class

```php
namespace VQ\Security;

class Captcha_Service {
    
    /**
     * Verify reCAPTCHA token
     *
     * @param string $token
     * @param string $action Expected action (v3 only)
     * @param string|null $ip Client IP
     * @return array ['success' => bool, 'score' => float, 'error' => string]
     */
    public static function verify($token, $action = null, $ip = null) {
        $settings = get_option('vqcheckout_settings', []);
        
        if (empty($settings['recaptcha_enabled'])) {
            return ['success' => true, 'bypassed' => true];
        }
        
        $secret_key = $settings['recaptcha_secret_key'] ?? '';
        if (empty($secret_key)) {
            return [
                'success' => false,
                'error' => 'reCAPTCHA not configured'
            ];
        }
        
        // Call Google API
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $ip
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message()
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check success
        if (empty($body['success'])) {
            return [
                'success' => false,
                'error' => 'Verification failed',
                'error_codes' => $body['error-codes'] ?? []
            ];
        }
        
        // For v3: Check score
        $result = ['success' => true];
        
        if ($settings['recaptcha_type'] === 'v3') {
            $score = $body['score'] ?? 0;
            $threshold = $settings['recaptcha_threshold'] ?? 0.5;
            
            $result['score'] = $score;
            
            if ($score < $threshold) {
                $result['success'] = false;
                $result['error'] = sprintf(
                    'Score too low: %s (threshold: %s)',
                    $score,
                    $threshold
                );
            }
            
            // Verify action
            if ($action && (!empty($body['action']) && $body['action'] !== $action)) {
                $result['success'] = false;
                $result['error'] = 'Invalid action';
            }
        }
        
        // Verify hostname
        if (!empty($body['hostname'])) {
            $allowed_hosts = apply_filters('vq_recaptcha_allowed_hosts', [
                parse_url(home_url(), PHP_URL_HOST)
            ]);
            
            if (!in_array($body['hostname'], $allowed_hosts, true)) {
                $result['success'] = false;
                $result['error'] = 'Invalid hostname';
            }
        }
        
        return $result;
    }
    
    /**
     * Render reCAPTCHA field (frontend)
     *
     * @param string $action Action name for v3
     * @return string HTML
     */
    public static function render_field($action = 'checkout') {
        $settings = get_option('vqcheckout_settings', []);
        
        if (empty($settings['recaptcha_enabled'])) {
            return '';
        }
        
        $site_key = $settings['recaptcha_site_key'] ?? '';
        $type = $settings['recaptcha_type'] ?? 'v3';
        
        if ($type === 'v2') {
            // v2 Checkbox
            return sprintf(
                '<div class="g-recaptcha" data-sitekey="%s"></div>',
                esc_attr($site_key)
            );
        } else {
            // v3 Invisible
            return sprintf(
                '<input type="hidden" name="recaptcha_token" id="vq-recaptcha-token" data-action="%s">',
                esc_attr($action)
            );
        }
    }
    
    /**
     * Enqueue reCAPTCHA script
     */
    public static function enqueue_script() {
        $settings = get_option('vqcheckout_settings', []);
        
        if (empty($settings['recaptcha_enabled'])) {
            return;
        }
        
        $site_key = $settings['recaptcha_site_key'] ?? '';
        $type = $settings['recaptcha_type'] ?? 'v3';
        
        if ($type === 'v2') {
            wp_enqueue_script(
                'google-recaptcha-v2',
                'https://www.google.com/recaptcha/api.js',
                [],
                null,
                true
            );
        } else {
            wp_enqueue_script(
                'google-recaptcha-v3',
                'https://www.google.com/recaptcha/api.js?render=' . $site_key,
                [],
                null,
                true
            );
            
            // Generate token on page load
            wp_add_inline_script('google-recaptcha-v3', "
                grecaptcha.ready(function() {
                    var tokenField = document.getElementById('vq-recaptcha-token');
                    if (tokenField) {
                        var action = tokenField.dataset.action || 'checkout';
                        grecaptcha.execute('{$site_key}', {action: action})
                            .then(function(token) {
                                tokenField.value = token;
                            });
                    }
                });
            ");
        }
    }
}
```

---

## V. RATE LIMITER

### 5.1. Rate Limiter Class

```php
namespace VQ\Security;

class Rate_Limiter {
    
    /**
     * Check if action is allowed
     *
     * @param string $action Action identifier
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $limit Max requests
     * @param int $window Time window in seconds
     * @return bool|\WP_Error True if allowed, WP_Error if limited
     */
    public static function check($action, $identifier, $limit = 10, $window = 600) {
        $key = sprintf('vq_rl_%s_%s', $action, md5($identifier));
        
        // Get current attempts
        $data = get_transient($key);
        
        if ($data === false) {
            // First attempt
            self::track($key, 1, $window);
            return true;
        }
        
        $attempts = is_array($data) ? $data['attempts'] : $data;
        
        if ($attempts >= $limit) {
            $ttl = self::get_ttl($key);
            
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Please try again in %d seconds.', 'vq-checkout'),
                    $ttl
                ),
                [
                    'status' => 429,
                    'retry_after' => $ttl
                ]
            );
        }
        
        // Increment
        self::track($key, $attempts + 1, $window);
        
        return true;
    }
    
    /**
     * Track attempt
     *
     * @param string $key
     * @param int $attempts
     * @param int $window
     */
    private static function track($key, $attempts, $window) {
        set_transient($key, [
            'attempts' => $attempts,
            'first_attempt' => $attempts === 1 ? time() : null
        ], $window);
    }
    
    /**
     * Get TTL for key
     *
     * @param string $key
     * @return int Seconds remaining
     */
    private static function get_ttl($key) {
        $timeout_key = '_transient_timeout_' . $key;
        $timeout = get_option($timeout_key);
        
        if ($timeout) {
            return max(0, $timeout - time());
        }
        
        return 0;
    }
    
    /**
     * Reset limit for identifier
     *
     * @param string $action
     * @param string $identifier
     */
    public static function reset($action, $identifier) {
        $key = sprintf('vq_rl_%s_%s', $action, md5($identifier));
        delete_transient($key);
    }
    
    /**
     * Get remaining attempts
     *
     * @param string $action
     * @param string $identifier
     * @param int $limit
     * @return int
     */
    public static function get_remaining($action, $identifier, $limit = 10) {
        $key = sprintf('vq_rl_%s_%s', $action, md5($identifier));
        $data = get_transient($key);
        
        if ($data === false) {
            return $limit;
        }
        
        $attempts = is_array($data) ? $data['attempts'] : $data;
        
        return max(0, $limit - $attempts);
    }
}
```

---

## VI. SANITIZATION & VALIDATION

### 6.1. Input Sanitization Standards

```php
namespace VQ\Utils;

class Sanitizer {
    
    /**
     * Sanitize rate data
     *
     * @param array $data
     * @return array
     */
    public static function sanitize_rate_data($data) {
        return [
            'instance_id' => absint($data['instance_id'] ?? 0),
            'rate_order' => absint($data['rate_order'] ?? 0),
            'label' => sanitize_text_field($data['label'] ?? ''),
            'base_cost' => floatval($data['base_cost'] ?? 0),
            'is_block_rule' => (bool) ($data['is_block_rule'] ?? false),
            'stop_processing' => (bool) ($data['stop_processing'] ?? true),
            'ward_codes' => array_map('sanitize_text_field', (array) ($data['ward_codes'] ?? [])),
            'conditions' => self::sanitize_conditions($data['conditions'] ?? null)
        ];
    }
    
    /**
     * Sanitize conditions JSON
     *
     * @param mixed $conditions
     * @return array|null
     */
    private static function sanitize_conditions($conditions) {
        if (!is_array($conditions) || empty($conditions)) {
            return null;
        }
        
        $sanitized = [];
        
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            
            $sanitized[] = [
                'min_total' => isset($condition['min_total']) ? floatval($condition['min_total']) : null,
                'max_total' => isset($condition['max_total']) ? floatval($condition['max_total']) : null,
                'cost_override' => isset($condition['cost_override']) ? floatval($condition['cost_override']) : null
            ];
        }
        
        return empty($sanitized) ? null : $sanitized;
    }
    
    /**
     * Sanitize settings data
     *
     * @param array $settings
     * @return array
     */
    public static function sanitize_settings($settings) {
        return [
            // reCAPTCHA
            'recaptcha_enabled' => (bool) ($settings['recaptcha_enabled'] ?? false),
            'recaptcha_type' => in_array($settings['recaptcha_type'] ?? '', ['v2', 'v3']) ? 
                $settings['recaptcha_type'] : 'v3',
            'recaptcha_site_key' => sanitize_text_field($settings['recaptcha_site_key'] ?? ''),
            'recaptcha_secret_key' => sanitize_text_field($settings['recaptcha_secret_key'] ?? ''),
            'recaptcha_threshold' => max(0, min(1, floatval($settings['recaptcha_threshold'] ?? 0.5))),
            
            // Rate Limit
            'rate_limit_enabled' => (bool) ($settings['rate_limit_enabled'] ?? true),
            'rate_limit_requests' => max(1, absint($settings['rate_limit_requests'] ?? 10)),
            'rate_limit_window' => max(60, absint($settings['rate_limit_window'] ?? 600)),
            
            // Anti-spam
            'antispam_enabled' => (bool) ($settings['antispam_enabled'] ?? false),
            'antispam_blocked_ips' => self::sanitize_ip_list($settings['antispam_blocked_ips'] ?? ''),
            'antispam_blocked_keywords' => self::sanitize_keyword_list($settings['antispam_blocked_keywords'] ?? '')
        ];
    }
    
    /**
     * Sanitize IP list
     *
     * @param string $list
     * @return array
     */
    private static function sanitize_ip_list($list) {
        $lines = explode("\n", $list);
        $sanitized = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#') === 0) {
                continue; // Skip empty and comments
            }
            
            // Validate IP or CIDR
            if (filter_var($line, FILTER_VALIDATE_IP) || self::validate_cidr($line)) {
                $sanitized[] = $line;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate CIDR notation
     *
     * @param string $cidr
     * @return bool
     */
    private static function validate_cidr($cidr) {
        if (strpos($cidr, '/') === false) {
            return false;
        }
        
        list($ip, $mask) = explode('/', $cidr, 2);
        
        return filter_var($ip, FILTER_VALIDATE_IP) && is_numeric($mask) && $mask >= 0 && $mask <= 32;
    }
    
    /**
     * Sanitize keyword list
     *
     * @param string $list
     * @return array
     */
    private static function sanitize_keyword_list($list) {
        $lines = explode("\n", $list);
        $sanitized = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (!empty($line) && strpos($line, '#') !== 0) {
                $sanitized[] = strtolower($line);
            }
        }
        
        return $sanitized;
    }
}
```

---

## VII. SECURITY CHECKLIST

### ✅ Implementation Checklist

- [ ] **reCAPTCHA v2/v3 server-side verify** (not just client-side)
- [ ] **Rate limiting** on all public endpoints (5-10 req/10min)
- [ ] **Nonce verification** on all admin actions
- [ ] **Capability checks** (`manage_woocommerce`) on admin endpoints
- [ ] **Input sanitization** (100% of user input)
- [ ] **Output escaping** (esc_html, esc_attr, esc_url)
- [ ] **Prepared statements** (100% of DB queries)
- [ ] **CSRF protection** (wp_verify_nonce)
- [ ] **XSS prevention** (no innerHTML, use textContent)
- [ ] **SQL injection prevention** (wpdb::prepare)
- [ ] **Security logging** (failed attempts, rate limits)
- [ ] **Log cleanup** (cron job, 7-14 days TTL)
- [ ] **Privacy by design** (minimal data return)
- [ ] **IP validation** (handle proxies correctly)

---

## VIII. TESTING SECURITY

### 8.1. Security Test Cases

```php
// Test reCAPTCHA bypass
public function test_recaptcha_bypass_blocked() {
    $response = wp_remote_post('/wp-json/vqcheckout/v1/address-by-phone', [
        'body' => [
            'phone' => '0912345678',
            'recaptcha_token' => 'invalid_token'
        ]
    ]);
    
    $this->assertEquals(403, wp_remote_retrieve_response_code($response));
}

// Test rate limit
public function test_rate_limit_enforced() {
    $data = ['phone' => '0912345678', 'recaptcha_token' => 'valid'];
    
    // Make 10 requests (should pass)
    for ($i = 0; $i < 10; $i++) {
        $response = wp_remote_post('/wp-json/vqcheckout/v1/address-by-phone', ['body' => $data]);
        $this->assertNotEquals(429, wp_remote_retrieve_response_code($response));
    }
    
    // 11th request (should fail with 429)
    $response = wp_remote_post('/wp-json/vqcheckout/v1/address-by-phone', ['body' => $data]);
    $this->assertEquals(429, wp_remote_retrieve_response_code($response));
}

// Test SQL injection
public function test_sql_injection_prevented() {
    $response = wp_remote_post('/wp-json/vqcheckout/v1/address-by-phone', [
        'body' => [
            'phone' => "0912345678' OR '1'='1",
            'recaptcha_token' => 'valid'
        ]
    ]);
    
    // Should sanitize and not cause SQL error
    $this->assertNotEquals(500, wp_remote_retrieve_response_code($response));
}

// Test XSS prevention
public function test_xss_prevented() {
    $malicious = '<script>alert("XSS")</script>';
    
    $response = wp_remote_post('/wp-json/vqcheckout/v1/rates', [
        'body' => [
            'label' => $malicious,
            'instance_id' => 1,
            // ... other fields
        ],
        'headers' => [
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ]
    ]);
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Label should be sanitized
    $this->assertNotContains('<script>', $body['data']['label']);
}
```

---

## IX. SUMMARY - TÓM TẮT

### Key Security Features

✅ **REST API Architecture:**
- Standard WordPress REST API
- Proper authentication/authorization
- Structured error responses
- Testable endpoints

✅ **reCAPTCHA:**
- Server-side verification
- Support v2 & v3
- Score threshold configurable
- Hostname validation

✅ **Rate Limiting:**
- Transient-based (scalable)
- Configurable limits
- Per-IP + per-action
- Graceful error messages

✅ **Input Sanitization:**
- 100% coverage
- Type-specific sanitizers
- Array/JSON validation
- Whitelist approach

✅ **Output Escaping:**
- Context-aware escaping
- No innerHTML injection
- JSON encoding for API

✅ **Security Logging:**
- Track failed attempts
- TTL-based cleanup
- Privacy-aware (no PII)
- Queryable for analysis

---

**Document Owner:** Security Engineer  
**Last Updated:** 2025-11-05

---

**END OF SECURITY & API DOCUMENT**

*Bảo mật là ưu tiên hàng đầu. Không thỏa hiệp.*

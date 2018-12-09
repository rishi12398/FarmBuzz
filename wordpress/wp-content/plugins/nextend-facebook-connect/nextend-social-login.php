<?php

require_once(NSL_PATH . '/includes/exceptions.php');

require_once dirname(__FILE__) . '/NSL/Persistent/Persistent.php';
require_once dirname(__FILE__) . '/NSL/Notices.php';
require_once dirname(__FILE__) . '/NSL/REST.php';

require_once dirname(__FILE__) . '/NSL/GDPR.php';

require_once(NSL_PATH . '/class-settings.php');
require_once(NSL_PATH . '/includes/provider.php');
require_once(NSL_PATH . '/admin/admin.php');

require_once(NSL_PATH . '/compat.php');

class NextendSocialLogin {

    public static $version = '3.0.14';

    public static $nslPROMinVersion = '3.0.14';

    public static $proxyPage = false;

    public static function checkVersion() {
        $isAuthorizedDomainChanged = NextendSocialLogin::isProAddonAuthorizedDomainChanged();

        if (version_compare(self::$version, NextendSocialLoginPRO::$nslMinVersion, '<')) {
            if (did_action('init')) {
                NextendSocialLogin::noticeUpdateFree();
            } else {
                add_action('init', 'NextendSocialLogin::noticeUpdateFree');
            }

            return false;
        }
        if (version_compare(NextendSocialLoginPRO::$version, self::$nslPROMinVersion, '<')) {
            if (did_action('init')) {
                NextendSocialLogin::noticeUpdatePro();
            } else {
                add_action('init', 'NextendSocialLogin::noticeUpdatePro');
            }

            return false;
        }

        if ($isAuthorizedDomainChanged) {
            return false;
        }

        return true;
    }

    public static function isProAddonAuthorizedDomainChanged() {

        $authorizedDomain = preg_replace('/^www\./', '', NextendSocialLogin::$settings->get('authorized_domain'));
        if (NextendSocialLogin::$settings->get('license_key_ok') == '1' && $authorizedDomain != '') {
            $authorizedDomain = '.' . $authorizedDomain;
            $currentDomain    = '.' . preg_replace('/^www\./', '', NextendSocialLogin::getDomain());
            if ($authorizedDomain != $currentDomain && strrpos($currentDomain, $authorizedDomain) !== strlen($currentDomain) - strlen($authorizedDomain)) {
                if (is_admin() && current_user_can('manage_options')) {
                    if (did_action('init')) {
                        NextendSocialLogin::noticeChangedDomain();
                    } else {
                        add_action('init', 'NextendSocialLogin::noticeChangedDomain');
                    }
                }

                return true;
            }
        }

        return false;
    }


    public static function noticeUpdateFree() {
        if (is_admin() && current_user_can('manage_options')) {
            $file = 'nextend-facebook-connect/nextend-facebook-connect.php';
            \NSL\Notices::addError(sprintf(__('Please update %1$s to version %2$s or newer.', 'nextend-facebook-connect'), "Nextend Social Login", NextendSocialLoginPRO::$nslMinVersion) . ' <a href="' . esc_url(wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file)) . '">' . __('Update now!', 'nextend-facebook-connect') . '</a>');
        }
    }

    public static function noticeUpdatePro() {
        if (is_admin() && current_user_can('manage_options')) {
            $file = 'nextend-social-login-pro/nextend-social-login-pro.php';
            \NSL\Notices::addError(sprintf(__('Please update %1$s to version %2$s or newer.', 'nextend-facebook-connect'), "Nextend Social Login Pro Addon", self::$nslPROMinVersion) . ' <a href="' . esc_url(wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file)) . '">' . __('Update now!', 'nextend-facebook-connect') . '</a>');
        }
    }

    public static function noticeChangedDomain() {
        if (is_admin() && current_user_can('manage_options')) {
            // Your site domain name changed so you must authorize the Nextend Social Login Pro Addon again
            \NSL\Notices::addError(sprintf(__('Your domain name changed so you must authorize %1$s again.', 'nextend-facebook-connect'), "Nextend Social Login Pro Addon") . ' <a class="button button-primary" href="' . NextendSocialLoginAdmin::getAdminUrl('domain-changed') . '">Authorize now</a>');
        }
    }

    /** @var NextendSocialLoginSettings */
    public static $settings;

    private static $styles = array(
        'default' => array(
            'container' => 'nsl-container-block'
        ),
        'icon'    => array(
            'container' => 'nsl-container-inline'
        )
    );

    public static $providersPath;

    /**
     * @var NextendSocialProviderDummy[]
     */
    public static $providers = array();

    /**
     * @var NextendSocialProvider[]
     */
    public static $allowedProviders = array();

    /**
     * @var NextendSocialProvider[]
     */
    public static $enabledProviders = array();

    private static $ordering = array();

    private static $loginHeadAdded = false;
    private static $loginMainButtonsAdded = false;
    public static $counter = 1;

    public static $WPLoginCurrentView = '';

    public static $WPLoginCurrentFlow = 'login';

    public static function init() {
        add_action('plugins_loaded', 'NextendSocialLogin::plugins_loaded');
        register_activation_hook(NSL_PATH_FILE, 'NextendSocialLogin::install');

        add_action('activate_nextend-google-connect/nextend-google-connect.php', 'NextendSocialLogin::compatPreventActivationGoogle');
        add_action('activate_nextend-twitter-connect/nextend-twitter-connect.php', 'NextendSocialLogin::compatPreventActivationTwitter');

        add_action('delete_user', 'NextendSocialLogin::delete_user');

        self::$settings = new NextendSocialLoginSettings('nextend_social_login', array(
            'enabled'                          => array(),
            'register-flow-page'               => '',
            'proxy-page'                       => '',
            'ordering'                         => array(
                'facebook',
                'google',
                'twitter'
            ),
            'license_key'                      => '',
            'license_key_ok'                   => '0',
            'terms_show'                       => 0,
            'terms'                            => __('By clicking Register, you accept our <a href="#privacy_policy_url" target="_blank">Privacy Policy</a>', 'nextend-facebook-connect'),
            'store_name'                       => 1,
            'store_email'                      => 1,
            'avatar_store'                     => 1,
            'store_access_token'               => 1,
            'redirect_prevent_external'        => 0,
            'redirect'                         => '',
            'redirect_reg'                     => '',
            'default_redirect'                 => '',
            'default_redirect_reg'             => '',
            'blacklisted_urls'                 => '',
            'target'                           => 'prefer-popup',
            'allow_register'                   => 1,
            'show_login_form'                  => 'show',
            'show_registration_form'           => 'show',
            'login_form_button_style'          => 'default',
            'login_form_layout'                => 'below',
            'show_embedded_login_form'         => 'show',
            'embedded_login_form_button_style' => 'default',
            'embedded_login_form_layout'       => 'below',
            'comment_login_button'             => 'show',
            'comment_button_style'             => 'default',
            'buddypress_register_button'       => 'bp_before_account_details_fields',
            'buddypress_register_button_style' => 'default',
            'woocommerce_login'                => 'after',
            'woocommerce_login_form_layout'    => 'default',
            'woocommerce_register'             => 'after',
            'woocommerce_register_form_layout' => 'default',
            'woocommerce_billing'              => 'before',
            'woocommerce_billing_form_layout'  => 'default',
            'woocoommerce_form_button_style'   => 'default',
            'woocommerce_account_details'      => 'before',

            'memberpress_login_form_button_style'  => 'default',
            'memberpress_login_form_layout'        => 'below-separator',
            'memberpress_signup'                   => 'before',
            'memberpress_signup_form_button_style' => 'default',
            'memberpress_signup_form_layout'       => 'below-separator',
            'memberpress_account_details'          => 'after',
            'registration_notification_notify'     => '0',
            'debug'                                => '0',
            'login_restriction'                    => '0',
            'review_state'                         => -1,
            'woocommerce_dismissed'                => 0,

            'userpro_show_login_form'            => 'show',
            'userpro_show_register_form'         => 'show',
            'userpro_login_form_button_style'    => 'default',
            'userpro_register_form_button_style' => 'default',
            'userpro_login_form_layout'          => 'below',
            'userpro_register_form_layout'       => 'below',

            'ultimatemember_login'                      => 'after',
            'ultimatemember_login_form_button_style'    => 'default',
            'ultimatemember_login_form_layout'          => 'below-separator',
            'ultimatemember_register'                   => 'after',
            'ultimatemember_register_form_button_style' => 'default',
            'ultimatemember_register_form_layout'       => 'below-separator',
            'ultimatemember_account_details'            => 'after',

            'authorized_domain' => '',
        ));

        add_action('itsec_initialized', 'NextendSocialLogin::disable_better_wp_security_block_long_urls', -1);

        add_action('bp_loaded', 'NextendSocialLogin::buddypress_loaded');
    }

    public static function plugins_loaded() {
        if (get_option('nsl-version') != self::$version) {
            NextendSocialLogin::install();

            if (NextendSocialLogin::$settings->get('license_key_ok') == '1' && version_compare(get_option('nsl-version'), '3.0.6', '<=')) {

                add_filter('nsl_update_settings_validate_nextend_social_login', 'NextendSocialLoginAdmin::validateSettings', 10, 2);

                NextendSocialLogin::$settings->update(array(
                    'authorized_domain' => NextendSocialLogin::getDomain()
                ));
            }

            update_option('nsl-version', self::$version, true);
            wp_redirect(set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
            exit;
        }
        do_action('nsl_start');

        load_plugin_textdomain('nextend-facebook-connect', false, basename(dirname(__FILE__)) . '/languages/');

        NextendSocialLoginAdmin::init();

        \NSL\Notices::init();

        self::$providersPath = NSL_PATH . '/providers/';

        $providers = array_diff(scandir(self::$providersPath), array(
            '..',
            '.'
        ));

        foreach ($providers AS $provider) {
            if (file_exists(self::$providersPath . $provider . '/' . $provider . '.php')) {
                require_once(self::$providersPath . $provider . '/' . $provider . '.php');
            }
        }

        do_action('nsl_add_providers');

        self::$ordering = array_flip(self::$settings->get('ordering'));
        uksort(self::$providers, 'NextendSocialLogin::sortProviders');
        uksort(self::$allowedProviders, 'NextendSocialLogin::sortProviders');
        uksort(self::$enabledProviders, 'NextendSocialLogin::sortProviders');

        do_action('nsl_providers_loaded');

        add_action('login_form_login', 'NextendSocialLogin::login_form_login');
        add_action('login_form_register', 'NextendSocialLogin::login_form_register');
        add_action('login_form_link', 'NextendSocialLogin::login_form_link');
        add_action('bp_core_screen_signup', 'NextendSocialLogin::bp_login_form_register');

        add_action('login_form_unlink', 'NextendSocialLogin::login_form_unlink');


        add_action('template_redirect', 'NextendSocialLogin::alternate_login_page_template_redirect');

        add_action('parse_request', 'NextendSocialLogin::editProfileRedirect');

        if (count(self::$enabledProviders) > 0) {

            if (self::$settings->get('show_login_form') == 'hide') {
                add_action('login_form_login', 'NextendSocialLogin::removeLoginFormAssets');
            } else {
                add_action('login_form', 'NextendSocialLogin::addLoginFormButtons');
                add_action('login_form_login', 'NextendSocialLogin::jQuery');
            }

            if (NextendSocialLogin::$settings->get('show_registration_form') == 'hide') {
                add_action('login_form_register', 'NextendSocialLogin::removeLoginFormAssets');
            } else {
                add_action('register_form', 'NextendSocialLogin::addLoginFormButtons');
                add_action('login_form_register', 'NextendSocialLogin::jQuery');
            }

            if (NextendSocialLogin::$settings->get('show_embedded_login_form') != 'hide') {
                add_filter('login_form_bottom', 'NextendSocialLogin::filterAddEmbeddedLoginFormButtons');
            }
            add_action('bp_sidebar_login_form', 'NextendSocialLogin::addLoginButtons');

            add_action('profile_personal_options', 'NextendSocialLogin::addLinkAndUnlinkButtons');


            /*
             * Shopkeeper theme fix. Remove normal login form hooks while WooCommerce registration/login form rendering
             */
            add_action('woocommerce_login_form_start', 'NextendSocialLogin::remove_action_login_form_buttons');
            add_action('woocommerce_login_form_end', 'NextendSocialLogin::add_action_login_form_buttons');

            add_action('woocommerce_register_form_start', 'NextendSocialLogin::remove_action_login_form_buttons');
            add_action('woocommerce_register_form_end', 'NextendSocialLogin::add_action_login_form_buttons');
            /* End of fix */


            add_action('wp_head', 'NextendSocialLogin::styles', 100);
            add_action('admin_head', 'NextendSocialLogin::styles', 100);
            add_action('login_head', 'NextendSocialLogin::loginHead', 100);

            add_action('wp_print_footer_scripts', 'NextendSocialLogin::scripts', 100);
            add_action('login_footer', 'NextendSocialLogin::scripts', 100);

            require_once dirname(__FILE__) . '/includes/avatar.php';

            add_shortcode('nextend_social_login', 'NextendSocialLogin::shortcode');
        }

        add_action('admin_print_footer_scripts', 'NextendSocialLogin::scripts', 100);

        require_once(NSL_PATH . '/widget.php');

        do_action('nsl_init');

        /**
         * Fix for Hide my WP plugin @see https://codecanyon.net/item/hide-my-wp-amazing-security-plugin-for-wordpress/4177158
         */
        if (class_exists('HideMyWP', false)) {
            if (!empty($_REQUEST['loginSocial'])) {
                global $HideMyWP;
                $loginPath = '/wp-login.php';
                if (is_object($HideMyWP) && substr($_SERVER['PHP_SELF'], -1 * strlen($loginPath))) {
                    $login_query = $HideMyWP->opt('login_query');
                    if (!$login_query) {
                        $login_query = 'hide_my_wp';
                    }
                    $_GET[$login_query] = $HideMyWP->opt('admin_key');
                }
            }
        }

        if (!empty($_REQUEST['loginSocial'])) {

            // Fix for all-in-one-wp-security-and-firewall
            if (empty($_GET['action'])) {
                $_GET['action'] = 'nsl-login';
            }

            // Fix for wps-hide-login
            if (empty($_REQUEST['action'])) {
                $_REQUEST['action'] = 'nsl-login';
            }

            // Fix for Social Rabbit as it catch our code response from Facebook
            if (class_exists('\SR\Utils\Scheduled', true)) {
                add_action('init', 'NextendSocialLogin::fixSocialRabbit', 0);
            }

            // Fix for Dokan https://wedevs.com/dokan/
            if (function_exists('dokan_redirect_to_register')) {
                remove_action('login_init', 'dokan_redirect_to_register', 10);
            }

            // Fix for Jetpack SSO
            add_filter('jetpack_sso_bypass_login_forward_wpcom', '__return_false');
        }
    }

    public static function fixSocialRabbit() {
        remove_action('init', '\SR\Utils\Scheduled::init', 10);
    }

    public static function removeLoginFormAssets() {
        remove_action('login_head', 'NextendSocialLogin::loginHead', 100);
        remove_action('wp_print_footer_scripts', 'NextendSocialLogin::scripts', 100);
        remove_action('login_footer', 'NextendSocialLogin::scripts', 100);
    }

    public static function styles() {

        $stylesheet = self::get_template_part('style.css');
        if (!empty($stylesheet) && file_exists($stylesheet)) {
            echo '<style type="text/css">' . file_get_contents($stylesheet) . '</style>';
        }
    }

    public static function loginHead() {
        self::styles();

        $template = self::get_template_part('login/' . sanitize_file_name(self::$settings->get('login_form_layout')) . '.php');
        if (!empty($template) && file_exists($template)) {
            require($template);
        }

        self::$loginHeadAdded = true;
    }

    public static function scripts() {
        static $once = null;
        if ($once === null) {
            $scripts = NSL_PATH . '/js/nsl.js';
            if (file_exists($scripts)) {
                echo '<script type="text/javascript">(function (undefined) {var targetWindow =' . wp_json_encode(self::$settings->get('target')) . ";\n" . file_get_contents($scripts) . '})();</script>';
            }
            $once = true;
        }
    }

    public static function install() {
        global $wpdb;
        $table_name = $wpdb->prefix . "social_users";
        $sql        = "CREATE TABLE " . $table_name . " (`ID` int(11) NOT NULL, `type` varchar(20) NOT NULL, `identifier` varchar(100) NOT NULL, KEY `ID` (`ID`,`type`));";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        //Legacy

        $facebookSettings = maybe_unserialize(get_option('nextend_fb_connect'));

        if ($facebookSettings !== false && !empty($facebookSettings['fb_appid']) && !empty($facebookSettings['fb_secret'])) {
            $providerPath = NSL_PATH . '/providers/facebook/facebook.php';
            if (file_exists($providerPath)) {

                require_once($providerPath);

                self::$providers['facebook']->settings->update(array(
                    'legacy' => 1
                ));
            }
        }
        if (function_exists('new_google_connect_install')) {
            $googleSettings = maybe_unserialize(get_option('nextend_google_connect'));
            if ($googleSettings !== false && !empty($googleSettings['google_client_id']) && !empty($googleSettings['google_client_secret'])) {
                $providerPath = NSL_PATH . '/providers/google/google.php';
                if (file_exists($providerPath)) {

                    require_once($providerPath);

                    self::$providers['google']->settings->update(array(
                        'legacy' => 1
                    ));
                }
            }

            NextendSocialLogin::compatDeactivateGoogle();
            add_action('activated_plugin', 'NextendSocialLogin::compatDeactivateGoogle');
        }

        if (function_exists('new_twitter_connect_install')) {
            $twitterSettings = maybe_unserialize(get_option('nextend_twitter_connect'));
            if ($twitterSettings !== false && !empty($twitterSettings['twitter_consumer_key']) && !empty($twitterSettings['twitter_consumer_secret'])) {
                $providerPath = NSL_PATH . '/providers/twitter/twitter.php';
                if (file_exists($providerPath)) {

                    require_once($providerPath);

                    self::$providers['twitter']->settings->update(array(
                        'legacy' => 1
                    ));
                }
            }

            NextendSocialLogin::compatDeactivateTwitter();
            add_action('activated_plugin', 'NextendSocialLogin::compatDeactivateTwitter');
        }
    }

    public static function compatDeactivateGoogle() {
        if (is_plugin_active('nextend-google-connect/nextend-google-connect.php')) {
            deactivate_plugins('nextend-google-connect/nextend-google-connect.php');
        }
    }

    public static function compatPreventActivationGoogle() {
        printf(__('%s took the place of Nextend Google Connect. You can delete Nextend Google Connect as it is not needed anymore.', 'nextend-facebook-connect'), "Nextend Social Login");
        exit;
    }

    public static function compatDeactivateTwitter() {
        if (is_plugin_active('nextend-twitter-connect/nextend-twitter-connect.php')) {
            deactivate_plugins('nextend-twitter-connect/nextend-twitter-connect.php');
        }
    }

    public static function compatPreventActivationTwitter() {
        printf(__('%s took the place of Nextend Twitter Connect. You can delete Nextend Twitter Connect as it is not needed anymore.', 'nextend-facebook-connect'), "Nextend Social Login");
        exit;
    }

    public static function sortProviders($a, $b) {
        if (isset(self::$ordering[$a]) && isset(self::$ordering[$b])) {
            if (self::$ordering[$a] < self::$ordering[$b]) {
                return -1;
            }

            return 1;
        }
        if (isset(self::$ordering[$a])) {
            return -1;
        }

        return 1;
    }

    /**
     * @param $provider NextendSocialProviderDummy
     */
    public static function addProvider($provider) {
        if (in_array($provider->getId(), self::$settings->get('enabled'))) {
            if ($provider->isTested() && $provider->enable()) {
                self::$enabledProviders[$provider->getId()] = $provider;
            }
        }
        self::$providers[$provider->getId()] = $provider;

        if ($provider instanceof NextendSocialProvider) {
            self::$allowedProviders[$provider->getId()] = $provider;
        }
    }

    public static function enableProvider($providerID) {
        if (isset(self::$providers[$providerID])) {
            $enabled   = self::$settings->get('enabled');
            $enabled[] = self::$providers[$providerID]->getId();
            $enabled   = array_unique($enabled);

            self::$settings->update(array(
                'enabled' => $enabled
            ));
        }
    }

    public static function disableProvider($providerID) {
        if (isset(self::$providers[$providerID])) {

            $enabled = array_diff(self::$settings->get('enabled'), array(self::$providers[$providerID]->getId()));

            self::$settings->update(array(
                'enabled' => $enabled
            ));
        }
    }

    public static function isProviderEnabled($providerID) {
        return isset(self::$enabledProviders[$providerID]);
    }

    public static function alternate_login_page_template_redirect() {

        $isAlternatePage = ((self::getProxyPage() !== false && is_page(self::getProxyPage())) || (self::getRegisterFlowPage() !== false && is_page(self::getRegisterFlowPage())));
        if ($isAlternatePage) {
            nocache_headers();

            if (!empty($_REQUEST['loginSocial']) || (isset($_GET['interim_login']) && $_GET['interim_login'] === 'nsl')) {

                $action = isset($_GET['action']) ? $_GET['action'] : 'login';
                if (!in_array($action, array(
                    'login',
                    'register',
                    'link',
                    'unlink'
                ))) {
                    $action = 'login';
                }
                switch ($action) {
                    case 'login':
                        NextendSocialLogin::login_form_login();
                        break;
                    case 'register':
                        NextendSocialLogin::login_form_register();
                        break;
                    case 'link':
                        NextendSocialLogin::login_form_link();
                        break;
                    case 'unlink':
                        NextendSocialLogin::login_form_unlink();
                        break;
                }
            } else {
                if (!is_front_page() && !is_home()) {
                    wp_redirect(home_url());
                    exit;
                }
            }
        }
    }

    public static function login_form_login() {
        self::$WPLoginCurrentView = 'login';
        self::login_init();
    }

    public static function login_form_register() {
        self::$WPLoginCurrentView = 'register';
        self::login_init();
    }

    public static function bp_login_form_register() {
        self::$WPLoginCurrentView = 'register-bp';
        self::login_init();
    }

    public static function login_form_link() {
        self::$WPLoginCurrentView = 'link';
        self::login_init();
    }

    public static function login_form_unlink() {
        self::$WPLoginCurrentView = 'unlink';
        self::login_init();
    }

    public static function login_init() {

        add_filter('wp_login_errors', 'NextendSocialLogin::wp_login_errors');

        if (isset($_GET['interim_login']) && $_GET['interim_login'] === 'nsl' && is_user_logged_in()) {
            self::onInterimLoginSuccess();

        }

        if (isset($_REQUEST['loginFacebook']) && $_REQUEST['loginFacebook'] == '1') {
            $_REQUEST['loginSocial'] = 'facebook';
        }
        if (isset($_REQUEST['loginGoogle']) && $_REQUEST['loginGoogle'] == '1') {
            $_REQUEST['loginSocial'] = 'google';
        }
        if (isset($_REQUEST['loginTwitter']) && $_REQUEST['loginTwitter'] == '1') {
            $_REQUEST['loginTwitter'] = 'twitter';
        }

        if (isset($_REQUEST['loginSocial']) && isset(self::$providers[$_REQUEST['loginSocial']]) && (self::$providers[$_REQUEST['loginSocial']]->isEnabled() || self::$providers[$_REQUEST['loginSocial']]->isTest())) {

            nocache_headers();

            self::$providers[$_REQUEST['loginSocial']]->connect();
        }
    }

    private static function onInterimLoginSuccess() {
        require_once(NSL_PATH . '/admin/interim.php');
    }

    public static function wp_login_errors($errors) {

        if (empty($errors)) {
            $errors = new WP_Error();
        }

        $errorMessages = \NSL\Notices::getErrors();
        if ($errorMessages !== false) {
            foreach ($errorMessages AS $errorMessage) {
                $errors->add('error', $errorMessage);
            }
        }

        return $errors;
    }

    public static function editProfileRedirect() {
        global $wp;

        if (isset($wp->query_vars['editProfileRedirect'])) {
            if (function_exists('bp_loggedin_user_domain')) {
                header('LOCATION: ' . bp_loggedin_user_domain() . 'profile/edit/group/1/');
            } else {
                header('LOCATION: ' . self_admin_url('profile.php'));
            }
            exit;
        }
    }

    public static function jQuery() {
        wp_enqueue_script('jquery');
    }

    public static function filterAddEmbeddedLoginFormButtons($ret) {

        return $ret . self::getEmbeddedLoginForm();
    }

    private static function getEmbeddedLoginForm() {
        ob_start();
        self::styles();

        $index = self::$counter++;

        $containerID = 'nsl-custom-login-form-' . $index;

        echo '<div id="' . $containerID . '">' . self::renderButtonsWithContainer(self::$settings->get('embedded_login_form_button_style'), false) . '</div>';

        $template = self::get_template_part('embedded-login/' . sanitize_file_name(self::$settings->get('embedded_login_form_layout')) . '.php');
        if (!empty($template) && file_exists($template)) {
            include($template);
        }

        return ob_get_clean();
    }

    public static function addLoginFormButtons() {
        echo self::getRenderedLoginButtons();
    }

    public static function addLoginButtons() {
        echo self::getRenderedLoginButtons();
    }

    public static function remove_action_login_form_buttons() {
        remove_action('login_form', 'NextendSocialLogin::addLoginFormButtons');
        remove_action('register_form', 'NextendSocialLogin::addLoginFormButtons');
    }

    public static function add_action_login_form_buttons() {
        add_action('login_form', 'NextendSocialLogin::addLoginFormButtons');
        add_action('register_form', 'NextendSocialLogin::addLoginFormButtons');
    }

    private static function getRenderedLoginButtons() {
        if (!self::$loginHeadAdded || self::$loginMainButtonsAdded) {

            return self::getEmbeddedLoginForm();
        }

        self::$loginMainButtonsAdded = true;

        $ret = '<div id="nsl-custom-login-form-main">';
        $ret .= self::renderButtonsWithContainer(self::$settings->get('login_form_button_style'), false);
        $ret .= '</div>';


        return $ret;
    }

    public static function addLinkAndUnlinkButtons() {
        echo self::renderLinkAndUnlinkButtons();
    }

    /**
     * @param bool|false|string $heading
     * @param bool              $link
     * @param bool              $unlink
     *
     * @return string
     */
    public static function renderLinkAndUnlinkButtons($heading = '', $link = true, $unlink = true) {
        if (count(self::$enabledProviders)) {
            $buttons = '';
            if ($heading !== false) {
                if (empty($heading)) {
                    $heading = __('Social Login', 'nextend-facebook-connect');
                }
                $buttons = '<h2>' . $heading . '</h2>';
            }
            $providerCount = 0;
            foreach (self::$enabledProviders AS $provider) {
                if ($provider->isCurrentUserConnected()) {
                    if ($unlink) {
                        $buttons .= $provider->getUnLinkButton();
                        $providerCount++;
                    }
                } else {
                    if ($link) {
                        $buttons .= $provider->getLinkButton();
                        $providerCount++;
                    }
                }
            }

            if ($providerCount > 0) {
                return '<div class="nsl-container ' . self::$styles['default']['container'] . '">' . $buttons . '</div>';
            }
        }

        return '';
    }

    /**
     * @deprecated
     *
     * @param $user_id
     *
     * @return bool
     */
    public static function getAvatar($user_id) {
        foreach (self::$enabledProviders AS $provider) {
            $avatar = $provider->getAvatar($user_id);
            if ($avatar !== false) {
                return $avatar;
            }
        }

        return false;
    }

    public static function shortcode($atts) {
        if (!is_array($atts)) {
            $atts = array();
        }

        $atts = array_merge(array(
            'login'   => 1,
            'link'    => 0,
            'unlink'  => 0,
            'heading' => false
        ), $atts);

        if (!is_user_logged_in()) {

            if (filter_var($atts['login'], FILTER_VALIDATE_BOOLEAN) === false) {
                return '';
            }

            $atts = array_merge(array(
                'style'       => 'default',
                'provider'    => false,
                'redirect'    => false,
                'trackerdata' => false
            ), $atts);

            $providers  = false;
            $providerID = $atts['provider'] === false ? false : $atts['provider'];
            if ($providerID !== false && isset(self::$enabledProviders[$providerID])) {
                $providers = array(self::$enabledProviders[$providerID]);
            }

            return self::renderButtonsWithContainerAndTitle($atts['heading'], $atts['style'], $providers, $atts['redirect'], $atts['trackerdata']);
        }

        $link   = filter_var($atts['link'], FILTER_VALIDATE_BOOLEAN);
        $unlink = filter_var($atts['unlink'], FILTER_VALIDATE_BOOLEAN);

        if ($link || $unlink) {
            return self::renderLinkAndUnlinkButtons($atts['heading'], $link, $unlink);
        }

        return '';
    }

    /**
     * @param string                       $style
     * @param bool|NextendSocialProvider[] $providers
     * @param bool|string                  $redirect_to
     *
     * @return string
     */
    public static function renderButtonsWithContainer($style = 'default', $providers = false, $redirect_to = false, $trackerData = false) {
        return self::renderButtonsWithContainerAndTitle(false, $style, $providers, $redirect_to, $trackerData);
    }

    private static function renderButtonsWithContainerAndTitle($heading = false, $style = 'default', $providers = false, $redirect_to = false, $trackerData = false) {

        if (!isset(self::$styles[$style])) {
            $style = 'default';
        }

        if ($redirect_to === false) {
            $redirect_to = NextendSocialLogin::getCurrentPageURL();
        }

        $enabledProviders = false;
        if (is_array($providers)) {
            $enabledProviders = array();
            foreach ($providers AS $provider) {
                if ($provider && isset(self::$enabledProviders[$provider->getId()])) {
                    $enabledProviders[$provider->getId()] = $provider;
                }
            }
        }
        if ($enabledProviders === false) {
            $enabledProviders = self::$enabledProviders;
        }

        if (count($enabledProviders)) {
            $buttons = '';
            foreach ($enabledProviders AS $provider) {
                $buttons .= $provider->getConnectButton($style, $redirect_to, $trackerData);
            }

            if (!empty($heading)) {
                $heading = '<h2>' . $heading . '</h2>';
            } else {
                $heading = '';
            }

            $ret = '<div class="nsl-container ' . self::$styles[$style]['container'] . '">' . $heading . $buttons . '</div>';
            if (defined('DOING_AJAX') && DOING_AJAX) {
                $id  = md5(uniqid('nsl-ajax-'));
                $ret = '<div id="' . $id . '">' . $ret . '</div><script>(function($){$("#' . $id . '").find("a").each(function(i,el){var href=$(el).attr("href");if(href.indexOf("?") === -1){href+="?"}else{href+="&"}$(el).attr("href", href+"redirect="+encodeURIComponent(window.location.href));});})(jQuery);</script>';
            }

            return $ret;
        }

        return '';
    }


    public static function getCurrentPageURL() {

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return '';
        }

        $currentUrl = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

        if (!self::isAllowedRedirectUrl($currentUrl)) {
            return false;
        }

        return $currentUrl;
    }

    public static function getLoginUrl($scheme = null) {
        static $alternateLoginPage = null;
        if ($alternateLoginPage === null) {
            $proxyPage = self::getProxyPage();
            if ($proxyPage !== false) {
                $alternateLoginPage = get_permalink($proxyPage);
            }
            if (empty($alternateLoginPage)) {
                $alternateLoginPage = false;
            }
        }

        if ($alternateLoginPage !== false) {
            return $alternateLoginPage;
        }

        return site_url('wp-login.php', $scheme);
    }

    public static function getRegisterUrl() {

        return wp_registration_url();
    }

    public static function isAllowedRedirectUrl($url) {
        $loginUrl = self::getLoginUrl();

        // If the currentUrl is the loginUrl, then we should not return it for redirects
        if (strpos($url, $loginUrl) === 0) {
            return false;
        }

        $loginUrl2 = site_url('wp-login.php');

        // If the currentUrl is the loginUrl, then we should not return it for redirects
        if ($loginUrl2 !== $loginUrl && strpos($url, $loginUrl2) === 0) {
            return false;
        }

        $registerUrl = wp_registration_url();
        // If the currentUrl is the registerUrl, then we should not return it for redirects
        if (strpos($url, $registerUrl) === 0) {
            return false;
        }

        $blacklistedUrls = NextendSocialLogin::$settings->get('blacklisted_urls');
        if (!empty($blacklistedUrls)) {
            $blackListedUrlArray = preg_split('/\r\n|\r|\n/', $blacklistedUrls);
            // If the currentUrl is blacklisted, then we should not return it for redirects
            foreach ($blackListedUrlArray as $blackListedUrl) {
                //If the url contains the blackListedUrl returns false
                if (strpos($url, $blackListedUrl) !== false) {
                    return false;
                }
            }
        }


        return true;
    }

    public static function get_template_part($file_name, $name = null) {
        // Execute code for this part
        do_action('get_template_part_' . $file_name, $file_name, $name);

        // Setup possible parts
        $templates   = array();
        $templates[] = $file_name;

        // Allow template parts to be filtered
        $templates = apply_filters('nsl_get_template_part', $templates, $file_name, $name);

        // Return the part that is found
        return self::locate_template($templates);
    }

    public static function locate_template($template_names) {
        // No file found yet
        $located = false;

        // Try to find a template file
        foreach ((array)$template_names as $template_name) {

            // Continue if template is empty
            if (empty($template_name)) {
                continue;
            }

            // Trim off any slashes from the template name
            $template_name = ltrim($template_name, '/');
            // Check child theme first
            if (file_exists(trailingslashit(get_stylesheet_directory()) . 'nsl/' . $template_name)) {
                $located = trailingslashit(get_stylesheet_directory()) . 'nsl/' . $template_name;
                break;

                // Check parent theme next
            } else if (file_exists(trailingslashit(get_template_directory()) . 'nsl/' . $template_name)) {
                $located = trailingslashit(get_template_directory()) . 'nsl/' . $template_name;
                break;

                // Check theme compatibility last
            } else if (file_exists(trailingslashit(self::get_templates_dir()) . $template_name)) {
                $located = trailingslashit(self::get_templates_dir()) . $template_name;
                break;
            } else if (defined('NSL_PRO_PATH') && file_exists(trailingslashit(NSL_PRO_PATH) . 'template-parts/' . $template_name)) {
                $located = trailingslashit(NSL_PRO_PATH) . 'template-parts/' . $template_name;
                break;
            }
        }

        return $located;
    }

    public static function get_templates_dir() {
        return NSL_PATH . '/template-parts';
    }

    public static function delete_user($user_id) {
        /** @var $wpdb WPDB */
        global $wpdb, $blog_id;

        $wpdb->delete($wpdb->prefix . 'social_users', array(
            'ID' => $user_id
        ), array(
            '%d'
        ));

        $attachment_id = get_user_meta($user_id, $wpdb->get_blog_prefix($blog_id) . 'user_avatar', true);
        if (wp_attachment_is_image($attachment_id)) {
            wp_delete_attachment($attachment_id, true);
        }

    }

    public static function disable_better_wp_security_block_long_urls() {
        if (class_exists('ITSEC_System_Tweaks', false)) {
            remove_action('itsec_initialized', array(
                ITSEC_System_Tweaks::get_instance(),
                'block_long_urls'
            ));
        }
    }

    public static function buddypress_loaded() {
        add_action('bp_settings_setup_nav', 'NextendSocialLogin::bp_settings_setup_nav');
    }

    public static function bp_settings_setup_nav() {

        if (!bp_is_active('settings')) {
            return;
        }

        // Determine user to use.
        if (bp_loggedin_user_domain()) {
            $user_domain = bp_loggedin_user_domain();
        } else {
            return;
        }

        // Get the settings slug.
        $settings_slug = bp_get_settings_slug();

        bp_core_new_subnav_item(array(
            'name'            => __('Social Accounts', 'nextend-facebook-connect'),
            'slug'            => 'social',
            'parent_url'      => trailingslashit($user_domain . $settings_slug),
            'parent_slug'     => $settings_slug,
            'screen_function' => 'NextendSocialLogin::bp_display_account_link',
            'position'        => 30,
            'user_has_access' => bp_core_can_edit_settings()
        ), 'members');

    }

    public static function bp_display_account_link() {

        add_action('bp_template_title', 'NextendSocialLogin::bp_template_title');
        add_action('bp_template_content', 'NextendSocialLogin::bp_template_content');
        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    public static function bp_template_title() {
        _e('Social Login', 'nextend-facebook-connect');
    }

    public static function bp_template_content() {
        echo self::renderLinkAndUnlinkButtons(false);
    }

    public static function getTrackerData() {
        return \NSL\Persistent\Persistent::get('trackerdata');
    }

    public static function getDomain() {
        return parse_url(site_url(), PHP_URL_HOST);
    }

    public static function getRegisterFlowPage() {
        static $registerFlowPage = null;
        if ($registerFlowPage === null) {
            $registerFlowPage = intval(self::$settings->get('register-flow-page'));
            if (empty($registerFlowPage) || get_post($registerFlowPage) === null) {
                $registerFlowPage = false;
            }
        }

        return $registerFlowPage;
    }

    public static function getProxyPage() {
        static $proxyPage = null;
        if ($proxyPage === null) {
            $proxyPage = intval(self::$settings->get('proxy-page'));
            if (empty($proxyPage) || get_post($proxyPage) === null) {
                $proxyPage = false;
            }
        }

        return $proxyPage;
    }
}

NextendSocialLogin::init();

<div class="nsl-admin-content">
    <style>
        .nsl-admin-notices {
            display: none;
        }
    </style>
    <h1 class="title"><?php _e('Debug', 'nextend-facebook-connect'); ?></h1>
    <?php


    if (NextendSocialLoginAdmin::isPro()) {
        $proAddonState = NextendSocialLoginAdmin::getProState();
        echo "<p><b>Pro Addon State</b>: " . $proAddonState . "</p>";

        $authorizedDomain = NextendSocialLogin::$settings->get('authorized_domain');
        echo "<p><b>Authorized Domain</b>: " . $authorizedDomain . "</p>";

        $currentDomain = NextendSocialLogin::getDomain();
        echo "<p><b>Current Domain</b>: " . $currentDomain . "</p><br>";

        $licenseKey = substr(NextendSocialLogin::$settings->get('license_key'), 0, 8);
        echo "<p><b>License Key</b>: " . $licenseKey . "...</p>";

        $isLicenseKeyOk = NextendSocialLogin::$settings->get('license_key_ok');
        echo "<p><b>License Key OK</b>: " . (boolval($isLicenseKeyOk) ? 'Yes' : 'No') . "</p><br>";
    }

    $defaultRedirect = NextendSocialLogin::$settings->get('default_redirect');
    echo "<p><b>Default Redirect URL</b>: " . $defaultRedirect . "</p>";

    $defaultRedirectReg = NextendSocialLogin::$settings->get('default_redirect_reg');
    echo "<p><b>Default Reg Redirect URL</b>: " . $defaultRedirectReg . "</p><br>";

    $fixRedirect = NextendSocialLogin::$settings->get('redirect');
    echo "<p><b>Fix Redirect URL</b>: " . $fixRedirect . "</p>";

    $fixRedirectReg = NextendSocialLogin::$settings->get('redirect_reg');
    echo "<p><b>Fix Reg Redirect URL</b>: " . $fixRedirectReg . "</p><br>";

    echo '<h1>' . __('Test network connection with providers', 'nextend-facebook-connect') . '</h1>';

    if (!function_exists('curl_init')) {
        ?>

        <div class="error">
            <p>
                <?php _e('You don\'t have cURL support, please enable it in php.ini!', 'nextend-facebook-connect'); ?>
            </p>
        </div>

        <?php
    } else {
        foreach (NextendSocialLogin::$allowedProviders AS $provider) {
            ?>
            <p>
            <a target="_blank" href="<?php echo add_query_arg('provider', $provider->getId(), NextendSocialLoginAdmin::getAdminUrl('test-connection')); ?>" class="button button-primary">
                <?php printf(__('Test %1$s connection', 'nextend-facebook-connect'), $provider->getLabel()); ?>
            </a>
        </p>
            <?php
        }
    }

    ?>
</div>
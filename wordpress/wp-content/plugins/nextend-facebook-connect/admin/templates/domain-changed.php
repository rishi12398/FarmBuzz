<div class="nsl-admin-content">
    <style>
        .nsl-admin-notices {
            display: none;
        }
    </style>
    <h1 class="title"><?php _e('Pro Addon - Authorized domain has been changed', 'nextend-facebook-connect'); ?></h1>
    <?php

    $authorizedDomain = NextendSocialLogin::$settings->get('authorized_domain');
    if (!empty($authorizedDomain) && $authorizedDomain != NextendSocialLogin::getDomain()) {
        ?>
        <p>
            <?php
            _e('<b>You must authorize your new domain</b> to receive <b>updates and support</b> in the future.', 'nextend-facebook-connect');
            ?>
        </p>

        <p>
            <?php
            _e('You can authorize your new domain by completing the following steps:', 'nextend-facebook-connect');
            ?>
        </p>
        <ol>
            <li><?php printf(__('Navigate to %s', 'nextend-facebook-connect'), '<a href="https://secure.nextendweb.com/" target="_blank">https://secure.nextendweb.com/</a>'); ?></li>
            <li><?php _e('Log in with your credentials if you are not logged in', 'nextend-facebook-connect'); ?></li>
            <li><?php printf(__('Find your old domain name: <b>%s</b>', 'nextend-facebook-connect'), $authorizedDomain); ?></li>
            <li><?php printf(__('Click on the %1$s next to your domain name.', 'nextend-facebook-connect'), '"Deactivate"'); ?></li>
            <li><?php printf(__('Authorize your %1$s by clicking on the following button.', 'nextend-facebook-connect'), "Nextend Social Login Pro Addon"); ?></li>
        </ol>
        <?php
        NextendSocialLoginAdmin::authorizeBox();
    } else {
        echo '<div class="updated"><p>' . __('The authorized domain name of your site is fine!', 'nextend-facebook-connect') . '</p></div>';
    }


    ?>
</div>
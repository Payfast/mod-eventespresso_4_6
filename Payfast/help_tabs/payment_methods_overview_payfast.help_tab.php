<?php
/**
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 */
?>
<h3><?php _e('PayFast', 'event_espresso'); ?></h3>
<p>
<?php _e('You will need a PayFast Individual or Business account to receive payments using PayFast.', 'event_espresso'); ?>
</p>
<h3><?php _e('PayFast Settings', 'event_espresso'); ?></h3>
<ul>
    <li>
<strong><?php _e('Debug Mode', 'event_espresso'); ?></strong><br />
<?php _e('This is the equivalent to sandbox or test mode. If this option is enabled, be sure to enter the sandbox credentials in the necessary fields. Be sure to turn this setting off when you are done testing.', 'event_espresso'); ?>
</li>
<li>
<strong><?php _e('Image URL', 'event_espresso'); ?></strong><br />
<?php _e('Select an image/logo that should be shown on the payment page for PayFast.', 'event_espresso'); ?>
</li>
<li>
<strong><?php _e('PayFast Merchant ID', 'event_espresso'); ?></strong><br />
<?php _e( 'The merchant id available from the \'Settings\' page within the logged in dashboard on PayFast.co.za' );?>
</li>
<li>
<strong><?php _e('PayFast Merchant Key', 'event_espresso'); ?></strong><br />
<?php _e( 'The merchant key available from the \'Settings\' page within the logged in dashboard on PayFast.co.za' );?>
</li>
<strong><?php _e('PayFast Passphrase', 'event_espresso'); ?></strong><br />
<?php _e( 'ONLY INSERT A VALUE INTO THE SECURE PASSPHRASE IF YOU HAVE SET THIS ON THE INTEGRATION PAGE OF THE LOGGED IN AREA OF THE PAYFAST WEBSITE!' );?>
</li>

</ul>
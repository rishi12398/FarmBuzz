<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'wordpressuser');

/** MySQL database password */
define('DB_PASSWORD', 'smart12398');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('FS_METHOD', 'direct');
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'NJ@dE+=l5)3*8m9lwVc{jY.Zgnp[clQ-@Eo[Fwf`E7hwXk7:dhB.}E /ISxY]NBV');
define('SECURE_AUTH_KEY',  'jjhVf_`hiZ8~t0Iv2,|#ukw]8X7+e7{;al&m+yf ZBnX>mb%0._~+[+Y*baJRrb@');
define('LOGGED_IN_KEY',    'B);6C1F=TM^LdC:yy 9wa9|0 ~4o*u8v=8U#n+T+&=f,F-Y^r~6X6nA3f+yI<P#|');
define('NONCE_KEY',        '|dT}aG^dO~mxcD9}_Uab1+:2wVut-|n`Xh()<sLBmL{-)T$YDA<Px6e_X0Dq4L}U');
define('AUTH_SALT',        'mTK-h8/{$x5]GbGHyY|ft72BTlJBoZ|VCutA2yveWG*U(@xAM>9Pohgq-~e`F|!4');
define('SECURE_AUTH_SALT', 'M8&[^W~OYQJ[-OUKCC=i_a!-E*h|=@@-}j-SX^4/kma^>f;^<?d.<v2mmoq5vEoL');
define('LOGGED_IN_SALT',   'RjTEqvQF#}YF|A))!|j[x2+-G8f);~nVoJ&l<!F4X5c9oZTL5x|To$QQ^pNXIBcQ');
define('NONCE_SALT',       'ArX[{tSw=Qvs,0!4RBQg8P,)Kl5~sM]V!.Z)F@a[#s8j~|)h@zJb,e9[6;Ny[(x6');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

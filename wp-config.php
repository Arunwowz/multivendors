<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'multivendorlocal' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'TQCUrgWW;7l@8H,:Y3MH:M:}5W/8E+HS6o,$pfg^6IE93KQ%IXnTUO0e]MacV`V$' );
define( 'SECURE_AUTH_KEY',  'A&:K^48!-8w4{Mz%CVvf0>HAGRc^sz`=_YNTITj YBf^3#,x=Q,r60?HQAzVq-).' );
define( 'LOGGED_IN_KEY',    '9?6];OrJUUmC`^+Z}i2t#*r[;aixLmI*m!,Uol$!+3MnR7DpYDg).$(~D-yyYhgo' );
define( 'NONCE_KEY',        '2Hz]gUyse5}OK>;|hp*Adv[7`maT[qO Sh->K<o k_Reu<-+Kt{yS`x_+]Pn|YlK' );
define( 'AUTH_SALT',        'pnVK`YEX$PvxvV)ZJs|Q77gD&S|)@ g3gv~rd7,{k.UR6G=!/G.I.|+oCOG`8>A9' );
define( 'SECURE_AUTH_SALT', '!:?^2|jO.f*Xvp.qS)E(F~RbvCFn{6r#3P l@c!5/r@EOh67TAaeQ~ZF?(.~08O:' );
define( 'LOGGED_IN_SALT',   'w&R0,0v?6S;KeQSP(},J5>&l#jxaq:0(#@U-lV=b|&AKaHG/f6v?%(c;QyL;3#r6' );
define( 'NONCE_SALT',       '~mU=9KQk`]Npmj&`^e3:{I}5B>JJQuFYUb|J1*}5kF~9g<lf?*S<}50fj]3AljhW' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

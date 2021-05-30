<?php
/**
 * Plugin Name:		ImageBase642File
 * Author:		James John
 * Author URI:		https://www.linkedin.com/in/donjajo/
 * Description:		An over-engineered-memory-safe search and replace base64 inline images to an uploaded URL when post is saved
 * Version:		1.0
 * Plugin URI:		https://github.com/donjajo/imgb642file
 * Text Domain:		imgb642file
 * License:		GPLv3
 * License URI:		https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP:	7.1
 * Requires at least:	5.1
 */

defined( 'IMGB642FILE_ABSPATH' )  || define( 'IMGB642FILE_ABSPATH', __DIR__ );

require_once IMGB642FILE_ABSPATH . '/class/class-imgb642file.php';

if ( ! isset ( $GLOBALS['imgb642file'] ) ) {
	$GLOBALS['imgb642file'] = new \ImgB642File\ImgB642File();
}
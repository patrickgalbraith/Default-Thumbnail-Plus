<?php

/*
SLT File Select 0.2.1
http://sltaylor.co.uk/wordpress/plugins/file-select/
Provides themes and plugins with a form interface to select a file from the Media Library.
Created by Steve Taylor
http://sltaylor.co.uk
GPLv2
*/

/* Inspired by code in Professional WordPress Development by Brad Williams, Ozh Richard and Justin Tadlock */

/*
This program is free software; you can redistribute it and/or modify 
it under the terms of the GNU General Public License as published by 
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
GNU General Public License for more details. 

You should have received a copy of the GNU General Public License 
along with this program; if not, write to the Free Software 
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA 
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	_e( "Hi there! I'm just a plugin, not much I can do when called directly." );
	exit;
}

// JavaScript
add_action( 'admin_print_scripts', 'dtp_slt_fs_scripts' );
function dtp_slt_fs_scripts() {
	wp_enqueue_script( 'slt-file-select', plugins_url( '/default-thumbnail-plus/include/slt-file-select.js'), array( 'jquery', 'media-upload', 'thickbox' ) );
	$protocol = isset( $_SERVER[ 'HTTPS' ] ) ? 'https://' : 'http://';
	wp_localize_script( 'slt-file-select', 'slt_file_select', array(
		'ajaxurl'			=> admin_url( 'admin-ajax.php', $protocol ),
		'text_select_file'	=> esc_html__( 'Select', 'slt-file-select' )
	));
}

// Styles
add_action( 'admin_print_styles', 'dtp_slt_fs_styles' );
function dtp_slt_fs_styles() {
	wp_enqueue_style( 'thickbox' );
}

// Disable Flash uploader when this plugin invokes Media Library overlay
add_action( 'admin_init', 'dtp_slt_fs_disable_flash_uploader' );
function dtp_slt_fs_disable_flash_uploader() {
	if ( basename( $_SERVER['SCRIPT_FILENAME'] ) == 'media-upload.php' && array_key_exists( 'slt_fs_field', $_GET ) )
		add_filter( 'flash_uploader', create_function( '$a','return false;' ), 5 );
}

// Output form button
function dtp_slt_fs_button( $name, $value, $label = 'Select file', $preview_size = 'thumbnail', $removable = true) { ?>
    <div class="slt-fs-preview" id="<?php echo esc_attr( $name ); ?>_preview"><?php
		if ( $value && wp_get_attachment_url($value) !== FALSE ) {
			if ( wp_attachment_is_image( $value ) ) {
				// Show image preview
				echo wp_get_attachment_image( $value, $preview_size );
			} else {
				// File link
				echo dtp_slt_fs_file_link( $value );
			}
		} else {
			//default image
			echo '<img width="150" height="150" class="attachment-thumbnail" src="'.plugins_url('/default-thumbnail-plus/img/default-thumb.jpg').'">';
		}
	?></div>
	<div>
		<input type="button" class="button-secondary slt-fs-button" value="<?php echo esc_attr( $label ); ?>" />
		<?php if ( $value && $removable ) { ?>
			<br /><input type="checkbox" name="<?php echo esc_attr( $name ); ?>_remove" value="1" class="slt-fs-remove" /> <label for="<?php echo esc_attr( $name ); ?>_remove"><?php _e( 'Remove' ); ?></label>
		<?php } ?>
		<input type="hidden" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="slt-fs-value" />
		<input type="hidden" value="<?php echo esc_attr( $preview_size ); ?>" name="<?php echo esc_attr( $name ); ?>_preview-size" id="<?php echo esc_attr( $name ); ?>_preview-size" class="slt-fs-preview-size" />
	</div>
<?php }

// AJAX wrapper to get image HTML
add_action( 'wp_ajax_slt_fs_get_file', 'dtp_slt_fs_get_file_ajax' );
function dtp_slt_fs_get_file_ajax() {
	if ( wp_attachment_is_image( $_REQUEST['id'] ) ) {
		echo wp_get_attachment_image( $_REQUEST['id'], $_REQUEST['size'] );
	} else {
		echo dtp_slt_fs_file_link( $_REQUEST['id'] );
	}
	die();

}

// Generate markup for file link
function dtp_slt_fs_file_link( $id ) {
	$attachment_url = wp_get_attachment_url( $id );
	$filetype_check = wp_check_filetype( $attachment_url );
	$filetype_parts = explode( '/', $filetype_check['type'] );
	return '<a href="' . wp_get_attachment_url( $id ) . '" style="display: block; min-height:32px; padding: 10px 0 0 38px; background: url(' . plugins_url( "img/icon-" . $filetype_parts[1] . ".png", __FILE__ ) . ') no-repeat; font-size: 13px; font-weight: bold;">' . basename( $attachment_url ) . '</a>';
}


<?php
/*
Plugin Name: Default Thumbnail Plus
Plugin URI: http://www.pjgalbraith.com/2011/12/default-thumbnail-plus/
Description: Add a default thumbnail image to post's with no post_thumbnail set.
Version: 1.0.1
Author: Patrick Galbraith, gyrus
Author URI: http://www.pjgalbraith.com
License: GPL2 
*/

/*
 * Phuc PN.Truong
 * This plugins did support the multi images for on category or tag.
 * This plugins did not cached category images
 */

/*  Copyright 2011  Patrick Galbraith  (email : patrick.j.galbraith@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation. 

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
	die('Direct script access not allowed');
}

class DefaultPostThumbnailPlugin {
	
	private static $default_config = array(
		'dpt_options' => array(
							'default' => array('attachment_id' => '', 'value' => '')
							//'tag_id' => array( array('attachment_id' => FALSE, 'value' => ''), array('attachment_id' => FALSE, 'value' => '') )
							),
		'dpt_meta_key' => '',
		'dpt_use_first_attachment' => true,
		'dpt_excluded_posts' => array()
	);
	
	static function install() {
		foreach(self::$default_config as $name => $value)
			add_option($name, $value);
	}
	 
	static function init_plugin_menu() {
		global $dpt_plugin_hook;
		$dpt_plugin_hook = add_submenu_page('options-general.php', __('Default Thumbnail Plus'), __('Default Thumb Plus'), 'manage_options', 'DefaultPostThumbnailPlugin', array('DefaultPostThumbnailPlugin', 'get_admin_page_html'));
		
		// Add CSS styles hook
		add_action("admin_head-{$dpt_plugin_hook}", array('DefaultPostThumbnailPlugin', 'admin_register_head'));
	}
	
	static function register_settings() {
		register_setting( 'dpp-options', 'dpt_options' );
		register_setting( 'dpp-options', 'dpt_meta_key' );
		register_setting( 'dpp-options', 'dpt_use_first_attachment' );
		register_setting( 'dpp-options', 'dpt_excluded_posts' );
        register_setting('dpp-options', 'dpt_post_img_cached');
	}
	
	/*-------------------------------------------------------------
	Thumbnail Cascade Order:
	
	- 1 Featured Image
	 |- 2 Custom field
	  |- 3 Image attachment
	   |- 4 Category/Tag/Taxonomy Thumbnail
		|- 5 Default thumbnail
		 |- 6 nothing
	-------------------------------------------------------------*/
	
	static function default_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
		if ( $post_thumbnail_id ) {
			// 1. Do nothing as this will be handled by the core function
		} else {
			
			// If post_id is an excluded post then return default output
			if(in_array($post_id, get_option('dpt_excluded_posts')))
				return $html;
			
			$dpt_options = get_option('dpt_options');
			$default_post_thumbnail_id = FALSE;
			
			// 2. Custom field
			if(get_option('dpt_meta_key')) {
				$default_post_thumbnail_id = get_post_meta($post_id, get_option('dpt_meta_key'), true);
				
				if(is_numeric($default_post_thumbnail_id)) {
					//Does the attachment acutally exist if not then we will set the default_post_thumbnail_id to false
					if(self::post_attachment_exists($default_post_thumbnail_id ) === false)
						$default_post_thumbnail_id = false;
				} else if(empty($default_post_thumbnail_id)) {
					$default_post_thumbnail_id = false;
				} else {
					//This means the default_post_thumbnail_id contains a link to an image not ideal but we will try to deal with it as best we can
					global $_wp_additional_image_sizes;
					$other_attr = '';
					
					if( isset( $_wp_additional_image_sizes[$size] ) ) {
						$width = $_wp_additional_image_sizes[$size]['width'];
						$height = $_wp_additional_image_sizes[$size]['height'];
						$other_attr = image_hwstring($width, $height).'class="attachment-'.$size.' wp-post-image"';
					} else if(is_array($size)) {
						$width = $size[0];
						$height = $size[1];
						$other_attr = image_hwstring($width, $height).'class="attachment-'.$width.'x'.$height.' wp-post-image"';
					}
					
					return '<img src="'.$default_post_thumbnail_id.'" '.$other_attr.' />';
				}
			}
			
			// 3. Image attachment
			if($default_post_thumbnail_id === FALSE && get_option('dpt_use_first_attachment'))
				$default_post_thumbnail_id = self::get_first_post_attachment_id($post_id);
			
			// 4. Category/Tag/Taxonomy thumbnail
			if($default_post_thumbnail_id === FALSE) {
				foreach($dpt_options as $key => $dpt_option_arr) {
					
                    if ($key == 'default')
                        continue;
                    foreach ($dpt_option_arr as $catslug => $dpt_optionArr) {
                        if (is_object_in_term($post_id, $key, $catslug)) { //!empty($dpt_option['attachment_id']) && 
                            //choose randomly the image
                            $i = array_rand($dpt_optionArr);
                            $default_post_thumbnail_id = $dpt_optionArr[$i]['attachment_id'];
					
                            //store cached img
                            update_post_meta($post_id, '_thumbnail_id', $default_post_thumbnail_id);
                            $postImgCached = get_option('dpt_post_img_cached', array());
                            $postImgCached[] = $post_id;
                            update_option('dpt_post_img_cached', $postImgCached);
						} 
					}
				}
			}
			
			// 5. If the post has no attachment load the default thumbnail id
			if($default_post_thumbnail_id === FALSE) 
				$default_post_thumbnail_id = $dpt_options['default']['attachment_id'];
			
			// 6. If option is still not set then return
			if($default_post_thumbnail_id === FALSE) 
				return $html;
			
			$size = apply_filters( 'post_thumbnail_size', $size );
			
			do_action( 'begin_fetch_post_thumbnail_html', $post_id, $default_post_thumbnail_id, $size ); // for "Just In Time" filtering of all of wp_get_attachment_image()'s filters
			$html = wp_get_attachment_image( intval($default_post_thumbnail_id), $size, false, $attr );
			do_action( 'end_fetch_post_thumbnail_html', $post_id, $default_post_thumbnail_id, $size );
		}
		
		return $html;
	}
	
	// Check if an attachment with the specified ID exists
	static function post_attachment_exists($attachment_id) {
		if( wp_get_attachment_image( $attachment_id ) !== '')
			return true;
			
		return false;
	}
	
	static function get_first_post_attachment_id($post_id) {
		$thumbs = get_posts ( 
						array(
							'posts_per_page' => 1,
							'post_type' => 'attachment',
							'post_status' => 'any',
							'post_parent' => $post_id,
							'orderby' => 'ID',
							'order' => 'ASC',
						));
		if ($thumbs)
			return $thumbs[0]->ID;
		
		return false;
	}
	
	static function backend_enqueue_scripts($hook_suffix) {
		
		if($hook_suffix != 'settings_page_DefaultPostThumbnailPlugin')
			return;
		
		wp_enqueue_script(
            'dpt_admin_script', plugins_url('admin-script.js', __FILE__), array('jquery')
		);
	}
	
	static function admin_register_head() {
		?>
		<style>
			.row-title {
				width: 130px;
				text-align: center;
			}
			.row-title img {
				width: 80px;
				height: 80px;
				border: solid 2px white;
				margin-bottom: 4px;
				margin-top: 6px;
				
				-webkit-box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5);
				-moz-box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5);
				box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5); 
			}
            .dtp-item img{
                width: 80px;
                height: 80px;
                border: solid 2px white;
                margin-bottom: 4px;
                margin-top: 6px;

                -webkit-box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5);
                -moz-box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5);
                box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.5); 
            }
            .dtp-item{
                float:left;
            }
			.widefat td {
				vertical-align: middle;
			}
			#template_row {
				display:none; 
			}
		</style><?php
	}
	
	static function contextual_help($contextual_help, $screen_id, $screen) {

		global $dpt_plugin_hook;
		
		if ($screen_id == $dpt_plugin_hook)
			$contextual_help = '<a target="_blank" href="http://www.pjgalbraith.com/2011/12/default-thumbnail-plus/">Full Documentation with Images!</a><br><a target="_blank" href="http://wordpress.org/extend/plugins/default-thumbnail-plus/">WordPress.org Plugin Page</a>';
			
		return $contextual_help;
	}
	
	static function handle_options_update() {
		
        if(isset($_POST['clear_cached'])){
            $postCached = get_option('dpt_post_img_cached');
            delete_option('dpt_post_img_cached');
            for($i = 0; $i < count($postCached); $i++){
                delete_post_thumbnail($postCached[$i]);
            }
            return;
        }

		$dpt_options = array();
		$count = 1;
		$dpt_options['default'] = array('attachment_id' => $_POST['attachment_id_default'], 'value' => '');		
        $count          = $_POST['countimg'];
		
        for ($i = 1; $i <= $count; $i++) {
            if (isset($_POST['filter_name_' . $i]) && !isset($_POST['attachment_id_'.$i.'_remove'])) {
                $dpt_options[$_POST['filter_name_' . $i]][$_POST['filter_value_' . $i]][] = array(
                    'attachment_id' => $_POST['attachment_id_' . $i],
                    'value'         => $_POST['filter_value_' . $i]
                );
            }
		}
		
		update_option( 'dpt_options', $dpt_options );
        update_option( 'dpt_meta_key', $_POST['dpt_meta_key'] );
		update_option( 'dpt_use_first_attachment', $_POST['dpt_use_first_attachment'] );
		
		$excluded_posts_arr = explode(',', $_POST['dpt_excluded_posts']); //explode comma separated string on comma
		array_walk($excluded_posts_arr, create_function('&$val', '$val = trim($val);')); //trim spaces from all post ids
		update_option( 'dpt_excluded_posts', $excluded_posts_arr );
	}
	
	static function get_admin_page_html() {
		
		if (!current_user_can('manage_options')) {
		    wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		if( isset($_POST[ 'dpt_submit_hidden' ]) && $_POST[ 'dpt_submit_hidden' ] == 'Y' ) {
			self::handle_options_update();
			?>
            <div class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
            <?php
		}
		
		foreach(self::$default_config as $name => $value) {
			$$name = get_option($name, $value);
		}
		?>
        <div class="wrap">
		<?php screen_icon(); ?>
        <h2><?php _e('Default Thumbnail Plus') ?></h2>
        <br/>
        <div style="max-width:1200px">
        <form id="dpt_options_form" name="dpt_options_form" method="post" action=""> 
        <?php settings_fields( 'dpp-options' ); ?>
        <input type="hidden" name="dpt_submit_hidden" value="Y">
        <table id="dpt_filter-table" class="widefat">
            <thead>
                <tr>
                    <th class="row-title"><?php _e('Image') ?></th>
                    <th><?php _e('Taxonomy') ?></th>
                    <th><?php _e('Value') ?></th>
                                <th><?php _e('Description + More images') ?></th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <tr data-attachment_id="<?php echo $dpt_options['default']['attachment_id']; ?>" data-taxonomy="" data-value="" data-array_index="0">
                    <td class="row-title"><?php dtp_slt_fs_button( 'attachment_id_default', $dpt_options['default']['attachment_id'], 'Select image', 'thumbnail', false ) ?></td>
                    <td>
                    	<select disabled="disabled">
                            <option value="default">Any</option>
                            <option value="category">Category</option>
                            <option value="post_tag">Tag</option>
                        </select>
                    </td>
                    <td style="color:#999">-</td>
                                <td>This is the default thumbnail that will be loaded if the post has no featured image set.
                                </td>
                    <td></td>
                </tr>
                
                <?php 
				$count = 1; 
				foreach($dpt_options as $key => $dpt_option_arr): 
                                if ($key == 'default') {
                                    continue;
                                }
                                // for traversing through the term slug in category
                                $index = 0;
					
                                foreach ($dpt_option_arr as $catslug => $dpt_optionArr) :
                                    $moreImages = array();
                                    if (isset($dpt_option_arr['attachment_id'])) {
                                        $dpt_option = $dpt_optionArr;
                                    } else {
                                        $dpt_option = array_shift($dpt_optionArr);
                                        $moreImages = $dpt_optionArr;
                                        $index++;
                                    }
                                    ?>
                	
                    <tr data-attachment_id="<?php echo $dpt_option['attachment_id']; ?>" data-taxonomy="<?php echo $key; ?>" data-value="<?php echo $dpt_option['value']; ?>" data-array_index="<?php echo $count; ?>">
                        <td class="row-title"><?php dtp_slt_fs_button( 'attachment_id_'.$count, $dpt_option['attachment_id'], 'Select image', 'thumbnail', false ) ?></td>
                        <td>
                            <select class="filter_name" name="filter_name_<?php echo $count; ?>">
                                <option value="category" <?php echo ($key == 'category') ? 'selected="selected"' : '' ?>>Category</option>
                                <option value="post_tag" <?php echo ($key == 'post_tag') ? 'selected="selected"' : '' ?>>Tag</option>
                                <?php 
                                $taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'names', 'and'); //get a list of custom taxonomies
                                foreach ($taxonomies as $taxonomy ) {
                                    echo '<option value="'.$taxonomy.'" '.(($taxonomy == $key) ? 'selected="selected"' : '').'>'. ucfirst(str_replace('_', ' ', $taxonomy)). '</option>';
                                }
                                ?>
                            </select>
                        </td>
                        <td style="color:#CCC">
                             <input name="filter_value_<?php echo $count; ?>" type="text" value="<?php echo $dpt_option['value']; ?>" class="filter_value regular-text" style="width: 100px;" required="required" />
                        </td>
                                        <td class="row_description">
                                            <div class="more-img-section">
                                                <?php foreach ($moreImages as $img): 
                                                   $count++; 
                                                    ?>
                                                    <div class="dtp-item"><?php dtp_slt_fs_button('attachment_id_' . $count, $img['attachment_id'], 'Select image', 'thumbnail', true) ?>
    <input type="hidden" name="filter_name_<?php echo $count?>" value="<?php echo $key;?>" />
    <input type="hidden" name="filter_value_<?php echo $count?>" value="<?php echo $catslug;?>" />
                                                    </div>
                                                    <?php
                                                endforeach;
                                                ?>
                                            </div>
                                            <div style="clear:both"></div>

                                            <input type="button" class="button-secondary slt-fs-button-more" value="<?php echo esc_attr('More image...'); ?>" />
                                        </td>
                        <td class="row_actions"><a href="javascript:void(0)" onclick="dpt_remove_row(this)"><img alt="Delete Icon" src="<?php echo plugins_url('/default-thumbnail-plus/img/icon-delete.png'); ?>" /></a></td>
                    </tr>
                    
                                    <?php
                                    $count++;
                                endforeach;
                            endforeach;
                            ?>
                
                <tr id="template_row" class="alternate" data-attachment_id="" data-taxonomy="" data-value="" data-array_index="">
                    <td class="row-title"><?php dtp_slt_fs_button( 'attachment_id_template', '', 'Select image', 'thumbnail', false ) ?></td>
                    <td>
                        <select class="filter_name">
                        	<option value="category">Category</option>
                            <option value="post_tag">Tag</option>
							<?php 
							$taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'names', 'and'); //get a list of custom taxonomies
							foreach ($taxonomies as $taxonomy ) {
							    echo '<option value="'.$taxonomy.'">'. ucfirst(str_replace('_', ' ', $taxonomy)). '</option>';
							}
							?>
                        </select>
                    </td>
                    <td style="color:#CCC">
                         <input type="text" value="" class="filter_value regular-text" style="width: 100px;" required="required" />
                    </td>
                                <td class="row_description">
                                    <div class="more-img-section">
                                        <div class="dtp-item">
        <?php dtp_slt_fs_button('attachment_id_sample', 'sample', 'Select image', 'thumbnail', true) ?></div>
                                    </div>
                                    <div style="clear:both"></div>
                                    <input type="button" class="button-secondary slt-fs-button-more" value="<?php echo esc_attr('More image...'); ?>" />
                                </td>
                    <td class="row_actions"><a href="javascript:void(0)" onclick="dpt_remove_row(this)"><img alt="Delete Icon" src="<?php echo plugins_url('/default-thumbnail-plus/img/icon-delete.png'); ?>" /></a></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" style="text-align:center"><input id="dpt_add-filter-btn" class="button-secondary" style="padding:4px" type="submit" value="<?php _e( 'Add Filter' ); ?>" /></th>
                </tr>
            </tfoot>
        </table>
                    <input type="hidden" value="<?php echo $count?>" name="countimg" id="countimg"/>
        
        <br/>
        <p style="margin-top:25px;">
        <fieldset>
            <legend class="screen-reader-text"><span>Automatically use first attachment as fallback if available</span></legend>
            <label for="dpt_use_first_attachment">
                <input name="dpt_use_first_attachment" type="checkbox" id="dpt_use_first_attachment" value="true" <?php echo ($dpt_use_first_attachment == true) ? 'checked="checked"' : ''; ?> />
                Use image attachment if available
            </label>
        </fieldset>
        <span class="description">Automatically use the post's first available image attachment for the thumbnail. This is useful for older posts that haven't got a featured image set.</span>
		</p>
        
        <p style="margin-top:25px;">
        Custom field
        <input id="dpt_meta_key" class="regular-text" type="text" value="<?php echo $dpt_meta_key; ?>" name="dpt_meta_key">
		<br/><span class="description">Enter a custom field key here, it's value if set will become the default post thumbnail for that post. The custom field value can either be an Attachment ID, or a link to an image.</span>
        </p>
        
        <p style="margin-top:25px;">
        Excluded posts
        <input id="dpt_excluded_posts" class="regular-text" type="text" value="<?php echo implode(', ', $dpt_excluded_posts); ?>" name="dpt_excluded_posts">
		<br/><span class="description">List of posts to be ignored by this plugin. Comma separated e.g. 10, 2, 7, 14</span>
        </p>
        
        <br/>
        <p><input id="dpt_submit-btn" class="button-primary" type="submit" name="Save" value="<?php _e( 'Save Changes' ); ?>" /></p>
        </form>
                <form name="dpt_options_form" method="post" action=""> 
                    <?php settings_fields('dpp-options'); ?>
                    <input type="hidden" name="dpt_submit_hidden" value="Y">
                    <input id="dpt-clear-cached" class="button-primary" type="submit" name="clear_cached" value="<?php _e('Clear thumbnail cached')?>" />
                </form>
        </div>
        <?php
	}
	 
    }

    //end class

add_action( 'after_setup_theme', 'dpt_add_theme_support', 99 ); //we want this to run last so we can override any previous post-thumbnail support settings

function dpt_add_theme_support() {
	if ( function_exists( 'add_theme_support' ) ) { 
    	add_theme_support( 'post-thumbnails' ); 
	}
}

register_activation_hook( __FILE__, array('DefaultPostThumbnailPlugin', 'install') );

add_filter('post_thumbnail_html', array('DefaultPostThumbnailPlugin', 'default_post_thumbnail_html'), 10, 5);

if ( is_admin() ){ // admin actions
	include('slt-file-select.php');
	
	add_action('admin_menu',            array('DefaultPostThumbnailPlugin', 'init_plugin_menu')); 
	add_action('admin_enqueue_scripts', array('DefaultPostThumbnailPlugin', 'backend_enqueue_scripts') );
	add_action('admin_init',            array('DefaultPostThumbnailPlugin', 'register_settings'));
	
	add_filter('contextual_help', array('DefaultPostThumbnailPlugin', 'contextual_help'), 10, 3);
} 
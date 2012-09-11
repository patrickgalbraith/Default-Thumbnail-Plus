<?php
/*
Plugin Name: Default Thumbnail Plus
Plugin URI: http://www.pjgalbraith.com/2011/12/default-thumbnail-plus/
Description: Add a default thumbnail image to post's with no post_thumbnail set.
Version: 1.0.2.3
Author: Patrick Galbraith, gyrus
Author URI: http://www.pjgalbraith.com
License: GPL2 
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
                            ),
        'dpt_meta_key' => '',
        'dpt_use_first_attachment' => true,
        'dpt_use_embedded_img' => false,
        'dpt_use_embedded_video' => false,
        'dpt_excluded_posts' => array(),
        'dpt_hook_post_meta' => true,
        'dpt_hook_post_thumbnail_html' => true,
        'dpt_use_image_cache' => false
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
        register_setting( 'dpp-options', 'dpt_use_embedded_img' );
        register_setting( 'dpp-options', 'dpt_use_embedded_video' );
        register_setting( 'dpp-options', 'dpt_excluded_posts' );
        
        register_setting( 'dpp-options', 'dpt_hook_post_meta' );
        register_setting( 'dpp-options', 'dpt_hook_post_thumbnail_html' );
        
        register_setting( 'dpp-options', 'dpt_use_image_cache' );
    }
    
    /*-------------------------------------------------------------
    Thumbnail Cascade Order:
    
    - 1 Featured Image
     |- 2 Custom field
      |- 3 Image attachment
       |- 4 Embedded images
        |- 5 Embedded video
         |- 6 Category/Tag/Taxonomy Thumbnail
          |- 7 Default thumbnail
           |- 8 nothing
    -------------------------------------------------------------*/
    
    static function default_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr, $return_id = false) {
        
        //Temporarily remove filter to retrieve default metadata
        self::remove_filter_post_metadata();
        
        $post_thumbnail_id = get_metadata('post', $post_id, '_thumbnail_id');
        if(is_array($post_thumbnail_id))
            $post_thumbnail_id = reset($post_thumbnail_id);
            
        self::add_filter_post_metadata();
        
        $size = apply_filters('post_thumbnail_size', $size);
        $dpt_options = get_option('dpt_options');
        $post = null;
        
        $default_post_thumbnail = FALSE;
        
        if($post_thumbnail_id) {
            // 1. Use the manually chosen featured image
            $default_post_thumbnail = intval($post_thumbnail_id);
                                                                                
            if(!self::post_attachment_exists($default_post_thumbnail))
                $default_post_thumbnail = FALSE;                                                                
        }
                                        
        if($default_post_thumbnail === FALSE && !in_array($post_id, get_option('dpt_excluded_posts'))){
                        
            // 2. Custom field
            if(get_option('dpt_meta_key')) {
                $default_post_thumbnail = get_post_meta($post_id, get_option('dpt_meta_key'), true);
                
                if(is_array($default_post_thumbnail))
                    $default_post_thumbnail = reset($default_post_thumbnail);
                
                if(is_numeric($default_post_thumbnail)) {
                    //Does the attachment acutally exist if not then we will set the $default_post_thumbnail to false
                    if(self::post_attachment_exists($default_post_thumbnail ) === false)
                        $default_post_thumbnail = false;
                } else if(empty($default_post_thumbnail)) {
                    $default_post_thumbnail = false;
                } else {
                    //This means the $default_post_thumbnail contains a link to an image not ideal but we will try to deal with it as best we can
                    $size_attr_str = self::get_attr_string($size);
                    
                    $default_post_thumbnail = '<img src="'.self::get_cache_image($default_post_thumbnail, $size).'" '.$size_attr_str.' '.$attr.' />';
                }
            }

            // 3. Get first image attachment
            if($default_post_thumbnail === FALSE && get_option('dpt_use_first_attachment'))
                $default_post_thumbnail = self::get_first_post_attachment_id($post_id);

            // 4. Get img tags from content
            if($default_post_thumbnail === FALSE && get_option('dpt_use_embedded_img')) {
                
                if($post === null)
                    $post = get_post($post_id);
                
                preg_match_all( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_content, $matches);
                
                if(!empty($matches[1][0])) {
                    $size_attr_str = self::get_attr_string($size);
                    $image_src = $matches[1][0];
                    $default_post_thumbnail = '<img src="'.self::get_cache_image($image_src, $size).'" '.$size_attr_str.' '.$attr.' />'; 
                }
            }

            // 5. Check if there is an embedded video
            if($default_post_thumbnail === FALSE && get_option('dpt_use_embedded_video')) {
                
                if($post === null)
                    $post = get_post($post_id);
                
                $video_image_url = self::get_video_image($post->post_content);
                
                if(!empty($video_image_url)) {
                    $size_attr_str = self::get_attr_string($size);
                    $default_post_thumbnail = '<img src="'.self::get_cache_image($video_image_url, $size).'" '.$size_attr_str.' '.$attr.' />'; 
                }
            }

            // 6. Category/Tag/Taxonomy thumbnail
            if($default_post_thumbnail === FALSE) {
                foreach($dpt_options as $key => $dpt_option_arr) {
                    
                    if($key == 'default') 
                        continue; 
                    
                    foreach($dpt_option_arr as $dpt_option) {
                        if(!is_array($dpt_option['value']))
                            $dpt_option['value'] = array($dpt_option['value']);
                        
                        foreach($dpt_option['value'] as $dpt_option_value) {
                            if( is_object_in_term($post_id, $key, $dpt_option_value) ) {
                                $default_post_thumbnail = intval($dpt_option['attachment_id']);
                            }
                        }
                    }
                }
            }

            // 7. If the post has no attachment load the default thumbnail id
            if($default_post_thumbnail === FALSE) 
                $default_post_thumbnail = intval($dpt_options['default']['attachment_id']);
        }

        if(!is_numeric($default_post_thumbnail)) {
            $html = $default_post_thumbnail;
        } else {
            do_action( 'begin_fetch_post_thumbnail_html', $post_id, $default_post_thumbnail, $size ); // for "Just In Time" filtering of all of wp_get_attachment_image()'s filters
            $html = wp_get_attachment_image( intval($default_post_thumbnail), $size, false, $attr );
            do_action( 'end_fetch_post_thumbnail_html', $post_id, $default_post_thumbnail, $size );
        }
        
        if($return_id) {
            if(is_numeric($default_post_thumbnail))
                return $default_post_thumbnail;
            else
                return null;
        }
        
        return $html;
    }
    
    static function get_attr_string($size, $return_array = false) {
        global $_wp_additional_image_sizes;
        $other_attr = '';
        $width = null;
        $height = null;
        
        if(empty($size))
            return $other_attr;
        
        if(is_array($size)) {
            $width = $size[0];
            $height = $size[1];
            
            $other_attr = image_hwstring($width, $height).'class="attachment-'.$width.'x'.$height.' wp-post-image"';
        } else if( isset( $_wp_additional_image_sizes[$size] ) ) {
            $width = $_wp_additional_image_sizes[$size]['width'];
            $height = $_wp_additional_image_sizes[$size]['height'];
            
            $other_attr = image_hwstring($width, $height).'class="attachment-'.$size.' wp-post-image"';
        }
        
        if($return_array) {
            if($width !== null && $height !== null)
                return array($width, $height);
            else
                return array();
        }
        
        return $other_attr;
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
    
    //Attempt to find video source and if found return url to video thumbnail
    static function get_video_image($embed) {
        // YouTube
        preg_match( '/http:\/\/(?:www\.)?youtu(?:be\.com\/watch\?v=|\.be\/)(\w*)(&(amp;)?[\w\?=]*)?/', $embed, $match);
        if( isset($match[1]) )
            return "http://img.youtube.com/vi/".$match[1]."/0.jpg";
        
        // More sources to come... maybe ;-)
        // ...
        return false;
    }
    
    //Returns the image href. Will autmatically cache and resize/crop the image depending on the dpt_use_image_cache setting.
    static function get_cache_image($src, $size) {
        
        if(get_option('dpt_use_image_cache', false) === false) {
            return $src;
        }
        
        if(!is_array($size))
            $size = self::get_attr_string($size, true);
        
        if(isset($size[0]) && isset($size[1]))
            $size_str = $size[0].'x'.$size[1];
        else
            $size_str = '';
        
        add_filter('upload_dir', array('DefaultPostThumbnailPlugin', 'upload_dir'));
        $upload_dir = wp_upload_dir();
        remove_filter('upload_dir', array('DefaultPostThumbnailPlugin', 'upload_dir'));
        
        $file_name = sha1($src);
        $file_ext = pathinfo($src, PATHINFO_EXTENSION);
        $orig_file_name = $file_name.'.'.$file_ext;
        $resize_file_name = !empty($size_str) ? $file_name.'-'.$size_str.'.'.$file_ext : $orig_file_name;
        
        // Check if it is cached already
        if(file_exists($upload_dir['path'].DIRECTORY_SEPARATOR.$resize_file_name)) {
            return $upload_dir['url'].DIRECTORY_SEPARATOR.$resize_file_name;
        }
        
        ////////////////////////
        //      CACHE IT      //
        ////////////////////////
        $src_file = false;
        
        $old_ua = ini_get('user_agent');
        ini_set('user_agent', 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.107 Safari/534.13'); //change default user agent this fixes issues with images linked from sites such as Wikipedia
        
        if(ini_get('allow_url_fopen') != 1)
            @ini_set('allow_url_fopen', '1');
        
        if(ini_get('allow_url_fopen') != 1) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $src);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $src_file = curl_exec($ch);
            curl_close($ch);
        } else {
            $src_file = @file_get_contents($src, false);
        }
        
        ini_set('user_agent', $old_ua);
        
        if($src_file === false)
            return $src;
        
        $result = @file_put_contents($upload_dir['path'].DIRECTORY_SEPARATOR.$orig_file_name, $src_file);
        
        if($result === false)
            return $src;
        
        if(isset($size[0]) && isset($size[1])) {
            $resized_file = image_resize(
                                    $upload_dir['path'].DIRECTORY_SEPARATOR.$orig_file_name, 
                                    $size[0],
                                    $size[1],
                                    true,
                                    $size_str, 
                                    $upload_dir['path'].DIRECTORY_SEPARATOR.$resize_file_name
                                  );
            if(is_wp_error($resized_file))
                return $src;
            
            return $upload_dir['url'].DIRECTORY_SEPARATOR.$resize_file_name;
        } else {
            return $upload_dir['url'].DIRECTORY_SEPARATOR.$orig_file_name;
        }
    }
    
    static function upload_dir($upload) {
        $upload['subdir']    = DIRECTORY_SEPARATOR.'default-thumb-plus';
        $upload['path']        = $upload['basedir'] . $upload['subdir'];
        $upload['url']        = $upload['baseurl'] . $upload['subdir'];
        return $upload;
    }
    
    /* @param string|array $metadata - Always null for post metadata.
     * @param int $object_id - Post ID for post metadata
     * @param string $meta_key - metadata key.
     * @param bool $single - Indicates if processing only a single $metadata value or array of values.
     * @return Original or Modified $metadata. */
    static function filter_get_post_metadata($metadata, $object_id, $meta_key, $single) {
        
        if(isset($meta_key) && '_thumbnail_id' === $meta_key) {
            
            //Temporarily remove filter
            self::remove_filter_post_metadata();
            
            //check if there is a thumbnail for this post
            $result = self::default_post_thumbnail_html('', $object_id, null, null, '', true);
            
            //Add filter again
            self::add_filter_post_metadata();
            
            if(!empty($result))
                return $result;
        }
        
        return $metadata;
    }
    
    static function remove_filter_post_metadata() {
        remove_filter('get_post_metadata', array('DefaultPostThumbnailPlugin', 'filter_get_post_metadata'), true, 4);
    }
    
    static function add_filter_post_metadata() {
        add_filter('get_post_metadata', array('DefaultPostThumbnailPlugin', 'filter_get_post_metadata'), true, 4);
    }
    
    static function backend_enqueue_scripts($hook_suffix) {
        
        if($hook_suffix != 'settings_page_DefaultPostThumbnailPlugin')
            return;
        
        wp_enqueue_script(
            'dpt_admin_script', 
            plugins_url('admin-script.js', __FILE__),
            array('jquery')
        );
    }
    
    static function admin_register_head() {
        ?><style>
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
        
        $default_config = self::$default_config;
        $dpt_options = array();
        $count = 1;
        
        $dpt_options['default'] = array('attachment_id' => $_POST['attachment_id_default'], 'value' => '');        
        
        while( isset($_POST['filter_name_'.$count]) ) {
            $value = explode(',', $_POST['filter_value_'.$count]); //explode comma separated string on comma
            array_walk($value, create_function('&$val', '$val = trim($val);')); //trim spaces
            
            $dpt_options[$_POST['filter_name_'.$count]][] = array('attachment_id' => intval($_POST['attachment_id_'.$count]), 'value' => $value);
            $count++;
        }
        
        update_option( 'dpt_options', $dpt_options );
        update_option( 'dpt_meta_key', isset($_POST['dpt_meta_key']) ? $_POST['dpt_meta_key'] : $default_config['dpt_meta_key'] );
        update_option( 'dpt_use_first_attachment', isset($_POST['dpt_use_first_attachment']) && $_POST['dpt_use_first_attachment'] == true ? true : false );
        update_option( 'dpt_use_embedded_img', isset($_POST['dpt_use_embedded_img']) && $_POST['dpt_use_embedded_img'] == true ? true : false );
        update_option( 'dpt_use_embedded_video', isset($_POST['dpt_use_embedded_video']) && $_POST['dpt_use_embedded_video'] == true ? true : false );
        update_option( 'dpt_hook_post_meta', isset($_POST['dpt_hook_post_meta']) && $_POST['dpt_hook_post_meta'] == true ? true : false );
        update_option( 'dpt_hook_post_thumbnail_html', isset($_POST['dpt_hook_post_thumbnail_html']) && $_POST['dpt_hook_post_thumbnail_html'] == true ? true : false );
        update_option( 'dpt_use_image_cache', isset($_POST['dpt_use_image_cache']) && $_POST['dpt_use_image_cache'] == true ? true : false );
        
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
        
        include 'admin.html.php';
    }
     
}//end class

add_action( 'after_setup_theme', 'dpt_add_theme_support', 99 ); //we want this to run last so we can override any previous post-thumbnail support settings

function dpt_add_theme_support() {
    if ( function_exists( 'add_theme_support' ) ) { 
        add_theme_support( 'post-thumbnails' ); 
    }
}

register_activation_hook( __FILE__, array('DefaultPostThumbnailPlugin', 'install') );

if(get_option('dpt_hook_post_thumbnail_html', true))
    add_filter('post_thumbnail_html', array('DefaultPostThumbnailPlugin', 'default_post_thumbnail_html'), 10, 5);

if ( is_admin() ){ 
    // admin actions
    include('include'.DIRECTORY_SEPARATOR.'slt-file-select.php');
    
    add_action('admin_menu',            array('DefaultPostThumbnailPlugin', 'init_plugin_menu')); 
    add_action('admin_enqueue_scripts', array('DefaultPostThumbnailPlugin', 'backend_enqueue_scripts') );
    add_action('admin_init',            array('DefaultPostThumbnailPlugin', 'register_settings'));
    
    add_filter('contextual_help', array('DefaultPostThumbnailPlugin', 'contextual_help'), 10, 3);
} else {
    // non admin actions
    if(get_option('dpt_hook_post_meta', true))
        DefaultPostThumbnailPlugin::add_filter_post_metadata();
}


//////////////////////////
//    HELPER FUNCTIONS
//////////////////////////

//Global helper function which returns the default thumbnail attachment id for a specified post
function dpt_post_thumbnail_id($post_id, $size = null, $attr = '') {
    $thumb_id = DefaultPostThumbnailPlugin::default_post_thumbnail_html('', $post_id, null, $size, $attr, true);
    return $thumb_id;
}
//Global helper function which returns default thumbnail image tag for a specified post
function dpt_post_thumbnail_html($post_id, $size = null, $attr = '') {
    return DefaultPostThumbnailPlugin::default_post_thumbnail_html('', $post_id, null, $size, $attr);
}
//Global helper function which returns the image src for a specified post
function dpt_post_thumbnail_src($post_id, $size = null) {
    $img_tag = DefaultPostThumbnailPlugin::default_post_thumbnail_html('', $post_id, null, $size, '');
    $matches = array();
    
    preg_match('/src=[\'"](.*?)[\'"]/i', $img_tag, $matches); //yes it is bad to use regex to parse html (-_-);
    
    if(isset($matches[1]))
        return $matches[1];
    else
        return '';
}
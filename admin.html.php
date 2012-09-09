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
                    <th><?php _e('Description') ?></th>
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
                    <td>This is the default thumbnail that will be loaded if the post has no featured image set.</td>
                    <td></td>
                </tr>
                
                <?php 
                $count = 1; 
                foreach($dpt_options as $key => $dpt_option_arr): 
                    if($key == 'default') { continue; } 
                    
                    foreach($dpt_option_arr as $dpt_option) : ?>
                    
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
                             <input name="filter_value_<?php echo $count; ?>" type="text" value="<?php echo is_array($dpt_option['value']) ? implode(', ', $dpt_option['value']) : $dpt_option['value']; ?>" class="filter_value regular-text" style="width: 100%;" required="required" />
                        </td>
                        <td class="row_description"></td>
                        <td class="row_actions"><a href="javascript:void(0)" onclick="dpt_remove_row(this)"><img alt="Delete Icon" src="<?php echo plugins_url('/default-thumbnail-plus/img/icon-delete.png'); ?>" /></a></td>
                    </tr>
                    
                <?php $count++; endforeach; endforeach; ?>
                
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
                         <input type="text" value="" class="filter_value regular-text" style="width: 100%;" required="required" />
                    </td>
                    <td class="row_description"></td>
                    <td class="row_actions"><a href="javascript:void(0)" onclick="dpt_remove_row(this)"><img alt="Delete Icon" src="<?php echo plugins_url('/default-thumbnail-plus/img/icon-delete.png'); ?>" /></a></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" style="text-align:center"><input id="dpt_add-filter-btn" class="button-secondary" style="padding:4px" type="submit" value="<?php _e( 'Add Filter' ); ?>" /></th>
                </tr>
            </tfoot>
        </table>
        
        <br/>
        <p style="margin-top:25px;">
            <fieldset>
                <legend class="screen-reader-text"><span>Automatically use first attachment as fallback if available</span></legend>
                <label for="dpt_use_first_attachment">
                    <input name="dpt_use_first_attachment" type="checkbox" id="dpt_use_first_attachment" value="1" <?php echo ($dpt_use_first_attachment == true) ? 'checked="checked"' : ''; ?> />
                    Use image attachment if available
                </label>
            </fieldset>
            <span class="description">Automatically use the post's first available image attachment for the thumbnail. This is useful for older posts that haven't got a featured image set.</span>
        </p>
        
        <p style="margin-top:25px;">
            <fieldset>
                <legend class="screen-reader-text"><span>Automatically use first embedded image</span></legend>
                <label for="dpt_use_embedded_img">
                    <input name="dpt_use_embedded_img" type="checkbox" id="dpt_use_embedded_img" value="1" <?php echo ($dpt_use_embedded_img == true) ? 'checked="checked"' : ''; ?> />
                    Use embedded image if available
                </label>
            </fieldset>
            <span class="description">Automatically use the post's first available embedded image for the thumbnail. This is useful if you embed external images.</span>
        </p>
       
        <p style="margin-top:25px;">
            <fieldset>
                <legend class="screen-reader-text"><span>Automatically use first embedded video</span></legend>
                <label for="dpt_use_embedded_video">
                    <input name="dpt_use_embedded_video" type="checkbox" id="dpt_use_embedded_video" value="1" <?php echo ($dpt_use_embedded_video == true) ? 'checked="checked"' : ''; ?> />
                    Use embedded video thumbnail if available
                </label>
            </fieldset>
            <span class="description">Automatically use the post's first available embedded video for the thumbnail (YouTube only).</span>
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
        
        <br />
        <h3>Advanced Options</h3>
        
        <p style="margin-top:25px;">
            <fieldset>
                <legend class="screen-reader-text"><span>Automatically cache external images</span></legend>
                <label for="dpt_use_image_cache">
                    <input name="dpt_use_image_cache" type="checkbox" id="dpt_use_image_cache" value="1" <?php echo ($dpt_use_image_cache == true) ? 'checked="checked"' : ''; ?> />
                    Cache and resize external images
                </label>
            </fieldset>
            <span class="description">Automatically resize, crop and cache external images &amp; video thumbnails. Recommended option, however doesn't work with some server configurations.</span>
        </p>
        
        <p style="margin-top:25px;">
            <fieldset>
                <legend class="screen-reader-text"><span>Enable or disable plugin hooks (for developers only).</span></legend>
                <label for="dpt_hook_post_thumbnail_html">
                    <input name="dpt_hook_post_thumbnail_html" type="checkbox" id="dpt_hook_post_thumbnail_html" value="1" <?php echo $dpt_hook_post_thumbnail_html == true ? 'checked="checked"' : ''; ?> />
                    Filter post_thumbnail_html
                </label>
                &nbsp;&nbsp;&nbsp;
                <label for="dpt_hook_post_meta">
                    <input name="dpt_hook_post_meta" type="checkbox" id="dpt_hook_post_meta" value="1" <?php echo $dpt_hook_post_meta == true ? 'checked="checked"' : ''; ?> />
                    Filter get_post_metadata
                </label>
            </fieldset>
            <span class="description">Enable or disable plugin hooks. For developers only, see documentation for how to invoke the plugin manually when hooks are disabled.</span>
        </p>
        
        <br/>
        <p><input id="dpt_submit-btn" class="button-primary" type="submit" name="Save" value="<?php _e( 'Save Changes' ); ?>" /></p>
    </form>
</div>
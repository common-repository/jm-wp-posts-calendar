<?php
/**
 * Plugin Name: Posts Calendar
 * Plugin URI: http://www.jmds.co.uk
 * Description: Calendar display using standard posts.
 * Version: 1.2
 * Author: John Messingham
 * Author URI: http://www.jmds.co.uk
 * License: GPL2
 *  
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

$jm_posts_calendar_db_version = '1.1';

function jm_delete_file_if_exists($filename) {

    if (file_exists($filename)) {
        unlink( $filename );    
    }
        
}

function jm_posts_calendar_get_posts() {
    
    global $wpdb;
    $posts = array();
    
    $start = filter_input(INPUT_GET, 'start');
    $end = filter_input(INPUT_GET, 'end');

    $mysqlstart = date( 'Y-m-d H:i', $start);
    $mysqlend = date( 'Y-m-d H:i', $end);
  
    $postFrom = $wpdb->get_results( $wpdb->prepare( 
    	"SELECT * 
    	FROM $wpdb->postmeta 
        WHERE meta_key = %s
        AND (meta_value BETWEEN %s AND %s)        
        ", 
    	'_jm_calendar_event_start', $mysqlstart, $mysqlend
    ));
     
    $postTo = $wpdb->get_results( $wpdb->prepare( 
    	"SELECT * 
    	FROM $wpdb->postmeta 
    	WHERE meta_key = %s
        AND (meta_value BETWEEN %s AND %s)
    	", 
    	'_jm_calendar_event_end', $mysqlstart, $mysqlend
    ));
  
    foreach($postFrom as $post) {
        if (trim($post->meta_value) > "") {
            $postData = get_post($post->post_id);
            $posts[$post->post_id]['title'] = $postData->post_title;       
            $posts[$post->post_id]['id'] = $post->post_id;       
            $posts[$post->post_id]['start'] = date('Y-m-d H:i', strtotime($post->meta_value));
            $posts[$post->post_id]['end'] = get_post_meta( $post->post_id, '_jm_calendar_event_end', true );
        }
    }
    
    foreach($postTo as $post) {
        if (trim($post->meta_value) > "") {
            $postData = get_post($post->post_id);
            $posts[$post->post_id]['title'] = $postData->post_title;       
            $posts[$post->post_id]['id'] = $post->post_id;       
            $posts[$post->post_id]['end'] = date('Y-m-d H:i', strtotime($post->meta_value));
            $posts[$post->post_id]['start'] = get_post_meta( $post->post_id, '_jm_calendar_event_start', true );
        }
    }

    if ($posts) {
        foreach($posts as $post) {
            if (trim($post['start']) > "" && trim($post['end']) > "") {
                $rows[] = array('id' => $post['id'],
                    'title' => $post['title'],
                    'start' => $post['start'],
                    'end' => $post['end'],
                    'allDay' => "",
                    'color' => ""
                );
            }
        }   
        echo json_encode($rows);
    }	
    die();
    
}
add_action( 'wp_ajax_jm_posts_calendar_get_posts', 'jm_posts_calendar_get_posts' );
add_action( 'wp_ajax_nopriv_jm_posts_calendar_get_posts', 'jm_posts_calendar_get_posts' );

function jm_posts_calendar_display($atts){

    if (isset($atts['theme'])) {
        $usetheme = 'true';
    } else {
        $usetheme = 'false';
    }
    
    $pluginfolder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
    
    wp_enqueue_script('fullcalendar', $pluginfolder . '/js/fullcalendar.js', array('jquery'), '', false );
    if (isset($atts['theme'])) {
        wp_enqueue_style('jquery.ui.theme', $pluginfolder . '/themes/'.$atts['theme'].'/jquery-ui.min.css');    
    }
    wp_enqueue_style('fullcalendar', $pluginfolder . '/assets/fullcalendar.css');
    wp_enqueue_style('jm_fullcalendar', $pluginfolder . '/assets/jm-wp-posts-calendar.css');
    
     $ret = "<div id=\"calendar\"></div>";

     $ret .= "<script type='text/javascript'>
    
        jQuery(document).ready(function() {
    
            jQuery('#calendar').fullCalendar({
    
                theme: ".$usetheme.",
                
                titleFormat: {
                    month: 'MMMM yyyy',
                    week: 'MMMM d - {d} yyyy',
                    day: 'dd MMMM yyyy'
                },

                columnFormat: {
                    month: 'ddd',
                    week: 'ddd d/M',
                    day: 'dddd d/M'
                },
                    
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'month,basicWeek,basicDay'
    		},
                           
                eventSources: ['".get_site_url()."/wp-admin/admin-ajax.php?action=jm_posts_calendar_get_posts'],
              	                 
                eventClick: function(calEvent, jsEvent, view) {
                    window.open('?p='+calEvent.id, '_parent');
                },
                          	
            });
            
        });
    
    </script>";
    
    return $ret;
    
}
add_shortcode( 'jm-posts-calendar', 'jm_posts_calendar_display' );

function jm_calendar_add_custom_box() {

    $screens = array( 'post' );

    foreach ( $screens as $screen ) {

        add_meta_box(
            'myplugin_sectionid',
            __( 'Event Calendar Details', 'jm_calendar_textdomain' ),
            'jm_calendar_inner_custom_box',
            $screen
        );
        
    }
    
}
add_action( 'add_meta_boxes', 'jm_calendar_add_custom_box' );

function jm_calendar_inner_custom_box( $post ) {

    wp_nonce_field( 'jm_calendar_inner_custom_box', 'jm_calendar_inner_custom_box_nonce' );
    
    $startValue = get_post_meta( $post->ID, '_jm_calendar_event_start', true );
    $endValue = get_post_meta( $post->ID, '_jm_calendar_event_end', true );

    echo '<label for="jm_calendar_event_start">';
    _e( "Event Start", 'jm_calendar_textdomain' );
    echo '</label> ';
    echo '<input readonly type="text" id="jm_calendar_event_start" name="jm_calendar_event_start" value="' . esc_attr( $startValue ) . '" size="15" />';
    echo '<br /><br />&nbsp;&nbsp;';
    echo '<label for="jm_calendar_event_end">';
    _e( "Event End", 'jm_calendar_textdomain' );
    echo '</label> ';
    echo '<input readonly type="text" id="jm_calendar_event_end" name="jm_calendar_event_end" value="' . esc_attr( $endValue ) . '" size="15" />';
 
    $pluginfolder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
   
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-widget');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('jquery-ui-timepicker-addon', $pluginfolder . '/js/jquery-ui-timepicker-addon.js', array('jquery'), '', false );

    wp_enqueue_style('jquery.ui.theme', $pluginfolder . '/themes/admin/minified/jquery-ui.min.css');
    wp_enqueue_style('jquery-ui-timepicker-addon', $pluginfolder . '/assets/jquery-ui-timepicker-addon.css');

    echo "<script type=\"text/javascript\">";
    echo "jQuery(document).ready(function(){";
    echo "jQuery('#jm_calendar_event_start').datetimepicker({ dateFormat: \"yy-mm-dd\" });";
    echo "jQuery('#jm_calendar_event_start').dblclick(function() {";
    echo "jQuery('#jm_calendar_event_start').val(\"\");";
    echo "});";    
    echo "jQuery('#jm_calendar_event_end').datetimepicker({ dateFormat: \"yy-mm-dd\" });";
    echo "jQuery('#jm_calendar_event_end').dblclick(function() {";
    echo "jQuery('#jm_calendar_event_end').val(\"\");";
    echo "});";    
    echo "});";
    echo "</script>";
    
}

function jm_calendar_save_postdata( $post_id ) {

  // Check if our nonce is set.
  if ( !isset( $_POST['jm_calendar_inner_custom_box_nonce'] ) ) {
    return $post_id;
  }

  $nonce = filter_input(INPUT_POST, 'jm_calendar_inner_custom_box_nonce');

  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'jm_calendar_inner_custom_box' ) ) {
      return $post_id;
  }
  
  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return $post_id;
  }
  // Check the user's permissions.
  if ('page' == filter_input( INPUT_POST, 'post_type' ) ) {
        return $post_id;  
  } else {
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }
  }

  // Sanitize user input.
  $startdate = sanitize_text_field( filter_input(INPUT_POST, 'jm_calendar_event_start' ) );

  // Update the meta field in the database.
  update_post_meta( $post_id, '_jm_calendar_event_start', $startdate );
  
  // Sanitize user input.
  $enddate = sanitize_text_field( filter_input(INPUT_POST, 'jm_calendar_event_end') );

  // Update the meta field in the database.
  update_post_meta( $post_id, '_jm_calendar_event_end', $enddate );

}
add_action( 'save_post', 'jm_calendar_save_postdata' );

function jm_posts_calendar_update() {
    
    global $jm_posts_calendar_db_version;
    
    if (get_option( 'jm_posts_calendar_db_version' ) != $jm_posts_calendar_db_version) {

        if ($jm_posts_calendar_db_version > '1.0') {
            
            $path = plugin_dir_path(__FILE__);
            
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.accordion.css' ); 
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery-ui.css' );
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.all.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.autocomplete.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.base.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.button.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.core.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.datepicker.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.dialog.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.menu.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.progressbar.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.resizable.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.selectable.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.slider.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.spinner.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.theme.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.tooltip.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/jquery.ui.tabs.css' );   

            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.accordion.min.css' ); 
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery-ui.min.css' );
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.all.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.autocomplete.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.base.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.button.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.core.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.datepicker.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.dialog.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.menu.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.progressbar.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.resizable.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.selectable.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.slider.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.spinner.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.theme.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.tooltip.min.css' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/jquery.ui.tabs.min.css' );  
            
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_flat_0_aaaaaa_40x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_flat_75_ffffff_40x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_glass_55_fbf9ee_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_glass_65_ffffff_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_glass_75_dadada_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_glass_75_e6e6e6_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_glass_95_fef1ec_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-bg_highlight-soft_75_cccccc_1x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-icons_222222_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-icons_2e83ff_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-icons_454545_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-icons_888888_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/images/ui-icons_cd0a0a_256x240.png' );   

            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_flat_0_aaaaaa_40x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_flat_75_ffffff_40x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_glass_55_fbf9ee_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_glass_65_ffffff_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_glass_75_dadada_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_glass_75_e6e6e6_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_glass_95_fef1ec_1x400.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-bg_highlight-soft_75_cccccc_1x100.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-icons_222222_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-icons_2e83ff_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-icons_454545_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-icons_888888_256x240.png' );   
            jm_delete_file_if_exists( $path . 'themes/smoothness/minified/images/ui-icons_cd0a0a_256x240.png' );
            
            if ( is_dir( $path . 'themes/smoothness/minified/images' ) ) {
                rmdir( $path . 'themes/smoothness/minified/images' );
            }
            
            if ( is_dir( $path . 'themes/smoothness/minified' ) ) {
                rmdir( $path . 'themes/smoothness/minified' );
            }

            if ( is_dir( $path . 'themes/smoothness/images' ) ) {
                rmdir( $path . 'themes/smoothness/images' );
            } 
            
            if ( is_dir( $path . 'themes/smoothness' ) ) {
                rmdir( $path . 'themes/smoothness' );
            }            
        }
            
		update_option( 'jm_posts_calendar_db_version', $jm_posts_calendar_db_version );
    
	}

}
add_action( 'plugins_loaded', 'jm_posts_calendar_update' );

register_uninstall_hook('uninstall.php', '');

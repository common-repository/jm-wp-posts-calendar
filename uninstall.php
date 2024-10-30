<?php

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { 
	exit();
}
    
delete_post_meta_by_key( '_jm_calendar_event_start' );
delete_post_meta_by_key( '_jm_calendar_event_end' );
delete_option( 'jm_posts_calendar_db_version' );
<?php
/*
Plugin Name: Admin Menu Post List
Plugin URI: http://wordpress.org/plugins/admin-menu-post-list/
Description: Display a post list in the admin menu
Version: 1.4
Author: Eliot Akira
Author URI: eliotakira.com
License: GPL2
*/



/* Add settings link on plugin page */

add_filter( "plugin_action_links", 'ampl_plugin_settings_link', 10, 4 );
 
function ampl_plugin_settings_link( $links, $file ) {
	$plugin_file = 'admin-menu-post-list/admin-menu-post-list.php';
	//make sure it is our plugin we are modifying
	if ( $file == $plugin_file ) {
		$settings_link = '<a href="' .
			admin_url( 'admin.php?page=admin_menu_post_list_settings_page' ) . '">' .
			__( 'Settings', 'admin_menu_post_list_settings_page' ) . '</a>';
		array_unshift( $links, $settings_link );
	}
	return $links;
}


/*
 * Build post list
 */


function build_post_list_item($post_id,$post_type,$is_child) {


	if( !isset($_GET['post']) )
		$current_post_ID = -1;
	else
		$current_post_ID = $_GET['post']; /* Get current post ID on admin screen */

	$edit_link = get_edit_post_link($post_id);
	$title = get_the_title($post_id);
	$title = esc_html($title);

 	/* Limit title length */

	if(strlen($title)>20) {
		if( function_exists( 'mb_substr' ) ) {
			$title = mb_substr($title, 0, 20) . '..';
		} else {
			$title = substr($title, 0, 20) . '..';
		}
	}

	$output = '<div class="';

	if($is_child != 'child') { $output .= 'post_list_view_indent'; }

	if($current_post_ID == ($post_id)) { $output .= ' post_current'; }

	$output .= '"><a href="' . $edit_link . '">';

	if($is_child == 'child') { $output .= '&mdash; '; }

	/* Display post status */

	switch(get_post_status($post_id)) {
		case 'draft':
		case 'pending':
		case 'future' : $output .= '<i>'; break;
	}
	if($current_post_ID == ($post_id))
		$output .= '<b>';

	/* Display post title */

	$output .= $title;

	if($current_post_ID == ($post_id))
		$output .= '</b>';
	switch(get_post_status($post_id)) {
		case 'draft':
		case 'pending':
		case 'future' : $output .= '</i>'; break;
	}

	$output .= '</a>';

	/*** Search for children ***/

	$children = get_posts(array(
        'post_parent' => $post_id,
        'post_type' => $post_type,
		"orderby" => "menu_order",
        'post_status' => 'any',
    ));

	if($children) {
		foreach($children as $child) {
			$output .= build_post_list_item($child->ID,$post_type,'child');
		}
	}

	$output .= '</div>';

	return $output;
}


add_action('admin_menu', 'custom_post_list_view', 11);
function custom_post_list_view() {

	/** Get settings **/

	$settings = get_option( 'ampl_settings' );

	/*** Get all post types ***/

	$post_types = get_post_types();

	foreach ($post_types as $post_type) {

	/*** If enabled in settings ***/

	if(!isset($settings['post_types'][$post_type]))
		$post_types_setting = 'off';
	else 
		$post_types_setting = $settings['post_types'][$post_type];

	if($post_types_setting == 'on' ) {

		/* Get display options */

		$max_limit = $settings['max_limit'][$post_type];
		if($max_limit=='') $max_limit = 0;

		$post_orderby = $settings['orderby'][$post_type];
		if($post_orderby=='') $post_orderby = 'date';

		$post_order = $settings['order'][$post_type];
		if($post_order=='') $post_order = 'ASC';

		if( !isset($settings['exclude_status']) || !isset($settings['exclude_status'][$post_type]))
			$post_exclude = '';
		else
			$post_exclude = $settings['exclude_status'][$post_type];

		if($post_exclude=='') $post_exclude = 'off';
		if($post_exclude=='on') {
			$post_exclude = 'publish';
		} else {
			$post_exclude = 'any';
		}

		$custom_menu_slug = $post_type;
		$output = '';
		if ($max_limit==0) {
			$max_numberposts = 25;
		} else {
			$max_numberposts = $max_limit;
		}

		$args = array(
			"post_type" => $post_type,
			"parent" => "0",
			"post_parent" => "0",
			"numberposts" => $max_numberposts,
			"orderby" => $post_orderby,
			"order" => $post_order,
			"post_status" => $post_exclude,
			"suppress_filters" => 0
		);

		$posts = get_posts($args);

		if($posts) {

			$output .= '</a>';

			$output .= '<div class="post_list_view">'
						. '<div class="post_list_view_headline">' . '<hr>' . '</div>';

			$count=0;

			foreach ($posts as $post) {
				if(($max_limit==0) ||
					($count<$max_limit))
						$output .= build_post_list_item($post->ID,$post_type,'parent');
				$count++;
			}

			$output .= '</div>';
			$output .= '<a>';

			if($post_type == 'post') {
				add_posts_page( "Title", $output, "edit_posts", $custom_menu_slug, "custom_post_list_view_page");
			} else {
				 if ($post_type == 'page') {
					add_pages_page( "Title", $output, "edit_pages", $custom_menu_slug, "custom_post_list_view_page");
				} else {
					if($post_type == 'attachment') {
						 add_media_page("Title", $output, "edit_posts", $custom_menu_slug, "custom_post_list_view_page");
					} else {
						add_submenu_page(('edit.php?post_type=' . $post_type), "Title", $output, "edit_posts", $custom_menu_slug, "custom_post_list_view_page");
					}
				}
			}
		}
	}
	} // End foreach post type
}

function custom_post_list_view_page() { /* Empty */ }

/*
 * Add admin menu style in header
 */

function custom_post_list_view_css() { ?>

	<style>
		.post_list_view_headline {
			padding-left: 10px !important; 
			padding-right: 10px !important;
			margin-top: -8px !important;
		}
		.post_list_view_headline hr {
			border-color: #666 !important;
		}
		.post_list_view_indent {
			margin-left:12px;
		}
		.post_list_view a {
			line-height:1 !important;
			padding:5px 0 !important;
		}
    </style>

<?php }
add_action( 'admin_head', 'custom_post_list_view_css' );


/**********
 *
 * Add settings page
 *
 */

// create custom plugin settings menu
add_action('admin_menu', 'ampl_create_menu');

function ampl_create_menu() {
	add_options_page('Post List', 'Post List', 'manage_options', 'admin_menu_post_list_settings_page', 'ampl_settings_page');
}

add_action( 'admin_init', 'ampl_register_settings' );
function ampl_register_settings() {
	register_setting( 'ampl_settings_field', 'ampl_settings', 'ampl_settings_field_validate' );
	add_settings_section('ampl_settings_section', '', 'ampl_settings_section_page', 'ampl_settings_section_page_name');
	add_settings_field('ampl_settings_field_string', '<b>Select post types to enable</b>', 'ampl_settings_field_input', 'ampl_settings_section_page_name', 'ampl_settings_section');
}

function ampl_settings_section_page() { /*	Empty  */ }

function ampl_settings_field_input() {

	$settings = get_option( 'ampl_settings');

	?>
	<tr>
		<td><b>Post type</b></td>
		<td><b>Max items (0=all)</b></td>
		<td><b>Order by</b></td>
		<td><b>Order</b></td>
		<td><b>Show only published</b></td>
	</tr>
	<?php

	$all_post_types = get_post_types(array('public'=>true));
	$exclude_types = array('attachment');

	 foreach ($all_post_types as $key) {

	 	if(!in_array($key, $exclude_types)) {

			$post_types = isset( $settings['post_types'][ $key ] ) ? esc_attr( $settings['post_types'][ $key ] ) : '';

			$post_type_object = get_post_type_object( $key );
			$post_type_label = $post_type_object->labels->name;

		 	if(isset( $settings['max_limit'][ $key ] ) ) {
			 	$max_number =  $settings['max_limit'][ $key ];
			 } else {
			 	$max_number =  '0';
			 }
		 	if(isset( $settings['orderby'][ $key ] ) ) {
			 	$post_orderby =  $settings['orderby'][ $key ];
			 } else {
			 	$post_orderby =  'date';
			 }
		 	if(isset( $settings['order'][ $key ] ) ) {
			 	$post_order =  $settings['order'][ $key ];
			 } else {
			 	$post_order =  'DESC';
			 }
		 	if(isset( $settings['exclude_status'][ $key ] ) ) {
			 	$post_exclude = $settings['exclude_status'][ $key ];
			 } else {
			 	$post_exclude =  'off';
			 }

			?>
			<tr>
				<td width="200px">
					<input type="checkbox" id="<?php echo $key; ?>" name="ampl_settings[post_types][<?php echo $key; ?>]" <?php checked( $post_types, 'on' ); ?>/>
					<?php echo '&nbsp;' . ucwords($post_type_label); ?>
				</td>
				<td width="200px">
					<input type="text" size="1"
						id="ampl_settings_field_max_limit"
						name="ampl_settings[max_limit][<?php echo $key; ?>]"
						value="<?php echo $max_number; ?>" />
				</td>
				<td width="200px">
					<input type="radio" value="date" name="ampl_settings[orderby][<?php echo $key; ?>]" <?php checked( 'date', $post_orderby ); ?>/>
					<?php echo 'created&nbsp;&nbsp;<br>'; ?>
					<input type="radio" value="modified" name="ampl_settings[orderby][<?php echo $key; ?>]" <?php checked( 'modified', $post_orderby ); ?>/>
					<?php echo 'modified&nbsp;&nbsp;<br>'; ?>
					<input type="radio" value="title" name="ampl_settings[orderby][<?php echo $key; ?>]" <?php checked( 'title', $post_orderby ); ?>/>
					<?php echo 'title&nbsp;&nbsp;'; ?>
				</td>
				<td width="200px">
					<input type="radio" value="ASC" name="ampl_settings[order][<?php echo $key; ?>]" <?php checked( 'ASC', $post_order ); ?>/>
					<?php echo 'ASC&nbsp;&nbsp;- alphabetical<br>'; ?>
					<input type="radio" value="DESC" name="ampl_settings[order][<?php echo $key; ?>]" <?php checked( 'DESC', $post_order ); ?>/>
					<?php echo 'DESC&nbsp; - new to old'; ?>
				</td>
				<td width="200px">
					<input type="checkbox" name="ampl_settings[exclude_status][<?php echo $key; ?>]" <?php checked( $post_exclude, 'on' ); ?>/>
				</td>
			</tr>
			<?php
		}
	}
}

function ampl_settings_field_validate($input) {
	// Validate somehow
	return $input;
}

function ampl_get_post_types() {

	$args = array( 'public' => true	);

	$post_types = get_post_types( $args );

	unset( $post_types[ 'attachment' ] );
	return apply_filters( 'ampl_get_post_types', $post_types );
}

function ampl_settings_page() {
	?>
	<div class="wrap">
	<h2>Admin Menu Post List</h2>
	<form method="post" action="options.php">
	    <?php settings_fields( 'ampl_settings_field' ); ?>
	    <?php do_settings_sections( 'ampl_settings_section_page_name' ); ?>
	    <?php submit_button(); ?>
	</form>
	</div>
	<?php
}

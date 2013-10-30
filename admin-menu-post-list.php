<?php
/*
Plugin Name: Admin Menu Post List
Plugin URI: http://wordpress.org/plugins/admin-menu-post-list/
Description: Display a post list in the admin menu
Version: 0.5
Author: Eliot Akira
Author URI: eliotakira.com
License: GPL2
*/


/*
 * Build post list
 */


function build_post_list_item($post_id,$post_type,$is_child) {

	$current_post_ID = $_GET['post']; /* Get current post ID on admin screen */

	$edit_link = get_edit_post_link($post_id);
	$title = get_the_title($post_id);
	$title = esc_html($title);
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

	/*** Get all post types ***/

	$post_types = get_post_types();

	foreach ($post_types as $post_type) {

	/*** If enabled in settings ***/

	if(ampl_enabled($post_type)) {

		$custom_menu_slug = $post_type;
		$output = '';

		$args = array(
			"post_type" => $post_type,
			"parent" => "0",
			"post_parent" => "0",
			"numberposts" => "-1",
			"orderby" => "menu_order",
			"order" => "ASC",
			"post_status" => "any",
			"suppress_filters" => 0
		);

		$posts = get_posts($args);

		if($posts) {

			$output .= '</a>';

			$output .= '<div class="post_list_view">'
						. '<div class="post_list_view_headline">' . '<hr>' . '</div>';

			foreach ($posts as $post) {
				$output .= build_post_list_item($post->ID,$post_type,'parent');
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
						add_submenu_page(('edit.php?post_type=' . $post_type), "Title", $output, "edit_posts", '', "custom_post_list_view_page");
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
	add_options_page('Post List', 'Post List', 'manage_options', 'ampl_menu_slug', 'ampl_settings_page');
}

add_action( 'admin_init', 'ampl_register_settings' );
function ampl_register_settings() {
	register_setting( 'ampl_settings_field', 'ampl_settings', 'ampl_settings_field_validate' );
	add_settings_section('ampl_settings_section', '', 'ampl_settings_section_page', 'ampl_settings_section_page_name');
	add_settings_field('ampl_settings_field_string', 'Select post type to enable', 'ampl_settings_field_input', 'ampl_settings_section_page_name', 'ampl_settings_section');
}

function ampl_settings_section_page() {
/*	echo '<p>Main description</p>'; */
}

function ampl_settings_field_input() {
//	$options = get_option('ampl_settings');
//	echo "<input id='ampl_settings_field_string' name='ampl_settings[page]' type='checkbox' value='{$options['text_string']}' />";

	$settings = (array) get_option( 'ampl_settings', $defaults );

	 foreach ( custom_gallery_get_post_types() as $key => $label ) {
		$post_types = isset( $settings['post_types'][ $key ] ) ? esc_attr( $settings['post_types'][ $key ] ) : '';

		?><p>
			<input type="checkbox" id="<?php echo $key; ?>" name="ampl_settings[post_types][<?php echo $key; ?>]" <?php checked( $post_types, 'on' ); ?>/><label for="<?php echo $key; ?>"> <?php echo $label; ?></label>
		</p><?php
	} 
}

function ampl_settings_field_validate($input) {
	// Validate somehow?
	return $input;
}

function ampl_enabled( $post_type ) {

	// get the allowed post type from the DB
	$enabled_post_types = ( array ) get_option( 'ampl_settings', $defaults );

	// check the two against each other
	if ( $enabled_post_types['post_types'][$post_type] == 'on' )
		return true;
	else
		return false;
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
<?php }

?>
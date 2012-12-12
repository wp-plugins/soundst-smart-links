<?php
/*
 * Plugin Name: Soundst Smart Links
 * Plugin URI: http://soundst.com/
 * Description: Allows to add smart links from the Smart Links Meta box
 * Author: Sound Strategies Inc.
 * Author URI: http://soundst.com
 * Version: 0.0.4
*/


/* Creating table */

global $jal_db_version;
$jal_db_version = "0.1";

function jal_install () {
	global $wpdb;
	global $jal_db_version;
	$table_name = $wpdb->prefix . "smartlinks";
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

		/** Creating index **/
		
		$sql = "CREATE TABLE " . $table_name . " (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name tinytext NOT NULL,
		text text NOT NULL,
		UNIQUE KEY id (id),
		INDEX tag_name (name(255))
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		add_option("jal_db_version", $jal_db_version);
	}
}

register_activation_hook(__FILE__,'jal_install');


/* Add shortcode */
function smart_box_list( $atts ) {
	global $metabox, $post, $wpdb;
	$CTOP = get_option('smart_links_submenu_page');
	$table_name = $wpdb->prefix . "smartlinks";
	$first_item  = mysql_real_escape_string($atts[0]);
	$query = "SELECT id, name, text FROM $table_name USE INDEX(tag_name) WHERE name = '$first_item'";
	$res = $wpdb->get_results($query);
	if (count($res) > 0) {
		$smartlink_postorder = get_post_meta($post->ID, 'Smartlink_postorder', TRUE);
		if ($smartlink_postorder != '') {
			$need_id = ($smartlink_postorder % count($res) + 1);
		} else {
			$need_id = ($post->ID % count($res) + 1);
		}
		//print_r('Count_res ' . count($res) . ' Smartlink postorder '  . $smartlink_postorder . '  Need id: ' . $need_id . '<br>');
		//$need_id = ($post->ID % count($res) + 1);
		$need_arr = explode(',', $res[$need_id]->text);
		if (count($need_arr) == 7) {
			$need_link = $need_arr[5] . $need_arr[0] . '<a href="' . $need_arr[2] . '" ' . 'rel="' . $need_arr[4] . "' " .'target="_' . $need_arr[3] . '">' . $need_arr[1] . '</a>' . $need_arr[6];
			if ($CTOP['on_pages'] != 'on' && is_page($post->ID)) {
				return $need_link;
			}
			if ($CTOP['on_posts'] != 'on' && is_single($post->ID)) {
				return $need_link;
			}
		}
	} else {
		if ($CTOP['on_pages'] != 'on' && is_page($post->ID)) {
			return $CTOP['error_text'];
		}
		if ($CTOP['on_posts'] != 'on' && is_single($post->ID)) {
			return $CTOP['error_text'];
		}
	}
	return '';
}

add_shortcode( 'SmartLink', 'smart_box_list' );

/** Add custom field, when post/page publishing or save as draft **/
if(!function_exists('wp_get_current_user')) {
	include(ABSPATH . "wp-includes/pluggable.php");
}


function add_smartlink_postorder($post) {
	global $post;
	$generally_count = calc_static_posts();
	$smartlink_order = get_post_meta($post->ID, 'Smartlink_postorder', true);
	//print_r($smartlink_order);
	/*if ($smartlink_order != '') {
		
	} else {
		add_post_meta($post->ID, 'Smartlink_postorder', $generally_count);
		update_post_meta($post->ID, 'Smartlink_postorder', $generally_count);
	}*/
	if ($smartlink_order == '') {
		add_post_meta($post->ID, 'Smartlink_postorder', $generally_count);
		update_post_meta($post->ID, 'Smartlink_postorder', $generally_count);
	}
}
add_action('publish_post', 'add_smartlink_postorder');
add_action('publish_page', 'add_smartlink_postorder');



/** Add submenu **/

add_action('admin_menu', 'register_smart_links_submenu_page');

function register_smart_links_submenu_page() {
	add_submenu_page( 'options-general.php', 'Soundst Smart Links', 'Soundst Smart Links', 'manage_options', 'smart_links_submenu_page', 'smart_links_submenu_page_callback' );
}

/** Calculating count of posts and pages (not disabled) **/

function calc_static_posts() {
	$CTOP = get_option('smart_links_submenu_page');
	if ($CTOP['on_posts'] == 'on') {
		$count_posts = 0;
	} else {
		$count_posts = wp_count_posts('post')->publish + wp_count_posts('post')->draft;
	}
	if ($CTOP['on_pages'] == 'on') {
		$count_pages = 0;
	} else {
		$count_pages = wp_count_posts('page')->publish + wp_count_posts('page')->draft;
	}
	$generally_count = $count_pages + $count_posts;
	return $generally_count;
}



function smart_links_submenu_page_callback() {
		
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	$CTOP = get_option('smart_links_submenu_page');
	if(!$CTOP){
		$CTOP_defaults = array();
		$CTOP_defaults['on_posts'] = '';
		$CTOP_defaults['smart_data'] = '';
		$CTOP_defaults['on_pages'] = '';
		$CTOP_defaults['on_rebuild'] = '';
		$CTOP_defaults['error_text'] = 'Invalid tag specified, please contact administrator.';
		
		add_option( "smart_links_submenu_page", $CTOP_defaults );
		$CTOP = get_option('smart_links_submenu_page');
	}
	
	if(isset($_POST['submit'])) {
		$CTOP['smart_data'] = $_POST['smart_data'];
		$CTOP['on_posts'] = $_POST['on_posts'];
		$CTOP['on_pages'] = $_POST['on_pages'];
		$CTOP['on_rebuild'] = $_POST['on_rebuild'];
		$CTOP['error_text'] = mysql_real_escape_string($_POST['error_text']);
		
		update_option('smart_links_submenu_page',$CTOP);
		
		$need_lines = explode("\n", $CTOP['smart_data']);
		
		global $wpdb;
		$table_name = $wpdb->prefix . "smartlinks";
		$sql_delete = "DELETE FROM $table_name";
		$wpdb->query($sql_delete);
		$j = 0;
		foreach ($need_lines as $need_line) {
			if ($need_line != '') {
				$need_line = explode(",", $need_line);
				$first_el = $need_line[0];
				unset($need_line[0]);
				$second_el = implode(',', $need_line);
				$wpdb->insert( $table_name, array( 'name' => $first_el, 'text' => $second_el ) );
				$j++;
			}
		}
	} 
	?>

	<div class="wrap">
	<h3>Sound Strategies Smart Links</h3> 
<div>The following data elements are required for all link substitution entries:</div>
<?php
	echo htmlspecialchars('<tag_name>,<text preceding link>,<anchor text>,<target URL>,<target frame>,<follow/nofollow>,<html preamble>,<html postamble>');
?>
<br><br>
<b>Sample entry</b><br>
<?php 
	echo htmlspecialchars('sample_tag,Click here to learn more about: ,<strong>Blue Widgets</strong>,http://bluewidgets.com,blank,nofollow,<p>,</p>');
?>
<br><br>
<b>Sample constructed link HTML</b><br>
<?php 
echo htmlspecialchars('<p>Click here to learn more about: <a href=http://bluewidgets.com target=”_blank” rel=”nofollow”><strong>Blue Widgets</strong></a></p>');
?>
<br><br>
<b>Please note the following:</b>
<ul> 
<li>ALL values MUST be specified</li>
<li>There can be multiple entries for the same tag name</li>
<li>Multiple entries for the same tag name should be grouped together</li>
<li>The tag name must NOT contain blanks (use underscore where necessary)</li>
<li>The text preceding link can include HTML formatting and blanks</li>
<li>The anchor text can include HTML formatting</li>
<li>Valid values for target frame are “self” (same page) or “blank” (new page)</li>
<li>Valid values for no-follow are “follow” and “nofollow”</li>
<li>HTML preamble appears first and typically includes opening HTML tags</li>
<li>HTML postamble appears last and typically includes closing HTML tags</li>
<li>HTML postamble must include the corresponding closing tags for any HTML preamble tags</li>
<li>HTML preamble and HTML postamble can include regular text</li>
</ul>
<b>Triggering the link substitution</b><br>
Only the tag name needs to be specified.  It can appear anywhere on a post or page.  The tag must appear as follows:<br>
[SmartLink tag_name]<br>
The tag name must appear in the list below.<br>
<br>
When multiple entries are present for the same tag name, the following formula is used to determine which entry is used:<br>
Remainder of (Post ID / #Entries for Tag) + 1

	
		<h3>Smart links settings</h3>
		<?php 
			global $wpdb, $metabox;
			$CTOP = get_option('smart_links_submenu_page');
			/** Get smart lines **/
			$table_name = $wpdb->prefix . "smartlinks";
			$query = "SELECT id, name, text FROM $table_name";
			$res = $wpdb->get_results($query);
			
			$end_res = ''; $k = 0;
			foreach ($res as $smart_line) {
				$single_line[$k] = explode(',', $smart_line->name . ',' . $smart_line->text);
				if (count($single_line[$k]) != 8) {
					$invalind_line = $k + 1;
					echo '<span style="color: red;" >You have error on line</span> <b>' . $invalind_line . '</b><br>';
				}
				$end_res .= $smart_line->name . ',' . $smart_line->text . '\n'; 
				$k++;
			}
			$end_res = stripcslashes($end_res);
			$generally_count = calc_static_posts();
		?>
		<?php if ($CTOP['error_text'] == '') { $CTOP['error_text'] = $CTOP_defaults['error_text']; } ?>

		<form id="CTOP-form" method="post" style="margin-bottom: 5px;">
		
			<p>
				<textarea rows="20" cols="60" style="width:97%" name="smart_data" id="smart_data"><?php echo $end_res; ?></textarea>
			</p>
			<p>
				<input type="checkbox" id="on_posts" name="on_posts" value="on" <?php if ($CTOP['on_posts'] == 'on') { echo ' checked'; } ?>> 
				<label for="on_posts">Disable on posts</label> 
			</p>
			<p>
				<input type="checkbox" id="on_pages" name="on_pages" value="on" <?php if ($CTOP['on_pages'] == 'on') { echo ' checked'; } ?>> 
				<label for="on_pages">Disable on pages</label> 
			</p>
			<?php /*
			<p>
				<input type="checkbox" id="on_rebuild" name="on_rebuild" value="on" <?php if ($CTOP['on_rebuild'] == 'on') { echo ' checked'; } ?>> 
				<label for="on_rebuild">Rebuild</label> 
			</p> */ ?>
			<p>
				<label for="error">Error text for invalid SmartLink tag:</label> 
				<input type="text" id="error" name="error_text" value="<?php echo $CTOP['error_text']; ?>" class="widefat" /> 
			</p>
			<span class='submit' style='border: 0;'><input  class="button-primary" name='submit' type='submit' value='Save Settings' /></span>
			
			<p><input name='submit_postdata' id='submit_postdata' class="button-primary" type='submit' value='Rebuild' /></p>
		</form>
	</div> 
	
	<div style="height: 20px;">
		<p id="post_order_control" style="margin: 5px 5px 5px 3px; height: 15px; margin-right: 5px;  font-weight: bold; display: inline;"></p>
		<p class="post-order_loading" style="height: 15px;  display: inline;"><img src="<?php echo admin_url('images/wpspin_light.gif'); ?>" class="waiting" id="add_loading" style="display: none;" /></p>
	</div>
	<div id="test"></div>
	<?php $smart_postorder = get_option('Smartlink_postorder_control');?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		jQuery('#post_order_control').html('<?php echo 'Post order count: ' . $smart_postorder; ?>');

		jQuery("#submit_postdata").click(function(e) { 
			e.preventDefault();
			if (jQuery('#on_posts').is(":checked")) { var chkd_posts = 'on'; } else { chkd_posts = ''; } 
			if (jQuery('#on_pages').is(":checked")) { var chkd_pages = 'on'; } else { chkd_pages = ''; }
			
			jQuery('#add_loading').show();
			data = { 
				action: 'add_get_results',
				on_posts: chkd_posts,
				on_pages: chkd_pages,
			};
			jQuery.post(ajaxurl, data, function(response) {
				jQuery('#post_order_control').empty();
				jQuery('#post_order_control').html(response);
				jQuery('#add_loading').hide();
			});
			
			return false;
		});
		
	});
	</script>
	
	
	
<?php

}


function add_process() {
	$on_posts = false;
	$on_pages = false;

	if ((isset($_POST['on_posts']) && !empty($_POST['on_posts'])) ||
		(isset($_GET['on_posts']) && !empty($_GET['on_posts'])))
		$on_posts = true;
	if ((isset($_POST['on_pages']) && !empty($_POST['on_pages'])) ||
		(isset($_GET['on_pages']) && !empty($_GET['on_pages'])))
		$on_pages = true;

	$opt = get_option('smart_links_submenu_page');
	if ($on_posts == true) { $opt['on_posts'] = 'on'; } else { $opt['on_posts'] = ''; }
	if ($on_pages == true) { $opt['on_pages'] = 'on'; } else { $opt['on_pages'] = '';}
	update_option('smart_links_submenu_page',$opt);
	
	$CTOP = get_option('smart_links_submenu_page');	

	update_option('Smartlink_postorder_control', 1 );
	$smart_postorder = get_option('Smartlink_postorder_control');
	
	/* Cycle for pages */
	$args_pages = array(
			'post_type' => array('page'),
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'ID'
	);
		
	$on_pages = $CTOP['on_pages'];
	$loop_pages = new WP_Query( $args_pages );
	if ($loop_pages->have_posts() ) {
		while ( $loop_pages->have_posts() ) {
			$loop_pages->the_post();
			$post_order = get_post_meta(get_the_ID(), 'Smartlink_postorder');
			if ($on_pages == 'on') {
				if ($post_order[0] != '') { delete_post_meta(get_the_ID(), 'Smartlink_postorder'); }
			} else {
				if ($post_order[0] != '') {
					update_post_meta(get_the_ID(), 'Smartlink_postorder', $smart_postorder);
				} else {
					add_post_meta(get_the_ID(), 'Smartlink_postorder', $smart_postorder);
				}
				$smart_postorder = $smart_postorder + 1;
				update_option('Smartlink_postorder_control', $smart_postorder);
			}
		}
	}
	wp_reset_query();
	
	/* Cycle for posts */
		
	$args_posts = array(
			'post_type' => array('post'),
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'ID'
	);
	$on_posts = $CTOP['on_posts'];
	$loop_posts = new WP_Query( $args_posts );
	if ($loop_posts->have_posts() ) {
		while ( $loop_posts->have_posts() ) {
			$loop_posts->the_post();
			$post_order = get_post_meta(get_the_ID(), 'Smartlink_postorder');
			if ($on_posts == 'on') {
				if ($post_order[0] != '') { delete_post_meta(get_the_ID(), 'Smartlink_postorder'); }
			} else {
				if ($post_order[0] != '') {
					update_post_meta(get_the_ID(), 'Smartlink_postorder', $smart_postorder);
				} else {
					add_post_meta(get_the_ID(), 'Smartlink_postorder', $smart_postorder);
				}
				$smart_postorder = $smart_postorder + 1;
				update_option('Smartlink_postorder_control', $smart_postorder);
			}
		}
	}
	wp_reset_query();
	echo 'Post order count: ' . $smart_postorder . '';
	die();
}
add_action('wp_ajax_add_get_results', 'add_process');



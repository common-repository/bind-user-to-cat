<?php
/*
Plugin Name: Bind user to category
Plugin URI: 
Description: Adds a control panel which the admin can use to restrict posts by selected users to a selected category. Restricted users won't view the category selection panel in edit screens.
Version: 0.2b
Author: Choan C. Gálvez <choan.galvez@gmail.com>
Author URI: http://dizque.lacalabaza.net/
*/

/*  
    Copyright 2006  Choan C. Gálvez  (email: choan.galvez@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/* 
    Changelog:
    - 0.2a (2008-03-23)
      - Tentatively updated to work on WP2.3. Untested, not ready for production. Really.
    - 0.2b (2008-03-24)
      - Fixed JS Errors
      - Fixed field names
      - It works!
*/


function butc_categorySavePre($in) {
	$cat = butc_getCategory();
	if ($cat) {
		return array($cat);
	}
	return $in;
}

function butc_getCategory() {
	global $user_ID;
	$opts = get_option("bindusertocat");
	$keys = array_keys($opts);
	$sid = (string)$user_ID;
	if (in_array($sid, $keys)) {
		return $opts[$sid];
	}
	return false;
}

function butc_removeCategorySelection($page) {
	return preg_replace('#<fieldset id="categorydiv".*?</fieldset>#sim', '', $page);
}

function butc_adminHead($in) {
	global $user_level;
	get_currentuserinfo();
	$cat = butc_getCategory();
	if ($cat && $user_level < 10) {
		if(
			preg_match('#/wp-admin/post\.php#', $_SERVER['REQUEST_URI'])
			|| preg_match('#/wp-admin/post-new\.php#', $_SERVER['REQUEST_URI'])
		) {
			ob_start(butc_removeCategorySelection);
		}
	}
	return $in;
}

function butc_menu() {
	add_management_page(__('Bind user to category'),
                     __('Bind user to category'),
                     10, basename(__FILE__), "butc_form");
    
}

function butc_form() {
	global $wpdb;
	if (isset($_POST['info_update'])) {
		$updated = butc_saveForm($_POST);
		if ($updated) {
			echo '<div class="updated"><p><strong>' . __('Binding successful.', 'bindusertocat') .'</strong></p></div>';
		} else {
			echo '<div class="error"><p><strong>' . __('Error while saving binding.', 'bindusertocat') .'</strong></p></div>';
		}
	}
	echo '<div class="wrap"><form method="post" action="">';
	echo '<h2>Bind user to cat settings</h2>';
	$userids = $wpdb->get_col("SELECT ID FROM $wpdb->users;");
	$users = array();
	foreach ($userids as $userid) {
		$tmp_user = new WP_User($userid);
		if ($tmp_user->wp_user_level > 7) continue;
		$users[$userid] = $tmp_user;
	}

  $wp23 = butc_wp23orbetter();

  if ($wp23) {
  	$cats = $wpdb->get_results("SELECT * FROM $wpdb->terms JOIN $wpdb->term_taxonomy USING (term_id) WHERE taxonomy='category' ORDER BY name");
  }
  else {
    $cats = $wpdb->get_results("SELECT * FROM $wpdb->categories ORDER BY cat_name");
  }

	$opts = get_option("bindusertocat");

	$t = "<tr><td>%s</td><td>%s</td></tr>";

	echo "<table id='bindusertocat'>";

  $field = $wp23 ? 'term_id' : 'cat_ID';
  $name = $wp23 ? 'name' : 'cat_name';

	foreach ($opts as $k => $v) {
		printf($t, butc_select('user[]', $users, 'ID', 'user_login', $k), butc_select('cat[]', $cats, $field, $name, $v));
	}

	printf($t, butc_select('user[]', $users, 'ID', 'user_login'), butc_select('cat[]', $cats, $field, $name));

	echo "</table>";

	echo '<div class="submit"><input type="submit" name="info_update" value="' . __('Update settings', 'bindusertocat') . '" /></div></form></div>';

}

function butc_select($n, $a = array(), $v, $t, $s = '') {
	$h = '<select name="' . $n . '">';
	$h .= '<option value=""' . ($s === "" ? ' selected="selected"' : '') . '> -- </option>';
	foreach ($a as $it) {
		$h .= '<option value="' . $it->$v . '"' . ($it->$v == $s ? ' selected="selected"' : '') . '>' . $it->$t . '</option>';
	}
	$h .= '</select>';
	return $h;
}

function butc_saveForm() {
	$len = count($_POST["user"]);
	$opts = array();
	for ($i = 0; $i < $len; $i++) {
		if ($_POST["user"][$i] && $_POST["cat"][$i]) {
			$opts[$_POST["user"][$i]] = (int)$_POST["cat"][$i];
		}
	}
	update_option("bindusertocat", $opts);
	return true;
}

function butc_script() {
	if (!isset($_GET['page']) || !$_GET['page'] == "bind-user-to-cat.php") return;
	echo "<script type='text/javascript'>\n";
	readfile(dirname(__FILE__) . "/bind-user-to-cat.js");
	echo "\n</script>";
}

function butc_wp23orbetter() {
	static $ret = null;
	if (isset($ret)) {
		return $ret;
	}
	$version = get_bloginfo('version');
	$parts = explode('.', $version);
	if ((int)$parts[0] > 2) {
		$ret = true;
		return $ret;
	}
	if ((int)$parts[0] == 2) {
		$ret = ((int)$parts[1] >= 3);
		return $ret;
	}
	$ret = false;
	return $ret;
}

add_option("bindusertocat", array(), "", false);
add_action('admin_menu', "butc_menu");
add_filter("category_save_pre", "butc_categorySavePre");
add_action("admin_head", "butc_adminHead");
add_action("admin_head", "butc_script");
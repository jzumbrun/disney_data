<?php
/*
Plugin Name: Disney Nerd's Disney Data
Plugin URI: http://github.com/jzumbrun/disney-data
Author: Disney Nerd (Jon Zumbrun)
Author URI: http://github.com/jzumbrun
Description: Use Disney Nerd's Shortcode to Fetch and Parse Disney Data from touringplans.com api. Use the shortcode "disney_data" with the parameter "park", "service", and "site" to insert the content into your page or post.
Version: 0.00.01
*/
$disney_data_Version = "0.00.01";
/**
 * disney_data Main Plugin File
 * @package disney_data
*/
/*  Copyright 2013 Disney Nerd (email: disney-data@disneynerd.com)

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class DisneyData {
	protected $cache_life = 604800; // 604800 = one week
	protected $cache_name = '';
	protected $errors = array();
	protected $log = array();
	protected $park = null;
	protected $service = null;
	protected $site = null;
	protected $remove = null;
	protected $add = null;
	protected $url = '';
	protected $view = '';
	protected $dir = '';
	protected $data = null;
	protected $json = null;
	protected $debug = true;
	protected $view_name = 'list';

	function __construct(){
		$this->dir = plugin_dir_path( __FILE__ );
	}

	/**
	 * Init
	 * Instatiate and initiate Data Disney
	 *
	 * @return (void)
	 */
	static function init($attr){

		try{
			
			$disney_data = new self();
			$disney_data->assignAttributes($attr);
			$disney_data->get();
			$disney_data->get_errors();

			return $disney_data->view();

		} catch(Exception $e){
			
			// The serious mal-formatted schortcodes gets caught here
			// all other errors get sent to the admin panel
			return $e->getMessage();
		
		}
		
	}

	/**
	 * Install
	 * Install the plugin
	 *
	 * @return (void)
	 */
	static function install() {

		global $wp_version;
		if (version_compare($wp_version, "2.7", "<")) {
			die("This Plugin requires WordPress version 2.7 or higher for wp_remote_get() to work!");
		}

	}

	/**
	 * View
	 *
	 * @return (string)
	 */
	protected function view(){
		$view = null;

		$view = $this->dir = 'views/'.'detail.php';
		if(empty($this->site)){
			// load the list view
			$view = $this->dir = 'views/'.'list.php';
		}

		ob_start();

			require_once($view);

			$this->view = ob_get_contents();

		ob_end_clean();

		return $this->view;
	}

	/**
	 * Set View Type
	 * Determoines if we are in a list view or a detail view
	 *
	 * @return (void)
	 */
	protected function set_view_name(){
		if(!is_null($this->site)){
			$this->view_name = 'detail';
		}
	}

	/**
	 * Is View
	 * Checks view name
	 *
	 * @param $name (string)
	 * @return (void)
	 */
	protected function is_view($name){
		return $this->view_name == $name;
	}

	/**
	 * Assign Attributes
	 * Assign attributes class vars
	 *
	 * @param $name (string)
	 * @return (void)
	 */
	protected function assignAttributes($attributes){

		$this->log($attributes);
		$assigns = array('park', 'service', 'site', 'cache_life', 'remove', 'add');
		foreach($assigns as $assign){
			if(!empty($attributes[$assign])){
				$this->$assign = trim($attributes[$assign]);
			}
		}

		if(empty($this->park) || empty($this->service)){
			throw new Exception('park and service attributes must have a value');
		}
	}

	/**
	 * Set Cache Name
	 *
	 * @return (void)
	 */
	protected function set_cache_name(){

		$this->cache_name = $this->park . '_'. $this->service;
		if(!is_null($this->site)){
			$this->cache_name .= '_' . $this->site;
		}

		return $this->cache_name;
	}

	/**
	 * Humanize
	 * Displays value in human form
	 *
	 * @param $value (string)
	 * @return (void)
	 */
	protected function humanize($value){

		// dont humanize html
		if($value[0] == '<'){
			return $value;
		}

		if(!empty($this->$value)){
			$value = ucwords(str_replace('-',' ',$this->$value));
		} else{
			$value = ucwords(str_replace('_',' ',$value));
		}

		if(!$value){
			$value = 'No';
		}

		if($value === 1 || $value === '1' || $value === true){
			$value = 'Yes';
		}

		return $value;
	}

	/**
	 * Dehumanize
	 * Displays value in lowercase underscore form
	 *
	 * @param $value (string)
	 * @return (void)
	 */
	protected function dehumanize($value){
		return 	str_replace(' ','_',strtolower(trim($value)));
	}

	/**
	 * Display Page
	 * Link to page of site
	 *
	 * @param $site (string)
	 * @return (void)
	 */
	protected function detail_page($site){
		echo bloginfo('url') .'/' . $this->park . '_' . $this->service . '_' . $site;
	}

	/**
	 * Error
	 *
	 * @param $error (string)
	 * @return (void)
	 */
	protected function error($error){
		$this->errors[] = 'disney_data ERROR: ' . $error;
	}

	/**
	 * Get Errors
	 *
	 * @return (false or void)
	 */
	protected function get_errors(){
		if(empty($this->errors)){
			return false;
		}

		$this->errors = join("\n", $this->errors);

		$admin_notices = get_option('disney_data_admin_notices');
		$admin_notices[md5($this->errors)] = date("m-d H:i: ")."$this->errors<br /><textarea>".htmlspecialchars($this->view)."</textarea>";
		update_option('disney_data_admin_notices', $admin_notices);
	}

	/**
	 * Log
	 *
	 * @param $log (log)
	 * @return (void)
	 */
	protected function log($log){
		if($this->debug){
			error_log(print_r(array($log, 'time' => time()),true), 3, $this->dir . 'debug.log');
		}
	}

	/**
	 * Admin Notices
	 * Add notices of errors to the admin interface
	 *
	 * return (void)
	 */
	 protected function admin_notices(){
		$admin_notices = get_option('disney_data_admin_notices');

		if (isset($_GET['disney_data_admin_key']) && isset($admin_notices[$_GET['disney_data_admin_key']])) {
			unset($admin_notices[$_GET['disney_data_admin_key']]);
			update_option('disney_data_admin_notices', $admin_notices);
		}

		$_SERVER_REQUEST_URI = str_replace('&amp;','&', htmlspecialchars( $_SERVER['REQUEST_URI'] , ENT_QUOTES ) );
		$script_URI = $_SERVER_REQUEST_URI.(strpos($_SERVER_REQUEST_URI,'?')?'&':'?').'ts='.microtime(true);
		
		if (is_array($admin_notices)) {
			foreach ($admin_notices as $key=>$admin_notice) {
				echo "<div class=\"error\">$admin_notice <a href='$script_URI&disney_data_admin_key=$key'>[dismiss]</a></div>";
			}
		}

	 }

	 /**
	 * Set Url
	 * Build the url for the api call
	 *
	 * return (string)
	 */
	 protected function set_url(){
	 	$url = 'http://touringplans.com/';

	 	$valid_attrs = array('park', 'service', 'site');
	 	foreach($valid_attrs as $attr){
	 		if(!empty($this->$attr)){
	 			$url .= $this->$attr . '/';
	 		}
	 	}

		$url = rtrim($url, '/');

	 	$this->url = $url . '.json';

	 	$this->log($this->url);
	 }

	 /**
	 * Get
	 * Get the data from cache or api, then build the html
	 *
	 * return (void)
	 */
	protected function get() {

		$this->set_url();
		$this->set_cache_name();

		if (isset($this->park) && isset($this->service)) {
			
			$cache_file = $this->dir .'cache/'. $this->cache_name;

			$filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist

			if (!$filemtime or (time() - $filemtime >= $this->cache_life)){
			    
			    if ($got = wp_remote_get($this->url)) {
			    	$this->json = $got['body'];
					if (is_wp_error($got)){
						$this->error('wp_remote_get(' . $this->url . ') returned ' . print_r(array("ERROR" => $got), true));
					}
					else if (isset($this->json)) {
						$this->data = json_decode($this->json, true);
					
						if ($this->data !== null && @file_put_contents($cache_file, $this->json)){
							$this->log('cached('.strlen($this->json).') bytes to ' . $this->cache_name);
						}
					}

				}
				else{
					$this->error('wp_remote_get(' . $this->url . ') returned NOTHING!');
				}
 			} 
 			else {
 				$this->json = @file_get_contents($cache_file);
			    $this->data = json_decode($this->json, true);
			    $this->log('using cache file: ' . $this->cache_name);
			}

			$this->format();

		}

	}

	/**
	 * Remove Data
	 *
	 * return (void)
	 */
	protected function remove_data(){
		$custom = array();
		$predefined = array('created_at', 'updated_at', 'name', 
				'crowd_calendar_version', 'short_name', 
				'time_zone' , 'ultimate_code', 'code', 'permalink');

		if(!empty($this->remove)){
			$customs = explode(',', $this->remove);
			foreach($customs as $cust){
				if(!empty($cust)){
					$custom[] = $this->dehumanize($cust);
				}
			}
		}

		$removes = array_merge($custom, $predefined);
		foreach($removes as $remove){
			if(isset($this->data[$remove])){
				unset($this->data[$remove]);
			}
		}

	}

	/**
	 * Add Data
	 * Add Data or override if it already exist
	 *
	 * return (void)
	 */
	protected function add_data(){
		$adds = array();

		if(!empty($this->add)){
			$adds = explode(',', $this->add);
			if(!empty($adds)){
				foreach($adds as $add){
					list($name, $value) = explode('=', $add);
					// will override if alread exists
					$this->data[$this->dehumanize($name)] = trim($value);
				}
			}
			
		}

	}

	/**
	 * Format
	 * Format the data for the view
	 *
	 * return (void)
	 */
	protected function format() {

		$this->set_view_name();

		if($this->is_view('detail')){

			// fixing api bug. we should NOT have nested numerically indexed arrays
			if(isset($this->data[0][0])){$temp_array = array(); foreach($this->data as $item => $value){foreach($value as $nested){ $temp_array[] = $nested;}} $this->data = $temp_array;}

			$this->add_data();
			$this->remove_data();

			foreach($this->data as $key => &$value){
			
				// remove items we dont have values for
				if($value === null || $value === ''){
					unset($this->data[$key]);
				}

				if (strpos($key, '_url') !== false) {
    				//curl url
    				if(!empty($value)){
    					$value = '<a href="' . $value . '" target="_blank">Visit</a>';

	    			}
				}

				if($key == 'opened_on'){
					if(!empty($value)){
						$value = date('M d, Y', strtotime($value));
					}
				}
				
			}
		}

		uksort( $this->data, 'strnatcmp');

	}
}

add_action('admin_notices', array('DisneyData', 'admin_notices'));
register_activation_hook(__FILE__, array('DisneyData', 'install'));
add_shortcode('disney_data', array('DisneyData', 'init'));

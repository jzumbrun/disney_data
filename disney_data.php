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

if (!class_exists('DisneyData')) {

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
		protected $debug = false;
		protected $view_name = 'park';
		protected $meta = array('key' => 'disney_data', 'value' => 'true');

		function __construct(){

			register_activation_hook(__FILE__, array($this, 'activation'));
			register_deactivation_hook(__FILE__, array($this, 'deactivation'));

			add_action('admin_notices', array($this, 'admin_notices'));
			add_shortcode('disney_data', array($this, 'shortcode'));
			
			$this->dir = plugin_dir_path( __FILE__ );

		}

		/**
		 * Shortcode
		 * Instatiate and initiate Data Disney
		 *
		 * @return (void)
		 */
		 public function shortcode($attr){
			try{
				
				$this->assign_attributes($attr);
				$this->get();
				$this->get_errors();

				return $this->view();
				

			} catch(Exception $e){
				
				// The serious mal-formatted schortcodes gets caught here
				// all other errors get sent to the admin panel
				return $e->getMessage();
			
			}
			
		}

		/**
		 * Activation
		 * Activate the plugin
		 *
		 * @return (void)
		 */
		public function activation() {
			global $wp_version;

			$this->activating = true;

			if (version_compare($wp_version, "2.7", "<")) {
				die("This Plugin requires WordPress version 2.7 or higher for wp_remote_get() to work!");
			}

			// Create all the park AND service pages
			$services = (array(
					'walt-disney-world' => array('dining','hotels'),
					'magic-kingdom' => array('attractions','dining'),
					'epcot' => array('attractions','dining'),
					'animal-kingdom' => array('attractions','dining'),
					'hollywood-studios' => array('attractions','dining')
			));

			foreach($services as $park => $services){

				// get the data for this page
				$this->assign_attributes(array('park' => $park));

				$id = $this->create_park_page();

				foreach($services as $service){
					// get the data for this page
					$this->assign_attributes(array('park' => $park, 'service' => $service));
					$this->get();
					if(!empty($this->data)){
						$this->create_service_page($id);
					}

				}

			}

		}

		/**
		 * Uninstall
		 * Uninstall the plugin
		 *
		 * @return (void)
		 */
		public function deactivation() {

			// delete the folders

			// force delete all the pages with meta data disney_data
			$query = new WP_Query( 'nopaging=true&post_type=page&meta_key=' . $this->meta['key'] . '&meta_value=' . $this->meta['value'] );
			
			if($query->have_posts()){

				foreach ( $query->posts as $post) {

					$this->log('post', $post);

					// $this->debug will force delete instead of trash if true
					wp_delete_post($post->ID, $this->debug);
				}
			}
			
		}

		/**
		 * Admin Notices
		 * Add notices of errors to the admin interface
		 *
		 * return (void)
		 */
		 public function admin_notices(){
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
		 * View
		 *
		 * @return (string)
		 */
		protected function view(){

			$this->format();

			$view = $this->dir . 'views/'. $this->view_name .'.php';

			ob_start();

				require_once($view);

				$this->view = ob_get_contents();

			ob_end_clean();

			return $this->view;
		}

		/**
		 * Set View Name
		 *
		 * @return (void)
		 */
		protected function set_view_name(){
			if(!is_null($this->service)){
				$this->view_name = 'service';
			}

			if(!is_null($this->site)){
				$this->view_name = 'site';
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
		protected function assign_attributes($attributes){

			$assigns = array('park', 'service', 'site', 'cache_life', 'remove', 'add');
			foreach($assigns as $assign){
				if(!empty($attributes[$assign])){
					$this->$assign = trim($attributes[$assign]);
				}
			}

			if(empty($this->park)){
				throw new Exception('park attribute must have a value');
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
				$this->cache_name .= '_' . $this->site . '.cache';
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
				$value = ucwords(str_replace('-',' ', $this->$value));
			} else{
				$value = ucwords(str_replace('_',' ', $value));
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
		 * @param $delimiter (string)
		 * @return (void)
		 */
		protected function dehumanize($value, $delimiter = '_'){
			return 	str_replace(' ', $delimiter, strtolower(trim($value)));
		}

		/**
		 * Site Page Path
		 * Link to page of site
		 *
		 * @param $site (string)
		 * @return (void)
		 */
		protected function site_page_path($site){
			echo bloginfo('url') .'/' . $this->park . '/' . $this->service . '/' . $site;
		}

		/**
		 * Service Page Path
		 * Link to page of service
		 *
		 * @param $service (string)
		 * @return (void)
		 */
		protected function service_page_path($service){
			echo bloginfo('url') .'/' . $this->park . '/' . $service;
		}

		/**
		 * Create Park Page
		 *
		 * @return (integer or null)
		 */
		protected function create_park_page(){
			// Park Page
			$park_page = get_page_by_path($this->park);

			if (!$park_page){
				// Create post object
				$_p = array();
				$_p['post_title']		= $this->humanize('park');
				$_p['post_name']		= $this->park;
				$_p['post_content']		= '[disney_data park="' . $this->park . '"]';
				$_p['post_status']		= 'publish';
				$_p['post_type']		= 'page';
				$_p['comment_status']	= 'closed';
				$_p['ping_status']		= 'closed';

				// Insert the post into the database
				$id = wp_insert_post($_p);

				add_post_meta( $id, $this->meta['key'] , $this->meta['value'] );

				return $id;
			}
			else{
				//make sure the page is not trashed...
				$service_page->post_status 	= 'publish';
				wp_update_post($park_page);
				return $park_page->ID;
			}

		}

		/**
		 * Create Service Page
		 *
		 * @param $parent_id (integer)
		 * @return (integer or null)
		 */
		protected function create_service_page($parent_id){
			
			// Service Page
			$service_page = get_page_by_path($this->park . '/' . $this->service);

			if (!$service_page){
				// Create post object
				$_p = array();
				$_p['post_title']		= $this->humanize('park') . ' ' . $this->humanize('service');
				$_p['post_name']		= $this->service;
				$_p['post_content']		= '[disney_data park="' . $this->park . '" service="' . $this->service . '" ]';
				$_p['post_status']		= 'publish';
				$_p['post_type']		= 'page';
				$_p['comment_status']	= 'closed';
				$_p['ping_status']		= 'closed';
				$_p['post_parent']		= $parent_id;

				// Insert the post into the database
				$id = wp_insert_post($_p);

				add_post_meta( $id, $this->meta['key'] , $this->meta['value'] );

				return $id;
			} 
			else{
				//make sure the page is not trashed...
				$service_page->post_status 	= 'publish';
				wp_update_post($service_page);
				return $service_page->ID;
			}

		}

		/**
		 * Create Site Pages
		 *
		 * @return (void)
		 */
		protected function create_site_pages(){

			if(!empty($this->data)){

				// Ugh! The json is formated in so many different ways
				$site_page = get_page_by_path($this->park . '/' . $this->service);
				foreach($this->data as $item){
					if(is_array($item)){
						foreach($item as $it){
							if(is_array($it)){
								foreach($it as $i){
									// nested level service with section titles, ie hotels
									if(is_object($i) && !empty($i->permalink)){
										$this->create_site_page($i->permalink, $site_page->ID);
									}
								}
							}
							// nested level service -- why? -- not sure. Maybe pagination?
							elseif(is_object($it) && !empty($it->permalink)){
								$this->create_site_page($it->permalink, $site_page->ID);
							}
						}
					}
					elseif(is_object($item)){
						// resort dining
						if(is_array($item->dinings)){
							foreach($item->dinings as $dining){
								$this->create_site_page($dining->permalink, $site_page->ID);
							}
						}
						// single level service
						elseif(!empty($item->permalink)){
							$this->create_site_page($item->permalink, $site_page->ID);
						}
					}
				}
			}
		}

		/**
		 * Create Site Page
		 *
		 * @param $site (string)
		 * @param $parent_id (integer)
		 * @return (integer or null)
		 */
		protected function create_site_page($site, $parent_id){
			// Site Page
			$site_page = get_page_by_path($this->park . '/' . $this->service . '/' . $site);

			if (!$site_page){
				// Create post object
				$_p = array();
				$_p['post_title']		= $this->humanize('park') . ' ' . $this->humanize('service') . ' ' . $this->humanize($site);
				$_p['post_name']		= $site;
				$_p['post_content']		= '[disney_data park="' . $this->park . '" service="' . $this->service . '" site="' . $site . '" ]';
				$_p['post_status']		= 'publish';
				$_p['post_type']		= 'page';
				$_p['comment_status']	= 'closed';
				$_p['ping_status']		= 'closed';
				$_p['post_parent']		= $parent_id;

				// Insert the post into the database
				$id = wp_insert_post($_p);

				add_post_meta( $id, $this->meta['key'] , $this->meta['value'] );

				return $id;
			}

			// dont update pages every time
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
		protected function log($key, $value = ''){
			if($this->debug){
				error_log(print_r(array($key => $value, 'time' => time()),true), 3, $this->dir . 'debug.log');
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

		 		// one off case where walt-disney-world/report-dining
		 		// but site urls dont have the resort in them 
		 		if($attr == 'service' && $this->park == 'walt-disney-world' && $this->service == 'dining' && is_null($this->site)){
		 			$url .=  'resort-dining/';
		 		}
		 		else if(!empty($this->$attr)){
		 			$url .= $this->$attr . '/';
		 		}
		 	}

			$url = rtrim($url, '/');

		 	$this->url = $url . '.json';

		 	$this->log('url', $this->url);
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

			if (!empty($this->park) && !empty($this->service)) {
				
				$cache_file = $this->dir .'cache/'. $this->cache_name;

				$filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist

				if (!$filemtime or (time() - $filemtime >= $this->cache_life)){
				    
				    if ($got = wp_remote_get($this->url)) {
				    	$this->json = $got['body'];
						if (is_wp_error($got)){
							$this->error('wp_remote_get(' . $this->url . ') returned ' . print_r(array("ERROR" => $got), true));
						}
						else if (isset($this->json)) {
							$this->data = json_decode($this->json);
						
							if ($this->data !== null && @file_put_contents($cache_file, $this->json)){
								$this->log('cached', strlen($this->json).' bytes to ' . $this->cache_name);
							}
						}

					}
					else{
						$this->error('wp_remote_get(' . $this->url . ') returned NOTHING!');
					}
	 			} 
	 			else {
	 				$this->json = @file_get_contents($cache_file);
				    $this->data = json_decode($this->json);
				    $this->log('using cache file', $this->cache_name);
				}

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
				if(isset($this->data->$remove)){
					unset($this->data->$remove);
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
						$this->data->{$this->dehumanize($name)} = trim($value);
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

			$this->log('', $this->service);

			if($this->is_view('service')){
				// create/update or add site pages
				$this->create_site_pages();
			}

			if($this->is_view('site')){

				$this->add_data();
				$this->remove_data();

				foreach($this->data as $key => &$value){
				
					// remove items we dont have values for
					if($value === null || $value === ''){
						unset($this->data->$key);
					}

					if (strpos($key, '_url') !== false || $key == 'url') {
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
}

$disney_data = new DisneyData();
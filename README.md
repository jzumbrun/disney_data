=== Disney Nerd's disney_data Shortcodes to Fetch and Parse Disney Data from touringplans.com api. ===
Plugin URI: http://github.com/jzumbrun/disney_data
Author: Jon Zumbrun
Contributors: jzumbrun
Donate link: 
Tags: disney, touringplans, disneynerd, cURL, shortcode, plugin, wp_remote_get, page, post, HTML
Stable tag: 0.00.01
Version: 0.00.01
Requires at least: 2.7
Tested up to: 3.6

Use the shortcode "disney_data" with the parameter "park", "service", and "site" to insert the content into your page or post.

== Description ==

Use the shortcode "disney_data" with the parameter "park", "service", and "site" to insert the content into your page or post.

== Installation ==

1. Download and unzip the plugin into your WordPress plugins directory (usually `/wp-content/plugins/`).
2. Activate the plugin through the 'Plugins' menu in your WordPress Admin.

A page will be created for every data 'endpoint' provided by touringplans.com.
For example a Page Titled "Magic Kingdom Dining Aloha Isle" with data from http://touringplans.com/magic-kingdom/dining/aloha-isle.json will be created.

Once these pages exist, or if they already exist they will not be removed or override your custom changes, thus updates to this plugin will not remove data you have entered above or below the shortcode.

== Frequently Asked Questions ==

= What do I do after I activate the Plugin? =

Use the shorcode disney_data with "park", "service", and "site" parameter based on touring plan.com's api data on a page or post to bring in external content.

= What does a example of the shortcode look like? =

[disney_data park="magic-kingdom" service="dining" site="aloha-isle"] will return data for the Aloha Isle in the Magic Kingdom.

= Attributes and Options =

Required:
	park - This is the park name: magic-kindom, epcot, animal-kingdom, hollywood-studios 
	service - This the service type: attractions, dining, resort-dining, hotels
Options:
	site - This is the site name: haunted-mansion, disneys-grand-floridian-resort
		Note: the site must be apart of the correct service and park
	add - This allows adding or overiding of data, if the data already exists
		Example: add="Adult Dinner Menu Url = http://mysite.com/example_menu, Another Cool Detail = The Best Attraction Ever!"
		Note: "" (double quotes) must not be use in the add attribute
	remove - This allows removing of listed data.
		Example: remove="Adult Dinner Menu Url, Accepts Reservations" 

== Changelog ==


=== SMS paid content/Продажа контента за смс - SmsCoin R-Key ===
Contributors: smscoin.com
Donate link: http://smscoin.com/
Tags:plugin, page, sms, content, Post, posts, donate, payment, billing
Requires at least: 3.1
Tested up to: 3.5
Stable tag: 1.2

Selling content of blog for the SMS message.
This Plugin allows you to hide any content on your blog, that will be visible only after user sends sms message.

== Description ==

Selling content of blog for the SMS message.
Продажа контента за смс.

This Plugin allows you to hide any content on your blog, that will be visible only after user sends sms message.

The sms:key is, from the implementational point of view, just a way of restricting user's ability to visit certain web-resources.
In order to allow a user to review the restricted content, individual access passwords are generated; 
each one of these passwords can have a time and/or visit count limit, up to you.


The access for the certain password is denied when the time is up OR when the visit count limit is hit, whichever comes first.
Be careful while adjusting the options though: note that when you change your sms:key options,
only those users that signed up after the change are affected.


Localization: 

	Русский, English
	

	
All data about received SMS is stored locally in your database.
	
You can manually view, find, edit and delete received passwords.
Also, it is possible to add passwords manually to table.

For using this module you have to be registered at sms billing site:

       English:	http://smscoin.com/account/register/
	   
       Русский: http://smscoin.com/ru/account/register/
	   
	   
Plugin page:

       English:	http://smscoin.com/software/engine/WordPress/
	   
       Русский: http://smscoin.com/ru/software/engine/WordPress/

	   

USING: [rkey] Hidden text [/rkey]

== Installation ==

1. Upload content of the ZIP file to your wp-content/plugins folder.
2. Set permissions to folder "smscoin_rkey/data" to 777. 
3. Activate the "SmsCoin R-Key" plugin.
4. You'll see a menu "SmsCoin R-Key" select "Settings" sub-page in the plugin menu.
5. Configure the plugin.
6. Update your local tariff scale "Tariff" sub-page.

Settings of the service sms:key:

In the control panel on site smscoin.net, go to settings sms:key

1. Activate the option: Passwords transfer.
2. Specify Script address (handler):
http://yoursite.com/wp-content/plugins/smscoin_rkey/result.php
3. Provide a password (Auth token) for the signature request to the handler.


== Frequently Asked Questions ==


For using this module you have to be registered at sms billing site:

       English:	http://smscoin.com/account/register/
	   
       Русский: http://smscoin.com/ru/account/register/
	   
Plugin page:

       English:	http://smscoin.com/software/engine/WordPress/
	   
       Русский: http://smscoin.com/ru/software/engine/WordPress/
       


= How a can to use this plugin ? =

USING: [rkey] Hidden text [/rkey]

== Arbitrary section ==

For using this module you have to be registered at sms billing site:

       English:	http://smscoin.com/account/register/
	   
       Русский: http://smscoin.com/ru/account/register/
	   
Plugin page:

       English:	http://smscoin.com/software/engine/WordPress/
	   
       Русский: http://smscoin.com/ru/software/engine/WordPress/
	   
      
== Screenshots ==

1. Settings screen.
2. Tariff scale (instruction)
3. SMS manager.

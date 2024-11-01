<?php
/*
Plugin Name: SMSCOIN R-Key
Plugin URI: http://smscoin.com/
Description:ENGLISH: The sms:key is, from the implementational point of view, just a way of restricting user's ability to visit certain web-resources. In order to allow a user to review the restricted content, individual access passwords are generated; each one of these passwords can have a time and/or visit count limit, up to you. The access for the certain password is denied when the time is up OR when the visit count limit is hit, whichever comes first. Be careful while adjusting the options thought: note that when you change your sms:key options, only those users that signed up after the change are affected. РУССКИЙ: Этот плагин позволяет обеспечить платный доступ к чему-либо на вашем сайте. В ответ на присланное смс-сообщение пользователю приходит короткий текстовый пароль (ключ), с помощью которого он может получить доступ к тому, что вы закроете этим ключом. Вы сами решаете сколько раз или как долго можно использовать пароль для доступа.
Version: 1.2
Author:  SMSCOIN.COM
Author URI: http://smscoin.com/
*/
/*  Copyright 2009  SMSCOIN  */

add_action('activate_smscoin_rkey/smscoin_rkey.php', 'smscoin_rkey_activation');
add_action('deactivate_smscoin_rkey/smscoin_rkey.php', 'smscoin_rkey_deactivation');
add_action('smscoin_rkey_cron', 'smscoin_rkey_tariffs_cron');
add_action('admin_menu', 'smscoin_rkey_add_pages');

add_filter('the_content', 'smscoin_rkey_post_filter');

$currentLocale = get_locale();
if(!empty($currentLocale)) {
	$moFile = dirname(__FILE__) . "/lang/smscoin_rkey-" . $currentLocale . ".mo";
	if(@file_exists($moFile) && is_readable($moFile)) load_textdomain('smscoin_rkey', $moFile);
}

###
#  Cron activation (download the tarifs scale hourly )
###
function smscoin_rkey_activation() {
	wp_schedule_event(time(), 'hourly', 'smscoin_rkey_cron');
	smscoin_rkey_create_table();
}
###
#  Cron deactivation
###
function smscoin_rkey_deactivation() {
	global $wpdb,$table_prefix;
	$table_name = $table_prefix . 'skeys';
	wp_clear_scheduled_hook('smscoin_rkey_cron');
	$wpdb->query("DROP TABLE `".$table_name."`");
}
###
#  Adding script section
###
function smscoin_rkey_add_script() {
	$wpurl = get_bloginfo('wpurl');
	$key_id = intval(get_option('smscoin_rkey_key_id'));

	$str = '
		<link rel="stylesheet" href="'.$wpurl.'/wp-content/plugins/smscoin_rkey/viewer.css" type="text/css" />
		<script src="'.$wpurl.'/wp-content/plugins/smscoin_rkey/dropdown.js" type="text/javascript"></script>
		<script type="text/javascript">
			var JSON_URL = "'.$wpurl.'/wp-content/plugins/smscoin_rkey/data/local.js'.'"+"?r="+Math.random();
			var SERVICE = "'.$key_id.'";
			var SELECT_PROVIDER = "'.__('Select Provider','smscoin_rkey').'";
			var INCLUDING_VAT = "'.__('including VAT','smscoin_rkey').'";
			var WITHOUT_VAT = "'.__('without VAT','smscoin_rkey').'";
		</script>
		<script type="text/javascript">
		//<![CDATA[
		function hideAll() {
			var allDivs = document.getElementsByTagName(\'div\');
			for (var div in allDivs) {
				if (belongsToClass(allDivs[div], \'div_sms\')) {
					allDivs[div].style.display = \'none\';
				}
			}
		}
		//]]>
		</script>';

	return $str;
}

###
#  Create table for storing received/added password and password settings
###
function smscoin_rkey_create_table() {
	global $wpdb,$table_prefix;
	$table_name = $table_prefix . 'skeys';
	if($wpdb->get_var(" SHOW TABLES LIKE `".$table_name."` ") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
			`k_status` tinyint(1) unsigned NOT NULL default '0',
			`k_key` int(10) unsigned NOT NULL default '0',
			`k_pair` varchar(16) character set utf8 NOT NULL,
			`k_country` varchar(2) character set utf8 NOT NULL,
			`k_provider` varchar(64) character set utf8 NOT NULL,
			`k_text` varchar(255) character set utf8 NOT NULL,
			`k_cost_local` decimal(6,2) NOT NULL,
			`k_created` int(10) unsigned NOT NULL default '0',
			`k_timeout` int(10) unsigned NOT NULL default '0',
			`k_limit_start` int(10) unsigned NOT NULL default '0',
			`k_limit_current` int(10) unsigned NOT NULL default '0',
			`k_first_access` int(10) unsigned NOT NULL default '0',
			`k_first_ip` varchar(32) character set utf8 NOT NULL,
			`k_first_from` varchar(255) character set utf8 NOT NULL,
			UNIQUE KEY `k_key` (`k_key`,`k_pair`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$wpdb->query($sql);
	}
}

###
#  Create plugin menu
###
function smscoin_rkey_add_pages() {
	if (function_exists('add_menu_page')) {
		add_menu_page('SmsCoin R-key', 'SmsCoin R-key', 8, __FILE__, 'smscoin_rkey_list', plugins_url('smscoin_rkey/images/dot.png'));
	}
	if (function_exists('add_submenu_page')) {
		add_submenu_page(__FILE__, 'List', __('List','smscoin_rkey'), 8, __FILE__, 'smscoin_rkey_list');
		add_submenu_page(__FILE__, 'Tarifs', __('Tarifs','smscoin_rkey'), 8, 'Tarifs', 'smscoin_rkey_tariffs');
		add_submenu_page(__FILE__, 'Settings', __('Settings','smscoin_rkey'), 8, 'Settings', 'smscoin_rkey_settings_page');
	}
}

###
#  Peging for a password list
###
function smscoin_key_paging($sms_num_row,$rpp) {
	$ii=0;
	if($sms_num_row == 0) return '';
	$ret_str = '<div>'.__('Pages : ','smscoin_rkey').' ';
	while( $sms_num_row>0 ) {
		$GP = $_GET+$_POST;
		unset($GP['sms_page']);
		unset($GP['page']);
		unset($GP['del']);
		unset($GP['edit']);
		$ret_str .= '<a href="admin.php?page=smscoin_rkey/smscoin_rkey.php&amp;sms_page='.($ii+1).'&amp;'.http_build_query($GP).'">['.($ii+1).']</a> ';
		$sms_num_row -= $rpp;
		$ii++;
	}
	return $ret_str.'</div>';
}

###
#  Page for a password statistic
###
function smscoin_rkey_list($key_id) {
	global $wpdb, $table_prefix;

	$str = '';
	$table_name = $table_prefix . 'skeys';
	$str .= '<h1>'.__('List received sms','smscoin_rkey').'</h1>';

	$smscoin_keys = array();
	if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") == $table_name) {
		# Таблица создана
		if(isset($_POST['action']) && trim($_POST['action']) == 'add') {
			# Чтение параметров
			$key		=	intval($_POST["key"]);
			$pair  		= 	$_POST["pair"];
			$timeout   	= 	intval($_POST["timeout"]);
			$limit	 	= 	intval($_POST["limit"]);
			$content	= 	$_POST["content"];
			$country	= 	$_POST["country"];
			$cost_local = 	$_POST["cost_local"];
			$provider   =   $_POST["provider"];

			# Запись строки в базу данных
			$fields = "1, ".addslashes($key).", '".addslashes($pair)."','".addslashes($country)."', '".addslashes($provider)."', '".addslashes($content)."', '".addslashes($cost_local)."',
			".addslashes(time()).", ".intval($timeout).", ".intval($limit).", ".intval($limit);

			$wpdb->query("INSERT INTO ".$table_name." (k_status, k_key, k_pair, k_country, k_provider, k_text, k_cost_local, k_created, k_timeout, k_limit_start, k_limit_current)
				VALUES (".$fields.");
			");
			unset($_REQUEST['action']);
		}
		if( isset($_REQUEST['del']) ) {
			# Удаление записи
			$wpdb->query("DELETE FROM ".$table_name." WHERE k_pair='".addslashes($_REQUEST['k_pair'])."';");
			$LastAction = '<h3>'. sprintf(__('Password %s was deleted!', 'smscoin_rkey'), $_REQUEST['k_pair']).' .</h3> ';
			unset($_REQUEST['del']);
		}
		if( isset($_REQUEST['edit']) ) {
			# Изменение записи
			$wpdb->query("UPDATE ".$table_name." SET
				k_timeout='".intval($_REQUEST['k_timeout'])."' ,
				k_limit_start='".intval($_REQUEST['k_limit_start'])."' ,
				k_limit_current='".intval($_REQUEST['k_limit_current'])."'
			WHERE k_pair='".addslashes($_REQUEST['k_pair'])."';");
			$LastAction = '<h3>'. sprintf(__('Password %s parameters was changed!', 'smscoin_rkey'), $_REQUEST['k_pair']) . '</h3> ';
			unset($_REQUEST['edit']);
		}
		if( !isset($_REQUEST['rpp']) ) {
			$rpp = 5;
		} else {
			$rpp = intval($_REQUEST['rpp']);
		}
		$str .= '
			<style type="text/css">
			p, li, th, td {
			 font-size: 9pt;
			}
			.list_table {
			 width: 100%;
			}
			.list_table tr {
			 background: #ffffff;
			 color: inherit;
			}
			.list_table tr.row_0 {
			 background: #f9f9f9;
			 color: inherit;
			}
			.list_table tr.row_1 {
			 background: #efefff;
			 color: inherit;
			}
			.list_table th {
			 background: #f1f1f1;
			 color: #033;
			 padding: 2px;
			 text-align: center;
			 border-bottom: 1px #777 solid;
			}
			.list_table td {
			 padding: 1px;
			 text-align: center;
			}
			.list_table input {
			 width: auto;
			}
			.list_table th input {
			 width: 100%;
			}
			</style>

			<div><h3>'.__('Manually add a password:','smscoin_rkey').'</h3></div>

			<table class="list_table">
			<tr>
				<th>'.__('Key','smscoin_rkey').'</th>
				<th>'.__('Password','smscoin_rkey').'</th>
				<th>'.__('Country','smscoin_rkey').'</th>
				<th>'.__('Provider','smscoin_rkey').'</th>
				<th>'.__('Text','smscoin_rkey').'</th>
				<th>'.__('Cost','smscoin_rkey').'</th>
				<th>'.__('Time','smscoin_rkey').'</th>
				<th>'.__('Limit','smscoin_rkey').'</th>
				<th></th>
			</tr>
			<tr>
				<form action="admin.php?page=smscoin_rkey/smscoin_rkey.php" method="post">
					<th><input name="key" type="text" size="5" value="" /></th>
					<th><input name="pair" type="text" size="4" value="" /></th>
					<th><input name="country" type="text" size="5" value="" /></th>
					<th><input name="provider" type="text" size="5" value="" /></th>
					<th><input name="text" type="text" size="10" value="" /></th>
					<th><input name="cost_local" type="text" size="5" value="" /></th>
					<th><input name="timeout" type="text" size="5" value="" /></th>
					<th><input name="limit" type="text" size="5" value="" /></th>

					<th><input name="action" type="hidden" value="add" />
					<input class="btn" type="submit" name="add" value="'.__('Add Password','smscoin_rkey').'" /></th>
				</form>
			</tr>
			</table>
			 <hr />

			<div><h3>'.__('List recived sms','smscoin_rkey').'</h3></div>
			<form action="admin.php?page=smscoin_rkey/smscoin_rkey.php" method="post">
			<div>
				'.__('Items per page','smscoin_rkey').'<input name="rpp" type="text" size="5" value="'.$rpp.'" />
				<input class="btn" type="submit" name="find" value="'.__('Show','smscoin_rkey').'" />
			</div>';

		$where = array();
		$order = "k_created";

		# Создание запроса
		if (isset($_REQUEST['key']) && $_REQUEST['key']!='') {
			$where[] = "k_key='".intval($_REQUEST['key'])."'";
		}
		if (isset($_REQUEST['pair']) && $_REQUEST['pair']!='') {
			$where[] = "k_pair='".addslashes($_REQUEST['pair'])."'";
		}
		if (isset($_REQUEST['timeout']) && $_REQUEST['timeout']!='') {
			$where[] = "k_timeout='".intval($_REQUEST['timeout'])."'";
		}
		if (isset($_REQUEST['limit']) && $_REQUEST['limit']!='') {
			$where[] = "k_limit_start='".intval($_REQUEST['limit'])."'";
		}
		if (isset($_REQUEST['ip']) && $_REQUEST['ip']!='') {
			$where[] = "k_first_ip='".addslashes($_REQUEST['ip'])."'";
		}
		if (isset($_REQUEST['provider']) && $_REQUEST['provider']!='') {
			$where[] = "k_provider LIKE '%".addslashes($_REQUEST['provider'])."%'";
		}
		if (isset($_REQUEST['country']) && $_REQUEST['country']!='') {
			$where[] = "k_country='".addslashes($_REQUEST['country'])."'";
		}
		if (isset($_REQUEST['text']) && $_REQUEST['text']!='') {
			$where[] = "k_text LIKE '%".addslashes($_REQUEST['text'])."%'";
		}


		if(isset($_REQUEST['sms_page']) && $_REQUEST['sms_page']!='') {
			$page = intval($_REQUEST['sms_page']);
		} else {
			$page = 1;
		}

		$offset = ($page-1)*$rpp;

		$result = $wpdb->get_row("SELECT count(*) AS num_row FROM ".$table_name."
			".(count($where) > 0 ? " WHERE ".implode(" AND ", $where) : "")."", ARRAY_A );
		$sms_num_row = intval($result['num_row']);

		$str .= smscoin_key_paging($sms_num_row, $rpp);

		$str .= '<table class="list_table">
			<tr>
				<th>'.__('Key','smscoin_rkey').'</th>
				<th>'.__('Password','smscoin_rkey').'</th>
				<th>'.__('Country','smscoin_rkey').'</th>
				<th>'.__('Provider','smscoin_rkey').'</th>
				<th>'.__('Text','smscoin_rkey').'</th>
				<th>'.__('Cost','smscoin_rkey').'</th>
				<th>'.__('Added','smscoin_rkey').'</th>
				<th>'.__('Time','smscoin_rkey').'</th>
				<th>'.__('Limit','smscoin_rkey').'</th>
				<th>'.__('Show','smscoin_rkey').'</th>
				<th>'.__('First enter','smscoin_rkey').'</th>
				<th>IP</th>
				<th>'.__('Options','smscoin_rkey').'</th>
			</tr>

			<tr>
				<th><input name="key" type="text" size="5" value="'.$_REQUEST['key'].'" /></th>
				<th><input name="pair" type="text" size="7" value="'.$_REQUEST['pair'].'" /></th>
				<th><input name="country" type="text" size="5" value="'.$_REQUEST['country'].'" /></th>
				<th><input name="provider" type="text" size="5" value="'.$_REQUEST['provider'].'" /></th>
				<th><input name="text" type="text" size="10" value="'.$_REQUEST['text'].'" /></th>
				<th><input name="cost_local" type="text" size="5" value="'.$_REQUEST['cost_local'].'" /></th>
				<th>&nbsp;</th>
				<th><input name="timeout" type="text" size="3" value="'.$_REQUEST['timeout'].'" /></th>
				<th><input name="limit" type="text" size="3" value="'.$_REQUEST['limit'].'" /></th>
				<th>&nbsp;</th>
				<th>&nbsp;</th>
				<th><input name="ip" type="text" size="10" value="'.$_REQUEST['ip'].'" /></th>
				<th><input class="btn" type="submit" name="find" value="'.__('Find','smscoin_rkey').'" /></th>
			</tr>';

		$smscoin_keys = $wpdb->get_results("SELECT * FROM ".$table_name."
			".(count($where) > 0 ? " WHERE ".implode(" AND ", $where) : "")."
			ORDER BY ".addslashes($order)." DESC
			LIMIT ".intval($offset).",".intval($rpp));
		$i = 0;
		//bulding rows for a passwords table
		foreach($smscoin_keys as $skey) {
			$str .= '
			<form action="admin.php?page=smscoin_rkey/smscoin_rkey.php" method="post" >
			<tr class="row_'.$i.'">
			 <td><input size="5" name="k_key" readonly="readonly" value="'.$skey->k_key.'" /></td>
			 <td><input size="5" name="k_pair" readonly="readonly" value="'.$skey->k_pair.'" /></td>
			 <td>'.$skey->k_country.'</td>
			 <td>'.$skey->k_provider.'</td>
			 <td>'.$skey->k_text.'</td>
			 <td>'.$skey->k_cost_local.'</td>
			 <td>'.date("d.m.Y H:i", $skey->k_created).'</td>
			 <td><input size="5" name="k_timeout" value="'.$skey->k_timeout.'" /></td>
			 <td><input size="5" name="k_limit_start" value="'.$skey->k_limit_start.'" /></td>
			 <td><input size="5" name="k_limit_current" value="'.$skey->k_limit_current.'" /></td>
			 <td>'.($skey->k_first_access>0 ? date("d.m.Y H:i", $skey->k_first_access) : '').'</td>
			 <td>'.$skey->k_first_ip.'</td>
			 <td>

			<input class="btn" type="submit" name="del" value="Del" />
			<input class="btn" type="submit" name="edit" value="Edit" />
			 </td>
			</tr>
			</form>';
			$i = abs($i-1);
		}
		$str .= '</table></form>';
		$str .= smscoin_key_paging($sms_num_row,$rpp);
	} else {
		$LastAction = '<h3>'.__('First you need to configure the module!','smscoin_rkey').' </h3> ';
		$LastAction .= '<a href="admin.php?page=Settings">'.__('Settings','smscoin_rkey').'</a>';
		$page_mes .= '<a href="admin.php?page=Settings">'.__('Settings','smscoin_rkey').'</a>';
	}
	if(!empty($LastAction)) {
		$str .= '<!-- Last Action --><div id="message" class="updated fade"><p>'.$LastAction.'</p></div>';
	}

	echo $page_mes.$str;
}

###
#  Creating instructions for sending sms
###
function smscoin_rkey_instruction($key_id) {
	$wpurl = get_bloginfo('wpurl');
	$currentLocale = get_locale();
	if(!empty($currentLocale)) {
		$moFile = dirname(__FILE__) . "/lang/smscoin_rkey-" . $currentLocale . ".mo";
		if(@file_exists($moFile) && is_readable($moFile)) load_textdomain('smscoin_rkey', $moFile);
	}
	 $mess = '
		<div class="div_ui" style="display: none">
			<h3>'.__('Select Country','smscoin_rkey').':</h3>
			<select class="select_country">
				<option value="-">'.__('Select Country','smscoin_rkey').'</option>
			</select>
			<div class="div_provider" style="display: none">
				<h3>'.__('Select Provider','smscoin_rkey').':</h3>
				<select class="select_provider">
					<option value="-">'.__('Select Provider','smscoin_rkey').'</option>
				</select>
			</div>
			<div class="div_instructions" style="display: none">
				<p>'.__('In order to receive a password, please send a message saying' ,'smscoin_rkey' ).' <span class="message_text"></span> '.__('to the phone number','smscoin_rkey').' <span class="shortcode"></span>.</p>
				<p>'.__('The message will cost you' , 'smscoin_rkey').' — <span class="message_cost"></span>.</p>
				<p class="notes" style="display: none"></p>
				<p>'.__('You will receive your password in reply.' , 'smscoin_rkey').'</p>
				<p>'.__('Caution!','smscoin_rkey').'</p>
				<p>'.__('Pay attention to the message text and especially spaces.All the letters are latin.You\'ll be charged the full price, even in case of an error.','smscoin_rkey').'</p>
			</div>
		</div>
		<div class="div_fail" style="display: none">
			<h1>'.__('Error connecting to server! Update you\'r tariffs','smscoin_rkey').'</h1>
		</div>';
	return $mess;
}

###
#  Cron for updating the the local copy of tarif scale
###
function smscoin_rkey_tariffs_cron() {
	@ini_set('user_agent', 'smscoin_key_cron');

	$wpurl = get_bloginfo('wpurl');
	$key_id = intval(get_option('smscoin_rkey_key_id'));

	$response = file_get_contents("http://service.smscoin.com/language/".get_option('smscoin_rkey_language')."/json/key/".$key_id."/");
	if(preg_match('|(JSONResponse = \[.*\])|is', $response, $feed) > 0) {
		if ($response !== false) {
			$filename = dirname(__FILE__).'/data/local.js';
			if (($hnd = @fopen($filename, 'w')) !== false) {
				if (@fwrite($hnd, $response) !== false) {
					$LastAction .= ' - Success, file updated @ '.date("r");
					$last_update = date("r");
					update_option('smscoin_rkey_last_update_net', trim($last_update) );
				}
				fclose($hnd);
			}
		}
	}
}

###
#  Show the tarifs scale in admin panel
###
function smscoin_rkey_tariffs() {
	echo smscoin_rkey_add_script();
	$wpurl = get_bloginfo('wpurl');
	$key_id = intval(get_option('smscoin_rkey_key_id'));
	$last_update = get_option('smscoin_rkey_last_update_net');
	if($key_id > 200000) {
		if ( isset($_POST['submit']) ) {
			if( isset($_POST['action']) && $_POST['action'] === 'up') {
				@ini_set('user_agent', 'smscoin_key_cron');
				$response = file_get_contents("http://service.smscoin.com/language/".get_option('smscoin_rkey_language')."/json/key/".$key_id."/");
				$LastAction .= 'From : <a onclick="window.open(this.href); return false;" href="http://service.smscoin.com/language/'.get_option('smscoin_rkey_language').'/json/key/'.$key_id.'/">http://service.smscoin.com/language/'.get_option('smscoin_rkey_language').'/json/key/'.$key_id.'/</a> ';
				if ($response !== false) {
					$filename = dirname(__FILE__).'/data/local.js';
					if (($hnd = @fopen($filename, 'w')) !== false) {
						if (@fwrite($hnd, $response) !== false) {
							$LastAction .= ' - Success, file updated @ '.date("r");
							$last_update = date("r");
							update_option('smscoin_rkey_last_update_net', trim($last_update) );
						} else {
							$LastAction = 'File "'.$filename.'" not writeable!';
						}
						fclose($hnd);
					} else {
						$LastAction = 'Could not open file';
					}
				} else {
					$LastAction = 'Unable to connect to remote server';
				}
				$page = '';
			}
		}

		$page .=  '<h2>'.__('Your local tariff scale','smscoin_rkey').'</h2>'.smscoin_rkey_instruction($key_id);
		$page .= '<h2>'.__('Update your local tariff scale','smscoin_rkey').'</h2>';
		$page .= '
			'.__('Last update: ','smscoin_rkey').' '.$last_update.'
			<form action="admin.php?page=Tarifs" method="post" id="smscoin_rkey-conf" style="text-align: left ; margin: left; width: 50em; ">
				<input type="hidden" name="action" value="up" />
				<p class="submit"><input type="submit" name="submit" value="'.__('Update now: ','smscoin_rkey').'" /></p>
			</form>';

	} else {
		$LastAction = '<h3>'.__('First you need to configure the module!','smscoin_rkey').'</h3> ';
		$LastAction .= '<a href="admin.php?page=Settings">'.__('Settings','smscoin_rkey').'</a>';
		$page_mes .= '<a href="admin.php?page=Settings">'.__('Settings','smscoin_rkey').'</a>';
	}

	if(!empty($LastAction)) {
		echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$LastAction.'</p></div>';
	}
	echo $page;
}


###
#  Main plugin settings page
###
function smscoin_rkey_settings_page() {
	$languages = array("russian", "belarusian", "english", "estonian", "french", "german", "hebrew", "latvian", "lithuanian", "romanian", "spanish", "ukrainian");
	$str = '<h2>'.__('Module Settings','smscoin_rkey').' SmsCoin R-key</h2>';
	if ( isset($_POST['submit']) ) {
		check_admin_referer();
		update_option('smscoin_rkey_key_id', intval(trim($_POST['key_id'])));
		update_option('smscoin_rkey_language', trim($_POST['language']));
		update_option('smscoin_rkey_s_enc', trim($_POST['s_enc']));
		update_option('smscoin_rkey_s_secret', trim($_POST['s_secret']));
		if( trim($_POST['s_tag']) != '') {
			update_option('smscoin_rkey_s_tag', trim($_POST['s_tag']));
		} else {
			update_option('smscoin_rkey_s_tag', 'rkey');
		}
		if (trim($_POST['key_id']) === "") {
			$mess='<h3>'.__('Wrong sms:key ID','smscoin_rkey').'</h3>';
		} else {
			$mess='<h3>'.__('Settings saved','smscoin_rkey').'</h3>';
		}
		$LastAction = $mess;
	}

	if(!empty($LastAction)) {
		$str .= '<!-- Last Action --><div id="message" class="updated fade"><p>'.$LastAction.'</p></div>';
	}
	echo $str;
?>

	<div class="wrap">
		<fieldset class="options">
			<legend><h2>SmsCoin - sms:key, <?php _e('Settings','smscoin_rkey') ?></h2></legend>
			<p><?php _e('For using this module you have to be' ,'smscoin_rkey') ?> <a href="http://smscoin.com/account/register/" onclick="this.target = '_blank';"><b><?php _e('registered' ,'smscoin_rkey') ?></b></a><?php _e(' at smscoin.net .' ,'smscoin_rkey') ?></p>
			<?php _e('<p>The sms:key is, from the implementational point of view, just a way of restricting user\'s ability to visit certain web-resources. In order to allow a user to review the restricted content, individual access passwords are generated; each one of these passwords can have a time and/or visit count limit, up to you. The access for the certain password is denied when the time is up OR when the visit count limit is hit, whichever comes first. Be careful while adjusting the options thought: note that when you change your sms:key options, only those users that signed up after the change are affected.</p>' ,'smscoin_rkey') ?>
			<p><?php _e('For more information about this service:') ?> <a href="http://smscoin.com/info/smskey-tech/" onclick="this.target = '_blank';" >SmsCoin - sms:key.</a></p>
			<?php _e('<p><b>How does it work ?</b><br /> Add to content of the page or post, 2 tags and beetween hidden text, exampel: [rkey] hidden text [/rkey].</p>','smscoin_rkey')?>
			<p><hr /></p>
			<form action="admin.php?page=Settings" method="post" id="smscoin_rkey-conf" style="text-align: left ; margin: left; width: 50em; ">
				<p><?php _e('Enter ID of you\'r sms:key:' , 'smscoin_rkey')?> <a href="http://smscoin.com/keys/add/" onclick="this.target = '_blank';"><?php _e('get sms:key','smscoin_rkey') ?></a></p>
				<p><input id="key_id" name="key_id" type="text" size="12" maxlength="6" style="font-family: 'Courier New', monospace; font-size: 1.5em;" value="<?php echo get_option('smscoin_rkey_key_id'); ?>" />
				<?php
					$select_txt = '<p>'.__('Select Country/Providers list language','smscoin_rkey').'</p>
					<select id="language" name="language" type="text"  style="font-family: \'Courier New\', monospace; font-size: 1.5em;">';
					$langs = $languages;
					foreach ($langs as $lang) {
						$select_txt .= '<option value="'.$lang.'"'.(($lang === get_option('smscoin_rkey_language') )?' selected="selected"':'').'>'.$lang.'</option>';
					}
					echo $select_txt.'</select>';
				 ?>

				<p><?php echo __('Input charset of you\'r site (defaul value UTF-8):','smscoin_rkey'); ?></p>
				<p><input id="s_enc" name="s_enc" type="text" size="12" style="font-family: 'Courier New', monospace; font-size: 1.5em;" <?php echo (get_option('smscoin_rkey_s_enc') == "" ? ' value="UTF-8" ' : ' value="'. get_option('smscoin_rkey_s_enc') .'" ')?>  />
				<p><?php echo __('Enter Secret code from settings of sms:key:','smscoin_rkey'); ?></p>
				<p><input id="s_secret" name="s_secret" type="text" size="12" style="font-family: 'Courier New', monospace; font-size: 1.5em;" <?php echo (get_option('smscoin_rkey_s_secret') == "" ? ' value="" ' : ' value="'. get_option('smscoin_rkey_s_secret') .'" ')?>  />
				<p><?php echo __('Enter the Tag name for hide the content:','smscoin_rkey'); ?></p>
				<p><input id="s_tag" name="s_tag" type="text" size="12" style="font-family: 'Courier New', monospace; font-size: 1.5em;" <?php echo (get_option('smscoin_rkey_s_tag') == "" ? ' value="rkey" ' : ' value="'. get_option('smscoin_rkey_s_tag') .'" ')?>  />
				
				</p>


				<p class="submit"><input type="submit" name="submit" value="<?php echo __('Save Settings','smscoin_rkey'); ?> &raquo;" /></p>
			</form>
		</fieldset>
	</div>
	<?php
}

###
#  Filter tags from the post/page content, replaicing hidden content to instruction
###
function smscoin_rkey_post_filter($content) {

	$tag_name_start = get_option('smscoin_rkey_s_tag');
	$tag_name_end = $tag_name_start;
	$language = get_option('smscoin_rkey_language');
	$key_id = get_option('smscoin_rkey_key_id');
	$s_enc = get_option('smscoin_rkey_s_enc');
	$smscoin_rkey_last_update_net = get_option('smscoin_rkey_last_update_net');
	$response = '';
	$flag = 0;
	# Search the tags
	if (preg_match('/\\['.$tag_name_start.'\\](.*?)\\[\\/'.$tag_name_end.'\\]/is', $content, $matches)) {
		################################################################################
		### SMS:Key v1.0.6 ###
		if (intval($key_id) > 200000) {
			if($language == "") {
				$language = "russian";
			}
			if($s_enc == "") {
				$s_enc="UTF-8";
			}

			if (isset($_GET['s_pair']) && $_GET['s_pair'] !='' && strlen($_GET['s_pair'])<=10) {
				$flag = do_key_local_check ($key_id, $_GET['s_pair']);
			}

			if ($flag != 1) {
				$response .= '
				 	<div><a onclick="var xx = this.parentNode.getElementsByTagName(\'div\')[0]; if (xx) { if (xx.style.display == \'none\') { hideAll(); xx.style.display = \'\'; } else { xx.style.display = \'none\'; } } return false;" href="#">'.__('Click here to get access', 'smscoin_rkey').'</a>
				 	<div class="div_sms" style="display: none">
				'.__('<h3>If you have already received the password, enter it here:</h3>','smscoin_rkey').'<br />';
				$array_qs = array();
				parse_str($_SERVER["QUERY_STRING"], $array_qs);
				$response .= '
				<form action="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'" method="get">
					<div>
						<input name="s_pair" type="text" value="" />';
						foreach($array_qs as $key=>$val) {
							if($key != "s_pair") {
								$response .= '<input name="'.$key.'" type="hidden" value="'.$val.'" />';
							}
						}
						$response .= '
						<input type="submit" value="'.__('Continue','smscoin_rkey').'" />
					</div>
				</form>';

				$response .= __('<h3>To receive your password please send an sms</h3>','smscoin_rkey'). smscoin_rkey_add_script().''.smscoin_rkey_instruction($key_id).'</div></div>';
				$rpl_hidd = $response;
			} else {
				$rpl_hidd = $matches[1];
			}
		} else {
			$rpl_hidd = '<div style="text-align: left ;">'.__('Hidden text','smscoin_rkey').'</div>';
		}
		# Output modified content
		$content = preg_replace('/\\['.$tag_name_start.'\\].*?\\[\\/'.$tag_name_end.'\\]/is', $rpl_hidd, $content);
		### SMS:Key end ###
		################################################################################
	}
	return $content;
}

###
#  Validaiting inserted password
#
###
function do_key_local_check ($key, $pair) {
	global $wpdb, $table_prefix;
	$table_name = $table_prefix . 'skeys';

	$do_die = 0;
	if (isset($pair) && $pair !='' && strlen($pair)<=10) {

		# Query to DB
		$result = $wpdb->get_row("SELECT * FROM $table_name
			WHERE k_status='1'
				AND k_pair='".addslashes($pair)."'
				AND k_key='".intval($key)."'",ARRAY_A);
			$data = $result;
			if ($data && $data['k_first_access'] == '0') {
				# First password activation
				$wpdb->query("UPDATE $table_name
					SET k_first_access='".time()."', k_first_ip='".addslashes($_SERVER["REMOTE_ADDR"])."',
						k_first_from='".addslashes($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"])."'".($data['k_limit_current'] > 0 ? ", k_limit_current=k_limit_current-1" : "")."
					WHERE k_pair='".addslashes($pair)."' AND k_key='".intval($key)."'");
				$do_die = 1;
			} elseif ($data && $data['k_timeout'] == 0 || ($data['k_first_access']+$data['k_timeout']*60)>time()) {
				if ($data['k_limit_start'] > 0) {
					if ($data['k_limit_current'] > 0) {
						# Anoser activations
						$wpdb->query("UPDATE $table_name SET k_limit_current=k_limit_current-1
							WHERE k_pair='".addslashes($pair)."'
								AND k_key='".intval($key)."' AND k_limit_current>0");
						$do_die = 1;
					}
				} else {
					$do_die = 1;
				}
			}

	}
	return $do_die;
}

?>

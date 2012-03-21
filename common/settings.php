<?php

$GLOBALS['colour_schemes'] = array(
	1 => 'ಕಿತ್ತಳೆ|b50,ddd,111,555,fff,eee,ffa,dd9,e81,c40,fff',
	2 => 'ನೀಲಿ|138,ddd,111,555,fff,eee,ffa,dd9,138,fff,fff',
	3 => 'ರೇಶಿಮೆ ಹಸಿರು|293C03,ccc,000,555,fff,eee,CCE691,ACC671,495C23,919C35,fff',
	4 => 'ಕೆನ್ನೀಲಿ|d5d,000,ddd,999,222,111,202,101,909,222,000,000',
	5 => 'ಕೆಂಪು|d12,ddd,111,911,fff,eee,ffa,dd9,c12,fff,fff',
);

menu_register(array(
	'settings' => array(
		'callback' => 'settings_page',
	),
	'reset' => array(
		'hidden' => true,
		'callback' => 'cookie_monster',
	),
));

function cookie_monster() {
	$cookies = array(
		'browser',
		'settings',
		'utc_offset',
		'search_favourite',
		'USER_AUTH',
	);
	$duration = time() - 3600;
	foreach ($cookies as $cookie) {
		setcookie($cookie, NULL, $duration, '/');
		setcookie($cookie, NULL, $duration);
	}
	return theme('page', 'Cookie Monster', '<p>Cookie monster ನಿಮ್ಮನ್ನು ಲಾಗ್ ಔಟ್ ಮಾಡಿದೆ ಮತ್ತು ಎಲ್ಲಾ ಸೆಟ್ಟಿಂಗ್‌ಗಳನ್ನು ಅಳಿಸಿದೆ. ಮತ್ತೊಮ್ಮೆ ಲಾಗಿನ್ ಆಗಲು ಪ್ರಯತ್ನಿಸಿ.</p>');
}

function setting_fetch($setting, $default = NULL) {
	$settings = (array) unserialize(base64_decode($_COOKIE['settings']));
	if (array_key_exists($setting, $settings)) {
		return $settings[$setting];
	} else {
		return $default;
	}
}

function setcookie_year($name, $value) {
	$duration = time() + (3600 * 24 * 365);
	setcookie($name, $value, $duration, '/');
}

function settings_page($args) {
	if ($args[1] == 'save') {
		$settings['browser']     = $_POST['browser'];
		$settings['gwt']         = $_POST['gwt'];
		$settings['colours']     = $_POST['colours'];
		$settings['reverse']     = $_POST['reverse'];
		$settings['timestamp']   = $_POST['timestamp'];
		$settings['hide_inline'] = $_POST['hide_inline'];
		$settings['utc_offset']  = (float)$_POST['utc_offset'];
		
		// Save a user's oauth details to a MySQL table
		if (MYSQL_USERS == 'ON' && $newpass = $_POST['newpassword']) {
			user_is_authenticated();
			list($key, $secret) = explode('|', $GLOBALS['user']['password']);
			$sql = sprintf("REPLACE INTO user (username, oauth_key, oauth_secret, password) VALUES ('%s', '%s', '%s', MD5('%s'))",  mysql_escape_string(user_current_username()), mysql_escape_string($key), mysql_escape_string($secret), mysql_escape_string($newpass));
			mysql_query($sql);
		}
		
		setcookie_year('settings', base64_encode(serialize($settings)));
		twitter_refresh('');
	}

	$modes = array(
		'mobile' => 'ಸಾಮಾನ್ಯ ಫೋನ್',
		'touch' => 'ಸ್ಪರ್ಷ ಸಂವೇದಿ ಫೋನ್',
		'desktop' => 'ಗಣಕಯಂತ್ರ/ಲ್ಯಾಪ್ಟಾಪ್',
		'text' => 'ಅಕ್ಷರಗಳು ಮಾತ್ರ',
		'worksafe' => 'Work Safe',
		'bigtouch' => 'ದೊಡ್ಡ ಸ್ಪರ್ಷ ಸಂವೇದಿ ತೆರೆ',
	);

	$gwt = array(
		'off' => 'ನೇರವಾಗಿ',
		'on' => 'GWT ಮೂಲಕ',
	);

	$colour_schemes = array();
	foreach ($GLOBALS['colour_schemes'] as $id => $info) {
		list($name, $colours) = explode('|', $info);
		$colour_schemes[$id] = $name;
	}
	
	$utc_offset = setting_fetch('utc_offset', 0);
/* returning 401 as it calls http://api.twitter.com/1/users/show.json?screen_name= (no username???)	
	if (!$utc_offset) {
		$user = twitter_user_info();
		$utc_offset = $user->utc_offset;
	}
*/
	if ($utc_offset > 0) {
		$utc_offset = '+' . $utc_offset;
	}

	$content .= '<form action="settings/save" method="post"><p>ಬಣ್ಣಗಳ ಆಯ್ಕೆ:<br /><select name="colours">';
	$content .= theme('options', $colour_schemes, setting_fetch('colours', 5));
	$content .= '</select></p><p>Mode:<br /><select name="browser">';
	$content .= theme('options', $modes, $GLOBALS['current_theme']);
	$content .= '</select></p><p>ಬಾಹ್ಯ ಸಂಪರ್ಕಗಳು:<br /><select name="gwt">';
	$content .= theme('options', $gwt, setting_fetch('gwt', $GLOBALS['current_theme'] == 'text' ? 'on' : 'off'));
	$content .= '</select><small><br /><font color="#990000">Google Web Transcoder (GWT) ಮೂರನೇ ವ್ಯಕ್ತಿಯ ಸೈಟ್‌ಗಳನ್ನು ಸೂಕ್ತವಾದ ಸಣ್ಣ ಹಾಗೂ ವೇಗವಾಗಿ ಲೋಡ್ ಆಗಬಲ್ಲ ಪುಟಗಳನ್ನಾಗಿ ಪರಿವರ್ತಿಸುತ್ತದೆ. ಇದು ಹಳೆಯ ಫೋನ್ ಬಳಕೆದಾರರಿಗೆ ಹಾಗೂ ಕಡಿಮೆ ಬ್ಯಾಂಡ್ವಿಡ್ತ್ ಹೊಂದಿರುವವರಿಗೆ ಅನುಕೂಲಕರ.</font></small></p>';
	$content .= '<p><label><input type="checkbox" name="reverse" value="yes" '. (setting_fetch('reverse') == 'yes' ? ' checked="checked" ' : '') .' /> ಸಂಭಾಷಣೆಯ ಥ್ರೆಡ್ ನೋಟವನ್ನು ಹಿನ್ನೋಟವಾಗಿ ಬದಲಿಸಲು ಪ್ರಯತ್ನಿಸು.</label></p>';
	$content .= '<p><label><input type="checkbox" name="timestamp" value="yes" '. (setting_fetch('timestamp') == 'yes' ? ' checked="checked" ' : '') .' /> 25 ಕ್ಷಣದ ಹಿಂದೆ ಎಂಬುದರ ಬದಲಾಗಿ ' . twitter_date('H:i') . ' ಎಂದು ತೋರಿಸು.</label></p>';
	$content .= '<p><label><input type="checkbox" name="hide_inline" value="yes" '. (setting_fetch('hide_inline') == 'yes' ? ' checked="checked" ' : '') .' /> ಒಳಗಿನ ಮಾಧ್ಯಮವನ್ನು ಅಡಗಿಸು (ಉದಾಹರಣೆಗೆ TwitPicನ ಚಿಕ್ಕ ಚಿತ್ರಗಳು)</label></p>';
	$content .= '<p><label>UTCಯಲ್ಲಿ ಈಗ ಸಮಯ ' . gmdate('H:i') . ', <input type="text" name="utc_offset" value="'. $utc_offset .'" size="3" /> ಬಳಸುವುದರ ಮೂಲಕ ನಾವು ಸಮಯವನ್ನು ' . twitter_date('H:i') . ' ಎಂದು ತೋರಿಸುತ್ತೇವೆ.<br />ನಿಮಗೆ ಸಮಯವು ತಪ್ಪಾಗಿದೆ ಎಂದು ಕಂಡುಬಂದರೆ ಇದನ್ನು ಬದಲಿಸುವುದು ಒಳ್ಳೆಯದು. (ಭಾರತಕ್ಕೆ +5.5 ಬಳಸಿ)</label></p>';

	
	// Allow users to choose a Dabr password if accounts are enabled
	if (MYSQL_USERS == 'ON' && user_is_authenticated()) {
		$content .= '<fieldset><legend>ಕನ್ನಡ ಟ್ವಿಟರ್ ಖಾತೆ</legend><small><font color="#990000">ನೀವು Twitter.comಗೆ ಹೋಗದೇ "ಕನ್ನಡ ಟ್ವಿಟರ್"ನ್ನು ಬಳಸಬಹುದು. ಅದಕ್ಕಾಗಿ ಇಲ್ಲಿ ಒಂದು ಗುಪ್ತಪದವನ್ನು ನೀಡಿ. ಮುಂದಿನ ಸಲದಿಂದ ಇದನ್ನು ಬಳಸಿಕೊಂಡು ನೀವು "ಕನ್ನಡ ಟ್ವಿಟರ್"ಗೆ ಲಾಗಿನ್ ಆಗಬಹುದು.</font></small></p><p>"ಕನ್ನಡ ಟ್ವಿಟರ್" ಗುಪ್ತಪದವನ್ನು ಬದಲಾಯಿಸಿ<br /><input type="password" name="newpassword" /><br /><small><font color="#990000">ಗುಪ್ತಪದವನ್ನು ಬದಲಿಸುವುದು ಬೇಡವಾದರೆ ಇದನ್ನು ಹಾಗೆಯೇ ಬಿಡಿ.</font></small></fieldset>';
	}
	
	$content .= '<p><input type="submit" value="ಆಯ್ಕೆಯನ್ನು ಉಳಿಸಿ" /></p></form>';

	$content .= '<hr /><p>ನಿಮ್ಮ ಆಯ್ಕೆಗಳು ತೀರಾ ಹಾಳಾಗಿದೆ ಎಂದು ಕಂಡುಬಂದರೆ <a href="reset">ರೀಸೆಟ್ ಪುಟ</a>ಕ್ಕೆ ಭೇಟಿ ನೀಡಿ. ಇದು ನಿಮ್ಮನ್ನು ಲಾಗ್ ಔಟ್ ಮಾಡುತ್ತದೆ ಹಾಗೂ ಎಲ್ಲಾ ಆಯ್ಕೆಗಳನ್ನು ಅಳಿಸಿಹಾಕುತ್ತದೆ.</p>';

	return theme('page', 'Settings', $content);
}
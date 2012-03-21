<?php

menu_register(array(
	'oauth' => array(
		'callback' => 'user_oauth',
		'hidden' => 'true',
	),
	'login' => array(
		'callback' => 'user_login',
		'hidden' => 'true',
	),
));

function user_oauth() {
	require_once 'OAuth.php';

	// Session used to keep track of secret token during authorisation step
	session_start();

	// Flag forces twitter_process() to use OAuth signing
	$GLOBALS['user']['type'] = 'oauth';

	if ($oauth_token = $_GET['oauth_token']) {
		// Generate ACCESS token request
		$params = array('oauth_verifier' => $_GET['oauth_verifier']);
		$response = twitter_process('https://api.twitter.com/oauth/access_token', $params);
		parse_str($response, $token);

		// Store ACCESS tokens in COOKIE
		$GLOBALS['user']['password'] = $token['oauth_token'] .'|'.$token['oauth_token_secret'];

		// Fetch the user's screen name with a quick API call
		unset($_SESSION['oauth_request_token_secret']);
		$user = twitter_process('https://api.twitter.com/account/verify_credentials.json');
		$GLOBALS['user']['username'] = $user->screen_name;

		_user_save_cookie(1);
		header('Location: '. BASE_URL);
		exit();

	} else {
		// Generate AUTH token request
		$params = array('oauth_callback' => BASE_URL.'oauth');
		$response = twitter_process('https://api.twitter.com/oauth/request_token', $params);
		parse_str($response, $token);

		// Save secret token to session to validate the result that comes back from Twitter
		$_SESSION['oauth_request_token_secret'] = $token['oauth_token_secret'];

		// redirect user to authorisation URL
		$authorise_url = 'https://api.twitter.com/oauth/authorize?oauth_token='.$token['oauth_token'];
		header("Location: $authorise_url");
	}
}

function user_oauth_sign(&$url, &$args = false) {
	require_once 'OAuth.php';

	$method = $args !== false ? 'POST' : 'GET';

	// Move GET parameters out of $url and into $args
	if (preg_match_all('#[?&]([^=]+)=([^&]+)#', $url, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$args[$match[1]] = $match[2];
		}
		$url = substr($url, 0, strpos($url, '?'));
	}

	$sig_method = new OAuthSignatureMethod_HMAC_SHA1();
	$consumer = new OAuthConsumer(OAUTH_CONSUMER_KEY, OAUTH_CONSUMER_SECRET);
	$token = NULL;

	if (($oauth_token = $_GET['oauth_token']) && $_SESSION['oauth_request_token_secret']) {
		$oauth_token_secret = $_SESSION['oauth_request_token_secret'];
	} else {
		list($oauth_token, $oauth_token_secret) = explode('|', $GLOBALS['user']['password']);
	}
	if ($oauth_token && $oauth_token_secret) {
		$token = new OAuthConsumer($oauth_token, $oauth_token_secret);
	}

	$request = OAuthRequest::from_consumer_and_token($consumer, $token, $method, $url, $args);
	$request->sign_request($sig_method, $consumer, $token);

	switch ($method) {
		case 'GET':
			$url = $request->to_url();
			$args = false;
			return;
		case 'POST':
			$url = $request->get_normalized_http_url();
			$args = $request->to_postdata();
			return;
	}
}

function user_ensure_authenticated() {
	if (!user_is_authenticated()) {
		$content = theme('login');
		$content .= file_get_contents('about.html');
		theme('page', 'Login', $content);
	}
}

function user_logout() {
	unset($GLOBALS['user']);
	setcookie('USER_AUTH', '', time() - 3600, '/');
}

function user_is_authenticated() {
	if (!isset($GLOBALS['user'])) {
		if(array_key_exists('USER_AUTH', $_COOKIE)) {
			_user_decrypt_cookie($_COOKIE['USER_AUTH']);
		} else {
			$GLOBALS['user'] = array();
		}
	}
	
	// Auto-logout any users that aren't correctly using OAuth
	if (user_current_username() && user_type() !== 'oauth') {
		user_logout();
		twitter_refresh('logout');
	}

	if (!user_current_username()) {
		if ($_POST['username'] && $_POST['password']) {
			$GLOBALS['user']['username'] = trim($_POST['username']);
			$GLOBALS['user']['password'] = $_POST['password'];
			$GLOBALS['user']['type'] = 'oauth';
			
			$sql = sprintf("SELECT * FROM user WHERE username='%s' AND password=MD5('%s') LIMIT 1", mysql_escape_string($GLOBALS['user']['username']), mysql_escape_string($GLOBALS['user']['password']));
			$rs = mysql_query($sql);
			if ($rs && $user = mysql_fetch_object($rs)) {
				$GLOBALS['user']['password'] = $user->oauth_key . '|' . $user->oauth_secret;
			} else {
				theme('error', 'ಯೂಸರ್ ನೇಮ್ ಅಥವಾ ಗುಪ್ತಪದ ತಪ್ಪಾಗಿದೆ.');
			}
			
			_user_save_cookie($_POST['stay-logged-in'] == 'yes');
			header('Location: '. BASE_URL);
			exit();
		} else {
			return false;
		}
	}
	return true;
}

function user_current_username() {
	return $GLOBALS['user']['username'];
}

function user_is_current_user($username) {
	return (strcasecmp($username, user_current_username()) == 0);
}

function user_type() {
	return $GLOBALS['user']['type'];
}

function _user_save_cookie($stay_logged_in = 0) {
	$cookie = _user_encrypt_cookie();
	$duration = 0;
	if ($stay_logged_in) {
		$duration = time() + (3600 * 24 * 365);
	}
	setcookie('USER_AUTH', $cookie, $duration, '/');
}

function _user_encryption_key() {
	return ENCRYPTION_KEY;
}

function _user_encrypt_cookie() {
	$plain_text = $GLOBALS['user']['username'] . ':' . $GLOBALS['user']['password'] . ':' . $GLOBALS['user']['type'];

	$td = mcrypt_module_open('blowfish', '', 'cfb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	mcrypt_generic_init($td, _user_encryption_key(), $iv);
	$crypt_text = mcrypt_generic($td, $plain_text);
	mcrypt_generic_deinit($td);
	return base64_encode($iv.$crypt_text);
}

function _user_decrypt_cookie($crypt_text) {
	$crypt_text = base64_decode($crypt_text);
	$td = mcrypt_module_open('blowfish', '', 'cfb', '');
	$ivsize = mcrypt_enc_get_iv_size($td);
	$iv = substr($crypt_text, 0, $ivsize);
	$crypt_text = substr($crypt_text, $ivsize);
	mcrypt_generic_init($td, _user_encryption_key(), $iv);
	$plain_text = mdecrypt_generic($td, $crypt_text);
	mcrypt_generic_deinit($td);

	list($GLOBALS['user']['username'], $GLOBALS['user']['password'], $GLOBALS['user']['type']) = explode(':', $plain_text);
}

function user_login() {
	return theme('page', 'Login','
<form method="post" action="'.$_GET['q'].'">
<p>ಬಳಕೆದಾರರ ಹೆಸರು <input name="username" size="15" />
<br />ಗುಪ್ತಪದ <input name="password" type="password" size="15" />
<br /><label><input type="checkbox" checked="checked" value="yes" name="stay-logged-in" /> ಲಾಗಿನ್ ಆಗಿಯೇ ಇರಬೇಕೇ? </label>
<br /><input type="submit" value="ಸೈನ್-ಇನ್ ಆಗಿ" /></p>
</form>

<p><b>ನೋಂದಣಿ ಹಂತಗಳು:</b></p>

<ol>
	<li>ಯಾವುದೇ ಕಂಪ್ಯೂಟರ್ ಮೂಲಕ <a href="oauth">Twitter.com</a>ಗೆ  ಸೈನ್-ಇನ್ ಆಗಿ</li>
	<li>ಒಂದು ಗುಪ್ತಪದವನ್ನು ಆಯ್ಕೆ ಮಾಡಲು ಕನ್ನಡ ಟ್ವಿಟರಿನ <a href="settings">ಜೋಡಣೆ ಪುಟಕ್ಕೆ</a> ಭೇಟಿ ನೀಡಿ</li>
	<li>ಅಷ್ಟೆ! ಈಗ ನೀವು ಟ್ವಿಟರನ್ನು "ಕನ್ನಡ ಟ್ವಿಟರಿನ" ಮೂಲಕ ಎಲ್ಲಿಂದ ಬೇಕಾದರೂ ಉಪಯೋಗಿಸಬಹುದು. (twitter.com ಬ್ಲಾಕ್ ಆಗಿರುವಲ್ಲಿಯೂ ಇದನ್ನು ಬಳಸಬಹುದು)</li>
</ol>
');
}

function theme_login() {
	$content = '<div style="margin:1em; font-size: 1.2em">
<p><a href="oauth"><img src="images/twitter_button_2_lo.gif" alt="Sign in with Twitter/OAuth" width="165" height="28" /></a><br /><a href="oauth">Twitter.com ಮೂಲಕ ಸೈನ್-ಇನ್ ಆಗಿ</a></p>
<p>ನಿಮ್ಮ ಯೂಸರ್ ನೇಮ್ ಮತ್ತು ಗುಪ್ತಪದದ ಮೂಲಕ ನೇರವಾಗಿ ಲಾಗಿನ್ ಆಗಲು ಟ್ವಿಟರ್ ಅನುಮತಿ ನೀಡುವುದಿಲ್ಲ. ಹಾಗಾಗಿ ನಾವು ಇಲ್ಲಿ ಸಾಮಾನ್ಯ ಲಾಗಿನ್ ಫಾರಂ ಅನ್ನು ತೋರಿಸುವುದಿಲ್ಲ.</p>';
	
	if (MYSQL_USERS == 'ON') $content .= '<p>Twitter.comಗೆ access ಇಲ್ಲವೇ? ಹಾಗಾದರೆ <a href="login">ಕನ್ನಡ ಟ್ವಿಟರ್ ಖಾತೆಯ ಮೂಲಕ ಲಾಗಿನ್ ಆಗಿ.</a></p>';
	$content .= '</div>';
	return $content;
}

function theme_logged_out() {
	return '<p>Logged out. <a href="">ಮತ್ತೊಮ್ಮೆ ಲಾಗಿನ್ ಆಗಿ</a></p>';
}

?>
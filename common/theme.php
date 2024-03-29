<?php
require_once ("common/advert.php");

$current_theme = false;

function theme() {
	global $current_theme;
	$args = func_get_args();
	$function = array_shift($args);
	$function = 'theme_'.$function;

	if ($current_theme) {
		$custom_function = $current_theme.'_'.$function;
		if (function_exists($custom_function))
		$function = $custom_function;
	} else {
		if (!function_exists($function))
		return "<p>ದೋಷ: ಥೀಮ್ ಕಾರ್ಯ <b>$function</b> ಕಂಡುಬಂದಿಲ್ಲ.</p>";
	}
	return call_user_func_array($function, $args);
}

function theme_csv($headers, $rows) {
	$out = implode(',', $headers)."\n";
	foreach ($rows as $row) {
		$out .= implode(',', $row)."\n";
	}
	return $out;
}

function theme_list($items, $attributes) {
	if (!is_array($items) || count($items) == 0) {
		return '';
	}
	$output = '<ul'.theme_attributes($attributes).'>';
	foreach ($items as $item) {
		$output .= "<li>$item</li>\n";
	}
	$output .= "</ul>\n";
	return $output;
}

function theme_options($options, $selected = NULL) {
	if (count($options) == 0) return '';
	$output = '';
	foreach($options as $value => $name) {
		if (is_array($name)) {
			$output .= '<optgroup label="'.$value.'">';
			$output .= theme('options', $name, $selected);
			$output .= '</optgroup>';
		} else {
			$output .= '<option value="'.$value.'"'.($selected == $value ? ' selected="selected"' : '').'>'.$name."</option>\n";
		}
	}
	return $output;
}

function theme_info($info) {
	$rows = array();
	foreach ($info as $name => $value) {
		$rows[] = array($name, $value);
	}
	return theme('table', array(), $rows);
}

function theme_table($headers, $rows, $attributes = NULL) {
	$out = '<div'.theme_attributes($attributes).'>';
	if (count($headers) > 0) {
		$out .= '<thead><tr>';
		foreach ($headers as $cell) {
			$out .= theme_table_cell($cell, TRUE);
		}
		$out .= '</tr></thead>';
	}
	if (count($rows) > 0) {
		$out .= theme('table_rows', $rows);
	}
	$out .= '</div>';
	return $out;
}

function theme_table_rows($rows) {
	$i = 0;
	foreach ($rows as $row) {
		if ($row['data']) {
			$cells = $row['data'];
			unset($row['data']);
			$attributes = $row;
		} else {
			$cells = $row;
			$attributes = FALSE;
		}
		$attributes['class'] .= ($attributes['class'] ? ' ' : '') . ($i++ %2 ? 'even' : 'odd');
		$out .= '<div'.theme_attributes($attributes).'>';
		foreach ($cells as $cell) {
			$out .= theme_table_cell($cell);
		}
		$out .= "</div>\n";
	}
	return $out;
}

function theme_attributes($attributes) {
	if (!$attributes) return;
	foreach ($attributes as $name => $value) {
		$out .= " $name=\"$value\"";
	}
	return $out;
}

function theme_table_cell($contents, $header = FALSE) {
	$celltype = $header ? 'th' : 'td';
	if (is_array($contents)) {
		$value = $contents['data'];
		unset($contents['data']);
		$attributes = $contents;
	} else {
		$value = $contents;
		$attributes = false;
	}
	return "<span".theme_attributes($attributes).">$value</span>";
}


function theme_error($message) {
	theme_page('Error', $message);
}

function theme_page($title, $content) {
	$body = theme('menu_top');
	$body .= $content;
	$body .= theme('menu_bottom');
	$body .= theme('google_analytics');
	if (DEBUG_MODE == 'ON') {
		global $dabr_start, $api_time, $services_time;
		$time = microtime(1) - $dabr_start;
		$body .= '<p>'.round($time, 4).' ಕ್ಷಣದಲ್ಲಿ ಸಂಸ್ಕರಣೆ ಮಾಡಲಾಯಿತು ('.round(($time - $api_time - $services_time) / $time * 100).'% ಕನ್ನಡ ಟ್ವಿಟರ್, '.round($api_time / $time * 100).'% ಟ್ವಿಟರ್, '.round($services_time / $time * 100).'% ಬೇರೆ ಸೇವೆಗಳು)</p>';
	}
	if ($title == 'Login') {
		$title = 'ಕನ್ನಡ ಟ್ವಿಟರ್ ಲಾಗಿನ್';
		$meta = '<meta name="description" content="ಟ್ವಿಟರನ್ನು ಕನ್ನಡದಲ್ಲಿ ಉಪಯೋಗಿಸಬಹುದಾದ ತಂತ್ರಾಂಶ. ಇದರಲ್ಲಿ ಎಲ್ಲಾ ಆಯ್ಕೆಗಳು ಕನ್ನಡದಲ್ಲಿರುತ್ತದೆ." />';
	}
	ob_start('ob_gzhandler');
	header('Content-Type: text/html; charset=utf-8');
	echo	'<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>',$title,' - ಕನ್ನಡ ಟ್ವಿಟರ್</title>
				<base href="',BASE_URL,'" />
				'.$meta.theme('css').'
				<meta name="viewport" content="width=device-width; initial-scale=1.0;" />
			</head>
			<body>';

	echo 	theme('advert');
	echo 	$body;

	echo	'</body>
		</html>';
	exit();
}

function theme_colours() {
	$info = $GLOBALS['colour_schemes'][setting_fetch('colours', 5)];
	list($name, $bits) = explode('|', $info);
	$colours = explode(',', $bits);
	return (object) array(
		'links' => $colours[0],
		'bodybg' => $colours[1],
		'bodyt' => $colours[2],
		'small' => $colours[3],
		'odd' => $colours[4],
		'even' => $colours[5],
		'replyodd' => $colours[6],
		'replyeven' => $colours[7],
		'menubg' => $colours[8],
		'menut' => $colours[9],
		'menua' => $colours[10],
	);
}

function theme_css() {
	$c = theme('colours');
	return "<style type='text/css'>
  a{color:#{$c->links}}
  table{border-collapse:collapse}
  form{margin:.3em;}
  td{vertical-align:top;padding:0.3em}
  img{border:0}
  small,small a{color:#{$c->small}}
  body{background:#{$c->bodybg};
  color:#{$c->bodyt};margin:0;font:90% sans-serif}
  .odd{background:#{$c->odd}}
  .even{background:#{$c->even}}
  .reply{background:#{$c->replyodd}}
  .reply.even{background: #{$c->replyeven}}
  .menu{color:#{$c->menut};background:#{$c->menubg};padding: 2px}
  .menu a{color:#{$c->menua};text-decoration: none}
  .tweet,.features{padding:5px}
  .date{padding:5px;font-size:0.8em;font-weight:bold;color:#{$c->small}}
  .about,.time{font-size:0.75em;color:#{$c->small}}
  .avatar{display:block; height:26px; width:26px; left:0.3em; margin:0; overflow:hidden; position:absolute;}
  .status{display:block;word-wrap:break-word;}
  .shift{margin-left:30px;min-height:24px;}
  .from{font-size:0.75em;color:#{$c->small};font-family:serif;}
  .from a{color:#{$c->small};}
</style>";
}

function theme_google_analytics() {
	global $GA_ACCOUNT;
	if (!$GA_ACCOUNT) return '';
	$googleAnalyticsImageUrl = googleAnalyticsGetImageUrl();
	return "<img src='{$googleAnalyticsImageUrl}' />";
}

?>

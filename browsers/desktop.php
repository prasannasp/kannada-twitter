<?php
function desktop_theme_status_form($text = '', $in_reply_to_id = NULL) {
	if (user_is_authenticated()) {
		$output = '<form method="post" action="update">
  <fieldset><legend><img src="http://si0.twimg.com/images/dev/cms/intents/bird/bird_blue/bird_16_blue.png" width="16" height="16" /> ಏನು ನಡೆಯುತ್ತಿದೆ?</legend>
  <textarea id="status" name="status" rows="3" style="width:95%; max-width: 400px;">'.$text.'</textarea>
  <div><input name="in_reply_to_id" value="'.$in_reply_to_id.'" type="hidden" /><input type="submit" value="ಟ್ವೀಟ್!" /> <span id="remaining">140</span> 
  <span id="geo" style="display: none;"><input onclick="goGeo()" type="checkbox" id="geoloc" name="location" /> <label for="geoloc" id="lblGeo"></label></span></div>
  </fieldset>
  <script type="text/javascript">
started = false;
chkbox = document.getElementById("geoloc");
if (navigator.geolocation) {
	geoStatus("ಸ್ಥಳವನ್ನೂ ನಮೂದಿಸು");
	if ("'.$_COOKIE['geo'].'"=="Y") {
		chkbox.checked = true;
		goGeo();
	}
}
function goGeo(node) {
	if (started) return;
	started = true;
	geoStatus("Locating...");
	navigator.geolocation.getCurrentPosition(geoSuccess, geoStatus);
}
function geoStatus(msg) {
	document.getElementById("geo").style.display = "inline";
	document.getElementById("lblGeo").innerHTML = msg;
}
function geoSuccess(position) {
	geoStatus("Tweet my <a href=\'http://maps.google.co.uk/m?q=" + position.coords.latitude + "," + position.coords.longitude + "\' target=\'blank\'>location</a>");
	chkbox.value = position.coords.latitude + "," + position.coords.longitude;
}
  </script>
</form>';
		$output .= js_counter('status');
		return $output;
	}
}

function desktop_theme_search_form($query) {
	$query = stripslashes(htmlentities($query,ENT_QUOTES,"UTF-8"));
	return "<form action='search' method='get'><input name='query' value=\"$query\" style='width:100%; max-width: 300px' /><input type='submit' value='ಹುಡುಕು' /></form>";
}
?>
<?php
/**
 * Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * In addition, this program is also subject to certain additional terms. You should
 * have received a copy of these additional terms immediately following the terms and
 * conditions of the GNU Affero General Public License which accompanied the program.
 * If not, please request a copy in writing from the Utah Department of Health at
 * the address below.
 * 
 * If you have questions concerning this license or the applicable additional terms,
 * you may contact us in writing at:
 * Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 * 
 * @copyright Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Auth\Authenticator;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Authenticator::userHasPermission(Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script type="text/javascript">
	$(function() {
		$(".addnew_lab").show();
		$("#raw_xml").trigger("focus");
		
		$("#btn_save").button({
            icon: "ui-icon-elrsave"
        });
	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>XML Formatter</h1>

<?php

	if (isset($_POST['raw_xml']) && strlen(trim($_POST['raw_xml'])) > 0) {
	    echo "<div class=\"addnew_lab ui-widget ui-widget-content ui-corner-all\">\n";
		echo "<label class='emsa_form_heading' for='pretty_xml_results'>Formatted XML Output:</label><br>\n";
		echo "<textarea readonly id='pretty_xml_results' class=\"ui-corner-all\" style=\"padding: 5px; font-family: 'Consolas', monospace; font-weight: bold; width: 90%; height: 15em; color: darkred;\">".htmlentities(DisplayUtils::formatXml(trim($_POST['raw_xml'])))."</textarea>\n";
		echo "</div>";
	} else {
		DisplayUtils::drawError("No XML detected.");
	}
?>

<div id="addhl7form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<form id="new_onboard_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
        <label class="emsa_form_heading" for="raw_xml">Add XML String to Format:</label><br>
		<textarea class="ui-corner-all" name="raw_xml" id="raw_xml" style="padding: 5px; width: 90%; height: 15em;"></textarea>
		<br><br><button type="submit" name="btn_save" id="btn_save">Make Pretty</button>
	</form>
</div>

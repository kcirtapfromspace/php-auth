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

use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\UI\Queue\FilterFactory;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script>
	$(function() {
		$(".tooltip").tooltip();
		
		$("#addnew_form").show();
		
		$("#new_savelab").button({
            icon: "ui-icon-elrsave"
        });
		
	});
</script>
<style type="text/css">
	.ui-dialog-content label {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 600;
	}
	.ui-dialog-content select, .ui-dialog-content input, #addnew_form select, #addnew_form input {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 400;
		background-color: lightcyan;
	}
	.ui-dialog-content {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 400;
	}
	.ui-dialog-title {
		font-family: 'Oswald', serif; font-weight: 500; font-size: 1.5em;
		text-shadow: 1px 1px 6px dimgray;
	}
	.ui-dialog-content h3 {
		font-family: 'Oswald', serif; font-weight: 500; font-size: 1.3em;
		color: firebrick;
	}
	.ui-dialog {
		box-shadow: 4px 4px 15px dimgray;
	}
</style>

<?php

	if (isset($_POST['edit_flag']) && filter_var($_POST['edit_flag'], FILTER_VALIDATE_INT) && (intval($_POST['edit_flag']) == 1)) {
		$email_sanitized = array();
		$email_unsafe_arr = (isset($_POST['distribution_list'])) ? preg_split("/[;,]/", trim($_POST['distribution_list'])) : array();
		foreach ($email_unsafe_arr as $email_unsafe) {
			if (filter_var(trim($email_unsafe), FILTER_VALIDATE_EMAIL)) {
				$email_sanitized[] = filter_var(trim($email_unsafe), FILTER_SANITIZE_EMAIL);
			}
		}
		
		$connectors_sanitized = array();
		$connectors_unsafe_arr = (isset($_POST['connectors'])) ? $_POST['connectors'] : array();
		if (is_array($connectors_unsafe_arr) && count($connectors_unsafe_arr)) {
			foreach ($connectors_unsafe_arr as $connectors_unsafe) {
				$connectors_sanitized[] = trim($connectors_unsafe);
			}
		} else {
			$connectors_sanitized = array();
		}
		
		$reporters_sanitized = array();
		$reporters_unsafe_arr = (isset($_POST['reporters'])) ? $_POST['reporters'] : array();
		if (is_array($reporters_unsafe_arr) && count($reporters_unsafe_arr)) {
			foreach ($reporters_unsafe_arr as $reporters_unsafe) {
				$reporters_sanitized[] = intval($reporters_unsafe);
			}
		} else {
			$reporters_sanitized = array(); 
		}
		
		$edit_sql = sprintf("UPDATE %sintake_stats_config 
			SET received_sigma = %f, accepted_sigma = %f, connectors = %s, reporters = %s, distribution_list = %s WHERE id = 1;",
				$emsaDbSchemaPrefix,
				((isset($_POST['received_sigma']) && floatval($_POST['received_sigma']) > 0) ? floatval($_POST['received_sigma']) : 0),
				((isset($_POST['accepted_sigma']) && floatval($_POST['accepted_sigma']) > 0) ? floatval($_POST['accepted_sigma']) : 0),
				((isset($connectors_sanitized) && (count($connectors_sanitized) > 0)) ? "'".pg_escape_string(implode(';', $connectors_sanitized))."'" : "NULL"),
				((isset($reporters_sanitized) && (count($reporters_sanitized) > 0)) ? "'".pg_escape_string(implode(';', $reporters_sanitized))."'" : "NULL"),
				((isset($email_sanitized) && (count($email_sanitized) > 0)) ? "'".pg_escape_string(implode(';', $email_sanitized))."'" : "NULL")
		);

		if (@pg_query($host_pa, $edit_sql)) {
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Settings successfully updated!", "ui-icon-elrsuccess");
		} else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save settings.");
		}
	}
	
	// get current state of config options...
	$current_qry = "SELECT * FROM ".$emsaDbSchemaPrefix."intake_stats_config WHERE id = 1;";
	$current_rs = @pg_query($host_pa, $current_qry);
	if ($current_rs) {
		$current_row = @pg_fetch_object($current_rs);
		$current_received_sigma = floatval($current_row->received_sigma);
		$current_accepted_sigma = floatval($current_row->accepted_sigma);
		$current_connectors = preg_split("/[;,]/", trim($current_row->connectors));
		$current_reporters = preg_split("/[;,]/", trim($current_row->reporters));
		$current_distribution_list = preg_split("/[;,]/", trim($current_row->distribution_list));
	} else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve current Intake Monitoring configuration.");
	}
	@pg_free_result($current_rs);

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>Intake Monitoring Alert Configuration</h1>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Edit Alert Settings:</span><br><br></div>
	<form id="search_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
		
		<label class="vocab_search_form2" for="received_sigma">Received Messages Threshold:</label><input title="If current week's number of received messages is lower than this standard deviation vs. the prior two months' mean, trigger an alert." class="ui-corner-all tooltip" type="text" name="received_sigma" id="received_sigma" value="<?php echo $current_received_sigma; ?>" /> &sigma;<br><br>
		<label class="vocab_search_form2" for="accepted_sigma">Accepted Messages Threshold:</label><input title="If current week's number of accepted messages per CDC disease category is lower than this standard deviation vs. the prior two months' mean, trigger an alert." class="ui-corner-all tooltip" type="text" name="accepted_sigma" id="accepted_sigma" value="<?php echo $current_accepted_sigma; ?>" /> &sigma;<br><br>
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
		<?php
            $sql = "SELECT DISTINCT connector AS value, connector AS label FROM system_original_messages 
                    WHERE connector NOT ILIKE 'ELR%' AND connector <> '2.16.840.1.114222.4.3.2.2.1.232.1' 
                    AND connector NOT ILIKE 'ehars' AND connector NOT ILIKE 'MANUAL%' 
                    ORDER by connector;";
            (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $sql), $current_connectors ?? null))
                ->render("Monitor Received Messages from...", "connectors");
        ?>
		</div>
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
		<?php
            $sql = "SELECT id AS value, ui_name AS label FROM structure_labs 
                    WHERE ui_name <> 'eHARS' AND alias_for = 0 
                    ORDER by ui_name;";
            (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $sql), $current_reporters ?? null))
                ->render("Monitor Accepted Messages from...", "reporters");
        ?>
		</div>
		
		<br>
		<label class="vocab_search_form2" for="distribution_list">Send Alerts to:</label><br><textarea class="tooltip" style="margin-left: 15px; width: 50%; height: 5em;" title="Separate multiple e-mail addresses with semicolons or commas." class="ui-corner-all" name="distribution_list" id="distribution_list"><?php echo htmlspecialchars($emsaHTMLPurifier->purify(implode(";", $current_distribution_list))); ?></textarea><br><br>
		
		<input type="hidden" name="edit_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save Changes</button>
	</form>
</div>
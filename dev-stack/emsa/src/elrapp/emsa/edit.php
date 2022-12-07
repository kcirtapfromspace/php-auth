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

use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('Udoh\Emsa\Auth\Authenticator')) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
	
/* @var $dbConn PDO */

$emsaFocusUrl = $webappBaseUrl . 'index.php?selected_page=' . (int) $navSelectedPage;

if (isset($navSubmenu) && ((int) $navSubmenu > 0)) {
    $emsaFocusUrl .= '&submenu=' . (int) $navSubmenu;
}

if (isset($navCat) && ((int) $navCat > 0)) {
    $emsaFocusUrl .= '&cat=' . (int) $navCat;
}

$emsaFocusUrl .= '&type=' . (int) $type . '&focus=' . (int) $ampId;

try {
    $getMasterQry = "SELECT lab_id, master_xml, vocab_app_id 
        FROM system_messages 
        WHERE id = :systemMessageId;";
    $getMasterStmt = $dbConn->prepare($getMasterQry);
    $getMasterStmt->bindValue(':systemMessageId', $ampId, PDO::PARAM_INT);
    if ($getMasterStmt->execute()) {
        $getMasterRow = $getMasterStmt->fetchObject();

        $labId = (int) $getMasterRow->lab_id;
        $appId = (int) $getMasterRow->vocab_app_id;
        $masterStr = trim($getMasterRow->master_xml);

        libxml_disable_entity_loader(true);
        $masterXmlObj = simplexml_load_string($masterStr);
        libxml_disable_entity_loader(false);
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
}

try {
    $localTimeZoneName = (new DateTime())->getTimezone()->getName();
} catch (Throwable $e) {
    $e = null;
    $localTimeZoneName = '[Unknown Time Zone]';
}

$editDOBDateTime = null;
$editReportedDateTime = null;
$editCollectedDateTime = null;
$editTestedDateTime = null;

try {
    if (!empty(trim($masterXmlObj->person->date_of_birth))) {
        $editDOBDateTime = (new DateTime(trim($masterXmlObj->person->date_of_birth)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y");
    }
} catch (Throwable $e) {
    $editDOBDateTime = null;
    ExceptionUtils::logException($e);
}

try {
    if (!empty(trim($masterXmlObj->reporting->report_date))) {
        $editReportedDateTime = (new DateTime(trim($masterXmlObj->reporting->report_date)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y H:i:s");
    }
} catch (Throwable $e) {
    $editReportedDateTime = null;
    ExceptionUtils::logException($e);
}

try {
    if (!empty(trim($masterXmlObj->labs->collection_date))) {
        $editCollectedDateTime = (new DateTime(trim($masterXmlObj->labs->collection_date)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y H:i:s");
    }
} catch (Throwable $e) {
    $editCollectedDateTime = null;
    ExceptionUtils::logException($e);
}

try {
    if (!empty(trim($masterXmlObj->labs->lab_test_date))) {
        $editTestedDateTime = (new DateTime(trim($masterXmlObj->labs->lab_test_date)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y H:i:s");
    }
} catch (Throwable $e) {
    $editTestedDateTime = null;
    ExceptionUtils::logException($e);
}

?>

<script type="text/javascript">
	$(function() {
		$("#search_form").hide();
		
		$("#edit_dob").datepicker({
			changeMonth: true,
			changeYear: true,
			showButtonPanel: true,
			closeText: "Done",
			yearRange: "-120:+0"
		});
	
		$("#edit_date_reported, #edit_date_collected, #edit_date_tested, .edit_date_admitted, .edit_date_discharged").datetimepicker({
			changeMonth: true,
			changeYear: true,
			showButtonPanel: true,
			closeText: "Done",
			yearRange: "-120:+0",
			timeFormat: "HH:mm:ss"
		});
	
		$("#edit_save").button({
			icon: "ui-icon-elrsave"
		}).one("click", function (e) {
		    e.preventDefault();
		    $("*").css("cursor", "wait");
            $(this).button("disable");
            $("#edit_cancel").button("disable");
            $("#edit_lab_form").trigger("submit");
        });
		
		$("#edit_cancel").button({
            icon: "ui-icon-elrcancel"
        }).one("click", function(e) {
            e.preventDefault();
            $(this).button("disable");
            $("#edit_save").button("disable");
            $("*").css("cursor", "wait");
            window.location.href = "<?php echo $emsaFocusUrl; ?>";
        });
	});
</script>
<script type="text/javascript" src="js/jquery-ui-timepicker-addon.min.js?v=1.5.0"></script>

<style type="text/css">
	#edit_lab_form label { display: inline-block; font-weight: bold; width: 250px !important; text-align: right; }
	#edit_lab_form input, #edit_lab_form select { margin: 4px; min-width: 350px; }
	#ui-datepicker-div { box-shadow: 2px 2px 15px 2px #666666; }
	.audit_log th, .audit_log td { text-align: left; }
	.audit_log th { color: dimgray; border-bottom: 2px darkgray solid; }
	.audit_log td { color: black; border-bottom: 1px lightgray solid; }
	
	/* css for timepicker */
	.ui-timepicker-div .ui-widget-header { margin-bottom: 8px; }
	.ui-timepicker-div dl { text-align: left; }
	.ui-timepicker-div dl dt { float: left; clear:left; padding: 0 0 0 5px; }
	.ui-timepicker-div dl dd { margin: 0 10px 10px 45%; }
	.ui-timepicker-div td { font-size: 90%; }
	.ui-tpicker-grid-label { background: none; border: none; margin: 0; padding: 0; }

	.ui-timepicker-rtl{ direction: rtl; }
	.ui-timepicker-rtl dl { text-align: right; padding: 0 5px 0 0; }
	.ui-timepicker-rtl dl dt{ float: right; clear: right; }
	.ui-timepicker-rtl dl dd { margin: 0 45% 10px 10px; }
</style>

<fieldset class="emsa-list ui-widget ui-widget-content ui-corner-all">
	<legend class="emsa-list-legend ui-widget-content ui-corner-all">Current Errors/Pending Flags</legend>
	<div class="emsa_results_container">
		<div class="exception_details">
			<table class="audit_log">
				<thead>
					<tr>
						<th>Error Type</th>
						<th>Error Description</th>
						<th>Error Details</th>
					</tr>
				</thead>
				<tbody>

		<?php
			
			try {
				$currentSql = "SELECT se.description AS description, sme.info AS info, ss.name AS type 
					FROM system_message_exceptions sme 
					INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id) 
					INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
					WHERE sme.system_message_id = :systemMessageId
					ORDER BY sme.id;";
				$currentStmt = $dbConn->prepare($currentSql);
				$currentStmt->bindValue(':systemMessageId', $ampId, PDO::PARAM_INT);
				if ($currentStmt->execute()) {
					while ($currentRow = $currentStmt->fetchObject()) {
						echo '<tr><td>'.DisplayUtils::xSafe($currentRow->type).'</td><td>'.DisplayUtils::xSafe($currentRow->description).'</td><td>'.DisplayUtils::xSafe($currentRow->info).'</td></tr>';
					}
				} else {
					echo '<tr><td colspan="3"><em>Unable to retrieve list of errors</em></td></tr>';
				}
			} catch (Throwable $e) {
				Udoh\Emsa\Utils\ExceptionUtils::logException($e);
				echo '<tr><td colspan="3"><em>Unable to retrieve list of errors</em></td></tr>';
			}
			
		?>

				</tbody>
			</table>
		</div>
	</div>
</fieldset>

<fieldset class="emsa-list ui-widget ui-widget-content ui-corner-all">
	<legend class="emsa-list-legend ui-widget-content ui-corner-all">Edit Message</legend>
    <em>All dates &amp; times below displayed according to time zone '<?php echo $localTimeZoneName; ?>'</em>
	<div class="emsa_results_container">
		<form id="edit_lab_form" method="POST" action="<?php echo $emsaFocusUrl; ?>">
			<div class="h3">Patient Information</div>
            <?php
            $personFacilityMRNVisitCount = 0;
            if (isset($masterXmlObj->person_facilities) && count($masterXmlObj->person_facilities) > 0) {
                foreach ($masterXmlObj->person_facilities as $personFacilityMRNVisit) {
                    if (empty($personFacilityMRNVisit->facility_visit_type)) {
                        continue;  // skip if no visit type indicated
                    }

                    $personFacilityMRNVisitCount++;
                }
            }

            if ($personFacilityMRNVisitCount === 0) {
                ?>
                <label for="edit_patient_id">Patient ID/MRN:</label> <input class="ui-corner-all" type="text" name="edit_patient_id" id="edit_patient_id" value="<?php DisplayUtils::xEcho($masterXmlObj->hospital_info->medical_record ?? ''); ?>" /><br>
                <?php
            }
            ?>
            <label for="edit_last_name">Last Name:</label> <input class="ui-corner-all" type="text" name="edit_last_name" id="edit_last_name" value="<?php DisplayUtils::xEcho($masterXmlObj->person->last_name); ?>" /><br>
			<label for="edit_first_name">First Name:</label> <input class="ui-corner-all" type="text" name="edit_first_name" id="edit_first_name" value="<?php DisplayUtils::xEcho($masterXmlObj->person->first_name); ?>" /><br>
			<label for="edit_middle_name">Middle Name:</label> <input class="ui-corner-all" type="text" name="edit_middle_name" id="edit_middle_name" value="<?php DisplayUtils::xEcho($masterXmlObj->person->middle_name); ?>" /><br>
			<label for="edit_gender">Gender:</label>
				<select name="edit_gender" id="edit_gender" class="ui-corner-all">
					<option value="" <?php echo ((strlen(trim($masterXmlObj->person->gender)) < 1) ? " selected" : ""); ?>>--</option>
				<?php
					try {
						$genderSql = "SELECT vm.concept AS master_label, vc.concept AS child_label 
							FROM vocab_master_vocab vm 
							INNER JOIN vocab_child_vocab vc ON (vm.id = vc.master_id AND vc.lab_id = :labId) 
							WHERE (vm.category = elr.vocab_category_id(:category)) 
							ORDER BY vm.concept;";
						$genderStmt = $dbConn->prepare($genderSql);
						$genderStmt->bindValue(':labId', intval($labId), PDO::PARAM_INT);
						$genderStmt->bindValue(':category', 'gender', PDO::PARAM_STR);
						
						if ($genderStmt->execute()) {
							while ($genderRow = $genderStmt->fetchObject()) {
								if (trim($genderRow->child_label) == trim($masterXmlObj->person->gender)) {
									echo '<option value="'.DisplayUtils::xSafe($genderRow->child_label).'" selected>'.DisplayUtils::xSafe($genderRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($genderRow->child_label).']</option>';
								} else {
									echo '<option value="'.DisplayUtils::xSafe($genderRow->child_label).'">'.DisplayUtils::xSafe($genderRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($genderRow->child_label).']</option>';
								}
							}
						} else {
							DisplayUtils::drawError('Unable to retrieve list of genders', true);
						}
					} catch (Throwable $e) {
						Udoh\Emsa\Utils\ExceptionUtils::logException($e);
						DisplayUtils::drawError('Unable to retrieve list of genders', true);
					}
				?>
				</select><br>
            
            <label for="edit_race">Race:</label>
				<select name="edit_race" id="edit_race" class="ui-corner-all">
					<option value="" <?php echo ((strlen(trim($masterXmlObj->person->race)) < 1) ? " selected" : ""); ?>>--</option>
				<?php
					try {
						$raceSql = "SELECT vm.concept AS master_label, vc.concept AS child_label 
							FROM vocab_master_vocab vm 
							INNER JOIN vocab_child_vocab vc ON (vm.id = vc.master_id AND vc.lab_id = :labId) 
							WHERE (vm.category = elr.vocab_category_id(:category)) 
							ORDER BY vm.concept;";
						$raceStmt = $dbConn->prepare($raceSql);
						$raceStmt->bindValue(':labId', intval($labId), PDO::PARAM_INT);
						$raceStmt->bindValue(':category', 'race', PDO::PARAM_STR);
						
						if ($raceStmt->execute()) {
							while ($raceRow = $raceStmt->fetchObject()) {
								if (trim($raceRow->child_label) == trim($masterXmlObj->person->race)) {
									echo '<option value="'.DisplayUtils::xSafe($raceRow->child_label).'" selected>'.DisplayUtils::xSafe($raceRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($raceRow->child_label).']</option>';
								} else {
									echo '<option value="'.DisplayUtils::xSafe($raceRow->child_label).'">'.DisplayUtils::xSafe($raceRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($raceRow->child_label).']</option>';
								}
							}
						} else {
							DisplayUtils::drawError('Unable to retrieve list of races', true);
						}
					} catch (Throwable $e) {
						Udoh\Emsa\Utils\ExceptionUtils::logException($e);
						DisplayUtils::drawError('Unable to retrieve list of races', true);
					}
				?>
				</select><br>
            
            <label for="edit_ethnicity">Ethnicity:</label>
				<select name="edit_ethnicity" id="edit_ethnicity" class="ui-corner-all">
					<option value="" <?php echo ((strlen(trim($masterXmlObj->person->ethnicity)) < 1) ? " selected" : ""); ?>>--</option>
				<?php
					try {
						$ethnicitySql = "SELECT vm.concept AS master_label, vc.concept AS child_label 
							FROM vocab_master_vocab vm 
							INNER JOIN vocab_child_vocab vc ON (vm.id = vc.master_id AND vc.lab_id = :labId) 
							WHERE (vm.category = elr.vocab_category_id(:category)) 
							ORDER BY vm.concept;";
						$ethnicitySql = $dbConn->prepare($ethnicitySql);
						$ethnicitySql->bindValue(':labId', intval($labId), PDO::PARAM_INT);
						$ethnicitySql->bindValue(':category', 'ethnicity', PDO::PARAM_STR);

						if ($ethnicitySql->execute()) {
							while ($ethnicityRow = $ethnicitySql->fetchObject()) {
								if (trim($ethnicityRow->child_label) == trim($masterXmlObj->person->ethnicity)) {
									echo '<option value="'.DisplayUtils::xSafe($ethnicityRow->child_label).'" selected>'.DisplayUtils::xSafe($ethnicityRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($ethnicityRow->child_label).']</option>';
								} else {
									echo '<option value="'.DisplayUtils::xSafe($ethnicityRow->child_label).'">'.DisplayUtils::xSafe($ethnicityRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($ethnicityRow->child_label).']</option>';
								}
							}
						} else {
							DisplayUtils::drawError('Unable to retrieve list of ethnicities', true);
						}
					} catch (Throwable $e) {
						Udoh\Emsa\Utils\ExceptionUtils::logException($e);
						DisplayUtils::drawError('Unable to retrieve list of ethnicities', true);
					}
				?>
				</select><br>
            
			<label for="edit_street_name">Street Address:</label> <input class="ui-corner-all" type="text" name="edit_street_name" id="edit_street_name" value="<?php DisplayUtils::xEcho($masterXmlObj->person->street_name); ?>" /><br>
			<label for="edit_unit">Unit:</label> <input class="ui-corner-all" type="text" name="edit_unit" id="edit_unit" value="<?php DisplayUtils::xEcho($masterXmlObj->person->unit); ?>" /><br>
			<label for="edit_city">City:</label> <input class="ui-corner-all" type="text" name="edit_city" id="edit_city" value="<?php DisplayUtils::xEcho($masterXmlObj->person->city); ?>" /><br>
			<label for="edit_state">State:</label> <input class="ui-corner-all" type="text" name="edit_state" id="edit_state" value="<?php DisplayUtils::xEcho($masterXmlObj->person->state); ?>" /><br>
			<label for="edit_county">County:</label> <input class="ui-corner-all" type="text" name="edit_county" id="edit_county" value="<?php DisplayUtils::xEcho($masterXmlObj->person->county); ?>" /><br>
			<label for="edit_zip">ZIP/Postal Code:</label> <input class="ui-corner-all" type="text" name="edit_zip" id="edit_zip" value="<?php DisplayUtils::xEcho($masterXmlObj->person->zip); ?>" /><br>
			<label for="edit_country">Country:</label> <input class="ui-corner-all" type="text" name="edit_country" id="edit_country" value="<?php DisplayUtils::xEcho($masterXmlObj->person->country); ?>" /><br><br>
			<label for="edit_area_code">Area Code:</label> <input class="ui-corner-all" type="text" name="edit_area_code" id="edit_area_code" value="<?php DisplayUtils::xEcho($masterXmlObj->person->area_code); ?>" /><br>
			<label for="edit_telephone">Telephone:</label> <input class="ui-corner-all" type="text" name="edit_telephone" id="edit_telephone" value="<?php DisplayUtils::xEcho($masterXmlObj->person->phone); ?>" /><br><br>
			<label for="edit_email">Email:</label> <input class="ui-corner-all" type="text" name="edit_email" id="edit_email" value="<?php DisplayUtils::xEcho($masterXmlObj->person->email); ?>" /><br><br>
			<label for="edit_dob">Date of Birth:</label> <input class="ui-corner-all" type="text" name="edit_dob" id="edit_dob" value="<?php DisplayUtils::xEcho($editDOBDateTime, "UTF-8", false); ?>" placeholder="MM/DD/YYYY" /><br><br>
			<div class="h3">Lab Information</div>
		<?php if (($type == EXCEPTIONS_STATUS) || ($type == NEDSS_EXCEPTION_STATUS)) { ?>
			<label for="edit_date_reported">Date/Time Reported:</label> <input class="ui-corner-all" type="text" name="edit_date_reported" id="edit_date_reported" value="<?php DisplayUtils::xEcho($editReportedDateTime, "UTF-8", false); ?>" placeholder="MM/DD/YYYY HH:MM:SS" /><br>
			<label for="edit_agency">Reporting Agency:</label> <input class="ui-corner-all" type="text" name="edit_agency" id="edit_agency" value="<?php DisplayUtils::xEcho($masterXmlObj->reporting->agency); ?>" /><br>
			<label for="edit_performing_lab">Performing Lab:</label> <input class="ui-corner-all" type="text" name="edit_performing_lab" id="edit_performing_lab" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->lab); ?>" /><br>
			<label for="edit_child_loinc">Child LOINC Code:</label> <input class="ui-corner-all" type="text" name="edit_child_loinc" id="edit_child_loinc" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_loinc_code); ?>" /><br>
			<label for="edit_test_name">Child LOINC Test Name:</label> <input class="ui-corner-all" type="text" name="edit_test_name" id="edit_test_name" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_test_name); ?>" /><br>
			<label for="edit_local_code">Local Test Code:</label> <input class="ui-corner-all" type="text" name="edit_local_code" id="edit_local_code" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_code); ?>" /><br>
			<label for="edit_local_code_test_name">Local Code Test Name:</label> <input class="ui-corner-all" type="text" name="edit_local_code_test_name" id="edit_local_code_test_name" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_code_test_name); ?>" /><br>
			<label for="edit_result_value">Local Result Value:</label> <input class="ui-corner-all" type="text" name="edit_result_value" id="edit_result_value" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_result_value); ?>" /><br>
		<?php } else { ?>
			<input type="hidden" name="edit_date_reported" value="<?php DisplayUtils::xEcho($editReportedDateTime, "UTF-8", false); ?>">
			<input type="hidden" name="edit_agency" value="<?php DisplayUtils::xEcho($masterXmlObj->reporting->agency); ?>">
			<input type="hidden" name="edit_performing_lab" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->lab); ?>">
			<input type="hidden" name="edit_test_name" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_test_name); ?>">
			<input type="hidden" name="edit_child_loinc" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_loinc_code); ?>">
			<input type="hidden" name="edit_test_name" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_test_name); ?>">
			<input type="hidden" name="edit_local_code" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_code); ?>">
			<input type="hidden" name="edit_local_code_test_name" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_code_test_name); ?>">
			<input type="hidden" name="edit_result_value" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->local_result_value); ?>">
		<?php } ?>
			<label for="edit_abnormal_flag">Abnormal Flag:</label>
				<select name="edit_abnormal_flag" id="edit_abnormal_flag" class="ui-corner-all">
					<option value="" <?php echo ((strlen(trim($masterXmlObj->labs->abnormal_flag)) < 1) ? " selected" : ""); ?>>--</option>
				<?php
					try {
						$abnormalFlagSql = "SELECT vm.concept AS master_label, vc.concept AS child_label 
							FROM vocab_master_vocab vm 
							INNER JOIN vocab_child_vocab vc ON (vm.id = vc.master_id AND vc.lab_id = :labId) 
							WHERE (vm.category = elr.vocab_category_id(:category)) 
							ORDER BY vm.concept;";
						$abnormalFlagSql = $dbConn->prepare($abnormalFlagSql);
						$abnormalFlagSql->bindValue(':labId', intval($labId), PDO::PARAM_INT);
						$abnormalFlagSql->bindValue(':category', 'abnormal_flag', PDO::PARAM_STR);

						if ($abnormalFlagSql->execute()) {
							while ($abnormalFlagRow = $abnormalFlagSql->fetchObject()) {
								if (trim($abnormalFlagRow->child_label) == trim($masterXmlObj->labs->abnormal_flag)) {
									echo '<option value="'.DisplayUtils::xSafe($abnormalFlagRow->child_label).'" selected>'.DisplayUtils::xSafe($abnormalFlagRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($abnormalFlagRow->child_label).']</option>';
								} else {
									echo '<option value="'.DisplayUtils::xSafe($abnormalFlagRow->child_label).'">'.DisplayUtils::xSafe($abnormalFlagRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($abnormalFlagRow->child_label).']</option>';
								}
							}
						} else {
							DisplayUtils::drawError('Unable to retrieve list of abnormal flags', true);
						}
					} catch (Throwable $e) {
						Udoh\Emsa\Utils\ExceptionUtils::logException($e);
						DisplayUtils::drawError('Unable to retrieve list of abnormal flags', true);
					}
				?>
				</select><br>
            
			<label for="edit_specimen_source">Specimen Source:</label>
				<select name="edit_specimen_source" id="edit_specimen_source" class="ui-corner-all">
					<option value="" <?php echo ((strlen(trim($masterXmlObj->labs->local_specimen_source)) < 1) ? " selected" : ""); ?>>--</option>
				<?php
					try {
                        $specimenSql = "SELECT vm.concept AS master_label, vc.concept AS child_label 
							FROM vocab_master_vocab vm 
							INNER JOIN vocab_child_vocab vc ON (vm.id = vc.master_id AND vc.lab_id = :labId) 
							WHERE (vm.category = elr.vocab_category_id(:category)) 
							ORDER BY vm.concept;";
						$specimenStmt = $dbConn->prepare($specimenSql);
						$specimenStmt->bindValue(':labId', intval($labId), PDO::PARAM_INT);
						$specimenStmt->bindValue(':category', 'specimen', PDO::PARAM_STR);
						
						if ($specimenStmt->execute()) {
							while ($specimenRow = $specimenStmt->fetchObject()) {
                                if (trim($specimenRow->child_label) == trim($masterXmlObj->labs->local_specimen_source)) {
									echo '<option value="'.DisplayUtils::xSafe($specimenRow->child_label).'" selected>'.DisplayUtils::xSafe($specimenRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($specimenRow->child_label).']</option>';
								} else {
									echo '<option value="'.DisplayUtils::xSafe($specimenRow->child_label).'">'.DisplayUtils::xSafe($specimenRow->master_label).' ['.DisplayUtils::xSafe($masterXmlObj->labs->lab).': '.DisplayUtils::xSafe($specimenRow->child_label).']</option>';
								}
							}
						} else {
							DisplayUtils::drawError('Unable to retrieve list of specimen sources', true);
						}
					} catch (Throwable $e) {
						Udoh\Emsa\Utils\ExceptionUtils::logException($e);
						DisplayUtils::drawError('Unable to retrieve list of specimen sources', true);
					}
				?>
				</select><br>
                <label for="edit_accession_number">Accession Number:</label> <input class="ui-corner-all" type="text" name="edit_accession_number" id="edit_accession_number" value="<?php DisplayUtils::xEcho($masterXmlObj->labs->accession_number); ?>" /><br>
				<label for="edit_date_collected">Specimen Collection Date/Time:</label> <input class="ui-corner-all" type="text" name="edit_date_collected" id="edit_date_collected" value="<?php DisplayUtils::xEcho($editCollectedDateTime, "UTF-8", false); ?>" placeholder="MM/DD/YYYY HH:MM:SS" /><br>
				<label for="edit_date_tested">Lab Test Date/Time:</label> <input class="ui-corner-all" type="text" name="edit_date_tested" id="edit_date_tested" value="<?php DisplayUtils::xEcho($editTestedDateTime, "UTF-8", false); ?>" placeholder="MM/DD/YYYY HH:MM:SS" /><br><br>

            <?php
            if (isset($masterXmlObj->person_facilities) && count($masterXmlObj->person_facilities) > 0) {
                $personFacilityVisitCount = 0;

                foreach ($masterXmlObj->person_facilities as $personFacilityVisit) {
                    if (empty($personFacilityVisit->facility_visit_type)) {
                        continue;  // skip if no visit type indicated
                    }

                    $editAdmittedDateTime = null;
                    $editDischargedDateTime = null;

                    try {
                        if (!empty(trim($personFacilityVisit->admission_date))) {
                            $editAdmittedDateTime = (new DateTime(trim($personFacilityVisit->admission_date)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y H:i:s");
                        }
                    } catch (Throwable $e) {
                        $editAdmittedDateTime = null;
                        ExceptionUtils::logException($e);
                    }

                    try {
                        if (!empty(trim($personFacilityVisit->discharge_date))) {
                            $editDischargedDateTime = (new DateTime(trim($personFacilityVisit->discharge_date)))->setTimezone((new DateTime("now"))->getTimezone())->format("m/d/Y H:i:s");
                        }
                    } catch (Throwable $e) {
                        $editDischargedDateTime = null;
                        ExceptionUtils::logException($e);
                    }

                    echo '<div class="h3">Healthcare Facility Visit Information</div>';
                    echo '<label for="edit_mrn_' . (int) $personFacilityVisitCount . '">MRN:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][mrn]" id="edit_mrn_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->medical_record_number), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_date_admitted_' . (int) $personFacilityVisitCount . '">Admission (Visit Start) Date/Time:</label> <input class="ui-corner-all edit_date_admitted" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][date_admitted]" id="edit_date_admitted_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe($editAdmittedDateTime, "UTF-8", false) . '" placeholder="MM/DD/YYYY HH:MM:SS" /><br>';
                    echo '<label for="edit_date_discharged_' . (int) $personFacilityVisitCount . '">Discharge (Visit End) Date/Time:</label> <input class="ui-corner-all edit_date_discharged" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][date_discharged]" id="edit_date_discharged_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe($editDischargedDateTime, "UTF-8", false) . '" placeholder="MM/DD/YYYY HH:MM:SS" /><br>';
                    echo '<label for="edit_hf_name_' . (int) $personFacilityVisitCount . '">Facility Name:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][name]" id="edit_hf_name_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->name), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_hf_street_name_' . (int) $personFacilityVisitCount . '">Street Address:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][street_name]" id="edit_hf_street_name_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->street_name), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_hf_unit_' . (int) $personFacilityVisitCount . '">Unit:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][unit]" id="edit_hf_unit_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->unit_number), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_hf_city_' . (int) $personFacilityVisitCount . '">City:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][city]" id="edit_hf_city_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->city), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_hf_state_' . (int) $personFacilityVisitCount . '">State:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][state]" id="edit_hf_state_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->state), "UTF-8", false) . '" /><br>';
                    echo '<label for="edit_hf_zip_' . (int) $personFacilityVisitCount . '">ZIP/Postal Code:</label> <input class="ui-corner-all" type="text" name="edit_visits[' . (int) $personFacilityVisitCount . '][zip]" id="edit_hf_zip_' . (int) $personFacilityVisitCount . '" value="' . DisplayUtils::xSafe(trim($personFacilityVisit->facility->zipcode), "UTF-8", false) . '" /><br><br>';

                    $personFacilityVisitCount++;
                }
            }
            ?>
				
            <div class="h3">Ordering Clinician Information</div>
            <label for="edit_clinician_last_name">Clinician Last Name:</label> <input class="ui-corner-all" type="text" name="edit_clinician_last_name" id="edit_clinician_last_name" value="<?php DisplayUtils::xEcho($masterXmlObj->clinicians->last_name); ?>" /><br>
            <label for="edit_clinician_first_name">Clinician First Name:</label> <input class="ui-corner-all" type="text" name="edit_clinician_first_name" id="edit_clinician_first_name" value="<?php DisplayUtils::xEcho($masterXmlObj->clinicians->first_name); ?>" /><br>
            <label for="edit_clinician_middle_name">Clinician Middle Name:</label> <input class="ui-corner-all" type="text" name="edit_clinician_middle_name" id="edit_clinician_middle_name" value="<?php DisplayUtils::xEcho($masterXmlObj->clinicians->middle_name); ?>" /><br>
            <label for="edit_clinician_area_code">Clinician Area Code:</label> <input class="ui-corner-all" type="text" name="edit_clinician_area_code" id="edit_clinician_area_code" value="<?php DisplayUtils::xEcho($masterXmlObj->clinicians->area_code); ?>" /><br>
            <label for="edit_clinician_telephone">Clinician Telephone:</label> <input class="ui-corner-all" type="text" name="edit_clinician_telephone" id="edit_clinician_telephone" value="<?php DisplayUtils::xEcho($masterXmlObj->clinicians->phone); ?>" /><br><br>

            <div class="h3">Ordering Facility Information</div>
			<label for="edit_df_name">Facility Name:</label> <input class="ui-corner-all" type="text" name="edit_df_name" id="edit_df_name" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->name); ?>" /><br>
			<label for="edit_df_street_name">Street Address:</label> <input class="ui-corner-all" type="text" name="edit_df_street_name" id="edit_df_street_name" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->street_name); ?>" /><br>
			<label for="edit_df_unit">Unit:</label> <input class="ui-corner-all" type="text" name="edit_df_unit" id="edit_df_unit" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->unit); ?>" /><br>
			<label for="edit_df_city">City:</label> <input class="ui-corner-all" type="text" name="edit_df_city" id="edit_df_city" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->city); ?>" /><br>
			<label for="edit_df_state">State:</label> <input class="ui-corner-all" type="text" name="edit_df_state" id="edit_df_state" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->state); ?>" /><br>
			<label for="edit_df_zip">ZIP/Postal Code:</label> <input class="ui-corner-all" type="text" name="edit_df_zip" id="edit_df_zip" value="<?php DisplayUtils::xEcho($masterXmlObj->diagnostic->zipcode); ?>" /><br>

			<input type="hidden" name="emsa_action" id="emsa_action" value="save" />
			<input type="hidden" name="id" id="id" value="<?php echo (int) $ampId; ?>" />
			<button type="submit" name="edit_save" id="edit_save">Save & Retry</button>
			<button type="button" name="edit_cancel" id="edit_cancel">Cancel</button>
		</form>
	</div>
	
</fieldset>

<?php
	exit;
    

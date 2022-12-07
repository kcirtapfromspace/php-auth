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

/**
 * Methods for drawing Vocab Rule Builder dialog DIVs
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabRuleDivBuilder
{

    /**
     * Generate HTML DIVs for Graylist Rule Builder dialogs
     * @param PDO $dbConn PDO connection to EMSA database
     * @return string
     */
    public static function drawGraylist(PDO $dbConn)
    {
        $apps = '';
        $sql = 'SELECT DISTINCT id, app_name
				FROM vocab_app
				ORDER BY app_name;';
        $stmt = $dbConn->prepare($sql);
        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $apps .= '<option value="' . intval($row->id) . '">' . htmlentities($row->app_name, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }
        }

        $html = <<< EOG
<div id="confirm_deleterule_gray_dialog" title="Delete this Graylist Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Graylist Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_gray_dialog" title="Add/Edit Graylist Rule">
	<form id="rule_mod_gray_form" method="GET">
		<h2 id="rulemod_gray_organism"></h2>
		<label for="rulemod_gray_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0px;" name="rulemod_gray_application" id="rulemod_gray_application" title="Values will be transformed for this application">
			<option value="0" selected>--</option>
			$apps
		</select><br><br>
		<div class="h3">Match from Graylist If...</div>
		<div id="rulemod_gray_condition_container"></div>
		
		<input type="hidden" name="rulemod_gray_id" id="rulemod_gray_id" />
		<input type="hidden" name="focus_id" id="rulemod_gray_focus_id" />
		<input type="hidden" name="rulemod_gray_action" id="rulemod_gray_action" />
	</form>
</div>
EOG;
        return $html;
    }

    /**
     * Generate HTML DIVs for Master LOINC Case Management Rule Builder dialogs
     * @param PDO $dbConn PDO connection to EMSA database
     * @return string
     */
    public static function drawLoincCmr(PDO $dbConn)
    {
        $apps = '';
        $stateCaseStatuses = '';

        $appsSql = 'SELECT DISTINCT id, app_name
				FROM vocab_app
				ORDER BY app_name;';
        $appsStmt = $dbConn->prepare($appsSql);
        if ($appsStmt->execute()) {
            while ($appsRow = $appsStmt->fetchObject()) {
                $apps .= '<option value="' . intval($appsRow->id) . '">' . htmlentities($appsRow->app_name, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }
        }

        $sccSql = "SELECT vm.id AS id, vm.concept AS label 
                   FROM vocab_master_vocab vm 
                   WHERE (vm.category = elr.vocab_category_id('case')) 
                   ORDER BY vm.concept;";
        $sccStmt = $dbConn->prepare($sccSql);
        if ($sccStmt->execute()) {
            while ($sccRow = $sccStmt->fetchObject()) {
                $stateCaseStatuses .= '<option value="' . intval($sccRow->id) . '">' . htmlentities($sccRow->label, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }
        }

        $html = <<<EOLCMR
<div id="confirm_deleterule_cmr_dialog" title="Delete this Case Management Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Case Management Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_cmr_dialog" title="Add/Edit LOINC-Based Case Management Rule">
	<form id="rule_mod_cmr_form" method="GET">
		<h2 id="rulemod_cmr_loinc"></h2>
		<label for="rulemod_cmr_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0px;" name="rulemod_cmr_application" id="rulemod_cmr_application" title="Values will be transformed for this application">
			<option value="0" selected>--</option>
			$apps
		</select><br><br>
		<div class="h3">If...</div>
		<div id="rulemod_cmr_condition_container"></div>
		<input type="hidden" name="rulemod_cmr_condition_counter" id="rulemod_cmr_condition_counter" value="1">
		<button type="button" id="rulemod_cmr_add_condition" title="Add New Condition">Add Condition</button><br><br>
		<div class="h3">Then...</div>
		<table>
			<tbody>
				<tr>
					<td>
						<label for="rulemod_new_cmr">Create New CMR?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_new_cmr" id="rulemod_new_cmr" title="Create a new Morbidity event based on this test result?">
							<option value="t" selected>Yes</option>
							<option value="f">No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_update_cmr">Update Existing CMRs?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_update_cmr" id="rulemod_update_cmr" title="Update an existing CMR (if found)?">
							<option value="t" selected>Yes</option>
							<option value="f">No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_is_surveillance">Surveillance Event?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_is_surveillance" id="rulemod_is_surveillance" title="If creating a new CMR, create as a Surveillance Event?">
							<option value="t">Yes</option>
							<option value="f" selected>No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_state_case_status">Set State Case Status to:</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_state_case_status" id="rulemod_state_case_status" title="For a new Morbidity event, set this State Case Status.">
							<option value="-1" selected>--</option>
							$stateCaseStatuses
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="rulemod_cmr_id" id="rulemod_cmr_id" />
		<input type="hidden" name="focus_id" id="rulemod_cmr_focus_id" />
		<input type="hidden" name="rulemod_cmr_action" id="rulemod_cmr_action" />
	</form>
</div>
EOLCMR;
        return $html;
    }

    /**
     * Generate HTML DIVs for Master SNOMED Case Management Rule Builder dialogs
     * @param PDO $dbConn PDO connection to EMSA database
     * @return string
     */
    public static function drawSnomedCmr(PDO $dbConn)
    {
        $apps = '';
        $stateCaseStatuses = '';

        $appsSql = 'SELECT DISTINCT id, app_name
                    FROM vocab_app
                    ORDER BY app_name;';
        $appsStmt = $dbConn->prepare($appsSql);
        if ($appsStmt->execute()) {
            while ($appsRow = $appsStmt->fetchObject()) {
                $apps .= '<option value="' . intval($appsRow->id) . '">' . htmlentities($appsRow->app_name, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }
        }

        $sccSql = "SELECT vm.id AS id, vm.concept AS label 
                   FROM vocab_master_vocab vm 
                   WHERE (vm.category = elr.vocab_category_id('case')) 
                   ORDER BY vm.concept;";
        $sccStmt = $dbConn->prepare($sccSql);
        if ($sccStmt->execute()) {
            while ($sccRow = $sccStmt->fetchObject()) {
                $stateCaseStatuses .= '<option value="' . intval($sccRow->id) . '">' . htmlentities($sccRow->label, ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
            }
        }

        $html = <<<EOLSNOMED
<div id="confirm_deleterule_ms_cmr_dialog" title="Delete this Case Management Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Case Management Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_ms_cmr_dialog" title="Add/Edit Organism-Based Case Management Rule">
	<form id="rule_mod_ms_cmr_form" method="GET">
		<h2 id="rulemod_ms_cmr_organism"></h2>
		<label for="rulemod_ms_cmr_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0px;" name="rulemod_ms_cmr_application" id="rulemod_ms_cmr_application" title="Values will be transformed for this application">
			<option value="0" selected>--</option>
			$apps
		</select><br><br>
		<div class="h3">If...</div>
		<div id="rulemod_ms_cmr_condition_container"></div>
		<input type="hidden" name="rulemod_cmr_condition_counter" id="rulemod_ms_cmr_condition_counter" value="1">
		<button type="button" id="rulemod_ms_cmr_add_condition" title="Add New Condition">Add Condition</button><br><br>
		<div class="h3">Then...</div>
		<table>
			<tbody>
				<tr>
					<td>
						<label for="rulemod_ms_new_cmr">Create New CMR?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_new_cmr" id="rulemod_ms_new_cmr" title="Create a new Morbidity event based on this test result?">
							<option value="t" selected>Yes</option>
							<option value="f">No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_ms_update_cmr">Update Existing CMRs?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_update_cmr" id="rulemod_ms_update_cmr" title="Update an existing CMR (if found)?">
							<option value="t" selected>Yes</option>
							<option value="f">No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_ms_is_surveillance">Surveillance Event?</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_is_surveillance" id="rulemod_ms_is_surveillance" title="If creating a new CMR, create as a Surveillance Event?">
							<option value="t">Yes</option>
							<option value="f" selected>No</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_ms_state_case_status">Set State Case Status to:</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0px;" name="rulemod_state_case_status" id="rulemod_ms_state_case_status" title="For a new Morbidity event, set this State Case Status.">
							<option value="-1" selected>--</option>
							$stateCaseStatuses
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="rulemod_ms_cmr_id" id="rulemod_ms_cmr_id" />
		<input type="hidden" name="focus_id" id="rulemod_ms_cmr_focus_id" />
		<input type="hidden" name="rulemod_ms_cmr_action" id="rulemod_ms_cmr_action" />
	</form>
</div>
EOLSNOMED;
        return $html;
    }

    /**
     * Generate HTML DIVs for Single-Field Editor dialogs
     * @return string
     */
    public static function drawSingleField()
    {
        $html = <<<EOSINGLE
<div id="edit_singlefield_dialog" title="Edit Vocabulary Item">
	<form id="edit_singlefield_form" method="GET">
		<label for="singlefield_val" style="font-weight: 700;">New Value:</label>
		<input class="ui-corner-all" type="text" name="singlefield_val" id="singlefield_val" style="width: 95%;">
		<input type="hidden" name="singlefield_old" id="singlefield_old">
		<input type="hidden" name="singlefield_id" id="singlefield_id">
		<input type="hidden" name="singlefield_col" id="singlefield_col">
		<input type="hidden" name="action" id="action" value="edit" />
	</form>
</div>
EOSINGLE;
        return $html;
    }

}

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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<div id="confirm_deleterule_ms_cmr_dialog" title="Delete this Case Management Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Case Management Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_ms_cmr_dialog" title="Add/Edit Organism-Based Case Management Rule">
	<form id="rule_mod_ms_cmr_form" method="GET">
		<h2 id="rulemod_ms_cmr_organism"></h2>
		<label for="rulemod_ms_cmr_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0;" name="rulemod_ms_cmr_application" id="rulemod_ms_cmr_application" title="Values will be transformed for this application">
			<option value="0" selected>--</option>
		<?php
			// get list of applications
			$apps_sql = sprintf("SELECT DISTINCT id, app_name FROM %svocab_app ORDER BY app_name;", $emsaDbSchemaPrefix);
			$apps_rs = @pg_query($host_pa, $apps_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.", true);
			while ($apps_row = pg_fetch_object($apps_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($apps_row->id), htmlentities($apps_row->app_name));
			}
			pg_free_result($apps_rs);
		?>
		</select><br><br>
		<div class="h3">If...</div>
		<div id="rulemod_ms_cmr_condition_container">
			<div class="rulemod_condition" id="rulemod_ms_cmr_condition_1">
				<button type="button" class="rulemod_cmr_delete_condition" value="1" title="Delete This Condition">Delete Condition</button>
				<label for="rulemod_ms_cmr_operator_1">Test Result </label>
				<select class="ui-corner-all" style="margin: 0; max-width: 11em;" name="rulemod_ms_cmr_operator[1]" id="rulemod_ms_cmr_operator_1" title="Choose a comparison type">
					<option value="0" selected>--</option>
				</select>
				<select aria-label="Choose a test result" class="ui-corner-all" style="margin: 0; max-width: 8em;" name="rulemod_ms_cmr_operand_value[1]" id="rulemod_ms_cmr_operand_value_1">
					<option value="0" selected>--</option>
				</select>
			</div>
		</div>
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
						<select class="ui-corner-all" style="margin: 0;" name="rulemod_new_cmr" id="rulemod_ms_new_cmr" title="Create a new Morbidity event based on this test result?">
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
						<select class="ui-corner-all" style="margin: 0;" name="rulemod_update_cmr" id="rulemod_ms_update_cmr" title="Update an existing CMR (if found)?">
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
						<select class="ui-corner-all" style="margin: 0;" name="rulemod_is_surveillance" id="rulemod_ms_is_surveillance" title="If creating a new CMR, create as a Surveillance Event?">
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
						<select class="ui-corner-all" style="margin: 0;" name="rulemod_state_case_status" id="rulemod_ms_state_case_status" title="For a new Morbidity event, set this State Case Status.">
							<option value="-1" selected>--</option>
						<?php
							// get list of state case statuses
							$casestatus_sql = 'SELECT vm.id AS value, vm.concept AS label FROM '.$emsaDbSchemaPrefix.'vocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id(\'case\')) ORDER BY vm.concept;';
							$casestatus_rs = @pg_query($host_pa, $casestatus_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of statuses.");
							while ($casestatus_row = @pg_fetch_object($casestatus_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $casestatus_row->value, htmlentities($casestatus_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($casestatus_rs);
						?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="rulemod_ms_cmr_id" id="rulemod_ms_cmr_id" />
		<input type="hidden" name="focus_id" id="rulemod_ms_cmr_focus_id" />
		<input type="hidden" name="rulemod_ms_cmr_action" id="rulemod_ms_cmr_action" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
	</form>
</div>
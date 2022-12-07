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
<div id="confirm_deleterule_dialog" title="Delete this Result Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Result Interpretation Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_dialog" title="Add/Edit Result Interpretation Rule">
	<form id="rule_mod_form" method="GET">
		<h2 id="rulemod_child_loinc"></h2>
		<label for="rulemod_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0;" name="rulemod_application" id="rulemod_application" title="Final result values will be transformed for this application">
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
		<div class="h3">If Child Result meets these conditions...</div>
		<div id="rulemod_condition_container">
			<div class="rulemod_condition" id="rulemod_condition_1">
				<button type="button" class="rulemod_delete_condition" value="1" title="Delete This Condition">Delete Condition</button><label for="rulemod_operator_1">Result </label> 
				<select class="ui-corner-all" style="margin: 0;" name="rulemod_operator[1]" id="rulemod_operator_1" title="Choose a comparison type">
					<option value="0" selected>--</option>
				</select>
				<label for="rulemod_operand_value_1">this value:</label> <input class="ui-corner-all" type="text" name="rulemod_operand_value[1]" id="rulemod_operand_value_1" title="Compare message result with this value" />
			</div>
		</div>
		<input type="hidden" name="rulemod_condition_counter" id="rulemod_condition_counter" value="1">
		<button type="button" id="rulemod_add_condition" title="Add New Condition">Add Condition</button><br><br>
		<div class="h3">Then set...</div>
		<table>
			<tbody>
				<tr>
					<td>
						<label for="rulemod_master_result">Test Result:</label>
					</td>
					<td>
						<select class="ui-corner-all" style="margin: 0;" name="rulemod_master_result" id="rulemod_master_result" title="Transform result to this Master Test Result value">
							<option value="0" selected>--</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<label for="rulemod_comments">Add Results to Comments:</label>
					</td>
					<td>
						<input class="ui-corner-all" type="text" name="rulemod_comments" id="rulemod_comments" />
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="rulemod_id" id="rulemod_id" />
		<input type="hidden" name="focus_id" id="rulemod_focus_id" />
		<input type="hidden" name="rulemod_action" id="rulemod_action" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
	</form>
</div>
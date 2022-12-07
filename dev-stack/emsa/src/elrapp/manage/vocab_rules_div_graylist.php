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
<div id="confirm_deleterule_gray_dialog" title="Delete this Graylist Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Graylist Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="rule_mod_gray_dialog" title="Add/Edit Graylist Rule">
	<form id="rule_mod_gray_form" method="GET">
		<h2 id="rulemod_gray_organism"></h2>
		<label for="rulemod_gray_application">Rule applies to:</label>
		<select class="ui-corner-all" style="margin: 0px;" name="rulemod_gray_application" id="rulemod_gray_application" title="Values will be transformed for this application">
			<option value="0" selected>--</option>
		<?php
			// get list of data types for menu
			$apps_sql = sprintf("SELECT DISTINCT id, app_name FROM %svocab_app ORDER BY app_name;", $emsaDbSchemaPrefix);
			$apps_rs = @pg_query($host_pa, $apps_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.", true);
			while ($apps_row = pg_fetch_object($apps_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($apps_row->id), htmlentities($apps_row->app_name));
			}
			pg_free_result($apps_rs);
		?>
		</select><br><br>
		<div class="h3">Match from Graylist If...</div>
		<div id="rulemod_gray_condition_container"></div>
		
		<input type="hidden" name="rulemod_gray_id" id="rulemod_gray_id" />
		<input type="hidden" name="focus_id" id="rulemod_gray_focus_id" />
		<input type="hidden" name="rulemod_gray_action" id="rulemod_gray_action" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
	</form>
</div>
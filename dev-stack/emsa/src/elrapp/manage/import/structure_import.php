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

try {
    $sql = "SELECT id, ui_name FROM structure_labs 
            WHERE alias_for = 0 ORDER BY ui_name;";
    $stmt = $adminDbConn->query($sql);
    $labList = $stmt->fetchAll();
    $stmt = null;
} catch (Throwable $e) {
    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to load list of facilities', true);
}
?>

<script>
    $(function() {
        $("#upload_button").button({
            icon: "ui-icon-elrsave"
        }).on("click", function() {
            $("#import_uploader").trigger("submit");
        });

        $("#vocab_type_master").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_childmirth").on("click", function() {
            $("#vocab_child_lab").show('fast');
        });
    });
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrimport"></span>Import New Structure</h1>

<div class="import_widget ui-widget" style="font-family: 'Open Sans', Arial, Helvetica;">
    <div>
        <p>To mass-import a new structure definition, select the Excel workbook below and click <em>Import New Structure</em>.</p>
    </div>
    <?php \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("<strong>Warning:</strong>  This upload will replace ALL existing structure definitions stored in the database for the selected structure type.  This action cannot be undone.  Please ensure that all definitions are accurate & complete before continuing.</p>"); ?>
</div>

<div class="import_widget ui-widget ui-widget-content ui-corner-all" style="padding: 5px; font-family: 'Open Sans', Arial, Helvetica;">
    <form name="import_uploader" id="import_uploader" method="POST" enctype="multipart/form-data">
        <fieldset style="margin-bottom: 20px;">
            <legend class="emsa_form_heading">Which structure type are you uploading?</legend>
            <input type="radio" name="vocab_type" id="vocab_type_childmirth" value="childmirth"> <label for="vocab_type_childmirth">Lab-specific HL7 XML Structure (Specify Lab) <code>[&lt;LabName&gt;_HL7_Structure.xls]</code></label><br>

            <label class="sr-only" for="vocab_child_lab">Choose Reporter</label>
            <select class="ui-corner-all" name="vocab_child_lab" id="vocab_child_lab" style="display: none; margin-left: 25px;">
                <option value="" selected>--</option>
                <?php
                foreach ($labList as $lab) {
                    echo '<option value="' . intval($lab['id']) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($lab['ui_name']) . '</option>';
                }
                ?>
            </select>
        </fieldset>

        <p><label for="vocab_source" class="emsa_form_heading">Select an Excel workbook to import:</label></p>
        <p style="margin-bottom: 20px;"><input type="file" name="vocab_source" id="vocab_source" class="ui-corner-all"></p>
        <p><button id="upload_button">Import New Structure</button></p>
        <input type="hidden" name="import_flag" id="import_flag" value="1">
        <input type="hidden" name="selected_page" id="selected_page" value="6">
        <input type="hidden" name="submenu" id="submenu" value="4">
        <input type="hidden" name="cat" id="cat" value="2">
        <input type="hidden" name="subcat" id="subcat" value="7">
    </form>
</div>
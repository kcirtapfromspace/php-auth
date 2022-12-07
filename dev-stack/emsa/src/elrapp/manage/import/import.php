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
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Authenticator::userHasPermission(Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$labList = CoreUtils::getReporterList($adminDbConn);

?>

<script>
    $(function() {
        $("#upload_button").button({
            icon: "ui-icon-elrsave"
        }).on("click", function() {
            $("#import_uploader").trigger("submit");
        });

        $("#vocab_type_mastervocab").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_trisano").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_master").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_master_icd").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_master_pfge").on("click", function() {
            $("#vocab_child_lab").hide('fast');
        });

        $("#vocab_type_child").on("click", function() {
            $("#vocab_child_lab").show('fast');
        });

        $("#vocab_type_childvocab").on("click", function() {
            $("#vocab_child_lab").show('fast');
        });
    });
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrimport"></span>Import New Vocabulary</h1>

<div class="import_widget ui-widget" style="font-family: 'Open Sans', Arial, Helvetica, sans-serif;">
    <div>
        <p>To mass-import a new vocabulary definition, select the Excel workbook below and click <em>Import New Vocabulary</em>.
    </div>
    <?php DisplayUtils::drawHighlight("<strong>Warning:</strong>  This upload will replace ALL existing vocabulary definitions stored in the database for the selected vocabulary type.  This action cannot be undone.  Please ensure that all definitions are accurate & complete before continuing.</p>"); ?>
</div>

<div class="import_widget ui-widget ui-widget-content ui-corner-all" style="padding: 5px; font-family: 'Open Sans', Arial, Helvetica, sans-serif;">
    <form name="import_uploader" id="import_uploader" method="POST" enctype="multipart/form-data">
        <fieldset style="margin-bottom: 20px;">
            <legend class="emsa_form_heading">Which vocabulary type are you uploading?</legend>
            <input type="radio" name="vocab_type" id="vocab_type_mastervocab" value="mastervocab"> <label for="vocab_type_mastervocab">Master Dictionary <code>[Master_Value_Set.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_trisano" value="trisano"> <label for="vocab_type_trisano">EpiTrax Dictionary <code>[Application_Value_Set.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_master" value="master"> <label for="vocab_type_master">Master LOINC/Condition/SNOMED <code>[MasterLoinc.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_master_icd" value="master-icd"> <label for="vocab_type_master_icd">ICD Codes <code>[ICD_Codes.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_master_pfge" value="master-pfge"> <label for="vocab_type_master_pfge">PulseNet Serotype (PFGE) Codes <code>[PulseNet_Codes.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_childvocab" value="childvocab"> <label for="vocab_type_childvocab">Child Dictionary (Specify Reporter) <code>[&lt;LabName&gt;_Child_Values.xls]</code></label><br>
            <input type="radio" name="vocab_type" id="vocab_type_child" value="child"> <label for="vocab_type_child">Child LOINC/SNOMED (Specify Reporter) <code>[&lt;LabName&gt;_Child_Values.xls]</code></label><br>

            <label class="sr-only" for="vocab_child_lab">Choose Reporter</label>
            <select class="ui-corner-all" name="vocab_child_lab" id="vocab_child_lab" style="display: none; margin-left: 25px;">
                <option value="" selected>--</option>
                <?php
                foreach ($labList as $labId => $labName) {
                    echo '<option value="' . (int) $labId . '">' . DisplayUtils::xSafe($labName) . '</option>';
                }
                ?>
            </select>
        </fieldset>

        <p><label for="vocab_source" class="emsa_form_heading">Select an Excel workbook to import:</label></p>
        <p style="margin-bottom: 20px;"><input type="file" name="vocab_source" id="vocab_source" class="ui-corner-all"></p>
        <p><button id="upload_button">Import New Vocabulary</button></p>
        <input type="hidden" name="import_flag" id="import_flag" value="1">
        <input type="hidden" name="selected_page" id="selected_page" value="6">
        <input type="hidden" name="submenu" id="submenu" value="3">
        <input type="hidden" name="cat" id="vocab" value="3">
        <input type="hidden" name="subcat" id="subcat" value="6">
    </form>
</div>
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

$clean = array();

if (isset($_REQUEST['condition_id']) && !EmsaUtils::emptyTrim($_REQUEST['condition_id'])) {
    $clean['condition_id'] = intval($_REQUEST['condition_id']);
}
?>

<script type="text/javascript">
    var jsMainUrl = "<?php echo trim($webappBaseUrl); ?>";
    var jsCat = <?php echo intval($navCat); ?>;
    var jsSubcat = <?php echo intval($navSubcat); ?>;

    $(function() {
        $("#rbc_change").button({
            icon: "ui-icon-elrretry"
        }).on("click", function () {
            $("#rbc_change_condition").trigger("submit");
        });
    });
</script>
<script type="text/javascript" src="<?php echo $webappBaseUrl; ?>js/vocab_ajax.js"></script>

<h1 class="elrhdg" style="display: inline;">
    <span class="ui-icon ui-icon-header ui-icon-emsadictionary"></span>
    Manage Rules for 
<?php
$sql = 'SELECT mc.c_id AS id, mv.concept AS label
        FROM vocab_master_condition mc
        INNER JOIN vocab_master_vocab mv ON (mv.id = mc.condition)
        ORDER BY mv.concept;';
$stmt = $adminDbConn->prepare($sql);
$stmt->execute();

echo '<form style="display: inline;" id="rbc_change_condition" method="POST" action="' . $webappBaseUrl . '?selected_page=' . intval($navSelectedPage) . '&submenu=' . intval($navSubmenu) . '&cat=' . intval($navCat) . '&subcat=' . intval($navSubcat) . '">' . "\n";
echo '<label for="condition_id" class="sr-only">Select a condition</label><select name="condition_id" id="condition_id" class="ui-corner-all vocab_rbc_bigselect">' . "\n";

if (!isset($clean['condition_id']) || EmsaUtils::emptyTrim($clean['condition_id'])) {
    echo "\t" . '<option value="-1" selected>[Select a condition]</option>' . "\n";
}
while ($row = $stmt->fetchObject()) {
    if ((isset($clean['condition_id'])) && (!EmsaUtils::emptyTrim($clean['condition_id'])) && ($clean['condition_id'] === intval($row->id))) {
        $clean['condition_name'] = addslashes(trim($row->label));
        echo "\t" . '<option value="' . intval($row->id) . '" selected>' . htmlspecialchars(trim($row->label)) . '</option>' . "\n";
    } else {
        echo "\t" . '<option value="' . intval($row->id) . '">' . htmlspecialchars(trim($row->label)) . '</option>' . "\n";
    }
}
echo '</select>';
echo '</form>';
?>
</h1>
<button type="button" id="rbc_change" title="Show selected condition">Select</button>

    <?php
    if (!isset($clean['condition_id']) || EmsaUtils::emptyTrim($clean['condition_id'])) {
        exit;
    }
    ?>

<div class="h3 blue">Whitelist/Graylist Rules</div>

<div class="rbc_widget ui-corner-all">
    <strong class="big_strong">Morbidity Whitelist Rule</strong>
    <div id="rbc_white_rule_status" style="display: inline-block;"><img alt="Retrieving Data" style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" /> Updating...</div>
    <div id="rbc_white_rule_ajax"></div>
</div>

<div class="rbc_widget ui-corner-all">
    <strong class="big_strong">Contact Whitelist Rule</strong>
    <div id="rbc_contact_rule_status" style="display: inline-block;"><img alt="Retrieving Data" style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" /> Updating...</div>
    <div id="rbc_contact_rule_ajax"></div>
</div>

<div class="rbc_widget ui-corner-all">
    <strong class="big_strong">Graylist Rules</strong>
    <div id="rbc_graylist_rule_status" style="display: inline-block;"><img alt="Retrieving Data" style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" /> Updating...</div>
    <div id="rbc_graylist_rule_ajax"></div>
</div>

<div class="h3 blue">Test-Specific Case Management Rules</div>

<?php
$lsql = 'SELECT ml.l_id AS l_id, ml.concept_name AS concept_name, ml.loinc AS loinc, mv.concept AS concept
         FROM vocab_master_loinc ml
         INNER JOIN vocab_master_vocab mv ON (mv.id = ml.trisano_test_type)
         WHERE ml.trisano_condition = :conditionId
         ORDER BY mv.concept, ml.loinc;';
$lstmt = $adminDbConn->prepare($lsql);
$lstmt->bindValue(':conditionId', $clean['condition_id'], PDO::PARAM_INT);

$lstmt->execute();

while ($lrow = $lstmt->fetchObject()) {
    echo '<div class="rbc_widget rbc_row ui-corner-all">';
    echo '<div class="rbc_widget rbc_inline ui-corner-all">';
    echo '<strong class="big_strong">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($lrow->concept) . '</strong>';
    echo '<div id="rbc_loinc_cmr_' . intval($lrow->l_id) . '_status" style="display: inline-block;"><img alt="Retrieving Data" style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" /> Updating...</div><br>';
    echo '<em style="color: dimgray;">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($lrow->concept_name) . '</em></div>';
    echo '<div class="rbc_widget rbc_inline ui-corner-all" id="rbc_loinc_cmr_' . intval($lrow->l_id) . '_ajax"></div>';
    echo '<script type="text/javascript">$(function() { drawRulesForCondition(' . VocabAjaxService::TABLE_RULES_CMR_LOINC . ', ' . intval($lrow->l_id) . ', \'' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($lrow->loinc)) . '\', \'rbc_loinc_cmr_' . intval($lrow->l_id) . '\'); });</script>';
    echo '</div>';
}
?>


<div class="h3 blue">Organism-Specific Case Management Rules</div>

<?php
$osql = 'SELECT mo.o_id AS o_id, mv.concept AS concept
         FROM vocab_master_organism mo
         INNER JOIN vocab_master_vocab mv ON (mv.id = mo.organism)
         WHERE mo.condition = :conditionId
         ORDER BY mv.concept;';
$ostmt = $adminDbConn->prepare($osql);
$ostmt->bindValue(':conditionId', $clean['condition_id'], PDO::PARAM_INT);

$ostmt->execute();

while ($orow = $ostmt->fetchObject()) {
    echo '<div class="rbc_widget rbc_row ui-corner-all">';
    echo '<div class="rbc_widget rbc_inline ui-corner-all">';
    echo '<strong class="big_strong">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($orow->concept) . '</strong>';
    echo '<div id="rbc_snomed_cmr_' . intval($orow->o_id) . '_status" style="display: inline-block;"><img alt="Retrieving Data" style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" /> Updating...</div></div>';
    echo '<div class="rbc_widget rbc_inline ui-corner-all" id="rbc_snomed_cmr_' . intval($orow->o_id) . '_ajax"></div>';
    echo '<script type="text/javascript">$(function() { drawRulesForCondition(' . VocabAjaxService::TABLE_RULES_CMR_SNOMED . ', ' . intval($orow->o_id) . ', \'' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($orow->concept)) . '\', \'rbc_snomed_cmr_' . intval($orow->o_id) . '\'); });</script>';
    echo '</div>';
}

echo VocabRuleDivBuilder::drawGraylist($adminDbConn);
echo VocabRuleDivBuilder::drawLoincCmr($adminDbConn);
echo VocabRuleDivBuilder::drawSnomedCmr($adminDbConn);
echo VocabRuleDivBuilder::drawSingleField();
?>

<div id="ajax_results_success_dialog" title="Success!"></div>
<div id="ajax_results_error_dialog" title="An Error Occurred"></div>

<script type="text/javascript">
    $(function() {
        drawTextField(<?php echo VocabAjaxService::TABLE_MASTER_CONDITION; ?>, 'white_rule', <?php echo $clean['condition_id']; ?>, 'rbc_white_rule');
        drawTextField(<?php echo VocabAjaxService::TABLE_MASTER_CONDITION; ?>, 'contact_white_rule', <?php echo $clean['condition_id']; ?>, 'rbc_contact_rule');
        drawRulesForCondition(<?php echo VocabAjaxService::TABLE_RULES_GRAYLIST; ?>, <?php echo $clean['condition_id']; ?>, '<?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($clean['condition_name']); ?>', 'rbc_graylist_rule');
        ruleBuilderJQueryUI();
    });
</script>
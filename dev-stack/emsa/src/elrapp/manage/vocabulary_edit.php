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

	##### set up some vocab-specific values for later use... #####
	$currentVocabEditItem = null;
	switch (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"])) {
		case 5:
			// Child LOINC
			$va_table = VocabAudit::TABLE_CHILD_LOINC;
			$currentVocabEditItem['id'] = "id";
            $currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_child_loinc", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_lab_id", "colname" => "lab_id", "validtype" => "list-required"),
					array("get_field" => "edit_archived", "colname" => "archived", "validtype" => "bool"),
					array("get_field" => "edit_child_loinc", "colname" => "child_loinc", "validtype" => "text-required"),
					array("get_field" => "edit_master_loinc", "colname" => "master_loinc", "validtype" => "list-required"),
					array("get_field" => "edit_child_orderable_test_code", "colname" => "child_orderable_test_code", "validtype" => "text"),
					array("get_field" => "edit_child_resultable_test_code", "colname" => "child_resultable_test_code", "validtype" => "text"),
					array("get_field" => "edit_child_concept_name", "colname" => "child_concept_name", "validtype" => "text"),
					array("get_field" => "edit_child_alias", "colname" => "child_alias", "validtype" => "text"),
					array("get_field" => "edit_workflow", "colname" => "workflow", "validtype" => "list"),
					array("get_field" => "edit_interpret_results", "colname" => "interpret_results", "validtype" => "bool"),
                    array("get_field" => "edit_interpret_override", "colname" => "interpret_override", "validtype" => "bool-nullable"),
                    array("get_field" => "edit_allow_preprocessing", "colname" => "allow_preprocessing", "validtype" => "bool"),
					array("get_field" => "edit_location", "colname" => "result_location", "validtype" => "list"),
					array("get_field" => "edit_units", "colname" => "units", "validtype" => "text"),
					array("get_field" => "edit_pregnancy", "colname" => "pregnancy", "validtype" => "bool"),
					array("get_field" => "edit_refrange", "colname" => "refrange", "validtype" => "text"), 
					array("get_field" => "edit_hl7_refrange", "colname" => "hl7_refrange", "validtype" => "text"), 
                    array("get_field" => "edit_offscale_low_result", "colname" => "offscale_low_result", "validtype" => "list-required"), 
                    array("get_field" => "edit_offscale_high_result", "colname" => "offscale_high_result", "validtype" => "list-required"), 
                    array("get_field" => "edit_notes", "colname" => "admin_notes", "validtype" => "text")
				);
			break;
		case 4:
			// Child Organism
			$va_table = VocabAudit::TABLE_CHILD_SNOMED;
			$currentVocabEditItem['id'] = "id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_child_organism", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_lab_id", "colname" => "lab_id", "validtype" => "list-required"),
					array("get_field" => "edit_child_code", "colname" => "child_code", "validtype" => "text-required"),
					array("get_field" => "edit_organism", "colname" => "organism", "validtype" => "list"),
					array("get_field" => "edit_test_result_id", "colname" => "test_result_id", "validtype" => "list"),
					array("get_field" => "edit_result_value", "colname" => "result_value", "validtype" => "text"),
					array("get_field" => "edit_comment", "colname" => "comment", "validtype" => "text"),
                    array("get_field" => "edit_notes", "colname" => "admin_notes", "validtype" => "text")
				);
			break;
		case 13:
			// Master PFGE to SNOMED
			$va_table = VocabAudit::TABLE_MASTER_PFGE;
			$currentVocabEditItem['id'] = "id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsapfge';
			$currentVocabEditItem['table'] = sprintf("%svocab_pfge", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_pattern", "colname" => "pattern", "validtype" => "text-required"),
					array("get_field" => "edit_organism", "colname" => "master_snomed_id", "validtype" => "list")
				);
			break;
		case 14:
            // ICD Codes
			$va_table = VocabAudit::TABLE_MASTER_ICD;
			$currentVocabEditItem['id'] = "id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_icd", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_codeset", "colname" => "codeset_id", "validtype" => "list-required"),
                    array("get_field" => "edit_icd_code", "colname" => "code_value", "validtype" => "text-required"),
                    array("get_field" => "edit_code_description", "colname" => "code_description", "validtype" => "text"),
                    array("get_field" => "edit_master_condition", "colname" => "master_condition_id", "validtype" => "list-nullable"),
                    array("get_field" => "edit_master_snomed", "colname" => "master_snomed_id", "validtype" => "list-nullable"),
                    array("get_field" => "edit_allow_new_cmr", "colname" => "allow_new_cmr", "validtype" => "bool"),
                    array("get_field" => "edit_allow_update_cmr", "colname" => "allow_update_cmr", "validtype" => "bool"),
                    array("get_field" => "edit_is_surveillance", "colname" => "is_surveillance", "validtype" => "bool"),
                    array("get_field" => "edit_pregnancy_indicator", "colname" => "pregnancy_indicator", "validtype" => "bool"),
                    array("get_field" => "edit_pregnancy_status", "colname" => "pregnancy_status", "validtype" => "bool-nullable")
				);
			break;
        case 3:
			// Master Organism
			$va_table = VocabAudit::TABLE_MASTER_SNOMED;
			$currentVocabEditItem['id'] = "o_id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_master_organism", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_snomed_category", "colname" => "snomed_category", "validtype" => "list-required"),
					array("get_field" => "edit_condition", "colname" => "condition", "validtype" => "list"),
					array("get_field" => "edit_snomed", "colname" => "snomed", "validtype" => "text"),
					array("get_field" => "edit_snomed_alt", "colname" => "snomed_alt", "validtype" => "text"),
					array("get_field" => "edit_organism", "colname" => "organism", "validtype" => "list"),
					array("get_field" => "edit_list", "colname" => "list", "validtype" => "list"),
					array("get_field" => "edit_test_result", "colname" => "test_result", "validtype" => "list"),
                    array("get_field" => "edit_semi_auto_usage", "colname" => "semi_auto_usage", "validtype" => "bool-nullable"),
                    array("get_field" => "edit_notes", "colname" => "admin_notes", "validtype" => "text")
				);
			break;
		case 2:
			// Master Condition
			$va_table = VocabAudit::TABLE_MASTER_CONDITION;
			$currentVocabEditItem['id'] = "c_id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_master_condition", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_condition", "colname" => "condition", "validtype" => "list-required"),
					array("get_field" => "edit_disease_category", "colname" => "disease_category", "validtype" => "list-required"),
					array("get_field" => "edit_is_initial", "colname" => "is_initial", "validtype" => "bool"),
					array("get_field" => "edit_gateways", "colname" => "gateway_xref", "validtype" => "multi"),
					array("get_field" => "edit_check_xref_first", "colname" => "check_xref_first", "validtype" => "bool"),
					array("get_field" => "edit_whitelist_override", "colname" => "whitelist_override", "validtype" => "bool"),
					array("get_field" => "edit_whitelist_ignore_case_status", "colname" => "whitelist_ignore_case_status", "validtype" => "bool"),
					array("get_field" => "edit_allow_multi_assign", "colname" => "allow_multi_assign", "validtype" => "bool"),
                    array("get_field" => "edit_o2m_addcmr_exclusions", "colname" => "o2m_addcmr_exclusions", "validtype" => "multi"),
                    array("get_field" => "edit_valid_specimen", "colname" => "valid_specimen", "validtype" => "multi"),
					array("get_field" => "edit_invalid_specimen", "colname" => "invalid_specimen", "validtype" => "multi"),
                    array("get_field" => "edit_ignore_age_rule", "colname" => "ignore_age_rule", "validtype" => "text"),
					array("get_field" => "edit_white_rule", "colname" => "white_rule", "validtype" => "text"),
					array("get_field" => "edit_contact_white_rule", "colname" => "contact_white_rule", "validtype" => "text"),
					array("get_field" => "edit_immediate_notify", "colname" => "immediate_notify", "validtype" => "bool"),
					array("get_field" => "edit_require_specimen", "colname" => "require_specimen", "validtype" => "bool"),
					array("get_field" => "edit_notify_state", "colname" => "notify_state", "validtype" => "bool"),
                    array("get_field" => "edit_ast_multi_colony", "colname" => "ast_multi_colony", "validtype" => "bool"),
					array("get_field" => "edit_bypass_oos", "colname" => "bypass_oos", "validtype" => "bool"),
					array("get_field" => "edit_blacklist_preliminary", "colname" => "blacklist_preliminary", "validtype" => "bool"),
					array("get_field" => "edit_district_override", "colname" => "district_override", "validtype" => "list")
				);
			break;
		default:
			// Master LOINC
			$va_table = VocabAudit::TABLE_MASTER_LOINC;
			$currentVocabEditItem['id'] = "l_id";
			$currentVocabEditItem['header_icon'] = 'ui-icon-emsadictionary';
			$currentVocabEditItem['table'] = sprintf("%svocab_master_loinc", $emsaDbSchemaPrefix);
			$currentVocabEditItem['fields'] = array(
					array("get_field" => "edit_loinc", "colname" => "loinc", "validtype" => "text-required"),
					array("get_field" => "edit_concept_name", "colname" => "concept_name", "validtype" => "text"),
					array("get_field" => "edit_antimicrobial_agent", "colname" => "antimicrobial_agent", "validtype" => "list"),
					array("get_field" => "edit_condition_from_result", "colname" => "condition_from_result", "validtype" => "bool"),
					array("get_field" => "edit_trisano_condition", "colname" => "trisano_condition", "validtype" => "list"),
					array("get_field" => "edit_organism_from_result", "colname" => "organism_from_result", "validtype" => "bool"),
					array("get_field" => "edit_trisano_organism", "colname" => "trisano_organism", "validtype" => "list"),
					array("get_field" => "edit_trisano_test_type", "colname" => "trisano_test_type", "validtype" => "list-required"),
					array("get_field" => "edit_specimen_source", "colname" => "specimen_source", "validtype" => "list"),
					array("get_field" => "edit_list", "colname" => "list", "validtype" => "list"),
                    array("get_field" => "edit_notes", "colname" => "admin_notes", "validtype" => "text")
				);
			break;
	}
	
	##### verify we've got a valid ID to edit #####
	unset($vocab_id);
	if (is_numeric($_GET['edit_id']) && (intval(trim($_GET['edit_id'])) > 0)) {
		$validedit_sql = sprintf("SELECT count(%s) AS id FROM %s WHERE %s = %d", $currentVocabEditItem['id'], $currentVocabEditItem['table'], $currentVocabEditItem['id'], intval(trim($_GET['edit_id'])));
		$validedit_count = @pg_fetch_result(@pg_query($host_pa, $validedit_sql), 0, id);
		if ($validedit_count == 1) {
			$vocab_id = intval(trim($_GET['edit_id']));
		}
	}
	
	if (!isset($vocab_id)) {
		\Udoh\Emsa\Utils\DisplayUtils::drawError("Cannot edit vocabulary:  Record not found", true);
	}
	
    if (isset($_POST['save_flag'])) {
        $va_prev_vals = $va->getPreviousVals($vocab_id, $va_table);
        $va_new_vals = array();
		foreach ($currentVocabEditItem['fields'] as $va_new_field) {
			$va_new_vals[$va_new_field['colname']] = isset($_POST[$va_new_field['get_field']]) ? $_POST[$va_new_field['get_field']] : null;
		}
		$va_prepared_new_vals = $va->prepareNewValues($va_table, $va_new_vals);
		
		##### save changes #####
		$changes_saved = TRUE;
		
		// verify all fields passed are valid types
		foreach ($currentVocabEditItem['fields'] as $new_field) {
			switch ($new_field['validtype']) {
				case "list-nullable":
                    if (isset($_POST[$new_field['get_field']]) && empty(trim($_POST[$new_field['get_field']]))) {
                        $changes_saved = $changes_saved && TRUE;
                    } elseif (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']]))) {
                        $changes_saved = $changes_saved && TRUE;
                    } else {
                        $changes_saved = $changes_saved && FALSE;
                    }
                    break;
                case "list-required":
					if (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']])) && (intval(trim($_POST[$new_field['get_field']])) > 0)):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
				case "list":
					if (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']]))):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
				case "text":
					if (isset($_POST[$new_field['get_field']])):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
				case "text-required":
					if (isset($_POST[$new_field['get_field']]) && ctype_print(trim($_POST[$new_field['get_field']])) && (strlen(trim($_POST[$new_field['get_field']])) > 0)):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
				case "multi":
					$changes_saved = $changes_saved && TRUE;
					break;
				case "bool-nullable":
					if (isset($_POST[$new_field['get_field']]) && ctype_lower(trim($_POST[$new_field['get_field']])) && ((trim($_POST[$new_field['get_field']]) == "t") || (trim($_POST[$new_field['get_field']]) == "f") || (trim($_POST[$new_field['get_field']]) == "u"))):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
                case "bool":
					if (isset($_POST[$new_field['get_field']]) && ctype_lower(trim($_POST[$new_field['get_field']])) && ((trim($_POST[$new_field['get_field']]) == "t") || (trim($_POST[$new_field['get_field']]) == "f"))):
						$changes_saved = $changes_saved && TRUE;
					else:
						$changes_saved = $changes_saved && FALSE;
					endif;
					break;
			}
		}
		
		if ($changes_saved) {
			$edit_sql = sprintf("UPDATE %s SET ", $currentVocabEditItem['table']);
			foreach ($currentVocabEditItem['fields'] as $new_fieldval) {
				$edit_sql .= $new_fieldval['colname'] . " = ";
				switch ($new_fieldval['validtype']) {
					case "list":
					case "list-required":
						//$edit_sql .= intval($_POST[$new_fieldval['get_field']]) . ", ";
                        $edit_sql .= filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_NUMBER_INT) . ", ";
						break;
					case "list-nullable":
                        if ((!isset($_POST[$new_fieldval['get_field']])) || (is_null($_POST[$new_fieldval['get_field']])) || (empty(trim($_POST[$new_fieldval['get_field']])))) {
                            $edit_sql .= 'NULL, ';
                        } else {
                            //$edit_sql .= intval($_POST[$new_fieldval['get_field']]) . ', ';
                            $edit_sql .= filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_NUMBER_INT) . ', ';
                        }
                        break;
                    case "text":
					case "text-required":
						$edit_sql .= ((strlen(trim($_POST[$new_fieldval['get_field']])) > 0) ? "'".pg_escape_string(trim($_POST[$new_fieldval['get_field']]))."'" : "NULL") . ", ";
						break;
                    case "bool-nullable":
                        if (isset($_POST[$new_fieldval['get_field']]) && (filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_STRING) == 't')) {
                            $edit_sql .= "'t', ";
                        } elseif (isset($_POST[$new_fieldval['get_field']]) && (filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_STRING) == 'f')) {
                            $edit_sql .= "'f', ";
                        } else {
                            $edit_sql .= "NULL, ";
                        }
                        break;
                    case "bool":
						$edit_sql .= ((trim($_POST[$new_fieldval['get_field']]) == "t") ? "'t'" : "'f'") . ", ";
						break;
					case "multi":
						$multi_string = '';
						if (isset($_POST[$new_fieldval['get_field']]) && is_array($_POST[$new_fieldval['get_field']]) && (count($_POST[$new_fieldval['get_field']]) > 0)) {
							$multi_string = implode(";", $_POST[$new_fieldval['get_field']]);
						}
						$edit_sql .= ((strlen(trim($multi_string)) > 0) ? "'".pg_escape_string(trim($multi_string))."'" : "NULL") . ", ";
						break;
				}
			}
			$edit_sql = substr($edit_sql, 0, -2);
			$edit_sql .= sprintf(" WHERE %s = %d;", $currentVocabEditItem['id'], $vocab_id);
			
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight(sprintf("%s updated successfully!", \Udoh\Emsa\Utils\DisplayUtils::xSafe($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], 'UTF-8', false)), "ui-icon-elrsuccess");
				$va->resetAudit();
				$va->setOldVals($va_prev_vals);
				$va->setNewVals($va_prepared_new_vals);
				$va->auditVocab($vocab_id, $va_table, VocabAudit::ACTION_EDIT);
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError(sprintf("Could not save changes to %s.", \Udoh\Emsa\Utils\DisplayUtils::xSafe($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], 'UTF-8', false)));
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError(sprintf("Could not save changes to %s:  Some values were missing/invalid", \Udoh\Emsa\Utils\DisplayUtils::xSafe($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], 'UTF-8', false)));
		}
		
	} else {
		##### draw edit form #####
	?>
		<script>
			var unsavedChanges = false;
			window.onbeforeunload = function(e) {
				if (unsavedChanges) {
					return 'Changes to this vocabulary item not saved!';
				}
			};
			
			$(function() {
				$(".vocab_filter_selectall").on("click", function() {
					var thisFilter = $(this).attr("rel");
					$("div.addnew_lookup_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
						if (!$(this).is(':checked')) {
							$(this).trigger('click');
						}
					});
				});
				
				$(".vocab_filter_selectnone").on("click", function() {
					var thisFilter = $(this).attr("rel");
					$("div.addnew_lookup_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
						if ($(this).is(':checked')) {
							$(this).trigger('click');
						}
					});
				});
				
				$("#edit_vocab_form input").on("change", function() {
					unsavedChanges = true;
				});
				
				$("#edit_vocab_form select").on("change", function() {
					unsavedChanges = true;
				});
				
				$("#edit_vocab_form textarea").on("change", function() {
					unsavedChanges = true;
				});
				
				$("#edit_cancel").button({
					icon: "ui-icon-elrcancel"
				}).on("click", function(e) {
					e.preventDefault();
					var cancelAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>&focus_id=<?php echo $vocab_id; ?>";
					window.location.href = cancelAction;
				});
				
				$("#edit_savevocab").button({
					icon: "ui-icon-elrsave"
				}).on("click", function() {
					unsavedChanges = false;
					return true;
				});
				
				$("#edit_vocab_form").on("submit", function(e) {
					var incomplete_fields = 0;
					$(":input.required", this).each(function() {
						if ($(this).val() === '' || parseInt($(this).val()) === -1) {
							alert($('label[for="'+$(this).attr('id')+'"]').text()+' requires a value');
							incomplete_fields++;
						}
					});
					if (incomplete_fields > 0) {
						return false;
					} else {
						return true;
					}
				});
				
			<?php
				$editvals_sql = "SELECT ";
				foreach ($currentVocabEditItem['fields'] as $edit_field) {
					$editvals_sql .= $edit_field['colname'] . ", ";
				}
				$editvals_sql = substr($editvals_sql, 0, -2);
				$editvals_sql .= sprintf(" FROM %s WHERE %s = %d", $currentVocabEditItem['table'], $currentVocabEditItem['id'], $vocab_id);
				$editvals_row = @pg_fetch_object(@pg_query($host_pa, $editvals_sql));
				
				foreach ($currentVocabEditItem['fields'] as $load_field) {
					if ($load_field['validtype'] == "multi") {
					    // this now handled via arrays that are passed to AccessibleMultiselectListbox constructors, not jQuery
						$multi_vals = explode(";", $editvals_row->{$load_field['colname']});
						if (is_array($multi_vals) && (count($multi_vals) > 0)) {
							foreach ($multi_vals as $multi_val) {
                                ${"selectedOptions__" . $load_field['get_field']}[] = $multi_val;
							}
						}
                    } elseif ($load_field['validtype'] == "bool-nullable") {
                        if ($editvals_row->{$load_field['colname']} == 't') {
                            printf("$(\"#%s\").val('t');\n", $load_field['get_field']);
                        } elseif ($editvals_row->{$load_field['colname']} == 'f') {
                            printf("$(\"#%s\").val('f');\n", $load_field['get_field']);
                        } else {
                            printf("$(\"#%s\").val('u');\n", $load_field['get_field']);
                        }
					} else {
						printf("$(\"#%s\").val(%s);\n", $load_field['get_field'], json_encode($editvals_row->{$load_field['colname']}));
						printf("$(\"input:text#%s\").width('%dem');\n", $load_field['get_field'], (0.6*strlen($editvals_row->{$load_field['colname']})));
					}
				}
				
			?>
			});
		</script>
		
		<h1 class="elrhdg"><span class="ui-icon ui-icon-header <?php echo $currentVocabEditItem['header_icon']; ?>"></span><?php \Udoh\Emsa\Utils\DisplayUtils::xEcho($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], 'UTF-8', false); ?> Editor</h1>
		
		<div id="edit_form" class="edit_vocab_form ui-widget ui-widget-content ui-corner-all">
		<div style="clear: both;"><span class="emsa_form_heading">Edit <?php \Udoh\Emsa\Utils\DisplayUtils::xEcho($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], 'UTF-8', false); ?>:</span><br><br></div>
			<form id="edit_vocab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>&edit_id=<?php echo intval($vocab_id); ?>">
			
				<?php
					if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) {
						// Child LOINC
						#lab_id lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_lab_id\">Child Lab</label><br><select class=\"ui-corner-all required\" name=\"edit_lab_id\" id=\"edit_lab_id\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["lab"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#child_loinc text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_child_loinc\">Child Test Concept Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"edit_child_loinc\" id=\"edit_child_loinc\" /></div>";
						#archived y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_archived\">Archived?</label><br><select class=\"ui-corner-all\" name=\"edit_archived\" id=\"edit_archived\">\n";
						echo "<option value=\"-1\">--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\" selected>No</option>\n";
						echo "</select></div>";
						#master_loinc lookup (incl master concept name)
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_master_loinc\">Master Test Concept Code (LOINC)</label><br><select class=\"ui-corner-all required\" name=\"edit_master_loinc\" id=\"edit_master_loinc\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = sprintf("SELECT vml.l_id AS value, vml.loinc AS label, vml.concept_name AS concept FROM %svocab_master_loinc vml ORDER BY vml.loinc;", $emsaDbSchemaPrefix);
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s - (%s)</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->concept, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#workflow y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_workflow\">Message Workflow</label><br><select class=\"ui-corner-all\" name=\"edit_workflow\" id=\"edit_workflow\">\n";
						echo "<option value=\"".ENTRY_STATUS."\">Automated Processing</option>\n";
						echo "<option value=\"".SEMI_AUTO_STATUS."\">Semi-Automated Entry</option>\n";
						echo "<option value=\"".QA_STATUS."\" selected>QA Review</option>\n";
						echo "</select></div>";

						echo "<div class=\"add-form-divider\"></div>";
						#interpret_results y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_interpret_results\">Quantitative Result?</label><br><select class=\"ui-corner-all\" name=\"edit_interpret_results\" id=\"edit_interpret_results\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#interpret_override y/n/u
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_interpret_override\">Result Interpretation</label><br><select class=\"ui-corner-all\" name=\"edit_interpret_override\" id=\"edit_interpret_override\">\n";
                        echo "<option value=\"u\" selected>Set by OBX-2</option>\n";
                        echo "<option value=\"t\">Override Quantitative</option>\n";
                        echo "<option value=\"f\">Override Coded Entry</option>\n";
                        echo "</select></div>";
                        #allow_preprocessing y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_allow_preprocessing\">Preprocessor Concatenation?</label><br><select class=\"ui-corner-all\" name=\"edit_allow_preprocessing\" id=\"edit_allow_preprocessing\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#result location lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_location\">Result Location</label><br><select class=\"ui-corner-all\" name=\"edit_location\" id=\"edit_location\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["result_location"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list Result Locations.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#units text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_units\">Units</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_units\" id=\"edit_units\" /></div>";
						#refrange text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_refrange\">Reference Range</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_refrange\" id=\"edit_refrange\" /></div>";
						#hl7_refrange text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_hl7_refrange\">HL7 Reference Range</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_hl7_refrange\" id=\"edit_hl7_refrange\" /></div>";
						#offscale_low_result lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_offscale_low_result\">Off-scale Low Test Result</label><br><select class=\"ui-corner-all required\" name=\"edit_offscale_low_result\" id=\"edit_offscale_low_result\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["offscale_low_result"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of test results.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
                        #offscale_high_result lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_offscale_high_result\">Off-scale High Test Result</label><br><select class=\"ui-corner-all required\" name=\"edit_offscale_high_result\" id=\"edit_offscale_high_result\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["offscale_high_result"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of test results.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
                        #pregnancy y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_pregnancy\">Indicates Pregnancy?</label><br><select class=\"ui-corner-all\" name=\"edit_pregnancy\" id=\"edit_pregnancy\">\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\" selected>No</option>\n";
						echo "</select></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#--extended--
						echo "<div style=\"clear: both; margin-bottom: 10px;\"><span class=\"emsa_form_heading\">Extended Child LOINC Fields</span></div>";
						#child_orderable_test_code text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_child_orderable_test_code\">Child Orderable Test Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_child_orderable_test_code\" id=\"edit_child_orderable_test_code\" /></div>";
						#child_resultable_test_code text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_child_resultable_test_code\">Child Resultable Test Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_child_resultable_test_code\" id=\"edit_child_resultable_test_code\" /></div>";
						#child_concept_name text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_child_concept_name\">Child Concept Name</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_child_concept_name\" id=\"edit_child_concept_name\" /></div>";
						#child_alias text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_child_alias\">Alias</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_child_alias\" id=\"edit_child_alias\" /></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
                        #admin_notes text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_notes\">Admin Notes</label><br><textarea class=\"ui-corner-all\" name=\"edit_notes\" id=\"edit_notes\" ></textarea></div>";
						
					} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) {
						// Child Organism
						#lab_id lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_lab_id\">Child Lab</label><br><select class=\"ui-corner-all required\" name=\"edit_lab_id\" id=\"edit_lab_id\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["lab"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#child_code text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_child_code\">Child Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"edit_child_code\" id=\"edit_child_code\" /></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#organism lookup (incl master snomed)
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_organism\">Master Organism SNOMED</label><br><select class=\"ui-corner-all\" name=\"edit_organism\" id=\"edit_organism\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = 'SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label 
								FROM '.$emsaDbSchemaPrefix.'vocab_master_organism vmo 
								LEFT JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv ON (vmo.organism = mv.id) 
								INNER JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) 
								WHERE mv2.category = '.$emsaDbSchemaPrefix.'vocab_category_id(\'snomed_category\') AND mv2.concept = \'Organism\'
								ORDER BY mv.concept, vmo.snomed;';
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of organisms.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s [SNOMED %s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						echo "<div class=\"add-form-divider\"></div>";
						
						#test_result_id lookup (incl master snomed)
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_test_result_id\">Master Test Result SNOMED</label><br><select class=\"ui-corner-all\" name=\"edit_test_result_id\" id=\"edit_test_result_id\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = 'SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label 
								FROM '.$emsaDbSchemaPrefix.'vocab_master_organism vmo 
								LEFT JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv ON (vmo.organism = mv.id) 
								INNER JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) 
								WHERE mv2.category = '.$emsaDbSchemaPrefix.'vocab_category_id(\'snomed_category\') AND mv2.concept = \'Test Result\'
								ORDER BY mv.concept, vmo.snomed;';
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of organisms.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s [SNOMED %s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						echo "<div class=\"add-form-divider\"></div>";
						
						#result_value text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_result_value\">Result Value</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_result_value\" id=\"edit_result_value\" /></div>";
						#comment text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_comment\">Comments</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_comment\" id=\"edit_comment\" /></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
                        #admin_notes text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_notes\">Admin Notes</label><br><textarea class=\"ui-corner-all\" name=\"edit_notes\" id=\"edit_notes\" ></textarea></div>";
						
					} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 13) {
						// Master PFGE to SNOMED
						#pattern text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_pattern\">PulseNet Serotype Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"edit_pattern\" id=\"edit_pattern\" /></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#organism lookup (incl master snomed)
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_organism\">Master Organism SNOMED</label><br><select class=\"ui-corner-all\" name=\"edit_organism\" id=\"edit_organism\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = 'SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label 
								FROM '.$emsaDbSchemaPrefix.'vocab_master_organism vmo 
								LEFT JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv ON (vmo.organism = mv.id) 
								INNER JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) 
								WHERE mv2.category = '.$emsaDbSchemaPrefix.'vocab_category_id(\'snomed_category\') AND mv2.concept = \'Organism\'
								ORDER BY mv.concept, vmo.snomed;';
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of organisms.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s [SNOMED %s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						echo "<div class=\"add-form-divider\"></div>";
						
					} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 14) {
						// ICD Codes
                        #codeset lookup
                        echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"edit_codeset\">Coding System</label><br><select class=\"ui-corner-all required\" name=\"edit_codeset\" id=\"edit_codeset\">\n";
                        echo "<option value=\"-1\" selected>--</option>\n";
                            $lookup_qry = sprintf("SELECT id AS value, codeset_name AS label FROM %svocab_codeset ORDER BY codeset_name;", $emsaDbSchemaPrefix);
                            $lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Coding Systems.");
                            while ($lookup_row = @pg_fetch_object($lookup_rs)) {
                                echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
                            }
                            pg_free_result($lookup_rs);
                        echo "</select></div>";

                        #icd code text
                        echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"edit_icd_code\">ICD Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"edit_icd_code\" id=\"edit_icd_code\" /></div>";

                        #code description text
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_code_description\">Code Description</label><br><textarea class=\"ui-corner-all\" name=\"edit_code_description\" id=\"edit_code_description\"></textarea></div>";

                        echo "<div class=\"add-form-divider\"></div>";
                        #master_condition_id lookup
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_master_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"edit_master_condition\" id=\"edit_master_condition\">\n";
                        echo "<option value=\"\" selected>--</option>\n";
                            $lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["master_condition"]["lookupqry"];
                            $lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
                            while ($lookup_row = @pg_fetch_object($lookup_rs)) {
                                echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
                            }
                            pg_free_result($lookup_rs);
                        echo "</select></div>";

                        #master_snomed_id lookup
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_master_snomed\">Master Organism</label><br><select class=\"ui-corner-all\" name=\"edit_master_snomed\" id=\"edit_master_snomed\">\n";
                        echo "<option value=\"\" selected>--</option>\n";
                            $lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["master_snomed"]["lookupqry"];
                            $lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of organisms.");
                            while ($lookup_row = @pg_fetch_object($lookup_rs)) {
                                echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
                            }
                            pg_free_result($lookup_rs);
                        echo "</select></div>";

                        echo "<div class=\"add-form-divider\"></div>";
                        #allow_new_cmr y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_allow_new_cmr\">Create New CMRs?</label><br><select class=\"ui-corner-all\" name=\"edit_allow_new_cmr\" id=\"edit_allow_new_cmr\">\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\" selected>No</option>\n";
                        echo "</select></div>";
                        #allow_update_cmr y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_allow_update_cmr\">Update Existing CMRs?</label><br><select class=\"ui-corner-all\" name=\"edit_allow_update_cmr\" id=\"edit_allow_update_cmr\">\n";
                        echo "<option value=\"t\" selected>Yes</option>\n";
                        echo "<option value=\"f\">No</option>\n";
                        echo "</select></div>";
                        #is_surveillance y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_is_surveillance\">New CMRs Are Surveillance Events?</label><br><select class=\"ui-corner-all\" name=\"edit_is_surveillance\" id=\"edit_is_surveillance\">\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\" selected>No</option>\n";
                        echo "</select></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
                        #pregnancy_indicator y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_pregnancy_indicator\">Determines Pregnancy?</label><br><select class=\"ui-corner-all\" name=\"edit_pregnancy_indicator\" id=\"edit_pregnancy_indicator\">\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\" selected>No</option>\n";
                        echo "</select></div>";
                        #pregnancy_status y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_pregnancy_status\">Pregnancy Status</label><br><select class=\"ui-corner-all\" name=\"edit_pregnancy_status\" id=\"edit_pregnancy_status\">\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\" selected>No</option>\n";
                        echo "<option value=\"u\">Unknown</option>\n";
                        echo "</select></div>";
						
					} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 3) {
						// Master Organism
						#snomed_category lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_snomed_category\">SNOMED Type</label><br><select class=\"ui-corner-all required\" name=\"edit_snomed_category\" id=\"edit_snomed_category\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('snomed_category')ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of disease categories.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#snomed text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_snomed\">SNOMED Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_snomed\" id=\"edit_snomed\" /></div>";
						#snomed_alt text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_snomed_alt\">Secondary SNOMED Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_snomed_alt\" id=\"edit_snomed_alt\" /></div>";
						echo "<div class=\"add-form-divider\"></div>";
						#condition lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"edit_condition\" id=\"edit_condition\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["condition"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#organism lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_organism\">Type Concept Name</label><br><select class=\"ui-corner-all\" name=\"edit_organism\" id=\"edit_organism\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('organism') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						echo "<div class=\"add-form-divider\"></div>";
						
						#list lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_list\">List</label><br><select class=\"ui-corner-all\" name=\"edit_list\" id=\"edit_list\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["list"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of lists.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#test_result lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_test_result\">Test Result</label><br><select class=\"ui-corner-all\" name=\"edit_test_result\" id=\"edit_test_result\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["test_result"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of test results.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
                        #semi_auto_usage y/n/u
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_semi_auto_usage\">Semi-Auto Usage</label><br><select class=\"ui-corner-all\" name=\"edit_semi_auto_usage\" id=\"edit_semi_auto_usage\">\n";
                        echo "<option value=\"u\" selected>Allow Semi-Auto</option>\n";
                        echo "<option value=\"t\">Force Semi-Auto</option>\n";
                        echo "<option value=\"f\">Skip Semi-Auto</option>\n";
                        echo "</select></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
                        #admin_notes text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_notes\">Admin Notes</label><br><textarea class=\"ui-corner-all\" name=\"edit_notes\" id=\"edit_notes\" ></textarea></div>";
						
					} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 2) {
						// Master Condition
						#disease_category lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_disease_category\">CDC Category</label><br><select class=\"ui-corner-all required\" name=\"edit_disease_category\" id=\"edit_disease_category\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('disease_category') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of disease categories.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#condition type initial/final
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_is_initial\">Condition Type</label><br><select class=\"ui-corner-all required\" name=\"edit_is_initial\" id=\"edit_is_initial\">\n";
						echo "<option value=\"t\" selected>Initial</option>\n";
						echo "<option value=\"f\">Final</option>\n";
						echo "</select></div>";
						#condition lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_condition\">Condition</label><br><select class=\"ui-corner-all required\" name=\"edit_condition\" id=\"edit_condition\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('condition') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#valid_specimen multi
						echo "<div class=\"addnew_field\">";
						(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT id AS value, concept AS label FROM vocab_master_vocab WHERE category = vocab_category_id('specimen') ORDER BY concept;"), $selectedOptions__edit_valid_specimen ?? null))
                            ->render('Valid Specimen Sources', 'edit_valid_specimen', true);
						echo "</div>";
						#invalid_specimen multi
						echo "<div class=\"addnew_field\">";
						(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT id AS value, concept AS label FROM vocab_master_vocab WHERE category = vocab_category_id('specimen') ORDER BY concept;"), $selectedOptions__edit_invalid_specimen ?? null))
                            ->render('Invalid Specimen Sources', 'edit_invalid_specimen', true);
						echo "</div>";
						
						#ignore_age_rule text
						echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"edit_ignore_age_rule\">Ignore Older Than</label><br><textarea class=\"ui-corner-all\" name=\"edit_ignore_age_rule\" id=\"edit_ignore_age_rule\"></textarea></div>";
						#white_rule text
						echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"edit_white_rule\">Morbidity Whitelist Rules</label><br><textarea class=\"ui-corner-all\" name=\"edit_white_rule\" id=\"edit_white_rule\"></textarea></div>";
						#contact_white_rule text
						echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"edit_contact_white_rule\">Contact Whitelist Rules</label><br><textarea class=\"ui-corner-all\" name=\"edit_contact_white_rule\" id=\"edit_contact_white_rule\"></textarea></div>";
						#gateway_xref text
						echo "<div class=\"addnew_field\">";
						$gateway_xref_qry = 'SELECT mc.c_id AS value, mv.concept AS label
                            FROM vocab_master_condition mc
                            INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
                            ORDER BY mv.concept;';
						(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $gateway_xref_qry), $selectedOptions__edit_gateways ?? null))
                            ->render('Whitelist Crossrefs', 'edit_gateways');
						echo "</div>";
                        #o2m_addcmr_exclusions text
                        echo "<div class=\"addnew_field\">";
                        $o2m_addcmr_exclusions_qry = 'SELECT mc.c_id AS value, mv.concept AS label
                            FROM vocab_master_condition mc
                            INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
                            ORDER BY mv.concept;';
                        (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $o2m_addcmr_exclusions_qry), $selectedOptions__edit_o2m_addcmr_exclusions ?? null))
                            ->render('(One-to-Many) Add CMR If Not Found', 'edit_o2m_addcmr_exclusions');
                        echo "</div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#check_xref_first y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_check_xref_first\">Check Crossrefs First?</label><br><select class=\"ui-corner-all\" name=\"edit_check_xref_first\" id=\"edit_check_xref_first\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#whitelist_ignore_case_status y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_whitelist_ignore_case_status\">Whitelist Rules Ignore State Case Status?</label><br><select class=\"ui-corner-all\" name=\"edit_whitelist_ignore_case_status\" id=\"edit_whitelist_ignore_case_status\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#whitelist_override y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_whitelist_override\">Override Target Whitelist Rules?</label><br><select class=\"ui-corner-all\" name=\"edit_whitelist_override\" id=\"edit_whitelist_override\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#allow_multi_assign y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_allow_multi_assign\">Allow One-to-Many?</label><br><select class=\"ui-corner-all\" name=\"edit_allow_multi_assign\" id=\"edit_allow_multi_assign\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
                        #ast_multi_colony y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_ast_multi_colony\">Allow Multi-Colony AST?</label><br><select class=\"ui-corner-all\" name=\"edit_ast_multi_colony\" id=\"edit_ast_multi_colony\">\n";
                        echo "<option value=\"-1\" selected>--</option>\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\">No</option>\n";
                        echo "</select></div>";
                        #bypass_oos y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_bypass_oos\">Bypass OOS Queue?</label><br><select class=\"ui-corner-all\" name=\"edit_bypass_oos\" id=\"edit_bypass_oos\">\n";
                        echo "<option value=\"-1\" selected>--</option>\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\">No</option>\n";
                        echo "</select></div>";
                        #blacklist_preliminary y/n
                        echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_blacklist_preliminary\">Blacklist Preliminary Results?</label><br><select class=\"ui-corner-all\" name=\"edit_blacklist_preliminary\" id=\"edit_blacklist_preliminary\">\n";
                        echo "<option value=\"-1\" selected>--</option>\n";
                        echo "<option value=\"t\">Yes</option>\n";
                        echo "<option value=\"f\">No</option>\n";
                        echo "</select></div>";
                        #immediate_notify y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_immediate_notify\">Immediately Notifiable?</label><br><select class=\"ui-corner-all required\" name=\"edit_immediate_notify\" id=\"edit_immediate_notify\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#require_specimen y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_require_specimen\">Require Specimen Source<br>From Nominal Culture?</label><br><select class=\"ui-corner-all required\" name=\"edit_require_specimen\" id=\"edit_require_specimen\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#notify_state y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_notify_state\">Notify State Upon Receipt?</label><br><select class=\"ui-corner-all required\" name=\"edit_notify_state\" id=\"edit_notify_state\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
						echo "<option value=\"t\">Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#district_override lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_district_override\">Jurisdiction Override</label><br><select class=\"ui-corner-all\" name=\"edit_district_override\" id=\"edit_district_override\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["district_override"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of jurisdictions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						
					} else {
						// Master LOINC
						#loinc text
						echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"edit_loinc\">Test Concept Code (LOINC)</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"edit_loinc\" id=\"edit_loinc\" /></div>";
						#concept_name text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_concept_name\">Preferred Concept Name</label><br><textarea class=\"ui-corner-all\" name=\"edit_concept_name\" id=\"edit_concept_name\"></textarea></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
                        #antimicrobial_agent lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_antimicrobial_agent\">Antimicrobial Agent</label><br><select class=\"ui-corner-all\" name=\"edit_antimicrobial_agent\" id=\"edit_antimicrobial_agent\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["antimicrobial_agent"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Antimicrobial Agents.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
						#condition_from_result y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_condition_from_result\">Look Up Condition?</label><br><select class=\"ui-corner-all\" name=\"edit_condition_from_result\" id=\"edit_condition_from_result\">\n";
						echo "<option value=\"t\" selected>Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#condition lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_trisano_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"edit_trisano_condition\" id=\"edit_trisano_condition\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_condition"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of conditions.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#organism_from_result y/n
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_organism_from_result\">Look Up Organism?</label><br><select class=\"ui-corner-all\" name=\"edit_organism_from_result\" id=\"edit_organism_from_result\">\n";
						echo "<option value=\"t\" selected>Yes</option>\n";
						echo "<option value=\"f\">No</option>\n";
						echo "</select></div>";
						#organism lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_trisano_organism\">Master Organism</label><br><select class=\"ui-corner-all\" name=\"edit_trisano_organism\" id=\"edit_trisano_organism\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_organism"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of organisms.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						
						echo "<div class=\"add-form-divider\"></div>";
						#test_type lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"edit_trisano_test_type\">Test Type</label><br><select class=\"ui-corner-all required\" name=\"edit_trisano_test_type\" id=\"edit_trisano_test_type\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_test_type"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of test types.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#specimen_source lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_specimen_source\">Specimen Source</label><br><select class=\"ui-corner-all\" name=\"edit_specimen_source\" id=\"edit_specimen_source\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["specimen_source"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of specimen sources.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
						#list lookup
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_list\">List</label><br><select class=\"ui-corner-all\" name=\"edit_list\" id=\"edit_list\">\n";
						echo "<option value=\"-1\" selected>--</option>\n";
							$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["list"]["lookupqry"];
							$lookup_rs = @pg_query($host_pa, $lookup_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of lists.");
							while ($lookup_row = @pg_fetch_object($lookup_rs)) {
								echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
							}
							pg_free_result($lookup_rs);
						echo "</select></div>";
                        
                        echo "<div class=\"add-form-divider\"></div>";
                        #admin_notes text
						echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"edit_notes\">Admin Notes</label><br><textarea class=\"ui-corner-all\" name=\"edit_notes\" id=\"edit_notes\" ></textarea></div>";
						
					}
				?>
			
				<input type="hidden" name="save_flag" value="1" />
				<br><br><button type="submit" name="edit_savevocab" id="edit_savevocab">Save Changes</button>
				<button type="button" name="edit_cancel" id="edit_cancel">Cancel</button>
			</form>
		</div>
		
	<?php
		########## Show dependencies/child items for selected vocabulary item ##########
		if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) < 4) {
	?>
		<div id="dependencies" class="edit_vocab_form ui-widget ui-widget-content ui-corner-all">
		<?php
			if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 1) {
				// Master LOINC dependents
		?>
				<div style="clear: both;"><span class="emsa_form_heading">Dependent Child LOINCs...</span></div>
					<table id="labResults">
						<thead>
							<tr><th>Child Lab</th><th>Child LOINC</th></tr>
						</thead>
						<tbody>
		<?php
				$dependent_sql = sprintf("SELECT vcl.id AS id, sl.ui_name AS lab_name, vcl.child_loinc AS child_loinc 
					FROM %svocab_child_loinc vcl JOIN %sstructure_labs sl ON (vcl.lab_id = sl.id)
					WHERE vcl.master_loinc = %d
					ORDER BY sl.ui_name, vcl.child_loinc",
					$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $vocab_id);
				$dependent_rs = @pg_query($host_pa, $dependent_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve Child LOINCs.");
				if (@pg_num_rows($dependent_rs) < 1) {
					echo "<tr><td colspan=\"4\"><em>No records found</em></td></tr>";
				}
				while ($dependent_row = @pg_fetch_object($dependent_rs)) {
					printf("<tr><td>%s</td><td><a class=\"vocab_xref\" title=\"View/Edit\" href=\"%s\">%s</a></td></tr>",
						$dependent_row->lab_name,
						($webappBaseUrl . sprintf("?selected_page=%s&submenu=%s&cat=%d&subcat=%d&edit_id=%s", $navSelectedPage, $navSubmenu, 2, 5, $dependent_row->id)),
						$dependent_row->child_loinc);
				}
				@pg_free_result($dependent_rs);
				echo "<tbody></table>";
				
			} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 2) {
				// Master Condition dependents
		?>
				<div style="clear: both;"><span class="emsa_form_heading">Dependent Master LOINCs...</span></div>
					<table id="labResults">
						<tbody>
		<?php
				$dependent_sql = sprintf("SELECT vml.l_id AS id, vml.loinc AS loinc, vml.concept_name AS concept_name 
					FROM %svocab_master_loinc vml 
					WHERE vml.trisano_condition = %d ORDER BY vml.concept_name;",
					$emsaDbSchemaPrefix, $vocab_id);
				$dependent_rs = @pg_query($host_pa, $dependent_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve dependent Master LOINCs.");
				if (@pg_num_rows($dependent_rs) < 1) {
					echo "<tr><td><em>No records found</em></td></tr>";
				}
				while ($dependent_row = @pg_fetch_object($dependent_rs)) {
					printf("<tr><td><a class=\"vocab_xref\" title=\"View/Edit\" href=\"%s\">%s (LOINC %s)</a></td></tr>",
						($webappBaseUrl . sprintf("?selected_page=%s&submenu=%s&cat=%d&subcat=%d&edit_id=%s", $navSelectedPage, $navSubmenu, 1, 1, $dependent_row->id)),
						$dependent_row->concept_name,
						$dependent_row->loinc);
				}
				@pg_free_result($dependent_rs);
				echo "<tbody></table><br>";
		?>
				<div style="clear: both;"><span class="emsa_form_heading">Dependent Master Organisms...</span></div>
					<table id="labResults">
						<tbody>
		<?php
				$dependent_sql = sprintf("SELECT vmo.o_id AS id, mv.concept AS concept, vmo.snomed AS snomed 
					FROM %svocab_master_organism vmo
					LEFT JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id)
					WHERE vmo.condition = %d ORDER BY mv.concept, vmo.snomed;",
					$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $vocab_id);
				$dependent_rs = @pg_query($host_pa, $dependent_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve dependent Master Organisms.");
				if (@pg_num_rows($dependent_rs) < 1) {
					echo "<tr><td><em>No records found</em></td></tr>";
				}
				while ($dependent_row = @pg_fetch_object($dependent_rs)) {
					printf("<tr><td><a class=\"vocab_xref\" title=\"View/Edit\" href=\"%s\">%s (SNOMED %s)</a></td></tr>",
						($webappBaseUrl . sprintf("?selected_page=%s&submenu=%s&cat=%d&subcat=%d&edit_id=%s", $navSelectedPage, $navSubmenu, 1, 3, $dependent_row->id)),
						$dependent_row->concept,
						$dependent_row->snomed);
				}
				@pg_free_result($dependent_rs);
				echo "<tbody></table>";
		
			} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 3) {
				// Master Organism dependents
		?>
				<div style="clear: both;"><span class="emsa_form_heading">Dependent Master LOINCs...</span></div>
					<table id="labResults">
						<tbody>
		<?php
				$dependent_sql = sprintf("SELECT vml.l_id AS id, vml.loinc AS loinc, vml.concept_name AS concept_name 
					FROM %svocab_master_loinc vml 
					WHERE vml.trisano_organism = %d ORDER BY vml.concept_name;",
					$emsaDbSchemaPrefix, $vocab_id);
				$dependent_rs = @pg_query($host_pa, $dependent_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve dependent Master LOINCs.");
				if (@pg_num_rows($dependent_rs) < 1) {
					echo "<tr><td><em>No records found</em></td></tr>";
				}
				while ($dependent_row = @pg_fetch_object($dependent_rs)) {
					printf("<tr><td><a class=\"vocab_xref\" title=\"View/Edit\" href=\"%s\">%s (LOINC %s)</a></td></tr>",
						($webappBaseUrl . sprintf("?selected_page=%s&submenu=%s&cat=%d&subcat=%d&edit_id=%s", $navSelectedPage, $navSubmenu, 1, 1, $dependent_row->id)),
						$dependent_row->concept_name,
						$dependent_row->loinc);
				}
				@pg_free_result($dependent_rs);
				echo "<tbody></table><br>";
		?>
				<div style="clear: both;"><span class="emsa_form_heading">Child Organisms...</span></div>
					<table id="labResults">
						<thead>
							<tr><th>Child Lab</th><th>Child Code</th></tr>
						</thead>
						<tbody>
		<?php
				$dependent_sql = sprintf("SELECT vco.id AS id, sl.ui_name AS lab_name, vco.child_code AS child_code
					FROM %svocab_child_organism vco JOIN %sstructure_labs sl ON (vco.lab_id = sl.id)
					WHERE (vco.organism = %d) OR (vco.test_result_id = %d)
					ORDER BY sl.ui_name, vco.child_code",
					$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $vocab_id, $vocab_id);
				$dependent_rs = @pg_query($host_pa, $dependent_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve Child LOINCs.");
				if (@pg_num_rows($dependent_rs) < 1) {
					echo "<tr><td colspan=\"2\"><em>No records found</em></td></tr>";
				}
				while ($dependent_row = @pg_fetch_object($dependent_rs)) {
					printf("<tr><td>%s</td><td><a class=\"vocab_xref\" title=\"View/Edit\" href=\"%s\">%s</a></td></tr>",
						$dependent_row->lab_name,
						($webappBaseUrl . sprintf("?selected_page=%s&submenu=%s&cat=%d&subcat=%d&edit_id=%s", $navSelectedPage, $navSubmenu, 2, 4, $dependent_row->id)),
						$dependent_row->child_code);
				}
				@pg_free_result($dependent_rs);
				echo "<tbody></table>";
		
			}
		?>
		</div>
		
	<?php
		}
		
	?>
	
	<div id="vocab_log" class="edit_vocab_form ui-widget ui-state-highlight ui-widget-content ui-corner-all">
		<div style="clear: both;"><span class="emsa_form_heading">Audit Log</span></div>
		<?php
			if ($va_table === VocabAudit::TABLE_CHILD_LOINC) {
				echo $va->displayVocabAuditById(intval($vocab_id), array($va_table, VocabAudit::TABLE_CHILD_TESTRESULT));
			} elseif ($va_table === VocabAudit::TABLE_MASTER_LOINC) {
				echo $va->displayVocabAuditById(intval($vocab_id), array($va_table, VocabAudit::TABLE_CMR_RULES));
			} elseif ($va_table === VocabAudit::TABLE_MASTER_SNOMED) {
				echo $va->displayVocabAuditById(intval($vocab_id), array($va_table, VocabAudit::TABLE_MS_CMR_RULES));
			} elseif ($va_table === VocabAudit::TABLE_MASTER_CONDITION) {
				echo $va->displayVocabAuditById(intval($vocab_id), array($va_table, VocabAudit::TABLE_GRAYLIST_RULES));
			} else {
				echo $va->displayVocabAuditById(intval($vocab_id), array($va_table));
			}
		?>
	</div>
	
	<?php
		
		##### don't show the rest of the Vocabulary page if 'edit' form is drawn #####
		exit();
	}
	
?>
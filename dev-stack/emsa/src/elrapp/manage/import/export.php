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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Udoh\Emsa\Import\ImportUtils;
use Udoh\Emsa\Import\VocabExportConstants;
use Udoh\Emsa\Import\VocabImportUtils;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	//set_time_limit(120);
	
	define('EXPORT_TIMESTAMP', date("YmdHis", time()));
	
	$vocabExportFilename = __DIR__ . '/upload/elr_vocabexport_'.strtolower(EXPORT_SERVERNAME).'_'.EXPORT_TIMESTAMP.'.zip';
	
	$vocabTableFiles = array();  // placeholder for filenames to be zipped
	$exportTableDefinitions = array(
		array(
			'filename' => __DIR__ . '/upload/Master_Value_Set__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls', 
			'sheets' => array(
				'Sheet1' => array(
					'query' => VocabExportConstants::EXPORT_QRY_MASTERVOCAB,
					'columns' => array(
						array(
							'width' => '25', 
							'label' => 'Category',
							'column' => 'category'
						), 
						array(
							'width' => '35', 
							'label' => 'Coding System', 
							'column' => 'codeset'
						), 
						array(
							'width' => '50', 
							'label' => 'Master Code', 
							'column' => 'concept'
						), 
						array(
							'width' => '40', 
							'label' => 'Last Updated', 
							'column' => 'last_updated'
						)
					)
				)
			), 
			'object' => new Spreadsheet()
		), 
		array(
			'filename' => __DIR__ . '/upload/Application_Value_Set__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls', 
			'sheets' => array(
				'Sheet1' => array(
					'query' => VocabExportConstants::EXPORT_QRY_APPVALUE,
					'columns' => array(
						array(
							'width' => '10', 
							'label' => 'App', 
							'column' => 'app_name'
						), 
						array(
							'width' => '25', 
							'label' => 'Category', 
							'column' => 'category'
						), 
						array(
							'width' => '50', 
							'label' => 'Master Value', 
							'column' => 'master_concept'
						), 
						array(
							'width' => '50', 
							'label' => 'App Value', 
							'column' => 'coded_value'
						), 
						array(
							'width' => '40', 
							'label' => 'Last Updated', 
							'column' => 'last_updated'
						)
					)
				)
			), 
			'object' => new Spreadsheet()
		), 
		array(
			'filename' => __DIR__ . '/upload/ICD_Codes__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls', 
			'sheets' => array(
				'Sheet1' => array(
					'query' => VocabExportConstants::EXPORT_QRY_ICD,
					'columns' => array(
						array(
							'width' => '15', 
							'label' => 'Codeset',
							'column' => 'codeset_name'
						), 
						array(
							'width' => '15', 
							'label' => 'ICD Code',
							'column' => 'code_value'
						), 
						array(
							'width' => '50', 
							'label' => 'Code Description', 
							'column' => 'code_description'
						), 
						array(
							'width' => '30', 
							'label' => 'Condition',
							'column' => 'condition'
						), 
						array(
							'width' => '30', 
							'label' => 'Organism SNOMED Code',
							'column' => 'organism_snomed'
						), 
						array(
							'width' => '30', 
							'label' => 'Organism Alt. SNOMED Code',
							'column' => 'organism_snomed_alt'
						), 
						array(
							'width' => '35', 
							'label' => 'Organism Name',
							'column' => 'organism'
						), 
						array(
							'width' => '20', 
							'label' => 'Create New CMR?',
							'column' => 'allow_new_cmr'
						), 
						array(
							'width' => '20', 
							'label' => 'Update CMR?',
							'column' => 'allow_update_cmr'
						), 
						array(
							'width' => '20', 
							'label' => 'Surveillance?',
							'column' => 'is_surveillance'
						), 
						array(
							'width' => '20', 
							'label' => 'Determines Pregnancy?',
							'column' => 'pregnancy_indicator'
						), 
						array(
							'width' => '20', 
							'label' => 'Pregnancy Status?',
							'column' => 'pregnancy_status'
						)
					)
				)
			), 
			'object' => new Spreadsheet()
		), 
		array(
			'filename' => __DIR__ . '/upload/PulseNet_Codes__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls', 
			'sheets' => array(
				'Sheet1' => array(
					'query' => VocabExportConstants::EXPORT_QRY_PFGE,
					'columns' => array(
						array(
							'width' => '25', 
							'label' => 'Serotype Code', 
							'column' => 'pattern'
						), 
						array(
							'width' => '30', 
							'label' => 'Organism SNOMED Code', 
							'column' => 'organism_snomed'
						), 
						array(
							'width' => '30', 
							'label' => 'Organism Alt. SNOMED Code', 
							'column' => 'organism_snomed_alt'
						), 
						array(
							'width' => '50', 
							'label' => 'Organism', 
							'column' => 'organism'
						)
					)
				)
			), 
			'object' => new Spreadsheet()
		), 
		array(
			'filename' => __DIR__ . '/upload/MasterLoinc__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls', 
			'sheets' => array(
				'Master Condition' => array(
					'query' => VocabExportConstants::EXPORT_QRY_ML_CONDITION,
					'columns' => array(
						array(
							'width' => '45', 
							'label' => 'Condition', 
							'column' => 'condition'
						), 
						array(
							'width' => '25', 
							'label' => 'Morbidity Whitelist Rules', 
							'column' => 'white_rule'
						), 
						array(
							'width' => '25', 
							'label' => 'Contact Whitelist Rules', 
							'column' => 'contact_white_rule'
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Condition Operator', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'operator',
							'segment' => 0
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Condition', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'operand',
							'segment' => 0, 
							'get_app_value' => true
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Test Type Operator', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'operator1',
							'segment' => 0
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Test Type', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'operand1',
							'segment' => 0, 
							'get_app_value' => true
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Collected Before', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'collect_lbound',
							'segment' => 0
						), 
						array(
							'width' => '10', 
							'label' => 'Graylist Rule Collected After', 
							'column' => 'graylist_rule_structured', 
							'decode' => 'collect_ubound',
							'segment' => 0
						), 
						array(
							'width' => '25', 
							'label' => 'Whitelist Crossrefs', 
							'column' => 'gateway_xref',
                            'helper_class' => '\Udoh\Emsa\Utils\VocabUtils', 
							'helper' => 'whitelistCrossrefNamesByIdList',
                            'delimiter' => '|'
						), 
						array(
							'width' => '15', 
							'label' => 'Check Crossrefs First?', 
							'column' => 'check_xref_first'
						), 
						array(
							'width' => '15', 
							'label' => 'Require Specimen Source from Nominal Culture', 
							'column' => 'require_specimen'
						), 
						array(
							'width' => '30', 
							'label' => 'Valid Specimen Sources', 
							'column' => 'valid_specimen'
						), 
						array(
							'width' => '30', 
							'label' => 'Invalid Specimen Sources', 
							'column' => 'invalid_specimen'
						), 
						array(
							'width' => '15', 
							'label' => 'Notify State Upon Receipt', 
							'column' => 'notify_state'
						), 
						array(
							'width' => '15', 
							'label' => 'Immediately Notifiable', 
							'column' => 'immediate_notify'
						), 
						array(
							'width' => '40', 
							'label' => 'Jurisdiction Override', 
							'column' => 'district_override'
						), 
						array(
							'width' => '25', 
							'label' => 'CDC Category', 
							'column' => 'disease_category'
						), 
						array(
							'width' => '25', 
							'label' => 'Condition Type', 
							'column' => 'condition_type'
						), 
						array(
							'width' => '40', 
							'label' => 'Last Updated', 
							'column' => 'last_updated'
						), 
						array(
							'width' => '25', 
							'label' => 'Ignore Older Than', 
							'column' => 'ignore_age_rule'
						), 
						array(
							'width' => '15', 
							'label' => 'Override Target Whitelist Rules?', 
							'column' => 'whitelist_override'
						), 
						array(
							'width' => '15', 
							'label' => 'Allow One-to-Many?', 
							'column' => 'allow_multi_assign'
						),
                        array(
                            'width' => '15',
                            'label' => 'Allow Multi-Colony AST?',
                            'column' => 'ast_multi_colony'
                        ),
                        array(
                            'width' => '15',
                            'label' => 'Bypass OOS Queue?',
                            'column' => 'bypass_oos'
                        ),
                        array(
                            'width' => '25',
                            'label' => 'O2M Create if Not Found',
                            'column' => 'o2m_addcmr_exclusions',
                            'helper_class' => '\Udoh\Emsa\Utils\VocabUtils',
                            'helper' => 'whitelistCrossrefNamesByIdList',
                            'delimiter' => '|'
                        ),
                        array(
                            'width' => '15',
                            'label' => 'Blacklist Prelim Results?',
                            'column' => 'blacklist_preliminary'
                        ),
						array(
							'width' => '15',
							'label' => 'Whitelist Rules Ignore State Case Status?',
							'column' => 'whitelist_ignore_case_status'
						)
					)
				), 
				'Master LOINC to Condition' => array(
					'query' => VocabExportConstants::EXPORT_QRY_ML_LOINC,
					'columns' => array(
						array(
							'width' => '15', 
							'label' => 'Test Concept Code (LOINC)', 
							'column' => 'loinc'
						), 
						array(
							'width' => '30', 
							'label' => 'Preferred Concept Name', 
							'column' => 'preferred_concept'
						), 
						array(
							'width' => '15', 
							'label' => 'Look Up Condition?', 
							'column' => 'condition_from_result'
						), 
						array(
							'width' => '25', 
							'label' => 'Master Condition', 
							'column' => 'condition'
						), 
						array(
							'width' => '15', 
							'label' => 'Look Up Organism?', 
							'column' => 'organism_from_result'
						), 
						array(
							'width' => '25', 
							'label' => 'Master Organism', 
							'column' => 'organism'
						), 
						array(
							'width' => '25', 
							'label' => 'Test Type', 
							'column' => 'test_type'
						), 
						array(
							'width' => '20', 
							'label' => 'Specimen Source', 
							'column' => 'specimen_source'
						), 
						array(
							'width' => '15', 
							'label' => 'List', 
							'column' => 'list'
						), 
						array(
							'width' => '10', 
							'label' => 'Operator A', 
							'column' => 'rule_structured', 
							'decode' => 'operator',
							'segment' => 0
						), 
						array(
							'width' => '15', 
							'label' => 'Operand A', 
							'column' => 'rule_structured', 
							'decode' => 'operand',
							'segment' => 0, 
							'get_app_value' => true
						), 
						array(
							'width' => '10', 
							'label' => 'Operator B', 
							'column' => 'rule_structured', 
							'decode' => 'operator',
							'segment' => 1
						), 
						array(
							'width' => '15', 
							'label' => 'Operand B', 
							'column' => 'rule_structured', 
							'decode' => 'operand',
							'segment' => 1, 
							'get_app_value' => true
						), 
						array(
							'width' => '15', 
							'label' => 'Create New CMR?', 
							'column' => 'allow_new_cmr'
						), 
						array(
							'width' => '15', 
							'label' => 'Update Existing CMRs?', 
							'column' => 'allow_update_cmr'
						), 
						array(
							'width' => '15', 
							'label' => 'Surveillance?', 
							'column' => 'is_surveillance'
						), 
						array(
							'width' => '25', 
							'label' => 'Set State Case Status to:', 
							'column' => 'status'
						), 
						array(
							'width' => '25', 
							'label' => 'Antimicrobial Agent', 
							'column' => 'antimicrobial_agent'
						), 
                        array(
                            'width' => '30', 
                            'label' => 'Admin Notes', 
                            'column' => 'admin_notes'
                        ), 
						array(
							'width' => '40', 
							'label' => 'Last Updated', 
							'column' => 'last_updated'
						)
					)
				), 
				'Master Organism' => array(
					'query' => VocabExportConstants::EXPORT_QRY_ML_ORGANISM,
					'columns' => array(
						array(
							'width' => '20', 
							'label' => 'SNOMED Type', 
							'column' => 'snomed_category'
						), 
						array(
							'width' => '30', 
							'label' => 'SNOMED Code', 
							'column' => 'snomed'
						), 
						array(
							'width' => '30', 
							'label' => 'Secondary SNOMED Code', 
							'column' => 'snomed_alt'
						), 
						array(
							'width' => '40', 
							'label' => 'Condition', 
							'column' => 'condition'
						), 
						array(
							'width' => '40', 
							'label' => 'Organism', 
							'column' => 'organism'
						), 
						array(
							'width' => '20', 
							'label' => 'List', 
							'column' => 'list'
						), 
						array(
							'width' => '20', 
							'label' => 'Test Result', 
							'column' => 'test_result'
						), 
						array(
							'width' => '10', 
							'label' => 'Operator A', 
							'column' => 'rule_structured', 
							'decode' => 'operator',
							'segment' => 0
						), 
						array(
							'width' => '15', 
							'label' => 'Operand A', 
							'column' => 'rule_structured', 
							'decode' => 'operand',
							'segment' => 0, 
							'get_app_value' => true
						), 
						array(
							'width' => '10', 
							'label' => 'Operator B', 
							'column' => 'rule_structured', 
							'decode' => 'operator',
							'segment' => 1
						), 
						array(
							'width' => '15', 
							'label' => 'Operand B', 
							'column' => 'rule_structured', 
							'decode' => 'operand',
							'segment' => 1, 
							'get_app_value' => true
						), 
						array(
							'width' => '15', 
							'label' => 'Create New CMR?', 
							'column' => 'allow_new_cmr'
						), 
						array(
							'width' => '15', 
							'label' => 'Update Existing CMRs?', 
							'column' => 'allow_update_cmr'
						), 
						array(
							'width' => '15', 
							'label' => 'Surveillance?', 
							'column' => 'is_surveillance'
						), 
						array(
							'width' => '25', 
							'label' => 'Set State Case Status to:', 
							'column' => 'status'
						), 
						array(
							'width' => '40', 
							'label' => 'Last Updated', 
							'column' => 'last_updated'
						), 
						array(
							'width' => '35', 
							'label' => 'Semi-Auto Usage', 
							'column' => 'semi_auto_usage'
						), 
                        array(
                            'width' => '30', 
                            'label' => 'Admin Notes', 
                            'column' => 'admin_notes'
                        )
					)
				)
			), 
			'object' => new Spreadsheet()
		)
	);
	
	/* 
	 * loop through all children with vocab & add to export_files list
	 */
    $childReporterList = CoreUtils::getReporterList($adminDbConn);
	if (!empty($childReporterList)) {
		foreach ($childReporterList as $childLabId => $childLabName) {
			$exportTableDefinitions[] = array(
				'filename' => __DIR__ . '/upload/' . trim(preg_replace('/[\\/:"*?<>|]+/', '', $childLabName)) . '_Child_Values__' . EXPORT_SERVERNAME . 'Export__' . EXPORT_TIMESTAMP . '.xls',
				'lab_name' => trim($childLabName),
                'lab_id' => (int) $childLabId,
				'sheets' => array(
					'Child LOINC' => array(
						'query' => VocabExportConstants::EXPORT_QRY_CHILD_LOINC,
						'columns' => array(
							array(
								'width' => '15', 
								'label' => 'Archived?', 
								'column' => 'archived'
							), 
							array(
								'width' => '15', 
								'label' => 'Child Test Concept Code', 
								'column' => 'child_loinc'
							), 
							array(
								'width' => '15', 
								'label' => 'Test Concept Code (LOINC)', 
								'column' => 'master_loinc'
							), 
							array(
								'width' => '30', 
								'label' => 'Preferred Concept Name', 
								'column' => 'preferred_concept_name'
							), 
							array(
								'width' => '15', 
								'label' => 'Child Orderable Test Code**', 
								'column' => 'cotc'
							), 
							array(
								'width' => '15', 
								'label' => 'Child Resultable Test Code**', 
								'column' => 'crtc'
							), 
							array(
								'width' => '30', 
								'label' => 'Child Preferred Concept Name**', 
								'column' => 'child_concept_name'
							), 
							array(
								'width' => '20', 
								'label' => 'Alias**', 
								'column' => 'child_alias'
							), 
							array(
								'width' => '20', 
								'label' => 'Result Location', 
								'column' => 'result_location'
							), 
							array(
								'width' => '15', 
								'label' => 'Quantitative (Deprecated)', 
								'column' => 'interpret_results'
							), 
							array(
								'width' => '15', 
								'label' => 'Message Workflow', 
								'column' => 'workflow'
							), 
							array(
								'width' => '15', 
								'label' => 'Units', 
								'column' => 'units'
							), 
							array(
								'width' => '15', 
								'label' => 'Indicates Pregnancy?', 
								'column' => 'pregnancy'
							), 
							array(
								'width' => '25', 
								'label' => 'Reference Range', 
								'column' => 'refrange'
							), 
							array(
								'width' => '20', 
								'label' => 'HL7 Reference Range', 
								'column' => 'hl7_refrange'
							), 
							array(
								'width' => '10', 
								'label' => 'Operator A', 
								'column' => 'rule_structured', 
								'decode' => 'operator',
								'segment' => 0
							), 
							array(
								'width' => '15', 
								'label' => 'Operand A', 
								'column' => 'rule_structured', 
								'decode' => 'operand',
								'segment' => 0
							), 
							array(
								'width' => '10', 
								'label' => 'Operator B', 
								'column' => 'rule_structured', 
								'decode' => 'operator',
								'segment' => 1
							), 
							array(
								'width' => '15', 
								'label' => 'Operand B', 
								'column' => 'rule_structured', 
								'decode' => 'operand',
								'segment' => 1
							), 
							array(
								'width' => '20', 
								'label' => 'Test Result', 
								'column' => 'result'
							), 
							array(
								'width' => '20', 
								'label' => 'Results to Comments', 
								'column' => 'results_to_comments'
							), 
							array(
								'width' => '40', 
								'label' => 'Last Updated', 
								'column' => 'last_updated'
							), 
							array(
								'width' => '30', 
								'label' => 'Admin Notes', 
								'column' => 'admin_notes'
							), 
							array(
								'width' => '15', 
								'label' => 'Preprocessor Concatenation?', 
								'column' => 'allow_preprocessing'
							), 
							array(
								'width' => '20', 
								'label' => 'Off-Scale Low Result', 
								'column' => 'offscale_low_result'
							), 
							array(
								'width' => '20', 
								'label' => 'Off-scale High Result', 
								'column' => 'offscale_high_result'
							), 
							array(
								'width' => '15', 
								'label' => 'Result Interpretation', 
								'column' => 'interpret_override'
							)
						)
					), 
					'Child Organism' => array(
						'query' => VocabExportConstants::EXPORT_QRY_CHILD_ORGANISM,
						'columns' => array(
							array(
								'width' => '20', 
								'label' => 'Child Code', 
								'column' => 'child_code'
							), 
							array(
								'width' => '20', 
								'label' => 'Organism SNOMED Code', 
								'column' => 'snomed'
							), 
							array(
								'width' => '50', 
								'label' => 'Organism', 
								'column' => 'organism'
							), 
							array(
								'width' => '20', 
								'label' => 'Test Result SNOMED Code', 
								'column' => 'test_result_snomed'
							), 
							array(
								'width' => '50', 
								'label' => 'Test Result', 
								'column' => 'test_result_id'
							), 
							array(
								'width' => '20', 
								'label' => 'Result Value', 
								'column' => 'result_value'
							), 
							array(
								'width' => '20', 
								'label' => 'Comments', 
								'column' => 'comment'
							), 
							array(
								'width' => '40', 
								'label' => 'Last Updated', 
								'column' => 'last_updated'
							), 
							array(
								'width' => '30', 
								'label' => 'Admin Notes', 
								'column' => 'admin_notes'
							)
						)
					), 
					'Child Vocab' => array(
						'query' => VocabExportConstants::EXPORT_QRY_CHILD_VOCAB,
						'columns' => array(
							array(
								'width' => '25', 
								'label' => 'Category', 
								'column' => 'category'
							), 
							array(
								'width' => '50', 
								'label' => 'Child Value', 
								'column' => 'child_concept'
							), 
							array(
								'width' => '50', 
								'label' => 'Master Value', 
								'column' => 'master_concept'
							),
							array(
								'width' => '50',
								'label' => 'Append to Comments',
								'column' => 'comment'
							),
							array(
								'width' => '40', 
								'label' => 'Last Updated', 
								'column' => 'last_updated'
							)
						)
					)
				), 
				'object' => new Spreadsheet()
			);
		}
	}
	
	foreach ($exportTableDefinitions as $exportFile) {  // create all files expected for export

        /** @var Spreadsheet $currentExportFile */
        $currentExportFile = $exportFile['object'];
	
		try {
            $currentExportFile->getProperties()->setCreator("UDOH ELR Vocabulary Exporter");
            $currentExportFile->getProperties()->setLastModifiedBy("UDOH ELR Vocabulary Exporter");

            $sheetCounter = 0;
            foreach ($exportFile['sheets'] as $sheetName => $sheetData) {  // create all sheets for this file
                if ($sheetCounter > 0) {
                    $currentExportFile->createSheet();
                }
                $sheetLastColumn = (count($sheetData['columns']) - 1);
                $headerRange = 'A1:' . ImportUtils::getExcelColumnLabel($sheetLastColumn) . '1';

                $currentExportFile->setActiveSheetIndex($sheetCounter);
                $currentExportFile->getActiveSheet()->setTitle(substr($sheetName, 0, 30));  // tab name limited to 31 chars

                if (isset($sheetData['columns']) && is_array($sheetData['columns']) && (count($sheetData['columns']) > 0)) {
                    // write out column headers
                    foreach ($sheetData['columns'] as $columnIndex => $columnData) {
                        $currentExportFile->getActiveSheet()->setCellValue(ImportUtils::getExcelColumnLabel($columnIndex) . '1', $columnData['label']);
                    }

                    // get data back for this sheet's columns
                    $sheetRs = null;
                    $sheetRow = null;

                    if (isset($sheetData['query']) && !is_null($sheetData['query'])) {
                        if (array_key_exists('lab_id', $exportFile) && !empty($exportFile['lab_id'])) {
                            $sheetRs = Udoh\Emsa\Import\VocabImportUtils::getExportRecordSet($adminDbConn, (string)$sheetData['query'], (int)$exportFile['lab_id']);
                        } else {
                            $sheetRs = Udoh\Emsa\Import\VocabImportUtils::getExportRecordSet($adminDbConn, (string)$sheetData['query']);
                        }
                    }

                    if (!empty($sheetRs)) {
                        $sheetRowIndex = 2;
                        while ($sheetRow = $sheetRs->fetchObject()) {
                            foreach ($sheetData['columns'] as $columnIndexFetch => $columnDataFetch) {
                                if (isset($columnDataFetch['helper']) && !is_null($columnDataFetch['helper'])) {
                                    if (isset($columnDataFetch['delimiter']) && !is_null($columnDataFetch['delimiter'])) {
                                        $currentExportFile->getActiveSheet()->setCellValueExplicit(
                                            ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex),
                                            implode($columnDataFetch['delimiter'], call_user_func(array($columnDataFetch['helper_class'], $columnDataFetch['helper']), $adminDbConn, $sheetRow->{$columnDataFetch['column']})),
                                            DataType::TYPE_STRING
                                        );
                                    } else {
                                        $currentExportFile->getActiveSheet()->setCellValueExplicit(
                                            ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex),
                                            call_user_func(array($columnDataFetch['helper_class'], $columnDataFetch['helper']), $adminDbConn, $sheetRow->{$columnDataFetch['column']}),
                                            DataType::TYPE_STRING
                                        );
                                    }
                                } elseif (isset($columnDataFetch['decode']) && !is_null($columnDataFetch['decode']) && isset($columnDataFetch['get_app_value']) && $columnDataFetch['get_app_value']) {
                                    $currentExportFile->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex),
                                        VocabImportUtils::decodeJSONForExport($adminDbConn, $sheetRow->{$columnDataFetch['column']}, $columnDataFetch['decode'], $columnDataFetch['segment'], true, 2) . ' ',
                                        DataType::TYPE_STRING
                                    );
                                } elseif (isset($columnDataFetch['decode']) && !is_null($columnDataFetch['decode'])) {
                                    $currentExportFile->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex),
                                        VocabImportUtils::decodeJSONForExport($adminDbConn, $sheetRow->{$columnDataFetch['column']}, $columnDataFetch['decode'], $columnDataFetch['segment'], false) . ' ',
                                        DataType::TYPE_STRING
                                    );
                                } else {
                                    $currentExportFile->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex),
                                        $sheetRow->{$columnDataFetch['column']} . ' ',
                                        DataType::TYPE_STRING
                                    );
                                }
                                $currentExportFile->getActiveSheet()->getStyle(ImportUtils::getExcelColumnLabel($columnIndexFetch) . intval($sheetRowIndex))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                            }
                            $sheetRowIndex++;
                        }
                    }

                    // size columns based on defined widths
                    foreach ($sheetData['columns'] as $columnIndexSizing => $columnDataTrash) {
                        $currentExportFile->getActiveSheet()->getColumnDimension(ImportUtils::getExcelColumnLabel($columnIndexSizing))->setWidth($columnDataTrash['width']);
                    }

                    // formatting & beautification
                    $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFont()->setBold(true);
                    $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFont()->setSize('12');
                    $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID);
                    $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FFB0C4DE');

                    if (stripos($exportFile['filename'], 'child_values') !== false) {
                        // add lab name header to file if child vocab
                        $currentExportFile->getActiveSheet()->insertNewRowBefore(1, 1);  // one new row inserted before current column headings
                        $currentExportFile->getActiveSheet()->setCellValue('A1', $exportFile['lab_name']);
                        $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFont()->setBold(true);
                        $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFont()->setSize('16');
                        $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID);
                        $currentExportFile->getActiveSheet()->getStyle($headerRange)->getFill()->getStartColor()->setARGB('FFA4D246');
                        $currentExportFile->getActiveSheet()->mergeCells($headerRange);
                        $currentExportFile->getActiveSheet()->freezePane('A3');
                    } else {
                        $currentExportFile->getActiveSheet()->freezePane('A2');
                    }

                    // apply text wrapping to entire range of cells
                    $currentExportFile->getActiveSheet()->getStyle('A1:' . ImportUtils::getExcelColumnLabel($sheetLastColumn) . trim($sheetRowIndex))->getAlignment()->setWrapText(true);
                }

                $sheetRs = null;
                $sheetCounter++;
            }

            $currentExportFile->setActiveSheetIndex(0);  // set focus back to first sheet in workbook

            $currentWriter = new Xls($currentExportFile);
            $currentWriter->save($exportFile['filename']);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $currentExportFile->disconnectWorksheets();
            $currentWriter = null;
            $currentExportFile = null;
        }
		
		$vocabTableFiles[] = $exportFile['filename'];
	}
	
	// package all files together, remove originals
	VocabImportUtils::createZip($vocabTableFiles, $vocabExportFilename);
	
	echo 'Done!';

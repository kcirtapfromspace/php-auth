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

use Udoh\Emsa\Client\AppClientInterface;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Vocabulary Manager auditing functionality
 * 
 * Audit changes made to items in the Vocabulary system.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabAudit
{

    const TABLE_MASTER_VOCAB = 1;
    const TABLE_MASTER_TO_APP = 2;
    const TABLE_CHILD_VOCAB = 3;
    const TABLE_MASTER_LOINC = 4;
    const TABLE_MASTER_CONDITION = 5;
    const TABLE_MASTER_SNOMED = 6;
    const TABLE_CHILD_LOINC = 7;
    const TABLE_CHILD_SNOMED = 8;
    const TABLE_CHILD_TESTRESULT = 9;
    const TABLE_CMR_RULES = 10;
    const TABLE_MS_CMR_RULES = 11;
    const TABLE_GRAYLIST_RULES = 12;
    const TABLE_MASTER_PFGE = 13;
    const TABLE_MASTER_ICD = 14;
    const TABLE_MASTER_RXNORM = 15;
    const ACTION_ADD = 1;
    const ACTION_EDIT = 2;
    const ACTION_DELETE = 3;

    /** @var PDO */
    protected $dbConn;

    /** @var AppClientInterface */
    protected $authClient;
    public $vocab_id;
    public $table;
    public $action;
    protected $user_id;
    protected $old_vals;
    protected $new_vals;

    /**
     * Create a new Vocabulary Auditer object.
     *
     * @param PDO                $dbConn     PDO connection to the EMSA database.
     * @param AppClientInterface $authClient Application client used for authentication.
     */
    public function __construct(PDO $dbConn, AppClientInterface $authClient)
    {
        $this->dbConn = $dbConn;
        $this->authClient = $authClient;
        $this->vocab_id = null;
        $this->table = null;
        $this->action = null;
        $this->user_id = trim($_SESSION[EXPORT_SERVERNAME]['umdid']);
        $this->old_vals = null;
        $this->new_vals = null;
    }

    /**
     * Resets this Vocabulary Auditer object to starting values.
     */
    public function resetAudit(): void
    {
        $this->vocab_id = null;
        $this->table = null;
        $this->action = null;
        $this->user_id = trim($_SESSION[EXPORT_SERVERNAME]['umdid']);
        $this->old_vals = null;
        $this->new_vals = null;
    }

    /**
     * Returns the verbose name of the table specified by <em>tableID</em>.
     *
     * @param int|null $tableID
     *
     * @return string
     */
    public function tableName(?int $tableID = null): string
    {
        $name = 'Unknown/Unspecified Table';

        if (empty($tableID)) {
            return $name;
        }

        switch ((int) $tableID) {
            case self::TABLE_MASTER_VOCAB:
                $name = 'Master Dictionary';
                break;
            case self::TABLE_MASTER_TO_APP:
                $name = 'Master to App Translation';
                break;
            case self::TABLE_CHILD_VOCAB:
                $name = 'Child Dictionary';
                break;
            case self::TABLE_MASTER_LOINC:
                $name = 'Master LOINC';
                break;
            case self::TABLE_MASTER_CONDITION:
                $name = 'Master Condition';
                break;
            case self::TABLE_MASTER_SNOMED:
                $name = 'Master SNOMED';
                break;
            case self::TABLE_MASTER_PFGE:
                $name = 'PulseNet Serotype Code';
                break;
            case self::TABLE_MASTER_ICD:
                $name = 'ICD Code';
                break;
            case self::TABLE_CHILD_LOINC:
                $name = 'Child LOINC';
                break;
            case self::TABLE_CHILD_SNOMED:
                $name = 'Child SNOMED';
                break;
            case self::TABLE_CHILD_TESTRESULT:
                $name = 'Child LOINC Interpretive Rule';
                break;
            case self::TABLE_CMR_RULES:
                $name = 'Master LOINC CMR Rule';
                break;
            case self::TABLE_MS_CMR_RULES:
                $name = 'Master SNOMED CMR Rule';
                break;
            case self::TABLE_GRAYLIST_RULES:
                $name = 'Graylist Rule';
                break;
            default:
                $name = 'Unknown/Unspecified Table';
                break;
        }

        return $name;
    }

    public function setOldVals($package = null): bool
    {
        if (empty($package)) {
            $this->old_vals = '';
            return false;
        }

        if (is_object($package) || is_array($package)) {
            $encoded_package = @json_encode($package);
            if ($encoded_package === false) {
                $this->old_vals = '';
                return false;
            } else {
                $this->old_vals = $encoded_package;
                return true;
            }
        } else {
            $obj_check = @json_decode($package);
            if (!is_null($obj_check) && (is_object($obj_check) || is_array($obj_check))) {
                $this->old_vals = $package;
                return true;
            } else {
                $this->old_vals = '';
                return false;
            }
        }
    }

    public function setNewVals($package = null): bool
    {
        if (empty($package)) {
            $this->new_vals = '';
            return false;
        }

        if (is_object($package) || is_array($package)) {
            $encoded_package = @json_encode($package);
            if ($encoded_package === false) {
                $this->new_vals = '';
                return false;
            } else {
                $this->new_vals = $encoded_package;
                return true;
            }
        } else {
            $obj_check = @json_decode($package);
            if (!is_null($obj_check) && (is_object($obj_check) || is_array($obj_check))) {
                $this->new_vals = $package;
                return true;
            } else {
                $this->new_vals = '';
                return false;
            }
        }
    }

    public function getRuleParentId($rule_id = null, $table = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;

        if (empty($rule_id) || empty($table)) {
            return null;
        }

        switch (intval($table)) {
            case self::TABLE_CHILD_TESTRESULT:
                $sql = 'SELECT child_loinc_id AS parent_id FROM ' . $emsaDbSchemaPrefix . 'vocab_c2m_testresult WHERE id = ' . intval($rule_id) . ';';
                break;
            case self::TABLE_CMR_RULES:
                $sql = 'SELECT master_loinc_id AS parent_id FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_masterloinc WHERE id = ' . intval($rule_id) . ';';
                break;
            case self::TABLE_MS_CMR_RULES:
                $sql = 'SELECT master_snomed_id AS parent_id FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_mastersnomed WHERE id = ' . intval($rule_id) . ';';
                break;
            case self::TABLE_GRAYLIST_RULES:
                $sql = 'SELECT master_condition_id AS parent_id FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_graylist WHERE id = ' . intval($rule_id) . ';';
                break;
            default:
                $sql = null;
        }

        if (empty($sql)) {
            return null;
        }

        $rs = @pg_query($host_pa, $sql);
        if (($rs === false) || (@pg_num_rows($rs) !== 1)) {
            return null;
        }

        return intval(@pg_fetch_result($rs, 0, 'parent_id'));
    }

    /**
     * Get previously-set values for a vocabulary item prior to updating or deleting it.
     * 
     * @global resource $host_pa
     * @global string $emsaDbSchemaPrefix
     * 
     * @param int $vocabId PK ID of vocabulary item being affected
     * @param int $tableId ID of vocabulary table item resides in
     * @param int $appId [Optional]<br>If vocabulary is Application-specific, ID of the associated Application
     * 
     * @return array|null
     */
    public function getPreviousVals($vocabId = null, $tableId = null, $appId = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;

        if (empty($vocabId) || empty($tableId)) {
            return null;
        }

        switch (intval($tableId)) {
            case self::TABLE_MASTER_VOCAB:
                $sql = 'SELECT sc.label AS "Category", mv.codeset AS "Value Set Code", mv.concept AS "Master Concept Name" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc ON (mv.category = sc.id) 
						WHERE mv.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_TO_APP:
                $sql = 'SELECT a.app_name AS "Application", m2a.coded_value AS "Value" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_master2app m2a 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_app a ON (m2a.app_id = a.id) 
						WHERE m2a.master_id = ' . intval($vocabId) . '
                        AND m2a.app_id = ' . intval($appId) . ';';
                break;
            case self::TABLE_CHILD_VOCAB:
                $sql = 'SELECT l.ui_name AS "Lab", sc.label AS "Category", cv.concept AS "Child Code", mv.concept AS "Master Concept Name", cv.comment AS "Append to Comments" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_child_vocab cv 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_labs l ON (cv.lab_id = l.id) 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv ON (cv.master_id = mv.id) 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc ON (mv.category = sc.id) 
						WHERE cv.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_LOINC:
                $sql = 'SELECT ml.loinc AS "LOINC Code", ml.concept_name AS "Concept Name", mv_aa.concept AS "Antimicrobial Agent", 
						CASE WHEN ml.condition_from_result IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Look Up Condition?", mv_c.concept AS "Condition", 
						CASE WHEN ml.organism_from_result IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Look Up Organism?", mv_o.concept AS "Organism", 
						mv_tt.concept AS "Test Type", mv_s.concept AS "Specimen Source", ss.name AS "List"
						FROM ' . $emsaDbSchemaPrefix . 'vocab_master_loinc ml 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_condition mc ON (ml.trisano_condition = mc.c_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo ON (ml.trisano_organism = mo.o_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_o ON (mo.organism = mv_o.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_tt ON (ml.trisano_test_type = mv_tt.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_s ON (ml.specimen_source = mv_s.id) 
                        LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_aa ON (ml.antimicrobial_agent= mv_aa.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'system_statuses ss ON (ml.list = ss.id) 
						WHERE ml.l_id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_CONDITION:
                $sql = 'SELECT 
						mv_dc.concept AS "CDC Category", 
						CASE WHEN mc.is_initial IS TRUE THEN \'Initial\' ELSE \'Final\' END AS "Condition Type", 
						mv_c.concept AS "Condition Name", 
						mc.valid_specimen AS "Valid Specimens", 
						mc.invalid_specimen AS "Invalid Specimens", 
                        mc.ignore_age_rule AS "Ignore Older Than", 
						CASE WHEN mc.blacklist_preliminary IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Blacklist Preliminary Results?",
						mc.white_rule AS "CMR White. Rules", 
						mc.contact_white_rule AS "Contact White. Rules", 
						CASE WHEN mc.whitelist_ignore_case_status IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Whitelist Rules Ignore State Case Status?", 
						mc.gateway_xref AS "Whitelist Xrefs", 
						CASE WHEN mc.check_xref_first IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Check Xrefs First?", 
						CASE WHEN mc.whitelist_override IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Override Target Whitelist Rules?", 
						CASE WHEN mc.allow_multi_assign IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Allow One-to-Many?",
						CASE WHEN mc.ast_multi_colony IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Allow Multi-Colony AST?",
						CASE WHEN mc.bypass_oos IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Bypass OOS Queue?",
						mc.o2m_addcmr_exclusions AS "O2M Add CMR if Not Found",
						CASE WHEN mc.immediate_notify IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Imm. Notifiable?", 
						CASE WHEN mc.require_specimen IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Req. Specimen from Noms?", 
						CASE WHEN mc.notify_state IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Notify State?", 
						sd.health_district AS "Jurisdiction Override" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_master_condition mc 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_dc ON (mc.disease_category = mv_dc.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'system_districts sd ON (mc.district_override = sd.id) 
						WHERE mc.c_id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_SNOMED:
                $sql = 'SELECT 
						mv_sc.concept AS "SNOMED Type", 
						mo.snomed AS "SNOMED Code", 
						mo.snomed_alt AS "Alt. SNOMED Code", 
						mv_c.concept AS "Master Condition", 
						mv_o.concept AS "Type Concept Name", 
						ss.name AS "List", 
						mv_t.concept || CASE WHEN sc_t.label = \'test_result\' THEN \' (Labs)\' WHEN sc_t.label = \'resist_test_result\' THEN \' (AST)\' END AS "Test Result", 
                        CASE WHEN mo.semi_auto_usage IS NULL THEN \'Allow Semi-Auto\' WHEN mo.semi_auto_usage IS TRUE THEN \'Force Semi-Auto\' ELSE \'Skip Semi-Auto\' END AS "Semi-Auto Usage" 
                        FROM ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_sc ON (mo.snomed_category = mv_sc.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_condition mc ON (mo.condition = mc.c_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_o ON (mo.organism = mv_o.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_t ON (mo.test_result = mv_t.id)
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc_t ON (sc_t.id = mv_t.category) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'system_statuses ss ON (mo.list = ss.id) 
						WHERE mo.o_id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_ICD:
                $sql = 'SELECT 
                        vset.codeset_name AS "Codeset", 
                        vi.code_value AS "ICD Code", vi.code_description AS "Code Description", 
						mv_c.concept AS "Condition", mv_o.concept AS "Organism", 
                        CASE WHEN vi.allow_new_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "New CMR?" , 
                        CASE WHEN vi.allow_update_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Update CMR?" , 
                        CASE WHEN vi.is_surveillance IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Surveillance?" , 
                        CASE WHEN vi.pregnancy_indicator IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Determines Pregnancy?" , 
                        CASE WHEN vi.pregnancy_status IS TRUE THEN \'Yes\' WHEN vi.pregnancy_status IS FALSE THEN \'No\' ELSE \'Unknown\' END AS "Pregnancy Status" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_icd vi
                        LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_codeset vset ON (vi.codeset_id = vset.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_condition mc ON (vi.master_condition_id = mc.c_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo ON (vi.master_snomed_id = mo.o_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_o ON (mo.organism = mv_o.id) 
						WHERE vi.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MASTER_PFGE:
                $sql = 'SELECT
						vp.pattern AS "Serotype Code", 
						mo1.snomed AS "Master SNOMED" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_pfge vp 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo1 ON (vp.master_snomed_id = mo1.o_id) 
						WHERE vp.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_CHILD_LOINC:
                $sql = 'SELECT
						l.ui_name AS "Lab", 
						cl.child_loinc AS "Child LOINC", 
						CASE WHEN cl.archived IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Archived?", 
						ml.loinc AS "Master LOINC", 
						CASE WHEN cl.workflow = ' . ENTRY_STATUS . ' THEN \'Automated Processing\' WHEN cl.workflow = ' . SEMI_AUTO_STATUS . ' THEN \'Semi-Automated Entry\' WHEN cl.workflow = ' . QA_STATUS . ' THEN \'QA Review\' ELSE \'Unknown Workflow\' END AS "Message Workflow", 
						CASE WHEN cl.interpret_override IS NULL THEN \'Set by OBX-2\' WHEN cl.interpret_override IS TRUE THEN \'Override Quantitative\' ELSE \'Override Coded Entry\' END AS "Result Interpretation", 
                        CASE WHEN cl.allow_preprocessing IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Preprocessor?", 
						mv_l.concept AS "Result Location", 
						cl.units AS "Units", 
						cl.refrange AS "Reference Range", 
						cl.hl7_refrange AS "HL7 Reference Range", 
                        mv_tl.concept || CASE WHEN sc_tl.label = \'test_result\' THEN \' (Labs)\' WHEN sc_tl.label = \'resist_test_result\' THEN \' (AST)\' END AS "Off-scale Low Test Result", 
                        mv_th.concept || CASE WHEN sc_th.label = \'test_result\' THEN \' (Labs)\' WHEN sc_th.label = \'resist_test_result\' THEN \' (AST)\' END AS "Off-scale High Test Result", 
						CASE WHEN cl.pregnancy IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Pregnancy?", 
						cl.child_orderable_test_code AS "Orderable Test Code", 
						cl.child_resultable_test_code AS "Resultable Test Code", 
						cl.child_concept_name AS "Child Concept Name", 
						cl.child_alias AS "Child Alias" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_child_loinc cl 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_labs l ON (cl.lab_id = l.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_loinc ml ON (cl.master_loinc = ml.l_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_l ON (cl.result_location = mv_l.id) 
                        LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_tl ON (cl.offscale_low_result = mv_tl.id) 
                        LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_th ON (cl.offscale_high_result = mv_th.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc_tl ON (sc_tl.id = mv_tl.category) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc_th ON (sc_th.id = mv_th.category) 
						WHERE cl.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_CHILD_SNOMED:
                $sql = 'SELECT
						l.ui_name AS "Lab", 
						co.child_code AS "Child SNOMED Code", 
						mo1.snomed AS "Master Organism", 
						mo2.snomed AS "Master Test Result", 
						co.result_value AS "Result Value", 
						co.comment AS "Comments" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_child_organism co 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_labs l ON (co.lab_id = l.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo1 ON (co.organism = mo1.o_id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_organism mo2 ON (co.test_result_id = mo2.o_id) 
						WHERE co.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_CHILD_TESTRESULT:
                $sql = 'SELECT 
						a.app_name AS "Application", 
						c2m.conditions_js AS "Conditions", 
						mv.concept || CASE WHEN sc.label = \'test_result\' THEN \' (Labs)\' WHEN sc.label = \'resist_test_result\' THEN \' (AST)\' END AS "Test Result", 
						c2m.results_to_comments AS "Comments" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_c2m_testresult c2m 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_app a ON (c2m.app_id = a.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv ON (mv.id = c2m.master_id)
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_category sc ON (sc.id = mv.category) 
						WHERE c2m.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_CMR_RULES:
                $sql = 'SELECT 
						a.app_name AS "Application", 
						cmr.conditions_js AS "Conditions", 
						CASE WHEN cmr.allow_new_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "New CMR?", 
						CASE WHEN cmr.allow_update_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Update CMRs?", 
						CASE WHEN cmr.is_surveillance IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Surveillance?", 
						mv_s.concept AS "State Case Status" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_masterloinc cmr 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_app a ON (cmr.app_id = a.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_s ON (cmr.state_case_status_master_id = mv_s.id) 
						WHERE cmr.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_MS_CMR_RULES:
                $sql = 'SELECT 
						a.app_name AS "Application", 
						cmr.conditions_js AS "Conditions", 
						CASE WHEN cmr.allow_new_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "New CMR?", 
						CASE WHEN cmr.allow_update_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Update CMRs?", 
						CASE WHEN cmr.is_surveillance IS TRUE THEN \'Yes\' ELSE \'No\' END AS "Surveillance?", 
						mv_s.concept AS "State Case Status" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_mastersnomed cmr 
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_app a ON (cmr.app_id = a.id) 
						LEFT JOIN ' . $emsaDbSchemaPrefix . 'vocab_master_vocab mv_s ON (cmr.state_case_status_master_id = mv_s.id) 
						WHERE cmr.id = ' . intval($vocabId) . ';';
                break;
            case self::TABLE_GRAYLIST_RULES:
                $sql = 'SELECT 
						a.app_name AS "Application", 
						vrg.conditions_structured AS "Conditions" 
						FROM ' . $emsaDbSchemaPrefix . 'vocab_rules_graylist vrg
						INNER JOIN ' . $emsaDbSchemaPrefix . 'vocab_app a ON (vrg.app_id = a.id) 
						WHERE vrg.id = ' . intval($vocabId) . ';';
                break;
            default:
                $sql = null;
        }

        if (empty($sql)) {
            return null;
        }

        $rs = @pg_query($host_pa, $sql);
        if (($rs === false) || (@pg_num_rows($rs) !== 1)) {
            return null;
        }

        $row = @pg_fetch_assoc($rs);
        $vals = array();
        foreach ($row as $col => $val) {
            if (stripos($col, 'list xref') !== false) {
                $vals[] = array('col' => trim($col), 'val' => trim(implode(";", VocabUtils::whitelistCrossrefNamesByIdList($this->dbConn, $val))));
            } elseif (stripos($col, 'o2m add') !== false) {
                $vals[] = array('col' => trim($col), 'val' => trim(implode(";", VocabUtils::whitelistCrossrefNamesByIdList($this->dbConn, $val))));
            } elseif (stripos($col, 'valid spec') !== false) {
                $vals[] = array('col' => trim($col), 'val' => VocabUtils::specimenIdValues($this->dbConn, $val));
            } elseif ((intval($tableId) == self::TABLE_CMR_RULES) && (stripos($col, 'conditions') !== false)) {
                $vals[] = array('col' => trim($col), 'val' => trim($this->verboseCmrConditions($val)));
            } elseif ((intval($tableId) == self::TABLE_MS_CMR_RULES) && (stripos($col, 'conditions') !== false)) {
                $vals[] = array('col' => trim($col), 'val' => trim($this->verboseMsCmrConditions($val)));
            } elseif ((intval($tableId) == self::TABLE_GRAYLIST_RULES) && (stripos($col, 'conditions') !== false)) {
                $tempJsonDecode = @json_decode($val);
                if (!EmsaUtils::emptyTrim($tempJsonDecode) && is_array($tempJsonDecode)) {
                    $tempDecodedObj = $tempJsonDecode[0];
                    $tempDecodedArr[] = @json_decode(@json_encode($tempDecodedObj), true);
                    $vals[] = array('col' => trim($col), 'val' => trim($this->verboseGraylistConditions($tempDecodedArr)));
                } else {
                    $vals[] = array('col' => trim($col), 'val' => '');
                }
            } elseif ((intval($tableId) == self::TABLE_CHILD_TESTRESULT) && (stripos($col, 'conditions') !== false)) {
                $vals[] = array('col' => trim($col), 'val' => trim($this->verboseInterpRuleConditions($val)));
            } else {
                $vals[] = array('col' => trim($col), 'val' => trim($val));
            }
        }

        return $vals;
    }

    public function prepareNewValues(int $table, ?array $newvals = []): array
    {
        $vals = [];

        if (empty($newvals)) {
            return $vals;
        }

        $params = [];

        switch (intval($table)) {
            case self::TABLE_MASTER_VOCAB:
                $sql = 'SELECT sc.label AS "Category", :valueSet AS "Value Set Code", :masterConcept AS "Master Concept Name" 
						FROM structure_category sc 
						WHERE sc.id = :categoryId;';
                $params[":valueSet"] = trim($newvals['valueset']);
                $params[":masterConcept"] = trim($newvals['masterconcept']);
                $params[":categoryId"] = (int) $newvals['category'];
                break;
            case self::TABLE_MASTER_TO_APP:
                $sql = 'SELECT a.app_name AS "Application", :appValue AS "Value" 
						FROM vocab_app a 
						WHERE a.id = :appId;';
                $params[":appValue"] = trim($newvals['appvalue']);
                $params[":appId"] = (int) $newvals['app_id'];
                break;
            case self::TABLE_CHILD_VOCAB:
                // lab_id, master_id, child_code
                $sql = 'SELECT l.ui_name AS "Lab", sc.label AS "Category", :childCode AS "Child Code", mv.concept AS "Master Concept Name", :comment AS "Append to Comments" 
						FROM structure_labs l, vocab_master_vocab mv 
						INNER JOIN structure_category sc ON (mv.category = sc.id) 
						WHERE (l.id = :labId) 
						AND (mv.id = :masterId);';
                $params[":childCode"] = trim($newvals['child_code']);
                $params[":labId"] = (int) $newvals['lab_id'];
                $params[":masterId"] = (int) $newvals['master_id'];
                $params[":comment"] = trim($newvals['comment']);
                break;
            case self::TABLE_MASTER_LOINC:
                $sql = 'SELECT 
						:loinc AS "LOINC Code", 
						:conceptName AS "Concept Name", 
                        (SELECT mv_aa.concept FROM vocab_master_vocab mv_aa WHERE mv_aa.id = :antimicrobialAgent) AS "Antimicrobial Agent", 
						CASE WHEN :conditionFromResult = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Look Up Condition?", 
						(SELECT mv_c.concept FROM vocab_master_vocab mv_c INNER JOIN vocab_master_condition mc ON ((mv_c.id = mc.condition) AND (mc.c_id = :nedssCondition))) AS "Condition", 
						CASE WHEN :organismFromResult = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Look Up Organism?", 
						(SELECT mv_o.concept FROM vocab_master_vocab mv_o INNER JOIN vocab_master_organism mo ON ((mv_o.id = mo.organism) AND (mo.o_id = :nedssOrganism))) AS "Organism", 
						(SELECT mv_tt.concept FROM vocab_master_vocab mv_tt WHERE mv_tt.id = :nedssTestType) AS "Test Type", 
						(SELECT mv_s.concept FROM vocab_master_vocab mv_s WHERE mv_s.id = :specimenSource) AS "Specimen Source", 
						(SELECT ss.name FROM system_statuses ss WHERE ss.id = :list) AS "List";';
                $params[":loinc"] = trim($newvals['loinc']);
                $params[":conceptName"] = trim($newvals['concept_name']);
                $params[":antimicrobialAgent"] = (int) $newvals['antimicrobial_agent'];
                $params[":conditionFromResult"] = trim($newvals['condition_from_result']);
                $params[":nedssCondition"] = (int) $newvals['trisano_condition'];
                $params[":organismFromResult"] = trim($newvals['organism_from_result']);
                $params[":nedssOrganism"] = (int) $newvals['trisano_organism'];
                $params[":nedssTestType"] = (int) $newvals['trisano_test_type'];
                $params[":specimenSource"] = (int) $newvals['specimen_source'];
                $params[":list"] = (int) $newvals['list'];
                break;
            case self::TABLE_MASTER_CONDITION:
                $sql = 'SELECT 
						(SELECT mv_dc.concept FROM vocab_master_vocab mv_dc WHERE mv_dc.id = :diseaseCategory) AS "CDC Category", 
						CASE WHEN :isInitial = \'t\' THEN \'Initial\' ELSE \'Final\' END AS "Condition Type", 
						(SELECT mv_c.concept FROM vocab_master_vocab mv_c WHERE mv_c.id = :condition) AS "Condition Name", 
						:validSpecimen AS "Valid Specimens", 
						:invalidSpecimen AS "Invalid Specimens", 
                        :ignoreAgeRule AS "Ignore Older Than", 
						CASE WHEN :blacklistPreliminary = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Blacklist Preliminary Results?",
						:whiteRule AS "CMR White. Rules", 
						:contactWhiteRule AS "Contact White. Rules", 
						CASE WHEN :ignoreStateCaseStatus = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Whitelist Rules Ignore State Case Status?", 
						:gatewayXref AS "Whitelist Xrefs", 
						CASE WHEN :checkXrefFirst = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Check Xrefs First?", 
						CASE WHEN :whitelistOverride = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Override Target Whitelist Rules?", 
						CASE WHEN :allowMultiAssign = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Allow One-to-Many?",
						CASE WHEN :astMultiColony = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Allow Multi-Colony AST?",
						CASE WHEN :bypassOos = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Bypass OOS Queue?",
						:o2mAddcmrExclusions AS "O2M Add CMR if Not Found", 
						CASE WHEN :immediateNotify = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Imm. Notifiable?", 
						CASE WHEN :requireSpecimen = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Req. Specimen from Noms?", 
						CASE WHEN :notifyState = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Notify State?", 
						(SELECT sd.health_district FROM system_districts sd WHERE sd.id = :districtOverride) AS "Jurisdiction Override";';
                $params[":diseaseCategory"] = (int) $newvals['disease_category'];
                $params[":isInitial"] = trim($newvals['is_initial']);
                $params[":condition"] = (int) $newvals['condition'];
                $params[":validSpecimen"] = trim(implode(';', $newvals['valid_specimen'] ?? []));
                $params[":invalidSpecimen"] = trim(implode(';', $newvals['invalid_specimen'] ?? []));
                $params[":ignoreAgeRule"] = trim($newvals['ignore_age_rule']);
                $params[":blacklistPreliminary"] = trim($newvals['blacklist_preliminary']);
                $params[":whiteRule"] = trim($newvals['white_rule']);
                $params[":contactWhiteRule"] = trim($newvals['contact_white_rule']);
                $params[":gatewayXref"] = trim(implode(';', $newvals['gateway_xref'] ?? []));
                $params[":checkXrefFirst"] = trim($newvals['check_xref_first']);
                $params[":whitelistOverride"] = trim($newvals['whitelist_override']);
                $params[":ignoreStateCaseStatus"] = trim($newvals['whitelist_ignore_case_status']);
                $params[":allowMultiAssign"] = trim($newvals['allow_multi_assign']);
                $params[":astMultiColony"] = trim($newvals['ast_multi_colony']);
                $params[":bypassOos"] = trim($newvals['bypass_oos']);
                $params[":o2mAddcmrExclusions"] = trim(implode(';', $newvals['o2m_addcmr_exclusions'] ?? []));
                $params[":immediateNotify"] = trim($newvals['immediate_notify']);
                $params[":requireSpecimen"] = trim($newvals['require_specimen']);
                $params[":notifyState"] = trim($newvals['notify_state']);
                $params[":districtOverride"] = (int) $newvals['district_override'];
                break;
            case self::TABLE_MASTER_SNOMED:
                $sql = 'SELECT
						(SELECT mv_sc.concept FROM vocab_master_vocab mv_sc WHERE mv_sc.id = :snomedCategory) AS "SNOMED Type", 
						:snomed AS "SNOMED Code", 
						:snomedAlt AS "Alt. SNOMED Code", 
						(SELECT mv_c.concept FROM vocab_master_vocab mv_c INNER JOIN vocab_master_condition mc ON ((mv_c.id = mc.condition) AND (mc.c_id = :condition))) AS "Condition", 
						(SELECT mv_o.concept FROM vocab_master_vocab mv_o WHERE mv_o.id = :organism) AS "Type Concept Name", 
						(SELECT ss.name FROM system_statuses ss WHERE ss.id = :list) AS "List", 
						(SELECT mv_t.concept || CASE WHEN sc_t.label = \'test_result\' THEN \' (Labs)\' WHEN sc_t.label = \'resist_test_result\' THEN \' (AST)\' END AS concept FROM vocab_master_vocab mv_t INNER JOIN structure_category sc_t ON (mv_t.category = sc_t.id) WHERE mv_t.id = :testResult) AS "Test Result",
                        CASE WHEN :semiAutoUsage = \'t\' THEN \'Force Semi-Auto\' WHEN :semiAutoUsage = \'f\' THEN \'Skip Semi-Auto\' ELSE \'Allow Semi-Auto\' END AS "Semi-Auto Usage";';
                $params[":snomedCategory"] = (int) $newvals['snomed_category'];
                $params[":snomed"] = trim($newvals['snomed']);
                $params[":snomedAlt"] = trim($newvals['snomed_alt']);
                $params[":condition"] = (int) $newvals['condition'];
                $params[":organism"] = (int) $newvals['organism'];
                $params[":list"] = (int) $newvals['list'];
                $params[":testResult"] = (int) $newvals['test_result'];
                $params[":semiAutoUsage"] = trim($newvals['semi_auto_usage']);
                break;
            case self::TABLE_MASTER_ICD:
                $sql = 'SELECT 
                        (SELECT codeset_name FROM vocab_codeset WHERE id = :codesetId) AS "Codeset", 
						:codeValue AS "ICD Code", 
						:codeDescription AS "Code Description", 
						(SELECT mv_c.concept FROM vocab_master_vocab mv_c INNER JOIN vocab_master_condition mc ON ((mv_c.id = mc.condition) AND (mc.c_id = :masterConditionId))) AS "Condition", 
						(SELECT mv_o.concept FROM vocab_master_vocab mv_o INNER JOIN vocab_master_organism mo ON ((mv_o.id = mo.organism) AND (mo.o_id = :masterSnomedId))) AS "Organism", 
						CASE WHEN :allowNewCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "New CMR?", 
						CASE WHEN :allowUpdateCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Update CMR?", 
						CASE WHEN :isSurveillance = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Surveillance?", 
						CASE WHEN :pregnancyIndicator = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Determines Pregnancy?", 
						CASE WHEN :pregnancyStatus = \'t\' THEN \'Yes\' WHEN :pregnancyStatus = \'f\' THEN \'No\' ELSE \'Unknown\' END AS "Pregnancy Status";';
                $params[":codesetId"] = (int) $newvals['codeset_id'];
                $params[":codeValue"] = trim($newvals['code_value']);
                $params[":codeDescription"] = trim($newvals['code_description']);
                $params[":masterConditionId"] = (int) $newvals['master_condition_id'];
                $params[":masterSnomedId"] = (int) $newvals['master_snomed_id'];
                $params[":allowNewCmr"] = trim($newvals['allow_new_cmr']);
                $params[":allowUpdateCmr"] = trim($newvals['allow_update_cmr']);
                $params[":isSurveillance"] = trim($newvals['is_surveillance']);
                $params[":pregnancyIndicator"] = trim($newvals['pregnancy_indicator']);
                $params[":pregnancyStatus"] = trim($newvals['pregnancy_status']);
                break;
            case self::TABLE_MASTER_PFGE:
                $sql = 'SELECT 
						:pattern AS "Serotype Code", 
						(SELECT mo1.snomed FROM vocab_master_organism mo1 WHERE mo1.o_id = :masterSnomedId) AS "Master SNOMED";';
                $params[":pattern"] = trim($newvals['pattern']);
                $params[":masterSnomedId"] = (int) $newvals['master_snomed_id'];
                break;
            case self::TABLE_CHILD_LOINC:
                $sql = 'SELECT
						(SELECT l.ui_name FROM structure_labs l WHERE l.id = :labId) AS "Lab", 
						:childLoinc AS "Child LOINC", 
						CASE WHEN :archived = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Archived?", 
						(SELECT ml.loinc FROM vocab_master_loinc ml WHERE ml.l_id = :masterLoinc) AS "Master LOINC", 
						CASE WHEN :workflow = ' . ENTRY_STATUS . ' THEN \'Automated Processing\' WHEN :workflow = ' . SEMI_AUTO_STATUS . ' THEN \'Semi-Automated Entry\' WHEN :workflow = ' . QA_STATUS . ' THEN \'QA Review\' ELSE \'Unknown Workflow\' END AS "Message Workflow", 
						CASE WHEN :interpretOverride = \'t\' THEN \'Override Quantitative\' WHEN :interpretOverride = \'f\' THEN \'Override Coded Entry\' ELSE \'Set by OBX-2\' END AS "Result Interpretation", 
                        CASE WHEN :allowPreprocessing = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Preprocessor?", 
						(SELECT mv_rl.concept FROM vocab_master_vocab mv_rl WHERE mv_rl.id = :resultLocation) AS "Result Location", 
						:units AS "Units", 
						:refrange AS "Reference Range", 
						:hl7Refrange AS "HL7 Reference Range", 
                        (SELECT mv_tl.concept || CASE WHEN sc_tl.label = \'test_result\' THEN \' (Labs)\' WHEN sc_tl.label = \'resist_test_result\' THEN \' (AST)\' END AS concept FROM vocab_master_vocab mv_tl INNER JOIN structure_category sc_tl ON (mv_tl.category = sc_tl.id) WHERE mv_tl.id = :offscaleLowResult) AS "Off-scale Low Test Result", 
                        (SELECT mv_th.concept || CASE WHEN sc_th.label = \'test_result\' THEN \' (Labs)\' WHEN sc_th.label = \'resist_test_result\' THEN \' (AST)\' END AS concept FROM vocab_master_vocab mv_th INNER JOIN structure_category sc_th ON (mv_th.category = sc_th.id) WHERE mv_th.id = :offscaleHighResult) AS "Off-scale High Test Result", 
						CASE WHEN :pregnancy = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Pregnancy?", 
						:childOrderableTestCode AS "Orderable Test Code", 
						:childResultableTestCode AS "Resultable Test Code", 
						:childConceptName AS "Child Concept Name", 
						:childAlias AS "Child Alias";';
                $params[":labId"] = (int) $newvals['lab_id'];
                $params[":childLoinc"] = trim($newvals['child_loinc']);
                $params[":archived"] = trim($newvals['archived']);
                $params[":masterLoinc"] = (int) $newvals['master_loinc'];
                $params[":workflow"] = trim($newvals['workflow']);
                $params[":interpretOverride"] = trim($newvals['interpret_override']);
                $params[":allowPreprocessing"] = trim($newvals['allow_preprocessing']);
                $params[":resultLocation"] = (int) $newvals['result_location'];
                $params[":units"] = trim($newvals['units']);
                $params[":refrange"] = trim($newvals['refrange']);
                $params[":hl7Refrange"] = trim($newvals['hl7_refrange']);
                $params[":offscaleLowResult"] = (int) $newvals['offscale_low_result'];
                $params[":offscaleHighResult"] = (int) $newvals['offscale_high_result'];
                $params[":pregnancy"] = trim($newvals['pregnancy']);
                $params[":childOrderableTestCode"] = trim($newvals['child_orderable_test_code']);
                $params[":childResultableTestCode"] = trim($newvals['child_resultable_test_code']);
                $params[":childConceptName"] = trim($newvals['child_concept_name']);
                $params[":childAlias"] = trim($newvals['child_alias']);
                break;
            case self::TABLE_CHILD_SNOMED:
                $sql = 'SELECT 
						(SELECT l.ui_name FROM structure_labs l WHERE l.id = :labId) AS "Lab", 
						:childCode AS "Child SNOMED Code", 
						(SELECT mo1.snomed FROM vocab_master_organism mo1 WHERE mo1.o_id = :organism) AS "Master Organism", 
						(SELECT mo2.snomed FROM vocab_master_organism mo2 WHERE mo2.o_id = :testResultId) AS "Master Test Result", 
						:resultValue AS "Result Value", 
						:comment AS "Comments";';
                $params[":labId"] = (int) $newvals['lab_id'];
                $params[":childCode"] = trim($newvals['child_code']);
                $params[":organism"] = (int) $newvals['organism'];
                $params[":testResultId"] = (int) $newvals['test_result_id'];
                $params[":resultValue"] = trim($newvals['result_value']);
                $params[":comment"] = trim($newvals['comment']);
                break;
            case self::TABLE_CHILD_TESTRESULT:
                $sql = 'SELECT 
						(SELECT a.app_name FROM vocab_app a WHERE a.id = :appId) AS "Application", 
						:conditions AS "Conditions", 
						(SELECT mv.concept || CASE WHEN sc.label = \'test_result\' THEN \' (Labs)\' WHEN sc.label = \'resist_test_result\' THEN \' (AST)\' END AS concept FROM vocab_master_vocab mv INNER JOIN structure_category sc ON (mv.category = sc.id) WHERE mv.id = :testResult) AS "Test Result", 
						:comments AS "Comments";';
                $params[":appId"] = (int) $newvals['app_id'];
                $params[":conditions"] = trim($newvals['conditions']);
                $params[":testResult"] = (int) $newvals['test_result'];
                $params[":comments"] = trim($newvals['comments']);
                break;
            case self::TABLE_CMR_RULES:
                $sql = 'SELECT
						(SELECT a.app_name FROM vocab_app a WHERE a.id = :appId) AS "Application", 
						:conditions AS "Conditions", 
						CASE WHEN :newCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "New CMR?", 
						CASE WHEN :updateCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Update CMRs?", 
						CASE WHEN :surveillance = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Surveillance?", 
						(SELECT mv_s.concept FROM vocab_master_vocab mv_s WHERE mv_s.id = :stateCaseStatusMasterId) AS "State Case Status";';
                $params[":appId"] = (int) $newvals['app_id'];
                $params[":conditions"] = trim($newvals['conditions']);
                $params[":newCmr"] = trim($newvals['new_cmr']);
                $params[":updateCmr"] = trim($newvals['update_cmr']);
                $params[":surveillance"] = trim($newvals['surveillance']);
                $params[":stateCaseStatusMasterId"] = (int) $newvals['state_case_status_master_id'];
                break;
            case self::TABLE_MS_CMR_RULES:
                $sql = 'SELECT
						(SELECT a.app_name FROM vocab_app a WHERE a.id = :appId) AS "Application", 
						:conditions AS "Conditions", 
						CASE WHEN :newCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "New CMR?", 
						CASE WHEN :updateCmr = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Update CMRs?", 
						CASE WHEN :surveillance = \'t\' THEN \'Yes\' ELSE \'No\' END AS "Surveillance?", 
						(SELECT mv_s.concept FROM vocab_master_vocab mv_s WHERE mv_s.id = :stateCaseStatusMasterId) AS "State Case Status";';
                $params[":appId"] = (int) $newvals['app_id'];
                $params[":conditions"] = trim($newvals['conditions']);
                $params[":newCmr"] = trim($newvals['new_cmr']);
                $params[":updateCmr"] = trim($newvals['update_cmr']);
                $params[":surveillance"] = trim($newvals['surveillance']);
                $params[":stateCaseStatusMasterId"] = (int) $newvals['state_case_status_master_id'];
                break;
            case self::TABLE_GRAYLIST_RULES:
                $sql = 'SELECT
						(SELECT a.app_name FROM vocab_app a WHERE a.id = :appId) AS "Application", 
						:conditions AS "Conditions";';
                $params[":appId"] = (int) $newvals['app_id'];
                $params[":conditions"] = trim($newvals['conditions']);
                break;
            default:
                $sql = null;
        }

        if (empty($sql)) {
            return $vals;
        }

        try {
            $stmt = $this->dbConn->prepare($sql);
            if ($stmt->execute($params)) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (is_array($row) && !empty($row)) {
                    foreach ($row as $col => $val) {
                        if (stripos($col, 'list xref') !== false) {
                            $vals[] = array('col' => trim($col), 'val' => trim(implode(";", VocabUtils::whitelistCrossrefNamesByIdList($this->dbConn, $val))));
                        } elseif (stripos($col, 'o2m add') !== false) {
                            $vals[] = array('col' => trim($col), 'val' => trim(implode(";", VocabUtils::whitelistCrossrefNamesByIdList($this->dbConn, $val))));
                        } elseif (stripos($col, 'valid spec') !== false) {
                            $vals[] = array('col' => trim($col), 'val' => VocabUtils::specimenIdValues($this->dbConn, $val));
                        } else {
                            $vals[] = array('col' => trim($col), 'val' => trim($val));
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $vals;
    }

    /**
     * Audit changes to a Vocabulary object.
     *
     * @param int $vocabID Vocabulary object ID
     * @param int $table   Vocabulary table being audited (one of <b>VocabAudit::TABLE_*</b> constants)
     * @param int $action  Action being taken on specified Vocabulary object (one of <b>VocabAudit::ACTION_ADD, VocabAudit::ACTION_EDIT, VocabAudit::ACTION_DELETE</b>)
     *
     * @return bool
     */
    public function auditVocab(int $vocabID, int $table, int $action): bool
    {
        if ((int) $vocabID > 0) {
            $this->vocab_id = (int) $vocabID;
        }

        if ((int) $table > 0) {
            $this->table = (int) $table;
        }

        if ((int) $action > 0) {
            $this->action = (int) $action;
        }

        if (empty($this->vocab_id) || empty($this->table) || empty($this->action)) {
            return false;
        }

        try {
            $sql = "INSERT INTO vocab_audits 
                        (vocab_id, tbl, user_id, action, old_vals, new_vals)
                    VALUES 
                        (:vocabId, :tbl, :userId, :action, :oldVals, :newVals);";
            $stmt = $this->dbConn->prepare($sql);

            $stmt->bindValue(":vocabId", $this->vocab_id, PDO::PARAM_INT);
            $stmt->bindValue(":tbl", $this->table, PDO::PARAM_INT);
            $stmt->bindValue(":userId", trim($this->user_id), PDO::PARAM_STR);
            $stmt->bindValue(":action", $this->action, PDO::PARAM_INT);
            $stmt->bindValue(":oldVals", $this->old_vals, PDO::PARAM_STR);
            $stmt->bindValue(":newVals", $this->new_vals, PDO::PARAM_STR);

            if ($stmt->execute()) {
                return true;
            }
        } catch (PDOException $e) {
            ExceptionUtils::logException($e);
            return false;
        } finally {
            $stmt = null;
            $sql = null;
        }

        return false;
    }

    public function auditSingleFieldChange($vocab_id = null, $table = null, $action = null, $oldVal = null, $newVal = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;

        if (intval($vocab_id) > 0) {
            $this->vocab_id = intval($vocab_id);
        }

        if (intval($table) > 0) {
            $this->table = intval($table);
        }

        if (intval($action) > 0) {
            $this->action = intval($action);
        }

        if (is_null($this->vocab_id) || is_null($this->table) || is_null($this->action)) {
            return false;
        }


        $sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'vocab_audits ( 
					vocab_id, 
					tbl, 
					user_id, 
					action, 
					old_vals, 
					new_vals 
				) VALUES ( 
					' . intval($this->vocab_id) . ', 
					' . intval($this->table) . ', 
					\'' . pg_escape_string(trim($this->user_id)) . '\', 
					' . intval($this->action) . ', 
					' . ((strlen(trim($oldVal)) > 0) ? '\'' . pg_escape_string(trim($oldVal)) . '\'' : 'NULL') . ', 
					' . ((strlen(trim($newVal)) > 0) ? '\'' . pg_escape_string(trim($newVal)) . '\'' : 'NULL') . ' 
				);';
        $rs = @pg_query($host_pa, $sql);
        if ($rs === false) {
            throw new Exception('Could not record audit log for this change; a database error occurred.<br><br>' . pg_last_error());
        }

        @pg_free_result($rs);
        return true;
    }

    public function displayVocabAuditById($vocab_id = null, $table = array())
    {
        global $host_pa, $emsaDbSchemaPrefix;

        $html = '';

        $html_prefix = '<table class="emsa_results audit_log">
				<thead>
					<tr>
						<th>Date/Time</th>
						<th>User</th>
						<th>Action</th>
						<th>Old Values</th>
						<th>New Values</th>
					</tr>
				</thead>
				<tbody>';

        $html_suffix = '</tbody>
				</table>';

        $html_not_found = '<tr>
					<td colspan="5">
						<em>No actions found for this vocabulary entry.</em>
					</td>
				</tr>';

        if (empty($vocab_id) || empty($table)) {
            return $html_prefix . $html_not_found . $html_suffix;
        }

        if (is_array($table)) {
            $clean_table = array_map('intval', $table);
            $table_list = implode(',', $clean_table);
        } else {
            return $html_prefix . $html_not_found . $html_suffix;
        }

        $sql = 'SELECT * FROM ' . $emsaDbSchemaPrefix . 'vocab_audits 
				WHERE (vocab_id = ' . intval($vocab_id) . ') 
				AND (tbl IN (' . $table_list . ')) 
				ORDER BY event_time;';
        $rs = @pg_query($host_pa, $sql);
        if (($rs === false) || (@pg_num_rows($rs) < 1)) {
            return $html_prefix . $html_not_found . $html_suffix;
        } else {
            while ($row = @pg_fetch_object($rs)) {
                $this_action = '';
                switch (intval($row->action)) {
                    case self::ACTION_ADD:
                        $this_action = 'Added new';
                        break;
                    case self::ACTION_EDIT:
                        $this_action = 'Edited';
                        break;
                    case self::ACTION_DELETE:
                        $this_action = 'Deleted';
                        break;
                }
                $html .= '<tr>
							<td>' . DisplayUtils::xSafe(date("m/d/Y [g:ia]", strtotime($row->event_time))) . '</td>
							<td>' . AppClientUtils::userFullNameByUserId($this->authClient, $row->user_id) . '</td>
							<td><strong>' . $this_action . '</strong> ' . DisplayUtils::xSafe($this->tableName(intval($row->tbl))) . '</td>
							<td>' . $this->displayJsonVals(trim($row->old_vals)) . '</td>
							<td>' . $this->displayJsonVals(trim($row->new_vals)) . '</td>
						</tr>';
            }
        }
        @pg_free_result($rs);
        return $html_prefix . $html . $html_suffix;
    }

    public function displayJsonVals($obj)
    {
        $retval = '';

        if (empty($obj)) {
            return $retval;
        }

        $decoded = @json_decode($obj);

        if (empty($decoded)) {
            return $retval;
        }

        foreach ($decoded as $decoded_ojb) {
            if (strlen(trim($decoded_ojb->val)) > 0) {
                $retval .= '<strong>' . DisplayUtils::xSafe($decoded_ojb->col) . ':  </strong><span class="mono_prewrap">' . DisplayUtils::xSafe($decoded_ojb->val) . '</span><br>' . "\n";
            } else {
                $retval .= '<strong>' . DisplayUtils::xSafe($decoded_ojb->col) . ':  </strong><span class="mono_prewrap">[No Value]</span><br>' . "\n";
            }
        }

        return $retval;
    }

    public function verboseCmrConditions($condition_raw = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;
        $retval = '';
        $trans = array('input' => 'Test Result', '&&' => 'AND');

        if (empty($condition_raw)) {
            return $retval;
        }

        $sql = 'SELECT id, concept || \' (Labs)\' as concept
                FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab
                WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'test_result\')
                UNION ALL 
                SELECT id, concept || \' (AST)\' as concept
                FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab
                WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'resist_test_result\')
                ORDER BY concept;';

        $rs = @pg_query($host_pa, $sql);
        if ($rs !== false) {
            while ($row = @pg_fetch_object($rs)) {
                $trans[trim($row->id)] = "'" . trim($row->concept) . "'";
            }
        }

        if (count($trans) > 0) {
            $retval = strtr($condition_raw, $trans);
        }

        return $retval;
    }

    public function verboseMsCmrConditions($condition_raw = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;
        $retval = '';
        $trans = array('input' => 'Test Result', '&&' => 'AND');

        if (empty($condition_raw)) {
            return $retval;
        }

        $sql = 'SELECT id, concept || \' (Labs)\' as concept
                FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab
                WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'test_result\')
                UNION ALL 
                SELECT id, concept || \' (AST)\' as concept
                FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab
                WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'resist_test_result\')
                ORDER BY concept;';

        $rs = @pg_query($host_pa, $sql);
        if ($rs !== false) {
            while ($row = @pg_fetch_object($rs)) {
                $trans[trim($row->id)] = "'" . trim($row->concept) . "'";
            }
        }

        if (count($trans) > 0) {
            $retval = strtr($condition_raw, $trans);
        }

        return $retval;
    }

    public function verboseGraylistConditions($conditions_raw = null)
    {
        global $host_pa, $emsaDbSchemaPrefix;
        $retval = '';

        if (empty($conditions_raw)) {
            return $retval;
        }

        $conditionObj = $conditions_raw[0];

        $conditionDecoded = null;
        $testTypeDecoded = null;

        $sql = 'SELECT concept
				FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab 
				WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'condition\') 
				AND id = ' . intval($conditionObj['operand']) . '';
        $rs = @pg_query($host_pa, $sql);
        if ($rs !== false) {
            $conditionDecoded = trim(@pg_fetch_result($rs, 0, 'concept'));
        }

        $sql = 'SELECT concept
				FROM ' . $emsaDbSchemaPrefix . 'vocab_master_vocab 
				WHERE category = ' . $emsaDbSchemaPrefix . 'vocab_category_id(\'test_type\') 
				AND id = ' . intval($conditionObj['operand1']) . '';
        $rs = @pg_query($host_pa, $sql);
        if ($rs !== false) {
            $testTypeDecoded = trim(@pg_fetch_result($rs, 0, 'concept'));
        }

        if (EmsaUtils::emptyTrim($conditionDecoded)) {
            $conditionDecoded = 'Any';
        }
        if (EmsaUtils::emptyTrim($testTypeDecoded)) {
            $testTypeDecoded = 'Any';
        }

        $retval .= "((Condition " . EmsaUtils::graphicalOperatorById($this->dbConn, intval($conditionObj['operator'])) . " \"" . trim($conditionDecoded) . "\") AND (Test Type " . EmsaUtils::graphicalOperatorById($this->dbConn, intval($conditionObj['operator1'])) . " \"" . trim($testTypeDecoded) . "\") AND (Specimen Collected " . trim($conditionObj['collect_lbound']) . " before or " . trim($conditionObj['collect_ubound']) . " after Event Date))";

        return $retval;
    }

    public function verboseInterpRuleConditions($condition_raw = null)
    {
        $trans = array('input' => 'Result Value', '&&' => 'AND');

        if (empty($condition_raw)) {
            return '';
        }

        return strtr($condition_raw, $trans);
    }

}

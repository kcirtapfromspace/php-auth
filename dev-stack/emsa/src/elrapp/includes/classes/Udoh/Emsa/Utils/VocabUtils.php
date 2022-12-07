<?php
namespace Udoh\Emsa\Utils;

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

use InvalidArgumentException;
use PDO;
use Throwable;
use Udoh\Emsa\Constants\VocabTable;
use Udoh\Emsa\Rules\WhitelistRuleSet;
use Udoh\Emsa\Rules\ContactWhitelistRule;
use Udoh\Emsa\Rules\WhitelistRule;

/**
 * Vocabulary-related utilities
 * 
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabUtils
{

    /**
     * Get the Master Concept for a Master Dictionary entry by ID.
     *
     * @param PDO $dbConn        PDO connection to EMSA database
     * @param int $masterVocabId Master Vocabulary ID
     *
     * @return string
     */
    public static function getMasterConceptById(PDO $dbConn, $masterVocabId)
    {
        $masterConcept = '';

        if (intval($masterVocabId) > 0) {
            $sql = 'SELECT concept
					FROM vocab_master_vocab
					WHERE id = :masterVocabId;';
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':masterVocabId', $masterVocabId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $masterConcept = trim($stmt->fetchColumn(0));
            }
        }

        return $masterConcept;
    }
    
    /**
     * Returns the Master Preferred Concept value for a given coded Child vocabulary value
     *
     * @param PDO    $dbConn          PDO connection to EMSA database
     * @param string $childCodedValue Coded Child vocabulary value
     * @param string $category        Vocabulary Category
     * @param int    $labId           Child Lab ID
     *
     * @return string
     */
    public static function getMasterConceptFromChildCode(PDO $dbConn, $childCodedValue = null, $category = null, $labId = null)
    {
        $masterConcept = '';
        
        if (empty($childCodedValue) || empty($category) || empty($labId)) {
            return $masterConcept;
        }
        
        try {
            $sql = "SELECT m.concept AS concept 
                    FROM vocab_master_vocab m
                    INNER JOIN vocab_child_vocab c ON (
                        c.master_id = m.id AND 
                        c.concept ILIKE :childCode
                    )
                    WHERE m.category = vocab_category_id(:category) 
                    AND c.lab_id = :labId;";
            
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':childCode', $childCodedValue, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $masterConcept = (string) $stmt->fetchColumn(0);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
        
        return $masterConcept;
    }

    /**
     * Returns the Application-Specific Coded Value for a given coded Child vocabulary value
     *
     * @param PDO    $dbConn          PDO connection to EMSA database
     * @param string $childCodedValue Coded Child vocabulary value
     * @param string $category        Vocabulary Category
     * @param int    $labId           Child Lab ID
     * @param int    $appId           Application ID
     *
     * @return string
     */
    public static function getAppCodedValueFromChildCode(PDO $dbConn, $childCodedValue = null, $category = null, $labId = null, $appId = 2)
    {
        $appCodedValue = '';
        
        if (empty($childCodedValue) || empty($category) || empty($labId)) {
            return $appCodedValue;
        }
        
        try {
            $sql = "SELECT m2a.coded_value AS coded_value
                    FROM vocab_master2app m2a
                    INNER JOIN vocab_master_vocab m ON (m2a.master_id = m.id)
                    INNER JOIN vocab_child_vocab c ON (
                        c.master_id = m.id AND 
                        c.concept ILIKE :childCode
                    )
                    WHERE m.category = vocab_category_id(:category) 
                    AND c.lab_id = :labId
                    AND m2a.app_id = :appId;";
            
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':childCode', $childCodedValue, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $appCodedValue = (string) $stmt->fetchColumn(0);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
        
        return $appCodedValue;
    }

    /**
     * Get a list of App-specific Organism names configured in EMSA and mapped to the specified Application.
     *
     * @param PDO $dbConn
     * @param int $appId [Optional; default 2 (EpiTrax)]<br>Application ID
     *
     * @return array
     */
    public static function getOrganismNamesMappedToApp(PDO $dbConn, $appId = 2)
    {
        $masterOrganismList = array();

        if (intval($appId) < 1) {
            $appId = 2;
        }

        try {
            $sql = "SELECT DISTINCT m2a.coded_value AS label
                        FROM vocab_master2app m2a
                        WHERE m2a.master_id IN (
                            SELECT DISTINCT mo.organism
                            FROM vocab_master_organism mo
                            INNER JOIN vocab_master_vocab mv ON (mv.id = mo.snomed_category)
                            WHERE mv.category = vocab_category_id(:category)
                            AND mv.concept = :orgType
                        )
                        AND m2a.app_id = :appId
                        ORDER BY m2a.coded_value;";

            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':category', 'snomed_category', PDO::PARAM_STR);
            $stmt->bindValue(':orgType', 'Organism', PDO::PARAM_STR);
            $stmt->bindValue(':appId', intval($appId), PDO::PARAM_INT);

            $stmt->execute();

            while ($row = $stmt->fetchObject()) {
                $masterOrganismList[] = trim($row->label);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $masterOrganismList;
    }

    /**
     * For a specified master vocab ID, checks to ensure that a row exists in vocab_master2app for all applications
     * prior to a vocabulary update so that adding a previously-blank field will properly save.
     *
     * @param PDO $dbConn        PDO connection to the EMSA database.
     * @param int $masterVocabId Master Vocabulary ID.
     */
    public static function createMissingMasterToAppVocab(PDO $dbConn, $masterVocabId)
    {
        try {
            $appSql = 'SELECT id, app_name 
                       FROM vocab_app 
                       ORDER BY id;';
            $appStmt = $dbConn->query($appSql);

            if ($appStmt !== false) {
                while ($appRow = $appStmt->fetchObject()) {
                    $masterAppSql = 'SELECT id 
                                     FROM vocab_master2app 
                                     WHERE app_id = :appId 
                                     AND master_id = :masterId;';
                    $masterAppStmt = $dbConn->prepare($masterAppSql);
                    $masterAppStmt->bindValue(':appId', intval($appRow->id), PDO::PARAM_INT);
                    $masterAppStmt->bindValue(':masterId', intval($masterVocabId), PDO::PARAM_INT);

                    if ($masterAppStmt->execute() && ($masterAppStmt->rowCount() < 1)) {
                        $insertSql = 'INSERT INTO vocab_master2app (app_id, master_id) 
                                      VALUES (:appId, :masterId);';
                        $insertStmt = $dbConn->prepare($insertSql);
                        $insertStmt->bindValue(':appId', intval($appRow->id), PDO::PARAM_INT);
                        $insertStmt->bindValue(':masterId', intval($masterVocabId), PDO::PARAM_INT);

                        $insertStmt->execute();
                        $insertStmt = null;
                    }

                    $masterAppStmt = null;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $appStmt = null;
        }
    }

    /**
     * Returns a comma-separated string containing the selected values of a specified filter.
     *
     * @param PDO   $dbConn           PDO connection to the EMSA database.
     * @param array $filterColumnData Filter Column data indicating database table/field to query for IDs.
     * @param array $values           Array containing the coded selected filter values.
     *
     * @return string
     */
    public static function verboseFilterValues(PDO $dbConn, array $filterColumnData, array $values)
    {
        $filterString = '';
        $filterVals = [];

        if (is_null($filterColumnData) || is_null($values) || !is_array($filterColumnData) || !is_array($values) || count($filterColumnData) < 1 || count($values) < 1) {
            return $filterString;
        }

        if (is_null($filterColumnData['itemlabel'])) {
            return trim(implode(', ', $values));
        }

        $whereArr = [];
        $pseudoInArr = [];

        foreach ($values as $value) {
            $whereArr[] = $value;
            $pseudoInArr[] = "(" . $filterColumnData['itemval'] . " = ?)";
        }

        try {
            $sql = 'SELECT ' . $filterColumnData['itemlabel'] . ' AS label 
                    FROM ' . $filterColumnData['fieldtable'] . ' 
                    WHERE (' . implode(' OR ', $pseudoInArr) . ');';
            $stmt = $dbConn->prepare($sql);
            $stmt->execute($whereArr);

            if ($stmt !== false) {
                while ($row = $stmt->fetchObject()) {
                    $filterVals[] = trim($row->label);
                }
            }

            $filterString = implode(', ', $filterVals);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $filterString;
    }

    /**
     * Converts a list of speciemn source master vocabulary IDs into a list of verbose specimen names.
     *
     * Accepts a string containing a semicolon-delimited list of master vocabulary IDs and converts them
     * to a semicolon-delimited string of verbose specimen sources for display in UI.
     *
     * @param PDO    $dbConn         PDO connection to the EMSA database.
     * @param string $specimenIdList List of master vocabulary IDs, separated by semicolons.
     *
     * @return string Semicolon-delimited string of verbose specimen sources.
     */
    public static function specimenIdValues(PDO $dbConn, $specimenIdList)
    {
        $xrefVals = array();

        if (strlen(trim($specimenIdList)) < 1) {
            return '';
        }

        $xrefIds = explode(';', $specimenIdList);
        if (count($xrefIds) < 1) {
            return '';
        }

        try {
            $sql = "SELECT concept 
                    FROM vocab_master_vocab 
                    WHERE category = elr.vocab_category_id('specimen') 
                    AND id = :xrefId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindParam(':xrefId', $xrefId, PDO::PARAM_INT);

            foreach ($xrefIds as $xrefId) {
                $stmt->execute();
                $text = $stmt->fetchColumn(0);

                if (strlen(trim($text)) > 0) {
                    $xrefVals[] = trim($text);
                } else {
                    return '';
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return '';
        } finally {
            $stmt = null;
        }

        return implode(';', $xrefVals);
    }

    /**
     * Get comments configured for a given Child Dictionary 'Specimen Source' value.
     *
     * @param PDO         $dbConn
     * @param int         $labId
     * @param string|null $childCode
     *
     * @return string|null
     */
    public static function getSpecimenComments(PDO $dbConn, int $labId, ?string $childCode = null): ?string
    {
        $specimenComments = null;

        if (empty($childCode)) {
            return $specimenComments;
        }

        try {
            $sql = "SELECT c.comment
                    FROM vocab_child_vocab c
                    INNER JOIN vocab_master_vocab m ON (c.master_id = m.id)
                    WHERE c.lab_id = :labId
                    AND m.category = vocab_category_id('specimen')
                    AND lower(c.concept) = lower(:childCode);";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':childCode', $childCode, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $specimenComments = $stmt->fetchColumn(0);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $specimenComments;
    }

    /**
     * Returns the application-specific Coded Value for the specified Master Vocabulary item.
     *
     * Returns an empty string if no vocabulary item found, no name specified, or an error.
     *
     * @param PDO $dbConn        PDO connection to the EMSA database
     * @param int $masterVocabId Master Vocabulary ID
     * @param int $appId         [Optional]<br>Application ID.  Default: 2 (EpiTrax)
     *
     * @return string
     */
    public static function appCodedValueByMasterID(PDO $dbConn, $masterVocabId = null, $appId = 2)
    {
        $codedValue = '';

        if (is_null($masterVocabId) || (intval(trim($masterVocabId)) < 1)) {
            return $codedValue;
        }

        try {
            $qry = "SELECT coded_value 
                    FROM vocab_master2app 
                    WHERE app_id = :appId 
                    AND master_id = :masterId;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':appId', intval($appId), PDO::PARAM_INT);
            $stmt->bindValue(':masterId', intval($masterVocabId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $codedValue = trim($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $codedValue;
    }

    /**
     * Returns the application-specific Coded Value, with category-based prefix, for the specified Test Result Master Vocab item.
     *
     * Returns an empty string if no vocabulary item found, no name specified, or an error.
     *
     * @param PDO $dbConn        PDO connection to the EMSA database
     * @param int $masterVocabId Master Vocabulary ID
     * @param int $appId         [Optional]<br>Application ID.  Default: 2 (EpiTrax)
     *
     * @return string
     */
    public static function appCodedTestResultValueByMasterID(PDO $dbConn, $masterVocabId = null, $appId = 2)
    {
        $codedValue = '';

        if (is_null($masterVocabId) || (intval(trim($masterVocabId)) < 1)) {
            return $codedValue;
        }

        try {
            $qry = "SELECT m2a.coded_value || CASE WHEN sc.label = 'test_result' THEN ' (Labs)' ELSE ' (AST)' END AS coded_value 
                    FROM vocab_master2app m2a
                    INNER JOIN vocab_master_vocab mv ON (m2a.master_id = mv.id)
                    INNER JOIN structure_category sc ON (sc.id = mv.category)
                    WHERE m2a.app_id = :appId 
                    AND m2a.master_id = :masterId;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':appId', intval($appId), PDO::PARAM_INT);
            $stmt->bindValue(':masterId', intval($masterVocabId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $codedValue = trim($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $codedValue;
    }

    /**
     * Returns an array with the ID of the Master Vocabulary items corresponding to the specified Application Result
     * Value name. Returns an empty array if no vocabulary item found, no name specified, or an error.
     *
     * @param PDO         $dbConn     PDO connection to the EMSA database
     * @param string|null $resultName Application Concept value (Returns empty array if no value specified)
     * @param int|null    $appId      [Optional] Application ID (Default 2 (EpiTrax))
     * @param bool|null   $isAST      [Optional] If specified, indicates lookup should specifically happen in AST results (if true) or Lab Test results (if false).  If null, looks in both and returns all matches.
     *
     * @return array Array of integer vocab IDs for all rows matching the specified resultName.  Empty array returned in case of errors.
     */
    public static function appResultMasterVocabIdByName(PDO $dbConn, ?string $resultName = null, ?int $appId = 2, ?bool $isAST = null): array
    {
        $masterVocabIDs = [];

        if (is_null($resultName) || (strlen(trim($resultName)) < 1)) {
            return $masterVocabIDs;
        }

        if (empty($appId)) {
            $appId = 2;
        }

        if (($isAST === true) || ($isAST === false)) {
            // when AST or Lab-specific test result is specified, ensure AST/Labs identifying prefix is stripped before looking up
            $cleanResultName = trim(strtr($resultName, ['(AST)' => '', '(Labs)' => '']));
        } else {
            $cleanResultName = trim($resultName);
        }

        if (is_null($isAST)) {
            $qry = "SELECT vm.id AS master_id 
                    FROM vocab_master_vocab vm
                    INNER JOIN vocab_master2app m2a ON (m2a.master_id = vm.id)
                    INNER JOIN structure_category sc ON (sc.id = vm.category)
                    WHERE ((sc.label = 'test_result') OR (sc.label = 'resist_test_result')) 
                    AND m2a.app_id = :appId
                    AND m2a.coded_value ILIKE :resultName;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
            $stmt->bindValue(':resultName', $cleanResultName, PDO::PARAM_STR);
        } elseif ($isAST === true) {
            $qry = "SELECT vm.id AS master_id 
                    FROM vocab_master_vocab vm
                    INNER JOIN vocab_master2app m2a ON (m2a.master_id = vm.id)
                    INNER JOIN structure_category sc ON (sc.id = vm.category)
                    WHERE sc.label = 'resist_test_result' 
                    AND m2a.app_id = :appId
                    AND m2a.coded_value ILIKE :resultName;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
            $stmt->bindValue(':resultName', $cleanResultName, PDO::PARAM_STR);
        } elseif ($isAST === false) {
            $qry = "SELECT vm.id AS master_id 
                    FROM vocab_master_vocab vm
                    INNER JOIN vocab_master2app m2a ON (m2a.master_id = vm.id)
                    INNER JOIN structure_category sc ON (sc.id = vm.category)
                    WHERE sc.label = 'test_result' 
                    AND m2a.app_id = :appId
                    AND m2a.coded_value ILIKE :resultName;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
            $stmt->bindValue(':resultName', $cleanResultName, PDO::PARAM_STR);
        } else {
            return $masterVocabIDs;
        }

        try {
            if ($stmt->execute()) {
                while ($row = $stmt->fetchObject()) {
                    $masterVocabIDs[] = (int)$row->master_id;
                }
            } else {
                return $masterVocabIDs;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return $masterVocabIDs;
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $masterVocabIDs;
    }

    /**
     * Checks to see if the test result name indicates AST test result or Lab test result.
     *
     * @param string $testResult
     *
     * @return bool
     */
    public static function isTestResultAST(string $testResult): bool
    {
        if (mb_stripos($testResult, '(AST)') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Return a boolean TRUE/FALSE indicator of whether a Child LOINC indicates pregnancy or not.
     *
     * @param PDO    $dbConn     PDO connection to the EMSA database
     * @param string $childLoinc Child LOINC/test code
     * @param int    $labId      Integer specifying which laboratory to search against.  Defaults to ARUP [id = 1] if none set.
     *
     * @return bool
     *
     * @static
     */
    public static function isPregnancyIndicatedByLoinc(PDO $dbConn, $childLoinc = null, $labId = 1)
    {
        $pregnancyIndicated = false;
        
        if (empty($childLoinc)) {
            return false;
        }
        
        try {
            $sql = "SELECT pregnancy FROM vocab_child_loinc 
                    WHERE child_loinc = :childLoinc 
                    AND lab_id = :labId 
                    AND pregnancy IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':childLoinc', $childLoinc, PDO::PARAM_STR);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->fetchColumn(0) === true) {
                    $pregnancyIndicated = true;
                }
            }
        } catch (Throwable $ex) {
            ExceptionUtils::logException($ex);
        } finally {
            $stmt = null;
        }
        
        return $pregnancyIndicated;
    }

    /**
     * Converts a list of whitelist crossref final condition IDs into a list of verbose condition names.
     *
     * @param PDO    $dbConn        PDO connection to the EMSA database
     * @param string $gatewayIdList List of final Master Condition IDs, separated by semicolons
     *
     * @return array Array of final condition names.  Returns empty array if no IDs supplied or on error.
     */
    public static function whitelistCrossrefNamesByIdList(PDO $dbConn, ?string $gatewayIdList = null): array
    {
        $xrefVals = [];

        if (empty($gatewayIdList)) {
            return $xrefVals;
        }

        $xrefIds = explode(";", $gatewayIdList);

        if (count($xrefIds) < 1) {
            return $xrefVals;
        }

        foreach ($xrefIds as $xrefId) {
            try {
                $sql = "SELECT mv.concept AS concept
                        FROM vocab_master_vocab mv
                        INNER JOIN vocab_master_condition mc ON (mc.condition = mv.id)
                        WHERE mc.c_id = :xrefId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':xrefId', (int) $xrefId, PDO::PARAM_INT);

                $stmt->execute();

                $text = (string) $stmt->fetchColumn(0);

                if (strlen(trim($text)) > 0) {
                    $xrefVals[] = trim($text);
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
            }
        }

        return $xrefVals;
    }
    
    /**
     * Returns list of whitelist crossreference final condition names for the specified initial Master Condition.
     *
     * @param PDO    $dbConn          PDO connection to the EMSA database
     * @param string $masterCondition Initial condition Master Concept name
     *
     * @return array Array of final condition names for use with People Search.  Returns empty array if no crossrefs found or an error occurs.
     */
    public static function whitelistCrossrefNamesByInitialConditionName(PDO $dbConn, string $masterCondition): array
    {
        $crossrefNames = [];

        try {
            // get crossrefs from database
            $sql = "SELECT mc.gateway_xref AS gateway_xref 
                    FROM vocab_master_condition mc
                    JOIN vocab_master_vocab mv ON (
                        mv.id = mc.condition 
                        AND mv.category = elr.vocab_category_id('condition') 
                        AND mv.concept = :masterCondition
                    );";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':masterCondition', trim($masterCondition), PDO::PARAM_STR);

            if ($stmt->execute()) {
                $row = $stmt->fetchObject();
                $crossrefNames = self::whitelistCrossrefNamesByIdList($dbConn, $row->gateway_xref);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return $crossrefNames;
        } finally {
            $row = null;
            $stmt = null;
        }

        return $crossrefNames;
    }

    /**
     * Returns list of condition names that must be not found in order for One-to-Many to create a new Morbidity event based on the specified initial Master Condition.
     *
     * @param PDO    $dbConn          PDO connection to the EMSA database
     * @param string $masterCondition Initial condition Master Concept name
     *
     * @return array Array of condition names for use with message processing.  Returns empty array if no conditions listed or an error occurs.
     */
    public static function o2mCreateIfNotFoundConditionsByInitialConditionName(PDO $dbConn, string $masterCondition): array
    {
        $o2mExcludedConditions = [];

        try {
            // get crossrefs from database
            $sql = "SELECT mc.o2m_addcmr_exclusions AS o2m_addcmr_exclusions 
                    FROM vocab_master_condition mc
                    JOIN vocab_master_vocab mv ON (
                        mv.id = mc.condition 
                        AND mv.category = elr.vocab_category_id('condition') 
                        AND mv.concept = :masterCondition
                    );";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':masterCondition', trim($masterCondition), PDO::PARAM_STR);

            if ($stmt->execute()) {
                $row = $stmt->fetchObject();
                $o2mExcludedConditions = self::whitelistCrossrefNamesByIdList($dbConn, $row->o2m_addcmr_exclusions);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return $o2mExcludedConditions;
        } finally {
            $row = null;
            $stmt = null;
        }

        return $o2mExcludedConditions;
    }

    /**
     * Accepts an encoded value for Abnormal Flag from the Master XML & returns the decoded Preferred Concept Name
     *
     * @param PDO    $dbConn            PDO connection to the EMSA database
     * @param string $codedAbnormalFlag Coded Abnormal Flag value
     *
     * @return string
     */
    public static function decodeAbnormalFlag(PDO $dbConn, $codedAbnormalFlag = null)
    {
        $decodedAbnormalFlag = '';
        
        if (!empty($codedAbnormalFlag)) {
            $flag = trim($codedAbnormalFlag);
            
            if (!empty($flag)) {
                try {
                    $sql = "SELECT m.concept AS concept 
                            FROM vocab_master_vocab m
                            INNER JOIN vocab_child_vocab c ON (
                                c.master_id = m.id AND 
                                m.category = vocab_category_id('abnormal_flag') AND 
                                c.concept ILIKE :flag
                            );";
                    $stmt = $dbConn->prepare($sql);
                    $stmt->bindValue(':flag', $flag, PDO::PARAM_STR);
                    
                    $stmt->execute();
                    
                    $decodedAbnormalFlag = (string) $stmt->fetchColumn(0);
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                } finally {
                    $stmt = null;
                }
            }
        }
        
        return $decodedAbnormalFlag;
    }
    
    /**
     * Get the Master Vocab ID representing an Antimicrobial Agent identified by a Master LOINC code.
     *
     * @param PDO    $dbConn
     * @param string $masterLoinc
     *
     * @return int
     */
    public static function getAntimicrobialAgentByLoinc(PDO $dbConn, $masterLoinc)
    {
        $masterAgentId = null;
        
        if (!empty($masterLoinc)) {
            try {
                $sql = "SELECT antimicrobial_agent 
                        FROM vocab_master_loinc
                        WHERE loinc = :masterLoinc;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':masterLoinc', $masterLoinc, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $masterAgentId = $stmt->fetchColumn(0);
                }
            } catch (Throwable $ex) {
                $ex = null;
            } finally {
                $stmt = null;
            }
        }
        
        return $masterAgentId;
    }
    
    /**
     * Get Morbidity and Contact Whitelist Rules for a given condition name.
     *
     * @param PDO    $dbConn                 EMSA database connection.
     * @param int    $appId                  Application ID
     * @param string $appConditionCodedValue Coded value representing the condition name for the specified application.
     *
     * @return WhitelistRuleSet
     */
    public static function getWhitelistRulesByAppCondition(PDO $dbConn, $appId, $appConditionCodedValue)
    {
        $whitelistRules = new WhitelistRuleSet();
        
        try {
            $sql = "SELECT mc.white_rule AS whitelist_rule, mc.contact_white_rule AS contact_whitelist_rule, mc.whitelist_override AS whitelist_override, mc.whitelist_ignore_case_status AS whitelist_ignore_case_status
                    FROM vocab_master_condition mc
                    INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
                    INNER JOIN vocab_master2app m2a ON (mv.id = m2a.master_id)
                    WHERE mv.category = vocab_category_id('condition')
                    AND m2a.app_id = :appId
                    AND m2a.coded_value ILIKE :conditionCoded";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', (int) $appId, PDO::PARAM_INT);
            $stmt->bindValue(':conditionCoded', (string) $appConditionCodedValue, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                while ($row = $stmt->fetchObject()) {
                    $whitelistRules->setContactWhitelistRule(new ContactWhitelistRule((string) $row->contact_whitelist_rule, (bool) $row->whitelist_override, (bool) $row->whitelist_ignore_case_status));
                    $whitelistRules->setMorbidityWhitelistRule(new WhitelistRule((string) $row->whitelist_rule, (bool) $row->whitelist_override, (bool) $row->whitelist_ignore_case_status));
                }
            }
        } catch (Throwable $ex) {
            $ex = null;
        } finally {
            $stmt = null;
        }
        
        return $whitelistRules;
    }

    /**
     * Determine whether a duplicate vocabulary item exists before creation.
     *
     * @param PDO   $db
     * @param int   $tbl One of <b>VocabTable</b> constants, indicating which vocabulary object is being checked for
     * @param array ...$params Parameters to send to the query, in the form of <i>[ 'key' => ':PDOParamId', 'value' => 'Value of parameter' ]</i>
     *
     * @return bool
     */
    public static function duplicateVocabExists(PDO $db, int $tbl, array ...$params): bool
    {
        $duplicateVocabExists = false;
        $stmt = null;
        $sql = null;
        $paramList = [];

        try {
            if ($tbl == VocabTable::CHILD_ORGANISM) {
                $sql = "SELECT count(*)
                        FROM vocab_child_organism
                        WHERE lab_id = :labId
                        AND lower(child_code) = lower(:childCode);";
                foreach ($params as $param) {
                    $paramList[$param['key']] = $param['value'];
                }
                $stmt = $db->prepare($sql);
            }

            if (!empty($stmt) && $stmt->execute($paramList)) {
                if ((int) $stmt->fetchColumn(0) > 0) {
                    $duplicateVocabExists = true;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $duplicateVocabExists;
    }

    /**
     * @param int         $vocabTable
     * @param string|null $searchValue
     * @param int|null    $labId
     *
     * @return string|null
     */
    public static function getLinkToVocab(int $vocabTable, ?string $searchValue = null, ?int $labId = null): ?string
    {
        $vocabLink = null;
        $linkPrefix = MAIN_URL . '/';

        if (empty($searchValue)) {
            return $vocabLink;
        }

        $childVocabTables = [VocabTable::CHILD_ORGANISM, VocabTable::CHILD_LOINC, VocabTable::CHILD_DICTIONARY];
        if (in_array($vocabTable, $childVocabTables) && empty($labId)) {
            return $vocabLink;
        }

        $cleanLabId = (int) filter_var($labId, FILTER_VALIDATE_INT);
        $cleanSearchValue = urlencode(trim($searchValue));
        $escapedDisplayText = DisplayUtils::xSafe($searchValue, "UTF-8", false);

        switch ($vocabTable) {
            case VocabTable::CHILD_LOINC:
                $vocabLink = "{$linkPrefix}?q={$cleanSearchValue}&f[archived][]=f&f[archived][]=t&f[lab][]={$cleanLabId}&selected_page=6&submenu=3&cat=2&subcat=5";
                break;
            case VocabTable::CHILD_ORGANISM:
                $vocabLink = "{$linkPrefix}?q={$cleanSearchValue}&f[lab][]={$cleanLabId}&selected_page=6&submenu=3&cat=2&subcat=4";
                break;
            case VocabTable::MASTER_LOINC:
                $vocabLink = "{$linkPrefix}?q={$cleanSearchValue}&selected_page=6&submenu=3&cat=1&subcat=1";
                break;
            default:
                $vocabLink = null;
                break;
        }

        if (!empty($vocabLink)) {
            return "<a href='$vocabLink' target='_blank'>$escapedDisplayText</a>";
        } else {
            return $vocabLink;
        }
    }

    /**
     * Get the description for a given ICD code for a specific lab and coding system
     *
     * @param PDO    $db
     * @param int    $labId
     * @param string $codeSystem
     * @param string $codeValue
     *
     * @return string|null
     */
    public static function getICDCodeDescription(PDO $db, int $labId, string $codeSystem, string $codeValue): ?string
    {
        $codeDescription = "";

        try {
            $sql = "SELECT i.code_description
                    FROM vocab_icd i
                    INNER JOIN vocab_codeset c ON c.id = i.codeset_id
                    INNER JOIN vocab_child_codeset cc ON cc.master_codeset_id = c.id
                    WHERE cc.child_codeset_value = :codeSystem
                    AND cc.structure_labs_id = :labId
                    AND i.code_value = :codeValue;";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(":codeSystem", $codeSystem, PDO::PARAM_STR);
            $stmt->bindValue(":labId", $labId, PDO::PARAM_INT);
            $stmt->bindValue(":codeValue", $codeValue, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $codeDescription = trim($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $codeDescription;
    }
    
}

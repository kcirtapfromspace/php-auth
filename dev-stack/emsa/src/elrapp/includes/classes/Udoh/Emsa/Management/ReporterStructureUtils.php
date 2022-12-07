<?php

namespace Udoh\Emsa\Management;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

use PDO;
use PDOStatement;
use Throwable;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Utilities for managing Reporter XML Structure mapping
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ReporterStructureUtils
{
    /**
     * Get a list of the valid filter fields used by Reporter Structure Management.
     * 
     * @return array
     */
    public static function getFilterColumns()
    {
        $validFilterColumns = array(
            "lab" => array(
                "colname" => "h.lab_id", 
                "label" => "Reporter", 
                "filter" => TRUE, 
                "textsearch" => FALSE, 
                "filterlookup" => TRUE, 
                "has_id" => TRUE, 
                "lookupqry" => "SELECT sl.id AS value, sl.ui_name AS label FROM structure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", 
                "filtercolname" => "lab_id"), 
            "hl7_version" => array(
                "colname" => "h.message_version", 
                "label" => "Message Version", 
                "display" => TRUE, 
                "filter" => TRUE, 
                "textsearch" => FALSE, 
                "filterlookup" => TRUE, 
                "has_id" => FALSE, 
                "lookupqry" => "SELECT DISTINCT message_version AS label FROM structure_path_mirth ORDER BY message_version;", 
                "filtercolname" => "message_version"),
            "master_path" => array(
                "colname" => "p.xpath", 
                "label" => "Master XML Path", 
                "display" => TRUE, 
                "filter" => TRUE, 
                "textsearch" => TRUE, 
                "filterlookup" => TRUE, 
                "has_id" => TRUE, 
                "lookupqry" => "SELECT DISTINCT id AS value, element||' ('||xpath||')' AS label FROM structure_path ORDER BY 2;", 
                "filtercolname" => "master_path_id"), 
            "hl7_path" => array(
                "colname" => "h.xpath", 
                "label" => "HL7 XML Path", 
                "display" => TRUE, 
                "filter" => FALSE, 
                "textsearch" => TRUE)
        );
        
        return $validFilterColumns;
    }
    /**
     * Get matching Reporter XML mappings
     * 
     * @param PDO $dbConn
     * @param string $searchString Text search to apply to all searchable columns.
     * @param array $filters Selected filters to restrict search
     * 
     * @return PDOStatement
     */
    public static function getResults(PDO $dbConn, $searchString = null, array $filters = null)
    {
        $validFilterColumns = self::getFilterColumns();
        
        $sql = "SELECT h.id AS id, h.message_version AS message_version, h.xpath AS xpath, h.master_path_id AS master_path_id, h.sequence AS sequence, h.glue_string AS glue_string, l.id AS lab_id, l.ui_name AS lab_name, p.xpath AS master_xpath, p.element AS master_element
                FROM structure_path_mirth h 
                INNER JOIN structure_labs l ON (h.lab_id = l.id) 
                LEFT JOIN structure_path p ON (h.master_path_id = p.id)\n";
        
        // build WHERE clause
        $whereItems = array();
        
        if (!empty($searchString) & !empty($validFilterColumns)) {
            $textSearchItems = array();
            $textSearchString = null;
            
            foreach ($validFilterColumns as $searchColumn) {
                if ($searchColumn['textsearch']) {
                    $textSearchItems[] = '(' . (string) filter_var($searchColumn['colname'], FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH) . ' ILIKE :searchText)';
                }
            }
            
            if (!empty($textSearchItems)) {
                $textSearchString = '(' . implode(" OR ", $textSearchItems) . ')';
                $whereItems[] = $textSearchString;
            }
        }
        
        
        
        if (!empty($whereItems)) {
            $sql .= "WHERE " . implode(" AND ", $whereItems) . "\n";
        }
        
        $sql .= "ORDER BY h.lab_id, h.message_version, h.master_path_id, h.sequence, h.xpath;";
        
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':searchText', $searchString, PDO::PARAM_STR);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Check whether a Reporter XML Mapping exists by ID.
     * 
     * @param PDO $dbConn
     * @param int $pathId
     * 
     * @return boolean
     */
    public static function pathExists(PDO $dbConn, $pathId)
    {
        $rowExists = false;
        
        try {
            $sql = "SELECT count(id) FROM structure_path_mirth WHERE id = :pathId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':pathId', $pathId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $rowsFound = (int) $stmt->fetchColumn(0);
                
                if ($rowsFound === 1) {
                    $rowExists = true;
                }
            }
            
            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $rowExists;
    }

    /**
     * Check whether the specified XSL Transformation exists
     *
     * @param PDO $dbConn
     * @param int $docId ID of the XSL Transformation doc to check for
     *
     * @return bool
     */
    public static function xsltExists(PDO $dbConn, int $docId)
    {
        $xsltExists = false;

        try {
            $sql = "SELECT count(id) FROM structure_xslt WHERE id = :docId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':docId', $docId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $rowsFound = (int) $stmt->fetchColumn(0);

                if ($rowsFound === 1) {
                    $xsltExists = true;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $xsltExists;
    }

    /**
     * Check whether an XSL Transformation exists for the specified reporter and message version.
     *
     * @param PDO      $dbConn
     * @param int      $labId          Reporter ID
     * @param string   $messageVersion Message version (e.g. 34133-9 for CCD, 55751-2 for Public Health Case Reports, etc
     *
     * @return bool
     */
    public static function xsltExistsForVersion(PDO $dbConn, int $labId, string $messageVersion)
    {
        $xsltExists = false;

        try {
            $sql = "SELECT count(id) 
                    FROM structure_xslt 
                    WHERE structure_labs_id = :pathId
                    AND message_version = :msgVersion;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':pathId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':msgVersion', $messageVersion, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $rowsFound = (int) $stmt->fetchColumn(0);

                if ($rowsFound > 0) {
                    $xsltExists = true;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $xsltExists;
    }

    /**
     * Add a single new Reporer XML element mapping.
     * 
     * @param PDO $dbConn
     * @param int $labId
     * @param int $masterPathId
     * @param int $sequence
     * @param string $messageVersion
     * @param string $reporterXPath
     * @param string $concatString
     * 
     * @return boolean
     */
    public static function addSinglePath(PDO $dbConn, $labId, $masterPathId, $sequence, $messageVersion, $reporterXPath, $concatString)
    {
        $status = false;
        
        if (empty($sequence)) {
            $sequence = 1;
        }
        
        if (empty($concatString)) {
            $concatString = null;
        }
        
        if (empty($masterPathId)) {
            $masterPathId = null;
        }
        
        try {
            $sql = "INSERT INTO structure_path_mirth (lab_id, message_version, master_path_id, glue_string, xpath, sequence) 
                    VALUES (:labId, :msgVersion, :masterPathId, :concatString, :reporterXPath, :sequence);";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':masterPathId', $masterPathId, PDO::PARAM_INT);
            $stmt->bindValue(':sequence', $sequence, PDO::PARAM_INT);
            $stmt->bindValue(':msgVersion', trim($messageVersion), PDO::PARAM_STR);
            $stmt->bindValue(':concatString', $concatString, PDO::PARAM_STR);
            $stmt->bindValue(':reporterXPath', trim($reporterXPath), PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $status = true;
            }
            
            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $status;
    }

    /**
     * Add a new XSL Transformation
     *
     * @param PDO         $dbConn
     * @param int         $labId          Reporter ID
     * @param string      $messageVersion Message version (e.g. 34133-9 for CCD, 55751-2 for Public Health Case Reports, etc
     * @param string|null $xsltBody       The XSL Transformation document body
     *
     * @return bool
     */
    public static function addXSLT(PDO $dbConn, int $labId, string $messageVersion, ?string $xsltBody = null)
    {
        $status = false;

        if (empty($xsltBody)) {
            $xsltBody = null;
        }

        try {
            $sql = "INSERT INTO structure_xslt (structure_labs_id, message_version, xslt) 
                    VALUES (:labId, :msgVersion, :xsltBody);";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':msgVersion', trim($messageVersion), PDO::PARAM_STR);
            $stmt->bindValue(':xsltBody', $xsltBody, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $status = true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $status;
    }

    /**
     * Clone XML mapping from one reporter to another.
     * 
     * @param PDO $dbConn
     * @param int $cloneFromId
     * @param int $cloneToId
     */
    public static function cloneReporterXMLMapping(PDO $dbConn, $cloneFromId, $cloneToId)
    {
        $sql = "INSERT INTO structure_path_mirth (lab_id, message_version, master_path_id, glue_string, xpath, sequence)
                SELECT :cloneToId, message_version, master_path_id, glue_string, xpath, sequence 
                FROM structure_path_mirth 
                WHERE lab_id = :cloneFromId;";
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':cloneToId', $cloneToId, PDO::PARAM_INT);
        $stmt->bindValue(':cloneFromId', $cloneFromId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        $stmt = null;
    }
    
    /**
     * Deletes a single Reporter XML path
     * 
     * @param PDO $dbConn
     * @param int $pathId
     * 
     * @return boolean
     */
    public static function deleteSinglePath(PDO $dbConn, $pathId)
    {
        $status = false;
        
        try {
            $sql = "DELETE FROM ONLY structure_path_mirth WHERE id = :pathId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':pathId', $pathId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $status = true;
            }
            
            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $status;
    }

    /**
     * Delete an XSL Transformation
     *
     * @param PDO $dbConn
     * @param int $docId ID of the XSL Transformation to delete
     *
     * @return bool
     */
    public static function deleteXSLT(PDO $dbConn, int $docId)
    {
        $status = false;

        try {
            $sql = "DELETE FROM ONLY structure_xslt WHERE id = :docId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':docId', $docId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $status = true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $status;
    }

    /**
     * Save changes to a Reporter XML mapping
     * 
     * @param PDO $dbConn
     * @param int $pathId
     * @param int $labId
     * @param int $masterPathId
     * @param int $sequence
     * @param string $messageVersion
     * @param string $reporterXPath
     * @param string $concatString
     * 
     * @return boolean
     */
    public static function updateSinglePath(PDO $dbConn, $pathId, $labId, $masterPathId, $sequence, $messageVersion, $reporterXPath, $concatString)
    {
        $status = false;
        
        if (empty($sequence)) {
            $sequence = 1;
        }
        
        if (empty($concatString)) {
            $concatString = null;
        }
        
        if (empty($masterPathId)) {
            $masterPathId = null;
        }
        
        try {
            $sql = "UPDATE structure_path_mirth 
                    SET lab_id = :labId, message_version = :msgVersion, master_path_id = :masterPathId, glue_string = :concatString, xpath = :reporterXPath, sequence = :sequence 
                    WHERE id = :pathId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':pathId', $pathId, PDO::PARAM_INT);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':masterPathId', $masterPathId, PDO::PARAM_INT);
            $stmt->bindValue(':sequence', $sequence, PDO::PARAM_INT);
            $stmt->bindValue(':msgVersion', trim($messageVersion), PDO::PARAM_STR);
            $stmt->bindValue(':concatString', $concatString, PDO::PARAM_STR);
            $stmt->bindValue(':reporterXPath', trim($reporterXPath), PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $status = true;
            }
            
            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $status;
    }

    /**
     * Save changes to an XSL Transformation
     *
     * @param PDO         $dbConn
     * @param int         $docId          ID of the XSL Transformation to update
     * @param int         $labId          Reporter ID
     * @param string      $messageVersion Message version (e.g. 34133-9 for CCD, 55751-2 for Public Health Case Reports, etc
     * @param string|null $xsltBody       The new XSL Transformation document body
     *
     * @return bool
     */
    public static function updateXSLT(PDO $dbConn, int $docId, int $labId, string $messageVersion, ?string $xsltBody = null)
    {
        $status = false;

        if (empty($xsltBody)) {
            $xsltBody = null;
        }

        try {
            $sql = "UPDATE structure_xslt
                    SET structure_labs_id = :labId, message_version = :msgVersion, xslt = :xsltBody 
                    WHERE id = :pathId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':pathId', $docId, PDO::PARAM_INT);
            $stmt->bindValue(':labId', $labId, PDO::PARAM_INT);
            $stmt->bindValue(':msgVersion', trim($messageVersion), PDO::PARAM_STR);
            $stmt->bindValue(':xsltBody', $xsltBody, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $status = true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $status;
    }
}

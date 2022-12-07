<?php

namespace Udoh\Emsa\Utils;

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
use Throwable;

/**
 * Core EMSA Utilities
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class CoreUtils
{

    /**
     * Indicates whether a State identified by the two-character USPS code from the patient's address is one we are sending Interstate ELR messages to.
     *
     * @param PDO    $db
     * @param string $stateCode
     *
     * @return bool
     */
    public static function isStateParticipatingInterstateTx(PDO $db, ?string $stateCode = null): bool
    {
        $isParticipating = false;

        try {
            $sql = "SELECT count(*) FROM interstate WHERE LOWER(state) = :stateLower AND transmitting IS TRUE;";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(":stateLower", strtolower(trim($stateCode)), PDO::PARAM_STR);

            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) > 0) {
                    $isParticipating = true;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $isParticipating;
    }

    /**
     * Get a list of configured Applications in EMSA.
     *
     * @param PDO  $dbConn
     * @param bool $enabledOnly [Optional; Default <b>FALSE</b>]<br>If <b>TRUE</b>, only returns applications set to <i>enabled</i>.
     *
     * @return array An array with <i>id => 'name'</i> pair for each Application.  Returns empty array on error.
     */
    public static function getAppList(PDO $dbConn, ?bool $enabledOnly = false): array
    {
        $appList = array();
        $enabledOnly = $enabledOnly ?? false;
        
        try {
            if ($enabledOnly) {
                $sql = "SELECT id, app_name 
                        FROM vocab_app 
                        WHERE enabled IS TRUE 
                        ORDER BY id;";
                $stmt = $dbConn->query($sql);
            } else {
                $sql = "SELECT id, app_name 
                        FROM vocab_app 
                        ORDER BY id;";
                $stmt = $dbConn->query($sql);
            }

            while ($row = $stmt->fetchObject()) {
                $appList[(int) filter_var($row->id, FILTER_SANITIZE_NUMBER_INT)] = (string) filter_var($row->app_name, FILTER_SANITIZE_STRING);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $appList;
    }
    
    /**
     * Get a list of hosts to use for Round Robin load balancing for a specified application.
     * 
     * @param PDO $dbConn
     * @param int $appId Application ID
     * 
     * @return array
     */
    public static function getAppRoundRobinHosts(PDO $dbConn, int $appId): array
    {
        $roundRobinHosts = array();
        
        try {
            $sql = "SELECT host_addr FROM app_client_hosts
                    WHERE app_id = :appId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                while ($row = $stmt->fetchObject()) {
                    $roundRobinHosts[] = (string) $row->host_addr;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
        
        return $roundRobinHosts;
    }
    
    /**
     * Get a list of active (visible) reporters configured in EMSA in an id=>name array.
     *
     * @param PDO  $dbConn
     * @param bool $includeHidden [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, include non-visible reporters.
     *
     * @return array An array with <i>id => 'name'</i> pair for each active reporter.  Returns empty array on error.
     */
    public static function getReporterList(PDO $dbConn, ?bool $includeHidden = false): array
    {
        $reporterList = array();
        $includeHidden = $includeHidden ?? false;
        
        try {
            if ($includeHidden) {
                $sql = "SELECT id, ui_name 
                        FROM structure_labs 
                        WHERE alias_for = 0 
                        ORDER BY ui_name;";
            } else {
                $sql = "SELECT id, ui_name 
                        FROM structure_labs 
                        WHERE visible IS TRUE AND alias_for = 0 
                        ORDER BY ui_name;";
            }
            $stmt = $dbConn->query($sql);
            
            while ($row = $stmt->fetchObject()) {
                $reporterList[(int) $row->id] = (string) $row->ui_name;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
        
        return $reporterList;
    }

    /**
     * Get the name of a reporting facility based on the lab id.
     * @param PDO $db
     * @param int $labId
     *
     * @return string
     */
    public static function getReporterUINameByID(PDO $db, int $labId): string
    {
        $reporterName = "";

        try {
            $sql = "SELECT ui_name FROM structure_labs WHERE id = ?;";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, $labId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $reporterName = (string) $stmt->fetchColumn(0);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $reporterName;
    }
    
    /**
     * Get a sorted list of unique connector names from the raw ELR messsages table.
     *
     * @param PDO $dbConn
     * @param array $excludedConnectors [Optional] List of connectors to exclude from the results
     *
     * @return array
     */
    public static function getELRConnectorList(PDO $dbConn, ?array $excludedConnectors = array())
    {
        $connectorList = array();

        try {
            $sql = "SELECT connector
                    FROM system_original_messages
                    GROUP BY connector
                    ORDER BY connector;";
            $stmt = $dbConn->query($sql);

            if ($stmt !== false) {
                while ($row = $stmt->fetchObject()) {
                    if (!empty(trim($row->connector))) {
                        if (empty($excludedConnectors) || !in_array((string)$row->connector, $excludedConnectors)) {
                            $connectorList[] = (string) $row->connector;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $connectorList;
    }

    /**
     * Case-insensitive string comparison.
     * 
     * @param string $a
     * @param string $b
     * 
     * @return bool Returns <b>TRUE</b> if both strings are empty, or if both strings are non-empty and have the same value.  Returns <b>FALSE</b> for all other cases.
     */
    public static function mbStrCaseCmp(?string $a = null, ?string $b = null): bool
    {
        if (empty($a) xor empty($b)) {
            return false;
        }
        
        if (empty($a) && empty($b)) {
            return true;
        }
        
        if (mb_strtolower(trim($a)) == mb_strtolower(trim($b))) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks to see if the given string is Base64-encoded or not; if so, returns decoded value
     *
     * @param string $input
     *
     * @return string
     */
    public static function decodeIfBase64Encoded(string $input): string
    {
        if (base64_encode(base64_decode($input)) === $input) {
            return base64_decode($input);
        } else {
            return $input;
        }
    }
    
    /**
     * Generates a random RGB Hex Color string (e.g. "#3366CC") based on a given seed.
     * 
     * @param mixed $inputSeed Input value to use as a seed to generate the color.<br>Same seed should always generate the same color.<br>If empty, will generate a new random color each call.
     * 
     * @return string
     */
    public static function randomRGBHex($inputSeed = null): string
    {
        $colorStr = '#';
        
        if (empty($inputSeed)) {
            try {
                $rSeed = dechex(random_int(0, 255));
                $gSeed = dechex(random_int(0, 255));
                $bSeed = dechex(random_int(0, 255));
            } catch (Throwable $e) {
                $rSeed = dechex(mt_rand(0, 255));
                $gSeed = dechex(mt_rand(0, 255));
                $bSeed = dechex(mt_rand(0, 255));
            }
        } else {
            $seedHash = md5($inputSeed);
            $rSeed = substr($seedHash, 0, 2);
            $gSeed = substr($seedHash, 5, 2);
            $bSeed = substr($seedHash, 10, 2);
        }
        
        $rStr = str_pad($rSeed, 2, '0', STR_PAD_LEFT);
        $gStr = str_pad($gSeed, 2, '0', STR_PAD_LEFT);
        $bStr = str_pad($bSeed, 2, '0', STR_PAD_LEFT);
        
        $colorStr .= $rStr . $gStr . $bStr;
        
        return $colorStr;
    }

    /**
     * Return a usable comparison operator for a given operator ID
     * Returns false if invalid ID is passed or no ID specified
     *
     * @param int $operator_id Operator ID
     *
     * @return string|bool
     */
    public static function operatorById($operator_id = null)
    {
        $o_id = filter_var($operator_id, FILTER_VALIDATE_INT);
        if (!$o_id) {
            return false;
        }

        switch ($o_id) {
            case 1:
                return "==";
            case 2:
                return "!=";
            case 3:
                return ">";
            case 4:
                return "<";
            case 5:
                return ">=";
            case 6:
                return "<=";
            case 10:
                return "Contains";
            case 11:
                return "Does Not Contain";
            default:
                return false;
        }
    }

    /**
     * Return an operator ID for a given comparison operator symbol
     * Returns false if invalid operator is passed or no operator specified
     *
     * @param string $operator Operator Symbol
     *
     * @return int|bool
     */
    public static function operatorBySymbol($operator = null)
    {
        if (is_null($operator)) {
            return false;
        }

        $o = trim($operator);

        switch ($o) {
            case "=":
            case "==":
                return 1;
            case "!=":
            case "<>":
                return 2;
            case ">":
                return 3;
            case "<":
                return 4;
            case ">=":
                return 5;
            case "<=":
                return 6;
            case "Contains":
                return 10;
            case "Does Not Contain":
                return 11;
            default:
                return false;
        }
    }

}

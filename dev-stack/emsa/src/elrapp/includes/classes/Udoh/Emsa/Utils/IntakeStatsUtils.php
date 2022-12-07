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

use PDO;
use Throwable;

/**
 * Functions for use with Intake Monitoring configuration.
 * 
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class IntakeStatsUtils
{

    /**
     * Get an array of connectors enabled for intake volume monitoring.
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * 
     * @return string[] Array of connector names.  Returns empty array on error or if no connectors are configured.
     */
    public static function getActiveConnectors(PDO $dbConn)
    {
        $connectors = array();

        try {
            $sql = "SELECT connectors 
                    FROM intake_stats_config WHERE id = 1;";
            $stmt = $dbConn->query($sql);

            if (($stmt !== false) && ($stmt->rowCount() === 1)) {
                $implodedConnnectors = $stmt->fetchColumn(0);
                $connectors = preg_split("/[;,]/", $implodedConnnectors);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $connectors;
    }

    /**
     * Get an array of reporting facilities enabled for intake volume monitoring
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * 
     * @return array Array of reporting facilities.<br><br>Each reporting facility contains:<br><b>id</b>:  Facility ID<br><b>name</b>: Facility name
     */
    public static function getActiveReporters(PDO $dbConn)
    {
        $reportersTemp = array();
        $reporters = array();

        try {
            $sql = "SELECT reporters 
                    FROM intake_stats_config WHERE id = 1;";
            $stmt = $dbConn->query($sql);

            if (($stmt !== false) && ($stmt->rowCount() === 1)) {
                $implodedReporters = $stmt->fetchColumn(0);
                $reportersTemp = preg_split("/[;,]/", $implodedReporters);
            }

            if (count($reportersTemp) > 0) {
                $nSql = "SELECT id, ui_name
					FROM structure_labs
					WHERE id IN (" . implode(',', array_map(function($reporter_token) {
                                    return intval($reporter_token);
                                }, $reportersTemp)) . ")
					ORDER BY id;";
                $nStmt = $dbConn->query($nSql);

                if (($nStmt !== false) && ($nStmt->rowCount() > 0)) {
                    while ($nRow = $nStmt->fetchObject()) {
                        $reporters[] = array(
                            'id' => intval($nRow->id),
                            'name' => trim($nRow->ui_name)
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $reporters;
    }

}

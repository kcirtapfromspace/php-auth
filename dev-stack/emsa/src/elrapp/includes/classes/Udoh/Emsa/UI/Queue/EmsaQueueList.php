<?php

namespace Udoh\Emsa\UI\Queue;

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
use EmsaUtils;
use EmsaListResult;
use EmsaMessage;
use PDOStatement;
use Throwable;
use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Functionality for displaying a list of messages in an EMSA queue.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaQueueList
{

    /** @var PDO */
    protected $dbConn;

    /** @var AppClientList */
    protected $appClientList;

    const RESTRICT_NON_IMMEDIATE = 0;
    const RESTRICT_IMMEDIATE = 1;
    const RESTRICT_SHOW_ALL = 2;

    /**
     * Create a new EmsaQueueList object.
     * @param PDO $dbConn PDO connection to the EMSA database
     * @param AppClientList $appClientList List of configured client applications for this installation.
     */
    public function __construct(PDO $dbConn, AppClientList $appClientList)
    {
        $this->dbConn = $dbConn;
        $this->appClientList = $appClientList;
    }

    /**
     * Get a count of messages matching the given parameters.
     *
     * @param int  $queueId               EMSA Queue ID being viewed
     * @param int  $offset                [Optional]<br>SQL record offset, for pagination.  Default = 0.
     * @param int  $pageSize              [Optional]<br>Number of records to display per page of results.  Default = 25.
     * @param int  $immediatelyNotifiable [Optional; Default = <b>EmsaQueueList::RESTRICT_NON_IMMEDIATE</b>]<br>"Immediately Notifiable" visibility setting:<br>EmsaQueueList::RESTRICT_NON_IMMEDIATE = Show only non-immediately reportable conditions<br>EmsaQueueList::RESTRICT_IMMEDIATE = Show only immediately reportable conditions<br>EmsaQueueList::RESTRICT_SHOW_ALL = Show all conditions whether immediately or non-immediately reportable
     * @param int  $focusId               [Optional]<br>If specified, ignores <i>queueId</i> and attempts to locate the specified message ID within limits of the user's visibility permissions.
     *
     * @return int
     */
    public function getMessageCount($queueId, $offset = 0, $pageSize = 25, $immediatelyNotifiable = 0, $focusId = null): int
    {
        $msgCount = 0;
        $resultsStmt = $this->getQueueResults($queueId, $offset, $pageSize, $immediatelyNotifiable, true, $focusId);

        if (!empty($resultsStmt)) {
            $msgCount = (int) $resultsStmt->fetchColumn(0);
        }

        $resultsStmt = null;
        return $msgCount;
    }

    /**
     * Return the matching messages in an EmsaQueueListItemSet container.
     *
     * @param int  $queueId               EMSA Queue ID being viewed
     * @param int  $offset                [Optional]<br>SQL record offset, for pagination.  Default = 0.
     * @param int  $pageSize              [Optional]<br>Number of records to display per page of results.  Default = 25.
     * @param int  $immediatelyNotifiable [Optional; Default = <b>EmsaQueueList::RESTRICT_NON_IMMEDIATE</b>]<br>"Immediately Notifiable" visibility setting:<br>EmsaQueueList::RESTRICT_NON_IMMEDIATE = Show only non-immediately reportable conditions<br>EmsaQueueList::RESTRICT_IMMEDIATE = Show only immediately reportable conditions<br>EmsaQueueList::RESTRICT_SHOW_ALL = Show all conditions whether immediately or non-immediately reportable
     * @param int  $focusId               [Optional]<br>If specified, ignores <i>queueId</i> and attempts to locate the specified message ID within limits of the user's visibility permissions.
     *
     * @return EmsaQueueListItemSet
     */
    public function getEmsaQueueListItemSet($queueId, $offset = 0, $pageSize = 25, $immediatelyNotifiable = 0, $focusId = null): EmsaQueueListItemSet
    {
        $queueListItemSet = new EmsaQueueListItemSet();
        $resultsStmt = $this->getQueueResults($queueId, $offset, $pageSize, $immediatelyNotifiable, false, $focusId);

        if (!empty($resultsStmt)) {
            while ($row = $resultsStmt->fetchObject()) {
                    try {
                        $queueListItem = new EmsaQueueListItem($this->dbConn, $this->appClientList);
                        $queueListItem->setId($row->sm_id);
                        $queueListItem->setOriginalMessageId($row->sm_original_message_id);
                        $queueListItem->setLastName($row->lname);
                        $queueListItem->setFirstName($row->fname);
                        $queueListItem->setMiddleName($row->mname);
                        $queueListItem->setCondition($row->condition);
                        $queueListItem->setLabTestResult($row->lab_test_result);
                        $queueListItem->setSusceptibilityTestResult($row->susceptibility_test_result);
                        $queueListItem->setReportingInterfaceId($row->reporting_facility_id);
                        $queueListItem->setReportingInterfaceName($row->reporting_facility_name);
                        $queueListItem->setReportedDateTime(DateTimeUtils::createMixed($row->reported_at));

                        if (!empty($row->dob)) {
                            // if DOB is null/empty, calling this would set DOB to today, which is wrong.  is there air?  you don't know!
                            $queueListItem->setDateOfBirth(DateTimeUtils::createMixed($row->dob));
                        }

                        if ($queueId === ASSIGNED_STATUS) {
                            $queueListItem->setDisplayDateTime(DateTimeUtils::createMixed($row->assigned_date));
                        } elseif ($queueId === GRAY_STATUS) {
                            $queueListItem->setDisplayDateTime(DateTimeUtils::createMixed($row->assigned_date));
                        } else {
                            $queueListItem->setDisplayDateTime(DateTimeUtils::createMixed($row->reported_at));
                        }

                        $queueListItemSet->add($queueListItem);
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                    }
                }
        }

        return $queueListItemSet;
    }

    /**
     * Return the matching messages in an EmsaListResultSet container.
     *
     * @param int  $queueId               EMSA Queue ID being viewed
     * @param int  $offset                [Optional]<br>SQL record offset, for pagination.  Default = 0.
     * @param int  $pageSize              [Optional]<br>Number of records to display per page of results.  Default = 25.
     * @param int  $immediatelyNotifiable [Optional; Default = <b>EmsaQueueList::RESTRICT_NON_IMMEDIATE</b>]<br>"Immediately Notifiable" visibility setting:<br>EmsaQueueList::RESTRICT_NON_IMMEDIATE = Show only non-immediately reportable conditions<br>EmsaQueueList::RESTRICT_IMMEDIATE = Show only immediately reportable conditions<br>EmsaQueueList::RESTRICT_SHOW_ALL = Show all conditions whether immediately or non-immediately reportable
     * @param int  $focusId               [Optional]<br>If specified, ignores <i>queueId</i> and attempts to locate the specified message ID within limits of the user's visibility permissions.
     *
     * @return EmsaListResultSet
     */
    public function getEmsaListResultSet($queueId, $offset = 0, $pageSize = 25, $immediatelyNotifiable = 0, $focusId = null): EmsaListResultSet
    {
        $listResultSet = new EmsaListResultSet();
        $resultsStmt = $this->getQueueResults($queueId, $offset, $pageSize, $immediatelyNotifiable, false, $focusId);

        if (!empty($resultsStmt)) {
            while ($row = $resultsStmt->fetchObject()) {
                    try {
                        $listResultSet->addResult(
                            new EmsaListResult(
                                $this->dbConn, new EmsaMessage(
                                    $this->dbConn, $this->appClientList, (int) $row->sm_id, false, true, true
                                ), $this->appClientList
                            )
                        );
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                    }
                }
        }

        return $listResultSet;
    }

    /**
     * Performs a query against the EMSA database to find matching messages for a specific EMSA queue, based on the user's visibility permissions and selected filters.
     *
     * @param int  $queueId               EMSA Queue ID being viewed
     * @param int  $offset                [Optional]<br>SQL record offset, for pagination.  Default = 0.
     * @param int  $pageSize              [Optional]<br>Number of records to display per page of results.  Default = 25.
     * @param int  $immediatelyNotifiable [Optional; Default = <b>EmsaQueueList::RESTRICT_NON_IMMEDIATE</b>]<br>"Immediately Notifiable" visibility setting:<br>EmsaQueueList::RESTRICT_NON_IMMEDIATE = Show only non-immediately reportable conditions<br>EmsaQueueList::RESTRICT_IMMEDIATE = Show only immediately reportable conditions<br>EmsaQueueList::RESTRICT_SHOW_ALL = Show all conditions whether immediately or non-immediately reportable
     * @param bool $countOnly             [Optional]<br>Indicates whether this query should return a set of records, or a count of total matching rows.  Default <b>FALSE</b> (set of records).
     * @param int  $focusId               [Optional]<br>If specified, ignores <i>queueId</i> and attempts to locate the specified message ID within limits of the user's visibility permissions.
     *
     * @return PDOStatement|null
     */
    protected function getQueueResults($queueId, $offset = 0, $pageSize = 25, $immediatelyNotifiable = 0, $countOnly = false, $focusId = null): ?PDOStatement
    {
        if (((int) $queueId < 0) && ((int) $focusId < 0)) {
            return null;
        }

        if (empty($offset)) {
            $offset = 0;
        }

        if (empty($pageSize)) {
            $pageSize = 25;
        }

        if (empty($countOnly)) {
            $countOnly = false;
        }

        if (empty($immediatelyNotifiable)) {
            $immediatelyNotifiable = 0;
        }

        $selectCols = (($countOnly) ? 'count(*) AS counter' : 'sm.id AS sm_id, sm.original_message_id AS sm_original_message_id, sm.reported_at AS reported_at, sm.assigned_date AS assigned_date, sm.fname AS fname, sm.lname AS lname, sm.mname AS mname, sm.dob AS dob, sm.disease AS condition, sm.lab_test_result AS lab_test_result, sm.susceptibility_test_result AS susceptibility_test_result, l.ui_name AS reporting_facility_name, l.id AS reporting_facility_id');

        $sql = "SELECT DISTINCT $selectCols\n";
        $sql .= "FROM system_messages sm\n";

        if (!$countOnly) {
            // don't need to JOIN to structure_labs table if only doing a count of messages
            // should help coerce query planner in postgres to do Index Only Scan for faster counts without the JOIN
            $sql .= "INNER JOIN structure_labs l ON (sm.lab_id = l.id)\n";
        }

        if (isset($_SESSION[EXPORT_SERVERNAME]['override_user_role']) && (intval($_SESSION[EXPORT_SERVERNAME]['override_user_role']) > 0)) {
            // specific role selected from role override menu; use selected role
            if (!$_SESSION[EXPORT_SERVERNAME]['override_is_admin'] && !$_SESSION[EXPORT_SERVERNAME]['override_is_qa']) {
                // if user is admin or qa user, ignore roles & show all conditions; otherwise filter
                $sql .= "INNER JOIN auth_conditions ac ON ((lower(ac.condition) = lower(sm.disease)) AND (ac.role_id = " . intval($_SESSION[EXPORT_SERVERNAME]['override_user_role']) . "))\n";
            }
        } else {
            // use sum of user's roles
            if (!$_SESSION[EXPORT_SERVERNAME]['is_admin'] && !$_SESSION[EXPORT_SERVERNAME]['is_qa']) {
                // if user is admin or qa user, ignore roles & show all conditions; otherwise filter
                $sql .= "INNER JOIN auth_conditions ac ON ((lower(ac.condition) = lower(sm.disease)) AND (ac.role_id IN (" . implode(',', array_map('intval', $_SESSION[EXPORT_SERVERNAME]['user_system_roles'])) . ")))\n";
            }
        }

        if ((int) $focusId > 0) {
            $sql .= "WHERE (sm.id = :focusId)\n";
        } else {
            if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testtype"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testtype"])) {
                $sql .= "INNER JOIN vocab_master_loinc ml ON (sm.loinc_code = ml.loinc)\n";  // if filtering by test type, have to JOIN to master LOINC table
            }
            $sql .= "WHERE (sm.vocab_app_id = 2) AND (sm.final_status = :finalStatus)\n";
            if ($queueId == GRAY_STATUS) {
                // for Graylist, only show messages reported in the past 30 days
                $sql .= "AND (sm.reported_at > (CURRENT_TIMESTAMP - interval '30 days'))\n";
            }

            $sql .= $this->applyFilters($queueId);
            $sql .= $this->restrictImmediatelyNotifiable($immediatelyNotifiable);
            $sql .= $this->restrictIsAutomated();
            $sql .= $this->restrictShowDeleted();
        }

        $sql .= $this->getOrderBy($queueId, $countOnly);

        $stmt = $this->dbConn->prepare($sql);
        if (!$countOnly) {
            $stmt->bindValue(':pageSize', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        if ((int) $focusId > 0) {
            $stmt->bindValue(':focusId', $focusId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':finalStatus', $queueId, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            return $stmt;
        } else {
            return null;
        }
    }

    /**
     * Sets the "ORDER BY", "LIMIT", and "OFFSET" statements in the EMSA Queue List query based on queue type and user's preferences
     *
     * @param int  $queueId   Queue ID of the EMSA queue being viewed
     * @param bool $countOnly Indicates whether this query is performing a count-only operation, or returning a set of records
     *
     * @return string
     */
    private function getOrderBy($queueId, $countOnly)
    {
        $orderByStr = '';

        if (!$countOnly) {
            if ($queueId == ASSIGNED_STATUS && isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]) && isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["sort_order"])) {
                // if sort order is specified, use
                // otherwise, sort by assigned_date DESC (most recent at the top)
                // ...with a fallback to submit_date, just in case...
                switch ((int) $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["sort_order"]) {
                    case 6:
                        $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 5:
                        $orderByStr = "ORDER BY sm.assigned_date ASC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 4:
                        $orderByStr = "ORDER BY sm.lname DESC, sm.fname DESC, sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 3:
                        $orderByStr = "ORDER BY sm.lname ASC, sm.fname ASC, sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 2:
                        $orderByStr = "ORDER BY sm.reported_at DESC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 1:
                        $orderByStr = "ORDER BY sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    default:
                        $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                }
            } elseif ($queueId == ASSIGNED_STATUS) {
                // if assigned, sort by assigned_date DESC (most recent at the top)
                // ...with a fallback to submit_date, just in case...
                $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
            } elseif ($queueId == PENDING_STATUS) {
                // sort by immediately notifiable, then submit_date
                $orderByStr = "ORDER BY sm.immediate_notify DESC, sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
            } elseif ($queueId == GRAY_STATUS && isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]) && isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["sort_order"])) {
                // if sort order is specified, use
                // otherwise, sort by graylisted date DESC (newest at the top)
                switch ((int) $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["sort_order"]) {
                    case 8:
                        $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 7:
                        $orderByStr = "ORDER BY sm.assigned_date ASC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 6:
                        $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 5:
                        $orderByStr = "ORDER BY sm.assigned_date ASC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 4:
                        $orderByStr = "ORDER BY sm.lname DESC, sm.fname DESC, sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 3:
                        $orderByStr = "ORDER BY sm.lname ASC, sm.fname ASC, sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 2:
                        $orderByStr = "ORDER BY sm.reported_at DESC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    case 1:
                        $orderByStr = "ORDER BY sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                    default:
                        $orderByStr = "ORDER BY sm.assigned_date DESC NULLS LAST\nLIMIT :pageSize\nOFFSET :offset;";
                        break;
                }
            } else {
                // otherwise, sort by submit_date ASC (oldest at the top)
                $orderByStr = "ORDER BY sm.reported_at ASC\nLIMIT :pageSize\nOFFSET :offset;";
            }
        }

        return $orderByStr;
    }

    /**
     * Applies user-selected filter and query terms to EMSA queue listing query
     *
     * @param int $queueId Queue ID of the EMSA queue being viewed
     *
     * @return string
     */
    private function applyFilters($queueId)
    {
        $filterStr = '';

        if (($_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"] == EXCEPTIONS_STATUS) || ($_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"] == NEDSS_EXCEPTION_STATUS)) {
            if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["eflag"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["eflag"]) && (count($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["eflag"]) > 0)) {
                $filterStr .= "AND sm.id IN (SELECT DISTINCT system_message_id FROM system_message_exceptions WHERE exception_id IN (" . implode(",", array_map('intval', $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["eflag"])) . "))\n";  // filter for selected exception types
            }
            if (isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue']) && (strlen(trim($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue'])) > 0)) {
                $filterStr .= "AND sm.id IN (SELECT DISTINCT system_message_id FROM system_message_exceptions WHERE info ILIKE '" . pg_escape_string(CoreUtils::decodeIfBase64Encoded(rawurldecode(trim($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue'])))) . "')\n";  // filter for selected exception types
            }
        }

        // apply search terms...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["q_sql"]) && (strlen(trim($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["q_sql"])) > 0)) {
            $searchEscaped = pg_escape_string($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["q_sql"]);
            $filterStr .= "AND ( (sm.fname ILIKE '%" . $searchEscaped . "%') OR (sm.lname ILIKE '%" . $searchEscaped . "%') OR (sm.disease ILIKE '%" . $searchEscaped . "%') )\n";
        }

        // apply any lab-specific filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["lab"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["lab"])) {
            $labIdFilter = implode(",", array_map('intval', $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["lab"]));
            $filterStr .= "AND sm.lab_id IN (" . $labIdFilter . ")\n";
        }

        // apply any user-specified disease filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["disease"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["disease"])) {
            $filter_disease_counter = 0;
            foreach ($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["disease"] as $filter_disease_name) {
                if ($filter_disease_counter == 0) {
                    $filterStr .= " AND ((sm.disease ILIKE '" . @pg_escape_string(CoreUtils::decodeIfBase64Encoded(rawurldecode(trim($filter_disease_name)))) . "')";
                } else {
                    $filterStr .= " OR (sm.disease ILIKE '" . @pg_escape_string(CoreUtils::decodeIfBase64Encoded(rawurldecode(trim($filter_disease_name)))) . "')";
                }
                $filter_disease_counter++;
            }
            $filterStr .= ")\n";
        }

        // apply any user-specified test type filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testtype"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testtype"])) {
            $filterStr .= "AND (ml.trisano_test_type IN (" . implode(',', array_map('intval', $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testtype"])) . "))\n";
        }

        // apply any user-specified message flag filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["mflag"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["mflag"])) {
            $filterStr .= "AND (sm.message_flags & (" . implode(' | ', array_map('intval', $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["mflag"])) . ") <> 0)\n";
        } else {
            if ($queueId == OOS_STATUS) {
                // by default, hide stuff with "Investigation Completed" set in the "Out of State" queue
                $filterStr .= "AND (sm.message_flags & " . EMSA_FLAG_INVESTIGATION_COMPLETE . " = 0)\n";
            }
        }

        // apply any user-specified test result filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testresult"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testresult"])) {
            $filterTestResultCounter = 0;
            foreach ($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["testresult"] as $filterTestResult) {
                if ($filterTestResultCounter == 0) {
                    $filterStr .= " AND ((sm.lab_test_result ILIKE '" . @pg_escape_string($filterTestResult) . "')";
                } else {
                    $filterStr .= " OR (sm.lab_test_result ILIKE '" . @pg_escape_string($filterTestResult) . "')";
                }
                $filterTestResultCounter++;
            }
            $filterStr .= ")\n";
        }

        // apply any user-specified susceptibility result filters...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["astresult"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["astresult"])) {
            $filterASTResultCounter = 0;
            foreach ($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["astresult"] as $filterASTResult) {
                if ($filterASTResultCounter == 0) {
                    $filterStr .= " AND ((sm.susceptibility_test_result ILIKE '" . @pg_escape_string($filterASTResult) . "')";
                } else {
                    $filterStr .= " OR (sm.susceptibility_test_result ILIKE '" . @pg_escape_string($filterASTResult) . "')";
                }
                $filterASTResultCounter++;
            }
            $filterStr .= ")\n";
        }

        return $filterStr;
    }

    /**
     * Adds parameters to SQL query to restrict based on whether message is for an immediately-notifiable condition or not
     * 
     * @param int $immediatelyNotifiable [Optional; Default = EmsaQueueList::RESTRICT_NON_IMMEDIATE]<br>"Immediately Notifiable" visiblity parameter:<br>EmsaQueueList::RESTRICT_NON_IMMEDIATE = Show only non-immediately reportable conditions<br>EmsaQueueList::RESTRICT_IMMEDIATE = Show only immediately reportable conditions<br>EmsaQueueList::RESTRICT_SHOW_ALL = Show all conditions whether immediately or non-immediately reportable
     * 
     * @return string
     */
    private function restrictImmediatelyNotifiable($immediatelyNotifiable = self::RESTRICT_NON_IMMEDIATE)
    {
        if (EmsaUtils::emptyTrim($immediatelyNotifiable)) {
            $immediatelyNotifiable = self::RESTRICT_NON_IMMEDIATE;
        }

        if ($immediatelyNotifiable == self::RESTRICT_IMMEDIATE) {
            // restrict to 'immediately reportable' diseases
            $str = "AND (sm.immediate_notify IS TRUE)\n";
        } elseif ($immediatelyNotifiable == self::RESTRICT_SHOW_ALL) {
            // show all diseases whether immediately reportable or not
            // used when list type does not segregate, such as Assigned or Gray lists
            $str = "";
        } else {
            // restrict to 'non-immediately reportable' diseases
            $str = "AND (sm.immediate_notify IS FALSE)\n";
        }

        return $str;
    }

    /**
     * Adds parameters to SQL query to restrict based on whether message was processed automatically or manually
     * @return string
     */
    private function restrictIsAutomated()
    {
        $str = '';

        // check to see if 'show automated' flag is set...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"])) {
            $str = "AND";
            if (count($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"]) > 1) {
                $str .= " (";
            }
            if (in_array(0, $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"])) {
                // show only automated
                $str .= " EXISTS (SELECT sma.id FROM system_messages_audits sma WHERE sma.user_id = '" . EPITRAX_AUTH_ELR_UID . "' AND sma.message_action_id IN (22, 23, 24, 28, 29, 39) AND sma.system_message_id = sm.id) \n";
            }
            if (count($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"]) > 1) {
                $str .= " OR ";
            }
            if (in_array(1, $_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"])) {
                // show only manual
                $str .= " NOT EXISTS (SELECT sma.id FROM system_messages_audits sma WHERE sma.user_id = '" . EPITRAX_AUTH_ELR_UID . "' AND sma.message_action_id IN (22, 23, 24, 28, 29, 39) AND sma.system_message_id = sm.id) \n";
            }
            if (count($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showauto"]) > 1) {
                $str .= ")\n";
            }
        }

        return $str;
    }

    /**
     * Adds parameters to SQL query to restrict based on whether message has been deleted or not
     * @return string
     */
    private function restrictShowDeleted()
    {
        $str = '';

        // check to see if 'show deleted' flag is set...
        if (isset($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showdeleted"]) && is_array($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["filters"]["showdeleted"])) {
            // show deleted messages (any vals for deleted flag)
            $str = '';
        } else {
            // hide deleted messages (only deleted == 0)
            $str .= "AND (sm.deleted = 0)\n";
        }

        return $str;
    }

}

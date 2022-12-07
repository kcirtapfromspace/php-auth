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

use Udoh\Emsa\MessageProcessing\BulkSiblingEventLogTuple;
use Udoh\Emsa\MessageProcessing\BulkSiblingList;
use Udoh\Emsa\MessageProcessing\EventLogItem;
use Udoh\Emsa\Rules\WhitelistRuleTuple;
use EmsaCompareNedssEvent;
use EmsaMessage;

/**
 * EMSA-specific custom sorting functions.
 * 
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class SortUtils
{

    /**
     * Sorts People Search Results based on calculated 'Real Score' instead of NEDSS-returned 'Match Score'
     */
    public static function peopleSearchSortByRealScore($a, $b)
    {
        if ($a['real_score'] == $b['real_score']) {
            return strcasecmp($a['full_name'], $b['full_name']);
        } else {
            return ($a['real_score'] > $b['real_score']) ? -1 : 1;
        }
    }

    /**
     * Sorts an array of coded data from an application by their description
     */
    public static function sortExternalCodesByDescriptionAlpha($a, $b)
    {
        return strnatcasecmp($a['codeDescription'], $b['codeDescription']);
    }

    /**
     * Sorts an array of <b>DateTime</b> objects with the newest DateTime first.
     */
    public static function sortDateTimeNewestFirst($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        return ($a > $b) ? -1 : 1;
    }

    /**
     * Sorts an array of <b>DateTime</b> objects with the oldest DateTime first.
     */
    public static function sortDateTimeOldestFirst($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    /**
     * Sorts an array of <b>EmsaCompareNedssEvent</b> objects by their Event Date with the newest event first.
     *
     * @param EmsaCompareNedssEvent $a
     * @param EmsaCompareNedssEvent $b
     *
     * @return int
     */
    public static function sortNedssEventDateNewestFirst(EmsaCompareNedssEvent $a, EmsaCompareNedssEvent $b)
    {
        if ($a->getEventDate() == $b->getEventDate()) {
            return 0;
        }

        return ($a->getEventDate() > $b->getEventDate()) ? -1 : 1;
    }

    /**
     * Sorts an array of <b>EmsaCompareNedssEvent</b> objects by their Event Date with the oldest event first.
     *
     * @param EmsaCompareNedssEvent $a
     * @param EmsaCompareNedssEvent $b
     *
     * @return int
     */
    public static function sortNedssEventDateOldestFirst(EmsaCompareNedssEvent $a, EmsaCompareNedssEvent $b)
    {
        if ($a->getEventDate() == $b->getEventDate()) {
            return 0;
        }

        return ($a->getEventDate() < $b->getEventDate()) ? -1 : 1;
    }

    /**
     * Sorts an array of <b>WhitelistRuleTuple</b> objects by their Event Date with the newest event first.
     *
     * @param WhitelistRuleTuple $a
     * @param WhitelistRuleTuple $b
     *
     * @return int
     */
    public static function sortWhitelistRuleTupleEventDateNewestFirst(WhitelistRuleTuple $a, WhitelistRuleTuple $b)
    {
        if ($a->getNedssEvent()->getEventDate() == $b->getNedssEvent()->getEventDate()) {
            return 0;
        }

        return ($a->getNedssEvent()->getEventDate() > $b->getNedssEvent()->getEventDate()) ? -1 : 1;
    }

    /**
     * Sorts an array of <b>WhitelistRuleTuple</b> objects by their Event Date with the oldest event first.
     *
     * @param WhitelistRuleTuple $a
     * @param WhitelistRuleTuple $b
     *
     * @return int
     */
    public static function sortWhitelistRuleTupleEventDateOldestFirst(WhitelistRuleTuple $a, WhitelistRuleTuple $b)
    {
        if ($a->getNedssEvent()->getEventDate() == $b->getNedssEvent()->getEventDate()) {
            return 0;
        }

        return ($a->getNedssEvent()->getEventDate() < $b->getNedssEvent()->getEventDate()) ? -1 : 1;
    }

    /**
     * Sorts a <b>BulkSiblingList</b> object, placing those with EmsaMessages containing <i>allowNewCMR = TRUE</i> first.
     *
     * @param BulkSiblingEventLogTuple $a
     * @param BulkSiblingEventLogTuple $b
     *
     * @return int
     */
    public static function sortBulkSiblingListByAllowNewCmr(BulkSiblingEventLogTuple $a, BulkSiblingEventLogTuple $b)
    {
        if (($a->getEmsaMessage()->allowNewCmr === \CaseManagementRulesEngine::CMR_YES) && ($b->getEmsaMessage()->allowNewCmr === \CaseManagementRulesEngine::CMR_NO)) {
            return -1;
        } elseif (($a->getEmsaMessage()->allowNewCmr === \CaseManagementRulesEngine::CMR_NO) && ($b->getEmsaMessage()->allowNewCmr === \CaseManagementRulesEngine::CMR_YES)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Sorts EventLogItem objects by oldest entry first.
     *
     * @param EventLogItem $a
     * @param EventLogItem $b
     *
     * @return int
     */
    public static function sortEventLogOldestFirst(EventLogItem $a, EventLogItem $b)
    {
        if ($a->getCreatedAt() == $b->getCreatedAt()) {
            return 0;
        }

        return ($a->getCreatedAt() < $b->getCreatedAt()) ? -1 : 1;
    }

    /**
     * Sorts EventLogItem objects by newest entry first.
     *
     * @param EventLogItem $a
     * @param EventLogItem $b
     *
     * @return int
     */
    public static function sortEventLogNewestFirst(EventLogItem $a, EventLogItem $b)
    {
        if ($a->getCreatedAt() == $b->getCreatedAt()) {
            return 0;
        }

        return ($a->getCreatedAt() > $b->getCreatedAt()) ? -1 : 1;
    }

    /**
     * Sorts HL7 column names for the onboarding message review generation script
     * Expects column parameters to be in the format of "SEG_I-J" where...
     *   SEG = HL7 segment
     *   I = Collection index of the segment, where more than one of the same segment exists in a single message (optional; if only instance of segment in message, use format "SEG-J"
     *   J = Field identifier
     */
    public static function hl7ColumnSort($a, $b)
    {
        $hl7_segments = array(
            'MSH',
            'SFT',
            'EVN',
            'PID',
            'PD1',
            'NK1',
            'PV1',
            'PV2',
            'ORC',
            'OBR',
            'TQ1',
            'TQ2',
            'CTD',
            'OBX',
            'DG1',
            'FT',
            'CT',
            'SPM',
            'ZLR',
            'NTE'
        );

        $first_split_a = explode('-', $a);
        $first_split_b = explode('-', $b);

        $second_split_a = explode('_', $first_split_a[0]);
        $second_split_b = explode('_', $first_split_b[0]);

        $segment_a = trim($second_split_a[0]);
        $segment_b = trim($second_split_b[0]);

        if (isset($second_split_a[1])) {
            $segment_coll_order_a = intval($second_split_a[1]);
        } else {
            $segment_coll_order_a = 0;
        }
        if (isset($second_split_b[1])) {
            $segment_coll_order_b = intval($second_split_b[1]);
        } else {
            $segment_coll_order_b = 0;
        }

        $segment_id_a = intval($first_split_a[1]);
        $segment_id_b = intval($first_split_b[1]);

        $segment_order_a = array_search($segment_a, $hl7_segments);
        $segment_order_b = array_search($segment_b, $hl7_segments);

        if ($segment_order_a == $segment_order_b) {
            if ($segment_coll_order_a == $segment_coll_order_b) {
                if ($segment_id_a == $segment_id_b) {
                    return 0;
                } else {
                    return ($segment_id_a < $segment_id_b) ? -1 : 1;
                }
            } else {
                return ($segment_coll_order_a < $segment_coll_order_b) ? -1 : 1;
            }
        } else {
            return ($segment_order_a < $segment_order_b) ? -1 : 1;
        }
    }

}

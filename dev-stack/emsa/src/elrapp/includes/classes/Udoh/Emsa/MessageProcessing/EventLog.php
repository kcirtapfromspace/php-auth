<?php

namespace Udoh\Emsa\MessageProcessing;

/**
 * Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
 */

use Countable;
use Iterator;

/**
 * Container for stats and event log items (notices, warnings, etc.) that occur during message processing in EMSA
 *
 * @package Udoh\Emsa\MessageProcessing
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EventLog implements Iterator, Countable
{
    /** @var EventLogItem[] */
    protected $eventLogEntries;

    public function current()
    {
        return current($this->eventLogEntries);
    }

    public function next()
    {
        return next($this->eventLogEntries);
    }

    public function key()
    {
        return key($this->eventLogEntries);
    }

    public function valid()
    {
        return !is_null(key($this->eventLogEntries));
    }

    public function rewind()
    {
        return reset($this->eventLogEntries);
    }

    public function count()
    {
        if (is_array($this->eventLogEntries)) {
            return count($this->eventLogEntries);
        } else {
            return 0;
        }
    }

    /**
     * Get the total number of messages found in the Event Log.
     *
     * @return int
     */
    public function getMessageCount()
    {
        $count = 0;

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogItem) {
                $count += $eventLogItem->getMessageCount();
            }
        }

        return $count;
    }

    /**
     * Get the number of messages found in the Event Log that were successfully processed
     * .
     *
     * @return int
     */
    public function getSuccessMessageCount()
    {
        $count = 0;

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogItem) {
                $count += $eventLogItem->getSuccessMessageCount();
            }
        }

        return $count;
    }

    /**
     * Get the number of messages found in the Event Log that were not successfully processed
     * .
     *
     * @return int
     */
    public function getFailureMessageCount()
    {
        $count = 0;

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogItem) {
                $count += $eventLogItem->getFailureMessageCount();
            }
        }

        return $count;
    }

    /**
     * Add an entry to this Event Log.
     *
     * @param EventLogItem $entry
     *
     * @return EventLog
     */
    public function add(EventLogItem $entry)
    {
        $this->eventLogEntries[] = $entry;
        return $this;
    }

    public function display()
    {
        if ($this->count() > 0) {
            $totalCount = $this->getMessageCount();
            $successCount = $this->getSuccessMessageCount();
            $failedCount = $this->getFailureMessageCount();

            $totalMessages = ($totalCount === 1) ? 'message' : 'messages';
            $successMessages = ($successCount === 1) ? 'message' : 'messages';

            if (($totalCount > 0) && ($successCount == $totalCount)) {
                $uiIcon = "ui-icon-elrsuccess";
            } elseif (($totalCount > 0) && ($successCount > 0)) {
                $uiIcon = "ui-icon-elrerror";
            } elseif (($totalCount > 0) && ($successCount == 0)) {
                $uiIcon = "ui-icon-elrstop";
            } else {
                $uiIcon = "ui-icon-info";
            }

            echo '<div class="import_widget ui-widget import_error ui-state-highlight ui-corner-all" style="padding: 5px;">';
            echo '<table>';

            echo '<tr>';
            echo '<td class="ui-icon ' . $uiIcon . '" style="margin: 10px 4px;"></td>';
            echo '<td style="padding: 4px;"><strong>Finished processing ' . (int) $totalCount . ' ' . $totalMessages . '!  ' . (int) $successCount . ' ' . $successMessages . ' processed successfully (' . (int) $failedCount . ' had errors).</strong>  <button name="mplog-toggle" id="mplog-toggle">Show More Details</button>';

            foreach ($this->eventLogEntries as $eventLogItem) {
                $eventLogItem->display();
            }

            echo '</table>';

            echo '</div>';
        }
    }

    /**
     * Sort the list of Event Log Entries by date.
     *
     * @param bool $asc [Optional; Default TRUE] If TRUE, sort oldest-first.
     */
    protected function sort($asc = true)
    {
        if ($asc) {
            usort($this->eventLogEntries, array('Udoh\Emsa\Utils\SortUtils', 'sortEventLogOldestFirst'));
        } else {
            usort($this->eventLogEntries, array('Udoh\Emsa\Utils\SortUtils', 'sortEventLogNewestFirst'));
        }
    }

}
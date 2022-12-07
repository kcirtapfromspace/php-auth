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

use Udoh\Emsa\Client\AppClientInterface;
use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Class EventLogMessage
 *
 * @package Udoh\Emsa\MessageProcessing
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EventLogMessage extends EventLogItem
{

    /** @var int */
    protected $systemMessageId;
    /** @var string */
    protected $appRecordLink;
    /** @var bool */
    protected $processedSuccessfully;
    /** @var EventLogItem[] */
    protected $eventLogEntries;

    /**
     * EventLogMessage constructor.
     *
     * @param int $systemMessageId EMSA Message ID being processed.
     */
    public function __construct($systemMessageId)
    {
        parent::__construct();
        $this->processedSuccessfully = false;
        $this->systemMessageId = (int) $systemMessageId;
    }

    /**
     * @param bool $processedSuccessfully
     *
     * @return EventLogMessage
     */
    public function setProcessedSuccessfully($processedSuccessfully)
    {
        $this->processedSuccessfully = $processedSuccessfully;
        return $this;
    }

    /**
     * @return bool
     */
    public function isProcessedSuccessfully()
    {
        return $this->processedSuccessfully;
    }

    /**
     * Add an entry to this Event Log.
     *
     * @param EventLogItem $entry
     *
     * @return EventLogMessage
     */
    public function add(EventLogItem $entry)
    {
        $this->eventLogEntries[] = $entry;
        return $this;
    }

    /**
     * Build a link to a record in the target application.
     *
     * Causes a button to be displayed linking to the target record when this EventLogMessage is displayed in the UI.
     *
     * @param AppClientInterface $appClient
     * @param int                $appRecordType   One of Udoh\Emsa\Constants\AppRecordType
     * @param string             $appRecordNumber Record Number of the target record
     * @param int                $appEventId      The app-specific internal ID of the record to link to
     * @param bool               $readOnly        [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to a modifiable view of the record.  Otherwise, link points to read-only view
     */
    public function buildAppRecordLink(AppClientInterface $appClient, $appRecordType, $appRecordNumber, $appEventId, $readOnly = true)
    {
        $url = $appClient->getAppLinkToRecord($appRecordType, $appRecordNumber, $readOnly, $appEventId);
        $appClientName = DisplayUtils::xSafe($appClient->getAppName(), 'UTF-8', false);

        $this->appRecordLink = "<a href='$url' target='_blank' class='emsa_btn_viewnedss' id='emsa_btn_viewnedss_" . (int) $appEventId . "'>View in $appClientName</a>";
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

    /**
     * Print Event Log Item.
     *
     * @return void
     */
    public function display()
    {
        echo '<tr class="mplog-hideable">';
        echo '<td class="ui-icon ui-icon-comment" style="margin: 4px;"></td>';
        echo '<td style="padding: 4px;"><strong><a href="' . MAIN_URL . '/?selected_page=6&submenu=6&focus=' . (int) $this->systemMessageId . '" target="_blank">Message ID# ' . (int) $this->systemMessageId . '</a> found.  Starting message processing...</strong>';

        if (count($this->eventLogEntries) > 0) {
            echo '<br><table>';
            foreach ($this->eventLogEntries as $eventLogItem) {
                $eventLogItem->display();
            }
            echo '</table>';
        }

        if (!empty($this->appRecordLink)) {
            echo "<br>$this->appRecordLink";
        }
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Get count of messages contained within this EventLogItem.
     *
     * @return int
     */
    public function getMessageCount()
    {
        $count = 1;

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogEntry) {
                $count += $eventLogEntry->getMessageCount();
            }
        }

        return $count;
    }

    /**
     * Get count of successfully-processed messages contained within this EventLogItem.
     *
     * @return int
     */
    public function getSuccessMessageCount()
    {
        $count = 0;

        if ($this->isProcessedSuccessfully()) {
            $count++;
        }

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogEntry) {
                $count += $eventLogEntry->getSuccessMessageCount();
            }
        }

        return $count;
    }

    /**
     * Get count of messages that failed to process contained within this EventLogItem.
     *
     * @return int
     */
    public function getFailureMessageCount()
    {
        $count = 0;

        if (!$this->isProcessedSuccessfully()) {
            $count++;
        }

        if (count($this->eventLogEntries) > 0) {
            foreach ($this->eventLogEntries as $eventLogEntry) {
                $count += $eventLogEntry->getFailureMessageCount();
            }
        }

        return $count;
    }


}
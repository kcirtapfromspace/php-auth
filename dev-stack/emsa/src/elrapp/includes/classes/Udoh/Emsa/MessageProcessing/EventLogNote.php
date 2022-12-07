<?php

namespace Udoh\Emsa\MessageProcessing;

use Udoh\Emsa\Utils\DisplayUtils;

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

/**
 * Class EventLogNote
 *
 * @package Udoh\Emsa\MessageProcessing
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EventLogNote extends EventLogItem
{

    /**
     * @var string Message to display for this Event Log item.
     */
    protected $message;
    /**
     * @var string Icon to accompany this message.
     */
    protected $icon;
    /**
     * @var string Error details associated with this note.
     */
    protected $errorDetails;
    /**
     * @var EventLogExceptionDetailSet;
     */
    protected $exceptionDetails;

    /**
     * EventLogNote constructor.
     *
     * @param string                     $message      Message to display for this note.
     * @param string                     $icon         [Optional; Default 'ui-icon-info']
     * @param string                     $errorDetails [Optional]
     * @param EventLogExceptionDetailSet $exceptionDetails [Optional]
     */
    public function __construct($message, $icon = 'ui-icon-info', $errorDetails = null, EventLogExceptionDetailSet $exceptionDetails = null)
    {
        parent::__construct();
        $this->exceptionDetails = new EventLogExceptionDetailSet();
        $this->setIcon($icon);
        $this->setMessage($message);
        $this->setErrorDetails($errorDetails);
        $this->setExceptionDetails($exceptionDetails);
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $icon [Optional] Icon to accompany this message. Default 'ui-icon-info'.
     *
     * @return EventLogNote
     */
    protected function setIcon($icon = 'ui-icon-info')
    {
        if (empty($icon)) {
            $icon = 'ui-icon-info';
        }

        $this->icon = $icon;
        return $this;
    }

    /**
     * @param string $message
     *
     * @return EventLogNote
     */
    protected function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param string $errorDetails
     *
     * @return EventLogNote
     */
    public function setErrorDetails($errorDetails = null)
    {
        if (!empty($errorDetails)) {
            $this->errorDetails = $errorDetails;
        }

        return $this;
    }

    /**
     * @param EventLogExceptionDetailSet $exceptionDetails
     *
     * @return EventLogNote
     */
    public function setExceptionDetails(EventLogExceptionDetailSet $exceptionDetails = null)
    {
        if (!empty($exceptionDetails)) {
            $this->exceptionDetails = $exceptionDetails;
        }

        return $this;
    }

    /**
     * Print this Event Log item.
     *
     * @return void
     */
    public function display()
    {
        echo '<tr class="mplog-hideable">';
        echo '<td class="ui-icon ' . DisplayUtils::xSafe($this->getIcon(), 'UTF-8', false) . '" style="margin: 4px;"></td>';
        echo '<td style="padding: 4px;">';

        if (count($this->exceptionDetails) > 0) {
            echo 'The following errors were found while revalidating this message:<br>';
            echo '<table class="audit_log">
            <thead>
                <tr>
                    <th>Error Type</th>
                    <th>Error Description</th>
                    <th>Error Details</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($this->exceptionDetails as $exceptionDetail) {
                echo '<tr>';
                echo '<td>' . DisplayUtils::xSafe($exceptionDetail->getType(), 'UTF-8', false) . '</td>';
                echo '<td>' . DisplayUtils::xSafe($exceptionDetail->getDescription(), 'UTF-8', false) . '</td>';
                echo '<td>' . DisplayUtils::xSafe($exceptionDetail->getDetails(), 'UTF-8', false) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<br>';
        }

        echo DisplayUtils::xSafe($this->getMessage(), 'UTF-8', false);

        if (!empty($this->errorDetails)) {
            echo '<br><br>' . DisplayUtils::xSafe($this->errorDetails, 'UTF-8', false);
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
        return 0;
    }

    /**
     * Get count of successfully-processed messages contained within this EventLogItem.
     *
     * @return int
     */
    public function getSuccessMessageCount()
    {
        return 0;
    }

    /**
     * Get count of messages that failed to process contained within this EventLogItem.
     *
     * @return int
     */
    public function getFailureMessageCount()
    {
        return 0;
    }


}
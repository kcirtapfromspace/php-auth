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

use DateTime;
use Udoh\Emsa\Utils\DateTimeUtils;

/**
 * Event Log item (warning, note, etc.) that occurs during processing of an EMSA message.
 *
 * @package Udoh\Emsa\MessageProcessing
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
abstract class EventLogItem
{
    /** @var DateTime */
    protected $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    /**
     * Get the date/time this Event Log item was created.
     *
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     *
     * @return DateTime|null|string Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getCreatedAt($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->createdAt, $formatted, $formatStr);
    }

    /**
     * Print this Event Log item.
     *
     * @return void
     */
    abstract public function display();

    /**
     * Get count of messages contained within this EventLogItem.
     *
     * @return int
     */
    abstract public function getMessageCount();

    /**
     * Get count of successfully-processed messages contained within this EventLogItem.
     *
     * @return int
     */
    abstract public function getSuccessMessageCount();

    /**
     * Get count of messages that failed to process contained within this EventLogItem.
     *
     * @return int
     */
    abstract public function getFailureMessageCount();
}
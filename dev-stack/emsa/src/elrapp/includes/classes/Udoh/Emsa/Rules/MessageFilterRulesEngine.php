<?php

namespace Udoh\Emsa\Rules;

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

use Throwable;

/**
 * Rules engine for evaluating EMSA Message Filter Rules
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class MessageFilterRulesEngine
{

    /** Missing required fields */
    const MISSING_REQUIRED_FIELDS = -1;
    /** Required manual review or an error occurred */
    const RESULT_EXCEPTION = 0;
    /** Allow the message to be processed */
    const RESULT_ALLOW = 1;
    /** Message should be filtered (not processed) */
    const RESULT_FILTER = 2;

    /**
     * Evaluate Message Filter Rules for the current message.
     * 
     * @param MessageFilterRule $filterRule Filter Rule for the message's condition.
     * @param \DateTime         $refDate    Reference Date for the specified message.
     * 
     * @return int Returns <b>RESULT_ALLOW</b> if message should be processed, <b>RESULT_FILTER</b> if the message should be filtered, or <b>RESULT_EXCEPTION</b> on error.<br>If Reference Date is missing, returns <b>MISSING_REQUIRED_FIELDS</b>.
     */
    public static function evaluate(MessageFilterRule $filterRule, \DateTime $refDate = null)
    {
        $result = self::RESULT_EXCEPTION;
        
        if (is_null($refDate)) {
            return self::MISSING_REQUIRED_FIELDS;
        }

        try {
            $currentDate = new \DateTime();

            if ($filterRule->getRuleType() === MessageFilterRule::RULETYPE_ALWAYS) {
                $result = self::RESULT_ALLOW;
            } elseif ($filterRule->getRuleType() === MessageFilterRule::RULETYPE_TIME_REFDATE) {
                $startStr = '+' . $filterRule->getRuleValue();
                $startDate = clone $refDate;
                $startDate->add(\DateInterval::createFromDateString($startStr));

                if ($startDate > $currentDate) {
                    $result = self::RESULT_ALLOW;
                } else {
                    $result = self::RESULT_FILTER;
                }
            } else {
                $result = self::RESULT_EXCEPTION;
            }
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        }

        return $result;
    }

}

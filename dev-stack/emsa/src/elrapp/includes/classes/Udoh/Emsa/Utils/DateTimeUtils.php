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

use DateInterval;
use DateTime;
use EmsaUtils;
use Throwable;
use Exception;

/**
 * EMSA utilities for working with DateTime values.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class DateTimeUtils
{
    /**
     * Create a <b>DateTime</b> object from multiple date/time input formats.
     *
     * Valid <i>input</i> values...<br>
     * - <b>NULL</b> (returns a <b>DateTime</b> object with the current time),
     * - UNIX timestamp,
     * - Valid string format supported by <b>DateTime::__construct</b> parser
     *
     * @param mixed $input The desired date/time
     *
     * @return DateTime
     *
     * @throws Exception
     */
    public static function createMixed($input = null): DateTime
    {
        $currentDateTime = new DateTime();

        if (empty($input)) {
            return $currentDateTime;
        }

        if ($input instanceof DateTime) {
            return $input;
        }

        if ($input === strtotime(date(DATE_ISO8601, intval($input)))) {
            return $currentDateTime->setTimestamp(intval($input));
        }

        try {
            $currentDateTime = new DateTime(trim($input));
            return $currentDateTime;
        } catch (Throwable $e) {
            // attempt to create from some odd HL7/eICR time formats with microseconds
            $currentDateTime = DateTime::createFromFormat("YmdHis\.uO", trim($input));

            if ($currentDateTime !== false) {
                return $currentDateTime;
            }
        }

        throw new Exception('Failed to parse time string (' . (string) $input . ')');
    }

    /**
     * Formattable getter for DateTime objects.
     *
     * @param DateTime $dateObj   Date object to work with.
     * @param boolean  $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string   $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public static function getDateFormatted(?DateTime $dateObj = null, ?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        $formatted = $formatted ?? false;
        $formatStr = $formatStr ?? DateTime::RFC3339;

        if (!empty($dateObj) && ($dateObj instanceof DateTime)) {
            if ($formatted) {
                return $dateObj->format($formatStr);
            } else {
                return $dateObj;
            }
        }

        return null;
    }
    
    /**
     * Checks whether a given input is a valid PHP date/time string
     * 
     * @param mixed $timeStr Date/time string to validate
     * 
     * @return bool
     */
    public static function validateDateTimeString($timeStr = null): bool
    {
        if (EmsaUtils::emptyTrim($timeStr)) {
            return false;
        }

        try {
            $foo = self::createMixed();
            $bar = clone $foo;
            $baz = DateInterval::createFromDateString($timeStr);

            $foo->add($baz);

            return ($foo != $bar);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return false;
        }
    }

    /**
     * Formats an elapsed time (in seconds) to a string value.
     *
     * @param int $time
     * @param int $precision Precision to use for resulting string (0 = days, 1 = hours, 2 = minutes, 3 = seconds)
     *
     * @return string Elapsed time (in days, hours, minutes, and seconds) for given <i>time</i>.  Returns "N/A" if <i>time</i> is empty.
     */
    public static function elapsedTimeToString(?int $time = null, ?int $precision = 0): string
    {
        $elapsed = '';

        if (empty($time)) {
            return 'N/A';
        }

        $t = filter_var($time, FILTER_SANITIZE_NUMBER_INT);

        switch (filter_var($precision, FILTER_SANITIZE_NUMBER_INT)) {
            case 1:
            case 2:
            case 3:
                $p = $precision;
                break;
            default:
                $p = 0;
                break;
        }

        if ($t !== false) {
            $days = intval(floor($t / (3600 * 24)));
            $hours = intval(floor(($t - ($days * 3600 * 24)) / 3600));
            $mins = intval(floor(($t - ($days * 3600 * 24) - ($hours * 3600)) / 60));
            $secs = intval(floor(($t - ($days * 3600 * 24) - ($hours * 3600) - ($mins * 60))));

            $elapsed_array = array();

            if ($days > 0) {
                $elapsed_array[] = ($days == 1) ? $days . ' day' : $days . ' days';
            }
            if ($hours > 0 && $p > 0) {
                $elapsed_array[] = ($hours == 1) ? $hours . ' hour' : $hours . ' hours';
            }
            if ($mins > 0 && $p > 1) {
                $elapsed_array[] = ($mins == 1) ? $mins . ' minute' : $mins . ' minutes';
            }
            if ($secs > 0 && $p > 2) {
                $elapsed_array[] = ($secs == 1) ? $secs . ' second' : $secs . ' seconds';
            }

            if ($t > 0) {
                $elapsed .= implode(', ', $elapsed_array);
            }
        }

        return $elapsed;
    }

    public static function ageFromDob(DateTime $dateOfBirth)
    {
        $now = self::createMixed();

        try {
            $diff = $now->diff($dateOfBirth)->y;
            if ($diff < 1) {
                return "<1";
            } else {
                return $diff;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return 'N/A';
        }
    }

    public static function setDashboardDates($fromTimeStr = null, $toTimeStr = null)
    {
        if (!EmsaUtils::emptyTrim($fromTimeStr) && !EmsaUtils::emptyTrim($toTimeStr)) {
            try {
                $fromDateTime = self::createMixed($fromTimeStr);
                $toDateTime = self::createMixed($toTimeStr);
                $nowDateTime = self::createMixed();
                $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] = $fromDateTime->format("m/d/Y");
                $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] = $toDateTime->format("m/d/Y");
                $_SESSION[EXPORT_SERVERNAME]['dashboard_date_last_set'] = $nowDateTime;
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                self::setDefaultDashboardDates();
            }
        }
    }

    public static function setDefaultDashboardDates()
    {
        $nowDateTime = self::createMixed();
        $fromDateTime = clone $nowDateTime;

        $fromDateTime->sub(DateInterval::createFromDateString('6 days'));

        $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] = $fromDateTime->format("m/d/Y");
        $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] = $nowDateTime->format("m/d/Y");
        $_SESSION[EXPORT_SERVERNAME]['dashboard_date_last_set'] = $nowDateTime;
    }
    
    public static function initializeDashboardDates()
    {
        $expiredDateTime = self::createMixed();
        $expiredDateTime->sub(DateInterval::createFromDateString('12 hours'));
        
        if (!isset($_SESSION[EXPORT_SERVERNAME]['dashboard_date_last_set']) || !($_SESSION[EXPORT_SERVERNAME]['dashboard_date_last_set'] instanceof DateTime) || ($expiredDateTime > $_SESSION[EXPORT_SERVERNAME]['dashboard_date_last_set'])) {
            self::setDefaultDashboardDates();
        }
        
        if (!isset($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from']) || !isset($_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'])) {
            self::setDefaultDashboardDates();
        }
    }
}

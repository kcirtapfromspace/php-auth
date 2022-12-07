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

use Throwable;

/**
 * Utilities for handling EMSA Exceptions
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ExceptionUtils
{

    /**
     * Logs the details of a caught Exception to the error log.
     *
     * @param Throwable $e Exception to log.
     */
    public static function logException(Throwable $e): void
    {
        error_log('[EMSA] [Caught ' . get_class($e) . ']' . $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage());

        $stackTrace = $e->getTrace();
        if (is_array($stackTrace)) {
            error_log('Stack Trace:');
            foreach ($stackTrace as $traceNumber => $traceData) {
                $errorSource = '';
                $argData = '(' . self::traceArgsToString($traceData['args']) . ')';

                if (isset($traceData['class'])) {
                    $errorSource .= str_replace('\\', '/', $traceData['class']) . $traceData['type'] . $traceData['function'] . $argData;
                } else {
                    $errorSource .= $traceData['function'] . $argData;
                }
                if (!isset($traceData['file']) && !isset($traceData['line'])) {
                    error_log('...#' . intval($traceNumber) . ' [internal function]): ' . $errorSource);
                } else {
                    error_log('...#' . intval($traceNumber) . ' ' . $traceData['file'] . '(' . intval($traceData['line']) . '): ' . $errorSource);
                }
            }
        }

        $previousException = $e->getPrevious();
        if (!is_null($previousException)) {
            self::logException($previousException);
        }
    }
    
    /**
     * Converts an argument list from a PHP stack trace to a human-readable string.
     * 
     * @param array $traceArgs Argument list to convert to string.
     * 
     * @return string
     */
    private static function traceArgsToString(array $traceArgs): string
    {
        $tempArgData = array();
        $tempArgStr = '';

        if (count($traceArgs) > 0) {
            foreach ($traceArgs as $thisArg) {
                if (is_object($thisArg)) {
                    $tempArgData[] = 'Object(' . get_class($thisArg) . ')';
                } elseif (is_array($thisArg)) {
                    $tempArgData[] = 'array(' . self::traceArgsToString($thisArg) . ')';
                } else {
                    if (is_numeric($thisArg)) {
                        $tempArgData[] = $thisArg;
                    } elseif (is_bool($thisArg)) {
                        $tempArgData[] = $thisArg ? 'true' : 'false';
                    } else {
                        $tempArgData[] = "'" . substr($thisArg, 0, 25) . ((strlen($thisArg) > 25) ? '...' : '') . "'";
                    }
                }
            }
            
            $tempArgStr .= implode(', ', $tempArgData);
        }
        
        return $tempArgStr;
    }

}

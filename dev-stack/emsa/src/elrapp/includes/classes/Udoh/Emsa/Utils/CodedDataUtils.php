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

/**
 * Utilities for interacting with Application-specific Coded Data entries stored in PHP session.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class CodedDataUtils
{

    /**
     * Get the ID of a specific coded entry for the current application by code description.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param string $codeDescription Code description to look up.
     * @return int Code ID for the matched coded entry.  Returns 0 if no match found.
     * @static
     */
    public static function getCodeIdFromDescription(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codeDescription)
    {
        $codeId = 0;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName])) {
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName] as $codedEntryId => $codedEntryData) {
                if (isset($codedEntryData['codeDescription']) && ((string) $codedEntryData['codeDescription'] === (string) $codeDescription)) {
                    $codeId = (int) $codedEntryId;
                    break;
                }
            }
        }

        return $codeId;
    }

    /**
     * Get the ID of a specific coded entry for the current application by coded value.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param string $codedValue Code value to look up.
     * @return int Code ID for the matched coded entry.  Returns 0 if no match found.
     * @static
     */
    public static function getCodeIdFromCodedValue(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codedValue)
    {
        $codeId = 0;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName])) {
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName] as $codedEntryId => $codedEntryData) {
                if (isset($codedEntryData['codedValue']) && ((string) $codedEntryData['codedValue'] === (string) $codedValue)) {
                    $codeId = (int) $codedEntryId;
                    break;
                }
            }
        }

        return $codeId;
    }

    /**
     * Get the description from a specific coded entry for the current application by ID value.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param int $codeId Code ID to look up.
     * @return string Description for the matched coded entry.  Returns <b>NULL</b> if no match found.
     * @static
     */
    public static function getCodeDescriptionFromId(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codeId)
    {
        $codeDescription = null;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName][(int) $codeId]['codeDescription'])) {
            $codeDescription = (string) $_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName][(int) $codeId]['codeDescription'];
        }

        return $codeDescription;
    }

    /**
     * Get the description from a specific coded entry for the current application by coded value.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param string $codedValue Code value to look up.
     * @return string Description for the matched coded entry.  Returns <b>NULL</b> if no match found.
     * @static
     */
    public static function getCodeDescriptionFromCodedValue(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codedValue)
    {
        $codeDescription = null;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName])) {
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName] as $codedEntryData) {
                if (isset($codedEntryData['codedValue']) && ((string) $codedEntryData['codedValue'] === (string) $codedValue)) {
                    $codeDescription = (string) $codedEntryData['codeDescription'];
                    break;
                }
            }
        }

        return $codeDescription;
    }

    /**
     * Get the coded value of a specific coded entry for the current application by code description.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param string $codeDescription Code description to look up.
     * @return string Coded value for the matched coded entry.  Returns <b>NULL</b> if no match found.
     * @static
     */
    public static function getCodedValueFromDescription(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codeDescription)
    {
        $codedValue = null;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName])) {
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName] as $codedEntryData) {
                if (isset($codedEntryData['codeDescription']) && ((string) $codedEntryData['codeDescription'] === (string) $codeDescription)) {
                    $codedValue = (string) $codedEntryData['codedValue'];
                    break;
                }
            }
        }

        return $codedValue;
    }

    /**
     * Get the coded value from a specific coded entry for the current application by ID value.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Client for the current Application.
     * @param string $codesetName Code category to search within.
     * @param int $codeId Code ID to look up.
     * @return string Coded value for the matched coded entry.  Returns <b>NULL</b> if no match found.
     * @static
     */
    public static function getCodedValueFromId(\Udoh\Emsa\Client\AppClientInterface $appClient, $codesetName, $codeId)
    {
        $codedValue = null;

        if (isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName][(int) $codeId]['codedValue'])) {
            $codedValue = (string) $_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][(string) $codesetName][(int) $codeId]['codedValue'];
        }

        return $codedValue;
    }

}

<?php

namespace Udoh\Emsa\Management;

/**
 * Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Container for Preprocessor Exception Value
 *
 * @package Udoh\Emsa\Management
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
final class PreprocessorExceptionValue
{
    private $exceptionValue;
    private $decodedJSONObject;

    /**
     * PreprocessorExceptionValue constructor.
     *
     * @param $exceptionValue
     */
    public function __construct(?string $exceptionValue = null)
    {
        $this->exceptionValue = $exceptionValue;
        $this->parseEmbeddedFields();
    }

    private function parseEmbeddedFields()
    {
        $tmpDecodedObject = json_decode($this->exceptionValue);

        if ((json_last_error() === JSON_ERROR_NONE) && is_object($tmpDecodedObject)) {
            $this->decodedJSONObject = $tmpDecodedObject;
        }
    }

    /**
     * Get the content of the Preprocessor Exception Value.
     *
     * @param bool|null   $escaped [Optional]<br>Default TRUE.  If TRUE, escape return values for display.  If FALSE, returns raw string value.
     * @param string|null $encoding [Optional]<br>Default "UTF-8".
     * @param bool|null   $doubleEncode [Optional]<br>Default TRUE.
     *
     * @return string|null
     */
    public function getExceptionValue(?bool $escaped = true, ?string $encoding = 'UTF-8', ?bool $doubleEncode = true): ?string
    {
        $value = null;

        if ($escaped !== true) {
            $value = $this->exceptionValue;
        } else {
            if (!empty($this->decodedJSONObject)) {
                // exception value contained JSON-encoded values
                if (property_exists($this->decodedJSONObject, "apiDescription")) {
                    // ICD code lookup
                    $value = "<strong>" . DisplayUtils::xSafe($this->getDecodedProperty("codeSystem") ?? '[Unknown Code System]', $encoding, $doubleEncode) . " code:</strong> " . DisplayUtils::xSafe($this->getDecodedProperty("code"), $encoding, $doubleEncode) . "<br>";
                    $value .= "<strong>Standard Code Description:</strong> " . DisplayUtils::xSafe($this->getDecodedProperty("apiDescription") ?? 'N/A', $encoding, $doubleEncode) . "<br>";
                    $value .= "<strong>Sender's Code Description:</strong> " . DisplayUtils::xSafe($this->getDecodedProperty("displayName") ?? 'N/A', $encoding, $doubleEncode);
                } else {
                    // fallback in case an unrecognized JSON object
                    $value = DisplayUtils::xSafe($this->exceptionValue, $encoding, $doubleEncode);
                }
            } else {
                $value = DisplayUtils::xSafe($this->exceptionValue, $encoding, $doubleEncode);
            }
        }

        return $value;
    }

    /**
     * Attempt to get value for a given property in the decoded JSON object generated from a Preprocessor Exception Value.
     *
     * @param string $propertyName
     *
     * @return mixed Returns null if named property doesn't exist, otherwise returns raw value of named property.
     */
    public function getDecodedProperty(string $propertyName)
    {
        if (!empty($this->decodedJSONObject) && is_object($this->decodedJSONObject) && property_exists($this->decodedJSONObject, $propertyName)) {
            return $this->decodedJSONObject->$propertyName;
        } else {
            return null;
        }
    }




}
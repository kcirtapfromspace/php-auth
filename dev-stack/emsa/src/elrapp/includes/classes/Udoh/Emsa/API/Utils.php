<?php

namespace Udoh\Emsa\API;

/**
 * Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Utilities used by the EMSA Vocabulary REST API
 *
 * @package Udoh\Emsa\API
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Utils
{
    /**
     * @param string   $fieldName Name of field to retrieve
     * @param int|null $method    (Optional, default <b>INPUT_POST</b>)<br>One of <b>INPUT_GET</b>, <b>INPUT_POST</b>, <b>INPUT_COOKIE</b>, <b>INPUT_SERVER</b>, or <b>INPUT_ENV</b>.
     *
     * @return int|null
     */
    public static function getInteger(string $fieldName, ?int $method = INPUT_POST): ?int
    {
        if (empty($method)) {
            $method = INPUT_POST;
        }

        $tempValue = filter_input($method, $fieldName, FILTER_SANITIZE_NUMBER_INT);

        if (!empty($tempValue)) {
            return (int) $tempValue;
        } else {
            return null;
        }
    }

    /**
     * @param string   $fieldName Name of field to retrieve
     * @param int|null $method    (Optional, default <b>INPUT_POST</b>)<br>One of <b>INPUT_GET</b>, <b>INPUT_POST</b>, <b>INPUT_COOKIE</b>, <b>INPUT_SERVER</b>, or <b>INPUT_ENV</b>.
     *
     * @return bool|null
     */
    public static function getBoolean(string $fieldName, ?int $method = INPUT_POST): ?bool
    {
        if (empty($method)) {
            $method = INPUT_POST;
        }

        $tempValue = filter_input($method, $fieldName, FILTER_SANITIZE_STRING);

        if (empty($tempValue)) {
            return null;
        } elseif ($tempValue === 't') {
            return true;
        } elseif ($tempValue === 'f') {
            return false;
        } else {
            return null;
        }
    }
}
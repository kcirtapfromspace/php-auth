<?php
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

/**
 * Rule parameter class
 * 
 * Allows for parameters used in rule classes (e.g. \Udoh\Emsa\Email\Notification) to have forced typing
 *
 * @copyright 2016 State of Utah - All rights reserved
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class RuleParameter
{

    protected $dataType;
    protected $value;
    protected $typeArr = array('Boolean', 'Integer', 'Float', 'String', 'DateTime');

    /*
     * 'Parameter value from' types
     */

    const PARAMVALUE_USER = 1;  // parameter value comes from user-entry
    const PARAMVALUE_LOOKUP = 2; // parameter value comes from lookup table
    const PARAMVALUE_VOCAB = 3;  // parameter value comes from master vocabulary

    public function __construct($type)
    {
        if (in_array($type, $this->typeArr)) {
            $this->dataType = $type;
            if ($this->dataType == 'Boolean') {
                $this->value = false;
            }
        }
    }

    public function __toString()
    {
        if (is_null($this->value)) {
            return 'NULL';
        } else {
            return trim($this->value);
        }
    }

    public function getValue()
    {
        if (!is_null($this->dataType) && !is_null($this->value)) {
            switch ($this->dataType) {
                case 'Boolean':
                    return (bool) $this->value;
                case 'Integer':
                    return intval($this->value);
                case 'Float':
                    return floatval($this->value);
                case 'DateTime':
                    return $this->value;
                default:
                    return trim($this->value);
            }
        } else {
            return null;
        }
    }

    public function setValue($input)
    {
        if (isset($this->dataType) && !empty($this->dataType)) {
            switch ($this->dataType) {
                case 'Boolean':
                    $this->value = ((filter_var($input, FILTER_VALIDATE_BOOLEAN)) ? true : false);
                    return true;
                case 'Integer':
                    $this->value = intval($input);
                    return true;
                case 'Float':
                    $this->value = floatval($input);
                    return true;
                case 'DateTime':
                    if (trim(strtotime(date('Y-m-d H:i:s', intval($input)))) === trim($input)) {
                        $this->value = intval($input);
                    } else {
                        $this->value = ((strtotime(trim($input)) !== false) ? strtotime(trim($input)) : null);
                    }
                    return true;
                default:
                    $this->value = filter_var(trim($input), FILTER_SANITIZE_STRING);
                    return true;
            }
        } else {
            return false;
        }
    }

    public function getDataType()
    {
        return $this->dataType;
    }

}

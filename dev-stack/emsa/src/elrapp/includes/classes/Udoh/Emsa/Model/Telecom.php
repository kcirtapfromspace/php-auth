<?php

namespace Udoh\Emsa\Model;

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
 * EMSA Telecom class.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Telecom
{
    const TYPE_PHONE = 1;
    const TYPE_FAX = 2;
    const TYPE_EMAIL = 3;
    
    const USE_HOME = 1;
    const USE_WORK = 2;
    const USE_MOBILE = 3;
    const USE_UNKNOWN = 4;
    
    /** @var int */
    protected $type;
    /** @var int */
    protected $use;
    /** @var string */
    protected $countryCode;
    /** @var string */
    protected $areaCode;
    /** @var string */
    protected $localNumber;
    /** @var string */
    protected $extension;
    /** @var string */
    protected $emailAddress;
    
    /**
     * Create a new Telecom object.
     * 
     * @param int $type One of <i><b>\Udoh\Emsa\Model\Telecom::TYPE_*</b></i> constants (Default <i><b>\Udoh\Emsa\Model\Telecom::TYPE_PHONE</b></i>)
     * @param int $use One of <i><b>\Udoh\Emsa\Model\Telecom::USE_*</b></i> constants (Default <i><b>\Udoh\Emsa\Model\Telecom::USE_UNKNOWN</b></i>)
     * @param string $countryCode
     * @param string $areaCode
     * @param string $localNumber
     * @param string $extension
     * @param string $emailAddress
     */
    public function __construct($type = null, $use = null, $countryCode = null, $areaCode = null, $localNumber = null, $extension = null, $emailAddress = null)
    {
        $this->setType($type);
        $this->setUse($use);
        $this->setCountryCode($countryCode);
        $this->setAreaCode($areaCode);
        $this->setLocalNumber($localNumber);
        $this->setExtension($extension);
        $this->setEmailAddress($emailAddress);
    }
    
    public function getType()
    {
        return $this->type;
    }

    public function getUse()
    {
        return $this->use;
    }

    public function getCountryCode()
    {
        return $this->countryCode;
    }

    public function getAreaCode()
    {
        return $this->areaCode;
    }

    public function getLocalNumber()
    {
        return $this->localNumber;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    /**
     * Set the type of this Telecom object.
     * @param int $type One of <i><b>\Udoh\Emsa\Model\Telecom::TYPE_*</b></i> constants (Default <i><b>\Udoh\Emsa\Model\Telecom::TYPE_PHONE</b></i>)
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setType($type = null)
    {
        $safeType = (int) filter_var($type, FILTER_SANITIZE_NUMBER_INT);
        
        if ($safeType > 0) {
            $this->type = $safeType;
        } else {
            $this->type = self::TYPE_PHONE;
        }
        
        return $this;
    }

    /**
     * Set the usage of this Telecom object.
     * @param int $use One of <i><b>\Udoh\Emsa\Model\Telecom::USE_*</b></i> constants (Default <i><b>\Udoh\Emsa\Model\Telecom::USE_UNKNOWN</b></i>)
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setUse($use = null)
    {
        $safeUse = (int) filter_var($use, FILTER_SANITIZE_NUMBER_INT);
        
        if ($safeUse > 0) {
            $this->use = $safeUse;
        } else {
            $this->use = self::USE_UNKNOWN;
        }
        
        return $this;
    }

    /**
     * Set the Country Code.
     * @param string $countryCode
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setCountryCode($countryCode = null)
    {
        $this->countryCode = (string) filter_var($countryCode, FILTER_SANITIZE_STRING);
        return $this;
    }

    /**
     * Set the Area Code.
     * @param string $areaCode
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setAreaCode($areaCode = null)
    {
        $this->areaCode = (string) $this->cleanTelephoneSpecialChars($areaCode);
        return $this;
    }

    /**
     * Set the Local Telephone Number.
     * @param string $localNumber
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setLocalNumber($localNumber = null)
    {
        $this->localNumber = (string) $this->cleanTelephoneSpecialChars($localNumber);
        return $this;
    }

    /**
     * Set the Extension.
     * @param type $extension
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setExtension($extension = null)
    {
        $this->extension = (string) filter_var($extension, FILTER_SANITIZE_STRING);
        return $this;
    }

    /**
     * Set the E-mail Address.
     * @param string $emailAddress Valid e-mail address.
     * @return \Udoh\Emsa\Model\Telecom
     */
    public function setEmailAddress($emailAddress)
    {
        $validatedEmailAddress = filter_var($emailAddress, FILTER_VALIDATE_EMAIL);
        
        if (!empty($validatedEmailAddress)) {
            $this->emailAddress = (string) filter_var($emailAddress, FILTER_SANITIZE_EMAIL);
        }
        
        return $this;
    }
    
    /**
     * Sanitize an input to remove any special characters used in telephone numbers.
     * @param mixed $input
     * @return string
     */
    protected function cleanTelephoneSpecialChars($input = null)
    {
        if (empty($input)) {
            return null;
        }
        
        $cleanser = array(
            'tel:' => '',
            'fax:' => '',
            '(' => '',
            ')' => '',
            '-' => '',
            '.' => '',
            '+1' => '',
            ' ' => ''
        );
        
        $filteredInput = (string) filter_var($input, FILTER_SANITIZE_STRING);
        
        return (string) strtr($filteredInput, $cleanser);
    }



}

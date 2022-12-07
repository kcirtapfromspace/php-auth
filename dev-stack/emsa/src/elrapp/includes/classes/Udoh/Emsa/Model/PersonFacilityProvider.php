<?php

namespace Udoh\Emsa\Model;

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

use SimpleXMLElement;

/**
 * The healthcare provider associated with a PersonFacility healthcare facility encounter.
 *
 * @package Udoh\Emsa\Model
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PersonFacilityProvider
{
    /** @var string */
    protected $firstName;
    /** @var string */
    protected $lastName;
    /** @var string */
    protected $middleName;
    /** @var string */
    protected $phone;
    /** @var string */
    protected $areaCode;
    /** @var string */
    protected $extension;
    /** @var string */
    protected $email;

    /**
     * Create a new PersonFacilityProvider.
     *
     * @param SimpleXMLElement $provider
     */
    public function __construct(SimpleXMLElement $provider)
    {
        if (isset($provider->lastName) && !empty($provider->lastName)) {
            $this->setLastName((string) $provider->lastName);
        }

        if (isset($provider->firstName) && !empty($provider->firstName)) {
            $this->setFirstName((string) $provider->firstName);
        }

        if (isset($provider->middleName) && !empty($provider->middleName)) {
            $this->setMiddleName((string) $provider->middleName);
        }

        if (isset($provider->areaCode) && !empty($provider->areaCode)) {
            $this->setAreaCode((string) $provider->areaCode);
        }

        if (isset($provider->phone) && !empty($provider->phone)) {
            $this->setPhone((string) $provider->phone);
        }

        if (isset($provider->extension) && !empty($provider->extension)) {
            $this->setExtension((string) $provider->extension);
        }

        if (isset($provider->email) && !empty($provider->email)) {
            $this->setEmail((string) $provider->email);
        }
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     *
     * @return PersonFacilityProvider
     */
    public function setFirstName($firstName = null)
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     *
     * @return PersonFacilityProvider
     */
    public function setLastName($lastName = null)
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @return string
     */
    public function getMiddleName()
    {
        return $this->middleName;
    }

    /**
     * @param string $middleName
     *
     * @return PersonFacilityProvider
     */
    public function setMiddleName($middleName = null)
    {
        $this->middleName = $middleName;
        return $this;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param string $phone
     *
     * @return PersonFacilityProvider
     */
    public function setPhone($phone = null)
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @return string
     */
    public function getAreaCode()
    {
        return $this->areaCode;
    }

    /**
     * @param string $areaCode
     *
     * @return PersonFacilityProvider
     */
    public function setAreaCode($areaCode = null)
    {
        $this->areaCode = $areaCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return PersonFacilityProvider
     */
    public function setEmail($email = null)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     *
     * @return PersonFacilityProvider
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }


}
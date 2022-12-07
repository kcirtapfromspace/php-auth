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
 * The participating facility associated with a PersonFacility healthcare facility encounter.
 *
 * @package Udoh\Emsa\Model
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PersonFacilityParticipant
{
    /** @var string */
    protected $facilityName;
    /** @var string */
    protected $streetName;
    /** @var string */
    protected $unitNumber;
    /** @var string */
    protected $city;
    /** @var string */
    protected $state;
    /** @var string */
    protected $postalCode;

    /**
     * Create a new PersonFacilityParticipant.
     *
     * @param SimpleXMLElement $facility
     */
    public function __construct(SimpleXMLElement $facility)
    {
        if (isset($facility->name) && !empty($facility->name)) {
            $this->setFacilityName((string) $facility->name);
        }

        if (isset($facility->address->street) && !empty($facility->address->street)) {
            $this->setStreetName((string) $facility->address->street);
        }

        if (isset($facility->address->unitNumber) && !empty($facility->address->unitNumber)) {
            $this->setUnitNumber((string) $facility->address->unitNumber);
        }

        if (isset($facility->address->city) && !empty($facility->address->city)) {
            $this->setCity((string) $facility->address->city);
        }

        if (isset($facility->address->state->code) && !empty($facility->address->state->code)) {
            $this->setState((string) $facility->address->state->code);
        }

        if (isset($facility->address->zip) && !empty($facility->address->zip)) {
            $this->setPostalCode((string) $facility->address->zip);
        }
    }

    /**
     * @return string
     */
    public function getFacilityName()
    {
        return $this->facilityName;
    }

    /**
     * @param string $facilityName
     *
     * @return PersonFacilityParticipant
     */
    public function setFacilityName($facilityName = null)
    {
        $this->facilityName = $facilityName;
        return $this;
    }

    /**
     * @return string
     */
    public function getStreetName()
    {
        return $this->streetName;
    }

    /**
     * @param string $streetName
     *
     * @return PersonFacilityParticipant
     */
    public function setStreetName($streetName = null)
    {
        $this->streetName = $streetName;
        return $this;
    }

    /**
     * @return string
     */
    public function getUnitNumber()
    {
        return $this->unitNumber;
    }

    /**
     * @param string $unitNumber
     *
     * @return PersonFacilityParticipant
     */
    public function setUnitNumber($unitNumber = null)
    {
        $this->unitNumber = $unitNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string $city
     *
     * @return PersonFacilityParticipant
     */
    public function setCity($city = null)
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     *
     * @return PersonFacilityParticipant
     */
    public function setState($state = null)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @param string $postalCode
     *
     * @return PersonFacilityParticipant
     */
    public function setPostalCode($postalCode = null)
    {
        $this->postalCode = $postalCode;
        return $this;
    }


}
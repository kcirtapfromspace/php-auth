<?php

namespace Udoh\Emsa\Model;

use DateTime;

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
 * EMSA Person object.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Person
{

    /** @var int */
    protected $personId;

    /** @var float */
    protected $matchScore;

    /** @var string */
    protected $firstName;

    /** @var string */
    protected $middleName;

    /** @var string */
    protected $lastName;

    /** @var string */
    protected $gender;
    
    /** @var array Array of race descriptions (race is a collection, not a single property) */
    protected $races;
    
    /** @var string */
    protected $ethnicity;
    
    /** @var string */
    protected $language;

    /** @var DateTime */
    protected $dateOfBirth;
    
    /** @var DateTime */
    protected $dateOfDeath;

    /** @var PersonFacilityList */
    protected $personFacilityList;

    /** @var AppRecordList */
    protected $recordList;

    /** @var AddressList */
    protected $addressList;

    /** @var TelecomList */
    protected $telecomList;

    function __construct()
    {
        $this->recordList = new AppRecordList();
        $this->addressList = new AddressList();
        $this->telecomList = new TelecomList();
        $this->personFacilityList = new PersonFacilityList();
        $this->races = array();
    }

    public function getPersonId()
    {
        return $this->personId;
    }

    public function getMatchScore()
    {
        return $this->matchScore;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getMiddleName()
    {
        return $this->middleName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function getGender()
    {
        return $this->gender;
    }
    
    /**
     * Checks whether this Person has the specified Race value set.
     * @param string $raceName Race description to check for.
     * @return boolean
     */
    public function hasRace($raceName)
    {
        if (!empty($this->races)) {
            if (in_array((string) $raceName, $this->races)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get an array containing all of this Person's Races.
     * 
     * @return array Array of strings representing race names.
     */
    public function getRaces()
    {
        return $this->races;
    }
    
    /**
     * Get this Person's Ethnicity.
     * 
     * @return string
     */
    public function getEthnicity()
    {
        return $this->ethnicity;
    }
    
    /**
     * Get this Person's Primary Language.
     * 
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Get the person's Date of Birth.
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     * 
     * @return string|DateTime|null
     */
    public function getDateOfBirth($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return \Udoh\Emsa\Utils\DateTimeUtils::getDateFormatted($this->dateOfBirth, $formatted, $formatStr);
    }

    /**
     * Get the person's Date of Death.
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     * 
     * @return string|DateTime|null
     */
    public function getDateOfDeath($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return \Udoh\Emsa\Utils\DateTimeUtils::getDateFormatted($this->dateOfDeath, $formatted, $formatStr);
    }

    /**
     * Get this person's list of Healthcare Facilities
     *
     * @return PersonFacilityList
     */
    public function getPersonFacilityList(): PersonFacilityList
    {
        return $this->personFacilityList;
    }

    /**
     * Get this person's list of records.
     * 
     * @return AppRecordList
     */
    public function getRecordList()
    {
        return $this->recordList;
    }

    /**
     * Get this person's addresses.
     * 
     * @return AddressList
     */
    public function getAddressList()
    {
        return $this->addressList;
    }

    /**
     * Get this person's telecom items.
     * 
     * @return TelecomList
     */
    public function getTelecomList()
    {
        return $this->telecomList;
    }

    /**
     * Set the person's ID.
     * 
     * @param int $personId
     * @return \Udoh\Emsa\Model\Person
     */
    public function setPersonId($personId = null)
    {
        if (!empty($personId)) {
            $this->personId = (int) filter_var($personId, FILTER_SANITIZE_NUMBER_INT);
        } else {
            $this->personId = null;
        }

        return $this;
    }

    /**
     * Set the Match Score for this person (used for ranked search results)
     * @param float $matchScore [Optional]<br>Floating point match percentage value (0-100)
     * @return \Udoh\Emsa\Model\Person
     */
    public function setMatchScore($matchScore = null)
    {
        $this->matchScore = null;
        $matchScoreClean = (float) filter_var($matchScore, FILTER_SANITIZE_NUMBER_FLOAT);

        if (!empty($matchScore)) {
            if (($matchScoreClean <= 100.0) && ($matchScoreClean >= 0.0)) {
                $this->matchScore = $matchScoreClean;
            } else {
                throw new \InvalidArgumentException("Match Score must be a numerical value from 0-100");
            }
        }

        return $this;
    }

    /**
     * Set person's First Name.
     * 
     * @param string $firstName
     * @return \Udoh\Emsa\Model\Person
     */
    public function setFirstName($firstName = null)
    {
        if (!empty($firstName)) {
            $this->firstName = $firstName;
        } else {
            $this->firstName = null;
        }

        return $this;
    }

    /**
     * Set person's Middle Name.
     * 
     * @param string $middleName
     * @return \Udoh\Emsa\Model\Person
     */
    public function setMiddleName($middleName = null)
    {
        if (!empty($middleName)) {
            $this->middleName = $middleName;
        } else {
            $this->middleName = null;
        }

        return $this;
    }

    /**
     * Set person's Last Name.
     * 
     * @param string $lastName
     * @return \Udoh\Emsa\Model\Person
     */
    public function setLastName($lastName = null)
    {
        if (!empty($lastName)) {
            $this->lastName = $lastName;
        } else {
            $this->lastName = null;
        }

        return $this;
    }

    /**
     * Set the person's Gender.
     * 
     * @param string $gender
     * @return \Udoh\Emsa\Model\Person
     */
    public function setGender($gender = null)
    {
        if (!empty($gender)) {
            $this->gender = $gender;
        } else {
            $this->gender = null;
        }

        return $this;
    }
    
    /**
     * Adds to the person's set of Races.
     * 
     * @param string $race
     * @return \Udoh\Emsa\Model\Person
     */
    public function addRace($race = null)
    {
        if (!empty($race) && !$this->hasRace((string) $race)) {
            $this->races[] = (string) $race;
        }

        return $this;
    }
    
    /**
     * Set the person's Ethnicity.
     * 
     * @param string $ethnicity
     * @return \Udoh\Emsa\Model\Person
     */
    public function setEthnicity($ethnicity = null)
    {
        if (!empty($ethnicity)) {
            $this->ethnicity = $ethnicity;
        } else {
            $this->ethnicity = null;
        }

        return $this;
    }

    /**
     * Set the person's Primary Language.
     * 
     * @param string $language
     * @return \Udoh\Emsa\Model\Person
     */
    public function setLanguage($language = null)
    {
        if (!empty($language)) {
            $this->language = $language;
        } else {
            $this->language = null;
        }

        return $this;
    }

    /**
     * Set person's Date of Birth.
     * 
     * @param DateTime $dateOfBirth
     * @return \Udoh\Emsa\Model\Person
     */
    public function setDateOfBirth(DateTime $dateOfBirth = null)
    {
        if (!empty($dateOfBirth)) {
            $this->dateOfBirth = $dateOfBirth;
        } else {
            $this->dateOfBirth = null;
        }

        return $this;
    }

    /**
     * Set person's Date of Death.
     * 
     * @param DateTime $dateOfDeath
     * @return \Udoh\Emsa\Model\Person
     */
    public function setDateOfDeath(DateTime $dateOfDeath = null)
    {
        if (!empty($dateOfDeath)) {
            $this->dateOfDeath = $dateOfDeath;
        } else {
            $this->dateOfDeath = null;
        }

        return $this;
    }

    /**
     * Add a single Person Facility to this Person's Person Facility List.
     *
     * @param PersonFacility $personFacility
     *
     * @return Person
     */
    public function addPersonFacility(PersonFacility $personFacility): Person
    {
        if (empty($this->personFacilityList) || !($this->personFacilityList instanceof PersonFacilityList)) {
            $this->personFacilityList = new PersonFacilityList();
        }

        $this->personFacilityList->add($personFacility);
        return $this;
    }

    /**
     * Add a Record List to this person.
     * 
     * @param \Udoh\Emsa\Model\AppRecordList $recordList
     * @return \Udoh\Emsa\Model\Person
     */
    public function addRecordList(AppRecordList $recordList)
    {
        $this->recordList = $recordList;
        return $this;
    }

    /**
     * Add a single Record to this person's Record List.
     * 
     * @param \Udoh\Emsa\Model\AppRecord $record
     * @return \Udoh\Emsa\Model\Person
     */
    public function addRecord(AppRecord $record)
    {
        if (empty($this->recordList) || !($this->recordList instanceof AppRecordList)) {
            $this->recordList = new AppRecordList();
        }

        $this->recordList->add($record);
        return $this;
    }

    /**
     * Add an Address List to this person.
     * 
     * @param \Udoh\Emsa\Model\AddressList $addressList
     * @return \Udoh\Emsa\Model\Person
     */
    public function addAddressList(AddressList $addressList)
    {
        $this->addressList = $addressList;
        return $this;
    }

    /**
     * Add an Address to this person.
     * 
     * @param \Udoh\Emsa\Model\Address $address
     * @return \Udoh\Emsa\Model\Person
     */
    public function addAddress(Address $address)
    {
        if (empty($this->addressList) || !($this->addressList instanceof AddressList)) {
            $this->addressList = new AddressList();
        }

        $this->addressList->add($address);
        return $this;
    }

    /**
     * Add a Telecom List to this person.
     * 
     * @param \Udoh\Emsa\Model\TelecomList $telecomList
     * @return \Udoh\Emsa\Model\Person
     */
    public function addTelecomList(TelecomList $telecomList)
    {
        $this->telecomList = $telecomList;
        return $this;
    }

    /**
     * Add a new Telecom to this person.
     * 
     * @param \Udoh\Emsa\Model\Telecom $telecom
     * @return \Udoh\Emsa\Model\Person
     */
    public function addTelecom(Telecom $telecom)
    {
        if (empty($this->telecomList) || !($this->telecomList instanceof TelecomList)) {
            $this->telecomList = new TelecomList();
        }

        $this->telecomList->add($telecom);
        return $this;
    }

}

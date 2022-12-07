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

use DateTime;
use SimpleXMLElement;
use Throwable;
use Udoh\Emsa\Utils\DateTimeUtils;

/**
 * A healthcare facility encounter associated with a patient's health record.
 *
 * @package Udoh\Emsa\Model
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PersonFacility
{
    /** @var PersonFacilityProvider */
    protected $provider;
    /** @var PersonFacilityParticipant */
    protected $participant;
    /** @var DateTime */
    protected $admissionDate;
    /** @var DateTime */
    protected $dischargeDate;
    /** @var string */
    protected $visitType;
    /** @var string */
    protected $medicalRecordNumber;

    /**
     * Create a new PersonFacility.
     *
     * @param SimpleXMLElement $personFacilityXML
     */
    public function __construct(SimpleXMLElement $personFacilityXML)
    {
        if (isset($personFacilityXML->medicalRecordNumber) && !empty($personFacilityXML->medicalRecordNumber)) {
            $this->setMedicalRecordNumber((string) $personFacilityXML->medicalRecordNumber);
        }

        if (isset($personFacilityXML->facilityVisitType->code) && !empty($personFacilityXML->facilityVisitType->code)) {
            $this->setVisitType((string) $personFacilityXML->facilityVisitType->code);
        }

        try {
            if (isset($personFacilityXML->admissionDate) && !empty($personFacilityXML->admissionDate)) {
                $this->setAdmissionDate(DateTimeUtils::createMixed((string)$personFacilityXML->admissionDate));
            }
        } catch (Throwable $e) {
            $e = null;
        }

        try {
            if (isset($personFacilityXML->dischargeDate) && !empty($personFacilityXML->dischargeDate)) {
                $this->setDischargeDate(DateTimeUtils::createMixed((string)$personFacilityXML->dischargeDate));
            }
        } catch (Throwable $e) {
            $e = null;
        }

        if (isset($personFacilityXML->provider)) {
            $this->setProvider(new PersonFacilityProvider($personFacilityXML->provider));
        }

        if (isset($personFacilityXML->facility)) {
            $this->setParticipant(new PersonFacilityParticipant($personFacilityXML->facility));
        }
    }

    /**
     * @return PersonFacilityProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param PersonFacilityProvider $provider
     *
     * @return PersonFacility
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return PersonFacilityParticipant
     */
    public function getParticipant()
    {
        return $this->participant;
    }

    /**
     * @param PersonFacilityParticipant $participant
     *
     * @return PersonFacility
     */
    public function setParticipant($participant)
    {
        $this->participant = $participant;
        return $this;
    }

    /**
     * Get the Admission Date for this healthcare facility visit.
     *
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     *
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getAdmissionDate($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->admissionDate, $formatted, $formatStr);
    }

    /**
     * @param DateTime $admissionDate
     *
     * @return PersonFacility
     */
    public function setAdmissionDate($admissionDate)
    {
        $this->admissionDate = $admissionDate;
        return $this;
    }

    /**
     * Get the Discharge Date for this healthcare facility visit.
     *
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     *
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getDischargeDate($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dischargeDate, $formatted, $formatStr);
    }

    /**
     * @param DateTime $dischargeDate
     *
     * @return PersonFacility
     */
    public function setDischargeDate($dischargeDate)
    {
        $this->dischargeDate = $dischargeDate;
        return $this;
    }

    /**
     * @return string
     */
    public function getVisitType()
    {
        return $this->visitType;
    }

    /**
     * @param string $visitType
     *
     * @return PersonFacility
     */
    public function setVisitType($visitType)
    {
        $this->visitType = $visitType;
        return $this;
    }

    /**
     * @return string
     */
    public function getMedicalRecordNumber()
    {
        return $this->medicalRecordNumber;
    }

    /**
     * @param string $medicalRecordNumber
     *
     * @return PersonFacility
     */
    public function setMedicalRecordNumber($medicalRecordNumber)
    {
        $this->medicalRecordNumber = $medicalRecordNumber;
        return $this;
    }


}
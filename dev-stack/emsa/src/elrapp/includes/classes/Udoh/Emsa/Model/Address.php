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

use Udoh\Emsa\Client\AppClientInterface;
use Udoh\Emsa\Exceptions\AppClientNoValidHosts;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Address class description
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Address
{

    protected $street;
    protected $unitNumber;
    protected $city;
    protected $state;
    protected $postalCode;
    protected $county;
    protected $latitude;
    protected $longitude;

    /**
     * Create a new Address object.
     * 
     * @param string $street
     * @param string $unitNumber
     * @param string $city
     * @param string $state
     * @param string $postalCode
     * @param string $county
     */
    public function __construct($street = null, $unitNumber = null, $city = null, $state = null, $postalCode = null, $county = null)
    {
        $this->setStreet($street);
        $this->setUnitNumber($unitNumber);
        $this->setCity($city);
        $this->setState($state);
        $this->setPostalCode($postalCode);
        $this->setCounty($county);
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function getUnitNumber()
    {
        return $this->unitNumber;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getPostalCode()
    {
        return $this->postalCode;
    }

    public function getCounty()
    {
        return $this->county;
    }
    
    public function getLatitude()
    {
        return $this->latitude;
    }
    
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set the Street Address
     *
     * @param string $street
     *
     * @return Address
     */
    public function setStreet($street = null)
    {
        $this->street = null;

        if (!empty($street)) {
            $this->street = (string) filter_var($street, FILTER_SANITIZE_STRING);
        }

        return $this;
    }

    /**
     * Set the Unit Number
     *
     * @param string $unitNumber
     *
     * @return Address
     */
    public function setUnitNumber($unitNumber = null)
    {
        $this->unitNumber = null;

        if (!empty($unitNumber)) {
            $this->unitNumber = (string) filter_var($unitNumber, FILTER_SANITIZE_STRING);
        }

        return $this;
    }

    /**
     * Set the City
     *
     * @param string $city
     *
     * @return Address
     */
    public function setCity($city = null)
    {
        $this->city = null;

        if (!empty($city)) {
            $this->city = (string) filter_var($city, FILTER_SANITIZE_STRING);
        }

        return $this;
    }

    /**
     * Set the State
     *
     * @param string $state
     *
     * @return Address
     */
    public function setState($state = null)
    {
        $this->state = null;

        if (!empty($state)) {
            $this->state = (string) filter_var($state, FILTER_SANITIZE_STRING);
        }

        return $this;
    }

    /**
     * Set the Postal Code
     *
     * @param string $postalCode
     *
     * @return Address
     */
    public function setPostalCode($postalCode = null)
    {
        $this->postalCode = null;

        if (!empty($postalCode)) {
            $this->postalCode = (string) filter_var($postalCode, FILTER_SANITIZE_STRING);
        }

        return $this;
    }

    /**
     * Set the County
     * @param string $county
     * @return Address
     */
    public function setCounty($county = null)
    {
        $this->county = null;

        if (!empty($county)) {
            $this->county = (string) filter_var($county, FILTER_SANITIZE_STRING);
        }

        return $this;
    }
    
    /**
     * Set the Latitude
     *
     * @param string $latitude
     *
     * @return Address
     */
    public function setLatitude($latitude = null)
    {
        $this->latitude = null;
        
        if (!empty($latitude)) {
            $this->latitude = (string) filter_var($latitude, FILTER_SANITIZE_STRING);
        }
        
        return $this;
    }

    /**
     * Set the Longitude
     * @param string $longitude
     * @return Address
     */
    public function setLongitude($longitude = null)
    {
        $this->longitude = null;
        
        if (!empty($longitude)) {
            $this->longitude = (string) filter_var($longitude, FILTER_SANITIZE_STRING);
        }
        
        return $this;
    }
    
    /**
     * Uses geocoding service to set latitude and longitude for an Addresses and attempts to set County based on Zip Code if not already set.
     *
     * @param AppClientInterface $appClient
     *
     * @return Address
     */
    public function geocode(AppClientInterface $appClient)
    {
        if (!defined('EPITRAX_GEO_SERVICE_ENDPOINT')) {
            return $this;
        }

        $geocodedLat = null;
        $geocodedLong = null;
        $geocodedCounty = null;
        $translatedCountyId = null;

        if (!empty($this->getStreet()) && !empty($this->getPostalCode())) {
            try {
                $geoCurl = curl_init();
                $geoUrl = $appClient->getServiceURLRoundRobin() . EPITRAX_GEO_SERVICE_ENDPOINT . '?street_name=' . @urlencode($this->getStreet()) . '&zip=' . @urlencode($this->getPostalCode());
                curl_setopt($geoCurl, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID));
                curl_setopt($geoCurl, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($geoCurl, CURLOPT_TIMEOUT, 10);
                curl_setopt($geoCurl, CURLOPT_RETURNTRANSFER, true);
                // Warning: only use CURLOPT_VERBOSE for debugging non-production data; can cause addresses to be exposed in error_log
                // curl_setopt($geo_curl, CURLOPT_VERBOSE, true);
                curl_setopt($geoCurl, CURLOPT_URL, $geoUrl);

                $geocodeReturn = curl_exec($geoCurl);
                curl_close($geoCurl);

                if ($geocodeReturn) {
                    $geocodeReturnArr = @json_decode($geocodeReturn);
                    if (is_array($geocodeReturnArr) && (count($geocodeReturnArr) == 1)) {  // validate that exactly one match found
                        // make sure the lat & long are well-formed
                        $geocodedLat = (isset($geocodeReturnArr[0]->metadata->latitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($geocodeReturnArr[0]->metadata->latitude))) ? trim($geocodeReturnArr[0]->metadata->latitude) : null;
                        $geocodedLong = (isset($geocodeReturnArr[0]->metadata->longitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($geocodeReturnArr[0]->metadata->longitude))) ? trim($geocodeReturnArr[0]->metadata->longitude) : null;
                        $geocodedCounty = (isset($geocodeReturnArr[0]->metadata->county_name)) ? trim($geocodeReturnArr[0]->metadata->county_name) : null;
                        $geocodedState = (isset($geocodeReturnArr[0]->components->state_abbreviation)) ? trim($geocodeReturnArr[0]->components->state_abbreviation) : null;
                    }
                }

                if (empty($this->getCounty()) && !empty($geocodedCounty)) {
                    // if no county is set by mapping, and we got a county back from Geocoding service, set it based on the county returned from Geocoding service...
                    $translatedCountyId = (int) CodedDataUtils::getCodeIdFromDescription($appClient, 'county', $geocodedCounty);

                    if ($translatedCountyId > 0) {
                        $this->setCounty($geocodedCounty);
                    } else {
                        // if county didn't find a match & state code doesn't match HOME_STATE_ABBR, set to 'Out-of-state'
                        if (isset($geocodedState) && strtoupper($geocodedState) != strtoupper(HOME_STATE_ABBR)) {
                            $this->setCounty('Out-of-state');
                        }
                    }
                }

                if (!empty($geocodedLat) && !empty($geocodedLong)) {
                    $this->setLatitude($geocodedLat);
                    $this->setLongitude($geocodedLong);
                }
            } catch (AppClientNoValidHosts $e) {
                ExceptionUtils::logException($e);
            }
        }
        return $this;
    }

    /**
     * Uses geocoding service to validate & standardize an Addresses and attempts to set County based on Zip Code if not already set.
     *
     * @param AppClientInterface $appClient
     *
     * @return Address
     */
    public function geocodeFull(AppClientInterface $appClient)
    {
        if (!defined('EPITRAX_GEO_SERVICE_ENDPOINT')) {
            return $this;
        }

        if (!empty($this->getStreet()) && !empty($this->getPostalCode())) {
            try {
                $geoCurl = curl_init();
                $geoUrl = $appClient->getServiceURLRoundRobin() . EPITRAX_GEO_SERVICE_ENDPOINT . '?zip=' . urlencode($this->getPostalCode());

                if (!empty($this->getUnitNumber())) {
                    // concatenate street+unit if unit is present, since geo service can't currently accept unit as discrete input
                    $geoUrl .= '&street_name=' . urlencode($this->getStreet() . ' ' . $this->getUnitNumber());
                } else {
                    $geoUrl .= '&street_name=' . urlencode($this->getStreet());
                }

                if (!empty($this->getCity())) {
                    $geoUrl .= '&city=' . urlencode($this->getCity());
                }
                if (!empty($this->getState())) {
                    $geoUrl .= '&state=' . urlencode($this->getState());
                }

                curl_setopt($geoCurl, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID));
                curl_setopt($geoCurl, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($geoCurl, CURLOPT_TIMEOUT, 10);
                curl_setopt($geoCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($geoCurl, CURLOPT_URL, $geoUrl);

                $geocodeReturn = curl_exec($geoCurl);
                curl_close($geoCurl);

                if ($geocodeReturn) {
                    $geocodeReturnArr = @json_decode($geocodeReturn);
                    if (is_array($geocodeReturnArr) && (count($geocodeReturnArr) > 0)) {
                        $geoReturnObj  = $geocodeReturnArr[0];
                        $analysisObj   = $geoReturnObj->analysis;
                        $metadataObj   = $geoReturnObj->metadata;
                        $componentsObj = $geoReturnObj->components;

                        $dpvMatchCode = (string) ($analysisObj->dpv_match_code ?? "");
                        $dpvFootnotes = (string) ($analysisObj->dpv_footnotes ?? "");
                        $recordType   = (string) ($metadataObj->record_type ?? "");

                        if (!empty($dpvMatchCode) && !empty($dpvFootnotes)) {
                            if ($dpvMatchCode == "N") {
                                // no match
                            } elseif (($dpvMatchCode == "D") || ($dpvMatchCode == "Y") || ($dpvMatchCode == "S")) {
                                if (stripos($dpvFootnotes, "A1") !== false) {
                                    // no match
                                } else {
                                    // update this address with values from geocoding service
                                    //   a) set lat/long
                                    if (!empty($metadataObj->latitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($metadataObj->latitude))) {
                                        $this->setLatitude(trim($metadataObj->latitude));
                                    }
                                    if (!empty($metadataObj->longitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($metadataObj->longitude))) {
                                        $this->setLongitude(trim($metadataObj->longitude));
                                    }

                                    //   b) assemble component values
                                    $foundPrimaryNumber = null;
                                    $foundStreetPredirection = null;
                                    $foundStreetPostdirection = null;
                                    $foundStreetName = null;
                                    $foundStreetSuffix = null;
                                    $foundSecondaryDesignator = null;
                                    $foundSecondaryNumber = null;
                                    $foundUnit = null;
                                    $deliveryLine1 = null;
                                    $deliveryLine2 = null;
                                    $foundCityName = null;
                                    $foundStateAbbreviation = null;
                                    $foundZipcode = null;
                                    $foundCounty = null;
                                    $streetAddress = "";

                                    if (!empty($componentsObj->primary_number)) {
                                        $foundPrimaryNumber = trim($componentsObj->primary_number);
                                    }
                                    if (!empty($componentsObj->street_predirection)) {
                                        $foundStreetPredirection = trim($componentsObj->street_predirection);
                                    }
                                    if (!empty($componentsObj->street_postdirection)) {
                                        $foundStreetPostdirection = trim($componentsObj->street_postdirection);
                                    }
                                    if (!empty($componentsObj->street_name)) {
                                        $foundStreetName = trim($componentsObj->street_name);
                                    }
                                    if (!empty($componentsObj->street_suffix)) {
                                        $foundStreetSuffix = trim($componentsObj->street_suffix);
                                    }
                                    if (!empty($componentsObj->secondary_designator)) {
                                        $foundSecondaryDesignator = trim($componentsObj->secondary_designator);
                                    }
                                    if (!empty($componentsObj->secondary_number)) {
                                        $foundSecondaryNumber = trim($componentsObj->secondary_number);
                                    }
                                    if (!empty($componentsObj->delivery_line_1)) {
                                        $deliveryLine1 = trim($componentsObj->delivery_line_1);
                                    }
                                    if (!empty($componentsObj->delivery_line_2)) {
                                        $deliveryLine2 = trim($componentsObj->delivery_line_2);
                                    }
                                    if (!empty($componentsObj->city_name)) {
                                        $foundCityName = trim($componentsObj->city_name);
                                    }
                                    if (!empty($componentsObj->state_abbreviation)) {
                                        $foundStateAbbreviation = trim($componentsObj->state_abbreviation);
                                    }
                                    if (!empty($componentsObj->zipcode)) {
                                        $foundZipcode = trim($componentsObj->zipcode);
                                    }
                                    if (!empty($metadataObj->county_name)) {
                                        $foundCounty = trim($metadataObj->county_name);
                                    }

                                    //   c) build street address, part 1
                                    if (!empty($recordType) && ($recordType == "P")) {
                                        if (!empty($foundStreetName)) {
                                            $streetAddress .= $foundStreetName;
                                        }
                                        if (!empty($foundPrimaryNumber)) {
                                            $streetAddress .= " ";
                                            $streetAddress .= $foundPrimaryNumber;
                                        }
                                    } else {
                                        if (!empty($foundPrimaryNumber)) {
                                            $streetAddress .= $foundPrimaryNumber;
                                        }
                                        if (!empty($foundStreetPredirection)) {
                                            $streetAddress .= " ";
                                            $streetAddress .= $foundStreetPredirection;
                                        }
                                        if (!empty($foundStreetName)) {
                                            $streetAddress .= " ";
                                            $streetAddress .= $foundStreetName;
                                        }
                                        if (!empty($foundStreetSuffix)) {
                                            $streetAddress .= " ";
                                            $streetAddress .= $foundStreetSuffix;
                                        }
                                        if (!empty($foundStreetPostdirection)) {
                                            $streetAddress .= " ";
                                            $streetAddress .= $foundStreetPostdirection;
                                        }

                                        if (!empty($foundSecondaryDesignator) && !empty($foundSecondaryNumber)) {
                                            $foundUnit = "$foundSecondaryDesignator $foundSecondaryNumber";
                                        } elseif (!empty($foundSecondaryNumber)) {
                                            $foundUnit = $foundSecondaryNumber;
                                        }
                                    }

                                    $this->setUnitNumber($foundUnit);
                                    $this->setCity($foundCityName);
                                    $this->setState($foundStateAbbreviation);
                                    $this->setPostalCode($foundZipcode);
                                    $this->setCounty($foundCounty);

                                    //   d) build street address, part 2
                                    $footnotesContainsAA = (stripos($dpvFootnotes, "AA") !== false);
                                    $footnotesContainsBB = (stripos($dpvFootnotes, "BB") !== false);
                                    $footnotesContainsCC = (stripos($dpvFootnotes, "CC") !== false);
                                    $footnotesContainsN1 = (stripos($dpvFootnotes, "N1") !== false);
                                    $footnotesContainsU1 = (stripos($dpvFootnotes, "U1") !== false);
                                    $footnotesContainsF1 = (stripos($dpvFootnotes, "F1") !== false);
                                    $footnotesContainsG1 = (stripos($dpvFootnotes, "G1") !== false);

                                    if ($footnotesContainsAA || $footnotesContainsBB || $footnotesContainsCC || $footnotesContainsN1 || $footnotesContainsU1) {
                                        $this->setStreet($streetAddress);
                                    }

                                    if ($footnotesContainsF1 || $footnotesContainsG1) {
                                        $dlStreetAddress = "";
                                        if (!empty($deliveryLine1)) {
                                            $dlStreetAddress .= $deliveryLine1;
                                        }
                                        if (!empty($deliveryLine2)) {
                                            $dlStreetAddress .= $deliveryLine2;
                                        }
                                        $this->setStreet($dlStreetAddress);
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (AppClientNoValidHosts $e) {
                ExceptionUtils::logException($e);
            }
        }
        return $this;
    }

    /**
     * Returns the specified address elements in a formatted, escaped, multi-line HTML string.
     *
     * @param string|null $streetAddress
     * @param string|null $unitNumber
     * @param string|null $city
     * @param string|null $state
     * @param string|null $postalCode
     * @param string|null $county
     *
     * @return string
     */
    public static function formatAddressMultiLine(?string $streetAddress, ?string $unitNumber, ?string $city, ?string $state, ?string $postalCode, ?string $county): string
    {
        $addressStr = implode("<br>", array_filter(array(
            DisplayUtils::xSafe($streetAddress),
            DisplayUtils::xSafe($unitNumber),
            implode(" ", array_filter(array(DisplayUtils::xSafe($city), DisplayUtils::xSafe($state), DisplayUtils::xSafe($postalCode))))
        )));

        if (strlen(trim($county)) > 0) {
            $addressStr .= '<br>[' . DisplayUtils::xSafe($county) . ' ' . self::getCountySuffix($state) . ']';
        }

        return $addressStr;
    }

    /**
     * Returns the specified address elements in a formatted, escaped, single-line string.
     *
     * @param string|null $streetAddress
     * @param string|null $unitNumber
     * @param string|null $city
     * @param string|null $state
     * @param string|null $postalCode
     * @param string|null $county
     *
     * @return string
     */
    public static function formatAddressSingleLine(?string $streetAddress, ?string $unitNumber, ?string $city, ?string $state, ?string $postalCode, ?string $county): string
    {
        $addressStr = implode(", ", array_filter(array(
            DisplayUtils::xSafe($streetAddress),
            DisplayUtils::xSafe($unitNumber),
            DisplayUtils::xSafe($city),
            implode(" ", array_filter(array(DisplayUtils::xSafe($state), DisplayUtils::xSafe($postalCode))))
        )));

        if (strlen(trim($county)) > 0) {
            $addressStr .= ' [' . DisplayUtils::xSafe($county) . ' ' . self::getCountySuffix($state) . ']';
        }

        return $addressStr;
    }

    /**
     * Retuns the appropriate State administrative subdivision name for the given state.
     *
     * @param string|null $state Two-letter State abbreviation (e.g. "UT")
     *
     * @return string
     */
    protected static function getCountySuffix(?string $state)
    {
        $countySuffix = 'County';

        if (!empty($state)) {
            if ($state == 'LA') {
                $countySuffix = 'Parrish';
            } elseif ($state == 'AK') {
                $countySuffix = 'Borough';
            }
        }

        return $countySuffix;
    }

}

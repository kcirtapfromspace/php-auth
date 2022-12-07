<?php

namespace Udoh\Emsa\Client;

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
 * Client for interacting with Mirth SOAP service.
 * 
 * @package Udoh\Emsa\Client
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class MirthServiceClient
{

    /** @var \SoapClient */
    protected $client;

    /**
     * Creates a new MirthServiceClient object.
     * 
     * @throws \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault
     */
    public function __construct()
    {
        try {
            $this->client = new \SoapClient(\MIRTH_PATH);
        } catch (\SoapFault $e) {
            throw new \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault($e->faultcode, 'Unable to connect to SOAP service:  ' . $e->getMessage());
        }
    }
    
    /**
     * Get back hl7v2 XML from Mirth for an HL7 message.
     * 
     * @param string $msgPayload HL7 message
     * 
     * @return \SimpleXMLElement hl7v2 XML
     */
    public function acceptMessage($msgPayload)
    {
        $payload = array('arg0' => $msgPayload);
        
        $result = $this->client->acceptMessage($payload);
        
        $acceptReturn = simplexml_load_string(str_replace('xmlns="urn:hl7-org:v2xml"', '', $result->return));

        return $acceptReturn;
    }

}

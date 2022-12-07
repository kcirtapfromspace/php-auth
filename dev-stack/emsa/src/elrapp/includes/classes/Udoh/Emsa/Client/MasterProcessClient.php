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
 * Client for managing Master Process web service requests.
 * 
 * @package Udoh\Emsa\Client
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class MasterProcessClient
{

    /** @var \SoapClient */
    protected $client;

    /**
     * Creates a new MasterProcessClient object.
     * 
     * @throws \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault
     */
    public function __construct()
    {
        try {
            $this->client = new \SoapClient(\MASTER_WSDL_PATH);
        } catch (\SoapFault $e) {
            throw new \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault($e->faultcode, 'Unable to connect to SOAP service:  ' . $e->getMessage());
        }
    }

    /**
     * Executes a call to saveMaster service to revalidate an EMSA message
     * 
     * @param \SimpleXMLElement $masterXml Master XML prepared for saveMaster() function
     * 
     * @return \SimpleXMLElement Master Process SOAP API results
     * 
     * @throws \Exception if <i>masterXml</i> is empty
     * @throws \SoapFault on SOAP errors
     */
    public function saveMaster(\SimpleXMLElement $masterXml = null)
    {
        if (empty($masterXml) || \EmsaUtils::emptyTrim($masterXml->asXML())) {
            throw new \Exception('Could not validate message:  Master XML missing');
        }

        $result = $this->client->saveMaster(array("healthMessage" => $masterXml->asXML()));

        $saveMasterReturn = simplexml_load_string($result->return);

        return $saveMasterReturn;
    }
    
    /**
     * Get a blank example TriSano XML document.
     * 
     * <i>Used as part of the Non-ELR Data Import process, which probably needs massive re-writes, if not wholesale removal.</i>
     * 
     * @param string $exampleXml
     * 
     * @return \SimpleXMLElement
     */
    public function getTrisanoExample($exampleXml = null)
    {
        $payload = array('healthMessage' => $exampleXml);
        
        $result = $this->client->getTrisanoExample($payload);
        
        $exampleReturn = simplexml_load_string($result->return);
        
        return $exampleReturn;
    }
    
    /**
     * Validates the response from Master Process and returns its status.
     * 
     * @param \SimpleXMLElement $responseXML XML response from Master Process
     * 
     * @return \Udoh\Emsa\Model\ClientResponse
     */
    public function validateResponse(\SimpleXMLElement $responseXML)
    {
        $masterProcessResponse = new \Udoh\Emsa\Model\ClientResponse();
        
        $statusBool = true;
        
        foreach ($responseXML->status_message as $statusObj) {
            if ((int) $statusObj->status === 100) {
                $statusBool = $statusBool && true;
            } else {
                $statusBool = $statusBool && false;
                $masterProcessResponse->addError((string) $statusObj->action, (string) $statusObj->status, (string) $statusBool->error_message);
            }
        }
        
        $masterProcessResponse->setStatus($statusBool);
        
        return $masterProcessResponse;
    }

}

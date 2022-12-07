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
 * TriSano event model.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class TrisanoRecord extends AppRecord
{
    public function getConditionId()
    {
        $conditionId = null;
        
        if (isset($this->appRecordDocument) && ($this->appRecordDocument instanceof \SimpleXMLElement)) {
            if (!empty($this->appRecordDocument->trisano_health->disease_events->disease_id)) {
                $conditionId = (int) $this->appRecordDocument->trisano_health->disease_events->disease_id;
            }
        }
        
        return $conditionId;
    }
    
    public function getConditionName()
    {
        $conditionName = null;
        $conditionId = $this->getConditionId();
        
        if (!empty($conditionId)) {
            $conditionName = \Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromId(new \Udoh\Emsa\Client\TrisanoSQLAClient(1), 'diseases', $conditionId);
        }
        
        return $conditionName;
    }

    public function getEventDate($formatted = false, $formatStr = \DateTime::RFC3339)
    {
        $onsetDateRaw = null;
        
        if (isset($this->appRecordDocument) && ($this->appRecordDocument instanceof \SimpleXMLElement)) {
            if (!empty($this->appRecordDocument->trisano_health->events->event_onset_date)) {
                $onsetDateRaw = (string) $this->appRecordDocument->trisano_health->events->event_onset_date;
            }
        }
        
        if (!empty($onsetDateRaw)) {
            return \Udoh\Emsa\Utils\DateTimeUtils::getDateFormatted(\Udoh\Emsa\Utils\DateTimeUtils::createMixed($onsetDateRaw), $formatted, $formatStr);
        } else {
            return null;
        }
    }

    public function getEventId()
    {
        $eventId = null;
        
        if (isset($this->appRecordDocument) && ($this->appRecordDocument instanceof \SimpleXMLElement)) {
            if (!empty($this->appRecordDocument->trisano_health->disease_events->event_id)) {
                $eventId = (int) $this->appRecordDocument->trisano_health->disease_events->event_id;
            }
        }
        
        return $eventId;
    }

    public function getRecordNumber()
    {
        $recordNumber = null;
        
        if (isset($this->appRecordDocument) && ($this->appRecordDocument instanceof \SimpleXMLElement)) {
            if (!empty($this->appRecordDocument->trisano_health->events->record_number)) {
                $recordNumber = (string) $this->appRecordDocument->trisano_health->events->record_number;
            }
        }
        
        return $recordNumber;
    }

    public function getRecordType()
    {
        if (isset($this->appRecordDocument) && ($this->appRecordDocument instanceof \SimpleXMLElement)) {
            if (!empty($this->appRecordDocument->trisano_health->events->type)) {
                if ((string) $this->appRecordDocument->trisano_health->events->type == 'MorbidityEvent') {
                    return \Udoh\Emsa\Constants\AppRecordType::MORBIDITY_EVENT;
                } elseif ((string) $this->appRecordDocument->trisano_health->events->type == 'ContactEvent') {
                    return \Udoh\Emsa\Constants\AppRecordType::CONTACT_EVENT;
                }
            }
        }
        
        return null;
    }

}

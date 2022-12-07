<?php

namespace Udoh\Emsa\Constants;

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
 * Constants for values in system_message_actions
 * 
 * @package Udoh\Emsa\Constants
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
final class SystemMessageActions
{

    /** Message contains duplicate lab results */
    const DUPLICATE_LAB_RESULTS = 7;

    /** Message Deleted */
    const MESSAGE_DELETED = 8;

    /** White */
    const WHITE = 9;

    /** Black */
    const BLACK = 10;

    /** Gray */
    const GRAY = 11;

    /** Pending */
    const PENDING = 12;

    /** Exception */
    const EXCEPTION = 13;

    /** Holding */
    const HOLDING = 14;

    /** Address Update */
    const ADDRESS_UPDATE = 16;

    /** Loinc Code Update */
    const LOINC_CODE_UPDATE = 17;

    /** Birth Date Update */
    const BIRTH_DATE_UPDATE = 18;

    /** Last Name Update */
    const LAST_NAME_UPDATE = 19;

    /** First Name Update */
    const FIRST_NAME_UPDATE = 20;

    /** Message Retried */
    const MESSAGE_RETRIED = 21;

    /** Message appended new Lab to existing CMR */
    const MESSAGE_ASSIGNED_UPDATED_CMR_ADDED_NEW_LAB = 22;

    /** Message generated new Person and CMR event */
    const MESSAGE_ASSIGNED_NEW_CMR_NEW_PERSON = 23;

    /** Message generated new CMR event for existing Person */
    const MESSAGE_ASSIGNED_NEW_CMR_EXISTING_PERSON = 24;

    /** Message moved by user */
    const MESSAGE_MOVED_BY_USER = 25;

    /** Message fixed from Tasks Manager */
    const MESSAGE_FIXED_TASK_MANAGER = 26;

    /** Message moved by automated rules */
    const MESSAGE_MOVED_BY_WHITELIST_RULE = 27;

    /** Message updated existing Lab for existing CMR */
    const MESSAGE_ASSIGNED_UPDATED_CMR_UPDATED_LAB = 28;

    /** Message appended new Lab Results to existing Lab */
    const MESSAGE_ASSIGNED_UPDATED_CMR_ADDED_RESULTS = 29;

    /** Invalid Params From Mirth */
    const INVALID_PARAMS_MIRTH = 30;

    /** Application XML Created */
    const NEDSS_XML_CREATED = 31;

    /** Message Flag Set */
    const MESSAGE_FLAG_SET = 32;

    /** Message Flag Cleared */
    const MESSAGE_FLAG_CLEARED = 33;

    /** Automatically processed via ELR */
    const MESSAGE_AUTOPROCESSED = 34;

    /** Attempted to create new Morbidity Event */
    const ATTEMPTED_NEW_CMR = 35;

    /** Message copy created */
    const MESSAGE_COPY_CREATED = 36;

    /** Graylist Request status set/changed */
    const GRAYLIST_REQUEST_STATUS_CHANGE = 37;

    /** Message processed by Graylist */
    const MESSAGE_PROCESSED_BY_GRAYLIST = 38;
    
    /** Message updated event with non-laboratory data */
    const MESSAGE_ASSIGNED_APPENDED_NON_LAB_DATA = 39;
    
    /** Attempted to update existing event */
    const ATTEMPTED_UPDATE_CMR = 40;

    /** Record lock encountered in target system */
    const LOCK_ENCOUNTERED = 41;

}

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
 * Constants for values in system_exceptions
 * 
 * @package Udoh\Emsa\Constants
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
final class SystemExceptions
{

    /** LOINC Code not added to ELR Manager */
    const LOINC_CODE_NOT_ADDED = 1;

    /** LOINC Code not found in the Message */
    const LOINC_CODE_NOT_FOUND_IN_MESSAGE = 2;

    /** Zip Code not Added to ELR Manager */
    const ZIP_CODE_NOT_ADDED = 3;

    /** SQL Agent Error */
    const SQLA_ERROR = 4;

    /** Message does not meet any criteria */
    const NO_CRITERIA_MET = 5;

    /** Lab and Event are the Same */
    const LAB_AND_EVENT_SAME = 7;

    /** Lab Missing Specimen */
    const LAB_MISSING_SPEICMEN = 8;

    /** Lab Missing Disease */
    const LAB_MISSING_DISEASE = 9;

    /** Lab Missing Organism */
    const LAB_MISSING_ORGANISM = 10;

    /** Lab Missing Test Type */
    const LAB_MISSING_TEST_TYPE = 12;

    /** LOINC Code Values Missing */
    const LOINC_CODE_VALUES_MISSING = 13;

    /** Test Result Value Should Be Set */
    const TEST_RESULT_VALUE_SHOULD_BE_SET = 14;

    /** Message Not A Lab Or An Imported Lab */
    const MESSAGE_NOT_A_LAB = 15;

    /** No Health Object Created */
    const NO_HEALTH_OBJECT_CREATED = 16;

    /** Message could not be transformed and mapped to XML for the target application */
    const MESSAGE_NOT_TRANSFORMED = 17;

    /** Bad Response From SQL Agent on CMR Import */
    const BAD_RESPONSE_FROM_SQLA_CMR_ON_IMPORT = 18;

    /** Bad Response from SQL Agent On Do Rules */
    const BAD_RESPONSE_FROM_SQLA_ON_RULES = 19;

    /** Could Not Create System Object */
    const COULD_NOT_CREATE_SYSTEM_OBJECT = 20;

    /** Rules were found but no LOINC Result Rules evaluated to true */
    const NO_LOINC_RULES_EVALUATED_TRUE = 21;

    /** Failed to Add Or Update Event SQL Error */
    const FAILED_TO_ADD_OR_UPDATE_SQL_ERROR = 22;

    /** Local Result Value Not Mapped For Master/ Local Code */
    const LOCAL_RESULT_VALUE_NOT_MAPPED = 23;

    /** Birthday is Invalid */
    const BIRTHDAY_INVALID = 24;

    /** Specimen Required But Not Found */
    const SPECIMEN_REQUIRED_NOT_FOUND = 29;

    /** Missing Required Field */
    const MISSING_REQUIRED_FIELD = 31;

    /** Invalid Value For Data Type */
    const INVALID_VALUE_FOR_DATA_TYPE = 32;

    /** Validation Rule Failure */
    const VALIDATION_RULE_FAILURE = 33;

    /** Mirth To Master Mappings Not Found */
    const MIRTH_TO_MASTER_MAPPINGS_NOT_FOUND = 34;

    /** Master XML To Application XML Mappings Not Found */
    const MASTER_TO_APPLICATION_MAPPINGS_NOT_FOUND = 35;

    /** Missing Mirth Param Original Message Id */
    const MISSING_MIRTH_PARAM_ORIGINAL_ID = 36;

    /** Missing Mirth Param HL7XML */
    const MISSING_MIRTH_PARAM_HL7XML = 37;

    /** Missing Mirth Param Lab Name */
    const MISSING_MIRTH_PARAM_LAB_NAME = 38;

    /** Missing Mirth Param Version */
    const MISSING_MIRTH_PARAM_VERSION = 39;

    /** Lab Id Not Found For Lab Name */
    const LAB_ID_NOT_FOUND_FOR_LAB_NAME = 40;

    /** Original Message Id Not A Number */
    const ORIGINAL_ID_NOT_NUMERIC = 41;

    /** Unable To Transform Mirth XML to Master Doc */
    const UNABLE_TO_TRANSFORM_MIRTH_XML = 42;

    /** Unable To Convert Master Doc to XML */
    const UNABLE_TO_CONVERT_MASTER_DOCUMENT = 43;

    /** Unable to Obtain Empty Application XML Document */
    const UNABLE_TO_OBTAIN_EMPTY_APPLICATION_DOCUMENT = 44;

    /** Unable to Add System Message */
    const UNABLE_TO_ADD_SYSTEM_MESSAGE = 45;

    /** Error Accessing Path In Master Doc */
    const ERROR_ACCESSING_PATH_MASTER_DOCUMENT = 46;

    /** Error Accessing Path In Application XML Doc */
    const ERROR_ACCESSING_PATH_APPLICATION_DOCUMENT = 47;

    /** Error Setting Value In Application XML Doc */
    const ERROR_SETTING_VALUE_APPLICATION_DOCUMENT = 48;

    /** Unable To Find Application Coded Value */
    const UNABLE_TO_FIND_APPLICATION_CODE = 49;

    /** Unable To Convert Application XML Doc To XML */
    const UNABLE_TO_CONVERT_APPLICATION_DOCUMENT = 50;

    /** Error Finding App Code With Local LOINC */
    const ERROR_FINDING_APPCODE_WITH_LOCAL_LOINC = 51;

    /** No App Code Found With Local LOINC */
    const NO_APPCODE_FOUND_WITH_LOCAL_LOINC = 52;

    /** Coded Value ID Lookup Failed */
    const APPLICATION_CODED_ID_LOOKUP_FAILED = 53;

    /** Unable to Obtain Date Message Received */
    const UNABLE_TO_OBTAIN_DATE_RECEIVED = 54;

    /** Unable to Set Date Message Received In Doc */
    const UNABLE_TO_SET_DATE_RECEIVED = 55;

    /** Unable to Evaluate Rule */
    const UNABLE_TO_EVALUATE_RULE = 56;

    /** Unable to Look Up Disease Name */
    const UNABLE_TO_LOOK_UP_DISEASE = 57;

    /** Unable to Look Up Organism */
    const UNABLE_TO_LOOK_UP_ORGANISM = 58;

    /** Unable to Set Date in Master Doc */
    const UNABLE_TO_SET_DATE_IN_MASTER = 59;

    /** Unable to Set Master LOINC in Master Doc */
    const UNABLE_TO_SET_MASTER_LOINC_IN_MASTER = 60;

    /** No Reference Range Found In Master Doc */
    const NO_REFERENCE_RANGE_FOUND_IN_MASTER = 61;

    /** ID Needed for Coded Value, None Found */
    const APPLICATION_CODED_ID_NEEDED_NONE_FOUND = 62;

    /** No Test Type Found For Master LOINC */
    const NO_TEST_TYPE_FOUND_FOR_MASTER_LOINC = 63;

    /** No Disease Name Found For Local LOINC */
    const NO_DISEASE_NAME_FOUND_FOR_LOCAL_LOINC = 64;

    /** No Test Result Rules Found For Local LOINC */
    const NO_TEST_RESULT_RULES_FOUND_FOR_LOCAL_LOINC = 65;

    /** Unable to Assign Application XML */
    const UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION = 66;

    /** Specimen not mapped */
    const SPECIMEN_NOT_MAPPED = 67;

    /** Suspect patient Last Name changed due to marriage */
    const PATIENT_LAST_NAME_CHANGED_MARRIAGE = 68;

    /** Entry Queue Exception */
    const ENTRY_QUEUE_EXCEPTION = 69;

    /** Whitelist Rule Exception */
    const WHITELIST_RULE_EXCEPTION = 70;

    /** Unexpected value in local result value */
    const UNEXPECTED_VALUE_IN_LOCAL_RESULT = 71;

    /** No Case Management Rules Found For Master LOINC */
    const NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_LOINC = 72;

    /** No Case Management Rules Evaluated True */
    const NO_CASE_MANAGEMENT_RULES_TRUE = 73;

    /** No Master Vocab Id Found For Local Result Value */
    const NO_MASTER_VOCAB_ID_FOUND_FOR_LOCAL_RESULT_VALUE = 74;

    /** No Case Management Rules Found For Master SNOMED */
    const NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_SNOMED = 75;
    
    /** No Case Management Rules Found For Diagnostic Code */
    const NO_CASE_MANAGEMENT_RULES_FOUND_ICD_CODE = 76;

}

<?php
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
 * @var int $serverEnvironment Used to define the server environment (one of <b>ELR_ENV_TEST</b> or <b>ELR_ENV_PROD</b>).
 */
$serverEnvironment = ELR_ENV_TEST;

define('ORGANIZATION_NAME_SHORT', 'Kcirtap Department of Health');  // install
define('ORGANIZATION_NAME_LONG', 'Kcirtap Department of Health - Division of Disease Control & Prevention');  // install
define('EXPORT_SERVERNAME', 'DEV');
define('LOGOUT_URL', '/');  // install - set to relative URL of your logout page, if desired
define('MAIN_URL', '/elrapp');

define('ELR_NOTIFY_ROLE_NAME', 'ELR-Notification');  // install - set to name of 'ELR Notification' role in your EHR

define('EMSA_FLAG_INVESTIGATION_COMPLETE', 2);
define('EMSA_FLAG_CLEAN_DATA', 4);
define('EMSA_FLAG_QA_MANDATORY', 8);
define('EMSA_FLAG_QA_CODING', 16);
define('EMSA_FLAG_QA_MQF', 32);
define('EMSA_FLAG_DE_ERROR', 64);
define('EMSA_FLAG_FIX_DUPLICATE', 128);
define('EMSA_FLAG_DE_OTHER', 256);
define('EMSA_FLAG_DE_NEEDFIX', 512);

// turn automated entry on or off
// true = ON; false = OFF
define('AUTOMATION_ENABLED', false);

// allow or disallow bulk exceptions retry via Mirth Connect integration
// if not using Mirth Connect/NextGen Connect, leave off
define('BULK_RETRY_VIA_MIRTH', false);

/**
 * Determines whether attempting to add a new CMR to multiple existing people in the target application
 * will use an algorithm for determining which person to add the new event to (if set to TRUE), or trigger the message
 * to be sent to the 'Pending' queue for manual resolution (if set to FALSE).
 */
define('MULTIPERSON_ADDCMR_PENDING_BYPASS', false);

define('DB_SSLMODE', 'prefer');  // install - Set default SSL mode used to connect to the database.  Supports 'disable', 'allow', 'prefer', or 'require'.  See https://www.postgresql.org/docs/current/libpq-connect.html for more information.

$emsaDbHost = 'emsa-db.default.svc.cluster.local';  // install - set to host/IP address of your database server running the EMSA database
$emsaDbPort = '5432';
$emsaDbName = 'dcp';
$emsaDbUser = 'dcpadmin';
$emsaDbPass = 'droolspass';  // install - set a unique password for $my_db_username
$emsaDbSchemaPrefix = 'elr.';
$emsaDbSchemaPDO = 'elr';

// $replicationDbHost = 'emsa-db.default.svc.cluster.local';  // install - set to host/IP address of your database replication server for the EMSA database
// $replicationDbPort = '5433';

define('MIRTH_PATH', 'http://mirthhost:mirthport/services/Mirth?wsdl');  // install - set host/port for Mirth Connect service
define('WSDL_PATH', 'http://sqlahost:8080/sqla/SqlAgentService?wsdl');  // install - set host for Wildfly server running SQLA
define('MASTER_WSDL_PATH', 'http://mphost:8080/mp/MasterWebService?WSDL');  // install - set host for Wildfly server running Master Process
define('UTNEDS_URL', 'https://nedsshost/trisano/cmrs/');  // install - set TriSano server hostname
define('BASE_NEDSS_URL', 'https://nedsshost/trisano/');  // install - set TriSano server hostname
define('BASE_EPITRAX_URL', 'https://epitraxhost/nedss/');  // install - set EpiTrax server hostname
define('EPITRAX_REST_SERVICE_URL', '/nedss/admin/rest/RestEJB/');
define('EPITRAX_GEO_SERVICE_ENDPOINT', '/nedss/geo/find');

define('EPITRAX_AUTH_HEADER', '1');  // install - set to header used to pass user ID for authentication
define('EPITRAX_AUTH_ELR_UID', '1');  // install - set to user ID corresponding to ELR user in EpiTrax

define('SMTP_HOST', 'mail.test.info');  // install - set SMTP host/IP
define('SMTP_PORT', 1025);  // install - change if needed
define('SMTP_HELO', 'smtphelo.example.com');  // install - set SMTP HELO domain

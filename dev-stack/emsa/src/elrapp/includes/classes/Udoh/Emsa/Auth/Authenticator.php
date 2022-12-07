<?php

namespace Udoh\Emsa\Auth;

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

use PDO;
use Throwable;
use Udoh\Emsa\Client\AppClientInterface;

/**
 * Authentication class for EMSA application
 * 
 * @package Udoh\Emsa\Auth
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Authenticator
{

    private $dbConn;
    /** @var AppClientInterface */
    private $authClient;
    private $serverEnvironment;
    private $hasAdminRole = false;
    private $hasQaRole = false;

    const URIGHTS_DASHBOARD = 1;
    const URIGHTS_PENDING = 2;
    const URIGHTS_ENTRY = 3;
    const URIGHTS_SEMI_AUTO = 25;
    const URIGHTS_ASSIGNED = 4;
    const URIGHTS_GRAY = 5;
    const URIGHTS_ADMIN = 6;
    const URIGHTS_NEDSS_EXCEPTION = 26;
    const URIGHTS_OOS = 27;
    const URIGHTS_TAB_FULL = 17;
    const URIGHTS_TAB_AUDIT = 18;
    const URIGHTS_TAB_ERROR = 19;
    const URIGHTS_TAB_HL7 = 20;
    const URIGHTS_TAB_XML = 21;
    const URIGHTS_TAB_QA = 24;
    const URIGHTS_ACTION_MOVE = 22;
    const URIGHTS_ACTION_DELETE = 23;
    const URIGHTS_ACTION_EXPORTEMSA = 24;
    const ROLE_TYPE_ADMIN = 1;
    const ROLE_TYPE_QA = 2;
    const ROLE_TYPE_DATA_ENTRY = 3;
    const MENU_TYPE_DISABLED = 0;
    const MENU_TYPE_MENU = 1;
    const MENU_TYPE_FEATURE = 2;

    /**
     * Create a new EMSA Authenticator instance
     *
     * @param PDO                $dbConn            EMSA database handle
     * @param AppClientInterface $authClient        App Client for application to be used for authentication
     * @param int                $serverEnvironment EMSA server environment
     */
    public function __construct(PDO $dbConn, AppClientInterface $authClient, $serverEnvironment)
    {
        $this->dbConn = $dbConn;
        $this->authClient = $authClient;
        
        $cleanServerEnvironment = (int) filter_var($serverEnvironment, \FILTER_SANITIZE_NUMBER_INT);

        switch ($cleanServerEnvironment) {
            case ELR_ENV_DEV:
            case ELR_ENV_PROD:
            case ELR_ENV_TEST:
                $this->serverEnvironment = $cleanServerEnvironment;
                break;
            default:
                $this->serverEnvironment = null;
                break;
        }
    }

    /**
     * Main authentication routine
     * 
     * @param \Udoh\Emsa\Client\AppClientList $appClientList Configured Application clients.
     * @param bool $ignorePermission [Optional]<br>If <b>TRUE</b>, skips authentication and allows access.  Only used
     *                               for some non-sensitive automated scripts.  Default <b>FALSE</b>.
     */
    public function authenticate(\Udoh\Emsa\Client\AppClientList $appClientList, $ignorePermission = false)
    {
        $reloadCachedTables = false;
        
        if (empty($_SESSION) || empty($_SESSION[EXPORT_SERVERNAME])) {
            $this->logOff();
        }

        if ((int) filter_input(INPUT_GET, 'logoff', FILTER_SANITIZE_NUMBER_INT) === 1) {
            $this->logOff();
        }
        
        if ((int) filter_input(INPUT_GET, 'updateperm', FILTER_SANITIZE_NUMBER_INT) === 1) {
            $this->clearPermissions();
        }

        if (!isset($_SESSION[EXPORT_SERVERNAME]['jurisdictions']) || !isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->authClient->getAppName()]) || !isset($_SESSION[EXPORT_SERVERNAME]['user_system_roles'])) {
            $this->clearPermissions(); // if user for some reason doesn't have these set, reset the permissions
        }

        if ((!isset($_SESSION[EXPORT_SERVERNAME]['user_roles']) || (count($_SESSION[EXPORT_SERVERNAME]['user_roles']) == 0)) && isset($_SESSION[EXPORT_SERVERNAME]['umdid'])) {
            $reloadCachedTables = $this->setPermissions($_SESSION[EXPORT_SERVERNAME]['umdid']); // set user permissions
        }

        if (!self::userHasPermission()) {
            if (!$ignorePermission) {
                $this->noAccess();
            }
        }
        
        // if we just reset permissions, load code tables from authenticating app into memory
        if ($reloadCachedTables) {
            /** @var AppClientInterface $configuredAppClient */
            foreach ($appClientList->getClients() as $configuredAppClient) {
                foreach ($configuredAppClient->getCodedDataTables() as $dumpTableName) {
                    $this->dumpTable($configuredAppClient, $dumpTableName);
                }
            }
        }
    }

    /**
     * Indicates whether the authenticated user has Admin (superuser) privileges
     * 
     * @return bool
     */
    private function isAdmin()
    {
        return $this->hasAdminRole;
    }

    /**
     * Indicates whether the authenticated user has QA privileges
     * 
     * @return bool
     */
    private function isQa()
    {
        return $this->hasQaRole;
    }

    /**
     * Log off current user, forcing them to re-authenticate
     */
    private function logOff()
    {
        $_SESSION[EXPORT_SERVERNAME] = array();
        ob_end_clean();
        //header('Location: ' . LOGOUT_URL);
        header('Location: ' . MAIN_URL . '/');
        exit;
    }

    /**
     * Displays the 'No Access' intercept page
     */
    private function noAccess()
    {
        $_SESSION[EXPORT_SERVERNAME] = array();
        ob_end_clean();
        \Udoh\Emsa\Utils\DisplayUtils::drawHeader($this->dbConn, $this->serverEnvironment);
        echo '<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrerrorbig"></span>No Access</h1>';
        echo '<h2>Sorry, you do not have access to this page.</h2>';
        \Udoh\Emsa\Utils\DisplayUtils::drawFooter();
        exit;
    }

    /**
     * Reset current user's permission (re-query from NEDSS) while maintaining current session (user remains logged in)
     */
    private function clearPermissions()
    {
        $_SESSION[EXPORT_SERVERNAME]['user_roles'] = array();
        $_SESSION[EXPORT_SERVERNAME]['user_system_roles'] = array();
        $_SESSION[EXPORT_SERVERNAME]['codedData'] = array();
        $_SESSION[EXPORT_SERVERNAME]['jurisdictions'] = array();
        $_SESSION[EXPORT_SERVERNAME]['user_role_menus'] = array();
    }

    /**
     * Set permissions for the authenticated user
     * 
     * @param string $userId Current user's UMDID
     * 
     * @return boolean
     */
    private function setPermissions($userId = null)
    {
        if (\EmsaUtils::emptyTrim($userId)) {
            return false; // short-circuit if empty ID passed
        }
        
        // ensure session data is cleared out
        $_SESSION[EXPORT_SERVERNAME]['user_role_menus'] = array();
        $_SESSION[EXPORT_SERVERNAME]['user_roles'] = array();
        $_SESSION[EXPORT_SERVERNAME]['user_system_roles'] = array();
        $_SESSION[EXPORT_SERVERNAME]['jurisdictions'] = array();
        $_SESSION[EXPORT_SERVERNAME]['codedData'] = array();

        $_SESSION[EXPORT_SERVERNAME]['is_admin'] = false;
        $_SESSION[EXPORT_SERVERNAME]['is_qa'] = false;

        // load jurisdictions into session
        $this->getJurisdictions();

        // get app role IDs for UMDID, load into $_SESSION[EXPORT_SERVERNAME]['user_roles']
        try {
            $userRoleList = $this->authClient->getUserRoles($userId);
            foreach ($userRoleList as $userRoleId => $userRoleName) {
                if (!in_array((int) $userRoleId, $_SESSION[EXPORT_SERVERNAME]['user_roles'])) {
                    array_push($_SESSION[EXPORT_SERVERNAME]['user_roles'], $userRoleId);
                }
            }
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            \Udoh\Emsa\Utils\DisplayUtils::drawError('An unexpected error occurred while attempting to log in:<br>' . $e->getMessage(), true);
            return false;
        }

        // get EMSA role IDs corresponding to authenticating App role IDs, load into $_SESSION[EXPORT_SERVERNAME]['user_system_roles']
        $this->setEMSARolesByAppRoleIds($_SESSION[EXPORT_SERVERNAME]['user_roles']);

        // get user menus for EMSA roles specified in $_SESSION[EXPORT_SERVERNAME]['user_system_roles'], load into $_SESSION[EXPORT_SERVERNAME]['user_role_menus']
        $this->setUserMenusByEMSARoleIds($_SESSION[EXPORT_SERVERNAME]['user_system_roles'], false);

        return true;
    }

    /**
     * Temporarily sets permissions for the user to be based on one of user's selected EMSA roles, rather than the sum
     * of all assigned EMSA roles.
     * @param int $emsaRoleId ID of EMSA role to temporarily use
     */
    public function setOverridePermission($emsaRoleId)
    {
        if ((intval($emsaRoleId) > 0) && isset($_SESSION[EXPORT_SERVERNAME]['user_system_roles']) && is_array($_SESSION[EXPORT_SERVERNAME]['user_system_roles']) && in_array(intval($emsaRoleId), $_SESSION[EXPORT_SERVERNAME]['user_system_roles'])) {
            // role ID passed is a valid role for this user
            $_SESSION[EXPORT_SERVERNAME]['override_user_role'] = intval($emsaRoleId);
            $_SESSION[EXPORT_SERVERNAME]['override_is_admin'] = false;
            $_SESSION[EXPORT_SERVERNAME]['override_is_qa'] = false;
            $_SESSION[EXPORT_SERVERNAME]['override_user_role_menus'] = array();

            // get admin & qa params for $emsaRoleId, store in $_SESSION[EXPORT_SERVERNAME]['override_is_admin'] & $_SESSION[EXPORT_SERVERNAME]['override_is_qa']
            $sql = "SELECT role_type
					FROM auth_roles
					WHERE id = :roleId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':roleId', intval($emsaRoleId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $roleType = intval($stmt->fetchColumn(0));
                if ($roleType === self::ROLE_TYPE_ADMIN) {
                    $_SESSION[EXPORT_SERVERNAME]['override_is_admin'] = true;
                    $_SESSION[EXPORT_SERVERNAME]['override_is_qa'] = false;
                } elseif ($roleType === self::ROLE_TYPE_QA) {
                    $_SESSION[EXPORT_SERVERNAME]['override_is_admin'] = false;
                    $_SESSION[EXPORT_SERVERNAME]['override_is_qa'] = true;
                } else {
                    $_SESSION[EXPORT_SERVERNAME]['override_is_admin'] = false;
                    $_SESSION[EXPORT_SERVERNAME]['override_is_qa'] = false;
                }
            }

            // get menus for $emsaRoleId, store in $_SESSION[EXPORT_SERVERNAME]['override_user_role_menus']
            $this->setUserMenusByEMSARoleIds(array(intval($emsaRoleId)), true);
        } else {
            // clear override session data
            unset($_SESSION[EXPORT_SERVERNAME]['override_user_role']);
            unset($_SESSION[EXPORT_SERVERNAME]['override_user_role_menus']);
            unset($_SESSION[EXPORT_SERVERNAME]['override_is_admin']);
            unset($_SESSION[EXPORT_SERVERNAME]['override_is_qa']);
        }
    }

    /**
     * Check to see if current user has valid permissions
     * 
     * @param int $feature [Optional]<br>Specific feature to check permission against (one of <i>system_menus.id</i>),
     *                     e.g. a menu option or action bar button
     * @return bool
     */
    public static function userHasPermission($feature = null)
    {
        $userMenusArray = ((!isset($_SESSION[EXPORT_SERVERNAME]['override_user_role_menus']) || \EmsaUtils::emptyTrim($_SESSION[EXPORT_SERVERNAME]['override_user_role_menus'])) ? $_SESSION[EXPORT_SERVERNAME]['user_role_menus'] : $_SESSION[EXPORT_SERVERNAME]['override_user_role_menus']);

        if (count($userMenusArray) > 0) {
            if (is_null($feature) || empty($feature)) {
                // no menu specified, check permissions for currently-selected page
                // 'home'/'dashboard' don't have a 'selected_page' specified in querystring, so if not set, default to 1
                $testPage = ((isset($_GET['selected_page'])) ? intval(trim($_GET['selected_page'])) : 1);
            } else {
                // specific menu passed to check
                $testPage = ((filter_var($feature, FILTER_VALIDATE_INT)) ? filter_var($feature, FILTER_SANITIZE_NUMBER_INT) : -1);
            }

            if (filter_var($testPage, FILTER_VALIDATE_INT) && $testPage > 0) {
                // verify user has access to this menu
                if (in_array($testPage, $userMenusArray)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Find the EMSA roles the current user is assigned to, based upon their assigned roles from the authenticatng
     * Application.
     * 
     * @param array $appRoleIds List of current user's assigned application-specific role IDs
     */
    private function setEMSARolesByAppRoleIds(array $appRoleIds = array())
    {
        if (count($appRoleIds) > 0) {
            $sql = "SELECT DISTINCT r.id AS auth_role_id, r.role_type AS auth_role_type
					FROM auth_roles r
                    INNER JOIN auth_app_roles a ON (a.auth_role_id = r.id AND a.app_id = :appId)
					WHERE a.app_role_id IN (" . implode(',', array_map('intval', $appRoleIds)) . ");";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':appId', $this->authClient->getAppId(), PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetchObject()) {
                if ((int) $row->auth_role_type === self::ROLE_TYPE_ADMIN) {
                    $this->hasAdminRole = $this->hasAdminRole || true;
                } elseif ((int) $row->auth_role_type === self::ROLE_TYPE_QA) {
                    $this->hasQaRole = $this->hasQaRole || true;
                }

                $_SESSION[EXPORT_SERVERNAME]['user_system_roles'][] = (int) $row->auth_role_id;
            }

            if ($this->isAdmin()) {
                $_SESSION[EXPORT_SERVERNAME]['is_admin'] = true;
            }

            if ($this->isQa()) {
                $_SESSION[EXPORT_SERVERNAME]['is_qa'] = true;
            }
        }
    }

    /**
     * Get assigned menus for current user
     * 
     * @param array $emsaRoleIds Collection of user's EMSA role IDs
     * @param bool $isOverrideMode [Optional]If <b>TRUE</b>, getting menus for temporarily-overridden user role.
     *                             Default <b>FALSE</b>.
     */
    private function setUserMenusByEMSARoleIds(array $emsaRoleIds = array(), $isOverrideMode = false)
    {
        if (\EmsaUtils::emptyTrim($isOverrideMode)) {
            $isOverrideMode = false;
        }

        if (count($emsaRoleIds) > 0) {
            $sql = "SELECT DISTINCT menu_id
					FROM auth_menus
					WHERE role_id IN (" . implode(',', array_map('intval', $emsaRoleIds)) . ");";
            foreach ($this->dbConn->query($sql, PDO::FETCH_OBJ) as $emsaMenu) {
                if ($isOverrideMode) {
                    $_SESSION[EXPORT_SERVERNAME]['override_user_role_menus'][] = intval($emsaMenu->menu_id);
                } else {
                    $_SESSION[EXPORT_SERVERNAME]['user_role_menus'][] = intval($emsaMenu->menu_id);
                }
            }
        }
    }

    /**
     * Dump a code table from the specified Application into session.
     * 
     * If <i>tableName</i> is "external_codes", individual code categories within "external_codes"<br>
     * are dumped into $_SESSION[EXPORT_SERVERNAME]['codedData'][<i>appName</i>][<i>category</i>], otherwise<br>
     * into $_SESSION[EXPORT_SERVERNAME]['codedData'][<i>appName</i>][<i>tableName</i>].<br><br>
     * Elements are of the format <i>codeId</i> => (<i>codedValue</i>, <i>codeDescription</i>).
     * 
     * @param AppClientInterface $appClient Application-specific client
     * @param string             $tableName Name of the code table to dump
     */
    private function dumpTable(AppClientInterface $appClient, $tableName)
    {
        $cleanTableName = htmlspecialchars($tableName);

        try {
            $codedDataResults = $appClient->getCodedData($cleanTableName);
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to obtain ' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($appClient->getAppName()) . ' Codes:  Could not query codes table.<br>' . $e->getMessage(), true);
        }
        
        if (!isset($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()]) || is_null($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()]) || !is_array($_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()])) {
            $_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()] = array();
        }
        
        /* @var $codedDataResultItem \Udoh\Emsa\Model\CodedDataResult */
        foreach ($codedDataResults as $codedDataResultItem) {
            foreach ($codedDataResultItem as $codedDataResultItemId => $codedDataResultItemData) {
                $_SESSION[EXPORT_SERVERNAME]['codedData'][$appClient->getAppName()][$codedDataResultItem->getCodesetName()][$codedDataResultItemId] = array(
                    'codedValue' => $codedDataResultItemData['codedValue'], 
                    'codeDescription' => $codedDataResultItemData['codeDescription']
                );
            }
        }
    }

    private function getJurisdictions()
    {
        $sql = "SELECT aj.app_id AS app_id, sd.health_district AS health_district, aj.app_jurisdiction_id AS app_jurisdiction_id
				FROM app_jurisdictions aj
                INNER JOIN system_districts sd ON (aj.system_district_id = sd.id)
				WHERE sd.enabled IS TRUE
				ORDER BY aj.app_id, sd.health_district;";
        
        foreach ($this->dbConn->query($sql, PDO::FETCH_OBJ) as $emsaJurisdiction) {
            $_SESSION[EXPORT_SERVERNAME]['jurisdictions'][(int) $emsaJurisdiction->app_id][(int) $emsaJurisdiction->app_jurisdiction_id] = trim($emsaJurisdiction->health_district);
        }
    }

}

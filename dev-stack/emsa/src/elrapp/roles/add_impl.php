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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$roleEditAction = null;
$cleanRoleEditAction = (string) filter_input(\INPUT_POST, 'action', \FILTER_SANITIZE_STRING);

switch (strtolower(trim($cleanRoleEditAction))) {
    case "add":
    case "edit":
        $roleEditAction = strtolower(trim($cleanRoleEditAction));
        break;
    default:
        $roleEditAction = null;
}

if (!is_null($roleEditAction)) {
    $emsaRoleId = (int) filter_input(\INPUT_POST, 'emsa_role_id', \FILTER_SANITIZE_NUMBER_INT);
    $newRoleType = (int) filter_input(\INPUT_POST, 'role_type', \FILTER_SANITIZE_NUMBER_INT);
    $newRoleName = trim(filter_input(\INPUT_POST, 'role_name', \FILTER_SANITIZE_STRING));
    
    $newConditions = ((isset($_POST['conditions']) && is_array($_POST['conditions'])) ? $_POST['conditions'] : array());
    $newMenus = ((isset($_POST['menus']) && is_array($_POST['menus'])) ? $_POST['menus'] : array());
    $newFeatures = ((isset($_POST['features']) && is_array($_POST['features'])) ? $_POST['features'] : array());
    $newMenuFeatures = array_merge($newMenus, $newFeatures);
    
    $appRoleIds = array();
    foreach ($appClientList->getClients() as $addUpdateClient) {
        $appRoleId = (int) filter_input(\INPUT_POST, 'app_' . $addUpdateClient->getAppId() . '_role_id', \FILTER_SANITIZE_NUMBER_INT);
        
        if ($appRoleId > 0) {
            $appRoleIds[$addUpdateClient->getAppId()] = $appRoleId;
        }
    }
}

if ($roleEditAction == "add") {
    if (\RoleUtils::isValidRoleName($adminDbConn, $newRoleName)) {
        $addRoleSql = "INSERT INTO auth_roles (name, role_type)
                       VALUES (:roleName, :roleType)
                       RETURNING id;";
        $addRoleStmt = $adminDbConn->prepare($addRoleSql);
        $addRoleStmt->bindValue(':roleName', $newRoleName, PDO::PARAM_STR);
        $addRoleStmt->bindValue(':roleType', $newRoleType, PDO::PARAM_INT);

        if ($addRoleStmt->execute()) {
            $emsaRoleId = (int) filter_var($addRoleStmt->fetchColumn(0), \FILTER_SANITIZE_NUMBER_INT);
        }

        if ($emsaRoleId > 0) {
            $addAppSql = "INSERT INTO auth_app_roles (auth_role_id, app_id, app_role_id)
                          VALUES (?, ?, ?);";
            $addAppStmt = $adminDbConn->prepare($addAppSql);

            foreach ($appClientList->getClients() as $addAppClient) {
                // check to see if posted field exists for appId & insert it
                $addAppStmt->execute(array((int) $emsaRoleId, (int) $addAppClient->getAppId(), (int) $appRoleIds[$addAppClient->getAppId()]));
            }

            // insert conditions for new role
            if (count($newConditions) > 0) {
                $addConditionSql = "INSERT INTO auth_conditions (role_id, condition) 
                                    VALUES (:roleId, :conditionName);";
                $addConditionStmt = $adminDbConn->prepare($addConditionSql);
                $adminDbConn->beginTransaction();
                try {
                    foreach ($newConditions as $newCondition) {
                        $addConditionStmt->bindValue(':roleId', $emsaRoleId, PDO::PARAM_INT);
                        $addConditionStmt->bindValue(':conditionName', $newCondition, PDO::PARAM_STR);
                        $addConditionStmt->execute();
                    }
                    $adminDbConn->commit();
                } catch (PDOException $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $adminDbConn->rollBack();
                }
            }

            // insert menus/features for new role
            if (count($newMenuFeatures) > 0) {
                $addMenuFeatureSql = "INSERT INTO auth_menus (role_id, menu_id) 
                                      VALUES (:roleId, :menuId);";
                $addMenuFeatureStmt = $adminDbConn->prepare($addMenuFeatureSql);
                $adminDbConn->beginTransaction();
                try {
                    foreach ($newMenuFeatures as $newMenuFeature) {
                        $addMenuFeatureStmt->bindValue(':roleId', $emsaRoleId, PDO::PARAM_INT);
                        $addMenuFeatureStmt->bindValue(':menuId', $newMenuFeature, PDO::PARAM_STR);
                        $addMenuFeatureStmt->execute();
                    }
                    $adminDbConn->commit();
                } catch (PDOException $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $adminDbConn->rollBack();
                }
            }

            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New role '" . htmlentities($newRoleName, ENT_QUOTES, "UTF-8") . "' successfully added!", "ui-icon-elrsuccess");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new role.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new role!  Role name must contain at least 3 characters and be unique.");
    }
}

if ($roleEditAction == "edit") {
    if (\RoleUtils::isValidRoleName($adminDbConn, $newRoleName, $emsaRoleId)) {
        $updateRoleSql = "UPDATE auth_roles
                          SET name = :roleName, role_type = :roleType
                          WHERE id = :emsaRoleId;";
        $updateRoleStmt = $adminDbConn->prepare($updateRoleSql);
        $updateRoleStmt->bindValue(':roleName', $newRoleName, PDO::PARAM_STR);
        $updateRoleStmt->bindValue(':roleType', $newRoleType, PDO::PARAM_INT);
        $updateRoleStmt->bindValue(':emsaRoleId', $emsaRoleId, PDO::PARAM_INT);

        if ($updateRoleStmt->execute()) {
            // delete old app-specific role mappings...
            $deleteAppRoleSql = "DELETE FROM ONLY auth_app_roles
                                 WHERE auth_role_id = ?;";
            $deleteAppRoleStmt = $adminDbConn->prepare($deleteAppRoleSql);
            $deleteAppRoleStmt->execute(array($emsaRoleId));
            
            $updateAppRoleSql = "INSERT INTO auth_app_roles (app_role_id, auth_role_id, app_id)
                                 VALUES (?, ?, ?);";
            $updateAppRoleStmt = $adminDbConn->prepare($updateAppRoleSql);
            
            foreach ($appClientList->getClients() as $updateAppRoleClient) {
                if (isset($appRoleIds[(int) $updateAppRoleClient->getAppId()])) {
                    $updateAppRoleStmt->execute(array((int) $appRoleIds[(int) $updateAppRoleClient->getAppId()], $emsaRoleId, (int) $updateAppRoleClient->getAppId()));
                }
            }
            
            // remove all old conditions for this role first...
            $deleteOldConditionSql = "DELETE FROM auth_conditions
					WHERE role_id = :emsaRoleId;";
            $deleteOldConditionStmt = $adminDbConn->prepare($deleteOldConditionSql);
            $deleteOldConditionStmt->bindValue(':emsaRoleId', $emsaRoleId, PDO::PARAM_INT);
            $deleteOldConditionStmt->execute();

            if (count($newConditions) > 0) {
                $addConditionSql = "INSERT INTO auth_conditions
						(role_id, condition) VALUES (:roleId, :conditionName);";
                $addConditionStmt = $adminDbConn->prepare($addConditionSql);
                $adminDbConn->beginTransaction();
                try {
                    foreach ($newConditions as $newCondition) {
                        $addConditionStmt->bindValue(':roleId', $emsaRoleId, PDO::PARAM_INT);
                        $addConditionStmt->bindValue(':conditionName', $newCondition, PDO::PARAM_STR);
                        $addConditionStmt->execute();
                    }
                    $adminDbConn->commit();
                } catch (PDOException $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $adminDbConn->rollBack();
                }
            }

            // remove all old menus for this role first...
            $deleteOldMenuSql = "DELETE FROM auth_menus
					WHERE role_id = :emsaRoleId;";
            $deleteOldMenuStmt = $adminDbConn->prepare($deleteOldMenuSql);
            $deleteOldMenuStmt->bindValue(':emsaRoleId', $emsaRoleId, PDO::PARAM_INT);
            $deleteOldMenuStmt->execute();

            if (count($newMenuFeatures) > 0) {
                $addMenuFeatureSql = "INSERT INTO auth_menus
						(role_id, menu_id) VALUES (:roleId, :menuId);";
                $addMenuFeatureStmt = $adminDbConn->prepare($addMenuFeatureSql);
                $adminDbConn->beginTransaction();
                try {
                    foreach ($newMenuFeatures as $newMenuFeature) {
                        $addMenuFeatureStmt->bindValue(':roleId', $emsaRoleId, PDO::PARAM_INT);
                        $addMenuFeatureStmt->bindValue(':menuId', $newMenuFeature, PDO::PARAM_STR);
                        $addMenuFeatureStmt->execute();
                    }
                    $adminDbConn->commit();
                } catch (PDOException $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $adminDbConn->rollBack();
                }
            }

            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Changes to role '" . htmlentities($newRoleName, ENT_QUOTES, "UTF-8") . "' saved!", "ui-icon-elrsuccess");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to role.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to role!  Role name must contain at least 3 characters and be unique.");
    }
}

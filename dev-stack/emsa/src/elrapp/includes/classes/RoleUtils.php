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
 * Utility functions associated with EMSA User Roles
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class RoleUtils
{

    public static function menuRoleTypes(PDO $dbConn, $selectedRole = 0)
    {
        $html = '';

        if ($selectedRole < 1) {
            $selectedRole = \Udoh\Emsa\Auth\Authenticator::ROLE_TYPE_DATA_ENTRY;
        }

        $sql = 'SELECT id, label
				FROM auth_role_types
				ORDER BY id;';
        $stmt = $dbConn->prepare($sql);

        if ($stmt->execute()) {
            $html .= '<select name="role_type" id="role_type">' . PHP_EOL;
            while ($row = $stmt->fetchObject()) {
                if ((intval($selectedRole) > 0) && (intval($selectedRole) === intval($row->id))) {
                    $html .= '<option value="' . intval($row->id) . '" selected>' . htmlspecialchars(trim($row->label), ENT_QUOTES, 'UTF-8') . '</option>' . PHP_EOL;
                } else {
                    $html .= '<option value="' . intval($row->id) . '">' . htmlspecialchars(trim($row->label), ENT_QUOTES, 'UTF-8') . '</option>' . PHP_EOL;
                }
            }
            $html .= '</select>' . PHP_EOL;
        }

        return $html;
    }

    /**
     * Builds a drop-down menu populated with user roles for the specified Application.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient
     * @param int $roleId [Optional] If specified, will select the indicated <i>roleId</i> in the drop-down menu.
     * @param string $elementName Suffix to use when generating a DOM id for the HTML &lt;SELECT&gt; element (will be "{applicationName}<i>elementName</i>")
     * @return string Code for an HTML &lt;SELECT&gt; element, populated with &lt;OPTION&gt;s for the Application's roles.
     */
    public static function menuApplicationRoles(\Udoh\Emsa\Client\AppClientInterface $appClient, $roleId = 0, $elementName = 'role_id')
    {
        $html = '';

        try {
            $return = $appClient->getRoles();
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            return '<em>Unable to retrieve ' . $appClient->getAppName() . ' Roles: ' . $e->getMessage() . '</em>';
        }

        $html .= '<select name="app_' . $appClient->getAppId() . $elementName . '" id="app_' . $appClient->getAppId() . $elementName . '">';
        foreach ($return as $foundRoleId => $foundRoleName) {
            if ($roleId == $foundRoleId) {
                $sel = 'selected="selected"';
            } else {
                $sel = '';
            }

            $html .= '<option value="' . (int) $foundRoleId . '" ' . $sel . '>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($foundRoleName) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Get the name of a specific user role from a give Application.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient
     * @param int $roleId ID of the role to retrieve the name for.
     * @return string 
     */
    public static function getApplicationRoleName(\Udoh\Emsa\Client\AppClientInterface $appClient, $roleId = 0)
    {
        $roleName = null;
        $cleanRoleId = (int) filter_var($roleId, \FILTER_SANITIZE_NUMBER_INT);

        if ($cleanRoleId > 0) {
            try {
                $return = $appClient->getRoles();
                $roleName = $return->getRoleNameById($cleanRoleId);
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                return '<em>Unable to retrieve ' . $appClient->getAppName() . ' Roles: ' . $e->getMessage() . '</em>';
            }
        }

        return $roleName;
    }

    public static function getConditionList(PDO $dbConn)
    {
        $conditionsArr = array();

        $sql = 'SELECT mv2.concept AS category_name, mv.concept AS condition_name
				FROM vocab_master_condition mc
				INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
				INNER JOIN vocab_master_vocab mv2 ON (mc.disease_category = mv2.id)
				WHERE mc.is_initial IS TRUE
				ORDER BY mv2.concept, mv.concept;';
        $stmt = $dbConn->prepare($sql);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $conditionsArr[] = array(
                    'category' => trim($row->category_name),
                    'condition' => trim($row->condition_name)
                );
            }
        }

        return $conditionsArr;
    }

    public static function getMenuList(PDO $dbConn, $listType = \Udoh\Emsa\Auth\Authenticator::MENU_TYPE_MENU)
    {
        $menusArr = array();

        $sql = 'SELECT id, menu_name
				FROM system_menus
				WHERE menu_type = :menuType
				ORDER BY sorty;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':menuType', $listType, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $menusArr[] = array(
                    'id' => intval($row->id),
                    'label' => trim($row->menu_name)
                );
            }
        }

        return $menusArr;
    }

    public static function getSelectedConditions(PDO $dbConn, $roleId)
    {
        $conditions = array();

        $sql = 'SELECT DISTINCT condition
				FROM auth_conditions
				WHERE role_id = :roleId;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':roleId', intval($roleId), PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $conditions[] = hash('sha512', trim($row->condition));
            }
        }

        return $conditions;
    }

    public static function getSelectedMenus(PDO $dbConn, $roleId)
    {
        $menus = array();

        $sql = 'SELECT DISTINCT menu_id
				FROM auth_menus
				WHERE role_id = :roleId;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':roleId', intval($roleId), PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $menus[] = intval($row->menu_id);
            }
        }

        return $menus;
    }

    public static function isValidRoleName(PDO $dbConn, $newRoleName, $roleId = 0)
    {
        if (strlen(trim($newRoleName)) < 3) {
            return false;
        }

        $oldRoleName = null;

        if ($roleId > 0) {
            $oldNameSql = 'SELECT name
					FROM auth_roles
					WHERE id = :roleId;';
            $oldNameStmt = $dbConn->prepare($oldNameSql);
            $oldNameStmt->bindValue(':roleId', intval($roleId), PDO::PARAM_INT);

            if ($oldNameStmt->execute()) {
                $oldRoleName = trim($oldNameStmt->fetchColumn(0));
            }
        }

        if ($oldRoleName == $newRoleName) {
            return true;
        }

        $sql = 'SELECT name
				FROM auth_roles
				WHERE name ILIKE :roleName;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':roleName', trim($newRoleName), PDO::PARAM_STR);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

}

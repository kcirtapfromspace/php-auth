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

$selectedRoleId = null;
$roleData = array(
    'roleName' => null,
    'roleType' => null,
    'appIds' => array()
);

foreach ($appClientList->getClients() as $appClient) {
    $roleData['appIds'][$appClient->getAppId()] = null;
}

if (isset($_GET['role_id']) && (intval(trim($_GET['role_id'])) > 0)) {
    $selectedRoleId = intval(trim($_GET['role_id']));
}

if (!\EmsaUtils::emptyTrim($selectedRoleId)) {
    $sql = "SELECT r.name AS name, r.role_type AS role_type, ar.app_id AS app_id, ar.app_role_id AS app_role_id 
			FROM auth_roles r
            LEFT JOIN auth_app_roles ar ON (ar.auth_role_id = r.id)
			WHERE r.id = :roleId;";
    $stmt = $adminDbConn->prepare($sql);
    $stmt->bindValue(':roleId', $selectedRoleId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $roleData['roleName'] = (string) filter_var(trim($row->name), \FILTER_SANITIZE_STRING);
            $roleData['roleType'] = (int) filter_var($row->role_type, \FILTER_SANITIZE_NUMBER_INT);
            $roleData['appIds'][(int) filter_var($row->app_id, \FILTER_SANITIZE_NUMBER_INT)] = (int) filter_var($row->app_role_id, \FILTER_SANITIZE_NUMBER_INT);
        }
    }
    
    $action = "edit";
    $selectedConditions = \RoleUtils::getSelectedConditions($adminDbConn, $selectedRoleId);
    $selectedMenus = \RoleUtils::getSelectedMenus($adminDbConn, $selectedRoleId);
} else {
    $action = "add";
    $selectedConditions = array();
    $selectedMenus = array();
}
?>
<style>
    /* input[type='checkbox'].ui-helper-hidden-accessible { position: relative !important; } */
    @-moz-document url-prefix() {
        input[type='checkbox'].ui-helper-hidden-accessible { position: absolute !important; }
    }
    fieldset { padding: 10px; font-family: 'Open Sans', Arial, Helvetica, sans-serif !important; box-shadow: 2px 2px 4px darkgray; }
    legend { font-family: 'Oswald', serif; margin-left: 10px; color: firebrick; font-weight: 500; font-size: 1.5em; }
    fieldset label, fieldset strong { font-weight: 600 !important; line-height: 2em; }
    fieldset h2 {
        font-family: 'Oswald', serif;
        color: mediumblue;
        font-weight: 500;
        font-size: 1.3em;
        border-bottom: 1px darkgray solid;
        display: block;
        margin: 5px 10px;
        line-height: 1em;
    }

    select, input {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        font-weight: 400 !important;
        margin: 0;
        font-size: 0.9em !important;
    }

    input {
        padding: 3px !important;
    }

    .vis_check_label {
        border: none !important;
        background: none !important;
    }

    th a:link, th a:visited, th a:active, th a:hover, td a:link, td a:visited, td a:active, td a:hover {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif;
        font-weight: 400;
        font-size: 0.8em;
    }

    th a:link, th a:visited, th a:active, td a:link, td a:visited, td a:active {
        color: blue;
    }

    th a:hover, td a:hover {
        color: crimson;
    }
</style>
<script type="text/javascript">
    $(function() {
        $("select, input").addClass("ui-corner-all");
    });

    function checkAll(target) {
        $("." + target).each(function() {
            if (!$(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    }

    function checkNone(target) {
        $("." + target).each(function() {
            if ($(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    }
</script>

<form method="post" action="<?php echo $webappBaseUrl . '?selected_page=' . intval($navSelectedPage) . '&submenu=' . intval($navSubmenu) . '&cat=' . intval($navCat); ?>">
    <input type="hidden" name="action" value="<?php echo $action; ?>">
    <input type="hidden" name="emsa_role_id" value="<?php echo $selectedRoleId; ?>">

    <fieldset class="ui-widget ui-widget-content ui-corner-all">
        <legend>Basic Settings</legend>
        <div class="addnew_field">
            <label for="role_name">EMSA Role Name:</label><br>
            <input style="width: 25em;" type="text" name="role_name" id="role_name" value="<?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($roleData['roleName']); ?>" />
        </div>
        <div class="addnew_field">
            <label for="role_type">Role Type:</label><br>
<?php echo \RoleUtils::menuRoleTypes($adminDbConn, $roleData['roleType']); ?>
        </div>
        <?php
        foreach ($appClientList->getClients() as $appClient) {
        ?>
        <div class="addnew_field">
            <label for="app_<?php echo $appClient->getAppId(); ?>_role_id">Applies to users with <?php echo $appClient->getAppName(); ?> Role...</label><br>
<?php echo \RoleUtils::menuApplicationRoles($appClient, (int) $roleData['appIds'][$appClient->getAppId()], '_role_id'); ?>
        </div>
        <?php
        }
        ?>
    </fieldset><br>

    <fieldset class="ui-widget ui-widget-content ui-corner-all">
        <legend>Condition Visibility</legend>
        <table id="labResults" style="display: table;">
            <thead style="float: left; width: 100% !important;">
                <tr style="display: table; width: 100%;">
                    <th style="white-space: nowrap; width: 14%;">Category</th>
                    <th style="white-space: nowrap; width: 80%;">Has Access to Condition? <a href="#" title="Grant access to all conditions" onclick="checkAll('vis_conditions_checkboxes');
                        return false;">[All]</a> <a href="#" title="Revoke access to all conditions" onclick="checkNone('vis_conditions_checkboxes');
                        return false;">[None]</a></th>
                </tr>
            </thead>
            <tbody style="float: left; width: 100%; overflow: auto; max-height: 250px;">

<?php
$conditionsArr = \RoleUtils::getConditionList($adminDbConn);

foreach ($conditionsArr as $conditionList) {
    $hashCondition = hash('sha512', $conditionList['condition']);
    $isSelectedCondition = ((in_array($hashCondition, $selectedConditions)) ? true : false);

    echo '<tr>' . PHP_EOL;
    echo '<td style="width: 15%;">' . htmlentities($conditionList['category'], ENT_QUOTES, 'UTF-8') . ' <a href="#" title="Grant access to all \'' . addslashes($conditionList['category']) . '\' conditions" onclick="checkAll(\'vis_category_' . md5($conditionList['category']) . '_checkboxes\'); return false;">[All]</a> <a href="#" title="Revoke access to all \'' . addslashes($conditionList['category']) . '\' conditions"onclick="checkNone(\'vis_category_' . md5($conditionList['category']) . '_checkboxes\'); return false;">[None]</a></td>' . PHP_EOL;
    echo '<td style="width: 85%;"><input class="condition_visibility_checkbox vis_conditions_checkboxes vis_category_' . md5($conditionList['category']) . '_checkboxes" name="conditions[]" id="conditions_' . $hashCondition . '" type="checkbox" value="' . htmlspecialchars($conditionList['condition']) . '"' . (($isSelectedCondition) ? ' checked' : '') . '> <label class="vis_check_label" for="conditions_' . $hashCondition . '">' . htmlentities($conditionList['condition'], ENT_QUOTES, 'UTF-8') . '</label></td>' . PHP_EOL;
    echo '</tr>' . PHP_EOL;
}
?>
            </tbody>
        </table>
    </fieldset><br>

    <fieldset class="ui-widget ui-widget-content ui-corner-all">
        <legend>Menu Access</legend>
        <table id="labResults" style="display: table;">
            <thead style="float: left; width: 100% !important;">
                <tr style="display: table; width: 100%;">
                    <th style="white-space: nowrap; width: 100%; text-align: left;">Has Access to Menu? <a href="#" title="Grant access to all menus" onclick="checkAll('access_menus');
                            return false;">[All]</a> <a href="#" title="Revoke access to all menus" onclick="checkNone('access_menus');
                                    return false;">[None]</a></th>
                </tr>
            </thead>
            <tbody style="float: left; width: 100%; overflow: auto;">

<?php
$menusArr = \RoleUtils::getMenuList($adminDbConn, \Udoh\Emsa\Auth\Authenticator::MENU_TYPE_MENU);

foreach ($menusArr as $menuList) {
    $isSelectedMenu = ((in_array(intval($menuList['id']), $selectedMenus)) ? true : false);

    echo '<tr style="display: table; width: 100%;">' . PHP_EOL;
    echo '<td style="width: 100%;"><input class="condition_visibility_checkbox access_menus" name="menus[]" id="menus_' . intval($menuList['id']) . '" type="checkbox" value="' . intval($menuList['id']) . '"' . (($isSelectedMenu) ? ' checked' : '') . '> <label class="vis_check_label" for="menus_' . intval($menuList['id']) . '">' . htmlentities($menuList['label'], ENT_QUOTES, 'UTF-8') . '</label></td>' . PHP_EOL;
    echo '</tr>' . PHP_EOL;
}
?>
            </tbody>
        </table>
    </fieldset><br>

    <fieldset class="ui-widget ui-widget-content ui-corner-all">
        <legend>Feature Access</legend>
        <table id="labResults" style="display: table;">
            <thead style="float: left; width: 100% !important;">
                <tr style="display: table; width: 100%;">
                    <th style="white-space: nowrap; width: 100%; text-align: left;">Can Use Feature? <a href="#" title="Grant access to all features" onclick="checkAll('access_features');
                            return false;">[All]</a> <a href="#" title="Revoke access to all features" onclick="checkNone('access_features');
                                    return false;">[None]</a></th>
                </tr>
            </thead>
            <tbody style="float: left; width: 100%; overflow: auto;">

<?php
$featuresArr = \RoleUtils::getMenuList($adminDbConn, \Udoh\Emsa\Auth\Authenticator::MENU_TYPE_FEATURE);

foreach ($featuresArr as $featureList) {
    $isSelectedFeature = ((in_array(intval($featureList['id']), $selectedMenus)) ? true : false);

    echo '<tr style="display: table; width: 100%;">' . PHP_EOL;
    echo '<td style="width: 100%;"><input class="condition_visibility_checkbox access_features" name="features[]" id="features_' . intval($featureList['id']) . '" type="checkbox" value="' . intval($featureList['id']) . '"' . (($isSelectedFeature) ? ' checked' : '') . '> <label class="vis_check_label" for="features_' . intval($featureList['id']) . '">' . htmlentities($featureList['label'], ENT_QUOTES, 'UTF-8') . '</label></td>' . PHP_EOL;
    echo '</tr>' . PHP_EOL;
}
?>
            </tbody>
        </table>
    </fieldset><br>

    <button id="submit_button" name="submit_button" type="submit">Save User Role</button>&nbsp;<button id="back_button" name="back_button" type="reset">Back/Cancel</button>

    <br><br>

</form>
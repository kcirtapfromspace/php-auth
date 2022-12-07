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
?>
<script>
	$(function() {
		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			var addAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=11&edit_flag=1";
			window.location.href = addAction;
		});
		
		$(".edit_role").button({
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).parent().controlgroup();
		
		$(".edit_role").on("click", function() {
			var editAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=11&edit_flag=1&role_id="+$(this).val();
			window.location.href = editAction;
		});
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});
		
		$(".delete_role").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=11&delete_flag=1&role_id="+$(this).val();


			$("#confirm_delete_dialog").dialog('option', 'buttons', {
					"Delete" : function() {
						window.location.href = deleteAction;
						},
					"Cancel" : function() {
						$(this).dialog("close");
						}
					});

			$("#confirm_delete_dialog").dialog("open");

		});
		
		$("#back_button").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
			var backAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=11";
			window.location.href = backAction;
		});
		
		$("#submit_button").button({
            icon: "ui-icon-elrsave"
        });
		
	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elredituser"></span>User Role Management</h1>

<?php
	
	include __DIR__ . '/add_impl.php';
	
	if (isset($_GET['delete_flag']) && (intval($_GET['delete_flag']) == 1)) {
		// handle delete function
		include_once __DIR__ . '/delete_impl.php';
	}
	
	if (isset($_GET['edit_flag']) && (intval($_GET['edit_flag']) == 1)) {
		// handle add/edit functions
		include_once __DIR__ . '/add2.php';
	} else {
	
?>

<div class="emsa_search_controls ui-tabs ui-widget" style="margin-bottom: 4em;">
	<button id="addnew_button" title="Add a new user role">Add New Role</button>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th style="white-space: nowrap; width: 15%;">Actions</th>
				<th style="width: 45%;">EMSA Role Name</th>
				<th style="white-space: nowrap; width: 20%;">Role Type</th>
                <?php
                foreach ($appClientList->getClients() as $appClient) {
                    echo '<th style="white-space: nowrap; width: 20%;">Associated ' . $appClient->getAppName() . ' Role</th>';
                }
                ?>
			</tr>
		</thead>
		<tbody>

<?php
	
	$roleListData = array();
    array(
        'roleName' => null,
        'roleType' => null,
        'appIds' => array()
    );
    $sql = 'SELECT r.id AS id, r.name AS role_name, va.id AS app_id, ar.app_role_id AS app_role_id, rt.label AS role_type
		FROM auth_roles r
        CROSS JOIN vocab_app va
		INNER JOIN auth_role_types rt ON (r.role_type = rt.id)
        LEFT JOIN auth_app_roles ar ON (ar.auth_role_id = r.id) AND (ar.app_id = va.id)
		ORDER BY r.name;';
	$stmt = $adminDbConn->prepare($sql);
	if ($stmt->execute()) {
		while ($row = $stmt->fetchObject()) {
            $roleListData[(int) $row->id]['roleName'] = (string) filter_var(trim($row->role_name), \FILTER_SANITIZE_STRING);
            $roleListData[(int) $row->id]['roleType'] = (string) filter_var(trim($row->role_type), \FILTER_SANITIZE_STRING);
            $roleListData[(int) $row->id]['appIds'][(int) filter_var($row->app_id, \FILTER_SANITIZE_NUMBER_INT)] = (int) filter_var($row->app_role_id, \FILTER_SANITIZE_NUMBER_INT);
        }
        
        foreach ($roleListData as $roleListId => $roleListItem) {
		?>
			<tr>
				<td nowrap>
					<button class="edit_role" title="Edit this user role" value="<?php echo (int) $roleListId; ?>">Edit</button><button class="delete_role" title="Remove this user role" value="<?php echo (int) $roleListId; ?>">Delete</button>
				</td>
                <td><?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($roleListItem['roleName']); ?></td>
                <td nowrap><?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($roleListItem['roleType']); ?></td>
                <?php
                foreach ($appClientList->getClients() as $appClient) {
                    echo '<td nowrap>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\RoleUtils::getApplicationRoleName($appClient, (int) $roleListItem['appIds'][(int) $appClient->getAppId()])) . '</td>';
                }
                ?>
			</tr>
		<?php
		}
	} else {
		\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of User Roles.");
	}
	
?>

		</tbody>
	</table>
</div>

<div id="confirm_delete_dialog" title="Delete this User Role?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This User Role will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<?php
	
	}
    

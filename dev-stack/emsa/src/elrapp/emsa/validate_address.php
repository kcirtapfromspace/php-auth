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

exit;  // not currently used... make sure no one can externally request it

	include __DIR__ . '/../includes/app_config.php';
	
	session_write_close(); // done writing to session; prevent blocking
	
	$this_id = intval(trim($_POST['id']));
	$xml = getValidAddress($this_id);

?>
<fieldset class="emsa-list ui-widget ui-widget-content ui-corner-all">
	<legend class="emsa-list-legend ui-widget-content ui-corner-all">Validated Address</legend>
		<div class="emsa_results_container">
			<p style="font-family: Consolas, 'Courier New', Courier; font-size: 1.2em; line-height: 1.1em; font-weight: 700; margin-left: 20px; color: darkgreen;"><?php echo $xml['valid_address']; ?></p>
			<input type="hidden" name="validated_address" id="validated_address" value="<?php echo $xml['valid_address']; ?>" />
		</div>
</fieldset>

<fieldset class="emsa-list ui-widget ui-widget-content ui-corner-all">
	<legend class="emsa-list-legend ui-widget-content ui-corner-all">Original Message Address</legend>
		<div class="emsa_results_container">
			<p style="font-family: Consolas, 'Courier New', Courier; font-size: 1.2em; line-height: 1.1em; font-weight: 700; margin-left: 20px; color: navy;">
				<?php echo $xml['street_name']; ?><br>
				<?php echo $xml['city']; ?>, <?php echo $xml['state']; ?> <?php echo $xml['postal_code']; ?><br>
			</p>
		</div>
</fieldset>
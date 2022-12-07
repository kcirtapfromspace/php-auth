/*
  Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.

  In addition, this program is also subject to certain additional terms. You should
  have received a copy of these additional terms immediately following the terms and
  conditions of the GNU Affero General Public License which accompanied the program.
  If not, please request a copy in writing from the Utah Department of Health at
  the address below.

  If you have questions concerning this license or the applicable additional terms,
  you may contact us in writing at:
  Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 */

$("#edit_singlefield_dialog").dialog({
	autoOpen: false,
	width: 700,
	modal: true
});

$("#edit_singlefield_form").on("submit", function(e) {
	e.preventDefault;
	return false;
});

$(".edit_singlefield").button({
	icon: "ui-icon-elrpencil",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');
	
	if (jsonObj.id) {
		$("#singlefield_val").val(jsonObj.val);
		$("#singlefield_old").val(jsonObj.val);
		$("#singlefield_id").val(jsonObj.id);
		$("#singlefield_col").val(jsonObj.callback_col);
		
		$("#edit_singlefield_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					handleEditSubmit($("#edit_singlefield_form"), targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.id, '', jsonObj.callback_col);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#edit_singlefield_dialog").dialog("open");
	} else {
		return false;
	}
});
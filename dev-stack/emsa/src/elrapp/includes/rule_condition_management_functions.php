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

use Udoh\Emsa\Email\Notification;
use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Display conditions for a speicified Notification rule.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * @global Notification $nc
 * 
 * @param int $rule_id
 * @param int $parent_chain_id
 */
function displayRule($rule_id, $parent_chain_id = 0)
{
    global $host_pa, $emsaDbSchemaPrefix, $nc;

    $next_chain_id = $nc->getNextChain($rule_id, $parent_chain_id, 0);
    $left_id = $next_chain_id;

    echo '<div class="rule_chain ui-corner-all">';

    do {
        $chain_sql = 'SELECT 
						c.id AS id, c.left_id AS left_id, c.link_type AS link_type, c.link_id AS link_id, c.left_operator_id AS operator_id, 
						o.label AS operator, 
						l.type_left AS type_left, l.type_right AS type_right, l.operand_left AS operand_left, l.operand_right AS operand_right, l.operator_id AS link_operator_id 
					FROM ' . $emsaDbSchemaPrefix . 'bn_expression_chain c 
					LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_operator o ON (c.left_operator_id = o.id) 
					LEFT JOIN ' . $emsaDbSchemaPrefix . 'bn_expression_link l ON (c.link_id = l.id) 
					WHERE c.id = ' . $next_chain_id . ';';
        $chain_rs = @pg_query($host_pa, $chain_sql);

        if (($chain_rs === false) || (intval(@pg_num_rows($chain_rs)) !== 1)) {
            echo '<div class="link_wrapper"><span class="ui-icon ui-icon-elrerror" style="display: inline-block; vertical-align: top; margin-right: 5px;"></span>No Conditions found!  Add a new Condition or Condition Group... ';
            echo '<div class="rule_actions">';
            $edit_addlink_params = array(
                'rule_id' => $rule_id,
                'parent_chain_id' => $parent_chain_id,
                'left_id' => 0
            );
            echo '<button type="button" class="chain_action_add_expression" value=\'' . @json_encode($edit_addlink_params) . '\'>Add new Condition</button>';
            $edit_addgroup_params = array(
                'rule_id' => $rule_id,
                'parent_chain_id' => $parent_chain_id,
                'left_id' => 0
            );
            echo '<button type="button" class="chain_action_add_group" value=\'' . @json_encode($edit_addgroup_params) . '\'>Add new Condition Group</button>';
            echo '<button type="button" class="chain_action_edit" value="" disabled>Edit Condition</button>';
            echo '<button type="button" class="chain_action_delete" value="" disabled>Delete Condition/Group</button>';
            echo '</div>';
            echo '</div>';
        } else {
            $chain = @pg_fetch_object($chain_rs);
            echo '<div class="link_wrapper">';
            if (intval($chain->operator_id) !== 0) {
                echo '<div class="rule_operator ui-corner-all">' . DisplayUtils::xSafe($chain->operator) . '</div>';
            } else {
                echo '<div class="rule_operator ui-corner-all">--</div>';
            }
            if (intval($chain->link_type) === Notification::LINKTYPE_LINK) {
                displayLink(intval($chain->link_id));
            } elseif (intval($chain->link_type) === Notification::LINKTYPE_CHAIN) {
                displayRule($rule_id, intval($chain->id));
            } else {
                DisplayUtils::drawError('Invalid logical expression type.');
            }
            echo '<div class="rule_actions">';
            $edit_addlink_params = array(
                'rule_id' => $rule_id,
                'parent_chain_id' => $parent_chain_id,
                'left_id' => intval($chain->id)
            );
            echo '<button type="button" class="chain_action_add_expression" title="Add new Condition" value=\'' . @json_encode($edit_addlink_params) . '\'>Add Condition</button>';
            $edit_addgroup_params = array(
                'rule_id' => $rule_id,
                'parent_chain_id' => $parent_chain_id,
                'left_id' => intval($chain->id)
            );
            echo '<button type="button" class="chain_action_add_group" title="Add new Condition Group" value=\'' . @json_encode($edit_addgroup_params) . '\'>Add Condition Group</button>';
            if (intval($chain->link_type) === Notification::LINKTYPE_LINK) {
                $edit_link_params = array(
                    'rule_id' => $rule_id,
                    'parent_chain_id' => $parent_chain_id,
                    'link_id' => intval($chain->link_id),
                    'operator_id' => intval($chain->operator_id),
                    'left_id' => intval($chain->left_id),
                    'link_operator_id' => intval($chain->link_operator_id),
                    'type_left' => intval($chain->type_left),
                    'type_right' => intval($chain->type_right),
                    'operand_left' => trim($chain->operand_left),
                    'operand_right' => trim($chain->operand_right)
                );
                echo '<button type="button" class="chain_action_edit" title="Edit Condition" value=\'' . @json_encode($edit_link_params) . '\'>Edit</button>';
                $delete_link_params = array(
                    'type' => 'link',
                    'rule_id' => $rule_id,
                    'link_id' => intval($chain->id),
                    'left_id' => intval($chain->left_id)
                );
                echo '<button type="button" class="chain_action_delete" title="Delete Condition" value=\'' . @json_encode($delete_link_params) . '\'>Delete</button>';
            } else {
                $edit_group_params = array();
                echo '<button type="button" class="chain_action_edit" title="Edit Condition" value=\'' . @json_encode($edit_group_params) . '\' disabled>Edit</button>';
                $delete_group_params = array(
                    'type' => 'chain',
                    'rule_id' => $rule_id,
                    'parent_chain_id' => intval($chain->id),
                    'left_id' => intval($chain->left_id)
                );
                echo '<button type="button" class="chain_action_delete" title="Delete Group" value=\'' . @json_encode($delete_group_params) . '\'>Delete</button>';
            }
            echo '</div>';
            echo '</div>';
        }

        $next_chain_id = $nc->getNextChain($rule_id, $parent_chain_id, $left_id);
        $left_id = $next_chain_id;
    } while ($next_chain_id !== false);

    echo '</div>';
}

/**
 * Display a link in a rule chain.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * @global Notification $nc
 * 
 * @param int $link_id
 */
function displayLink($link_id = null)
{
    global $host_pa, $emsaDbSchemaPrefix, $nc;

    $defaultHTMLPurifierConfig = HTMLPurifier_Config::createDefault();
    $emsaHTMLPurifier = new HTMLPurifier($defaultHTMLPurifierConfig);

    $link_sql = 'SELECT l.type_left AS type_left, l.type_right AS type_right, l.operand_left AS operand_left, l.operand_right AS operand_right, o.label AS operator 
				FROM ' . $emsaDbSchemaPrefix . 'bn_expression_link l 
				LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_operator o ON (l.operator_id = o.id) 
				WHERE l.id = ' . intval($link_id) . ';';
    $link_rs = @pg_query($host_pa, $link_sql);

    if (($link_rs === false) || (intval(@pg_num_rows($link_rs)) !== 1)) {
        DisplayUtils::drawError('Unable to evaluate expression link:  Link not found.');
    } else {
        $link = @pg_fetch_object($link_rs);

        $operand_left = $link->operand_left;
        $operand_right = $link->operand_right;

        echo '<div class="rule_link ui-corner-all">';

        if ((intval($link->type_left) === Notification::OPTYPE_PARAMETER) && (intval($link->type_right) === Notification::OPTYPE_PARAMETER)) {
            // parameter to parameter
            echo '<strong>' . htmlspecialchars($emsaHTMLPurifier->purify(displayParameterLabel($operand_left))) . '</strong>';
            echo '<div class="rule_operator ui-corner-all">' . htmlspecialchars($emsaHTMLPurifier->purify(trim($link->operator))) . '</div>';
            echo '<strong>' . htmlspecialchars($emsaHTMLPurifier->purify(displayParameterLabel($operand_right))) . '</strong>';
        } elseif ((intval($link->type_left) === Notification::OPTYPE_PARAMETER) && (intval($link->type_right) === Notification::OPTYPE_VALUE)) {
            // parameter to value
            echo '<strong>' . htmlspecialchars($emsaHTMLPurifier->purify(displayParameterLabel($operand_left))) . '</strong>';
            if (($nc->getDataType($operand_left) === 'Boolean') && (intval($operand_right) === 1)) {
                echo '<div class="rule_operator ui-corner-all">is TRUE</div>';
            } elseif (($nc->getDataType($operand_left) === 'Boolean') && (intval($operand_right) === 0)) {
                echo '<div class="rule_operator ui-corner-all">is FALSE</div>';
            } else {
                echo '<div class="rule_operator ui-corner-all">' . htmlspecialchars($emsaHTMLPurifier->purify(trim($link->operator))) . '</div>';
                echo '&quot;' . htmlspecialchars($emsaHTMLPurifier->purify($operand_right)) . '&quot;';
            }
        } else {
            // ain't no way, no how...
            DisplayUtils::drawError('Unable to evaluate expression link:  Invalid operand types specified.');
        }
        echo '</div>';
    }
}

/**
 * Display a label for the specified parameter in a rule.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * @param string $varname
 * 
 * @return string
 */
function displayParameterLabel($varname = null)
{
    global $host_pa, $emsaDbSchemaPrefix;

    if (!empty($varname)) {
        $sql = 'SELECT label FROM ' . $emsaDbSchemaPrefix . 'bn_rule_parameters WHERE varname = \'' . pg_escape_string($varname) . '\';';
        $rs = @pg_query($host_pa, $sql);
        if ($rs !== false) {
            return trim(@pg_fetch_result($rs, 0, 'label'));
        } else {
            return '';
        }
    } else {
        return '';
    }
}

/**
 * Deletes the specified rule chain.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * @global Notification $nc
 * 
 * @param int $rule_id
 * @param int $parent_chain_id
 * 
 * @return boolean
 */
function deleteChain($rule_id = null, $parent_chain_id = 0)
{
    global $host_pa, $emsaDbSchemaPrefix;

    $delete_links = array();
    $delete_chains = array();
    $outer_sql = 'SELECT id, link_id, link_type FROM ' . $emsaDbSchemaPrefix . 'bn_expression_chain 
			WHERE (rule_id = ' . intval($rule_id) . ') AND (parent_chain_id = ' . intval($parent_chain_id) . ');';
    $outer_rs = @pg_query($host_pa, $outer_sql);
    if (($outer_rs !== false) && (@pg_num_rows($outer_rs) > 0)) {
        while ($outer_row = @pg_fetch_object($outer_rs)) {
            if (intval($outer_row->link_type) === Notification::LINKTYPE_LINK) {
                $delete_links[] = intval($outer_row->link_id);
                $delete_chains[] = intval($outer_row->id);
            } else {
                deleteChain($rule_id, intval($outer_row->id)); // recursively delete descendant chains
            }
        }
    }

    $delete_chains[] = intval($parent_chain_id);

    if (count($delete_links) > 0) {
        $final_links_sql = 'DELETE FROM ' . $emsaDbSchemaPrefix . 'bn_expression_link WHERE id IN (' . implode(', ', $delete_links) . ');';
        @pg_query($host_pa, $final_links_sql);
        //echo $final_links_sql.'<br>';
    }

    if (count($delete_chains) > 0) {
        $final_chains_sql = 'DELETE FROM ' . $emsaDbSchemaPrefix . 'bn_expression_chain WHERE id IN (' . implode(', ', $delete_chains) . ');';
        @pg_query($host_pa, $final_chains_sql);
        //echo $final_chains_sql.'<br>';
    }

    return true;
}

/**
 * Deletes a specific link from a rule chain.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * @global Notification $nc
 * 
 * @param int $rule_id
 * @param int $link_id
 * 
 * @return boolean
 */
function deleteLink($rule_id = null, $link_id = 0)
{
    global $host_pa, $emsaDbSchemaPrefix;

    $delete_links = array();
    $delete_chains = array();
    $outer_sql = 'SELECT id, link_id, link_type FROM ' . $emsaDbSchemaPrefix . 'bn_expression_chain 
			WHERE (rule_id = ' . intval($rule_id) . ') AND (id = ' . intval($link_id) . ');';
    $outer_rs = @pg_query($host_pa, $outer_sql);
    if (($outer_rs !== false) && (@pg_num_rows($outer_rs) > 0)) {
        while ($outer_row = @pg_fetch_object($outer_rs)) {
            if (intval($outer_row->link_type) === Notification::LINKTYPE_LINK) {
                $delete_links[] = intval($outer_row->link_id);
                $delete_chains[] = intval($outer_row->id);
            } else {
                deleteChain($rule_id, intval($outer_row->id)); // recursively delete descendant chains
            }
        }
    } else {
        return false;
    }

    if (count($delete_links) > 0) {
        $final_links_sql = 'DELETE FROM ' . $emsaDbSchemaPrefix . 'bn_expression_link WHERE id IN (' . implode(', ', $delete_links) . ');';
        @pg_query($host_pa, $final_links_sql);
        //echo $final_links_sql.'<br>';
    }

    if (count($delete_chains) > 0) {
        $final_chains_sql = 'DELETE FROM ' . $emsaDbSchemaPrefix . 'bn_expression_chain WHERE id IN (' . implode(', ', $delete_chains) . ');';
        @pg_query($host_pa, $final_chains_sql);
        //echo $final_chains_sql.'<br>';
    }

    return true;
}

/**
 * Promotes a rule chain.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * 
 * @param int $rule_id
 * @param int $old_left_id
 * @param int $new_left_id
 */
function promoteChain($rule_id = -1, $old_left_id = -1, $new_left_id = -1)
{
    global $host_pa, $emsaDbSchemaPrefix;

    if ($new_left_id === 0) {
        $reset_operator_for_leftmost = ', left_operator_id = 0';
    } else {
        $reset_operator_for_leftmost = '';
    }

    $sql = 'UPDATE ' . $emsaDbSchemaPrefix . 'bn_expression_chain SET left_id = ' . intval($new_left_id) . $reset_operator_for_leftmost . ' WHERE (rule_id = ' . intval($rule_id) . ') AND (left_id = ' . intval($old_left_id) . ');';
    @pg_query($host_pa, $sql);
    //echo $sql.'<br>';
}

/**
 * Demotes a rule chain.
 * 
 * @global resource $host_pa
 * @global string $emsaDbSchemaPrefix
 * 
 * @param int $rule_id
 * @param int $parent_chain_id
 * @param int $old_left_id
 * @param int $new_left_id
 */
function demoteChain($rule_id = -1, $parent_chain_id = -1, $old_left_id = -1, $new_left_id = -1)
{
    global $host_pa, $emsaDbSchemaPrefix;

    $sql = 'UPDATE ' . $emsaDbSchemaPrefix . 'bn_expression_chain SET left_id = ' . intval($new_left_id) . ' WHERE (rule_id = ' . intval($rule_id) . ') AND (parent_chain_id = ' . intval($parent_chain_id) . ') AND (left_id = ' . intval($old_left_id) . ') AND (id <> ' . intval($new_left_id) . ');';
    @pg_query($host_pa, $sql);
    //echo $sql.'<br>';
}


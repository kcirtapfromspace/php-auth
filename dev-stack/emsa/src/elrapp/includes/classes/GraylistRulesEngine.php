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

use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Graylist Rules Engine
 * 
 * Evaluates Graylist Rules for a given condition & determines which matches from the Graylist to use
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
final class GraylistRulesEngine
{

    /** @var PDO */
    protected $dbConn;

    /** @var GraylistRequest */
    protected $graylistRequest;

    public function __construct(PDO $dbConn, GraylistRequest $graylistRequest)
    {
        $this->dbConn = $dbConn;
        $this->graylistRequest = $graylistRequest;
    }

    /**
     *
     * @param string $appCondition
     * @param int    $appId
     *
     * @return GraylistRule[]
     */
    private function getRulesForAppCondition(string $appCondition, int $appId): array
    {
        $rules = [];

        $sql = 'SELECT g.conditions_structured
				FROM vocab_master2app m2a
				INNER JOIN vocab_master_vocab mv ON (mv.id = m2a.master_id)
				INNER JOIN vocab_master_condition mc ON (mc.condition = mv.id)
				INNER JOIN vocab_rules_graylist g ON (g.master_condition_id = mc.c_id)
				WHERE m2a.coded_value ILIKE :appCondition
				AND m2a.app_id = :appId
                AND g.app_id = :appId
				AND mv.category = vocab_category_id(:mvCategory);';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':mvCategory', 'condition', PDO::PARAM_STR);
        $stmt->bindValue(':appCondition', $appCondition, PDO::PARAM_STR);
        $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $rules[] = new GraylistRule($row->conditions_structured);
            }
        }

        return $rules;
    }

    /**
     *
     * @param EmsaMessage $emsaMessage
     *
     * @return bool
     */
    public function evaluateRules(EmsaMessage $emsaMessage): bool
    {
        $rules = $this->getRulesForAppCondition($this->graylistRequest->getCondition(), $emsaMessage->getApplicationId());
        return $this->evaluateRuleList($rules, $emsaMessage);
    }

    /**
     *
     * @param array       $ruleList
     * @param EmsaMessage $emsaMessage
     *
     * @return bool
     */
    private function evaluateRuleList(array $ruleList, EmsaMessage $emsaMessage): bool
    {
        $overallResults = false;

        if (count($ruleList) > 0) {
            foreach ($ruleList as $rule) {
                try {
                    if (GraylistRule::evaluateRule($this->dbConn, $rule, $emsaMessage, $this->graylistRequest)) {
                        $overallResults = $overallResults || true;
                    }
                } catch (Throwable $e) {
                    // prevent an exception in rule evaluation for one message from spoiling the entire result set
                    ExceptionUtils::logException($e);
                }
            }
        }

        return $overallResults;
    }

}

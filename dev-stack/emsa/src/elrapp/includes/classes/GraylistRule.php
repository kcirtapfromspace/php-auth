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

use Udoh\Emsa\Utils\CoreUtils;

/**
 * Functionality for evaluating Graylist Rules
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistRule
{

    protected $conditions;

    public function __construct($conditions = null)
    {
        $this->conditions = @json_decode($conditions);
    }

    /**
     * Evaluate a Graylist Rule against a given Graylist candidate message
     *
     * @param PDO             $dbConn          PDO connection to EMSA database
     * @param GraylistRule    $rule            Current Graylist Rule
     * @param EmsaMessage     $emsaMessage     Candidate message to check against rules
     * @param GraylistRequest $graylistRequest Current Graylist Request being processed
     *
     * @return bool
     */
    public static function evaluateRule(PDO $dbConn, GraylistRule $rule, EmsaMessage $emsaMessage, GraylistRequest $graylistRequest): bool
    {
        $candidateConditionId = EmsaUtils::appConceptMasterVocabId($dbConn, 'condition', $emsaMessage->masterCondition, $emsaMessage->getApplicationId());
        $candidateTestTypeId = EmsaUtils::appConceptMasterVocabId($dbConn, 'test_type', $emsaMessage->masterTestType, $emsaMessage->getApplicationId());
        $candidateReferenceDate = $emsaMessage->getReferenceDate();
        $eventDate = $graylistRequest->getEventDate();

        $testCondition = true;

        if (is_iterable($rule->conditions)) {
            foreach ($rule->conditions as $test) {
                $thisDiseaseOperator = CoreUtils::operatorById($test->operator);
                $thisTestTypeOperator = CoreUtils::operatorById($test->operator1);

                // check matching disease
                if (intval($test->operand) > 0) {
                    // skip if operand = -1 ("Any")
                    switch ($thisDiseaseOperator) {
                        case '==':
                            $testCondition = $testCondition && ($candidateConditionId == $test->operand);
                            break;
                        case '!=':
                            $testCondition = $testCondition && ($candidateConditionId != $test->operand);
                            break;
                        case '>':
                            $testCondition = $testCondition && ($candidateConditionId > $test->operand);
                            break;
                        case '<':
                            $testCondition = $testCondition && ($candidateConditionId < $test->operand);
                            break;
                        case '>=':
                            $testCondition = $testCondition && ($candidateConditionId >= $test->operand);
                            break;
                        case '<=':
                            $testCondition = $testCondition && ($candidateConditionId <= $test->operand);
                            break;
                        default:
                            $testCondition = $testCondition && false;
                            break;
                    }
                }

                if (!$testCondition) {
                    return false; // short-circuit
                }

                // check matching test type
                if (intval($test->operand1) > 0) {
                    // skip if operand = -1 ("Any")
                    switch ($thisTestTypeOperator) {
                        case '==':
                            $testCondition = $testCondition && ($candidateTestTypeId == $test->operand1);
                            break;
                        case '!=':
                            $testCondition = $testCondition && ($candidateTestTypeId != $test->operand1);
                            break;
                        case '>':
                            $testCondition = $testCondition && ($candidateTestTypeId > $test->operand1);
                            break;
                        case '<':
                            $testCondition = $testCondition && ($candidateTestTypeId < $test->operand1);
                            break;
                        case '>=':
                            $testCondition = $testCondition && ($candidateTestTypeId >= $test->operand1);
                            break;
                        case '<=':
                            $testCondition = $testCondition && ($candidateTestTypeId <= $test->operand1);
                            break;
                        default:
                            $testCondition = $testCondition && false;
                            break;
                    }
                }

                if (!$testCondition) {
                    return false; // short-circuit
                }

                // check collection date range
                $eventDateLBound = clone $eventDate;
                $eventDateLBound->sub(DateInterval::createFromDateString(trim($test->collect_lbound)));

                $eventDateUBound = clone $eventDate;
                $eventDateUBound->add(DateInterval::createFromDateString(trim($test->collect_ubound)));

                if (($candidateReferenceDate > $eventDateLBound) && ($candidateReferenceDate < $eventDateUBound)) {
                    $testCondition = $testCondition && true;
                } else {
                    $testCondition = $testCondition && false;
                }
            }
        }

        if ($testCondition) {
            return true;
        } else {
            return false;
        }
    }

}

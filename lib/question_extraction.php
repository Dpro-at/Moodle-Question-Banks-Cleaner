<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question extraction helper functions.
 * Demonstrates how to extract questions and quizzes from a course
 * using the same filter mechanism as the Question Bank interface.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/**
 * Decode the filter parameter from URL
 *
 * @param string $filterstring URL-encoded JSON filter string
 * @return array Decoded filter data
 */
function local_questioncleaner_decode_filter_parameter($filterstring) {
    $decoded = json_decode(urldecode($filterstring), true);
    
    if (!$decoded) {
        $decoded = json_decode($filterstring, true);
    }
    
    return $decoded;
}

/**
 * Extract filter conditions
 *
 * @param array $filterdata Decoded filter data
 * @return array Extracted conditions
 */
function local_questioncleaner_extract_filter_conditions($filterdata) {
    $conditions = [];
    
    // Extract category filter
    if (isset($filterdata['category'])) {
        $categoryfilter = $filterdata['category'];
        $conditions['category'] = [
            'values' => $categoryfilter['values'] ?? [],
            'includesubcategories' => false
        ];
        
        // Check for includesubcategories option
        if (isset($categoryfilter['filteroptions'])) {
            foreach ($categoryfilter['filteroptions'] as $option) {
                if (isset($option['name']) && $option['name'] === 'includesubcategories') {
                    $conditions['category']['includesubcategories'] = (bool)($option['value'] ?? false);
                }
            }
        }
    }
    
    // Extract hidden filter
    if (isset($filterdata['hidden'])) {
        $hiddenfilter = $filterdata['hidden'];
        $conditions['hidden'] = [
            'values' => $hiddenfilter['values'] ?? [0]
        ];
    }
    
    // Extract jointype (1 = AND, 2 = OR)
    $conditions['jointype'] = $filterdata['jointype'] ?? 2;
    
    return $conditions;
}

/**
 * Get all subcategory IDs recursively
 *
 * @param int $categoryid Category ID
 * @return array Array of subcategory IDs
 */
function local_questioncleaner_get_subcategory_ids($categoryid) {
    global $DB;
    
    $subcategoryids = [];
    $subcategories = $DB->get_records('question_categories', ['parent' => $categoryid], '', 'id');
    
    foreach ($subcategories as $subcat) {
        $subcategoryids[] = $subcat->id;
        $subsubcats = local_questioncleaner_get_subcategory_ids($subcat->id);
        $subcategoryids = array_merge($subcategoryids, $subsubcats);
    }
    
    return $subcategoryids;
}

/**
 * Get quizzes that use these questions
 *
 * @param array $questionids Array of question IDs
 * @param int|null $courseid Optional course ID
 * @return array Array of quiz usage records
 */
function local_questioncleaner_get_quizzes_using_questions($questionids, $courseid = null) {
    global $DB;
    
    if (empty($questionids)) {
        return [];
    }
    
    list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
    
    $sql = "SELECT DISTINCT qz.id AS quizid,
                   qz.name AS quizname,
                   qz.course AS courseid,
                   qs.id AS slotid,
                   qs.slot AS slotnumber,
                   qs.page,
                   qs.maxmark,
                   qr.questionbankentryid,
                   q.id AS questionid
              FROM {quiz} qz
              JOIN {quiz_slots} qs ON qs.quizid = qz.id
              JOIN {question_references} qr ON qr.itemid = qs.id
                                          AND qr.component = 'mod_quiz'
                                          AND qr.questionarea = 'slot'
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qv.questionid
             WHERE q.id $insql";
    
    if ($courseid) {
        $sql .= " AND qz.course = :courseid";
        $params['courseid'] = $courseid;
    }
    
    $sql .= " ORDER BY qz.id, qs.slot";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Get random question slots (question_set_references)
 *
 * @param int $courseid Course ID
 * @param array|null $filterconditions Optional filter conditions
 * @return array Array of random question slot records
 */
function local_questioncleaner_get_random_question_slots($courseid, $filterconditions = null) {
    global $DB;
    
    $params = ['courseid' => $courseid];
    $sql = "SELECT qsr.id,
                   qsr.filtercondition,
                   qsr.questionscontextid,
                   qs.id AS slotid,
                   qs.quizid,
                   qs.slot AS slotnumber,
                   qz.name AS quizname
              FROM {question_set_references} qsr
              JOIN {quiz_slots} qs ON qs.id = qsr.itemid
              JOIN {quiz} qz ON qz.id = qs.quizid
              JOIN {context} ctx ON ctx.id = qsr.usingcontextid
              JOIN {course_modules} cm ON cm.id = ctx.instanceid
             WHERE qsr.component = 'mod_quiz'
               AND qsr.questionarea = 'slot'
               AND qz.course = :courseid
             ORDER BY qz.id, qs.slot";
    
    return $DB->get_records_sql($sql, $params);
}


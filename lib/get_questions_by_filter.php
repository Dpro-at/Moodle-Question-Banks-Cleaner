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
 * Script to extract questions and quizzes from a course based on filter parameter.
 * Uses the correct Moodle core methods.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__ . '/question_extraction.php');

// Get parameters
$courseid = optional_param('courseid', 0, PARAM_INT);
$filterstring = optional_param('filter', '', PARAM_RAW);

if (!$courseid || !$filterstring) {
    die("Usage: ?courseid=48&filter={\"category\":{...}}\n");
}

// Decode filter
$filterdata = local_questioncleaner_decode_filter_parameter($filterstring);
if (!$filterdata) {
    die("Invalid filter format\n");
}

// Extract category IDs
$categoryids = [];
$includesubcategories = false;

if (isset($filterdata['category']['values'])) {
    $categoryids = $filterdata['category']['values'];
    
    if (isset($filterdata['category']['filteroptions'])) {
        foreach ($filterdata['category']['filteroptions'] as $option) {
            if (isset($option['name']) && $option['name'] === 'includesubcategories') {
                $includesubcategories = (bool)($option['value'] ?? false);
            }
        }
    }
}

// Get all category IDs (including subcategories if needed)
$allcategoryids = $categoryids;
if ($includesubcategories) {
    foreach ($categoryids as $catid) {
        $subcats = local_questioncleaner_get_subcategory_ids($catid);
        $allcategoryids = array_merge($allcategoryids, $subcats);
    }
    $allcategoryids = array_unique($allcategoryids);
}

// Get hidden filter
$showhidden = false;
if (isset($filterdata['hidden']['values']) && !empty($filterdata['hidden']['values'])) {
    $showhidden = ($filterdata['hidden']['values'][0] == 1);
}

// Build query using correct Moodle method
global $DB;

$params = [];
$whereconditions = [];

// Base conditions
$whereconditions[] = 'q.parent = 0';

// Latest version (from custom_view.php)
$latestversion = 'qv.version = (SELECT MAX(v.version)
                                  FROM {question_versions} v
                                  JOIN {question_bank_entries} be
                                    ON be.id = v.questionbankentryid
                                 WHERE be.id = qbe.id AND v.status <> :substatus)';
$whereconditions[] = $latestversion;
$params['substatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN;

// Status filter
if ($showhidden) {
    $whereconditions[] = 'qv.status = :hiddenstatus';
    $params['hiddenstatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_HIDDEN;
} else {
    $whereconditions[] = 'qv.status = :status';
    $params['status'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
}

// Category filter
if (!empty($allcategoryids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($allcategoryids, SQL_PARAMS_NAMED);
    $whereconditions[] = "qbe.questioncategoryid $insql";
    $params = array_merge($params, $inparams);
}

// Course context (from custom_view.php)
$coursecontext = context_course::instance($courseid);
$whereconditions[] = "(ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)";
$params['coursecontextid'] = $coursecontext->id;
$params['coursecontextpath'] = $coursecontext->path . '/%';

// Build SQL
$sql = "SELECT DISTINCT q.id,
           q.name,
           q.questiontext,
           q.qtype,
           qc.id AS categoryid,
           qc.name AS categoryname,
           qv.version,
           qv.status,
           qbe.id AS questionbankentryid
      FROM {question} q
      JOIN {question_versions} qv ON q.id = qv.questionid
      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
      JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
      JOIN {context} ctx ON ctx.id = qc.contextid
     WHERE " . implode(' AND ', $whereconditions) . "
     ORDER BY q.id";

$questions = $DB->get_records_sql($sql, $params);

// Get quizzes using these questions
$questionids = array_keys($questions);
$quizzes = [];

if (!empty($questionids)) {
    $quizzes = local_questioncleaner_get_quizzes_using_questions($questionids, $courseid);
}

// Get random question slots
$randomslots = local_questioncleaner_get_random_question_slots($courseid);

// Output results
header('Content-Type: application/json; charset=utf-8');

$result = [
    'courseid' => $courseid,
    'filter' => $filterdata,
    'categories_searched' => $allcategoryids,
    'includesubcategories' => $includesubcategories,
    'total_questions' => count($questions),
    'questions' => array_values($questions),
    'total_quizzes' => count(array_unique(array_column($quizzes, 'quizid'))),
    'quiz_usage' => array_values($quizzes),
    'total_random_slots' => count($randomslots),
    'random_slots' => array_values($randomslots)
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


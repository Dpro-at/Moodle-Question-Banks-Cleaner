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
 * Main cleaner class for question bank cleanup.
 * Uses the correct Moodle core methods to identify questions and their usage.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questioncleaner;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

use core_question\local\bank\question_version_status;

/**
 * Class cleaner
 */
class cleaner {

    /**
     * Add a small delay to reduce database load between heavy queries
     */
    public static function delay() {
        usleep(100000); // 0.1 second delay
    }

    /**
     * Get questions by course context (correct method from Moodle core)
     * Based on mod/quiz/classes/question/bank/custom_view.php
     *
     * @param int $courseid Course ID
     * @return array Array of question objects
     */
    public static function get_questions_by_course_context($courseid) {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        
        // Latest version condition (from custom_view.php)
        $latestversion = 'qv.version = (SELECT MAX(v.version)
                                          FROM {question_versions} v
                                          JOIN {question_bank_entries} be
                                            ON be.id = v.questionbankentryid
                                         WHERE be.id = qbe.id AND v.status <> :substatus)';

        $params = [
            'status' => question_version_status::QUESTION_STATUS_READY,
            'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
            'coursecontextid' => $coursecontext->id,
            'coursecontextpath' => $coursecontext->path . '/%',
        ];

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
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              JOIN {context} ctx ON ctx.id = qc.contextid
             WHERE (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
               AND q.parent = 0
               AND {$latestversion}
               AND qv.status = :status
             ORDER BY q.id";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get used question bank entry IDs (correct method from Moodle core)
     * Based on mod/quiz/classes/question/bank/qbank_helper.php
     *
     * @return array Array of question bank entry IDs that are used
     */
    public static function get_used_question_bank_entry_ids() {
        global $DB;

        $sql = "SELECT DISTINCT qr.questionbankentryid
                  FROM {question_references} qr
                 WHERE qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'";

        return $DB->get_fieldset_sql($sql);
    }

    /**
     * Get count of questions actually used in quizzes
     *
     * @return int Number of questions used
     */
    public static function get_used_questions_count() {
        $usedentryids = self::get_used_question_bank_entry_ids();
        return count($usedentryids);
    }

    /**
     * Get count of used questions
     *
     * @param int|null $courseid Optional course ID to filter by course
     * @return int Total count of used questions
     */
    /**
     * Get count of used questions for a specific course
     *
     * @param int $courseid Course ID
     * @return int Number of used questions in the course
     */
    public static function get_used_questions_count_by_course($courseid) {
        return self::get_used_questions_count_total($courseid);
    }

    /**
     * Get count of unused questions for a specific course
     *
     * @param int $courseid Course ID
     * @return int Number of unused questions in the course
     */
    public static function get_unused_questions_count_by_course($courseid) {
        global $DB;

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();
        
        // Latest version condition
        $latestversion = 'qv.version = (SELECT MAX(v.version)
                                          FROM {question_versions} v
                                          JOIN {question_bank_entries} be
                                            ON be.id = v.questionbankentryid
                                         WHERE be.id = qbe.id AND v.status <> :substatus)';

        $params = [
            'status' => question_version_status::QUESTION_STATUS_READY,
            'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
        ];

        $sql = "SELECT COUNT(DISTINCT q.id) AS total
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE q.parent = 0
               AND {$latestversion}
               AND qv.status = :status";

        // Filter by course if specified
        if ($courseid) {
            $coursecontext = \context_course::instance($courseid);
            $sql .= " AND EXISTS (
                        SELECT 1 FROM {context} ctx
                        WHERE ctx.id = qc.contextid
                        AND (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
                      )";
            $params['coursecontextid'] = $coursecontext->id;
            $params['coursecontextpath'] = $coursecontext->path . '/%';
        }

        // Exclude used questions
        if (!empty($usedentryids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
            $sql .= " AND qbe.id $insql";
            $params = array_merge($params, $inparams);
        }

        $result = $DB->get_record_sql($sql, $params);
        return (int)($result->total ?? 0);
    }

    public static function get_used_questions_count_total($courseid = null) {
        global $DB;

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();
        
        if (empty($usedentryids)) {
            return 0;
        }
        
        // Latest version condition
        $latestversion = 'qv.version = (SELECT MAX(v.version)
                                          FROM {question_versions} v
                                          JOIN {question_bank_entries} be
                                            ON be.id = v.questionbankentryid
                                         WHERE be.id = qbe.id AND v.status <> :substatus)';

        $params = [
            'status' => question_version_status::QUESTION_STATUS_READY,
            'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
        ];

        $sql = "SELECT COUNT(DISTINCT q.id) AS total
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE q.parent = 0
               AND {$latestversion}
               AND qv.status = :status";

        // Include only used questions
        list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED);
        $sql .= " AND qbe.id $insql";
        $params = array_merge($params, $inparams);

        // Filter by course if specified
        if ($courseid) {
            $coursecontext = \context_course::instance($courseid);
            $sql .= " AND EXISTS (
                        SELECT 1 FROM {context} ctx
                        WHERE ctx.id = qc.contextid
                        AND (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
                      )";
            $params['coursecontextid'] = $coursecontext->id;
            $params['coursecontextpath'] = $coursecontext->path . '/%';
        }

        $result = $DB->get_record_sql($sql, $params);
        return (int)($result->total ?? 0);
    }

    /**
     * Get used questions (questions linked to quizzes)
     *
     * @param int|null $courseid Optional course ID to filter by course
     * @param int $limit Maximum number of questions to return
     * @param int $offset Offset for pagination
     * @return array Array of used question objects
     */
    public static function check_used_questions($courseid = null, $limit = 1000, $offset = 0) {
        global $DB;

        $limit = max(1, min(10000, (int)$limit));
        $offset = max(0, (int)$offset);

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();
        
        if (empty($usedentryids)) {
            return [];
        }
        
        // Latest version condition
        $latestversion = 'qv.version = (SELECT MAX(v.version)
                                          FROM {question_versions} v
                                          JOIN {question_bank_entries} be
                                            ON be.id = v.questionbankentryid
                                         WHERE be.id = qbe.id AND v.status <> :substatus)';

        $params = [
            'status' => question_version_status::QUESTION_STATUS_READY,
            'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
        ];

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
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE q.parent = 0
               AND {$latestversion}
               AND qv.status = :status";

        // Include only used questions
        list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED);
        $sql .= " AND qbe.id $insql";
        $params = array_merge($params, $inparams);

        // Filter by course if specified
        if ($courseid) {
            $coursecontext = \context_course::instance($courseid);
            $sql .= " AND EXISTS (
                        SELECT 1 FROM {context} ctx
                        WHERE ctx.id = qc.contextid
                        AND (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
                      )";
            $params['coursecontextid'] = $coursecontext->id;
            $params['coursecontextpath'] = $coursecontext->path . '/%';
        }

        $sql .= " ORDER BY q.id LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Check unused questions using the correct Moodle method
     *
     * @param int|null $courseid Optional course ID to filter by course
     * @param int $limit Maximum number of questions to return
     * @return array Array of unused question objects
     */
    public static function check_unused_questions($courseid = null, $limit = 1000) {
        global $DB;

        $limit = max(1, min(10000, (int)$limit));

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();
        
        // Latest version condition (from custom_view.php)
        $latestversion = 'qv.version = (SELECT MAX(v.version)
                                          FROM {question_versions} v
                                          JOIN {question_bank_entries} be
                                            ON be.id = v.questionbankentryid
                                         WHERE be.id = qbe.id AND v.status <> :substatus)';

        $params = [
            'status' => question_version_status::QUESTION_STATUS_READY,
            'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
        ];

        $sql = "SELECT q.id,
                   q.name,
                   q.questiontext,
                   q.qtype,
                   qc.id AS categoryid,
                   qc.name AS categoryname,
                   qv.version,
                   qbe.id AS questionbankentryid
              FROM {question} q
              JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             WHERE q.parent = 0
               AND {$latestversion}
               AND qv.status = :status";

        // Filter by course if specified
        if ($courseid) {
            $coursecontext = \context_course::instance($courseid);
            $sql .= " AND EXISTS (
                        SELECT 1 FROM {context} ctx
                        WHERE ctx.id = qc.contextid
                        AND (ctx.id = :coursecontextid OR ctx.path LIKE :coursecontextpath)
                      )";
            $params['coursecontextid'] = $coursecontext->id;
            $params['coursecontextpath'] = $coursecontext->path . '/%';
        }

        // Exclude used questions
        if (!empty($usedentryids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
            $sql .= " AND qbe.id $insql";
            $params = array_merge($params, $inparams);
        }

        $sql .= " ORDER BY q.id LIMIT " . (int)$limit;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Verify question usage before deletion (double check)
     *
     * @param array $questionbankentryids Array of question bank entry IDs to verify
     * @return array Array of verified unused question bank entry IDs
     */
    public static function verify_question_usage($questionbankentryids) {
        global $DB;

        if (empty($questionbankentryids)) {
            return [];
        }

        // Convert to integers
        $questionbankentryids = array_map('intval', $questionbankentryids);
        $questionbankentryids = array_unique($questionbankentryids);

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();
        $usedentryids = array_map('intval', $usedentryids);

        // Return only unused ones
        $unused = array_diff($questionbankentryids, $usedentryids);

        return array_values($unused);
    }

    /**
     * Check duplicate questions
     *
     * @param int $limit Maximum number of duplicates to return
     * @return array Array of duplicate question groups
     */
    public static function check_duplicate_questions($limit = 100) {
        global $DB;

        $limit = max(1, min(10000, (int)$limit));

        self::delay();

        try {
            $sql = "SELECT q.id,
                       q.name,
                       q.qtype,
                       q.questiontext,
                       CONCAT(q.name, '-', q.qtype, '-', MD5(q.questiontext)) AS duplicate_key
                  FROM {question} q
                 WHERE CONCAT(q.name, '-', q.qtype, '-', MD5(q.questiontext)) IN (
                       SELECT CONCAT(name, '-', qtype, '-', MD5(questiontext)) AS dup_key
                         FROM {question}
                        GROUP BY CONCAT(name, '-', qtype, '-', MD5(questiontext))
                       HAVING COUNT(*) > 1
                     )
                 ORDER BY duplicate_key, q.id
                 LIMIT " . (int)$limit;

            return $DB->get_records_sql($sql);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check orphaned answers (answers linked to deleted questions)
     *
     * @param int $limit Maximum number of answers to return
     * @return array Array of orphaned answer objects
     */
    public static function check_orphaned_answers($limit = 1000) {
        global $DB;

        $limit = max(1, min(10000, (int)$limit));

        self::delay();

        try {
            $sql = "SELECT qa.id,
                       qa.question,
                       qa.answer,
                       qa.fraction,
                       qa.feedback
                  FROM {question_answers} qa
             LEFT JOIN {question} q ON q.id = qa.question
                 WHERE q.id IS NULL
                 ORDER BY qa.id
                 LIMIT " . (int)$limit;

            return $DB->get_records_sql($sql);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check answers for unused questions
     *
     * @param int $limit Maximum number of answers to return
     * @return array Array of answer objects for unused questions
     */
    public static function check_unused_question_answers($limit = 1000) {
        global $DB;

        $limit = max(1, min(10000, (int)$limit));

        // Get used question bank entry IDs
        $usedentryids = self::get_used_question_bank_entry_ids();

        self::delay();

        try {
            $latestversion = 'qv.version = (SELECT MAX(v.version)
                                              FROM {question_versions} v
                                              JOIN {question_bank_entries} be
                                                ON be.id = v.questionbankentryid
                                             WHERE be.id = qbe.id AND v.status <> :substatus)';

            $params = [
                'status' => question_version_status::QUESTION_STATUS_READY,
                'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
            ];

            $sql = "SELECT qa.id,
                       qa.question,
                       qa.answer,
                       qa.fraction,
                       qa.feedback,
                       q.id AS questionid,
                       q.name AS questionname
                  FROM {question_answers} qa
                  JOIN {question} q ON q.id = qa.question
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE {$latestversion}
                   AND qv.status = :status";

            // Exclude answers for used questions
            if (!empty($usedentryids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
                $sql .= " AND qbe.id $insql";
                $params = array_merge($params, $inparams);
            } else {
                // If no used questions, all are unused
            }

            $sql .= " ORDER BY qa.id LIMIT " . (int)$limit;

            return $DB->get_records_sql($sql, $params);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get statistics
     *
     * @return array Statistics array
     */
    public static function get_statistics() {
        global $DB;

        $stats = [
            'total_questions' => 0,
            'duplicated_questions' => 0,
            'unused_questions' => 0,
            'orphaned_answers' => 0,
            'unused_question_answers' => 0,
        ];

        try {
            // Total questions
            $stats['total_questions'] = $DB->count_records('question', ['parent' => 0]);
        } catch (\Exception $e) {
            // Ignore
        }

        self::delay();

        try {
            // Duplicated questions count
            $sql = "SELECT COUNT(*) - COUNT(DISTINCT CONCAT(name, '-', qtype, '-', MD5(questiontext))) AS duplicated
                      FROM {question}
                     WHERE parent = 0";
            $result = $DB->get_record_sql($sql);
            $stats['duplicated_questions'] = $result->duplicated ?? 0;
        } catch (\Exception $e) {
            // Ignore
        }

        self::delay();

        try {
            // Unused questions count
            $usedentryids = self::get_used_question_bank_entry_ids();
            $latestversion = 'qv.version = (SELECT MAX(v.version)
                                              FROM {question_versions} v
                                              JOIN {question_bank_entries} be
                                                ON be.id = v.questionbankentryid
                                             WHERE be.id = qbe.id AND v.status <> :substatus)';

            $params = [
                'status' => question_version_status::QUESTION_STATUS_READY,
                'substatus' => question_version_status::QUESTION_STATUS_HIDDEN,
            ];

            $sql = "SELECT COUNT(DISTINCT q.id) AS unused
                      FROM {question} q
                      JOIN {question_versions} qv ON qv.questionid = q.id
                      JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                     WHERE q.parent = 0
                       AND {$latestversion}
                       AND qv.status = :status";

            if (!empty($usedentryids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($usedentryids, SQL_PARAMS_NAMED, 'used', false);
                $sql .= " AND qbe.id $insql";
                $params = array_merge($params, $inparams);
            }

            $result = $DB->get_record_sql($sql, $params);
            $stats['unused_questions'] = $result->unused ?? 0;
        } catch (\Exception $e) {
            // Ignore
        }

        self::delay();

        try {
            // Orphaned answers count
            $sql = "SELECT COUNT(*) AS orphaned
                      FROM {question_answers} qa
                 LEFT JOIN {question} q ON q.id = qa.question
                     WHERE q.id IS NULL";
            $result = $DB->get_record_sql($sql);
            $stats['orphaned_answers'] = $result->orphaned ?? 0;
        } catch (\Exception $e) {
            // Ignore
        }

        return $stats;
    }

    /**
     * Format number with human-readable format (only for large numbers > 100,000)
     *
     * @param int $number The number to format
     * @return string Formatted number string
     */
    public static function format_large_number($number) {
        global $CFG;
        $number = (int)$number;
        
        // Get current language
        $lang = current_language();
        
        // Only format if > 100,000
        if ($number > 100000) {
            if ($number >= 1000000) {
                $formatted = number_format($number / 1000000, 1);
                if ($lang === 'de') {
                    return $formatted . ' ' . get_string('million', 'local_questioncleaner');
                } else {
                    return $formatted . ' ' . get_string('million', 'local_questioncleaner');
                }
            } else {
                $formatted = number_format($number / 1000, 0);
                if ($lang === 'de') {
                    return $formatted . ' ' . get_string('thousand', 'local_questioncleaner');
                } else {
                    return $formatted . ' ' . get_string('thousand', 'local_questioncleaner');
                }
            }
        }
        
        // Return number as is for smaller numbers
        return number_format($number);
    }

    /**
     * Delete unused questions safely (with verification)
     *
     * @param array $questionids Array of question IDs to delete
     * @param bool $verify Whether to verify before deletion
     * @param int $batch_size Batch size for deletion
     * @return array Result with 'deleted', 'failed', 'errors' keys
     */
    public static function delete_unused_questions_safe($questionids, $verify = true, $batch_size = 1000) {
        global $DB;

        $result = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (empty($questionids)) {
            return $result;
        }

        // Convert to integers
        $questionids = array_map('intval', $questionids);
        $questionids = array_unique($questionids);

        // Get question bank entry IDs for these questions
        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT qbe.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE q.id $insql";
        $entryids = $DB->get_fieldset_sql($sql, $params);

        // Verify if requested
        if ($verify && !empty($entryids)) {
            $verified = self::verify_question_usage($entryids);
            // Only delete questions whose entry IDs are verified as unused
            $verifiedquestionids = [];
            if (!empty($verified)) {
                list($verifiedsql, $verifiedparams) = $DB->get_in_or_equal($verified, SQL_PARAMS_NAMED);
                $sql = "SELECT DISTINCT q.id
                          FROM {question} q
                          JOIN {question_versions} qv ON qv.questionid = q.id
                          JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                         WHERE qbe.id $verifiedsql";
                $verifiedquestionids = $DB->get_fieldset_sql($sql, $verifiedparams);
            }
            $questionids = array_intersect($questionids, $verifiedquestionids);
        }

        if (empty($questionids)) {
            return $result;
        }

        // Process in batches
        $batches = array_chunk($questionids, $batch_size);

        foreach ($batches as $batch) {
            try {
                list($batchsql, $batchparams) = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED);

                // Delete related data first (in correct order)
                // 1. Delete question answers
                $DB->execute("DELETE qa FROM {question_answers} qa
                              WHERE qa.question $batchsql", $batchparams);

                // 2. Delete question type specific data
                $qtypes = $DB->get_fieldset_sql("SELECT DISTINCT qtype FROM {question} WHERE id $batchsql", $batchparams);
                foreach ($qtypes as $qtype) {
                    if ($qtype && $qtype !== 'missingtype') {
                        $tablename = "qtype_{$qtype}_options";
                        if ($DB->get_manager()->table_exists($tablename)) {
                            $DB->execute("DELETE FROM {{$tablename}} WHERE questionid $batchsql", $batchparams);
                        }
                    }
                }

                // 3. Delete question versions
                $DB->execute("DELETE qv FROM {question_versions} qv
                              JOIN {question} q ON q.id = qv.questionid
                              WHERE q.id $batchsql", $batchparams);

                // 4. Delete question bank entries (if no versions left)
                $DB->execute("DELETE qbe FROM {question_bank_entries} qbe
                              WHERE qbe.id NOT IN (
                                  SELECT DISTINCT questionbankentryid FROM {question_versions}
                              )");

                // 5. Delete questions (last)
                $DB->execute("DELETE FROM {question} WHERE id $batchsql", $batchparams);

                $result['deleted'] += count($batch);
            } catch (\Exception $e) {
                $result['failed'] += count($batch);
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Delete orphaned answers (answers linked to deleted questions)
     * These are 100% safe to delete because the question is already deleted
     *
     * @param array $answerids Array of answer IDs to delete
     * @param int $batch_size Batch size for deletion
     * @return array Result with 'deleted', 'failed', 'errors' keys
     */
    public static function delete_orphaned_answers($answerids, $batch_size = 1000) {
        global $DB;

        $result = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (empty($answerids)) {
            return $result;
        }

        // Convert to integers
        $answerids = array_map('intval', $answerids);
        $answerids = array_unique($answerids);

        // Verify these are truly orphaned (question doesn't exist)
        list($insql, $params) = $DB->get_in_or_equal($answerids, SQL_PARAMS_NAMED);
        $sql = "SELECT qa.id
                  FROM {question_answers} qa
             LEFT JOIN {question} q ON q.id = qa.question
                 WHERE qa.id $insql
                   AND q.id IS NULL";
        $verifiedids = $DB->get_fieldset_sql($sql, $params);

        if (empty($verifiedids)) {
            return $result;
        }

        // Process in batches
        $batches = array_chunk($verifiedids, $batch_size);

        foreach ($batches as $batch) {
            try {
                list($batchsql, $batchparams) = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED);
                $DB->execute("DELETE FROM {question_answers} WHERE id $batchsql", $batchparams);
                $result['deleted'] += count($batch);
            } catch (\Exception $e) {
                $result['failed'] += count($batch);
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Delete unused answers (answers for unused questions)
     * Verifies that the question is not used before deleting answers
     *
     * @param array $answerids Array of answer IDs to delete
     * @param int $batch_size Batch size for deletion
     * @return array Result with 'deleted', 'failed', 'errors' keys
     */
    public static function delete_unused_answers($answerids, $batch_size = 1000) {
        global $DB;

        $result = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (empty($answerids)) {
            return $result;
        }

        // Convert to integers
        $answerids = array_map('intval', $answerids);
        $answerids = array_unique($answerids);

        // Get question IDs for these answers
        list($insql, $params) = $DB->get_in_or_equal($answerids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT qa.question
                  FROM {question_answers} qa
                 WHERE qa.id $insql";
        $questionids = $DB->get_fieldset_sql($sql, $params);

        if (empty($questionids)) {
            return $result;
        }

        // Get question bank entry IDs for these questions
        list($qinsql, $qparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT qbe.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE q.id $qinsql";
        $entryids = $DB->get_fieldset_sql($sql, $qparams);

        // Verify questions are not used
        $verifiedentryids = self::verify_question_usage($entryids);

        if (empty($verifiedentryids)) {
            // All questions are used, don't delete answers
            return $result;
        }

        // Get verified question IDs
        list($verifiedsql, $verifiedparams) = $DB->get_in_or_equal($verifiedentryids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.id $verifiedsql";
        $verifiedquestionids = $DB->get_fieldset_sql($sql, $verifiedparams);

        // Get answers for verified unused questions only
        list($qverifiedsql, $qverifiedparams) = $DB->get_in_or_equal($verifiedquestionids, SQL_PARAMS_NAMED);
        $sql = "SELECT qa.id
                  FROM {question_answers} qa
                 WHERE qa.id $insql
                   AND qa.question $qverifiedsql";
        $verifiedanswerids = $DB->get_fieldset_sql($sql, array_merge($params, $qverifiedparams));

        if (empty($verifiedanswerids)) {
            return $result;
        }

        // Process in batches
        $batches = array_chunk($verifiedanswerids, $batch_size);

        foreach ($batches as $batch) {
            try {
                list($batchsql, $batchparams) = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED);
                $DB->execute("DELETE FROM {question_answers} WHERE id $batchsql", $batchparams);
                $result['deleted'] += count($batch);
            } catch (\Exception $e) {
                $result['failed'] += count($batch);
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Delete duplicate questions (keeping one from each group)
     * Strategy: Keep the oldest question (lowest ID) from each duplicate group
     * Only deletes duplicates that are NOT used in any quiz
     *
     * @param array $questionids Array of question IDs to delete (duplicates only)
     * @param int $batch_size Batch size for deletion
     * @return array Result with 'deleted', 'failed', 'errors' keys
     */
    public static function delete_duplicate_questions($questionids, $batch_size = 1000) {
        global $DB;

        $result = [
            'deleted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        if (empty($questionids)) {
            return $result;
        }

        // Convert to integers
        $questionids = array_map('intval', $questionids);
        $questionids = array_unique($questionids);

        // Get used question bank entry IDs - don't delete questions that are used
        $usedentryids = self::get_used_question_bank_entry_ids();

        // Get question bank entry IDs for these questions and verify they are not used
        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT DISTINCT q.id, qbe.id AS entryid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE q.id $insql
                   AND q.parent = 0";
        $questionentries = $DB->get_records_sql($sql, $params);

        // Filter out used questions
        $todelete = [];
        foreach ($questionentries as $qe) {
            if (!in_array($qe->entryid, $usedentryids)) {
                $todelete[] = $qe->id;
            }
        }

        if (empty($todelete)) {
            return $result;
        }

        // Get duplicate keys for the questions we want to delete
        list($todeletesql, $todeleteparams) = $DB->get_in_or_equal($todelete, SQL_PARAMS_NAMED);
        $sql = "SELECT q.id,
                       q.name,
                       q.qtype,
                       q.questiontext,
                       CONCAT(q.name, '-', q.qtype, '-', MD5(q.questiontext)) AS duplicate_key
                  FROM {question} q
                 WHERE q.id $todeletesql";
        $duplicates = $DB->get_records_sql($sql, $todeleteparams);
        
        if (empty($duplicates)) {
            return $result;
        }
        
        // Get all questions with the same duplicate keys to find groups
        $duplicate_keys = array_unique(array_column($duplicates, 'duplicate_key'));
        list($keysql, $keyparams) = $DB->get_in_or_equal($duplicate_keys, SQL_PARAMS_NAMED);
        $sql = "SELECT q.id,
                       q.name,
                       q.qtype,
                       q.questiontext,
                       CONCAT(q.name, '-', q.qtype, '-', MD5(q.questiontext)) AS duplicate_key
                  FROM {question} q
                 WHERE CONCAT(q.name, '-', q.qtype, '-', MD5(q.questiontext)) $keysql
                   AND q.parent = 0
                 ORDER BY duplicate_key, q.id";
        $all_duplicates = $DB->get_records_sql($sql, $keyparams);
        
        // Group by duplicate_key
        $grouped = [];
        foreach ($all_duplicates as $question) {
            $key = $question->duplicate_key;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $question;
        }

        // For each group, keep the lowest ID, mark others for deletion (only if in $todelete)
        $verifiedtodelete = [];
        foreach ($grouped as $key => $group) {
            if (count($group) > 1) {
                // Sort by ID to get the lowest (oldest)
                usort($group, function($a, $b) {
                    return $a->id - $b->id;
                });
                
                // Keep the first (lowest ID), mark others for deletion if they are in $todelete
                for ($i = 1; $i < count($group); $i++) {
                    if (in_array($group[$i]->id, $todelete)) {
                        $verifiedtodelete[] = $group[$i]->id;
                    }
                }
            }
        }

        if (empty($verifiedtodelete)) {
            return $result;
        }

        // Use the existing safe deletion function
        return self::delete_unused_questions_safe($verifiedtodelete, true, $batch_size);
    }
}


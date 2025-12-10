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
 * Statistics class for detailed database statistics.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questioncleaner;

defined('MOODLE_INTERNAL') || die();

/**
 * Class statistics
 */
class statistics {

    /**
     * Get detailed database statistics for question-related tables
     *
     * @return array Detailed statistics
     */
    public static function get_detailed_statistics() {
        global $DB;

        $stats = [];

        // Question-related tables
        $tables = [
            'question' => 'Questions',
            'question_answers' => 'Question Answers',
            'question_versions' => 'Question Versions',
            'question_bank_entries' => 'Question Bank Entries',
            'question_categories' => 'Question Categories',
            'question_references' => 'Question References',
            'question_set_references' => 'Question Set References',
            'qtype_multichoice_options' => 'Multichoice Options',
            'qtype_shortanswer_options' => 'Shortanswer Options',
            'question_attempts' => 'Question Attempts',
            'question_attempt_steps' => 'Question Attempt Steps',
            'question_attempt_step_data' => 'Question Attempt Step Data',
            'question_response_count' => 'Question Response Count',
            'question_multianswer' => 'Question Multianswer',
        ];

        foreach ($tables as $tablename => $label) {
            try {
                $table = $DB->get_prefix() . $tablename;
                
                // Check if table exists
                if (!$DB->get_manager()->table_exists($tablename)) {
                    continue;
                }

                // Get row count
                $count = $DB->count_records($tablename);
                
                // Get table size (if MySQL/MariaDB)
                $size = self::get_table_size($tablename);
                
                $stats[$tablename] = [
                    'label' => $label,
                    'count' => $count,
                    'size' => $size,
                    'size_formatted' => self::format_bytes($size)
                ];
            } catch (\Exception $e) {
                // Skip if table doesn't exist or error
                $stats[$tablename] = [
                    'label' => $label,
                    'count' => 0,
                    'size' => 0,
                    'size_formatted' => 'N/A',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $stats;
    }

    /**
     * Get table size from database
     *
     * @param string $tablename Table name (without prefix)
     * @return int Size in bytes
     */
    private static function get_table_size($tablename) {
        global $DB;

        try {
            $dbtype = $DB->get_dbfamily();
            
            if ($dbtype === 'mysql' || $dbtype === 'mariadb') {
                $table = $DB->get_prefix() . $tablename;
                $sql = "SELECT 
                            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                        FROM information_schema.TABLES 
                        WHERE table_schema = DATABASE()
                        AND table_name = ?";
                $result = $DB->get_record_sql($sql, [$table]);
                if ($result) {
                    return (int)($result->size_mb * 1024 * 1024); // Convert to bytes
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return 0;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes Bytes
     * @return string Formatted string
     */
    public static function format_bytes($bytes) {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $size = round($bytes / pow(1024, $i), 2);

        return $size . ' ' . $units[$i];
    }

    /**
     * Get summary statistics
     *
     * @return array Summary
     */
    public static function get_summary() {
        global $DB;

        $summary = [
            'total_tables' => 0,
            'total_records' => 0,
            'total_size' => 0,
            'largest_table' => null,
            'largest_table_size' => 0
        ];

        $detailed = self::get_detailed_statistics();

        foreach ($detailed as $table => $data) {
            if (isset($data['error'])) {
                continue;
            }

            $summary['total_tables']++;
            $summary['total_records'] += $data['count'];
            $summary['total_size'] += $data['size'];

            if ($data['size'] > $summary['largest_table_size']) {
                $summary['largest_table_size'] = $data['size'];
                $summary['largest_table'] = $data['label'];
            }
        }

        $summary['total_size_formatted'] = self::format_bytes($summary['total_size']);

        return $summary;
    }
}


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
 * Cache helper for statistics.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questioncleaner;

defined('MOODLE_INTERNAL') || die();

/**
 * Class cache_helper
 */
class cache_helper {

    /**
     * Get cache instance
     *
     * @return \cache_application
     */
    private static function get_cache() {
        return \cache::make('local_questioncleaner', 'statistics');
    }

    /**
     * Get cached statistics
     *
     * @return array|false Statistics or false if not cached
     */
    public static function get_cached_statistics() {
        $cache = self::get_cache();
        return $cache->get('statistics');
    }

    /**
     * Set cached statistics
     *
     * @param array $statistics Statistics to cache
     * @return bool Success
     */
    public static function set_cached_statistics($statistics) {
        $cache = self::get_cache();
        $data = [
            'statistics' => $statistics,
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s')
        ];
        return $cache->set('statistics', $data);
    }

    /**
     * Clear cached statistics
     *
     * @return bool Success
     */
    public static function clear_cache() {
        $cache = self::get_cache();
        return $cache->delete('statistics');
    }

    /**
     * Get cache timestamp
     *
     * @return int|false Timestamp or false if not cached
     */
    public static function get_cache_timestamp() {
        $cached = self::get_cached_statistics();
        if ($cached && isset($cached['timestamp'])) {
            return $cached['timestamp'];
        }
        return false;
    }

    /**
     * Get cache date
     *
     * @return string|false Date or false if not cached
     */
    public static function get_cache_date() {
        $cached = self::get_cached_statistics();
        if ($cached && isset($cached['date'])) {
            return $cached['date'];
        }
        return false;
    }

    /**
     * Check if cache exists and is valid
     *
     * @param int $maxage Maximum age in seconds (default: 1 hour)
     * @return bool True if cache is valid
     */
    public static function is_cache_valid($maxage = 3600) {
        $timestamp = self::get_cache_timestamp();
        if ($timestamp === false) {
            return false;
        }
        return (time() - $timestamp) < $maxage;
    }
}


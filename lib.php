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
 * Library functions for questioncleaner plugin.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation items to Site administration
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context
 */
function local_questioncleaner_extend_navigation_user_settings($navigation, $course, $context) {
    // This function is called for user settings navigation
}

/**
 * Add navigation items to Site administration menu
 */
function local_questioncleaner_extend_navigation(global_navigation $navigation) {
    global $PAGE, $CFG;

    // Only show for users with capability
    if (!has_capability('local/questioncleaner:view', context_system::instance())) {
        return;
    }

    // Add to Site administration -> Reports
    $reportsnode = $navigation->find('reports', global_navigation::TYPE_ROOTNODE);
    if ($reportsnode) {
        $url = new moodle_url('/local/questioncleaner/index.php');
        $reportsnode->add(
            get_string('pluginname', 'local_questioncleaner'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'questioncleaner',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Add navigation items to settings navigation
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param context $context The context
 */
function local_questioncleaner_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    // Only show for users with capability
    if (!has_capability('local/questioncleaner:view', context_system::instance())) {
        return;
    }

    // Add to Site administration -> Reports
    if ($settingsnav instanceof settings_navigation) {
        $reportsnode = $settingsnav->find('reports', navigation_node::TYPE_CONTAINER);
        if ($reportsnode) {
            $url = new moodle_url('/local/questioncleaner/index.php');
            $reportsnode->add(
                get_string('pluginname', 'local_questioncleaner'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'questioncleaner',
                new pix_icon('i/report', '')
            );
        }
    }
}


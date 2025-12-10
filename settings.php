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
 * Settings for the questioncleaner plugin.
 *
 * @package     local_questioncleaner
 * @copyright   2024
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add main page to Site administration -> Plugins -> Local plugins
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_questioncleaner',
        get_string('pluginname', 'local_questioncleaner'),
        new moodle_url('/local/questioncleaner/index.php'),
        'local/questioncleaner:view'
    ));

    // Add settings page to Site administration -> Plugins -> Local plugins
    $settings = new admin_settingpage('local_questioncleaner_settings', get_string('settings', 'local_questioncleaner'));
    $ADMIN->add('localplugins', $settings);

    // Batch size setting
    $settings->add(new admin_setting_configtext(
        'local_questioncleaner/batch_size',
        get_string('batchsize', 'local_questioncleaner'),
        get_string('batchsize_desc', 'local_questioncleaner'),
        10000,
        PARAM_INT
    ));

    // Enable auto cleanup
    $settings->add(new admin_setting_configcheckbox(
        'local_questioncleaner/enable_auto_cleanup',
        get_string('enableautocleanup', 'local_questioncleaner'),
        get_string('enableautocleanup_desc', 'local_questioncleaner'),
        0
    ));
}


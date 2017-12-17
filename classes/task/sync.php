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
 * Cohort database tool - sync.
 *
 * @package    tool_cohortdatabase
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_cohortdatabase\task;
defined('MOODLE_INTERNAL') || die();
/**
 * Task class
 *
 * @package    tool_cohortdatabase
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync extends \core\task\scheduled_task {
    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('sync', 'tool_cohortdatabase');
    }
    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        if (empty($CFG->showcrondebugging)) {
            $trace = new \null_progress_trace();
        } else {
            $trace = new \text_progress_trace();
        }
        $cohortdatabase = new \tool_cohortdatabase_sync();
        return $cohortdatabase->sync($trace);
    }
}

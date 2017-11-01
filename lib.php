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
 * Cohort database sync plugin
 *
 * This plugin synchronises cohorts with external database table.
 *
 * @package    tool_cohortdatabase
 * @author     Dan Marsden
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class tool_cohortdatabase_sync {
    /**
     * @var stdClass config for this plugin
     */
    protected $config;

    /**
     * Performs a full sync with external database.
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 4 db read failure
     */
    public function sync(progress_trace $trace) {
        global $DB;
        $trace->output('Starting cohort synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $this->config = get_config('tool_cohortdatabase');

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external cohort database');
            $trace->finished();
            return 1;
        }

        // Set some vars for better code readability.
        $cohorttable           = $this->config->remotecohorttable;
        $localuserfield        = $this->config->localuserfield;
        $remoteuserfield       = $this->config->remoteuserfield;
        $remotecohortidfield   = $this->config->remotecohortidfield;
        $remotecohortnamefield = $this->config->remotecohortnamefield;
        $remotecohortdescfield = $this->config->remotecohortdescfield;
        $removeaction          = $this->config->removeaction;

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $remoteuserfield_l  = strtolower($remoteuserfield);
        $remotecohortidfield_l = strtolower($remotecohortidfield);
        $remotecohortnamefield_l  = strtolower($remotecohortnamefield);
        $remotecohortdescfield_l  = strtolower($remotecohortdescfield);


        if (empty($cohorttable) || empty($localuserfield) || empty($remoteuserfield) || empty($remotecohortidfield)) {
            $trace->output('External cohort config not complete.');
            return 1;
        }
        // Get list of current cohorts indexed by idnumber.
        $cohorts = $DB->get_records('cohort', array('component' => 'tool_cohortdatabase'), '', 'idnumber, id, name, description');
        $sysctxt = context_system::instance();

        // First check/create all cohorts.
        $cohortsupdated = 0;
        $newcohorts = array();
        $now = time();
        $sqlfields = array($remotecohortidfield, $remotecohortnamefield);
        if (!empty($remotecohortdescfield)) {
            $sqlfields[] = $remotecohortdescfield;
        }
        $sql = $this->db_get_sql($cohorttable, array(), $sqlfields, true);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    if (empty($fields[$remotecohortidfield_l]) or empty($fields[$remotecohortnamefield_l])) {
                        $trace->output('error: invalid external cohort record, id and name are mandatory: ' . json_encode($fields), 1); // Hopefully every geek can read JS, right?
                        continue;
                    }

                    if (!empty($cohorts[$fields[$remotecohortidfield_l]])) {
                        // If this cohort exists, check to see if it needs name/description updated.
                        $existingcohort = $cohorts[$fields[$remotecohortidfield_l]];
                        if ($existingcohort->name <> $fields[$remotecohortnamefield_l] ||
                            (!empty($remotecohortdescfield) && $existingcohort->description <> $fields[$remotecohortdescfield_l])) {
                            $existingcohort->name = $fields[$remotecohortnamefield_l];
                            if (!empty($remotecohortdescfield)) {
                                $existingcohort->description = $fields[$remotecohortdescfield_l];
                            }
                            $DB->update_record('cohort', $existingcohort);
                            $cohortsupdated++;
                        }
                    } else  {
                        // Need to create this cohort.
                        $newcohort = new stdClass();
                        $newcohort->name = $fields[$remotecohortnamefield_l];
                        $newcohort->idnumber = $fields[$remotecohortidfield_l];
                        if (!empty($remotecohortdescfield)) {
                            $newcohort->description = $fields[$remotecohortdescfield_l];
                        }
                        $newcohort->component = 'tool_cohortdatabase';
                        $newcohort->timecreated = $now;
                        $newcohort->timemodified = $now;
                        $newcohort->visible = 1;
                        $newcohort->contextid = $sysctxt->id;
                        $newcohort->descriptionformat = FORMAT_HTML;

                        // Put into array so we can use insert_records in bulk.
                        $newcohorts[] = $newcohort;
                    }
                }
            }
            $rs->Close();
            $trace->output('Updated '.$cohortsupdated.' cohort names/descriptions');
            // Check to see if there are cohorts to insert
            if (!empty($newcohorts)) {
                $DB->insert_records('cohort', $newcohorts);
                $trace->output('Bulk insert of '.count($newcohorts).' new cohorts');
                // We don't need $newcohorts anymore - set to null to help with overall php memory usage.
                $newcohorts = null;
            }
        } else {
            $extdb->Close();
            $trace->output('Error reading data from the external cohort table');
            $trace->finished();
            return 4;
        }

        $trace->output('Starting cohort database user sync');

        // Next - add/remove users to cohorts.

        // This iterates over each cohort - it might be better to batch process these and do multiple cohorts at a time,
        // but for now we process each cohort individually.

        $newusers = array();
        foreach ($cohorts as $cohort) {
            // Current users should be array of configured key == $USER->id
            $sql = "SELECT u.".$localuserfield.", c.userid
                      FROM {user} u
                      JOIN {cohort_members} c ON c.userid = u.id
                     WHERE c.id = ?";
            $currentusers = $DB->get_records_sql($sql, array($cohort->id));

            // Now get records from external table.
            $sqlfields = array($remoteuserfield);
            $sql = $this->db_get_sql($cohorttable, array($remotecohortidfield => $cohort->idnumber), $sqlfields, true);
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $fields = array_change_key_case($fields, CASE_LOWER);
                        $fields = $this->db_decode($fields);
                        if (empty($fields[$remoteuserfield_l])) {
                            $trace->output('error: invalid external cohort record, user fields is mandatory: ' . json_encode($fields), 1); // Hopefully every geek can read JS, right?
                            continue;
                        }
                        if (!empty($currentusers[$fields[$remoteuserfield_l]])) {
                            // This user is already a member of the cohort.
                            unset($currentusers[$fields[$remoteuserfield_l]]);
                        } else {
                            // Add user to cohort.
                            $newuser = new stdClass();
                            $newuser->cohortid  = $cohort->id;
                            $newuser->userid    = $currentusers[$fields[$remoteuserfield_l]];
                            $newuser->timeadded = time();
                            $newusers[] = $newuser;
                        }
                    }
                }
            }
            if (!empty($currentusers)) {
                // Delete users no longer in cohort.
                list($sql, $params) = $DB->get_in_or_equal($currentusers);
                $sql .= " AND cohortid = ?";
                $params[] = $cohort->id;
                $DB->delete_records_select('cohort_members', $sql);
                $trace->output('Bulk delete of '.count($currentusers).' users from cohortid'.$cohort->id);
            }
        }
        // We do this at the very end so we can batch process all inserts for speed.
        if (!empty($newusers)) {
            $DB->insert_records('cohort_members', $newusers);
            $trace->output('Bulk insert of '.count($newusers).' users');
        }

        $extdb->Close();
        return 0;
    }

    /**
     * Test plugin settings, print info to output.
     */
    function test_settings() {
        global $CFG, $OUTPUT;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        raise_memory_limit(MEMORY_HUGE);

        $this->config = get_config('tool_cohortdatabase');

        $cohorttable = $this->config->remotecohorttable;

        if (empty($cohorttable)) {
            echo $OUTPUT->notification('External cohort table not specified.', 'notifyproblem');
            return;
        }

        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        $olddebugdb = $this->config->debugdb;
        $this->config->debugdb = 1;
        error_reporting($CFG->debug);

        $adodb = $this->db_init();

        if (!$adodb or !$adodb->IsConnected()) {
            $this->config->debugdb = $olddebugdb;
            $CFG->debug = $olddebug;
            ini_set('display_errors', $olddisplay);
            error_reporting($CFG->debug);
            ob_end_flush();

            echo $OUTPUT->notification('Cannot connect the database.', 'notifyproblem');
            return;
        }

        if (!empty($cohorttable)) {
            $rs = $adodb->Execute("SELECT *
                                     FROM $cohorttable");
            if (!$rs) {
                echo $OUTPUT->notification('Can not read external cohort table.', 'notifyproblem');

            } else if ($rs->EOF) {
                echo $OUTPUT->notification('External cohort table is empty.', 'notifyproblem');
                $rs->Close();

            } else {
                $fields_obj = $rs->FetchObj();
                $columns = array_keys((array)$fields_obj);

                echo $OUTPUT->notification('External cohort table contains following columns:<br />'.implode(', ', $columns), 'notifysuccess');
                $rs->Close();
            }
        }

        $adodb->Close();

        $this->config->debugdb = $olddebugdb;
        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
        ob_end_flush();
    }

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */

    function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->config->dbtype);
        if ($this->config->debugdb) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->config->dbhost, $this->config->dbuser, $this->config->dbpass,
                $this->config->dbname, true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->config->dbsetupsql) {
            $extdb->Execute($this->config->dbsetupsql);
        }
        return $extdb;
    }
    protected function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    protected function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

}




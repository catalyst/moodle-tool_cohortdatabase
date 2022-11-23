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

/**
 * Cohortdatabase tool class
 *
 * @package    tool_cohortdatabase
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

        $this->config = get_config('tool_cohortdatabase');

        // Check if it is configured.
        if (empty($this->config->dbtype) || empty($this->config->dbhost)) {
            $trace->finished();
            return 1;
        }

        $trace->output('Starting cohort synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        // Set some vars for better code readability.
        $cohorttable           = trim($this->config->remotecohorttable);
        $localuserfield        = trim($this->config->localuserfield);
        $remoteuserfield       = trim($this->config->remoteuserfield);
        $remotecohortidfield   = trim($this->config->remotecohortidfield);
        $remotecohortnamefield = trim($this->config->remotecohortnamefield);
        $remotecohortdescfield = trim($this->config->remotecohortdescfield);
        $removeaction          = trim($this->config->removeaction); // Should rename this (0 = remove, 1 = keep).
        $createusers           = trim($this->config->createusers);
        $remotecreateusersusername = trim($this->config->createusers_username);
        $remotecreateusersemail = trim($this->config->createusers_email);
        $remotecreateusersfirstname = trim($this->config->createusers_firstname);
        $remotecreateuserslastname = trim($this->config->createusers_lastname);
        $remotecreateusersidnumber = trim($this->config->createusers_idnumber);
        $remotecreateusersauth = trim($this->config->createusers_auth);

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $remoteuserfieldl  = strtolower($remoteuserfield);
        $remotecohortidfieldl = strtolower($remotecohortidfield);
        $remotecohortnamefieldl = strtolower($remotecohortnamefield);
        $remotecohortdescfieldl = strtolower($remotecohortdescfield);

        if (empty($cohorttable) || empty($localuserfield) || empty($remoteuserfield) || empty($remotecohortidfield)) {
            $trace->output('External cohort config not complete.');
            $trace->finished();
            return 1;
        }

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external cohort database');
            $trace->finished();
            return 1;
        }

        // Sanity check - make sure external table has the expected number of records before we trigger the sync.
        $hasenoughrecords = false;
        $count = 0;
        $minrecords = $this->config->minrecords;
        if (!empty($minrecords)) {
            $sql = "SELECT count(*) FROM $cohorttable";
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $count = array_pop($fields);
                        if ($count >= $minrecords) {
                            $hasenoughrecords = true;
                        }
                    }
                }
            }
        }
        if (!$hasenoughrecords) {
            $message = "Failed to sync because the external db returned $count records and the minimum required is $minrecords";
            $this->email_admins($message);
            mtrace($message);
            $trace->finished();
            return 1;
        }

        // Get list of current cohorts indexed by idnumber.
        $cohorts = $this->get_cohorts();

        $sysctxt = context_system::instance();

        // First check/create all cohorts.
        $cohortsupdated = 0;
        $newcohorts = array();
        $newcohortids = array(); // List of idnumbers for newcohorts.
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
                    if (empty($fields[$remotecohortidfieldl]) || empty($fields[$remotecohortnamefieldl])) {
                        $trace->output('error: invalid external cohort record, id and name are mandatory: '
                         . json_encode($fields), 1); // Hopefully every geek can read JS, right?
                        continue;
                    }
                    // Trim some values.
                    $fields[$remotecohortidfieldl] = trim($fields[$remotecohortidfieldl]);
                    $fields[$remotecohortnamefieldl] = trim($fields[$remotecohortnamefieldl]);
                    if (!empty($remotecohortdescfield)) {
                        $fields[$remotecohortdescfieldl] = trim($fields[$remotecohortdescfieldl]);
                    }

                    if (!empty($cohorts[$fields[$remotecohortidfieldl]])) {
                        // If this cohort exists, check to see if it needs name/description updated.
                        $existingcohort = $cohorts[$fields[$remotecohortidfieldl]];
                        if ($existingcohort->name <> $fields[$remotecohortnamefieldl] ||
                            (!empty($remotecohortdescfield) && !empty($this->config->remotecohortdescupdate) &&
                             $existingcohort->description <> $fields[$remotecohortdescfieldl])) {
                                $existingcohort->name = $fields[$remotecohortnamefieldl];
                            if (!empty($remotecohortdescfield) && !empty($this->config->remotecohortdescupdate)) {
                                $existingcohort->description = $fields[$remotecohortdescfieldl];
                            }

                            $DB->update_record('cohort', $existingcohort);
                            $cohortsupdated++;
                        }
                    } else {
                        // Need to create this cohort.
                        $newcohort = new stdClass();
                        $newcohort->idnumber = trim($fields[$remotecohortidfieldl]);
                        if (!in_array($newcohort->idnumber, $newcohortids)) {
                            $newcohort->name = trim($fields[$remotecohortnamefieldl]);

                            if (!empty($remotecohortdescfield)) {
                                $newcohort->description = trim($fields[$remotecohortdescfieldl]);
                            }
                            $newcohort->component = 'tool_cohortdatabase';
                            $newcohort->timecreated = $now;
                            $newcohort->timemodified = $now;
                            $newcohort->visible = 1;
                            $newcohort->contextid = $sysctxt->id;
                            $newcohort->descriptionformat = FORMAT_HTML;

                            // Put into array so we can use insert_records in bulk.
                            $newcohorts[] = $newcohort;
                            $newcohortids[] = $newcohort->idnumber;
                        }
                    }
                }
            }
            $rs->Close();
            $trace->output('Updated '.$cohortsupdated.' cohort names/descriptions');
            // Check to see if there are cohorts to insert.
            if (!empty($newcohorts)) {
                $DB->insert_records('cohort', $newcohorts);
                $trace->output('Bulk insert of '.count($newcohorts).' new cohorts');
            }
        } else {
            $extdb->Close();
            $message = 'Cohort sync failed: Error reading data from the external cohort table';
            $this->email_admins($message);
            $trace->output($message);
            $trace->finished();
            return 4;
        }

        if (!empty($newcohorts)) {
            // New cohorts have been added, get the full updated list.
            $cohorts = $this->get_cohorts();
            // We don't need $newcohorts anymore - set to null to help with overall php memory usage.
            $newcohorts = null;
        }

        $trace->output('Starting cohort database user sync');

        // Next - add/remove users to cohorts.

        // This iterates over each cohort - it might be better to batch process these and do multiple cohorts at a time,
        // but for now we process each cohort individually.
        $missingusers = array(); // Users that need to be created.
        $newmembers = array();
        $needusers = array(); // Contains a list of external user ids we need to get for insert.
        $countdeletes = 0;
        foreach ($cohorts as $cohort) {
            // Current users should be array of configured key == $USER->id.
            $sql = "SELECT u.".$localuserfield.", c.userid
                      FROM {user} u
                      JOIN {cohort_members} c ON c.userid = u.id
                     WHERE c.cohortid = ?";
            $currentusers = $DB->get_records_sql_menu($sql, array($cohort->id));
            $currentusers = array_change_key_case($currentusers); // Convert key to lowercase.

            $foundexternalcohort = false;
            // Now get records from external table.
            $sqlfields = array($remoteuserfield);
            $sql = $this->db_get_sql($cohorttable, array($remotecohortidfield => $cohort->idnumber), $sqlfields, true);
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $fields = array_change_key_case($fields, CASE_LOWER);
                        $fields = $this->db_decode($fields);
                        $fields[$remoteuserfieldl] = trim($fields[$remoteuserfieldl]);
                        if (empty($fields[$remoteuserfieldl])) {
                            $trace->output('error: invalid external cohort record, user fields is mandatory: ' .
                              json_encode($fields), 1); // Hopefully every geek can read JS, right?
                            continue;
                        }
                        $foundexternalcohort = true;
                        $remotefield = strtolower($fields[$remoteuserfieldl]);
                        if (!empty($currentusers[$remotefield])) {
                            // This user is already a member of the cohort.
                            unset($currentusers[$remotefield]);
                        } else {
                            // Add user to cohort.
                            $newmember = new stdClass();
                            $newmember->cohortid  = $cohort->id;
                            $newmember->userid    = $fields[$remoteuserfieldl];
                            $newmember->timeadded = time();
                            $newmembers[] = $newmember;
                            $needusers[] = $remotefield;
                        }
                    }
                }
            }

            // If the cohort isn't found in external source, we only remove the cohort and users depending on settings.
            if (!$foundexternalcohort && !empty($this->config->emptycohortremoval)) {
                if ((int)$this->config->emptycohortremoval == 1) {
                    // If emptycohortremoval is set to 1, allow removal of empty cohorts.
                    $foundexternalcohort = true;
                } else if ($this->config->emptycohortremoval == 2) {
                    // If emptycohortremoval is set to 2, allow removal if not in use.
                    if (!$DB->record_exists('enrol', ['enrol' => 'cohort', 'customint1' => $cohort->id])) {
                        // Check if this cohort is used by an enrolment sync.
                        $foundexternalcohort = true;
                    }
                }
            }
            if ($foundexternalcohort && empty($removeaction) && !empty($currentusers)) {
                $todelete = count($currentusers);
                if (!empty($this->config->maxremovals) && ($countdeletes + $todelete) > ($this->config->maxremovals)) {
                    $a = new \stdClass();
                    $a->countdeletes = $countdeletes;
                    $a->todelete = $todelete;
                    $a->maxremovals = $this->config->maxremovals;
                    $a->cohortid = $cohort->id;
                    $message = get_string('maxremovalsexceeded', 'tool_cohortdatabase', $a);
                    $this->email_admins($message);
                    mtrace($message);
                    $trace->finished();
                    return 1;
                }
                // Delete users no longer in cohort.
                // Using core function for this is very slow (one by one) - use bulk delete instead.
                list($csql, $params) = $DB->get_in_or_equal($currentusers);
                $sql = "userid $csql AND cohortid = ?";
                $params[] = $cohort->id;
                $DB->delete_records_select('cohort_members', $sql, $params);
                $trace->output('Bulk delete of '.count($currentusers).' users from cohortid'.$cohort->id);

                // Trigger removed events - used by enrolment plugins etc.
                foreach ($currentusers as $removedid) {
                    $countdeletes++;
                    $event = \core\event\cohort_member_removed::create(array(
                        'context' => context::instance_by_id($cohort->contextid),
                        'objectid' => $cohort->id,
                        'relateduserid' => $removedid,
                    ));
                    $event->add_record_snapshot('cohort', $cohort);
                    $event->trigger();
                }
            }
        }
        // We do this at the very end so we can batch process all inserts for speed.
        if (!empty($newmembers)) {
            // Split into chunks to prevent long proceses from dying and nothing being done.
            $chunksize = 500; // Using 500 as this is the max allowed in one chunk for insert_records in pg anyway.
            $memberchunks = array_chunk($newmembers, $chunksize, true);
            foreach ($memberchunks as $members) {
                // First we need to map the userid in external table with userid in moodle..
                foreach ($members as $id => $newmember) {
                    $sql = "SELECT ".$localuserfield.", id
                            FROM {user} u
                            WHERE ".
                            $DB->sql_equal($localuserfield, '?', false);
                    $currentuser = $DB->get_record_sql($sql, [$newmember->userid]);
                    if (empty($currentuser)) {
                        // This is a new user, we need to create.
                        $missingusers[$newmember->userid] = $newmember->userid;
                        unset($members[$id]); // Cannot insert these members yet.
                        $trace->output('Could not find user with '.$localuserfield.' = '.$newmember->userid);
                    } else {
                        $members[$id]->userid = $currentuser->id;
                    }
                }

                // Now check if users are supposed to be created.
                $createdusers = 0;
                if ($createusers && !empty($missingusers)) {
                    // Get user info from external db.
                    $fields = array($remotecreateusersusername, $remotecreateusersemail,
                                    $remotecreateusersfirstname, $remotecreateuserslastname);
                    if (!empty($remotecreateusersidnumber)) {
                        $fields[] = $remotecreateusersidnumber;
                    }
                    $sql = "SELECT DISTINCT ".implode(",", $fields)."
                              FROM $cohorttable WHERE $remoteuserfield IN ('".implode("','", $missingusers)."')";
                    if ($rs = $extdb->Execute($sql)) {
                        if (!$rs->EOF) {
                            while ($fields = $rs->FetchRow()) {
                                $user = create_user_record($fields[$remotecreateusersusername], '', $remotecreateusersauth);
                                $user->email = $fields[$remotecreateusersemail];
                                $user->firstname = $fields[$remotecreateusersfirstname];
                                $user->lastname = $fields[$remotecreateuserslastname];
                                if (!empty($remotecreateusersidnumber)) {
                                    $user->idnumber = $fields[$remotecreateusersidnumber];
                                }
                                user_update_user($user, false, false);
                                $createdusers++;
                            }
                        }
                    }
                    $trace->output("Created $createdusers new users");
                }

                // Now insert the records (using core function for this is very slow - use bulk insert instead.
                $DB->insert_records('cohort_members', $members);
                $trace->output('Bulk insert of '.count($members).' new members');

                // Trigger events used by cohort sync plugins etc.
                foreach ($members as $newm) {
                    $event = \core\event\cohort_member_added::create(array(
                        'context' => context_system::instance(),
                        'objectid' => $newm->cohortid,
                        'relateduserid' => $newm->userid,
                    ));
                    $event->trigger();
                }
            }
        }

        $extdb->Close();
        $trace->finished();
        $this->cleanup();
        return 0;
    }

    /**
     * Test plugin settings, print info to output.
     */
    public function test_settings() {
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

        if (!$adodb || !$adodb->IsConnected()) {
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
                $fieldsobj = $rs->FetchObj();
                $columns = array_keys((array)$fieldsobj);

                echo $OUTPUT->notification('External cohort table contains following columns:<br />'.
                    implode(', ', $columns), 'notifysuccess');
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
    public function db_init() {
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

    /**
     * Encode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_encode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) || $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    /**
     * Decode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_decode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) || $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    /**
     * Generate SQL required based on params.
     *
     * @param string $table - name of table
     * @param array $conditions - conditions for select.
     * @param array $fields - fields to return
     * @param boolean $distinct
     * @param string $sort
     * @return string
     */
    protected function db_get_sql($table, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";

        return $sql;
    }

    /**
     * Add slashes to text.
     *
     * @param string $text
     * @return string
     */
    protected function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->config->dbsybasequoting) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }

    /**
     * Helper function to email users on failure.
     * @param string $message
     */
    protected function email_admins($message) {
        global $DB, $CFG;
        if (empty($this->config->erroremails)) {
            return;
        } else if ($this->config->erroremails == 'support') {
            $user = \core_user::get_support_user();
            email_to_user($user, $user, 'tool_cohortdatabase error', $message);
        } else if ($this->config->erroremails == 'alladmins') {
            $users = $DB->get_records_list('user', 'id', explode(',', $CFG->siteadmins));
            foreach ($users as $user) {
                email_to_user($user, $user, 'tool_cohortdatabase error', $message);
            }
        }
    }

    /**
     * Function to delete empty unused cohorts created by this plugin.
     *
     * @return void
     */
    protected function cleanup() {
        global $DB;
        // Clean up empty cohorts created by this plugin that are not in use by enrolment plugins.
        $sql = "DELETE FROM {cohort}
                 WHERE id in (SELECT c.id
                         FROM (SELECT * FROM {cohort}) AS c
                           LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
                               WHERE component = 'tool_cohortdatabase' AND cm.id is null
                                     AND c.id NOT IN (select customint1 from {enrol} where enrol = 'cohort'))";
        $DB->execute($sql);
    }

    /**
     * Get cohorts indexed by idnumber for easy processing.
     *
     * @return array()
     */
    protected function get_cohorts() {
        global $DB;
        $cohortrecords = $DB->get_records('cohort', array('component' => 'tool_cohortdatabase'), '',
         'idnumber, id, name, description, contextid, descriptionformat, visible, component, timecreated, timemodified, theme');
        $cohorts = [];
        foreach ($cohortrecords as $cohort) {
            // Index the cohorts using idnumber for easy processing.
            $cohorts[$cohort->idnumber] = $cohort;
        }
        return $cohorts;
    }
}

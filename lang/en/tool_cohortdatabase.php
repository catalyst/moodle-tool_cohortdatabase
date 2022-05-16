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
 * Strings for component 'tool_cohortdatabase', language 'en'.
 *
 * @package   tool_cohortdatabase
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['dbencoding'] = 'Database encoding';
$string['dbhost'] = 'Database host';
$string['dbhost_desc'] = 'Type database server IP address or host name. Use a system DSN name if using ODBC.';
$string['dbname'] = 'Database name';
$string['dbname_desc'] = 'Leave empty if using a DSN name in database host.';
$string['dbpass'] = 'Database password';
$string['dbsetupsql'] = 'Database setup command';
$string['dbsetupsql_desc'] = 'SQL command for special database setup, often used to setup communication encoding - example for MySQL and PostgreSQL: <em>SET NAMES \'utf8\'</em>';
$string['dbsybasequoting'] = 'Use sybase quotes';
$string['dbsybasequoting_desc'] = 'Sybase style single quote escaping - needed for Oracle, MS SQL and some other databases. Do not use for MySQL!';
$string['dbtype'] = 'Database driver';
$string['dbtype_desc'] = 'ADOdb database driver name, type of the external database engine.';
$string['dbuser'] = 'Database user';
$string['debugdb'] = 'Debug ADOdb';
$string['debugdb_desc'] = 'Debug ADOdb connection to external database - use when getting empty page during login. Not suitable for production sites!';
$string['localuserfield'] = 'Local user field';
$string['pluginname'] = 'Cohort external database';
$string['pluginname_desc'] = 'You can use an external database (of nearly any kind) to control your cohorts.';
$string['remotecohorttable'] = 'Remote user cohort table';
$string['remotecohorttable_desc'] = 'Specify the name of the table that contains list of user cohorts.';
$string['remoteuserfield'] = 'Remote user field';
$string['remoteuserfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the user table.';
$string['remotecohortidfield'] = 'Remote cohort id field';
$string['remotecohortidfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the cohort table.';
$string['remotecohortnamefield'] = 'Remote cohort name field';
$string['remotecohortnamefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the cohort table.';
$string['remotecohortdescfield'] = 'Remote cohort description field';
$string['remotecohortdescfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the cohort table.';
$string['settingsheaderdb'] = 'External database connection';
$string['settingsheaderlocal'] = 'Local field mapping';
$string['settingsheaderremote'] = 'Remote cohort sync';
$string['removefromcohort'] = 'Remove from cohort';
$string['keepincohort'] = 'Keep in cohort';
$string['removedaction_desc'] = 'Select action to carry out when user disappears from external cohort source. Please note that some user data and settings are purged from course if this is synced with course unenrolment.';
$string['removedaction'] = 'External remove action';
$string['settingscreateusers'] = 'Create users';
$string['settingscreateusers_desc'] = 'Create users if they do not exist.';
$string['createusers_username'] = 'Remote username';
$string['createusers_username_desc'] = 'The name of the field in the remote table that contains the username';
$string['createusers_email'] = 'Remote email';
$string['createusers_email_desc'] = 'The name of the field in the remote table that contains the email';
$string['createusers_firstname'] = 'Remote firstname';
$string['createusers_firstname_desc'] = 'The name of the field in the remote table that contains the firstname';
$string['createusers_lastname'] = 'Remote lastname';
$string['createusers_lastname_desc'] = 'The name of the field in the remote table that contains the lastname';
$string['createusers_idnumber'] = 'Remote idnumber';
$string['createusers_idnumber_desc'] = 'The name of the field in the remote table that contains the idnumber';
$string['createusers_auth'] = 'Auth';
$string['createusers_auth_desc'] = 'The authentication type to set for these users.';
$string['sync'] = 'Sync cohorts with external database';
$string['minrecords'] = 'Minimum records';
$string['minrecords_desc'] = 'Prevent the sync from running if the number of records returned in the external table is below this number (helps to prevent removal of users when the external table is empty).';
$string['maxremovals'] = 'Maximum removals';
$string['maxremovals_desc'] = 'Stop the sync from running and email admins if the number of cohort removals exceeds this number (helps to prevent accidental removal of lots of users). If set to 0 it will be ignored.';
$string['maxremovalsexceeded'] = 'The cohort sync process has removed {$a->countdeletes} members from previous cohorts,
and is now trying to delete {$a->todelete} members from cohortid: {$a->cohortid}
this exceeds the max removal threshold of {$a->maxremovals} so the process was stopped.
If this is unexpected you should check the validity of the data or it will attempt to continue removal on next cron.';
$string['supportuser'] = 'Support user';
$string['alladmins'] = 'All site admins';
$string['disabled'] = 'Disabled';
$string['erroremails'] = 'Email on error';
$string['erroremails_desc'] = 'When an error during sync occurs, email these users';
$string['privacy:metadata'] = 'The Cohort database plugin does not store any personal data.';
$string['emptycohortremoval'] = 'Delete empty cohorts';
$string['emptycohortremoval_desc'] = 'If set to no, if a cohort does not exist in the external source, it will ignore the sync process for that cohort. This is done for safety reasons - in case the external db connection fails weirdly and returns an empty result for a specific cohort.
If set to not in use, it will only delete if the cohort is not attached to a cohort sync enrolment instance in a course.';
$string['ifnotinuse'] = 'If not attached to a cohort enrolment.';
$string['remotecohortdescupdate'] = 'Update description on sync';
$string['remotecohortdescupdate_desc'] = 'If set to yes, the description field will be updated in Moodle if it is different in the external Db.';

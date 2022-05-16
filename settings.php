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
 * Cohort database plugin settings and presets.
 *
 * @package    tool_cohortdatabase
 * @author     Dan Marsden
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('tool_cohortdatabase', new lang_string('pluginname', 'tool_cohortdatabase'),
        'moodle/site:config', false);
    $ADMIN->add('tools', $settings);
    // General settings.
    $settings->add(new admin_setting_heading('tool_cohortdatabase_settings', '',
        get_string('pluginname_desc', 'tool_cohortdatabase')));

    $settings->add(new admin_setting_heading('tool_cohortdatabase_exdbheader',
        get_string('settingsheaderdb', 'tool_cohortdatabase'), ''));

    $options = array('', "pdo", "pdo_mssql", "pdo_sqlsrv", "access", "ado_access", "ado", "ado_mssql", "borland_ibase",
        "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql",
        "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64",
        "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/dbtype',
        get_string('dbtype', 'tool_cohortdatabase'),
        get_string('dbtype_desc', 'tool_cohortdatabase'), '', $options));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/dbhost',
        get_string('dbhost', 'tool_cohortdatabase'),
        get_string('dbhost_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/dbuser',
        get_string('dbuser', 'tool_cohortdatabase'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('tool_cohortdatabase/dbpass',
        get_string('dbpass', 'tool_cohortdatabase'), '', ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/dbname',
        get_string('dbname', 'tool_cohortdatabase'),
        get_string('dbname_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/dbencoding',
        get_string('dbencoding', 'tool_cohortdatabase'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/dbsetupsql',
        get_string('dbsetupsql', 'tool_cohortdatabase'),
        get_string('dbsetupsql_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_cohortdatabase/dbsybasequoting',
        get_string('dbsybasequoting', 'tool_cohortdatabase'),
        get_string('dbsybasequoting_desc', 'tool_cohortdatabase'), 0));

    $settings->add(new admin_setting_configcheckbox('tool_cohortdatabase/debugdb',
        get_string('debugdb', 'tool_cohortdatabase'),
        get_string('debugdb_desc', 'tool_cohortdatabase'), 0));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/minrecords',
        get_string('minrecords', 'tool_cohortdatabase'),
        get_string('minrecords_desc', 'tool_cohortdatabase'), 10));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/maxremovals',
        get_string('maxremovals', 'tool_cohortdatabase'),
        get_string('maxremovals_desc', 'tool_cohortdatabase'), 500));

    $options = ['' => get_string('disabled', 'tool_cohortdatabase'),
                'support' => get_string('supportuser', 'tool_cohortdatabase'),
                'alladmins' => get_string('alladmins', 'tool_cohortdatabase')];

    $settings->add(new admin_setting_configselect('tool_cohortdatabase/erroremails',
        get_string('erroremails', 'tool_cohortdatabase'),
        get_string('erroremails_desc', 'tool_cohortdatabase'), '', $options));

    $settings->add(new admin_setting_heading('tool_cohortdatabase_localheader',
        get_string('settingsheaderlocal', 'tool_cohortdatabase'), ''));

    // Only local users if username selected, no mnet users!
    $options = array('id' => 'id', 'idnumber' => 'idnumber', 'email' => 'email', 'username' => 'username');
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/localuserfield',
        get_string('localuserfield', 'tool_cohortdatabase'), '', 'idnumber', $options));

    $settings->add(new admin_setting_heading('tool_cohortdatabase_remoteheader',
        get_string('settingsheaderremote', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/remotecohorttable',
        get_string('remotecohorttable', 'tool_cohortdatabase'),
        get_string('remotecohorttable_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/remoteuserfield',
        get_string('remoteuserfield', 'tool_cohortdatabase'),
        get_string('remoteuserfield_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/remotecohortidfield',
        get_string('remotecohortidfield', 'tool_cohortdatabase'),
        get_string('remotecohortidfield_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/remotecohortnamefield',
        get_string('remotecohortnamefield', 'tool_cohortdatabase'),
        get_string('remotecohortnamefield_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/remotecohortdescfield',
        get_string('remotecohortdescfield', 'tool_cohortdatabase'),
        get_string('remotecohortdescfield_desc', 'tool_cohortdatabase'), ''));

    $options = array(0 => get_string('no'),
                     1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/remotecohortdescupdate',
        get_string('remotecohortdescupdate', 'tool_cohortdatabase'),
        get_string('remotecohortdescupdate_desc', 'tool_cohortdatabase'), 0, $options));

    $options = array(0  => get_string('removefromcohort', 'tool_cohortdatabase'),
                     1  => get_string('keepincohort', 'tool_cohortdatabase'));
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/removeaction',
        get_string('removedaction', 'tool_cohortdatabase'),
        get_string('removedaction_desc', 'tool_cohortdatabase'), 0, $options));

    $options = [0 => get_string('no'),
                1 => get_string('yes'),
                2 => get_string('ifnotinuse', 'tool_cohortdatabase')];
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/emptycohortremoval',
        get_string('emptycohortremoval', 'tool_cohortdatabase'),
        get_string('emptycohortremoval_desc', 'tool_cohortdatabase'), 0, $options));

    $settings->add(new admin_setting_heading('tool_cohortdatabase_createusers',
        get_string('settingscreateusers', 'tool_cohortdatabase'), ''));

    $options = array(0 => get_string('no'),
                     1 => get_string('yes'));
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/createusers',
        get_string('settingscreateusers', 'tool_cohortdatabase'),
        get_string('settingscreateusers_desc', 'tool_cohortdatabase'), 0, $options));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/createusers_username',
        get_string('createusers_username', 'tool_cohortdatabase'),
        get_string('createusers_username_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/createusers_email',
        get_string('createusers_email', 'tool_cohortdatabase'),
        get_string('createusers_email_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/createusers_firstname',
        get_string('createusers_firstname', 'tool_cohortdatabase'),
        get_string('createusers_firstname_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/createusers_lastname',
        get_string('createusers_lastname', 'tool_cohortdatabase'),
        get_string('createusers_lastname_desc', 'tool_cohortdatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_cohortdatabase/createusers_idnumber',
        get_string('createusers_idnumber', 'tool_cohortdatabase'),
        get_string('createusers_idnumber_desc', 'tool_cohortdatabase'), ''));

    // Get list of Auth plugins and make the key => value.
    $plugins = core_component::get_plugin_list('auth');
    $keys = array_keys($plugins);
    $options = array_combine($keys, $keys);
    $settings->add(new admin_setting_configselect('tool_cohortdatabase/createusers_auth',
        get_string('createusers_auth', 'tool_cohortdatabase'),
        get_string('createusers_auth_desc', 'tool_cohortdatabase'), 'manual', $options));

}

<?php

defined('MOODLE_INTERNAL') or die();

if ($ADMIN->fulltree) {
    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paynow_settings', '', get_string('pluginname_desc', 'enrol_paynow')));
    $settings->add(new admin_setting_configtext('enrol_paynow/userid', get_string('userid', 'enrol_paynow'), get_string('userid_desc', 'enrol_paynow'), ''));
    $settings->add(new admin_setting_configpasswordunmask('enrol_paynow/key', get_string('key', 'enrol_paynow'), get_string('key_desc', 'enrol_paynow'), ''));


    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_paynow_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_paynow/status',
        get_string('status', 'enrol_paynow'), get_string('status_desc', 'enrol_paynow'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_paynow/cost', get_string('cost', 'enrol_paynow'), '', 0, PARAM_FLOAT, 4));

    $paynowcurrencies = array(
        'USD' => 'USD',
    );
    $settings->add(new admin_setting_configselect('enrol_paynow/currency', get_string('currency', 'enrol_paynow'), '', 'NZD', $paynowcurrencies));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_paynow/roleid',
            get_string('defaultrole', 'enrol_paynow'), get_string('defaultrole_desc', 'enrol_paynow'), $student->id, $options));
    }

    $settings->add(new admin_setting_configtext('enrol_paynow/enrolperiod',
        get_string('enrolperiod', 'enrol_paynow'), get_string('enrolperiod_desc', 'enrol_paynow'), 0, PARAM_INT));
}


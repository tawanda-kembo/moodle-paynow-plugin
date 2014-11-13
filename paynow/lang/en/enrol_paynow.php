<?php

$string['pluginname'] = 'Paynow Payment Gateway';
$string['pluginname_desc'] = 'This plugin lets you configure courses to be paid for using the Paynow payment gateway.';

$string['key'] = 'Paynow Key';
$string['key_desc'] = 'This is the Paynow private key that was issued with the user id.';
$string['userid'] = 'Paynow User ID';
$string['userid_desc'] = 'The Paynow User ID to use for authentication.';
$string['unavailabletoguest'] = 'This course requires payment and is unavailable to the guest user.';
$string['status'] = 'Allow Paynow enrolments';
$string['status_desc'] = 'Allow users to use Paynow to enrol into a course by default.';
$string['cost'] = 'Cost';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Paynow enrolments';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid (in seconds). If set to zero, the enrolment duration will be unlimited by default.';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['assignrole'] = 'Assign role';
$string['enrolstartdate'] = 'Start date';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['paynow:config'] = 'Configure Paynow enrol instances';
$string['paynow:manage'] = 'Manage enrolled users';
$string['paynow:unenrol'] = 'Unenrol users from course';
$string['paynow:unenrolself'] = 'Unenrol self from the course';
$string['coursenotfound'] = 'Course not found';


// Error messages
$string['error_curlrequired'] = 'The PHP Curl extension is required for the Paynow enrolment plugin.';
$string['error_paynowcurrency'] = 'The course fee is not in a currency recognised by Paynow.';
$string['error_paynowinitiate'] = 'could not initiate a transaction with the Paynow payment server - please try again later.';
$string['error_enrolmentkey'] = 'That enrolment key was incorrect, please try again.';
$string['error_paymentfailure'] = 'Your payment was not successful. Paynow Payment Express returned the following error: $a';
$string['error_paymentunsucessful'] = 'Payment was not successful, please try again later.';
$string['error_txalreadyprocessed'] = 'Paynow Payment Express: This transaction has already been processed.';
$string['error_txdatabase'] = 'Fatal: could not create the Paynow transaction in the Moodle database.';
$string['error_txinvalid'] = 'Paynow Payment Express: invalid transaction, please try again.';
$string['error_txnotfound'] = 'Paynow Payment Express: corresponding Moodle transaction record not found.';
$string['error_usercourseempty'] = 'user or course empty';


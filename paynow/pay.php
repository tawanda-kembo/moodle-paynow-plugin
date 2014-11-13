<?php

// Set up a Paynow transaction and redirect user to payment service.
require dirname(dirname(dirname(__FILE__))) . "/config.php";
require_once "{$CFG->dirroot}/lib/enrollib.php";

require_login();

$id = required_param('id', PARAM_INT);  // plugin instance id

// get plugin instance
if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$id, "status"=>0))) {
    print_error('invalidinstance');
}

$plugin = enrol_get_plugin('paynow');

$xmlreply = $plugin->begin_transaction($plugin_instance, $USER);

$response = $plugin->ParseMsg($xmlreply);

print_r($response);

if ($response['status'] != 'Ok') {
   print_error('error_paynowinitiate', 'enrol_paynow');
}

// otherwise, redirect to the Paynow provided URI
redirect($response['browserurl']);


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
 * Enrolment using the Paynow credit card payment gateway.
 *
 * This plugin handles credit card payments by conducting PxPay transactions
 * through the Paynow Payment Express gateway. A successful payment results in the
 * enrolment of the user. We use PxPay because it does not require handling the
 * credit card details in Moodle. A truncated form of the credit card number is
 * returned in the PxPay response and is stored for reference only.
 *
 * Details of the Paynow PxPay API are online:
 * http://www.paymentexpress.com/technical_resources/ecommerce_hosted/pxpay.html
 *
 * @package    enrol
 * @subpackage paynow
 * @copyright  2014 Tawanda Kembo (tkembo@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();


/**
* Paynow enrolment plugin for NZ Paynow Payment Express.
* Developed by Catalyst IT Limited for The Open Polytechnic of New Zealand.
* Uses the Paynow PxPay method (redirect and return).
*/
class enrol_paynow_plugin extends enrol_plugin {

    /**
     * Constructor.
     * Fetches configuration from the database and sets up language strings.
     */
    function __construct() {

        // set up the configuration
        $this->load_config();
        $this->recognised_currencies = array(
            'USD',
        );
        $this->paynow_url = 'https://www.paynow.co.zw/interface/initiatetransaction';
    }

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        return array(new pix_icon('icon', get_string('pluginname', 'enrol_paynow'), 'enrol_paynow'));
    }

    public function roles_protected() {
        // users with role assign cap may tweak the roles later
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        // users with unenrol cap may unenrol other users manually - requires enrol/paynow:unenrol
        return true;
    }

    public function allow_manage(stdClass $instance) {
        // users with manage cap may tweak period and status - requires enrol/paynow:manage
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    /**
     * Sets up navigation entries.
     *
     * @param object $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'paynow') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);
        if (has_capability('enrol/paynow:config', $context)) {
            $managelink = new moodle_url('/enrol/paynow/edit.php', array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'paynow') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);

        $icons = array();

        if (has_capability('enrol/paynow:config', $context)) {
            $editlink = new moodle_url("/enrol/paynow/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/paynow:config', $context)) {
            return NULL;
        }

        // multiple instances supported - different cost for different roles
        return new moodle_url('/enrol/paynow/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_paynow').'</p>';
        } else {

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    // This actually is not so secure ;-), 'cause we're
                    // in unencrypted connection...
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $cost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
                //Sanitise some fields before building the paynow form
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
                $instancename    = $this->get_instance_name($instance);

                include($CFG->dirroot.'/enrol/paynow/enrol.html');
            }
        }

        return $OUTPUT->box(ob_get_clean());
    }

   

    /////
    /*
    PHP functions from Panow sample code - by tkembo
    Start
    */
    
    //create a hash value sent in any HTTP POST between the merchant site and Paynow
    function CreateHash($values, $MerchantKey){  
        $string = "";  
        foreach($values as $key=>$value) {  
            if( strtoupper($key) != "HASH" ){  
                $string .= $value;  
            }  
        }  
        $string .= $MerchantKey; 
  
        $hash = hash("sha512", $string);  
        
        return strtoupper($hash);    

    }

    //create a string of key-value pairs in the form key1=value1&key2=value2
    function UrlIfy($fields) {  
        //url-ify the data for the POST  
        $delim = "";  
        $fields_string = "";  
        foreach($fields as $key=>$value) {  
            $fields_string .= $delim . $key . '=' . $value;  

            $delim = "&";  
        }  
  
        return $fields_string;
    } 

    //urlencode and format your cURL POST fields into one string for posting to Paynow
    function createmessage($values, $MerchantKey){  
        $fields = array();  
        foreach($values as $key=>$value) {  
           $fields[$key] = $value;  
        }  
  
        $fields["hash"] = urlencode($this->CreateHash($values, $MerchantKey));  
  
        $fields_string = $this->UrlIfy($fields);  

        return $fields_string;  
    }  

    //Will convert a message of the form created by CreateMsg back into an associative array / dictionary
    function ParseMsg($msg) {  
        //convert to array data  
        $parts = explode("&",$msg);  
        $result = array();  
        foreach($parts as $i => $value) {  
            $bits = explode("=", $value, 2);  
            $result[$bits[0]] = urldecode($bits[1]);  
        }  
  
        return $result;  
    } 
    /*
    End
    */
    //////

     /**
     * Start the Paynow transaction by storing a record in the transactions table
     * and returning the GenerateRequest XML message.
     *
     * @param object $instance The course to be enroled.
     * @param object $user
     * @return string
     * @access public
     */
    function begin_transaction($instance, $user) {
        global $CFG, $DB;

        if (!$course = $DB->get_record('course', array('id' => $instance->courseid))) {
            print_error('coursenotfound', 'enrol_paynow');
        }
        if (empty($course) or empty($user)) {
            print_error('error_usercourseempty', 'enrol_paynow');
        }

        if (!in_array($instance->currency, $this->recognised_currencies)) {
            print_error('error_paynowcurrency', 'enrol_paynow');
        }

        // log the transaction
        $fullname = fullname($user);
        $paynowtx->courseid = $course->id;
        $paynowtx->userid = $user->id;
        $paynowtx->instanceid = $instance->id;
        $paynowtx->cost = clean_param(format_float((float)$instance->cost, 2), PARAM_CLEAN);
        $paynowtx->currency = clean_param($instance->currency, PARAM_CLEAN);
        $paynowtx->date_created = time();
        $site = get_site();
        $sitepart   = substr($site->shortname, 0, 20);
        $coursepart = substr("{$course->id}:{$course->shortname}", 0, 20);
        $userpart   = substr("{$user->id}:{$user->lastname} {$user->firstname}", 0, 20);
        $paynowtx->merchantreference = clean_param(strtoupper("$sitepart:{$coursepart}:{$userpart}"), PARAM_CLEAN);
        $paynowtx->email = clean_param($user->email, PARAM_CLEAN);
        $paynowtx->txndata1 = clean_param("{$paynowtx->courseid}: {$course->fullname}", PARAM_CLEAN);
        $paynowtx->txndata2 = clean_param("{$paynowtx->userid}: {$fullname}", PARAM_CLEAN);
        $paynowtx->txndata3 = "";

        if (!$paynowtx->id = $DB->insert_record('enrol_paynow_transactions', $paynowtx)) {
            print_error('error_txdatabase', 'enrol_paynow');
        }

        // create the "Generate Request" XML message
        
        $xmlrequest = "<GenerateRequest>
            <PxPayUserId>{$this->config->userid}</PxPayUserId>
            <PxPayKey>{$this->config->key}</PxPayKey>
            <AmountInput>{$paynowtx->cost}</AmountInput>
            <CurrencyInput>{$paynowtx->currency}</CurrencyInput>
            <MerchantReference>{$paynowtx->merchantreference}</MerchantReference>
            <EmailAddress>{$paynowtx->email}</EmailAddress>
            <TxnData1>{$paynowtx->txndata1}</TxnData1>
            <TxnData2>{$paynowtx->txndata2}</TxnData2>
            <TxnData3>{$paynowtx->txndata3}</TxnData3>
            <TxnType>Purchase</TxnType>
            <TxnId>{$paynowtx->id}</TxnId>
            <BillingId></BillingId>
            <EnableAddBillCard>0</EnableAddBillCard>
            <UrlSuccess>{$CFG->wwwroot}/enrol/paynow/confirm.php</UrlSuccess>
            <UrlFail>{$CFG->wwwroot}/enrol/paynow/fail.php</UrlFail>
            <Opt></Opt>\n</GenerateRequest>";

        $values = array('resulturl' => $CFG->wwwroot."/enrol/paynow/confirm.php?result=".$paynowtx->id,  
            'returnurl' =>  $CFG->wwwroot."/enrol/paynow/confirm.php?result=".$paynowtx->id,  
            'reference' =>  $paynowtx->id,  
            'amount' => $paynowtx->cost,  
            'id' =>  $this->config->userid,   
            'authemail' =>  $paynowtx->email,  
            'status' =>  'Message'); //just a simple message 

        $fields_string = $this->createmessage($values, $this->config->key);
        
        return $this->querypaynow($fields_string);
    }

    /**
     * Start the Paynow transaction by storing a record in the transactions table
     * and returning the GenerateRequest XML message.
     *
     * @param object $course The course to be enroled.
     * @param object $result
     * @return string
     * @access public
     */
    function confirm_transaction($result) {
        global $USER, $SESSION, $CFG, $DB;

        /*
        $xmlrequest = "<ProcessResponse>
            <PxPayUserId>{$this->config->userid}</PxPayUserId>
            <PxPayKey>{$this->config->key}</PxPayKey>
            <Response>{$result}</Response>\n</ProcessResponse>";
        $xmlreply = $this->querypaynow($xmlrequest);
        $response = $this->getdom($xmlreply);

        

        // abort if invalid
        if ($response === false or $response->attributes()->valid != '1') {
            print_error('error_txinvalid', 'enrol_paynow');
        }
        if (!$paynowtx = $DB->get_record('enrol_paynow_transactions', array('id' =>$response->TxnId))) {
            print_error('error_txnotfound', 'enrol_paynow');
        }

        // abort if already processed
        if (!empty($paynowtx->response)) {
            print_error('error_txalreadyprocessed', 'enrol_paynow');
        }
        */

        $paynowtx = $DB->get_record('enrol_paynow_transactions', array('id' =>$result));

        $paynowtx->success    = 1;
        /*
        $paynowtx->authcode   = clean_param($response->AuthCode, PARAM_CLEAN);
        $paynowtx->cardtype   = clean_param($response->CardName, PARAM_CLEAN);
        $paynowtx->cardholder = clean_param($response->CardHolderName, PARAM_CLEAN);
        $paynowtx->cardnumber = clean_param($response->CardNumber, PARAM_CLEAN); // truncated form only
        $paynowtx->cardexpiry = clean_param($response->DateExpiry, PARAM_CLEAN);
        $paynowtx->clientinfo = clean_param($response->ClientInfo, PARAM_CLEAN);
        $paynowtx->paynowtxnref  = clean_param($response->PaynowTxnRef, PARAM_CLEAN);
        $paynowtx->txnmac     = clean_param($response->TxnMac, PARAM_CLEAN);
        */
        $paynowtx->response   = "APPROVED";
        
        // update transaction
        if (!$DB->update_record('enrol_paynow_transactions', $paynowtx)) {
            print_error('error_txnotfound', 'enrol_paynow');
        }
        
        // recover the course
        list($courseid, $coursename) = explode(":", $paynowtx->txndata1);
        $course = $DB->get_record('course', array('id' => $result));

        // enrol and continue if Paynow returns "APPROVED"
        
        if ($paynowtx->success == 1 and $paynowtx->response == "APPROVED") {
        
            // enrol the student and continue
            // TODO: ASSUMES the currently logged in user. Does not check the user in $paynowtx, but they should be the same!
            if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$paynowtx->instanceid, "status"=>0))) {
                print_error('Not a valid instance id');
            }
            if ($plugin_instance->enrolperiod) {
                $timestart = time();
                $timeend   = $timestart + $plugin_instance->enrolperiod;
            } else {
                $timestart = 0;
                $timeend   = 0;
            }
            // Enrol the user!
            $this->enrol_user($plugin_instance, $paynowtx->userid, $plugin_instance->roleid, $timestart, $timeend);

            // force a refresh of mycourses
            unset($USER->mycourses);

            // redirect to course view
            if ($SESSION->wantsurl) {
                $destination = $SESSION->wantsurl;
                unset($SESSION->wantsurl);
            } else {
                $destination = "{$CFG->wwwroot}/course/view.php?id={$course->id}";
            }
            redirect($destination);
        
        } else {
            // abort
            print_error('error_paymentunsucessful', 'enrol_paynow');
        
         }  
     }

    /**
     * Roll back the Paynow transaction by updating the record in the transactions
     * table.
     *
     * @param object $course The course to be enroled.
     * @param object $result
     * @return string
     * @access public
     */
    function abort_transaction($result) {
        global $USER, $SESSION, $CFG, $DB;

        $xmlrequest = "<ProcessResponse>
            <PxPayUserId>{$this->config->userid}</PxPayUserId>
            <PxPayKey>{$this->config->key}</PxPayKey>
            <Response>{$result}</Response>\n</ProcessResponse>";
        $xmlreply = $this->querypaynow($xmlrequest);
        $response = $this->getdom($xmlreply);

        // abort if invalid
        if ($response === false or $response->attributes()->valid != '1') {
            print_error('error_txinvalid', 'enrol_paynow');
        }
        if (!$paynowtx = $DB->get_record('enrol_paynow_transactions', array('id' => $response->TxnId))) {
            print_error('error_txnotfound', 'enrol_paynow');
        }

        // abort if already processed
        if (!empty($paynowtx->response)) {
            print_error('error_txalreadyprocessed', 'enrol_paynow');
        }

        $paynowtx->success    = clean_param($response->Success, PARAM_CLEAN);
        $paynowtx->authcode   = clean_param($response->AuthCode, PARAM_CLEAN);
        $paynowtx->cardtype   = clean_param($response->CardName, PARAM_CLEAN);
        $paynowtx->cardholder = clean_param($response->CardHolderName, PARAM_CLEAN);
        $paynowtx->cardnumber = clean_param($response->CardNumber, PARAM_CLEAN); // truncated form only
        $paynowtx->cardexpiry = clean_param($response->DateExpiry, PARAM_CLEAN);
        $paynowtx->clientinfo = clean_param($response->ClientInfo, PARAM_CLEAN);
        $paynowtx->paynowtxnref  = clean_param($response->PaynowTxnRef, PARAM_CLEAN);
        $paynowtx->txnmac     = clean_param($response->TxnMac, PARAM_CLEAN);
        $paynowtx->response   = clean_param($response->ResponseText, PARAM_CLEAN);

        // update transaction
        if (!$DB->update_record('enrol_paynow_transactions', $paynowtx)) {
            print_error('error_txnotfound', 'enrol_paynow');
        }

        print_error('error_paymentfailure', 'enrol_paynow', '', $paynowtx->response);
    }

    /**
    * Cron method.
    * @return void
    */
    function cron() {
    }

    /**
     * Turn an XML string into a DOM object.
     *
     * @param string $xml An XML string
     * @return object The SimpleXMLElement object representing the root element.
     * @access public
     */
    function getdom($xml) {
        $dom = new DomDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        return simplexml_import_dom($dom);
    }

    /**
     * Send an XML message to the Paynow service and return the XML response.
     *
     * @param string $xml The XML request to send.
     * @return string The XML response from Paynow.
     * @access public
     */
	function querypaynow($xml){
        
        if (!extension_loaded('curl') or ($curl = curl_init($this->paynow_url)) === false) {
            print_error('curlrequired', 'enrol_paynow');
        }

		curl_setopt($curl, CURLOPT_URL, $this->paynow_url);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

		// TODO: fix up curl proxy stuffs, c.f. lib/filelib.php
		//curl_setopt($ch,CURLOPT_PROXY , "{$CFG->proxyhost}:{$CFG->proxyport}");
		//curl_setopt($ch,CURLOPT_PROXYUSERPWD,"{$CFG->proxyuser}:{$CFG->proxypassword}");

		$response = curl_exec($curl);
		curl_close($curl);
		return $response;
	}
}


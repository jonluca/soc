 <?php
 require_once("WSCORE2.php");
 require_once("soc1c.php");
 
 if (file_exists("/info/www/soc/nusoap/lib/nusoap.php")) {
   require_once("/info/www/soc/nusoap/lib/nusoap.php");
 } else if (file_exists("../nusoap/lib/nusoap.php")) {
   require_once("../nusoap/lib/nusoap.php");
 } else if (file_exists("nusoap/lib/nusoap.php")) {
   require_once("nusoap/lib/nusoap.php");
 }
 
 
 class AIS_SOAP_WS extends SOC_Widget {
   function AIS_SOAP_WS($conf) {
     SOC_Widget::SOC_Widget($conf);
   }
 
   function xmlFromSoap($service,$term,$code) {
     $namespace = $this->get("soc_namespace");
     $soc_wsdl = $this->get("soc_wsdl");
     $iui_wsdl = $this->get("iui_wsdl");
     $soapaction = $namespace.$service;
   
     $xml = "";
     // Create the soap client instance
     $client = new nusoap_client($soc_wsdl);
   
     // Try replacing xml file.
     switch ($service) {
       case 'GetBioLink':
         // Create the soap client instance
         // $code is the USC 7 digit employee id
         $envelope = "<GetBioLink xmlns=\"$namespace\"><strInstEmpID>$code</strInstEmpID></GetBioLink>";
         // Create the client instance
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
         if ($client->fault) {
           $this->error("Client failed in $service ".$client->fault);
         } else if (! isset($response['GetBioLinkResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
          $xml = $response["GetBioLinkResult"];
         }
         break;
       case 'GetSOCTerms':
         $envelope = "<$service xmlns=\"$namespace\" />";
         // Create the client instance
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
         if ($client->fault) {
           $this->error("Client failed in $service ".$client->fault);
         } else if (! isset($response['GetSOCTermsResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
           $xml = $response["GetSOCTermsResult"];
         }
         break;
       case 'GetIUITerms':
         $envelope = "<GetIUITerms xmlns=\"$namespace\" />";
         // Create the client instance
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
         if ($client->fault || isset($response['faultstring'])) {
           $this->error("Client failed in $service ".$client->fault." ".$response['faultstring']);
         } else if (! isset($response['GetIUITermsResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
           $xml = $response["GetIUITermsResult"];
         }
         break;
       case 'GetDeptList':
         $envelope = "<GetDeptList xmlns=\"$namespace\"><strTerm>".$term.'</strTerm></GetDeptList>';
         // Create the client instance
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
         
         if ($client->fault) {
           $this->error("Client failed in $service ".$client->fault);
         } else if (! isset($response['GetDeptListResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
           $xml = $response["GetDeptListResult"];
         }
         break;
       case 'GetCourseList':
         $strPrefixType = 'N';
         // find department code (the xml filename without extension)
         $envelope = "<GetCourseList xmlns=\"$namespace\"><strTerm>".$term.'</strTerm><strPrefixCode>'.$code.'</strPrefixCode><strPrefixType>'.$strPrefixType.'</strPrefixType></GetCourseList>';
         
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
         
         $xml = "";
         if ($client->fault) {
           $this->error("Client failed in $service ".$client->fault);
         } else if (isset($response['GetCourseListResult'])) {
           $xml = str_replace('<?xml version="1.0" encoding="utf-16"?>','',$response["GetCourseListResult"]);
         }
         
         if (strpos($xml,'syllabus') === false) {
           // Look like we only got the Dept info so let's try with a C type
           $strPrefixType = 'C';
           $envelope = "<GetCourseList xmlns=\"$namespace\"><strTerm>".$term.'</strTerm><strPrefixCode>'.$code.'</strPrefixCode><strPrefixType>'.$strPrefixType.'</strPrefixType></GetCourseList>';
           
           $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
           
           /* Send the SOAP message and specify the soapaction  */
           $response = @ $client->send($mysoapmsg, $soapaction);
           
           if ($client->fault) {
             $this->error("Client failed in $service ".$client->fault);
           } else if (! isset($response['GetCourseListResult'])) {
             $this->error("$service failed, check $soc_wsdl");
           } else {
             $xml = str_replace('<?xml version="1.0" encoding="utf-16"?>','',$response["GetCourseListResult"]);
           }
         }
         break;
       case 'GetSyllabus':
         $section = $code;
         $envelope = '<GetSyllabus xmlns="'.$namespace.'"><strTerm>'.$term.'</strTerm><strSectionID>'.$section.'</strSectionID></GetSyllabus>';
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');      
         $response = @ $client->send($mysoapmsg, $soapaction);
         if ($client->fault) {
           $this->error("Client failed in $service $envelope ".$client->fault);
         } else if (! isset($response['GetSyllabusResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
           $xml = $response["GetSyllabusResult"];
         }
         break;
       case 'GetSessionInfo':
         // Create the soap client instance
         $client = new nusoap_client($soc_wsdl);
         $session = $code;
         $envelope = '<GetSessionInfo xmlns="'.$namespace.'"><strTerm>'.$term.'</strTerm><strSession>'.$session.'</strSession></GetSessionInfo>';
           
         $mysoapmsg = $client->serializeEnvelope($envelope,'',array(),'document', 'literal');
         
         /* Send the SOAP message and specify the soapaction  */
         $response = @ $client->send($mysoapmsg, $soapaction);
     
         if ($client->fault) {
           $this->error("Client failed in $service ".$client->fault);
         } else if (! isset($response['GetSessionInfoResult'])) {
           $this->error("$service failed, check $soc_wsdl");
         } else {
           $xml = $response["GetSessionInfoResult"];
         }
         break;
       default:
         $this->error("$service not supported");
         break;
     }
     
     if ($this->errorCount() == 0) {
       // Adding caching of the XML returned from the soap service to 
       // facility debugging the persistant character encoding problems.
       $cache_id = trim($service . ':' . $term . ':' . $code);
       $sql = 'REPLACE INTO soap_xml_cache (cache_id, service, term, code, xml) VALUES ("' . $cache_id . '","' . $service . '","' . $term .'","' . $code . '","'. addslashes($xml) . '")';
       $db = new WSCORE2($this);
       $db->open();
       $db->execute($sql);
       // Can't close the DB connection because is shared later.
 
       // Now that we've saved the response from SIS, 
       // Let's remove the predictable invalid XML invalid e.g. &amp;quot; 
       if (strpos($xml, '&amp;quot;')) {
         $tmp = str_replace('&amp;quot;','"', $xml);
         $xml = $tmp;
       }
 
       return $xml;
     }
     return false;
   }
 }
 
 
 
 ?>
<?php
require_once("WSCORE2.php");
require_once("soc1c.php");
require_once("ais_soap1c.php");

function debug($msg) {
    $fp = fopen('/www/ws/soc/debug-log/debug.log','a');
    fwrite($fp, date('Y-m-d H:i:s') . ' -> ' . $msg . PHP_EOL);
    fclose($fp);
}

class SOC_API1C extends SOC_Widget {
    function __construct($conf) {
        SOC_Widget::SOC_Widget($conf);
        $this->version_msg = "SOC API1C";
        $this->cache = new SOC_Cache2($this);
        $this->cache->open();
        $this->service = "";
        if ($this->get("soc_path")) {
            $filename = $this->get("soc_path") . '/data/dept-urls.json';
        } else {
            $filename = "data/dept-urls.json";
        }
        if (file_exists($filename)) {
            $this->dept_info = json_decode(file_get_contents($filename), true);
        } else {
            $this->dept_info = array();
        }
    }

    public function close () {
        return $this->cache->close();
    }

    function _restParse ($restful_args) {
        $args = explode('/',trim($restful_args));
        if (count($args) < 2) {
            $this->error("Not sure what you want me to do [$restful_args]");
        }
        $i = 1; // Index we're working on.
        if (isset($args[$i])) {
            $object = strtolower($args[$i]);
            // Figure out the output format, content type and initial content.
            switch($object) {
                case 'help':
                    $this->format = "html";
                    $this->content_type = "text/html";
                    break;
                default:
                    // Set the default content type and format
                    //$this->content_type = "text/plain";
                    $this->content_type = "application/json";
                    $this->format = "json";
                    break;
            }
        }

        // Figure out the service we're accessing
        if (isset($args[$i])) {
            $object = strtolower($args[$i]);
            $this->service = $object;
            switch($object) {
                case 'session':
                    $this->service = 'session';
                    $i++;
                    if (isset($args[$i])) {
                        $this->set("active_session",$args[$i]);
                    } else {
                        $this->error("Must include a session code. e.g. 001, 002, 003");
                    }
                    $i++;
                    if (isset($args[$i])) {
                        $this->set("active_term",$args[$i]);
                    } else {
                        $this->error("Must include a term code. e.g. 20091 for Spring 2009, 20092 for Summer 2009, 20093 for Fall 2009");
                    }
                    break;
                case 'classes':
                    $this->service = 'classes';
                    $i++;
                    if (isset($args[$i])) {
                        $this->set("active_dept",$args[$i]);
                        $i++;
                    } else {
                        $this->error("You need to provide a department for your request.  e.g. ".$this->get("soc_uri")."/api1c/classes/hist/20091 would request classes offered by the History department for Spring 2009");
                    }
                    if (isset($args[$i])) {
                        $this->set("active_term",$args[$i]);
                        $i++;
                    } else {
                        $this->error("You need to provide a term for your request.  e.g. ".$this->get("soc_uri")."/api1c/classes/hist/20091 would request classes offered by the History department for Spring 2009");
                    }
                    break;
                case 'departments':
                case 'depts':
                    $this->service = 'departments';
                    $i++;
                    if (isset($args[$i])) {
                        $this->set("active_term",$args[$i]);
                        $i++;
                    } else {
                        $this->error("You need to provide a term with the department requested.  e.g. ".$this->get("soc_uri")."/api1c/depts/20091 would request department information for Spring 2009");
                    }
                    break;
                case 'terms':
                    $this->service = 'terms';
                    $i++;
                    break;
                case 'bio':
                    $this->service = 'bio';
                    $i++;
                    if (isset($args[$i])) {
                        $this->set("eid",$args[$i]);
                    } else {
                        $this->error("Must include an employee number");
                    }
                    break;
                case 'booklist':
                    $this->service = 'booklist';
                    $i++;
                    if (isset($args[$i])) {
                        $this->set('section',$args[$i]);
                        $i++;
                    } else {
                        $this->error("Must include a section number for the book list e.g. /api1c/booklist/21012/20093 would be a book list for section 21012 in Fall 2009");
                    }
                    if (isset($args[$i])) {
                        $this->set("active_term",$args[$i]);
                        $i++;
                    } else {
                        $this->error("Must include a term number for the book list e.g. /api1c/booklist/21012/20093 would be a book list for section 21012 in Fall 2009");
                    }
                    break;
                case 'help':
                default:
                    $this->content = $this->help();
                    $i++;
                    break;
            }
        }
        unset($args);
        if ($this->errorCount() == 0) {
            return true;
        }
        return false;
    }

    function getSession ($session, $term) {
        $soap = new AIS_SOAP_WS($this);
        $xml = $soap->xmlFromSoap("GetSessionInfo", $term, $session);
        if ($soap->errorCount()) {
            $this->error("Can't get session data from AIS SOAP Service.\n" . $soap->errors());
            return false;
        }

        if ($xml !== false) {
            // Parse for course info
            try {
                $dom = new SimpleXMLElement($xml);
                $json = json_encode($dom);
            } catch (Exception $e) {
                $this->error("SimpleXMLElement can't parse active sessions for $session, $term [$xml] ".$e->getMessage()."\n".$soap->errors());
                unset($soap);
                return false;
            }
            unset($dom);
            unset($soap);
            $this->cache->setCache("/session/$session/$term", $json);
            return true;
        }
        $this->error ("Can't find information for session $session in term $term");
        unset($soap);
        return false;
    }

    public function getCacheForTerm($term) {
        if (trim($term) === '') {
            $this->error("Need to set term for Cache");
            return false;
        }
        $sql = "SELECT uri FROM soc_cache WHERE uri LIKE '%$term' AND uri NOT LIKE '/booklist%' AND uri NOT LIKE '/classes%' ORDER BY soc_cache.modified ASC";
        $url_list = array();
        $this->cache->execute($sql);
        while ($row = $this->cache->getRow()) {
            if (isset($row['uri'])) {
                $url_list[] = $row['uri'];
            }
        }
        $this->cache->release();
        if ($this->cache->errorCount() === 0) {
            return $url_list;
        }
        $this->error("Can't find data for $term.");
        return false;
    }

    public function getBooklist($section, $term) {
        if ($this->get("booklist_url") === false) {
            $this->error("Book list URL not configured");
            return false;
        }
        $uri = $this->get("booklist_url");
        $booklist = substr($term,2).'-'.$section;
        // Get URL where Booklist source is stored
        $json = false;
        if (trim($uri) !== '') {
            if (strpos($uri,'file://') === 0) {
                // FIXME: when we move to PHP 5 on www.usc.edu I can replace
                // this with a try/catch for getting file contents.
                $json = @file_get_contents(substr($uri,7) . '/'. $booklist . '.json');
            } else {
                $uri = $uri.'/'.$booklist.'.json';
                // create curl resource
                $ch = curl_init();

                // Set up some identification for process.
                curl_setopt($ch, CURLOPT_USERAGENT, "soc-api1c");
                curl_setopt($ch, CURLOPT_REFERER, "http://web-app.usc.edu/ws/soc/models/api1c.php");

                // set url
                curl_setopt($ch, CURLOPT_URL, $uri);
                //return the transfer as a string
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                // $output contains the output string
                $json = curl_exec($ch);
                // close curl resource to free up system resources
                curl_close($ch);
                // Check to make sure we don't have an error in the output from the CURL call
            }
        }

        if ($json !== false) {
            if (strpos($json, "<!DOC") !== false || strpos($json,"404 Not Found") !==
                false) {
                $this->error($booklist . " not available.");
                return false;
            }
            // Check to make sure we have valid JSON
            $test = json_decode($json, true);
            if (function_exists('json_last_error')) {
                switch(json_last_error()) {
                    case JSON_ERROR_DEPTH:
                        $this->error($booklist . ' maximum stack depth exceeded.');
                        return false;
                    case JSON_ERROR_CTRL_CHAR:
                        $this->error($booklist . ' unexpected control character found.');
                        return false;
                    case JSON_ERROR_SYNTAX:
                        $this->error($booklist . ' syntax error, malformed JSON.');
                        return false;
                }
            } else if (is_array($test) === false) {
                $this->error($booklist . " was improperly formatted." . print_r($test,
                        true));
                return false;
            }
            unset($test);
            $this->cache->setCache('/booklist/'.$section.'/'.$term,$json);
            return $json;
        }
        $this->error("Booklist $booklist requested was an empty string.");
        return false;
    }

    public function getBioUrl($id) {
        $obj = new AIS_SOAP_WS($this);
        $xml = $obj->xmlFromSoap("GetBioLink",NULL,$id);
        if ($obj->errorCount() == 0  && trim($xml) !== '<BioURL />' && $xml !== false && trim($xml) != "") {
            libxml_use_internal_errors(true);
            $sxml = simplexml_load_string($xml);
            if (! $sxml) {
                $this->error("XML parse error(s). GetBioLink(". strlen($xml) . "): [<pre>". $xml . "</pre>]");
                $xmlerrors = libxml_get_errors();
                foreach ($xmlerrors as $xerror) {
                    $msg = 'ERROR line: ' . $xml[$xerror->line - 1] . " ";
                    $msg .= str_repeat('-', $xerror->column) . ": ";
                    $msg .= $xerror->message;
                    $this->error($msg);
                }
                libxml_clear_errors();
                return false;
            }
            $bio_link = urldecode($sxml."");
            unset($sxml);
            unset($xml);
            if (trim($bio_link) != "") {
                return $bio_link;
            }
        }
        unset($obj);
        return false;
    }

    function getDeptInfo($abbreviation) {
        foreach ($this->dept_info as $info) {
            if (isset($info["abbreviation"]) && $info["abbreviation"] === $abbreviation) {
                return $info;
            }
        }
        return false;
    }

    function getClasses ($dept, $term) {
        $soap = new AIS_SOAP_WS($this);

        $xml = mb_convert_encoding($soap->xmlFromSoap("GetCourseList",$term, $dept), 'UTF-8');

        if ($xml == false || $soap->errorCount() > 0) {
            $this->error("Can't get Course Lists from AIS SOAP Service. for $dept, $term\n".$soap->errors());
            unset($soap);
            return false;
        }
        unset($soap);
        $uri = "/classes/$dept/$term";


        // Parse for course info
        libxml_use_internal_errors(true);
        $sxml = simplexml_load_string($xml);
        if (! $sxml) {
            $this->error("XML parse error(s). GetCourseList: [".$xml."]");
            $xmlerrors = libxml_get_errors();
            foreach ($xmlerrors as $xerror) {
                $msg = 'line: ' . $xml[$xerror->line - 1] . " ";
                $msg .= str_repeat('-', $xerror->column) . ": ";
                $msg .= $xerror->message;
                $this->error($msg);
            }
            libxml_clear_errors();
            return false;
        }


        // Cache XML version
        if ($sxml !== false) {
            // Get Dept Info from Dept_Info field
            // FIXME: hook in SOC_DeptUrls Object;
            $DeptInfo = false;
            if (isset($sxml->Dept_Info)) {
                $info = $this->getDeptInfo(trim($sxml->Dept_Info->abbreviation));
                if (isset($info['uri'])) {
                    $sxml->Dept_Info->dept_url = $info['uri'];
                    $DeptInfo = $sxml->Dept_Info;
                }
            }
            $HasDistanceLearning = 'N';

            for($i = 0; $i < count($sxml->OfferedCourses->course); $i++ ) {
                for($j = 0; $j <  count($sxml->OfferedCourses->course[$i]->ConcurrentCourse); $j++) {
                    for ($k = 0; $k < count($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData); $k++ ) {

                        // Create a flag for Internet based classes
                        if (isset($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->comment) && trim($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->comment) != '') {
                            $sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->IsDistanceLearning = 'Y';
                            $HasDistanceLearning = 'Y';
                        } else {
                            $sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->IsDistanceLearning = 'N';
                        }

                        for ($l = 0; $l < count($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor);$l++) {
                            $bio_url = false;
                            // $sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]
                            if (isset($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]->id)) {
                                $uscemployeeid =
                                    $sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]->id;
                                $bio_url = $this->getBioUrl($uscemployeeid);
                            }
                            if ($bio_url !== false) {
                                $sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]->bio_url =
                                    trim($bio_url);
                            } else {
                                unset($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]->bio_url);
                            }
                            unset($sxml->OfferedCourses->course[$i]->ConcurrentCourse[$j]->CourseData->SectionData[$k]->instructor[$l]->id);
                        }// End of Instructors loop
                    } // End loop of Concurrent Course Sections
                } // End loop Concurrent Courses

                $HasDistanceLearning = 'N';


                for ($j = 0; $j < count($sxml->OfferedCourses->course[$i]->CourseData->SectionData); $j++ ) {

                    // Create a flag for Internet based classes
                    if (isset($sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->comment) && trim($sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->comment) != '') {
                        $sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->IsDistanceLearning = 'Y';
                        $HasDistanceLearning = 'Y';
                    } else {
                        $sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->IsDistanceLearning = 'N';
                    }

                    for ($k = 0; $k < count($sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->instructor); $k++) {
                        $uscemployeeid = $sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->instructor[$k]->id;

                        $bio_url = $this->getBioUrl($uscemployeeid);
                        if ($bio_url !== false) {
                            $sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->instructor[$k]->bio_url = trim($bio_url);
                        } else {
                            unset($sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->instructor[$k]->bio_url);
                        }
                        unset($sxml->OfferedCourses->course[$i]->CourseData->SectionData[$j]->instructor[$k]->id);
                    }// End of loop through instructors
                } // End of Section Loop of CourseData
            } // End of Course Loop

            // Write out sanitized CourseListResults from AIS Service.
            try {
                $json_text = json_encode($sxml);
            } catch (Exception $e) {
                $this->error($e->getMessage()." Caching $uri: [$json_text]");
            }
            if ($this->errorCount() > 0) {
                return false;
            }
            $this->cache->setCache(strtolower($uri), $json_text);
            unset($sxml);
            unset($xml);
            unset($json_text);
            return true;
        }
        $this->error ("Can't find classes for $dept in term $term; xml [$xml]");
        return false;
    }

    function getDepartments ($term) {
        $soap = new AIS_SOAP_WS($this);
        $xml = $soap->xmlFromSoap("GetDeptList", $term, NULL);
        if ($soap->errorCount()) {
            $this->error("Can't get Dept. Lists from AIS SOAP Service.\n".$soap->errors() . " xml:[<pre>$xml</pre>]");
            unset($soap);
            return false;
        }
        unset($soap);
        $uri = "/departments/$term";

        if ($xml !== false) {
            // Parse for department codes
            try {
                //Ampersand hack because SIS Doesn't know how to validate their generated XML.
                $sxml = new SimpleXMLElement(str_replace(" & ", " &amp; ", $xml));
                $json = json_encode($sxml);
                $this->cache->setCache(strtolower($uri), $json);
                unset($sxml);
                unset($xml);
                return true;
            } catch (Exception $e) {
                $this->error("SimpleXMLElement can't parse active depts for $term [$xml] ".$e->getMessage()."\n");
            }
        }
        return false;
    }

    function getTerms () {
        // Try to populate the cache
        $soap = new AIS_SOAP_WS($this);
        $xml = $soap->xmlFromSoap("GetSOCTerms",NULL,NULL);
        if ($soap->errorCount()) {
            $this->error("Can't get Term List from AIS SOAP Service.\n".$soap->errors());
            unset($soap);
            return false;
        }
        if ($xml !== false) {
            // Parse of terms
            $dom = new SimpleXMLElement($xml);
            $json = json_encode($dom);
            $this->cache->setCache(strtolower("/terms"), $json);
            unset($dom);
            unset($xml);
            return true;
        }
        $this->error("Can't populate $rest_args");
        return false;
    }

    function eventLoop($get,$post,$session,$server,$file) {
        if (isset($server['PATH_INFO'])) {
            $this->_restParse($server['PATH_INFO']);
        } else {
            $rest_args = "/help";
            $this->_restParse("/help");
        }

        switch($this->service) {
            case 'session':
                $term = $this->get("active_term");
                $session = $this->get("active_session");

                $uri = "/session/".$session."/".$term;
                if ($this->get("cache_replace")) {
                    $this->getSession($session, $term);
                }
                $this->content = $this->cache->getCache(strtolower($uri));
                if ($this->content === false) {
                    // getSessions sets the cache as a side effect
                    $this->getSession($session, $term);
                    $this->content = $this->cache->getCache(strtolower($uri));
                    if ($this->content === false) {
                        $this->content = $this->errors();
                    }
                }
                break;
            case 'classes':
                $term = $this->get("active_term");
                $dept = $this->get("active_dept");

                $uri = "/classes/".$dept."/".$term;
                if ($this->get("cache_replace")) {
                    $this->getClasses($dept, $term);
                }
                $this->content = $this->cache->getCache($uri);
                if ($this->content === false) {
                    // getClasses sets a bunch of cache records as a side effect
                    $this->getClasses($dept, $term);
                    $this->content = $this->cache->getCache($uri);
                    if ($this->content === false) {
                        $this->content = $this->errors("ERROR: can't read cache for " . $dept . ' ' . $term);
                    }
                }
                break;
            case 'departments':
                $term = $this->get("active_term");

                $uri = "/departments/".$term;
                if ($this->get("cache_replace")) {
                    $this->getDepartments($term);
                }
                $this->content = $this->cache->getCache($uri);
                if ($this->content === false) {
                    $this->getDepartments($term);
                    $this->content = $this->cache->getCache($uri);
                    if ($this->content === false) {
                        $this->content = $this->errors();
                    }
                }
                break;
            case 'terms':
                $uri = "/terms";
                if ($this->get("cache_replace")) {
                    $this->getTerms();
                }
                $this->content = $this->cache->getCache($uri);
                if ($this->content === false) {
                    $this->getTerms();
                    $this->content = $this->cache->getCache($uri);
                    if ($this->content === false) {
                        $this->content = $this->errors();
                    }
                }
                break;
            case 'bio':
                $eid = $this->get("eid");
                $uri = "/bio/".$this->get("eid");
                if ($this->get("cache_replace")) {

                    $bio_url = $this->getBioUrl($eid);
                    $bio_url !== false ? $this->cache->setCache(strtolower($uri), $bio_url) : $this->error("Can't find Bio URL");
                }
                $this->content = $this->cache->getCache($uri);
                if ($this->content === false) {
                    $bio_url = $this->getBioUrl($eid);
                    $bio_url !== false ? $this->cache->setCache(strtolower($uri), $bio_url) : $this->error("Can't find Bio URL");
                    if ($this->errorCount() == 0) {
                        $this->content = $this->cache->getCache(strtolower($uri));
                    } else {
                        $this->content = $this->errors();
                    }
                }
                break;
            case "booklist":
                $section = $this->get("section");
                $term = $this->get('active_term');
                $uri = "/booklist/".$section.'/'.$term;
                if ($this->get("cache_replace")) {
                    $booklist_content = $this->getBooklist($section, $term);
                    $booklist_content !== false ? $this->cache->setCache(strtolower($uri), $booklist_content) : '';
                }
                $this->content = $this->cache->getCache($uri);
                if ($this->content === false) {
                    $booklist_content = $this->getBooklist($section, $term);
                    $booklist_content !== false ? $this->cache->setCache(strtolower($uri), $booklist_content) : '';
                    if ($this->errorCount() == 0) {
                        $this->content = $this->cache->getCache(strtolower($uri));
                    } else {
                        $this->content = $this->errors();
                    }
                }
                break;
            case "";
            case 'help':
                break;
            default:
                $this->error ("'".$this->service."' not supported.");
                break;
        }
        if ($this->errorCount() == 0) {
            return true;
        }
        return false;
    }

    function contentType() {
        if (isset($this->content_type)) {
            return "Content-Type: ".$this->content_type;
        }
        return "Content-Type: text/plain";
    }

    function render() {
        if (isset($this->content)) {
            return $this->content;
        }
        return false;
    }

    function help() {
        $soc_uri = SOC_Widget::get('soc_uri');
        $content=<<<EOF
 <h1>USC - ITS Web services</h1>
 <h2>Schedule of Classes Web Services API</h2>
 <p />
 <h3>Developer</h3>
 <ul>
   <li><a href='$soc_uri/help/README.txt'>README</a> (includes example URI)</li>
   <li><a href='$soc_uri/docs/html'>Source Code Documentation</a></li>
 </ul>
 
 <p />
 
 <h3>Installation and Analysis</h3>
 <ul>
 EOF;

        $txt_files = glob(SOC_Widget::get('soc_path')."/*.txt");
        foreach ($txt_files as $filename) {
            if (strpos($filename,"README.txt") === false && strpos($filename,"Participants.txt") === false && strpos($filename,"INSTALLATION.txt") === false) {
                $link = str_replace(array($this->attributes['soc_path']."/"),
                    array($this->attributes['soc_uri']."/help/"), $filename);
                $label = str_replace(array($this->attributes['soc_path']."/",".txt","_"),array("",""," "),$filename);
                $content .= "\t<li><a href='$link'>$label</a></li>\n";
            }
        }
        $content .=<<<EOF
 </ul>
 
 <h3>AIS/REG source data</h3>
 <ul>
   <li><a href='http://cmsdev-jhh102.usc.edu/soc-ws/soc-ws.asmx'>REG/SOC SOAP service documentation</a></li>
 </ul>
 
 EOF;
        return $content;
    }
}
?>

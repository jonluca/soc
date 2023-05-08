<?php
$all = '';
$term_code = '';
$section = '';
if (isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', $_SERVER['PATH_INFO']);
    if (isset($parts[1])) {
        if ($parts[1] === 'help') {
            header('Content-Type: text/plain');
            die(file_get_contents('README.utf8books'));
        }
        $term_code = substr(strip_tags(str_replace(array("'",'"'," ","/"),'', urldecode($parts[1]))),-3);
    }
    if (isset($parts[2])) {
        if ($parts[2] === 'all') {
            $all = true;
        } else {
            $section = strip_tags(str_replace(array("'",'"'," ","/"),'', urldecode($parts[2])));
        }
    }
} else if (isset($_GET['term']) && isset($_GET['section'])) {
    $term_code = substr(strip_tags(str_replace(array("'",'"'," ","/"),'', urldecode($_GET['term']))),-3);
    if ($_GET['section'] == 'all') {
        $all = true;
    } else {
        $section = strip_tags(str_replace(array("'",'"'," ","/"),'', urldecode($_GET['section'])));
    }
} else if (isset($_GET['term'])) {
    $term_code = substr(strip_tags(str_replace(array("'",'"'," ","/"),'', urldecode($_GET['term']))),-3);
}

$callback = '';
if (isset($_GET['callback'])) {
    $callback = strip_tags(str_replace(array("'",'"'," ","/"),'',urldecode($_GET['callback'])));
}

if ($all === true && $term_code != '') {
    $filenames = glob('booklist/' . $term_code . '-*.json');
    $result = '';
    foreach ($filenames as $filename) {
        $books = json_decode(file_get_contents($filename),true);
        for ($i = 0; $i < count($books); $i += 1) {
            foreach ($books[$i] as $key => $value) {
                $books[$i][$key] = mb_convert_encoding($value,'UTF-8');
            }
        }
        if ($result === '') {
            $result .= json_encode($books);
        } else {
            $result .= ',' . json_encode($books);
        }
        unset($books);
    }
    $result .= '';
} else if ($section != '' && $term_code != '') {
    $filename = 'booklist/' . substr($term_code,-3) . '-' . $section . '.json';
    if (file_exists($filename)) {
        $books = json_decode(file_get_contents($filename),true);
        for ($i = 0; $i < count($books); $i += 1) {
            foreach ($books[$i] as $key => $value) {
                $books[$i][$key] = mb_convert_encoding($value,'UTF-8');
            }
        }
        $result = json_encode($books);
    } else {
        $result = '{"error":"' . "Can't find $filename" . '"}';
    }
} else if ($term_code != '') {
    $files = glob("booklist/" . substr($term_code,-3) . "-?????.json");
    $booklists = array();
    foreach ($files as $file) {
        list($term_code, $section) = explode('-', str_replace(array('booklist/','.json'),'',$file));
        $booklists[] = $section;
    }
    $result = json_encode($booklists);
} else {
    $filenames = glob("booklist/???-?????.json");
    $term_codes = array();
    foreach ($filenames as $filename) {
        $term_codes[] = '20'. substr($filename,9,3);
    }
    $result = json_encode(array_unique($term_codes,SORT_NUMERIC));
}


if ($callback !== "") {
    header('Content-Type: application/javascript');
    echo $callback . '(';
} else {
    header('Content-Type: application/json');
}
echo $result;
if ($callback !== "") {
    echo ')';
}
?>
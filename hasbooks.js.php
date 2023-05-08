
<?php
if (isset($_SERVER['PATH_INFO'])) {
    $term_code = strip_tags(str_replace(array("'",'"'," ","/"),'',urldecode($_SERVER['PATH_INFO'])));
} else if (isset($_GET['term'])) {
    $term_code = strip_tags(str_replace(array("'",'"'," ","/"),'',urldecode($_GET['term'])));
} else {
    $yr = Date("Y");
    $mon = Date("n");
    if ($mon >= 1 && $mon < 4) {
        $term_code = substr($yr."1",2);
    } else if ($mon >= 4 && $mon < 7) {
        $term_code = substr($yr."2",2);
    } else if ($mon >= 7 && $mon <= 11) {
        $term_code = substr($yr."3",2);
    } else {
        $term_code = substr($yr."3",1);
    }
}
$jsonp_callback = false;
if (isset($_GET['jsonp_callback'])) {
    $jsonp_callback = strip_tags(str_replace(array("'",'"'," ","/"),'',urldecode($_GET['jsonp_callback'])));
}


$files = glob("booklist/$term_code-?????.json");
$booklists = array();
foreach ($files as $file) {
    list($term_code, $section) = explode('-', str_replace(array('booklist/','.json'),'',$file));
    $booklists[] = $section;
}
if ($jsonp_callback !== false) {
    echo $jsonp_callback."(".
        json_encode($booklists). ")";
} else {
    echo json_encode($booklists);
}
?>
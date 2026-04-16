<?php
function each(&$array) {
    $res = array();
    $key = key($array);
    if ($key !== null) {
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    } else {
        $res = false;
    }
    return $res;
}

if (isset($_GET["Id"])) {
    $id = $_GET["Id"];
    
    if ( isset($_COOKIE[$id]) ) {
        while ( list($name, $value) = each($_COOKIE[$id]) ) {
            setcookie($id."[".$name."]", "", time()-3600);
        }
    }
}

header("Location: shoppingcart.php");
exit();
?>
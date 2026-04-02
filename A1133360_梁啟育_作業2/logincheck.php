<?php
$fID="a";
$fPWD="123";

if(isset($_POST["uID"])&&isset($_POST["uPWD"])){

    $uID=$_POST["uID"];
    $uPWD=$_POST["uPWD"];

    if($fID==$uID&&$fPWD==$uPWD){
        header("Location:作業1.php");
    }else{
        echo "fail";
        header("Refresh:2;url=login.php");
    }
}
?>
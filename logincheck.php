<?php
session_start();

$fID="student";
$fPWD="123";

$aID="teacher";
$aPWD="1234";

$bID='admin';
$bPWD='12345';

if(isset($_POST["uID"])&&isset($_POST["uPWD"])){
    
    $uID=$_POST['uID'];
    $uPWD=$_POST['uPWD'];
    $date=strtotime("+3 days",time());

    if($uID==$fID&&$uPWD==$fPWD){
        $_SESSION['login']='student';
        setcookie("ID", $uID, time()+3600);
        header("Refresh:1;url=student.php");
    }elseif($uID==$aID&&$uPWD==$aPWD){
        $_SESSION['login']='teacher';
        setcookie("ID", $uID, time()+3600);
        header("Refresh:1;url=teacher.php");
    }elseif($uID==$bID&&$uPWD==$bPWD){
        $_SESSION['login']='admin';
        setcookie("ID", $uID, time()+3600);
        header("Refresh:1;url=admin.php");
    }
    else{
        header("Refresh:2;url=login.php");
        echo "fail";
    }
}
?>
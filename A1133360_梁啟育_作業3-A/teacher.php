<?php
session_start();
if(isset($_SESSION['login'])){
    if($_SESSION['login']=='teacher'){
        echo "Welcome!<br>";
        echo "你的 ID 是：" . $_COOKIE['ID'];
        echo "<br>";
        echo "<a href='cookiedel.php'>刪除cookie</a><br>";
        echo "<a href='logout.php'>Logout</a>";
    }else{
        echo "Login illegal.";
        header("Refresh:1;url=login.php");
    }
}else{
        echo "Login illegal.";
        header("Refresh:1;url=login.php");
    }
?>
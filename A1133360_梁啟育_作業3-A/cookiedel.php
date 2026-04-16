<?php
setcookie('ID','',time()-100);
header("Refresh:1;url=login.php");
?>
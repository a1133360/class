<?php
echo "Your name is:".$_POST["nName"]."<br>";
$nGender=$_POST["nGender"];
$nDate=$_POST["nDate"];
$nDistrict=$_POST["nDistrict"];
$nInterest=$_POST["nInterest"];
$nNumber=$_POST["nNumber"];
$nEmail=$_POST["nEmail"];
$nComment=$_POST["nComment"];
echo "Your gender is:".$nGender."<br>";
echo "Your birthday is:".$nDate."<br>";
echo "Your hometown is:".$nDistrict."<br>";
echo "Your Interest is:";
foreach($nInterest as $nI){
    switch($nI){
        case "basketball";
            echo "籃球 ";
            break;
        case "baseball";
            echo "棒球 ";
            break;
        case "badminton";
            echo "羽球";
            break;
    }
}
echo "<br>";
echo "Your age is:".$nNumber."<br>";
echo "Your email is:".$nEmail."<br>";
echo "Your opinion:<br>".$nComment."<br>";
?>
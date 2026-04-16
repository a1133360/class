<?php
session_start();
if ( isset($_POST["Item"]) ) {
    $_SESSION["Quantity"] = $_POST["Quantity"];
    $id = $_POST["Item"];
    $_SESSION["ID"] = $id;

    switch (strtoupper($id)) {
        case "S001":
            $_SESSION["Name"] = "10吋平板電腦";
            $_SESSION["Price"] = 12000;
            break;
        case "S002":
            $_SESSION["Name"] = "15.6吋筆記型電腦";
            $_SESSION["Price"] = 27000;
            break;
        case "S003":
            $_SESSION["Name"] = "iPhone智慧型手機";
            $_SESSION["Price"] = 21000;
            break;
    }
    
    header("Location: savecart.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>商品目錄</title>
</head>
<body>
    <h2>購物車 - 商品目錄</h2>
    <form action="catalog.php" method="post">
        選擇商品：
        <select name="Item">
            <option value="S001">10吋平板電腦 - $12000</option>
            <option value="S002">15.6吋筆記型電腦 - $27000</option>
            <option value="S003">iPhone智慧型手機 - $21000</option>
        </select>
        <br><br>
        輸入數量：
        <input type="number" name="Quantity" value="1" min="1">
        <br><br>
        <input type="submit" value="加入購物車">
    </form>
    <hr>
    <a href="shoppingcart.php">檢視購物車</a>
</body>
</html>
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>檢視購物車</title>
</head>
<body>
    <h2>您的購物車</h2>
    <table border="1" cellpadding="5">
        <tr bgcolor="#DDDDDD">
            <th>功能</th>
            <th>商品 ID</th>
            <th>商品名稱</th>
            <th>單價</th>
            <th>數量</th>
        </tr>
        <?php
        $total = 0;
        $flag = false;

        while ( list($arr, $value) = each($_COOKIE) ) {
            if ( isset($_COOKIE[$arr]) && is_array($_COOKIE[$arr]) ) {
                if ($flag) {
                    $flag = false;
                    $color = "#FF99CC";
                } else {
                    $flag = true;
                    $color = "#99FFCC";
                }
                
                echo "<tr bgcolor='".$color."'>";
                echo "<td><a href='delete.php?Id=".$arr."'>刪除</a></td>";
                
                $price = 0;
                $quantity = 0;

                while ( list($name, $value) = each($_COOKIE[$arr]) ) {
                    echo "<td>" . $value . "</td>";
                    
                    if ($name == "Price") $price = $value;
                    if ($name == "Quantity") $quantity = $value;
                }
                
                $total += $price * $quantity;
                echo "</tr>";
            }
        }
        ?>
    </table>
    
    <h3>購物車總金額：<?php echo $total; ?> 元</h3>
    <hr>
    <a href="catalog.php">繼續購物</a>
</body>
</html>
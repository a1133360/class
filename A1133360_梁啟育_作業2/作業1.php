<html>
<head>
<title>夏令營報名表單</title>
<style>
body{
    background-color: #f0feff;
}
</style>
</head>
<body>
<form action="result.php" method="POST">
<center><font size=8 color=blue>夏令營報名表單</font></center>
<br>
姓名:<input type="text" placeholder="Your name" name="nName"><br>
性別:
男<input type="radio" name="nGender" value="male">
女<input type="radio" name="nGender" value="female"><br>
生日:
<input type="date" name="nDate" value="n"><br>
你來自哪個地區?<select name="nDistrict">
<option value="North">北部</option>
<option value="Center">中部</option>
<option value="South">南部</option>
<option value="East">東部</option>
</select>
<br>
有興趣的項目:
<br>
籃球<input type="checkbox" name="nInterest[]" value="basketball"><br>
<img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSRiSsl9eMEorv6Am2Alclqi5CAncdaeh7yUw&s"></img><br>
棒球<input type="checkbox" name="nInterest[]" value="baseball"><br>
<img src="https://d24o4k0vdyt0z8.cloudfront.net/thumb/20250313/e3cdaa130b24142f6ddee459c331896e8bdc5690d2b3874907fe624fedf7e3b52e641f6bc3f5307803d31edb9be1241f866f1f11f9dfc3ad0b60aea9d36fbfa3.jpg" width="250" height="300"></img><br>
羽球<input type="checkbox" name="nInterest[]" value="badminton"><br>
<img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRTgL1Oq1Xj4R2F4gId8S4wSUCcwTdGMNdniQ&s"></img><br>
年紀:
<input type="number" name="nNumber" value="m" min="1" max="100"><br>
Email:
<input type="email" name="nEmail" value="xxxxx@gmail.com"><br>
<textarea name="nComment" rows="10" cols="40">
備註:
</textarea>
<br>
<input type="submit">
</form>
</body>
</html>
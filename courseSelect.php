<?php
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
session_start();
if(!isset($_SESSION['id'])) {
   header("Location: https://www-student.cse.buffalo.edu/CSE442-542/2019-Summer/cse-442e/index.php");
   exit();
 }
$email = $_SESSION['email'];
$id = $_SESSION['id'];
$Student_ID = $_SESSION['Student_ID'];
require "lib/database.php";
$con = connectToDatabase();


 $student_classes =array();
 $class_IDs = array();
 $stmt = $con->prepare('SELECT DISTINCT course.Name, course.Course_ID FROM `Teammates`  INNER JOIN course 
ON Teammates.Course_ID = course.Course_ID WHERE Teammates.Student_ID=?');
 $stmt->bind_param('i', $Student_ID);
 $stmt->execute();
 $stmt->bind_result($class_name,$class_ID);
 $stmt->store_result();
 while ($stmt->fetch()){
   $student_classes[$class_name] = $class_ID;
 }
 $_SESSION['student_classes'] = $student_classes;

 if(isset($_POST['courseSelect'])){
	 
   $_SESSION['course'] = $_POST['courseSelect'];
   $_SESSION['course_ID'] = $_SESSION['student_classes'][$_SESSION['course']];


   header("Location: peerEvalForm.php");
   exit();
 }
 
 ?>
<!DOCTYPE HTML>
<html>
<title>UB CSE course select</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://www.w3schools.com/lib/w3-theme-blue.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.min.css">
<body>


<style>

hr {
    clear: both;
    visibility: hidden;

}


.dropbtn {
  background-color: #4CAF50;
  color: white;
  padding: 16px;
  font-size: 16px;
  border: none;
}

.dropdown {
  position: relative;
  display: inline-block;
}

.dropdown-content {
  display: none;
  position: absolute;
  background-color: #f1f1f1;
  min-width: 160px;
  box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
  z-index: 1;
}

.dropdown-content a {
  color: black;
  padding: 12px 16px;
  text-decoration: none;
  display: block;
}

.dropdown-content a:hover {background-color: #ddd;}

.dropdown:hover .dropdown-content {display: block;}

.dropdown:hover .dropbtn {background-color: #3e8e41;}

</style>

<!-- Header -->
<header id="header" class="w3-container w3-theme w3-padding">
    <div id="headerContentName"  <font class="w3-center w3-theme"> <h1> Please select the course you would like to complete a peer evaluation for. </h1> </font> </div>
</header>

<hr>

<div id= "dropdown" style="text-align:center;">
<div class="dropdown w3-theme w3-center ">
  <!--
<button class="dropbtn w3-theme w3-center">Dropdown</button>
  <div class="dropdown-content w3-theme w3-center">
  -->
  <form id="courseSelect" class="w3-container w3-card-4 w3-light-blue" method='post'>
    <select name ="courseSelect">
      <?php
      if(isset($_SESSION['student_classes'])){
       foreach ($student_classes as $key => $value) {
      echo ('<option value="' . $key .'">' . $key .'</option>');
      }
    }
?>

  </div>

</div>
<input type='submit' id="EvalSubmit" class="w3-center w3-button w3-theme-dark" value="Continue"></input>
</form>
</div>

</body>
</html>

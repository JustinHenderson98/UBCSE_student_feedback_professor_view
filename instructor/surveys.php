<?php
//error logging
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
ini_set("error_log", "~/php-error.log");

// start the session variable
session_start();

// bring in required code
require_once "../lib/database.php";
require_once "../lib/constants.php";
require_once "lib/termPresentation.php";
require_once "lib/courseQueries.php";

// set timezone
date_default_timezone_set('America/New_York');

// query information about the requester
$con = connectToDatabase();

//try to get information about the instructor who made this request by checking the session token and redirecting if invalid
if (!isset($_SESSION['id'])) {
  http_response_code(403);
  echo "Forbidden: You must be logged in to access this page.";
  exit();
}
$instructor_id = $_SESSION['id'];
  
// Just to be certain, we will unset any session variables that we are using to track state within a process
unset($_SESSION["rubric_reviewed"]);
unset($_SESSION["rubric"]);

// Find out the term that we are currently in
$month = idate('m');
$term = MONTH_MAP_SEMESTER[$month];
$year = idate('Y');

// store information about courses based on the terms. The value for each term will be a map of course ids to courses
$terms = array();

// get information about all courses an instructor teaches in priority order
$courses = getAllCoursesForInstructor($con, $instructor_id);

foreach ($courses as $course_info) {
  $tempSurvey = array();
  $tempSurvey['name'] = $course_info['name'];
  $tempSurvey['semester'] = SEMESTER_MAP_REVERSE[$course_info['semester']];
  $tempSurvey['year'] = $course_info['year'];
  $tempSurvey['code'] = $course_info['code'];
  $tempSurvey['id'] = $course_info['id'];
  // If this course is current or in the future, we can create new surveys for it
  $tempSurvey['mutable'] = ($tempSurvey['year'] >= $year) && ($course_info['semester'] >= $term);
  // Create the arrays we will need for later
  $tempSurvey['upcoming'] = array();
  $tempSurvey['active'] = array();
  $tempSurvey['expired'] = array();
  $term_name = $tempSurvey['year']." ".$tempSurvey['semester'];
  $term_courses = null;
  if (array_key_exists($term_name, $terms)) {
    $term_courses = $terms[$term_name];
  } else {
    $term_courses = array();
  }

  $term_courses[$tempSurvey['id']] = $tempSurvey;
  $terms[$term_name] = $term_courses;
}

$terms = addSurveysToCourses($con, $terms);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
  <script>
    function updateRoster() {
      // Create the fake form we are uploading
      let formData = new FormData();
      formData.append("roster-file", document.getElementById("roster-file").files[0]);
      formData.append("course-id", document.getElementById("roster-course-id").value);
      fetch('rosterUpdate.php', {method: "POST", body: formData})
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            document.getElementById("roster-file-label").innerHTML = "Success!";
            // Show success message for 5 seconds before modal closes
            setTimeout(() => { $('#rosterUpdateModal').modal('hide');}, 5000);
          } else {
            document.getElementById("roster-file-label").innerHTML = data.error;
          }
        });
    }
  </script>
  <title>CSE Evaluation Survey System - Instuctor Overview</title>
</head>
<body class="text-center">
<!-- Header -->
<main>
  <div class="container-fluid">
    <div class="modal fade" id="rosterUpdateModal" tabindex="-1" aria-labelledby="rosterUpdateModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="rosterUpdateModalLabel">Add to course roster</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
          <input type="hidden" id="roster-course-id" value=""></input>
          <span style="font-size:small;color:DarkGrey">File needs 2 columns per row: <tt>name</tt>, <tt>email address</tt></span>
          <div class="input-group input-group-sm">
          <input type="file" id="roster-file" class="form-control" name="roster-file"></input>
          <label for="roster-file" id="roster-file-label"></label></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="updateRoster()">Update</button>
          </div>
        </div>
      </div>
    </div>
    <div class="row justify-content-md-center bg-primary mt-1 mx-1 rounded-pill">
      <div class="col-sm-auto text-center">
        <h4 class="text-white display-1">UB CSE Evalution System<br>Instructor Overview</h4>
      </div>
    </div>
    <div class="row mt-5 mx-4">
    <div class="col">
        <a href="rubricReview.php" class="btn btn-outline-secondary btn-lg">View Existing Rubrics</a>
      </div>
      <div class="col ms-auto">
        <a href="courseAdd.php" class="btn btn-success btn-lg">+ Add Class</a>
      </div>
      <div class="col ms-auto">
        <a href="rubricAdd.php" class="btn btn-outline-secondary btn-lg">+ Add Rubric</a>
      </div>
    </div>
    <div class="row justify-content-md-center mt-5 mx-4">
      <div class="accordion" id="surveys">
        <?php
        $counter = 0;
        foreach ($terms as $name => $course_list) {
          emit_term($counter,$name, $course_list);
          $counter++;
        }
        ?>
      </div>
    </div>
  </div>
</main>
<script>
  let rosterModal = document.getElementById("rosterUpdateModal");
  rosterModal.addEventListener('show.bs.modal', function (event) {
      // Get the course name from the button that was clicked
      let course_name = event.relatedTarget.getAttribute('data-bs-coursename')      
      let modTitle = document.getElementById("rosterUpdateModalLabel");
      modTitle.innerHTML = "Update " + course_name + " Roster";
      // Also get the course id from the button that was clicked
      let course_id = event.relatedTarget.getAttribute('data-bs-courseid')
      let courseIdInput = document.getElementById("roster-course-id");
      courseIdInput.value = course_id;
      // Clear the file input in case it had been used before
      let modFile = document.getElementById("roster-file");
      modFile.value ='';
      modFile.value = null;
  });
</script>
</body>
</html>
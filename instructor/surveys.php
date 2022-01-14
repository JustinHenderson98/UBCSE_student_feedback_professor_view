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
require_once "../lib/infoClasses.php";
require_once "../termPresentation.php";

// set timezone
date_default_timezone_set('America/New_York');

// query information about the requester
$con = connectToDatabase();

// try to get information about the instructor who made this request by checking the session token and redirecting if invalid
$instructor = new InstructorInfo();
$instructor->check_session($con, 0);

// store information about courses based on the terms. The value for each term will be a map of course ids to courses
$terms = array();

// get information about all courses an instructor teaches in priority order
$stmt1 = $con->prepare('SELECT name, semester, year, code, id FROM course WHERE instructor_id=? ORDER BY year DESC, semester DESC, code DESC');
$stmt1->bind_param('i', $instructor->id);
$stmt1->execute();
$result1 = $stmt1->get_result();

while ($row = $result1->fetch_assoc()) {
  $tempSurvey = array();
  $tempSurvey['name'] = $row['name'];
  $tempSurvey['semester'] = SEMESTER_MAP_REVERSE[$row['semester']];
  $tempSurvey['year'] = $row['year'];
  $tempSurvey['code'] = $row['code'];
  $tempSurvey['id'] = $row['id'];
  $term_name = $tempSurvey['year']." ".$tempSurvey['semester'];
  $term_courses = null;
  if (array_key_exists($term_name, $terms)) {
    $term_courses = $terms[$term_name];
  } else {
    $term_courses = array();
    $terms[$term_name] = $term_courses;
  }
  $term_courses[$tempSurvey[$id]] = $tempSurvey;
}

// get today's date
$today = new DateTime();

// Now get data on all of the surveys in each of those courses
foreach ($terms as $term_courses) {
  foreach($term_courses as $course) {
    // store information about surveys as three arrays for each type
    $course['upcoming'] = array();
    $course['active'] = array();
    $course['expired'] = array();

    // Get the course's surveys in reverse chronological order
    $stmt2 = $con->prepare('SELECT course_id, name, start_date, expiration_date, rubric_id, id FROM surveys WHERE course_id=? ORDER BY start_date DESC, expiration_date DESC');
    $stmt2->bind_param('i', $course['id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    while ($row = $result2->fetch_assoc()) {
        $survey_info = array();
        $survey_info['course_id'] = $row['course_id'];
        $survey_info['name'] = $row['name'];
        $survey_info['start_date'] = $row['start_date'];
        $survey_info['expiration_date'] = $row['expiration_date'];
        $survey_info['rubric_id'] = $row['rubric_id'];
        $survey_info['id'] = $row['id'];

        // Determine the completion rate of the survey
        $stmt_total = $con->prepare('SELECT COUNT(reviewers.id) AS total, COUNT(evals.id) AS completed 
                                    FROM reviewers 
                                    LEFT JOIN evals on evals.reviewers_id=reviewers.id
                                    WHERE survey_id=?');
        $stmt_total->bind_param('i', $survey_info['id']);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $data_total = $result_total->fetch_all(MYSQLI_ASSOC);

        // Generate and store that progress as text
        $percentage = 0;
        if ($data_total[0]['total'] != 0) {
          $percentage = floor(($data_total[0]['completed'] / $data_total[0]['total']) * 100);
        }
        $survey_info['completion'] = $data_total[0]['completed'] . '/' . $data_total[0]['total'] . '<br />(' . $percentage . '%)';

        // determine status of survey. then adjust dates to more friendly format
        $s = new DateTime($survey_info['start_date']);
        $e = new DateTime($survey_info['expiration_date']);
        $survey_info['sort_start_date'] = $survey_info['start_date'];
        $survey_info['sort_expiration_date'] = $survey_info['expiration_date'];
        $survey_info['start_date'] = $s->format('F j, Y') . '<br />' . $s->format('g:i A');
        $survey_info['expiration_date'] = $e->format('F j, Y') . '<br />' . $e->format('g:i A');

        if ($today < $s) {
          $course['upcoming'][] = $survey_info;
        } else if ($today < $e) {
          $course['active'][] = $survey_info;
        } else {
          $course['expired'][] = $survey_info;
        }
      }
    }
  }
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
  <title>CSE Evaluation Survey System - Instuctor</title>
</head>
<body class="text-center">
<!-- Header -->
<main>
  <div class="container-fluid">
    <div class="row justify-content-md-center bg-primary mt-1 mx-1 rounded-pill">
      <div class="col-sm-auto text-center">
        <h1 class="text-white display-1">UB CSE Evalution System</h1><br>
        <p class="text-white lead">Instructor Mode</p>
      </div>
    </div>
    <div class="row justify-content-md-center mt-5 mx-4">
      <div class="accordion" id="surveys">
        <?php
        foreach ($term_courses as $name => $course_list) {
          emit_term_accordian($name, $course_list);
        }
        ?>
      </div>
    </div>
  </div>
</main>
</body>
</html>
?>
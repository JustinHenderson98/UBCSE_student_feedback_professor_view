<?php
  error_reporting(-1); // reports all errors
  ini_set("display_errors", "1"); // shows all errors
  ini_set("log_errors", 1);
  session_start();
  require "lib/constants.php";

  if(!isset($_SESSION['email'])) {
    header("Location: ".SITE_HOME."index.php");
    exit();
  }
  $email = $_SESSION['email'];
  require "lib/database.php";
  require "lib/surveyQueries.php";
  $con = connectToDatabase();

  // Verify that the survey exists
  if (!empty($_GET) && isset($_GET) && isset($_GET['survey'])) {
    $survey = $_GET['survey'];
  } else {
    echo "Bad Request: Missing GET parameters";
    http_response_code(400);
    exit();
  }

  // Verify that the survey is a valid one for this student
  if (validActiveSurvey($con, $survey, $email)) {
    $_SESSION['survey_id'] = $survey;
  } else {
    // This is not a valid survey for this student
    echo "Bad Request: Talk to your instructor about this error.";
    http_response_code(400);
    exit();
  }

  // Get the list of students being evaluated and initialize the variable count
  $_SESSION['group_member_number'] = 0;

  // Setup the names and ids for the student's to review
  $_SESSION['group_members'] = initializeRevieweeData($con, $survey, $email);

  // Get the questions and responses for this survey. For now, this will be hard coded.
  $_SESSION['topics'] = getSurveyTopics($con, $survey);
	$_SESSION['answers'] = array();
  foreach ($_SESSION['topics'] as $topic_id => $topic) {
    $_SESSION['answers'][$topic_id] = getSurveyResponses($con, $topic_id);
  }

  // Now redirect the user to the peer evaluation form
  $loc_string = "Location: ".SITE_HOME."/peerEvalForm.php";
  header($loc_string);
  exit();
?>
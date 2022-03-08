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
require_once "../lib/surveyQueries.php";


// query information about the requester
$con = connectToDatabase();

// try to get information about the instructor who made this request by checking the session token and redirecting if invalid
$instructor = new InstructorInfo();
$instructor->check_session($con, 0);


// respond not found on no query string parameters
$sid = NULL;
if (!isset($_GET['survey']))
{
  http_response_code(404);
  echo "404: Not found.";
  exit();
}
if (!isset($_GET['type']))
{
  http_response_code(404);
  echo "404: Not found.";
  exit();
}

// make sure the type query is one of the valid types. if not, respond not found
if ($_GET['type'] !== 'raw-full' && $_GET['type'] !== 'individual' && $_GET['type'] !== 'average')
{
  http_response_code(404);
  echo "404: Not found.";
  exit();
}

// make sure the query string is an integer, reply 404 otherwise
$sid = intval($_GET['survey']);

if ($sid === 0)
{
  http_response_code(404);
  echo "404: Not found.";
  exit();
}

// try to look up info about the requested survey
$survey_info = array();

$stmt = $con->prepare('SELECT course_id, start_date, expiration_date, rubric_id FROM surveys WHERE id=?');
$stmt->bind_param('i', $sid);
$stmt->execute();
$result = $stmt->get_result();

$survey_info = $result->fetch_all(MYSQLI_ASSOC);

// reply not found on no match
if ($result->num_rows == 0) {
  http_response_code(404);
  echo "404: Not found.";
  exit();
}

// make sure the survey is for a course the current instructor actually teaches
$stmt = $con->prepare('SELECT year FROM course WHERE id=? AND instructor_id=?');
$stmt->bind_param('ii', $survey_info[0]['course_id'], $instructor->id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

// reply forbidden if instructor did not create survey
if ($result->num_rows == 0) {
  http_response_code(403);
  echo "403: Forbidden.";
  exit();
}


// This wil be an array of arrays organized by the person BEING REVIEWED.
$scores = array();
// Array mapping email to total number of points
$totals = array();
// Array mapping email addresses to names
$teammates = array();
// Array mapping email address to normalized results
$averages = array();
// Array mapping email address to names of reviewers
$reviewers = array();

$stmt = $con->prepare('SELECT reviewer_email, students.name, SUM(rubric_scores.score) total_score, COUNT(DISTINCT teammate_email) expected, COUNT(DISTINCT evals.id) actual
                       FROM reviewers
                       INNER JOIN students ON reviewers.reviewer_email=students.email 
                       LEFT JOIN evals ON evals.reviewers_id=reviewers.id 
                       LEFT JOIN scores2 ON scores2.eval_id=evals.id 
                       LEFT JOIN rubric_scores ON rubric_scores.id=scores2.score_id 
                       WHERE survey_id=? 
                       GROUP BY reviewer_email, students.name');
$stmt->bind_param('i', $sid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_array(MYSQLI_NUM)) {
  $email_addr = $row[0];
  $reviewers[$email_addr] = $row[1];
  // If the reviewer completed this survey
  if ($row[3] == $row[4]) {
    // Initialize the total number of points
    $totals[$email_addr] = $row[2] / $row[3];
  }
}
$stmt->close();

// Get the info for everyone who will be evaluated
$stmt = $con->prepare('SELECT DISTINCT teammate_email, students.name
                       FROM reviewers 
                       INNER JOIN students ON reviewers.teammate_email=students.email 
                       WHERE survey_id=?');
$stmt->bind_param('i', $sid);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_array(MYSQLI_NUM)) {
  $email_addr = $row[0];
  $teammates[$email_addr] = $row[1];
  $scores[$email_addr] = array();
}
$stmt->close();

// Get information completed by the reviewer -- how many were reviewed and the total points
$stmt_scores = $con->prepare('SELECT reviewer_email, teammate_email, topic_id, score 
                              FROM reviewers
                              LEFT JOIN evals on evals.reviewers_id=reviewers.id 
                              LEFT JOIN scores2 ON evals.id=scores2.eval_id
                              LEFT JOIN rubric_scores ON rubric_scores.id=scores2.score_id
                              WHERE survey_id=? AND teammate_email=?');
foreach ($teammates as $email => $name) {
  $stmt_scores->bind_param('is',$sid, $email);
  $stmt_scores->execute();
  $result = $stmt_scores->get_result();
  while ($row = $result->fetch_array(MYSQLI_NUM)) {
    if (isset($row[2])) {
      if (!isset($scores[$email][$row[0]])) {
        $scores[$email][$row[0]] = array();
      }
      if (isset($row[2])) {
        $scores[$email][$row[0]][$row[2]] = $row[3];
      }
    }
  }
}
$stmt_scores->close();
$topics = getSurveyTopics($con, $sid);

foreach ($teammates as $email => $name) {
  $sum_normalized = 0;
  $reviews = 0;
  $norm_reviews = 0;
  $personal_average = array();
  foreach (array_keys($topics) as $topic_id) {
    $personal_average[$topic_id] = 0;
  }
  foreach ($scores[$email] as $reviewer => $scored) {
    $sum = 0;
    foreach ($scored as $id => $score) {
      $sum = $sum + $score;
      $personal_average[$id] =  $personal_average[$id] + $score;
    }
    $reviews = $reviews + 1;
    // Verify that this reviewer completed all of their 
    if (isset($totals[$reviewer]) && ($totals[$reviewer] != NO_SCORE_MARKER)) {
      $scores[$email][$reviewer]['normalized'] = ($sum / $totals[$reviewer]);
      $sum_normalized = $sum_normalized + ($sum / $totals[$reviewer]);
      $norm_reviews = $norm_reviews + 1;
    } else {
      $scores[$email][$reviewer]['normalized'] = NO_SCORE_MARKER;
    }
  }
  foreach (array_keys($topics) as $topic_id) {
    if ($reviews == 0) {
      $averages[$email][$topic_id] = NO_SCORE_MARKER;
    } else {
      $averages[$email][$topic_id] = $personal_average[$topic_id] / $reviews;
    }
  }
  if ($norm_reviews == 0) {
    $averages[$email]["overall"] = NO_SCORE_MARKER;
  } else {
    $averages[$email]["overall"] = $sum_normalized / $norm_reviews;
  }
}

// now generate the raw scores output
if ($_GET['type'] === 'individual') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="survey-' . $sid . '-individual-results.csv"');
  $out = fopen('php://output', 'w');
  $header = array("Reviewee");
  foreach ($topics as $topic_id => $question) {
    array_push($header,$question);
  }
  fputcsv($out, $header);
  foreach ($teammates as $email => $name) {
    $line = array($email);
    foreach ($topics as $topic_id => $question) {
      $line[] = $averages[$email][$topic_id];
    }
    fputcsv($out, $line);
  }
  fclose($out);
} else if ($_GET['type'] === 'raw-full') {
  $topics['normalized'] = 'Normalized Score';
  // generate the correct headers for the file download
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="survey-' . $sid . '-raw-results.csv"');
  $out = fopen('php://output', 'w');
  $header = array("Reviewer","Reviewee");
  foreach ($topics as $topic_id => $question) {
    array_push($header,$question);
  }
  fputcsv($out, $header);
  foreach ($teammates as $email => $name) {
    foreach ($scores[$email] as $reviewer => $scored) {
      $line = array();
      array_push($line, $reviewer);
      array_push($line, $email);
      foreach ($topics as $topic_id => $question) {
        if (isset($scored[$topic_id])) {
          array_push($line, $scored[$topic_id]);
        } else {
          array_push($line, '--');
        }
      }
      fputcsv($out, $line);
    }
  }
  fclose($out);
} else {
  // generate the correct headers for the file download
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="survey-' . $sid . '-normalized-averages.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, array("Reviewee","Average Normalized Score"));
  foreach ($averages as $email => $avg_results) {
    $line = array();
    array_push($line, $email);
    if ($avg_results["overall"] === NO_SCORE_MARKER) {
      array_push($line, '--');
    } else {
      array_push($line, $avg_results["overall"]);
    }
    fputcsv($out, $line);
  }
  fclose($out);
}
?>

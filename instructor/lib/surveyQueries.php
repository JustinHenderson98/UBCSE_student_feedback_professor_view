<?
function deleteSurvey($con, $survey_id) {
  $stmt = $con->prepare('DELETE FROM surveys WHERE id=?');
  $stmt->bind_param('i', $survey_id);
  $retVal = $stmt->execute();
  $stmt->close();
  return $retVal;
}

function insertSurvey($con, $course_id, $name, $start, $end, $rubric_id) {
  $stmt = $con->prepare('INSERT INTO surveys (course_id, name, start_date, end_date, rubric_id) VALUES (?, ?, ?, ?, ?)');
  $stmt->bind_param('isssi', $course_id, $name, $start, $end, $rubric_id);
  $stmt->execute();
  $survey_id = $con->insert_id;
  $stmt->close();
  return $survey_id;
}

function updateSurvey($con, $survey_id, $name, $start, $end, $rubric_id) {
  $stmt = $con->prepare('UPDATE surveys SET name = ?, start_date = ?, end_date = ?, rubric_id = ? WHERE id = ?');
  $stmt->bind_param('sssii', $name, $start, $end, $rubric_id, $survey_id);
  $retVal = $stmt->execute();
  $stmt->close();
  return $retVal;
}

function isSurveyInstructor($con, $survey_id, $instructor_id) {
  $stmt = $con->prepare('SELECT surveys.course_id 
                         FROM surveys
                         INNER JOIN courses ON surveys.course_id=courses.id
                         INNER JOIN course_instructors ON courses.id=course_instructors.course_id
                         WHERE surveys.id=? AND course_instructors.instructor_id=?');
  $stmt->bind_param('ii', $survey_id, $instructor_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_all(MYSQLI_ASSOC);
  $retVal = $result->num_rows > 0;
  $stmt->close();
  return $retVal;
}

function getSurveyData($con, $survey_id) {
  // Pessimistically assume that this fails
  $retVal = null;
  $stmt = $con->prepare('SELECT course_id, start_date, end_date, name, rubric_id 
                         FROM surveys 
                         WHERE id=?');
  $stmt->bind_param('i', $survey_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_all(MYSQLI_ASSOC);
  // reply not found on no match
  if ($result->num_rows > 0) {
    $retVal = $row[0];
  }
  $stmt->close();
  return $retVal;
}

function getReviewerData($con, $survey_id) {
  return getSurveyParticipantData($con, $survey_id, 'reviewer_id');
}

function getReviewedData($con, $survey_id) {
  return getSurveyParticipantData($con, $survey_id, 'reviewed_id');
}

function getSurveyParticipantData($con, $survey_id, $retrieved_field) {
  $ret_val = array();
  $stmt = $con->prepare('SELECT DISTINCT students.id, email, name
                         FROM reviews 
                         INNER JOIN students ON reviews.' . $retrieved_field . '=students.id 
                         WHERE survey_id=?');
  $stmt->bind_param('i', $survey_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $student_id = $row[0];
    $ret_val[$student_id] = array("email"=>$row[1], "name"=>$row[2]);
  }
  $stmt->close();
  return $ret_val;
}

function getCompletionData($con, $survey_id) {
  $ret_val = array();
  $stmt = $con->prepare('SELECT reviews.reviewer_id, reviews.team_id, COUNT(reviews.id) expected, COUNT(evals.id) actual
                         FROM reviews
                         LEFT JOIN evals ON evals.reviews_id=reviews.id
                         WHERE survey_id=?
                         GROUP BY reviews.reviewer_id, reviews.team_id');
  $stmt->bind_param('i', $survey_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $student_id = $row[0];
    $team_id = $row[1];
    $completed = ($row[2] == $row[3]);
    if (!array_key_exists($student_id, $ret_val)) {
      $ret_val[$student_id] = array();
    }
    $ret_val[$student_id][$team_id] = $completed;
  }
  $stmt->close();
  return $ret_val;
}

function getSurveyTotals($con, $survey_id, $teammates) {
  $ret_val = array();
  $stmt = $con->prepare('SELECT reviewer_id, reviews.team_id, SUM(score * eval_weight) 
                         FROM reviews
                         LEFT JOIN evals on evals.reviews_id=reviews.id
                         LEFT JOIN scores ON evals.id=scores.eval_id
                         LEFT JOIN rubric_scores ON rubric_scores.id=scores.rubric_score_id
                         LEFT JOIN rubric_topics ON rubric_topics.id=scores.topic_id
                         WHERE survey_id=? AND question_response <> "'.FREEFORM_QUESTION_TYPE.'"
                         GROUP BY reviewer_id, reviews.team_id');
  $stmt->bind_param('i',$survey_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $student_id = $row[0];
    $team_id = $row[1];
    $total = $row[2];
    if (!array_key_exists($student_id, $ret_val)) {
      $ret_val[$student_id] = array();
    }
    $ret_val[$student_id][$team_id] = $total;
  }
  $stmt->close();
  return $ret_val;
}

function getSurveyWeights($con, $survey_id, $teammates) {
  $ret_val = array();
  $stmt = $con->prepare('SELECT reviews.reviewer_id, eval_weight
                         FROM reviews
                         WHERE survey_id=? AND reviews.reviewed_id=?');
  foreach (array_keys($teammates) as $student_id) {
    $stmt->bind_param('ii',$survey_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
      $reviewer = $row[0];
      $weight = $row[1];
      if (!isset($ret_val[$student_id])) {
        $ret_val[$student_id] = array("total" => 0);
      }
      $ret_val[$student_id][$reviewer] = $weight;
      $ret_val[$student_id]["total"] = $ret_val[$student_id]["total"] + $weight;
    }
  }
  $stmt->close();
  return $ret_val;
}

function getSurveyScores($con, $survey_id, $teammates) {
  $ret_val = array();
  $stmt = $con->prepare('SELECT reviewer.id, topic_id, score 
                         FROM reviews
                         INNER JOIN evals on evals.reviews_id=reviews.id 
                         INNER JOIN scores ON evals.id=scores.eval_id
                         INNER JOIN rubric_scores ON rubric_scores.id=scores.rubric_score_id
                         WHERE survey_id=? AND reviewer.reviewed_id=? AND topic_id IS NOT NULL');
  foreach (array_keys($teammates) as $student_id) {
    // Create the space for this teammate -- ASSUMES TEAMMATES ARE UNIQUE
    $ret_val[$student_id] = array();
    $stmt->bind_param('ii',$survey_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
      $reviewer_id = $row[0];
      $topic_id = $row[1];
      $score = $row[2];
      // Create space the first time we see the teammate reviewed by the reviewer
      if (!isset($ret_val[$student_id][$reviewer_id])) {
        $ret_val[$student_id][$reviewer_id] = array();
      }
      $ret_val[$student_id][$reviewer_id][$topic_id] = $score;
    }
  }
  $stmt->close();
  return $ret_val;
}
?>
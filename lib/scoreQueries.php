<?php
  function getEvalScores($db_connection, $eval_id) {
    $retVal = array();
    $stmt = $db_connection->prepare('SELECT topic_id, score_id FROM scores2 WHERE eval_id=? AND score_id IS NOT NULL');
    $stmt->bind_param('i', $eval_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
      $retVal[$row[0]] = $row[1];
    }
    return $retVal;
  }

  function getReviewScores($db_connection, $review_id, $topics, $answers) {
    $query_str = 'SELECT topic_id, score_id FROM scores2 INNER JOIN evals ON scores2.evals_id=evals.id WHERE evals.reviewers_id=?';
    $retVal = array();
    // Prepare the next selection statement
    $stmt = $db_connection->prepare($query_str);
    $stmt->bind_param('i', $review_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
      $topic_id = $row[0];
      $score = $row[1];
      if (!array_key_exists($topic_id, $topics)) {
        // This is not a valid survey for this student
        echo "ERROR: Survey submission was invalid. Talk to your instructor about this error.";
        http_response_code(400);
        exit();
      }
      $retVal[$topic_id] = $score;
    }
    $stmt->close();
    return $retVal;
  }

  function insertNewScore($db_connection, $eval_id, $topic_id, $score_id) {
    $query_str = 'INSERT INTO scores2 (eval_id, topic_id, score_id) VALUES (?,?,?)';
    $stmt = $db_connection->prepare($query_str);
    $stmt->bind_param('iii',$eval_id, $topic_id, $score_id);
    $stmt->execute();
    $stmt->close();
  }

  function updateExistingScore($db_connection, $eval_id, $topic_id, $score_id) {
    $query_str = 'UPDATE scores2 SET score_id=? WHERE eval_id=? AND topic_id=?';
    $stmt = $db_connection->prepare($query_str);
    $stmt->bind_param('iii',$score_id, $eval_id, $topic_id);
    $stmt->execute();
    $stmt->close();
  }
?>
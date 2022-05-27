<?php
function emitAveragesTable($topics, $answers, $scores, $members) {
    foreach ($topics as $topic_id => $topic) {
        echo '<div class="col-2 ms-auto"><b>'.$topic.'</b></div>';
    }
    echo '</div>';
    echo '<div class="row py-2 mx-1 align-items-stretch border-bottom border-1 border-secondary" style="background-color:#e1e1e1">';
    foreach ($topics as $topic_id => $topic) {
        echo '<div class="col-2 ms-auto"><b>'.end($answers[$topic_id]).'</b></div>';
    }
    echo '</div>';
    echo '<div class="row py-2 mx-1 align-items-stretch border-bottom border-1 border-secondary" style="background-color:#f8f8f8"">';
    foreach ($topics as $topic_id => $topic) {
        $sum = 0;
        $count = 0;
        foreach ($scores as $submit) {
            if (isset($submit[$topic_id])) {
              $sum += $submit[$topic_id];
              $count++;
            }
        }
        if ($count > 0) {
            $average = $sum / $count;
            echo '<div class="col-2 ms-auto">'.$average.'</div>';
        } else {
            echo '<div class="col-2 ms-auto">--</div>';
        }

    }
    echo '</div>';
  }

  function emitResultsTable($topics, $answers, $scores, $members) {
    echo '<div class="col-2"><b>Name</b></div>';
    foreach ($topics as $topic_id => $topic) {
        echo '<div class="col-2 ms-auto"><b>'.$topic.'</b></div>';
    }
    echo '</div>';
    $shaded = true;
    foreach ($members as $reviewer_id => $name) {
        if ($shaded) {
            $bg_color = "#e1e1e1";
        } else {
            $bg_color = "#f8f8f8";
        }
        echo '<div class="row py-2 mx-1 align-items-stretch border-bottom border-1 border-secondary" style="background-color:'.$bg_color.'">';
        echo '  <div class="col-2 text-center"><b>'.$name.'</b></div>';
        foreach ($topics as $topic_id => $topic) {
            echo '<div class="col-2 ms-auto">'.$answers[$topic_id][$scores[$reviewer_id][$topic_id]].'</div>';
        }
        echo '</div>';
        $shaded = !$shaded;
    }
  }
?>
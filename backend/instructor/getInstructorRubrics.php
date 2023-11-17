<?php

function create_levels_array($scores) {
	$ret_val = array("names" => array(), "values" => array());
	$max_level = count($scores) - 1;
	$cur_level = 0;
	foreach (array_values($scores) as $score) {
		if ($cur_level == 0) {
			$ret_val["names"]["level5"] = $score["name"];
			$ret_val["values"]["level5"] = $score["score"];
		} else if ($cur_level == $max_level) {
			$ret_val["names"]["level1"] = $score["name"];
			$ret_val["values"]["level1"] = $score["score"];
		} else if ($cur_level == 1) {
			if (($max_level == 3) || ($max_level == 4)) {
				$ret_val["names"]["level4"] = $score["name"];
				$ret_val["values"]["level4"] = $score["score"];
			} else {
				$ret_val["names"]["level3"] = $score["name"];
				$ret_val["values"]["level3"] = $score["score"];
			}
		} else if ($cur_level == 2) {
			if ($max_level == 3) {
				$ret_val["names"]["level2"] = $score["name"];
				$ret_val["values"]["level2"] = $score["score"];
			} else {
				$ret_val["names"]["level3"] = $score["name"];
				$ret_val["values"]["level3"] = $score["score"];
			}
		} else if ($cur_level == 3) {
			$ret_val["names"]["level2"] = $score["name"];
			$ret_val["values"]["level2"] = $score["score"];
		}
		$cur_level = $cur_level + 1;
	}
	return $ret_val;
}

function create_topics_array($topics) {
	$ret_val = array();
	foreach ($topics as $topic) {
		$topic_data = array();
		$topic_data["question"] = $topic["question"];
		$topic_data["type"] = $topic["type"];
		$topic_data["responses"] = array();
		$max_level = count($topic["responses"]) - 1;
		$cur_level = 0;
		foreach (array_values($topic["responses"]) as $response) {
			if ($cur_level == 0) {
				$topic_data["responses"]["level5"] = $response;
			} else if ($cur_level == $max_level) {
				$topic_data["responses"]["level1"] = $response;
			} else if ($cur_level == 1) {
				if (($max_level == 3) || ($max_level == 4)) {
					$topic_data["responses"]["level4"] = $response;
				} else {
					$topic_data["responses"]["level3"] = $response;
				}
			} else if ($cur_level == 2) {
				if ($max_level == 3) {
					$topic_data["responses"]["level2"] = $response;
				} else {
					$topic_data["responses"]["level3"] = $response;
				}
			} else if ($cur_level == 3) {
				$topic_data["responses"]["level2"] = $response;
			}
			$cur_level = $cur_level + 1;
		}
		$ret_val[] = $topic_data;
	}
	return $ret_val;
}

//error logging
error_reporting(-1); // reports all errors
ini_set("display_errors", "1"); // shows all errors
ini_set("log_errors", 1);
ini_set("error_log", "~/php-error.log");

//start the session variable
session_start();

//bring in required code
require_once "../lib/database.php";
require_once "../lib/constants.php";
require_once "lib/instructorQueries.php";
require_once "lib/rubricQueries.php";

//query information about the requester
$con = connectToDatabase();

//try to get information about the instructor who made this request by checking the session token and redirecting if invalid
if (!isset($_SESSION['id'])) {
  http_response_code(403);
  echo "Forbidden: You must be logged in to access this page.";
  exit();
}
$instructor_id = $_SESSION['id'];







?>
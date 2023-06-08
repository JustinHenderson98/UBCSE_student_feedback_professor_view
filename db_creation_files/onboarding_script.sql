-- faculty table
-- each row defines a single instructor who could use this system
CREATE TABLE `instructors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` TEXT NOT NULL,
  `email` VARCHAR(20) NOT NULL,
  `init_auth_id` VARCHAR(255),
  `session_token` VARCHAR(255),
  `session_expiration` INT,
  `csrf_token` VARCHAR(255),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `init_auth` (`init_auth_id`),
  UNIQUE KEY `session_token` (`session_token`),
  UNIQUE KEY `csrf_token` (`csrf_token`)
) ENGINE=InnoDB;


-- courses table
-- each row defines a specific course that uses this system
CREATE TABLE `courses` ( -- was `course`
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `code` text NOT NULL,
 `name` text NOT NULL,
 `semester` tinyint NOT NULL,
 `year` tinyint NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB;


-- courses table
-- each row defines an instructor of a course; this normalization allows a course to include multiple instructors 
CREATE TABLE `course_instructors` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `course_id` int(11) NOT NULL,
 `instructor_id` int(11) NOT NULL,
 PRIMARY KEY (`id`),
 KEY `course_instructors_instructors_idx` (`instructor_id`),
 CONSTRAINT `course_instructor_instructors_constraint` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
 KEY `course_instructors_course_idx` (`course_id`),
 CONSTRAINT `course_instructor_course_constraint` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;

-- surveys TABLE
-- each row represents a use of this system for a course. Students must only be able to submit evaluations between
-- the start date and end date listed
CREATE TABLE `surveys` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `course_id` int(11) NOT NULL,
 `start_date` datetime NOT NULL,
 `end_date` datetime NOT NULL, -- was expiration_date
 `name` CHARACTER NOT NULL,
 `rubric_id` int(11) NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `id` (`id`),
 KEY `survey_course_idx` (`course_id`),
 CONSTRAINT `survey_course_constraint` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;


-- students TABLE
-- each row is a distinct student who has been added to this system. Each student must only appear once EVEN IF they are registered in multiple classes
CREATE TABLE `students` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `email` varchar(20) NOT NULL,
 `name` text NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB;

-- enrollments TABLE
-- each row is a distinct enrollment of a student in a course within this system. Each student may appear in a course at most once.
CREATE TABLE `enrollments` (
 `student_id` int(11) NOT NULL,
 `course_id` int(11) NOT NULL,
  PRIMARY KEY (`student_id`,`course_id`),
  KEY `enrollments_student_id` (`student_id`),
  KEY `enrollments_course_id` (`course_id`),
  CONSTRAINT `enrollments_student_constraint` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `enrollments_course_constraint` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;


-- reviews TABLE
-- each row represents a set of evaluations that will need to be completed
CREATE TABLE `reviews` ( -- was reviewers
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `survey_id` int(11) NOT NULL,
 `reviewer_id` int(11) NOT NULL,
 `team_id` int(11), -- allowing null for historical purposes
 `reviewed_id` int(11) NOT NULL,
 `eval_weight` int(11) NOT NULL DEFAULT 1,
 PRIMARY KEY (`id`),
 KEY `reviews_survey_id` (`survey_id`),
 KEY `reviews_reviewer_id` (`reviewer_id`),
 KEY `reviews_reviewed_id` (`reviewed_id`),
 CONSTRAINT `reviews_survey_constraint` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
 CONSTRAINT `reviews_reviewer_constraint` FOREIGN KEY (`reviewer_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
 CONSTRAINT `reviews_reviewed_constraint` FOREIGN KEY (`reviewed_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;


-- evals table
-- each row defines a single peer- or self-evaluation. Rows are added/updated only as students complete their evaluations
CREATE TABLE `evals` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `review_id` int(11) NOT NULL, -- was reviewers_id
 PRIMARY KEY (`id`),
 KEY `evals_review_id` (`review_id`),
 CONSTRAINT `evals_review_constraint` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;


-- rubrics TABLE
-- each row represents a single rubric that we can use
CREATE TABLE `rubrics` (
  `id` int(11) NOT NULL,
  `description` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;


-- rubric_scores TABLE
-- each row represents a score levels from the multiple choice questions in this rubric
CREATE TABLE `rubric_scores` (
  `id` int(11) NOT NULL,
  `rubric_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `score` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `rubric_scores_rubric_id` (`rubric_id`),
  CONSTRAINT `rubric_scorse_rubric_constraint` FOREIGN KEY (`rubric_id`) REFERENCES `rubrics` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;


-- rubric_topics TABLE
-- each row represents a score levels from the multiple choice questions in this rubric
CREATE TABLE `rubric_topics` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `rubric_id` int(11) NOT NULL,
 `question` text NOT NULL, 
 `question_response` enum('multiple_choice','text') NOT NULL DEFAULT 'multiple_choice',
 PRIMARY KEY (`id`),
 KEY `rubric_topics_rubric_id` (`rubric_id`),
 CONSTRAINT `rubric_topics_rubric_constraint` FOREIGN KEY (`rubric_id`) REFERENCES `rubrics` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;

-- rubric_responses TABLE
-- each row contains the text of the response at a specific score level on the specific topic
CREATE TABLE `rubric_responses` (
  `topic_id` int(11) NOT NULL,
  `score_id` int(11) NOT NULL,
  `response` text NOT NULL,
  PRIMARY KEY (`topic_id`,`score_id`),
  KEY `rubric_responses_topic_id` (`topic_id`),
  KEY `rubric_responses_score_id` (`score_id`),
  CONSTRAINT `rubric_responses_topic_constraint` FOREIGN KEY (`topic_id`) REFERENCES `rubric_topics` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `rubric_responses_score_constraint` FOREIGN KEY (`score_id`) REFERENCES `rubric_scores` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB;

-- scores TABLE
-- each row represents the score entered by a student on an evaluation in response to a topic
CREATE TABLE `scores` (
 `eval_id` int(11) NOT NULL,
 `topic_id` int(11) NOT NULL,
 `score_id` int(11) NOT NULL,
 PRIMARY KEY (`eval_id`,`topic_id`),
 KEY `scores_eval_id` (`eval_id`),
 KEY `scores_topic_id` (`topic_id`),
 KEY `scores_score_id` (`score_id`),
 CONSTRAINT `scores_eval_constraint` FOREIGN KEY (`eval_id`) REFERENCES `evals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `scores_topic_constraint` FOREIGN KEY (`topic_id`) REFERENCES `rubric_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `scores_score_constraint` FOREIGN KEY (`score_id`) REFERENCES `rubric_scores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- freeforms TABLE
-- each row represents the freeform response entered on an evaluation in response to a freeform question
CREATE TABLE `freeforms` (  -- was freeform
 `eval_id` int(11) NOT NULL,
 `topic_id` int(11) NOT NULL,
 `response` TEXT DEFAULT NULL,
 PRIMARY KEY (`eval_id`,`topic_id`),
 KEY `freeform_eval_id` (`eval_id`),
 KEY `freeform_topic_id` (`topic_id`),
 CONSTRAINT `freeform_eval_constraint` FOREIGN KEY (`eval_id`) REFERENCES `evals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT `freeform_topic_constraint` FOREIGN KEY (`topic_id`) REFERENCES `rubric_topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

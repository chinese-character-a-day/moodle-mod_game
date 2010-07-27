<?php  // $Id: lib.php,v 1.18 2010/07/27 14:16:41 bdaloukas Exp $
/**
 * Library of functions and constants for module game
 *
 * @author 
 * @version $Id: lib.php,v 1.18 2010/07/27 14:16:41 bdaloukas Exp $
 * @package game
 **/


/// CONSTANTS ///////////////////////////////////////////////////////////////////

/**#@+
 * The different review options are stored in the bits of $game->review
 * These constants help to extract the options
 */
/**
 * The first 6 bits refer to the time immediately after the attempt
 */
define('GAME_REVIEW_IMMEDIATELY', 0x3f);
/**
 * the next 6 bits refer to the time after the attempt but while the game is open
 */
define('GAME_REVIEW_OPEN', 0xfc0);
/**
 * the final 6 bits refer to the time after the game closes
 */
define('GAME_REVIEW_CLOSED', 0x3f000);

// within each group of 6 bits we determine what should be shown
define('GAME_REVIEW_RESPONSES',   1*0x1041); // Show responses
define('GAME_REVIEW_SCORES',      2*0x1041); // Show scores
define('GAME_REVIEW_FEEDBACK',    4*0x1041); // Show feedback
define('GAME_REVIEW_ANSWERS',     8*0x1041); // Show correct answers
// Some handling of worked solutions is already in the code but not yet fully supported
// and not switched on in the user interface.
define('GAME_REVIEW_SOLUTIONS',  16*0x1041); // Show solutions
define('GAME_REVIEW_GENERALFEEDBACK', 32*0x1041); // Show general feedback
/**#@-*/


/**
 * Given an object containing all the necessary data, 
 * (defined by the form in mod.html) this function 
 * will create a new instance and return the id number 
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted game record
 **/

function game_add_instance($game) {
    global $DB;

    $game->timemodified = time();
    game_before_add_or_update( $game);
	
    # May have to add extra stuff in here #
    
    $id = $DB->insert_record("game", $game);
    
    $game = $DB->get_record_select( 'game', "id=$id");
    
    // Do the processing required after an add or an update.
    game_grade_item_update( $game);
    
    return $id;
}

/**
 * Given an object containing all the necessary data, 
 * (defined by the form in mod.html) this function 
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function game_update_instance($game) {
    global $DB;

    $game->timemodified = time();
    $game->id = $game->instance;

    if( !isset( $game->glossarycategoryid)){
        $game->glossarycategoryid = 0;
    }
    
    if( !isset( $game->glossarycategoryid2)){
        $game->glossarycategoryid2 = 0;
    }
        
    if( $game->grade == ''){
        $game->grade = 0;
    }

    if( !isset( $game->param1)){
        $game->param1 = 0;
    }

    if( $game->param1 == ''){
        $game->param1 = 0;
    }

    if( !isset( $game->param2)){
        $game->param2 = 0;
    }

    if( $game->param2 == ''){
        $game->param2 = 0;
    }
    
    game_before_add_or_update( $game);
        	
    if( !$DB->update_record("game", $game)){
        return false;
    }
    
    // Do the processing required after an add or an update.
    game_grade_item_update( $game);
    
    return true;    
}

function game_before_add_or_update(&$game) {
    if( $game->gamekind == 'millionaire')
    {
        if( substr( $game->param8, 0, 1) == '#')
        {
            $game->param8 = hexdec(substr( $game->param8, 1));
        }
    }
}

/**
 * Given an ID of an instance of this module, 
 * this function will permanently delete the instance 
 * and any data that depends on it. 
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function game_delete_instance($gameid) {
    global $DB;

    $result = true;

    # Delete any dependent records here #
	
	if( ($recs = $DB->get_records( 'game_attempts', array( 'gameid' => $gameid))) != false){
	    $ids = '';
	    $count = 0;
	    $aids = array();
		foreach( $recs as $rec){
		    $ids .= ','.$rec->id;
		    if( ++$count > 10){
		        $count = 0;
		        $aids[] = $ids;
		        $ids = '';
		    }
		}
		if( $ids != ''){
    		$aids[] = $ids;
        }
        
		foreach( $aids as $ids){
		    if( $result == false){
		        break;
		    }
	        $tables = array( 'game_hangman', 'game_cross', 'game_cryptex', 'game_millionaire', 'game_bookquiz', 'game_sudoku', 'game_snakes');
	        foreach( $tables as $t){
	            $sql = "DELETE FROM {".$t."} WHERE id IN (".substr( $ids, 1).')';
		        if (! $DB->execute( $sql)) {
			        $result = false;
			        break;
                }
            }
		}
	}
		    
    $tables = array( 'game_attempts', 'game_grades', 'game_bookquiz_questions', 'game_queries', 'game_repetitions');
    foreach( $tables as $t){
        if( $result == false){
            break;
        }
		    
        if (! $DB->delete_records( $t, array( 'gameid' =>  $gameid))) {
            $result = false;
		}
	}
	
	if( $result){
        $tables = array( 'game_export_javame', 'game_export_html', 'game');
        if (!$DB->delete_records( 'game', array( 'id' => $gameid))) {
            $result = false;
        }
    }
        
    return $result;
}

/**
 * Return a small object with summary information about what a 
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 **/
function game_user_outline($course, $user, $mod, $game) {
    global $DB;

    if ($grade = $DB->get_record_select('game_grades', "userid=$user->id AND gameid = $game->id", null, 'id,score,timemodified')) {

        $result = new stdClass;
        if ((float)$grade->score) {
            $result->info = get_string('grade').':&nbsp;'.round($grade->score * $game->grade, $game->decimalpoints).' '.
                            get_string('percent', 'game').':&nbsp;'.round(100 * $grade->score, $game->decimalpoints).' %';
        }
        $result->time = $grade->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Print a detailed representation of what a user has done with 
 * a given particular instance of this module, for user activity reports.
 **/
function game_user_complete($course, $user, $mod, $game) {
    global $DB;

    if ($attempts = $DB->get_records_select('game_attempts', "userid='$user->id' AND gameid='$game->id'", null, 'attempt ASC')) {
        if ($game->grade && $grade = $DB->get_record('game_grades', array( 'userid' => $user->id, 'gameid' => $game->id))) {
            echo get_string('grade').': '.game_format_score( $game, $grade->score).'/'.$game->grade.'<br />';
        }
        foreach ($attempts as $attempt) {
            echo get_string('attempt', 'game').' '.$attempt->attempt.': ';
            if ($attempt->timefinish == 0) {
                print_string( 'unfinished');
            } else {
                echo game_format_score( $game, $attempt->score).'/'.$game->grade;
            }
            echo ' - '.userdate($attempt->timelastattempt).'<br />';
        }
    } else {
       print_string('noattempts', 'game');
    }

    return true;
}

/**
 * Given a course and a time, this module should find recent activity 
 * that has occurred in game activities and print it out. 
 * Return true if there was output, or false is there was none. 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function game_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false 
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such 
 * as sending out mail, toggling flags etc ... 
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function game_cron () {
    global $CFG;

    return true;
}

/**
 * Must return an array of grades for a given instance of this module, 
 * indexed by user.  It also returns a maximum allowed grade.
 * 
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $gameid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 **/
function game_grades($gameid) {
/// Must return an array of grades, indexed by user, and a max grade.

    global $DB;

    $game = $DB->get_record( 'game', array( 'id' => intval($gameid)));
    if (empty($game) || empty($game->grade)) {
        return NULL;
    }

    $return = new stdClass;
    $return->grades = $DB->get_records_menu('game_grades', 'gameid', $game->id, '', "userid, score * {$game->grade}");
    $return->maxgrade = $game->grade;

    return $return;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $gameid id of game
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function game_get_user_grades($game, $userid=0) {
    global $DB;

    $user = $userid ? "AND u.id = $userid" : "";

    $sql = 'SELECT u.id, u.id AS userid, '.$game->grade.' * g.score AS rawgrade, g.timemodified AS dategraded, MAX(a.timefinish) AS datesubmitted
            FROM {user} u, {game_grades} g, {game_attempts} a
            WHERE u.id = g.userid AND g.gameid = '.$game->id.' AND a.gameid = g.gameid AND u.id = a.userid';
    if( $userid != 0)
        $sql .= ' AND u.id='.$userid;
    $sql .= ' GROUP BY u.id, g.score, g.timemodified';

    return $DB->get_records_sql( $sql);
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of game. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $gameid ID of an instance of this module
 * @return mixed boolean/array of students
 **/
function game_get_participants($gameid) {
    return false;   //todo
}

/**
 * This function returns if a scale is being used by one game
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $gameid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function game_scale_used ($gameid,$scaleid) {
    $return = false;

    //$rec = get_record("game","id","$gameid","scale","-$scaleid");
    //
    //if (!empty($rec)  && !empty($scaleid)) {
    //    $return = true;
    //}
   
    return $return;
}

/**
 * Update grades in central gradebook
 *
 * @param object $game null means all games
 * @param int $userid specific user only, 0 mean all
 */
function game_update_grades($game=null, $userid=0, $nullifnone=true) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        if( file_exists( $CFG->libdir.'/gradelib.php')){
            require_once($CFG->libdir.'/gradelib.php');
        }else{
            return;
        }
    }

    if ($game != null) {
        if ($grades = game_get_user_grades($game, $userid)) {
            game_grade_item_update($game, $grades);

        } else if ($userid and $nullifnone) {
            $grade = new object();
            $grade->userid   = $userid;
            $grade->rawgrade = NULL;
            game_grade_item_update( $game, $grade);

        } else {
            game_grade_item_update( $game);
        }

    } else {
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                  FROM {game} a, {course_modules} cm, {modules} m
                 WHERE m.name='game' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = $DB->get_recordset_sql( $sql)) {
            while ($game = $DB->rs_fetch_next_record( $rs)) {
                if ($game->grade != 0) {
                    game_update_grades( $game, 0, false);
                } else {
                    game_grade_item_update( $game);
                }
            }
            $DB->rs_close( $rs);
        }
    }
}

/**
 * Create grade item for given game
 *
 * @param object $game object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function game_grade_item_update($game, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        if( file_exists( $CFG->libdir.'/gradelib.php')){
            require_once($CFG->libdir.'/gradelib.php');
        }else{
            return;
        }
    }

    if (array_key_exists('cmidnumber', $game)) { //it may not be always present
        $params = array('itemname'=>$game->name, 'idnumber'=>$game->cmidnumber);
    } else {
        $params = array('itemname'=>$game->name);
    }

    if ($game->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $game->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }


    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/game', $game->course, 'mod', 'game', $game->id, 0, $grades, $params);
}


/**
 * Delete grade item for given game
 *
 * @param object $game object
 * @return object game
 */
function game_grade_item_delete( $game) {
    global $CFG;
    
    if( file_exists( $CFG->libdir.'/gradelib.php')){
        require_once($CFG->libdir.'/gradelib.php');
    }else{
        return;
    }    

    return grade_update('mod/game', $game->course, 'mod', 'game', $game->id, 0, NULL, array('deleted'=>1));
}

/**
 * Returns all game graded users since a given time for specified game
 */
function game_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $DB, $COURSE, $USER;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array( 'id' => $courseid));
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    if ($userid) {
        $userselect = "AND u.id = $userid";
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gm.groupid = $groupid";
        $groupjoin   = "JOIN {groups_members} gm ON  gm.userid=u.id";
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }
    
    if (!$attempts = $DB->get_records_sql("SELECT qa.*, q.grade,
                                             u.firstname, u.lastname, u.email, u.picture 
                                        FROM {game_attempts} qa
                                             JOIN {game} q ON q.id = qa.gameid
                                             JOIN {user} u ON u.id = qa.userid
                                             $groupjoin
                                       WHERE qa.timefinish > $timestart AND q.id = $cm->instance
                                             $userselect $groupselect
                                    ORDER BY qa.timefinish ASC")) {
         return;
    }


    $cm_context      = get_context_instance(CONTEXT_MODULE, $cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);
    $grader          = has_capability('mod/game:grade', $cm_context);
    //$grader          = isteacher( $courseid, $userid);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
    }

    $aname = format_string($cm->name,true);
    foreach ($attempts as $attempt) {
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // grade permission required
                continue;
            }

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) { 
                $usersgroups = groups_get_all_groups($course->id, $attempt->userid, $cm->groupingid);
                if (!is_array($usersgroups)) {
                    continue;
                }
                $usersgroups = array_keys($usersgroups);
                $interset = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
       }

        $tmpactivity = new object();

        $tmpactivity->type      = 'game';
        $tmpactivity->cmid      = $cm->id;
        $tmpactivity->name      = $aname;
        $tmpactivity->sectionnum= $cm->sectionnum;
        $tmpactivity->timestamp = $attempt->timefinish;
        
        $tmpactivity->content->attemptid = $attempt->id;
        $tmpactivity->content->sumgrades = $attempt->score * $attempt->grade;
        $tmpactivity->content->maxgrade  = $attempt->grade;
        $tmpactivity->content->attempt   = $attempt->attempt;
        
        $tmpactivity->user->userid   = $attempt->userid;
        $tmpactivity->user->fullname = fullname($attempt, $viewfullnames);
        $tmpactivity->user->picture  = $attempt->picture;
        
        $activities[$index++] = $tmpactivity;
    }

  return;
}

function game_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    print_user_picture($activity->user->userid, $courseid, $activity->user->picture);
    echo "</td><td>";

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo "<img src=\"$CFG->modpixpath/{$activity->type}/icon.gif\" ".
             "class=\"icon\" alt=\"$modname\" />";
        echo "<a href=\"$CFG->wwwroot/mod/game/view.php?id={$activity->cmid}\">{$activity->name}</a>";
        echo '</div>';
    }

    echo '<div class="grade">';
    echo  get_string("attempt", "game")." {$activity->content->attempt}: ";
    $grades = "({$activity->content->sumgrades} / {$activity->content->maxgrade})";
    echo "<a href=\"$CFG->wwwroot/mod/game/review.php?attempt={$activity->content->attemptid}\">$grades</a>";
    echo '</div>';

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->userid}&amp;course=$courseid\">"
         ."{$activity->user->fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';

    echo "</td></tr></table>";

    return;
}


/**
 * Removes all grades from gradebook
 * @param int $courseid
 * @param string optional type
 */
function game_reset_gradebook($courseid, $type='') {
    global $DB;

    $sql = "SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
              FROM {game} q, {course_modules} cm, {modules} m
             WHERE m.name='game' AND m.id=cm.module AND cm.instance=q.id AND q.course=$courseid";

    if ($games = $DB->get_records_sql( $sql)) {
        foreach ($games as $game) {
            game_grade_item_update( $game, 'reset');
        }
    }
}

/**
 * @uses FEATURE_GRADE_HAS_GRADE
 * @return bool True if quiz supports feature
 */
function game_supports($feature) {
    switch($feature) {

        case FEATURE_GRADE_HAS_GRADE:         return true;

        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return false;

        default: return null;
    }
}

/**
 * @global object
 * @global stdClass
 * @return array all other caps used in module
 */
function game_get_extra_capabilities() {
    global $DB, $CFG;

    require_once($CFG->libdir.'/questionlib.php');
    $caps = question_get_all_capabilities();
    $reportcaps = $DB->get_records_select_menu('capabilities', 'name LIKE ?', array('quizreport/%'), 'id,name');
    $caps = array_merge($caps, $reportcaps);
    $caps[] = 'moodle/site:accessallgroups';
    return $caps;
}

/**
 * Return a textual summary of the number of attemtps that have been made at a particular game,
 * returns '' if no attemtps have been made yet, unless $returnzero is passed as true.
 *
 * @global stdClass
 * @global object
 * @global object
 * @param object $game the game object. Only $game->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid fields are used at the moment.
 * @param boolean $returnzero if false (default), when no attempts have been made '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string a string like "Attempts: 123", "Attemtps 123 (45 from your groups)" or
 *          "Attemtps 123 (45 from this group)".
 */
function game_num_attempt_summary($game, $cm, $returnzero = false, $currentgroup = 0) {
    global $CFG, $USER, $DB;

    $numattempts = $DB->count_records('game_attempts', array('gameid'=> $game->id, 'preview'=>0));
    if ($numattempts || $returnzero) {
        if (groups_get_activity_groupmode($cm)) {
            $a->total = $numattempts;
            if ($currentgroup) {
                $a->group = $DB->count_records_sql('SELECT count(1) FROM ' .
                        '{game_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE gameid = ? AND preview = 0 AND groupid = ?', array($game->id, $currentgroup));
                return get_string('attemptsnumthisgroup', 'quiz', $a);
            } else if ($groups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid)) {
                list($usql, $params) = $DB->get_in_or_equal(array_keys($groups));
                $a->group = $DB->count_records_sql('SELECT count(1) FROM ' .
                        '{game_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE gameid = ? AND preview = 0 AND ' .
                        "groupid $usql", array_merge(array($game->id), $params));
                return get_string('attemptsnumyourgroups', 'quiz', $a);
            }
        }
        return get_string('attemptsnum', 'quiz', $numattempts);
    }
    return '';
}

function game_format_score($game, $score) {
    return format_float($game->grade * $score / 100, $game->decimalpoints);
}

function game_format_grade($game, $grade) {
    return format_float($grade, $game->decimalpoints);
}

/**
 * @return the options for calculating the quiz grade from the individual attempt grades.
 */
function game_get_grading_options() {
    return array (
            QUIZ_GRADEHIGHEST => get_string('gradehighest', 'quiz'),
            QUIZ_GRADEAVERAGE => get_string('gradeaverage', 'quiz'),
            QUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'quiz'),
            QUIZ_ATTEMPTLAST  => get_string('attemptlast', 'quiz'));
}

/**
 * This fucntion extends the global navigaiton for the site.
 * It is important to note that you should not rely on PAGE objects within this
 * body of code as there is no guarantee that during an AJAX request they are
 * available
 *
 * @param navigation_node $gamenode The game node within the global navigation
 * @param stdClass $course The course object returned from the DB
 * @param stdClass $module The module object returned from the DB
 * @param stdClass $cm The course module isntance returned from the DB
 */
function game_extend_navigation($gamenode, $course, $module, $cm) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    if (!has_capability('mod/game:viewreports', $context))
        return;

    if (has_capability('mod/game:view', $context)) {
        $url = new moodle_url('/mod/game/view.php', array('id'=>$cm->id));
        $gamenode->add(get_string('info', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/info', ''));
    }

    if (has_capability('mod/game:manage', $context)) {
        $url = new moodle_url('/course/modedit.php', array('update' => $cm->id, 'return' => true, 'sesskey' => sesskey()));
        $gamenode->add(get_string('edit', 'moodle', ''), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('t/edit', ''));
    }

    /* if (has_capability('mod/game:viewreports', $context)) {
        $url = new moodle_url('/mod/game/report.php', array('q'=>$cm->instance));
        $reportnode = $gamenode->add(get_string('results', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/item', ''));
    } */

    if (has_capability('mod/game:viewreports', $context)) {
        $url = new moodle_url('/mod/game/showanswers.php', array('q'=>$cm->instance));
        $reportnode = $gamenode->add(get_string('showanswers', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/item', ''));
    }

    if (has_capability('mod/game:viewreports', $context)) {
        $url = new moodle_url('/mod/game/showattempts.php', array('q'=>$cm->instance));
        $reportnode = $gamenode->add(get_string('showattempts', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('f/explore', ''));
    }


    if (has_capability('mod/game:viewreports', $context)) 
    {
        switch( $module->gamekind){
        case 'hangman':
            $url = new moodle_url('', null);
            $exportnode = $gamenode->add( get_string('export', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));

            $url = new moodle_url('/mod/game/export.php', array( 'id' => $cm->id,'courseid'=>$course->id, 'target' => 'html'));
            $exportnode->add( get_string('export_to_html', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/item', ''));

            $url = new moodle_url('/mod/game/export.php', array( 'id' => $cm->id,'courseid'=>$course->id, 'target' => 'javame'));
            $exportnode->add( get_string('export_to_javame', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/item', ''));
            break;
        case 'cross':
        case 'millionaire':
            $url = new moodle_url('/mod/game/export.php', array( 'id' => $cm->id,'courseid'=>$course->id, 'target' => 'html'));
            $gamenode->add(get_string('export_to_html', 'game'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/item', ''));
            break;
        }
    }
}

/**
 * Returns an array of game type objects to construct
 * menu list when adding new game 
 *
 */
function game_get_types(){
    global $DB;

    $types = array();

    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game_group_start";
    $type->typestr = '--'.'Games';
    $types[] = $type;

    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=hangman";
    $type->typestr = get_string('game_hangman', 'game');
    $types[] = $type;

    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=cross";
    $type->typestr = get_string('game_cross', 'game');
    $types[] = $type;
    
    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=cryptex";
    $type->typestr = get_string('game_cryptex', 'game');
    $types[] = $type;
    
    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=millionaire";
    $type->typestr = get_string('game_millionaire', 'game');
    $types[] = $type;
    
    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=sudoku";
    $type->typestr = get_string('game_sudoku', 'game');
    $types[] = $type;
   
    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=snakes";
    $type->typestr = get_string('game_snakes', 'game');
    $types[] = $type;

    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game&amp;type=hiddenpicture";
    $type->typestr = get_string('game_hiddenpicture', 'game');
    $types[] = $type;

    if($DB->get_record( 'modules', array( 'name' => 'book'), 'id,id')){
        $type = new object();
        $type->modclass = MOD_CLASS_ACTIVITY;
        $type->type = "game&amp;type=bookquiz";
        $type->typestr = get_string('game_bookquiz', 'game');
        $types[] = $type;
    }

    $type = new object();
    $type->modclass = MOD_CLASS_ACTIVITY;
    $type->type = "game_group_end";
    $type->typestr = '--';
    $types[] = $type;

    return $types;

}

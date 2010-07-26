<?php // $Id: play.php,v 1.5 2010/07/26 00:13:31 bdaloukas Exp $

function game_bookquiz_continue( $id, $game, $attempt, $bookquiz, $chapterid=0)
{	
	if( $attempt != false and $bookquiz != false){
		return game_bookquiz_play( $id, $game, $attempt, $bookquiz, $chapterid);
	}
	
	if( $attempt == false){
		$attempt = game_addattempt( $game);
	}

	unset( $bookquiz);
	$bookquiz->lastchapterid = 0;
	$bookquiz->id = $attempt->id;
	
	if( !game_insert_record(  'game_bookquiz', $bookquiz)){
		print_error( 'game_bookquiz_continue: error inserting in game_bookquiz');
	}	
	
	return game_bookquiz_play( $id, $game, $attempt, $bookquiz, 0);
}

function game_bookquiz_play( $id, $game, $attempt, $bookquiz, $chapterid)
{
    global $DB;

	if( $bookquiz->lastchapterid == 0){
		game_bookquiz_play_computelastchapter( $game, $bookquiz);
		
		if( $bookquiz->lastchapterid == 0){
			error( get_string( 'bookquiz_empty', 'game'));
		}
	}
	if( $chapterid == 0){
		$chapterid = $bookquiz->lastchapterid;
	}else
	{
		if( ($DB->set_field( 'game_bookquiz', 'lastchapterid', $chapterid, array( 'id' => $bookquiz->id))) == false){
			print_error( "Can't update table game_bookquiz with lastchapterid to $chapterid");
		}
	}
	
	$book = $DB->get_record( 'book', array('id' => $game->bookid));
	if( !$chapter = $DB->get_record( 'book_chapters', array('id' => $chapterid))){
		print_error('Error reading book chapters.');
	}
	$select = "bookid = $game->bookid AND hidden = 0";
	$chapters = $DB->get_records_select('book_chapters', $select, null, 'pagenum', 'id, pagenum, subchapter, title, hidden');
	
	$okchapters = array();
	if( ($recs = $DB->get_records( 'game_bookquiz_chapters', array( 'attemptid' => $attempt->id))) != false){
		foreach( $recs as $rec){
			//1 means correct answer
			$okchapters[ $rec->chapterid] = 1;
		}
	}
	//2 means current
	//$okchapters[ $chapterid] =  2;
	$showquestions = false;
	$a = array( 'gameid' => $game->id, 'chapterid' => $chapterid);
	if( ($questions = $DB->get_records( 'game_bookquiz_questions', $a)) === false){
		if( !array_key_exists( $chapterid, $okchapters)){
			$okchapters[ $chapterid] =  1;
			unset( $newrec);
			$newrec->attemptid = $attempt->id;
			$newrec->chapterid = $chapterid;
		
			if( !$DB->insert_record( 'game_bookquiz_chapters', $newrec)){
				print_error( "Can't insert to table game_bookquiz_chapters");
			}
		}
	}else
	{
		//Have to select random one question
		$questionid = game_bookquiz_selectrandomquestion( $questions);
		if( $questionid != 0){
			$showquestions = true;
		}
	}
	
	
/// prepare chapter navigation icons
$previd = null;
$nextid = null;
$found = 0;
$scoreattempt = 0;
foreach ($chapters as $ch) {
	$scoreattempt++;
    if ($found) {
        $nextid= $ch->id;
        break;
    }
    if ($ch->id == $chapter->id) {
        $found = 1;
    }
    if (!$found) {
        $previd = $ch->id;
    }
}
if ($ch == current($chapters)) {
    $nextid = $ch->id;
}
if( count( $chapters)){
	$scoreattempt = ($scoreattempt-1) / count( $chapters);
}

$chnavigation = '';	


if ($previd) {
	$chnavigation .= '<a title="'.get_string('navprev', 'book').'" href="attempt.php?id='.$id.'&chapterid='.$previd.'"><img src="bookquiz/pix/nav_prev.gif" class="bigicon" alt="'.get_string('navprev', 'book').'"/></a>';
} else {
    $chnavigation .= '<img src="pix/nav_prev_dis.gif" class="bigicon" alt="" />';
}

$nextbutton = '';
if ($nextid) {
	if( !$showquestions){
		$chnavigation .= '<a title="'.get_string('navnext', 'book').'" href="attempt.php?id='.$id.'&chapterid='.$nextid.'"><img src="bookquiz/pix/nav_next.gif" class="bigicon" alt="'.get_string('navnext', 'book').'" ></a>';
		$nextbutton = '<center>';
		$nextbutton  .= '<form name="form" method="get" action="attempt.php">';
		$nextbutton  .= '<input type="hidden" name="id" value="'.$id.'" >'."\r\n";
		$nextbutton  .= '<input type="hidden" name="chapterid" value="'.$nextid.'" >'."\r\n";
		$nextbutton  .= '<input type="submit" value="'.get_string( 'continue').'">';
		$nextbutton  .= '</center>';
		$showquestions = false;
		game_updateattempts_maxgrade( $game, $attempt, $scoreattempt, 0);
	}
} else {
	game_updateattempts_maxgrade( $game, $attempt, 1, 0);
    $sec = '';
    if ($section = $DB->get_record('course_sections', array( 'id' => $cm->section))) {
        $sec = $section->section;
    }
	
	if (! $cm = $DB->get_record('course_modules', array( 'id' => $id))) {
		print_error("Course Module ID was incorrect id=$id");
	}	
    $chnavigation .= '<a title="'.get_string('navexit', 'book').'" href="../../course/attempt.php?id='.$cm->course.'"><img src="bookquiz/pix/nav_exit.gif" class="bigicon" alt="'.get_string('navexit', 'book').'" /></a>';
}

require( 'toc.php');
$tocwidth = '10%';

	if( $showquestions){
		game_bookquiz_showquestions( $id, $questionid, $chapter->id, $nextid, $scoreattempt, $game);
	}

	
?>
<table border="0" cellspacing="0" width="100%" valign="top" cellpadding="2">

<!-- subchapter title and upper navigation row //-->
<tr>
    <td width="<?php echo  10;?>" valign="bottom">
    </td>
    <td valign="top">
        <table border="0" cellspacing="0" width="100%" valign="top" cellpadding="0">
        <tr>
            <td align="right"><?php echo $chnavigation ?></td>
        </tr>
        </table>
    </td>
</tr>

<!-- toc and chapter row //-->
<tr>
    <td width="<?php echo $tocwidth ?>" valign="top" align="left">
        <?php
        print_box_start('generalbox');
        echo $toc;
        print_box_end();
        ?>
    </td>
    <td valign="top" align="left">
        <?php
        print_box_start('generalbox');
        $content = '';
        if (!$book->customtitles) {
          if ($currsubtitle == '&nbsp;') {
              $content .= '<p class="book_chapter_title">'.$currtitle.'</p>';
          } else {
              $content .= '<p class="book_chapter_title">'.$currtitle.'<br />'.$currsubtitle.'</p>';
          }
        }
        $content .= $chapter->content;

        $nocleanoption = new object();
        $nocleanoption->noclean = true;
        echo '<div>';
		if( $nextbutton != ''){
			echo $nextbutton;
		}
        echo format_text($content, FORMAT_HTML, $nocleanoption, $id);
		if( $nextbutton != ''){
			echo $nextbutton;
		}
		
        echo '</div>';
        print_box_end();
        /// lower navigation
        echo '<p align="right">'.$chnavigation.'</p>';
        ?>
    </td>
</tr>
</table>

<?php
	if( $showquestions){
		game_bookquiz_showquestions( $id, $questionid, $chapter->id, $nextid, $scoreattempt, $game);
	}
}

function game_bookquiz_play_computelastchapter( $game, &$bookquiz)
{
    global $DB;

	$pagenum = $DB->get_field( 'book_chapters', 'min(pagenum) as p', array('bookid' => $game->bookid));
	if( $pagenum){
		$bookquiz->lastchapterid = $DB->get_field( 'book_chapters', 'id', array('bookid' => $game->bookid, 'pagenum' => $pagenum));
		
		if( $bookquiz->lastchapterid){
			//update the data in table game_bookquiz
			if( ($DB->set_field( 'game_bookquiz', 'lastchapterid', $bookquiz->lastchapterid, array('id' => $bookquiz->id))) == false){
				print_error( "Can't update table game_bookquiz with lastchapterid to $bookquiz->lastchapterid");
			}
		}
	}
}

function game_bookquiz_showquestions( $id, $questionid, $chapterid, $nextchapterid, $scoreattempt, $game)
{
	$onlyshow  = false;
	$showsolution = false;

	$questionlist = $questionid;
    $questions = game_sudoku_getquestions( $questionlist);

	global $CFG;
	
	/// Start the form
    echo "<form id=\"responseform\" method=\"post\" action=\"{$CFG->wwwroot}/mod/game/attempt.php\" onclick=\"this.autocomplete='off'\">\n";
	if( ($onlyshow === false) and ($showsolution  === false)){
		echo "<center><input type=\"submit\" name=\"finishattempt\" value=\"".get_string('sudoku_submit', 'game')."\"></center>\n";
	}

    // Add a hidden field with the quiz id
    echo '<div>';
    echo '<input type="hidden" name="id" value="' . s($id) . "\" />\n";
    echo '<input type="hidden" name="action" value="bookquizcheck" />';
    echo '<input type="hidden" name="chapterid" value="'.$chapterid.'" />';
    echo '<input type="hidden" name="scoreattempt" value="'.$scoreattempt.'" />';
    echo '<input type="hidden" name="nextchapterid" value="'.$nextchapterid.'" />';

	/// Print all the questions

    // Add a hidden field with questionids
    echo '<input type="hidden" name="questionids" value="'.$questionlist."\" />\n";

	$number=0;
    foreach ($questions as $question) {
	
		global $QTYPES;
		
		unset( $cmoptions);
        $cmoptions->course = $game->course;
        $cmoptions->optionflags->optionflags = 0;
		$cmoptions->id = 0;
		$cmoptions->shuffleanswers = 1;
		
		$attempt = 0;
		if (!$QTYPES[$question->qtype]->create_session_and_responses( $question, $state, $cmoptions, $attempt)) {
			error( 'game_bookquiz_showquestions: problem');
		}
		
		$state->last_graded = new StdClass;
		$state->last_graded->event = QUESTION_EVENTOPEN;
		$state->event = QUESTION_EVENTOPEN;
		$options->scores->score = 0;
		$question->maxgrade = 100;
		$state->manualcomment = '';
		$cmoptions->optionflags = 0;
		$options->correct_responses = 0;
		$options->feedback = 0;
		$options->readonly = 0;

		if( $showsolution){
			$state->responses = $QTYPES[$question->qtype]->get_correct_responses($question, $state);
		}
		
		$number++;
		print_question($question, $state, $number, $cmoptions, $options);
    }

    echo "</div>";

    // Finish the form
    echo '</div>';
	if( ($onlyshow === false) and ($showsolution === false)){
		echo "<center><input type=\"submit\" name=\"finishattempt\" value=\"".get_string('sudoku_submit', 'game')."\"></center>\n";
	}

    echo "</form>\n";
}

function game_bookquiz_selectrandomquestion( $questions)
{
    global $DB;

	$categorylist = '';
	if( $questions == false){
		return 0;
	}
	
	foreach( $questions as $rec){
		$categorylist  .= ',' . $rec->questioncategoryid;
	}
	$select = 'category in ('.substr( $categorylist, 1). ") AND qtype in ('shortanswer', 'truefalse', 'multichoice')";
	if( ($recs = $DB->get_records_select( 'question', $select, null, '', 'id,id')) == false){
		return 0;
	}
	$a = array();
	foreach( $recs as $rec){
		$a[ $rec->id] = $rec->id;
	}

	if( count( $a) == 0){
		return 0;
	}else
	{
		return array_rand( $a);
	}
}

function game_bookquiz_check_questions( $id, $game, $attempt, $bookquiz)
{
    global $QTYPES, $CFG, $USER, $DB;

    $responses = data_submitted();

	$questionlist = $responses->questionids;
	
    $questions = game_sudoku_getquestions( $questionlist);

    $actions = question_extract_responses($questions, $responses, QUESTION_EVENTSUBMIT);

	$scorequestion = 0;
	$scoreattempt = 0;
    foreach($questions as $question) {
        if( !array_key_exists( $question->id, $actions)){
            //no answered
            continue;
        }
        unset( $state);
        unset( $cmoptions);
        $question->maxgrade = 100;
        $state->responses = $actions[ $question->id]->responses;
		$state->event = QUESTION_EVENTGRADE;

		$cmoptions = array();
        $QTYPES[$question->qtype]->grade_responses( $question, $state, $cmoptions);            
        if( $state->raw_grade < 50){
            continue;
        }

        //found one correct answer
		$chapterid = $responses->chapterid;
		if( !$DB->get_field( 'game_bookquiz_chapters', 'id', array( 'attemptid' => $attempt->id, 'chapterid' => $chapterid)))
		{
			unset( $newrec);
			$newrec->attemptid = $attempt->id;
			$newrec->chapterid = $chapterid;
			if( !$DB->insert_record( 'game_bookquiz_chapters', $newrec, false)){
				print_object( $newrec);
				print_error( "Can't insert to table game_bookquiz_chapters");
			}
		}
		//Have to go to next page.
		$bookquiz->lastchapterid = $responses->nextchapterid;
		if( !$DB->set_field( 'game_bookquiz', 'lastchapterid', $bookquiz->lastchapterid, array('id' => $bookquiz->id))){
			print_error( "Can't compute next chapter");
		}
		$scorequestion = 1;
		$scoreattempt = $_POST[ 'scoreattempt'];
		
		break;
    }
	
	$query->id = 0;
	$query->attemptid = $attempt->id;
	$query->gameid = $game->id;
	$query->userid = $USER->id;
	$query->sourcemodule = 'question';
	$query->questionid = $question->id;
	$query->glossaryentryid = 0;
	$query->questiontext = $question->questiontext;
	$query->timelastattempt = time();
	game_update_queries( $game, $attempt, $query, $scorequestion, '');
	
	game_updateattempts( $game, $attempt, $scoreattempt, 0);

	game_bookquiz_continue( $id, $game, $attempt, $bookquiz);
}

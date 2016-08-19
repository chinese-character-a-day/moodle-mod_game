<?php  // $Id: play.php,v 1.13 2010/08/03 20:48:51 bdaloukas Exp $
/**
 * This page plays the cryptex game
 * 
 * @author  bdaloukas
 * @version $Id: play.php,v 1.13 2010/08/03 20:48:51 bdaloukas Exp $
 * @package game
 **/

require_once( "cryptexdb_class.php");

function game_cryptex_continue( $id, $game, $attempt, $cryptexrec, $endofgame)
{
    global $DB, $USER;

	error_log('mod/game/cryptex/play.php : game_cryptex_continue');
	
	if( $endofgame){
		game_updateattempts( $game, $attempt, -1, true);
		$endofgame = false;
	}	
	
	if( $attempt != false and $cryptexrec != false){
        $crossm = $DB->get_record( 'game_cross', array( 'id' => $attempt->id));
		return game_cryptex_play( $id, $game, $attempt, $cryptexrec, $crossm);
	}

	if( $attempt === false){
		$attempt = game_addattempt( $game);
	}
	
	$cryptex = new CryptexDB();

	$questions = array();
	$infos = array();

	$answers = array();
	$recs = game_questions_shortanswer( $game);
	if( $recs == false){
		print_error( get_string( 'cryptex_nowords', 'game'));
	}

	$infos = array();
    $reps = array();
	foreach( $recs as $rec){
	    if( $game->param7 == false){	        
    		if( core_text::strpos( $rec->answertext, ' ')){
	    		continue;		//spaces not allowed
	    	}
	    }
		
		$rec->answertext = game_upper( $rec->answertext);
		$answers[ $rec->answertext] = game_repairquestion( $rec->questiontext);
		$infos[ $rec->answertext] = array( $game->sourcemodule, $rec->questionid, $rec->glossaryentryid);

        $a = array( 'gameid' => $game->id, 'userid' => $USER->id, 'questionid' => $rec->questionid, 'glossaryentryid' => $rec->glossaryentryid);
        if(($rec2 = $DB->get_record('game_repetitions', $a, 'id,repetitions r')) != false){
            $reps[ $rec->answertext] = $rec2->r;
        }
	}

	$cryptex->setwords( $answers, $game->param1, $reps);
	
	if( $cryptex->computedata( $crossm, $crossd, $letters, $game->param2)){
		$new_crossd = array();
		foreach( $crossd as $rec)
		{
			if( array_key_exists( $rec->answertext, $infos)){
				$info = $infos[ $rec->answertext];
				
				$rec->id = 0;
				$rec->sourcemodule = $info[ 0];
				$rec->questionid = $info[ 1];
				$rec->glossaryentryid = $info[ 2];
			}
			game_update_queries( $game, $attempt, $rec, 0, '');
			$new_crossd[] = $rec;
		}
		$cryptexrec = $cryptex->save( $game, $crossm, $new_crossd, $attempt->id, $letters);
	}
	
	game_updateattempts( $game, $attempt, 0, 0);

	return game_cryptex_play( $id, $game, $attempt, $cryptexrec, $crossm);
}


function cryptex_showlegend( $legend, $title)
{
	error_log('mod/game/cryptex/play.php : cryptex_showlegend');
	
  if( count( $legend) == 0)
    return;
    
  echo "<br><b>$title</b><br>";
  foreach( $legend as $key => $line)
    echo "$key: $line<br>";
}


//q means game_queries.id
function game_cryptex_check( $id, $game, $attempt, $cryptexrec, $q, $answer)
{
    global $DB;

	error_log('mod/game/cryptex/play.php : game_cryptex_check');
	
	if( $attempt === false){
		game_cryptex_continue( $id, $game, $attempt, $cryptexrec, false);
		return;
	}

	$crossm = $DB->get_record_select( 'game_cross', "id=$attempt->id");
	$query = $DB->get_record_select( 'game_queries', "id=$q");

	$answer1 = trim( game_upper( $query->answertext));
	$answer2 = trim( game_upper( $answer));

	$len1 = core_text::strlen( $answer1);
	$len2 = core_text::strlen( $answer2);
	$equal = ( $len1 == $len2);
	if( $equal){
		for( $i=0; $i < $len1; $i++)
		{
			if( core_text::substr( $answer1, $i, 1) != core_text::substr( $answer2, $i, 1))
			{
				$equal = true;
				break;
			}
		}
	}
	if( $equal == false)
	{
		game_update_queries( $game, $attempt, $query, 0, $answer2, true);
		game_cryptex_play( $id, $game, $attempt, $cryptexrec, $crossm, true);
		return;
	}

	game_update_queries( $game, $attempt, $query, 1, $answer2);

	game_cryptex_play( $id, $game, $attempt, $cryptexrec, $crossm, true);
}

function game_cryptex_play( $id, $game, $attempt, $cryptexrec, $crossm, $updateattempt=false, $onlyshow=false, $showsolution=false)
{
    global $DB;

	error_log('mod/game/cryptex/play.php : game_cryptex_play');
	
	
	global $CFG;

	if( $game->toptext != ''){
		echo $game->toptext.'<br>';
	}
	
	echo '<br>';
	
	$cryptex = new CryptexDB();
    $language = $attempt->language;
	$questions = $cryptex->load( $crossm, $mask, $corrects, $attempt->language);

    if( $language != $attempt->language){
        if( !$DB->set_field( 'game_attempts', 'language', $attempt->language, array( 'id' => $attempt->id))){
            print_error( "game_cross_play: Can't set language");
        }
    }

    if( $attempt->language != '')
        $wordrtl = game_right_to_left( $attempt->language);
    else
        $wordrtl = right_to_left();
    $reverseprint = ($wordrtl != right_to_left());
    if( $reverseprint)
        $textdir = 'dir="'.($wordrtl ? 'rtl' : 'ltr').'"';
    else
        $textdir = '';

	$len = core_text::strlen( $mask);
	
	//count1 means there is a guested letter 
	//count2 means there is a letter that not guessed
	$count1 = $count2 = 0;
	for($i=0; $i < $len; $i++)
	{
		$c = core_text::substr( $mask, $i, 1);
		if( $c == '1'){
			$count1++;
		}else if( $c == '2')
		{
			$count2++;
		}
	}
	if( $count1 + $count2 == 0){
		$gradeattempt = 0;
	}else
	{
		$gradeattempt = $count1 / ($count1 + $count2);
	}
	$finished = ($count2 == 0);
	
	if( ($finished === false) && ($game->param8 > 0))
	{
		$found = false;
		foreach ( $questions as $q)
		{
			if ( $q->tries < $game->param8)
				$found = true;
		}	
		if( $found == false)
			$finished = true;	//rich max tries
	}

	if( $updateattempt){
		game_updateattempts( $game, $attempt, $gradeattempt, $finished);
	}

	if( ($onlyshow == false) and ($showsolution == false)){
		if( $finished){
			game_cryptex_onfinished( $id, $game, $attempt, $cryptexrec);
		}
	}

?>
<style type="text/css"><!--

.answerboxstyle  {
background-color:	#FFFAF0;
border-color:	#808080;
border-style:	solid;
border-width:	1px;
display:	block;
padding:	.75em;
width:	240pt;
}
--></style>
<?php

	echo '<table border=0>';
	echo '<tr><td>';
	$cryptex->display( $crossm->cols, $crossm->rows, $cryptexrec->letters, $mask, $showsolution, $textdir);
?>
</td>

<td width=10%>&nbsp;</td>
<td>

<form  method="get" action="<?php echo $CFG->wwwroot?>/mod/game/attempt.php">
<div id="answerbox" class="answerboxstyle" style="display:none;">
<div id="wordclue" name="wordclue" class="cluebox"> </div>
<input id="action" name="action" type="hidden" value="cryptexcheck">
<input id="q" name="q" type="hidden" >
<input id="id" name="id" value="<?php echo $id; ?>" type="hidden">

<div style="margin-top:1em;"><input id="answer" name="answer" type="text" size="24"
 style="font-weight: bold; text-transform:uppercase;" autocomplete="off"></div>

<table border="0" cellspacing="0" cellpadding="0" width="100%" style="margin-top:1em;"><tr>
<td align="right">
<button id="okbutton" type="submit" class="button" style="font-weight: bold;">OK</button> &nbsp;
<button id="cancelbutton" type="button" class="button" onclick="DeselectCurrentWord();">Cancel</button>
</td></tr></table>
</form>
</td>
</tr>
</table>


<?php
	$grade = round( 100 * $gradeattempt);
	echo '<br>'.get_string( 'grade', 'game').' '.$grade.' %';

	echo "<br><br>";
	$i = 0;
	foreach( $questions as $key => $q){
		$i++;
		if( $showsolution == false){
			//When I want to show the solution a want to show the questions to.
			if( array_key_exists( $q->id, $corrects)){
				continue;
			}	
		}
		
        $question = "$i. ".$q->questiontext;
		if( ($onlyshow == false) and ($showsolution == false)){
			if( ($game->param8 == 0) || ($game->param8 > $q->tries))
				$question .= ' &nbsp;<input type="submit" value="'.get_string( 'answer').'" onclick="OnCheck( '.$q->id.',\''.$question.'\');" />';
		}

		$question = game_filtertext( $question, 0);
		echo $question;
		$question = strip_tags($question); //ADDED BY DP (AUG 2009) - fixes " breaking the Answer button for this question
		$question = str_replace("'","\'",$question);
		if( $showsolution){
			echo " &nbsp;&nbsp;&nbsp;$q->answertext<B></b>";
		}
        echo '<br>';
	}
	
	if( $game->bottomtext != ''){
		echo '<br><br>'.$game->bottomtext;
	}	
	
	?>
		<script>
			function OnCheck( id, question)
			{
				document.getElementById("q").value = id;
				document.getElementById("wordclue").innerHTML = question;

				// Finally, show the answer box.
				document.getElementById("answerbox").style.display = "block";
				try
				{
					document.getElementById("answer").focus();
					document.getElementById("answer").select();
				}
				catch (e)
				{
				}
			}
		</script>
	<?php
}

function game_cryptex_onfinished( $id, $game, $attempt, $cryptexrec)
{
	global $CFG, $DB;

	error_log('mod/game/cryptex/play.php : game_cryptex_onfinished');
	
	if (! $cm = $DB->get_record( 'course_modules', array( 'id' => $id))) {
		print_error( "Course Module ID was incorrect id=$id");
	}

	echo '<B>'.get_string( 'cryptex_win', 'game').'</B><BR>';	
	echo '<br>';	
	echo "<a href=\"$CFG->wwwroot/mod/game/attempt.php?id=$id&forcenew=1\">".get_string( 'nextgame', 'game').'</a> &nbsp; &nbsp; &nbsp; &nbsp; ';
	echo "<a href=\"$CFG->wwwroot/course/view.php?id=$cm->course\">".get_string( 'finish', 'game').'</a> ';
	echo "<br><br>\r\n";
}

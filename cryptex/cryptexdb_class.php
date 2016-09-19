<?PHP

class CryptexDB extends CrossDB
{
	function save( $game, &$crossm, $crossd, $id, $letters)
	{
		global $USER;

		error_log('mod/game/cryptex/cryptexdb_class.php : save');
		
        CrossDB::delete_records( $id);

		if( (CrossDB::save( $game, $crossm, $crossd, $id)) == false){
			return false;
		}
		
		$crossm->id = $id;
		
        $newrec->id = $id;
		$newrec->letters = $letters;
		
		if (!($cryptexid = game_insert_record( "game_cryptex", $newrec))){
			print_error( 'Insert page: new page game_cryptex not inserted');
		}		
				
		return $newrec;
	}


	function computeletters( $crossm, $crossd)
	{
		error_log('mod/game/cryptex/cryptexdb_class.php : computeletters');
		
		$letters = '';
		$cols = $crossm->cols + 1;
		$letters = str_repeat('.', $crossm->cols).'#';
		$letters = str_repeat($letters, $crossm->rows) ;
		
		$freqs1 = array();
		$count1 = $count2 = 0;
		foreach( $crossd as $rec)
		{
			$pos = $rec->col - 1 + ($rec->row-1) * $cols;
			$s = $rec->answertext;
			$len = core_text::strlen( $s);
			
			$a = array();
			for( $i=0; $i < $len; $i++){
				$a[] = core_text::substr( $s, $i, 1);
			}
			
			for( $i=0; $i < $len; $i++){
				$this->setchar( $letters, $pos,  $a[ $i]);
				$pos += ( $rec->horizontal ? 1 : $cols);
				
				$freqs1[ ++$count1] = $a[ $i];
				if( $i+1 < $len){
					$freqs2[ ++$count2] = $a[ $i].$a[ $i+1];
				}
			}
		}
	
		$len = core_text::strlen( $letters);
		$spaces = 0;
		for( $i=0; $i < $len; $i++){
			if( core_text::substr( $letters, $i, 1) == '.'){
				$spaces++;
			}
		}	
		
		$step = 1;
		while( $spaces)
		{
			if( $step == 1){
				$step = 2;
				$i = array_rand( $freqs1);
				$this->insertchar( $letters, $crossm->cols, $crossm->rows, $freqs1[ $i], $spaces);
			}else
			{
				$step=1;
				$i = array_rand( $freqs2);
				$this->insertchars( $letters, $crossm->cols, $crossm->rows, $freqs2[ $i], $spaces);
			}
		}

		$ret_letters = "";
		for( $row=0; $row < $crossm->rows; $row++){
			$ret_letters .= core_text::substr( $letters, $cols * $row, ($cols-1));
		}


		return $ret_letters;
	}

    function display( $cols, $rows, $letters, $mask, $showsolution, $textdir)
    {
		error_log('mod/game/cryptex/cryptexdb_class.php : display');
				
		echo "<table border=1 $textdir>";
		for( $row=0; $row < $rows; $row++)
		{
			echo "<tr>";
			for( $col=0; $col < $cols; $col++){
				$pos = $cols * $row+$col;
				$c = core_text::substr( $letters, $pos, 1);
				$m = core_text::substr( $mask, $pos, 1);
				
				if( $showsolution and $m > '0'){
					echo "<td align=center><b><FONT color=red>".$c."</font></td>";
				}else if( $m == '1'){
						echo "<td align=center><b><FONT color=red>".$c."</font></td>";
				}else
				{
					echo "<td align=center>".$c."</td>";
				}
			}
			echo "</tr>\r\n";
		}
		echo "</table>";
    }
	
	function insertchar( &$letters, $cols, $rows, $char, &$spaces)
	{
		
		$len = core_text::strlen( $letters);
		for( $i=0; $i < $len; $i++){
			if( core_text::substr( $letters, $i, 1) == '.'){
				$this->setchar( $letters, $i, $char);
				$spaces--;
				return;
			}
		}		
	}
	
	function insertchars( &$letters, $cols, $rows, $char, &$spaces)
	{
		
		$len = core_text::strlen( $letters);
		for( $i=0; $i < $len; $i++){
			if( core_text::substr( $letters, $i, 1) == '.'  and core_text::substr( $letters, $i+1, 1) == '.' ){
				$this->setchar( $letters, $i, core_text::substr( $char, 0, 1));
				$this->setchar( $letters, $i+1, core_text::substr( $char, 1, 1));
				$spaces-=2;
				return true;
			}
			if( core_text::substr( $letters, $i, 1) == '.'  and core_text::substr( $letters, $i+$cols+1, 1) == '.' ){
				$this->setchar( $letters, $i, core_text::substr( $char, 0, 1));
				$this->setchar( $letters, $i + $cols+1, core_text::substr( $char, 1, 1));
				$spaces-=2;
				return true;
			}
		}	
		
		return false;
	}
	
	function gethash( $word)
	{
		
		$x = 37;
		$len = count( core_text::strlen( $word));
		
		for($i=0; $i < $len; $i++){
			$x = $x xor ord( core_text::substr( $word, $i, 1));
		}
		
		return $x;
	}

	function load( $crossm, &$mask, &$corrects, &$language)
	{
        global $DB;

		error_log('mod/game/cryptex/cryptexdb_class.php : load');
				
		$questions = array();
		$corrects = array();
		
		$mask = str_repeat( '0', $crossm->cols * $crossm->rows);
		
		if ($recs = $DB->get_records( 'game_queries', array( 'attemptid' => $crossm->id)))
		{
			foreach ($recs as $rec)
			{
			    if( $rec->questiontext == ''){
			        $rec->questiontext = ' ';
			    }
				$key = $this->gethash( $rec->questiontext).'-'.$rec->answertext.'-'.$rec->id;
				$questions[ $key] = $rec;
				
				$word = $rec->answertext;
				$pos = $crossm->cols * ($rec->row-1)+($rec->col-1);
				$len = core_text::strlen( $word);
				$found = ($rec->answertext == $rec->studentanswer);

				for( $i=0; $i < $len; $i++)
				{
					$c = ( $found ? '1' : '2');
					
					if( core_text::substr( $mask, $pos,  1) != '1'){
						game_setchar( $mask, $pos, $c);
					}
					
					$pos += ($rec->horizontal ? 1 : $crossm->cols);
				}
				
				if( $found){
					$corrects[ $rec->id] = 1;
				}

                if( $language == ''){
                    $language = game_detectlanguage( $rec->answertext);
                }
			}
			ksort( $questions);
		}
		
		return $questions;
	}


	function setwords( $answers, $maxcols, $reps)
	{
		return Cross::setwords( $answers, $maxcols, $reps);
	}
    
	function computedata( &$crossm, &$crossd, &$letters, $maxwords)
	{	
	
		error_log('mod/game/cryptex/cryptexdb_class.php : computedata');
			
		if( !cross::computedata( $crossm, $crossd, $maxwords)){
			return false;
		}
		
		$letters = $this->computeletters( $crossm, $crossd);
		
		return true;
	}
}


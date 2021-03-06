<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
Crossing Words for
Codewalkers PHP Coding Contest of July 2002
(http://codewalkers.com/php-contest.php)

Author Àngel Fenoy from Arenys de Mar, Barcelona.
*/
class Cross
{
    public $minputanswers;   // Contains the words and the answers.
    public $mwords;           // The words that will be used.

    public $mtimelimit = 3;

    // Computed by computenextcross.
    public $mbestcrosspos;  // The best puzzle.
    public $mbestcrossdir;  // The best puzzle.
    public $mbestcrossword; // The best puzzle.
    public $mbestpuzzle;

    public $mbests;           // The best score as a phrase.
    public $mbestscore;       // The best score.

    public $mbestconnectors;
    public $mbestfilleds;
    public $mbestspaces;
    public $mbestn20;

    // Computepuzzleinfo.
    public $mmincol;    // Computed by ComputePuzzleInfo.
    public $mmaxcol;    // Computed by ComputePuzzleInfo.
    public $mminrow;    // Computed by ComputePuzzleInfo.
    public $mmaxrow;    // Computed by ComputePuzzleInfo.
    public $mcletter;   // Computed by ComputePuzzleInfo.
    public $mreps;      // Repetition of each word.
    public $maveragereps; // Average of repetitions.

    public function setwords( $answers, $maxcols, $reps) {
    
		error_log('mod/game/cross/cross_class.php : setwords');    
		error_log('mod/game/cross/cross_class.php : setwords: answers: ');
		//error_log(print_r($answers, true));
		
        $this->mreps = array();
        foreach ($reps as $word => $r) {
            $this->mreps[ game_upper( $word)] = $r;
        }

        $this->maveragereps = 0;
        foreach ($reps as $r) {
            $this->maveragereps += $r;
        }
        if (count( $reps)) {
            $this->maveragereps /= count( $reps);
        }

        $this->minputanswers = array();
        foreach ($answers as $word => $answer) {
            $this->minputanswers[ game_upper( $word)] = $answer;
        }

        $this->mwords = array();

        $maxlen = 0;
        foreach ($this->minputanswers as $word => $answer) {
            $len = game_strlen( $word);
            if ($len > $maxlen) {
                $maxlen = $len;
            }
        }

        $n20 = $maxlen;
        if ($n20 < 15) {
            $n20 = 15;
        }

        $this->mn20min = round( $n20 - $n20 / 4);
        $this->mn20max = round( $n20 + $n20 / 4);
        if ( $this->mn20max > $maxcols and $maxcols > 0) {
            $this->mn20max = $maxcols;
        }
        if ($this->mn20min > $this->mn20max) {
            $this->mn20min = $this->mn20max;
        }

        $this->mwords = array();
        foreach ($this->minputanswers as $word => $answer) {
            $len = game_strlen( $word);

            if ($len <= $this->mn20max) {
                $this->mwords[] = game_upper( $word);
            }
        }

        $this->randomize();

        return count( $this->mwords);
    }

    public function randomize() {
    	error_log('mod/game/cross/cross_class.php : randomize');
    
        $n = count( $this->mwords);
        for ($j = 0; $j <= $n / 4; $j++) {
            $i = array_rand( $this->mwords);

            $this->swap( $this->mwords[ $i], $this->mwords[ 0]);
        }
    }

    public function computedata( &$crossm, &$crossd, &$letters, $minwords, $maxwords, $mtimelimit=3) {
        $t1 = time();

		error_log('mod/game/cross/cross_class.php : computedata');
		
        $ctries = 0;
        $mbestscore = 0;

        $mbestconnectors = $mbestfilleds = $mbestspaces = 0;
        $mbestn20 = 0;

        $nochange = 0;
        $this->mtimelimit = $mtimelimit;
        if ($this->mtimelimit == 30) {
            $this->mtimelimit = 27;
        }
        for (;;) {
            // Selects the size of the cross.
            $n20 = mt_rand( $this->mn20min, $this->mn20max);

            if (!$this->computenextcross( $n20, $t1, $ctries, $minwords, $maxwords, $nochange)) {
                break;
            }

            $ctries++;

            if (time() - $t1 > $this->mtimelimit) {
                break;
            }

            if ($nochange > 10) {
                break;
            }
        }
        $this->computepuzzleinfo( $this->mbestn20, $this->mbestcrosspos, $this->mbestcrossdir, $this->mbestcrossword, false);

        return $this->savepuzzle( $crossm, $crossd, $ctries, time() - $t1);
    }

    public function computenextcross( $n20, $t1, $ctries, $minwords, $maxwords, &$nochange) {
        $maxw = $n20;

		error_log('mod/game/cross/cross_class.php : computenextcross: ctries: '.$ctries);
		
        $n21 = $n20 + 1;
        $n22 = $n20 + 2;
        $n2222 = $n22 * $n22;

        $basepuzzle = str_repeat('0', $n22) .
        str_repeat('0' . str_repeat('.', $n20) . '0', $n20) .
        str_repeat('0', $n22);

        $crosspos = array();
        $crossdir = array();
        $crossword = array();

        $magics = array();
        for ($n = 2; $n < $n21; $n++) {
            $a = array();
            for ($r = 2; $r < ($n + 2); $r++) {
                $a[] = $r;
            }

            uasort($a, array( $this, 'cmp_magic'));
            $magics[ $n] = $a;
        }

        uasort($this->mwords,  array( $this, 'cmp'));

        $words = ';' . implode(';', $this->mwords) . ';';

        $puzzle = $basepuzzle;

        $row = mt_rand(3, max( 3, $n20 - 3));
        $col = mt_rand(3, max( 3, $n20 - 3));
        $pos = $n22 * $row + $col;

        $poss = array();
        $ret = $this->scan_pos($pos, 'h', true, $puzzle, $words, $magics, $poss, $crosspos, $crossdir, $crossword, $n20);

        while ($s = count($poss)) {
            $p = array_shift($poss);

            if ($this->scan_pos($p[0], $p[1], false, $puzzle, $words, $magics, $poss, $crosspos, $crossdir, $crossword, $n20)) {
                $nwords = count( $crossword);
                if ($maxwords) {
                    if ($nwords >= $maxwords) {
                        break;
                    }
                }
            }
        }

        $nwords = count( $crossword);
        if ($minwords) {
            if ($nwords < $minwords) {
                return true;
            }
        }

        $score = $this->computescore( $puzzle, $n20, $n22, $n2222, $nwords, $nconnectors, $nfilleds, $cspaces, $crossword);

        if ($score > $this->mbestscore) {
            $this->mbestcrosspos = $crosspos;
            $this->mbestcrossdir = $crossdir;
            $this->mbestcrossword = $crossword;
            $this->mbestpuzzle = $puzzle;

            $this->mbests = array('Words' => "$nwords * 5 = " . ($nwords * 5),
                'Connectors' => "$nconnectors * 3 = " . ($nconnectors * 3),
                'Filled in spaces' => $nfilleds,
                "N20" => $n20
            );

            $this->mbestscore = $score;

            $this->mbestconnectors = $nconnectors;
            $this->mbestfilleds = $nfilleds;
            $this->mbestspaces = $cspaces;
            $this->mbestn20 = $n20;
            $nochange = 0;
        } else {
            $nochange++;
        }

        return true;
    }

    public function computescore( $puzzle, $n20, $n22, $n2222, $nwords, &$nconnectors, &$nfilleds, &$cspaces, $crossword) {
        $nconnectors = $nfilleds = 0;
        $puzzle00 = str_replace('.', '0', $puzzle);

		error_log('mod/game/cross/cross_class.php : computescore');
		
        $used = 0;
        for ($n = 0; $n < $n2222; $n++) {
            if ($puzzle00[$n]) {
                $used ++;

                if (($puzzle00[$n - 1] or $puzzle00[$n + 1]) and ($puzzle00[$n - $n22] or $puzzle00[$n + $n22])) {
                    $nconnectors++;
                } else {
                    $nfilleds++;
                }
            }
        }

        $cspaces = substr_count( $puzzle, ".");
        $score = ($nwords * 5) + ($nconnectors * 3) + $nfilleds;

        $sumrep = 0;
        foreach ($crossword as $word) {
            $word = game_substr( $word, 1, -1);

            if (array_key_exists( $word, $this->mreps)) {
                $sumrep += $this->mreps[ $word] - $this->maveragereps;
            }
        }

        return $score - 10 * $sumrep;
    }

    public function computepuzzleinfo( $n20, $crosspos, $crossdir, $crossword, $bprint=false) {
        $bprint = false;
        $n22 = $n20 + 2;

		error_log('mod/game/cross/cross_class.php : computepuzzleinfo');
		
        $this->mmincol = $n22;
        $this->mmaxcol = 0;
        $this->mminrow = $n22;
        $this->mmaxrow = 0;
        $this->mcletter = 0;

        if (count( $crossword) == 0) {
            return;
        }

        if ($bprint) {
            echo "<br><br>PuzzleInfo n20=$n20 words=".count( $crossword)."<BR>";
        }
        for ($i = 0; $i < count($crosspos); $i++) {
            $pos = $crosspos[ $i];
            $col = $pos % $n22;
            $row = floor( $pos / $n22);
            $dir = $crossdir[ $i];

            $len = game_strlen( $crossword[ $i]) - 3;

            if ($bprint) {
                echo "col=$col row=$row dir=$dir word=".$crossword[ $i]."<br>";
            }

            $this->mcletter += $len;

            if ($col < $this->mmincol) {
                $this->mmincol = $col;
            }

            if ($row < $this->mminrow) {
                $this->mminrow = $row;
            }

            if ($dir == 'h') {
                $col += $len;
            } else {
                $row += $len;
            }

            if ($col > $this->mmaxcol) {
                $this->mmaxcol = $col;
            }
            if ($row > $this->mmaxrow) {
                $this->mmaxrow = $row;
            }
        }

        if ($bprint) {
            echo "mincol={$this->mmincol} maxcol={$this->mmaxcol} minrow={$this->mminrow} maxrow={$this->mmaxrow}<br>";
        }

        if ($this->mmincol > $this->mmaxcol) {
            $this->mmincol = $this->mmaxcol;
        }
        if ($this->mminrow > $this->mmaxrow) {
            $this->mminrow = $this->mmaxrow;
        }
    }

    public function savepuzzle( &$crossm, &$crossd, $ctries, $time) {
        $n22 = $this->mbestn20 + 2;

		error_log('mod/game/cross/cross_class.php : savepuzzle');
		
        $cols = $this->mmaxcol - $this->mmincol + 1;
        $rows = $this->mmaxrow - $this->mminrow + 1;

        $bswapcolrow = false;

        if ($bswapcolrow) {
            swap( $cols, $rows);
            swap( $this->mmincol, $this->mminrow);
            swap( $this->mmaxcol, $this->mmaxrow);
        }

        $crossm = new stdClass();
        $crossm->datebegin = time();
        $crossm->time = $time;
        $crossm->cols = $cols;
        $crossm->rows = $rows;
        $crossm->words = count( $this->mbestcrosspos);
        $crossm->wordsall = count( $this->minputanswers);

        $crossm->createscore = $this->mbestscore;
        $crossm->createtries = $ctries;
        $crossm->createtimelimit = $this->mtimelimit;
        $crossm->createconnectors = $this->mbestconnectors;
        $crossm->createfilleds = $this->mbestfilleds;
        $crossm->createspaces = $this->mbestspaces;

        for ($i = 0; $i < count($this->mbestcrosspos); $i++) {
            $pos = $this->mbestcrosspos[ $i];

            $col = $pos % $n22;
            $row = floor( ($pos - $col) / $n22);

            $col += -$this->mmincol + 1;
            $row += -$this->mminrow + 1;

            $dir = $this->mbestcrossdir[ $i];
            $word = $this->mbestcrossword[ $i];
            $word = substr( $word, 1, strlen( $word) - 2);

            $rec = new stdClass();

            $rec->col = $col;
            $rec->row = $row;
            $rec->horizontal = ($dir == "h" ? 1 : 0);

            $rec->answertext = $word;
            $rec->questiontext = $this->minputanswers[ $word];

            if ($rec->horizontal) {
                $key = sprintf( 'h%10d %10d', $rec->row, $rec->col);
            } else {
                $key = sprintf( 'v%10d %10d', $rec->col, $rec->row);
            }

            $crossd[ $key] = $rec;
        }
        if (count( $crossd) > 1) {
            ksort( $crossd);
        }

        return (count( $crossd) > 0);
    }

    public function swap( &$a, &$b) {
        $temp = $a;
        $a = $b;
        $b = $temp;
    }

    public function displaycross($puzzle, $n20) {
    
    	error_log('mod/game/cross/cross_class.php : displaycross');
    	
        $n21 = $n20 + 1;
        $n22 = $n20 + 2;
        $n2222 = $n22 * $n22;
        $n2221 = $n2222 - 1;
        $n2200 = $n2222 - $n22;

        $ret = "<table border=0 cellpadding=2 cellspacing=1><tr>";
        for ($n = 0;; $n ++) {
            $c = game_substr( $puzzle, $n, 1);

            if (($m = $n % $n22) == 0 or $m == $n21 or $n < $n22 or $n > $n2200) {
                $ret .= "<td class=marc>  </td>";
            } else if ( $c == '0') {
                $ret .= "<td class=limit> </td>";
            } else if ($c == '.') {
                $ret .= "<td class=blanc> </td>";
            } else {
                if ((game_substr( $puzzle, $n - 1, 1) > '0' or
                    game_substr( $puzzle, $n + 1, 1) > '0') and
                    (game_substr( $puzzle, $n - $n22, 1) > '0'
                    or game_substr( $puzzle, $n + $n22, 1) > '0')) {
                    $ret .= "<td align=center class=connector>$c</td>";
                } else {
                    $ret .= "<td align=center class=filled>$c</td>";
                }
            }

            if ($n == $n2221) {
                return "$ret</tr></table>";
            } else if ($m == $n21) {
                $ret .= "</tr><tr>";
            }
        }
        return $ret.'</tr></table>';
    }

    public function scan_pos($pos, $dir, $valblanc, &$puzzle, &$words, &$magics,
        &$poss, &$crosspos, &$crossdir, &$crossword, $n20) {

		error_log('mod/game/cross/cross_class.php : scan_pos: pos: '.$pos.' dir: '.$dir); 
		
        $maxw = $n20;

        $n22 = $n20 + 2;
        $n2222 = $n22 * $n22;

        if ($dir == 'h') {
            $inc = 1;
            if ($pos + $inc >= $n2222) {
                return false;
            }
            $oinc = $n22;
            $newdir = 'v';
        } else {
            $inc = $n22;
            if ($pos + $inc >= $n2222) {
                return false;
            }
            $oinc = 1;
            $newdir = 'h';
        }

        $regex  = game_substr( $puzzle, $pos, 1);
        if ( ($regex == '0' or $regex == '.') and (!$valblanc)) {
            return false;
        }

        if ((game_substr( $puzzle, $pos - $inc, 1) > '0')) {
            return false;
        }

        if ((game_substr( $puzzle, $pos + $inc, 1) > '0')) {
            return false;
        }

        $left = $right = 0;
        for ($limita = $pos - $inc; ($w = game_substr( $puzzle, $limita, 1)) !== '0'; $limita -= $inc) {
            if ($w == '.' and ((game_substr( $puzzle, $limita - $oinc, 1) > '0') or
                (game_substr( $puzzle, $limita + $oinc, 1) > '0'))) {
                break;
            }

            if (++$left == $maxw) {
                $left --;
                break;
            }

            $regex = $w . $regex;
        }

        for ($limitb = $pos + $inc; ($w = game_substr( $puzzle, $limitb, 1)) !== '0'; $limitb += $inc) {
            if ($w == '.' and ((game_substr( $puzzle, $limitb - $oinc, 1) > '0')
                or (game_substr( $puzzle, $limitb + $oinc, 1) > '0'))) {
                break;
            }

            if (++$right == $maxw) {
                $right--;
                break;
            }

            $regex .= $w;
        }

        if (($lenregex = game_strlen($regex)) < 2) {
            return false;
        }

        foreach ($magics[$lenregex] as $m => $lens) {
            $ini = max(0, ($left + 1) - $lens);
            $fin = $left;

            $posp = max($limita + $inc, $pos - (($lens - 1 ) * $inc));

            for ($posc = $ini; $posc <= $fin; $posc++, $posp += $inc) {
                if (game_substr( $puzzle, $posp - $inc, 1) > '0') {
                    continue;
                }

                $w = game_substr($regex, $posc, $lens);

                if (!$this->my_preg_match( $w, $words, $word)) {
                    continue;
                }

                $larr0 = $posp + ((game_strlen( $word) - 2) * $inc);

                if ($larr0 >= $n2222) {
                    continue;
                }

                if (game_substr( $puzzle, $larr0, 1) > '0') {
                    continue;
                }

                $words = str_replace( $word, ';', $words);

                $len = game_strlen( $word);
                for ($n = 1, $pp = $posp; $n < $len - 1; $n++, $pp += $inc) {
                    $this->setchar( $puzzle, $pp,  game_substr( $word , $n, 1));

                    if ($pp == $pos) {
                        continue;
                    }

                    $c = game_substr( $puzzle, $pp, 1);
                    $poss[] = array($pp, $newdir, ord( $c));
                }

                $crosspos[] = $posp;
                $crossdir[] = ($newdir == 'h' ? 'v' : 'h');
                $crossword[] = $word;

                $this->setchar( $puzzle, $posp - $inc, '0');
                $this->setchar( $puzzle, $pp, '0');

                return true;
            }
        }

        return false;
    }

    public function my_preg_match( $w, $words, &$word) {
        $a = explode( ";", $words);
        $lenw = game_strlen( $w);
        foreach ($a as $test) {
            if (game_strlen( $test) != $lenw) {
                continue;
            }

            for ($i = 0; $i < $lenw; $i++) {
                if (game_substr( $w, $i, 1) == '.') {
                    continue;
                }
                if (game_substr( $w, $i, 1) != game_substr( $test, $i, 1) ) {
                    break;
                }
            }
            if ($i < $lenw) {
                continue;
            }
            $word = ';'.$test.';';

            return true;
        }
        return false;
    }

    public function setchar( &$s, $pos, $char) {
        $ret = "";

        if ($pos > 0) {
            $ret .= game_substr( $s, 0, $pos);
        }

        $s = $ret . $char . game_substr( $s, $pos + 1, game_strlen( $s) - $pos - 1);
    }

    public function showhtml_base( $crossm, $crossd, $showsolution, $showhtmlsolutions, $showstudentguess, $context, $game) {
        $this->mLegendh = array();
        $this->mLegendv = array();
		
		error_log('mod/game/cross/cross_class.php showhtml_base: rows: '.$crossm->rows.' cols: '.$crossm->cols);
		
        $sret = "CrosswordWidth  = {$crossm->cols};\n";
        $sret .= "CrosswordHeight = {$crossm->rows};\n";

        $sret .= "Words=".count( $crossd).";\n";
        $swordlength = "";
        $sguess = "";
        $ssolutions = '';
        $shtmlsolutions = '';
        $swordx = "";
        $swordy = "";
        $sclue = "";
        $lasthorizontalword = -1;
        $i = -1;
        $legendv = array();
        $legendh = array();

        if ($game->glossaryid) {
            $cmglossary = get_coursemodule_from_instance('glossary', $game->glossaryid, $game->course);
            $contextglossary = game_get_context_module_instance( $cmglossary->id);
        }
        foreach ($crossd as $rec) {
            if ($rec->horizontal == false and $lasthorizontalword == -1) {
                $lasthorizontalword = $i;
            }

            $i++;

            $swordlength .= ",".game_strlen( $rec->answertext);
            if ($rec->questionid != 0) {
                $q = game_filterquestion(str_replace( '\"', '"', $rec->questiontext),
                    $rec->questionid, $context->id, $game->course);
                $rec->questiontext = game_repairquestion( $q);
            } else {
                // Glossary.
                $q = game_filterglossary(str_replace( '\"', '"', $rec->questiontext),
                    $rec->glossaryentryid, $contextglossary->id, $game->course);
                $rec->questiontext = game_repairquestion( $q);
            }

            $sclue .= ',"'.game_tojavascriptstring( game_filtertext( $rec->questiontext, 0))."\"\r\n";
            if ($showstudentguess) {
                $sguess .= ',"'.$rec->studentanswer.'"';
            } else {
                $sguess .= ",''";
            }
            $swordx .= ",".($rec->col - 1);
            $swordy .= ",".($rec->row - 1);
            if ($showsolution) {
                $ssolutions .= ',"'.$rec->answertext.'"';
            } else {
                $ssolutions .= ',""';
            }

            if ($showhtmlsolutions) {
                $shtmlsolutions .= ',"'.base64_encode( $rec->answertext).'"';
            }

            $attachment = '';

            $s = $rec->questiontext.$attachment;
            if ($rec->horizontal) {
                if (array_key_exists( $rec->row, $legendh)) {
                    $legendh[ $rec->row][] = $s;
                } else {
                    $legendh[ $rec->row] = array( $s);
                }
            } else {
                if (array_key_exists( $rec->col, $legendv)) {
                    $legendv[ $rec->col][] = $s;
                } else {
                    $legendv[ $rec->col] = array( $s);
                }
            }
        }

        $letters = get_string( 'lettersall', 'game');

        $this->mlegendh = array();
        foreach ($legendh as $key => $value) {
            if (count( $value) == 1) {
                $this->mlegendh[ $key] = $value[ 0];
            } else {
                for ($i = 0; $i < count( $value); $i++) {
                    $this->mlegendh[ $key.game_substr( $letters, $i, 1)] = $value[ $i];
                }
            }
        }

        $this->mlegendv = array();
        foreach ($legendv as $key => $value) {
            if (count( $value) == 1) {
                $this->mlegendv[ $key] = $value[ 0];
            } else {
                for ($i = 0; $i < count( $value); $i++) {
                    $this->mlegendv[ $key.game_substr( $letters, $i, 1)] = $value[ $i];
                }
            }
        }

        ksort( $this->mlegendh);
        ksort( $this->mlegendv);

        $sret .= "WordLength = new Array( ".game_substr( $swordlength, 1).");\n";
        $sret .= "Clue = new Array( ".game_substr( $sclue, 1).");\n";
        $sguess = str_replace( ' ', '_', $sguess);
        $sret .= "Guess = new Array( ".game_substr( $sguess, 1).");\n";
        $sret .= "Solutions = new Array( ".game_substr( $ssolutions, 1).");\n";
        if ($showhtmlsolutions) {
            $sret .= "HtmlSolutions = new Array( ".game_substr( $shtmlsolutions, 1).");\n";
        }
        $sret .= "WordX = new Array( ".game_substr( $swordx, 1).");\n";
        $sret .= "WordY = new Array( ".game_substr( $swordy, 1).");\n";
        $sret .= "LastHorizontalWord = $lasthorizontalword;\n";

        return $sret;
    }

    public function cmp($a, $b) {
        return game_strlen($b) - game_strlen($a);
    }

    public function cmp_magic($a, $b) {
        return (game_strlen($a) + mt_rand(0, 3)) - (game_strlen($b) - mt_rand(0, 1));
    }
}

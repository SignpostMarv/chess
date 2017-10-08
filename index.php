<?php
/*-----------------------------------------------------------------------------
 *  Chess By Devin Gray <devingray@gmail.com>
 *-----------------------------------------------------------------------------
 *  This is an evolving chess application written by Devin Gray.  The languages
 *  used in this application are: PHP, HTML, and Javascript on the front-end,
 *  which will invoke a CGI program written in C and/or Go on the back-end.  I
 *  will experiment with many types of game modes and AI algorithms.  My goal
 *  is to build an AI that can beat me consistently in a head to head match,
 *  or at the very least a fun game of chess!
 *---------------------------------------------------------------------------*/

$num_to_letter = array('a','b','c','d','e','f','g','h');
$pieces = array(
    'wk'=>array('html'=>"&#9812;",'codepoint'=>'\u2654'),
    'wq'=>array('html'=>"&#9813;",'codepoint'=>'\u2655'),
    'wr'=>array('html'=>"&#9814;",'codepoint'=>'\u2656'),
    'wb'=>array('html'=>"&#9815;",'codepoint'=>'\u2657'),
    'wn'=>array('html'=>"&#9816;",'codepoint'=>'\u2658'),
    'wp'=>array('html'=>"&#9817;",'codepoint'=>'\u2659'),
    'bk'=>array('html'=>"&#9818;",'codepoint'=>'\u265A'),
    'bq'=>array('html'=>"&#9819;",'codepoint'=>'\u265B'),
    'br'=>array('html'=>"&#9820;",'codepoint'=>'\u265C'),
    'bb'=>array('html'=>"&#9821;",'codepoint'=>'\u265D'),
    'bn'=>array('html'=>"&#9822;",'codepoint'=>'\u265E'),
    'bp'=>array('html'=>"&#9823;",'codepoint'=>'\u265F')
);

function draw_chess_board_from_config($board_json) {
    global $pieces, $num_to_letter;
    $square_size = 80;
    $font_size = $square_size * 0.5;

    //json representation of an initial chess board
    $config = json_decode($board_json);
    $board = '<table class="table"><tbody>';
    for($i=8; $i>=1; $i--) {
        $board .= '<tr>';
        $board .= "<td style='vertical-align:middle; border:0px'>$i</td>";
        for($j=0; $j<8; $j++) {
            $l = $num_to_letter[$j];
            $square = "$l$i";
            $color = (($j+$i) % 2 != 0) ? 'silver' : 'white';
            $piece = property_exists($config, $square) && isset($pieces[$config->$square]) ? $pieces[$config->$square]['html'] : '&nbsp;';
            $board .= "<td style='padding:0; margin:0; border:0px' valign='center' align='center'><button class='btn btn-default' id='$square' type='button' style='width:${square_size}px; height:${square_size}px; background-color: $color; font-size:${font_size}px; border-radius:0px;' onclick='square_select(this)'>$piece</button></td>";
        }
        $board .= '</tr>';
    }
    $board .= '</tr>';
    $board .= '<tr>';
    $board .= "<td style='border:0px'></td>";
    for($j=0; $j<8; $j++) {
        $l = $num_to_letter[$j];
        $board .= "<td align='center'>$l</td>";
    }
    $board .= '</tr>';
    $board .= '</tbody></table>';
    return $board;
}


function get_initial_board()
{
    $board = '{"a8":"br","b8":"bn","c8":"bb","d8":"bq","e8":"bk","f8":"bb","g8":"bn","h8":"br",';
    $board .= '"a7":"bp","b7":"bp","c7":"bp","d7":"bp","e7":"bp","f7":"bp","g7":"bp","h7":"bp",';
    $board .= '"a2":"wp","b2":"wp","c2":"wp","d2":"wp","e2":"wp","f2":"wp","g2":"wp","h2":"wp",';
    $board .= '"a1":"wr","b1":"wn","c1":"wb","d1":"wq","e1":"wk","f1":"wb","g1":"wn","h1":"wr"}';
    return $board;
}


function get_random_board()
{
    global $num_to_letter, $pieces;
    $blank_chance = $_GET['blank_chance'] != null ? intval($_GET['blank_chance']) : rand(0,100);
    $board = '{';
    for($i=8; $i>=1; $i--) {
        for($j=0; $j<8; $j++) {
            $l = $num_to_letter[$j];
            $square = "$l$i";
            if(rand(1,100) > $blank_chance) {
                continue; //allow for blank pieces
            }
            $rand_piece = array_rand($pieces);
            $board .= '"'.$square.'":"'.$rand_piece.'",';
        }
    }
    $board = rtrim($board, ",");
    $board .= '}';
    return $board;
}

//default values for FEN parts
$placement = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR";
$active_color = "w";
$castling_availability = "KQkq";
$en_passant_target = "-";
$halfmove_clock = 0;
$fullmove_number = 0;

function get_board_from_fen($fen)
{
    global $num_to_letter, $pieces, $placement, $active_color, $castling_availability, $en_passant_target, $halfmove_clock, $fullmove_number;

    //split fen into its 6 parts
    $fen_parts = explode(" ", $fen);
    $placement = null;
    $active_color = null;
    $castling_availability = null;
    $en_passant_target = null;
    $halfmove_clock = null;
    $fullmove_number = null;
    if (6 === count($fen_parts)) {
    $placement = $fen_parts[0];
    $active_color = $fen_parts[1];
    $castling_availability = $fen_parts[2];
    $en_passant_target = $fen_parts[3];
    $halfmove_clock = $fen_parts[4];
    $fullmove_number = $fen_parts[5];
    }

    //example = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR'
    $rows = explode("/", $placement);
    $currentRow = 8;
    $board = '{';
    foreach($rows as $row) {
        $currentCol = 1;
        $row_arr = str_split($row);
        foreach($row_arr as $char) {
            if(is_numeric($char)) {
                $step = intval($char);
            }
            else { //add the piece
                $l = $num_to_letter[$currentCol-1];
                $square = "$l$currentRow";
                $piece = convert_fen_piece($char);
                $board .= '"'.$square.'":"'.$piece.'",';
                $step = 1;
            }
            $currentCol = $currentCol + $step;
        }
        $currentRow = $currentRow - 1;
    }
    $board = rtrim($board, ",");
    $board .= '}';
    return $board;
}

function convert_fen_piece($char) {
    //uppercase are white, lowercase are black
    $color = ctype_upper($char) ? 'w' : 'b';
    return $color . strtolower($char);
}

$fen = '';

//define what board JSON to use for initial load
if(isset($_GET['random'])) {
    $board_to_draw = get_random_board();
    //TODO create a convert board to fen function to display random board's initial fen
}
else if(isset($_GET['fen'])) {
    $fen = $_GET['fen'];
    $board_to_draw = get_board_from_fen($fen);
}
else {
    $fen = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
    $board_to_draw = get_initial_board();
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chess by Devin Gray</title>
    <link rel="shortcut icon" href="http://devingray.com/chess/favicon.ico" />

    <!-- Bootstrap -->
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
<div class='container fill' style='min-width: 925px'>
<table><tr><td valign=top style='padding:10px'>
<div class="table-responsive" style='height:100%'>
<?=draw_chess_board_from_config($board_to_draw);?>
</div>
</td>
<td valign=top style='padding:10px'>
<b><a href='http://www.devingray.com/chess'>Chess by Devin Gray</a></b><br />
open source chess application<br />
on <a href="https://github.com/sleddog/chess">github.com/sleddog/chess</a><br />
<hr />

<div id='promotion_selection' style='display:none'>
<b>Promote Pawn:</b><br />
<?php foreach(array("n","b","r","q") as $type) { ?>
<button class="btn btn-default" id="promote_<?=$type;?>" type="button" style="width: 26px; height: 26px; font-size: 18px; border-top-left-radius: 0px; border-top-right-radius: 0px; border-bottom-right-radius: 0px; border-bottom-left-radius: 0px; border: 1px solid black; background-color: <?= $type=='q' ? 'lightgreen' : 'white' ?>; padding: 0px; " onclick="promotion_select(this)"><?=$pieces['w'.$type]['html'];?></button>
<?php } ?>
<br />
<hr />
</div>

<div id='submit_move_div'>
<input type='text' class='input-sm' style='width:45px' id='piece_from' readonly="true" />
<span>-</span>
<input type='text' class='input-sm' style='width:45px' id='piece_to' readonly="true" />
<input class='btn btn-default' id='submit_move_button' type='button' value='Submit Move' onclick='submit_move()' disabled='true'/>
</div>

<hr />
<div id='game_over_div'></div>
<table id='move_history_table' width='180'>
<tr style='border-bottom: 1px solid black'><td>&nbsp;</td><td><b>White</b><br />
<div class="stopwatch" id='w-clock'></div>
</td>
<td><b>Black</b><br />
<div class="stopwatch" id='b-clock'></div>
</td></tr>
</table>
<div id='enable_review' style='display:none'>
  <p style='font-size:35px; padding-left:5px; font-weight:bold'>
    <a href='javascript:void(0);' onclick='enableReview(true)'>&#8672;</a>
  </p>
</div>
<div id='review_controls' style='display:none'>
  <p style='font-size:35px; padding-left:5px; font-weight:bold'>
    <a href="javascript:void(0);" onclick='enableReview(false);' id='review_off'>&#8634;</a>
    <a href="javascript:void(0);" onclick='review("beginning");' id='review_beginning'>&#8676;</a>
    <a href="javascript:void(0);" onclick='review("back");' id='review_back'>&#8672;</a>
    <a href="javascript:void(0);" onclick='review("forward");' id='review_forward'>&#8674;</a>
    <a href="javascript:void(0);" onclick='review("end");' id='review_end'>&#8677;</a>
  </p>
  </div>
<br /><a href="http://en.wikipedia.org/wiki/Portable_Game_Notation">PGN format</a> coming soon
<br />
</td>
</tr></table>
<hr />
<a href='http://en.wikipedia.org/wiki/Forsyth-Edwards_Notation'>FEN record</a> (experimental)<br />
<input type='text' class='input-lg' style='width:700px' id='fen_record' name='fen_record' value="<?=$fen;?>" />
<br />
<a href="javascript:void(0);" onclick="open_fen_in_new_tab()" />Open in New Tab</a>
<hr />
<span style='font-size:10px'><b>Console:</b></span><br />
<div id='chess_console' style='border: 1px solid #dddddd; font-size: 10px'>
</div>
<hr />
FEN History (<a href="javascript:void(0);" onclick="toggle_fen_history()" />+/-</a>):<br />
<div style='display:none' id='fen_history'>
</div>
<hr />
<a href="javascript:void(0);" onclick="open_random_in_new_tab()" />Random Board?</a> (piece generation chance: <input type='text' id='rand_amount' name='rand_amount' value='50' style='width:25px' />%)
</div>

<script>
var selectedSquare = 0;
var targetSquare = 0;
var legal_moves = [];
var board = null;
var num_to_letter = ['a','b','c','d','e','f','g','h'];
var pieces = JSON.parse('<?=json_encode($pieces); ?>');
var history = [];
var ply_count = 0;
var history_cursor = null;
var highlighted_move;
var halfmove_clock = 0;
var fullmove_number = 1;
var special_moves = {};
var en_passant_target = "<?=$en_passant_target;?>";
var promotion_choice = 'q';  // by default assume queen is desired promotion
var review_mode = false;
var saved_board_state = null;
var timers = [];
timers['w'] = null;
timers['b'] = null;
var game_over = false;

function piece_to_unicode(piece) {
    return pieces[piece]['codepoint'];
}

function piece_to_html(piece) {
    return pieces[piece]['html'];
}

function letter_to_num(letter) {
    var letters = "abcdefgh";
    return letters.indexOf(letter);
}

function init_board(board_json) {
    var board_config = JSON.parse(board_json);
    board = new Array(8);
    for (var i=0; i<8; i++) {
        board[i] = new Array(8);
        for (var j=0; j<8; j++) {
            var letter = num_to_letter[j]
            var square = letter + (i+1);
            if (board_config.hasOwnProperty(square)) {
                var value = board_config[square];
                board[i][j] = value;
            }
            else {
                board[i][j] = 0;
            }
        }
    }
    //print_board_to_console(board);
}

function print_board_to_console(board) {
    for (var i=7; i>=0; i--) {
        var row = "";
        for (var j=0; j<8; j++) {
            row += "|" + ((board[i][j] == 0) ? "\u3000" : piece_to_unicode(board[i][j]));
            if (j==7) {
                row += "|";
            }
        }
        console.log(row);
    }
}


function square_to_color(square) {
  var letter = square.substring(0,1);
  var num = parseInt(square.substring(1,2));
  if("aceg".indexOf(letter) >= 0) {
    num++;
  }
  return  (num % 2 != 0) ? "white" : "silver";
}

function square_to_coord(square) {
    var letter = square.substring(0,1);
    var num = parseInt(square.substring(1,2));
    //[i,j]... a1 == [0,0]
    return [num-1, letter_to_num(letter)]
}

function square_select(button) {
    if(review_mode) {
        console.log('in review mode...');
        return;
    }

    var coord = square_to_coord(button.id);
    var value = board[coord[0]][coord[1]];

    if(selectedSquare == 0) {
        //determine if there is even a piece on this square
        if(value == 0) {
            return; // there must be a piece here in order to select
        }
        if(value.substring(0,1) == 'b') {
            return; // only allow white pieces to be selected at first
        }
        highlight_initial_move(button);
    }
    else {
        //are they unselecting the first square?
        if(selectedSquare == button.id) {
            reset_initial_square();
        }
        else { //set target
            //if they select another piece, restart selection
            if(value != 0) {
                if(value.substring(0,1) == 'w') {
                    reset_initial_square();
                    highlight_initial_move(button);
                    return;
                }
            }
            //make sure they are selecting legal moves AND a different target
            setTarget = (legal_moves.indexOf(button.id) > -1) && (targetSquare != button.id);
            reset_target_square();
            if(setTarget) {
                button.style.border = "darkred solid 4px";
                targetSquare = button.id;
                enable_submit_move();
                handle_pawn_promotion(board, selectedSquare, targetSquare);
            }
        }
    }
}

function reset_initial_square() {
    clear_legal_moves();
    document.getElementById(selectedSquare).style.border = 'none';
    selectedSquare = 0;
    document.getElementById('piece_from').value = '';
    reset_target_square();
    special_moves = {};
}

function reset_target_square() {
    if(targetSquare != 0) {
        document.getElementById(targetSquare).style.border = 'none';
    }
    targetSquare = 0;
    document.getElementById('piece_to').value = '';
    document.getElementById('submit_move_button').disabled = true;
    toggle_promote_pawn(false);
}

function enable_submit_move() {
    calculate_fen("b", selectedSquare, targetSquare);
    document.getElementById('submit_move_button').disabled = false;
    document.getElementById('piece_to').value = targetSquare;
}

// build a string representing the move in algebraic notation
// http://en.wikipedia.org/wiki/Algebraic_notation_(chess)
function format_move(next_move, color, promotion_choice)
{
    //split next_move into individual squares
    var moves = next_move.split('-');
    from = moves[0];
    to = moves[1];

    //determine what piece type
    var old_coord = square_to_coord(from);
    var fromPiece = board[old_coord[0]][old_coord[1]];
    pieceType = fromPiece.substring(1,2);

    //determine if this is an attack
    var new_coord = square_to_coord(to);
    var targetPiece = board[new_coord[0]][new_coord[1]];
    var attack = (targetPiece == "0") ? false : true;

    var formattedMove = "";
    switch(pieceType) {
        case 'p':
            if(attack) {
                formattedMove = from.substring(0,1); //grab only the column (a-h) to indicate pawn
            }
            break;
        case 'n':
            formattedMove = 'N'
            break;
        case 'b':
            formattedMove = 'B'
            break;
        case 'r':
            formattedMove = 'R'
            break;
        case 'q':
            formattedMove = 'Q'
            break;
        case 'k':
            formattedMove = 'K'
            break;
        default:
            break;
    }
    if(attack) {
        formattedMove += "x";
    }
    formattedMove += to;

    //rebuild formattedMove for promotions
    if(promotion_choice) {
        formattedMove = "";
        if(attack) {
            formattedMove = from.substring(0,1) + "x";
        }
        formattedMove += to + "=" + promotion_choice;
    }

    // determine if the opposite king is in check
    king_in_check = king_is_in_check(opposite(color), board, old_coord, new_coord)
    // determine if the game is over... i.e. checkmate
    game_over = is_game_over(opposite(color), board, old_coord, new_coord);
    if(game_over) { //game is over (i.e. no legal moves)
        if(king_in_check) { //and king is in check
            formattedMove += "#"; //CHECKMATE!
            update_game_over_box(color, 'checkmate');
        }
        else {
            //no legal moves exist, but king isn't in check
            formattedMove += " stalemate";
            update_game_over_box(color, 'stalemate');
        }
    }
    else if(king_in_check) {
        formattedMove = formattedMove + "+";
    }

    return formattedMove;
}

function update_game_over_box(color, outcome) {
    var box = document.getElementById('game_over_div');
    var msg = "";
    switch(outcome) {
        case 'checkmate':
            msg = (color == 'w') ? "1-0  white wins" : "0-1  black wins";
            break;
        case 'stalemate':
            msg = "&frac12;";
            break;
    }
    box.innerHTML = "<h2 style='padding-left:5px;'>"+msg+"</h2>";
}

function is_game_over(color, bd, old_coord, new_coord) {
    var from = coord_to_square(old_coord);
    var to = coord_to_square(new_coord);
    var new_board = create_new_board(bd, from, to);
    var lm = get_all_legal_moves(color, new_board, en_passant_target);
    if(lm.length == 0) {
        return true;
    }
    return false;
}

function opposite(color) {
    return (color == 'w') ? 'b' : 'w';
}


function king_is_in_check(color, bd, old_coord, new_coord) {
    var from = coord_to_square(old_coord);
    var to = coord_to_square(new_coord);
    var ep_target = fen_en_passant_target(bd, from, to);
    var new_board = create_new_board(bd, from, to);
    king_loc = get_king_location(color, new_board);
    if(king_loc == null) {
        return false; //should never happen...
    }
    //is this king in check? i.e. does this location now fall within the opposite color's attack squares?
    //calculate attack squares
    attack_coords = get_attack_coords(new_board, opposite(color), ep_target);
    var num_moves = 0;
    for(var k=0; k<attack_coords.length; k++) {
        var piece_moves = attack_coords[k].to;
        num_moves += piece_moves.length;
        for(i=0; i<piece_moves.length; i++) {
            //does king loc belong to these attack squares?
            if(king_loc[0] == piece_moves[i][0] && king_loc[1] == piece_moves[i][1]) {
                return true;
            }
        }
    }
    return false;
}


function get_attack_coords(bd, color, ep_target) {
		attack_coords = [];
    for (var i=0; i<8; i++) {
        for (var j=0; j<8; j++) {
            if(bd[i][j] != 0 && bd[i][j].substr(0,1) == color) {
                //get moves for this piece
                moves = get_moves(color, bd, [i,j], ep_target);
                if(moves) {
                    attack_coords.push(moves);
                }
            }
        }
    }
    return attack_coords;
}


function get_moves(color, bd, coord, ep_target) {
    var piece = bd[coord[0]][coord[1]];
    var type = piece.substring(1,2);
    var moves = [];
    switch(type) {
        case 'p':
            moves = get_pawn_moves(color, bd, coord, ep_target);
            break;
        case 'n':
            moves = get_knight_moves(color, bd, coord);
            break;
        case 'b':
            moves = get_bishop_moves(color, bd, coord);
            break;
        case 'r':
            moves = get_rook_moves(color, bd, coord);
            break;
        case 'q':
            moves = get_queen_moves(color, bd, coord);
            break;
        case 'k':
            moves = get_king_moves(color, bd, coord);
            break;
        default:
            break;
    }
    if (moves.length > 0) {
        return {'type':type, 'from':coord,'to':moves};
    }
    else {
        return null;
    }
}
function get_pawn_moves(color, bd, coord, ep_target) {
		var moves = [];

    if(color == 'w') {
        var newCoord = [coord[0]+1, coord[1]];
        //if first square in front is blank
        if(bd[newCoord[0]][newCoord[1]] == 0) {
            moves.push(newCoord);

            //now check 2 moves in front if on the 2nd row
            if(coord[0] == 1) {
                var newCoord2 = [coord[0]+2, coord[1]];
                if(bd[newCoord2[0]][newCoord2[1]] == 0) {
                    moves.push(newCoord2);
                }
            }
        }

        //can you attack diagonally?
        diag_right = [coord[0]+1, coord[1]+1];
        if(diag_right[1] <= 7) {
            var piece = bd[diag_right[0]][diag_right[1]];
            if(piece != 0) {
                //if a piece exists and is black
                if(piece.substring(0,1) == 'b') {
                    moves.push(diag_right);
                }
            }
        }
        diag_left = [coord[0]+1, coord[1]-1];
        if(diag_left[1] >= 0) {
            var piece = bd[diag_left[0]][diag_left[1]];
            if(piece != 0) {
                //if a piece exists and is black
                if(piece.substring(0,1) == 'b') {
                    moves.push(diag_left);
                }
            }
        }

		    // en passant
        if(ep_target != "-") {
            var en_passant_target_coord = square_to_coord(ep_target);
            if(coords_equal(en_passant_target_coord, diag_right)) {
                var from = coord_to_square(coord);
                //special en passant notation, example: exd6e.p.
                var notation = from.substring(0,1)+'x'+en_passant_target+'e.p.';
                special_moves[from+'-'+en_passant_target] = notation;
                moves.push(diag_right);
            }
            if(coords_equal(en_passant_target_coord, diag_left)) {
                var from = coord_to_square(coord);
                //special en passant notation, example: exd6e.p.
                var notation = from.substring(0,1)+'x'+en_passant_target+'e.p.';
                special_moves[from+'-'+en_passant_target] = notation;
                moves.push(diag_left);
            }
        }
    }
    else { // color == 'b'
        var newCoord = [coord[0]-1, coord[1]];
        //if first square below blank?
        if(bd[newCoord[0]][newCoord[1]] == 0) {
            moves.push(newCoord);

            //now check 2 moves below if on the 7th row (6 on the board)
            if(coord[0] == 6) {
                var newCoord2 = [coord[0]-2, coord[1]];
                if(bd[newCoord2[0]][newCoord2[1]] == 0) {
                    moves.push(newCoord2);
                }
            }
        }

        //can you attack diagonally?
        diag_right = [coord[0]-1, coord[1]+1];
        if(diag_right[1] <= 7) {
            var piece = bd[diag_right[0]][diag_right[1]];
            if(piece != 0) {
                //if a piece exists and is white
                if(piece.substring(0,1) == 'w') {
                    moves.push(diag_right);
                }
            }
        }
        diag_left = [coord[0]-1, coord[1]-1];
        if(diag_left[1] >= 0) {
            var piece = bd[diag_left[0]][diag_left[1]];
            if(piece != 0) {
                //if a piece exists and is white
                if(piece.substring(0,1) == 'w') {
                    moves.push(diag_left);
                }
            }
        }
		    // en passant
        if(ep_target != "-") {
            var en_passant_target_coord = square_to_coord(ep_target);
            if(coords_equal(en_passant_target_coord, diag_right)) {
                moves.push(diag_right);
            }
            if(coords_equal(en_passant_target_coord, diag_left)) {
                moves.push(diag_left);
            }
        }
    }
    //check if any of these pawn moves are promotions
    for(var i=0; i<moves.length; i++) {
        if(color == 'w' && moves[i][0] == 7) {
            special_moves[coord_to_square(coord)+'-'+coord_to_square(moves[i])] = '=';
        }
        else if(color == 'b' && moves[i][0] == 0) {
            //TODO?
        }
    }
		return moves;
}

function get_knight_moves(color, bd, coord) {
    //create an array of coords, based on knight's movement (L shape)
    var moves = new Array(8);
    moves[0] = [coord[0]+2, coord[1]-1];
    moves[1] = [coord[0]+2, coord[1]+1];
    moves[2] = [coord[0]-2, coord[1]-1];
    moves[3] = [coord[0]-2, coord[1]+1];
    moves[4] = [coord[0]+1, coord[1]-2];
    moves[5] = [coord[0]+1, coord[1]+2];
    moves[6] = [coord[0]-1, coord[1]-2];
    moves[7] = [coord[0]-1, coord[1]+2];
    //check each move
		lm = [];
    for(var i=0; i<8; i++) {
        if(get_can_move(color, bd, moves[i])) {
            lm.push(moves[i]);
        }
    }
		return lm;
}

function get_can_move(color, bd, coord) {
    //is off board?
    if(coord[0] < 0 || coord[0] > 7 || coord[1] < 0 || coord[1] > 7) {
        return false;
    }
    //is the square empty?
    var square = bd[coord[0]][coord[1]];
    if(square == 0) {
        return true;
    }
    else {
        if(square.substring(0,1) == opposite(color)) {
            return true;
        }
        else {
            return false;
        }
    }
}
function get_moves_from_directions(color, bd, directions, coord) {
		lm = [];
    for(var i=0; i<directions.length; i++) {
        var dir = directions[i];
        var step = 1;
        //for each direction, travel until the edge of board or piece
        while(true) {
            var move = [coord[0]+(dir[0]*step), coord[1]+(dir[1]*step)];
            if(get_can_move(color, bd, move)) {
                lm.push(move);
                step++;
                //if move is opposite piece, stop calculating on this line
                var square = bd[move[0]][move[1]];
                if(square != 0 && square.substring(0,1) == opposite(color)) {
                    break;
                }
            }
            else {
                break; //can't move here...
            }
        }
    }
		return lm;
}

function get_bishop_moves(color, bd, coord) {
    var directions = [[1,1],[1,-1],[-1,-1],[-1,1]];
    return get_moves_from_directions(color, bd, directions, coord);
}


function get_rook_moves(color, bd, coord) {
    var directions = [[1,0],[0,1],[-1,0],[0,-1]];
    return get_moves_from_directions(color, bd, directions, coord);
}


function get_queen_moves(color, bd, coord) {
		lm = get_bishop_moves(color, bd, coord);
		return lm.concat(get_rook_moves(color, bd, coord));
}


function get_king_moves(color, bd, coord) {
	  var lm = [];
    var directions = [[1,0],[0,1],[-1,0],[0,-1],[1,1],[1,-1],[-1,-1],[-1,1]];
    for(var i=0; i<directions.length; i++) {
        var move = [coord[0]+directions[i][0], coord[1]+directions[i][1]];
        if(get_can_move(color, bd, move)) {
            lm.push(move);
        }
    }
    lm = lm.concat(king_castle_moves(bd, coord, color));
		return lm;
}

var waitingForBlack = false;
function submit_move() {
    if(waitingForBlack){
        // show a popup telling the user black has not played.
        // just using an alert for testing.
        alert("Black hasn't moved yet!")
        return
    }
    //validate one last time...
    if(selectedSquare == 0 || targetSquare == 0) {
        alert('error some how...');
        return;
    }
    var selectedMove = selectedSquare + '-' + targetSquare;
    var formattedMove = '';
    var special_move = is_special_move(selectedMove);
    if(special_move) {
        //variable flipped for special moves (en passant and castling)
        formattedMove = special_move;
        switch(special_move) {
            case 'O-O':
                //move the rook from h1 to f1
                move_pieces('h1', 'f1');
                break;
            case 'O-O-O':
                //move the rook from a1 to cd
                move_pieces('a1', 'd1');
                break;
            case '=':
                //pawn promotion
                var promotedCoord = square_to_coord(selectedSquare);
                var promotedPiece = 'w'+promotion_choice;
                board[promotedCoord[0]][promotedCoord[1]] = promotedPiece;
                formattedMove = format_move(selectedMove, 'w', promotion_choice.toUpperCase());
                document.getElementById(targetSquare).innerHTML = piece_to_html(promotedPiece);
                break;
            default:
                //en passant example notation = 'exd6e.p.'
                if(special_move.substring(4,8) == "e.p.") {
                    //need to remove the attacked pawn (this example pawn on d5)
                    var remove_square = targetSquare.substring(0,1) + "5"; //always 5th for white
                    var remove_coord = square_to_coord(remove_square);
                    board[remove_coord[0]][remove_coord[1]] = 0;
                    document.getElementById(remove_square).innerHTML = '&nbsp;';
                }
                break;
        }
    }
    else {
        formattedMove = format_move(selectedMove, 'w');
    }

    //move the piece
    move_pieces(selectedSquare, targetSquare);

    //play a move sound
    play_move_sound('white', selectedSquare, targetSquare);

    //reset selections
    reset_initial_square();

    //update the history
    update_history('w', selectedMove, formattedMove);

    //TODO revisit history/review feature later...
    //hide review controls
    //enableReview(false);
    waitingForBlack = true;
    //now call the AI to get the computer's move
    get_next_move(selectedMove);
}

function play_move_sound(player, selectedSquare, targetSquare) {
  window.speechSynthesis.speak(
      new SpeechSynthesisUtterance(player + ' plays ' + selectedSquare + ' to ' + targetSquare)
  );
  new Audio('resources/move.mp3').play();

}

function addStatsToConsole(player, move, formattedMove) {
    var elapsed_time = (player == 'w') ? wClock.timestamp() : bClock.timestamp();
    var entry = 'Elapsed Time: ' + elapsed_time + '<br />';
    entry += (player == 'w') ? 'white (Human)' : 'black (AI)';
    entry += ' selected: <b>'+move+'</b>'
    entry += '&nbsp;&nbsp;&nbsp;or&nbsp;&nbsp;&nbsp;'+formattedMove+'<br />';
    addToConsole(entry);
}

function get_next_move(selectedMove) {
    //determine what mode we are in... human vs human, human vs AI
    //assuming for now to just be simple random AI
    get_move_from_server(selectedMove);
}

function get_move_from_server(selectedMove) {
  //var chessAI = "http://www.devingray.com/cgi-bin/chess_ai.cgi";
  var chessAI = "http://www.devingray.com/cgi-bin/chess_go_ai.cgi";
  $.getJSON( chessAI, {
    board: JSON.stringify(board),
    move: selectedMove //"e2-e4" this will be a legal chess move
  })
    .done(function( data ) {
        addToConsole('<hr style="margin:5px"/>');
        if(data['stats']) {
            update_stats(data);
        }
        if(data['next-move']) {
            make_move(data['next-move']);

            var moves = data['next-move'].split('-');
            play_move_sound('black', moves[0], moves[1]);

            // allow the player to move.
            waitingForBlack = false;
        }
        addToConsole('<hr style="margin:5px"/>');
    });
}

function make_move(next_move) {
    var moves = next_move.split('-');
    if(moves.length != 2) {
        alert('error with move: ' + next_move);
        return;
    }
    //make sure this is a valid move
    var old_coord = square_to_coord(moves[0]);
    var piece = board[old_coord[0]][old_coord[1]];
    if(piece && piece.substring(0,1) == 'b') {
        var formattedMove = format_move(next_move, 'b');
        fullmove_number++;
        calculate_fen('w', moves[0], moves[1]);
        move_pieces(moves[0], moves[1]);

        //update the history
        update_history('b', next_move, formattedMove);
    }
}

function move_pieces(from, to) {
    //move the piece
    var old_coord = square_to_coord(from);
    var new_coord = square_to_coord(to);
    var piece = board[old_coord[0]][old_coord[1]];
    board[old_coord[0]][old_coord[1]] = 0;
    board[new_coord[0]][new_coord[1]] = piece;
    //print_board_to_console(board);
    document.getElementById(from).innerHTML = '&nbsp;';
    document.getElementById(to).innerHTML = piece_to_html(piece);
}


function highlight_initial_move(button) {
    clear_legal_moves();
    selectedSquare = button.id;
    button.style.border = "darkblue solid 4px";
    highlight_legal_moves(selectedSquare);
    document.getElementById('piece_from').value = selectedSquare;
}

function highlight_legal_moves(selectedSquare) {
    //inspect the board, and determine what the legal moves are
    var coord = square_to_coord(selectedSquare);
    var piece = board[coord[0]][coord[1]];
    var color = piece.substring(0,1);
    var type = piece.substring(1,2);
    var moves = get_legal_moves(color, board, coord, en_passant_target);
    for(var i=0; i<moves.length; i++) {
        var square = coord_to_square(moves[i]);
        document.getElementById(square).style.backgroundColor = legal_move_color(square);
        legal_moves.push(square);
    }
}

function get_all_legal_moves(color, bd, ep_target) {
		var lm = [];
    for (var i=0; i<8; i++) {
        for (var j=0; j<8; j++) {
            if(bd[i][j] != 0 && bd[i][j].substr(0,1) == color) {
                //get moves for this piece
                var moves = get_legal_moves(color, bd, [i,j], ep_target);
                if(moves) {
                    if(moves.length > 0) {
                        lm.push(moves);
                    }
                }
            }
        }
    }
    return lm;
}

function get_legal_moves(color, bd, coord, ep_target) {
    var lm = [];
    var moveObj = get_moves(color, bd, coord, ep_target);
    if(!moveObj) {
        return null;
    }
    var moves = moveObj.to;
    var from = coord_to_square(coord);
    for(var i=0; i<moves.length; i++) {
        // determine if this move would place player in check
        var new_coord = moves[i];
        if(!king_is_in_check(color, bd, coord, new_coord)) {
            lm.push(new_coord);
        }
    }
    return lm;
}

function coord_to_square(coord) {
    var letter = num_to_letter[coord[1]];
    return letter + (coord[0]+1);
}

//from the given coord, highlight legal pawn moves, for the respective color
function coords_equal(coord1, coord2) {
    return ((coord1[0] == coord2[0]) && (coord1[1] == coord2[1]));
}

function king_castle_moves(bd, coord, color) {
    var moves = [];
    //TODO use the FEN board to know if castling is allowed
    if(color == 'w') {
        //for now just assume you can if the king and rook are in original location
        //and there are blank pieces between them
        if(coord[0] == 0 && coord[1] == 4) { //original location of king
            var kingSideRook = bd[0][7];
            if(kingSideRook == 'wr') {
                //check for blank squares
                if(bd[0][5] == 0 && bd[0][6] == 0) {
                    //white king can castle on the king side: O-O
                    special_moves[coord_to_square(coord)+'-'+coord_to_square([0,6])] = 'O-O';
                    moves.push([0, 6]);
                }
            }
            var queenSideRook = board[0][0];
            if(queenSideRook == 'wr') {
                //check for blank squares
                if(board[0][1] == 0 && board[0][2] == 0 && board[0][3] == 0) {
                    //white king can castle on the queen side: O-O-O
                    special_moves[coord_to_square(coord)+'-'+coord_to_square([0,2])] = 'O-O-O';
                    moves.push([0, 2]);
                }
            }
        }
    }
    else { // color == 'b'
        //for now just assume you can if the king and rook are in original location
        //and there are blank pieces between them
        if(coord[0] == 7 && coord[1] == 4) { //original location of king
            var kingSideRook = bd[7][7];
            if(kingSideRook == 'br') {
                //check for blank squares
                if(bd[7][5] == 0 && bd[7][6] == 0) {
                    moves.push([7, 6]);
                }
            }
            var queenSideRook = board[0][0];
            if(queenSideRook == 'br') {
                //check for blank squares
                if(board[7][1] == 0 && board[7][2] == 0 && board[7][3] == 0) {
                    moves.push([7, 2]);
                }
            }
        }
    }
    return moves;
}

function legal_move_color(square) {
    var color = square_to_color(square);
    return (color == 'white') ? "#FFFF99" : "#999966";
}


function get_king_location(color, bd) {
    for (var i=0; i<8; i++) {
        for (var j=0; j<8; j++) {
						if(bd[i][j] == color+'k') {
                return [i,j];
            }
        }
    }
		return null; //error king should always be present on the board
}


function clear_legal_moves() {
    for(var i=0; i<legal_moves.length; i++) {
        var square = legal_moves[i];
        document.getElementById(square).style.backgroundColor = square_to_color(square);
    }
    legal_moves = [];
}


function update_history(player, move, formattedMove) {
    //update console
    addStatsToConsole(player, move, formattedMove);

    var table = document.getElementById('move_history_table');
    if(!table) {
        return;
    }

    //based on the player, update the appropriate cell, or create new row
    var rowCount = table.rows.length
    var tablePositionRow = rowCount - 1;
    var tablePositionCol = 0;
    if(player == 'w') {
        var row = table.insertRow(rowCount);
        var moveNumber = row.insertCell(0);
        moveNumber.innerHTML = rowCount;
        var white = row.insertCell(1);
        white.innerHTML = formattedMove;
    }
    else { // black
        var row = table.rows[rowCount-1];
        var black = row.insertCell(2);
        black.innerHTML = formattedMove;
        tablePositionCol = 1;
        tablePositionRow--;
    }

    //update the history with this move
    var fen = document.getElementById('fen_record').value;
    update_fen_history(fen);
    var tablePosition = tablePositionRow + ":" + tablePositionCol;
    history[ply_count] = [player, move, fen, tablePosition];
    history_cursor = ply_count;
    ply_count++;

    //highlight what squares did the move
    set_highlighted_move(move)

    //update the timer
    update_timer(player);
}


function update_timer(player) {
    if(player == 'w') {
        wClock.stop();
        bClock.start();
    }
    else { //player == 'b'
        wClock.start();
        bClock.stop();
    }

    if(game_over) {
        //stop timers
        wClock.stop();
        bClock.stop();
    }
}


function clear_highlighted_move() {
    if(!highlighted_move) {
        return;
    }
    var squares = highlighted_move.split('-');
    document.getElementById(squares[0]).style.border = 'none';
    document.getElementById(squares[1]).style.border = 'none';
}


function set_highlighted_move(move) {
    clear_highlighted_move()
    highlighted_move = move;
    var squares = highlighted_move.split('-');
    document.getElementById(squares[0]).style.border = '#666666 solid 3px';
    document.getElementById(squares[1]).style.border = '#999999 solid 3px';
}


//return a string representing the FEN record for the current board
//http://en.wikipedia.org/wiki/Forsyth-Edwards_Notation
//example initial board:  "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"
function calculate_fen(active_color, from, to) {
    //create a new local copy of the board and simulate the current move
    var fen_board = create_new_board(board, from, to);

    //6 part string separated by spaces
    var fen = "";

    //1. Piece placement
    fen += fen_piece_placement(fen_board) + " ";
    //2. Active color. "w" means White moves next, "b" means Black.
    fen += active_color + " ";
    //3. Castling availability.
    fen += fen_castling_availability(fen_board, from, to) + " ";
    //4. En passant target square in algebraic notation
    target = fen_en_passant_target(board, from, to);
    if(active_color == 'w') {
        //cache the target square from black
        en_passant_target = target;
    }
    fen += target + " ";
    //5. Halfmove clock
    fen += fen_halfmove_clock() + " ";
    //6. Fullmove number
    fen += fen_fullmove_number();

    document.getElementById('fen_record').value = fen;
}

function create_new_board(bd, from, to) {
    var new_board = [];
    for (var i = 0; i < bd.length; i++)
        new_board[i] = bd[i].slice();
    var old_coord = square_to_coord(from);
    var new_coord = square_to_coord(to);
    new_board = move_pieces_on_board(new_board, old_coord, new_coord);
    //perform any special moves that may exist
    new_board = perform_special_moves(new_board, from, to);
    return new_board;
}

function move_pieces_on_board(bd, old_coord, new_coord) {
    var piece = bd[old_coord[0]][old_coord[1]];
    bd[old_coord[0]][old_coord[1]] = 0;
    bd[new_coord[0]][new_coord[1]] = piece;
    return bd;
}

function perform_special_moves(bd, from, to) {
    var special_move = is_special_move(from+'-'+to);
    if(special_move) {
        switch(special_move) {
            case 'O-O':
                //move the rook from h1 to f1
                bd = move_pieces_on_board(bd, square_to_coord('h1'), square_to_coord('f1'));
                break;
            case 'O-O-O':
                //move the rook from a1 to d1
                bd = move_pieces_on_board(bd, square_to_coord('a1'), square_to_coord('d1'));
                break;
            default:
                //en passant example notation = 'exd6e.p.'
                if(special_move.substring(4,8) == "e.p.") {
                    //need to remove the attacked pawn (this example pawn on d5)
                    var remove_square = to.substring(0,1) + "5"; //always 5th for white
                    var remove_coord = square_to_coord(remove_square);
                    bd[remove_coord[0]][remove_coord[1]] = 0;
                }
                break;
            //TODO implement pawn promotions... for now just assume pawn-->queen
        }
    }
    return bd;
}

//Piece placement (from white's perspective). Each rank is described, starting with rank 8 and ending with rank 1; within each rank, the contents of each square are described from file "a" through file "h". Following the Standard Algebraic Notation (SAN), each piece is identified by a single letter taken from the standard English names (pawn = "P", knight = "N", bishop = "B", rook = "R", queen = "Q" and king = "K").[1] White pieces are designated using upper-case letters ("PNBRQK") while black pieces use lowercase ("pnbrqk"). Empty squares are noted using digits 1 through 8 (the number of empty squares), and "/" separates ranks.
function fen_piece_placement(fen_board) {
    var piece_placement = "";
    for (var i=7; i>=0; i--) {
        var row = "";
        var blankCount = 0;
        for (var j=0; j<8; j++) {
            if(fen_board[i][j] == 0) {
                blankCount++;
                if(j==7) {
                    row += blankCount;
                }
            }
            else {
                if(blankCount > 0) {
                    row += blankCount;
                }
                row += piece_to_fen(fen_board[i][j]);
                blankCount = 0;
            }
            if (j==7 && i != 0) {
                row += "/";
            }
        }
        piece_placement += row;
    }
    return piece_placement;
}

function piece_to_fen(piece) {
    var color = piece.substr(0,1);
    var type = piece.substr(1,2);
    if(color == 'w') {
        return type.toUpperCase();
    }
    else {
        return type.toLowerCase();
    }
}

//Castling availability. If neither side can castle, this is "-". Otherwise, this has one or more letters: "K" (White can castle kingside), "Q" (White can castle queenside), "k" (Black can castle kingside), and/or "q" (Black can castle queenside).
function fen_castling_availability(fen_board, from, to) {
    var castling_available = {'K':true, 'Q':true, 'k':true, 'q':true};
    var old_coord = square_to_coord(from);
    var new_coord = square_to_coord(to);
    var piece = board[old_coord[0]][old_coord[1]];
    switch(piece) {
        case 'wk':
            castling_available['K'] = false;
            castling_available['Q'] = false;
            break;
        case 'bk':
            castling_available['k'] = false;
            castling_available['q'] = false;
            break;
    }

    var str = '';
    str += castling_available['K'] ? 'K' : '';
    str += castling_available['Q'] ? 'Q' : '';
    str += castling_available['k'] ? 'k' : '';
    str += castling_available['q'] ? 'q' : '';
    if(str == '') {
        str = '-';
    }
    return str;
}

//En passant target square in algebraic notation. If there's no en passant target square, this is "-". If a pawn has just made a two-square move, this is the position "behind" the pawn. This is recorded regardless of whether there is a pawn in position to make an en passant capture.
function fen_en_passant_target(bd, from, to) {
    var old_coord = square_to_coord(from);
    var new_coord = square_to_coord(to);
    var piece = bd[old_coord[0]][old_coord[1]];
    target = "-";
    if(piece == 'wp') {
        //white pawn on initial row and moved 2 spaces
        if(old_coord[0] == 1 && new_coord[0] == 3) {
            target = coord_to_square([2,old_coord[1]]);
        }
    }
    else if(piece == 'bp') {
        //black pawn on initial row and moved 2 spaces
        if(old_coord[0] == 6 && new_coord[0] == 4) {
            target = coord_to_square([5,old_coord[1]]);
        }
    }
    return target;
}

//Halfmove clock: This is the number of halfmoves since the last capture or pawn advance. This is used to determine if a draw can be claimed under the fifty-move rule.
function fen_halfmove_clock() {
    return halfmove_clock; //TODO finish halfmove clock
}

//Fullmove number: The number of the full move. It starts at 1, and is incremented after Black's move.
function fen_fullmove_number() {
    return fullmove_number;
}

function is_special_move(move) {
    //check if this move is in the special_moves dictionary
    if (special_moves.hasOwnProperty(move)) {
        return special_moves[move];
    }
    return null;
}

function open_fen_in_new_tab(fen) {
    if(!fen) {
        fen = document.getElementById('fen_record').value;
    }
    window.open('/chess/?fen='+encodeURIComponent(fen), '_blank');
}

function open_random_in_new_tab() {
    var rand_amount = document.getElementById('rand_amount').value;
    window.open('/chess/?random=True&blank_chance='+parseInt(rand_amount), '_blank');
}


function handle_pawn_promotion(bd, from, to) {
    //determine if this piece is a pawn in the act of promoting (reach last rank)
    var old_coord = square_to_coord(from);
    var fromPiece = bd[old_coord[0]][old_coord[1]];
    var color = fromPiece.substring(0,1);
    var pieceType = fromPiece.substring(1,2);
    if(pieceType != 'p') {
        return;
    }

    var new_coord = square_to_coord(to);
    if(color == 'w' && new_coord[0] == 7) {
        // white pawn is promoting
        toggle_promote_pawn(true);
    }
    else if(color =='b' && new_coord[0] == 0){
        // black pawn is promoting... TODO?
    }
}


function toggle_promote_pawn(shouldDisplay) {
    var d = document.getElementById('promotion_selection');
    if(shouldDisplay) {
        d.style.display = 'inline';
        // by default reset pawn promotion to queen (very very rarely would one underpromote)
        promotion_select(document.getElementById('promote_q'));
    }
    else {
        d.style.display = 'none';
    }
}

function promotion_select(button) {
    var type = button.id.substring(button.id.length - 1);
    document.getElementById('promote_'+promotion_choice).style.backgroundColor = 'white';
    button.style.backgroundColor = 'lightgreen';
    promotion_choice = type;
}


function toggle_fen_history() {
    var history_div = document.getElementById('fen_history');
    history_div.style.display = (history_div.style.display == 'none') ? 'inline' : 'none';
}


function update_fen_history(fen) {
    var link = '<a href="javascript:void(0);" onclick="open_fen_in_new_tab(\''+fen+'\');" />'+fen+'</a><br />'
    document.getElementById('fen_history').innerHTML += link;
    addToConsole(link);
}


//Review a move from the history.  Update the board UI, lock out move selection
function review(direction) {
    if(!saved_board_state) {
        saved_board_state = board;
    }
    var currentObj = history[history_cursor];
    switch(direction) {
        case 'beginning':
            history_cursor=0;
            break;
        case 'back':
            if(history_cursor <=0) {
                //at the beginning of history...
                return;
            }
            history_cursor--;
            break;
        case 'forward':
            if(history_cursor >= history.length-1) {
                //at the end of history...
                return;
            }
            history_cursor++;
            break;
        case 'end':
            history_cursor=history.length-1;
            break;
        default:
            return;
    }
    if(currentObj) {
        var currentCell = getHistoryCell(currentObj);
        currentCell.style.border = '0px';
    }
    var newObj = history[history_cursor];
    var newCell = getHistoryCell(newObj);
    newCell.style.border = '1px solid red';
}

function getHistoryCell(historyObj) {
    if(!historyObj) {
        return null;
    }
    //history[ply_count] = [player, move, fen, tablePosition];
    var player = historyObj[0];
    var move = historyObj[1];
    var fen = historyObj[2];
    var currentTablePosition = historyObj[3];
    var a = currentTablePosition.split(":");
    var tablePositionRow = parseInt(a[0]);
    var tablePositionCol = parseInt(a[1]);

    //for now just draw a red box around the cell
    var table = document.getElementById('move_history_table');
    var row = table.rows[tablePositionRow+1];
    var cell = row.cells[tablePositionCol+1];
    return cell;
}

function enableReview(enable) {
    var reviewControls = document.getElementById('review_controls');
    var enableReview = document.getElementById('enable_review');
    if(enable) {
        reviewControls.style.display = 'inline';
        enableReview.style.display = 'none';
        review("end");
        review_mode = true;
        reset_initial_square();
    }
    else {
        reviewControls.style.display = 'none';
        enableReview.style.display = 'inline';
        //turn off all history highlighting
        var currentObj = history[history_cursor];
        var cell = getHistoryCell(currentObj);
        if(cell) {
            cell.style.border = '0px';
        }
        history_cursor=null;
        review_mode = false;
    }
}


//JS object to store a chess clock timer
var ChessClock = function(elem) {
    var timer = createClock();
    var offset;
    var clock;
    var interval;

    elem.appendChild(timer);

    clock = 0;
    refreshClock();

    function createClock() {
        return document.createElement("span");
    }

    function start() {
        if (!interval) {
            offset   = Date.now();
            interval = setInterval(update, 1);
        }
    }

    function stop() {
        if (interval) {
            clearInterval(interval);
            interval = null;
        }
    }

    function update() {
        clock += timeDiff();
        refreshClock();
    }

    function timestamp() {
        var totalSec = Math.round(clock/1000);
        var hours = parseInt( totalSec / 3600 ) % 24;
        var minutes = parseInt( totalSec / 60 ) % 60;
        var seconds = totalSec % 60;
        var result = (hours < 10 ? "0" + hours : hours) + "-" +
                     (minutes < 10 ? "0" + minutes : minutes) + "-" +
                     (seconds  < 10 ? "0" + seconds : seconds);
        return result;
    }

    function refreshClock() {
        timer.innerHTML = timestamp();
    }

    function timeDiff() {
        var now = Date.now();
        var d = now - offset;
        offset = now;
        return d;
    }

    this.start  = start;
    this.stop   = stop;
    this.timestamp = timestamp;
};

function update_stats(data) {
    var stats = data['stats'];
    var next_move = data['next-move'];
    console.log('update_stats()');
    console.log(stats);
    var entry = ""
    entry += '# boards evaluated per depth:<br />';
    for (var key in stats) {
        if (stats.hasOwnProperty(key)) {
            console.log(key, stats[key]);
            entry += key + ":" + stats[key] + " | ";
        }
    }
    entry += '<br />';
    addToConsole(entry);
}

function addToConsole(msg) {
    var chess_console = document.getElementById('chess_console');
    chess_console.innerHTML = msg + chess_console.innerHTML;
}

//onload
(function() {
    init_board('<?=$board_to_draw; ?>');

    //create clocks
    wClock = new ChessClock(document.getElementById("w-clock"));
    bClock = new ChessClock(document.getElementById("b-clock"));

})();

</script>
<script type="text/javascript">
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
var pageTracker = _gat._getTracker("UA-1105056-1");
pageTracker._trackPageview();
</script>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <!-- Latest compiled and minified JavaScript -->
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
  </body>
</html>

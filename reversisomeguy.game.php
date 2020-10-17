<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * ReversiSomeguy implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * reversisomeguy.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */


require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');


class ReversiSomeguy extends Table
{
    function __construct()
    {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(array(
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ));
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "reversisomeguy";
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = array())
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = array("000000", "ffffff");

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)
        self::initStat("table", 'turns_number', 0);
        self::initStat("player", 'cornerDiscs', 0);
        self::initStat("player", 'borderDiscs', 0);
        self::initStat("player", 'centerDiscs', 0);
        self::initStat("player", 'turnedOver', 0);

        // init board
        $sql = "INSERT INTO board (board_x, board_y, board_player) VALUES ";
        $sql_values = array();
        list($black_player_id, $white_player_id) = array_keys($players);

        for ($x = 0; $x < 8; $x++) {
            for ($y = 0; $y < 8; $y++) {
                $token_value = "NULL";
                if (($x == 3 && $y == 3) || ($x == 4 && $y == 4))
                    $token_value = "'$white_player_id'";
                else if (($x == 3 && $y == 4) || ($x == 4 && $y == 3))
                    $token_value = "'$black_player_id'";
                $sql_values[] = "('$x', '$y', $token_value)";
            }
        }
        $sql .= implode(',', $sql_values);
        self::DbQuery($sql);

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array('players' => array());

        // Add players specific infos
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // get board
        $sql = "SELECT board_x x, board_y y, board_player player
        FROM board 
        WHERE board_player IS NOT NULL";

        $result['board'] = self::getObjectListFromDB($sql);

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    

    /*
    In this space, you can put any utility methods useful for your game logic
    */
    static function getBoard()
    {
        $sql = "SELECT board_x x, board_y y, board_player player FROM board";
        return self::getDoubleKeyCollectionFromDB($sql, true);
    }

    function getTurnedOverDiscs($x, $y, $player, $board)
    {
        // check if square has a disc
        $player_disc = $board[$x][$y];
        if (!is_null($player_disc))
            return [];

        $turned_discs = [];
        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                if ($i === 0 && $j === 0)
                    continue;

                $isLineOfDiscs = false;
                $line_of_discs = [];
                $x_direction = $i;
                $y_direction = $j;
                $nextX = $x + $i;
                $nextY = $y + $j;
                while ($nextX >= 0 && $nextX < 8 && $nextY >= 0 && $nextY < 8) {
                    $next_disc = $board[$nextX][$nextY];
                    // looking for the opponents disc to start a line
                    // then own disc to reverse disc inbetween
                    if (is_null($next_disc))
                        break;
                    else if ($next_disc === $player && !$isLineOfDiscs)
                        break;
                    else if ($next_disc === $player && $isLineOfDiscs) {
                        $turned_discs = array_merge($turned_discs, $line_of_discs);
                        break;
                    } else {
                        $isLineOfDiscs = true;
                        $line_of_discs[] = [$nextX, $nextY];
                    }

                    $nextX += $x_direction;
                    $nextY += $y_direction;
                }
            }
        }

        return $turned_discs;
    }


    function getPossibleMoves($player)
    {
        $board = self::getBoard();

        $moves = [];
        for ($x = 0; $x < 8; $x++) {
            for ($y = 0; $y < 8; $y++) {
                $turned_discs = $this->getTurnedOverDiscs($x, $y, $player, $board);
                if ($turned_discs)
                    $moves[] = [$x, $y];
            }
        }

        return $moves;
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in reversisomeguy.action.php)
    */

    function playDisc($x, $y)
    {
        // check if action allowed in current state
        self::checkAction('playDisc');

        $board = self::getBoard();
        $player_id = self::getActivePlayerId();
        $turned_over_discs = $this->getTurnedOverDiscs($x, $y, $player_id, $board);

        $sql = "UPDATE board
        SET board_player='$player_id'
        WHERE (board_x, board_y) IN ( ";
        foreach ($turned_over_discs as $disc) {
            [$turned_x, $turned_y] = $disc;
            $sql .= "($turned_x, $turned_y),";
        }
        $sql .= "($x, $y))";
        self::DbQuery($sql);

        $sql = "UPDATE player
        SET player_score = (
        SELECT COUNT(board_x) FROM board WHERE board_player='$player_id'
        )
        WHERE player_id='$player_id'";
        self::DbQuery($sql);

        $sql = "UPDATE player
        SET player_score = (
        SELECT COUNT(board_x) FROM board
        WHERE NOT board_player='$player_id' AND board_player IS NOT NULL
        )
        WHERE NOT player_id='$player_id'";
        self::DbQuery($sql);

        // statistics
        self::incStat(count($turned_over_discs), "turnedOver", $player_id);

        if (($x == 0 && $y == 0) || ($x == 7 && $y == 0) || ($x == 7 && $y == 7) || ($x == 0 && $y == 7))
            self::incStat(1, "cornerDiscs", $player_id);
        else if ($x == 0 || $x == 7 || $y == 0 || $y == 7)
            self::incStat(1, "borderDiscs", $player_id);
        else if ($x >= 2 && $x <= 5 && $y >= 2 && $y <= 5)
            self::incStat(1, "centerDiscs", $player_id);

        // notify
        self::notifyAllPlayers(
            "playDisc",
            clienttranslate('${player_name} plays a disc and turns over ${returned_nbr} disc(s)'),
            array(
                "player_id" => $player_id,
                "player_name" => self::getActivePlayerName(),
                "returned_nbr" => count($turned_over_discs),
                "x" => $x,
                "y" => $y
            )
        );

        self::notifyAllPlayers("turnOverDiscs", "", array(
            "player_id" => $player_id,
            "turnedOver" => $turned_over_discs
        ));

        $new_scores = self::getObjectListFromDB("SELECT player_id, player_score FROM player");
        self::notifyAllPlayers("newScores", "", array(
            "scores" => $new_scores
        ));

        // next state
        $this->gamestate->nextState('playDisc');
    }

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */


    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argPlayerTurn()
    {
        return array(
            'possibleMoves' => $this->getPossibleMoves(self::getActivePlayerId())
        );
    }

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, '');

            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
        //        if( $from_version <= 1404301345 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        if( $from_version <= 1405061421 )
        //        {
        //            // ! important ! Use DBPREFIX_<table_name> for all tables
        //
        //            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
        //            self::applyDbUpgradeToAllDB( $sql );
        //        }
        //        // Please add your future database scheme changes here
        //
        //


    }
}

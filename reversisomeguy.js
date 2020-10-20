/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * ReversiSomeguy implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * reversisomeguy.js
 *
 * ReversiSomeguy user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo", "dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
],
    function (dojo, declare) {
        return declare("bgagame.reversisomeguy", ebg.core.gamegui, {
            constructor: function () {
                console.log('reversisomeguy constructor');

                // Here, you can init the global variables of your user interface
                // Example:
                // this.myGlobalValue = 0;

            },

            /*
                setup:
                
                This method must set up the game user interface according to current game situation specified
                in parameters.
                
                The method is called each time the game interface is displayed to a player, ie:
                _ when the game starts
                _ when a player refreshes the game page (F5)
                
                "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
            */

            setup: function (gamedatas) {
                console.log("Starting game setup");
                // set board
                for (let { x, y, player } of Object.values(gamedatas.board)) {
                    if (player !== null)
                        this.addTokenOnBoard(x, y, player);
                }

                // make squares discable
                dojo.query('.square').connect('onclick', this, 'onPlayDisc');


                // Setup game notifications to handle (see "setupNotifications" method below)
                this.setupNotifications();

                this.ensureSpecificImageLoading(['../common/point.png']);

                console.log("Ending game setup");
            },


            ///////////////////////////////////////////////////
            //// Game & client states

            // onEnteringState: this method is called each time we are entering into a new game state.
            //                  You can use this method to perform some user interface changes at this moment.
            //
            onEnteringState: function (stateName, args) {
                console.log('Entering state: ' + stateName);

                switch (stateName) {

                    case 'playerTurn':
                        this.updatePossibleMoves(args.args.possibleMoves);
                        break;

                    /* Example:
                    
                    case 'myGameState':
                    
                        // Show some HTML block at this game state
                        dojo.style( 'my_html_block_id', 'display', 'block' );
                        
                        break;
                   */


                    case 'dummmy':
                        break;
                }
            },

            // onLeavingState: this method is called each time we are leaving a game state.
            //                 You can use this method to perform some user interface changes at this moment.
            //
            onLeavingState: function (stateName) {
                console.log('Leaving state: ' + stateName);

                switch (stateName) {

                    /* Example:
                    
                    case 'myGameState':
                    
                        // Hide the HTML block we are displaying only during this game state
                        dojo.style( 'my_html_block_id', 'display', 'none' );
                        
                        break;
                   */


                    case 'dummmy':
                        break;
                }
            },

            // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
            //                        action status bar (ie: the HTML links in the status bar).
            //        
            onUpdateActionButtons: function (stateName, args) {
                console.log('onUpdateActionButtons: ' + stateName);

                if (this.isCurrentPlayerActive()) {
                    switch (stateName) {
                        /*               
                                         Example:
                         
                                         case 'myGameState':
                                            
                                            // Add 3 action buttons in the action status bar:
                                            
                                            this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                                            this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                                            this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                                            break;
                        */
                    }
                }
            },

            ///////////////////////////////////////////////////
            //// Utility methods

            /*
            
                Here, you can defines some utility methods that you can use everywhere in your javascript
                script.
            
            */

            addTokenOnBoard: function (x, y, player) {
                dojo.place(this.format_block('jstpl_token', {
                    x: x,
                    y: y,
                    color: this.gamedatas.players[player].color
                }), 'tokens');

                this.placeOnObject(`token_${x}_${y}`, `overall_player_board_${player}`);
                this.slideToObject(`token_${x}_${y}`, `square_${x}_${y}`).play();
            },

            updatePossibleMoves: function (possibleMoves) {
                // remove current possible moves
                dojo.query('.possibleMove').removeClass('possibleMove');

                for (let [x, y] of possibleMoves) {
                    // x, y is a possible move
                    dojo.addClass(`square_${x}_${y}`, 'possibleMove');
                }
                this.addTooltipToClass('possibleMove', '', _('Place a disc here'));
            },


            ///////////////////////////////////////////////////
            //// Player's action

            /*
            
                Here, you are defining methods to handle player's action (ex: results of mouse click on 
                game objects).
                
                Most of the time, these methods:
                _ check the action is possible at this game state.
                _ make a call to the game server
            
            */

            onPlayDisc: function (e) {
                e.preventDefault();
                dojo.stopEvent(e);

                // square id = square_x_y
                let coords = e.currentTarget.id.split('_');
                let x = coords[1];
                let y = coords[2];

                if (!dojo.hasClass(`square_${x}_${y}`, 'possibleMove'))
                    return;

                if (this.checkAction('playDisc')) {
                    this.ajaxcall("/reversisomeguy/reversisomeguy/playDisc.html", {
                        x: x,
                        y: y,
                        lock: true,
                    }, this, function (result) { });
                }
            },

            ///////////////////////////////////////////////////
            //// Reaction to cometD notifications

            /*
                setupNotifications:
                
                In this method, you associate each of your game notifications with your local method to handle it.
                
                Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                      your reversisomeguy.game.php file.
            
            */
            setupNotifications: function () {
                console.log('notifications subscriptions setup');

                dojo.subscribe("playDisc", this, "notif_playDisc");
                this.notifqueue.setSynchronous("playDisc", 500);

                dojo.subscribe("turnOverDiscs", this, "notif_turnOverDiscs");
                this.notifqueue.setSynchronous("turnOverDiscs", 1500);

                dojo.subscribe("newScores", this, "notif_newScores");
                this.notifqueue.setSynchronous("newScores", 500);
            },

            notif_playDisc: function (notif) {
                dojo.query('.possibleMove').removeClass('possibleMove');
                this.addTokenOnBoard(notif.args.x, notif.args.y, notif.args.player_id);
            },

            notif_turnOverDiscs: function (notif) {
                // get color of disc turner
                const color = this.gamedatas.players[notif.args.player_id].color;

                // make discs blink and set to a specific color
                for (const token of notif.args.turnedOver) {
                    const [x, y] = token;
                    // make token blink 2 times
                    const anim = dojo.fx.chain([
                        dojo.fadeOut({ node: `token_${x}_${y}` }),
                        dojo.fadeIn({ node: `token_${x}_${y}` }),
                        dojo.fadeOut({
                            node: `token_${x}_${y}`,
                            onEnd: function (node) {
                                // remove color
                                dojo.removeClass(node, ['tokencolor_000000', 'tokencolor_ffffff']);
                                dojo.addClass(node, `tokencolor_${color}`);
                            }
                        }),
                        dojo.fadeIn({ node: `token_${x}_${y}` })
                    ]);

                    anim.play();
                }
            },

            notif_newScores: function (notif) {
                for (const { player_id, player_score } of notif.args.scores)
                    this.scoreCtrl[player_id].toValue(player_score);
            }
        });
    });

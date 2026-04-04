<!DOCTYPE html>
<html>

<head>
    <script src="./client.js"></script>
    <style>
        .hidden {
            display: none;
        }
    </style>
</head>

<body>
    <div style="display: flex; flex-direction: row;">
        <div>
            <label for="client_id">Client id:</label>
            <input type="text" name="client_id" id="client_id">
        </div>

        <div>
            <label for="websocket_port">Port:</label>
            <input type="number" name="websocket_port" id="websocket_port" value="1234" min="1" max="65536">
            <button id="connect_to_server" type="button">Connect to Websocket server</button>
        </div>
    </div>

    <div class="client-actions">
        <div>
            <label for="table_id">Table id:</label>
            <input type="text" name="table_id" id="table_id">
            <button id="join_table" type="button">Connect to table</button>
            <button id="quit_table" type="button">Disconnect from table</button>
        </div>

        <div>
            <!--<button id="create_new_table">Create a new table</button> -->
            <button id="list_tables">List tables</button>
            <button id="start_game">Start game</button>
            <button id="get_player_state">Get current state</button>
            <button id="clear_log">Clear button</button>
        </div>
    </div>

    <div class="tables-list"></div>

    <div class="table">
        <div>
            <h1>Pocket cards</h1>
            <div class="cards"></div>
        </div>

        <div>
            <h1>Board cards</h1>
            <div class="board-cards"></div>
        </div>

        <div class="player-actions hidden">
            <label for="betting_amount">Betting amount:</label>
            <input type="number" name="betting_amount" id="betting_amount">

            <button data-action="fold">Fold</button>
            <button data-action="check">Check</button>
            <button data-action="bet">Bet</button>
            <button data-action="call">Call</button>
        </div>

        <div id="message_log"></div>
    </div>
</body>

</html>
<html>

<head>
    <script src="./client.js"></script>
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
        </div>

        <div>
            <!--<button id="create_new_table">Create a new table</button> -->
            <button id="list_tables">List tables</button>
            <button id="start_game">Start game</button>
            <button id="get_player_state">Get current state</button>
            <button id="clear_log">Clear button</button>
        </div>
    </div>

    <div id="message_log"></div>
</body>

</html>
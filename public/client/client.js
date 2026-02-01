class Client {
    /** @var WebSocket */
    #websocket;

    /** @var HTMLElement */
    #log_element;

    /** @var string */
    #user_id;

    constructor(log_element, user_id) {
        this.#log_element = log_element;
        this.#user_id = user_id;
    }

    connect(port) {
        console.log("Connecting to websocket");
        this.#websocket = new WebSocket(`ws://${window.location.hostname}:${port}/`);
        console.log(this.#websocket);

        this.#websocket.onmessage = (event) => {
            this.#log_element.append(event.data);
            console.log(event.data);
        };
    }

    connectToTable(tableId) {
        this.sendJson({ "action": "playerJoin", "table_id": tableId, "user_id": this.#user_id });
    }

    createNewTable() {
        const rules = {
            maxPlayers: 4,
            tableType: "cash_game",
            deckRules: {
                "maxSize": 52,
                "generationType": "automatic",
                "cardGenerationConfig": {
                    "ranks": ["2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K", "A"],
                    "symbols": ["C", "D", "H", "S"]
                }
            },
            phases: [],
        }

        this.sendJson({ "action": "createTable", ...rules });
    }

    getTables() {
        this.sendJson({ "action": "listTables" });
    }

    startGame(tableId) {
        this.sendJson({ "action": "startGame", "table_id": tableId, "user_id": this.#user_id });
    }

    sendJson(data) {
        this.#websocket.send(JSON.stringify(data));
    }
}

function getPort() {
    const port = document.getElementById("websocket_port");
    port.reportValidity();

    if (isNaN(parseInt(port.value))) {
        port.setCustomValidity("You must specify a valid port !");
        port.reportValidity();
        return null;
    }

    return parseInt(port.value);
}

function getClientId() {
    const client_id = document.getElementById("client_id");
    client_id.reportValidity();
    if (client_id.value.length === 0) {
        client_id.setCustomValidity("You must specify a client id !");
        client_id.reportValidity();
        return null;
    }

    return client_id.value;
}

function getTableId() {
    const table_id = document.getElementById("table_id");
    table_id.reportValidity();

    if (table_id.value.length === 0) {
        table_id.setCustomValidity("You must specify a client id !");
        table_id.reportValidity();
        return null;
    }

    return table_id.value;
}

let client = null;
document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("connect_to_server").onclick = () => {
        const port = getPort();
        if (!port) { return; }

        const client_id = getClientId();
        if (!client_id) { return; }

        client = new Client(document.getElementById("message_log"), client_id);
        client.connect(port);
    }

    document.getElementById("join_table").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }
        client.connectToTable(table_id);
    }

    document.getElementById("create_new_table").onclick = () => {
        client.createNewTable();
    }

    document.getElementById("start_game").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }
        client.startGame(table_id);
    }
});
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

        this.#websocket.onopen = (event) => {
            if (event.data) {
                this.#log_element.append(event.data);
                console.log(event.data);
            }

            this.getTables();
            document.dispatchEvent(new CustomEvent("client_connected"));
        }

        this.#websocket.onmessage = (event) => {
            this.#log_element.append(event.data);
            console.log(event.data);
        };

        this.#websocket.onclose = (event) => {
            this.#log_element.append(event.data);
            console.log(event.data);
            document.dispatchEvent(new CustomEvent("client_disconnected"));
        }
    }

    getTables() {
        this.sendJson({ "action": "listTables" });
    }

    connectToTable(tableId) {
        this.sendJson({ "action": "playerJoin", "table_id": tableId, "user_id": this.#user_id });
    }

    startGame(tableId) {
        this.sendJson({ "action": "startGame", "table_id": tableId, "user_id": this.#user_id });
    }

    getState(tableId) {
        this.sendJson({ "action": "playerGetState", "table_id": tableId, "user_id": this.#user_id });
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
    document.querySelectorAll(".client-actions button").forEach(el => {
        el.disabled = true;
    });

    document.getElementById("connect_to_server").onclick = () => {
        const port = getPort();
        if (!port) { return; }

        const client_id = getClientId();
        if (!client_id) { return; }

        client = new Client(document.getElementById("message_log"), client_id);
        client.connect(port);
    }


    document.getElementById("clear_log").onclick = () => {
        document.getElementById("message_log").innerHTML = "";
    }
});

document.addEventListener("client_connected", () => {
    document.querySelectorAll(".client-actions button").forEach(el => {
        el.disabled = false;
    });

    document.getElementById("list_tables").onclick = () => {
        client.getTables();
    }

    document.getElementById("join_table").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }
        client.connectToTable(table_id);
    }

    document.getElementById("start_game").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }
        client.startGame(table_id);
    }

    document.getElementById("get_player_state").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }
        client.getState(table_id);
    }
});

document.addEventListener("client_disconnected", () => {
    document.querySelectorAll(".client-actions button").forEach(el => {
        el.disabled = true;
    });
});
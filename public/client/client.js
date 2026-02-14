class Client {
    /** @var WebSocket */
    #websocket;

    /** @var HTMLElement */
    #log_element;

    /** @var string */
    #user_id;

    /** @var string */
    #table_id;

    #tables = [];

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

            document.dispatchEvent(new CustomEvent("client_message", { detail: JSON.parse(event.data) }));

            if (event.data.tables) {
                this.#tables = event.data.tables;
                return;
            }
        };

        this.#websocket.onclose = (event) => {
            document.dispatchEvent(new CustomEvent("client_disconnected"));
        }
    }

    setTableId(tableId) {
        this.#table_id = tableId;
    }

    getTables() {
        this.sendJson({ "action": "listTables" });
    }

    connectToTable() {
        this.sendJson({ "action": "playerJoin", "table_id": this.#table_id, "user_id": this.#user_id });
    }

    startGame() {
        this.sendJson({ "action": "startGame", "table_id": this.#table_id, "user_id": this.#user_id });
    }

    getState() {
        this.sendJson({ "action": "playerGetState", "table_id": this.#table_id, "user_id": this.#user_id });
    }

    sendPlayerAction(data) {
        this.sendJson({ "action": "playerAction", "table_id": this.#table_id, "user_id": this.#user_id, ...data });
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
    document.querySelectorAll(".client-actions button").forEach(el => { el.disabled = false; });

    document.getElementById("list_tables").onclick = () => { client.getTables(); }

    document.getElementById("join_table").onclick = () => {
        const table_id = getTableId();
        if (!table_id) { return; }

        client.setTableId(table_id);

        client.connectToTable(table_id);
    }

    document.getElementById("start_game").onclick = () => { client.startGame(table_id); }

    document.getElementById("get_player_state").onclick = () => { client.getState(table_id); }
});

document.addEventListener("client_disconnected", () => {
    document.querySelectorAll(".client-actions button").forEach(el => {
        el.disabled = true;
    });
});

document.addEventListener("client_message", (e) => {
    console.log(e.detail)
    if (e.detail.tables) {
        createTableList(e.detail.tables);
    }
});

function createTableList(tables) {
    const table_container = document.querySelector(".tables");
    table_container.innerHTML = "";

    const clear_btn = document.createElement("button");
    clear_btn.innerText = "X";
    clear_btn.onclick = () => {
        table_container.innerHTML = "";
    }
    table_container.append(clear_btn);

    for (const entry of Object.entries(tables)) {
        const table_id = entry[0];

        const container = document.createElement("div");

        const title = document.createElement("h1");
        title.innerText = table_id;
        container.append(title);

        const join_btn = document.createElement("button");
        join_btn.innerText = "Join table";
        join_btn.onclick = () => {
            document.getElementById("table_id").value = table_id;
            client.setTableId(table_id);
            client.connectToTable(table_id);
        }
        container.append(join_btn);
        table_container.append(container);
    }
}
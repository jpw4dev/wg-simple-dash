const documentRoot = document;
const tbodyElement = documentRoot.querySelector("tbody");
const activeConnectionsElement = documentRoot.getElementById("active");
const receivedBytesElement = documentRoot.getElementById("rx");
const sentBytesElement = documentRoot.getElementById("tx");
const monitoringStartElement = documentRoot.getElementById("start-time");
const lastUpdateElement = documentRoot.getElementById("last-update");

monitoringStartElement.textContent = `Monitoring since ${new Date().toLocaleTimeString()}`;

const byteFormatter = new Intl.NumberFormat(undefined, {
  style: "unit",
  unit: "byte",
  notation: "compact",
  unitDisplay: "narrow",
});

function durationFormatter(sec) {
  if (sec == 0)            return `N/A`;
  if (sec < 60)            return `${Math.floor(sec)}s`;
  if (sec < 3600)          return `${Math.floor(sec / 60)}m`;
  if (sec < 86400)         return `${Math.floor(sec / 3600)}h`;
  return `${Math.floor(sec / 86400)}d`;
}

async function fetchData() {
  try {
    const response = await fetch("/api/wireguard");
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    const fragment = documentRoot.createDocumentFragment();

    let activeConnections = 0;
    let totalReceived = 0;
    let totalSent = 0;

    Object.entries(data).forEach(([interfaceName, { peers }]) => {
      peers.forEach((peer) => {
        const secondsSinceHandshake = peer.latest_handshake > 0
          ? Math.floor(Date.now() / 1000 - peer.latest_handshake) : 0;
        const isConnected = secondsSinceHandshake > 0 && secondsSinceHandshake < 300;

        if (isConnected) {
          activeConnections += 1;
          totalReceived += peer.transfer_rx;
          totalSent += peer.transfer_tx;
        }

        const row = documentRoot.createElement("tr");
        row.innerHTML = `
          <td>${interfaceName}</td>
          <td>${peer.peer_name || "Unnamed"}</td>
          <td>${peer.public_key || "Unknown"}</td>
          <td>${peer.endpoint || "—"}</td>
          <td>
            <span class="${isConnected ? "status-active" : "status-idle"}">
                ${isConnected ? "Active" : "Idle"}
              </span>
              <span class="peer-key">(${durationFormatter(secondsSinceHandshake)})</span>
          </td>
          <td>${byteFormatter.format(peer.transfer_rx)}</td>
          <td>${byteFormatter.format(peer.transfer_tx)}</td>`;
        fragment.appendChild(row);
      });
    });

    tbodyElement.replaceChildren(fragment);

    activeConnectionsElement.textContent = activeConnections;
    activeConnectionsElement.classList.remove("status-active", "status-idle");
    activeConnectionsElement.classList.add(activeConnections > 0 ? "status-active" : "status-idle");

    receivedBytesElement.textContent = byteFormatter.format(totalReceived);
    sentBytesElement.textContent = byteFormatter.format(totalSent);

    lastUpdateElement.textContent = `Updated ${new Date().toLocaleTimeString()}`;
  } catch (error) {
    console.error("Dashboard fetch error:", error);
    lastUpdateElement.textContent = "Error";
  } finally {
    setTimeout(fetchData, 10000);
  }
}

fetchData();

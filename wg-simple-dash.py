from flask import Flask, jsonify, send_from_directory
from gunicorn.app.base import Application
import subprocess, os, re, logging, time, threading

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger('wg-export')

CONFIG_DIR = "/wg-config"

BIND = os.getenv("WG_DASH_BIND", "0.0.0.0:8123")
WORKERS = int(os.getenv("WG_DASH_WORKERS", "2"))
KEY_DISPLAY_LEN = int(os.getenv("WG_DASH_KEY_DISPLAY_LEN", "44"))

CACHE_TTL = int(os.getenv("WG_DASH_CACHE_TTL", "5"))
_cache_data = None
_cache_ts   = 0
_cache_lock = threading.Lock()

PEER_NAMES = {}

app = Flask(__name__)

def normalize_public_key(key: str) -> str:
    clean = re.sub(r'\s+', '', key.strip())
    return clean.ljust(44, '=')[:44]

def load_peer_names() -> dict:
    cfg = os.path.join(CONFIG_DIR, 'wg0.conf')

    if not os.path.exists(cfg):
        logger.error("Main config file not found at %s", cfg)
        return {}

    peers = {}

    with open(cfg) as wg_cfg:
        in_peer_block = False
        current_name = None

        for line in wg_cfg:
            line = line.strip()

            if line.startswith("[Peer]"):
                in_peer_block = True
                current_name = None
                continue

            if in_peer_block:
                if line.startswith("#"):
                    current_name = line.lstrip("#").split("_")[1].strip()
                elif line.lower().startswith("publickey"):
                    public_key = normalize_public_key(line.split("=", 1)[1].strip())
                    if current_name:
                        peers[public_key] = current_name
                elif not line or line.startswith("["):
                    in_peer_block = False
                    current_name = None

    logger.info("Loaded %d peers at startup", len(peers))
    return peers

def fetch_wireguard_stats() -> dict:
    global _cache_data, _cache_ts
    with _cache_lock:
        now = time.time()
        if _cache_data is None or (now - _cache_ts) > CACHE_TTL:
            try:
                result = subprocess.run(['wg', 'show', 'all', 'dump'], capture_output=True, text=True, check=True)
                data = {}
                for line in result.stdout.strip().splitlines():
                    parts = line.split('\t')
                    if len(parts) < 9:
                        continue
                    iface, pk = parts[0], normalize_public_key(parts[1])
                    data.setdefault(iface, {'peers': []})['peers'].append({
                        'public_key': parts[1][:KEY_DISPLAY_LEN],
                        'peer_name': PEER_NAMES.get(pk, ''),
                        'endpoint': parts[3] if parts[3] != '(none)' else '',
                        'allowed_ips': parts[4],
                        'latest_handshake': int(parts[5]) if parts[5] != '0' else 0,
                        'transfer_rx': int(parts[6]),
                        'transfer_tx': int(parts[7])
                    })
                _cache_data = data
                _cache_ts   = now
            except Exception as e:
                logger.error("WireGuard fetch failed: %s", e)
                _cache_data = {"error": "Unable to fetch WireGuard stats"}
        return _cache_data

@app.route('/api/wireguard')
def wireguard_api():
    return jsonify(fetch_wireguard_stats())

class StandaloneApplication(Application):
    def __init__(self, app, opts=None):
        self.app = app
        self.opts = opts or {}
        super().__init__()

    def load_config(self):
        for k, v in self.opts.items():
            self.cfg.set(k, v)

    def load(self):
        return self.app

if __name__ == '__main__':
    PEER_NAMES = load_peer_names()
    StandaloneApplication(
        app,
        {'bind': BIND, 'workers': WORKERS}
    ).run()

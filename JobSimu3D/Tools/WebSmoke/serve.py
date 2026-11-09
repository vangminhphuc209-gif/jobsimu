"""Local-only Unity QA server. Does not expose the repository or alter PHP hosting."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
import argparse
import os

parser = argparse.ArgumentParser()
parser.add_argument("--port", type=int, default=8765)
args = parser.parse_args()
build = Path(__file__).resolve().parents[2] / "Builds" / "TeacherWeb"
if not (build / "index.html").is_file():
    raise SystemExit("Builds/TeacherWeb/index.html does not exist. Build the Unity Web demo first.")
os.chdir(build)

class Handler(SimpleHTTPRequestHandler):
    extensions_map = {**SimpleHTTPRequestHandler.extensions_map, ".wasm": "application/wasm", ".data": "application/octet-stream", ".js": "application/javascript"}
    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        super().end_headers()

print(f"Serving {build} at http://127.0.0.1:{args.port}", flush=True)
ThreadingHTTPServer(("127.0.0.1", args.port), Handler).serve_forever()

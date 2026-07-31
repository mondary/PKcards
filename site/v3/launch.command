#!/bin/bash
# PKTravelGames3 — serveur local. Double-clic dans Finder pour lancer.
# Stoppe avec Ctrl-C ou en fermant la fenêtre du Terminal.
cd "$(dirname "$0")" || exit 1
export PATH="$HOME/Library/Application Support/Herd/bin:/opt/homebrew/bin:/usr/local/bin:$PATH"
PORT=8765
if ! command -v php >/dev/null 2>&1; then
  echo "❌ php introuvable. Installe Herd (https://herd.laravel.com) ou PHP."
  read -n 1 -s -r -p "Appuie sur une touche pour fermer…"
  exit 1
fi
echo "🃏 PKTravelGames3 → http://localhost:$PORT  (Ctrl-C pour stopper)"
( sleep 1; open "http://localhost:$PORT" ) &
php -S "localhost:$PORT"

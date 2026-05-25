#!/bin/bash
export PORT="${PORT:-8080}"
exec php -S 0.0.0.0:$PORT -t .

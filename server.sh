#!/bin/bash
SVR=127.0.0.1:5888
echo "listen on $SVR"
php -S $SVR -t web > server.log

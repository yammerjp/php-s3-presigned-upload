#!/bin/bash

$( cat .env | xargs -I{} echo 'export {}' )

echo "open http://localhost:3333/index.html"
php -S 0.0.0.0:3333


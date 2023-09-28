#!/bin/bash

$( cat .env | xargs -I{} echo 'export {}' )

echo "open http://localhost:3333/index.html"
php -S localhost:3333


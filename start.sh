#!/bin/bash

# Export environment variables for envsubst
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

# Replace environment variables in .env file
envsubst < .env > .env.tmp && mv .env.tmp .env

# Start Apache
apache2-foreground

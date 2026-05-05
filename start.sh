#!/bin/bash

# Replace environment variables in .env file
envsubst < .env > .env.tmp && mv .env.tmp .env

# Start Apache
apache2-foreground

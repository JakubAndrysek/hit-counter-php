# Makefile for setting up the project and running the PHP server

# Install composer dependencies
install:
	composer install --working-dir=app

# Test run the PHP script
run-test:
	php app/index.php

# Start a PHP development server
serve:
	php -S localhost:8000 -t app

# Setup the database by running the SQL script on MariaDB
setup-db:
	mysql -u root -p < app/create_tables.sql

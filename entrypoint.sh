#!/bin/sh
cd /app

mkdir -p /var/log/ && chmod 777 /var/log/

echo "Private key path: " $AUTH_PRIVATE_KEY_PATH
echo "Public key path: " $AUTH_PUBLIC_KEY_PATH

# If key file does not exist in path, generate it
if [ -f "$AUTH_PRIVATE_KEY_PATH" ]; then
  echo "Private key exist."
else
  echo "Private key does not exist generating a new one..."
  
  mkdir ./generated_keys

  php bin/console app:generate-keys ./generated_keys

  PRIVATE_KEY_DIR=$(dirname "$AUTH_PRIVATE_KEY_PATH")

  # Check if private key path is writeable
  if [ ! -d "$PRIVATE_KEY_DIR" ]; then
    echo "Private key path does not exist, creating..."
    mkdir -p "$PRIVATE_KEY_DIR"
  fi

  # Move keys to the correct path
  mv ./generated_keys/key.private "$AUTH_PRIVATE_KEY_PATH"

  PUBLIC_KEY_DIR=$(dirname "$AUTH_PUBLIC_KEY_PATH")

  if [ ! -d "$PUBLIC_KEY_DIR" ]; then
    echo "Public key path does not exist, creating..."
    mkdir -p "$PUBLIC_KEY_DIR"
  fi

  # We need to move the new public key too
  mv ./generated_keys/key.pub "$AUTH_PUBLIC_KEY_PATH"
fi

## check for DB before trying to setup db
echo "Checking database status."
until nc -z -v -w30 "$DATABASE_HOST" "$DATABASE_PORT"
do
  echo "Waiting for database connection..."
  # wait for 5 seconds before check again
  sleep 5
done

# We wait a little random time (1-10 seconds) to defer migration in case multiple containers are trying simultaneously
RANDOM_SLEEP=$(shuf -i 1-10 -n 1)
sleep $RANDOM_SLEEP

## make sure the db is set up
echo -e "Migrating and Seeding D.B"
php bin/console doctrine:migrations:migrate --no-interaction

echo -e "Starting app..."
exec "$@"
#!/bin/bash

# Generate OAuth Keys for Laravel Passport
# This script generates the private and public keys for OAuth2

STORAGE_PATH="/var/www/html/storage/oauth-keys"
PRIVATE_KEY_PATH="$STORAGE_PATH/oauth-private.key"
PUBLIC_KEY_PATH="$STORAGE_PATH/oauth-public.key"

echo "Generating OAuth2 keys for Laravel Passport..."

# Create the directory if it doesn't exist
mkdir -p $STORAGE_PATH

# Generate private key
openssl genpkey -algorithm RSA -out $PRIVATE_KEY_PATH -pkcs8 -aes256 -pass pass:secret

# Extract public key from private key
openssl pkey -in $PRIVATE_KEY_PATH -passin pass:secret -pubout -out $PUBLIC_KEY_PATH

# Set proper permissions
chmod 600 $PRIVATE_KEY_PATH
chmod 644 $PUBLIC_KEY_PATH

# Set ownership to the application user
chown www-data:www-data $PRIVATE_KEY_PATH $PUBLIC_KEY_PATH

echo "OAuth2 keys generated successfully:"
echo "Private key: $PRIVATE_KEY_PATH"
echo "Public key: $PUBLIC_KEY_PATH"

# Show key files
ls -la $STORAGE_PATH/

echo "Keys generation completed!"

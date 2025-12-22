#!/bin/bash
# Generate SSL Certificates for Development
# This script creates self-signed SSL certificates for localhost

echo "Generating SSL Certificates for Secure Web Application..."
echo

# Create SSL directory if it doesn't exist
mkdir -p ssl
cd ssl

# Generate private key for CA
echo "Generating CA private key..."
openssl genrsa -out ca.key 2048

# Generate CA certificate
echo "Generating CA certificate..."
openssl req -new -x509 -days 365 -key ca.key -out ca.crt -subj "/CN=SecureWebApp CA/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

# Generate server private key
echo "Generating server private key..."
openssl genrsa -out server.local.key 2048

# Generate server certificate signing request
echo "Generating server certificate signing request..."
openssl req -new -key server.local.key -out server.local.csr -subj "/CN=localhost/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

# Sign server certificate with CA
echo "Signing server certificate..."
openssl x509 -req -days 365 -in server.local.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.local.crt

# Generate client private key
echo "Generating client private key..."
openssl genrsa -out client.local.key 2048

# Generate client certificate signing request
echo "Generating client certificate signing request..."
openssl req -new -key client.local.key -out client.local.csr -subj "/CN=client.local/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

# Sign client certificate with CA
echo "Signing client certificate..."
openssl x509 -req -days 365 -in client.local.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out client.local.crt

# Clean up CSR files
rm -f *.csr

echo
echo "SSL Certificates generated successfully!"
echo
echo "Files created:"
echo "  - ca.crt (Certificate Authority)"
echo "  - ca.key (CA Private Key)"
echo "  - server.local.crt (Server Certificate)"
echo "  - server.local.key (Server Private Key)"
echo "  - client.local.crt (Client Certificate)"
echo "  - client.local.key (Client Private Key)"
echo
echo "Note: These are self-signed certificates for development only."
echo "      For production, use certificates from a trusted CA."
echo

cd ..
chmod 600 ssl/*.key
chmod 644 ssl/*.crt


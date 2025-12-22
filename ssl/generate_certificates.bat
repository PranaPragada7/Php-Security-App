@echo off
REM Generate SSL Certificates for Development
REM This script creates self-signed SSL certificates for localhost

echo Generating SSL Certificates for Secure Web Application...
echo.

REM Set OpenSSL path (XAMPP installation)
set OPENSSL=C:\xampp\apache\bin\openssl.exe
set OPENSSL_CONF=C:\xampp\apache\conf\openssl.cnf

REM Check if OpenSSL exists
if not exist "%OPENSSL%" (
    echo ERROR: OpenSSL not found at %OPENSSL%
    echo Please check your XAMPP installation path.
    pause
    exit /b 1
)

REM Check if OpenSSL config exists
if not exist "%OPENSSL_CONF%" (
    echo ERROR: OpenSSL config not found at %OPENSSL_CONF%
    echo Please check your XAMPP installation path.
    pause
    exit /b 1
)

REM Create SSL directory if it doesn't exist
if not exist "ssl" mkdir ssl
cd ssl

REM Generate private key for CA
echo Generating CA private key...
"%OPENSSL%" genrsa -out ca.key 2048

REM Generate CA certificate
echo Generating CA certificate...
"%OPENSSL%" req -new -x509 -days 365 -key ca.key -out ca.crt -subj "/CN=SecureWebApp CA/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

REM Generate server private key
echo Generating server private key...
"%OPENSSL%" genrsa -out server.local.key 2048

REM Generate server certificate signing request
echo Generating server certificate signing request...
"%OPENSSL%" req -new -key server.local.key -out server.local.csr -subj "/CN=localhost/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

REM Sign server certificate with CA
echo Signing server certificate...
"%OPENSSL%" x509 -req -days 365 -in server.local.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.local.crt

REM Generate client private key
echo Generating client private key...
"%OPENSSL%" genrsa -out client.local.key 2048

REM Generate client certificate signing request
echo Generating client certificate signing request...
"%OPENSSL%" req -new -key client.local.key -out client.local.csr -subj "/CN=client.local/C=US/ST=IN/L=West Lafayette/O=Purdue University/OU=ITS"

REM Sign client certificate with CA
echo Signing client certificate...
"%OPENSSL%" x509 -req -days 365 -in client.local.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out client.local.crt

REM Clean up CSR files (if they exist)
if exist *.csr del *.csr 2>nul

echo.
echo SSL Certificates generated successfully!
echo.
echo Files created:
echo   - ca.crt (Certificate Authority)
echo   - ca.key (CA Private Key)
echo   - server.local.crt (Server Certificate)
echo   - server.local.key (Server Private Key)
echo   - client.local.crt (Client Certificate)
echo   - client.local.key (Client Private Key)
echo.
echo Note: These are self-signed certificates for development only.
echo       For production, use certificates from a trusted CA.
echo.

cd ..
pause


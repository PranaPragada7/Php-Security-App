@echo off
REM Generate SSL Certificates for Development with SAN (Subject Alternative Name)
REM This is required for Chrome to show the Secure Lock

echo Generating SSL Certificates with SAN...
echo.

REM Set OpenSSL path (XAMPP installation)
set OPENSSL=C:\xampp\apache\bin\openssl.exe
set OPENSSL_CONF=C:\xampp\apache\conf\openssl.cnf

if not exist "%OPENSSL%" (
    echo ERROR: OpenSSL not found. Check path.
    pause
    exit /b 1
)

if not exist "ssl" mkdir ssl
cd ssl

REM 1. Generate CA Key and Certificate
echo 1. Generating CA...
"%OPENSSL%" genrsa -out ca.key 2048
"%OPENSSL%" req -x509 -new -nodes -key ca.key -sha256 -days 1024 -out ca.crt -subj "/CN=SecureWebApp Root CA/C=US/ST=IN/L=West Lafayette/O=Purdue"

REM 2. Create a config file for the Server Certificate with SAN
echo 2. Creating Config with SAN...
(
echo authorityKeyIdentifier=keyid,issuer
echo basicConstraints=CA:FALSE
echo keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
echo subjectAltName = @alt_names
echo [alt_names]
echo DNS.1 = localhost
echo IP.1 = 127.0.0.1
) > v3.ext

REM 3. Generate Server Key and CSR
echo 3. Generating Server Key...
"%OPENSSL%" genrsa -out server.local.key 2048
"%OPENSSL%" req -new -key server.local.key -out server.local.csr -subj "/CN=localhost/C=US/ST=IN/L=West Lafayette/O=Purdue"

REM 4. Sign the Server Certificate using the CA and the SAN Config
echo 4. Signing Server Certificate...
"%OPENSSL%" x509 -req -in server.local.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out server.local.crt -days 500 -sha256 -extfile v3.ext

REM Cleanup
if exist v3.ext del v3.ext
if exist *.csr del *.csr

echo.
echo ========================================================
echo   SUCCESS! New Certificates Generated.
echo ========================================================
echo.
echo   YOU MUST NOW INSTALL "ssl\ca.crt" AGAIN!
echo.
cd ..
pause


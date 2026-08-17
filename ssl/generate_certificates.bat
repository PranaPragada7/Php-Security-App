@echo off
setlocal
set "SCRIPT_DIR=%~dp0"

where openssl >nul 2>nul || (
  echo OpenSSL is required and was not found on PATH.
  exit /b 1
)

openssl req -x509 -newkey rsa:3072 -sha256 -nodes ^
  -keyout "%SCRIPT_DIR%server.local.key" ^
  -out "%SCRIPT_DIR%server.local.crt" ^
  -days 365 ^
  -subj "/CN=localhost/O=Local Development" ^
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" ^
  -addext "keyUsage=digitalSignature,keyEncipherment" ^
  -addext "extendedKeyUsage=serverAuth"

if errorlevel 1 exit /b 1
echo Created a localhost development certificate in ssl\.


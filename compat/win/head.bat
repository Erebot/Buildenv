@echo off
for /f "delims=" %%a in ('type %~f1') do (
echo %%a
exit /b
)


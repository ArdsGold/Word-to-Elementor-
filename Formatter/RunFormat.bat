:: 1. Generate the region-proof timestamp filename
@echo off
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value ^| findstr "LocalDateTime"') do set datetime=%%I
set year=%datetime:~0,4%
set month=%datetime:~4,2%
set day=%datetime:~6,2%
set hour=%datetime:~8,2%
set minute=%datetime:~10,2%

set filename=%year%-%month%-%day%_%hour%-%minute%.txt

:: 2. Run your command and export the WHOLE output to the file
:: (Replace "ipconfig /all" with your actual command)
python .\format_docx_headings.py > .\logs\"%filename%" 2>&1

echo Command output completely exported to %filename%
pause
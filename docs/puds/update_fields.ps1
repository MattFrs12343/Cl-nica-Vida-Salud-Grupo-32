$ErrorActionPreference = "Stop"
$base = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds"
$docx = Join-Path $base "PUDS-UML_Clinica_Vida_Salud_Grupo-32.docx"
$log = "C:\Users\HP\AppData\Local\Temp\opencode\word_update.log"
Remove-Item $log -ErrorAction SilentlyContinue
"start $(Get-Date -Format o)" | Out-File $log -Encoding utf8

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {
    "app $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document = $word.Documents.Open($docx, $false, $false)
    "opened $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.Fields.Update() | Out-Null
    "fields $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.TablesOfContents.Item(1).Update() | Out-Null
    "toc $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.Repaginate()
    $document.Save()
    "saved $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.Close($false)
    "closed $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
finally {
    $word.Quit()
    "quit $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
Get-Content $log

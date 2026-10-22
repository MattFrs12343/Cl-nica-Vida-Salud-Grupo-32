$ErrorActionPreference = "Stop"
$base = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds"
$docx = Join-Path $base "PUDS-UML_Clinica_Vida_Salud_Grupo-32.docx"
$doc = Join-Path $base "PUDS-UML_Clinica_Vida_Salud_Grupo-32.doc"
$pdf = Join-Path $base "PUDS-UML_Clinica_Vida_Salud_Grupo-32.pdf"
$tmp = "C:\Users\HP\AppData\Local\Temp\opencode"
$tmpPdf = Join-Path $tmp "PUDS-UML_Clinica_Vida_Salud_Grupo-32.pdf"
$tmpDoc = Join-Path $tmp "PUDS-UML_Clinica_Vida_Salud_Grupo-32.doc"
$log = Join-Path $tmp "word_export.log"
Remove-Item $log -ErrorAction SilentlyContinue
Remove-Item $tmpPdf -ErrorAction SilentlyContinue
Remove-Item $tmpDoc -ErrorAction SilentlyContinue
"start $(Get-Date -Format o)" | Out-File $log -Encoding utf8

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
try {
    $document = $word.Documents.Open($docx, $false, $true)
    "opened pdf $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.ExportAsFixedFormat($tmpPdf, 17, $false, 0, 0, 0, 0, 0, $true, $true, 0, $true, $true, $false)
    "pdf $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.Close($false)
    "closed pdf $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8

    $word.Options.DisableFeaturesbyDefault = $true
    $document2 = $word.Documents.Open($docx, $false, $false)
    "opened doc $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document2.SaveAs2($tmpDoc, 0) | Out-Null
    "doc $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $pages = $document2.ComputeStatistics(2)
    "pages $pages" | Out-File $log -Append -Encoding utf8
    $document2.Close($false)
    "closed doc $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
finally {
    $word.Quit()
    "quit $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
Copy-Item -LiteralPath $tmpPdf -Destination $pdf -Force
Copy-Item -LiteralPath $tmpDoc -Destination $doc -Force
"copied $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
Get-Content $log

$ErrorActionPreference = "Stop"
$base = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds"
$docx = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds\PUDS-UML_Clinica_Vida_Salud_Grupo-32.docx"
$doc = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds\PUDS-UML_Clinica_Vida_Salud_Grupo-32.doc"
$pdf = "C:\Users\HP\OneDrive\Desktop\UNIVERSIDAD\Cl-nica-Vida-Salud-Grupo-32\docs\puds\PUDS-UML_Clinica_Vida_Salud_Grupo-32.pdf"
$tmpPdf = "C:\Users\HP\AppData\Local\Temp\opencode\PUDS-UML_Clinica_Vida_Salud_Grupo-32.pdf"
$tmpDoc = "C:\Users\HP\AppData\Local\Temp\opencode\PUDS-UML_Clinica_Vida_Salud_Grupo-32.doc"
$log = "C:\Users\HP\AppData\Local\Temp\opencode\word_export.log"
Remove-Item $log -ErrorAction SilentlyContinue
Remove-Item $tmpPdf -ErrorAction SilentlyContinue
Remove-Item $tmpDoc -ErrorAction SilentlyContinue
"start $(Get-Date -Format o)" | Out-File $log -Encoding utf8

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$word.Options.UpdateFieldsAtPrint = $false
$word.Options.UpdateLinksAtPrint = $false
$word.Options.PrintBackground = $false
try {
    $document = $word.Documents.Open($docx, $false, $true)
    "opened $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $document.SaveAs2($tmpPdf, 17) | Out-Null
    "pdf $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $word.Options.DisableFeaturesbyDefault = $true
    $document.SaveAs2($tmpDoc, 0) | Out-Null
    "doc $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
    $pages = $document.ComputeStatistics(2)
    "pages $pages" | Out-File $log -Append -Encoding utf8
    $document.Close($false)
    "closed $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
finally {
    $word.Quit()
    "quit $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
}
Copy-Item -LiteralPath $tmpPdf -Destination $pdf -Force
Copy-Item -LiteralPath $tmpDoc -Destination $doc -Force
"copied $(Get-Date -Format o)" | Out-File $log -Append -Encoding utf8
Get-Content $log

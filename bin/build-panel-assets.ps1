#requires -Version 5.1
<#
.SYNOPSIS
    Reproduce los assets del panel desde Gentelella v4.

.DESCRIPTION
    Usa el paquete npm publicado de Gentelella (incluye dist/ ya compilado)
    y copia el CSS y las imagenes a public/assets/panel.

    No requiere node_modules ni ejecutar Vite. Si se modifica el SCSS de
    Gentelella, compilar desde la fuente y ajustar este script.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File bin/build-panel-assets.ps1
#>

$ErrorActionPreference = 'Stop'

$version = '4.2.0'
$root = Split-Path -Parent $PSScriptRoot
$panel = Join-Path $root 'public\assets\panel'
$cssDir = Join-Path $panel 'css'
$imgDir = Join-Path $panel 'images'
$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('vidasalud-gentelella-' + [guid]::NewGuid().ToString('N'))

Write-Host "Gentelella $version -> $panel"

try {
    New-Item -ItemType Directory -Force -Path $temp, $cssDir, $imgDir | Out-Null

    Push-Location $temp
    try {
        npm pack "gentelella@$version" --silent | Out-Null

        $tarball = Get-ChildItem -LiteralPath $temp -Filter 'gentelella-*.tgz' | Select-Object -First 1

        if ($null -eq $tarball) {
            throw 'No se pudo descargar el paquete de Gentelella.'
        }

        $extract = Join-Path $temp 'extract'
        New-Item -ItemType Directory -Force -Path $extract | Out-Null
        tar -xzf $tarball.FullName -C $extract

        $dist = Join-Path $extract 'package\dist'

        if (-not (Test-Path -LiteralPath $dist)) {
            throw 'El paquete no contiene dist/.'
        }

        $css = Get-ChildItem -LiteralPath (Join-Path $dist 'assets') -Filter 'main-v4-*.css' | Select-Object -First 1

        if ($null -eq $css) {
            throw 'No se encontro el CSS compilado de Gentelella.'
        }

        Copy-Item -LiteralPath $css.FullName -Destination (Join-Path $cssDir 'gentelella.css') -Force
        Copy-Item -Path (Join-Path $dist 'images\*') -Destination $imgDir -Recurse -Force

        Write-Host "OK  $($css.Name) -> css/gentelella.css"
        Write-Host "OK  images/* -> images/"
    }
    finally {
        Pop-Location
    }
}
finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
    }
}

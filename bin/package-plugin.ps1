# Empaqueta vitacare-crm como ZIP instalable en WordPress (Plugins → Subir).
# Uso (desde la raíz del repo):
#   powershell -ExecutionPolicy Bypass -File .\bin\package-plugin.ps1
#
# No incluye .git, dist previos ni este script empaquetando basura del SO.

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$pluginSlug = "vitacare-crm"
$distDir = Join-Path $root "dist"
$stageDir = Join-Path $distDir $pluginSlug
$zipPath = Join-Path $distDir "$pluginSlug.zip"

if (Test-Path $distDir) {
    Remove-Item -Recurse -Force $distDir
}
New-Item -ItemType Directory -Force -Path $stageDir | Out-Null

$excludeDirs = @(".git", "dist", ".idea", ".vscode", "tests")
$excludeFiles = @(".gitignore")

Get-ChildItem -Path $root -Force | ForEach-Object {
    $name = $_.Name
    if ($excludeDirs -contains $name) { return }
    if ($excludeFiles -contains $name) { return }
    if ($name -eq "bin") {
        # Incluir bin/ opcionalmente no es necesario en WP; se omite del ZIP de producción
        return
    }
    Copy-Item -Path $_.FullName -Destination (Join-Path $stageDir $name) -Recurse -Force
}

if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath
}

Compress-Archive -Path $stageDir -DestinationPath $zipPath -Force
Write-Host "OK: $zipPath"
Write-Host "Instalar en WordPress: Plugins → Añadir nuevo → Subir plugin → activar."
Write-Host "Panel: https://vitacareec.org/crm (no modifica la raíz ni el sistema instalado)."

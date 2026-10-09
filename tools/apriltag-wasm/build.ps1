param(
    [string]$EmsdkEnv = 'C:\Users\santi\emsdk\emsdk_env.bat'
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$includeRoot = Join-Path $repoRoot 'vendor\apriltag\include'
$commonIncludeRoot = Join-Path $includeRoot 'common'
$sourceRoot = Join-Path $repoRoot 'vendor\apriltag\src'
$wrapper = Join-Path $PSScriptRoot 'aprism_apriltag_detector.c'
$outputRoot = Join-Path $repoRoot 'assets\vendor\apriltag'
$outputJs = Join-Path $outputRoot 'aprism_apriltag_detector.js'
$sourceFiles = @(
    (Join-Path $sourceRoot 'apriltag.c'),
    (Join-Path $sourceRoot 'apriltag_quad_thresh.c'),
    (Join-Path $sourceRoot 'tagStandard52h13.c'),
    (Join-Path $sourceRoot 'g2d.c'),
    (Join-Path $sourceRoot 'homography.c'),
    (Join-Path $sourceRoot 'image_u8.c'),
    (Join-Path $sourceRoot 'image_u8_parallel.c'),
    (Join-Path $sourceRoot 'image_u8x3.c'),
    (Join-Path $sourceRoot 'matd.c'),
    (Join-Path $sourceRoot 'pnm.c'),
    (Join-Path $sourceRoot 'string_util.c'),
    (Join-Path $sourceRoot 'svd22.c'),
    (Join-Path $sourceRoot 'time_util.c'),
    (Join-Path $sourceRoot 'unionfind.c'),
    (Join-Path $sourceRoot 'workerpool.c'),
    (Join-Path $sourceRoot 'zarray.c'),
    (Join-Path $sourceRoot 'zhash.c'),
    (Join-Path $sourceRoot 'zmaxheap.c'),
    $wrapper
)

if (-not (Test-Path -LiteralPath $EmsdkEnv)) {
    throw "Emscripten environment script not found: $EmsdkEnv"
}

New-Item -ItemType Directory -Force -Path $outputRoot | Out-Null
$sourceArgs = ($sourceFiles | ForEach-Object { '"' + $_ + '"' }) -join ' '
$includeArg = '"' + $includeRoot + '"'
$outputArg = '"' + $outputJs + '"'
$exportFunctions = '["_malloc","_free","_aprism_detector_init","_aprism_detector_destroy","_aprism_detector_set_image","_aprism_detector_detect"]'
$runtimeMethods = '["UTF8ToString","HEAPU8"]'
$commonIncludeArg = '"' + $commonIncludeRoot + '"'
$command = 'call "' + $EmsdkEnv + '" >nul && emcc -O3 -std=c99 -D_DEFAULT_SOURCE -D_GNU_SOURCE -I' + $includeArg + ' -I' + $commonIncludeArg + ' ' + $sourceArgs + ' -s MODULARIZE=1 -s EXPORT_NAME=AprismAprilTagModule -s ENVIRONMENT=web,worker,node -s ALLOW_MEMORY_GROWTH=1 -s EXPORTED_FUNCTIONS="' + $exportFunctions + '" -s EXPORTED_RUNTIME_METHODS="' + $runtimeMethods + '" -s FILESYSTEM=0 -o ' + $outputArg
cmd /c $command
if ($LASTEXITCODE -ne 0) {
    throw "Emscripten build failed with exit code $LASTEXITCODE."
}

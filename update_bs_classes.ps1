$files = Get-ChildItem -Path "admin/view/htm", "install/view/htm", "view/htm" -Filter "*.htm" -Recurse
foreach ($file in $files) {
    if ($file.Name -like "*header.inc.htm*") { continue }
    
    $content = Get-Content -Path $file.FullName -Raw
    $original = $content
    
    $content = $content -replace 'class="form-group row mb-3"', 'class="row mb-3"'
    $content = $content -replace 'class="form-group row"', 'class="row mb-3"'
    $content = $content -replace 'class="form-group"', 'class="mb-3"'
    $content = $content -replace '\bbtn-block\b', 'd-block w-100'
    
    if ($content -ne $original) {
        Write-Output "Updating $($file.FullName)"
        Set-Content -Path $file.FullName -Value $content -NoNewline
    }
}
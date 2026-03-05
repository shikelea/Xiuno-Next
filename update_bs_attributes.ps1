$files = Get-ChildItem -Path "admin/view/htm", "install/view/htm", "view/htm" -Filter "*.htm" -Recurse
foreach ($file in $files) {
    
    $content = Get-Content -Path $file.FullName -Raw
    $original = $content
    
    $content = $content -replace 'data-toggle="', 'data-bs-toggle="'
    $content = $content -replace 'data-target="', 'data-bs-target="'
    $content = $content -replace 'data-dismiss="', 'data-bs-dismiss="'
    
    if ($content -ne $original) {
        Write-Output "Updating attributes in $($file.FullName)"
        Set-Content -Path $file.FullName -Value $content -NoNewline
    }
}
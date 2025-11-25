# Add examples to DepartmentController, OrganizationController, ProjectController, TeamController
# This will add realistic examples to request bodies and responses

$updates = @{
    "DepartmentController.php" = @{
        patterns = @(
            @{find='@OA\\Property\(property="name", type="string"\)'; replace='@OA\\Property(property="name", type="string", example="Engineering Department")'},
            @{find='@OA\\Property\(property="description", type="string"\)'; replace='@OA\\Property(property="description", type="string", example="Handles all engineering projects and development")'},
            @{find='@OA\\Property\(property="id", type="integer"\)'; replace='@OA\\Property(property="id", type="integer", example=1)'},
            @{find='@OA\\Property\(property="parent_id", type="integer"\)'; replace='@OA\\Property(property="parent_id", type="integer", example=null)'},
            @{find='@OA\\Property\(property="manager_id", type="integer"\)'; replace='@OA\\Property(property="manager_id", type="integer", example=5)'}
        )
    }
    "OrganizationController.php" = @{
        patterns = @(
            @{find='@OA\\Property\(property="name", type="string"\)'; replace='@OA\\Property(property="name", type="string", example="Acme Corporation")'},
            @{find='@OA\\Property\(property="description", type="string"\)'; replace='@OA\\Property(property="description", type="string", example="Leading technology solutions provider")'},
            @{find='@OA\\Property\(property="id", type="integer"\)'; replace='@OA\\Property(property="id", type="integer", example=1)'},
            @{find='@OA\\Property\(property="slug", type="string"\)'; replace='@OA\\Property(property="slug", type="string", example="acme-corp")'}
        )
    }
    "ProjectController.php" = @{
        patterns = @(
            @{find='@OA\\Property\(property="name", type="string"\)'; replace='@OA\\Property(property="name", type="string", example="Website Redesign Project")'},
            @{find='@OA\\Property\(property="description", type="string"\)'; replace='@OA\\Property(property="description", type="string", example="Complete overhaul of company website")'},
            @{find='@OA\\Property\(property="id", type="integer"\)'; replace='@OA\\Property(property="id", type="integer", example=1)'},
            @{find='@OA\\Property\(property="status", type="string"\)'; replace='@OA\\Property(property="status", type="string", example="in_progress")'},
            @{find='@OA\\Property\(property="priority", type="string"\)'; replace='@OA\\Property(property="priority", type="string", example="high")'}
        )
    }
    "TeamController.php" = @{
        patterns = @(
            @{find='@OA\\Property\(property="name", type="string"\)'; replace='@OA\\Property(property="name", type="string", example="Development Team Alpha")'},
            @{find='@OA\\Property\(property="description", type="string"\)'; replace='@OA\\Property(property="description", type="string", example="Frontend development specialists")'},
            @{find='@OA\\Property\(property="id", type="integer"\)'; replace='@OA\\Property(property="id", type="integer", example=1)'},
            @{find='@OA\\Property\(property="team_lead_id", type="integer"\)'; replace='@OA\\Property(property="team_lead_id", type="integer", example=3)'}
        )
    }
}

foreach ($file in $updates.Keys) {
    $path = "app\Http\Controllers\Api\V1\$file"
    if (Test-Path $path) {
        $content = Get-Content $path -Raw
        foreach ($pattern in $updates[$file].patterns) {
            $content = $content -replace [regex]::Escape($pattern.find), $pattern.replace
        }
        Set-Content -Path $path -Value $content -NoNewline
        Write-Host "Updated $file"
    }
}

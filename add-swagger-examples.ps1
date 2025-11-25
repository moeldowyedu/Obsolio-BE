# Script to add example attributes to Swagger annotations
# This script adds example="value" to properties that don't have examples

$controllers = @(
    'BranchController.php',
    'DepartmentController.php',
    'OrganizationController.php',
    'ProjectController.php',
    'TeamController.php',
    'EngineController.php',
    'AgentController.php',
    'AgentExecutionController.php',
    'JobFlowController.php',
    'HITLApprovalController.php',
    'MarketplaceController.php',
    'ConnectedAppController.php',
    'RoleController.php',
    'PermissionController.php',
    'DashboardController.php',
    'TenantController.php',
    'UserActivityController.php'
)

foreach ($file in $controllers) {
    $path = "app\Http\Controllers\Api\V1\$file"
    if (Test-Path $path) {
        $content = Get-Content $path -Raw
        
        # Add examples to common properties
        $content = $content -replace '(@OA\\Property\(property="id", type="integer")(\))', '$1, example=1$2'
        $content = $content -replace '(@OA\\Property\(property="name", type="string")(\))', '$1, example="Example Name"$2'
        $content = $content -replace '(@OA\\Property\(property="description", type="string")(\))', '$1, example="Example description"$2'
        $content = $content -replace '(@OA\\Property\(property="status", type="string")(\))', '$1, example="active"$2'
        $content = $content -replace '(@OA\\Property\(property="success", type="boolean")(\))', '$1, example=true$2'
        $content = $content -replace '(@OA\\Property\(property="message", type="string")(\))', '$1, example="Operation successful"$2'
        $content = $content -replace '(@OA\\Property\(property="created_at", type="string", format="date-time")(\))', '$1, example="2024-01-15T10:30:00Z"$2'
        $content = $content -replace '(@OA\\Property\(property="updated_at", type="string", format="date-time")(\))', '$1, example="2024-01-15T10:30:00Z"$2'
        
        Set-Content -Path $path -Value $content -NoNewline
        Write-Host "Updated $file"
    }
}

Write-Host "`nCompleted adding examples to $($controllers.Count) controllers"

#!/bin/bash

# API Endpoint Testing Script
# Tests all API v1 endpoints and generates a report

BASE_URL="http://localhost:8000/api/v1"
TOKEN=""
RESULTS_FILE="/tmp/api_test_results.txt"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Clear results file
> "$RESULTS_FILE"

echo "=========================================="
echo "API Endpoint Testing Report"
echo "=========================================="
echo ""

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local needs_auth=$3
    local data=$4

    local url="${BASE_URL}${endpoint}"
    local status_code

    if [ "$needs_auth" = "true" ]; then
        if [ -z "$data" ]; then
            status_code=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url" \
                -H "Authorization: Bearer $TOKEN" \
                -H "Accept: application/json")
        else
            status_code=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url" \
                -H "Authorization: Bearer $TOKEN" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "$data")
        fi
    else
        if [ -z "$data" ]; then
            status_code=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url" \
                -H "Accept: application/json")
        else
            status_code=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "$data")
        fi
    fi

    # Determine result
    if [ "$status_code" = "200" ] || [ "$status_code" = "201" ]; then
        echo -e "${GREEN}✓${NC} $method $endpoint - $status_code"
        echo "PASS: $method $endpoint - $status_code" >> "$RESULTS_FILE"
    elif [ "$status_code" = "401" ] && [ "$needs_auth" = "true" ]; then
        echo -e "${YELLOW}⚠${NC} $method $endpoint - $status_code (Auth Required)"
        echo "WARN: $method $endpoint - $status_code (Auth Required)" >> "$RESULTS_FILE"
    elif [ "$status_code" = "404" ]; then
        echo -e "${YELLOW}⚠${NC} $method $endpoint - $status_code (Not Found - may need ID)"
        echo "WARN: $method $endpoint - $status_code (Not Found)" >> "$RESULTS_FILE"
    elif [ "$status_code" = "422" ]; then
        echo -e "${YELLOW}⚠${NC} $method $endpoint - $status_code (Validation Error - may need data)"
        echo "WARN: $method $endpoint - $status_code (Validation)" >> "$RESULTS_FILE"
    else
        echo -e "${RED}✗${NC} $method $endpoint - $status_code"
        echo "FAIL: $method $endpoint - $status_code" >> "$RESULTS_FILE"
    fi
}

# Step 1: Login to get token
echo "🔐 Step 1: Getting authentication token..."
LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
    -H "Content-Type: application/json" \
    -d '{
        "email": "test.salah@example.com",
        "password": "Asd123456@"
    }')

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo "❌ Failed to get authentication token. Exiting."
    exit 1
fi

echo "✓ Token acquired"
echo ""

# Step 2: Test Public Endpoints
echo "📋 Step 2: Testing Public Endpoints..."
echo "========================================"
test_endpoint "GET" "" false
test_endpoint "GET" "/engines" false
test_endpoint "GET" "/marketplace" false
echo ""

# Step 3: Test Authentication Endpoints
echo "🔐 Step 3: Testing Authentication Endpoints..."
echo "========================================"
test_endpoint "GET" "/auth/me" true
test_endpoint "POST" "/auth/refresh" true
test_endpoint "POST" "/auth/logout" true
echo ""

# Step 4: Test Resource Endpoints (GET)
echo "📊 Step 4: Testing Resource List Endpoints..."
echo "========================================"
test_endpoint "GET" "/users" true
test_endpoint "GET" "/organizations" true
test_endpoint "GET" "/branches" true
test_endpoint "GET" "/departments" true
test_endpoint "GET" "/projects" true
test_endpoint "GET" "/teams" true
test_endpoint "GET" "/agents" true
test_endpoint "GET" "/workflows" true
test_endpoint "GET" "/job-flows" true
test_endpoint "GET" "/hitl-approvals" true
test_endpoint "GET" "/activities" true
test_endpoint "GET" "/sessions" true
test_endpoint "GET" "/roles" true
test_endpoint "GET" "/permissions" true
test_endpoint "GET" "/api-keys" true
test_endpoint "GET" "/webhooks" true
test_endpoint "GET" "/connected-apps" true
test_endpoint "GET" "/subscriptions" true
test_endpoint "GET" "/executions" true
echo ""

# Step 5: Test Analytics Endpoints
echo "📈 Step 5: Testing Analytics Endpoints..."
echo "========================================"
test_endpoint "GET" "/analytics/agents" true
test_endpoint "GET" "/analytics/executions" true
test_endpoint "GET" "/dashboard" true
echo ""

# Step 6: Generate Summary
echo ""
echo "=========================================="
echo "📊 Test Summary"
echo "=========================================="

TOTAL=$(wc -l < "$RESULTS_FILE")
PASS=$(grep -c "^PASS:" "$RESULTS_FILE")
WARN=$(grep -c "^WARN:" "$RESULTS_FILE")
FAIL=$(grep -c "^FAIL:" "$RESULTS_FILE")

echo "Total Endpoints Tested: $TOTAL"
echo -e "${GREEN}Passed: $PASS${NC}"
echo -e "${YELLOW}Warnings: $WARN${NC}"
echo -e "${RED}Failed: $FAIL${NC}"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo "Failed Endpoints:"
    grep "^FAIL:" "$RESULTS_FILE" | sed 's/FAIL: /  - /'
fi

echo ""
echo "Full results saved to: $RESULTS_FILE"

#!/bin/bash

# API Response Test Script
# Tests that API endpoints return JSON, not HTML

API_URL="${1:-http://localhost:20000/api/v1}"

echo "🧪 Testing API JSON Responses"
echo "API URL: $API_URL"
echo "======================================"
echo ""

# Test 1: Valid endpoint with wrong method (should return JSON 405)
echo "Test 1: POST endpoint with GET method (should return JSON 405)"
RESPONSE=$(curl -s -X GET "$API_URL/resend-verification-email")
echo "Response: $RESPONSE"

if echo "$RESPONSE" | grep -q "<html>" || echo "$RESPONSE" | grep -q "<body>"; then
    echo "❌ FAIL: Response is HTML, not JSON"
    exit 1
else
    echo "✅ PASS: Response is JSON"
fi
echo ""

# Test 2: Non-existent endpoint (should return JSON 404)
echo "Test 2: Non-existent endpoint (should return JSON 404)"
RESPONSE=$(curl -s -X POST "$API_URL/non-existent-endpoint")
echo "Response: $RESPONSE"

if echo "$RESPONSE" | grep -q "<html>" || echo "$RESPONSE" | grep -q "<body>"; then
    echo "❌ FAIL: Response is HTML, not JSON"
    exit 1
else
    echo "✅ PASS: Response is JSON"
fi
echo ""

# Test 3: Valid POST to resend verification (should return JSON with user not found or success)
echo "Test 3: POST to resend-verification-email (should return JSON)"
RESPONSE=$(curl -s -X POST "$API_URL/resend-verification-email" \
    -H "Content-Type: application/json" \
    -d '{"email":"test@example.com"}')
echo "Response: $RESPONSE"

if echo "$RESPONSE" | grep -q "<html>" || echo "$RESPONSE" | grep -q "<body>"; then
    echo "❌ FAIL: Response is HTML, not JSON"
    exit 1
else
    echo "✅ PASS: Response is JSON"
fi
echo ""

# Test 4: Verify the response contains expected JSON structure
echo "Test 4: Check JSON structure (should have 'success' field)"
if echo "$RESPONSE" | grep -q '"success"'; then
    echo "✅ PASS: Response has 'success' field"
else
    echo "❌ FAIL: Response missing 'success' field"
    exit 1
fi
echo ""

echo "======================================"
echo "✅ All tests passed!"
echo ""
echo "Summary:"
echo "  - 405 errors return JSON ✓"
echo "  - 404 errors return JSON ✓"
echo "  - Valid requests return JSON ✓"
echo "  - JSON structure is correct ✓"

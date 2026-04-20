# Manual Testing Guide for TRE Healthcheck

This guide provides step-by-step instructions for manually testing the tre_healthcheck module.

## Prerequisites

- Drupal site with tre_healthcheck module installed and enabled
- Command line access (for curl commands)
- OR a REST client like Postman, Insomnia, or browser developer tools

## Setup

### 1. Enable and Configure the Module

1. Navigate to `/admin/config/services/tre_healthcheck`
2. Check "Enable healthcheck endpoint"
3. Add at least one access token in the textarea (one per line), for example:
   ```
   test-token-12345
   another-valid-token
   ```
4. Click "Save configuration"

### 2. Get Your Site URL

For local testing, the site URL is typically `https://tampere.l` or 
`https://tampere.ddev.site` (if using DDEV). Replace `https://tampere.l` in
the commands below with your actual URL.

---

## Test Cases

### Test 1: Valid Token (Query Parameter)

**Purpose:** Verify successful authentication with a valid token in the URL.

**Command:**
```bash
curl -i "https://tampere.l/healthcheck?token=test-token-12345"
```

**Expected Result:**
```
HTTP/1.1 200 OK
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
Content-Type: application/json

{"status":"OK"}
```

---

### Test 2: Valid Token (Header)

**Purpose:** Verify successful authentication with a valid token in the request header.

**Command:**
```bash
curl -i -H "Token: test-token-12345" "https://tampere.l/healthcheck"
```

**Expected Result:**
```
HTTP/1.1 200 OK
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
Content-Type: application/json

{"status":"OK"}
```

---

### Test 3: Invalid Token

**Purpose:** Verify that invalid tokens are rejected.

**Command:**
```bash
curl -i "https://tampere.l/healthcheck?token=wrong-token"
```

**Expected Result:**
```
HTTP/1.1 401 Unauthorized
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
Content-Type: application/json

{"error":"Unauthorized. Access token missing or invalid."}
```

---

### Test 4: Missing Token

**Purpose:** Verify that requests without a token are rejected.

**Command:**
```bash
curl -i "https://tampere.l/healthcheck"
```

**Expected Result:**
```
HTTP/1.1 401 Unauthorized
Content-Type: application/json

{"error":"Unauthorized. Access token missing or invalid."}
```

---

### Test 5: Multiple Valid Tokens

**Purpose:** Verify that all configured tokens work.

**Commands:**
```bash
curl -i "https://tampere.l/healthcheck?token=test-token-12345"
curl -i "https://tampere.l/healthcheck?token=another-valid-token"
```

**Expected Result:**
Both requests should return `200 OK` with `{"status":"OK"}`.

---

### Test 6: Header Takes Precedence

**Purpose:** Verify that when a token is provided in both header and query parameter, the header value is used.

**Command:**
```bash
# Header has valid token, query has invalid token
curl -i -H "Token: test-token-12345" "https://tampere.l/healthcheck?token=wrong-token"
```

**Expected Result:**
```
HTTP/1.1 200 OK
{"status":"OK"}
```

The request succeeds because the valid header token is used.

---

### Test 7: Flood Protection

**Purpose:** Verify that repeated failed authentication attempts are tracked (though not blocked if a valid token is provided).

**Commands:**
```bash
# Make 5 failed attempts
for i in {1..5}; do
  curl -s "https://tampere.l/healthcheck?token=wrong-token" | jq
done

# Now try with valid token
curl -i "https://tampere.l/healthcheck?token=test-token-12345"
```

**Expected Result:**
- All failed attempts return `401 Unauthorized`
- The valid token request still returns `200 OK` (not blocked)

---

### Test 8: Flood Counter Reset

**Purpose:** Verify that successful authentication clears the flood counter.

**Commands:**
```bash
# Make 3 failed attempts
for i in {1..3}; do
  curl -s "https://tampere.l/healthcheck?token=wrong" > /dev/null
done

# Successful request
curl -s "https://tampere.l/healthcheck?token=test-token-12345" | jq

# Make 3 more failed attempts (should not be blocked, counter was reset)
for i in {1..3}; do
  curl -s "https://tampere.l/healthcheck?token=wrong" > /dev/null
done

# Valid token should still work
curl -i "https://tampere.l/healthcheck?token=test-token-12345"
```

**Expected Result:**
The final request returns `200 OK` because the counter was reset by the successful request in the middle.

---

### Test 9: Disabled Endpoint

**Purpose:** Verify that the endpoint returns 503 when disabled.

**Steps:**
1. Go to `/admin/config/services/tre_healthcheck`
2. Uncheck "Enable healthcheck endpoint"
3. Click "Save configuration"

**Command:**
```bash
curl -i "https://tampere.l/healthcheck?token=test-token-12345"
```

**Expected Result:**
```
HTTP/1.1 503 Service Unavailable
Content-Type: application/json

{"error":"Healthcheck service unavailable."}
```

**Cleanup:** Re-enable the endpoint for further testing.

---

### Test 10: Cache Headers

**Purpose:** Verify that responses include proper no-cache headers.

**Command:**
```bash
curl -i "https://tampere.l/healthcheck?token=test-token-12345" | grep -E "(Cache-Control|Pragma|Expires)"
```

**Expected Result:**
```
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
```

These headers should be present on ALL responses (200, 401, 503).

---

## Testing with settings.php Overrides

### Test 11: Tokens from settings.php

**Purpose:** Verify that tokens can be provided via settings.php instead of the UI.

**Steps:**
1. Add to your `settings.php` or `settings.local.php`:
   ```php
   $config['tre_healthcheck.settings']['ping_auth_tokens'] = [
     'settings-php-token-xyz',
   ];
   ```
2. Clear cache: `drush cr`
3. In the admin UI, clear the access tokens textarea and save (leave it empty)

**Command:**
```bash
curl -i "https://tampere.l/healthcheck?token=settings-php-token-xyz"
```

**Expected Result:**
```
HTTP/1.1 200 OK
{"status":"OK"}
```

---

## Browser Testing

You can also test using a web browser:

1. **Successful request:**  
   Visit: `https://tampere.l/healthcheck?token=test-token-12345`  
   Should display: `{"status":"OK"}`

2. **Failed request:**  
   Visit: `https://tampere.l/healthcheck?token=wrong`  
   Should display: `{"error":"Unauthorized. Access token missing or invalid."}`

3. **Check response headers:**  
   - Open browser DevTools (F12)
   - Go to Network tab
   - Visit the healthcheck URL
   - Click on the request
   - Check Response Headers for Cache-Control, Pragma, and Expires

---

## Troubleshooting

### 404 Not Found
- Verify the module is enabled: `drush en tre_healthcheck`
- Clear cache: `drush cr`
- Check routes: `drush route:debug /healthcheck`

### 403 Forbidden
- Check permissions - anonymous users need "Access content" permission
- Verify no web server rules are blocking the path

### All Requests Return 401
- Verify tokens are configured correctly
- Check for whitespace in tokens
- Try viewing the config: `drush cget tre_healthcheck.settings`

### 503 Service Unavailable
- Check that "Enable healthcheck endpoint" is checked in config
- Verify: `drush cget tre_healthcheck.settings enabled` returns `true`

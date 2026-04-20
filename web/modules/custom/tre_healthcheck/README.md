# TRE Healthcheck

A Drupal 10+ module that provides a simple, secure healthcheck endpoint for monitoring systems to verify site availability.

## Features

- Simple JSON healthcheck endpoint at `/healthcheck`
- Token-based authentication via URL parameters
- Flood protection against brute-force attempts
- No caching to ensure real-time status
- Enable/disable functionality via admin UI
- Multiple access tokens support

## Requirements

- Drupal 10 or higher

## Installation

1. Place the module in your `modules/custom` directory
2. Enable the module: `drush en tre_healthcheck`
3. Configure the module at `/admin/config/services/tre_healthcheck`

## Configuration

Navigate to **Configuration** > **System** > **Healthcheck ping reply settings** (`/admin/config/services/tre_healthcheck`) to configure:

- **Enable healthcheck endpoint**: Check to activate the `/healthcheck` endpoint
- **Access tokens**: Enter one or more access tokens (one per line).

## Usage

### Basic Request

Send a GET request to the healthcheck endpoint with your token as a URL parameter:

```bash
curl "https://example.com/healthcheck?token=YOUR_ACCESS_TOKEN_HERE"
```

Alternatively, you can provide the access token in a 'Token' header in your request.

### Successful Response

```json
{
  "status": "OK"
}
```

HTTP Status: `200 OK`

### Error Responses

**Endpoint Disabled:**
```json
{
  "error": "Healthcheck service unavailable."
}
```
HTTP Status: `503 Service Unavailable`

**Missing or Invalid Token:**
```json
{
  "error": "Unauthorized. Access token missing or invalid."
}
```
HTTP Status: `401 Unauthorized`

**Too Many Failed Attempts:**
```json
{
  "error": "Request blocked. Too many unauthorized requests."
}
```
HTTP Status: `403 Forbidden`

## Security

### Token Requirements
- Tokens can be passed in a 'Token' header in the request, or alternatively as a 'token' URL parameter for compatibility with simple monitoring tools

### Flood Protection
- After 5 failed authentication attempts from the same IP address, all requests from that IP are blocked for 10 minutes
- Successful authentication clears the failure counter

## Integration Examples

### Load Balancer Health Check
Most load balancers can be configured to use this endpoint:

**AWS Application Load Balancer:**
- Path: `/healthcheck?token=YOUR_TOKEN`
- Success codes: `200`
- Interval: 30 seconds

**HAProxy:**
```
option httpchk GET /healthcheck?token=YOUR_TOKEN
http-check expect status 200
```

## Troubleshooting

### 503 Service Unavailable
- Check that the module is enabled in configuration
- Verify the "Enable healthcheck endpoint" checkbox is selected

### 401 Unauthorized
- Verify the token in the URL matches one configured in the admin UI
- Check for extra whitespace in configured tokens

### 403 Forbidden (Blocked)
- Wait 10 minutes for the block to expire
- Clear the cache: `drush cr`
- Check that you're using the correct token

### Response is Cached
- The module sets explicit no-cache headers
- Verify Varnish/CDN configuration isn't overriding cache headers
- Check that the response includes `Cache-Control: no-cache, no-store, must-revalidate`
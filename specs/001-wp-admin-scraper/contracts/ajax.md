# Background Scraper Client-Server AJAX Contract

Below defines the AJAX actions and payloads used between the WP Admin UI and the server-side runner.

### Action: `toc_validate_env`
- **Request**: `{ action: 'toc_validate_env', nonce: [WP_NONCE] }`
- **Response** (Success): `{ success: true, valid: true, message: 'Environment looks good' }`
- **Response** (Fail): `{ success: false, valid: false, message: 'Missing .venv...' }`

### Action: `toc_start_scraper`
- **Request**: `{ action: 'toc_start_scraper', arguments: { url: string, year: string, month: string }, nonce: [...] }`
- **Response**: `{ success: true, run_id: "unique-hash-uuid123" }`

### Action: `toc_poll_progress`
- **Request**: `{ action: 'toc_poll_progress', run_id: "unique-hash-uuid123", nonce: [...] }`
- **Response**: `{ success: true, status: 'running|completed|failed', output: [Array of log lines since last poll or full log string] }`

# Documentation: Run Scraper From WP Admin

## Overview
The WP Admin Scraper feature allows administrators to trigger the Taste of Cinema Python scraper directly from the WordPress dashboard. It eliminates the need for terminal access when importing individual articles or bulk batches by year/month.

## Location in Admin
Navigate to **Taste of Cinema Settings** > **Run Scraper**.

---

## 1. Prerequisites (Setup)
The system follows a **fail-fast** principle regarding the Python environment. It does not auto-install dependencies to ensure server stability and security.

*   **Virtual Environment**: A folder named `.venv` must exist inside `tasteofcinemascraped/`.
*   **Requirements**: All Python packages (`bs4`, `requests`, etc.) must be pre-installed in that environment.
*   **Permissions**: The web server user (e.g., `www-data`) must have execute permissions for the python binary and write permissions for the `/tmp` directory.

If the environment is missing, the UI will display a red error message and disable the execution buttons.

---

## 2. Features

### A. Run by Specific URL
*   **Usage**: Input a valid Taste of Cinema article URL.
*   **Behavior**: Spawns a background process that imports that specific article as a draft.
*   **Use Case**: Manual import of trending or specific requested reviews.

### B. Run Batch Import
*   **Usage**: Provide a **Year** (mandatory) and a **Month** (optional).
*   **Behavior**: The scraper will iterate through the site's archives for that period.
*   **Use Case**: Initial site seeding or monthly synchronization.

---

## 3. Technical Architecture

### Asynchronous Execution
Processing a batch can take minutes or hours. To prevent PHP timeouts (`max_execution_time`), the system:
1.  Initiates an AJAX request.
2.  PHP spawns a background shell process (`exec` with `&`) that writes its output to a temporary log file in `/tmp`.
3.  PHP immediately returns a `run_id` to the browser.

### Real-time Polling
The browser uses the `run_id` to poll the server every 2 seconds via AJAX. 
*   The server reads the content of the temporary log file.
*   The log is streamed back to the UI "Terminal" window.
*   The browser automatically scrolls to the bottom of the logs.

---

## 4. Security
*   **Capability Check**: Only users with the `manage_options` capability can access the page or trigger the API.
*   **Nonces**: All AJAX actions are secured with a WordPress Nonce (`toc_scraper_nonce`) to prevent CSRF attacks.
*   **Input Sanitization**: All inputs (URL, Year, Month) are strictly sanitized and escaped before being passed to the shell command.

---

## 5. Troubleshooting

### Process Hangs
If a process seems stuck:
*   Check server resource usage (CPU/RAM).
*   Ensure the Python script isn't waiting for user input (it is called with `-u` for unbuffered output).
*   Temporary logs are stored in `sys_get_temp_dir()` (usually `/tmp/toc_run_*.log`).

### Missing Module Errors
If the log shows `ModuleNotFoundError`:
*   The `.venv` might be activated but missing a specific package.
*   Run `pip install -r requirements.txt` manually on the server inside the plugin's python directory.

### UI Validation Error
If the status shows "Missing .venv":
*   Verify the path: `wp-content/plugins/tasteofcinemascraped-wp/tasteofcinemascraped/.venv/bin/python3`
*   Ensure the file is readable by PHP.

# Data Model

### Configuration

- **Option Name**: `toc_scraper_environment_valid`
  - **Type**: Boolean
  - **Purpose**: Caches the result of checking if `.venv` and Python dependencies exist. 

### Scraper Execution Object
Note: This is an internal representation of the state returned to the UI by the AJAX endpoints. Do not create a separate custom database table.

- **run_id**: String (Unique Identifier for a run)
- **status**: Enum (`starting`, `running`, `completed`, `failed`)
- **log_file**: String (Path to temporary log output of this run)
- **parameters**: Object containing URL, Year, Month.

Data transition rules:
- State begins `starting`, immediately transitions to `running`.
- Updates logs iteratively until python exit code retrieved.
- Final state becomes `completed` (exit code 0) or `failed` (exit code > 0).

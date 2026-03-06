# Quickstart: Dual Site Publishing

## Setup

1. **Remote Site Configuration**:
   - Log into the remote WordPress installation (`https://tasteofcinemaarabi.com`).
   - Navigate to **Users > Profile**.
   - Scroll down to the **Application Passwords** section.
   - Enter a name (e.g., "TasteOfCinema Scraper Sync") and click **Add New Application Password**.
   - Copy the generated password.

2. **Local Site Configuration**:
   - Open the `.env` file for the Taste of Cinema Scraped plugin.
   - Add the following variables:
     ```env
     REMOTE_WP_URL=https://tasteofcinemaarabi.com
     REMOTE_WP_USER=your_remote_username
     REMOTE_WP_APP_PASSWORD=the_copied_application_password
     ```

## Usage

The process is entirely automated. When a post is scraped, translated, and passes the translation evaluation stage on the local environment, the system will instantly push it to the remote URL. 

If the remote publish fails (e.g., network error), WP Cron will attempt to retry the action automatically every 15 minutes. No manual intervention is required for dual-publishing.

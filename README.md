# portalium-workspace
Module Workspace for Portalium

## Expired Invitation Cron Agent

This module provides a dedicated API identity for automatically rejecting expired
workspace invitations. The migration creates:

- User: `workspace_cron_agent`
- Role: `workspace_cron_agent`
- Permission: `workspaceApiInvitationExpireCron`

The role is assigned to the user and contains only the invitation-expiration
permission. Use this user's `access_token` for the cron job. Do not use an
administrator token.

Below is an guide to example cron job on Cpanel that fires evey day
### 1. Obtain the cron user's token

An administrator can retrieve the token from the `user_user` table by querying
the `workspace_cron_agent` username or using any database managment tool like phpMyAdmin.
Treat this token like a password and never commit it to source control.


### 2. Configure the cron environment

Create a `.env` file in the same directory as the cron script. Do not put spaces
around the `=` character:

```dotenv
WORKSPACE_CRON_ACCESS_TOKEN=replace_with_workspace_cron_agent_access_token
WORKSPACE_CRON_LOG_PATH=path_to_your_logs_file
WORKSPACE_CRON_CAN_LOG=true
CRON_REJECT_EXPIRED_INVITATION_ENDPOINT_URL=https://example.com/api/workspace/invitation/reject-expired
```
Create a `reject_expired_invitations.bash` file in the same directory as the env.
Add the following contents:

```bash
#!/bin/bash

SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

source "$SCRIPT_DIR/.env"

if [ -z "$WORKSPACE_CRON_CAN_LOG" ] || \
   [ -z "$WORKSPACE_CRON_ACCESS_TOKEN" ] || \
   [ -z "$WORKSPACE_CRON_LOG_PATH" ] || \
   [ -z "$CRON_REJECT_EXPIRED_INVITATION_ENDPOINT_URL" ]; then
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: required values are missing from .env" >&2
	exit 1
fi

LOG_FILE="$SCRIPT_DIR/$WORKSPACE_CRON_LOG_PATH"
mkdir -p "$(dirname "$LOG_FILE")"

if [ "$WORKSPACE_CRON_CAN_LOG" = "true" ]; then
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting reject-expired run" \
		>> "$LOG_FILE"
fi

RESPONSE=$(curl -s -X POST \
	-H "Authorization: Bearer $WORKSPACE_CRON_ACCESS_TOKEN" \
	-H "Accept: application/json" \
	-w "\nHTTP_STATUS:%{http_code}" \
	"$CRON_REJECT_EXPIRED_INVITATION_ENDPOINT_URL")

if [ "$WORKSPACE_CRON_CAN_LOG" = "true" ]; then
	echo "[$(date '+%Y-%m-%d %H:%M:%S')] Response: $RESPONSE" \
		>> "$LOG_FILE"
	echo "---" >> "$LOG_FILE"
fi

if [[ "$RESPONSE" != *"HTTP_STATUS:200"* ]]; then
    echo "reject_expired_invitations.bash failed: $RESPONSE" >&2
fi
```

The script reads the token from `.env`, sends a `POST` request with Bearer
authentication, creates the configured log directory when needed, and appends
the API response to the log file.

The API URL must use the host and API base path of the installation. The
`reject-expired` endpoint requires a `POST` request.

### 3. Configure cPanel Cron Jobs

Upload the cron script and `.env` outside `public_html` . Make the
script executable by giving permisson from Cpanel ui or with a command:
(this is a example, dont forget replacing the path with your actual bash script path)

```bash
chmod 700 /home/CPANEL_USER/scripts/reject_expired_invitations.bash
chmod 600 /home/CPANEL_USER/scripts/.env
```

You can give a email which send any console output from script into that mail.

Configure the schedule by how frequent you want script to run
for once a day use : 0 0 * * * (every midnight)

Use this command in cPanel Cron Jobs, replacing the path with your actual bash script path 

```bash
/bin/bash /home/CPANEL_USER/scripts/reject_expired_invitations.bash
```

If required environment variables are missing, the script exits with status `1`
and sends the error into mail. Normal responses are appended
to the configured log file. The log directory is created automatically and
existing log files are preserved. cPanel can also email the cron output if you
configure an email address in the Cron Jobs interface.


